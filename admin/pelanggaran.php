<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>

<?php
// ==== Fallback helper roles (sama seperti Prestasi) ====
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

// ==== Hitung total pelanggaran utk badge header ====
if (function_exists('count_table')) {
  $total_pelanggaran = count_table($koneksi, 'pelanggaran');
} else {
  $qcp = mysqli_query($koneksi, "SELECT COUNT(*) AS jml FROM pelanggaran");
  $total_pelanggaran = ($qcp && ($r=mysqli_fetch_assoc($qcp))) ? (int)$r['jml'] : 0;
}
?>

<!-- ===== THEME: Pelanggaran (Merah) ===== -->
<style>
  :root{
    --v-red-700:#b91c1c;   /* red-700 */
    --v-red-600:#dc2626;   /* red-600 */
    --v-red-500:#ef4444;   /* red-500 */
    --v-red-400:#f87171;   /* red-400 */
    --v-red-300:#fca5a5;   /* red-300 */
    --v-red-200:#fecaca;   /* red-200 */
    --v-red-100:#fee2e2;   /* red-100 */
    --v-red-50: #fef2f2;   /* red-50  */

    /* REVISI: palet zebra + header yang lebih soft */
    --tbl-odd-soft: #fff9f9;          /* lebih pucat dari red-50 (lebih dekat putih) */
    --tbl-head-start: #fff7f7;        /* gradasi header (atas) */
    --tbl-head-end:   #ffe8e8;        /* gradasi header (bawah), sedikit lebih muncul */
    --tbl-head-text:  #7f1d1d;        /* teks header merah gelap agar kontras */
    --tbl-head-border: rgba(239,68,68,.5);  /* garis bawah header sedikit lebih tegas */
  }

  /* Animasi halaman saat masuk */
  .content-wrapper{ animation: fadeLift 520ms ease-out both; }
  @keyframes fadeLift{ 0%{opacity:0;transform:translateY(10px);} 100%{opacity:1;transform:translateY(0);} }

  /* Modal fix supaya bisa diketik */
  .modal { z-index:1050; }
  .modal-backdrop { z-index:1040; }
  #modal_pelanggaran .modal-content{ position:relative; z-index:1060; }
  #modal_pelanggaran input.form-control{ background:#fff; pointer-events:auto; }

  /* Header box */
  .box .box-header{
    border-bottom:0;
    background:linear-gradient(90deg, var(--v-red-50), #fff);
    border-top:3px solid var(--v-red-600);
  }

  /* Title header */
  .title-wrap{ display:flex; align-items:center; gap:10px; }
  .badge-total{
    background:linear-gradient(90deg, var(--v-red-400), var(--v-red-600));
    color:#fff; border-radius:999px; padding:3px 10px; font-weight:700; font-size:12px;
    box-shadow:0 2px 8px rgba(220,38,38,.25);
  }

  /* Sembunyikan subjudul kecil di header box bila perlu */
  .hide-subtitle{ display:none !important; }

  /* Tombol tambah */
  .btn-add{
    background:linear-gradient(90deg, var(--v-red-400), var(--v-red-600));
    border-color:var(--v-red-700); color:#fff;
    transition:transform .08s, box-shadow .2s, filter .2s;
  }
  .btn-add:hover{ filter:saturate(1.1); transform:translateY(-1px); box-shadow:0 6px 16px rgba(220,38,38,.25); }

  /* Non-admin (disable) */
  .btn-disabled{ background:#e5e7eb!important; border-color:#d1d5db!important; color:#6b7280!important; cursor:not-allowed; filter:grayscale(.2); }

  /* Zebra table + garis + hover merah lembut */
  #table-datatable { border-collapse:separate; border-spacing:0; }

  /* REVISI HEADER: beri latar merah soft yang sedikit lebih muncul + garis lebih tegas */
  #table-datatable.table > thead > tr > th{
    background: linear-gradient(180deg, var(--tbl-head-start) 0%, var(--tbl-head-end) 100%) !important;
    color: var(--tbl-head-text);
    border-bottom: 2px solid var(--tbl-head-border) !important;
  }

  #table-datatable.table>tbody>tr>td{ border-bottom:1px solid rgba(239,68,68,.22)!important; }

  /* REVISI: baris ganjil merah lebih soft (dipucatkan), genap putih */
  #table-datatable.table>tbody>tr:nth-child(odd)>td{ background: var(--tbl-odd-soft); }
  #table-datatable.table>tbody>tr:nth-child(even)>td{ background: #fff; }

  #table-datatable.table>tbody>tr:hover>td{
    background:linear-gradient(90deg, var(--v-red-100), var(--v-red-50))!important;
    box-shadow:inset 0 0 0 9999px rgba(239,68,68,.06);
  }

  /* Nama pelanggaran */
  .nama-item{ font-weight:600; color:#1f2937; }

  /* Badge poin (merah) */
  .badge-point{
    display:inline-block; min-width:82px; text-align:center;
    background:linear-gradient(90deg, var(--v-red-200), var(--v-red-500));
    color:#5b0a0a; border-radius:999px; padding:4px 12px; font-weight:700;
    box-shadow:inset 0 0 0 1px rgba(239,68,68,.25), 0 2px 8px rgba(239,68,68,.18);
  }

  /* Badge header pada kolom OPSI */
  .opsi-head-badge{
    display:inline-flex; align-items:center; gap:6px;
    border-radius:999px; padding:2px 8px; font-size:11px; font-weight:700; letter-spacing:.2px;
  }
  .opsi-head-badge.admin{ background:linear-gradient(90deg,#ffe4e6,#fecaca); color:#7f1d1d; border:1px solid rgba(239,68,68,.3); }
  .opsi-head-badge.lock{  background:linear-gradient(90deg,#fff7ed,#ffedd5); color:#7c2d12; border:1px solid rgba(194,65,12,.25); }

  /* Aksi tombol */
  .btn-warning.btn-sm{
    background:linear-gradient(90deg,#f59e0b,#d97706);
    border-color:#b45309; color:#fff; transition:transform .08s, box-shadow .2s, filter .2s;
  }
  .btn-warning.btn-sm:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(217,119,6,.25); filter:saturate(1.05); }

  .btn-danger.btn-sm{
    background:linear-gradient(90deg,#ef4444,#dc2626);
    border-color:#b91c1c; color:#fff; transition:transform .08s, box-shadow .2s, filter .2s;
  }
  .btn-danger.btn-sm:hover{ transform:translateY(-1px); box-shadow:0 6px 14px rgba(220,38,38,.25); filter:saturate(1.05); }

  .tooltip-inner{ font-weight:600; }

  /* Mobile polish */
  @media (max-width:768px){
    .title-wrap small{ display:none; }
    .badge-total{ font-size:11px; padding:2px 8px; }
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
      <span><i class="fa fa-gavel" style="color:var(--v-red-600)"></i> Pelanggaran</span>
      <small class="badge-total" title="Total jenis pelanggaran"><?php echo (int)$total_pelanggaran; ?> jenis</small>
      <small style="color:#64748b;">Data Pelanggaran</small>
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
              <!-- Subjudul kecil bisa disembunyikan agar rapi -->
              <h3 class="box-title hide-subtitle" style="margin:0">Pelanggaran</h3>
            </div>

            <div class="btn-group pull-right" style="margin-left:10px;">
              <?php if (_is_admin()): ?>
                <!-- ADMIN: tombol aktif -->
                <button type="button" class="btn btn-add btn-sm" data-toggle="modal" data-target="#modal_pelanggaran" id="btnAddPelanggaran">
                  <i class="fa fa-plus"></i> &nbsp;Tambah Pelanggaran Baru
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_import_pelanggaran">
                  <i class="fa fa-file-excel-o"></i> &nbsp;Import Excel
                </button>
                <form method="post" action="kategori_import_act.php" style="display:inline" onsubmit="return confirm('Tambahkan daftar pelanggaran rekomendasi (referensi dari EPOIN) ke daftar Anda? Nama yang sudah ada akan dilewati.');">
                  <?php echo epoin_csrf_field(); ?>
                  <input type="hidden" name="jenis" value="pelanggaran">
                  <input type="hidden" name="aksi" value="muat_rekomendasi">
                  <button type="submit" class="btn btn-default btn-sm" data-toggle="tooltip" title="Tanam daftar referensi kurasi EPOIN"><i class="fa fa-magic"></i> &nbsp;Muat Rekomendasi</button>
                </form>
                <form method="post" action="kategori_import_act.php" style="display:inline" onsubmit="return confirm('Hapus SEMUA kategori pelanggaran yang BELUM terpakai? Kategori yang sudah dipakai pada data poin siswa tetap dipertahankan.');">
                  <?php echo epoin_csrf_field(); ?>
                  <input type="hidden" name="jenis" value="pelanggaran">
                  <input type="hidden" name="aksi" value="hapus_semua">
                  <button type="submit" class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus semua kategori yang belum terpakai"><i class="fa fa-trash"></i> &nbsp;Hapus Semua</button>
                </form>
              <?php else: ?>
                <!-- NON-ADMIN: tombol non-aktif -->
                <button type="button" class="btn btn-add btn-sm btn-disabled" data-toggle="tooltip" title="Hanya admin yang dapat menambah pelanggaran" disabled>
                  <i class="fa fa-lock"></i> &nbsp;Tambah Pelanggaran Baru
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
                  <option value="poin_asc" selected>Urut Poin (Rendah → Tinggi)</option> <!-- REVISI: default selected -->
                  <option value="poin_desc">Urut Poin (Tinggi → Rendah)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Modal Import Excel -->
          <div class="modal fade" id="modal_import_pelanggaran" tabindex="-1" role="dialog">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(90deg,#dbeafe,#fff); border-bottom:1px solid #bfdbfe;">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><i class="fa fa-file-excel-o" style="color:#2563eb"></i> Import Pelanggaran dari Excel</h4>
                </div>
                <form action="kategori_import_act.php" method="post" enctype="multipart/form-data" autocomplete="off">
                  <div class="modal-body">
                    <?php echo epoin_csrf_field(); ?>
                    <input type="hidden" name="jenis" value="pelanggaran">
                    <input type="hidden" name="aksi" value="import_excel">
                    <ol style="padding-left:18px; color:#334155; font-size:13px;">
                      <li>Unduh template dulu:
                        <a href="kategori_template.php?jenis=pelanggaran" class="btn btn-xs btn-default"><i class="fa fa-download"></i> Template Excel</a>
                      </li>
                      <li>Isi kolom <b>A = Nama Kategori</b>, <b>B = Poin</b> (angka positif; tanda minus otomatis). Baris 1 judul.</li>
                      <li>Simpan lalu unggah di sini. Nama yang sudah ada otomatis dilewati.</li>
                    </ol>
                    <div class="form-group">
                      <label>Pilih file (.xlsx / .xls / .csv, maks 5 MB)</label>
                      <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Unggah &amp; Import</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Modal Tambah -->
          <div class="modal fade" id="modal_pelanggaran" tabindex="-1" role="dialog" aria-labelledby="modalLabelPelanggaran">
            <div class="modal-dialog">
              <div class="modal-content">

                <div class="modal-header" style="background:linear-gradient(90deg,var(--v-red-200),#fff); border-bottom:1px solid var(--v-red-200);">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title" id="modalLabelPelanggaran"><i class="fa fa-plus-circle" style="color:var(--v-red-600)"></i> Pelanggaran Baru</h4>
                </div>

                <div class="modal-body">
                  <form action="pelanggaran_act.php" method="post" id="formPelanggaran" autocomplete="off">
                    <?php echo epoin_csrf_field(); ?>
                    <div class="form-group">
                      <label>Nama Pelanggaran</label>
                      <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-exclamation-triangle" style="color:var(--v-red-600)"></i></span>
                        <input type="text" class="form-control" name="nama" required placeholder="Masukkan Nama Pelanggaran..">
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Point</label>
                      <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-hashtag" style="color:var(--v-red-600)"></i></span>
                        <input type="number" class="form-control" name="point" required min="1" step="1" placeholder="Masukkan Jumlah Point..">
                        <span class="input-group-addon" style="font-weight:700; color:#7f1d1d;">Point</span>
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
                    <th>NAMA PELANGGARAN</th>
                    <th>POIN</th> <!-- REVISI: ganti POINT -> POIN -->
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
                  $data = mysqli_query($koneksi,"SELECT * FROM pelanggaran");
                  while($d = mysqli_fetch_array($data)){
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td class="nama-item"><?php echo htmlspecialchars($d['pelanggaran_nama']); ?></td>
                      <td class="text-center">
                        <!-- REVISI: tampil angka saja, hilangkan teks "Point" -->
                        <span class="badge-point" title="Poin pelanggaran"><?php echo (int)$d['pelanggaran_point']; ?></span>
                      </td>
                      <td>
                        <?php if (_is_admin()): ?>
                          <!-- ADMIN: boleh edit & hapus -->
                          <a class="btn btn-warning btn-sm" href="pelanggaran_edit.php?id=<?php echo $d['pelanggaran_id'] ?>" data-toggle="tooltip" title="Edit">
                            <i class="fa fa-cog"></i>
                          </a>
                          <form method="post" action="pelanggaran_hapus.php" style="display:inline" onsubmit="return confirm('Data yang terhubung akan ikut dihapus. Yakin ingin menghapus?');">
                            <input type="hidden" name="id" value="<?php echo (int)$d['pelanggaran_id']; ?>">
                            <?php echo epoin_csrf_field(); ?>
                            <button type="submit" class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus">
                              <i class="fa fa-trash"></i>
                            </button>
                          </form>
                        <?php else: ?>
                          <!-- NON-ADMIN: sembunyikan aksi -->
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

<!-- ======= SCRIPT: DataTables + sort numerik + tooltip + modal fix ======= -->
<script>
  $(function () {
    // Pastikan modal berada langsung di <body> agar tidak tertutup layer lain
    var $modal = $('#modal_pelanggaran');
    if ($modal.length) {
      $modal.appendTo('body');
      $modal.on('shown.bs.modal', function () {
        var $inputs = $(this).find('input,select,textarea');
        $inputs.prop('disabled', false).prop('readonly', false); // jaga-jaga
        $(this).find('input[name="nama"]').focus();
      });
    }

    // DataTables errors ke console
    if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }

    var $tbl = $('#table-datatable');

    // jika sudah pernah init, destroy dulu
    if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
      try { $tbl.DataTable().destroy(); } catch(e){}
      $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
    }

    if (!$.fn.DataTable) return;

    // Kolom: 0=NO, 1=Nama, 2=Poin, 3=Opsi
    var t = $tbl.DataTable({
      destroy: true,
      responsive: true,
      autoWidth: false,
      order: [[2, 'asc']], // REVISI: default urut POIN terendah dulu
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

    // Penomoran kolom NO mengikuti sort & search
    t.on('order.dt search.dt', function () {
      var i = 1;
      t.column(0, { search:'applied', order:'applied' })
       .nodes().each(function (cell) { cell.innerHTML = i++; });
    }).draw();

    // Dropdown kontrol sort
    $('#sort-control').on('change', function () {
      var v = $(this).val();
      if (v === 'nama_asc')       t.order([1, 'asc']).draw();
      else if (v === 'nama_desc') t.order([1, 'desc']).draw();
      else if (v === 'poin_asc')  t.order([2, 'asc']).draw();
      else if (v === 'poin_desc') t.order([2, 'desc']).draw();
    });

    // Tooltip init
    function initTips(){ if ($.fn.tooltip){ $('[data-toggle="tooltip"]').tooltip({container:'body'}); } }
    $(initTips);
    $(document).on('mouseover','[data-toggle="tooltip"]', initTips);
  });
</script>

<?php include 'footer.php'; ?>
