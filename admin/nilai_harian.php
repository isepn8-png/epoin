<?php
/**
 * EPS — Input Nilai Harian (Admin)
 * File : admin/nilai_harian.php
 * Versi: v2025-10-21.r296 + Excel (v2025-10-22.r1)
 * Perubahan fokus: Export CSV diganti tombol "Export Excel" (endpoint baru ?ajax=export_excel)
 * REVISI TERBARU: Full Support Multi-Semester (Filter UI, Create Set, & Filter Riwayat)
 */

if (function_exists('mysqli_report')) @mysqli_report(MYSQLI_REPORT_OFF);

/* ===== tahan header untuk AJAX ===== */
ob_start();
include __DIR__ . '/header.php';     // harus menyediakan $koneksi/$mysqli dan session
$__HEADER_HTML = ob_get_clean();

/* ===== koneksi ===== */
$DB = null;
if (isset($koneksi) && $koneksi instanceof mysqli && $koneksi->connect_errno===0) $DB=$koneksi;
elseif (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->connect_errno===0) $DB=$mysqli;
if (!$DB) { header('Content-Type:text/plain; charset=utf-8'); die('Koneksi DB tidak tersedia'); }
@mysqli_set_charset($DB,'utf8mb4');

/* ===== helpers ===== */
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function json_out($a){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($a); exit; }
function db_all(mysqli $db,$q){ $o=[]; $r=@mysqli_query($db,$q); if($r instanceof mysqli_result){ while($x=@mysqli_fetch_assoc($r)) $o[]=$x; @mysqli_free_result($r);} return $o; }
function db_one(mysqli $db,$q){ $r=@mysqli_query($db,$q); if($r instanceof mysqli_result){ $x=@mysqli_fetch_assoc($r); @mysqli_free_result($r); return $x?:null;} return null; }
function db_exec(mysqli $db,$q){ $ok=@mysqli_query($db,$q); if(!$ok){ @error_log('[SQL] '.mysqli_error($db).' | '.$q); } return $ok; }
function table_exists(mysqli $db,$t){ $t=@mysqli_real_escape_string($db,$t); $r=@mysqli_query($db,"SHOW TABLES LIKE '$t'"); return $r && mysqli_num_rows($r)>0; }
function table_cols(mysqli $db,$t){ $a=[]; $r=@mysqli_query($db,"SHOW COLUMNS FROM `".$t."`"); if($r){ while($x=@mysqli_fetch_assoc($r)) $a[]=$x['Field']; @mysqli_free_result($r);} return $a; }
function pick_col($have,$cand){ foreach($cand as $c){ if(in_array($c,$have,true)) return $c; } return null; }

/* ===== konteks ===== */
$SEKOLAH_ID = (int) (db_one($DB,"SELECT sekolah_id FROM sekolah LIMIT 1")['sekolah_id'] ?? 0);
$TA = db_one($DB,"SELECT * FROM ta WHERE ta_status=1 LIMIT 1");
if(!$TA) $TA = db_one($DB,"SELECT * FROM ta ORDER BY ta_id DESC LIMIT 1");
$TA_ID = (int)($TA['ta_id'] ?? 0);

/* Jul–Des = 1 ; Jan–Jun = 2 */
$SEM_AUTOMATIS = (int)(date('n')>=7 ? 1 : 2);

/* User & Admin (toleran) */
@session_start();
$USER_ID = 0;
foreach (['id','user_id','id_user','userid','uid','id_guru','guru_user_id','idguru','id_admin'] as $k) {
  if (isset($_SESSION[$k]) && (int)$_SESSION[$k] > 0) { $USER_ID = (int)$_SESSION[$k]; break; }
}
$admin_roles = ['admin','administrator','superadmin','super_admin','super admin','root'];
$is_admin = false;
if (function_exists('_is_admin')) { $is_admin = !!_is_admin(); }
if (!$is_admin){
  foreach (['level','level_name','role','roles','akses','hak_akses','tipe','type'] as $k){
    if (isset($_SESSION[$k]) && in_array(strtolower((string)$_SESSION[$k]), $admin_roles, true)){ $is_admin = true; break; }
  }
}
if (!$is_admin && (isset($_SESSION['username']) && strtolower((string)$_SESSION['username'])==='superadmin')) $is_admin = true;
if (!$is_admin && isset($_SESSION['is_admin'])) $is_admin = (bool)$_SESSION['is_admin'];
if (!$is_admin && $USER_ID===1) $is_admin = true;

/* ===== deteksi skema nilai_harian_* (legacy vs modern) ===== */
$cols_set  = table_cols($DB,'nilai_harian_set');
$cols_kol  = table_cols($DB,'nilai_harian_penilaian');
$cols_nilai= table_cols($DB,'nilai_harian_nilai');

$SCHEMA = 'legacy';
if (in_array('status',$cols_set,true) && in_array('tanggal',$cols_set,true)) $SCHEMA='modern';

$LEG_HAS_STATUS  = in_array('status',$cols_set,true);
$LEG_HAS_TA_TEXT = in_array('tahun_ajaran',$cols_set,true);

/* ===== deteksi kolom lain ===== */
$kCols = table_exists($DB,'kelas') ? table_cols($DB,'kelas') : [];
$K_ID   = pick_col($kCols,['kelas_id','id','id_kelas']);
$K_NAMA = pick_col($kCols,['kelas_nama','nama_kelas','nama','kelas']);
$K_TA   = pick_col($kCols,['kelas_ta','ta_id','tahunajaran_id','tahun_ajaran_id']);

$mCols = table_exists($DB,'mapel') ? table_cols($DB,'mapel') : [];
$M_ID   = pick_col($mCols,['mapel_id','id','id_mapel']);
$M_NAMA = pick_col($mCols,['mapel_nama','nama_mapel','nama','mapel']);

$sCols = table_exists($DB,'siswa') ? table_cols($DB,'siswa') : [];
$S_ID   = pick_col($sCols,['siswa_id','id','id_siswa']);
$S_NAMA = pick_col($sCols,['siswa_nama','nama_siswa','nama','nama_lengkap']);
$S_NIS  = pick_col($sCols,['siswa_nis','nis','no_induk','nipd']);
$S_NISN = pick_col($sCols,['siswa_nisn','nisn']);

$ksCols = table_exists($DB,'kelas_siswa') ? table_cols($DB,'kelas_siswa') : [];
$KS_KELAS = pick_col($ksCols,['kelas_id','ks_kelas','id_kelas','kelas']);
$KS_SISWA = pick_col($ksCols,['siswa_id','ks_siswa','id_siswa']);

$pCols = table_exists($DB,'pengampu_mapel') ? table_cols($DB,'pengampu_mapel') : [];
$P_TA     = pick_col($pCols,['ta_id','tahunajaran_id','tahun_ajaran_id','id_ta']);
$P_KELAS  = pick_col($pCols,['kelas_id','id_kelas','idkelas','kelas']);
$P_MAPEL  = pick_col($pCols,['mapel_id','id_mapel','mapel']);
$P_GURU   = pick_col($pCols,['guru_user_id','guru_id','user_id','id_user','id_guru']);

/* ===== helper: jenis_id legacy ===== */
function pilih_jenis_id(mysqli $db, array $selected){
  $target='PH';
  $s = array_map('strtolower',$selected);
  $map = ['ulangan'=>'PH','tugas'=>'Tugas','praktik'=>'Praktik','projek'=>'Proyek','proyek'=>'Proyek','kuis'=>'Kuis'];
  if (count($s)===1){ foreach ($map as $k=>$v){ if (in_array($k,$s,true)){ $target=$v; break; } } }
  $row = db_one($db, "SELECT jenis_id FROM jenis_penilaian WHERE LOWER(jenis_nama)=LOWER('".$db->real_escape_string($target)."') LIMIT 1");
  return (int)($row['jenis_id'] ?? 1);
}

/* ===================================================================
 * AJAX
 * =================================================================== */
