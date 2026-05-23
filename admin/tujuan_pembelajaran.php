<?php
/* ============================================================
 * TUJUAN PEMBELAJARAN — FINAL + POLISH tombol aksi mapel
 * (HANYA revisi posisi & style tombol Import/Tambah-Edit)
 * + BATAS KARAKTER TP (120) dengan counter & notifikasi
 * + PEMBATASAN AKSES: Admin = semua mapel; Guru = mapel diampu
 * (server-side guard untuk list/save/toggle/import)
 * + REVISI TERBARU: Filter Semester UI + Auto Deteksi Semester Berjalan
 * ============================================================ */

function tp_table_exists($koneksi, $name){
  $q = @mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
  return $q && mysqli_num_rows($q)>0;
}
function tp_table_columns($koneksi, $table){
  $cols=[]; $res=@mysqli_query($koneksi,"SHOW COLUMNS FROM `$table`");
  if($res){ while($r=@mysqli_fetch_assoc($res)){ if(!empty($r['Field'])) $cols[]=$r['Field']; } }
  return $cols;
}
function tp_pick_col($cols, array $cands){ foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; } return null; }
function tp_roman_to_int($s){
  $map=['CM'=>900,'CD'=>400,'XC'=>90,'XL'=>40,'IX'=>9,'IV'=>4,'M'=>1000,'D'=>500,'C'=>100,'L'=>50,'X'=>10,'V'=>5,'I'=>1];
  $s = strtoupper(trim($s)); $i=0; $out=0;
  while($i<strlen($s)){
    if($i+1<strlen($s) && isset($map[$s[$i].$s[$i+1]])){ $out += $map[$s[$i].$s[$i+1]]; $i+=2; }
    elseif(isset($map[$s[$i]])){ $out += $map[$s[$i]]; $i++; }
    else { $i++; }
  }
  return $out ?: null;
}

/* ====== Helpers tambahan untuk RBAC ala nilai_pts (SERVER-SIDE) ====== */
function tp_db_one(mysqli $db, string $sql, $default=null){
  $r = @mysqli_query($db,$sql);
  if(!$r) return $default;
  $row = @mysqli_fetch_row($r);
  return $row ? $row[0] : $default;
}

/**
 * Deteksi "admin-like" yang robust
 */
function tp_is_admin_like(mysqli $db=null, $user_id=null): bool {
  if (function_exists('_is_admin') && @_is_admin()) return true;
  if (function_exists('has_role')) {
    $adminNames = ['admin','administrator','superadmin','super-admin','root'];
    foreach ($adminNames as $rn) { if (@has_role($rn)) return true; }
  }
  if ($user_id === null) $user_id = (int)($_SESSION['id'] ?? 0);
  if ($db instanceof mysqli && $user_id > 0) {
    if (tp_table_exists($db,'roles') && tp_table_exists($db,'user_roles')) {
      $rCols  = tp_table_columns($db,'roles');
      $urCols = tp_table_columns($db,'user_roles');
      $R_ID   = tp_pick_col($rCols,  ['id','role_id']);
      $R_NAME = tp_pick_col($rCols,  ['name','role_name','slug','role']);
      $UR_UID = tp_pick_col($urCols, ['user_id','id_user','users_id']);
      $UR_RID = tp_pick_col($urCols, ['role_id','id_role','roles_id']);
      if ($R_ID && $R_NAME && $UR_UID && $UR_RID) {
        $sql = "SELECT r.`$R_NAME`
                FROM user_roles ur
                JOIN roles r ON r.`$R_ID` = ur.`$UR_RID`
                WHERE ur.`$UR_UID`=".(int)$user_id." LIMIT 10";
        $rs = @mysqli_query($db,$sql);
        if ($rs) {
          while($row = @mysqli_fetch_assoc($rs)){
            $nm = strtolower((string)$row[$R_NAME]);
            if (strpos($nm,'admin') !== false) return true;
          }
        }
      }
    }
  }
  $lvl = strtolower((string)($_SESSION['level'] ?? $_SESSION['role'] ?? ''));
  if (strpos($lvl,'admin') !== false) return true;
  return false;
}

function tp_active_ta_id(mysqli $db){
  if (!tp_table_exists($db,'ta')) return null;
  $ta = tp_db_one($db, "SELECT ta_id FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1");
  if ($ta) return (int)$ta;
  $ta = tp_db_one($db, "SELECT ta_id FROM ta ORDER BY ta_id DESC LIMIT 1");
  return $ta ? (int)$ta : null;
}

/**
 * Cek akses guru ke mapel tertentu
 */
function tp_can_manage_mapel(mysqli $db, int $user_id, int $mapel_id): bool {
  if ($user_id<=0 || $mapel_id<=0) return false;
  if (tp_is_admin_like($db,$user_id)) return true;
  if (!tp_table_exists($db,'pengampu_mapel')) return false;

  $pcols = tp_table_columns($db,'pengampu_mapel');
  $PM_MAPEL = tp_pick_col($pcols, ['mapel_id','id_mapel']);
  $PM_USER  = tp_pick_col($pcols, ['user_id','id_user','guru_user_id','user_guru_id','guru_id','id_guru']);
  if (!$PM_MAPEL || !$PM_USER) return false;

  $where_ta = '';
  if (in_array('ta_id',$pcols,true)) {
    $ta = tp_active_ta_id($db);
    if ($ta) $where_ta = " AND p.`ta_id`=".(int)$ta;
  }

  $sql = "SELECT 1
          FROM pengampu_mapel p
          WHERE p.`$PM_MAPEL`=".(int)$mapel_id."
            AND p.`$PM_USER`=".(int)$user_id."
            $where_ta
          LIMIT 1";
  $q = @mysqli_query($db,$sql);
  return $q && @mysqli_fetch_row($q);
}

