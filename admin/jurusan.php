<?php
// Pastikan koneksi & session dari header sudah jalan
include 'header.php';

// Pastikan variabel $koneksi tersedia dari header.php
if (!isset($koneksi)) {
  die('Koneksi database ($koneksi) tidak ditemukan. Pastikan header.php men-define $koneksi.');
}

// --- CSRF Token ---
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Helper escape
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Ambil data jurusan
$result = mysqli_query($koneksi, "SELECT jurusan_id, jurusan_nama FROM jurusan ORDER BY jurusan_nama ASC");
$rows = [];
if ($result) {
  while ($r = mysqli_fetch_assoc($result)) { $rows[] = $r; }
}
$total = count($rows);
?>

<!-- DataTables (tanpa Buttons) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap.min.css">

<style>
  /* =========================
     THEME: Soft Blue Dashboard
     ========================= */
  :root{
    /* Warna dasar */
    --blue-50:  #f0f6ff;
    --blue-100: #e3efff;
    --blue-200: #cfe3ff;
    --blue-300: #b9d6ff;
    --blue-400: #8fbaff;
    --blue-500: #4f9cf9;
    --blue-600: #2d6cdf;  /* Aksen */
    --blue-700: #1f5ac8;

    --ink-900:  #0b1220;  /* judul */
    --ink-800:  #1e293b;  /* teks umum */
    --ink-700:  #334155;  /* sekunder */
    --line:     #dbe5ff;  /* border lembut */

    --bg-page:  linear-gradient(180deg, #f8fbff 0%, #f3f7ff 100%);
    --bg-card:  #ffffff;
    --bg-row:   #f8fbff;
    --bg-hover: #eef4ff;
    --shadow:   0 10px 30px rgba(45,108,223,.12);
    --radius-lg: 16px;

    /* Tombol */
    --btn-grad: linear-gradient(90deg, var(--blue-600), var(--blue-500));
    --btn-grad-hover: linear-gradient(90deg, var(--blue-700), var(--blue-600));

    /* Aksi */
    --btn-edit: #f59e0b;
    --btn-del:  #ef4444;
  }

  /* Font stack modern */
  html, body, .content-wrapper{
    font-family: Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
    color: var(--ink-800);
  }

  .content-wrapper{ background: var(--bg-page); }

  /* ====== Animasi judul (sesuai contoh) ====== */
  @keyframes textFade{
    from{ opacity:0; transform: translateY(6px); }
    to  { opacity:1; transform: translateY(0); }
  }

  /* ===== Header halaman ===== */
  .content-header{
    margin-bottom: 8px;
  }
  .content-header h1{
    color: var(--ink-900);
    display:flex; align-items:center; gap:12px;
    animation: textFade .6s ease-out both;
    letter-spacing:.2px;
  }
  .title-icon{
    width:40px; height:40px; border-radius:12px;
    display:inline-flex; align-items:center; justify-content:center;
    background: var(--blue-100);
    color: var(--blue-600);
    box-shadow: inset 0 0 0 1px var(--line);
  }
  .title-badge{
    background: var(--blue-50);
    border: 1px solid var(--line);
    color: var(--ink-700);
    border-radius:999px;
    padding:4px 10px;
    display:inline-flex; align-items:center; gap:6px;
    font-weight:600;
    font-size: clamp(9px, 2.2vw, 11px);
    white-space: nowrap;
  }
  .title-badge i{ color: var(--blue-600); }

  .breadcrumb > li + li:before{ content:"› "; color:var(--ink-700); }

  /* ===== Box modern ===== */
  .box{
    border:0;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow:hidden;
    position: relative;
  }
  .box:before{
    content:"";
    position:absolute; inset:0 0 auto 0; height:4px;
    background: linear-gradient(90deg, var(--blue-600), var(--blue-400), var(--blue-600));
    opacity:.85;
  }
  .box-header.with-border{
    background: var(--bg-card);
    color: var(--ink-900);
    border-bottom:1px solid var(--line);
    padding:14px 18px;
  }
  .box-header .box-title{
    display:flex; align-items:center; gap:8px;
    font-weight:700;
  }
  .box-header .box-title i{ color: var(--blue-600); }

  /* ===== Badge & Button bar ===== */
  .pill-total{
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 10px; border-radius:999px;
    background: var(--blue-50);
    color: var(--ink-700);
    border:1px solid var(--line);
    font-weight:600; font-size:12px;
    white-space: nowrap;
  }
  .pill-total i{ color: var(--blue-600); }

  .btn-add{
    background: var(--btn-grad);
    color:#fff; border:0; border-radius:12px; padding:10px 14px;
    display:inline-flex; align-items:center; gap:8px; font-weight:700;
    box-shadow: 0 8px 20px rgba(45,108,223,.25);
    transition: transform .15s ease, filter .2s ease, box-shadow .2s ease;
  }
  .btn-add:hover{ filter:brightness(1.06); transform: translateY(-1px); background: var(--btn-grad-hover); }
  .btn-add i{ font-size:14px; }

  /* ===== Tabel (tetap teks hitam) ===== */
  .table-compact>thead>tr>th,
  .table-compact>tbody>tr>td{
    padding:10px 12px !important;
    vertical-align: middle;
    white-space: nowrap;
  }
  #table-tingkat{
    table-layout:auto;
    border-color: var(--line);
    background: var(--bg-card);
  }
  #table-tingkat thead th{
    background: linear-gradient(180deg, #f7faff 0%, #f1f6ff 100%);
    color: var(--ink-800);
    border-bottom: 1px solid var(--line) !important;
    text-transform: none; letter-spacing:.2px;
  }
  #table-tingkat tbody tr:nth-child(odd){ background: #fff; }
  #table-tingkat tbody tr:nth-child(even){ background: var(--bg-row); }
  #table-tingkat tbody tr{
    transition: background .18s ease, box-shadow .18s ease;
  }
  #table-tingkat tbody tr:hover{
    background: var(--bg-hover) !important;
    box-shadow: inset 3px 0 0 0 var(--blue-500);
    cursor: pointer;
  }
  #table-tingkat tbody td{ color: #000; }  /* penting: teks hitam */

  .table-hint{ color:#6b7a90; font-size:12px; }

  /* ===== Aksi tombol kecil (general) ===== */
  .btn-group .btn{
    border:0;
    display:inline-flex; align-items:center; justify-content:center;
    gap:6px; font-weight:700;
  }
  .btn-edit{ background: var(--btn-edit); color:#fff; border-radius:10px; }
  .btn-del { background: var(--btn-del);  color:#fff; border-radius:10px; }
  .btn-edit:hover,.btn-del:hover{ filter: brightness(1.05); transform: translateY(-1px); }

  /* ====== REVISI: Tombol Opsi menyatu (combo pill) & selalu 1 baris ====== */
  td.col-opsi{ white-space: nowrap !important; }
  td.col-opsi .btn-group{
    display:inline-flex; align-items:stretch; flex-wrap:nowrap;
    gap:0;                          /* tidak ada jarak */
    overflow:hidden;                /* sudut anak terpotong rapi */
    border-radius:9999px;           /* bentuk pil */
    box-shadow:0 6px 14px rgba(0,0,0,.08);
  }
  /* hilangkan margin bawaan bootstrap di tombol setelah tombol */
  td.col-opsi .btn-group>.btn + .btn{ margin-left:0 !important; }
  /* matikan float & radius anak agar benar2 nempel */
  td.col-opsi .btn-group .btn{
    float:none; margin:0 !important; border:0; border-radius:0 !important;
    line-height:1; padding:6px 10px; display:inline-flex; align-items:center; justify-content:center;
  }
  /* opsional: radius khusus ujung kiri/kanan (tidak wajib karena container sudah overflow:hidden) */
  td.col-opsi .btn-group .btn:first-child{ border-radius:9999px 0 0 9999px !important; }
  td.col-opsi .btn-group .btn:last-child { border-radius:0 9999px 9999px 0 !important; }

  /* ===== Modal ===== */
  .modal-content{
    border-radius: 16px; overflow:hidden; border:0;
    box-shadow: var(--shadow);
  }
  .modal-header{
    background: linear-gradient(180deg, var(--blue-50), #fff);
    color: var(--ink-900);
    border-bottom:1px solid var(--line) !important;
  }
  .modal-title i{ color: var(--blue-600); }
  .modal-body .form-control{
    border-radius:12px; border:1px solid var(--line);
    box-shadow: none;
  }
  .modal-body .form-control:focus{ border-color: var(--blue-500); box-shadow: 0 0 0 3px rgba(79,156,249,.15); }
  .modal-footer{ border-top:1px solid var(--line) !important; }

  /* ===== DataTables UI polish ===== */
  .dataTables_wrapper .dataTables_filter label{
    font-weight:700; color: var(--ink-700);
  }
  .dataTables_wrapper .dataTables_filter input{
    border:1px solid var(--line) !important;
    background:#fff;
    border-radius:999px !important;
    padding:8px 12px !important;
    outline:none;
  }
  .dataTables_wrapper .dataTables_length select{
    border:1px solid var(--line) !important;
    border-radius:10px; padding:6px 10px;
    background:#fff;
  }
  .dataTables_wrapper .dataTables_paginate .paginate_button{
    border-radius:10px !important; border:0 !important;
    background: #fff !important; color: var(--ink-800) !important;
    box-shadow: inset 0 0 0 1px var(--line);
    margin:0 2px !important;
  }
  .dataTables_wrapper .dataTables_paginate .paginate_button.current{
    background: var(--blue-600) !important; color:#fff !important; box-shadow:none;
  }
  .dataTables_wrapper .dataTables_info{ color: var(--ink-700); }

  /* Mobile tweak */
  @media (max-width: 480px){
    .pill-total{ margin-top:8px; display:block; }
    .box-header.with-border{ padding:12px; }
  }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      <span class="title-icon"><i class="fa fa-graduation-cap"></i></span>
      <b>Tingkat Kelas / Jurusan</b>
      <small class="title-badge"><i class="fa fa-list-ul"></i> Data Master</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Tingkat Kelas / Jurusan</li>
    </ol>
  </section>

  <section class="content">

    <!-- Flash message -->
    <div class="row">
      <div class="col-lg-12">
        <?php if (!empty($_SESSION['flash_success'])): ?>
          <div class="alert alert-success alert-dismissible" style="border-radius:12px;">
            <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            <i class="fa fa-check-circle"></i> <?php echo e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash_error'])): ?>
          <div class="alert alert-danger alert-dismissible" style="border-radius:12px;">
            <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            <i class="fa fa-exclamation-triangle"></i> <?php echo e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="row">
      <section class="col-lg-12">
        <div class="box">

          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-list-ul"></i> Daftar Tingkat Kelas / Jurusan</h3>

            <div class="pull-right" style="display:flex; gap:10px; align-items:center;">
              <span class="pill-total" title="Total baris">
                <i class="fa fa-database"></i> Total Data: <?php echo (int)$total; ?>
              </span>
              <button type="button" class="btn btn-add" data-toggle="modal" data-target="#modal_jurusan">
                <i class="fa fa-plus"></i> Tambah Data
              </button>
            </div>
          </div>

          <!-- Modal Tambah -->
          <div class="modal fade" id="modal_jurusan" tabindex="-1" role="dialog" aria-labelledby="modal_jurusan_label" aria-hidden="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">

                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true" style="color:#0f172a;">&times;</span>
                  </button>
                  <h4 class="modal-title" id="modal_jurusan_label">
                    <i class="fa fa-plus-circle"></i> Tambah Tingkat Kelas / Jurusan
                  </h4>
                </div>

                <div class="modal-body">
                  <form id="form-tambah-jurusan" action="jurusan_act.php" method="post" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">

                    <div class="form-group has-feedback">
                      <label for="jurusan_nama">Tingkat Kelas / Jurusan</label>
                      <input type="text"
                             class="form-control"
                             id="jurusan_nama"
                             name="nama"
                             required
                             minlength="2"
                             maxlength="100"
                             placeholder="Contoh: Kelas 7 / XI RPL / Desain Komunikasi Visual">
                      <span class="glyphicon glyphicon-education form-control-feedback" aria-hidden="true"></span>
                      <p class="help-block" style="color:#6b7a90;">Gunakan format yang konsisten agar mudah dicari.</p>
                    </div>
                  </form>
                </div>

                <div class="modal-footer" style="display:flex; gap:8px; justify-content:flex-end;">
                  <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius:10px;">
                    <i class="fa fa-times"></i> Batal
                  </button>
                  <button type="submit" form="form-tambah-jurusan" class="btn btn-add" style="padding:8px 14px;">
                    <i class="fa fa-save"></i> Simpan
                  </button>
                </div>

              </div>
            </div>
          </div>
          <!-- /Modal Tambah -->

          <?php if (!$result): ?>
            <div class="box-body">
              <div class="alert alert-danger" style="border-radius:12px;">
                Gagal memuat data: <?php echo e(mysqli_error($koneksi)); ?>
              </div>
            </div>
          <?php else: ?>
          <div class="box-body">
            <div class="row" style="margin-bottom:10px;">
              <div class="col-sm-6">
                <em class="table-hint"><i class="fa fa-info-circle" style="color:var(--blue-600)"></i> Gunakan kolom pencarian di tabel untuk menemukan data cepat.</em>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-compact nowrap" id="table-tingkat" style="width:100%; border-color:var(--line);">
                <thead>
                  <tr>
                    <th style="width:1%;">No</th>
                    <th>Tingkat Kelas / Jurusan</th>
                    <th style="width:14%;">Opsi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no = 1; foreach ($rows as $d): ?>
                  <tr>
                    <td><?php echo $no++; ?></td>
                    <td>
                      <span class="label" style="background:var(--blue-100); color:var(--ink-800); border:1px solid var(--line); border-radius:8px; padding:2px 6px; margin-right:6px;">
                        <i class="fa fa-tag" style="color:var(--blue-600)"></i>
                      </span>
                      <?php echo e($d['jurusan_nama']); ?>
                    </td>
                    <td class="col-opsi">
                      <div class="btn-group" role="group" aria-label="Opsi">
                        <a class="btn btn-edit btn-sm" data-toggle="tooltip" title="Edit data"
                           href="jurusan_edit.php?id=<?php echo (int)$d['jurusan_id']; ?>">
                          <i class="fa fa-pencil"></i>
                        </a>
                        <a class="btn btn-del btn-sm btn-hapus" data-toggle="tooltip" title="Hapus data"
                           href="jurusan_hapus.php?id=<?php echo (int)$d['jurusan_id']; ?>"
                           data-nama="<?php echo e($d['jurusan_nama']); ?>">
                          <i class="fa fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </section>
    </div>
  </section>

</div>

<!-- JS DataTables (tanpa Buttons) -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
(function() {
  // Inisialisasi DataTable (tanpa tombol export)
  var dt = $('#table-tingkat').DataTable({
    responsive: true,
    order: [[1, 'asc']],
    pageLength: 10,
    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
    dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" + "rt" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
    language: {
      search: "Cari:",
      lengthMenu: "Tampil _MENU_ data",
      zeroRecords: "Tidak ada data",
      info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
      infoEmpty: "Tidak ada data",
      infoFiltered: "(difilter dari total _MAX_ data)",
      paginate: { previous: "Sebelumnya", next: "Berikutnya" }
    }
  });

  // Tooltip untuk tombol aksi
  $('[data-toggle="tooltip"]').tooltip();

  // Konfirmasi hapus
  $(document).on('click', '.btn-hapus', function(e) {
    var nama = $(this).data('nama') || 'data ini';
    if (!confirm('Anda yakin ingin menghapus "' + nama + '"?\nData terkait mungkin ikut terhapus.')) {
      e.preventDefault();
    }
  });

  // Validasi ringan sisi klien
  $('#form-tambah-jurusan').on('submit', function(e) {
    var v = ($('#jurusan_nama').val() || '').trim();
    if (v.length < 2) {
      e.preventDefault();
      alert('Nama minimal 2 karakter.');
      $('#jurusan_nama').focus();
    }
  });
})();
</script>

<?php include 'footer.php'; ?>
