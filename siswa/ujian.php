<?php
// =====================================
// siswa/ujian.php
// Portal "Penilaian Sumatif (Ujian Online)" — INTEGRASI TABEL ujian_gform (fix filter kelas via kelas_siswa)
// =====================================

@ini_set('display_errors','1');
@error_reporting(E_ALL);

$CURRENT = 'ujian.php';
date_default_timezone_set('Asia/Jakarta');

/* ==== Bootstrap koneksi & sesi ==== */
if (!isset($koneksi)) { @include_once __DIR__ . '/../koneksi.php'; }
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'siswa') {
  header("Location: ../index.php?alert=belum_login"); exit;
}

/* ==== Helpers ==== */
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
function roman_grade($n){ $map=[7=>'VII',8=>'VIII',9=>'IX']; return isset($map[(int)$n])?$map[(int)$n]:(string)$n; }
function parse_grade_from_text($txt){
  $t=strtoupper(trim((string)$txt)); if($t==='') return null;
  if (preg_match('~\\b(7|8|9)\\b~',$t,$m)) return (int)$m[1];
  if (strpos($t,'VIII')!==false) return 8; if (strpos($t,'VII')!==false) return 7; if (strpos($t,'IX')!==false) return 9; return null;
}
function jenis_label_caps($k){
  $k=strtolower((string)$k);
  switch($k){
    case 'uh': return 'ULANGAN HARIAN';
    case 'pts': return 'PTS / STS';
    case 'pas': return 'PAS / SAS';
    case 'pat': return 'PAT / SAT';
    case 'praktik': return 'PRAKTIK';
    case 'remedial': return 'REMEDIAL';
    case 'susulan': return 'SUSULAN';
    default: return strtoupper($k!==''?$k:'LAINNYA');
  }
}
function status_ujian($mulai_at,$selesai_at){
  $now=time(); $st='tanpa_jadwal'; $badge='label-info'; $label='Aktif (tanpa jadwal)';
  $m=$mulai_at?strtotime($mulai_at):null; $s=$selesai_at?strtotime($selesai_at):null;
  if($mulai_at||$selesai_at){
    if($m!==null && $now < $m){ $st='belum_mulai'; $badge='label-default'; $label='Belum mulai'; }
    elseif($s!==null && $now > $s){ $st='selesai'; $badge='label-danger'; $label='Selesai'; }
    else { $st='berlangsung'; $badge='label-success'; $label='Sedang berlangsung'; }
  }
  return array($st,$badge,$label);
}
function fmt_wib($dt){ if(!$dt) return '-'; $ts=strtotime($dt); if(!$ts) return e($dt); return date('Y-m-d H:i', $ts).' WIB'; }
function table_exists($db,$t){ if(!$db) return false; $t=mysqli_real_escape_string($db,$t); $r=@mysqli_query($db,"SHOW TABLES LIKE '$t'"); return $r && mysqli_num_rows($r)>0; }
function col_exists($db,$t,$c){ if(!$db) return false; $t=str_replace('`','',$t); $c=str_replace('`','',$c); $r=@mysqli_query($db,"SHOW COLUMNS FROM `{$t}` LIKE '".mysqli_real_escape_string($db,$c)."'"); return $r && mysqli_num_rows($r)>0; }

/* ==== Ambil identitas & KELAS siswa (PATCH: pakai kelas_siswa) ==== */
$uid = (int)($_SESSION['id'] ?? 0);           // id user siswa (di sistem kamu ini = siswa_id)
$profil=array(); $kelasId=null; $kelasLabel=''; $gradeNum=null; $gradeRoman='';

// 1) Ambil baris siswa agar bisa dipakai info lainnya (opsional)
if ($uid && table_exists($koneksi,'siswa')) {
  $key = null;
  // di DB kamu kolom utama adalah siswa_id
  if (col_exists($koneksi,'siswa','siswa_id')) $key='siswa_id';
  elseif (col_exists($koneksi,'siswa','id'))   $key='id';
  $profil = array();
  if ($key) {
    $stmt = $koneksi->prepare("SELECT * FROM siswa WHERE $key = ? LIMIT 1");
    $stmt->bind_param("i",$uid);
    if ($stmt->execute()) {
      $rs = $stmt->get_result();
      $profil = $rs?($rs->fetch_assoc()?:array()):array();
    }
    $stmt->close();
  }
}