/* ============================ AJAX FIRST ============================ */
$IS_AJAX = isset($_GET['action']) && $_GET['action'] !== '';
if ($IS_AJAX) {
  header('Content-Type: application/json; charset=utf-8');

  if (session_status()===PHP_SESSION_NONE) session_start();
  require_once __DIR__.'/../koneksi.php';

  $TP_MAX_LEN = 120;

  $SEKOLAH_ID = 0;
  $_r = @mysqli_query($koneksi,"SELECT sekolah_id FROM sekolah LIMIT 1");
  if ($_r && $row=@mysqli_fetch_assoc($_r)) $SEKOLAH_ID = (int)$row['sekolah_id'];

  // Auth
  $user_id = 0;
  $auth_file = __DIR__.'/../includes/auth.php';
  if (is_file($auth_file)) {
    require_once $auth_file;
    if (function_exists('ensure_logged_in')) { @ensure_logged_in(); }
    if (function_exists('current_user_id')) { $user_id = (int)@current_user_id(); }
  }
  if ($user_id<=0) {
    if (empty($_SESSION['id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Belum login']); exit; }
    $user_id = (int)$_SESSION['id'];
  }
  $IS_ADMIN = tp_is_admin_like($koneksi,$user_id);

  $act = $_GET['action'];

  if ($act==='list' && isset($_GET['mapel_id'])) {
    $mapel_id = (int)$_GET['mapel_id'];
    // Ambil parameter semester dari UI, default 0 (semua) kalau tidak dikirim
    $semester_filter = isset($_GET['semester']) ? (int)$_GET['semester'] : 0; 
    
    if (!$IS_ADMIN && !tp_can_manage_mapel($koneksi, $user_id, $mapel_id)) {
      http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Anda tidak berhak melihat mapel ini.']); exit;
    }

    $items = [];
    $whereSemester = $semester_filter > 0 ? " AND semester = $semester_filter" : "";
    
    $sql = "SELECT tp_id, mapel_id, tingkat, semester, tp_text, status_enum
            FROM tujuan_pembelajaran
            WHERE mapel_id=$mapel_id $whereSemester".
            ($SEKOLAH_ID ? " AND (sekolah_id IS NULL OR sekolah_id=".$SEKOLAH_ID.")" : "").
            " ORDER BY tingkat, semester, tp_id";
    $q = @mysqli_query($koneksi,$sql);
    if ($q){ while($r=mysqli_fetch_assoc($q)){ $items[]=$r; } }
    else { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($koneksi)]); exit; }
    echo json_encode(['ok'=>true,'data'=>$items]); exit;
  }

  if ($act==='save' && !empty($_POST)) {
    $mapel_id = (int)$_POST['mapel_id'];
    if (!$IS_ADMIN && !tp_can_manage_mapel($koneksi, $user_id, $mapel_id)) {
      http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Anda tidak berhak mengelola mapel ini.']); exit;
    }

    $rows     = json_decode($_POST['rows'] ?? '[]', true);
    $deleted  = json_decode($_POST['deleted'] ?? '[]', true);
    if ($mapel_id<=0) { echo json_encode(['ok'=>false,'error'=>'Mapel tidak valid']); exit; }
    $now = date('Y-m-d H:i:s');

    mysqli_begin_transaction($koneksi);
    $err = null;

    if (!empty($deleted)) {
      $ids = array_map('intval', $deleted);
      $ids = array_values(array_filter($ids));
      if ($ids){
        $sql = "DELETE FROM tujuan_pembelajaran WHERE mapel_id=$mapel_id AND tp_id IN (".implode(',',$ids).")";
        if (!mysqli_query($koneksi,$sql)) $err = mysqli_error($koneksi);
      }
    }

    if (!$err) {
      $rowNo = 0;
      foreach((array)$rows as $r){
        $rowNo++;
        $tp_id    = (int)($r['tp_id'] ?? 0);
        $tingkat  = (int)($r['tingkat'] ?? 0);
        $semester = (int)($r['semester'] ?? 0);
        $status   = ($r['status_enum'] ?? 'Aktif') === 'Tidak Aktif' ? 'Tidak Aktif' : 'Aktif';
        $tp_text  = trim((string)($r['tp_text'] ?? ''));
        if ($tingkat<=0 || !in_array($semester,[1,2],true) || $tp_text===''){ $err='Data tidak lengkap (baris '.$rowNo.').'; break; }
        if (mb_strlen($tp_text,'UTF-8') > 120){ $err='TP melebihi 120 karakter pada baris '.$rowNo.'.'; break; }

        $tp_sql   = mysqli_real_escape_string($koneksi,$tp_text);

        if ($tp_id>0){
          $cek = tp_db_one($koneksi, "SELECT 1 FROM tujuan_pembelajaran WHERE tp_id=$tp_id AND mapel_id=$mapel_id LIMIT 1");
          if (!$cek){ $err='Baris tidak sah untuk mapel ini.'; break; }

          $sql = "UPDATE tujuan_pembelajaran
                    SET tingkat=$tingkat, semester=$semester, tp_text='$tp_sql', status_enum='$status',
                        updated_at='$now', updated_by=$user_id
                  WHERE tp_id=$tp_id AND mapel_id=$mapel_id";
        } else {
          $sek = $SEKOLAH_ID ?: 'NULL';
          $sql = "INSERT INTO tujuan_pembelajaran
                    (sekolah_id,mapel_id,tingkat,semester,tp_text,status_enum,created_at,updated_at,created_by,updated_by)
                  VALUES($sek,$mapel_id,$tingkat,$semester,'$tp_sql','$status','$now','$now',$user_id,$user_id)";
        }
        if (!mysqli_query($koneksi,$sql)){ $err = mysqli_error($koneksi); break; }
      }
    }

    if ($err){ mysqli_rollback($koneksi); echo json_encode(['ok'=>false,'error'=>$err]); exit; }
    mysqli_commit($koneksi);
    echo json_encode(['ok'=>true]); exit;
  }

  if ($act==='toggle' && isset($_POST['tp_id'])) {
    $tp_id = (int)$_POST['tp_id'];
    if ($tp_id<=0){ echo json_encode(['ok'=>false,'error'=>'Data tidak valid']); exit; }

    $mapel_id = (int)tp_db_one($koneksi, "SELECT mapel_id FROM tujuan_pembelajaran WHERE tp_id=$tp_id");
    if (!$mapel_id){ echo json_encode(['ok'=>false,'error'=>'Data tidak ditemukan']); exit; }
    if (!$IS_ADMIN && !tp_can_manage_mapel($koneksi,$user_id,$mapel_id)){
      http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Anda tidak berhak mengubah status item ini.']); exit;
    }

    $sql = "UPDATE tujuan_pembelajaran
              SET status_enum=IF(status_enum='Aktif','Tidak Aktif','Aktif'),
                  updated_at=NOW(), updated_by=$user_id
            WHERE tp_id=$tp_id";
    $ok = @mysqli_query($koneksi,$sql);
    echo json_encode(['ok'=> (bool)$ok, 'error'=> $ok?null:mysqli_error($koneksi)]); exit;
  }

  if ($act==='import' && isset($_POST['mapel_id'])) {
    $mapel_id = (int)$_POST['mapel_id'];
    if (!$IS_ADMIN && !tp_can_manage_mapel($koneksi, $user_id, $mapel_id)) {
      http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Anda tidak berhak mengimpor ke mapel ini.']); exit;
    }

    if (empty($_FILES['csv']['tmp_name'])) { echo json_encode(['ok'=>false,'error'=>'File tidak ditemukan']); exit; }
    $fh = fopen($_FILES['csv']['tmp_name'],'r');
    if(!$fh){ echo json_encode(['ok'=>false,'error'=>'Gagal membaca file']); exit; }
    $now = date('Y-m-d H:i:s'); $ins=0; $skip=0; $first=true;
    while(($row=fgetcsv($fh,0,','))!==false){
      if ($first && preg_match('~tingkat~i', $row[0] ?? '')){ $first=false; continue; }
      $first=false;
      $tingkat  = (int)($row[0] ?? 0);
      $semester = (int)($row[1] ?? 0);
      $tp_text  = trim((string)($row[2] ?? ''));
      if (mb_strlen($tp_text,'UTF-8')>120) $tp_text = mb_substr($tp_text,0,120,'UTF-8');
      $status   = (strcasecmp(trim((string)($row[3] ?? 'Aktif')),'aktif')===0) ? 'Aktif' : 'Tidak Aktif';
      if (!$tingkat || !in_array($semester,[1,2],true) || $tp_text===''){ $skip++; continue; }
      $tp_sql = mysqli_real_escape_string($koneksi,$tp_text);
      $sek = $SEKOLAH_ID ?: 'NULL';
      $sql = "INSERT INTO tujuan_pembelajaran
                (sekolah_id,mapel_id,tingkat,semester,tp_text,status_enum,created_at,updated_at,created_by,updated_by)
              VALUES($sek,$mapel_id,$tingkat,$semester,'$tp_sql','$status','$now','$now',$user_id,$user_id)";
      if (@mysqli_query($koneksi,$sql)) $ins++; else $skip++;
    }
    fclose($fh);
    echo json_encode(['ok'=>true,'inserted'=>$ins,'skipped'=>$skip]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'Aksi tidak dikenal']); exit;
}
/* ========================== END AJAX FIRST ========================== */

