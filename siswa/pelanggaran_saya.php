<?php include 'header.php'; ?>

<?php
// ---------- Helper ----------
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ---------- Identitas siswa ----------
$id_siswa = (int)($_SESSION['id'] ?? 0);

// Siswa + jurusan (ditampilkan sebagai "Tingkat Kelas")
$qs = mysqli_query($koneksi,"
  SELECT s.*, j.jurusan_nama
  FROM siswa s
  LEFT JOIN jurusan j ON j.jurusan_id = s.siswa_jurusan
  WHERE s.siswa_id = {$id_siswa} LIMIT 1
");
$profil = mysqli_fetch_assoc($qs) ?: [];

// Kelas terakhir siswa (badge info). TAMPILKAN NAMA KELAS SAJA (tanpa ID).
$qk = mysqli_query($koneksi,"
  SELECT k.kelas_id, k.kelas_nama
  FROM kelas_siswa ks
  JOIN kelas k ON k.kelas_id = ks.ks_kelas
  WHERE ks.ks_siswa = {$id_siswa}
  ORDER BY ks.ks_id DESC LIMIT 1
");
$kelas_terakhir = mysqli_fetch_assoc($qk);

// ---------- Opsi dropdown TA (saja) ----------
$optTA = mysqli_query($koneksi,"SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC");

// ---------- Filter GET ----------
$defaultStart = date('Y-m-01');
$defaultEnd   = date('Y-m-t');

$fil_ta = isset($_GET['ta']) ? (int)$_GET['ta'] : 0; // 0 = semua

$start = $_GET['start'] ?? $defaultStart;
$end   = $_GET['end']   ?? $defaultEnd;

// Validasi tanggal (YYYY-MM-DD)
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)) $start = $defaultStart;
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))   $end   = $defaultEnd;

$startEsc = mysqli_real_escape_string($koneksi, $start.' 00:00:00');
$endEsc   = mysqli_real_escape_string($koneksi, $end.' 23:59:59');

