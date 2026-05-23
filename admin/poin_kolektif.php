<?php
/**
 * poin_kolektif.php
 * — Input Kolektif (Multi-Kelas + Cari Item + Tema Dinamis + Modal Rincian)
 */

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';

$TIMEZONE = 'Asia/Jakarta';
$FALLBACK_USER_ID = 1;

date_default_timezone_set($TIMEZONE);
if (defined('APP_ENV') && APP_ENV === 'production') {
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
}

$bootDbError = null;
try {
  $pdo = epoin_get_pdo();
} catch (Throwable $e) {
  if (($_GET['action'] ?? $_POST['action'] ?? null) !== null) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Gagal konek DB.']);
    exit;
  }
  $bootDbError = 'Gagal konek DB.';
}

/* ---------------- helpers (tetap + fetch_logs) ---------------- */
function json_response($data, int $status = 200){ http_response_code($status); header('Content-Type: application/json'); echo json_encode($data); exit; }
function post($key,$default=null){ if(isset($_POST[$key]))return $_POST[$key]; $in=file_get_contents('php://input'); if($in){$j=json_decode($in,true); if(json_last_error()===JSON_ERROR_NONE && isset($j[$key])) return $j[$key];} return $default; }
function uid(int $fallback): int { return (int)($_SESSION['user_id'] ?? $fallback); }
function uname(PDO $pdo,int $id): string { $st=$pdo->prepare('SELECT user_nama FROM `user` WHERE user_id=?'); $st->execute([$id]); return $st->fetchColumn() ?: 'Pengguna'; }
function item_label(PDO $pdo,string $tipe,int $id): string { $st=$pdo->prepare($tipe==='pelanggaran'?'SELECT pelanggaran_nama FROM pelanggaran WHERE pelanggaran_id=?':'SELECT prestasi_nama FROM prestasi WHERE prestasi_id=?'); $st->execute([$id]); return ($st->fetchColumn()) ?: ''; }

function guru_name(PDO $pdo,int $guru_id): string {
  $cols = ['guru_nama','nama_guru','nama'];
  foreach($cols as $col){
    try {
      $st = $pdo->prepare("SELECT $col FROM guru WHERE guru_id=? LIMIT 1");
      $st->execute([$guru_id]);
      $val = $st->fetchColumn();
      if($val){ return (string)$val; }
    } catch (Throwable $e) {}
  }
  return '';
}

function resolve_actor(PDO $pdo, int $fallback_id): array {
  $sess = $_SESSION ?? [];
  $userIdKeys = ['user_id','id_user','uid','id','userid'];
  $guruIdKeys = ['guru_id','id_guru','gid','guru'];
  $nameKeys   = ['nama_guru','guru_nama','user_nama','nama','name','fullname'];

  $user_id = null;
  foreach($userIdKeys as $k){ if(isset($sess[$k]) && (int)$sess[$k] > 0){ $user_id = (int)$sess[$k]; break; } }
  $guru_id = null;
  foreach($guruIdKeys as $k){ if(isset($sess[$k]) && (int)$sess[$k] > 0){ $guru_id = (int)$sess[$k]; if(!$user_id) $user_id = $guru_id; break; } }

  $nama = '';
  foreach($nameKeys as $k){ if(!empty($sess[$k])){ $nama = trim((string)$sess[$k]); if($nama !== '') break; } }
  if($nama === '' && $guru_id){ $nama = guru_name($pdo, $guru_id); }
  if($nama === '' && $user_id){ $nama = uname($pdo, $user_id); }
  if(!$user_id){ $user_id = $fallback_id; }
  if($nama === ''){ $nama = 'Pengguna'; }
  return ['id'=>$user_id, 'nama'=>$nama];
}

/* ---------------- API (tetap + TAMBAHAN: fetch_logs) ---------------- */
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
  try {
    if (!isset($pdo)) throw new Exception('Koneksi DB belum siap.');
    epoin_staff_guard_json();
    if (!epoin_csrf_validate($_POST)) {
      json_response(['ok' => false, 'error' => 'CSRF token tidak valid'], 403);
    }
    switch ($action) {

      case 'fetch_classes': {
        $rows = $pdo->query('SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama ASC')->fetchAll();
        json_response(['ok'=>true,'data'=>$rows]);
      }

      case 'fetch_students_multi': {
        $kelas_ids = post('kelas_ids');
        if (!is_array($kelas_ids) || count($kelas_ids)===0) json_response(['ok'=>true,'data'=>[]]);
        $ids = array_values(array_unique(array_map('intval',$kelas_ids)));
        $in  = implode(',', array_fill(0,count($ids),'?'));
        $sql = "SELECT s.siswa_id, s.siswa_nama, k.kelas_id, k.kelas_nama
                FROM kelas_siswa ks
                JOIN siswa s ON s.siswa_id = ks.ks_siswa
                JOIN kelas k ON k.kelas_id = ks.ks_kelas
                WHERE ks.ks_kelas IN ($in)
                ORDER BY k.kelas_nama ASC, s.siswa_nama ASC";
        $st=$pdo->prepare($sql); $st->execute($ids);
        json_response(['ok'=>true,'data'=>$st->fetchAll()]);
      }

      case 'fetch_items': {
        $tipe = strtolower(trim((string)post('tipe','pelanggaran')));
        $q    = trim((string)post('q',''));
        if(!in_array($tipe,['pelanggaran','prestasi'],true)) json_response(['ok'=>false,'error'=>'tipe harus pelanggaran|prestasi'],400);
        if ($tipe==='pelanggaran') {
          $sql='SELECT pelanggaran_id AS id, pelanggaran_nama AS nama, pelanggaran_point AS poin FROM pelanggaran';
        } else {
          $sql='SELECT prestasi_id AS id, prestasi_nama AS nama, prestasi_point AS poin FROM prestasi';
        }
        $params=[];
        if($q!==''){ $sql.=' WHERE '.($tipe==='pelanggaran'?'pelanggaran_nama LIKE ?':'prestasi_nama LIKE ?'); $params[]='%'.$q.'%'; }
        $sql.=' ORDER BY poin ASC, nama ASC LIMIT 150';
        $st=$pdo->prepare($sql); $st->execute($params);
        json_response(['ok'=>true,'data'=>$st->fetchAll()]);
      }

      case 'save_bulk': {
        $tipe      = strtolower(trim((string)post('tipe')));
        $item_id   = (int)post('item_id');
        $waktu     = post('waktu') ?: date('Y-m-d H:i:s');
        $pairs     = post('pairs');
        $kelas_id  = (int)post('kelas_id');
        $siswa_ids = post('siswa_ids');

        if(!in_array($tipe,['pelanggaran','prestasi'],true)) json_response(['ok'=>false,'error'=>'tipe harus pelanggaran|prestasi'],400);
        if($item_id<=0) json_response(['ok'=>false,'error'=>'item_id wajib'],400);

        $actor     = resolve_actor($pdo, $GLOBALS['FALLBACK_USER_ID']);
        $user_id   = $actor['id'];
        $user_nama = $actor['nama'];

        $label     = item_label($pdo,$tipe,$item_id);

        $rows=[];
        if(is_array($pairs) && count($pairs)>0){
          foreach($pairs as $p){
            if(strpos($p,':')===false) continue;
            list($sid,$kid)=array_map('intval',explode(':',$p,2));
            if($sid>0 && $kid>0){ $rows[]=['sid'=>$sid,'kid'=>$kid]; }
          }
        } else {
          if($kelas_id<=0 || !is_array($siswa_ids) || count($siswa_ids)===0) json_response(['ok'=>false,'error'=>'kelas_id & siswa_ids wajib (atau gunakan pairs[])'],400);
          foreach($siswa_ids as $sid){ $sid=(int)$sid; if($sid>0){ $rows[]=['sid'=>$sid,'kid'=>$kelas_id]; }}
        }
        if(count($rows)===0) json_response(['ok'=>false,'error'=>'Tidak ada data untuk disimpan'],400);

        $chkKs = $pdo->prepare('SELECT 1 FROM kelas_siswa WHERE ks_siswa = ? AND ks_kelas = ? LIMIT 1');
        foreach ($rows as $r) {
          $chkKs->execute([$r['sid'], $r['kid']]);
          if (!$chkKs->fetchColumn()) {
            json_response(['ok'=>false,'error'=>'Siswa tidak terdaftar di kelas yang dipilih (ID '.$r['sid'].').'], 400);
          }
        }

        $pdo->beginTransaction();
        $sql = ($tipe==='pelanggaran')
          ? 'INSERT INTO input_pelanggaran (waktu, siswa, kelas, pelanggaran) VALUES (?,?,?,?)'
          : 'INSERT INTO input_prestasi    (waktu, siswa, kelas, prestasi)    VALUES (?,?,?,?)';
        $ins=$pdo->prepare($sql);
        $log=$pdo->prepare('INSERT INTO log_aktivitas (user_id, nama_guru, aktivitas, waktu) VALUES (?,?,?,NOW())');
        $si =$pdo->prepare('SELECT siswa_nama FROM siswa WHERE siswa_id=? LIMIT 1');

        $done=0;
        foreach($rows as $r){
          $ins->execute([$waktu, $r['sid'], $r['kid'], $item_id]);
          $si->execute([$r['sid']]); $sn=$si->fetchColumn();
          $aksi = ($tipe==='pelanggaran' ? "Input pelanggaran '" : "Input prestasi '")
                . $label . "' untuk siswa " . ($sn ?: ('ID#'.$r['sid']))
                . " (Kelas#".$r['kid'].") oleh " . $user_nama;
          $log->execute([$user_id,$user_nama,$aksi]);
          $done++;
        }
        $pdo->commit();
        json_response(['ok'=>true,'inserted'=>$done,'message'=>"Berhasil menyimpan $done data ($tipe) secara kolektif."]);
      }

      /* ===== TAMBAHAN: Log Aktivitas ===== */
      case 'fetch_logs': {
        $q        = trim((string)post('q',''));
        $range    = trim((string)post('range','7d')); // today|7d|30d|all|custom
        $from     = trim((string)post('date_from',''));
        $to       = trim((string)post('date_to',''));
        $page     = max(1,(int)post('page',1));
        $per_page = (int)post('per_page',15);
        if($per_page<5) $per_page=5; if($per_page>100) $per_page=100;
        $offset   = ($page-1)*$per_page;

        $where=[]; $params=[];
        if($q!==''){ $where[]='(nama_guru LIKE ? OR aktivitas LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
        if($range==='today'){
          $where[]='DATE(waktu)=CURDATE()';
        } elseif($range==='7d'){
          $where[]='waktu >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif($range==='30d'){
          $where[]='waktu >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif($range==='custom'){
          if($from!==''){ $where[]='waktu >= ?'; $params[]=$from.' 00:00:00'; }
          if($to!==''){ $where[]='waktu <= ?'; $params[]=$to.' 23:59:59'; }
        } elseif($range==='all'){
          // tanpa filter waktu
        } else {
          $where[]='waktu >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        }

        $whereSql = count($where)? ('WHERE '.implode(' AND ', $where)) : '';
        $stc = $pdo->prepare("SELECT COUNT(*) FROM log_aktivitas $whereSql");
        $stc->execute($params);
        $total = (int)$stc->fetchColumn();

        $sql = "SELECT user_id, nama_guru, aktivitas, waktu
                FROM log_aktivitas $whereSql
                ORDER BY waktu DESC
                LIMIT ".intval($per_page)." OFFSET ".intval($offset);
        $std = $pdo->prepare($sql);
        $std->execute($params);
        $rows = $std->fetchAll();

        json_response([
          'ok'=>true,
          'data'=>$rows,
          'page'=>$page,
          'per_page'=>$per_page,
          'total'=>$total,
          'has_more'=> ($offset + $per_page) < $total,
          'server_time'=> date('Y-m-d H:i:s')
        ]);
      }

      default: json_response(['ok'=>false,'error'=>'Unknown action'],404);
    }
  } catch(Throwable $e){ if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); json_response(['ok'=>false,'error'=>$e->getMessage()],500); }
}

