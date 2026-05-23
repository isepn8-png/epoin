<?php
/**
 * EPS — Input Nilai STS Siswa (AdminLTE-wrapped)
 * v2025-10-17.r3 + patch-CTRL-S/AUTOSAVE/VALIDASI/TOAST
 *
 * Revisi fokus tambahan:
 * A) Kolom tabel distabilkan lebarnya (mode TP & Deskripsi sama persis).
 * B) Validasi nilai 1–100 (kosong boleh). >100 ditolak (toast merah + flash merah).
 * C) Tombol Muat & Simpan pada footer diratakan ke kanan.
 * D) Toolbar tombol Mode/Generate: responsif + badge status (TP/DESK) + warna dinamis.
 * E) **Update diminta**: Mode **DESK** kini menggunakan tema **Orange Soft** (modern, eye‑catching)
 * dengan micro‑interaction (gradient pan + highlight radial pada hover/focus).
 * F) **UPDATE TERBARU**: Unlock Filter Semester di UI. Dropdown semester kini bisa diklik
 * sehingga guru bisa berpindah dan mengisi nilai antara Semester 1 dan Semester 2.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php'; ensure_logged_in();
require_once __DIR__ . '/../koneksi.php';

mysqli_report(MYSQLI_REPORT_OFF);

/* ---------- helpers ---------- */
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr); exit; }
function db_one(mysqli $db, $sql){
  $r = mysqli_query($db,$sql);
  if ($r instanceof mysqli_result){ $row=mysqli_fetch_assoc($r); mysqli_free_result($r); return $row?:null; }
  error_log('[SQL ERR] '.mysqli_error($db).' | '.$sql);
  return null;
}
function db_all(mysqli $db, $sql){
  $rows=[]; $r=mysqli_query($db,$sql);
  if ($r instanceof mysqli_result){ while($row=mysqli_fetch_assoc($r)) $rows[]=$row; mysqli_free_result($r); return $rows; }
  error_log('[SQL ERR] '.mysqli_error($db).' | '.$sql);
  return [];
}
function db_exec(mysqli $db, $sql){ $ok=mysqli_query($db,$sql); if(!$ok) error_log('[SQL ERR] '.mysqli_error($db).' | '.$sql); return $ok; }
function table_cols(mysqli $db,$name){ $cols=[]; $res=@mysqli_query($db,"SHOW COLUMNS FROM `$name`"); if($res){ while($r=@mysqli_fetch_assoc($res)){ $cols[]=$r['Field']; } } return $cols; }
function table_exists(mysqli $db,$name){
  $safe = mysqli_real_escape_string($db,$name);
  $q=@mysqli_query($db,"SHOW TABLES LIKE '$safe'");
  return $q && mysqli_num_rows($q)>0;
}
function pick_col($cols,$cands){ foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; } return null; }

/* [DESKRIPSI] auto-migrasi kolom deskripsi di nilai_pts */
function ensure_deskripsi_columns(mysqli $db){
  if(!table_exists($db,'nilai_pts')) return;
  $cols = table_cols($db,'nilai_pts');
  $need1 = !in_array('deskripsi_optimal',$cols,true);
  $need2 = !in_array('deskripsi_perlu',$cols,true);
  if($need1 || $need2){
    @db_exec($db,"ALTER TABLE `nilai_pts` ADD COLUMN `deskripsi_optimal` TEXT NULL AFTER `note`");
    @db_exec($db,"ALTER TABLE `nilai_pts` ADD COLUMN `deskripsi_perlu`   TEXT NULL AFTER `deskripsi_optimal`");
  }
}

/* ---------- context ---------- */
$SEKOLAH_ID = (int) (db_one($koneksi,"SELECT sekolah_id FROM sekolah LIMIT 1")['sekolah_id'] ?? 0);
$TA = db_one($koneksi,"SELECT * FROM ta WHERE ta_status=1 LIMIT 1");
if(!$TA) $TA = db_one($koneksi,"SELECT * FROM ta ORDER BY ta_id DESC LIMIT 1");
$TA_ID = (int)($TA['ta_id'] ?? 0);
$USER_ID = (int)($_SESSION['id'] ?? 0);

/* DETEKSI ADMIN/SUPER ADMIN */
$admin_roles = ['admin','administrator','superadmin','super_admin','super admin','root'];
$is_admin = false;
if (function_exists('_is_admin')) { $is_admin = !!_is_admin(); }
if (!$is_admin){
  foreach (['level','role','hak_akses','tipe','type'] as $k){
    if (isset($_SESSION[$k]) && in_array(strtolower((string)$_SESSION[$k]), $admin_roles, true)){
      $is_admin = true; break;
    }
  }
}
if (!$is_admin && isset($_SESSION['is_admin'])) $is_admin = (bool)$_SESSION['is_admin'];
if (!$is_admin && $USER_ID===1) $is_admin = true;

/* tolerant columns MAPEL */
$mapelCols = table_cols($koneksi,'mapel');
$MAPEL_ID   = pick_col($mapelCols, ['mapel_id','id_mapel','id']);
$MAPEL_NAMA = pick_col($mapelCols, ['mapel_nama','nama_mapel','nama','mapel']);

/* tolerant columns PENGAMPU */
$pengCols = table_exists($koneksi,'pengampu_mapel') ? table_cols($koneksi,'pengampu_mapel') : [];
$P_TA     = pick_col($pengCols, ['ta_id','tahunajaran_id','tahun_ajaran_id','id_ta']);
$P_KELAS  = pick_col($pengCols, ['kelas_id','id_kelas','idkelas','kelas']);
$P_MAPEL  = pick_col($pengCols, ['mapel_id','id_mapel','mapel']);
$P_GURU   = pick_col($pengCols, ['guru_user_id','guru_id','user_id','id_user','id_guru']);

/* tolerant columns NILAI_PTS_TP */
$nptCols = table_exists($koneksi,'nilai_pts_tp') ? table_cols($koneksi,'nilai_pts_tp') : [];
$NPT_PTS = pick_col($nptCols, ['pts_id','nilai_pts_id','id_nilai_pts','id_pts','npts_id','id']);
$NPT_TP  = pick_col($nptCols, ['tp_id','id_tp','tp','id_tujuan','tujuan_id']);
$NPT_KAT = pick_col($nptCols, ['kategori','kategori_enum','jenis','tipe','status','ket','keterangan']);

/* kompatibilitas: cek apakah nilai_pts_set masih punya kolom 'kkm' */
$npsCols = table_exists($koneksi,'nilai_pts_set') ? table_cols($koneksi,'nilai_pts_set') : [];
$HAS_KKM_COL = in_array('kkm', $npsCols, true);

/* semester otomatis: Jul–Des = 1; Jan–Jun = 2 */
$SEM_AUTOMATIS = (int) (date('n') >= 7 ? 1 : 2);

