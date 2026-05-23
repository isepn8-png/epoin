<?php
/* =============================================================
 *  NILAI PTS — sederhana, stabil, dan terintegrasi TP
 *  - Hanya SATU kolom skor (PTS)
 *  - Otomatis membuat tabel penyimpanan jika belum ada
 *  - Kompatibel dengan variasi skema: siswa / kelas / mapel / kelas_siswa
 *  - AJAX-first (endpoint JSON TIDAK membocorkan HTML dari header.php)
 * ============================================================= */
if (session_status()===PHP_SESSION_NONE) session_start();

// ---------------------- UTIL UMUM (tanpa output) ----------------------
function _tbl_exists($c,$n){ $q=@mysqli_query($c,"SHOW TABLES LIKE '".mysqli_real_escape_string($c,$n)."'"); return $q&&mysqli_num_rows($q)>0; }
function _tbl_cols($c,$t){ $a=[];$q=@mysqli_query($c,"SHOW COLUMNS FROM `$t`"); if($q){while($r=mysqli_fetch_assoc($q))$a[]=$r['Field'];} return $a; }
function _pick_any($have,$cands){ foreach($cands as $n){ foreach([$n,strtolower($n),strtoupper($n)] as $p){ if(in_array($p,$have,true)) return $p; }} return null; }
function _roman_to_int($s){$m=['CM'=>900,'CD'=>400,'XC'=>90,'XL'=>40,'IX'=>9,'IV'=>4,'M'=>1000,'D'=>500,'C'=>100,'L'=>50,'X'=>10,'V'=>5,'I'=>1];$s=strtoupper(trim($s));$i=0;$o=0;while($i<strlen($s)){if($i+1<strlen($s)&&isset($m[$s[$i].$s[$i+1]])){$o+=$m[$s[$i].$s[$i+1]];$i+=2;}elseif(isset($m[$s[$i]])){$o+=$m[$s[$i]];$i++;}else{$i++;}}return $o?:null;}
function _boot_db(){
  // coba beberapa lokasi umum untuk koneksi.php (menyediakan $koneksi)
  $paths=[__DIR__.'/koneksi.php',__DIR__.'/../koneksi.php',dirname(__DIR__).'/koneksi.php',__DIR__.'/../config/koneksi.php',__DIR__.'/../../koneksi.php'];
  foreach($paths as $p){ if(is_file($p)){ require_once $p; if(isset($GLOBALS['koneksi'])) return $GLOBALS['koneksi']; }}
  // fallback: ambil dari header.php namun ditahan outputnya
  if(is_file(__DIR__.'/header.php')){ ob_start(); require __DIR__.'/header.php'; ob_end_clean(); if(isset($GLOBALS['koneksi'])) return $GLOBALS['koneksi']; }
  return null; // tidak ketemu
}
function _school_id($db){ $id=0; if($db&&$r=@mysqli_fetch_assoc(@mysqli_query($db,"SELECT sekolah_id FROM sekolah LIMIT 1"))) $id=(int)$r['sekolah_id']; return $id; }
function _json_error($msg){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// -------------------------- AJAX FIRST --------------------------
if (!empty($_GET['action'])){
  @ini_set('display_errors',0); header('Content-Type: application/json; charset=utf-8');
  $db=_boot_db(); if(!$db){ echo json_encode(['ok'=>false,'error'=>'Koneksi DB tidak ditemukan.']); exit; }
  $user_id=(int)($_SESSION['id']??0); $SEKOLAH_ID=_school_id($db);
  $now=date('Y-m-d H:i:s');

  // --- deteksi kolom dinamis ---
  $mapelCols=_tbl_cols($db,'mapel');
  $MAPEL_ID  = _pick_any($mapelCols,['mapel_id','id_mapel','id','kode_mapel']);
  $MAPEL_NAMA= _pick_any($mapelCols,['mapel_nama','nama_mapel','nama','mapel','nama_pelajaran']);

  $kelasCols=_tbl_cols($db,'kelas');
  $KELAS_ID  = _pick_any($kelasCols,['kelas_id','id_kelas','id','rombel_id']);
  $KELAS_NAMA= _pick_any($kelasCols,['kelas_nama','nama_kelas','rombel','kelas']);
  $KELAS_JUR = _pick_any($kelasCols,['kelas_jurusan','jurusan_id','id_jurusan','jurusan']);

  $jurCols=_tbl_cols($db,'jurusan');
  $JUR_ID   = _pick_any($jurCols,['jurusan_id','id_jurusan','id']);
  $JUR_NAMA = _pick_any($jurCols,['jurusan_nama','nama_jurusan','nama','jurusan']);

  $sCols=_tbl_cols($db,'siswa');
  $SISWA_ID   = _pick_any($sCols,['siswa_id','id_siswa','id','id_pd','peserta_didik_id']);
  $SISWA_NAMA = _pick_any($sCols,['siswa_nama','nama_siswa','nama','nama_lengkap']);
  $SISWA_NIS  = _pick_any($sCols,['siswa_nis','nis','nisn','nipd','no_induk']);
  $SISWA_KELAS= _pick_any($sCols,['kelas_id','id_kelas','rombel_id','rombel']); // fallback

  $ksExists=_tbl_exists($db,'kelas_siswa');
  $KS_KELAS=$KS_SISWA=$KS_STATUS=null;
  if($ksExists){ $ksCols=_tbl_cols($db,'kelas_siswa');
    $KS_KELAS=_pick_any($ksCols,['ks_kelas','kelas_id','id_kelas','rombel_id','kelas']);
    $KS_SISWA=_pick_any($ksCols,['ks_siswa','siswa_id','id_siswa','id_pd','peserta_didik_id']);
    $KS_STATUS=_pick_any($ksCols,['ks_status','status','aktif','keaktifan','is_active']);
    if(!$KS_KELAS||!$KS_SISWA) $ksExists=false; // tidak kompatibel
  }

  // TP
  $tpCols=_tbl_cols($db,'tujuan_pembelajaran');
  $TP_ID    = _pick_any($tpCols,['tp_id','id']);
  $TP_MAPEL = _pick_any($tpCols,['mapel_id','id_mapel']);
  $TP_TKT   = _pick_any($tpCols,['tingkat','level','kelas_tingkat']);
  $TP_SMT   = _pick_any($tpCols,['semester','smt']);
  $TP_TEXT  = _pick_any($tpCols,['tp_text','deskripsi','tujuan','uraian']);
  $TP_STATUS= _pick_any($tpCols,['status_enum','status']);

  // --- helper tingkat dari nama kelas / jurusan ---
  $get_tingkat=function($kelas_id) use($db,$KELAS_ID,$KELAS_NAMA,$KELAS_JUR,$JUR_ID,$JUR_NAMA){
    $kelas_id=(int)$kelas_id; $label='';
    if($KELAS_JUR && $JUR_ID && $JUR_NAMA){
      $sql="SELECT k.`$KELAS_NAMA` kn, j.`$JUR_NAMA` jn FROM kelas k LEFT JOIN jurusan j ON j.`$JUR_ID`=k.`$KELAS_JUR` WHERE k.`$KELAS_ID`=$kelas_id LIMIT 1";
      if($q=@mysqli_query($db,$sql)){ if($r=@mysqli_fetch_assoc($q)) $label=$r['jn']?:$r['kn']; }
    } else {
      $sql="SELECT `$KELAS_NAMA` kn FROM kelas WHERE `$KELAS_ID`=$kelas_id LIMIT 1";
      if($q=@mysqli_query($db,$sql)){ if($r=@mysqli_fetch_assoc($q)) $label=$r['kn']; }
    }
    $num=null; if($label && preg_match('~\b(\d{1,2})\b~',$label,$m)) $num=(int)$m[1];
    if(!$num && $label && preg_match('~\b([IVXLCM]{1,6})\b~i',$label,$m)) $num=_roman_to_int($m[1]);
    return $num ?: 7; // default ke 7
  };

  // --- pastikan tabel penyimpanan ada ---
  if(!@mysqli_query($db,"CREATE TABLE IF NOT EXISTS nilai_pts_set (
      nps_id INT AUTO_INCREMENT PRIMARY KEY,
      sekolah_id INT NULL,
      mapel_id INT NOT NULL,
      kelas_id INT NOT NULL,
      semester TINYINT NOT NULL,
      tgl_pts DATE NULL,
      kkm TINYINT NOT NULL DEFAULT 75,
      created_at DATETIME NULL,
      updated_at DATETIME NULL,
      created_by INT NULL,
      updated_by INT NULL,
      UNIQUE KEY uniq_nps (mapel_id,kelas_id,semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) _json_error('Gagal memastikan tabel nilai_pts_set: '.mysqli_error($db));

  if(!@mysqli_query($db,"CREATE TABLE IF NOT EXISTS nilai_pts (
      nps_id INT NOT NULL,
      siswa_id INT NOT NULL,
      skor DECIMAL(5,2) NULL,
      PRIMARY KEY (nps_id, siswa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) _json_error('Gagal memastikan tabel nilai_pts: '.mysqli_error($db));

  if(!@mysqli_query($db,"CREATE TABLE IF NOT EXISTS nilai_pts_tp (
      nps_id INT NOT NULL,
      tp_id INT NOT NULL,
      PRIMARY KEY (nps_id,tp_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) _json_error('Gagal memastikan tabel nilai_pts_tp: '.mysqli_error($db));

  $act=$_GET['action'];

  // ------- ambil daftar TP (berdasarkan mapel+kelas+semester) -------
  if($act==='tps' && isset($_GET['mapel_id'],$_GET['kelas_id'],$_GET['semester'])){
    $mapel_id=(int)$_GET['mapel_id']; $kelas_id=(int)$_GET['kelas_id']; $semester=(int)$_GET['semester'];
    $tingkat=$get_tingkat($kelas_id); $items=[];
    if(!$TP_ID||!$TP_MAPEL||!$TP_TKT||!$TP_SMT||!$TP_TEXT) echo json_encode(['ok'=>true,'tingkat'=>$tingkat,'data'=>[]]);
    else {
      $sql="SELECT `$TP_ID` id, `$TP_TEXT` teks FROM tujuan_pembelajaran
            WHERE `$TP_MAPEL`=$mapel_id AND `$TP_TKT`=$tingkat AND `$TP_SMT`=$semester ".($TP_STATUS?"AND `$TP_STATUS`='Aktif'":'')." ORDER BY `$TP_ID`";
      $q=@mysqli_query($db,$sql); if(!$q) _json_error('Gagal ambil TP: '.mysqli_error($db));
      while($r=@mysqli_fetch_assoc($q)) $items[]=$r;
      echo json_encode(['ok'=>true,'tingkat'=>$tingkat,'data'=>$items]);
    }
    exit;
  }

  // ------- load / buat set PTS -------
  if($act==='load_pts' && isset($_GET['mapel_id'],$_GET['kelas_id'],$_GET['semester'])){
    $mapel_id=(int)$_GET['mapel_id']; $kelas_id=(int)$_GET['kelas_id']; $semester=(int)$_GET['semester'];
    $kkm=(int)($_GET['kkm']??75); $tgl=$_GET['tgl']??null; $tgl_esc=$tgl?"'".mysqli_real_escape_string($db,$tgl)."'":"NULL";

    $q=@mysqli_query($db,"SELECT nps_id, tgl_pts, kkm FROM nilai_pts_set WHERE mapel_id=$mapel_id AND kelas_id=$kelas_id AND semester=$semester LIMIT 1");
    if(!$q) _json_error('Gagal cek set: '.mysqli_error($db));
    if($set=@mysqli_fetch_assoc($q)){
      $nps_id=(int)$set['nps_id'];
      if($tgl||$kkm){ @mysqli_query($db,"UPDATE nilai_pts_set SET tgl_pts=$tgl_esc, kkm=$kkm, updated_at='$now', updated_by=$user_id WHERE nps_id=$nps_id"); }
    }else{
      $ok=@mysqli_query($db,"INSERT INTO nilai_pts_set(sekolah_id,mapel_id,kelas_id,semester,tgl_pts,kkm,created_at,updated_at,created_by,updated_by)
        VALUES(".($SEKOLAH_ID?:'NULL').",$mapel_id,$kelas_id,$semester,$tgl_esc,$kkm,'$now','$now',$user_id,$user_id)");
      if(!$ok) _json_error('Gagal membuat set: '.mysqli_error($db));
      $nps_id=(int)mysqli_insert_id($db);
    }

    // siswa di kelas
    $siswa=[];
    if($ksExists){
      $sql="SELECT s.`$SISWA_ID` id, s.`$SISWA_NAMA` nama, ".($SISWA_NIS?"s.`$SISWA_NIS`":"''")." nis
            FROM kelas_siswa ks JOIN siswa s ON s.`$SISWA_ID`=ks.`$KS_SISWA`
            WHERE ks.`$KS_KELAS`=$kelas_id".($KS_STATUS?" AND ks.`$KS_STATUS` IN ('Aktif','Y','1')":'')." ORDER BY s.`$SISWA_NAMA`";
    }else{
      if(!$SISWA_KELAS) _json_error('Kolom kelas di tabel siswa tidak ditemukan.');
      $sql="SELECT s.`$SISWA_ID` id, s.`$SISWA_NAMA` nama, ".($SISWA_NIS?"s.`$SISWA_NIS`":"''")." nis FROM siswa s WHERE s.`$SISWA_KELAS`=$kelas_id ORDER BY s.`$SISWA_NAMA`";
    }
    $sq=@mysqli_query($db,$sql); if(!$sq) _json_error('Gagal ambil siswa: '.mysqli_error($db));
    while($r=@mysqli_fetch_assoc($sq)) $siswa[]=$r;

    // skor
    $nilai=[]; $nq=@mysqli_query($db,"SELECT siswa_id, skor FROM nilai_pts WHERE nps_id=$nps_id");
    if(!$nq) _json_error('Gagal ambil nilai PTS: '.mysqli_error($db));
    while($r=@mysqli_fetch_assoc($nq)) $nilai[(int)$r['siswa_id']]= (float)$r['skor'];

    // tp terpilih
    $tp_ids=[]; $tq=@mysqli_query($db,"SELECT tp_id FROM nilai_pts_tp WHERE nps_id=$nps_id");
    if($tq) while($r=@mysqli_fetch_assoc($tq)) $tp_ids[]=(int)$r['tp_id'];

    echo json_encode(['ok'=>true,'nps_id'=>$nps_id,'kkm'=>$kkm,'tgl'=>$tgl,'siswa'=>$siswa,'nilai'=>$nilai,'tp_ids'=>$tp_ids]); exit;
  }

  // ------- simpan skor & meta PTS -------
  if($act==='save_pts' && !empty($_POST)){
    $nps_id=(int)($_POST['nps_id']??0); if($nps_id<=0) _json_error('Set PTS tidak valid.');
    $kkm=(int)($_POST['kkm']??75); $tgl=$_POST['tgl']??null; $tgl_esc=$tgl?"'".mysqli_real_escape_string($db,$tgl)."'":"NULL";
    $tp_ids = isset($_POST['tp_ids']) ? (array)json_decode($_POST['tp_ids'],true) : [];
    $rows   = isset($_POST['rows'])   ? (array)json_decode($_POST['rows'],true)   : [];

    @mysqli_query($db,"UPDATE nilai_pts_set SET tgl_pts=$tgl_esc, kkm=$kkm, updated_at='$now', updated_by=$user_id WHERE nps_id=$nps_id");

    mysqli_begin_transaction($db);
    foreach($rows as $r){ $sid=(int)($r['siswa_id']??0); if(!$sid) continue; $v=$r['skor']; $val=($v===''||$v===null)?'NULL':(0+floatval($v));
      $sql="INSERT INTO nilai_pts(nps_id,siswa_id,skor) VALUES($nps_id,$sid,$val) ON DUPLICATE KEY UPDATE skor=VALUES(skor)";
      if(!@mysqli_query($db,$sql)){ mysqli_rollback($db); _json_error('Gagal simpan skor: '.mysqli_error($db)); }
    }
    // tp
    @mysqli_query($db,"DELETE FROM nilai_pts_tp WHERE nps_id=$nps_id");
    if($tp_ids){ $vals=[]; foreach($tp_ids as $tp){ $vals[]='('.$nps_id.','.((int)$tp).')'; }
      if(!@mysqli_query($db,"INSERT INTO nilai_pts_tp(nps_id,tp_id) VALUES ".implode(',',$vals))){ mysqli_rollback($db); _json_error('Gagal simpan TP: '.mysqli_error($db)); }
    }
    mysqli_commit($db);
    echo json_encode(['ok'=>true]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'Aksi tidak dikenal']); exit;
}
// ------------------------ END OF AJAX FIRST ------------------------

// ========================= VIEW / UI =========================
require_once __DIR__.'/header.php'; // gunakan tema & guard login dari sistem Anda
$db = isset($koneksi)?$koneksi:_boot_db();

// ambil opsi dropdown (mapel sesuai pengampu jika ada)
function _mapel_options($db){
  $o=[]; $mapelCols=_tbl_cols($db,'mapel'); $MAPEL_ID=_pick_any($mapelCols,['mapel_id','id_mapel','id','kode_mapel']); $MAPEL_NAMA=_pick_any($mapelCols,['mapel_nama','nama_mapel','nama','mapel','nama_pelajaran']);
  if(!$MAPEL_ID||!$MAPEL_NAMA) return $o;
  $admin_like=function_exists('_is_admin') && _is_admin();
  if($admin_like || !_tbl_exists($db,'pengampu_mapel')){
    $sql="SELECT `$MAPEL_ID` id, `$MAPEL_NAMA` nama FROM mapel ORDER BY `$MAPEL_NAMA`";
  } else {
    $pc=_tbl_cols($db,'pengampu_mapel'); $PM_MAPEL=_pick_any($pc,['mapel_id','id_mapel']); $PM_USER=_pick_any($pc,['user_id','id_user','guru_id','id_guru']); $uid=(int)($_SESSION['id']??0);
    if($PM_MAPEL && $PM_USER) $sql="SELECT DISTINCT m.`$MAPEL_ID` id, m.`$MAPEL_NAMA` nama FROM pengampu_mapel p JOIN mapel m ON m.`$MAPEL_ID`=p.`$PM_MAPEL` WHERE p.`$PM_USER`=$uid ORDER BY m.`$MAPEL_NAMA`";
    else $sql="SELECT `$MAPEL_ID` id, `$MAPEL_NAMA` nama FROM mapel ORDER BY `$MAPEL_NAMA`";
  }
  $q=@mysqli_query($db,$sql); if($q) while($r=mysqli_fetch_assoc($q)) $o[]=$r; return $o;
}
function _kelas_options($db){ $o=[]; $kelasCols=_tbl_cols($db,'kelas'); $KELAS_ID=_pick_any($kelasCols,['kelas_id','id_kelas','id','rombel_id']); $KELAS_NAMA=_pick_any($kelasCols,['kelas_nama','nama_kelas','rombel','kelas']); $q=@mysqli_query($db,"SELECT `$KELAS_ID` id, `$KELAS_NAMA` nama FROM kelas ORDER BY `$KELAS_NAMA`"); if($q) while($r=mysqli_fetch_assoc($q)) $o[]=$r; return $o; }
$mapelOptions=_mapel_options($db); $kelasOptions=_kelas_options($db);
?>
<div class="content-wrapper">
  <section class="content-header polished-header">
    <h1 class="tp-title"><i class="fa fa-file-pen"></i><span>Nilai PTS</span></h1>
    <span class="subtitle-badge">Input nilai PTS per mapel & kelas, tetap bisa kaitkan ke Tujuan Pembelajaran</span>
  </section>

  <section class="content">
    <div class="filter-card">
      <div class="filter-row">
        <div class="filter-col">
          <label>Kelas</label>
          <select id="kelasSelect" class="form-control select2" style="width:100%">
            <option value="">— pilih kelas —</option>
            <?php foreach($kelasOptions as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nama']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="filter-col">
          <label>Mapel</label>
          <div class="input-group pretty-select">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <select id="mapelSelect" class="form-control select2" style="width:100%">
              <option value="">— pilih mapel —</option>
              <?php foreach($mapelOptions as $m): ?><option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nama']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="filter-col short">
          <label>Semester</label>
          <select id="smtSelect" class="form-control"><option value="1">1</option><option value="2">2</option></select>
        </div>
        <div class="filter-col short">
          <label>KKM</label>
          <input type="number" id="kkmInput" class="form-control" min="0" max="100" value="75">
        </div>
        <div class="filter-col short">
          <label>Tanggal PTS</label>
          <input type="date" id="tglInput" class="form-control">
        </div>
      </div>
      <div class="filter-actions">
        <button id="btnLoad"   class="btn btn-info btn-lg rounded-pill"><i class="fa fa-rotate"></i> Muat/Perbarui</button>
        <button id="btnTP"     class="btn btn-primary btn-lg rounded-pill"><i class="fa fa-bullseye"></i> Pilih TP</button>
        <button id="btnSave"   class="btn btn-success btn-lg rounded-pill"><i class="fa fa-save"></i> Simpan</button>
        <span id="setInfo" class="set-info"></span>
        <span class="label label-warning" style="margin-left:auto;border-radius:999px">Mode: PTS</span>
      </div>
    </div>

    <div class="box" style="border-radius:12px; overflow:hidden;">
      <div class="box-header" style="background:#0b1220;color:#cbe6ff;">
        <h3 class="box-title" style="font-weight:800;">Input Nilai PTS</h3>
      </div>
      <div class="box-body" style="padding:0;">
        <div class="table-responsive">
          <table id="ptsTable" class="table table-striped table-hover" style="margin:0;">
            <thead><tr><th style="width:50px">No</th><th>Nama Siswa</th><th class="text-center" style="width:120px">PTS</th><th class="text-center" style="width:100px">Predikat</th></tr></thead>
            <tbody id="ptsBody"><tr><td colspan="4" class="text-center text-muted" style="padding:24px;">Pilih Kelas & Mapel lalu klik <b>Muat/Perbarui</b>.</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal TP -->
<div class="modal fade" id="tpModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header" style="background:#0ea5e9;color:#fff;">
      <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      <h4 class="modal-title"><i class="fa fa-bullseye"></i> Pilih TP</h4>
    </div>
    <div class="modal-body"><div id="tpList" class="tp-list"></div>
      <small class="text-muted"><i class="fa fa-info-circle"></i> TP yang dicentang akan dikaitkan dengan PTS ini.</small>
    </div>
    <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Tutup</button><button id="btnSaveTP" class="btn btn-primary"><i class="fa fa-check"></i> Simpan TP</button></div>
  </div></div>
</div>

<style>
.polished-header{margin-bottom:10px}.tp-title{display:flex;align-items:center;gap:10px;font-weight:800;letter-spacing:.2px}.tp-title i{font-size:26px;color:#0ea5e9;background:#e6f6ff;border-radius:12px;padding:6px 10px}.subtitle-badge{display:inline-block;margin-top:6px;background:#e9f5ff;color:#0b5ed7;border:1px solid #b8e0ff;padding:4px 10px;border-radius:999px;font-weight:600;font-size:12px}
.filter-card{border-radius:14px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 6px 16px rgba(2,6,23,.06);padding:12px 14px;margin-bottom:16px}
.filter-row{display:flex;flex-wrap:wrap;gap:12px}.filter-col{flex:1 1 230px;min-width:210px}.filter-col.short{flex:0 0 160px}.filter-actions{margin-top:10px;display:flex;align-items:center;gap:10px}.rounded-pill{border-radius:999px}.set-info{color:#64748b;font-weight:600}
.tp-list .item{padding:8px 10px;border-bottom:1px dashed #e5e7eb}.tp-list .item:last-child{border-bottom:none}
#ptsTable th,#ptsTable td{vertical-align:middle}
</style>

<script>
(function(){
  var $=window.jQuery; if(!$) return;
  function toast(msg,type){ if(window.Swal&&Swal.mixin){ const T=Swal.mixin({toast:true,position:'top-end',showConfirmButton:false,timer:2000,timerProgressBar:true}); T.fire({icon:type||'info',title:msg}); } else alert(msg); }
  function esc(s){return (''+s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
  function pred(n,kkm){n=parseFloat(n||0);if(n>=90)return'A';if(n>=80)return'B';if(n>=kkm)return'C';return'D';}
  function fmt(x){return (Math.round((x||0)*100)/100).toFixed(2);}

  var state={nps_id:0,kkm:75,tgl:'',siswa:[],nilai:{},tp_ids:[],tp_list:[]};
  var $kelas=$('#kelasSelect'),$mapel=$('#mapelSelect'),$smt=$('#smtSelect'),$kkm=$('#kkmInput'),$tgl=$('#tglInput');

  $('#btnLoad').on('click',function(){
    if(!$kelas.val()||!$mapel.val()){ toast('Pilih Kelas dan Mapel dulu','warning'); return; }
    $.getJSON('nilai_pts.php',{action:'load_pts',kelas_id:$kelas.val(),mapel_id:$mapel.val(),semester:$smt.val(),kkm:$kkm.val(),tgl:$tgl.val()},function(r){
      if(!r||r.ok===false){ toast((r&&r.error)||'Gagal memuat data','error'); return; }
      state.nps_id=r.nps_id; state.kkm=parseInt($kkm.val()||r.kkm,10); state.tgl=$tgl.val()||r.tgl; state.siswa=r.siswa||[]; state.nilai=r.nilai||{}; state.tp_ids=r.tp_ids||[];
      build(); $('#setInfo').text('Set ID #'+state.nps_id+' | '+state.siswa.length+' siswa');
    }).fail(function(xhr){ toast('Gagal memuat data (AJAX): '+xhr.statusText,'error'); });
  });

  function build(){
    var tb=''; if(!state.siswa.length){ $('#ptsBody').html('<tr><td colspan="4" class="text-center text-muted" style="padding:24px;">Tidak ada siswa di kelas ini.</td></tr>'); return; }
    state.siswa.forEach(function(s,idx){ var v=state.nilai[s.id]; var row='<tr data-sid="'+s.id+'">'+
      '<td class="text-center">'+(idx+1)+'</td>'+
      '<td><div style="font-weight:700">'+esc(s.nama)+'</div><div class="text-muted" style="font-size:11px">'+(s.nis||'-')+'</div></td>'+
      '<td class="text-center"><input type="number" step="0.01" class="form-control skor" value="'+(v!==undefined?(''+v).replace(/[^0-9\.\-]/g,''):'')+'" style="width:100px;margin:auto"></td>'+
      '<td class="text-center pred">'+pred(v,$('#kkmInput').val())+'</td>'+
      '</tr>'; tb+=row; });
    $('#ptsBody').html(tb);
  }

  $('#ptsBody').on('input','.skor',function(){ var $tr=$(this).closest('tr'); var v=$(this).val(); var kkm=parseInt($('#kkmInput').val()||'75',10); $tr.find('.pred').text(pred(v,kkm)); });

  $('#btnTP').on('click',function(){ if(!state.nps_id){ toast('Klik "Muat/Perbarui" dulu','warning'); return; }
    $.getJSON('nilai_pts.php',{action:'tps',kelas_id:$kelas.val(),mapel_id:$mapel.val(),semester:$smt.val()},function(r){ if(!r||r.ok===false){ toast((r&&r.error)||'Gagal memuat TP','error'); return; } state.tp_list=r.data||[]; renderTP(); $('#tpModal').modal('show'); })
    .fail(function(xhr){ toast('Gagal memuat TP (AJAX): '+xhr.statusText,'error'); });
  });
  function renderTP(){ var sel={}; state.tp_ids.forEach(function(id){sel[id]=1;}); var html=''; if(!state.tp_list.length) html='<div class="text-muted">Belum ada TP aktif pada mapel/tingkat/semester ini.</div>'; else state.tp_list.forEach(function(tp){ html+='<label class="item"><input type="checkbox" class="tp-ck" value="'+tp.id+'" '+(sel[tp.id]?'checked':'')+'> '+esc(tp.teks)+'</label>'; }); $('#tpList').html(html); }
  $('#btnSaveTP').on('click',function(){ var ids=[]; $('#tpList .tp-ck:checked').each(function(){ ids.push(parseInt(this.value,10)); }); state.tp_ids=ids; $('#tpModal').modal('hide'); toast('TP disetel ('+ids.length+')','success'); });

  $('#btnSave').on('click',function(){ if(!state.nps_id){ toast('Klik "Muat/Perbarui" dulu','warning'); return; }
    var rows=[]; $('#ptsBody tr').each(function(){ var sid=parseInt($(this).attr('data-sid'),10)||0; if(!sid) return; rows.push({siswa_id:sid,skor:$(this).find('.skor').val()}); });
    var post={nps_id:state.nps_id,kkm:$('#kkmInput').val(),tgl:$('#tglInput').val(),tp_ids:JSON.stringify(state.tp_ids),rows:JSON.stringify(rows)}; var $btn=$(this).prop('disabled',true).text('Menyimpan...');
    $.post('nilai_pts.php?action=save_pts',post,function(r){ if(r&&r.ok){ toast('Tersimpan','success'); $('#btnLoad').trigger('click'); } else { toast((r&&r.error)||'Gagal menyimpan','error'); } },'json')
      .fail(function(xhr){ toast('Gagal menyimpan (AJAX): '+xhr.statusText,'error'); })
      .always(function(){ $btn.prop('disabled',false).html('<i class="fa fa-save"></i> Simpan'); });
  });

  // Auto pilih mapel kalau hanya satu opsi
  if ($('#mapelSelect option').length===2) $('#mapelSelect').prop('selectedIndex',1);
})();
</script>

<?php require_once __DIR__.'/footer.php'; ?>