// 2) **PATCH UTAMA**: Map kelas via kelas_siswa → kelas
if ($uid && table_exists($koneksi,'kelas_siswa')) {
  // relasi: kelas_siswa.ks_siswa (mengacu ke siswa.siswa_id), kelas_siswa.ks_kelas → kelas.kelas_id
  $sqlMap = "SELECT ks.ks_kelas AS kelas_id, k.kelas_nama 
             FROM kelas_siswa ks 
             LEFT JOIN kelas k ON k.kelas_id = ks.ks_kelas 
             WHERE ks.ks_siswa = ? 
             ORDER BY ks.ks_id DESC LIMIT 1";
  if ($stmt = $koneksi->prepare($sqlMap)) {
    $stmt->bind_param("i",$uid);
    if ($stmt->execute()) {
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        $kelasId    = (int)$row['kelas_id'];
        $kelasLabel = (string)($row['kelas_nama'] ?? '');
      }
    }
    $stmt->close();
  }
}

// 3) Tambahan fallback lama (kalau suatu saat ada kolom kelas_id di siswa)
if($kelasId===null){
  foreach(array('kelas_id','siswa_kelas_id') as $ck){
    if(!empty($profil[$ck]) && ctype_digit((string)$profil[$ck])){ $kelasId=(int)$profil[$ck]; break; }
  }
}
// label kelas
if(trim($kelasLabel)===''){
  foreach(array('siswa_kelas','kelas','kelas_nama','rombel','rombel_nama') as $ck){
    if(!empty($profil[$ck])){ $kelasLabel=trim((string)$profil[$ck]); break; }
  }
}
// derive tingkat (VII/VIII/IX) dari label
if($gradeNum===null && $kelasLabel!==''){
  $g=parse_grade_from_text($kelasLabel); if(in_array($g,array(7,8,9),true)) $gradeNum=$g;
}
if($gradeNum===null) $gradeNum=7;
$gradeRoman=roman_grade($gradeNum);

/* ==== Susun query data ujian dari ujian_gform (hanya aktif + filter kelas siswa) ==== */
$select = array(
  "u.id","u.jenis","u.judul","u.gform_url","u.durasi_menit","u.mulai_at","u.selesai_at","u.is_active","u.kelas_id","u.mapel_id"
);
$join   = '';
$kelasLabelFromJoin = '';
$mapelLabelExpr = "''";

// JOIN kelas (opsional, untuk label)
if (table_exists($koneksi,'kelas')){
  $kelasJoinOnParts = array();
  if (col_exists($koneksi,'kelas','kelas_id')) $kelasJoinOnParts[] = 'k.kelas_id=u.kelas_id';
  if (col_exists($koneksi,'kelas','id'))       $kelasJoinOnParts[] = 'k.id=u.kelas_id';
  if (!empty($kelasJoinOnParts)) {
    $join .= ' LEFT JOIN kelas k ON ('.implode(' OR ', $kelasJoinOnParts).')';
    if (col_exists($koneksi,'kelas','kelas_nama')) $kelasLabelFromJoin = 'k.kelas_nama';
    elseif (col_exists($koneksi,'kelas','nama'))   $kelasLabelFromJoin = 'k.nama';
    elseif (col_exists($koneksi,'kelas','label'))  $kelasLabelFromJoin = 'k.label';
  }
}
if ($kelasLabelFromJoin!=='') $select[] = $kelasLabelFromJoin.' AS kelas_nama';

// JOIN mapel (opsional, untuk label)
if (table_exists($koneksi,'mapel')){
  $mapelJoinOnParts = array();
  if (col_exists($koneksi,'mapel','mapel_id')) $mapelJoinOnParts[] = 'm.mapel_id=u.mapel_id';
  if (col_exists($koneksi,'mapel','id'))       $mapelJoinOnParts[] = 'm.id=u.mapel_id';
  if (!empty($mapelJoinOnParts)) {
    $join .= ' LEFT JOIN mapel m ON ('.implode(' OR ', $mapelJoinOnParts).')';
    if (col_exists($koneksi,'mapel','mapel_nama')) $mapelLabelExpr = 'm.mapel_nama';
    elseif (col_exists($koneksi,'mapel','nama'))   $mapelLabelExpr = 'm.nama';
  }
}
$select[] = $mapelLabelExpr.' AS mapel_nama';

