<?php
// admin/kalender_akademik.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/epoin_security.php';
// Helper akademik (epoin_resolve_semester, dll.) — normalnya sudah di-load koneksi.php; jaga-jaga:
if (!function_exists('epoin_resolve_semester')) {
  require_once __DIR__ . '/../includes/akademik_helper.php';
}

// === GUARD KEAMANAN: khusus staf (admin/guru/dll), tolak siswa & tamu. ===
// WAJIB dipanggil SEBELUM proses POST & sebelum output apa pun. Sebelumnya
// aksi POST (add/delete/generate) berjalan tanpa cek login sama sekali.
$user_id = epoin_staff_only_guard();

function escs($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function i($v){ return (int)$v; }
function _get($k,$d=null){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function _post($k,$d=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function valid_ymd($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s) && strtotime($s)!==false; }
function fmt_dmy($ymd){ if(!$ymd) return '-'; $ts=strtotime($ymd); return $ts?date('d/m/Y',$ts):escs($ymd); }
if (!function_exists('table_exists')) {
  function table_exists($k,$n){ $r=@mysqli_query($k,"SHOW TABLES LIKE '".mysqli_real_escape_string($k,$n)."'"); return $r && mysqli_num_rows($r)>0; }
}

$today = date('Y-m-d');

// Tipe libur valid — samakan dengan ENUM kolom kalender_libur.tipe
$TIPE_VALID = ['nasional','sekolah','kegiatan','cuti_bersama','lain'];

// Prasyarat tabel/view (degrade rapi, bukan fatal, bila DB belum dimigrasi)
$HAS_KALENDER = table_exists($koneksi,'kalender_libur');
$HAS_VIEWNE   = table_exists($koneksi,'view_non_efektif');
$HAS_HARIEF   = table_exists($koneksi,'hari_efektif');

// === Ambil TA aktif + daftar TA (via helper terpusat) ===
$ta_aktif = epoin_ta_aktif($koneksi);
$TA  = isset($_GET['ta']) ? i($_GET['ta']) : (int)($ta_aktif['ta_id'] ?? 0);
$tas = mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");

// === Semester: OTOMATIS sesuai bulan berjalan (Jul–Des=1, Jan–Jun=2). ===
// Bisa di-override manual lewat ?semester=1|2. Logika identik dgn modul lain.
$semester          = epoin_resolve_semester();
$semester_is_auto  = !isset($_GET['semester']);
$ta_row  = ($TA>0) ? mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_nama FROM ta WHERE ta_id=".i($TA))) : null;
$ta_nama = trim($ta_row['ta_nama'] ?? ($ta_aktif['ta_nama'] ?? ''));
[$semStart,$semEnd] = epoin_semester_range($ta_nama,$semester);
$hari_sekolah = (_get('hari_sekolah','5') === '6') ? 6 : 5;

// === Aksi: tambah libur (single / range)
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = _post('act','');
  // CSRF wajib untuk semua aksi yang mengubah data
  if (!epoin_csrf_validate()) {
    $msg = "<span class='text-danger'><b>Ditolak:</b> token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.</span>";
    $act = '';
  }
  if ($act==='add') {
    $tgl1 = _post('tgl1','');
    $tgl2 = _post('tgl2','');
    $tipe = _post('tipe','sekolah');
    if (!in_array($tipe, $TIPE_VALID, true)) $tipe = 'sekolah'; // cegah nilai di luar ENUM
    $ket  = _post('keterangan','');
    $ta_post = _post('ta_id','');

    // === Scope kelas MULTI-PILIH ===
    // scope_kelas_id[] = daftar kelas tercentang. "Semua Kelas" (scope_all=1) atau
    // tidak ada yang dipilih => NULL (berlaku untuk semua kelas / satu baris saja).
    $scope_all = (_post('scope_all','') === '1');
    $rawScopes = $_POST['scope_kelas_id'] ?? [];
    if (!is_array($rawScopes)) $rawScopes = ($rawScopes === '' ? [] : [$rawScopes]);
    // hanya terima kelas yang benar-benar milik TA terpilih (anti tamper)
    $validKelas = [];
    $rk = mysqli_query($koneksi, "SELECT kelas_id FROM kelas WHERE kelas_ta=".i($TA));
    while ($rk && $row = mysqli_fetch_row($rk)) { $validKelas[(int)$row[0]] = true; }
    $selKelas = [];
    foreach ($rawScopes as $v) { $v = (int)$v; if ($v > 0 && isset($validKelas[$v])) $selKelas[$v] = $v; }
    // Daftar scope yang ditulis: [null] = semua, atau satu entri per kelas terpilih
    $scopeList = ($scope_all || !$selKelas) ? [null] : array_values($selKelas);

    $err = [];
    if (!valid_ymd($tgl1)) $err[]='Tanggal mulai tidak valid';
    if ($tgl2!=='' && !valid_ymd($tgl2)) $err[]='Tanggal akhir tidak valid';
    if ($ket==='') $err[]='Keterangan wajib diisi';
    $tgl2 = $tgl2 ?: $tgl1;

    if (!$err) {
      // expand range
      $d = strtotime($tgl1); $e = strtotime($tgl2);
      if ($d>$e){ $tmp=$d; $d=$e; $e=$tmp; }
      $ta_val = ($ta_post==='')? null : i($ta_post);
      $ins=0; $skip=0; $hari=0;

      while($d <= $e){
        $tgl = date('Y-m-d',$d);
        $hari++;
        // satu baris per kombinasi (tanggal × kelas terpilih); dedup oleh unique index
        foreach ($scopeList as $scope_val) {
          if (mysqli_query($koneksi, "INSERT IGNORE INTO kalender_libur (ta_id,tgl,tipe,keterangan,scope_kelas_id,created_by) VALUES (".
              ($ta_val===null?'NULL':i($ta_val)).", '".
              mysqli_real_escape_string($koneksi,$tgl)."', '".
              mysqli_real_escape_string($koneksi,$tipe)."', '".
              mysqli_real_escape_string($koneksi,$ket)."', ".
              ($scope_val===null?'NULL':i($scope_val)).", ".
              i($user_id).")")) {
            if (mysqli_affected_rows($koneksi)>0) $ins++; else $skip++;
          } else {
            $skip++;
          }
        }
        $d = strtotime('+1 day',$d);
      }
      $scopeInfo = ($scopeList === [null]) ? 'semua kelas' : (count($scopeList).' kelas');
      $msg = "<span class='text-success'><b>Berhasil:</b> $ins baris ditambahkan ($hari hari × $scopeInfo)".($skip? ", $skip dilewati (duplikat)":"").".</span>";
    } else {
      $msg = "<span class='text-danger'>".escs(implode('; ',$err))."</span>";
    }
  }

  if ($act==='delete_bulk') {
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    if ($ids) {
      $idList = implode(',', $ids);
      mysqli_query($koneksi,"DELETE FROM kalender_libur WHERE id IN ($idList)");
      $msg = "<span class='text-success'>".count($ids)." item dihapus.</span>";
    } else {
      $msg = "<span class='text-warning'>Tidak ada data dipilih.</span>";
    }
  }

  if ($act==='generate_hari_efektif') {
    if ($TA<=0) {
      $msg = "<span class='text-danger'>Pilih Tahun Ajaran yang valid dulu sebelum generate.</span>";
    } elseif (!$HAS_VIEWNE || !$HAS_HARIEF) {
      $msg = "<span class='text-danger'>Tabel <code>hari_efektif</code>/<code>view_non_efektif</code> belum tersedia. Jalankan migrasi DB dulu.</span>";
    } else {
      // 1) kumpulkan semua weekdays sesuai hari_sekolah
      $dates = [];
      $d=strtotime($semStart); $e=strtotime($semEnd);
      while($d<=$e){
        $w=(int)date('N',$d); // 1..7
        $is_schoolday = ($hari_sekolah==6) ? ($w<=6) : ($w<=5);
        if($is_schoolday) $dates[] = date('Y-m-d',$d);
        $d=strtotime('+1 day',$d);
      }
      // 2) ambil non-efektif dari view (lintas TA atau TA ini)
      $non = [];
      $qne = mysqli_query($koneksi, "
        SELECT tgl
        FROM view_non_efektif
        WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$semStart)."' AND '".mysqli_real_escape_string($koneksi,$semEnd)."'
          AND (ta_id IS NULL OR ta_id=".i($TA).")
      ");
      while($qne && $r=mysqli_fetch_row($qne)){ $non[$r[0]]=true; }

      // 3) sisakan efektif
      $ef = [];
      foreach($dates as $dd){ if(empty($non[$dd])) $ef[]=$dd; }

      // 4) tulis ke hari_efektif dalam 1 TRANSAKSI: hapus rentang lama lalu insert.
      //    Bila gagal di tengah → rollback agar tidak ada state setengah jadi.
      $ok = true;
      mysqli_begin_transaction($koneksi);
      try {
        mysqli_query($koneksi,"DELETE FROM hari_efektif WHERE ta_id=".i($TA)." AND tanggal BETWEEN '".mysqli_real_escape_string($koneksi,$semStart)."' AND '".mysqli_real_escape_string($koneksi,$semEnd)."'");
        if ($ef) {
          $values = [];
          foreach($ef as $dd){ $values[]="(".i($TA).",'".mysqli_real_escape_string($koneksi,$dd)."',1)"; }
          foreach(array_chunk($values, 500) as $ch){
            if (!mysqli_query($koneksi,"INSERT INTO hari_efektif (ta_id,tanggal,is_efektif) VALUES ".implode(',',$ch))) {
              throw new \RuntimeException(mysqli_error($koneksi));
            }
          }
        }
        mysqli_commit($koneksi);
      } catch (\Throwable $ex) {
        $ok = false;
        @mysqli_rollback($koneksi);
      }
      $msg = $ok
        ? "<span class='text-success'>Regenerasi hari efektif selesai. Total efektif: <b>".count($ef)."</b>.</span>"
        : "<span class='text-danger'>Gagal regenerasi hari efektif (perubahan dibatalkan). Coba lagi.</span>";
    }
  }
}

// === Ambil daftar kelas utk scope, kelompokkan per tingkat (angka di awal nama: "7A" -> 7)
$kelas = mysqli_query($koneksi,"SELECT kelas_id, kelas_nama FROM kelas WHERE kelas_ta=".i($TA)." ORDER BY kelas_nama");
$kelasByTingkat = [];
while ($kelas && $k = mysqli_fetch_assoc($kelas)) {
  $nm = (string)$k['kelas_nama'];
  $tk = preg_match('/^\s*(\d+)/', $nm, $mm) ? $mm[1] : 'Lainnya';
  $kelasByTingkat[$tk][] = ['id'=>(int)$k['kelas_id'], 'nama'=>$nm];
}
ksort($kelasByTingkat, SORT_NATURAL);

// === List libur (filter kecil)
$fil_tipe = _get('ft','');
$fil_awal = _get('awal',$semStart);
$fil_akhir= _get('akhir',$semEnd);
$where = "WHERE kl.tgl BETWEEN '".mysqli_real_escape_string($koneksi,$fil_awal)."' AND '".mysqli_real_escape_string($koneksi,$fil_akhir)."' AND (kl.ta_id IS NULL OR kl.ta_id=".i($TA).")";
if ($fil_tipe!=='') $where .= " AND kl.tipe='".mysqli_real_escape_string($koneksi,$fil_tipe)."'";
// JOIN kelas sekali saja (hindari N+1 query nama kelas per baris)
$qList = $HAS_KALENDER ? mysqli_query($koneksi,"
  SELECT kl.id, kl.ta_id, kl.tgl, kl.tipe, kl.keterangan, kl.scope_kelas_id, k.kelas_nama
  FROM kalender_libur kl
  LEFT JOIN kelas k ON k.kelas_id = kl.scope_kelas_id
  $where
  ORDER BY kl.tgl ASC, kl.tipe") : false;

// === Ringkasan non-efektif per tipe (hanya weekdays sesuai 5/6 hari, exclude Sabtu/Minggu)
$sum = ['nasional'=>0,'sekolah'=>0,'kegiatan'=>0,'cuti_bersama'=>0,'lain'=>0];
$qAll = $HAS_VIEWNE ? mysqli_query($koneksi,"
  SELECT tgl, tipe
  FROM view_non_efektif
  WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$semStart)."' AND '".mysqli_real_escape_string($koneksi,$semEnd)."'
    AND (ta_id IS NULL OR ta_id=".i($TA).")
  ORDER BY tgl
") : false;
while($qAll && $r=mysqli_fetch_assoc($qAll)){
  $w=(int)date('N', strtotime($r['tgl']));
  $schoolday = ($hari_sekolah==6) ? ($w<=6) : ($w<=5);
  if ($schoolday) $sum[$r['tipe']] = ($sum[$r['tipe']]??0) + 1;
}
$total_non_ef = array_sum($sum);

// === UI
include 'header.php';
?>
<style>
/* ===== Tema Biru Elegan (konsisten halaman lain) ===== */
:root{
  --blue-50:#f0f6ff; --blue-100:#e6efff; --blue-200:#d7e6ff; --blue-300:#bfd7ff;
  --blue-400:#93b7ff; --blue-500:#4f9cf9; --blue-600:#2d6cdf; --blue-700:#1f5ac8;
  --ink-900:#0b1220; --ink-800:#1e293b; --ink-700:#334155; --line:#dbe5ff;
  --card:#ffffff; --bg:#f8fbff;
  --shadow:0 10px 30px rgba(45,108,223,.12);
  --radius:16px;
  --grad:linear-gradient(90deg,var(--blue-600),var(--blue-500));
  --grad-soft:linear-gradient(135deg,#eef2ff,#e0f2fe);
}

/* Halaman */
.content-wrapper{ background:var(--bg); }

/* Judul Halaman (ikon + badge) */
.page-title{
  display:flex; align-items:center; gap:12px; margin:0 0 6px;
  color:var(--ink-900); font-weight:800; letter-spacing:.2px;
}
.title-icon{
  width:42px;height:42px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;
  background:var(--blue-100); color:var(--blue-700); /* ikon lebih biru */
  box-shadow:inset 0 0 0 1px var(--line);
}
.title-icon i{ color:var(--blue-700); filter: drop-shadow(0 2px 4px rgba(31,90,200,.25)); }
.title-badge{
  display:inline-flex; align-items:center; gap:6px;
  background:var(--blue-50); color:var(--ink-700); border:1px solid var(--line);
  border-radius:999px; padding:4px 10px; font-weight:700; font-size:12px;
}

/* Box */
.box{ border:0; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; background:var(--card); }
.box-header{ background:var(--grad-soft); border-bottom:1px solid var(--line); }
.box-header .box-title{ display:flex; align-items:center; gap:8px; font-weight:800; color:var(--ink-900); }
.box-header .box-title i{ color:var(--blue-600); }

/* Tombol */
.btn-primary{ background:var(--grad); border:0; border-radius:12px; }
.btn-primary:hover{ filter:brightness(1.06); }
.btn-success{ border-radius:12px; }
.btn-danger{ border-radius:12px; }
.btn-default{ border-radius:12px; }

/* Form */
.form-control{ border-radius:12px; border:1px solid var(--line); box-shadow:none; }
.form-control:focus{ border-color:var(--blue-500); box-shadow:0 0 0 3px rgba(79,156,249,.15); }

/* Tabel */
.table{ background:#fff; }
.table>thead>tr>th{ background:linear-gradient(180deg,#f7faff 0%,#f1f6ff 100%); border-bottom:1px solid var(--line)!important; color:var(--ink-800); }
.table>tbody>tr:hover{ background:#f8fbff; }

/* Badge tipe */
.badge{ border-radius:9999px; padding:4px 10px; font-weight:700; border:1px solid transparent; }
.badge-nasional{ background:#fee2e2; color:#991b1b; border-color:#fecaca; }
.badge-sekolah{ background:#e0f2fe; color:#0c4a6e; border-color:#bae6fd; }
.badge-kegiatan{ background:#ecfccb; color:#3f6212; border-color:#d9f99d; }
.badge-cuti_bersama{ background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
.badge-lain{ background:#ede9fe; color:#4c1d95; border-color:#ddd6fe; }

/* Panel Scope Kelas (multi-pilih) */
.scope-panel{
  display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start;
  border:1px solid var(--line); border-radius:12px; padding:10px 12px; background:#fbfdff;
}
.scope-panel label{ font-weight:600; margin:0; cursor:pointer; }
.scope-panel input[type=checkbox]{ margin-right:5px; vertical-align:middle; }
.scope-all{
  display:inline-flex; align-items:center; padding:6px 10px; border-radius:10px;
  background:#eef6ff; border:1px solid var(--blue-200); color:var(--blue-700); white-space:nowrap;
}
.scope-group{
  border:1px dashed var(--line); border-radius:10px; padding:6px 10px; min-width:150px;
}
.scope-grp{ display:block; color:var(--blue-700); border-bottom:1px dashed var(--line); padding-bottom:4px; margin-bottom:6px; }
.scope-items{ display:flex; flex-wrap:wrap; gap:6px 12px; }
.scope-item{ font-weight:500!important; color:var(--ink-700); white-space:nowrap; }
.scope-item:hover{ color:var(--blue-700); }

/* DataTables polish kecil */
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input{
  border:1px solid var(--line)!important; border-radius:10px; padding:6px 10px; outline:none;
}
.dataTables_wrapper .dataTables_paginate .paginate_button{
  border-radius:10px!important; border:0!important; background:#fff!important; color:var(--ink-800)!important;
  box-shadow:inset 0 0 0 1px var(--line); margin:0 2px!important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current{
  background:var(--blue-600)!important; color:#fff!important; box-shadow:none;
}
.dataTables_wrapper .dataTables_filter label{ font-weight:700; color:var(--ink-700); }

/* Alert rapih */
.alert{ border-radius:12px; }

/* Responsif kecil */
@media (max-width:576px){
  .title-badge{ display:none; }
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <!-- Judul: pertahankan teks, ikon kalender dibuat lebih biru -->
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-calendar"></i></span>
      <span>Kalender Akademik</span>
      <span class="title-badge"><i class="fa fa-sliders"></i> / Kelola Libur &amp; Kegiatan</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Kalender Akademik</li>
    </ol>

    <?php if($msg): ?>
      <div class="alert alert-info" style="margin-top:8px;"><?= $msg ?></div>
    <?php endif; ?>
  </section>

  <section class="content">
    <!-- Filter TA + semester + hari sekolah -->
    <div class="box">
      <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-sliders"></i> Parameter</h3></div>
      <div class="box-body">
        <form class="form-inline" method="get" action="kalender_akademik.php">
          <div class="row">
            <div class="col-sm-3">
              <label>Tahun Ajaran</label>
              <select name="ta" class="form-control" style="width:100%">
                <?php mysqli_data_seek($tas,0); while($t=mysqli_fetch_assoc($tas)): ?>
                  <option value="<?=i($t['ta_id'])?>" <?=$TA==i($t['ta_id'])?'selected':'';?>><?=escs($t['ta_nama'])?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label>Semester</label>
              <select name="semester" class="form-control" style="width:100%">
                <option value="1" <?=$semester==1?'selected':'';?>>Semester 1 (Jul–Des)</option>
                <option value="2" <?=$semester==2?'selected':'';?>>Semester 2 (Jan–Jun)</option>
              </select>
            </div>
            <div class="col-sm-3">
              <label>Hari Sekolah</label>
              <select name="hari_sekolah" class="form-control" style="width:100%">
                <option value="5" <?=$hari_sekolah==5?'selected':'';?>>5 hari (Sen–Jum)</option>
                <option value="6" <?=$hari_sekolah==6?'selected':'';?>>6 hari (Sen–Sab)</option>
              </select>
            </div>
            <div class="col-sm-3">
              <label>&nbsp;</label><br>
              <button class="btn btn-primary" type="submit"><i class="fa fa-refresh"></i> Terapkan</button>
              <a class="btn btn-default" href="kalender_akademik.php"><i class="fa fa-undo"></i> Reset</a>
            </div>
          </div>
        </form>
        <div style="margin-top:8px; color:var(--ink-700);">
          Semester aktif: <b><?=escs(epoin_semester_label($semester,'short'))?></b>
          <?php if($semester_is_auto): ?><span class="title-badge" style="background:#ecfdf5;color:#065f46;border-color:#a7f3d0;"><i class="fa fa-magic"></i> otomatis (bulan ini)</span><?php endif; ?>
          &nbsp;·&nbsp; Rentang: <b><?=escs(fmt_dmy($semStart))?></b> s/d <b><?=escs(fmt_dmy($semEnd))?></b>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Kolom kiri: form tambah + daftar -->
      <div class="col-sm-8">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-plus-circle"></i> Tambah Libur/Kegiatan</h3></div>
          <div class="box-body">
            <form method="post">
              <input type="hidden" name="act" value="add">
              <?= epoin_csrf_field() ?>
              <div class="row">
                <div class="col-sm-3">
                  <label>Tgl Mulai</label>
                  <input type="date" name="tgl1" class="form-control" required>
                </div>
                <div class="col-sm-3">
                  <label>Tgl Akhir (opsional)</label>
                  <input type="date" name="tgl2" class="form-control">
                  <small class="text-muted">Kosongkan jika 1 hari</small>
                </div>
                <div class="col-sm-3">
                  <label>Tipe</label>
                  <select name="tipe" class="form-control">
                    <option value="sekolah">Sekolah</option>
                    <option value="kegiatan">Kegiatan</option>
                    <option value="cuti_bersama">Cuti Bersama</option>
                    <option value="lain">Lain</option>
                    <option value="nasional">Nasional</option>
                  </select>
                </div>
                <div class="col-sm-3">
                  <label>Berlaku untuk TA</label>
                  <select name="ta_id" class="form-control">
                    <option value="">Semua TA</option>
                    <?php mysqli_data_seek($tas,0); while($t=mysqli_fetch_assoc($tas)): ?>
                      <option value="<?=i($t['ta_id'])?>" <?=$TA==i($t['ta_id'])?'selected':'';?>><?=escs($t['ta_nama'])?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-sm-6" style="margin-top:8px;">
                  <label>Keterangan <span class="text-danger">*</span></label>
                  <input type="text" name="keterangan" class="form-control" placeholder="mis. Maulid Nabi / Class Meeting" required>
                </div>
                <div class="col-sm-6" style="margin-top:8px;">
                  <label>&nbsp;</label><br>
                  <button class="btn btn-success" type="submit"><i class="fa fa-save"></i> Simpan</button>
                  <button class="btn btn-default" type="button" onclick="presetRange(3)"><i class="fa fa-magic"></i> 3 Hari</button>
                  <button class="btn btn-default" type="button" onclick="presetRange(5)"><i class="fa fa-magic"></i> 5 Hari</button>
                </div>
                <div class="col-sm-12" style="margin-top:10px;">
                  <label>Scope Kelas (opsional) — pilih satu/lebih kelas, atau biarkan <b>Semua Kelas</b></label>
                  <div class="scope-panel">
                    <label class="scope-all"><input type="checkbox" name="scope_all" value="1" id="scopeAll" checked onclick="onScopeAll(this)"> <b>Semua Kelas</b></label>
                    <?php foreach($kelasByTingkat as $tk=>$list): ?>
                      <div class="scope-group">
                        <label class="scope-grp"><input type="checkbox" class="grp" data-tk="<?=escs($tk)?>" onclick="onGroupToggle(this)"> Tingkat <?=escs($tk)?></label>
                        <div class="scope-items">
                          <?php foreach($list as $kk): ?>
                            <label class="scope-item"><input type="checkbox" class="kelas-cb tk-<?=escs($tk)?>" name="scope_kelas_id[]" value="<?=i($kk['id'])?>" onclick="onKelasCb()"> <?=escs($kk['nama'])?></label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <small class="text-muted">Mis. centang <b>Tingkat 7</b> → aturan berlaku untuk 7A–7E saja. Tiap kelas tersimpan sebagai baris terpisah.</small>
                </div>
              </div>
            </form>
          </div>
        </div>

        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-list"></i> Daftar Libur/Kegiatan</h3>
            <div class="box-tools">
              <form class="form-inline" method="get" action="kalender_akademik.php" style="display:inline-flex; gap:6px;">
                <input type="hidden" name="ta" value="<?=$TA?>">
                <input type="hidden" name="semester" value="<?=$semester?>">
                <input type="hidden" name="hari_sekolah" value="<?=$hari_sekolah?>">
                <input type="date" name="awal" value="<?=escs($fil_awal)?>" class="form-control">
                <input type="date" name="akhir" value="<?=escs($fil_akhir)?>" class="form-control">
                <select name="ft" class="form-control">
                  <option value="">Semua tipe</option>
                  <option value="nasional" <?=$fil_tipe==='nasional'?'selected':'';?>>Nasional</option>
                  <option value="sekolah" <?=$fil_tipe==='sekolah'?'selected':'';?>>Sekolah</option>
                  <option value="kegiatan" <?=$fil_tipe==='kegiatan'?'selected':'';?>>Kegiatan</option>
                  <option value="cuti_bersama" <?=$fil_tipe==='cuti_bersama'?'selected':'';?>>Cuti Bersama</option>
                  <option value="lain" <?=$fil_tipe==='lain'?'selected':'';?>>Lain</option>
                </select>
                <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
              </form>
            </div>
          </div>
          <div class="box-body">
            <form method="post" onsubmit="return confirm('Hapus data terpilih?');">
              <input type="hidden" name="act" value="delete_bulk">
              <?= epoin_csrf_field() ?>
              <div class="table-responsive">
                <!-- Tambah ID untuk DataTables -->
                <table class="table table-bordered table-striped" id="table-libur">
                  <thead>
                    <tr>
                      <th style="width:36px;"><input type="checkbox" onclick="toggleAll(this)"></th>
                      <th>Tanggal</th>
                      <th>Tipe</th>
                      <th>Keterangan</th>
                      <th>Scope</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if(!$qList || mysqli_num_rows($qList)==0): ?>
                      <tr><td colspan="5"><em>Belum ada data</em></td></tr>
                    <?php else: while($r=mysqli_fetch_assoc($qList)): ?>
                      <tr>
                        <td><input type="checkbox" name="ids[]" value="<?=i($r['id'])?>"></td>
                        <td><?=escs(fmt_dmy($r['tgl']))?></td>
                        <td>
                          <span class="badge badge-<?=escs($r['tipe'])?>"><?=escs($r['tipe'])?></span>
                        </td>
                        <td><?=escs($r['keterangan'])?></td>
                        <td>
                          <?php
                            // nama kelas sudah di-JOIN di $qList (tanpa query per baris)
                            if (is_null($r['scope_kelas_id'])) echo '<span class="text-muted">Semua</span>';
                            else echo escs($r['kelas_nama'] ?? ('KID '.i($r['scope_kelas_id'])));
                          ?>
                        </td>
                      </tr>
                    <?php endwhile; endif; ?>
                  </tbody>
                </table>
              </div>
              <button class="btn btn-danger" type="submit"><i class="fa fa-trash"></i> Hapus Terpilih</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Kolom kanan: ringkasan -->
      <div class="col-sm-4">
        <div class="box">
          <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-pie-chart"></i> Ringkasan Semester</h3></div>
          <div class="box-body">
            <div style="margin-bottom:8px;">
              <div><b>TA:</b> <?=escs($ta_nama)?> | <b>Semester:</b> <?=escs(epoin_semester_label($semester,'short'))?> (<?=escs(epoin_semester_label($semester,'range'))?>)</div>
              <div><b>Rentang:</b> <?=escs(fmt_dmy($semStart))?> – <?=escs(fmt_dmy($semEnd))?></div>
              <div><b>Hari Sekolah:</b> <?=$hari_sekolah?> hari</div>
            </div>
            <ul style="padding-left:18px; line-height:1.7">
              <li><b>Total hari non-efektif (schooldays):</b> <?=$total_non_ef?></li>
              <li>Nasional: <?=$sum['nasional']?></li>
              <li>Sekolah: <?=$sum['sekolah']?></li>
              <li>Kegiatan: <?=$sum['kegiatan']?></li>
              <li>Cuti Bersama: <?=$sum['cuti_bersama']?></li>
              <li>Lain: <?=$sum['lain']?></li>
            </ul>
            <form method="post" onsubmit="return confirm('Generate ulang hari_efektif untuk TA/semester ini? Data sebelumnya di rentang ini akan dihapus. Lanjutkan?')">
              <input type="hidden" name="act" value="generate_hari_efektif">
              <?= epoin_csrf_field() ?>
              <button class="btn btn-primary btn-block" type="submit"><i class="fa fa-cog"></i> Generate Hari Efektif</button>
            </form>
            <p class="text-muted" style="margin-top:8px;">Membuat cache <code>hari_efektif</code> berdasarkan weekdays (<?=$hari_sekolah?> hari) dikurangi semua libur pada <code>view_non_efektif</code>.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
// Toggle checkbox semua (onclick handler — harus tersedia sebelum DOM dirender)
function toggleAll(cb){
  document.querySelectorAll('input[name="ids[]"]').forEach(x=>x.checked=cb.checked);
}
// Preset range n hari (onclick handler)
function presetRange(n){
  var s = document.querySelector('input[name="tgl1"]').value;
  if(!s){ alert('Isi Tgl Mulai dulu.'); return; }
  var d = new Date(s);
  d.setDate(d.getDate() + (n-1));
  var yyyy=d.getFullYear(), mm=('0'+(d.getMonth()+1)).slice(-2), dd=('0'+d.getDate()).slice(-2);
  document.querySelector('input[name="tgl2"]').value = yyyy+'-'+mm+'-'+dd;
}

// ===== Scope Kelas multi-pilih =====
function _scopeUncheckAll(){ var a=document.getElementById('scopeAll'); if(a) a.checked=false; }
// "Semua Kelas" dicentang => kosongkan semua centang kelas & toggle tingkat
function onScopeAll(cb){
  if(cb.checked){
    document.querySelectorAll('.kelas-cb, .grp').forEach(function(x){ x.checked=false; x.indeterminate=false; });
  }
}
// Toggle satu tingkat => centang/lepas semua kelas di tingkat itu
function onGroupToggle(g){
  _scopeUncheckAll();
  var tk = g.getAttribute('data-tk');
  document.querySelectorAll('.kelas-cb.tk-'+tk).forEach(function(x){ x.checked = g.checked; });
  g.indeterminate = false;
}
// Centang kelas individu => sinkronkan status toggle tingkat & lepas "Semua Kelas"
function onKelasCb(){
  _scopeUncheckAll();
  document.querySelectorAll('.grp').forEach(function(g){
    var tk = g.getAttribute('data-tk');
    var items = document.querySelectorAll('.kelas-cb.tk-'+tk);
    var on = 0; items.forEach(function(x){ if(x.checked) on++; });
    g.checked = (items.length>0 && on===items.length);
    g.indeterminate = (on>0 && on<items.length);
  });
}
</script>

<?php include 'footer.php'; ?>

<script>
// DataTables untuk Daftar Libur/Kegiatan — init setelah footer.php (DT 1.13.4)
$(function(){
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  var $tbl = $('#table-libur');
  if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
    try { $tbl.DataTable().destroy(); } catch(e){}
    $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
  }
  if ($.fn.DataTable) {
    $tbl.DataTable({
      destroy: true,
      autoWidth: false,
      pageLength: 10,
      lengthMenu: [[10,25,50,-1],[10,25,50,'Semua']],
      order: [[1,'asc'],[2,'asc']], // Tanggal, lalu Tipe
      columnDefs: [{ targets:[0], orderable:false }],
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampil _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        zeroRecords: 'Tidak ditemukan data yang cocok',
        infoFiltered: '(difilter dari total _MAX_ data)',
        paginate: { first:'Pertama', last:'Terakhir', next:'Berikutnya', previous:'Sebelumnya' }
      }
    });
  }
});
</script>