// ============================ UI SECTION =============================
$PAGE_TITLE = 'Tujuan Pembelajaran';
require_once 'header.php';

// AUTO DETEKSI SEMESTER UNTUK DEFAULT FILTER
$bulanBerjalan = (int)date('n');
$defaultSemester = ($bulanBerjalan >= 1 && $bulanBerjalan <= 6) ? 2 : 1;

/**
 * Ambil opsi mapel:
 * - Admin: semua mapel
 * - Guru: mapel yang diampu (filter TA aktif bila ada kolom `ta_id`)
 */
function tp_get_mapel_options($koneksi, $MAPEL_ID, $MAPEL_NAMA){
  $user_id = (int)($_SESSION['id'] ?? 0);
  $admin_like = tp_is_admin_like($koneksi, $user_id);

  if ($admin_like) {
    $sql = "SELECT `$MAPEL_ID` AS id, `$MAPEL_NAMA` AS nama FROM mapel ORDER BY `$MAPEL_NAMA`";
  } else {
    if (!tp_table_exists($koneksi,'pengampu_mapel')) return [];
    $pcols = tp_table_columns($koneksi,'pengampu_mapel');
    $PM_MAPEL = tp_pick_col($pcols, ['mapel_id','id_mapel']);
    $PM_USER  = tp_pick_col($pcols, ['user_id','id_user','guru_user_id','user_guru_id','guru_id','id_guru']);
    if ($PM_MAPEL && $PM_USER) {
      $where_ta = '';
      if (in_array('ta_id',$pcols,true)) {
        $ta = tp_active_ta_id($koneksi);
        if ($ta) $where_ta = " AND p.`ta_id`=".(int)$ta;
      }
      $sql = "SELECT DISTINCT m.`$MAPEL_ID` AS id, m.`$MAPEL_NAMA` AS nama
              FROM pengampu_mapel p
              JOIN mapel m ON m.`$MAPEL_ID`=p.`$PM_MAPEL`
              WHERE p.`$PM_USER`=$user_id $where_ta
              ORDER BY m.`$MAPEL_NAMA`";
    } else {
      return [];
    }
  }
  $opts=[]; $q=mysqli_query($koneksi,$sql);
  if($q){ while($r=mysqli_fetch_assoc($q)){ $opts[]=['id'=>$r['id'],'nama'=>$r['nama']]; } }
  return $opts;
}

