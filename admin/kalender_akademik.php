<?php
// admin/kalender_akademik.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

function escs($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function i($v){ return (int)$v; }
function _get($k,$d=null){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function _post($k,$d=null){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function valid_ymd($s){ return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function fmt_dmy($ymd){ if(!$ymd) return '-'; $ts=strtotime($ymd); return $ts?date('d/m/Y',$ts):escs($ymd); }
function table_exists($k,$n){ $r=@mysqli_query($k,"SHOW TABLES LIKE '".mysqli_real_escape_string($k,$n)."'"); return $r && mysqli_num_rows($r)>0; }

$today = date('Y-m-d');

// === Ambil TA aktif + daftar TA
$ta_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1"));
$TA = isset($_GET['ta']) ? i($_GET['ta']) : i($ta_aktif['ta_id'] ?? 0);
$tas = mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");

// Semester & rentang patokan
$semester = isset($_GET['semester']) ? i($_GET['semester']) : 1;
$ta_row = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT ta_nama FROM ta WHERE ta_id=$TA"));
$ta_nama = trim($ta_row['ta_nama'] ?? '');
preg_match('/(\d{4})\D+(\d{4})/',$ta_nama,$m);
$y1 = isset($m[1]) ? (int)$m[1] : (int)date('Y');
$y2 = isset($m[2]) ? (int)$m[2] : $y1+1;
if($semester==2){ $semStart="$y2-01-01"; $semEnd="$y2-06-30"; } else { $semStart="$y1-07-01"; $semEnd="$y1-12-31"; }
$hari_sekolah = (_get('hari_sekolah','5') === '6') ? 6 : 5;

// === Aksi: tambah libur (single / range)
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = _post('act','');
  if ($act==='add') {
    $tgl1 = _post('tgl1','');
    $tgl2 = _post('tgl2','');
    $tipe = _post('tipe','sekolah');
    $ket  = _post('keterangan','');
    $ta_post = _post('ta_id','');
    $scope_kelas_id = _post('scope_kelas_id','');

    $err = [];
    if (!valid_ymd($tgl1)) $err[]='Tanggal mulai tidak valid';
    if ($tgl2!=='' && !valid_ymd($tgl2)) $err[]='Tanggal akhir tidak valid';
    if ($ket==='') $err[]='Keterangan wajib diisi';
    $tgl2 = $tgl2 ?: $tgl1;

    if (!$err) {
      // expand range
      $d = strtotime($tgl1); $e = strtotime($tgl2);
      if ($d>$e){ $tmp=$d; $d=$e; $e=$tmp; }
      $ins=0; $skip=0;

      while($d <= $e){
        $tgl = date('Y-m-d',$d);
        // validasi tumpang tindih (cek sudah ada baris identik pada tanggal & scope & TA)
        $ta_val = ($ta_post==='')? null : i($ta_post);
        $scope_val = ($scope_kelas_id==='')? null : i($scope_kelas_id);

        // gunakan INSERT IGNORE bila pakai unique index; jika tidak ada, lakukan cek manual
        if (mysqli_query($koneksi, "INSERT IGNORE INTO kalender_libur (ta_id,tgl,tipe,keterangan,scope_kelas_id) VALUES (".
            ($ta_val===null?'NULL':i($ta_val)).", '".
            mysqli_real_escape_string($koneksi,$tgl)."', '".
            mysqli_real_escape_string($koneksi,$tipe)."', '".
            mysqli_real_escape_string($koneksi,$ket)."', ".
            ($scope_val===null?'NULL':i($scope_val)).")")) {
          if (mysqli_affected_rows($koneksi)>0) $ins++; else $skip++;
        } else {
          $skip++;
        }
        $d = strtotime('+1 day',$d);
      }
      $msg = "<span class='text-success'><b>Berhasil:</b> $ins ditambahkan".($skip? ", $skip dilewati (duplikat)":"").".</span>";
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
    // generate cache hari_efektif untuk TA & semester
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
        AND (ta_id IS NULL OR ta_id=$TA)
    ");
    while($r=mysqli_fetch_row($qne)){ $non[$r[0]]=true; }

    // 3) sisakan efektif
    $ef = [];
    foreach($dates as $d){ if(empty($non[$d])) $ef[]=$d; }

    // 4) tulis ke hari_efektif: hapus rentang lama TA, kemudian insert
    mysqli_query($koneksi,"DELETE FROM hari_efektif WHERE ta_id=$TA AND tanggal BETWEEN '$semStart' AND '$semEnd'");
    if ($ef) {
      $values = [];
      foreach($ef as $d){ $values[]="($TA,'".mysqli_real_escape_string($koneksi,$d)."',1)"; }
      $chunks=array_chunk($values, 500);
      foreach($chunks as $ch){
        mysqli_query($koneksi,"INSERT INTO hari_efektif (ta_id,tanggal,is_efektif) VALUES ".implode(',',$ch));
      }
    }
    $msg = "<span class='text-success'>Regenerasi hari efektif selesai. Total efektif: <b>".count($ef)."</b>.</span>";
  }
}

// === Ambil daftar kelas utk scope
$kelas = mysqli_query($koneksi,"SELECT kelas_id, kelas_nama FROM kelas WHERE kelas_ta=$TA ORDER BY kelas_nama");

// === List libur (filter kecil)
$fil_tipe = _get('ft','');
$fil_awal = _get('awal',$semStart);
$fil_akhir= _get('akhir',$semEnd);
$where = "WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$fil_awal)."' AND '".mysqli_real_escape_string($koneksi,$fil_akhir)."' AND (ta_id IS NULL OR ta_id=$TA)";
if ($fil_tipe!=='') $where .= " AND tipe='".mysqli_real_escape_string($koneksi,$fil_tipe)."'";
$qList = mysqli_query($koneksi,"SELECT id, ta_id, tgl, tipe, keterangan, scope_kelas_id FROM kalender_libur $where ORDER BY tgl ASC, tipe");

// === Ringkasan non-efektif per tipe (hanya weekdays sesuai 5/6 hari, exclude Sabtu/Minggu)
$sum = [];
$qSum = mysqli_query($koneksi,"
  SELECT tipe, COUNT(*) AS cnt FROM (
    SELECT tgl, tipe FROM view_non_efektif
    WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$semStart)."' AND '".mysqli_real_escape_string($koneksi,$semEnd)."'
      AND (ta_id IS NULL OR ta_id=$TA)
  ) x
");
# kita hitung manual agar bisa exclude weekend
$sum = ['nasional'=>0,'sekolah'=>0,'kegiatan'=>0,'cuti_bersama'=>0,'lain'=>0];
$qAll = mysqli_query($koneksi,"
  SELECT tgl, tipe
  FROM view_non_efektif
  WHERE tgl BETWEEN '".mysqli_real_escape_string($koneksi,$semStart)."' AND '".mysqli_real_escape_string($koneksi,$semEnd)."'
    AND (ta_id IS NULL OR ta_id=$TA)
  ORDER BY tgl
");
while($r=mysqli_fetch_assoc($qAll)){
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
          Rentang semester: <b><?=escs(fmt_dmy($semStart))?></b> s/d <b><?=escs(fmt_dmy($semEnd))?></b>
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
                <div class="col-sm-3" style="margin-top:8px;">
                  <label>Scope Kelas (opsional)</label>
                  <select name="scope_kelas_id" class="form-control">
                    <option value="">Semua Kelas</option>
                    <?php mysqli_data_seek($kelas,0); while($k=mysqli_fetch_assoc($kelas)): ?>
                      <option value="<?=i($k['kelas_id'])?>"><?=escs($k['kelas_nama'])?></option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-sm-3" style="margin-top:8px;">
                  <label>&nbsp;</label><br>
                  <button class="btn btn-success" type="submit"><i class="fa fa-save"></i> Simpan</button>
                  <button class="btn btn-default" type="button" onclick="presetRange(3)"><i class="fa fa-magic"></i> 3 Hari</button>
                  <button class="btn btn-default" type="button" onclick="presetRange(5)"><i class="fa fa-magic"></i> 5 Hari</button>
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
                    <?php if(mysqli_num_rows($qList)==0): ?>
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
                            if (is_null($r['scope_kelas_id'])) echo '<span class="text-muted">Semua</span>';
                            else {
                              $kid=i($r['scope_kelas_id']);
                              $kn=mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT kelas_nama FROM kelas WHERE kelas_id=$kid"));
                              echo escs($kn['kelas_nama']??('KID '.$kid));
                            }
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
              <div><b>TA:</b> <?=escs($ta_nama)?> | <b>Semester:</b> <?=$semester==1?'Jul–Des':'Jan–Jun'?></div>
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
// Toggle checkbox semua
function toggleAll(cb){
  document.querySelectorAll('input[name="ids[]"]').forEach(x=>x.checked=cb.checked);
}
// Preset range n hari
function presetRange(n){
  const s = document.querySelector('input[name="tgl1"]').value;
  if(!s){ alert('Isi Tgl Mulai dulu.'); return; }
  const d = new Date(s);
  d.setDate(d.getDate() + (n-1));
  const yyyy=d.getFullYear(), mm=('0'+(d.getMonth()+1)).slice(-2), dd=('0'+d.getDate()).slice(-2);
  document.querySelector('input[name="tgl2"]').value = `${yyyy}-${mm}-${dd}`;
}

// ====== DataTables untuk Daftar Libur/Kegiatan (pagination default 10) ======
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
      lengthMenu: [[10,25,50,-1],[10,25,50,"Semua"]],
      order: [[1,'asc'],[2,'asc']], // Tanggal, lalu Tipe
      columnDefs: [{ targets:[0], orderable:false }],
      language: {
        search: "Cari:",
        lengthMenu: "Tampil _MENU_ data",
        info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
        infoEmpty: "Tidak ada data",
        zeroRecords: "Tidak ditemukan data yang cocok",
        infoFiltered: "(difilter dari total _MAX_ data)",
        paginate: { first:"Pertama", last:"Terakhir", next:"Berikutnya", previous:"Sebelumnya" }
      }
    });
  }
});
</script>
<?php include 'footer.php'; ?>