$sql = "SELECT ".implode(',', $select)." FROM ujian_gform u ".$join." WHERE u.is_active=1";

/* === FILTER KELAS WAJIB ===
   Jika $kelasId terdeteksi dari kelas_siswa → tampilkan hanya ujian untuk kelas tersebut. */
if($kelasId){ $sql .= " AND u.kelas_id=".(int)$kelasId; }

// Urutan stabil
$sql .= " ORDER BY CASE u.jenis
  WHEN 'uh' THEN 1 WHEN 'pts' THEN 2 WHEN 'pas' THEN 3 WHEN 'pat' THEN 4 WHEN 'praktik' THEN 5 WHEN 'remedial' THEN 6 WHEN 'susulan' THEN 7 ELSE 99 END,
  u.mulai_at, u.id";

$rows=array();
$res = @mysqli_query($koneksi, $sql);
if(!$res){ @error_log('[UJIAN] SQL error: '.@mysqli_error($koneksi).' | SQL='.$sql); }
if($res){ while($r=@mysqli_fetch_assoc($res)){ $rows[]=$r; } }

// Bentuk struktur untuk filter JS: jenis -> mapel -> list rows
$jenisList=array(); $mapelByJenis=array(); $rowsByJenis=array();
for($i=0;$i<count($rows);$i++){
  $r=$rows[$i];
  $jkey = strtolower(isset($r['jenis'])?$r['jenis']:''); if($jkey==='') $jkey='lainnya';
  if(!isset($rowsByJenis[$jkey])){ $rowsByJenis[$jkey]=array(); $mapelByJenis[$jkey]=array(); $jenisList[]=array('key'=>$jkey,'label'=>jenis_label_caps($jkey)); }
  $mkey = (string)(!empty($r['mapel_id'])?$r['mapel_id']:(isset($r['mapel_nama'])?$r['mapel_nama']:'0'));
  $mlabel = !empty($r['mapel_nama'])?$r['mapel_nama']:'-';
  if(!isset($mapelByJenis[$jkey][$mkey])){ $mapelByJenis[$jkey][$mkey]=$mlabel; }

  list($st,$badge,$stLabel) = status_ujian(isset($r['mulai_at'])?$r['mulai_at']:null, isset($r['selesai_at'])?$r['selesai_at']:null);
  $rowsByJenis[$jkey][] = array(
    'id'=>(int)$r['id'],
    'jenis'=>$jkey,
    'jenis_label'=>jenis_label_caps($jkey),
    'kelas_label'=> isset($r['kelas_nama']) && $r['kelas_nama']!=='' ? $r['kelas_nama'] : ($kelasLabel!==''?$kelasLabel:$gradeRoman),
    'mapel_key'=>$mkey,
    'mapel_label'=>$mlabel,
    'judul'=> (!empty($r['judul'])?$r['judul']:(($mlabel!=='-'?$mlabel:'Ujian').' — '.jenis_label_caps($jkey))),
    'gform_url'=> isset($r['gform_url'])?$r['gform_url']:'',
    'durasi'=> isset($r['durasi_menit'])?(int)$r['durasi_menit']:null,
    'mulai'=> isset($r['mulai_at'])?$r['mulai_at']:null,
    'selesai'=> isset($r['selesai_at'])?$r['selesai_at']:null,
    'status'=>$st,
    'badge'=>$badge,
    'status_label'=>$stLabel,
  );
}
foreach($mapelByJenis as $jk=>$mm){ if (is_array($mm)) { natcasesort($mm); $mapelByJenis[$jk]=$mm; } }

if(!$jenisList){ $jenisList=array(); $mapelByJenis=new stdClass(); $rowsByJenis=new stdClass(); }

/* ==== Layout ==== */
$header = __DIR__.'/header.php';
if (is_file($header)) require_once $header; else echo "<div class='content-wrapper'><section class='content'><div class='alert alert-warning'>header.php tidak ditemukan.</div></section></div>";
?>

