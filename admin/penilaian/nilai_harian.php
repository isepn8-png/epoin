<?php
/* =====================================================================
 * NILAI PTS — sederhana, 1 kolom PTS, terhubung TP
 * - AJAX FIRST (tidak bocor HTML)
 * - Endpoint otomatis pakai nama file ini (fix error AJAX: error/404)
 * - Otomatis memakai/membuat Jenis Penilaian "PTS"
 * - Kompatibel DB Anda:
 *   siswa(siswa_id,siswa_nama,siswa_nis)
 *   kelas(kelas_id,kelas_nama)
 *   kelas_siswa(ks_kelas,ks_siswa)  ← dipakai ambil daftar siswa
 *   tujuan_pembelajaran(tp_id,mapel_id,tingkat,semester,tp_text,status_enum)
 *   nilai_harian_set / nilai_harian_penilaian / nilai_harian / nilai_harian_tp
 * ===================================================================== */

if (session_status()===PHP_SESSION_NONE) session_start();

/* ---------------- util umum ---------------- */
function _tbl_exists($c,$n){ $q=@mysqli_query($c,"SHOW TABLES LIKE '".mysqli_real_escape_string($c,$n)."'"); return $q&&mysqli_num_rows($q)>0; }
function _tbl_cols($c,$t){ $a=[];$q=@mysqli_query($c,"SHOW COLUMNS FROM `$t`"); if($q){while($r=mysqli_fetch_assoc($q))$a[]=$r['Field'];} return $a; }
function _pick_any($have,$cands){ foreach($cands as $n){ foreach([$n,strtolower($n),strtoupper($n)] as $p){ if(in_array($p,$have,true)) return $p; }} return null; }
function _roman_to_int($s){$m=['CM'=>900,'CD'=>400,'XC'=>90,'XL'=>40,'IX'=>9,'IV'=>4,'M'=>1000,'D'=>500,'C'=>100,'L'=>50,'X'=>10,'V'=>5,'I'=>1];$s=strtoupper(trim($s));$i=0;$o=0;while($i<strlen($s)){if($i+1<strlen($s)&&isset($m[$s[$i].$s[$i+1]])){$o+=$m[$s[$i].$s[$i+1]];$i+=2;}elseif(isset($m[$s[$i]])){$o+=$m[$s[$i]];$i++;}else{$i++;}}return $o?:null;}
function _boot_db(){
  $try=[__DIR__.'/koneksi.php',__DIR__.'/../koneksi.php',dirname(__DIR__).'/koneksi.php',__DIR__.'/../config/koneksi.php',__DIR__.'/../../koneksi.php'];
  foreach($try as $p){ if(is_file($p)){ require_once $p; return isset($GLOBALS['koneksi']); } }
  if(is_file(__DIR__.'/header.php')){ ob_start(); require __DIR__.'/header.php'; ob_end_clean(); return isset($GLOBALS['koneksi']); }
  return false;
}