$mapelCols = tp_table_columns($koneksi,'mapel');
$MAPEL_ID   = tp_pick_col($mapelCols, ['mapel_id','id_mapel','id']);
$MAPEL_NAMA = tp_pick_col($mapelCols, ['mapel_nama','nama_mapel','nama','mapel']);
$mapelOptions = $MAPEL_ID && $MAPEL_NAMA ? tp_get_mapel_options($koneksi,$MAPEL_ID,$MAPEL_NAMA) : [];

function tp_get_tingkat_from_jurusan($koneksi){
  $result = [];
  if (!tp_table_exists($koneksi,'jurusan')) {
    for($i=7;$i<=12;$i++) $result[$i]=(string)$i;
  } else {
    $cols = tp_table_columns($koneksi,'jurusan');
    $J_TKT  = tp_pick_col($cols, ['tingkat','kelas_tingkat','tingkat_kelas','kelas','level','kelas_level']);
    $J_NAMA = tp_pick_col($cols, ['jurusan_nama','nama_jurusan','nama','jurusan']);
    $rows = [];
    $q = mysqli_query($koneksi,"SELECT * FROM jurusan");
    if($q){ while($r=mysqli_fetch_assoc($q)){ $rows[]=$r; } }
    foreach($rows as $r){
      $label = $J_NAMA ? trim((string)$r[$J_NAMA]) : '';
      $num   = null;
      if ($J_TKT && isset($r[$J_TKT])) $num = (int)$r[$J_TKT];
      if (!$num && $label!==''){
        if (preg_match('~\b(\d{1,2})\b~', $label, $m)) $num=(int)$m[1];
        if (!$num && preg_match('~\b([IVXLCM]{1,6})\b~i', $label, $m)){ $num = tp_roman_to_int($m[1]); }
      }
      if ($num && $num>0 && !isset($result[$num])) $result[$num] = $label ?: (string)$num;
    }
    if (empty($result)) for($i=7;$i<=12;$i++) $result[$i]=(string)$i;
  }
  ksort($result, SORT_NUMERIC);
  $out=[]; foreach($result as $val=>$label){ $out[]=['val'=>(int)$val,'label'=>$label?: (string)$val]; }
  return $out;
}
$tingkatOptions = tp_get_tingkat_from_jurusan($koneksi);
?>
<div class="content-wrapper">

  <section class="content-header polished-header">
    <h1 class="tp-title">
      <i class="fa fa-bullseye"></i>
      <span>Tujuan Pembelajaran</span>
    </h1>
    <span class="subtitle-badge">Kelola daftar TP per mapel (menjadi referensi deskripsi rapor)</span>
  </section>

  <section class="content">

    <div class="mapel-card">
      <div class="mapel-card__header">
        <div class="mapel-card__title">
          <i class="fa fa-filter"></i>
          Pilih Mata Pelajaran & Semester
        </div>
      </div>

      <div class="mapel-card__body">
        <div class="row">
          <div class="col-md-8 col-sm-12" style="margin-bottom:10px;">
            <div class="input-group pretty-select">
              <span class="input-group-addon"><i class="fa fa-book-open"></i></span>
              <select id="mapelSelect" class="form-control select2" style="width:100%;" data-placeholder="Cari / pilih mata pelajaran">
                <option value="">— pilih mapel —</option>
                <?php foreach($mapelOptions as $m): ?>
                  <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['nama']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-md-4 col-sm-12">
            <div class="input-group pretty-select">
              <span class="input-group-addon"><i class="fa fa-calendar-check-o"></i></span>
              <select id="semesterSelect" class="form-control" style="width:100%;">
                <option value="1" <?php echo $defaultSemester == 1 ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?php echo $defaultSemester == 2 ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
              </select>
            </div>
          </div>
        </div>

        <div id="mapelActions" class="mapel-card__actions--below" style="display:none;">
          <button id="btnImport" class="btn cta-light is-disabled" disabled>
            <i class="fa fa-upload"></i> Import TP
          </button>
          <button id="btnEdit" class="btn cta-primary is-disabled" disabled>
            <i class="fa fa-pen"></i> Tambah/Edit TP
          </button>
        </div>

        <small class="mapel-hint"><i class="fa fa-info-circle"></i> Filter tabel berdasarkan Mapel dan Semester untuk memudahkan pengelolaan.</small>
      </div>
    </div>

    <div class="box" style="border-radius:12px; overflow:hidden;">
      <div class="box-header" style="background:#0b1220;color:#cbe6ff;">
        <h3 class="box-title" style="font-weight:800;">Data Tujuan Pembelajaran</h3>

        <div class="box-tools pull-right">
          <small id="hintMode" class="label bg-aqua">Mode: Lihat</small>
        </div>
      </div>
      <div class="box-body" style="padding:0;">
        <div class="table-responsive">
          <table id="tpTable" class="table table-striped table-hover" style="margin:0;">
            <thead>
              <tr style="background:#172554;color:#fff;">
                <th style="width:60px">No</th>
                <th style="width:220px">Tingkat</th>
                <th style="width:120px">Semester</th>
                <th>Tujuan Pembelajaran</th>
                <th style="width:140px">Status</th>
                <th style="width:90px">Hapus</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Pilih mapel untuk menampilkan data.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div id="editBar" class="box-footer" style="display:none;">
        <div class="row">
          <div class="col-sm-6" style="margin-bottom:8px;">
            <button id="btnAddRow" class="btn btn-addrow">
              <i class="fa fa-plus-circle"></i> Tambah Baris
            </button>
          </div>
          <div class="col-sm-6 text-right">
            <button id="btnCancel" class="btn btn-default" style="border-radius:10px;">Batal</button>
            <button id="btnSave" class="btn btn-success" style="border-radius:10px;font-weight:800;">
              <i class="fa fa-save"></i> Simpan Perubahan
            </button>
          </div>
        </div>
        <small class="text-muted">
          <i class="fa fa-info-circle"></i> Perubahan (termasuk baris yang ditandai hapus) baru diterapkan setelah menekan <b>Simpan Perubahan</b>.
        </small>
      </div>
    </div>

  </section>