<!-- =================== KONTEN =================== -->
<div class="content-wrapper">
  <section class="content-header">
    <h1>Ujian Online <small>Penilaian Sumatif • Panel Siswa</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Beranda</a></li>
      <li class="active">Ujian Online</li>
    </ol>
  </section>

  <section class="content">
    <!-- Info kelas terdeteksi -->
    <div class="box">
      <div class="box-body">
        <div class="row" style="align-items:center">
          <div class="col-sm-8">
            <p style="margin:0 0 6px"><strong>Hai! Kami mendeteksi Anda di</strong>
              <?php if (trim((string)$kelasLabel) !== ''): ?>
                <span class="label label-default" style="margin-left:4px">Kelas <?= e($kelasLabel) ?></span>
              <?php endif; ?>
            </p>
            <p class="note-mini" style="margin:2px 0 0;color:#6b7280">
              Ujian yang muncul sudah diset khusus buat kelasmu.
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter: Jenis Ujian + Mapel -->
    <div class="box">
      <div class="box-body filter-bar">
        <div class="row">
          <div class="col-sm-6">
            <label for="jenis">Jenis Ujian</label>
            <select id="jenis" class="form-control"></select>
            <p class="note-mini" id="jenisDesc" style="margin-top:6px;color:#6b7280">
              Pilih jenis ujian yang tersedia sesuai jadwal guru.
            </p>
          </div>
          <div class="col-sm-6">
            <label for="mapel">Mata Pelajaran</label>
            <select id="mapel" class="form-control"></select>
            <p class="note-mini" style="margin-top:6px;color:#6b7280">Pilih salah satu mapel atau biarkan "Semua".</p>
          </div>
        </div>
        <div class="legend" style="margin-top:8px">
          <span class="label label-success">Sedang berlangsung</span>
          <span class="label label-default">Belum mulai</span>
          <span class="label label-danger">Selesai</span>
          <span class="label label-info">Aktif (tanpa jadwal)</span>
        </div>
      </div>
    </div>

    <!-- Tabel tautan -->
    <div class="row exam-card">
      <div class="col-md-12">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-file-alt"></i> Tautan Ujian</h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th style="width:150px">JENIS</th>
                    <th style="width:120px">KELAS</th>
                    <th style="min-width:240px">JUDUL / MAPEL</th>
                    <th style="min-width:260px">JADWAL</th>
                    <th style="width:110px">DURASI</th>
                    <th style="width:160px">AKSI</th>
                  </tr>
                </thead>
                <tbody id="tbodyUjian">
                  <!-- diisi via JS -->
                </tbody>
              </table>
            </div>
            <p class="note-mini" style="margin-top:6px;color:#6b7280">
              Pastikan akun Google sudah login & koneksi stabil. Jika halaman tidak memuat, lakukan <b>Hard Reload (Ctrl/Cmd+Shift+R)</b>.
            </p>
          </div>
        </div>
      </div>
    </div>

  </section>
</div>

<?php $footer = __DIR__.'/footer.php'; if (is_file($footer)) require_once $footer; ?>

