<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>

<?php
// ==== Fallback helper roles (jaga-jaga jika belum ada) ====
if (!function_exists('_is_admin')) {
  function _is_admin(){
    $roles = $_SESSION['roles'] ?? [];
    $lvl   = $_SESSION['level'] ?? '';
    return in_array('administrator',$roles,true) || in_array('superadmin',$roles,true) || $lvl==='administrator';
  }
}
if (!function_exists('_is_guru')) {
  function _is_guru(){
    $roles = $_SESSION['roles'] ?? [];
    $lvl   = $_SESSION['level'] ?? '';
    return in_array('guru',$roles,true) || $lvl==='guru';
  }
}

// ==== Hitung total prestasi utk badge header ====
if (function_exists('count_table')) {
  $total_prestasi = count_table($koneksi, 'prestasi');
} else {
  $qcp = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM prestasi");
  $total_prestasi = ($qcp && ($r=mysqli_fetch_assoc($qcp))) ? (int)$r['jml'] : 0;
}
?>

<!-- ===== THEME: Prestasi (Hijau) ===== -->
<style>
  :root{
    --p-green: #16a34a;      /* emerald-600 */
    --p-green-700:#15803d;   /* emerald-700 */
    --p-green-500:#22c55e;   /* emerald-500 */
    --p-green-400:#34d399;   /* emerald-400 */
    --p-green-300:#86efac;   /* emerald-300 */
    --p-green-200:#bbf7d0;   /* emerald-200 */
    --p-green-100:#dcfce7;   /* emerald-100 */
    --p-green-50:#f0fdf4;    /* emerald-50 */
  }

  /* Animasi halaman saat masuk */
  .content-wrapper{ animation: fadeLift 520ms ease-out both; }
  @keyframes fadeLift{ 0%{opacity:0;transform:translateY(10px);} 100%{opacity:1;transform:translateY(0);} }

  /* Modal fix (kadang tertutup elemen lain) */
  .modal { z-index:1050; }
  .modal-backdrop { z-index:1040; }
  #modal_prestasi .modal-content{ position:relative; z-index:1060; }
  #modal_prestasi input.form-control{ background:#fff; pointer-events:auto; }

  /* Box header accent */
  .box .box-header{
    border-bottom:0;
    background:linear-gradient(90deg, var(--p-green-50), #fff);
    border-top:3px solid var(--p-green);
  }

  /* Judul + badge total */
  .title-wrap{ display:flex; align-items:center; gap:10px; }
  .badge-total-prestasi{
    background:linear-gradient(90deg, var(--p-green-500), var(--p-green));
    color:#fff; border-radius:999px; padding:3px 10px; font-weight:700; font-size:12px;
    box-shadow:0 2px 8px rgba(22,163,74,.25);
  }

  /* Sembunyikan sub-judul "Prestasi" di header box (permintaan sebelumnya) */
  .hide-subtitle{ display:none !important; }

  /* Button tambah */
  .btn-success.btn-sm{
    background:linear-gradient(90deg, var(--p-green-500), var(--p-green));
    border-color:var(--p-green-700);
    transition:transform .08s, box-shadow .2s, filter .2s;
  }
  .btn-success.btn-sm:hover{ filter:saturate(1.1); transform:translateY(-1px); box-shadow:0 6px 16px rgba(22,163,74,.25); }

  /* Tombol non-admin: non-aktif visual */
  .btn-disabled{ background:#e5e7eb!important; border-color:#d1d5db!important; color:#6b7280!important; cursor:not-allowed; filter:grayscale(.2); }

  /* ====== REVISI 1: Zebra table hijau soft (GANJIL hijau soft, GENAP putih) + header hijau tua soft ====== */
  #table-datatable { border-collapse:separate; border-spacing:0; }

  /* Header tabel: hijau tua soft, tidak mencolok */
  #table-datatable.table>thead>tr>th{
    background: linear-gradient(90deg, var(--p-green-200), var(--p-green-300));
    color:#064e3b; /* hijau gelap agar tetap lembut & terbaca */
    border-bottom:2px solid rgba(5,150,105,.35)!important;
  }

  /* Bodi tabel: garis pemisah */
  #table-datatable.table>tbody>tr>td{ border-bottom:1px solid rgba(16,185,129,.22)!important; }

  /* Zebra: ganjil hijau soft, genap putih */
  #table-datatable.table>tbody>tr:nth-child(odd)>td{ background: var(--p-green-50); }
  #table-datatable.table>tbody>tr:nth-child(even)>td{ background: #fff; }

  /* Hover tetap lembut */
  #table-datatable.table>tbody>tr:hover>td{
    background:linear-gradient(90deg, var(--p-green-100), var(--p-green-50))!important;
    box-shadow:inset 0 0 0 9999px rgba(34,197,94,.06);
  }

  .prestasi-name{ font-weight:600; color:#111827; }
  .badge-point{
    display:inline-block; min-width:82px; text-align:center;
    background:linear-gradient(90deg, var(--p-green-200), var(--p-green-500));
    color:#0b3b1f; border-radius:999px; padding:4px 12px; font-weight:700;
    box-shadow:inset 0 0 0 1px rgba(16,185,129,.25), 0 2px 8px rgba(16,185,129,.18);
  }

  .opsi-head-badge{ display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:700; letter-spacing:.2px; }
  .opsi-head-badge.admin{ background:linear-gradient(90deg,#e9f7ef,#d1fae5); color:#065f46; border:1px solid rgba(5,150,105,.25); }
  .opsi-head-badge.lock{ background:linear-gradient(90deg,#fff7ed,#ffedd5); color:#7c2d12; border:1px solid rgba(194,65,12,.25); }

  .btn-warning.btn-sm{ background:linear-gradient(90deg,#f59e0b,#d97706); border-color:#b45309; color:#fff; transition:transform .08s, box-shadow .2s, filter .2s; }
  .btn-warning.btn-sm:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(217,119,6,.25); filter:saturate(1.05); }
  .btn-danger.btn-sm{ background:linear-gradient(90deg,#ef4444,#dc2626); border-color:#b91c1c; color:#fff; transition:transform .08s, box-shadow .2s, filter .2s; }
  .btn-danger.btn-sm:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(220,38,38,.25); filter:saturate(1.05); }

  .tooltip-inner{ font-weight:600; }

  @media (max-width:768px){
    .title-wrap small{ display:none; }
    .badge-total-prestasi{ font-size:11px; padding:2px 8px; }
    .box .box-header .input-group{ width:100%!important; margin-top:8px; }
    .btn-group.pull-right{ float:none!important; width:100%; margin:10px 0 0 0!important; }
    .btn-group.pull-right .btn{ width:100%; }
    .badge-point{ min-width:72px; padding:3px 10px; font-size:12px; }
    #table-datatable .btn.btn-sm{ padding:4px 8px; }
  }
</style>
<!-- ===== /THEME ===== -->

<div class="content-wrapper">

  <section class="content-header">
    <h1 class="title-wrap">
      <span><i class="fa fa-trophy" style="color:var(--p-green)"></i> Prestasi</span>
      <small class="badge-total-prestasi" title="Total jenis prestasi"><?php echo (int)$total_prestasi; ?> jenis</small>
      <small style="color:#64748b;">Data Prestasi</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <div class="col-lg-12"><?php epoin_flash_render(); ?></div>
    </div>
    <div class="row">
      <section class="col-lg-12">
        <div class="box">

          <div class="box-header">
            <div class="title-wrap">
              <h3 class="box-title hide-subtitle" style="margin:0">Prestasi</h3>
            </div>

            <div class="btn-group pull-right" style="margin-left:10px;">
              <?php if (_is_admin()): ?>
                <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal_prestasi" id="btnAddPrestasi">
                  <i class="fa fa-plus"></i> &nbsp;Tambah Prestasi Baru
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-success btn-sm btn-disabled" data-toggle="tooltip" title="Hanya admin yang dapat menambah prestasi" disabled>
                  <i class="fa fa-lock"></i> &nbsp;Tambah Prestasi Baru
                </button>
              <?php endif; ?>
            </div>

            <!-- Kontrol Sort -->
            <div class="pull-right" style="margin-right:10px;">
              <div class="input-group input-group-sm" style="width:240px;">
                <span class="input-group-addon"><i class="fa fa-sort-amount-asc"></i></span>
                <select id="sort-control" class="form-control">
                  <option value="nama_asc">Urut Nama (A → Z)</option>
                  <option value="nama_desc">Urut Nama (Z → A)</option>
                  <option value="poin_asc" selected>Urut Poin (Rendah → Tinggi)</option> <!-- default disesuaikan dengan sorting -->
                  <option value="poin_desc">Urut Poin (Tinggi → Rendah)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Modal Tambah -->
          <div class="modal fade" id="modal_prestasi" tabindex="-1" role="dialog" aria-labelledby="modalLabelPrestasi">
            <div class="modal-dialog">
              <div class="modal-content">

                <div class="modal-header" style="background:linear-gradient(90deg,var(--p-green-200),#fff); border-bottom:1px solid var(--p-green-200);">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title" id="modalLabelPrestasi"><i class="fa fa-plus-circle" style="color:var(--p-green)"></i> Prestasi Baru</h4>
                </div>

                <div class="modal-body">
                  <form action="prestasi_act.php" method="post" id="formPrestasi" autocomplete="off">
                    <?php echo epoin_csrf_field(); ?>
                    <div class="form-group">
                      <label>Nama Prestasi</label>
                      <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-trophy" style="color:var(--p-green)"></i></span>
                        <input type="text" class="form-control" name="nama" required placeholder="Masukkan Nama Prestasi..">
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Poin</label>
                      <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-hashtag" style="color:var(--p-green)"></i></span>
                        <input type="number" class="form-control" name="point" required min="1" step="1" placeholder="Masukkan Jumlah Poin..">
                        <span class="input-group-addon" style="font-weight:700; color:#065f46;">Poin</span>
                      </div>
                    </div>
                    <div class="form-group">
                      <button type="button" class="btn btn-sm btn-danger" data-dismiss="modal">Batal</button>
                      <input type="submit" class="btn btn-sm btn-primary" value="Simpan">
                    </div>
                  </form>
                </div>

              </div>
            </div>
          </div>
          <!-- /Modal -->

          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="table-datatable">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>NAMA PRESTASI</th>
                    <th>POIN</th>
                    <th width="12%">
                      OPSI
                      <?php if (_is_admin()): ?>
                        <span class="opsi-head-badge admin" data-toggle="tooltip" title="Edit & Hapus tersedia">
                          <i class="fa fa-unlock"></i> Admin
                        </span>
                      <?php else: ?>
                        <span class="opsi-head-badge lock" data-toggle="tooltip" title="Hanya admin yang dapat Edit & Hapus">
                          <i class="fa fa-lock"></i> Hanya Admin
                        </span>
                      <?php endif; ?>
                    </th>
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
                      <td class="prestasi-name"><?php echo htmlspecialchars($d['prestasi_nama']); ?></td>
                      <td class="text-center">
                        <!-- tampilkan angka saja tanpa kata 'Point' -->
                        <span class="badge-point" title="Point prestasi"><?php echo (int)$d['prestasi_point']; ?></span>
                      </td>
                      <td>
                        <?php if (_is_admin()): ?>
                          <a class="btn btn-warning btn-sm" href="prestasi_edit.php?id=<?php echo $d['prestasi_id'] ?>" data-toggle="tooltip" title="Edit">
                            <i class="fa fa-cog"></i>
                          </a>
                          <form method="post" action="prestasi_hapus.php" style="display:inline" onsubmit="return confirm('Data yang terhubung akan ikut dihapus. Yakin ingin menghapus?');">
                            <input type="hidden" name="id" value="<?php echo (int)$d['prestasi_id']; ?>">
                            <?php echo epoin_csrf_field(); ?>
                            <button type="submit" class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus">
                              <i class="fa fa-trash"></i>
                            </button>
                          </form>
                        <?php else: ?>
                          <span class="text-muted" data-toggle="tooltip" title="Aksi khusus admin">
                            <i class="fa fa-lock"></i> —
                          </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php } ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<!-- ======= SCRIPT: DataTables & sort + tooltip + modal fix ======= -->
<script>
  $(function () {
    // Pastikan modal berada langsung di <body> agar tak ketutup elemen lain
    var $modal = $('#modal_prestasi');
    if ($modal.length) {
      $modal.appendTo('body');
      $modal.on('shown.bs.modal', function () {
        var $inputs = $(this).find('input,select,textarea');
        // jaga-jaga jika ada script lain yg pernah men-disable
        $inputs.prop('disabled', false).prop('readonly', false);
        $(this).find('input[name="nama"]').focus();
      });
    }

    // DataTables errors to console
    if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }

    var $tbl = $('#table-datatable');

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
      try { $tbl.DataTable().destroy(); } catch(e){}
      $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
    }

    var t = null;
    if ($.fn.DataTable) {
      t = $tbl.DataTable({
        destroy: true,
        responsive: true,
        autoWidth: false,
        /* ====== REVISI 2: default urutkan POIN terendah dulu ====== */
        order: [[2, 'asc']],
        columnDefs: [
          { targets: [0,3], orderable: false },
          {
            targets: 2,
            className: 'text-center',
            type: 'num',
            render: function (data, type) {
              if (type === 'sort' || type === 'type') {
                var m = String(data).replace(/<[^>]*>/g,'').match(/-?\d+/);
                return m ? parseInt(m[0], 10) : 0;
              }
              return data;
            }
          }
        ],
        language: {
          search: "Search:",
          lengthMenu: "Tampilkan _MENU_ data",
          info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
          infoEmpty: "Tidak ada data",
          zeroRecords: "Tidak ditemukan data yang cocok",
          paginate: { first:"Pertama", last:"Terakhir", next:"›", previous:"‹" }
        }
      });

      // nomor urut dinamis
      t.on('order.dt search.dt', function () {
        var i = 1;
        t.column(0, { search:'applied', order:'applied' })
         .nodes().each(function (cell) { cell.innerHTML = i++; });
      }).draw();

      // kontrol dropdown sort
      $('#sort-control').on('change', function () {
        var v = $(this).val();
        if (v === 'nama_asc')       t.order([1, 'asc']).draw();
        else if (v === 'nama_desc') t.order([1, 'desc']).draw();
        else if (v === 'poin_asc')  t.order([2, 'asc']).draw();
        else if (v === 'poin_desc') t.order([2, 'desc']).draw();
      });
    }

    // Tooltip init aman
    function initTips(){ if ($.fn.tooltip){ $('[data-toggle="tooltip"]').tooltip({container:'body'}); } }
    $(initTips);
    $(document).on('mouseover','[data-toggle="tooltip"]', initTips);
  });
</script>

<?php include 'footer.php'; ?>
