<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Pelanggaran
      <small>Data Pelanggaran</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <?php
    // Ringkasan ringan: total item & point tertinggi
    $stat = mysqli_fetch_assoc(mysqli_query($koneksi,"
      SELECT COUNT(*) AS total, COALESCE(MAX(pelanggaran_point),0) AS maxp
      FROM pelanggaran
    "));
  ?>

  <style>
    /* ===== Theme polish (khusus halaman ini) ===== */
    #boxPelanggaran{ border-radius:16px; box-shadow:0 10px 24px rgba(0,0,0,.06); overflow:hidden; }
    #boxPelanggaran .box-header{
      background: linear-gradient(135deg,#ffe4e6,#fff7ed); /* rose → amber */
      border-bottom: 1px solid #f3f4f6;
      padding: 14px 16px;
      display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    }
    .head-title{
      display:flex; align-items:center; gap:10px; margin:0; font-weight:800;
      color:#0f172a;
    }
    .head-title .alert{
      width:34px; height:34px; border-radius:50%;
      display:inline-flex; align-items:center; justify-content:center;
      background:#fee2e2; color:#dc2626;
    }

    .toolbar-right{ margin-left:auto; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .soft-chip{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px;
      background:#fff1f2; color:#b91c1c; border:1px solid #fecaca;
    }
    .soft-chip i{opacity:.8}

    /* Kontrol urut: biar menyatu & rapi */
    #sort-control{ border-radius:999px; }
    .input-group-addon{ border-radius:999px 0 0 999px; }

    /* Table look */
    .table-modern thead th{ background:#f8fafc; border-bottom:1px solid #e5e7eb; }
    .table-modern tbody tr:hover{ background:#fff7f7 !important; }

    .badge-point-red{
      display:inline-block; min-width:54px; text-align:center;
      padding:6px 12px; border-radius:999px;
      background:linear-gradient(135deg,#fca5a5,#ef4444);
      color:#7f1d1d; font-weight:800; box-shadow:0 4px 10px rgba(239,68,68,.18);
    }

    /* Fade-in anim */
    .fadein{opacity:0; transform:translateY(8px); transition:opacity .6s ease, transform .6s ease;}
    .fadein.show{opacity:1; transform:none;}

    /* Responsif: toolbar wrap rapi di HP */
    @media(max-width: 576px){
      .toolbar-right{ width:100%; margin-left:0; }
      .toolbar-right .input-group{ width:100% !important; }
    }
  </style>

  <section class="content">
    <div class="row">
      <section class="col-lg-12">
        <div class="box fadein" id="boxPelanggaran">

          <div class="box-header">
            <h3 class="head-title">
              <span class="alert"><i class="fa fa-exclamation-triangle"></i></span>
              <span>Data Point Pelanggaran</span>
            </h3>

            <!-- Chips ringkasan -->
            <span class="soft-chip" title="Jumlah jenis pelanggaran terdaftar">
              <i class="fa fa-list-ul"></i> Total Jenis: <strong><?php echo (int)$stat['total']; ?></strong>
            </span>
            <span class="soft-chip" title="Point tertinggi dari daftar pelanggaran">
              <i class="fa fa-bolt"></i> Point Tertinggi: <strong><?php echo (int)$stat['maxp']; ?></strong>
            </span>

            <!-- Kontrol urut poin -->
            <div class="toolbar-right">
              <div class="input-group input-group-sm" style="width:260px;">
                <span class="input-group-addon"><i class="fa fa-sort-amount-asc"></i></span>
                <select id="sort-control" class="form-control" title="Urutkan berdasarkan point">
                  <option value="poin_asc">Urut Point (Rendah → Tinggi)</option>
                  <option value="poin_desc">Urut Point (Tinggi → Rendah)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped table-modern" id="table-datatable">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>NAMA PELANGGARAN</th>
                    <th class="text-center">POINT</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT * FROM pelanggaran");
                  while($d = mysqli_fetch_array($data)){
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($d['pelanggaran_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <!-- data-order menjadikan sort numerik, display tetap "X Point" -->
                      <td class="text-center" data-order="<?php echo (int)$d['pelanggaran_point']; ?>">
                        <span class="badge-point-red"><?php echo (int)$d['pelanggaran_point']; ?></span>
                      </td>
                    </tr>
                    <?php 
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<!-- Inisialisasi DataTables + kontrol sort poin (AMAN dari double init tn/3) -->
<script>
  // Fade-in yang halus
  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(function(){
      var el=document.getElementById('boxPelanggaran'); if(el) el.classList.add('show');
    }, 60);
  });

  $(function () {
    // Hindari popup alert dari DataTables; kirim error ke console
    if ($.fn.dataTable && $.fn.dataTable.ext) {
      $.fn.dataTable.ext.errMode = 'console';
    }

    var $tbl = $('#table-datatable');

    // Bila tabel sudah pernah di-init oleh script global lain, destroy dulu
    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
      try { $tbl.DataTable().destroy(); } catch(e){}
      // Bersihkan class sorting lama agar header rapi
      $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
    }

    if (!$.fn.DataTable) return; // jaga-jaga jika plugin belum termuat

    // Kolom: 0=NO, 1=Nama, 2=Point
    var t = $tbl.DataTable({
      destroy: true,
      responsive: true,
      autoWidth: false,
      order: [[2, 'asc']], // default: point rendah → tinggi
      columnDefs: [
        { targets: [0], orderable: false },        // kolom NO tidak bisa di-sort
        { targets: [2], className: 'text-center' } // point rata tengah
      ],
      language: {
        search: "Cari:",
        lengthMenu: "Tampilkan _MENU_ data",
        info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
        infoEmpty: "Tidak ada data",
        zeroRecords: "Tidak ditemukan data yang cocok",
        paginate: { first:"Pertama", last:"Terakhir", next:"›", previous:"‹" }
      }
    });

    // Nomor urut otomatis mengikuti sort & filter
    t.on('order.dt search.dt', function () {
      var i = 1;
      t.column(0, { search: 'applied', order: 'applied' })
       .nodes()
       .each(function (cell) { cell.innerHTML = i++; });
    }).draw();

    // Dropdown kontrol sort poin
    $('#sort-control').on('change', function () {
      var v = $(this).val();
      if (v === 'poin_asc')  t.order([2, 'asc']).draw();
      if (v === 'poin_desc') t.order([2, 'desc']).draw();
    });
  });
</script>

<?php include 'footer.php'; ?>