// ---------- Data ringkas (kartu) ----------
$qTotal = mysqli_query($koneksi,"
  SELECT COALESCE(SUM(pl.pelanggaran_point),0) tot
  FROM input_pelanggaran ig
  JOIN pelanggaran pl ON pl.pelanggaran_id = ig.pelanggaran
  WHERE ig.siswa = {$id_siswa}
");
$total_pelanggaran = (int)mysqli_fetch_assoc($qTotal)['tot'];

// Terakhir pelanggaran (waktu + nama)
$qLast = mysqli_query($koneksi,"
  SELECT ig.waktu, pl.pelanggaran_nama
  FROM input_pelanggaran ig
  JOIN pelanggaran pl ON pl.pelanggaran_id = ig.pelanggaran
  WHERE ig.siswa = {$id_siswa}
  ORDER BY ig.waktu DESC LIMIT 1
");
$terakhir = mysqli_fetch_assoc($qLast);

// ---------- Query tabel (dengan filter) ----------
$where = "ig.siswa = {$id_siswa} AND ig.waktu BETWEEN '{$startEsc}' AND '{$endEsc}'";
if($fil_ta > 0) $where .= " AND k.kelas_ta = {$fil_ta}";

$sqlTabel = "
  SELECT ig.waktu, k.kelas_nama, ta.ta_nama,
         pl.pelanggaran_nama, pl.pelanggaran_point
  FROM input_pelanggaran ig
  JOIN pelanggaran pl ON pl.pelanggaran_id = ig.pelanggaran
  JOIN kelas k ON k.kelas_id = ig.kelas
  JOIN ta ON ta.ta_id = k.kelas_ta
  WHERE {$where}
  ORDER BY ig.waktu DESC
";
$rows = [];
$qt = mysqli_query($koneksi, $sqlTabel);
while($r = mysqli_fetch_assoc($qt)) $rows[] = $r;

// Hitung total pada hasil filter (untuk footer & KPI jumlah entri)
$total_filter = 0;
foreach($rows as $r) $total_filter += (int)$r['pelanggaran_point'];
$jumlah_entri = count($rows);
?>

<style>
  /* ========= THEME ========= */
  .soft-card{border-radius:14px; box-shadow:0 8px 20px rgba(0,0,0,.06); overflow:hidden;}
  .soft-body{padding:18px;}
  .muted{color:#6b7280}
  .chip{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:6px 10px;font-weight:700;font-size:12px}
  .chip-blue{background:#e0e7ff;color:#1d4ed8}
  .chip-amber{background:#fff7ed;color:#b45309}

  /* Header */
  .page-title{font-weight:800;margin:0}
  .breadcrumb{margin:6px 0 0}

  /* KPI cards */
  .kpi-row{display:flex;gap:12px;flex-wrap:wrap}
  .kpi{flex:1 1 280px; min-height:110px; padding:16px 16px 14px; color:#fff; position:relative;
       border-radius:16px; box-shadow:0 10px 24px rgba(0,0,0,.08); overflow:hidden}
  .kpi .icon-bg{position:absolute; right:14px; bottom:8px; font-size:38px; opacity:.18}
  .kpi-title{font-size:12px; letter-spacing:.5px; text-transform:uppercase; opacity:.95}
  .kpi-value{font-size:38px; font-weight:800; line-height:1.05}
  .kpi-sub{margin-top:2px; font-size:13px; opacity:.95}
  .kpi-red{background:linear-gradient(135deg,#f87171,#dc2626)}
  .kpi-green{background:linear-gradient(135deg,#34d399,#059669)}
  .kpi-amber{background:linear-gradient(135deg,#fbbf24,#d97706)}

  /* Identitas */
  .id-wrap{display:flex; align-items:center; gap:12px; flex-wrap:wrap}
  .avatar{width:40px;height:40px;border-radius:999px;background:#fee2e2;display:flex;align-items:center;justify-content:center;color:#b91c1c;font-weight:800}

  /* Filter form */
  .filter-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:10px}
  .filter-grid .col-6{grid-column:span 6}
  .filter-grid .col-3{grid-column:span 3}
  .filter-grid .col-12{grid-column:span 12}
  @media(max-width:768px){
    .filter-grid .col-6, .filter-grid .col-3{grid-column:span 12}
  }
  .btn-pill{border-radius:999px}
  .btn-danger-soft{background:#fee2e2;color:#b91c1c;border-color:#fecaca}
  .btn-danger-soft:hover{background:#fecaca}
  .btn-gray-soft{background:#f3f4f6;color:#374151;border-color:#e5e7eb}
  .btn-gray-soft:hover{background:#e5e7eb}

  /* Table (DataTables) */
  .table-modern thead th{background:#f9fafb;border-bottom:1px solid #e5e7eb}
  .table-modern tfoot td{background:#fff7f7;font-weight:800;color:#b91c1c}
  .table-hover>tbody>tr:hover{background:#fff1f2 !important;}

  /* Konsistensi perataan kolom Poin (header, isi, footer) */
  #riwayatTable th.col-poin,
  #riwayatTable td.col-poin,
  #riwayatTable tfoot td.col-poin{
    text-align:center !important;
    vertical-align:middle;
  }

  /* Badge kecil “pelanggaran terakhir” */
  .mini-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#ffe5e5;color:#b91c1c}

  /* Tooltip icon */
  .tip{color:#6b7280; cursor:pointer}

  /* Animasi masuk */
  .fadein{opacity:0; transform:translateY(8px); transition:opacity .6s ease, transform .6s ease;}
  .fadein.show{opacity:1; transform:none;}

  /* ====== PERBAIKAN RESPONSIF UNTUK BARIS TOMBOL + TEKS RINGKAS ======
     (agar “Menampilkan … entri — total poin:” rapi di HP)              */
  .filter-grid .col-12:last-child{
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;   /* bisa turun baris */
  }
  @media (max-width: 768px){
    .filter-grid .col-12:last-child > .muted{
      order:3;                 /* tampil setelah tombol */
      flex:0 0 100%;           /* lebar penuh */
      margin-left:0 !important;
      margin-top:6px;
      text-align:left;         /* bisa diganti center bila ingin */
      font-size:12px;
      line-height:1.35;
      word-break:break-word;   /* jaga agar kalimat panjang tetap rapi */
    }
    .filter-grid .col-12:last-child .btn{
      margin-bottom:6px;       /* spasi tombol di HP */
      white-space:nowrap;      /* teks tombol tidak terpotong */
    }
  }
</style>

<div class="content-wrapper fadein" id="rootFade">

  <!-- Header -->
  <section class="content-header">
    <h1 class="page-title">Riwayat Pelanggaran Saya
      <small>Riwayat Poin Pelanggaran</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Pelanggaran</li>
    </ol>
  </section>

  <!-- Content -->
  <section class="content">

    <!-- Identitas + chips -->
    <div class="box soft-card fadein">
      <div class="soft-body">
        <div class="id-wrap">
          <div class="avatar"><?php echo strtoupper(substr($profil['siswa_nama']??'S',0,1)); ?></div>
          <div>
            <div style="font-weight:800;font-size:18px"><?php echo h($profil['siswa_nama']??'-'); ?></div>
            <div class="muted">
              <i class="fa fa-id-card-o"></i> NIS: <?php echo h($profil['siswa_nis']??'-'); ?>
              &nbsp;&nbsp;|&nbsp;&nbsp;
              <i class="fa fa-graduation-cap"></i> <span class="muted">Tingkat Kelas:</span> <?php echo h($profil['jurusan_nama']??'-'); ?>
            </div>
          </div>

          <?php if($kelas_terakhir){ ?>
            <span class="chip chip-blue" style="margin-left:auto"><i class="fa fa-users"></i>
              Kelas: <?php echo h($kelas_terakhir['kelas_nama']); ?>
            </span>
          <?php } ?>
        </div>

        <!-- KPI -->
        <div class="kpi-row" style="margin-top:14px">
          <div class="kpi kpi-red fadein">
            <i class="fa fa-minus-circle icon-bg"></i>
            <div class="kpi-title">TOTAL POIN PELANGGARAN</div>
            <div class="kpi-value">
              <span id="count-total" data-value="<?php echo (int)$total_pelanggaran; ?>">0</span>
              <span class="kpi-sub" style="display:inline-flex;align-items:center;gap:6px;margin-left:6px;">
                <i class="fa fa-minus-circle" aria-hidden="true"></i>
              </span>
            </div>
          </div>

          <div class="kpi kpi-amber fadein">
            <i class="fa fa-list-alt icon-bg"></i>
            <div class="kpi-title">JUMLAH ENTRI</div>
            <div class="kpi-value"><span id="count-entries" data-value="<?php echo $jumlah_entri; ?>">0</span></div>
          </div>

          <div class="kpi kpi-green fadein">
            <i class="fa fa-clock-o icon-bg"></i>
            <div class="kpi-title">PELANGGARAN TERAKHIR</div>
            <div class="kpi-value" style="font-size:22px">
              <?php if($terakhir){ ?>
                <span class="mini-badge"><i class="fa fa-history"></i>
                  <?php echo h(date('d M Y H:i', strtotime($terakhir['waktu']))); ?>
                </span>
              <?php }else{ echo '—'; } ?>
            </div>
            <div class="kpi-sub"><?php echo $terakhir ? h($terakhir['pelanggaran_nama']) : 'Belum ada catatan'; ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filter (KELAS DIHILANGKAN) -->
    <div class="box soft-card fadein">
      <div class="soft-body">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
          <strong>Filter Riwayat</strong>
          <span class="tip" data-toggle="tooltip" data-placement="right"
            title="Atur rentang data yang ingin ditampilkan. Tanggal mulai &amp; tanggal akhir bersifat inklusif (ikut dihitung). Kosongkan salah satunya untuk menampilkan semua data.">
            <i class="fa fa-info-circle"></i>
          </span>
        </div>

        <form class="filter-grid" method="get">
          <!-- TA -->
          <div class="col-6">
            <select name="ta" class="form-control" title="Filter berdasarkan Tahun Ajaran (opsional)">
              <option value="0">Semua TA</option>
              <?php while($ot = mysqli_fetch_assoc($optTA)){ ?>
                <option value="<?php echo (int)$ot['ta_id']; ?>" <?php echo $fil_ta==$ot['ta_id']?'selected':''; ?>>
                  <?php echo h($ot['ta_nama']); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <!-- Start -->
          <div class="col-3">
            <div class="input-group" title="Tanggal Mulai – data dari tanggal ini akan ikut ditampilkan (inklusif)">
              <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
              <input type="date" class="form-control" name="start" value="<?php echo h($start); ?>">
            </div>
          </div>

          <!-- End -->
          <div class="col-3">
            <div class="input-group" title="Tanggal Akhir – data sampai tanggal ini akan ikut ditampilkan (inklusif)">
              <span class="input-group-addon"><i class="fa fa-calendar-check-o"></i></span>
              <input type="date" class="form-control" name="end" value="<?php echo h($end); ?>">
            </div>
          </div>

          <!-- Buttons -->
          <div class="col-12" style="display:flex;gap:8px;align-items:center;margin-top:4px">
            <button class="btn btn-danger btn-pill"><i class="fa fa-filter"></i> Terapkan</button>
            <a class="btn btn-gray-soft btn-pill" href="?"><i class="fa fa-undo"></i> Reset</a>

            <button type="button" class="btn btn-default btn-pill" onclick="printArea()">
              <i class="fa fa-print"></i> Cetak
            </button>

            <span class="muted" style="margin-left:auto">
              Menampilkan <strong><?php echo $jumlah_entri; ?></strong> entri — total poin:
              <strong style="color:#b91c1c"><?php echo $total_filter; ?></strong>
            </span>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabel (DataTables) -->
    <div class="box soft-card fadein" id="printArea">
      <div class="soft-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-modern table-hover" id="riwayatTable" width="100%">
            <thead>
              <tr>
                <th width="1%">No</th>
                <th>Waktu</th>
                <th>Kelas</th>
                <th>TA</th>
                <th>Pelanggaran</th>
                <th class="col-poin">Poin</th> <!-- header rata tengah -->
              </tr>
            </thead>
            <tbody>
              <?php if(empty($rows)){ ?>
                <tr><td colspan="6" class="text-center text-muted">Belum ada data pelanggaran pada rentang ini.</td></tr>
              <?php } else { $no=1; foreach($rows as $r){ ?>
                <tr title="Detail: <?php echo h($r['pelanggaran_nama']); ?>">
                  <td><?php echo $no++; ?></td>
                  <td><?php echo h(date('d-m-Y H:i:s', strtotime($r['waktu']))); ?></td>
                  <td><?php echo h($r['kelas_nama']); ?></td>
                  <td><?php echo h($r['ta_nama']); ?></td>
                  <td><?php echo h($r['pelanggaran_nama']); ?></td>
                  <td class="col-poin">
                    <span class="label label-danger" style="display:inline-block;min-width:34px"><?php echo (int)$r['pelanggaran_point']; ?></span>
                  </td>
                </tr>
              <?php }} ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-center">TOTAL</td>
                <td class="col-poin"><?php echo $total_filter; ?></td> <!-- footer rata tengah -->
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

  </section>
</div>

<!-- DataTables (Bootstrap 3 skin) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap.min.js"></script>

<script>
// Tooltip bootstrap
if (typeof $ !== 'undefined' && $.fn.tooltip) {
  $('[data-toggle="tooltip"], [title]').tooltip();
}

// Inisialisasi DataTables (anti reinit)
$(function(){
  if ($.fn.DataTable.isDataTable('#riwayatTable')) {
    $('#riwayatTable').DataTable().destroy();
  }
  $('#riwayatTable').DataTable({
    pageLength: 10,
    lengthMenu: [[10,25,50,-1],[10,25,50,"Semua"]],
    order: [[1,'desc']],
    language: {
      lengthMenu: "Tampilkan _MENU_ entri",
      zeroRecords: "Tidak ditemukan data",
      info: "Menampilkan _START_–_END_ dari _TOTAL_ entri",
      infoEmpty: "Menampilkan 0 entri",
      infoFiltered: "(disaring dari total _MAX_ entri)",
      search: "Cari:",
      paginate: { first:"Awal", last:"Akhir", next:"→", previous:"←" }
    }
  });
});

// Animasi angka + fade-in
function animateNumber(el, end, duration){
  var start = 0, startTime = null;
  end = Number(end)||0;
  function step(ts){
    if(!startTime) startTime = ts;
    var p = Math.min((ts - startTime)/duration, 1);
    el.textContent = Math.floor(p * end);
    if(p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
document.addEventListener('DOMContentLoaded', function(){
  setTimeout(function(){
    document.querySelectorAll('.fadein').forEach(function(el){ el.classList.add('show'); });
  }, 60);
  var ct = document.getElementById('count-total');
  var ce = document.getElementById('count-entries');
  if (ct) animateNumber(ct, ct.getAttribute('data-value'), 800);
  if (ce) animateNumber(ce, ce.getAttribute('data-value'), 900);
});

// Print yang rapi
function printArea(){
  var htmlSource = document.getElementById('printArea').innerHTML;
  var w = window.open('', 'PRINT', 'height=800,width=1000');
  var html = `
    <html>
      <head>
        <meta charset="utf-8">
        <title>Riwayat Pelanggaran</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
        <style>
          @page { margin: 18mm; }
          body{ font-family: "Segoe UI", Arial, sans-serif; color:#111; }
          h2{ margin-top:0; font-weight:700; }
          .meta{ margin:6px 0 14px; color:#555; }
          .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate{ display:none !important; }
          table{ width:100%; border-collapse:collapse; }
          thead th{ background:#f1f5f9; color:#111; border:1px solid #e5e7eb; padding:8px; font-weight:700; }
          tbody td{ border:1px solid #e5e7eb; padding:8px; vertical-align:top; }
          tfoot td{ border:1px solid #e5e7eb; background:#fff5f5; font-weight:700; color:#b91c1c; padding:8px; text-align:center; }
          /* pastikan kolom poin di print juga rata tengah */
          td.col-poin{ text-align:center !important; }
        </style>
      </head>
      <body>
        <h2>Riwayat Pelanggaran — <?php echo h($profil['siswa_nama']??''); ?></h2>
        <div class="meta"><?php echo h($start); ?> s.d. <?php echo h($end); ?></div>
        ${htmlSource}
        <script>window.onload = function(){ window.print(); setTimeout(function(){ window.close(); }, 50); }<\/script>
      </body>
    </html>`;
  w.document.write(html);
  w.document.close();
  w.focus();
  return true;
}
</script>

<?php include 'footer.php'; ?>
