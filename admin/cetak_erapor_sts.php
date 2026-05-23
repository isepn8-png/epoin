<?php
/**
 * CETAK e-RAPOR STS (PTS) — Panel Filter + Cetak
 * - Pilih: TA, Semester, Kelas, (opsional) Siswa
 * - Cetak 1 siswa atau semua siswa di kelas (page-break)
 * - Deskripsi otomatis dari view v_rapor_pts_deskripsi (optimal & perlu)
 * - Kompatibel dengan struktur DB E-POIN (kelas_siswa, nilai_pts_set, nilai_pts, mapel, ta, sekolah, user, absensi_harian/_detail)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// ==== koneksi (pakai proyek Anda) ====
$USING_PROJECT_HEADER = false;
$koneksi = null;
if (file_exists(__DIR__ . '/header.php')) {
  $USING_PROJECT_HEADER = true;
  include __DIR__ . '/header.php'; // biasanya sudah start session + include ../koneksi.php + $koneksi (mysqli)
}
if (!$koneksi && file_exists(__DIR__ . '/../koneksi.php')) {
  include __DIR__ . '/../koneksi.php'; // fallback
}

// graceful if masih belum ada koneksi
if (!isset($koneksi) || !$koneksi) {
  // koneksi mandiri (ubah sesuai server Anda jika perlu)
  $koneksi = @mysqli_connect('localhost','root','','smpn1gun_epoint');
  if (!$koneksi) { die('DB gagal terkoneksi. Sesuaikan kredensial di file ini.'); }
}
@mysqli_set_charset($koneksi,'utf8mb4');
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }

// ==== helpers ====
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function q_all($db, $sql, $params = []){
  if(!$params){ $r=@mysqli_query($db,$sql); $out=[]; if($r){ while($row=@mysqli_fetch_assoc($r)) $out[]=$row; @mysqli_free_result($r);} return $out; }
  $stmt=@mysqli_prepare($db,$sql); if(!$stmt) return [];
  $types = str_repeat('s', count($params));
  @mysqli_stmt_bind_param($stmt,$types,...$params);
  @mysqli_stmt_execute($stmt);
  $res=@mysqli_stmt_get_result($stmt);
  $out=[]; if($res){ while($row=@mysqli_fetch_assoc($res)) $out[]=$row; }
  @mysqli_stmt_close($stmt); return $out;
}
function q_one($db,$sql,$params=[],$def=null){
  $rows = q_all($db,$sql,$params); if(!$rows) return $def; $r=$rows[0]; return array_shift($r);
}
function q_row($db,$sql,$params=[]){ $rows=q_all($db,$sql,$params); return $rows? $rows[0]:null; }

function kelas_fase($kelas_nama){
  // heuristik sederhana: 7->D, 8->E, 9->F
  if (preg_match('~\b7\b|7\s*[A-Z]~',$kelas_nama)) return 'D';
  if (preg_match('~\b8\b|8\s*[A-Z]~',$kelas_nama)) return 'E';
  if (preg_match('~\b9\b|9\s*[A-Z]~',$kelas_nama)) return 'F';
  return '-';
}
function indo_tgl($ymd){
  $bln=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $t=strtotime($ymd?:date('Y-m-d')); return date('j',$t).' '.$bln[(int)date('n',$t)].' '.date('Y',$t);
}
function compose_desk($opt,$perlu){
  $opt = trim((string)$opt); $perlu = trim((string)$perlu);
  $bag=[]; if($opt!=='') $bag[]='Mencapai kompetensi pada: '.str_replace("\n","; ",$opt);
  if($perlu!=='') $bag[]='Perlu peningkatan pada: '.str_replace("\n","; ",$perlu);
  return $bag? implode('. ',$bag).'.' : '-';
}
function semester_range($ta_nama,$semester){
  if(!preg_match('~(\d{4})/(\d{4})~',$ta_nama,$m)) return [null,null];
  [$all,$y1,$y2]=$m; return ($semester==1)? ["$y1-07-01","$y1-12-31"] : ["$y2-01-01","$y2-06-30"];
}

// ==== ambil master untuk filter ====
$tas = q_all($koneksi,"SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");
$kelas = q_all($koneksi,"SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama");

$ta_id    = isset($_GET['ta_id']) ? (int)$_GET['ta_id'] : (int)q_one($koneksi,"SELECT ta_id FROM ta WHERE aktif=1 LIMIT 1",[],0);
$semester = isset($_GET['semester']) ? (int)$_GET['semester'] : ((date('n')>=7 && date('n')<=12)?1:2);
$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$mode     = isset($_GET['mode']) ? $_GET['mode'] : ''; // ''=form saja, 'preview'=render 1 siswa, 'all'=render semua

$ta_nama = $ta_id? (string)q_one($koneksi,"SELECT ta_nama FROM ta WHERE ta_id=?",[$ta_id],'') : '';

$sekolah = q_row($koneksi,"SELECT * FROM sekolah LIMIT 1");
$kepsekNama = '-'; $kepsekNIP='-';
if(!empty($sekolah['kepala_user_id'])){
  $u = q_row($koneksi,"SELECT user_nama, user_username FROM user WHERE user_id=?",[(int)$sekolah['kepala_user_id']]);
  if($u){ $kepsekNama = $u['user_nama']?:'-'; $kepsekNIP = $u['user_username']?:'-'; }
}
$kelas_nama = $kelas_id? (string)q_one($koneksi,"SELECT kelas_nama FROM kelas WHERE kelas_id=?",[$kelas_id],'') : '';
$fase = $kelas_nama? kelas_fase($kelas_nama) : '-';

// wali kelas (opsional tabel kelas_wali)
$waliNama='-'; $waliNIP='-';
$has_kw = q_one($koneksi,"SHOW TABLES LIKE 'kelas_wali'");
if($kelas_id && $has_kw){
  $kw = q_row($koneksi,"SELECT u.user_nama, u.user_username
                        FROM kelas_wali kw JOIN user u ON u.user_id = kw.wali_user_id
                        WHERE kw.kelas_id=?",[$kelas_id]);
  if($kw){ $waliNama = $kw['user_nama']?:'-'; $waliNIP = $kw['user_username']?:'-'; }
}

// daftar siswa pada kelas
$daftar_siswa=[];
if($kelas_id){
  $daftar_siswa = q_all($koneksi,"
    SELECT s.siswa_id,
           COALESCE(s.siswa_nama, s.nama) AS nama,
           COALESCE(s.siswa_nis, s.nis) AS nis,
           COALESCE(s.siswa_nisn, s.nisn) AS nisn
    FROM kelas_siswa ks
    JOIN siswa s ON s.siswa_id = ks.siswa_id
    WHERE ks.kelas_id=?
    ORDER BY nama
  ",[$kelas_id]);
}

// mapel/pts-set di kelas/ta/semester
$mapelSets = [];
if($kelas_id && $ta_id){
  $mapelSets = q_all($koneksi,"
    SELECT ps.pts_set_id, ps.mapel_id, m.mapel_nama
    FROM nilai_pts_set ps
    JOIN mapel m ON m.mapel_id = ps.mapel_id
    WHERE ps.kelas_id=? AND ps.ta_id=? AND ps.semester=?
    ORDER BY m.mapel_id
  ",[$kelas_id,$ta_id,$semester]);
  if(!$mapelSets){
    // fallback jika belum setup PTS: pakai semua mapel
    $mapelSets = q_all($koneksi,"SELECT NULL AS pts_set_id, mapel_id, mapel_nama FROM mapel ORDER BY mapel_id");
  }
}

[$dStart,$dEnd] = semester_range($ta_nama,$semester);
$absensi=[]; // S/I/A per siswa
if($kelas_id && $ta_id && $dStart && $dEnd){
  // kolom status pada detail biasanya 'status' (S/I/A)
  $abs = q_all($koneksi,"
    SELECT ahd.siswa_id,
           SUM(ahd.status='S') AS S,
           SUM(ahd.status='I') AS I,
           SUM(ahd.status='A') AS A
    FROM absensi_harian_detail ahd
    JOIN absensi_harian ah ON ah.harian_id = ahd.harian_id
    WHERE ah.kelas_id=? AND ah.ta_id=? AND ah.tgl BETWEEN ? AND ?
    GROUP BY ahd.siswa_id
  ",[$kelas_id,$ta_id,$dStart,$dEnd]);
  foreach($abs as $r){ $absensi[(int)$r['siswa_id']] = ['S'=>(int)$r['S'],'I'=>(int)$r['I'],'A'=>(int)$r['A']]; }
}

// fungsi render 1 halaman siswa
function render_lembar($db,$s,$mapelSets,$ta_nama,$semester,$kelas_nama,$fase,$sekolah,$waliNama,$waliNIP,$kepsekNama,$kepsekNIP,$absensi){
  $sakit = $absensi[$s['siswa_id']]['S'] ?? 0;
  $izin  = $absensi[$s['siswa_id']]['I'] ?? 0;
  $alpa  = $absensi[$s['siswa_id']]['A'] ?? 0;

  // query nilai + view deskripsi
  $get = @mysqli_prepare($db,"
    SELECT np.nilai, v.deskripsi_optimal, v.deskripsi_perlu
    FROM nilai_pts np
    LEFT JOIN v_rapor_pts_deskripsi v ON v.pts_id = np.pts_id
    WHERE np.siswa_id=? AND np.pts_set_id=?
  ");

  ob_start(); ?>
  <div class="sheet">
    <div class="kop">
      <table class="meta">
        <tr><td>Nama</td><td>: <?=esc($s['nama'])?></td></tr>
        <tr><td>NIS/NISN</td><td>: <?=esc($s['nis']?:'-')?> / <?=esc($s['nisn']?:'-')?></td></tr>
        <tr><td>Nama Sekolah</td><td>: <?=esc($sekolah['nama_sekolah']??'-')?></td></tr>
        <tr><td>Alamat</td><td>: <?=esc($sekolah['alamat']??'-')?></td></tr>
      </table>
      <table class="meta">
        <tr><td>Kelas</td><td>: <?=esc($kelas_nama)?></td></tr>
        <tr><td>Fase</td><td>: <?=esc($fase)?></td></tr>
        <tr><td>Semester</td><td>: <?=esc($semester)?></td></tr>
        <tr><td>Tahun Pelajaran</td><td>: <?=esc(trim($ta_nama))?></td></tr>
      </table>
    </div>

    <h2 class="title">LAPORAN HASIL BELAJAR</h2>

    <table class="tnilai">
      <thead>
        <tr><th>No</th><th>Mata Pelajaran</th><th>Nilai Akhir</th><th>Capaian Kompetensi</th></tr>
      </thead>
      <tbody>
      <?php $no=1;
      foreach($mapelSets as $ms){
        $nilaiTxt='-'; $desk='-';
        if ($ms['pts_set_id']) {
          @mysqli_stmt_bind_param($get,'ii',$s['siswa_id'],$ms['pts_set_id']);
          @mysqli_stmt_execute($get);
          $res=@mysqli_stmt_get_result($get);
          if($res && ($r=@mysqli_fetch_assoc($res))){
            $nilaiTxt = is_null($r['nilai'])?'-':(string)(int)$r['nilai'];
            $desk = compose_desk($r['deskripsi_optimal'],$r['deskripsi_perlu']);
          }
        }
        ?>
        <tr>
          <td class="ctr"><?=$no++?></td>
          <td><?=esc($ms['mapel_nama'])?></td>
          <td class="ctr"><?=$nilaiTxt?></td>
          <td><?=nl2br(esc($desk))?></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>

    <table class="rekap">
      <tr><td>Sakit</td><td>:</td><td><?=$sakit?> hari</td></tr>
      <tr><td>Izin</td><td>:</td><td><?=$izin?> hari</td></tr>
      <tr><td>Tanpa Keterangan</td><td>:</td><td><?=$alpa?> hari</td></tr>
    </table>

    <div class="ttd-wrap">
      <div class="ttd-col">Mengetahui<br>Orang Tua/Wali,<br><br><br><br>............................</div>
      <div class="ttd-col ctr">Mengetahui<br>Kepala Sekolah,<br><br><br><br>
        <strong><?=esc($kepsekNama)?></strong><br>NIP. <?=esc($kepsekNIP)?>
      </div>
      <div class="ttd-col ctr">Tasikmalaya, <?=esc(indo_tgl(date('Y-m-d'))) ?><br>Wali Kelas,<br><br><br><br>
        <strong><?=esc($waliNama)?></strong><br>NIP. <?=esc($waliNIP)?>
      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak e-Rapor STS</title>
<style>
  *{ box-sizing:border-box }
  body{ font-family: Arial, Helvetica, sans-serif; color:#000; }
  .container{ max-width: 980px; margin:12px auto; padding:0 12px; }
  .panel{ background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; box-shadow:0 6px 18px rgba(0,0,0,.05); }
  .panel h3{ margin:6px 0 10px; }
  .row{ display:flex; flex-wrap:wrap; gap:10px 14px; }
  .row .col{ min-width:220px }
  label{ font-size:12px; color:#334155; display:block; margin-bottom:4px }
  select,button{ padding:9px 10px; border-radius:9px; border:1px solid #cbd5e1; font-size:14px }
  .btn{ cursor:pointer; }
  .btn-primary{ background:#0B57D0; color:#fff; border-color:#0B57D0 }
  .btn-outline{ background:#fff; color:#0B57D0; border-color:#0B57D0 }
  .actions{ display:flex; gap:10px; align-items:flex-end; }
  .hint{ color:#475569; font-size:12px; margin-top:6px }

  @page { size:A4 portrait; margin:12mm; }
  .sheet{ width:210mm; min-height:297mm; padding:12mm 14mm; margin:0 auto; }
  .kop{ display:grid; grid-template-columns: 1fr 1fr; gap:14px; font-size:12px; }
  .meta{ width:100%; border-collapse:collapse }
  .meta td{ padding:3px 6px; vertical-align:top }
  h2.title{ text-align:center; margin:10px 0 12px; font-size:16px; }
  .tnilai{ width:100%; border-collapse:collapse; font-size:12px }
  .tnilai th,.tnilai td{ border:1px solid #000; padding:6px 7px; vertical-align:top }
  .tnilai th{ text-align:center }
  .tnilai td:nth-child(1){ width:22px; text-align:center }
  .tnilai td:nth-child(2){ width:240px }
  .tnilai td:nth-child(3){ width:70px; text-align:center }
  .rekap{ width:260px; border-collapse:collapse; margin:12px 0 0 auto; font-size:12px }
  .rekap td{ border:1px solid #000; padding:6px 8px }
  .ttd-wrap{ display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-top:18px; }
  .ttd-col{ font-size:12px; }
  .ctr{ text-align:center }

  .page-break{ page-break-after: always; }

  @media print{
    .noprint{ display:none !important }
    body{ background:#fff }
    .sheet{ padding:10mm 12mm }
  }
</style>
</head>
<body>

<div class="container noprint">
  <div class="panel">
    <h3>Cetak e-Rapor STS</h3>
    <form method="get" class="row">
      <div class="col">
        <label>Tahun Pelajaran</label>
        <select name="ta_id" onchange="this.form.submit()">
          <?php foreach($tas as $t): ?>
          <option value="<?=$t['ta_id']?>" <?=$t['ta_id']==$ta_id?'selected':''?>><?=esc($t['ta_nama'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col">
        <label>Semester</label>
        <select name="semester" onchange="this.form.submit()">
          <option value="1" <?=$semester==1?'selected':''?>>1 (Jul–Des)</option>
          <option value="2" <?=$semester==2?'selected':''?>>2 (Jan–Jun)</option>
        </select>
      </div>
      <div class="col">
        <label>Kelas</label>
        <select name="kelas_id" onchange="this.form.submit()">
          <option value="">— pilih kelas —</option>
          <?php foreach($kelas as $k): ?>
          <option value="<?=$k['kelas_id']?>" <?=$k['kelas_id']==$kelas_id?'selected':''?>><?=esc($k['kelas_nama'])?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Setelah memilih kelas, daftar siswa akan muncul.</div>
      </div>
      <div class="col">
        <label>Siswa (opsional untuk preview per siswa)</label>
        <select name="siswa_id">
          <option value="">— semua siswa —</option>
          <?php foreach($daftar_siswa as $s): ?>
          <option value="<?=$s['siswa_id']?>" <?=$s['siswa_id']==$siswa_id?'selected':''?>><?=esc($s['nama'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="actions">
        <button class="btn btn-outline" name="mode" value="">Terapkan</button>
        <button class="btn btn-primary" name="mode" value="preview" <?=$kelas_id&&$siswa_id?'':'disabled'?>>Tampilkan Preview</button>
        <button class="btn btn-primary" name="mode" value="all" <?=$kelas_id?'':'disabled'?>>Cetak Semua Siswa</button>
        <button type="button" class="btn" onclick="window.print()">Print</button>
      </div>
    </form>
  </div>
</div>

<?php
// === RENDER PREVIEW SATU SISWA ===
if ($mode==='preview' && $kelas_id && $siswa_id) {
  $s = q_row($koneksi,"
    SELECT s.siswa_id, COALESCE(s.siswa_nama,s.nama) AS nama,
           COALESCE(s.siswa_nis,s.nis) AS nis, COALESCE(s.siswa_nisn,s.nisn) AS nisn
    FROM siswa s WHERE s.siswa_id=?",[$siswa_id]);
  if($s){
    echo '<div class="noprint container" style="margin-top:8px;color:#334155">Preview 1 siswa. Klik <b>Print</b> untuk cetak.</div>';
    echo render_lembar($koneksi,$s,$mapelSets,$ta_nama,$semester,$kelas_nama,$fase,$sekolah,$waliNama,$waliNIP,$kepsekNama,$kepsekNIP,$absensi);
  } else {
    echo '<div class="container" style="color:#b91c1c">Siswa tidak ditemukan.</div>';
  }
}

// === RENDER SEMUA SISWA ===
if ($mode==='all' && $kelas_id && $daftar_siswa){
  echo '<div class="noprint container" style="margin:8px auto; color:#334155">Mode: Cetak semua siswa (page-break per siswa). Klik <b>Print</b>.</div>';
  $last = count($daftar_siswa)-1;
  foreach($daftar_siswa as $i=>$s){
    echo render_lembar($koneksi,$s,$mapelSets,$ta_nama,$semester,$kelas_nama,$fase,$sekolah,$waliNama,$waliNIP,$kepsekNama,$kepsekNIP,$absensi);
    if($i<$last) echo '<div class="page-break"></div>';
  }
}

// jika tidak ada mode, hanya panel filter ditampilkan (di atas)
?>

</body>
</html>
