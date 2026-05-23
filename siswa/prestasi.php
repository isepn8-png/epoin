<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Prestasi
      <small>Data Prestasi</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <?php
    // Tambahan ringkasan (tidak mengganggu tabel): total item & poin tertinggi
    $stat = mysqli_fetch_assoc(mysqli_query($koneksi,"
      SELECT COUNT(*) AS total, COALESCE(MAX(prestasi_point),0) AS maxp
      FROM prestasi
    "));
  ?>

  <style>
    /* ===== Theme polish (khusus halaman ini) ===== */
    #boxPrestasi{ border-radius:16px; box-shadow:0 10px 24px rgba(0,0,0,.06); overflow:hidden; }
    #boxPrestasi .box-header{
      background: linear-gradient(135deg,#e0f2fe,#d1fae5);
      border-bottom: 1px solid #eef2f7;
      padding: 14px 16px;
      display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    }
    .head-title{
      display:flex; align-items:center; gap:10px; margin:0; font-weight:800;
      color:#0f172a;
    }
    .head-title .trophy{
      width:34px; height:34px; border-radius:50%;
      display:inline-flex; align-items:center; justify-content:center;
      background:#dbeafe; color:#2563eb;
    }
    .toolbar-right{ margin-left:auto; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .soft-chip{
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px;
      background:#ecfeff; color:#0e7490; border:1px solid #cffafe;
    }
    .soft-chip i{opacity:.8}

    /* Kontrol urut: biar menyatu */
    #sort-control{ border-radius:999px; }
    .input-group-addon{ border-radius:999px 0 0 999px; }

    /* Table look */
    .table-modern thead th{ background:#f8fafc; border-bottom:1px solid #e5e7eb; }
    .table-modern tbody tr:hover{ background:#f0f9ff !important; }
    .badge-point{
      display:inline-block; min-width:54px; text-align:center;
      padding:6px 12px; border-radius:999px;
      background:linear-gradient(135deg,#86efac,#22c55e);
      color:#064e3b; font-weight:800; box-shadow:0 4px 10px rgba(34,197,94,.18);
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
        <div class="box fadein" id="boxPrestasi">

          <div class="box-header">
            <h3 class="head-title">
              <span class="trophy"><i class="fa fa-trophy"></i></span>
              <span>Data Poin Prestasi</span>
            </h3>

            <!-- Chips ringkasan -->
            <span class="soft-chip" title="Jumlah jenis prestasi terdaftar">
              <i class="fa fa-list-ul"></i> Total Jenis: <strong><?php echo (int)$stat['total']; ?></strong>
            </span>
            <span class="soft-chip" title="Poin tertinggi dari daftar prestasi">
              <i class="fa fa-star"></i> Poin Tertinggi: <strong><?php echo (int)$stat['maxp']; ?></strong>
            </span>

            <!-- Kontrol urut poin -->
            <div class="toolbar-right">
              <div class="input-group input-group-sm" style="width:260px;">
                <span class="input-group-addon"><i class="fa fa-sort-amount-asc"></i></span>
                <select id="sort-control" class="form-control" title="Urutkan berdasarkan poin">
                  <option value="poin_asc">Urut Poin (Rendah → Tinggi)</option>
                  <option value="poin_desc">Urut Poin (Tinggi → Rendah)</option>
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
                    <th>NAMA PRESTASI</th>
                    <th class="text-center">POIN</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT * FROM prestasi");
                  while($d = mysqli_fetch_array($data)){
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($d['prestasi_nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <!-- data-order memastikan sort numerik -->
                      <td class="text-center" data-order="<?php echo (int)$d['prestasi_point']; ?>">
                        <span class="badge-point"><?php echo (int)$d['prestasi_point']; ?></span>
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

<!-- Inisialisasi DataTables + kontrol sort poin (AMAN dari double init) -->
<script>
  // Fade-in
  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(function(){
      var el=document.getElementById('boxPrestasi'); if(el) el.classList.add('show');
    }, 60);
  });

  $(function () {
    // Hindari pop-up error
    if ($.fn.dataTable && $.fn.dataTable.ext) {
      $.fn.dataTable.ext.errMode = 'console';
    }

    var $tbl = $('#table-datatable');

    // Jika sudah pernah di-init oleh script global lain, destroy dulu & bersihkan kelas sort
    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
      try { $tbl.DataTable().destroy(); } catch(e){}
      $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
    }

    if (!$.fn.DataTable) return;

    // Kolom: 0=NO, 1=Nama, 2=Poin
    var t = $tbl.DataTable({
      destroy: true,
      responsive: true,
      autoWidth: false,
      order: [[2, 'asc']], // default: poin rendah → tinggi
      columnDefs: [
        { targets: [0], orderable: false },               // NO tidak bisa di-sort
        { targets: [2], className: 'text-center' }        // POIN rata tengah
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
