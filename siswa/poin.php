<?php include 'header.php'; ?>
<div class="content-wrapper">

  <!-- ===== Header ===== -->
  <section class="content-header">
    <h1>Poin Saya <small>Prestasi & Pelanggaran</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Poin</li>
    </ol>
  </section>

  <section class="content">
<?php
// ===== Helper =====
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$id_siswa = (int)($_SESSION['id'] ?? 0);

// ===== Ringkasan angka =====
$q_plus = mysqli_query($koneksi,"SELECT COALESCE(SUM(p.prestasi_point),0) AS plus_poin
                                 FROM input_prestasi ip
                                 JOIN prestasi p ON p.prestasi_id=ip.prestasi
                                 WHERE ip.siswa='{$id_siswa}'");
$plus = (int)mysqli_fetch_assoc($q_plus)['plus_poin'];

$q_min = mysqli_query($koneksi,"SELECT COALESCE(SUM(pg.pelanggaran_point),0) AS minus_poin
                                FROM input_pelanggaran ig
                                JOIN pelanggaran pg ON pg.pelanggaran_id=ig.pelanggaran
                                WHERE ig.siswa='{$id_siswa}'");
$minus = (int)mysqli_fetch_assoc($q_min)['minus_poin'];

$total_poin = $plus - $minus;

// ===== Aktivitas terbaru (5) =====
$aktivitas = [];
$sqlAkt = "(SELECT 'Prestasi' tipe, ip.waktu tgl, pr.prestasi_nama nama, pr.prestasi_point poin
            FROM input_prestasi ip JOIN prestasi pr ON pr.prestasi_id=ip.prestasi
            WHERE ip.siswa={$id_siswa})
           UNION ALL
           (SELECT 'Pelanggaran' tipe, ig.waktu tgl, pl.pelanggaran_nama nama, -pl.pelanggaran_point poin
            FROM input_pelanggaran ig JOIN pelanggaran pl ON pl.pelanggaran_id=ig.pelanggaran
            WHERE ig.siswa={$id_siswa})
           ORDER BY tgl DESC LIMIT 5";
$q = mysqli_query($koneksi,$sqlAkt);
while($row=mysqli_fetch_assoc($q)) $aktivitas[]=$row;

// ===== Daftar prestasi & pelanggaran (untuk tabel) =====
$prestasi = mysqli_query($koneksi,"SELECT ip.waktu tgl, pr.prestasi_nama nama, pr.prestasi_point poin
                                   FROM input_prestasi ip JOIN prestasi pr ON pr.prestasi_id=ip.prestasi
                                   WHERE ip.siswa='{$id_siswa}' ORDER BY ip.waktu DESC");
$pelanggaran = mysqli_query($koneksi,"SELECT ig.waktu tgl, pl.pelanggaran_nama nama, pl.pelanggaran_point poin
                                      FROM input_pelanggaran ig JOIN pelanggaran pl ON pl.pelanggaran_id=ig.pelanggaran
                                      WHERE ig.siswa='{$id_siswa}' ORDER BY ig.waktu DESC");
?>

<!-- ===== Styles ===== -->
<style>
  .fadein{opacity:0; transform:translateY(8px); transition:opacity .6s ease, transform .6s ease;}
  .fadein.show{opacity:1; transform:none;}

  .kpi-row{display:flex; gap:12px; flex-wrap:wrap}
  .kpi{flex:1 1 260px; min-height:120px; padding:16px 18px; color:#fff; border-radius:16px;
       box-shadow:0 10px 22px rgba(0,0,0,.10); position:relative; overflow:hidden}
  .kpi .icon{position:absolute; right:16px; bottom:12px; font-size:40px; opacity:.18}
  .kpi-title{font-size:12px; letter-spacing:.4px; text-transform:uppercase}
  .kpi-val{font-size:42px; font-weight:800; line-height:1.05}

  .kpi-total{background:linear-gradient(135deg,#60a5fa,#2563eb);}
  .kpi-plus {background:linear-gradient(135deg,#34d399,#059669);}
  .kpi-minus{background:linear-gradient(135deg,#fb7185,#dc2626);}

  .split-track{margin-top:12px;height:10px;border-radius:999px;background:#eef2ff;overflow:hidden}
  .split-plus{height:100%;background:#16a34a;float:left;transition:width .8s ease}
  .split-minus{height:100%;background:#ef4444;float:left;transition:width .8s ease}

  .card{border-radius:14px; background:#fff; box-shadow:0 8px 20px rgba(0,0,0,.06); overflow:hidden}
  .card-header{padding:14px 16px; border-bottom:1px dashed #e5e7eb; display:flex; align-items:center; justify-content:space-between}
  .card-body{padding:16px}
  .hint{color:#6b7280; cursor:pointer}

  .list-aktivitas{margin:0;padding:0;list-style:none}
  .list-aktivitas li{display:flex; gap:12px; align-items:flex-start; padding:10px 6px; border-bottom:1px dashed #e5e7eb}
  .act-badge{min-width:30px;height:30px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
  .act-prestasi{background:#10b981}
  .act-pelanggaran{background:#ef4444}
  .act-title{margin:0; font-weight:700}
  .act-meta{color:#6b7280; font-size:12px}
  .label-pill{border-radius:999px; padding:4px 10px; display:inline-block; min-width:34px}

  .table-modern thead th{background:#f9fafb}
  .table-hover>tbody>tr:hover{background:#f8fafc !important;}
  .col-poin{text-align:center !important; vertical-align:middle}

  /* ====== Tambahan: supaya progress bar tidak menutupi ikon ====== */
  .kpi.kpi-total{padding-right:92px;}           /* beri ruang kanan ekstra */
  .kpi-total .split-track{margin-right:76px; position:relative; z-index:1;} /* batang dipendekkan di sisi ikon */
  .kpi .icon{z-index:2;}                         /* ikon selalu di atas batang progress */

  @media (max-width: 480px){
    .kpi.kpi-total{padding-right:78px;}
    .kpi-total .split-track{margin-right:62px;}
  }
</style>

<!-- ===== KPI ===== -->
<div class="row fadein">
  <section class="col-md-12">
    <div class="kpi-row">
      <div class="kpi kpi-total">
        <i class="fa fa-chart-line icon"></i>
        <div class="kpi-title">TOTAL POIN</div>
        <div class="kpi-val" id="count-total" data-end="<?php echo (int)$total_poin; ?>">0</div>
        <div class="split-track" title="+Prestasi vs −Pelanggaran">
          <?php
            $sum = ($plus + $minus);
            $pp = $sum>0 ? round(($plus/$sum)*100) : 0;
            $pm = 100 - $pp;
          ?>
          <span class="split-plus"  style="width:<?php echo $pp; ?>%"></span>
          <span class="split-minus" style="width:<?php echo $pm; ?>%"></span>
        </div>
      </div>

      <div class="kpi kpi-plus">
        <i class="fa fa-trophy icon"></i>
        <div class="kpi-title">TOTAL PRESTASI</div>
        <div class="kpi-val" id="count-plus" data-end="<?php echo (int)$plus; ?>">0</div>
      </div>

      <div class="kpi kpi-minus">
        <i class="fa fa-exclamation-triangle icon"></i>
        <div class="kpi-title">TOTAL PELANGGARAN</div>
        <div class="kpi-val" id="count-minus" data-end="<?php echo (int)$minus; ?>">0</div>
      </div>
    </div>
  </section>
</div>

<!-- ===== Aktivitas & Donut ===== -->
<div class="row fadein" style="margin-top:12px;">
  <section class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h3 style="margin:0;font-weight:800">Aktivitas Poin Anda</h3>
        <span class="hint" data-toggle="tooltip" data-placement="left"
              title="Rekap 5 aktivitas poin terbaru yang Anda terima (prestasi atau pelanggaran).">
          <i class="fa fa-info-circle"></i>
        </span>
      </div>
      <div class="card-body">
        <?php if(empty($aktivitas)){ ?>
          <div class="text-center text-muted" style="padding:18px">
            <i class="fa fa-stream fa-2x"></i><br>Belum ada aktivitas.
          </div>
        <?php } else { ?>
          <ul class="list-aktivitas">
            <?php foreach($aktivitas as $a){ $isPlus = (int)$a['poin']>0; ?>
              <li>
                <span class="act-badge <?php echo $isPlus?'act-prestasi':'act-pelanggaran'; ?>">
                  <?php echo $isPlus?'+':'−'; ?>
                </span>
                <div style="flex:1">
                  <p class="act-title">
                    <strong><?php echo h($a['tipe']); ?></strong>
                    <?php if($isPlus){ ?>
                      <span class="label label-success label-pill" style="margin-left:6px;">+<?php echo (int)$a['poin']; ?></span>
                    <?php } else { ?>
                      <span class="label label-danger label-pill" style="margin-left:6px;"><?php echo (int)$a['poin']; ?></span>
                    <?php } ?>
                  </p>
                  <div class="act-meta">
                    <?php echo h($a['nama']); ?> — <i class="fa fa-clock-o"></i>
                    <?php echo date('d M Y H:i', strtotime($a['tgl'])); ?>
                  </div>
                </div>
              </li>
            <?php } ?>
          </ul>
        <?php } ?>
      </div>
    </div>
  </section>

  <section class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h3 style="margin:0;font-weight:800">Komposisi Poin</h3>
        <span class="hint" data-toggle="tooltip" data-placement="left"
              title="Perbandingan total poin prestasi (+) dan pelanggaran (−).">
          <i class="fa fa-info-circle"></i>
        </span>
      </div>
      <div class="card-body">
        <div style="max-width:380px;margin:auto;">
          <canvas id="donutPoin" height="220"></canvas>
        </div>
        <div class="text-center" style="margin-top:10px;color:#6b7280">
          <i class="fa fa-circle" style="color:#10b981"></i> Prestasi &nbsp;&nbsp;
          <i class="fa fa-circle" style="color:#ef4444"></i> Pelanggaran
        </div>
      </div>
    </div>
  </section>
</div>

<!-- ===== Tabel Prestasi & Pelanggaran ===== -->
<div class="row fadein" style="margin-top:12px;">
  <!-- Prestasi -->
  <section class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h3 style="margin:0;font-weight:800">Daftar Prestasi</h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-modern table-hover" id="tPrestasi" width="100%">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Prestasi</th>
                <th class="col-poin">Poin</th>
              </tr>
            </thead>
            <tbody>
              <?php if(mysqli_num_rows($prestasi)==0){ ?>
                <tr><td colspan="3" class="text-center text-muted">Belum ada data prestasi.</td></tr>
              <?php } else { while($p=mysqli_fetch_assoc($prestasi)){ ?>
                <tr>
                  <td><?php echo date('d M Y', strtotime($p['tgl'])); ?></td>
                  <td><?php echo h($p['nama']); ?></td>
                  <td class="col-poin"><span class="label label-success label-pill">+<?php echo (int)$p['poin']; ?></span></td>
                </tr>
              <?php }} ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- Pelanggaran -->
  <section class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h3 style="margin:0;font-weight:800">Daftar Pelanggaran</h3>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-modern table-hover" id="tPelanggaran" width="100%">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Pelanggaran</th>
                <th class="col-poin">Poin</th>
              </tr>
            </thead>
            <tbody>
              <?php if(mysqli_num_rows($pelanggaran)==0){ ?>
                <tr><td colspan="3" class="text-center text-muted">Belum ada data pelanggaran.</td></tr>
              <?php } else { while($pl=mysqli_fetch_assoc($pelanggaran)){ ?>
                <tr>
                  <td><?php echo date('d M Y', strtotime($pl['tgl'])); ?></td>
                  <td><?php echo h($pl['nama']); ?></td>
                  <td class="col-poin"><span class="label label-danger label-pill">-<?php echo (int)$pl['poin']; ?></span></td>
                </tr>
              <?php }} ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

  </section><!-- /content -->
</div><!-- /content-wrapper -->
<?php include 'footer.php'; ?>

<!-- ===== Libraries ===== -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap.min.js"></script>

<script>
// ===== Tooltip =====
if (typeof $ !== 'undefined' && $.fn.tooltip) {
  $('[data-toggle="tooltip"]').tooltip();
}

// ===== Count-up animation =====
function animateNumber(el){
  var end = Number(el.getAttribute('data-end')) || 0;
  var start = 0, startTime = null, duration = 900;
  function step(ts){
    if(!startTime) startTime = ts;
    var p = Math.min((ts - startTime)/duration, 1);
    el.textContent = Math.floor(start + (end-start)*p);
    if(p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// ===== Fade in =====
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.fadein').forEach(function(el){ setTimeout(function(){ el.classList.add('show'); }, 60); });

  ['count-total','count-plus','count-minus'].forEach(function(id){
    var el = document.getElementById(id); if(el) animateNumber(el);
  });
});

// ===== Donut chart =====
(function(){
  var ctx = document.getElementById('donutPoin');
  if(!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Prestasi (+)','Pelanggaran (−)'],
      datasets: [{
        data: [<?php echo (int)$plus; ?>, <?php echo (int)$minus; ?>],
        backgroundColor: ['#10b981','#ef4444'],
        hoverOffset: 6
      }]
    },
    options:{
      plugins:{ legend:{ display:false } },
      cutout:'58%',
      animation:{ animateRotate:true, animateScale:true, duration:1200 },
      maintainAspectRatio:false
    }
  });
})();

// ===== DataTables (safe init + default 5 baris) =====
$(function(){
  function initDT(sel, emptyMsg){
    // --- FIX: hilangkan baris placeholder dengan colspan yang bikin error column count ---
    var $tbl = $(sel);
    var colCount = $tbl.find('thead th').length;
    $tbl.find('tbody tr').each(function(){
      // jika jumlah kolom td tidak sesuai header (biasanya karena colspan), hapus sebelum init
      if ($(this).children('td').length !== colCount) {
        $(this).remove();
      }
    });

    if ($.fn.DataTable.isDataTable(sel)) $(sel).DataTable().destroy();

    $(sel).DataTable({
      pageLength: 5,
      lengthMenu: [[5,10,25,-1],[5,10,25,"Semua"]],
      order: [[0,'desc']],
      language:{
        lengthMenu: "Tampilkan _MENU_ entri",
        zeroRecords: "Tidak ditemukan data",
        info: "Menampilkan _START_–_END_ dari _TOTAL_ entri",
        infoEmpty: "Menampilkan 0 entri",
        infoFiltered: "(disaring dari _MAX_ entri)",
        search: "Cari:",
        paginate: { first:"Awal", last:"Akhir", next:"→", previous:"←" },
        emptyTable: emptyMsg || "Belum ada data."
      }
    });
  }
  initDT('#tPrestasi','Belum ada data prestasi.');
  initDT('#tPelanggaran','Belum ada data pelanggaran.');
});
</script>