</div>

<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importLabel">
  <div class="modal-dialog" role="document">
    <form id="formImport" enctype="multipart/form-data">
      <div class="modal-content">
        <div class="modal-header" style="background:#0ea5e9;color:#fff;">
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span>&times;</span></button>
          <h4 class="modal-title" id="importLabel" style="font-weight:800;">Import Tujuan Pembelajaran (CSV)</h4>
        </div>
        <div class="modal-body">
          <p class="text-muted" style="margin-bottom:12px;">Format: <code>tingkat, semester, tujuan, status</code>. Status: <b>Aktif</b>/<b>Tidak Aktif</b>.</p>
          <div class="form-group">
            <label>Pilih File CSV</label>
            <input type="file" name="csv" accept=".csv" class="form-control" required>
          </div>
          <div class="well well-sm" style="margin-bottom:0;">
            Contoh:
<pre style="white-space:pre; margin:0;">tingkat,semester,tujuan,status
7,1,"Keberagaman lingkungan sekitar",Aktif
7,2,"Memahami interaksi sosial",Aktif</pre>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Import</button>
        </div>
      </div>
    </form>
  </div>
</div>

<style>
/* Title */
.polished-header { margin-bottom: 10px; }
.tp-title { display:flex; align-items:center; gap:10px; font-weight:800; letter-spacing:.2px; }
.tp-title i { font-size:26px; color:#0ea5e9; background:#e6f6ff; border-radius:12px; padding:6px 10px; }
.subtitle-badge{
  display:inline-block; margin-top:6px; background:#e9f5ff; color:#0b5ed7;
  border:1px solid #b8e0ff; padding:4px 10px; border-radius:999px; font-weight:600; font-size:12px;
}

/* Mapel Card */
.mapel-card{
  border-radius:14px; overflow:hidden; margin-bottom:16px;
  background:linear-gradient(135deg,#0ea5e90d,#3b82f60d); border:1px solid #dbeafe;
  box-shadow:0 6px 16px rgba(2,6,23,.06);
}
.mapel-card__header{
  padding:12px 14px; background:linear-gradient(90deg,#0ea5e9,#3b82f6); color:#fff;
}
.mapel-card__title{ font-weight:800; letter-spacing:.2px; display:flex; align-items:center; gap:8px; }
.mapel-card__title i{ background:rgba(255,255,255,.2); padding:6px; border-radius:8px; }
.mapel-card__body{ padding:14px; }
.pretty-select .input-group-addon{
  background:#eef6ff; border:1px solid #dbeafe; border-right:none; color:#2563eb; font-weight:700;
}
.pretty-select .select2-container .select2-selection,
.pretty-select select.form-control {
  height:38px !important; border-radius:0 10px 10px 0 !important; border:1px solid #dbeafe !important;
}
.mapel-hint { display:block; margin-top:8px; color:#64748b; }

/* tombol actions */
.mapel-card__actions--below{
  display:flex; justify-content:flex-end; gap:10px; margin-top:12px;
}
.cta-primary{
  background:linear-gradient(90deg,#1976ff,#0ea5e9);
  color:#fff; font-weight:800; padding:10px 16px; border-radius:999px; border:none;
  box-shadow:0 6px 14px rgba(2,132,199,.25);
}
.cta-primary:hover{ filter:brightness(1.05); }
.cta-light{
  background:#eef2ff; color:#111827; border:1px solid #c7d2fe;
  font-weight:700; padding:10px 16px; border-radius:999px;
}
.is-disabled{ opacity:.65; cursor:not-allowed; }

/* Table */
#tpTable tbody tr td { vertical-align: middle; }
.tp-status-badge { border-radius:999px; font-weight:800; letter-spacing:.2px; cursor:pointer; }
.tp-status-badge.badge-aktif      { background:#16a34a; color:#fff; }
.tp-status-badge.badge-tidakaktif { background:#9ca3af; color:#0b1220; }
.editing .view-only { display:none; }
.editing .edit-only { display:block; }
.edit-only { display:none; }

/* >>> Counter karakter TP */
.tp-limit{display:flex; justify-content:space-between; margin-top:4px; font-size:11px; color:#6b7280;}
.tp-counter.warn{color:#b45309;}
.tp-counter.full{color:#b91c1c; font-weight:700;}

/* >>> POIN #1: Badge mode lebih besar & center vertical di kanan */
.box .box-header{ position:relative; min-height:30px; }
.box .box-header .box-tools{
  position:absolute; right:14px; top:50%; transform:translateY(-50%);
}
#hintMode{
  display:inline-block;
  padding:8px 14px;            /* sedikit lebih besar */
  font-size:10px;              /* sedikit lebih besar */
  line-height:1;
  border-radius:999px;
  box-shadow:0 4px 10px rgba(0,0,0,.12);
}
@media (max-width:480px){
  #hintMode{ font-size:12px; padding:7px 12px; }
}

/* >>> POIN #2: Tombol Tambah Baris lebih mencolok + pulse saat masuk Mode Edit */
.btn-addrow{
  border-radius:10px;
  font-weight:800;
  color:#fff;
  background:linear-gradient(90deg,#22c55e,#16a34a); /* hijau tambah */
  border:none;
  box-shadow:0 8px 18px rgba(34,197,94,.35);
}
.btn-addrow:hover{ filter:brightness(1.05); }
@keyframes pulseRing {
  0%   { box-shadow:0 0 0 0 rgba(34,197,94,.60); transform:scale(1); }
  70%  { box-shadow:0 0 0 12px rgba(34,197,94,0); transform:scale(1.03); }
  100% { box-shadow:0 0 0 0 rgba(34,197,94,0); transform:scale(1); }
}
.pulse-hint{ animation:pulseRing 1.6s ease-out infinite; }

/* >>> POIN #3: Teks deskripsi TP (mode Lihat) normal/regular, bukan bold */
.tp-text-view{ font-weight:400; }
</style>

<script>
(function(){
  var $ = window.jQuery; if(!$) return;

  function toast(msg, type){
    if (window.Swal && Swal.mixin){
      const T = Swal.mixin({toast:true, position:'top-end', showConfirmButton:false, timer:2200, timerProgressBar:true});
      T.fire({icon:type||'info', title: msg});
    } else { alert(msg); }
  }

  var TP_MAX_LEN = 120;

  var mapelId = 0;
  var semesterVal = parseInt($('#semesterSelect').val(), 10) || 1;
  var isEdit  = false;
  var dataset = [];
  var deleted = [];
  var tingkatOptions = <?php echo json_encode($tingkatOptions, JSON_UNESCAPED_UNICODE); ?>;

  function setMode(edit){
    isEdit = !!edit;
    $('#hintMode').text('Mode: ' + (isEdit?'Edit':'Lihat'))
                  .toggleClass('bg-aqua', !isEdit)
                  .toggleClass('bg-yellow', isEdit);

    $('#editBar').toggle(isEdit);
    $('#tpTable').toggleClass('editing', isEdit);

    /* >>> POIN #2: Auto-pulse pada tombol Tambah Baris saat masuk Mode Edit */
    var $add = $('#btnAddRow');
    if (isEdit) { $add.addClass('pulse-hint'); }
    else        { $add.removeClass('pulse-hint'); }

    renderTable();
  }
  function escapeHtml(s){ return (''+s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
  function labelTingkat(val){
    val = parseInt(val,10);
    if (Array.isArray(tingkatOptions)){
      for (var i=0;i<tingkatOptions.length;i++){
        if (parseInt(tingkatOptions[i].val,10)===val) return tingkatOptions[i].label;
      }
    }
    return ''+val;
  }
  function buildTingkatSelect(selected){
    var html = '<select class="form-control input-tingkat">';
    if (Array.isArray(tingkatOptions) && tingkatOptions.length){
      tingkatOptions.forEach(function(t){
        html += '<option value="'+t.val+'" '+(parseInt(selected)==parseInt(t.val)?'selected':'')+'>'+escapeHtml(t.label)+'</option>';
      });
    } else {
      for (var x=7;x<=12;x++){
        html += '<option value="'+x+'" '+(parseInt(selected)==x?'selected':'')+'>'+x+'</option>';
      }
    }
    html += '</select>';
    return html;
  }

  function renderRow(item, idx){
    var statusClass = (item.status_enum==='Aktif') ? 'badge-aktif' : 'badge-tidakaktif';
    var curLen = (item.tp_text||'').length;
    return `
      <tr data-id="${item.tp_id||0}">
        <td class="text-center">${idx+1}</td>
        <td class="text-center">
          <span class="view-only">${escapeHtml(labelTingkat(item.tingkat))}</span>
          <div class="edit-only">${buildTingkatSelect(item.tingkat)}</div>
        </td>
        <td class="text-center">
          <span class="view-only">${item.semester||''}</span>
          <div class="edit-only">
            <select class="form-control input-semester">
              <option value="1" ${parseInt(item.semester)==1?'selected':''}>1</option>
              <option value="2" ${parseInt(item.semester)==2?'selected':''}>2</option>
            </select>
          </div>
        </td>
        <td>
          <div class="view-only tp-text-view">${escapeHtml(item.tp_text||'')}</div>
          <div class="edit-only">
            <textarea class="form-control input-tp" rows="2" placeholder="Tuliskan tujuan pembelajaran..." maxlength="${TP_MAX_LEN}" data-max="${TP_MAX_LEN}">${escapeHtml(item.tp_text||'')}</textarea>
            <div class="tp-limit">
              <small>Batas ${TP_MAX_LEN} karakter</small>
              <small class="tp-counter">${curLen}/${TP_MAX_LEN}</small>
            </div>
          </div>
        </td>
        <td class="text-center">
          <span class="label tp-status-badge ${statusClass} view-only">${item.status_enum||'Aktif'}</span>
          <div class="edit-only">
            <select class="form-control input-status">
              <option value="Aktif" ${item.status_enum==='Aktif'?'selected':''}>Aktif</option>
              <option value="Tidak Aktif" ${item.status_enum!=='Aktif'?'selected':''}>Tidak Aktif</option>
            </select>
          </div>
        </td>
        <td class="text-center">
          <button type="button" class="btn btn-danger btn-xs btn-delete" title="Hapus baris" style="border-radius:8px;">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>
    `;
  }

  function renderTable(){
    var $tbody = $('#tpTable tbody');
    if (!mapelId) {
      $tbody.html('<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Pilih mapel terlebih dahulu.</td></tr>');
      return;
    }
    if (!dataset.length) {
      if (isEdit){
        // Saat edit, default baris baru menyesuaikan semester yang dipilih di atas
        dataset = [{ tp_id:0, tingkat:(tingkatOptions[0]?tingkatOptions[0].val:7), semester:semesterVal, tp_text:'', status_enum:'Aktif' }];
      } else {
        $tbody.html('<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Belum ada data di semester ini. Klik <b>Tambah/Edit TP</b> untuk menambahkan.</td></tr>');
        return;
      }
    }
    var html = '';
    dataset.forEach(function(it, idx){ html += renderRow(it, idx); });
    $tbody.html(html);

    $tbody.find('.input-tp').each(function(){
      var max=parseInt(this.getAttribute('maxlength')||this.dataset.max||TP_MAX_LEN,10);
      var len=(this.value||'').length;
      var $c=$(this).closest('td').find('.tp-counter');
      $c.text(len+'/'+max).toggleClass('warn', len>=max-10 && len<max).toggleClass('full', len>=max).data('ding', len>=max?1:0);
    });
  }

  function updateActions(){
    if (mapelId){
      $('#mapelActions').slideDown(120);
      $('#btnImport,#btnEdit').prop('disabled',false).removeClass('is-disabled');
    } else {
      $('#mapelActions').slideUp(120);
      $('#btnImport,#btnEdit').prop('disabled',true).addClass('is-disabled');
    }
  }

  function loadData(){
    if (!mapelId){ renderTable(); return; }
    
    // Kirim mapel_id DAN semester ke AJAX
    $.getJSON('tujuan_pembelajaran.php', { action:'list', mapel_id: mapelId, semester: semesterVal })
     .done(function(res){
        if (!res || res.ok===false){ throw new Error((res && res.error) || 'Gagal'); }
        dataset = res.data || [];
        deleted = [];
        setMode(false);
     })
     .fail(function(xhr, __, err){
        var msg = 'Gagal memuat data.';
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
        alert(msg);
        console.error('list error:', err);
     });
  }

  function collectRowsFromDOM(){
    var rows = [];
    $('#tpTable tbody tr').each(function(){
      var $tr = $(this);
      var txt = ($tr.find('.input-tp').val()||'').trim();
      if (txt.length>TP_MAX_LEN) txt = txt.slice(0,TP_MAX_LEN);
      rows.push({
        tp_id: parseInt($tr.attr('data-id'),10)||0,
        tingkat: parseInt($tr.find('.input-tingkat').val(),10)||0,
        semester: parseInt($tr.find('.input-semester').val(),10)||0,
        tp_text: txt,
        status_enum: ($tr.find('.input-status').val()||'Aktif')
      });
    });
    return rows;
  }

  // counter & notifikasi saat mengetik
  $('#tpTable').on('input','.input-tp', function(){
    var max=parseInt(this.getAttribute('maxlength')||this.dataset.max||TP_MAX_LEN,10);
    var len=(this.value||'').length;
    var $c=$(this).closest('td').find('.tp-counter');
    $c.text(len+'/'+max)
      .toggleClass('warn', len>=max-10 && len<max)
      .toggleClass('full', len>=max);

    var ding = $c.data('ding')||0;
    if(len===max && !ding){ toast('Batas '+max+' karakter tercapai','warning'); $c.data('ding',1); }
    if(len<max && ding){ $c.data('ding',0); }
  });

  // Events
  $('#mapelSelect').on('change', function(){
    mapelId = parseInt($(this).val(),10)||0;
    updateActions();
    loadData();
  });
  
  // Event ketika Semester Diubah
  $('#semesterSelect').on('change', function(){
    semesterVal = parseInt($(this).val(), 10)||1;
    loadData(); // Muat ulang data berdasarkan semester yang baru dipilih
  });

  $('#btnEdit').on('click', function(){
    if (!mapelId){ toast('Silakan pilih mapel terlebih dahulu.','info'); return; }
    setMode(!isEdit);
  });

  $('#btnAddRow').on('click', function(){
    // Tambah baris baru defaultnya mengikuti semester yang dipilih di filter atas
    dataset.push({ tp_id:0, tingkat:(tingkatOptions[0]?tingkatOptions[0].val:7), semester:semesterVal, tp_text:'', status_enum:'Aktif' });
    $(this).removeClass('pulse-hint'); /* hentikan pulse setelah dipakai */
    renderTable();
  });

  $('#tpTable').on('click', '.btn-delete', function(){
    if (!isEdit) return;
    var $tr = $(this).closest('tr');
    var id  = parseInt($tr.attr('data-id'),10)||0;
    var idx = $tr.index();

    var confirmDelete = function(cb){
      if (window.Swal){
        Swal.fire({
          title: 'Hapus baris ini?',
          text: 'Baris akan ditandai untuk dihapus dan akan benar-benar terhapus setelah Anda menekan "Simpan Perubahan".',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, tandai hapus',
          cancelButtonText: 'Batal'
        }).then(function(r){ if (r.isConfirmed) cb(); });
      } else {
        if (confirm('Hapus baris ini? Data akan terhapus setelah Anda menekan "Simpan Perubahan".')) cb();
      }
    };

    confirmDelete(function(){
      if (id>0) deleted.push(id);
      dataset.splice(idx,1);
      renderTable();
      toast('Baris ditandai untuk dihapus. Klik "Simpan Perubahan" untuk menerapkan.', 'info');
    });
  });

  $('#tpTable').on('click', '.tp-status-badge.view-only', function(){
    if (isEdit) return;
    var $tr = $(this).closest('tr');
    var id  = parseInt($tr.attr('data-id'),10)||0;
    if (!id) return;
    $.post('tujuan_pembelajaran.php?action=toggle', { tp_id:id }, function(r){
      if (r && r.ok){ loadData(); }
      else if (r && r.error){ toast(r.error,'error'); }
    }, 'json');
  });

  $('#btnCancel').on('click', function(){ setMode(false); });

  $('#btnSave').on('click', function(){
    if (!mapelId){ toast('Silakan pilih mapel terlebih dahulu.','info'); return; }
    var rows = collectRowsFromDOM();
    for (var i=0;i<rows.length;i++){
      if (!rows[i].tingkat || !rows[i].semester || !rows[i].tp_text){
        toast('Lengkapi Tingkat, Semester, dan Tujuan pada baris ke-'+(i+1),'warning'); return;
      }
      if ((rows[i].tp_text||'').length > TP_MAX_LEN){
        toast('TP baris ke-'+(i+1)+' melebihi '+TP_MAX_LEN+' karakter','warning'); return;
      }
    }
    var $btn = $(this).prop('disabled',true).text('Menyimpan...');
    $.ajax({
      url: 'tujuan_pembelajaran.php?action=save',
      method: 'POST',
      dataType: 'json',
      data: { mapel_id: mapelId, rows: JSON.stringify(rows), deleted: JSON.stringify(deleted) }
    }).done(function(r){
      if (r && r.ok){
        if (window.Swal) Swal.fire('Berhasil','Perubahan disimpan.','success');
        setMode(false);
        loadData();
      } else {
        var er = (r && r.error) ? r.error : 'Gagal menyimpan';
        if (window.Swal) Swal.fire('Gagal', er, 'error'); else alert(er);
      }
    }).fail(function(xhr){
      var er = 'Gagal menyimpan.';
      if (xhr && xhr.responseJSON && xhr.responseJSON.error) er = xhr.responseJSON.error;
      if (window.Swal) Swal.fire('Gagal', er, 'error'); else alert(er);
    }).always(function(){ $btn.prop('disabled',false).html('<i class="fa fa-save"></i> Simpan Perubahan'); });
  });

  $('#btnImport').on('click', function(){
    if (!mapelId){ toast('Silakan pilih mapel terlebih dahulu.','info'); return; }
    $('#importModal').modal('show');
  });

  $('#formImport').on('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this); fd.append('mapel_id', mapelId);
    $.ajax({ url:'tujuan_pembelajaran.php?action=import', method:'POST', data:fd, processData:false, contentType:false, dataType:'json' })
      .done(function(r){
        if (r && r.ok){ $('#importModal').modal('hide'); loadData();
          var msg='Import selesai. Ditambahkan: '+(r.inserted||0)+', dilewati: '+(r.skipped||0);
          if (window.Swal) Swal.fire('Berhasil', msg, 'success'); else alert(msg);
        } else { var er=(r&&r.error)?r.error:'Gagal import'; if (window.Swal) Swal.fire('Gagal',er,'error'); else alert(er); }
      }).fail(function(xhr){
        var er='Terjadi kesalahan jaringan';
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) er = xhr.responseJSON.error;
        if (window.Swal) Swal.fire('Gagal',er,'error'); else alert(er);
      });
  });

  // Auto-select jika hanya 1 mapel
  if ($('#mapelSelect option').length===2){
    $('#mapelSelect').prop('selectedIndex',1).trigger('change');
  } else {
    updateActions();
  }
})();
</script>

<?php require_once 'footer.php'; ?>