$__header = __DIR__ . '/header.php';
if (is_file($__header)) { include $__header; }
?>
<script>window.EPOIN_CSRF = <?= json_encode(epoin_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<!-- ===================== CONTENT WRAPPER ===================== -->
<div class="content-wrapper">
  <section class="content-header">
    <h1 class="page-title-compact title-wrap">
      <span class="hero-ico" id="titleIcon"><i class="fa fa-exclamation-triangle"></i></span>
      <span>Input Kolektif</span>
      <small id="subBadge" class="sub-badge badge-mode-red">Mode: Pelanggaran</small>
    </h1>
    <ol class="breadcrumb hidden-xs">
      <li><a href="index.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li class="active">Poin Kolektif</li>
    </ol>
  </section>

  <section class="content">

    <div class="row stack-mobile">
      <!-- Kolom kiri -->
      <div class="col-md-7 col-sm-12">
        <div id="boxLeft" class="box box-solid box-danger radius-12">
          <div class="box-header with-border radius-12-top">
            <h3 class="box-title">
              <i class="fa fa-list-check"></i> 1) Pilih Tipe, Kelas (multi), & Item
            </h3>
            <span class="pull-right hidden-xs badge badge-soft">
              <i class="fa fa-clock-o"></i> <span id="timeBadge"></span>
            </span>
          </div>
          <div class="box-body">

            <div class="row mb-10">
              <div class="col-xs-12 col-sm-6 col-md-5 col-lg-4">
                <label class="text-muted">Tipe</label><br>
                <div id="tipeToggle" class="btn-group btn-group-justified mobile-seg" role="tablist" aria-label="Pilih tipe">
                  <a class="btn btn-danger active btn-neo" data-tipe="pelanggaran" aria-selected="true">
                    <i class="fa fa-exclamation-triangle"></i> <span class="lbl">Pelanggaran</span>
                  </a>
                  <a class="btn btn-success btn-neo" data-tipe="prestasi" aria-selected="false">
                    <i class="fa fa-trophy"></i> <span class="lbl">Prestasi</span>
                  </a>
                </div>
              </div>

              <div class="col-xs-12 col-sm-6 col-md-7 col-lg-4">
                <label class="text-muted">Tanggal & Waktu</label>
                <input type="datetime-local" id="waktu" class="form-control">
              </div>
            </div>

            <div class="row mt-4">
              <div class="col-xs-12 col-sm-6">
                <label class="text-muted">Kelas (multi)</label>
                <div class="dropdown pos-rel">
                  <div id="kelasBox" class="form-control tokenbox">
                    <input id="kelasSearch" class="token-input" placeholder="Cari / pilih kelas..." />
                  </div>
                  <div id="kelasMenu" class="dropdown-menu dm-wide"></div>
                </div>
              </div>
              <div class="col-xs-12 col-sm-6">
                <label class="text-muted">Item (cari pelanggaran / prestasi)</label>
                <div id="itemCombo" class="dropdown pos-rel">
                  <input id="itemSearch" class="form-control" placeholder="Ketik nama item..." autocomplete="off">
                  <div id="itemMenu" class="dropdown-menu dm-wide"></div>
                </div>
              </div>
            </div>

            <div class="row mt-12">
              <div class="col-xs-12">
                <div class="box radius-10">
                  <div class="box-header with-border radius-10-top sticky-toolbar">
                    <div class="row row-tight">
                      <div class="col-xs-12 col-sm-6 mb-6-mobile">
                        <div class="input-group">
                          <span class="input-group-addon"><i class="fa fa-search"></i></span>
                          <input id="search" class="form-control" placeholder="Cari siswa (nama)...">
                        </div>
                      </div>
                      <div class="col-xs-7 col-sm-3 mb-6-mobile">
                        <button id="toggleAll" class="btn btn-default btn-block btn-neo-soft" title="Pilih semua hasil pencarian">
                          <i class="fa fa-check-double text-primary"></i> Pilih Semua
                        </button>
                      </div>
                      <div class="col-xs-5 col-sm-3 text-right">
                        <span class="label label-info label-pill">
                          <i class="fa fa-users"></i> <span id="checkedCount">0</span>dipilih
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="box-body pt-8">
                    <div id="students" class="students-scroll"></div>
                    <div id="legend" class="chips-wrap"></div>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /.box-body -->
        </div>
      </div>

      <!-- Kolom kanan -->
      <div class="col-md-5 col-sm-12">
        <div class="box box-solid box-default radius-12 sticky-side side-danger">
          <div class="box-header with-border radius-12-top">
            <h3 class="box-title"><i class="fa fa-floppy-o"></i> 2) Pratinjau & Simpan</h3>
          </div>
          <div class="box-body">
            <label class="text-muted">Ringkasan</label>
            <div id="summary" class="chips-wrap"></div>

            <div class="row mt-12">
              <div class="col-xs-12 col-sm-7 mb-8-mobile">
                <button id="submitBtn" class="btn btn-primary btn-block btn-lg shimmer-off cta-themed btn-neo-cta">
                  <i class="fa fa-paper-plane"></i> Simpan Input Kolektif
                </button>
              </div>
              <div class="col-xs-12 col-sm-5">
                <button id="resetBtn" class="btn btn-default btn-block btn-lg btn-neo-reset">
                  <i class="fa fa-undo" id="resetIcon"></i> Reset
                </button>
              </div>
              <div class="col-xs-12 mt-6">
                <span id="hint" class="label label-default label-pill">
                  <i class="fa fa-info-circle"></i> Data disimpan per siswa (1 transaksi).
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel info tema aktif -->
        <div class="box box-solid radius-12" id="boxTema">
          <div class="box-body d-flex">
            <span class="dot" id="temaDot"></span>
            <span id="temaLabel" class="text-muted">Mode: Pelanggaran</span>
          </div>
        </div>

        <!-- ====== PANEL LOG AKTIVITAS ====== -->
        <div class="box box-solid radius-12" id="boxLog">
          <div class="box-header with-border radius-12-top log-header">
            <h3 class="box-title">
              <i class="fa fa-history"></i> Log Aktivitas
              <small class="badge badge-live" id="logLiveBadge"><span class="live-dot"></span> Live</small>
            </h3>
            <div class="pull-right hidden-xs log-toolbar-right">
              <button id="logRefresh" class="btn btn-default btn-xs btn-neo-soft"><i class="fa fa-refresh"></i> Refresh</button>
              <label class="switch small" title="Auto refresh">
                <input type="checkbox" id="logAuto"><span class="slider"></span>
              </label>
            </div>
          </div>
          <div class="box-body">
            <div class="log-toolbar row row-tight">
              <div class="col-xs-12 col-sm-6 mb-6-mobile">
                <div class="input-group">
                  <span class="input-group-addon"><i class="fa fa-search"></i></span>
                  <input id="logSearch" class="form-control" placeholder="Cari nama guru / aktivitas...">
                </div>
              </div>
              <div class="col-xs-12 col-sm-6">
                <div class="row row-tight log-top-right" style="display:flex;align-items:center;gap:8px;">
                  <div class="col-xs-6">
                    <select id="logRange" class="form-control">
                      <option value="today">Hari ini</option>
                      <option value="7d" selected>7 hari</option>
                      <option value="30d">30 hari</option>
                      <option value="all">Semua</option>
                      <option value="custom">Kustom...</option>
                    </select>
                  </div>
                  <div class="col-xs-6 text-right">
                    <!-- Ekspor: lebih kecil, soft orange, rapi -->
                    <button id="logExport" class="btn btn-default btn-neo-soft btn-compact btn-orange-soft"><i class="fa fa-download"></i> Ekspor CSV</button>
                  </div>
                </div>
              </div>

              <div class="col-xs-12 mt-6" id="logCustomRow" style="display:none;">
                <div class="row row-tight log-custom-row">
                  <div class="col-xs-6">
                    <input type="date" id="logFrom" class="form-control input-compact" />
                  </div>
                  <div class="col-xs-6">
                    <input type="date" id="logTo" class="form-control input-compact" />
                  </div>
                </div>
              </div>
            </div>

            <div id="logSummaryChips" class="chips-wrap mt-6"></div>

            <div id="logList" class="log-list mt-6"></div>

            <div class="mt-10 text-center">
              <button id="logMore" class="btn btn-default btn-neo-soft" style="display:none;">
                <i class="fa fa-ellipsis-h"></i> Muat Lagi
              </button>
              <div id="logEmpty" class="text-muted" style="display:none;">
                <i class="fa fa-inbox"></i> Tidak ada data untuk ditampilkan.
              </div>
            </div>
          </div>
        </div>
        <!-- ====== /PANEL LOG ====== -->

      </div>
    </div>

    <!-- Modal Rincian -->
    <div id="resultModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="resultLabel">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content radius-12">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="resultLabel"><i class="fa fa-circle-info text-primary"></i> Rincian Input Kolektif</h4>
          </div>
          <div class="modal-body">
            <div id="resultSummary" class="chips-wrap"></div>
            <div class="table-responsive" style="max-height:60vh; overflow:auto;">
              <table class="table table-striped table-bordered" id="resultTable">
                <thead>
                  <tr>
                    <th style="width:60px;">#</th>
                    <th>Nama Siswa</th>
                    <th style="width:180px;">Kelas</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button id="closeModal" type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
          </div>
        </div>
      </div>
    </div>

    <div id="toast" class="toast-area"></div>

  </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<style>
  /* ===== Root theme var (font ikut bawaan) ===== */
  .content-wrapper { font-family: inherit; }
  .title-wrap{ display:flex; align-items:center; gap:10px; }
  .hero-ico{
    width:42px;height:42px;border-radius:12px;display:inline-grid;place-items:center;
    color:#fff;background:linear-gradient(135deg,#ef4444,#fb7185);
    box-shadow:0 10px 22px rgba(239,68,68,.25);
    position:relative; overflow:hidden; animation:pop 380ms ease-out both;
  }
  .hero-ico i{font-size:20px;line-height:1}
  @keyframes pop{from{transform:scale(.9);opacity:0}to{transform:scale(1);opacity:1}}

  /* Badge sub-judul */
  .sub-badge{ margin-left:8px; padding:6px 10px; border-radius:999px; font-weight:800; font-size:12px; letter-spacing:.2px; border:1px solid transparent; }
  .badge-mode-red{ background:linear-gradient(90deg,#fee2e2,#fff); color:#7f1d1d; border-color:#fecaca; }
  .badge-mode-green{ background:linear-gradient(90deg,#dcfce7,#fff); color:#065f46; border-color:#bbf7d0; }

  /* Card radius */
  .radius-12{border-radius:12px;}
  .radius-12-top{border-top-left-radius:12px;border-top-right-radius:12px;}
  .radius-10{border-radius:10px;}
  .mb-10{margin-bottom:10px}
  .mt-4{margin-top:4px}
  .mt-6{margin-top:6px}
  .mt-10{margin-top:10px}
  .mt-12{margin-top:12px}
  .pt-8{padding-top:8px}
  .row-tight>[class*="col-"]{padding-left:8px;padding-right:8px}
  .chips-wrap{display:flex;flex-wrap:wrap;gap:8px}
  .d-flex{display:flex;align-items:center;gap:10px}
  .pos-rel{position:relative}
  .label-pill{border-radius:999px;padding:6px 10px;font-size:12px}
  .badge-soft{background:#eef2ff;color:#1e293b;border:1px solid #c7d2fe;border-radius:999px;padding:6px 10px}
  .dot{display:inline-block;width:10px;height:10px;border-radius:50%}

  /* ===== Header box rata tengah vertikal (global) ===== */
  .box-header.with-border{
    display:flex; align-items:center; justify-content:space-between;
    min-height:46px;
  }
  .box-header .box-title{ margin:0; line-height:1.2; display:flex; align-items:center; gap:8px; }
  .box-header .pull-right{ margin-left:auto; float:none !important; display:flex; align-items:center; }

  /* ===== (1) Khusus panel Pratinjau: judul rata kiri (tidak center) ===== */
  .sticky-side>.box-header{ justify-content:flex-start; }

  /* Toggle tipe */
  #tipeToggle.btn-group,#tipeToggle.btn-group-justified{display:flex!important;flex-wrap:wrap;gap:8px;width:100%;table-layout:auto}
  #tipeToggle.btn-group-justified>.btn{float:none;display:flex;align-items:center;justify-content:center}
  #tipeToggle .btn{flex:1 1 180px;min-height:42px;padding:8px 12px;gap:8px;border-radius:10px;font-weight:700;line-height:1.25;
    font-size:clamp(12px,1.5vw,14px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  #tipeToggle .btn i{opacity:.95}

  /* Tokenbox */
  .tokenbox{min-height:40px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:6px 8px}
  .token-input{flex:1; border:0; outline:none; min-width:120px}
  #kelasBox .token{
    display:inline-flex; align-items:center; gap:6px;
    background:#eef2ff; color:#1e293b; border:1px solid #c7d2fe;
    border-radius:999px; padding:4px 8px; font-size:12px; transition:.15s
  }
  #kelasBox .token:hover{transform:translateY(-1px)}
  #kelasBox .token .x{cursor:pointer; opacity:.85}
  #kelasBox .token .x:hover{opacity:1}

  /* Dropdown */
  .dropdown-menu.dm-wide{display:block;visibility:hidden;opacity:0;width:100%;max-height:320px;overflow:auto;transition:opacity .12s,visibility .12s;box-shadow:0 10px 24px rgba(0,0,0,.12);z-index:1000}
  .dropdown-menu.dm-wide.showy{visibility:visible;opacity:1}
  #kelasMenu .opt,#itemMenu .opt{padding:10px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;justify-content:space-between;align-items:center}
  #kelasMenu .opt:hover,#itemMenu .opt:hover{background:#eff6ff}
  #kelasMenu .muted,#itemMenu .muted{color:#64748b;font-size:12px}

  /* Chips */
  .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#0b1220;font-weight:600;border:1px solid #e2e8f0;font-size:12px;transition:.15s}
  .chip i{opacity:.9}
  .chip:hover{transform:translateY(-1px)}
  .chip.brand-green{background:linear-gradient(90deg,#22c55e,#34d399); color:#052314; border-color:transparent;}
  .chip.brand-red{background:linear-gradient(90deg,#ef4444,#fb7185); color:#310607; border-color:transparent;}

  /* List siswa */
  .student-item{display:flex;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9;padding:10px}
  .student-item:hover{background:#fafafa}
  .student-item input[type="checkbox"]{width:18px;height:18px}

  /* ===== (1) PERAPIHAN POSISI BAR CARI SISWA (RATA KIRI SEJAJAR KELAS) ===== */
  .sticky-toolbar{
    position:sticky; top:0; z-index:2; background:#fff; border-bottom:1px solid #eee;
    padding-left:15px; padding-right:15px; /* sejajarkan dengan gutter kolom (15px) */
  }
  .sticky-toolbar .row-tight > [class*="col-"]{
    display:flex; align-items:center;
    padding-left:0; padding-right:8px; /* hilangkan padding kiri agar start rata kiri */
  }
  .sticky-toolbar .input-group{width:100%;}
  .sticky-toolbar .input-group-addon{
    background:#fff; border-right:0;
    border-radius:8px 0 0 8px;
    width:40px; text-align:center;
  }
  #search.form-control{
    height:40px; border-radius:0 8px 8px 0;
  }

  /* ===== (2) RAPIKAN TOMBOL "Pilih Semua" AGAR TEKS PAS DI DALAM ===== */
  #toggleAll{
    min-height:40px; border-radius:10px;
    font-size:12px; font-weight:600; /* diperkecil supaya teks tidak keluar */
    line-height:1.2; padding:8px 10px;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }

  /* ===== (3) TAMBAH JEDA DI BADGE "dipilih" ===== */
  #checkedCount{ margin-right:6px; } /* beri spasi antar angka dan teks "dipilih" */

  .students-scroll{max-height:420px; overflow:auto}
  @media (max-width:767px){ .students-scroll{max-height:56vh} }

  /* Panel tema */
  #boxTema .dot{background:#ef4444}
  .tema-green #boxTema .dot{background:#10b981}
  .tema-red #boxTema .dot{background:#ef4444}

  /* ===== Tombol modern (neo) ===== */
  .btn-neo, .btn-neo-cta, .btn-neo-reset, .btn-neo-soft{
    position:relative; overflow:hidden; border:0; border-radius:14px; font-weight:800; letter-spacing:.2px;
    transition:transform .12s ease, box-shadow .18s ease, filter .18s ease, opacity .18s ease;
  }
  .btn-neo:hover, .btn-neo-cta:hover, .btn-neo-reset:hover, .btn-neo-soft:hover{ transform:translateY(-1px); filter:saturate(1.03); }
  .btn-neo:active, .btn-neo-cta:active, .btn-neo-soft:active{ transform:translateY(0); }

  /* Ripple */
  .btn-neo::after, .btn-neo-cta::after, .btn-neo-reset::after, .btn-neo-soft::after{
    content:""; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%) scale(0);
    width:140%; height:140%; border-radius:50%; background:rgba(255,255,255,.35); opacity:0; pointer-events:none;
  }
  .btn-neo:active::after, .btn-neo-cta:active::after, .btn-neo-soft:active::after{
    transform:translate(-50%,-50%) scale(1); opacity:1; transition:transform .28s ease, opacity .55s ease;
  }

  /* Soft neutral */
  .btn-neo-soft{ background:linear-gradient(90deg,#f1f5f9,#e5e7eb); color:#0b1220; box-shadow:0 6px 14px rgba(2,6,23,.08); }

  /* CTA themed + shimmer */
  #submitBtn{border:0;color:#fff}
  #submitBtn.shimmer-on{background-size:200% 100%,100% 100%;background-position:-150% 0,0 0;animation:shimmer 1.6s ease-in-out infinite}
  @keyframes shimmer {0%{background-position:-150% 0,0 0}60%{background-position:250% 0,0 0}100%{background-position:250% 0,0 0}}
  #submitBtn.shimmer-off{background:linear-gradient(90deg,#3b82f6,#0ea5e9)}
  #submitBtn.shimmer-on{background-image:linear-gradient(90deg,rgba(255,255,255,.0) 0%,rgba(255,255,255,.35) 50%,rgba(255,255,255,0) 100%),linear-gradient(90deg,#2563eb,#0ea5e9)}
  .btn-neo-cta{ box-shadow:0 12px 28px rgba(14,165,233,.35); }

  /* Reset modern */
  .btn-neo-reset{
    background:linear-gradient(90deg,#f8fafc,#eef2f7);
    color:#0f172a;
    border:1px solid #e5e7eb;
    box-shadow:0 6px 14px rgba(2,6,23,.06);
  }

  /* ====== ANIMASI RESET: PUTAR KE KIRI SEKALI ====== */
  #resetIcon.spin-once{ animation: spin-ccw 0.6s ease-out; transform-origin:center; }
  @keyframes spin-ccw{
    from { transform: rotate(0deg); }
    to   { transform: rotate(-360deg); }
  }

  /* Header Pratinjau warna tema (bukan abu) */
  .sticky-side.side-danger>.box-header{background:linear-gradient(90deg,#ef4444,#fb7185)!important;color:#fff!important}
  .sticky-side.side-success>.box-header{background:linear-gradient(90deg,#22c55e,#34d399)!important;color:#052314!important}
  .sticky-side>.box-header{background:linear-gradient(90deg,#6366f1,#0ea5e9)!important;color:#fff!important}

  /* Submit button mengikuti tema */
  body.theme-red #submitBtn.shimmer-off{background:linear-gradient(90deg,#ef4444,#f43f5e)}
  body.theme-red #submitBtn.shimmer-on{background-image:linear-gradient(90deg,rgba(255,255,255,.0) 0%,rgba(255,255,255,.4) 50%,rgba(255,255,255,0) 100%),linear-gradient(90deg,#ef4444,#f43f5e)}
  body.theme-green #submitBtn.shimmer-off{background:linear-gradient(90deg,#16a34a,#22c55e); color:#052314}
  body.theme-green #submitBtn.shimmer-on{background-image:linear-gradient(90deg,rgba(255,255,255,.0) 0%,rgba(255,255,255,.35) 50%,rgba(255,255,255,0) 100%),linear-gradient(90deg,#16a34a,#22c55e); color:#052314}

  /* Toast */
  .toast-area{position:fixed; right:16px; bottom:16px; z-index:9999; display:none}
  .toast-ok{background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:10px 12px; border-radius:10px;}
  .toast-err{background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:10px 12px; border-radius:10px;}

  /* Mobile polish */
  .page-title-compact{font-weight:800; letter-spacing:.2px}
  .stack-mobile > [class*="col-"]{margin-bottom:12px}
  .mobile-seg .btn{font-weight:700}
  .mb-6-mobile{margin-bottom:6px}
  .mb-8-mobile{margin-bottom:8px}
  .dm-wide{touch-action:pan-y}

  /* ====== LOG PANEL STYLES ====== */
  .log-header{background:linear-gradient(90deg,#0ea5e9,#22d3ee); color:#03243a;}
  .log-header .box-title{display:flex;align-items:center}
  .badge-live{margin-left:8px; background:#e0f2fe; color:#075985; border:1px solid #bae6fd; border-radius:999px; padding:4px 8px; font-size:11px; vertical-align:middle;}
  .live-dot{display:inline-block;width:8px;height:8px;border-radius:50%; background:#10b981; margin-right:6px; box-shadow:0 0 0 0 rgba(16,185,129,.8); animation:pulse 1.6s infinite;}
  @keyframes pulse { 0%{transform:scale(.9); box-shadow:0 0 0 0 rgba(16,185,129,.7)} 70%{transform:scale(1); box-shadow:0 0 0 12px rgba(16,185,129,0)} 100%{transform:scale(.9); box-shadow:0 0 0 0 rgba(16,185,129,0)} }

  .log-toolbar-right .btn{margin-right:8px}
  .switch.small{display:inline-block;position:relative;width:46px;height:24px;vertical-align:middle}
  .switch.small input{display:none}
  .switch.small .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#e5e7eb;border-radius:999px;transition:.2s}
  .switch.small .slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s; box-shadow:0 2px 5px rgba(0,0,0,.15)}
  .switch.small input:checked + .slider{background:#93c5fd}
  .switch.small input:checked + .slider:before{transform:translateX(22px)}

  /* Log list */
  .log-list{max-height:420px; overflow:auto}
  @media (max-width:767px){ .log-list{max-height:56vh} }
  .log-item{display:flex;gap:12px;align-items:flex-start;padding:10px;border-bottom:1px solid #f1f5f9}
  .log-item:hover{background:#fafafa}
  .log-ico{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;color:#fff;flex:0 0 34px;box-shadow:0 8px 18px rgba(0,0,0,.12)}
  .ico-red{background:linear-gradient(135deg,#ef4444,#f97316)}
  .ico-green{background:linear-gradient(135deg,#22c55e,#10b981)}
  .ico-gray{background:linear-gradient(135deg,#64748b,#94a3b8)}
  .log-main{flex:1}
  .log-title{margin:0;font-weight:500;font-size:13px;line-height:1.45}
  .log-title strong{font-weight:700}
  .log-meta{font-size:11px;color:#475569;display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
  .meta-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:3px 7px;border:1px solid #e2e8f0;background:#f8fafc;font-weight:600}
  .meta-badge .fa{opacity:.9}
  .type-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:3px 7px;font-size:10px;font-weight:800}
  .type-red{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
  .type-green{background:#dcfce7;color:#065f46;border:1px solid #bbf7d0}
  .type-gray{background:#e2e8f0;color:#334155;border:1px solid #cbd5e1}

  /* skeleton */
  .log-skel{display:flex;gap:12px;align-items:center;padding:10px}
  .log-skel .sk-ico{width:34px;height:34px;border-radius:10px;background:linear-gradient(90deg,#e5e7eb,#f1f5f9,#e5e7eb); background-size:200% 100%; animation:sh 1.2s infinite}
  .log-skel .sk-line{height:10px;border-radius:6px;background:linear-gradient(90deg,#e5e7eb,#f1f5f9,#e5e7eb); background-size:200% 100%; animation:sh 1.2s infinite}
  .log-skel .sk-line.w1{width:60%}.log-skel .sk-line.w2{width:40%}.log-skel .sk-line.w3{width:30%}
  @keyframes sh{0%{background-position:200% 0}100%{background-position:-200% 0}}

  /* Ekspor kecil + Soft Orange */
  .btn-compact{padding:5px 10px; font-size:11px; border-radius:999px; white-space:nowrap; max-width:100%}
  .btn-orange-soft{
    background:linear-gradient(90deg,#ffe4c7,#ffd4a8);
    color:#0b1220;
    border:1px solid #ffd1a1;
    box-shadow:0 10px 20px rgba(255,165,0,.15);
  }
  .btn-orange-soft:hover{ filter:saturate(1.05); transform:translateY(-1px); }
  .btn-orange-soft:active{ transform:translateY(0); }

  /* Date input compact */
  .input-compact{height:32px; padding:4px 8px; font-size:12px}
  .log-custom-row{display:flex; gap:8px; align-items:center; flex-wrap:nowrap}
  .log-custom-row .col-xs-6{flex:1 1 0}

  /* Responsif ekstra untuk mobile */
  @media (max-width:640px){
    .log-top-right{flex-wrap:wrap}
    #logExport{width:100%}
  }
  @media (max-width:480px){
    #toggleAll, #resetBtn, #submitBtn{min-height:44px}
    .page-title-compact{font-size:18px}
    .label-pill{font-size:11px}
    #itemMenu .opt, #kelasMenu .opt{padding:12px 14px}
    .box-header.with-border{min-height:44px}
    .log-top-right .col-xs-6{width:50%}
  }
</style>

<script>
(function(){
  const el = (q) => document.querySelector(q);
  const els = (q) => Array.from(document.querySelectorAll(q));
  const $toast = el('#toast');

  async function api(action, payload={}){
    const fd=new FormData(); fd.append('action',action);
    if(window.EPOIN_CSRF) fd.append('_csrf', window.EPOIN_CSRF);
    for(const k in payload){ if(Array.isArray(payload[k])) payload[k].forEach(v=>fd.append(k+'[]',v)); else fd.append(k,payload[k]); }
    let r, data; try{ r=await fetch(location.pathname,{method:'POST',body:fd}); }catch(e){ throw new Error('Tidak bisa menghubungi server: '+e.message); }
    try{ data=await r.json(); }catch{ throw new Error('Respons tidak valid (HTTP '+r.status+').'); }
    if(!r.ok || data.ok===false) throw new Error(data.error || ('HTTP '+r.status));
    return data;
  }
  function toast(msg, ok=true){
    $toast.innerHTML = '<div class="'+(ok?'toast-ok':'toast-err')+'">'+msg+'</div>';
    $toast.style.display='block';
    clearTimeout($toast._t);
    $toast._t=setTimeout(()=>{ $toast.style.display='none'; }, 3600);
  }

  const state = {
    tipe: 'pelanggaran',
    kelasList: [],
    kelasSelected: new Map(),
    items: [],
    item_id: null,
    item_label: '',
    students: [],
    selected: new Set(),
  };

  function refreshTimeBadge(){ try{ el('#timeBadge').textContent=new Date().toLocaleString('id-ID'); }catch(_){} }

  /* Ikon judul */
  function setTitleIcon(){
    const wrap = el('#titleIcon'); if(!wrap) return;
    const i = wrap.querySelector('i') || document.createElement('i');
    const want = (state.tipe==='prestasi') ? 'fa fa-trophy' : 'fa fa-exclamation-triangle';
    i.className = want;
    if(!wrap.contains(i)) wrap.appendChild(i);
    requestAnimationFrame(()=>{ try{
      const before = window.getComputedStyle(i, '::before').getPropertyValue('content');
      if(!before || before === 'none' || before === 'normal' || before === '""'){ i.className = 'fa fa-list-alt'; }
    }catch(_){}} );
  }

  /* Tema dinamis */
  function applyTheme(){
    const left = el('#boxLeft');
    left.classList.remove('box-success','box-danger');
    const side = document.querySelector('.sticky-side');
    side.classList.remove('side-success','side-danger');

    document.body.classList.remove('theme-green','theme-red');

    const subBadge = el('#subBadge');
    subBadge.classList.remove('badge-mode-green','badge-mode-red');

    if (state.tipe === 'prestasi') {
      left.classList.add('box-success');
      side.classList.add('side-success');
      document.body.classList.add('theme-green');
      subBadge.classList.add('badge-mode-green');
      subBadge.textContent = 'Mode: Prestasi';
      el('.hero-ico').style.background = 'linear-gradient(135deg,#22c55e,#34d399)';
      el('#temaLabel').innerHTML = '<i class="fa fa-leaf text-green"></i> Mode: Prestasi';
    } else {
      left.classList.add('box-danger');
      side.classList.add('side-danger');
      document.body.classList.add('theme-red');
      subBadge.classList.add('badge-mode-red');
      subBadge.textContent = 'Mode: Pelanggaran';
      el('.hero-ico').style.background = 'linear-gradient(135deg,#ef4444,#fb7185)';
      el('#temaLabel').innerHTML = '<i class="fa fa-bolt text-red"></i> Mode: Pelanggaran';
    }
    setTitleIcon();
  }

  function formReady(){ return (state.kelasSelected.size>0) && !!state.item_id && (state.selected.size>0); }
  function updateSubmitVisual(){
    const b = el('#submitBtn');
    if (formReady()){ b.classList.remove('shimmer-off'); b.classList.add('shimmer-on'); }
    else { b.classList.remove('shimmer-on'); b.classList.add('shimmer-off'); }
  }

  /* ====== KELAS ====== */
  function renderKelasMenu(filter=''){
    const menu = el('#kelasMenu');
    const q = (filter||'').toLowerCase();
    menu.innerHTML = '';

    const top=document.createElement('div'); top.className='opt';
    top.innerHTML='<span><b><i class="fa fa-layer-group"></i> Pilih Semua</b></span><span class="muted">'+state.kelasList.length+' kelas</span>';
    top.addEventListener('click',()=>{
      const allSelected = state.kelasSelected.size===state.kelasList.length;
      state.kelasSelected.clear();
      if(!allSelected) state.kelasList.forEach(k=>state.kelasSelected.set(String(k.kelas_id),k.kelas_nama));
      syncKelasTokens(); loadStudentsMulti(); updateSubmitVisual();
      menu.classList.remove('showy');
    });
    menu.append(top);

    (state.kelasList.filter(k=>k.kelas_nama.toLowerCase().includes(q))).forEach(k=>{
      const row=document.createElement('div'); row.className='opt';
      const checked = state.kelasSelected.has(String(k.kelas_id));
      row.innerHTML = `<span><i class="fa fa-chalkboard-teacher"></i> ${k.kelas_nama}</span><span class="muted">${checked?'Dipilih':''}</span>`;
      row.addEventListener('click',()=>{
        const key=String(k.kelas_id);
        if(state.kelasSelected.has(key)) state.kelasSelected.delete(key); else state.kelasSelected.set(key,k.kelas_nama);
        syncKelasTokens(); loadStudentsMulti(); updateSubmitVisual();
      });
      menu.append(row);
    });

    menu.classList.add('showy');
  }

  function syncKelasTokens(){
    const box = el('#kelasBox');
    box.querySelectorAll('.token').forEach(n=>n.remove());
    for(const [id,nama] of state.kelasSelected){
      const t=document.createElement('span'); t.className='token'; t.innerHTML=`<i class="fa fa-tag"></i> ${nama} <span class="x" title="Hapus">✕</span>`;
      t.querySelector('.x').addEventListener('click',()=>{ state.kelasSelected.delete(id); syncKelasTokens(); loadStudentsMulti(); updateSubmitVisual(); });
      box.insertBefore(t, el('#kelasSearch'));
    }
    updateSummary(); renderLegend();
  }
  function renderLegend(){
    const lg=el('#legend'); lg.innerHTML='';
    if(state.kelasSelected.size>0){
      lg.append(...Array.from(state.kelasSelected).map(([id,nama])=>{
        const c=document.createElement('div'); c.className='chip'; c.innerHTML=`<i class="fa fa-chalkboard"></i> ${nama}`; return c;
      }));
    }
  }

  /* ===== ITEM ===== */
  function renderItemMenu(list){
    const menu=el('#itemMenu');
    menu.innerHTML='';
    (list||[]).forEach(it=>{
      const row=document.createElement('div'); row.className='opt';
      row.innerHTML=`<span><i class="fa fa-tag text-primary"></i> ${it.nama}</span><span class="muted"><i class="fa fa-star-o"></i> ${it.poin} poin</span>`;
      row.addEventListener('click',()=>{
        state.item_id=String(it.id);
        state.item_label=`${it.nama} (${it.poin} poin)`;
        el('#itemSearch').value=state.item_label;
        menu.classList.remove('showy');
        updateSummary(); updateSubmitVisual();
      });
      menu.append(row);
    });
    if(menu.children.length===0){
      const emp=document.createElement('div'); emp.className='opt'; emp.innerHTML='<span><i class="fa fa-inbox"></i> Tidak ada hasil</span>'; menu.append(emp);
    }
    menu.classList.add('showy');
  }
  let itemTimer=null;
  async function searchItemsImmediate(q=''){
    el('#itemCombo').classList.add('loading');
    try{
      const r=await api('fetch_items',{tipe: state.tipe, q});
      state.items=r.data||[];
      renderItemMenu(state.items);
    }catch(e){ toast('Gagal memuat item: '+e.message, false); }
    finally{ el('#itemCombo').classList.remove('loading'); }
  }
  function searchItemsDebounced(q=''){ clearTimeout(itemTimer); itemTimer=setTimeout(()=>searchItemsImmediate(q), 240); }

  /* ===== STUDENTS ===== */
  function renderStudents(){
    const list=el('#students'); const q=(el('#search').value||'').toLowerCase();
    list.innerHTML='';
    const rows = state.students.filter(s=>s.siswa_nama.toLowerCase().includes(q));
    for(const s of rows){
      const row=document.createElement('div'); row.className='student-item';
      const cb=document.createElement('input'); cb.type='checkbox';
      cb.checked=state.selected.has(String(s.siswa_id));
      cb.addEventListener('change',()=>{ if(cb.checked) state.selected.add(String(s.siswa_id)); else state.selected.delete(String(s.siswa_id)); updateSummary(); updateSubmitVisual(); });
      const name=document.createElement('div'); name.style.flex='1'; name.innerHTML=`<strong>${s.siswa_nama}</strong><br><small><i class="fa fa-chalkboard"></i> ${s.kelas_nama}</small>`;
      row.append(cb,name); list.append(row);
    }
    updateSummary();
  }

  function updateSummary(){
    el('#checkedCount').textContent = state.selected.size;
    const wrap=el('#summary'); wrap.innerHTML='';
    const chip=(html,cls='')=>{const c=document.createElement('div'); c.className='chip '+cls; c.innerHTML=html; return c;};
    wrap.append(chip(`<i class="fa ${state.tipe==='prestasi'?'fa-trophy':'fa-exclamation-triangle'}"></i> ${state.tipe.toUpperCase()}`, state.tipe==='prestasi'?'brand-green':'brand-red'));
    wrap.append(chip('<i class="fa fa-layer-group"></i> '+(state.kelasSelected.size||0)+' kelas'));
    if(state.item_label) wrap.append(chip('<i class="fa fa-tag"></i> '+state.item_label));
    wrap.append(chip('<i class="fa fa-users"></i> '+state.selected.size+' siswa'));
  }

  /* ===== LOADERS ===== */
  async function loadClasses(){
    try{ const r=await api('fetch_classes'); state.kelasList=r.data||[]; renderKelasMenu(el('#kelasSearch').value); }
    catch(e){ toast('Gagal memuat kelas: '+e.message, false); }
  }
  async function loadStudentsMulti(){
    const ids=Array.from(state.kelasSelected.keys());
    if(ids.length===0){ state.students=[]; state.selected.clear(); renderStudents(); return; }
    try{ const r=await api('fetch_students_multi',{kelas_ids: ids}); state.students=r.data||[]; state.selected.clear(); renderStudents(); }
    catch(e){ toast('Gagal memuat siswa: '+e.message, false); }
  }

  /* ===== RESULT MODAL ===== */
  function showResultModal(data){
    const sum=el('#resultSummary'); sum.innerHTML='';
    const chip=(html)=>{const c=document.createElement('div'); c.className='chip '+(state.tipe==='prestasi'?'brand-green':'brand-red'); c.innerHTML=html; return c;};
    sum.append(chip(`<i class="fa ${state.tipe==='prestasi'?'fa-trophy':'fa-exclamation-triangle'}"></i> ${data.tipe.toUpperCase()}`));
    sum.append(chip(`<i class="fa fa-tag"></i> ${data.item}`));
    try{
      const d = new Date(data.waktu.replace(' ','T'));
      sum.append(chip(`<i class="fa fa-clock-o"></i> ${d.toLocaleString('id-ID')}`));
    }catch(_){ sum.append(chip(`<i class="fa fa-clock-o"></i> ${data.waktu}`)); }
    sum.append(chip(`<i class="fa fa-users"></i> ${data.count} siswa`));

    const tb=document.querySelector('#resultTable tbody'); tb.innerHTML='';
    data.students.forEach((s,i)=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`<td>${i+1}</td><td>${s.siswa_nama}</td><td><span class="label ${state.tipe==='prestasi'?'label-success':'label-danger'}"><i class="fa fa-chalkboard"></i> ${s.kelas_nama}</span></td>`;
      tb.append(tr);
    });

    if (window.jQuery && jQuery.fn.modal) { jQuery('#resultModal').modal('show'); }
  }

  /* ===== UTIL: konversi datetime-local -> SQL ===== */
  function toSqlDatetime(dtLocal){
    // dtLocal contoh: "2025-10-15T07:41" atau "2025-10-15T07:41:23"
    if(!dtLocal) return '';
    const s = String(dtLocal).trim().replace('T',' ');
    // tambahkan :00 jika detik tidak ada
    return /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/.test(s) ? s : (s+':00');
  }

  /* ===== INIT UI ===== */
  function initUI(){
    const bootErr = <?php echo json_encode($bootDbError ?? ''); ?>;
    if (bootErr) toast(bootErr, false);

    const now=new Date(); now.setMinutes(now.getMinutes()-now.getTimezoneOffset());
    el('#waktu').value=now.toISOString().slice(0,16);
    refreshTimeBadge(); setInterval(refreshTimeBadge, 30000);

    applyTheme();

    els('#tipeToggle .btn').forEach(b=>b.addEventListener('click',async()=>{
      els('#tipeToggle .btn').forEach(x=>{x.classList.remove('active','btn-danger','btn-success'); x.setAttribute('aria-selected','false');});
      b.classList.add('active'); b.setAttribute('aria-selected','true');

      state.tipe=b.dataset.tipe;
      if(state.tipe==='prestasi'){ b.classList.add('btn-success'); } else { b.classList.add('btn-danger'); }
      state.item_id=null; state.item_label=''; el('#itemSearch').value='';
      updateSummary(); applyTheme(); updateSubmitVisual();
      await searchItemsImmediate('');
    }));

    const km=el('#kelasMenu'); const ks=el('#kelasSearch');
    ks.addEventListener('focus',()=>{ renderKelasMenu(ks.value); km.classList.add('showy'); });
    ks.addEventListener('input',()=>renderKelasMenu(ks.value));
    document.addEventListener('click',(e)=>{ if(!e.target.closest('#kelasBox')) km.classList.remove('showy'); });

    const im=el('#itemMenu'); const is=el('#itemSearch');
    is.addEventListener('focus',()=>{ renderItemMenu(state.items); im.classList.add('showy'); });
    is.addEventListener('input',()=>{ im.classList.add('showy'); searchItemsDebounced(is.value); });
    document.addEventListener('click',(e)=>{ if(!e.target.closest('#itemCombo')) im.classList.remove('showy'); });

    el('#search').addEventListener('input',()=>renderStudents());
    el('#toggleAll').addEventListener('click',()=>{
      const q=(el('#search').value||'').toLowerCase();
      const filtered=state.students.filter(s=>s.siswa_nama.toLowerCase().includes(q));
      const all=filtered.every(s=>state.selected.has(String(s.siswa_id)));
      if(all) filtered.forEach(s=>state.selected.delete(String(s.siswa_id)));
      else filtered.forEach(s=>state.selected.add(String(s.siswa_id)));
      renderStudents(); updateSubmitVisual();
    });

    el('#resetBtn').addEventListener('click',()=>{
      const ri = el('#resetIcon');
      if (ri){ ri.classList.remove('spin-once'); void ri.offsetWidth; ri.classList.add('spin-once'); }
      state.kelasSelected.clear(); syncKelasTokens();
      state.students=[]; state.selected.clear(); renderStudents();
      state.item_id=null; state.item_label=''; el('#itemSearch').value='';
      updateSummary(); updateSubmitVisual();
    });

    /* ====== REVISI UTAMA: handler Simpan Kolektif ====== */
    el('#submitBtn').addEventListener('click', async ()=>{
      if(!formReady()){
        toast('Lengkapi pilihan: tipe, kelas, item, dan siswa.', false);
        return;
      }
      const btn = el('#submitBtn');
      btn.disabled = true;
      btn.classList.add('disabled');
      try {
        const waktuSql = toSqlDatetime(el('#waktu').value);
        // Map siswa -> kelas dari state.students
        const kelasMap = new Map(state.students.map(s => [String(s.siswa_id), s.kelas_id]));
        const unique = new Set();
        const pairs = [];
        state.selected.forEach(sidStr=>{
          const kid = kelasMap.get(String(sidStr));
          if(kid){ const token = `${sidStr}:${kid}`; if(!unique.has(token)){ unique.add(token); pairs.push(token); } }
        });
        if(!pairs.length){ toast('Tidak ada siswa terpilih.', false); btn.disabled=false; btn.classList.remove('disabled'); return; }

        const res = await api('save_bulk', {
          tipe: state.tipe,
          item_id: state.item_id,
          waktu: waktuSql,
          pairs: pairs
        });

        toast(res.message || 'Berhasil menyimpan.', true);

        // Tampilkan modal rincian dari state yang ada
        const picked = state.students.filter(s => state.selected.has(String(s.siswa_id)));
        showResultModal({
          tipe: state.tipe,
          item: state.item_label || '',
          waktu: waktuSql,
          count: picked.length,
          students: picked
        });

        // opsional: tetap pertahankan pilihan (tidak mereset form)
        // update shimmer agar tetap "on"
        updateSubmitVisual();
      } catch(e){
        toast('Gagal menyimpan: '+ e.message, false);
      } finally {
        btn.disabled = false;
        btn.classList.remove('disabled');
      }
    });

    updateSubmitVisual();
  }

  /* ====== LOG PANEL JS ====== */
  const logs = {
    items: [], page: 1, per_page: 15, q: '', range: '7d',
    from: '', to: '', loading: false, has_more: false, total: 0,
    auto: true, timer: null
  };

  function logTypeFromText(t){
    if(/prestasi/i.test(t)) return 'prestasi';
    if(/pelanggaran/i.test(t)) return 'pelanggaran';
    return 'other';
  }
  function fmtTime(s){ try{ const d=new Date(s.replace(' ','T')); return d.toLocaleString('id-ID'); }catch(_){ return s; } }

  /* (2) Bold hanya NAMA SISWA; hentikan sebelum "(...)" ATAU "oleh ..." */
  function escapeHTML(str){ return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  function formatAktivitas(raw){
    const txt = String(raw||'');
    const re  = /untuk siswa\s+(.+?)(?=\s*(?:\(|oleh\b|$))/i; // stop sebelum "(" atau "oleh" atau akhir
    const m   = re.exec(txt);
    if(!m) return escapeHTML(txt);
    const beforeAll = txt.slice(0, m.index);
    const before = beforeAll + 'untuk siswa ';
    const name  = m[1];
    const after = txt.slice(m.index + ('untuk siswa '.length) + name.length);
    return escapeHTML(before) + '<strong>' + escapeHTML(name.trim()) + '</strong>' + escapeHTML(after);
  }

  function logSkel(n=6){
    const list = el('#logList'); list.innerHTML='';
    for(let i=0;i<n;i++){
      const row = document.createElement('div');
      row.className='log-skel';
      row.innerHTML = `<div class="sk-ico"></div>
        <div style="flex:1">
          <div class="sk-line w1"></div>
          <div style="height:8px"></div>
          <div class="sk-line w3"></div>
        </div>`;
      list.append(row);
    }
  }
  function renderLogSummary(){
    const wrap = el('#logSummaryChips'); if(!wrap) return;
    wrap.innerHTML='';
    const chip=(html)=>{const c=document.createElement('div'); c.className='chip'; c.innerHTML=html; return c;};
    wrap.append(chip(`<i class="fa fa-database"></i> ${logs.total} aktivitas`));
    if(logs.q) wrap.append(chip(`<i class="fa fa-search"></i> "${logs.q}"`));
    let labelRange = {'today':'Hari ini','7d':'7 hari','30d':'30 hari','all':'Semua','custom':'Kustom'}[logs.range]||'7 hari';
    if(logs.range==='custom'){ labelRange = `Kustom: ${logs.from||'—'} s/d ${logs.to||'—'}`; }
    wrap.append(chip(`<i class="fa fa-calendar"></i> ${labelRange}`));
  }
  function renderLogs(){
    const list = el('#logList'); if(!list) return;
    list.innerHTML='';
    if(!logs.items.length){
      el('#logEmpty').style.display='block';
      el('#logMore').style.display='none';
      return;
    }
    el('#logEmpty').style.display='none';

    logs.items.forEach((r)=>{
      const t = logTypeFromText(r.aktivitas||'');
      const icoClass = t==='prestasi' ? 'ico-green' : (t==='pelanggaran' ? 'ico-red' : 'ico-gray');
      const typePill = t==='prestasi' ? '<span class="type-pill type-green"><i class="fa fa-trophy"></i> Prestasi</span>'
                    : t==='pelanggaran' ? '<span class="type-pill type-red"><i class="fa fa-exclamation-triangle"></i> Pelanggaran</span>'
                    : '<span class="type-pill type-gray"><i class="fa fa-file-text-o"></i> Lainnya</span>';

      const row = document.createElement('div');
      row.className='log-item';
      row.innerHTML = `
        <div class="log-ico ${icoClass}"><i class="fa ${t==='prestasi'?'fa-trophy':(t==='pelanggaran'?'fa-exclamation-triangle':'fa-history')}"></i></div>
        <div class="log-main">
          <p class="log-title">${formatAktivitas(r.aktivitas||'')}</p>
          <div class="log-meta">
            ${typePill}
            <span class="meta-badge"><i class="fa fa-user"></i> ${r.nama_guru ? escapeHTML(r.nama_guru) : '—'}</span>
            <span class="meta-badge"><i class="fa fa-clock-o"></i> ${fmtTime(r.waktu||'')}</span>
          </div>
        </div>
      `;
      list.append(row);
    });

    el('#logMore').style.display = logs.has_more ? 'inline-block' : 'none';
  }
  async function loadLogs(opts={reset:false}){
    if(logs.loading) return;
    logs.loading = true;
    if(opts.reset){ logs.page = 1; logs.items = []; renderLogs(); logSkel(); }
    try{
      const r = await api('fetch_logs', {
        q: logs.q, range: logs.range, date_from: logs.from, date_to: logs.to,
        page: logs.page, per_page: logs.per_page
      });
      logs.total = r.total||0;
      logs.has_more = !!r.has_more;
      const batch = Array.isArray(r.data)? r.data : [];
      if(opts.reset){ logs.items = batch; } else { logs.items = logs.items.concat(batch); }
      renderLogSummary();
      renderLogs();
    }catch(e){
      toast('Gagal memuat log: '+e.message, false);
      if(opts.reset){ el('#logList').innerHTML=''; el('#logEmpty').style.display='block'; }
    }finally{
      logs.loading = false;
    }
  }
  function debounce(fn,ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
  function initLogsUI(){
    const $q = el('#logSearch');
    const $range = el('#logRange');
    const $from = el('#logFrom');
    const $to = el('#logTo');
    const $customRow = el('#logCustomRow');
    const $refresh = el('#logRefresh');
    const $more = el('#logMore');
    const $auto = el('#logAuto');
    const $export = el('#logExport');

    if(!$q) return;

    $auto.checked = true;

    $q.addEventListener('input', debounce(()=>{ logs.q = $q.value.trim(); loadLogs({reset:true}); }, 300));
    $range.addEventListener('change', ()=>{
      logs.range = $range.value;
      $customRow.style.display = (logs.range==='custom') ? 'block' : 'none';
      loadLogs({reset:true});
    });
    $from.addEventListener('change', ()=>{ logs.from=$from.value; if(logs.range==='custom') loadLogs({reset:true}); });
    $to.addEventListener('change', ()=>{ logs.to=$to.value; if(logs.range==='custom') loadLogs({reset:true}); });
    $refresh.addEventListener('click', ()=> loadLogs({reset:true}) );
    $more.addEventListener('click', ()=>{ if(logs.has_more){ logs.page+=1; loadLogs({reset:false}); }});
    $auto.addEventListener('change', ()=>{
      logs.auto = $auto.checked;
      el('#logLiveBadge').style.opacity = logs.auto ? '1' : '.45';
      if(logs.timer){ clearInterval(logs.timer); logs.timer=null; }
      if(logs.auto){ logs.timer = setInterval(()=>loadLogs({reset:true}), 8000); }
    });
    $export.addEventListener('click', ()=>{
      if(!logs.items.length){ toast('Tidak ada data untuk diekspor', false); return; }
      const rows = [['Waktu','Nama Guru','Aktivitas']].concat(
        logs.items.map(r=>[r.waktu||'', r.nama_guru||'', String(r.aktivitas||'').replace(/\r?\n/g,' ')])
      );
      const csv = rows.map(cols=>cols.map(c=>`"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'}); const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href=url; a.download='log_aktivitas_'+new Date().toISOString().replace(/[:\-T]/g,'').slice(0,14)+'.csv';
      document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
    });

    if (window.jQuery) { jQuery('#resultModal').on('shown.bs.modal', function(){ loadLogs({reset:true}); }); }

    loadLogs({reset:true});
    logs.timer = setInterval(()=>{ if($auto.checked) loadLogs({reset:true}); }, 8000);
  }

  // Boot
  initUI();
  Promise.all([loadClasses(), searchItemsImmediate('')]).catch(()=>{});
  initLogsUI();

})();
</script>
<?php
$__footer = __DIR__ . '/footer.php';
if (is_file($__footer)) { include $__footer; }

/* ===================== Saran singkat (UI/UX) =====================
1) Tambahkan notifikasi “undo” (opsional) setelah simpan agar aman jika salah pilih.
2) Pertimbangkan limiter pilihan (misal maksimal 200 siswa/sekali simpan) untuk jaga performa DB.
3) Jika item sangat banyak, siapkan pagination/virtual list pada dropdown Item.
4) Log aktivitas bisa ditambah kolom IP/UA untuk audit trail.
================================================================== */
?>