<script>
(function(){
  var DATA = {
    jenisList: <?= json_encode($jenisList, JSON_UNESCAPED_UNICODE) ?>,
    mapelByJenis: <?= json_encode($mapelByJenis, JSON_UNESCAPED_UNICODE) ?>,
    rowsByJenis: <?= json_encode($rowsByJenis, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
    tingkat: <?= json_encode($gradeRoman, JSON_UNESCAPED_UNICODE) ?>
  };

  function escapeHtml(s){
    return String(s).replace(/[&<>"'`=\/]/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c];
    });
  }
  function b64(s){ try{ return btoa(unescape(encodeURIComponent(String(s)))); }catch(e){ return ''; } }
  function fmtWIB(iso){ if(!iso) return '-'; var d=new Date(iso.replace(' ','T')+'+07:00'); if(isNaN(d)) return escapeHtml(iso);
    var p=function(n){ return (n<10?'0':'')+n; }; return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+' '+p(d.getHours())+':'+p(d.getMinutes())+' WIB'; }

  function populateJenis(){
    var $j=$('#jenis').empty();
    if(!DATA.jenisList || DATA.jenisList.length===0){
      $j.append('<option value="">— Tidak ada jadwal —</option>');
      $('#mapel').empty().append('<option value="">—</option>');
      renderRows(null,null); return;
    }
    for(var i=0;i<DATA.jenisList.length;i++){
      var it=DATA.jenisList[i];
      $j.append('<option value="'+escapeHtml(it.key)+'">'+escapeHtml(it.label)+'</option>');
    }
    populateMapel();
  }

  function populateMapel(){
    var jKey=$('#jenis').val();
    var $m=$('#mapel').empty();
    var dict=(DATA.mapelByJenis && DATA.mapelByJenis[jKey])?DATA.mapelByJenis[jKey]:null;
    if(!dict){ $m.append('<option value="">— Tidak ada mapel —</option>'); renderRows(jKey,null); return; }
    $m.append('<option value="">Semua mapel</option>');
    for (var key in dict){ if(Object.prototype.hasOwnProperty.call(dict,key)){
      $m.append('<option value="'+escapeHtml(key)+'">'+escapeHtml(dict[key])+'</option>');
    }}
    renderRows(jKey, $('#mapel').val());
  }

  function renderRows(jKey, mKey){
    var $tbody=$('#tbodyUjian').empty();
    if(!jKey){ $tbody.append('<tr><td colspan="6" class="text-center text-muted">Belum ada jadwal ujian untuk tingkat Anda.</td></tr>'); return; }
    var list=(DATA.rowsByJenis && DATA.rowsByJenis[jKey])?DATA.rowsByJenis[jKey]:[];
    if(mKey){ list=list.filter(function(r){ return String(r.mapel_key)===String(mKey); }); }
    if(!list || list.length===0){ $tbody.append('<tr><td colspan="6" class="text-center text-muted">Tidak ada data untuk pilihan ini.</td></tr>'); return; }

    for(var i=0;i<list.length;i++){
      var ent=list[i];
      var jadwalStr='-';
      if(ent.mulai || ent.selesai){
        jadwalStr=(ent.mulai?('Mulai: '+fmtWIB(ent.mulai)):'') + (ent.selesai?('<br>Selesai: '+fmtWIB(ent.selesai)):'');
      } else { jadwalStr='<em class="text-muted">Tidak terjadwal</em>'; }

      var enable=(ent.status==='berlangsung' || ent.status==='tanpa_jadwal');
      var href='#';
      if(ent.id){
        href='exam_gform.php?ujian_id='+encodeURIComponent(ent.id);
        if(ent.gform_url){ href += '&g='+encodeURIComponent(b64(ent.gform_url)); }
        if(ent.durasi){ href += '&dur='+encodeURIComponent(ent.durasi); }
      }

      var $tr=$('<tr/>');
      $tr.append('<td><span class="label label-success">'+escapeHtml(ent.jenis_label)+'</span></td>');
      $tr.append('<td><span class="label label-primary">'+escapeHtml(ent.kelas_label||DATA.tingkat)+'</span></td>');
      $tr.append('<td><div><strong>'+escapeHtml(ent.judul)+'</strong><div class="text-muted" style="margin-top:2px">'+escapeHtml(ent.mapel_label||'-')+'</div></div></td>');
      $tr.append('<td>'+jadwalStr+'</td>');
      $tr.append('<td>'+(ent.durasi?escapeHtml(ent.durasi)+' menit':'-')+'</td>');
      $tr.append($('<td/>').append($('<a/>',{
        class:'btn btn-sm btn-primary'+(enable?'':' disabled'),
        href: enable?href:'#',
        'aria-disabled': enable?null:'true',
        title: enable? 'Mulai ujian' : 'Belum dalam rentang waktu ujian',
        html:'<i class="fa fa-play"></i> Mulai Ujian'
      })).append('<div style="margin-top:6px"><span class="label '+escapeHtml(ent.badge)+'">'+escapeHtml(ent.status_label)+'</span></div>'));

      $tbody.append($tr);
    }
  }

  $(document).on('change','#jenis', populateMapel);
  $(document).on('change','#mapel', function(){ renderRows($('#jenis').val(), $('#mapel').val()); });

  $(populateJenis);
})();
</script>