if (isset($_GET['ajax'])) {
  $act = $_GET['ajax'];

  if ($act==='ping') json_out(['ok'=>true,'php'=>PHP_VERSION, 'schema'=>$SCHEMA]);

  /* 1) scope: kelas & mapel */
  if ($act==='scope'){
    $kelas=[]; $mapel=[];
    $whereTA = ($K_TA && $TA_ID>0) ? " WHERE k.`$K_TA`={$TA_ID} " : "";

    // KELAS
    if ($K_ID && $K_NAMA){
      if (!$is_admin && table_exists($DB,'pengampu_mapel') && $P_TA && $P_KELAS && $P_GURU) {
        $condKlsTA = ($K_TA && $TA_ID>0) ? " AND k.`$K_TA`={$TA_ID} " : "";
        $kelas = db_all($DB,"SELECT DISTINCT k.`$K_ID` AS kelas_id, k.`$K_NAMA` AS nama
                             FROM pengampu_mapel p
                             JOIN kelas k ON k.`$K_ID`=p.`$P_KELAS`
                             WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID} $condKlsTA
                             ORDER BY k.`$K_NAMA`");
        if (!$kelas) {
          $kelas = db_all($DB,"SELECT k.`$K_ID` AS kelas_id, k.`$K_NAMA` AS nama FROM kelas k $whereTA ORDER BY k.`$K_NAMA`");
        }
      } else {
        $kelas = db_all($DB,"SELECT k.`$K_ID` AS kelas_id, k.`$K_NAMA` AS nama FROM kelas k $whereTA ORDER BY k.`$K_NAMA`");
      }
    }

    // MAPEL
    if ($M_ID && $M_NAMA){
      if (!$is_admin && table_exists($DB,'pengampu_mapel') && $P_TA && $P_MAPEL && $P_GURU) {
        $mapel = db_all($DB,"SELECT DISTINCT m.`$M_ID` AS id, m.`$M_NAMA` AS nama
                             FROM pengampu_mapel p
                             JOIN mapel m ON m.`$M_ID`=p.`$P_MAPEL`
                             WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID}
                             ORDER BY m.`$M_NAMA`");
        if (!$mapel) { $mapel = db_all($DB,"SELECT `$M_ID` AS id, `$M_NAMA` AS nama FROM mapel ORDER BY `$M_NAMA`"); }
      } else {
        $mapel = db_all($DB,"SELECT `$M_ID` AS id, `$M_NAMA` AS nama FROM mapel ORDER BY `$M_NAMA`");
      }
    }

    json_out(['ok'=>true,'ta_id'=>$TA_ID,'semester_auto'=>$SEM_AUTOMATIS,'kelas'=>$kelas,'mapel'=>$mapel,'is_admin'=>$is_admin]);
  }

  /* 2) mapel tergantung kelas */
  if ($act==='list_mapel'){
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
    if ($M_ID && $M_NAMA){
      if (!$is_admin && table_exists($DB,'pengampu_mapel') && $P_TA && $P_KELAS && $P_MAPEL && $P_GURU){
        $wK = $kelas_id>0 ? "AND p.`$P_KELAS`={$kelas_id}" : "";
        $mapel = db_all($DB,"SELECT DISTINCT m.`$M_ID` AS id, m.`$M_NAMA` AS nama
                             FROM pengampu_mapel p
                             JOIN mapel m ON m.`$M_ID`=p.`$P_MAPEL`
                             WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID} $wK
                             ORDER BY m.`$M_NAMA`");
        if (!$mapel) $mapel = db_all($DB,"SELECT `$M_ID` AS id, `$M_NAMA` AS nama FROM mapel ORDER BY `$M_NAMA`");
      } else {
        $mapel = db_all($DB,"SELECT `$M_ID` AS id, `$M_NAMA` AS nama FROM mapel ORDER BY `$M_NAMA`");
      }
    } else { $mapel=[]; }
    json_out(['ok'=>true,'data'=>$mapel]);
  }

  /* 3) daftar siswa by kelas */
  if ($act==='list_siswa'){
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
    $data=[];
    if ($kelas_id>0 && $KS_KELAS && $KS_SISWA && $S_ID && $S_NAMA && table_exists($DB,'kelas_siswa') && table_exists($DB,'siswa')){
      $selNIS  = $S_NIS  ? ", s.`$S_NIS`  AS nis"  : ", '' AS nis";
      $selNISN = $S_NISN ? ", s.`$S_NISN` AS nisn" : ", '' AS nisn";
      $data = db_all($DB,"SELECT s.`$S_ID` AS siswa_id, s.`$S_NAMA` AS nama $selNIS $selNISN
                          FROM kelas_siswa ks
                          JOIN siswa s ON s.`$S_ID`=ks.`$KS_SISWA`
                          WHERE ks.`$KS_KELAS`={$kelas_id}
                          ORDER BY s.`$S_NAMA`");
    }
    json_out(['ok'=>true,'data'=>$data]);
  }

  /* 4) daftar TP (opsional) - PERHATIKAN PARAM SEMESTER DITERIMA */
  if ($act==='list_tp'){
    $mapel_id = (int)($_GET['mapel_id'] ?? 0);
    $tingkat  = (int)($_GET['tingkat'] ?? 0);
    $semester = (int)($_GET['semester'] ?? $SEM_AUTOMATIS); // Menerima parameter dari dropdown UI

    if (!$mapel_id || !$tingkat || !table_exists($DB,'tujuan_pembelajaran')){
      json_out(['ok'=>true,'data'=>[]]);
    }

    $has = table_cols($DB,'tujuan_pembelajaran');
    $TP_ID='tp_id'; $TP_TXT='tp_text'; $TP_MAP='mapel_id'; $TP_TING='tingkat'; $TP_SMT='semester'; $TP_STAT='status_enum';

    $condMap  = in_array($TP_MAP,$has,true)  ? " `$TP_MAP`={$mapel_id} " : " 1=1 ";
    $condTing = in_array($TP_TING,$has,true) ? " AND `$TP_TING`={$tingkat} " : "";
    $condSmt  = in_array($TP_SMT,$has,true)  ? " AND `$TP_SMT` IN (0,{$semester}) " : ""; // Filter semester aktif
    $condStat = in_array($TP_STAT,$has,true) ? " AND `$TP_STAT`='Aktif' " : "";

    $tp = db_all($DB,"SELECT `$TP_ID` AS tp_id, `$TP_TXT` AS nama
                      FROM tujuan_pembelajaran
                      WHERE $condMap $condTing $condSmt $condStat
                      ORDER BY `$TP_ID` DESC LIMIT 300");
    json_out(['ok'=>true,'data'=>$tp]);
  }

  /* 5) create set — validasi wajib Pertemuan & Keterangan + auto-create kolom */
  if ($act==='create_set'){
    $kelas_id = (int)($_POST['kelas_id']??0);
    $mapel_id = (int)($_POST['mapel_id']??0);
    $semester = (int)($_POST['semester'] ?? $SEM_AUTOMATIS); // Menggunakan parameter semester dari UI
    $tanggal  = $_POST['tanggal'] ?? date('Y-m-d');    // modern only
    $pertemuan= (int)($_POST['pertemuan_ke']??0);      // modern only
    $tp_id    = (int)($_POST['tp_id']??0);             // modern only
    $ket      = trim((string)($_POST['keterangan']??'')); // legacy: deskripsi
    $pengampu_id = $is_admin ? 0 : (int)$USER_ID;

    // Validasi wajib
    if ($pertemuan<=0) json_out(['ok'=>false,'error'=>'Pertemuan ke wajib diisi (>0)']);
    if ($ket==='')     json_out(['ok'=>false,'error'=>'Keterangan wajib diisi']);

    $jenisRaw = $_POST['jenis'] ?? [];
    if (!is_array($jenisRaw)) $jenisRaw = array_filter(array_map('trim', explode(',', (string)$jenisRaw)));
    $jenis = []; foreach($jenisRaw as $j){ $j=trim((string)$j); if($j!=='') $jenis[] = substr($j,0,40); }
    $jenis = array_values(array_unique($jenis));

    if ($SCHEMA==='modern'){
      $sql="INSERT INTO `nilai_harian_set`
            (`sekolah_id`,`ta_id`,`semester`,`kelas_id`,`mapel_id`,`pengampu_id`,`tanggal`,`pertemuan_ke`,`tp_id`,`keterangan`,`status`)
            VALUES (?,?,?,?,?,?,?,?,NULLIF(?,0),?,'draft')";
      $st=@mysqli_prepare($DB,$sql);
      if(!$st){ json_out(['ok'=>false,'error'=>'Gagal prepare (create_set-modern)','detail'=>mysqli_error($DB)]); }
      @mysqli_stmt_bind_param($st,'iiiiiisiis',
        $SEKOLAH_ID,$TA_ID,$semester,$kelas_id,$mapel_id,$pengampu_id,$tanggal,$pertemuan,$tp_id,$ket
      );
      $ok=@mysqli_stmt_execute($st); $id=$ok?@mysqli_insert_id($DB):0; if($st) @mysqli_stmt_close($st);
      if(!$ok){ json_out(['ok'=>false,'error'=>'Gagal membuat set','detail'=>mysqli_error($DB)]); }

      if ($id>0 && $jenis){
        $urut=1;
        $pst=@mysqli_prepare($DB,"INSERT INTO `nilai_harian_penilaian` (`nh_set_id`,`label`,`jenis`,`urut`) VALUES (?,?,?,?)");
        if($pst){
          foreach($jenis as $j){
            $label = ucwords(str_replace('_',' ',$j));
            @mysqli_stmt_bind_param($pst,'issi',$id,$label,$label,$urut);
            @mysqli_stmt_execute($pst);
            $urut++;
          }
          @mysqli_stmt_close($pst);
        }
      }

    } else {
      // ===== LEGACY =====
      $jenis_id = pilih_jenis_id($DB, $jenis ?: ['ulangan']);
      $jumlah_penilaian = max(1, count($jenis) ?: 1);
      $tahunajaran = $TA ? ($TA['ta_nama'] ?? null) : null;

      // Cek UNIQUE manual dengan filter semester yang benar
      $cek = db_one($DB, "SELECT nh_set_id FROM nilai_harian_set
                          WHERE mapel_id={$mapel_id} AND kelas_id={$kelas_id}
                            AND semester={$semester} AND jenis_id={$jenis_id}
                          LIMIT 1");
      if ($cek && isset($cek['nh_set_id'])){
        json_out(['ok'=>true,'set_id'=>(int)$cek['nh_set_id'], 'existing'=>true]);
      }

      $sql="INSERT INTO `nilai_harian_set`
            (`sekolah_id`,`mapel_id`,`kelas_id`,`semester`,`jenis_id`,`jumlah_penilaian`,`kkm`,`tahun_ajaran`,`deskripsi`,`created_at`,`updated_at`,`created_by`,`updated_by`)
            VALUES (?,?,?,?,?, ?, 75, ?, ?, NOW(), NOW(), ?, ?)";
      $st=@mysqli_prepare($DB,$sql);
      if(!$st){ json_out(['ok'=>false,'error'=>'Gagal prepare (create_set-legacy)','detail'=>mysqli_error($DB)]); }
      @mysqli_stmt_bind_param($st,'iiiiiissii',
        $SEKOLAH_ID,$mapel_id,$kelas_id,$semester,$jenis_id,$jumlah_penilaian,$tahunajaran,$ket,$USER_ID,$USER_ID
      );
      $ok=@mysqli_stmt_execute($st); $id=$ok?@mysqli_insert_id($DB):0; if($st) @mysqli_stmt_close($st);
      if(!$ok){ json_out(['ok'=>false,'error'=>'Gagal membuat set','detail'=>mysqli_error($DB)]); }

      $N = $jumlah_penilaian;
      $pst=@mysqli_prepare($DB,"INSERT IGNORE INTO `nilai_harian_penilaian` (`nh_set_id`,`ke`,`label`) VALUES (?,?,?)");
      if($pst){
        for($i=1;$i<=$N;$i++){
          $label = isset($jenis[$i-1]) ? ucwords(str_replace('_',' ',$jenis[$i-1])) : ('P'.$i);
          @mysqli_stmt_bind_param($pst,'iis',$id,$i,$label);
          @mysqli_stmt_execute($pst);
        }
        @mysqli_stmt_close($pst);
      }
    }

    json_out(['ok'=>true,'set_id'=>$id]);
  }

  /* 6) list set (riwayat) — SEARCH & PAGINATION + tampil Keterangan */
  if ($act==='list_sets'){
    $semester_filter = (int)($_GET['semester'] ?? $SEM_AUTOMATIS); // Ambil dari URL agar riwayat sesuai UI

    $kelasNameExpr = ($K_ID && $K_NAMA) ? "(SELECT k.`$K_NAMA` FROM kelas k WHERE k.`$K_ID`=s.kelas_id LIMIT 1)" : "CONCAT('K',s.kelas_id)";
    $mapelNameExpr = ($M_ID && $M_NAMA) ? "(SELECT m.`$M_NAMA` FROM mapel m WHERE m.`$M_ID`=s.mapel_id LIMIT 1)" : "CONCAT('M',s.mapel_id)";
    $taLabelModern  = "(SELECT t.ta_nama FROM ta t WHERE t.ta_id=s.ta_id LIMIT 1)";
    $taLabelLegacy  = ($LEG_HAS_TA_TEXT ? "s.tahun_ajaran" : "''");

    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = (int)($_GET['per_page'] ?? 10);
    if ($per_page < 1) $per_page = 10;
    if ($per_page > 100) $per_page = 100;
    $offset = ($page-1) * $per_page;
    $q = trim((string)($_GET['q'] ?? ''));
    $qesc = $DB->real_escape_string($q);

    // Filter Semester selalu diaktifkan pada List Riwayat
    $w = " WHERE s.semester={$semester_filter} ";
    
    if (!$is_admin){
      if ($SCHEMA==='modern' && in_array('pengampu_id',$cols_set,true)) {
        $w .= " AND s.pengampu_id={$USER_ID} ";
      } elseif (table_exists($DB,'pengampu_mapel') && $P_TA && $P_KELAS && $P_MAPEL && $P_GURU) {
        $w .= " AND EXISTS(SELECT 1 FROM pengampu_mapel p
                           WHERE p.`$P_TA`={$TA_ID}
                             AND p.`$P_GURU`={$USER_ID}
                             AND p.`$P_KELAS`=s.kelas_id
                             AND p.`$P_MAPEL`=s.mapel_id) ";
      }
    }

    $search = '';
    if ($q !== '') {
      if ($SCHEMA==='modern'){
        $search = " AND ( ($kelasNameExpr) LIKE '%{$qesc}%'
                       OR ($mapelNameExpr) LIKE '%{$qesc}%'
                       OR s.tanggal LIKE '%{$qesc}%'
                       OR s.status LIKE '%{$qesc}%'
                       OR s.keterangan LIKE '%{$qesc}%'
                       OR CAST(s.pertemuan_ke AS CHAR) LIKE '%{$qesc}%'
                     ) ";
      } else {
        $search = " AND ( ($kelasNameExpr) LIKE '%{$qesc}%'
                       OR ($mapelNameExpr) LIKE '%{$qesc}%'
                       OR COALESCE(s.deskripsi,'') LIKE '%{$qesc}%'
                       OR DATE_FORMAT(COALESCE(s.created_at,NOW()),'%Y-%m-%d') LIKE '%{$qesc}%'
                     ) ";
      }
    }
    $WALL = $w.$search;

    $cntRow = db_one($DB, "SELECT COUNT(*) AS n FROM nilai_harian_set s $WALL");
    $total = (int)($cntRow['n'] ?? 0);

    if ($SCHEMA==='modern'){
      $sets=db_all($DB,"SELECT
            s.nh_set_id, s.tanggal, s.pertemuan_ke, s.`status`, s.keterangan,
            s.semester, ($taLabelModern) AS ta_label, s.kelas_id, s.mapel_id,
            ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama,
            (SELECT COUNT(*) FROM nilai_harian_penilaian p WHERE p.nh_set_id=s.nh_set_id) AS n_kol,
            (SELECT COUNT(*) FROM nilai_harian_nilai h WHERE h.nh_set_id=s.nh_set_id) AS n_isi,
            s.pengampu_id
        FROM nilai_harian_set s
        $WALL
        ORDER BY s.nh_set_id DESC
        LIMIT {$per_page} OFFSET {$offset}");
    } else {
      $sets=db_all($DB,"SELECT
            s.nh_set_id,
            DATE_FORMAT(COALESCE(s.created_at, NOW()), '%Y-%m-%d') AS tanggal,
            NULL AS pertemuan_ke,
            'published' AS `status`,
            COALESCE(s.deskripsi,'') AS keterangan,
            s.semester, ($taLabelLegacy) AS ta_label, s.kelas_id, s.mapel_id,
            ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama,
            s.jumlah_penilaian AS n_kol,
            (SELECT COUNT(*) FROM nilai_harian_nilai h WHERE h.nh_set_id=s.nh_set_id) AS n_isi
        FROM nilai_harian_set s
        $WALL
        ORDER BY s.nh_set_id DESC
        LIMIT {$per_page} OFFSET {$offset}");
    }

    $out=[];
    $has_pengampu = table_exists($DB,'pengampu_mapel') && $P_TA && $P_KELAS && $P_MAPEL && $P_GURU;
    foreach($sets as $s){
      $owned = false;
      if ($SCHEMA==='modern' && isset($s['pengampu_id'])) $owned = ((int)$s['pengampu_id'] === $USER_ID);
      elseif (!$is_admin && $has_pengampu) {
        $row = db_one($DB,"SELECT 1 FROM pengampu_mapel p
                           WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID}
                             AND p.`$P_KELAS`=".(int)$s['kelas_id']."
                             AND p.`$P_MAPEL`=".(int)$s['mapel_id']." LIMIT 1");
        $owned = !!$row;
      }
      $s['can_delete'] = $is_admin || $owned;
      $out[] = $s;
    }

    $pages = (int)ceil($total / $per_page);
    json_out(['ok'=>true,'data'=>$out,'is_admin'=>$is_admin,'total'=>$total,'page'=>$page,'per_page'=>$per_page,'pages'=>$pages]);
  }

  /* 7) kolom & nilai */
  if ($act==='kolom_list'){
    $set_id=(int)($_GET['set_id']??0);
    if ($SCHEMA==='modern'){
      $kol=db_all($DB,"SELECT nh_penilaian_id,label,COALESCE(jenis,'') AS jenis,COALESCE(urut,1) AS urut
                       FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY urut,nh_penilaian_id");
    } else {
      $kol=db_all($DB,"SELECT nhp_id AS nh_penilaian_id, label, '' AS jenis, ke AS urut
                       FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY ke,nhp_id");
    }
    json_out(['ok'=>true,'data'=>$kol]);
  }

  if ($act==='kolom_add'){
    $set_id=(int)($_POST['set_id']??0);
    $label=trim((string)($_POST['label']??''));
    if ($SCHEMA==='modern'){
      $jenis=trim((string)($_POST['jenis']??'')); if($jenis==='') $jenis=null;
      if($label===''){ $r=db_one($DB,"SELECT COALESCE(MAX(urut),0)+1 AS u FROM nilai_harian_penilaian WHERE nh_set_id=$set_id"); $label='Kolom '.(int)($r['u']??1); }
      $urut=(int)($_POST['urut']??0); if($urut<=0){ $r=db_one($DB,"SELECT COALESCE(MAX(urut),0)+1 AS u FROM nilai_harian_penilaian WHERE nh_set_id=$set_id"); $urut=(int)($r['u']??1); }
      $st=@mysqli_prepare($DB,"INSERT INTO `nilai_harian_penilaian` (`nh_set_id`,`label`,`jenis`,`urut`) VALUES (?,?,?,?)");
      if(!$st) json_out(['ok'=>false,'error'=>'Gagal prepare (kolom_add-modern)','detail'=>mysqli_error($DB)]);
      @mysqli_stmt_bind_param($st,'issi',$set_id,$label,$jenis,$urut);
      $ok=@mysqli_stmt_execute($st); if($st) @mysqli_stmt_close($st);
      json_out(['ok'=>$ok?true:false,'detail'=>$ok?null:mysqli_error($DB)]);
    } else {
      if($label===''){ $r=db_one($DB,"SELECT COALESCE(MAX(ke),0)+1 AS u FROM nilai_harian_penilaian WHERE nh_set_id=$set_id"); $label='P'.(int)($r['u']??1); }
      $ke=(int)($_POST['urut']??0); if($ke<=0){ $r=db_one($DB,"SELECT COALESCE(MAX(ke),0)+1 AS u FROM nilai_harian_penilaian WHERE nh_set_id=$set_id"); $ke=(int)($r['u']??1); }
      $st=@mysqli_prepare($DB,"INSERT INTO `nilai_harian_penilaian` (`nh_set_id`,`ke`,`label`) VALUES (?,?,?)");
      if(!$st) json_out(['ok'=>false,'error'=>'Gagal prepare (kolom_add-legacy)','detail'=>mysqli_error($DB)]);
      @mysqli_stmt_bind_param($st,'iis',$set_id,$ke,$label);
      $ok=@mysqli_stmt_execute($st); if($st) @mysqli_stmt_close($st);
      json_out(['ok'=>$ok?true:false,'detail'=>$ok?null:mysqli_error($DB)]);
    }
  }

  if ($act==='kolom_del'){
    $kol_id=(int)($_POST['kol_id']??0);
    if ($SCHEMA==='modern'){
      $ok=@mysqli_query($DB,"DELETE FROM `nilai_harian_penilaian` WHERE `nh_penilaian_id`=$kol_id LIMIT 1");
    } else {
      $ok=@mysqli_query($DB,"DELETE FROM `nilai_harian_penilaian` WHERE `nhp_id`=$kol_id LIMIT 1");
    }
    json_out(['ok'=>$ok?true:false]);
  }

  // Rename kolom
  if ($act==='kolom_rename'){
    $kol_id=(int)($_POST['kol_id']??0);
    $label=trim((string)($_POST['label']??''));
    if($label==='') json_out(['ok'=>false,'error'=>'Label kosong']);
    if ($SCHEMA==='modern'){
      $st=@mysqli_prepare($DB,"UPDATE `nilai_harian_penilaian` SET `label`=? WHERE `nh_penilaian_id`=? LIMIT 1");
    } else {
      $st=@mysqli_prepare($DB,"UPDATE `nilai_harian_penilaian` SET `label`=? WHERE `nhp_id`=? LIMIT 1");
    }
    if(!$st) json_out(['ok'=>false,'error'=>'Gagal prepare (kolom_rename)','detail'=>mysqli_error($DB)]);
    @mysqli_stmt_bind_param($st,'si',$label,$kol_id);
    $ok=@mysqli_stmt_execute($st); if($st) @mysqli_stmt_close($st);
    json_out(['ok'=>$ok?true:false]);
  }

  /* 8) muat tabel + nilai */
  if ($act==='load_table'){
    $set_id=(int)($_GET['set_id']??0);
    $row=db_one($DB,"SELECT kelas_id FROM nilai_harian_set WHERE nh_set_id=$set_id LIMIT 1");
    if(!$row) json_out(['ok'=>false,'error'=>'Set tidak ditemukan']);
    $kelas_id=(int)$row['kelas_id'];

    $nisSel  = $S_NIS  ? ", s.`$S_NIS`  AS nis"  : ", '' AS nis";
    $nisnSel = $S_NISN ? ", s.`$S_NISN` AS nisn" : ", '' AS nisn";
    $siswa = ($KS_KELAS && $KS_SISWA)
      ? db_all($DB,"SELECT s.`$S_ID` AS siswa_id, s.`$S_NAMA` AS nama $nisSel $nisnSel
                    FROM kelas_siswa ks JOIN siswa s ON s.`$S_ID`=ks.`$KS_SISWA`
                    WHERE ks.`$KS_KELAS`=$kelas_id ORDER BY s.`$S_NAMA`")
      : [];

    if ($SCHEMA==='modern'){
      $kolom = db_all($DB,"SELECT nh_penilaian_id,label,COALESCE(jenis,'') AS jenis,COALESCE(urut,1) AS urut FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY urut,nh_penilaian_id");
    } else {
      $kolom = db_all($DB,"SELECT nhp_id AS nh_penilaian_id,label,'' AS jenis,ke AS urut FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY ke,nhp_id");
    }

    $nilai = db_all($DB,"SELECT nh_penilaian_id,siswa_id,skor,catatan FROM nilai_harian_nilai WHERE nh_set_id=$set_id");
    $v=[]; foreach($nilai as $n){ $sid=(int)$n['siswa_id']; $kid=(int)$n['nh_penilaian_id']; if(!isset($v[$sid])) $v[$sid]=[]; $v[$sid][$kid]=$n; }
    json_out(['ok'=>true,'data'=>['siswa'=>$siswa,'kolom'=>$kolom,'nilai'=>$v]]);
  }

  /* 9) simpan skor — integer 0–100 */
  if ($act==='save_score'){
    $set_id=(int)($_POST['set_id']??0);
    $kol_id=(int)($_POST['kol_id']??0);
    $siswa_id=(int)($_POST['siswa_id']??0);
    $skorRaw=trim((string)($_POST['skor']??'')); $catatan=trim((string)($_POST['catatan']??''));
    if ($skorRaw==='') { $val=null; }
    else {
      if(!ctype_digit($skorRaw)) json_out(['ok'=>false,'error'=>'Nilai harus bilangan bulat 0–100']);
      $val=(int)$skorRaw; if($val<0||$val>100) json_out(['ok'=>false,'error'=>'Nilai di luar 0–100']);
    }

    if ($val===null){
      $st=@mysqli_prepare($DB,"INSERT INTO `nilai_harian_nilai` (`nh_set_id`,`nh_penilaian_id`,`siswa_id`,`skor`,`catatan`)
                               VALUES (?,?,?,NULL,?)
                               ON DUPLICATE KEY UPDATE skor=VALUES(skor), catatan=VALUES(catatan), updated_at=CURRENT_TIMESTAMP");
      if(!$st) json_out(['ok'=>false,'error'=>'Gagal prepare (NULL)','detail'=>mysqli_error($DB)]);
      @mysqli_stmt_bind_param($st,'iiis',$set_id,$kol_id,$siswa_id,$catatan);
    } else {
      $st=@mysqli_prepare($DB,"INSERT INTO `nilai_harian_nilai` (`nh_set_id`,`nh_penilaian_id`,`siswa_id`,`skor`,`catatan`)
                               VALUES (?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE skor=VALUES(skor), catatan=VALUES(catatan), updated_at=CURRENT_TIMESTAMP");
      if(!$st) json_out(['ok'=>false,'error'=>'Gagal prepare (VAL)','detail'=>mysqli_error($DB)]);
      @mysqli_stmt_bind_param($st,'iiiis',$set_id,$kol_id,$siswa_id,$val,$catatan);
    }
    $ok=@mysqli_stmt_execute($st); if($st) @mysqli_stmt_close($st);
    json_out(['ok'=>$ok?true:false,'detail'=>$ok?null:mysqli_error($DB)]);
  }

  /* 10) publish */
  if ($act==='publish_set'){
    $set_id=(int)($_POST['set_id']??0);
    if ($LEG_HAS_STATUS){
      $st=@mysqli_prepare($DB,"UPDATE `nilai_harian_set` SET `status`='published' WHERE `nh_set_id`=? LIMIT 1");
      if(!$st) json_out(['ok'=>false,'error'=>'Gagal prepare (publish_set)','detail'=>mysqli_error($DB)]);
      @mysqli_stmt_bind_param($st,'i',$set_id);
      $ok=@mysqli_stmt_execute($st); if($st) @mysqli_stmt_close($st);
      json_out(['ok'=>$ok?true:false]);
    } else {
      json_out(['ok'=>true]); // legacy: tidak ada kolom status
    }
  }

  /* 11) meta_set — untuk judul tabel */
  if ($act==='meta_set'){
    $set_id=(int)($_GET['set_id']??0);
    $kelasNameExpr = ($K_ID && $K_NAMA) ? "(SELECT k.`$K_NAMA` FROM kelas k WHERE k.`$K_ID`=s.kelas_id LIMIT 1)" : "CONCAT('K',s.kelas_id)";
    $mapelNameExpr = ($M_ID && $M_NAMA) ? "(SELECT m.`$M_NAMA` FROM mapel m WHERE m.`$M_ID`=s.mapel_id LIMIT 1)" : "CONCAT('M',s.mapel_id)";
    $taLabelModern  = "(SELECT t.ta_nama FROM ta t WHERE t.ta_id=s.ta_id LIMIT 1)";
    $taLabelLegacy  = ($LEG_HAS_TA_TEXT ? "s.tahun_ajaran" : "''");

    if ($SCHEMA==='modern'){
      $meta = db_one($DB,"SELECT s.nh_set_id, s.kelas_id, s.mapel_id, s.semester, ($taLabelModern) AS ta_label,
                                 s.tanggal, s.pertemuan_ke, ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama
                          FROM nilai_harian_set s WHERE s.nh_set_id={$set_id} LIMIT 1");
      $jenis = db_all($DB,"SELECT DISTINCT TRIM(jenis) AS j FROM nilai_harian_penilaian WHERE nh_set_id={$set_id} AND jenis IS NOT NULL AND TRIM(jenis)<>'' ORDER BY j");
      $meta['jenis_list'] = array_values(array_unique(array_map(function($x){return $x['j'];}, $jenis)));
    } else {
      $meta = db_one($DB,"SELECT s.nh_set_id, s.kelas_id, s.mapel_id, s.semester, ($taLabelLegacy) AS ta_label,
                                 DATE_FORMAT(COALESCE(s.created_at,NOW()),'%Y-%m-%d') AS tanggal,
                                 NULL AS pertemuan_ke, ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama,
                                 s.jenis_id, s.jumlah_penilaian
                          FROM nilai_harian_set s WHERE s.nh_set_id={$set_id} LIMIT 1");
      $jp = $meta && isset($meta['jenis_id']) ? db_one($DB,"SELECT jenis_nama FROM jenis_penilaian WHERE jenis_id=".(int)$meta['jenis_id']." LIMIT 1") : null;
      $meta['jenis_list'] = $jp ? [$jp['jenis_nama']] : ['Penilaian'];
    }
    json_out(['ok'=>true,'meta'=>$meta]);
  }

  /* 12) hapus set (tetap) */
  if ($act==='delete_set'){
    $set_id=(int)($_POST['set_id']??0);
    $s = db_one($DB,"SELECT nh_set_id, kelas_id, mapel_id, ".(in_array('pengampu_id',$cols_set,true)?'pengampu_id':'0 AS pengampu_id')." FROM nilai_harian_set WHERE nh_set_id={$set_id} LIMIT 1");
    if(!$s) json_out(['ok'=>false,'error'=>'Set tidak ditemukan']);
    $allowed = false;
    if ($is_admin) $allowed=true;
    else if ($SCHEMA==='modern' && in_array('pengampu_id',$cols_set,true) && (int)$s['pengampu_id']==USer_ID) $allowed=true; // tidak diubah
    else if (table_exists($DB,'pengampu_mapel') && $P_TA && $P_KELAS && $P_MAPEL && $P_GURU){
      $row=db_one($DB,"SELECT 1 FROM pengampu_mapel p WHERE p.`$P_TA`={$TA_ID} AND p.`$P_GURU`={$USER_ID} AND p.`$P_KELAS`=".(int)$s['kelas_id']." AND p.`$P_MAPEL`=".(int)$s['mapel_id']." LIMIT 1");
      $allowed = !!$row;
    }
    if(!$allowed) json_out(['ok'=>false,'error'=>'Anda tidak berwenang menghapus set ini']);

    @mysqli_begin_transaction($DB);
    $ok1 = db_exec($DB,"DELETE FROM nilai_harian_nilai WHERE nh_set_id={$set_id}");
    $ok2 = db_exec($DB,"DELETE FROM nilai_harian_penilaian WHERE nh_set_id={$set_id}");
    $ok3 = db_exec($DB,"DELETE FROM nilai_harian_set WHERE nh_set_id={$set_id} LIMIT 1");
    if($ok1 && $ok2 && $ok3){ @mysqli_commit($DB); json_out(['ok'=>true]); }
    @mysqli_rollback($DB);
    json_out(['ok'=>false,'error'=>'Gagal menghapus set','detail'=>mysqli_error($DB)]);
  }

  /* 13) Rekap (tetap) */
  if ($act==='rekap_set'){
    $set_id=(int)($_GET['set_id']??0);

    $kelasNameExpr = ($K_ID && $K_NAMA) ? "(SELECT k.`$K_NAMA` FROM kelas k WHERE k.`$K_ID`=s.kelas_id LIMIT 1)" : "CONCAT('K',s.kelas_id)";
    $mapelNameExpr = ($M_ID && $M_NAMA) ? "(SELECT m.`$M_NAMA` FROM mapel m WHERE m.`$M_ID`=s.mapel_id LIMIT 1)" : "CONCAT('M',s.mapel_id)";
    $taLabelModern  = "(SELECT t.ta_nama FROM ta t WHERE t.ta_id=s.ta_id LIMIT 1)";
    $taLabelLegacy  = ($LEG_HAS_TA_TEXT ? "s.tahun_ajaran" : "''");

    if ($SCHEMA==='modern'){
      $meta = db_one($DB,"SELECT s.nh_set_id, s.tanggal, s.pertemuan_ke, s.semester, ($taLabelModern) AS ta_label,
                                 ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama
                          FROM nilai_harian_set s WHERE s.nh_set_id={$set_id} LIMIT 1");
    } else {
      $meta = db_one($DB,"SELECT s.nh_set_id,
                                 DATE_FORMAT(COALESCE(s.created_at, NOW()), '%Y-%m-%d') AS tanggal,
                                 NULL AS pertemuan_ke, s.semester, ($taLabelLegacy) AS ta_label,
                                 ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama
                          FROM nilai_harian_set s WHERE s.nh_set_id={$set_id} LIMIT 1");
    }
    if(!$meta) json_out(['ok'=>false,'error'=>'Set tidak ditemukan']);

    $row=db_one($DB,"SELECT kelas_id FROM nilai_harian_set WHERE nh_set_id=$set_id LIMIT 1");
    $kelas_id=(int)($row['kelas_id'] ?? 0);

    $nisSel  = $S_NIS  ? ", s.`$S_NIS`  AS nis"  : ", '' AS nis";
    $nisnSel = $S_NISN ? ", s.`$S_NISN` AS nisn" : ", '' AS nisn";
    $siswa = ($KS_KELAS && $KS_SISWA)
      ? db_all($DB,"SELECT s.`$S_ID` AS siswa_id, s.`$S_NAMA` AS nama $nisSel $nisnSel
                    FROM kelas_siswa ks JOIN siswa s ON s.`$S_ID`=ks.`$KS_SISWA`
                    WHERE ks.`$KS_KELAS`=$kelas_id ORDER BY s.`$S_NAMA`")
      : [];

    if ($SCHEMA==='modern'){
      $kolom = db_all($DB,"SELECT nh_penilaian_id,label,COALESCE(jenis,'') AS jenis,COALESCE(urut,1) AS urut FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY urut,nh_penilaian_id");
    } else {
      $kolom = db_all($DB,"SELECT nhp_id AS nh_penilaian_id,label,'' AS jenis,ke AS urut FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY ke,nhp_id");
    }
    $nilai = db_all($DB,"SELECT nh_penilaian_id,siswa_id,skor FROM nilai_harian_nilai WHERE nh_set_id=$set_id");
    $map=[]; foreach($nilai as $n){ $map[(int)$n['siswa_id']][(int)$n['nh_penilaian_id']] = ($n['skor']===null?null:(float)$n['skor']); }

    json_out(['ok'=>true,'meta'=>$meta,'siswa'=>$siswa,'kolom'=>$kolom,'nilai'=>$map]);
  }

  /* ============== 14) Export Excel ============== */
  if ($act==='export_excel'){
    $set_id=(int)($_GET['set_id']??0);

    $kelasNameExpr = ($K_ID && $K_NAMA) ? "(SELECT k.`$K_NAMA` FROM kelas k WHERE k.`$K_ID`=s.kelas_id LIMIT 1)" : "CONCAT('K',s.kelas_id)";
    $mapelNameExpr = ($M_ID && $M_NAMA) ? "(SELECT m.`$M_NAMA` FROM mapel m WHERE m.`$M_ID`=s.mapel_id LIMIT 1)" : "CONCAT('M',s.mapel_id)";
    $taLabelModern  = "(SELECT t.ta_nama FROM ta t WHERE t.ta_id=s.ta_id LIMIT 1)";
    $taLabelLegacy  = ($LEG_HAS_TA_TEXT ? "s.tahun_ajaran" : "''");

    if ($SCHEMA==='modern'){
      $meta = db_one($DB,"SELECT s.nh_set_id, s.semester, ($taLabelModern) AS ta_label,
                                 ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama, s.tanggal, s.pertemuan_ke
                          FROM nilai_harian_set s WHERE s.nh_set_id={$set_id} LIMIT 1");
    } else {
      $meta = db_one($DB,"SELECT s.nh_set_id, s.semester, ($taLabelLegacy) AS ta_label,
                                 ($kelasNameExpr) AS kelas_nama, ($mapelNameExpr) AS mapel_nama,
                                 DATE_FORMAT(COALESCE(s.created_at,NOW()),'%Y-%m-%d') AS tanggal, NULL AS pertemuan_ke
                          FROM nilai_harian_set s WHERE s.nh_set_id={$set_id} LIMIT 1");
    }
    if(!$meta){ header('Content-Type: text/plain; charset=utf-8'); echo "Set tidak ditemukan"; exit; }

    $row=db_one($DB,"SELECT kelas_id FROM nilai_harian_set WHERE nh_set_id=$set_id LIMIT 1");
    $kelas_id=(int)($row['kelas_id'] ?? 0);

    $nisSel  = $S_NIS  ? ", s.`$S_NIS`  AS nis"  : ", '' AS nis";
    $nisnSel = $S_NISN ? ", s.`$S_NISN` AS nisn" : ", '' AS nisn";
    $siswa = ($KS_KELAS && $KS_SISWA)
      ? db_all($DB,"SELECT s.`$S_ID` AS siswa_id, s.`$S_NAMA` AS nama $nisSel $nisnSel
                    FROM kelas_siswa ks JOIN siswa s ON s.`$S_ID`=ks.`$KS_SISWA`
                    WHERE ks.`$KS_KELAS`=$kelas_id ORDER BY s.`$S_NAMA`")
      : [];

    if ($SCHEMA==='modern'){
      $kolom = db_all($DB,"SELECT nh_penilaian_id,label FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY urut,nh_penilaian_id");
    } else {
      $kolom = db_all($DB,"SELECT nhp_id AS nh_penilaian_id,label FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY ke,nhp_id");
    }

    $nilai = db_all($DB,"SELECT nh_penilaian_id,siswa_id,skor FROM nilai_harian_nilai WHERE nh_set_id=$set_id");
    $map=[]; foreach($nilai as $n){ $map[(int)$n['siswa_id']][(int)$n['nh_penilaian_id']] = ($n['skor']===null?null:(int)$n['skor']); }

    $fn = 'NilaiHarian_Set'.$set_id.'_'.preg_replace('/\s+/','',$meta['kelas_nama']).'_'.preg_replace('/\s+/','',$meta['mapel_nama']).'.xls';

    // Output sebagai HTML table dengan MIME Excel (tanpa dependency)
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$fn.'"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Styling sederhana agar rapi di Excel
    $css = "
      <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 6px; }
        thead th { background: #E6F0FF; font-weight: bold; text-align: center; }
        .meta { border: none; }
        .meta td { border: none; padding: 2px 4px; }
        .num { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
      </style>
    ";

    echo "<html><head><meta charset='utf-8'>{$css}</head><body>";
    echo "<table class='meta'>
            <tr><td class='bold'>Rekap Nilai Harian</td></tr>
            <tr><td>&nbsp;</td></tr>
            <tr><td><table class='meta'>
              <tr><td class='bold'>Kelas</td><td>: ".esc($meta['kelas_nama'])."</td></tr>
              <tr><td class='bold'>Mapel</td><td>: ".esc($meta['mapel_nama'])."</td></tr>
              <tr><td class='bold'>Semester</td><td>: ".esc($meta['semester'])." (".( ($meta['semester']==1)?'Ganjil':'Genap' ).")</td></tr>
              <tr><td class='bold'>Tahun Ajaran</td><td>: ".esc($meta['ta_label'])."</td></tr>
              <tr><td class='bold'>Tanggal</td><td>: ".esc((string)$meta['tanggal'])."</td></tr>
              <tr><td class='bold'>Pertemuan</td><td>: ".esc((string)$meta['pertemuan_ke'])."</td></tr>
            </table></td></tr>
          </table>";

    // Header tabel
    $ths = "<th>No</th><th>NIS.NISN</th><th>Nama</th>";
    foreach($kolom as $k){ $ths .= "<th>".esc($k['label'])."</th>"; }
    $ths .= "<th>Jumlah</th><th>Rata-rata</th>";

    echo "<table><thead><tr>{$ths}</tr></thead><tbody>";

    $no=0;
    foreach($siswa as $s){
      $no++;
      $nisn = trim(($s['nisn']??'').($s['nis']?(' / '.$s['nis']):''));
      $sum=0; $cnt=0;
      $tds  = "<td class='num'>{$no}</td>";
      $tds .= "<td>".esc($nisn)."</td>";
      $tds .= "<td>".esc($s['nama'] ?? '')."</td>";
      foreach($kolom as $k){
        $v = $map[(int)$s['siswa_id']][(int)$k['nh_penilaian_id']] ?? '';
        if($v!=='' && $v!==null){ $sum += (int)$v; $cnt++; }
        $tds .= "<td class='num'>".(($v===''||$v===null)?'':(int)$v)."</td>";
      }
      $tds .= "<td class='num'>".($cnt?$sum:'')."</td>";
      $tds .= "<td class='num'>".($cnt?round($sum/$cnt):'')."</td>";
      echo "<tr>{$tds}</tr>";
    }

    echo "</tbody></table>";
    echo "</body></html>";
    exit;
  }

  json_out(['ok'=>false,'error'=>'Aksi tidak dikenali']);
}

/* =================== VIEW (HTML) =================== */
echo $__HEADER_HTML;
?>
<style>
  .content-wrapper { background:#F6F9FF }
  .eps-container { padding:16px 12px 24px }
  @media(min-width:992px){ .eps-container{ padding:22px 20px 30px } }

  /* Poles judul & batas konten agar seragam (point 7) */
  .page-title{display:flex;align-items:center;gap:12px;margin:6px 0 16px}
  .page-title .title-icon{width:46px;height:46px;border-radius:12px;background:#F1F5FF;border:1px solid #DCE7FF;display:flex;align-items:center;justify-content:center;font-size:22px}
  .page-title h1{margin:0;font-size:22px;font-weight:900;color:#111827;letter-spacing:.2px}
  .page-title .title-badges{display:flex;gap:6px;margin-top:6px}
  .badge-soft{background:#EEF3FF;border:1px solid #D7E6FF;border-radius:999px;padding:3px 10px;font-size:12px;font-weight:700;color:#0B57D0}

  .box{background:#fff;border-radius:16px;box-shadow:0 6px 18px rgba(0,0,0,.06);overflow:hidden;border:1px solid #EEF3FF}
  .box .bar{height:6px;background:linear-gradient(90deg,#0B57D0,#2B7BFF)}
  .box .box-hd{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #EEF3FF}
  .box .box-tt{font-weight:800;color:#111827;font-size:18px;letter-spacing:.2px}
  .sub-tt{font-weight:600;color:#374151;font-size:13px;margin-left:8px}

  .status-dot{display:inline-block;width:10px;height:10px;border-radius:50%}
  .status-draft{background:#FFB020}.status-published{background:#22C55E}
  .btn-grad{background:linear-gradient(90deg,#0B57D0,#2B7BFF);color:#fff;border:none;border-radius:12px;padding:8px 14px;font-weight:700;box-shadow:0 6px 14px rgba(11,87,208,.18)}
  .btn-soft{background:#F3F7FF;border:1px solid #D7E6FF;color:#0B57D0;border-radius:10px;padding:8px 12px;font-weight:700}
  .btn-publish{background:linear-gradient(90deg,#10B981,#22C55E);color:#fff;border:none;border-radius:12px;padding:8px 14px;font-weight:800;box-shadow:0 6px 14px rgba(16,185,129,.22)}
  .btn-danger-soft{background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;border-radius:10px;padding:8px 12px;font-weight:700}
  .btn-undo{background:#FFF7ED;border:1px solid #FED7AA;color:#9A3412;border-radius:10px;padding:8px 12px;font-weight:700}
  .btn-rekap{background:linear-gradient(90deg,#7C3AED,#A78BFA);color:#fff;border:none;border-radius:12px;padding:8px 14px;font-weight:800;display:inline-flex;gap:8px;align-items:center;box-shadow:0 6px 14px rgba(124,58,237,.18)}

  .btn-bulk{display:none}

  .jenis-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .chip2{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:6px 12px;font-weight:700;border:1px solid #E5E7EB;cursor:pointer;user-select:none;transition:.15s}
  .chip2 .badge{background:#EEF2FF;border:1px solid #D1D5FF;border-radius:6px;padding:2px 6px;font-size:11px}
  .chip2[data-on="0"]{background:#fff;color:#111827}
  .chip2[data-on="1"]{background:#0B57D0;color:#fff;border-color:#0B57D0}
  .chip2[data-on="1"] .badge{background:#fff;color:#0B57D0;border-color:#fff}

  /* Helper tip — kecil & eye-catching */
  .helper-tip{background:#F8FAFF;border:1px dashed #C7D7FF;padding:10px 12px;border-radius:12px;color:#334155;font-weight:600;display:flex;gap:8px;align-items:flex-start;margin:10px 12px;font-size:12px}
  .helper-tip .emoji{font-size:16px;line-height:1}

  /* Kontrol baris tunggal — super responsive */
  .control-grid{display:grid;gap:12px;align-items:end; padding:12px 12px 4px;}
  @media(min-width:1280px){ .control-grid{grid-template-columns: repeat(7, minmax(160px,1fr));} } /* Diperlebar jadi 7 untuk tempat semester */
  @media(min-width:992px) and (max-width:1279px){ .control-grid{grid-template-columns: repeat(4, minmax(180px,1fr));} }
  @media(min-width:576px) and (max-width:991px){ .control-grid{grid-template-columns: repeat(2, minmax(160px,1fr));} }
  @media(max-width:575px){ .control-grid{grid-template-columns: 1fr;} }

  /* Picker compact */
  .pick-compact .input-group{max-width:240px}
  .kelas-compact .input-group{max-width:204px}
  #kelasPicker,#mapelPicker{cursor:pointer}
  #kelasPicker:hover,#mapelPicker:hover{border-color:#93C5FD}

  /* Tanggal & Pertemuan diperkecil lagi ~10% */
  .tgl-compact input.form-control{max-width:142px}
  .pertemuan-compact input.form-control{max-width:138px}
  .ket-compact input.form-control{max-width:340px}

  #pertemuan::placeholder, #ket::placeholder{font-size:12px;color:#6B7280;opacity:1}

  .info-tip{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;margin-left:6px;border-radius:50%;background:#E6F0FF;border:1px solid #C7D7FF;font-weight:800;color:#0B57D0;cursor:help;font-size:12px}

  @media(max-width:767px){
    .kelas-compact .input-group,
    .tgl-compact input.form-control,
    .pertemuan-compact input.form-control,
    .ket-compact input.form-control,
    .pick-compact .input-group{ max-width:100% }
    .addjenis .input-group{ max-width:100% }
  }

  table.table-eps{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:12px}
  table.table-eps th,table.table-eps td{border-bottom:1px solid #EEF3FF;padding:10px}
  table.table-eps thead th{background:linear-gradient(90deg,#F8FAFF,#F3F7FF);font-weight:800;color:#0B57D0;position:sticky;top:0;z-index:1}
  table.table-eps tbody tr:nth-child(even){background:#FAFCFF}
  table.table-eps tbody tr:hover{background:#EEF6FF;transition:.12s}
  .cell-nilai input{width:90px;text-align:center}
  .cell-nilai input[disabled]{background:#F3F4F6;color:#9CA3AF}
  .save-indicator{font-size:12px;margin-left:8px}
  .undo-flash{animation:flash 1s ease-in-out 1}
  @keyframes flash{0%{box-shadow:0 0 0 0 rgba(11,87,208,.4)}100%{box-shadow:0 0 0 12px rgba(11,87,208,0)}}

  .th-wrap{display:flex;align-items:center;justify-content:center;gap:6px}
  .th-lock{background:#F3F7FF;border:1px solid #D7E6FF;border-radius:6px;padding:2px 6px;font-size:12px;cursor:pointer}
  .th-label{cursor:pointer;border-bottom:1px dashed transparent}
  .th-label:hover{border-color:#93C5FD}

  .chip{display:inline-flex;align-items:center;gap:8px;background:#EEF3FF;border:1px solid #D7E6FF;border-radius:999px;padding:6px 10px;margin:4px 6px 0 0}
  .chip .x{cursor:pointer}
  .toast-mini{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;opacity:0;transform:translateY(10px);transition:.25s;z-index:1060}
  .toast-mini.show{opacity:1;transform:none}
  .toast-mini.warn{background:#DC2626}
  .toast-mini.okay{background:#065F46}

  .tip-publish{position:fixed;right:16px;bottom:60px;background:#10B981;color:#fff;padding:10px 14px;border-radius:10px;opacity:0;transform:translateY(10px);transition:.25s;z-index:1060;box-shadow:0 10px 20px rgba(0,0,0,.12)}
  .tip-publish.show{opacity:1;transform:none}

  /* Rekap modal */
  #rekapWrap table{width:100%;border-collapse:collapse}
  #rekapWrap th,#rekapWrap td{border:1px solid #E5E7EB;padding:8px}
  #rekapWrap thead th{background:#F8FAFF}
  .rekap-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:12px;flex-wrap:wrap}
  .rekap-meta{display:flex;flex-wrap:wrap;gap:8px}
  .meta-chip{display:inline-flex;align-items:center;gap:8px;background:#FFFFFF;border:1px solid #D7E6FF;border-radius:999px;padding:6px 12px;font-weight:700;color:#0B57D0;box-shadow:0 2px 8px rgba(11,87,208,.06);transition:transform .12s, box-shadow .12s}
  .meta-chip .i{width:20px;height:20px;border-radius:6px;background:#E6EFFF;border:1px solid #D7E6FF;display:inline-flex;align-items:center;justify-content:center;font-size:12px}
  .meta-chip:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(11,87,208,.12)}

  .btn-csv{background:linear-gradient(90deg,#0EA5E9,#22D3EE);color:#fff !important;border:none;border-radius:12px;padding:10px 14px;font-weight:800;display:inline-flex;gap:10px;align-items:center;box-shadow:0 8px 16px rgba(14,165,233,.25);text-decoration:none}
  .btn-csv:hover{filter:saturate(1.05);transform:translateY(-1px);box-shadow:0 10px 20px rgba(14,165,233,.32)}

  .pager{display:flex;gap:6px;align-items:center;justify-content:flex-end;margin-top:8px}
  .pager .btn-soft[disabled]{opacity:.5;cursor:not-allowed}

  @media print {.modal,.btn,.page-title,.box .bar,.btn-bulk,.btn-publish,.btn-grad,.btn-soft,.btn-undo,.btn-rekap{display:none !important}}

  /* Tambah Jenis diperkecil */
  .addjenis .input-group{ max-width:30%; background:#F6FAFF; border:1px solid #D7E6FF; border-radius:10px; padding:4px; }
  .addjenis .input-group input.form-control{ background:#F8FBFF; border:1px solid #CFE1FF; box-shadow: inset 0 1px 2px rgba(11,87,208,.06); }
  .addjenis .input-group .btn{ background:linear-gradient(90deg,#5B8CFF,#7AA6FF); color:#fff; border:none; font-weight:800; border-radius:8px; }
  .addjenis .input-group .btn:hover{ filter:saturate(1.05); }
</style>

<div class="content-wrapper">
  <section class="content">
    <div class="container-fluid eps-container">

      <div class="row"><div class="col-lg-12">
        <div class="page-title">
          <div class="title-icon">📚</div>
          <div>
            <h1>Input Nilai Harian</h1>
            <div class="title-badges">
              <div class="badge-soft">Nilai Pengetahuan</div>
              <div class="badge-soft">Keterampilan</div>
            </div>
          </div>
        </div>
      </div></div>

      <div class="row"><div class="col-lg-12">
        <div class="box">
          <div class="bar"></div>
          <div class="box-hd">
            <div class="box-tt">🧩 Set Nilai</div>
            <div style="display:flex;gap:8px;align-items:center">
              <button id="btnListSet" class="btn-soft" title="Lihat daftar set sebelumnya">🕘 Riwayat Set</button>
            </div>
          </div>

          <div class="control-grid">
            
            <div class="pick-compact">
              <label>📆 Pilih Semester</label>
              <select id="selSemester" class="form-control" style="border-radius:10px;">
                <option value="1">1 (Ganjil)</option>
                <option value="2">2 (Genap)</option>
              </select>
            </div>

            <div class="pick-compact kelas-compact">
              <label>🧑‍🏫 Pilih Kelas</label>
              <div class="input-group">
                <input id="kelasPicker" class="form-control" placeholder="Klik untuk memilih…" readonly>
                <span class="input-group-btn"><button id="btnPickKelas" type="button" class="btn btn-default">Pilih</button></span>
              </div>
              <select id="selKelas" class="form-control" style="display:none"></select>
            </div>

            <div class="pick-compact">
              <label>📘 Pilih Mapel <span id="mapelInfo" class="info-tip" title="—"></span></label>
              <div class="input-group">
                <input id="mapelPicker" class="form-control" placeholder="Klik untuk memilih…" readonly>
                <span class="input-group-btn"><button id="btnPickMapel" type="button" class="btn btn-default">Pilih</button></span>
              </div>
              <select id="selMapel" class="form-control" style="display:none"></select>
            </div>

            <div class="tgl-compact"><label>📅 Tanggal</label><input id="tanggal" type="date" class="form-control" value="<?= esc(date('Y-m-d')) ?>"></div>

            <div class="pertemuan-compact">
              <label>🔢 Pertemuan ke</label>
              <input id="pertemuan" type="number" min="1" class="form-control" placeholder="(wajib diisi – mis. 1)" required>
            </div>

            <div><label>🎯 TP (opsional)</label><select id="tp" class="form-control"></select></div>

            <div class="ket-compact">
              <label>📝 Keterangan</label>
              <input id="ket" type="text" class="form-control" placeholder="(wajib isi – mis. Ulangan)" required>
            </div>
          </div>

          <div style="padding:0 12px 12px">
            <div class="box-tt" style="font-size:16px;margin:8px 0">🏷️ Jenis Penilaian</div>

            <div class="helper-tip" title="Kolom = komponen penilaian (mis. Ulangan, Tugas, Praktik)">
              <div class="emoji">💡</div>
              <div>
                Pilih jenis yang relevan. Saat <b>Buat Set Baru</b>, kolom penilaian otomatis dibuat dengan nama yang sama (mis. <b>Ulangan</b>, <b>Tugas</b>). Klik nama kolom di header untuk <b>rename</b> cepat.
              </div>
            </div>

            <div class="jenis-wrap" id="jenisWrap"></div>
            <div class="addjenis" style="gap:8px;margin-top:8px">
              <div class="input-group" title="Ketik nama jenis baru lalu klik Tambah">
                <input id="inpJenis" type="text" class="form-control" placeholder="Tambah jenis (mis. Portofolio)">
                <span class="input-group-btn"><button id="btnAddJenis" class="btn btn-default" title="Tambah jenis penilaian baru">Tambah</button></span>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:10px;padding:12px">
            <button id="btnBuat" class="btn-grad">✨ Buat Set Baru</button>
            <button id="btnRekap" class="btn-rekap" disabled><span>📊</span><span>Rekap &amp; Export</span></button>
          </div>
        </div>
      </div></div>

      <div class="row" id="kolBox" style="display:none;margin-top:16px"><div class="col-lg-12">
        <div class="box">
          <div class="bar"></div>
          <div class="box-hd">
            <div class="box-tt">📝 Kolom Penilaian</div>
            <div style="display:flex;gap:8px;align-items:center">
              <input id="kolLabel" class="form-control" style="width:220px" placeholder="mis. Ulangan Bab 2" title="Contoh label: Ulangan, Kuis 1, Proyek 1">
              <button id="btnKolAdd" class="btn-grad" title="Tambah satu kolom penilaian ke set aktif">+ Tambah Kolom</button>
            </div>
          </div>
          <div style="padding:12px">
            <div id="kolChips"></div>
          </div>
        </div>
      </div></div>

      <div class="row" style="margin-top:16px"><div class="col-lg-12">
        <div class="box">
          <div class="bar"></div>
          <div class="box-hd">
            <div class="box-tt">🧑‍🎓 Daftar Siswa <span id="tableMeta" class="sub-tt"></span></div>
            <div style="display:flex;gap:8px;align-items:center">
              <button id="btnUndo" class="btn-undo" disabled title="↩︎ Batalkan perubahan terakhir (Ctrl+Z)">↩︎ Undo</button>
              <button id="btnPublish" class="btn-publish" disabled title="💾 Simpan &amp; Publish (Ctrl+S)">💾 Publish</button>
              <span id="setStatus" class="save-indicator"></span>
              <span id="saveInfo" class="save-indicator"></span>
            </div>
          </div>

          <div class="helper-tip" title="Panduan pengisian nilai">
            <div class="emoji">🧩</div>
            <div>
              <b>Masukkan nilai 0–100</b> (tanpa desimal) langsung pada sel. <b>Enter / ↓</b> turun baris di kolom yang sama, <b>↑</b> naik baris. <b>Gembok</b> di header untuk kunci/buka kolom. Salah input? <b>Undo</b> atau <b>Ctrl+Z</b>.
            </div>
          </div>

          <div style="padding:12px">
            <div id="tblWrap" style="overflow:auto">
              <table class="table-eps" id="tbl">
                <thead><tr id="tblHead"></tr></thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </div></div>

      <div class="toast-mini" id="toast">Tersimpan</div>
      <div class="toast-mini warn" id="toastDel">Set telah dihapus</div>
      <div class="tip-publish" id="tipPublish">✔ Disimpan & dipublish</div>

      <div class="modal fade" id="mdlSet" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">🕘 Riwayat Set</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
              <div id="listSet"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal fade" id="pickModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header" style="background:#0B57D0;color:#fff">
              <h5 class="modal-title" id="pickTitle">Pilih</h5>
              <button type="button" class="close" data-dismiss="modal" style="color:#fff">&times;</button>
            </div>
            <div class="modal-body">
              <input id="pickSearch" class="form-control" placeholder="Ketik untuk mencari…" style="margin-bottom:10px">
              <div id="pickList" class="list-group" style="max-height:60vh;overflow:auto;margin-bottom:0"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Tutup</button></div>
          </div>
        </div>
      </div>

      <div class="modal fade" id="mdlRekap" tabindex="-1">
        <div class="modal-dialog modal-xl">
          <div class="modal-content">
            <div class="modal-header" style="background:#0B57D0;color:#fff">
              <h5 class="modal-title">📊 Rekap Nilai Harian</h5>
              <button type="button" class="close" data-dismiss="modal" style="color:#fff">&times;</button>
            </div>
            <div class="modal-body">
              <div class="rekap-head">
                <div class="rekap-meta" id="rekapMeta"></div>
                <div style="display:flex;gap:8px">
                  <a id="btnXls" class="btn-csv" href="#" title="Export tabel ke Excel">
                    <span>📥</span><span>Export Excel</span>
                  </a>
                </div>
              </div>
              <div id="rekapWrap"></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<script>
(function(){
  const qs=s=>document.querySelector(s), qsa=s=>Array.from(document.querySelectorAll(s));
  const SELF = window.location.pathname;

  let SET_ID=0, STATUS='draft', KOL=[], SISWA=[], VAL={}, debounceTimer=null;
  let TA_ID=0, SEM_AUTO=1, IS_ADMIN=false;
  let kelas=[], mapel=[];

  // Undo max 3
  const HISTORY=[];
  function updateUndoState(){ qs('#btnUndo').disabled = HISTORY.length===0; }

  const LOCKED_COLS = {};
  const smtName=n=> (String(n)==='1'?'Ganjil':'Genap');

  const JENIS_ABBR_MAP = {
    'ulangan':'UH','ph':'UH','penilaian harian':'UH',
    'tugas':'TG',
    'praktik':'PR','praktek':'PR',
    'proyek':'PJ','projek':'PJ',
    'kuis':'KS'
  };

  const JENIS_DEFAULT = [
    {key:'ulangan', name:'Ulangan', icon:'🧪', badge:'UH'},
    {key:'tugas', name:'Tugas', icon:'📝', badge:'TG'},
    {key:'praktik', name:'Praktik', icon:'🔧', badge:'PR'},
    {key:'projek', name:'Proyek', icon:'📦', badge:'PJ'},
    {key:'kuis', name:'Kuis', icon:'🎯', badge:'KS'}
  ];
  let SELECTED_JENIS = new Set(['ulangan']);

  function toast(m, id='toast'){ const t=qs('#'+id); t.textContent=m||t.textContent; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),1400); }
  function tipPublish(){ const p=qs('#tipPublish'); p.classList.add('show'); setTimeout(()=>p.classList.remove('show'),1500); }
  async function jget(u){ const r=await fetch(u,{cache:'no-store'}); try{return await r.json();}catch(e){console.error('JSON gagal:',u); return {ok:false};} }
  async function jpost(u,d){ const r=await fetch(u,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}); try{return await r.json();}catch(e){return {ok:false};} }
  function detectTingkatFromText(txt){ const m=(txt||'').match(/(\d{1,2})/); return m?parseInt(m[1],10):0; }
  function fillHiddenSelect(el, items, idKey, nameKey){ el.innerHTML=''; (items||[]).forEach(it=>{ const op=document.createElement('option'); op.value=String(it[idKey]); op.textContent=it[nameKey]; el.appendChild(op); }); }
  function setHelpMapel(){
    const el = qs('#mapelInfo');
    if (!el) return;
    el.title = IS_ADMIN
      ? 'Admin: menampilkan semua mata pelajaran.'
      : 'Mapel sesuai pengampu akun (TA aktif) — jika kosong sistem fallback ke semua mapel.';
  }

  async function loadScope(){
    const js=await jget(SELF+'?ajax=scope');
    if(!js||!js.ok) return;
    TA_ID=+js.ta_id||0; SEM_AUTO=+js.semester_auto||1; IS_ADMIN=!!js.is_admin;
    
    // Set UI dropdown ke Semester Auto berjalan
    qs('#selSemester').value = String(SEM_AUTO);

    kelas=js.kelas||[]; mapel=js.mapel||[]; setHelpMapel();
    fillHiddenSelect(qs('#selKelas'), kelas, 'kelas_id','nama');
    fillHiddenSelect(qs('#selMapel'), mapel, 'id','nama');
    if(kelas.length){ qs('#selKelas').value=String(kelas[0].kelas_id); qs('#kelasPicker').value=kelas[0].nama; }
    await loadMapelForKelas(); await refreshTP(); await loadSiswa();
  }
  async function loadMapelForKelas(){
    const kid=qs('#selKelas').value||'';
    const js=await jget(SELF+`?ajax=list_mapel&kelas_id=${encodeURIComponent(kid)}`);
    mapel=(js&&js.ok)?(js.data||[]):[]; fillHiddenSelect(qs('#selMapel'), mapel, 'id','nama');
    if(mapel.length){ qs('#selMapel').value=String(mapel[0].id); qs('#mapelPicker').value=mapel[0].nama; } else { qs('#selMapel').value=''; qs('#mapelPicker').value=''; }
  }
  async function loadSiswa(){
    const kid=qs('#selKelas').value||'';
    const js=await jget(SELF+`?ajax=list_siswa&kelas_id=${encodeURIComponent(kid)}`); SISWA=(js&&js.ok)?(js.data||[]):[]; renderTable();
  }
  async function refreshTP(){ 
    const kelasTxt=qs('#kelasPicker').value||''; 
    const tingkat=detectTingkatFromText(kelasTxt)||0; 
    const mid=qs('#selMapel').value||''; 
    const smt=qs('#selSemester').value||'1'; // Tambahan kirim param semester
    const el=qs('#tp'); el.innerHTML='<option value="">— pilih TP —</option>'; 
    if(!mid||!tingkat){ return; } 
    const js=await jget(SELF+`?ajax=list_tp&mapel_id=${encodeURIComponent(mid)}&tingkat=${tingkat}&semester=${smt}`); 
    (js.data||[]).forEach(it=>{ const o=document.createElement('option'); o.value=it.tp_id; o.textContent=it.nama; el.appendChild(o); }); 
  }

  // Reload TP saat ganti semester
  qs('#selSemester').addEventListener('change', async () => {
    await refreshTP();
  });

  // Picker modal
  let pickType='kelas';
  function openPicker(type){ pickType=type; const items=(type==='kelas'?kelas:mapel); qs('#pickTitle').textContent='Pilih ' + (type==='kelas'?'Kelas':'Mata Pelajaran'); rebuildPickList(items); $('#pickModal').modal('show'); setTimeout(()=>{ qs('#pickSearch').value=''; qs('#pickSearch').focus(); }, 250); }
  function rebuildPickList(items, q){ q=(q||'').toLowerCase(); const list=qs('#pickList'); list.innerHTML=''; (items||[]).forEach(it=>{ const name=it.nama||it.kelas_nama||''; const id=it.id||it.kelas_id; if(q && name.toLowerCase().indexOf(q)<0) return; const a=document.createElement('a'); a.className='list-group-item pickable'; a.dataset.id=id; a.dataset.name=name; a.textContent=name; list.appendChild(a); }); if(!list.children.length){ list.innerHTML='<div class="list-group-item text-muted">Tidak ada data.</div>'; } }
  qs('#btnPickKelas').addEventListener('click', ()=>openPicker('kelas'));
  qs('#btnPickMapel').addEventListener('click', ()=>openPicker('mapel'));
  qs('#kelasPicker').addEventListener('click', ()=>openPicker('kelas'));
  qs('#mapelPicker').addEventListener('click', ()=>openPicker('mapel'));
  qs('#pickSearch').addEventListener('input', e=>rebuildPickList(pickType==='kelas'?kelas:mapel, e.target.value));
  qs('#pickList').addEventListener('click', async e=>{ const a=e.target.closest('.pickable'); if(!a) return; if (pickType==='kelas'){ qs('#selKelas').value=String(a.dataset.id); qs('#kelasPicker').value=a.dataset.name; await loadMapelForKelas(); await refreshTP(); await loadSiswa(); } else { qs('#selMapel').value=String(a.dataset.id); qs('#mapelPicker').value=a.dataset.name; await refreshTP(); } $('#pickModal').modal('hide'); });

  // Chips jenis
  function renderJenisChips(){
    const wrap=qs('#jenisWrap'); wrap.innerHTML='';
    JENIS_DEFAULT.forEach(j=>{
      const on = SELECTED_JENIS.has(j.key) ? 1 : 0;
      const el=document.createElement('div');
      el.className='chip2';
      el.dataset.key=j.key;
      el.dataset.on=String(on);
      el.title = j.name;
      el.innerHTML = `${j.icon} ${j.name} <span class="badge">${j.badge}</span>`;
      el.addEventListener('click', ()=>{ const now = el.dataset.on==='1'; el.dataset.on = now ? '0' : '1'; if (now) SELECTED_JENIS.delete(j.key); else SELECTED_JENIS.add(j.key); });
      wrap.appendChild(el);
    });
  }
  qs('#btnAddJenis').addEventListener('click', ()=>{ const v=(qs('#inpJenis').value||'').trim(); if(!v) return; const key=v.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'').substring(0,40) || 'custom'; if (!JENIS_DEFAULT.find(x=>x.key===key)){ JENIS_DEFAULT.push({key, name:v.replace(/\b\w/g,m=>m.toUpperCase()), icon:'✨', badge:key.substring(0,2).toUpperCase()}); } SELECTED_JENIS.add(key); renderJenisChips(); qs('#inpJenis').value=''; });

  function renderTable(){
    const head=document.getElementById('tblHead'), body=document.querySelector('#tbl tbody');
    head.innerHTML=''; body.innerHTML='';
    const h=['<th style="width:60px">No</th>','<th style="width:150px">NIS / NISN</th>','<th>Nama Siswa</th>'];
    KOL.forEach(k=>{
      const lockIcon = LOCKED_COLS[k.nh_penilaian_id] ? '🔒' : '🔓';
      h.push(`<th style="width:160px" data-kol="${k.nh_penilaian_id}"><div class="th-wrap"><span class="th-label" data-rename="${k.nh_penilaian_id}" title="Klik untuk ganti nama kolom">${k.label}</span><button class="th-lock" data-kol="${k.nh_penilaian_id}" title="Kunci/Buka kolom">${lockIcon}</button></div></th>`);
    });
    h.push('<th style="width:90px">Jumlah</th>','<th style="width:90px">Rata-rata</th>'); head.innerHTML=h.join('');
    SISWA.forEach((s,idx)=>{ const tr=document.createElement('tr');
      const nisT=(s.nisn ? (s.nisn + (s.nis ? ' / '+s.nis : '')) : (s.nis||'')) || '';
      let cols=[`<td>${idx+1}</td>`,`<td>${nisT}</td>`,`<td>${s.nama||''}</td>`]; let sum=0,cnt=0;
      KOL.forEach(k=>{ const sid=s.siswa_id; const v=(VAL[sid]&&VAL[sid][k.nh_penilaian_id])?(VAL[sid][k.nh_penilaian_id].skor??''):''; if(v!==''&&!isNaN(+v)){ sum+=(+v); cnt++; }
        const locked = !!LOCKED_COLS[k.nh_penilaian_id];
        cols.push(`<td class="cell-nilai"><input title="Nilai 0–100 • Enter/↑/↓ = navigasi vertikal • Auto-save" type="number" step="1" min="0" max="100" maxlength="3" ${locked?'disabled':''} data-prev="${v}" data-row="${idx}" data-sid="${sid}" data-kol="${k.nh_penilaian_id}" value="${v}"></td>`); });
      const avg=cnt?Math.round(sum/cnt):''; cols.push(`<td class="td-center">${cnt?sum:''}</td>`,`<td class="td-center">${avg}</td>`); tr.innerHTML=cols.join(''); body.appendChild(tr);
    });
    bindInputs();
    bindHeaderActions();
  }
  function bindHeaderActions(){
    qsa('.th-lock').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const kid=btn.getAttribute('data-kol');
        LOCKED_COLS[kid] = !LOCKED_COLS[kid];
        qsa(`#tbl input[data-kol="${kid}"]`).forEach(i=>i.disabled=LOCKED_COLS[kid]);
        btn.textContent = LOCKED_COLS[kid] ? '🔒' : '🔓';
      });
    });
    qsa('.th-label').forEach(lbl=>{
      lbl.addEventListener('click', async ()=>{
        const kid=lbl.getAttribute('data-rename');
        const current=lbl.textContent.trim();
        const nama=prompt('Ubah nama kolom:', current);
        if(!nama || nama.trim()===current) return;
        const r=await jpost(SELF+'?ajax=kolom_rename',{kol_id:kid,label:nama.trim()});
        if(r && r.ok){ lbl.textContent=nama.trim(); toast('Nama kolom diubah','toast'); }
        else { alert('Gagal mengubah nama kolom'); }
      });
    });
  }
  function bindInputs(){
    qsa('#tbl input[type=number]').forEach(el=>{
      el.addEventListener('input',()=>{
        let v = el.value.replace(/\D/g,'');
        if(v.length>3) v=v.slice(0,3);
        if(v!=='' && +v>100) v='100';
        el.value=v;
        clearTimeout(debounceTimer); debounceTimer=setTimeout(()=>saveScore(el),500);
      });
      el.addEventListener('blur',()=>saveScore(el));
      el.addEventListener('keydown',ev=>{
        if(ev.key==='Enter'||ev.key==='ArrowDown'){ ev.preventDefault(); moveVertical(el,+1); }
        if(ev.key==='ArrowUp'){ ev.preventDefault(); moveVertical(el,-1); }
      });
    });
  }
  function moveVertical(el,dir){
    const kid=el.getAttribute('data-kol');
    const colInputs=qsa(`#tbl .cell-nilai input[data-kol="${kid}"]`);
    const i=colInputs.indexOf(el);
    if(i<0) return;
    const nx=colInputs[i+dir];
    if(nx){ nx.focus(); nx.select(); }
  }

  async function saveScore(el){
    if(!SET_ID) return;
    if(el.disabled) return;
    const sid=+el.getAttribute('data-sid'), kid=+el.getAttribute('data-kol'); let skor=(el.value||'').trim();
    if(skor!=='' && (!/^\d{1,3}$/.test(skor) || +skor<0 || +skor>100)){ el.classList.add('is-invalid'); return; } else el.classList.remove('is-invalid');

    const prev = el.getAttribute('data-prev') ?? '';
    if (String(prev) !== String(skor)) {
      HISTORY.push({sid,kid,prev,now:skor,el});
      if (HISTORY.length>3) HISTORY.shift();
      updateUndoState();
    }

    qs('#saveInfo').textContent='Menyimpan…';
    const js=await jpost(SELF+'?ajax=save_score',{set_id:SET_ID,kol_id:kid,siswa_id:sid,skor});
    qs('#saveInfo').textContent=js.ok?'Tersimpan':'Gagal';
    if(js.ok){
      el.setAttribute('data-prev', skor);
      toast('✓ Nilai tersimpan — Ctrl+Z untuk Undo','toast');
    }
  }

  async function refreshMetaTitle(){
    if(!SET_ID){ qs('#tableMeta').textContent=''; return; }
    const r=await jget(SELF+'?ajax=meta_set&set_id='+SET_ID);
    if(!r||!r.ok){ qs('#tableMeta').textContent=''; return; }
    const m=r.meta;
    const jenisRaw = (m.jenis_list||[]);
    const jenisAbbr = jenisRaw.map(x=>{
      const k = String(x||'').trim().toLowerCase();
      return ({"ulangan":"UH","ph":"UH","penilaian harian":"UH","tugas":"TG","praktik":"PR","praktek":"PR","proyek":"PJ","projek":"PJ","kuis":"KS"}[k]) || x;
    }).filter(Boolean).join(', ') || '-';
    qs('#tableMeta').textContent =
      `• ${m.kelas_nama||'-'} / ${m.mapel_nama||'-'} • Smt ${m.semester||'-'} (`+<?php echo json_encode(['1'=>'Ganjil','2'=>'Genap']); ?>[String(m.semester)]+`) • TA ${m.ta_label||'-'} • Jenis: ${jenisAbbr}`;
  }

  // Actions
  qs('#btnBuat').addEventListener('click', async()=>{
    const btn=qs('#btnBuat');
    const kelas_id=qs('#selKelas').value, 
          mapel_id=qs('#selMapel').value, 
          semester=qs('#selSemester').value, // Kirim semester pilihan UI
          tanggal=qs('#tanggal').value, 
          pertemuan=qs('#pertemuan').value, 
          tp_id=qs('#tp').value, 
          keterangan=qs('#ket').value;
    
    if(!kelas_id||!mapel_id){ alert('Pilih kelas & mapel terlebih dahulu.'); return; }
    if(!pertemuan || +pertemuan<=0){ alert('Pertemuan ke wajib diisi (angka > 0).'); qs('#pertemuan').focus(); return; }
    if(!keterangan || !keterangan.trim()){ alert('Keterangan wajib diisi.'); qs('#ket').focus(); return; }
    const jenis = Array.from(SELECTED_JENIS.values());
    btn.disabled=true; btn.textContent='Membuat…';
    const js=await jpost(SELF+'?ajax=create_set',{kelas_id, mapel_id, semester, tanggal, pertemuan_ke:pertemuan, tp_id, keterangan, jenis:jenis});
    btn.disabled=false; btn.textContent='✨ Buat Set Baru';
    if(!js.ok){ alert((js.error||'Gagal membuat set') + (js.detail?('\n'+js.detail):'')); return; }
    SET_ID=js.set_id; STATUS='draft'; qs('#btnPublish').disabled=false; qs('#btnRekap').disabled=false; qs('#setStatus').innerHTML=`<span class="status-dot status-draft"></span> draft`;
    document.getElementById('kolBox').style.display='block';
    HISTORY.length=0; updateUndoState();
    Object.keys(LOCKED_COLS).forEach(k=>delete LOCKED_COLS[k]);
    await reloadKolom(); await reloadTable(); await refreshMetaTitle();
    toast(js.existing ? 'Set sudah ada – dibuka.' : 'Set dibuat','toast');
    document.getElementById('kolBox').scrollIntoView({behavior:'smooth', block:'start'});
  });

  // Tambah Kolom
  qs('#btnKolAdd').addEventListener('click', async()=>{
    if(!SET_ID){ alert('Buat atau buka set dahulu.'); return; }
    const label=(qs('#kolLabel').value||'').trim();
    const r=await jpost(SELF+'?ajax=kolom_add',{set_id:SET_ID,label});
    if(r && r.ok){ qs('#kolLabel').value=''; await reloadKolom(); await reloadTable(); await refreshMetaTitle(); toast('Kolom ditambahkan','toast'); }
    else { alert('Gagal menambah kolom' + (r&&r.detail?('\n'+r.detail):'')); }
  });

  // Publish
  async function publishNow(){
    if(!SET_ID) return;
    qsa('#tbl .cell-nilai input').forEach(i=>{ i.dispatchEvent(new Event('blur')); });
    const js=await jpost(SELF+'?ajax=publish_set',{set_id:SET_ID});
    if(js.ok){ STATUS='published'; qs('#btnPublish').disabled=true; qs('#setStatus').innerHTML=`<span class="status-dot status-published"></span> published`; tipPublish(); }
    else { alert(js.error||'Gagal publish'); }
  }
  qs('#btnPublish').addEventListener('click', publishNow);

  // Ctrl+S / Ctrl+Z
  document.addEventListener('keydown', e=>{
    if((e.ctrlKey||e.metaKey) && (e.key==='s' || e.key==='S')){ e.preventDefault(); publishNow(); }
    if((e.ctrlKey||e.metaKey) && (e.key==='z' || e.key==='Z')){ e.preventDefault(); doUndo(); }
  });

  async function doUndo(){
    if(HISTORY.length===0) return;
    const last = HISTORY.pop();
    updateUndoState();
    const el = last.el || qs(`#tbl input[data-sid="${last.sid}"][data-kol="${last.kid}"]`);
    if(!el) return;
    el.value = (last.prev===''? '' : last.prev);
    el.classList.add('undo-flash');
    setTimeout(()=>el.classList.remove('undo-flash'), 900);
    await saveScore(el);
  }
  qs('#btnUndo').addEventListener('click', doUndo);

  async function reloadKolom(){ const js=await jget(SELF+'?ajax=kolom_list&set_id='+SET_ID); if(js.ok){ KOL=js.data||[]; const wrap=document.getElementById('kolChips'); wrap.innerHTML=''; KOL.forEach(k=>{ const chip=document.createElement('span'); chip.className='chip'; chip.title='Hapus kolom ini'; chip.innerHTML=`<b>${k.label}</b> <span class="x" data-del="${k.nh_penilaian_id}">✕</span>`; wrap.appendChild(chip); }); wrap.querySelectorAll('[data-del]').forEach(el=>el.addEventListener('click', async e=>{ const id=e.currentTarget.getAttribute('data-del'); if(!confirm('Hapus kolom ini?')) return; const r=await jpost(SELF+'?ajax=kolom_del',{kol_id:id}); if(r.ok){ await reloadKolom(); await reloadTable(); await refreshMetaTitle(); } })); renderTable(); } }
  async function reloadTable(){ const js=await jget(SELF+'?ajax=load_table&set_id='+SET_ID); if(js.ok){ SISWA=js.data.siswa||[]; VAL=js.data.nilai||{}; renderTable(); } }

  // ===== Riwayat set =====
  let LIST_PAGE=1, LIST_Q='', LIST_PER=10, LAST_PAGES=1;
  function buildRiwayatShell(){
    const wrap=qs('#listSet');
    wrap.innerHTML = `
      <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;margin-bottom:8px">
        <input id="riwayatSearch" class="form-control" placeholder="Cari kelas/mapel/tanggal/status/keterangan…" style="max-width:320px">
        <div class="text-muted" id="riwayatInfo" style="font-size:12px"></div>
      </div>
      <div id="riwayatList"></div>
      <div class="pager" id="riwayatPager"></div>
    `;
    qs('#riwayatSearch').value = LIST_Q;
    qs('#riwayatSearch').addEventListener('input', e=>{
      const v=e.target.value||'';
      clearTimeout(debounceTimer);
      debounceTimer=setTimeout(()=>{ LIST_Q=v.trim(); LIST_PAGE=1; loadRiwayat(); }, 300);
    });
  }
  function renderPager(total,page,pages){
    const p=qs('#riwayatPager'); p.innerHTML='';
    const mkBtn=(label,disabled,toPage)=>{
      const b=document.createElement('button');
      b.className='btn-soft'; b.textContent=label; if(disabled) b.disabled=true;
      if(!disabled){ b.addEventListener('click',()=>{ LIST_PAGE=toPage; loadRiwayat(); }); }
      return b;
    };
    p.appendChild(mkBtn('« Prev', page<=1, Math.max(1,page-1)));
    const start=Math.max(1, page-2), end=Math.min(pages, page+2);
    for(let i=start;i<=end;i++){
      const b=mkBtn(String(i), false, i);
      if(i===page){ b.style.background='#0B57D0'; b.style.color='#fff'; }
      p.appendChild(b);
    }
    p.appendChild(mkBtn('Next »', page>=pages, Math.min(pages,page+1)));
  }
  async function loadRiwayat(){
    const smtFilter = qs('#selSemester').value || SEM_AUTO; // Menarik filter dari dropdown Semester UI
    const r=await jget(SELF+`?ajax=list_sets&page=${LIST_PAGE}&per_page=${LIST_PER}&q=${encodeURIComponent(LIST_Q)}&semester=${smtFilter}`);
    const wrap=document.getElementById('riwayatList'); const info=qs('#riwayatInfo');
    wrap.innerHTML='';
    if(!r || !r.ok){ wrap.innerHTML='<div class="text-muted">Gagal memuat data.</div>'; return; }
    LAST_PAGES = r.pages||1;
    if ((r.data||[]).length===0){
      if (LIST_PAGE>1 && LIST_PAGE>LAST_PAGES){ LIST_PAGE = Math.max(1, LAST_PAGES); return loadRiwayat(); }
      wrap.innerHTML=`<div class="text-muted">Tidak ada riwayat set pada Semester ${smtFilter}.</div>`;
    }
    (r.data||[]).forEach(it=>{
      const row=document.createElement('div');
      row.style.cssText='display:flex;align-items:center;justify-content:space-between;border:1px solid #EEF3FF;border-radius:10px;padding:10px;margin:0 0 8px;background:#fff';
      const statusDot  = `<span class="status-dot ${it.status==='published'?'status-published':'status-draft'}"></span>`;
      const top = `${it.kelas_nama||'-'} / ${it.mapel_nama||'-'} • kolom ${it.n_kol||0}`;
      const meta = `${it.tanggal} • Pertemuan ${it.pertemuan_ke||'-'} • Smt ${it.semester||'-'} • TA ${it.ta_label||'-'} • ${statusDot} ${it.status} • ${it.n_isi||0} isian • Ket: ${it.keterangan||'-'}`;
      row.innerHTML=`<div><div style="font-weight:700">${top}</div><div class="text-muted" style="font-size:12px">${meta}</div></div>
                     <div style="display:flex;gap:6px">
                       ${it.can_delete?'<button class="btn-danger-soft" data-del="'+it.nh_set_id+'">🗑️ Hapus</button>':''}
                       <button class="btn-soft" data-open="${it.nh_set_id}">📂 Buka</button>
                     </div>`;
      wrap.appendChild(row);
    });
    info.textContent = `Total: ${r.total||0} • Halaman ${r.page||1} dari ${r.pages||1}`;

    wrap.querySelectorAll('button[data-open]').forEach(b=>b.addEventListener('click', async e=>{
      const id=e.currentTarget.getAttribute('data-open'); $('#mdlSet').modal('hide'); SET_ID=parseInt(id||0);
      qs('#btnPublish').disabled=false; qs('#btnRekap').disabled=false; document.getElementById('kolBox').style.display='block'; HISTORY.length=0; updateUndoState(); Object.keys(LOCKED_COLS).forEach(k=>delete LOCKED_COLS[k]); await reloadKolom(); await reloadTable(); await refreshMetaTitle();
    }));
    wrap.querySelectorAll('button[data-del]').forEach(b=>b.addEventListener('click', async e=>{
      const id=e.currentTarget.getAttribute('data-del'); if(!confirm('Yakin hapus set ini beserta isinya?')) return;
      const rr=await jpost(SELF+'?ajax=delete_set',{set_id:id});
      if(!rr.ok){ alert(rr.error||'Gagal menghapus'); return; }
      toast('Set telah dihapus','toastDel');
      loadRiwayat();
    }));
    renderPager(r.total||0, r.page||1, r.pages||1);
  }
  async function openListSetModal(){
    buildRiwayatShell();
    await loadRiwayat();
    $('#mdlSet').modal('show');
  }
  document.getElementById('btnListSet').addEventListener('click', ()=>{ LIST_PAGE=1; LIST_Q=''; openListSetModal(); });

  // Rekap & Export — set tombol ke Excel
  async function openRekap(){
    if(!SET_ID) return;
    const js=await jget(SELF+'?ajax=rekap_set&set_id='+SET_ID);
    if(!js||!js.ok){ alert(js&&js.error?js.error:'Gagal memuat rekap'); return; }
    const m=js.meta, siswa=js.siswa||[], kol=js.kolom||[], nilai=js.nilai||{};

    const chips = [
      {icon:'🏫', label:'Kelas', val:m.kelas_nama||'-'},
      {icon:'📘', label:'Mapel', val:m.mapel_nama||'-'},
      {icon:'🌓', label:'Semester', val:`${m.semester||'-'} (${smtName(m.semester)})`},
      {icon:'📅', label:'Tanggal', val:(m.tanggal||'-')},
      {icon:'🔢', label:'Pertemuan', val:(m.pertemuan_ke||'-')},
      {icon:'🏷️', label:'TA', val:(m.ta_label||'-')},
    ].map(c=>`<span class="meta-chip"><span class="i">${c.icon}</span><span>${c.label}:</span><b>${c.val}</b></span>`).join(' ');
    qs('#rekapMeta').innerHTML = chips;

    // Tombol diarahkan ke export_excel
    qs('#btnXls').href = SELF+`?ajax=export_excel&set_id=${SET_ID}`;

    const th = ['<th style="width:60px">No</th>','<th style="width:160px">NIS/NISN</th>','<th>Nama</th>'];
    kol.forEach(k=>th.push(`<th>${k.label}</th>`));
    th.push('<th>Jumlah</th>','<th>Rata-rata</th>');
    let html = `<table><thead><tr>${th.join('')}</tr></thead><tbody>`;
    let no=0;
    siswa.forEach(s=>{
      no++;
      const nisn = (s.nisn? s.nisn : '') + (s.nis? (' / '+s.nis) : '');
      let sum=0,cnt=0, tds=[`<td>${no}</td>`,`<td>${nisn}</td>`,`<td>${s.nama||''}</td>`];
      kol.forEach(k=>{
        const v = (nilai[s.siswa_id] && nilai[s.siswa_id][k.nh_penilaian_id]!==undefined) ? nilai[s.siswa_id][k.nh_penilaian_id] : '';
        if(v!=='' && v!==null){ sum+=Number(v); cnt++; }
        tds.push(`<td style="text-align:center">${(v===''||v===null)?'':v}</td>`);
      });
      tds.push(`<td style="text-align:center">${cnt?sum:''}</td>`,`<td style="text-align:center">${cnt?Math.round(sum/cnt):''}</td>`);
      html += `<tr>${tds.join('')}</tr>`;
    });
    html += '</tbody></table>';
    qs('#rekapWrap').innerHTML = html;
    $('#mdlRekap').modal('show');
  }
  qs('#btnRekap').addEventListener('click', openRekap);

  // INIT
  renderJenisChips();
  loadScope();

})();
</script>

<?php include __DIR__ . '/footer.php'; ?>