/* ------------------------ AJAX FIRST ------------------------ */
if (!empty($_GET['action'])) {
  @ini_set('display_errors', 0);
  header('Content-Type: application/json; charset=utf-8');
  if(!_boot_db()){ echo json_encode(['ok'=>false,'error'=>'Koneksi DB tidak ditemukan.']); exit; }
  $koneksi=$GLOBALS['koneksi'];

  $user_id=(int)($_SESSION['id']??0);
  $SEKOLAH_ID=0; $x=@mysqli_query($koneksi,"SELECT sekolah_id FROM sekolah LIMIT 1"); if($x && $r=@mysqli_fetch_assoc($x)) $SEKOLAH_ID=(int)$r['sekolah_id'];

  // resolusi kolom
  $mapelCols=_tbl_cols($koneksi,'mapel');             $MAPEL_ID=_pick_any($mapelCols,['mapel_id','id_mapel','id','kode_mapel']); $MAPEL_NAMA=_pick_any($mapelCols,['mapel_nama','nama_mapel','nama','mapel','nama_pelajaran']);
  $kelasCols=_tbl_cols($koneksi,'kelas');             $KELAS_ID=_pick_any($kelasCols,['kelas_id','id_kelas','id','rombel_id']);  $KELAS_NAMA=_pick_any($kelasCols,['kelas_nama','nama_kelas','rombel','kelas']); $KELAS_JUR=_pick_any($kelasCols,['kelas_jurusan','jurusan_id','id_jurusan','jurusan']);
  $jurCols=_tbl_cols($koneksi,'jurusan');             $JUR_ID=_pick_any($jurCols,['jurusan_id','id_jurusan','id']);             $JUR_NAMA=_pick_any($jurCols,['jurusan_nama','nama_jurusan','nama','jurusan']);
  $sCols=_tbl_cols($koneksi,'siswa');                 $SISWA_ID=_pick_any($sCols,['siswa_id','id_siswa','id','id_pd','peserta_didik_id']); $SISWA_NAMA=_pick_any($sCols,['siswa_nama','nama_siswa','nama','nama_lengkap']); $SISWA_NIS=_pick_any($sCols,['siswa_nis','nis','nisn','nipd','no_induk']);
  $ksCols=_tbl_cols($koneksi,'kelas_siswa');          $KS_KELAS=_pick_any($ksCols,['ks_kelas','kelas_id','id_kelas','rombel_id','kelas']); $KS_SISWA=_pick_any($ksCols,['ks_siswa','siswa_id','id_siswa','id_pd','peserta_didik_id']);
  $tpCols=_tbl_cols($koneksi,'tujuan_pembelajaran');  $TP_ID=_pick_any($tpCols,['tp_id','id']); $TP_MAPEL=_pick_any($tpCols,['mapel_id','id_mapel']); $TP_TKT=_pick_any($tpCols,['tingkat','level','kelas_tingkat']); $TP_SMT=_pick_any($tpCols,['semester','smt']); $TP_TEXT=_pick_any($tpCols,['tp_text','deskripsi','tujuan','uraian']); $TP_STATUS=_pick_any($tpCols,['status_enum','status']);

  $json_err=function($m){ echo json_encode(['ok'=>false,'error'=>$m]); exit; };

  $get_tingkat=function($kelas_id) use($koneksi,$KELAS_ID,$KELAS_NAMA,$KELAS_JUR,$JUR_ID,$JUR_NAMA){
    $kelas_id=(int)$kelas_id; $label='';
    if ($KELAS_JUR && $JUR_ID && $JUR_NAMA){
      $sql="SELECT k.`$KELAS_NAMA` kn, j.`$JUR_NAMA` jn FROM kelas k LEFT JOIN jurusan j ON j.`$JUR_ID`=k.`$KELAS_JUR` WHERE k.`$KELAS_ID`=$kelas_id LIMIT 1";
      if($q=@mysqli_query($koneksi,$sql)){ if($r=@mysqli_fetch_assoc($q)) $label=$r['jn']?:$r['kn']; }
    } else {
      $sql="SELECT `$KELAS_NAMA` kn FROM kelas WHERE `$KELAS_ID`=$kelas_id LIMIT 1";
      if($q=@mysqli_query($koneksi,$sql)){ if($r=@mysqli_fetch_assoc($q)) $label=$r['kn']; }
    }
    $num=null; if($label && preg_match('~\b(\d{1,2})\b~',$label,$m)) $num=(int)$m[1];
    if(!$num && $label && preg_match('~\b([IVXLCM]{1,6})\b~i',$label,$m)) $num=_roman_to_int($m[1]);
    return $num ?: 7;
  };

  // pastikan jenis PTS ada → id
  $ensure_pts=function() use($koneksi){
    if(!_tbl_exists($koneksi,'jenis_penilaian')) return 0;
    $c=@mysqli_query($koneksi,"SELECT jenis_id FROM jenis_penilaian WHERE jenis_nama='PTS' LIMIT 1");
    if($c && $r=@mysqli_fetch_assoc($c)) return (int)$r['jenis_id'];
    @mysqli_query($koneksi,"INSERT INTO jenis_penilaian(jenis_nama,aktif) VALUES('PTS','Y')");
    return (int)mysqli_insert_id($koneksi);
  };

  $act=$_GET['action'];

  /* ---------- list TP ---------- */
  if ($act==='tps' && isset($_GET['mapel_id'],$_GET['kelas_id'],$_GET['semester'])) {
    $mapel_id=(int)$_GET['mapel_id']; $kelas_id=(int)$_GET['kelas_id']; $semester=(int)$_GET['semester'];
    $tingkat=$get_tingkat($kelas_id);
    $items=[];
    $sql="SELECT `$TP_ID` id, `$TP_TEXT` teks FROM tujuan_pembelajaran
          WHERE `$TP_MAPEL`=$mapel_id AND `$TP_TKT`=$tingkat AND `$TP_SMT`=$semester ".($TP_STATUS?"AND `$TP_STATUS`='Aktif'":"")."
          ORDER BY `$TP_ID`";
    $q=@mysqli_query($koneksi,$sql); if(!$q) $json_err('Gagal ambil TP: '.mysqli_error($koneksi));
    while($r=@mysqli_fetch_assoc($q)) $items[]=$r;
    echo json_encode(['ok'=>true,'tingkat'=>$tingkat,'data'=>$items]); exit;
  }

  /* ---------- MUAT / PERBARUI ---------- */
  if ($act==='load' && isset($_GET['mapel_id'],$_GET['kelas_id'],$_GET['semester'])) {
    $mapel_id=(int)$_GET['mapel_id']; $kelas_id=(int)$_GET['kelas_id']; $semester=(int)$_GET['semester'];
    $jenis_id=$ensure_pts(); if(!$jenis_id) $json_err('Tabel jenis_penilaian tidak ada.');
    $now=date('Y-m-d H:i:s');

    // ambil/buat set, pastikan 1 kolom
    $q=@mysqli_query($koneksi,"SELECT nh_set_id, jumlah_penilaian, kkm FROM nilai_harian_set
                               WHERE mapel_id=$mapel_id AND kelas_id=$kelas_id AND semester=$semester AND jenis_id=$jenis_id LIMIT 1");
    if(!$q) $json_err('Gagal cek set: '.mysqli_error($koneksi));
    if($set=@mysqli_fetch_assoc($q)){ $set_id=(int)$set['nh_set_id']; $kkm=(int)$set['kkm']; @mysqli_query($koneksi,"UPDATE nilai_harian_set SET jumlah_penilaian=1 WHERE nh_set_id=$set_id AND jumlah_penilaian<>1"); }
    else{
      $kkm=75;
      $ok=@mysqli_query($koneksi,"INSERT INTO nilai_harian_set(sekolah_id,mapel_id,kelas_id,semester,jenis_id,jumlah_penilaian,kkm,created_at,updated_at,created_by,updated_by)
              VALUES(".($SEKOLAH_ID?:'NULL').",$mapel_id,$kelas_id,$semester,$jenis_id,1,$kkm,'$now','$now',$user_id,$user_id)");
      if(!$ok) $json_err('Gagal membuat set: '.mysqli_error($koneksi));
      $set_id=(int)mysqli_insert_id($koneksi);
    }

    // meta kolom (harus 1: PTS)
    $meta=[]; $mq=@mysqli_query($koneksi,"SELECT nhp_id, ke, label, tanggal FROM nilai_harian_penilaian WHERE nh_set_id=$set_id ORDER BY ke");
    if(!$mq) $json_err('Gagal ambil meta: '.mysqli_error($koneksi));
    while($r=@mysqli_fetch_assoc($mq)) $meta[]=$r;
    if(empty($meta)){ @mysqli_query($koneksi,"INSERT INTO nilai_harian_penilaian(nh_set_id,ke,label) VALUES($set_id,1,'PTS')"); $meta=[['nhp_id'=>(int)mysqli_insert_id($koneksi),'ke'=>1,'label'=>'PTS','tanggal'=>null]]; }
    elseif(count($meta)>1){ for($i=1;$i<count($meta);$i++) @mysqli_query($koneksi,"DELETE FROM nilai_harian_penilaian WHERE nhp_id=".(int)$meta[$i]['nhp_id']); $meta=array($meta[0]); @mysqli_query($koneksi,"UPDATE nilai_harian_penilaian SET ke=1,label='PTS' WHERE nhp_id=".(int)$meta[0]['nhp_id']); }
    else { @mysqli_query($koneksi,"UPDATE nilai_harian_penilaian SET label='PTS',ke=1 WHERE nhp_id=".(int)$meta[0]['nhp_id']); $meta[0]['label']='PTS'; $meta[0]['ke']=1; }

    // siswa via kelas_siswa
    if(!$KS_KELAS || !$KS_SISWA) $json_err('Struktur tabel kelas_siswa tidak sesuai (butuh ks_kelas & ks_siswa).');
    $siswa=[]; $sq=@mysqli_query($koneksi,"SELECT s.`$SISWA_ID` id, s.`$SISWA_NAMA` nama, ".($SISWA_NIS?"s.`$SISWA_NIS`":"''")." nis
                                           FROM kelas_siswa ks JOIN siswa s ON s.`$SISWA_ID`=ks.`$KS_SISWA`
                                           WHERE ks.`$KS_KELAS`=$kelas_id ORDER BY s.`$SISWA_NAMA`");
    if(!$sq) $json_err('Gagal ambil siswa: '.mysqli_error($koneksi));
    while($r=@mysqli_fetch_assoc($sq)) $siswa[]=$r;

    // nilai PTS (ke=1)
    $nilai=[]; $nq=@mysqli_query($koneksi,"SELECT siswa_id, ke, skor FROM nilai_harian WHERE nh_set_id=$set_id AND ke=1");
    if(!$nq) $json_err('Gagal ambil nilai: '.mysqli_error($koneksi));
    while($r=@mysqli_fetch_assoc($nq)) $nilai[$r['siswa_id']][1]=$r['skor'];

    // TP map
    $tp_map=[]; $tq=@mysqli_query($koneksi,"SELECT p.nhp_id, t.tp_id FROM nilai_harian_penilaian p LEFT JOIN nilai_harian_tp t ON t.nhp_id=p.nhp_id WHERE p.nh_set_id=$set_id");
    if(!$tq) $json_err('Gagal ambil TP map: '.mysqli_error($koneksi));
    while($r=@mysqli_fetch_assoc($tq)) $tp_map[$r['nhp_id']][]=(int)$r['tp_id'];

    echo json_encode(['ok'=>true,'set_id'=>$set_id,'kkm'=>$kkm,'meta'=>$meta,'siswa'=>$siswa,'nilai'=>$nilai,'tp_map'=>$tp_map]); exit;
  }

  /* ---------- SIMPAN ---------- */
  if ($act==='save' && !empty($_POST)) {
    $set_id=(int)($_POST['set_id']??0); if($set_id<=0) $json_err('Set tidak valid.');
    $kkm=(int)($_POST['kkm']??75);
    $meta=json_decode($_POST['meta']??'[]',true); // satu item: {nhp_id,ke=1,label='PTS',tanggal,tp_ids[]}
    $rows=json_decode($_POST['rows']??'[]',true);
    $now=date('Y-m-d H:i:s');

    mysqli_begin_transaction($koneksi);

    if(!@mysqli_query($koneksi,"UPDATE nilai_harian_set SET jumlah_penilaian=1, kkm=$kkm, updated_at='$now', updated_by=".(int)($_SESSION['id']??0)." WHERE nh_set_id=$set_id")){
      mysqli_rollback($koneksi); $json_err('Gagal update set: '.mysqli_error($koneksi));
    }

    if(!empty($meta)){
      $m=$meta[0]; $nhp_id=(int)($m['nhp_id']??0); $tgl=!empty($m['tanggal'])?"'".mysqli_real_escape_string($koneksi,$m['tanggal'])."'":"NULL";
      if($nhp_id>0) $sql="UPDATE nilai_harian_penilaian SET ke=1,label='PTS',tanggal=$tgl WHERE nhp_id=$nhp_id AND nh_set_id=$set_id";
      else         $sql="INSERT INTO nilai_harian_penilaian(nh_set_id,ke,label,tanggal) VALUES($set_id,1,'PTS',$tgl)";
      if(!@mysqli_query($koneksi,$sql)){ mysqli_rollback($koneksi); $json_err('Gagal simpan meta: '.mysqli_error($koneksi)); }
      if($nhp_id<=0) $nhp_id=(int)mysqli_insert_id($koneksi);

      @mysqli_query($koneksi,"DELETE FROM nilai_harian_tp WHERE nhp_id=$nhp_id");
      if(!empty($m['tp_ids']) && is_array($m['tp_ids'])){
        $vals=[]; foreach($m['tp_ids'] as $tp){ $vals[]='('.$nhp_id.','.((int)$tp).')'; }
        if($vals){ if(!@mysqli_query($koneksi,"INSERT INTO nilai_harian_tp(nhp_id,tp_id) VALUES ".implode(',',$vals))){ mysqli_rollback($koneksi); $json_err('Gagal simpan TP: '.mysqli_error($koneksi)); } }
      }
    }

    foreach((array)$rows as $r){
      $sid=(int)$r['siswa_id']; if(!$sid) continue;
      $skor = array_key_exists('1',$r['nilai']) ? $r['nilai']['1'] : (array_values($r['nilai'])[0]??'');
      $val = ($skor===''||$skor===null)?'NULL':(0+floatval($skor));
      $sql="INSERT INTO nilai_harian(nh_set_id,siswa_id,ke,skor) VALUES($set_id,$sid,1,$val)
            ON DUPLICATE KEY UPDATE skor=VALUES(skor)";
      if(!@mysqli_query($koneksi,$sql)){ mysqli_rollback($koneksi); $json_err('Gagal simpan nilai: '.mysqli_error($koneksi)); }
    }

    mysqli_commit($koneksi); echo json_encode(['ok'=>true]); exit;
  }

  echo json_encode(['ok'=>false,'error'=>'Aksi tidak dikenal']); exit;
}
/* ---------------------- END AJAX ---------------------- */

/* ========================= VIEW ========================= */
require_once __DIR__.'/header.php'; // aman di luar blok AJAX

// opsi dropdown
function _mapel_options($conn,$MAPEL_ID,$MAPEL_NAMA){
  $user_id=(int)($_SESSION['id']??0);
  $admin_like=function_exists('_is_admin') && _is_admin();
  if ($admin_like || !_tbl_exists($conn,'pengampu_mapel')){
    $sql="SELECT `$MAPEL_ID` id, `$MAPEL_NAMA` nama FROM mapel ORDER BY `$MAPEL_NAMA`";
  } else {
    $pc=_tbl_cols($conn,'pengampu_mapel'); $PM_MAPEL=_pick_any($pc,['mapel_id','id_mapel']); $PM_USER=_pick_any($pc,['user_id','id_user','guru_id','id_guru']);
    if ($PM_MAPEL && $PM_USER){
      $sql="SELECT DISTINCT m.`$MAPEL_ID` id, m.`$MAPEL_NAMA` nama
            FROM pengampu_mapel p JOIN mapel m ON m.`$MAPEL_ID`=p.`$PM_MAPEL`
            WHERE p.`$PM_USER`=$user_id ORDER BY m.`$MAPEL_NAMA`";
    } else $sql="SELECT `$MAPEL_ID` id, `$MAPEL_NAMA` nama FROM mapel ORDER BY `$MAPEL_NAMA`";
  }
  $o=[];$q=mysqli_query($conn,$sql); if($q) while($r=mysqli_fetch_assoc($q)) $o[]=$r; return $o;
}
function _kelas_options($conn,$KELAS_ID,$KELAS_NAMA){ $o=[];$q=mysqli_query($conn,"SELECT `$KELAS_ID` id, `$KELAS_NAMA` nama FROM kelas ORDER BY `$KELAS_NAMA`"); if($q) while($r=mysqli_fetch_assoc($q)) $o[]=$r; return $o; }

$mapelCols=_tbl_cols($koneksi,'mapel'); $MAPEL_ID=_pick_any($mapelCols,['mapel_id','id_mapel','id','kode_mapel']); $MAPEL_NAMA=_pick_any($mapelCols,['mapel_nama','nama_mapel','nama','mapel','nama_pelajaran']);
$kelasCols=_tbl_cols($koneksi,'kelas'); $KELAS_ID=_pick_any($kelasCols,['kelas_id','id_kelas','id','rombel_id']); $KELAS_NAMA=_pick_any($kelasCols,['kelas_nama','nama_kelas','rombel','kelas']);
$mapelOptions=_mapel_options($koneksi,$MAPEL_ID,$MAPEL_NAMA);
$kelasOptions=_kelas_options($koneksi,$KELAS_ID,$KELAS_NAMA);

// === FIX PENTING: endpoint AJAX = file ini sendiri (tidak hardcode) ===
$API_URL = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="content-wrapper">
  <section class="content-header polished-header">
    <h1 class="tp-title"><i class="fa fa-clipboard"></i><span>Nilai PTS</span></h1>
    <span class="subtitle-badge">Input nilai PTS per mapel & kelas, tetap bisa kaitkan ke Tujuan Pembelajaran</span>
  </section>

  <section class="content">
    <div class="filter-card">
      <div class="filter-row">
        <div class="filter-col">
          <label>Kelas</label>
          <select id="kelasSelect" class="form-control select2" style="width:100%;">
            <option value="">— pilih kelas —</option>
            <?php foreach($kelasOptions as $r): ?>
              <option value="<?= (int)$r['id']; ?>"><?= htmlspecialchars($r['nama']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-col">
          <label>Mapel</label>
          <div class="input-group pretty-select">
            <span class="input-group-addon"><i class="fa fa-search"></i></span>
            <select id="mapelSelect" class="form-control select2" style="width:100%;">
              <option value="">— pilih mapel —</option>
              <?php foreach($mapelOptions as $m): ?>
                <option value="<?= (int)$m['id']; ?>"><?= htmlspecialchars($m['nama']); ?></option>
              <?php endforeach; ?>
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
        <div class="filter-col">
          <label>Tanggal PTS</label>
          <input type="date" id="tglPTS" class="form-control">
        </div>
      </div>
      <div class="filter-actions">
        <button id="btnLoad" class="btn btn-info btn-lg rounded-pill"><i class="fa fa-refresh"></i> Muat/Perbarui</button>
        <button id="btnPickTP" class="btn btn-primary btn-lg rounded-pill"><i class="fa fa-bullseye"></i> Pilih TP</button>
        <button id="btnSave" class="btn btn-success btn-lg rounded-pill"><i class="fa fa-save"></i> Simpan</button>
        <span id="setInfo" class="set-info"></span>
        <span class="label label-warning" style="border-radius:999px;margin-left:auto;">Mode: PTS</span>
      </div>
    </div>

    <div class="box" style="border-radius:12px; overflow:hidden;">
      <div class="box-header" style="background:#0b1220;color:#cbe6ff;">
        <h3 class="box-title" style="font-weight:800;">Input Nilai PTS</h3>
      </div>
      <div class="box-body" style="padding:0;">
        <div class="table-responsive">
          <table id="ptsTable" class="table table-striped table-hover" style="margin:0;">
            <thead><tr>
              <th style="width:60px">No</th>
              <th style="min-width:260px">Nama Siswa</th>
              <th class="text-center" style="width:140px">PTS</th>
              <th class="text-center" style="width:100px">Predikat</th>
            </tr></thead>
            <tbody id="ptsBody">
              <tr><td colspan="4" class="text-center text-muted" style="padding:24px;">Pilih Kelas & Mapel lalu klik <b>Muat/Perbarui</b>.</td></tr>
            </tbody>
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
      <h4 class="modal-title"><i class="fa fa-bullseye"></i> Pilih TP untuk PTS</h4>
    </div>
    <div class="modal-body"><div id="tpList" class="tp-list"></div>
      <small class="text-muted"><i class="fa fa-info-circle"></i> TP yang dicentang akan dikaitkan dengan PTS.</small>
    </div>
    <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">Tutup</button><button id="btnSaveTP" class="btn btn-primary"><i class="fa fa-check"></i> Simpan TP</button></div>
  </div></div>
</div>

<style>
.polished-header{margin-bottom:10px}.tp-title{display:flex;align-items:center;gap:10px;font-weight:800;letter-spacing:.2px}.tp-title i{font-size:26px;color:#0ea5e9;background:#e6f6ff;border-radius:12px;padding:6px 10px}.subtitle-badge{display:inline-block;margin-top:6px;background:#e9f5ff;color:#0b5ed7;border:1px solid #b8e0ff;padding:4px 10px;border-radius:999px;font-weight:600;font-size:12px}
.filter-card{border-radius:14px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 6px 16px rgba(2,6,23,.06);padding:12px 14px;margin-bottom:16px}
.filter-row{display:flex;flex-wrap:wrap;gap:12px}.filter-col{flex:1 1 230px;min-width:210px}.filter-col.short{flex:0 0 150px}.filter-actions{margin-top:10px;display:flex;align-items:center;gap:10px}.rounded-pill{border-radius:999px}.set-info{color:#64748b;font-weight:600}
.pretty-select .input-group-addon{background:#eef6ff;border:1px solid #dbeafe;border-right:none;color:#2563eb;font-weight:700}.pretty-select .select2-container .select2-selection{height:34px!important;border-radius:0 8px 8px 0!important;border:1px solid #dbeafe!important}
#ptsTable th,#ptsTable td{vertical-align:middle}
.tp-list .item{padding:8px 10px;border-bottom:1px dashed #e5e7eb}.tp-list .item:last-child{border-bottom:none}
</style>

<script>
(function(){
  var $=window.jQuery;if(!$)return;
  var API_URL = '<?= htmlspecialchars($API_URL,ENT_QUOTES) ?>'; // <— FIX: endpoint = file ini sendiri

  function toast(msg,type){ if(window.Swal&&Swal.mixin){const T=Swal.mixin({toast:true,position:'top-end',showConfirmButton:false,timer:2200,timerProgressBar:true});T.fire({icon:type||'info',title:msg});} else alert(msg); }

  var state={set_id:0,kkm:75,siswa:[],nilai:{},nhp_id:0,tp_ids:[]};
  var $kelas=$('#kelasSelect'),$mapel=$('#mapelSelect'),$smt=$('#smtSelect');

  $('#btnLoad').on('click',function(){
    if(!$kelas.val()||!$mapel.val()){ toast('Pilih Kelas dan Mapel dulu','warning'); return; }
    $.getJSON(API_URL,{action:'load',kelas_id:$kelas.val(),mapel_id:$mapel.val(),semester:$smt.val()},function(res){
      if(!res||res.ok===false){ toast((res&&res.error)||'Gagal memuat data','error'); return; }
      state.set_id=res.set_id; state.kkm=res.kkm; $('#kkmInput').val(state.kkm);
      state.siswa=res.siswa||[]; state.nilai=res.nilai||{};
      if(res.meta && res.meta.length){ state.nhp_id=res.meta[0].nhp_id||0; $('#tglPTS').val(res.meta[0].tanggal||''); }
      state.tp_ids=(res.tp_map && res.meta && res.meta.length && res.tp_map[res.meta[0].nhp_id]) ? res.tp_map[res.meta[0].nhp_id] : [];
      build();
      $('#setInfo').text('Set ID #'+state.set_id+' | '+state.siswa.length+' siswa');
    }).fail(function(xhr){ toast('Gagal memuat data (AJAX): '+xhr.statusText,'error'); });
  });

  function build(){
    if(!state.siswa.length){ $('#ptsBody').html('<tr><td colspan="4" class="text-center text-muted" style="padding:24px;">Tidak ada siswa.</td></tr>'); return; }
    var tb=''; var kkm=parseInt($('#kkmInput').val()||'75',10);
    state.siswa.forEach(function(s,idx){
      var id=s.id, val=(state.nilai[id] && state.nilai[id][1]!==undefined)?state.nilai[id][1]:'';
      tb+='<tr data-sid="'+id+'">'+
            '<td class="text-center">'+(idx+1)+'</td>'+
            '<td><div style="font-weight:700">'+esc(s.nama)+'</div><div class="text-muted" style="font-size:11px">'+(s.nis||'-')+'</div></td>'+
            '<td class="text-center"><input type="number" step="0.01" class="form-control input-pts" value="'+(val!==''?(''+val).replace(/[^0-9\.\-]/g,''):'')+'" style="width:120px;margin:auto;"></td>'+
            '<td class="text-center pred-cell">'+pred(val,kkm)+'</td>'+
          '</tr>';
    });
    $('#ptsBody').html(tb);
  }

  $('#ptsBody').on('input','.input-pts',function(){
    var $tr=$(this).closest('tr'),v=$(this).val();
    $tr.find('.pred-cell').text(pred(v, parseInt($('#kkmInput').val()||'75',10)));
  });

  $('#btnPickTP').on('click',function(){
    if(!state.set_id){ toast('Klik Muat/Perbarui dulu','warning'); return; }
    $.getJSON(API_URL,{action:'tps',mapel_id:$('#mapelSelect').val(),kelas_id:$('#kelasSelect').val(),semester:$('#smtSelect').val()},function(r){
      if(!r||r.ok===false){ toast((r&&r.error)||'Gagal memuat TP','error'); return; }
      var sel = {}; (state.tp_ids||[]).forEach(function(x){ sel[x]=1; });
      var html='';
      if(!r.data.length){ html='<div class="text-muted">Belum ada TP aktif.</div>'; }
      else r.data.forEach(function(tp){ html+='<label class="item"><input type="checkbox" class="tp-ck" value="'+tp.id+'" '+(sel[tp.id]?'checked':'')+'> '+esc(tp.teks)+'</label>'; });
      $('#tpList').html(html); $('#tpModal').modal('show');
    }).fail(function(xhr){ toast('Gagal memuat TP (AJAX): '+xhr.statusText,'error'); });
  });
  $('#btnSaveTP').on('click',function(){
    var ids=[]; $('#tpList .tp-ck:checked').each(function(){ ids.push(parseInt(this.value,10)); });
    state.tp_ids=ids; $('#tpModal').modal('hide'); toast('TP disimpan di memori (klik Simpan untuk permanen)','success');
  });

  $('#btnSave').on('click',function(){
    if(!state.set_id){ toast('Klik Muat/Perbarui dulu','warning'); return; }
    var rows=[]; $('#ptsBody tr').each(function(){ var sid=parseInt($(this).attr('data-sid'),10)||0; if(!sid)return; rows.push({siswa_id:sid,nilai:{1:$(this).find('.input-pts').val()}}); });
    var meta=[{nhp_id:state.nhp_id||0,ke:1,label:'PTS',tanggal:$('#tglPTS').val()||'',tp_ids:state.tp_ids||[]}];
    var post={set_id:state.set_id,kkm:parseInt($('#kkmInput').val(),10)||75,meta:JSON.stringify(meta),rows:JSON.stringify(rows)};
    var $btn=$(this).prop('disabled',true).text('Menyimpan...');
    $.post(API_URL+'?action=save',post,function(r){
      if(r&&r.ok){ toast('Tersimpan','success'); $('#btnLoad').trigger('click'); }
      else { toast((r&&r.error)||'Gagal menyimpan','error'); }
    },'json').fail(function(xhr){ toast('Gagal menyimpan (AJAX): '+xhr.statusText,'error'); })
    .always(function(){ $btn.prop('disabled',false).html('<i class="fa fa-save"></i> Simpan'); });
  });

  function esc(s){return (''+s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
  function pred(n,kkm){ var v=parseFloat(n); if(isNaN(v)) return '-'; if(v>=90) return 'A'; if(v>=80) return 'B'; if(v>=kkm) return 'C'; return 'D'; }

  // auto-pilih mapel jika cuma satu
  if ($('#mapelSelect option').length===2){ $('#mapelSelect').prop('selectedIndex',1); }
})();
</script>

<?php require_once __DIR__.'/footer.php'; ?>