/* ---------- AJAX endpoints ---------- */
if (!empty($_GET['action'])){
  $act = $_GET['action'];

  /* 1) scope awal */
  if ($act==='scope'){
    if ($is_admin){
      $kelas = db_all($koneksi,"SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama");
      $mapel = ($MAPEL_ID && $MAPEL_NAMA)
        ? db_all($koneksi,"SELECT `$MAPEL_ID` AS id, `$MAPEL_NAMA` AS nama FROM mapel ORDER BY `$MAPEL_NAMA`")
        : [];
    }else{
      if ($P_TA && $P_KELAS && $P_MAPEL && $P_GURU){
        $kelas = db_all($koneksi,"SELECT DISTINCT k.kelas_id, k.kelas_nama
                                  FROM pengampu_mapel p
                                  JOIN kelas k ON k.kelas_id = p.`$P_KELAS`
                                  WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID} AND k.kelas_ta={$TA_ID}
                                  ORDER BY k.kelas_nama");
        $mapel = ($MAPEL_ID && $MAPEL_NAMA)
          ? db_all($koneksi,"SELECT DISTINCT m.`$MAPEL_ID` AS id, m.`$MAPEL_NAMA` AS nama
                             FROM pengampu_mapel p
                             JOIN mapel m ON m.`$MAPEL_ID`=p.`$P_MAPEL`
                             WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID}
                             ORDER BY m.`$MAPEL_NAMA`")
          : [];
      }else{
        $kelas = db_all($koneksi,"SELECT kelas_id, kelas_nama FROM kelas WHERE kelas_ta={$TA_ID} ORDER BY kelas_nama");
        $mapel = ($MAPEL_ID && $MAPEL_NAMA)
          ? db_all($koneksi,"SELECT `$MAPEL_ID` AS id, `$MAPEL_NAMA` AS nama FROM mapel ORDER BY `$MAPEL_NAMA`")
          : [];
      }
    }

    json_out([
      'ok'=>true,
      'ta_id'=>$TA_ID,
      'semester_auto'=>$SEM_AUTOMATIS,
      'kelas'=>$kelas,
      'mapel'=>$mapel,
      'is_admin'=>$is_admin
    ]);
  }

  /* 2) mapel tergantung kelas */
  if ($act==='list_mapel'){
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);

    if ($is_admin || !$P_TA || !$P_KELAS || !$P_MAPEL || !$P_GURU || !$MAPEL_ID || !$MAPEL_NAMA){
      $mapel = ($MAPEL_ID && $MAPEL_NAMA)
        ? db_all($koneksi,"SELECT `$MAPEL_ID` AS id, `$MAPEL_NAMA` AS nama FROM mapel ORDER BY `$MAPEL_NAMA`")
        : [];
    } else {
      $whereKelas = $kelas_id>0 ? "AND p.`$P_KELAS`={$kelas_id}" : "";
      $mapel = db_all($koneksi,"SELECT DISTINCT m.`$MAPEL_ID` AS id, m.`$MAPEL_NAMA` AS nama
                                FROM pengampu_mapel p
                                JOIN mapel m ON m.`$MAPEL_ID`=p.`$P_MAPEL`
                                WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID} $whereKelas
                                ORDER BY m.`$MAPEL_NAMA`");
    }
    json_out(['ok'=>true,'data'=>$mapel]);
  }

  /* 3) siswa per kelas */
  if ($act==='list_siswa'){
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
    $sCols = table_cols($koneksi,'siswa');
    $S_ID   = pick_col($sCols,['siswa_id','id','id_siswa']);
    $S_NAMA = pick_col($sCols,['siswa_nama','nama_siswa','nama']);
    $S_NISN = pick_col($sCols,['siswa_nisn','nisn']);
    $S_NIS  = pick_col($sCols,['siswa_nis','nis','no_induk']);

    if (!$S_ID || !$S_NAMA){ json_out(['ok'=>false,'error'=>'Struktur tabel siswa tidak dikenali']); }

    $rows = db_all($koneksi,
      "SELECT s.`$S_ID` AS siswa_id,
             s.`$S_NAMA` AS nama,
             ".($S_NISN ? "s.`$S_NISN`" : "NULL")." AS nisn,
             ".($S_NIS  ? "s.`$S_NIS`"  : "NULL")." AS nis
       FROM kelas_siswa ks
       JOIN siswa s ON s.`$S_ID`=ks.ks_siswa
       WHERE ks.ks_kelas=$kelas_id
       ORDER BY s.`$S_NAMA`"
    );
    json_out(['ok'=>true,'data'=>$rows]);
  }

  /* 4) daftar TP aktif */
  if ($act==='list_tp'){
    $mapel_id = (int)($_GET['mapel_id'] ?? 0);
    $tingkat  = (int)($_GET['tingkat'] ?? 0);
    $semester = (int)($_GET['semester'] ?? $SEM_AUTOMATIS);
    if ($mapel_id<=0 || $tingkat<=0) json_out(['ok'=>false,'error'=>'Parameter kurang']);

    $q = db_all($koneksi,"SELECT tp_id, tp_text
                          FROM tujuan_pembelajaran
                          WHERE mapel_id=$mapel_id AND tingkat=$tingkat
                            AND semester IN (0,$semester)
                            AND status_enum='Aktif'
                          ORDER BY tp_id");
    json_out(['ok'=>true,'data'=>$q]);
  }

  /* 5) muat nilai & TP yang tersimpan (+ deskripsi jika ada) */
  if ($act==='load'){
    ensure_deskripsi_columns($koneksi); // [DESKRIPSI] pastikan kolom ada

    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
    $mapel_id = (int)($_GET['mapel_id'] ?? 0);
    $semester = (int)($_GET['semester'] ?? $SEM_AUTOMATIS);
    $ta_id    = (int)($_GET['ta_id'] ?? $TA_ID);

    $set = db_one($koneksi,"SELECT * FROM nilai_pts_set
                            WHERE ta_id=$ta_id AND semester=$semester
                              AND kelas_id=$kelas_id AND mapel_id=$mapel_id
                            LIMIT 1");
    $pts_set_id = (int)($set['pts_set_id'] ?? 0);
    $nilai=[]; $tpmap=[];
    if ($pts_set_id){
      $nilai = db_all($koneksi,"SELECT pts_id, siswa_id, nilai, deskripsi_optimal, deskripsi_perlu
                                FROM nilai_pts WHERE pts_set_id=$pts_set_id ORDER BY pts_id");

      if ($NPT_PTS && $NPT_TP){
        $katSQL = $NPT_KAT ? " , npt.`$NPT_KAT` AS kategori " : " , 'optimal' AS kategori ";
        $rows  = db_all($koneksi,"SELECT npt.`$NPT_PTS` AS pts_id, npt.`$NPT_TP` AS tp_id $katSQL
                                  FROM nilai_pts_tp npt
                                  JOIN nilai_pts np ON np.pts_id=npt.`$NPT_PTS`
                                  WHERE np.pts_set_id=$pts_set_id");
        foreach($rows as $r){
          $pid=(int)$r['pts_id'];
          $cat=strtolower((string)($r['kategori'] ?? 'optimal'));
          if($cat!=='optimal' && $cat!=='perlu') $cat='optimal';
          $tpmap[$pid][$cat][]=(int)$r['tp_id'];
        }
      }
    }
    json_out(['ok'=>true,'set_id'=>$pts_set_id,'nilai'=>$nilai,'tpmap'=>$tpmap,'semester_auto'=>$SEM_AUTOMATIS]);
  }

  /* 6) simpan nilai + TP + DESKRIPSI */
  if ($act==='save' && !empty($_POST)){
    ensure_deskripsi_columns($koneksi); // [DESKRIPSI]

    $kelas_id = (int)($_POST['kelas_id'] ?? 0);
    $mapel_id = (int)($_POST['mapel_id'] ?? 0);
    $semester = (int)($_POST['semester'] ?? $SEM_AUTOMATIS);
    $ta_id    = (int)($_POST['ta_id'] ?? $TA_ID);

    $rows     = json_decode($_POST['rows'] ?? '[]', true);
    if (!$kelas_id || !$mapel_id) json_out(['ok'=>false,'error'=>'Scope belum lengkap']);

    mysqli_begin_transaction($koneksi);
    $err=null;

    $set = db_one($koneksi,"SELECT pts_set_id FROM nilai_pts_set
                            WHERE ta_id=$ta_id AND semester=$semester AND kelas_id=$kelas_id AND mapel_id=$mapel_id LIMIT 1");
    if ($set){
      $pts_set_id=(int)$set['pts_set_id'];
      $q="UPDATE nilai_pts_set SET updated_at=NOW() WHERE pts_set_id=$pts_set_id";
      if (!db_exec($koneksi,$q)) $err='Gagal update set';
    }else{
      if ($HAS_KKM_COL){
        $q="INSERT INTO nilai_pts_set (sekolah_id,ta_id,semester,kelas_id,mapel_id,guru_user_id,kkm,created_at,updated_at)
            VALUES (".($SEKOLAH_ID?:'NULL').",$ta_id,$semester,$kelas_id,$mapel_id,$USER_ID,NULL,NOW(),NOW())";
      } else {
        $q="INSERT INTO nilai_pts_set (sekolah_id,ta_id,semester,kelas_id,mapel_id,guru_user_id,created_at,updated_at)
            VALUES (".($SEKOLAH_ID?:'NULL').",$ta_id,$semester,$kelas_id,$mapel_id,$USER_ID,NOW(),NOW())";
      }
      if(!db_exec($koneksi,$q)) $err='Gagal membuat set';
      $pts_set_id = $err?0:(int)mysqli_insert_id($koneksi);
    }

    if (!$err && $pts_set_id>0){
      foreach((array)$rows as $r){
        $siswa_id=(int)($r['siswa_id'] ?? 0);

        // nilai integer (server guard: clamp 1..100 bila tidak null)
        if (array_key_exists('nilai',$r) && $r['nilai'] !== null && $r['nilai'] !== ''){
          $nilai = (int)round((float)$r['nilai']);
          if ($nilai > 100) $nilai = 100;
          if ($nilai < 1)   $nilai = 1;
        } else {
          $nilai = null; // kosong tetap diperbolehkan
        }

        // ceklis TP (eksklusif)
        $optimal  = array_values(array_unique(array_map('intval',(array)($r['tp_optimal'] ?? []))));
        $perlu    = array_values(array_unique(array_map('intval',(array)($r['tp_perlu']   ?? []))));
        if ($optimal && $perlu){
          $dupe = array_intersect($optimal,$perlu);
          if ($dupe){ $perlu = array_values(array_diff($perlu,$dupe)); }
        }

        // [DESKRIPSI]
        $desk_opt = isset($r['desk_opt']) ? trim($r['desk_opt']) : null;
        $desk_per = isset($r['desk_per']) ? trim($r['desk_per']) : null;

        if ($siswa_id<=0){ $err='Siswa tidak valid'; break; }

        $row = db_one($koneksi,"SELECT pts_id FROM nilai_pts WHERE pts_set_id=$pts_set_id AND siswa_id=$siswa_id LIMIT 1");
        if ($row){
          $pid=(int)$row['pts_id'];
          $q="UPDATE nilai_pts SET nilai=".($nilai===null?'NULL':$nilai).",
                                   deskripsi_optimal=".($desk_opt===''||$desk_opt===null?'NULL':'\''.mysqli_real_escape_string($koneksi,$desk_opt).'\'').",
                                   deskripsi_perlu=".($desk_per===''||$desk_per===null?'NULL':'\''.mysqli_real_escape_string($koneksi,$desk_per).'\'').",
                                   updated_at=NOW()
             WHERE pts_id=$pid";
          if(!db_exec($koneksi,$q)){ $err='Gagal update nilai/deskripsi'; break; }
        }else{
          $q="INSERT INTO nilai_pts (pts_set_id,siswa_id,nilai,deskripsi_optimal,deskripsi_perlu,created_at,updated_at)
              VALUES ($pts_set_id,$siswa_id,".($nilai===null?'NULL':$nilai).",".
                     ($desk_opt===''||$desk_opt===null?'NULL':'\''.mysqli_real_escape_string($koneksi,$desk_opt).'\'').",".
                     ($desk_per===''||$desk_per===null?'NULL':'\''.mysqli_real_escape_string($koneksi,$desk_per).'\'').",NOW(),NOW())";
          if(!db_exec($koneksi,$q)){ $err='Gagal insert nilai/deskripsi'; break; }
          $pid=(int)mysqli_insert_id($koneksi);
        }

        /* simpan TP */
        if($NPT_PTS && $NPT_TP){
          if(!db_exec($koneksi,"DELETE FROM nilai_pts_tp WHERE `pts_id`=$pid")){ $err='Gagal reset TP'; break; }

          if(!$err && $optimal){
            $values=[]; foreach($optimal as $tp){ if($tp>0) $values[]="($pid,$tp,'optimal')"; }
            if($values && !db_exec($koneksi,"INSERT INTO nilai_pts_tp (`pts_id`,`tp_id`,`kategori`) VALUES ".implode(',',$values))){ $err='Gagal simpan TP optimal'; break; }
          }
          if(!$err && $perlu){
            $values=[]; foreach($perlu as $tp){ if($tp>0) $values[]="($pid,$tp,'perlu')"; }
            if($values && !db_exec($koneksi,"INSERT INTO nilai_pts_tp (`pts_id`,`tp_id`,`kategori`) VALUES ".implode(',',$values))){ $err='Gagal simpan TP perlu'; break; }
          }
        }
      }
    }

    if ($err){ mysqli_rollback($koneksi); json_out(['ok'=>false,'error'=>$err]); }
    mysqli_commit($koneksi);
    json_out(['ok'=>true,'message'=>'Tersimpan','pts_set_id'=>$pts_set_id]);
  }

  json_out(['ok'=>false,'error'=>'Aksi tidak dikenal']);
}

/* ---------- VIEW ---------- */
$PAGE_TITLE = 'Input Nilai STS Siswa';
require_once __DIR__ . '/header.php';
?>

<div class="content-wrapper"><section class="content-header" style="margin-bottom:8px">
    <h1 style="display:flex;align-items:center;gap:10px;font-weight:800">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;background:#E6EFFF;border:1px solid #D7E6FF;color:#0B57D0">
        <i class="fa fa-check-square-o"></i>
      </span>
      <span>Input Nilai STS Siswa</span>
      <small class="label bg-light-blue" style="margin-left:8px;border-radius:999px;">Penilaian</small>
    </h1>
  </section>

  <section class="content">

    <div class="box" style="border-radius:12px; overflow:hidden;">
      <div class="box-header with-border" style="background:linear-gradient(90deg,#1976ff,#0ea5e9);color:#fff">
        <h3 class="box-title" style="font-weight:800"><i class="fa fa-sliders"></i> Filter</h3>
        <div class="box-tools pull-right"></div>
      </div>

      <div class="box-body" style="background:#f8fbff;border-top:1px solid #e6efff">
        <div class="row">
          <div class="col-lg-4 col-md-6">
            <div class="form-group">
              <label><i class="fa fa-university"></i> Pilih Kelas</label>
              <div class="input-group">
                <input id="kelasPicker" class="form-control" placeholder="Klik tombol pilih…" readonly>
                <span class="input-group-btn">
                  <button id="btnPickKelas" class="btn btn-default" type="button"><i class="fa fa-search"></i> Pilih</button>
                </span>
              </div>
              <select id="selKelas" class="form-control" style="display:none"></select>
            </div>
          </div>

          <div class="col-lg-4 col-md-6">
            <div class="form-group">
              <label><i class="fa fa-book"></i> Pilih Mapel</label>
              <div class="input-group">
                <input id="mapelPicker" class="form-control" placeholder="Klik tombol pilih…" readonly>
                <span class="input-group-btn">
                  <button id="btnPickMapel" class="btn btn-default" type="button"><i class="fa fa-search"></i> Pilih</button>
                </span>
              </div>
              <select id="selMapel" class="form-control" style="display:none"></select>
              <p class="help-block" id="helpMapel" style="margin:6px 0 0">—</p>
            </div>
          </div>

          <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="form-group">
              <label><i class="fa fa-calendar-check-o"></i> Semester</label>
              <select id="selSemester" class="form-control">
                <option value="1">1 (Ganjil)</option>
                <option value="2">2 (Genap)</option>
              </select>
            </div>
          </div>
        </div>
        <small class="text-muted">TA aktif: <b><?php echo esc($TA['ta_nama'] ?? '-'); ?></b></small>
      </div>

      <div class="box-body tips-wrap">
        <div class="tips-bar">
          <i class="fa fa-keyboard-o"></i>
          <span><b>Tips input:</b> Pindah kolom nilai lebih cepat pakai tombol <b>← ↑ → ↓</b> atau <b>Tab/Shift+Tab</b>. Untuk Menyimpan <b>(Ctrl+S)</b> dan Di Mode HP, gunakan tombol <b>Simpan</b> melayang di kanan bawah.</span>
        </div>
      </div>

      <div id="globalTPBar" class="box-body" style="display:none; background:#f5f9ff; border-top:1px dashed #dbe7ff;" aria-expanded="false">
        <div class="global-head" id="globalTPToggle" role="button" tabindex="0" aria-controls="globalTPWrap" aria-expanded="false" title="Klik untuk menampilkan/sembunyikan Aksi Massal TP">
          <i class="fa fa-magic"></i>
          <b>Aksi Massal TP</b>
          <small>(diterapkan ke <u>semua siswa</u> pada tabel di bawah)</small>
          <span class="toggle-ico" style="margin-left:auto"><i class="fa fa-chevron-right"></i></span>
        </div>
        <div id="globalTPWrap" class="global-wrap" aria-hidden="true">
          <div class="global-col">
            <div class="global-title">Optimal (Tercapai)</div>
            <div id="globOptBox"></div>
          </div>
          <div class="global-col">
            <div class="global-title">Perlu Peningkatan</div>
            <div id="globPerBox"></div>
          </div>
        </div>
      </div>

      <div class="box-body action-toolbar" style="border-top:1px dashed #dbe7ff">
        <button id="btnToggleMode" class="btn btn-resp btn-toggle mode-tp" aria-pressed="false"
                title="Ganti ke mode Deskripsi">
          <i class="fa fa-random"></i>
          <span class="txt">Mode: <b>TP</b> ⇄ <b>Edit Deskripsi</b></span>
          <span class="badge-mode" id="badgeMode">TP</span>
        </button>

        <button id="btnGenAuto" class="btn btn-resp btn-generate" title="Generate deskripsi otomatis">
          <i class="fa fa-magic"></i>
          <span class="txt">Generate Otomatis</span>
        </button>
      </div>

      <div id="tpEmptyNotice" style="display:none; border-top:1px solid #ffe8b0">
        <div class="tp-alert">
          <i class="fa fa-exclamation-triangle"></i>
          <div class="tp-alert-text">
            <b>Belum ada Tujuan Pembelajaran aktif</b> untuk kombinasi <em>kelas/tingkat</em>, <em>mapel</em>, dan <em>semester</em> ini.
            Silakan input/aktifkan TP pada menu <b>Tujuan Pembelajaran</b>.
          </div>
          <a id="tpManageLink" class="tp-alert-btn" href="tujuan_pembelajaran.php" target="_blank">Kelola TP</a>
        </div>
      </div>

      <div class="box-body no-padding" style="background:#fff">
        <div id="tableWrap" class="table-responsive">
          <table id="ptsTable" class="table table-bordered table-hover-soft table-striped" style="margin:0">
            <colgroup id="ptsCols">
              <col class="col-no">
              <col class="col-name">
              <col class="col-ids">
              <col class="col-nilai">
              <col class="col-left">
              <col class="col-right">
            </colgroup>
            <thead>
              <tr>
                <th class="th-sticky sticky-col no-col">No</th>
                <th class="th-sticky sticky-col-2 th-name">Nama Siswa</th>
                <th class="th-sticky">NISN / NIS</th>
                <th class="th-sticky">Nilai</th>
                <th class="th-sticky tp-col" id="thLeft">TP yang diukur &amp; Tercapai <em>(Optimal)</em></th>
                <th class="th-sticky tp-col" id="thRight">TP yang diukur &amp; <em>Perlu Peningkatan</em></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <div class="box-footer" style="display:flex;gap:8px;align-items:center;justify-content:flex-end;border-top:1px dashed #dbe7ff">
        <button id="btnLoad" class="btn btn-theme-ghost"><i id="icoLoad" class="fa fa-refresh"></i> Muat</button>
        <button id="btnSave" class="btn btn-theme-primary" title="Ctrl+S"><i class="fa fa-save"></i> Simpan Data Nilai</button>
      </div>

    </div>

  </section>
</div><button id="fabSave" class="fab-save" aria-label="Simpan Data Nilai" title="Simpan (Ctrl+S)">
  <i id="fabIco" class="fa fa-save"></i>
</button>

<div class="modal fade" id="pickModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:12px">
      <div class="modal-header" style="background:#0B57D0;color:#fff;border-top-left-radius:12px;border-top-right-radius:12px">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="pickTitle">Pilih</h4>
      </div>
      <div class="modal-body">
        <input id="pickSearch" class="form-control" placeholder="Ketik untuk mencari…" style="margin-bottom:10px">
        <div id="pickList" class="list-group" style="max-height:60vh;overflow:auto;margin-bottom:0"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Header tabel lengket & perapihan garis */
.th-sticky{ position: sticky; top: 0; z-index: 2; background:#0B57D0; color:#fff; vertical-align:middle; }
#ptsTable thead th{ border-color:#e6efff !important; border-right:1px solid #e6efff !important; white-space:nowrap; }
#ptsTable thead th:first-child{ border-left:1px solid #e6efff !important; }
#ptsTable thead tr{ border-bottom:1px solid #e6efff; }

/* ====== STABILISASI LEBAR KOLOM ====== */
#ptsTable{ width:100%; table-layout: fixed; } /* kunci layout */
#ptsTable col.col-no    { width:38px; }
#ptsTable col.col-name  { width:160px; }
#ptsTable col.col-ids   { width:140px; }
#ptsTable col.col-nilai { width:85px; }
#ptsTable col.col-left  { width:250px; }
#ptsTable col.col-right { width:250px; }

/* Pastikan sel nama konsisten */
#ptsTable .th-name, #ptsTable .td-name{ overflow:hidden; text-overflow:ellipsis; white-space:wrap; }

/* Kolom TP/Deskripsi isi full tanpa merubah lebar tabel */
.tp-col{ min-width:360px }
.tp-box{border:1px dashed #e5e7eb;border-radius:8px;padding:8px}
.tp-toolbar{display:flex;gap:10px;margin-bottom:6px;font-size:12px;color:#64748b}
.tp-toolbar a{cursor:pointer;text-decoration:underline}
.tp-list{display:flex;flex-direction:column;gap:6px;max-height:220px;overflow:auto;overflow-x:hidden;padding-right:6px}
.tp-item{display:flex;align-items:flex-start;gap:8px;line-height:1.4;font-weight:400;}
.tp-item input{transform:translateY(2px)}
.tp-item span{ white-space:normal; word-break:break-word; overflow-wrap:anywhere; }

.value-input{width:70px}

/* [DESKRIPSI] editor */
.desk-box{border:1px dashed #cfe1ff;border-radius:8px;padding:8px;background:#f7fbff}
.desk-ta{width:100%;min-height:112px;border:1px solid #dbe7ff;border-radius:8px;padding:8px;resize:vertical}
.desk-hint{font-size:12px;color:#64748b;margin-top:4px}

/* Validasi input nilai: flash merah */
.inp-nilai.inp-invalid{ border-color:#ef4444 !important; background:#fff5f5; box-shadow: 0 0 0 2px rgba(239,68,68,.15) inset; transition: background .15s ease; }

/* Blok kosong TP */
.tp-empty-td{ background:#FFF8E7; }
.tp-empty{
  display:flex; align-items:flex-start; gap:10px;
  background:#FFF8E7; border:1px dashed #F5C97B; color:#7A4A00;
  padding:10px; border-radius:10px; font-size:13px;
}
.tp-empty .tp-empty-icon{ width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#FFEFC7; border:1px solid #FAD48D; }
.tp-empty b{font-weight:700}

/* Notice bar TP kosong */
#tpEmptyNotice .tp-alert{
  display:flex; align-items:center; gap:12px; padding:12px 14px;
  background:linear-gradient(0deg,#FFF5DB,#FFF); border-bottom:1px solid #FFE4A3;
}
.tp-alert i{ color:#B45309; font-size:16px }
.tp-alert-text{ color:#7A4A00 }
.tp-alert-btn{
  margin-left:auto; padding:8px 12px; border-radius:999px; text-decoration:none;
  background:#0EA5E9; color:#fff !important; border:1px solid #0EA5E9;
  box-shadow:0 1px 0 rgba(14,165,233,.35);
}
.tp-alert-btn:hover{ filter:brightness(.95); }

/* Tombol tema */
.btn-theme-primary{
  background:linear-gradient(90deg,#2563EB,#0EA5E9);
  color:#fff; border:none; border-radius:12px; padding:10px 16px; font-weight:700;
  box-shadow:0 6px 14px rgba(37,99,235,.18);
}
.btn-theme-primary:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(14,165,233,.22); }
.btn-theme-ghost{
  background:#fff; color:#0B57D0; border:1px solid #BFD7FF; border-radius:12px; padding:10px 16px; font-weight:700;
}
.btn-theme-ghost:hover{ background:#F3F8FF; }

/* Hover baris */
.table-hover-soft tbody tr:hover{ background:#F0F7FF !important; transition:background .15s ease-in-out; }

/* Unify scroll */
#tableWrap{ border-top:1px solid #eaeefb; overflow:visible; max-height:none; }

/* Tips kecil */
.tips-wrap{ padding-top:0; padding-bottom:0 }
.tips-bar{
  display:flex; align-items:center; gap:8px;
  font-size:12px; color:#0B57D0; line-height:1.4;
  background:linear-gradient(90deg,#EAF2FF,#F6FAFF);
  border:1px solid #D7E6FF; border-radius:10px;
  padding:8px 10px; box-shadow: inset 0 0 0 1px #F0F6FF;
  margin-top:-6px; margin-bottom:8px;
}
.tips-bar i{ font-size:13px; }

/* ===== GLOBAL TOOLBAR (collapsible) ===== */
#globalTPBar .global-head{ display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#0B57D0; cursor:pointer; user-select:none; }
#globalTPBar .global-head small{ color:#5b7ccf; font-weight:500 }
#globalTPBar .toggle-ico i{ transition: transform .2s ease; }
#globalTPBar.open .toggle-ico i{ transform: rotate(90deg); }
#globalTPBar .global-wrap{ display:grid; grid-template-columns:1fr 1fr; gap:12px; max-height:0; overflow:hidden; opacity:0; transition:max-height .25s ease, opacity .2s ease; }
#globalTPBar.open .global-wrap{ max-height:1000px; opacity:1; }
#globalTPBar .global-col{ background:#fff; border:1px solid #e6efff; border-radius:10px; padding:10px }
#globalTPBar .global-title{ font-weight:700; margin-bottom:6px; color:#0B57D0 }

/* Toast elegan melayang */
#epsToastStack{ position: fixed; right: 16px; top: 16px; z-index: 99999; display:flex; flex-direction:column; gap:10px; }
.eps-toast{
  min-width: 240px; max-width: 360px;
  background: linear-gradient(135deg,#2563EB,#0EA5E9);
  color:#fff; border-radius:12px; padding:10px 12px;
  box-shadow: 0 14px 28px rgba(14,165,233,.28), inset 0 0 0 1px rgba(255,255,255,.18);
  display:flex; align-items:flex-start; gap:10px; opacity:0; transform: translateY(-8px);
  transition: opacity .2s ease, transform .2s ease;
}
.eps-toast.show{ opacity:1; transform: translateY(0); }
.eps-toast .ico{
  width:22px; height:22px; border-radius:8px; display:flex; align-items:center; justify-content:center;
  background: rgba(255,255,255,.18); box-shadow: inset 0 0 0 1px rgba(255,255,255,.22);
}
.eps-toast .msg{ font-size:13px; line-height:1.35; flex:1; }
.eps-toast.success{ background: linear-gradient(135deg,#22c55e,#16a34a); }
.eps-toast.error{ background: linear-gradient(135deg,#ef4444,#dc2626); }
.eps-toast.warning{ background: linear-gradient(135deg,#f59e0b,#f97316); }

/* ===== Toolbar tombol responsif + badge mode ===== */
.action-toolbar{ display:flex; gap:10px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
.btn-resp{ display:inline-flex; align-items:center; gap:10px; font-weight:700; line-height:1; border:none; border-radius:999px; padding: clamp(8px, 1.2vw, 12px) clamp(12px, 2.2vw, 18px); font-size: clamp(12px, 1.8vw, 15px); color:#fff; box-shadow:0 8px 20px rgba(0,0,0,.08), inset 0 0 0 1px rgba(255,255,255,.14); transition: background-position .35s ease, transform .12s ease, box-shadow .12s ease, filter .12s ease; }
.btn-resp .fa{ font-size:1.05em }
.btn-resp:hover{ transform:translateY(-1px); filter:brightness(.98); box-shadow:0 10px 22px rgba(0,0,0,.10), inset 0 0 0 1px rgba(255,255,255,.18); }
.btn-resp:active{ transform:translateY(0); }

/* Toggle base + micro-interaction */
.btn-toggle{ position:relative; overflow:hidden; background-size:200% 100%; }
.btn-toggle:hover{ background-position:100% 0; }
.btn-toggle::after{ content:""; position:absolute; inset:-1px; pointer-events:none; background: radial-gradient(600px 120px at var(--mx,70%) 50%, rgba(255,255,255,.18), transparent 65%); opacity:0; transition:opacity .2s ease; }
.btn-toggle:hover::after{ opacity:1; }
.btn-toggle:focus-visible{ outline:2px solid rgba(3,105,161,.44); outline-offset:2px; }
.btn-toggle.mode-tp:focus-visible{ outline-color: rgba(34,197,94,.55); }
.btn-toggle.mode-desk:focus-visible{ outline-color: rgba(251,146,60,.55); }

.badge-mode{ margin-left:4px; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.28); border-radius:999px; padding:.18rem .5rem; font-weight:800; letter-spacing:.3px; font-size: clamp(10px, 1.5vw, 12px); }
.btn-toggle.mode-tp{ background:linear-gradient(90deg,#22C55E,#16A34A); box-shadow:0 8px 20px rgba(34,197,94,.20), inset 0 0 0 1px rgba(255,255,255,.18); }
/* === NEW THEME for DESK: Orange Soft === */
.btn-toggle.mode-desk{ background:linear-gradient(90deg,#F59E0B,#FB923C); box-shadow:0 8px 20px rgba(251,146,60,.22), inset 0 0 0 1px rgba(255,255,255,.18); }
.btn-toggle.mode-desk .badge-mode{ background:rgba(255,255,255,.22); border-color:rgba(255,255,255,.35); }

/* Tombol Generate */
.btn-generate{ background:linear-gradient(90deg,#0EA5E9,#2563EB); }

/* Kompres otomatis di layar kecil */
@media (max-width: 540px){ .btn-resp{ gap:8px; } .btn-resp .txt{ display:none; } }

/* Aksesibilitas: kurangi animasi jika user minta */
@media (prefers-reduced-motion: reduce){ .btn-resp{ transition:none; } .btn-toggle{ background-size:100% 100%; } .btn-toggle::after{ display:none; } }

/* Segmented control (masih ada untuk kompatibilitas, tidak dipakai default) */
.seg-toggle{ position:relative; display:grid; grid-template-columns:1fr 1fr; gap:4px; width: clamp(220px, 40vw, 360px); background:#EAF2FF; border:1px solid #D7E6FF; border-radius:999px; padding:4px; box-shadow: inset 0 0 0 1px #F0F6FF; }
.seg-toggle .seg-item{ position:relative; z-index:2; appearance:none; background:transparent; border:0; padding:8px 14px; border-radius:999px; font-weight:800; color:#0B57D0; display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer; }
.seg-toggle .seg-item .seg-txt-compact{ display:none; }
.seg-toggle .seg-item.active{ color:#fff; }
.seg-toggle .seg-item:focus-visible{ outline:2px solid rgba(3,105,161,.5); outline-offset:2px; }
.seg-toggle .seg-thumb{ position:absolute; z-index:1; top:4px; bottom:4px; left:4px; width: calc(50% - 4px); border-radius:999px; background: linear-gradient(90deg,#22C55E,#16A34A); box-shadow:0 8px 20px rgba(22,163,74,.20), inset 0 0 0 1px rgba(255,255,255,.22); transform: translateX(0%); transition: transform .18s ease, background .18s ease, box-shadow .18s ease; }
.seg-toggle.is-desk .seg-thumb{ transform: translateX(100%); background: linear-gradient(90deg,#F59E0B,#FB923C); box-shadow:0 8px 20px rgba(251,146,60,.22), inset 0 0 0 1px rgba(255,255,255,.22); }
.seg-toggle.is-tp   .seg-item[data-mode="tp"],
.seg-toggle.is-desk .seg-item[data-mode="desk"]{ color:#fff; }

/* Responsive */
@media (max-width: 1200px){
  #ptsTable thead th.sticky-col{ position: sticky; left: 0; z-index: 6; }
  #ptsTable thead th.sticky-col-2{ position: sticky; left: 54px; z-index: 6; }
  #ptsTable tbody td.sticky-col{ position: sticky; left: 0; z-index: 3; background:#fff; box-shadow: 1px 0 0 #e6efff inset; }
  #ptsTable tbody td.sticky-col-2{ position: sticky; left: 54px; z-index: 3; background:#fff; box-shadow: 1px 0 0 #e6efff inset; }
  #tableWrap{ overflow-x:auto; overflow-y:visible; -webkit-overflow-scrolling:touch; }
  #ptsTable{ min-width:980px; }
}
@media (max-width: 992px){
  .tp-col{min-width:280px}
  .value-input{width:72px}
  #tableWrap{ overflow-x:auto; overflow-y:visible; -webkit-overflow-scrolling:touch; }
  #ptsTable{ min-width:900px; }
  #ptsTable th.th-name, #ptsTable td.td-name{ min-width: max(200px, 40vw); }
}
@media (max-width: 768px){
  #ptsTable td, #ptsTable th{font-size:12.5px}
  .tp-col{min-width:144px}
  .tp-list{ gap:4px; }
  .tp-item{ font-size:12px; }
  .tp-item span{ white-space:normal; word-break: break-word; }
  #ptsTable th.th-name, #ptsTable td.td-name{ min-width: max(96px, 19.2vw) !important; white-space: normal; word-break: break-word; }
  #ptsTable th:nth-child(3), #ptsTable td:nth-child(3){ min-width:117px !important; }
  #ptsTable thead th.no-col, #ptsTable tbody td.no-col{ position: static !important; left:auto !important; z-index:auto !important; box-shadow:none !important; }
  #ptsTable thead th.sticky-col-2{ position: sticky; left: 0 !important; z-index: 6; }
  #ptsTable tbody td.sticky-col-2{ position: sticky; left: 0 !important; z-index: 3; background:#fff; box-shadow: 1px 0 0 #e6efff inset; }
  #tableWrap{ overflow-x:auto; overflow-y:visible; -webkit-overflow-scrolling:touch; }
  #ptsTable{ min-width:820px; }
}

/* FAB Save */
.fab-save{ position: fixed; right: 18px; bottom: 22px; width: 62px; height: 62px; border-radius: 50%; display: none; align-items: center; justify-content: center; background: linear-gradient(135deg,#2563EB,#0EA5E9); color:#fff; border:0; box-shadow: 0 14px 28px rgba(14,165,233,.32), inset 0 0 0 1px rgba(255,255,255,.18); z-index: 9999; }
.fab-save .fa{ font-size:22px }
.fab-save:active{ transform: translateY(1px) scale(.98); }
.fab-save[disabled]{ opacity:.6; cursor:not-allowed; }
@media (max-width:768px){ .fab-save{ display:inline-flex; } }

/* Prefer reduced motion */
@media (prefers-reduced-motion: reduce){ .btn-resp{ transition:none; } }
</style>

<script>
(function(){
  var $ = window.jQuery; if(!$) return;

  var taId = <?php echo (int)$TA_ID; ?>;
  var semesterAuto = <?php echo (int)$SEM_AUTOMATIS; ?>;
  var isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

  var kelas=[], mapel=[], siswa=[], tps=[];
  var nilaiMap={}, tpMap={}, setId=0;

  // ===== UI TP STATE per siswa =====
  var uiTP = Object.create(null);

  // ===== Auto-save state =====
  var changedRows = new Set();
  var autosaveTimer = null;
  var autoCooldownUntil = 0;

  var globalTPOpen = false;
  function applyGlobalTPState(){
    var $bar = $('#globalTPBar');
    $bar.toggleClass('open', globalTPOpen);
    $bar.attr('aria-expanded', globalTPOpen ? 'true' : 'false');
    $('#globalTPToggle').attr('aria-expanded', globalTPOpen ? 'true' : 'false');
    $('#globalTPWrap').attr('aria-hidden', globalTPOpen ? 'false' : 'true');
  }

  // ===== Toast helpers =====
  function ensureToastStack(){
    if (!document.getElementById('epsToastStack')){
      var d=document.createElement('div'); d.id='epsToastStack'; document.body.appendChild(d);
    }
  }
  function showToast(msg,type){
    ensureToastStack();
    var stack=document.getElementById('epsToastStack');
    var t=document.createElement('div');
    var ttype=(type||'info').toLowerCase();
    if(['success','error','warning','info'].indexOf(ttype)<0) ttype='info';
    t.className='eps-toast '+ttype;
    var icon='info-circle';
    if(ttype==='success') icon='check';
    else if(ttype==='error') icon='times';
    else if(ttype==='warning') icon='exclamation';
    t.innerHTML='<div class="ico"><i class="fa fa-'+icon+'"></i></div><div class="msg">'+(msg||'')+'</div>';
    stack.appendChild(t);
    requestAnimationFrame(function(){ t.classList.add('show'); });
    setTimeout(function(){ t.classList.remove('show'); setTimeout(function(){ if(t && t.parentNode) t.parentNode.removeChild(t); },200); }, 2200);
  }
  function toast(msg,type){
    if (window.Swal && Swal.mixin){
      Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:2200, timerProgressBar:true})
          .fire({icon:(type||'info'), title: msg});
    }else{
      showToast(msg,type);
    }
  }

  function detectTingkat(kelasNama){ var m=(kelasNama||'').match(/(\d{1,2})/); return m?parseInt(m[1],10):7; }
  function escapeHtml(s){ return (''+s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

  /* ====== MODE ====== */
  var viewMode = 'tp'; // 'tp' | 'desk'
  function setMode(mode){
    viewMode = mode;
    if(mode==='tp'){
      $('#thLeft').html('TP yang diukur &amp; Tercapai <em>(Optimal)</em>');
      $('#thRight').html('TP yang diukur &amp; <em>Perlu Peningkatan</em>');
    }else{
      $('#thLeft').html('Deskripsi Capaian Tertinggi');
      $('#thRight').html('Deskripsi Capaian Terendah');
    }
    // update tampilan tombol toggle (warna + badge)
    var isTP = (mode === 'tp');
    var $btn = $('#btnToggleMode');
    $btn.toggleClass('mode-tp', isTP).toggleClass('mode-desk', !isTP);
    $('#badgeMode').text(isTP ? 'TP' : 'DESK');
    $btn.attr('title', isTP ? 'Ganti ke mode Deskripsi' : 'Ganti ke mode TP')
        .attr('aria-pressed', isTP ? 'false' : 'true');

    renderTable();
  }

  /* --- HELPERS UI TP --- */
  function uniqIntArr(arr){
    var m={}, o=[]; (arr||[]).forEach(function(v){ v=parseInt(v,10)||0; if(v>0 && !m[v]){ m[v]=1; o.push(v); } }); return o;
  }
  function getUiTP(sid){
    sid = parseInt(sid,10)||0;
    if(!sid) return {opt:[],per:[]};
    if(!uiTP[sid]) uiTP[sid] = {opt:[], per:[]};
    return uiTP[sid];
  }
  function updateUiFromRow($tr){
    var sid = +$tr.data('sid')||0; if(!sid) return;
    var o=[], p=[];
    $tr.find('input.opt-chk:checked').each(function(){ o.push(+$(this).data('tp')); });
    $tr.find('input.per-chk:checked').each(function(){ p.push(+$(this).data('tp')); });
    var dup = o.filter(function(x){ return p.indexOf(x)>=0; });
    if(dup.length) p = p.filter(function(x){ return dup.indexOf(x)<0; });
    uiTP[sid] = {opt: uniqIntArr(o), per: uniqIntArr(p)};
  }
  function selectedFromUi(sid, kind){
    var st = getUiTP(sid);
    return kind==='opt' ? (st.opt||[]) : (st.per||[]);
  }
  function resetUiTpForNewTps(){
    var allow = {}; (tps||[]).forEach(function(t){ allow[+t.tp_id]=1; });
    Object.keys(uiTP).forEach(function(sid){
      uiTP[sid].opt = (uiTP[sid].opt||[]).filter(function(x){ return allow[x]; });
      uiTP[sid].per = (uiTP[sid].per||[]).filter(function(x){ return allow[x]; });
    });
  }

  /* --- BUILDERS --- */
  function buildTPBox(kind, cls, selected, isGlobal){
    selected = uniqIntArr(selected || []);

    var html = '<div class="tp-box tp-box-'+kind+'" data-kind="'+kind+'" data-cls="'+cls+'" '+(isGlobal?'data-global="1"':'')+'>';
    html += '<div class="tp-toolbar">';
    html += '<a class="tp-act" data-act="all">Semua</a>';
    html += '<a class="tp-act" data-act="none">Kosong</a>';
    html += '<a class="tp-act" data-act="toggle">Balik</a>';
    if (kind==='opt') html += '<a class="tp-act" data-act="move" data-to="per">→ Perlu</a>';
    if (kind==='per') html += '<a class="tp-act" data-act="move" data-to="opt">→ Optimal</a>';
    html += '</div><div class="tp-list">';

    for (var i=0;i<tps.length;i++){
      var tp=tps[i];
      var tid = parseInt(tp.tp_id, 10) || 0;
      var checked = (selected.indexOf(tid) !== -1) ? 'checked' : '';
      html+='<label class="tp-item"><input type="checkbox" class="'+cls+'" data-tp="'+tid+'" '+checked+'> <span>'+escapeHtml(tp.tp_text)+'</span></label>';
    }
    html+='</div></div>';
    return html;
  }
  function buildTPEmpty(){
    return ''+
      '<div class="tp-empty">'+
        '<div class="tp-empty-icon"><i class="fa fa-info"></i></div>'+
        '<div><b>Belum ada TP aktif</b><br><small>Silakan input TP di menu Tujuan Pembelajaran untuk mapel & tingkat ini.</small></div>'+
      '</div>';
  }

  /* [DESKRIPSI] editor kiri/kanan */
  function buildDeskBox(_label, cls, text){
    var html = ''+
      '<div class="desk-box">'+
        '<textarea class="desk-ta '+cls+'" placeholder="Tulis/hasil generate deskripsi di sini…">'+escapeHtml(text||'')+'</textarea>'+
        '<div class="desk-hint">Tip: Klik <b>Generate Otomatis</b> untuk mengisi berdasarkan checklist TP.</div>'+
      '</div>';
    return html;
  }

  /* --- GLOBAL TP BAR --- */
  function renderGlobalTP(){
    if (!tps || !tps.length){
      $('#globalTPBar').hide();
      $('#globOptBox').empty(); $('#globPerBox').empty();
      return;
    }
    $('#globOptBox').html(buildTPBox('opt','gopt-chk',[], true));
    $('#globPerBox').html(buildTPBox('per','gper-chk',[], true));
    $('#globalTPBar').show();
    applyGlobalTPState();
  }
  function fixGlobalExclusivity(prefer){
    var $bar = $('#globalTPBar');
    var seen = {};
    $bar.find('input.gopt-chk, input.gper-chk').each(function(){
      var tid = String($(this).data('tp'));
      seen[tid] = true;
    });
    Object.keys(seen).forEach(function(tid){
      var $o = $bar.find('input.gopt-chk[data-tp="'+tid+'"]');
      var $p = $bar.find('input.gper-chk[data-tp="'+tid+'"]');
      if ($o.prop('checked') && $p.prop('checked')){
        if (prefer === 'per') { $o.prop('checked', false); }
        else { $p.prop('checked', false); }
      }
    });
  }
  function propagateAllFromGlobal(){
    var gopt={}, gper={};
    $('#globalTPBar input.gopt-chk').each(function(){ gopt[String($(this).data('tp'))]=this.checked; });
    $('#globalTPBar input.gper-chk').each(function(){ gper[String($(this).data('tp'))]=this.checked; });
    $('#ptsTable tbody tr').each(function(){
      var $row=$(this);
      Object.keys(gopt).forEach(function(tid){
        var on=gopt[tid];
        $row.find('input.opt-chk[data-tp="'+tid+'"]').prop('checked', on);
        if (on){ $row.find('input.per-chk[data-tp="'+tid+'"]').prop('checked', false); }
      });
      Object.keys(gper).forEach(function(tid){
        var on=gper[tid];
        $row.find('input.per-chk[data-tp="'+tid+'"]').prop('checked', on);
        if (on){ $row.find('input.opt-chk[data-tp="'+tid+'"]').prop('checked', false); }
      });
      updateUiFromRow($row);
    });
  }
  $('#globalTPBar').on('change','input.gopt-chk, input.gper-chk', function(){
    var tid=String($(this).data('tp')), checked=this.checked;
    var isOpt=$(this).hasClass('gopt-chk');
    if (isOpt && checked){ $('#globalTPBar input.gper-chk[data-tp="'+tid+'"]').prop('checked', false); }
    else if (!isOpt && checked){ $('#globalTPBar input.gopt-chk[data-tp="'+tid+'"]').prop('checked', false); }
    fixGlobalExclusivity(isOpt ? 'opt' : 'per');
    propagateAllFromGlobal();
  });
  $('#globalTPBar').on('click','.tp-act',function(){
    var $btn=$(this), act=$btn.data('act');
    var $box=$btn.closest('.tp-box');
    var cls=$box.data('cls');
    var fromKind = $box.data('kind');
    var $checks=$box.find('input.'+cls);
    if (act==='all'){ $checks.prop('checked',true); if (fromKind==='opt'){ $('#globalTPBar input.gper-chk').prop('checked',false); } if (fromKind==='per'){ $('#globalTPBar input.gopt-chk').prop('checked',false); } }
    else if (act==='none'){ $checks.prop('checked',false); }
    else if (act==='toggle'){ $checks.each(function(){ $(this).prop('checked', !this.checked); }); }
    else if (act==='move'){
      var to=$btn.data('to');
      var $target=$('#globalTPBar .tp-box[data-kind="'+to+'"]')
        .first();
      var tcls=$target.data('cls');
      var $tChecks=$target.find('input.'+tcls);
      $checks.filter(':checked').each(function(){
        var tid=$(this).data('tp');
        $(this).prop('checked',false);
        $tChecks.filter('[data-tp="'+tid+'"]').prop('checked',true);
      });
    }
    fixGlobalExclusivity(fromKind);
    propagateAllFromGlobal();
  });
  $('#globalTPBar').on('click', '#globalTPToggle', function(){ globalTPOpen = !globalTPOpen; applyGlobalTPState(); })
                   .on('keydown', '#globalTPToggle', function(e){ if (e.key === 'Enter' || e.key === ' ' || e.code === 'Space'){ e.preventDefault(); globalTPOpen = !globalTPOpen; applyGlobalTPState(); } });

  /* --- Picker Modal --- */
  var pickType='kelas';
  function openPicker(type){
    pickType=type;
    var items = (type==='kelas'?kelas:mapel);
    $('#pickTitle').text('Pilih ' + (type==='kelas'?'Kelas':'Mata Pelajaran'));
    rebuildPickList(items);
    $('#pickModal').modal('show');
    setTimeout(function(){ $('#pickSearch').val('').focus(); }, 300);
  }
  function rebuildPickList(items, q){
    q=(q||'').toLowerCase();
    var $list = $('#pickList').empty();
    (items||[]).forEach(function(it){
      var name = it.nama || it.kelas_nama || '';
      if(q && name.toLowerCase().indexOf(q)<0) return;
      var id = it.id || it.kelas_id;
      $list.append('<a class="list-group-item pickable" data-id="'+id+'" data-name="'+escapeHtml(name)+'">'+escapeHtml(name)+'</a>');
    });
    if (!$list.children().length){ $list.append('<div class="list-group-item text-muted">Tidak ada data.</div>'); }
  }
  $('#btnPickKelas').on('click', function(){ openPicker('kelas'); });
  $('#btnPickMapel').on('click', function(){ openPicker('mapel'); });
  $('#pickSearch').on('input', function(){ var items=(pickType==='kelas'?kelas:mapel); rebuildPickList(items, this.value); });
  $('#pickList').on('click', '.pickable', function(){
    var id=$(this).data('id'), name=$(this).data('name');
    if (pickType==='kelas'){ $('#selKelas').val(String(id)).trigger('change'); $('#kelasPicker').val(name); }
    else{ $('#selMapel').val(String(id)).trigger('change'); $('#mapelPicker').val(name); }
    $('#pickModal').modal('hide');
  });
  function initHiddenSelect($sel, items){
    $sel.empty();
    (items||[]).forEach(function(it){
      $sel.append('<option value="'+(it.id||it.kelas_id)+'">'+escapeHtml(it.nama||it.kelas_nama||'')+'</option>');
    });
  }

  /* --- LOADERS --- */
  function loadScope(){
    return $.getJSON('nilai_pts.php?action=scope').then(function(r){
      if(!r||!r.ok) throw new Error('scope fail');
      kelas=r.kelas||[]; mapel=r.mapel||[];
      semesterAuto = +r.semester_auto || 1;
      isAdmin = !!r.is_admin;

      initHiddenSelect($('#selKelas'), kelas);
      initHiddenSelect($('#selMapel'), mapel);

      $('#selSemester').val(String(semesterAuto));

      $('#helpMapel').text(isAdmin ? 'Admin: menampilkan semua mata pelajaran.' : 'Mapel ditampilkan sesuai pengampu akun ini (TA aktif).');

      if(kelas.length){ $('#selKelas').val(String(kelas[0].kelas_id)); $('#kelasPicker').val(kelas[0].kelas_nama||''); }
      return loadMapelForKelas();
    }).fail(function(){ toast('Gagal memuat scope', 'error'); });
  }
  function loadMapelForKelas(){
    var kid=+$('#selKelas').val()||0;
    return $.getJSON('nilai_pts.php?action=list_mapel&kelas_id='+kid).then(function(r){
      mapel=(r&&r.ok)?(r.data||[]):[];
      initHiddenSelect($('#selMapel'), mapel);
      if(mapel.length){ $('#selMapel').val(String(mapel[0].id)); $('#mapelPicker').val(mapel[0].nama||''); }
      else { $('#selMapel').val(''); $('#mapelPicker').val(''); }
      return doLoadAll();
    }).fail(function(){ toast('Gagal memuat mapel', 'error'); });
  }
  function loadSiswa(){
    var kid=+$('#selKelas').val()||0;
    if(!kid){ siswa=[]; renderTable(); return $.Deferred().resolve().promise(); }
    return $.getJSON('nilai_pts.php?action=list_siswa&kelas_id='+kid).then(function(r){
      siswa=(r&&r.ok)?(r.data||[]):[]; renderTable();
    }).fail(function(){ toast('Gagal memuat siswa', 'error'); });
  }
  function loadTP(){
    var kid=+$('#selKelas').val()||0, mid=+$('#selMapel').val()||0, smt=parseInt($('#selSemester').val(),10)||semesterAuto||1;
    if(!kid||!mid){ tps=[]; renderTable(); renderGlobalTP(); updateTPNotice(); return $.Deferred().resolve().promise(); }
    var tingkat=detectTingkat($('#kelasPicker').val() || $('#selKelas option:selected').text());
    return $.getJSON('nilai_pts.php?action=list_tp&mapel_id='+mid+'&tingkat='+tingkat+'&semester='+smt).then(function(r){
      tps=(r&&r.ok)?(r.data||[]):[]; resetUiTpForNewTps(); renderTable(); renderGlobalTP(); updateTPNotice();
    }).fail(function(){ toast('Gagal memuat TP', 'error'); });
  }
  function loadExisting(){
    var kid=+$('#selKelas').val()||0, mid=+$('#selMapel').val()||0, smt=parseInt($('#selSemester').val(),10)||semesterAuto||1;
    if(!kid||!mid) return;
    return $.getJSON('nilai_pts.php?action=load&kelas_id='+kid+'&mapel_id='+mid+'&semester='+smt+'&ta_id='+taId)
      .then(function(r){
        setId=(r&&r.ok)?(r.set_id||0):0; nilaiMap={}; tpMap={};
        if (r && r.ok && r.nilai){
          r.nilai.forEach(function(n){
            nilaiMap[n.siswa_id]={pts_id:n.pts_id, nilai:n.nilai, desk_opt:n.deskripsi_optimal||'', desk_per:n.deskripsi_perlu||''};
          });
        }
        if (r && r.ok && r.tpmap){ tpMap=r.tpmap; }

        // seed uiTP
        uiTP = Object.create(null);
        var pidToSid = {};
        Object.keys(nilaiMap).forEach(function(sid){
          var pid = nilaiMap[sid].pts_id;
          if(pid) pidToSid[pid] = +sid;
        });
        Object.keys(tpMap).forEach(function(pid){
          var sid = pidToSid[pid]; if(!sid) return;
          var opt = uniqIntArr(tpMap[pid].optimal||[]);
          var per = uniqIntArr(tpMap[pid].perlu||[]);
          var dup = opt.filter(function(x){ return per.indexOf(x)>=0; });
          if(dup.length) per = per.filter(function(x){ return dup.indexOf(x)<0; });
          uiTP[sid] = {opt: opt, per: per};
        });

        changedRows.clear();
        renderTable();
      }).fail(function(){ toast('Gagal memuat nilai tersimpan', 'error'); });
  }
  function updateTPNotice(){
    var kid=+$('#selKelas').val()||0, mid=+$('#selMapel').val()||0, smt=parseInt($('#selSemester').val(),10)||semesterAuto||1;
    var tingkat=detectTingkat($('#kelasPicker').val() || $('#selKelas option:selected').text());
    var href='tujuan_pembelajaran.php';
    if(mid>0 && tingkat>0){ href+='?mapel_id='+mid+'&tingkat='+tingkat+'&semester='+smt; }
    $('#tpManageLink').attr('href', href).attr('target','_blank');
    if ((tps||[]).length === 0){ $('#tpEmptyNotice').show(); } else { $('#tpEmptyNotice').hide(); }
  }

  /* --- RENDER & EXCLUSIVE CHECKS --- */
  function enforceRowExclusive($tr){
    $tr.find('input.opt-chk:checked').each(function(){
      var id=$(this).data('tp');
      $tr.find('input.per-chk[data-tp="'+id+'"]').prop('checked',false);
    });
  }

  // Flash merah singkat pada input nilai
  function flashInvalid(inputEl){
    var $el=$(inputEl);
    $el.addClass('inp-invalid');
    setTimeout(function(){ $el.removeClass('inp-invalid'); }, 900);
  }

  function renderTable(){
    var $tb=$('#ptsTable tbody').empty();
    if(!siswa.length){
      $tb.append('<tr><td colspan="6" class="text-center text-muted" style="padding:18px">Pilih kelas & mapel, lalu klik <b>Muat</b>.</td></tr>');
      return;
    }
    var tpsKosong = (tps||[]).length===0;

    for(var i=0;i<siswa.length;i++){
      var s=siswa[i], N=i+1, existing=nilaiMap[s.siswa_id]||{}, pid=existing.pts_id||0;

      var nilaiDisplay = (existing.nilai!=null && existing.nilai!=='') ? String(Math.round(parseFloat(existing.nilai))) : '';
      var optSel = selectedFromUi(s.siswa_id,'opt');
      var perSel = selectedFromUi(s.siswa_id,'per');

      if (optSel && perSel){
        var dupe=optSel.filter(function(x){return perSel.indexOf(x)>=0;});
        if (dupe.length){ perSel = perSel.filter(function(x){return dupe.indexOf(x)<0;}); }
      }

      var nis=[]; if(s.nisn) nis.push(escapeHtml(s.nisn)); if(s.nis) nis.push(escapeHtml(s.nis)); var nisStr=nis.length?nis.join(' / '):'-';

      var leftHtml='', rightHtml='', leftClass='', rightClass='';
      if(viewMode==='tp'){
        leftHtml  = tpsKosong ? buildTPEmpty() : buildTPBox('opt','opt-chk',optSel);
        rightHtml = tpsKosong ? buildTPEmpty() : buildTPBox('per','per-chk',perSel);
        leftClass  = tpsKosong ? ' class="tp-empty-td"' : '';
        rightClass = tpsKosong ? ' class="tp-empty-td"' : '';
      }else{
        leftHtml  = buildDeskBox('Deskripsi Capaian Tertinggi','txt-desk-tinggi', existing.desk_opt||'');
        rightHtml = buildDeskBox('Deskripsi Capaian Terendah','txt-desk-rendah', existing.desk_per||'');
      }

      var tr='<tr data-sid="'+s.siswa_id+'">'+
        '<td class="text-center sticky-col no-col">'+N+'</td>'+
        '<td class="sticky-col-2 td-name">'+escapeHtml(s.nama)+'</td>'+
        '<td>'+nisStr+'</td>'+
        '<td><input type="number" step="1" inputmode="numeric" min="1" max="100" pattern="[0-9]*" class="form-control input-sm inp-nilai value-input" value="'+nilaiDisplay+'" maxlength="3"></td>'+
        '<td'+leftClass+'>'+leftHtml+'</td>'+
        '<td'+rightClass+'>'+rightHtml+'</td>'+
      '</tr>';

      var $tr=$(tr);
      $tb.append($tr);
      if(viewMode==='tp' && !tpsKosong) enforceRowExclusive($tr);
    }

    // VALIDASI INPUT NILAI 1–100
    var overToastTs = 0;
    $('#ptsTable').off('focus.storeprev','.inp-nilai').on('focus.storeprev','.inp-nilai', function(){
      this.dataset.prev = this.value || '';
    });

    $('#ptsTable').off('input.onlyInt','.inp-nilai').on('input.onlyInt','.inp-nilai', function(){
      var prev = this.dataset.prev || '';
      var v = (this.value || '').replace(/\D/g,'');

      if (v.length > 3){
        flashInvalid(this);
        v = v.slice(0,3);
      }
      if (v.length === 3 && v !== '100'){
        flashInvalid(this);
        var now = Date.now();
        if (now - overToastTs > 900){ toast('Nilai maksimal 100.', 'error'); overToastTs = now; }
        v = prev;
      }

      this.value = v;
      if (v === ''){
        this.dataset.prev = '';
      }else{
        var n = parseInt(v,10);
        if (!isNaN(n) && n >= 1 && n <= 100){
          this.dataset.prev = v;
        }
      }
    });

    // tracker autosave nilai
    $('#ptsTable').off('input.autosave','.inp-nilai').on('input.autosave','.inp-nilai', function(){
      var sid=+$(this).closest('tr').data('sid')||0;
      if (sid){ changedRows.add(sid); scheduleAutoSave(); }
    });

    // navigasi input nilai
    var $inputs = $('#ptsTable .inp-nilai');
    $inputs.each(function(idx){ this.dataset.idx = idx; });
    $inputs.off('keydown.epsnav').on('keydown.epsnav', function(e){
      var idx = +this.dataset.idx || 0;
      var max = $inputs.length - 1;
      if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)){
        e.preventDefault(); var next = Math.min(idx+1, max); $inputs.eq(next).focus().select();
      } else if (e.key === 'Tab' && e.shiftKey){
        e.preventDefault(); var prev = Math.max(idx-1, 0); $inputs.eq(prev).focus().select();
      } else if (e.key === 'ArrowDown'){
        e.preventDefault(); var j = Math.min(idx+1, max); $inputs.eq(j).focus().select();
      } else if (e.key === 'ArrowUp'){
        e.preventDefault(); var k = Math.max(idx-1, 0); $inputs.eq(k).focus().select();
      }
    }).off('focus.selectall').on('focus.selectall', function(){ this.select(); });
  }

  function uncheckMirror($row, tpId, fromKind){
    var targetKind = (fromKind==='opt'?'per':'opt');
    var tcls = (targetKind==='opt'?'opt-chk':'per-chk');
    $row.find('input.'+tcls+'[data-tp="'+tpId+'"]').prop('checked',false);
  }
  $('#ptsTable').on('change','input.opt-chk, input.per-chk', function(){
    var $cb=$(this), tpId=+($cb.data('tp'))||0; if(!tpId) return;
    var $row=$cb.closest('tr');
    var fromKind = $cb.hasClass('opt-chk') ? 'opt' : 'per';
    if ($cb.is(':checked')) uncheckMirror($row, tpId, fromKind);
    updateUiFromRow($row);
  });
  $('#ptsTable').on('click','.tp-act',function(){
    var $btn=$(this), act=$btn.data('act');
    var $box=$btn.closest('.tp-box');
    var cls=$box.data('cls');
    var fromKind = $box.data('kind');
    var $row=$box.closest('tr');
    var $checks=$box.find('input.'+cls);

    if (act==='all'){ $checks.prop('checked',true); }
    else if (act==='none'){ $checks.prop('checked',false); }
    else if (act==='toggle'){ $checks.each(function(){ $(this).prop('checked', !this.checked); }); }
    else if (act==='move'){
      var to=$btn.data('to');
      var $target=$row.find('.tp-box[data-kind="'+to+'"]');
      var tcls=$target.data('cls');
      var $tChecks=$target.find('input.'+tcls);
      $checks.filter(':checked').each(function(){
        var id=$(this).data('tp');
        $(this).prop('checked',false);
        $tChecks.filter('[data-tp="'+id+'"]').prop('checked',true);
      });
    }
    $box.find('input.'+cls+':checked').each(function(){ uncheckMirror($row, $(this).data('tp'), fromKind); });
    updateUiFromRow($row);
  });

  function doLoadAll(){ return $.when(loadSiswa(), loadTP()).then(loadExisting); }

  /* UI helpers Muat */
  function startLoadUI(){ $('#btnLoad').prop('disabled',true); $('#icoLoad').addClass('fa-spin'); }
  function endLoadUI(){ $('#btnLoad').prop('disabled',false); $('#icoLoad').removeClass('fa-spin'); }

  /* ====== DESKRIPSI GENERATOR ====== */
  function mapTpText(ids){
    var idSet={}; (ids||[]).forEach(function(i){ i=+i||0; if(i>0) idSet[i]=true; });
    var list=[]; (tps||[]).forEach(function(tp){ if(idSet[+tp.tp_id]) list.push(tp.tp_text); });
    return list;
  }
  function joinKalimat(list){
    list = (list||[]).filter(Boolean);
    if(!list.length) return '';
    if(list.length===1) return list[0];
    var last=list.pop();
    return list.join(', ') + ', dan ' + last;
  }
  function genDeskForRowByState($tr){
    var sid = +$tr.data('sid')||0;
    var st = getUiTP(sid);
    var optTxts = mapTpText(st.opt||[]);
    var perTxts = mapTpText(st.per||[]);

    var tinggi = optTxts.length ? ('Mencapai Kompetensi dengan sangat baik dalam hal ' + joinKalimat(optTxts) + '.') : '';
    var rendah = perTxts.length ? ('Perlu peningkatan dalam hal ' + joinKalimat(perTxts) + '.') : '';

    $tr.find('textarea.txt-desk-tinggi').val(tinggi);
    $tr.find('textarea.txt-desk-rendah').val(rendah);
  }
  function genDeskAll(){
    if(viewMode!=='desk') setMode('desk');
    $('#ptsTable tbody tr').each(function(){ genDeskForRowByState($(this)); });
    toast('Deskripsi digenerate','success');
  }

  /* ====== SAVE HELPERS (manual + auto) ====== */
  function gatherRowsPayload(){
    var rows=[];
    $('#ptsTable tbody tr').each(function(){
      var $tr=$(this), sid=+$tr.attr('data-sid'); if(!sid) return;
      var nilaiRaw=$tr.find('.inp-nilai').val().trim();
      var nilai = (nilaiRaw==='') ? null : Math.round(parseInt(nilaiRaw||'0',10));

      var hasChk = $tr.find('input.opt-chk').length>0;
      var optimal=[], perlu=[];
      if(hasChk){
        $tr.find('input.opt-chk:checked').each(function(){ optimal.push(+$(this).data('tp')); });
        $tr.find('input.per-chk:checked').each(function(){ perlu.push(+$(this).data('tp')); });
      }else{
        var st = getUiTP(sid); optimal = (st.opt||[]).slice(); perlu = (st.per||[]).slice();
      }

      var desk_opt = ($tr.find('.txt-desk-tinggi').length ? $tr.find('.txt-desk-tinggi').val().trim() : (nilaiMap[sid]?.desk_opt||''));
      var desk_per = ($tr.find('.txt-desk-rendah').length ? $tr.find('.txt-desk-rendah').val().trim() : (nilaiMap[sid]?.desk_per||''));

      rows.push({siswa_id:sid, nilai:nilai, tp_optimal:optimal, tp_perlu:perlu, desk_opt:desk_opt, desk_per:desk_per});
    });
    return rows;
  }
  function saveNow(isAuto){
    var invalid=false, firstBad=null;
    $('#ptsTable .inp-nilai').each(function(){
      var v=$(this).val().trim();
      if (v==='') return;
      var n=parseInt(v,10);
      if (isNaN(n) || n<1 || n>100){
        invalid=true; firstBad = firstBad || this; flashInvalid(this);
      }
    });
    if (invalid){
      toast('Nilai harus antara 1–100.', 'error');
      if(firstBad) firstBad.focus();
      return;
    }

    var kid=+$('#selKelas').val()||0, mid=+$('#selMapel').val()||0, smt=parseInt($('#selSemester').val(),10)||semesterAuto||1;
    if(!kid||!mid){ if(!isAuto) toast('Pilih kelas & mapel dulu','warning'); return; }
    var payload={ kelas_id:kid,mapel_id:mid,semester:smt,ta_id:taId, rows: JSON.stringify(gatherRowsPayload()) };

    var $btn=$('#btnSave'), oldHtml=$btn.html();
    var $fab=$('#fabSave'), $fabIco=$('#fabIco'), fabOld=$fabIco.attr('class');
    if (!isAuto){
      $btn.prop('disabled',true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
      $fab.prop('disabled',true); $fabIco.attr('class','fa fa-spinner fa-spin');
    }

    $.post('nilai_pts.php?action=save', payload, null, 'json')
      .done(function(r){
        if(r&&r.ok){
          setId=r.pts_set_id||setId;
          if (isAuto){
            toast('Tersimpan (Auto)','success');
            changedRows.clear();
            autoCooldownUntil = Date.now() + 5000;
          }else{
            toast('Tersimpan','success');
            loadExisting();
          }
        } else {
          toast((r&&r.error)||'Gagal menyimpan','error');
        }
      })
      .fail(function(){ toast('Gagal koneksi','error'); })
      .always(function(){
        if (!isAuto){
          $btn.prop('disabled',false).html(oldHtml);
          $fab.prop('disabled',false); $fabIco.attr('class', fabOld||'fa fa-save');
        }
      });
  }
  function scheduleAutoSave(){
    if (changedRows.size >= 3){
      if (Date.now() < autoCooldownUntil) return;
      if (autosaveTimer) clearTimeout(autosaveTimer);
      autosaveTimer = setTimeout(function(){ saveNow(true); }, 1000);
    }
  }

  /* events */
  $('#btnLoad').on('click', function(){ startLoadUI(); doLoadAll().always(endLoadUI); });
  $(document).on('keydown', function(e){
    var isSaveCombo = (e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.keyCode === 83);
    if (isSaveCombo){ e.preventDefault(); $('#btnSave').trigger('click'); }
  });
  $('#fabSave').on('click', function(){ $('#btnSave').trigger('click'); });
  $('#btnSave').on('click', function(){ saveNow(false); });

  $('#selKelas').on('change', loadMapelForKelas);
  $('#selMapel').on('change', doLoadAll);
  
  // Real-time Reload ketika Semester diganti di UI
  $('#selSemester').on('change', function() {
    doLoadAll();
  });

  // Toggle Mode button
  $('#btnToggleMode').on('click', function(){ setMode(viewMode==='tp' ? 'desk' : 'tp'); });
  // Micro-interaction: sorotan radial mengikuti mouse
  var btnT=document.getElementById('btnToggleMode');
  if(btnT){ btnT.addEventListener('mousemove', function(e){ var r=this.getBoundingClientRect(); var x=((e.clientX-r.left)/r.width*100).toFixed(2)+'%'; this.style.setProperty('--mx', x); }); }

  // Segmented toggle (legacy/opsional)
  $('#modeSeg').on('click', '.seg-item', function(){ var mode = $(this).data('mode'); setMode(mode); });
  $('#modeSeg').on('keydown', '.seg-item', function(e){ if (e.key === 'ArrowLeft' || e.key === 'ArrowRight'){ e.preventDefault(); var mode = (viewMode === 'tp') ? 'desk' : 'tp'; setMode(mode); $('#modeSeg .seg-item[data-mode="'+viewMode+'"]').focus(); } });

  // Generate Otomatis
  $('#btnGenAuto').on('click', function(){ genDeskAll(); });

  // INIT
  setMode('tp');
  loadScope();
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>