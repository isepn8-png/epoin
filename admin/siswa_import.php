<?php
// ===================
// Import Data Siswa - Halaman Upload (Final UI Polished + Contrast + Shimmer + Badge + Tooltip)
// Diperbarui: 10 Sep 2025
// Catatan: Tidak mengubah flow/fungsi import; hanya perapihan UI/UX.
// ===================

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'header.php';

// Pastikan koneksi tersedia
if (!isset($koneksi) || !$koneksi) {
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger">Koneksi database ($koneksi) tidak tersedia. Pastikan include/konfigurasi database sudah benar.</div></section></div>';
  include 'footer.php';
  exit;
}
?>
<style>
  /* ====== THEME POLISH & ACCESSIBLE CONTRAST ====== */
  .page-title-wrap {
    position: relative;
    background: linear-gradient(90deg, #1e88e5, #42a5f5);
    color: #fff;
    border-radius: 14px;
    padding: 18px 22px;
    margin-bottom: 18px;
    box-shadow: 0 10px 24px rgba(30,136,229,.18);
    overflow: hidden;
  }
  .page-title-wrap h1 {
    margin: 0; font-weight: 800; letter-spacing: .2px;
    text-shadow: 0 1px 2px rgba(0,0,0,.15);
  }
  .page-title-wrap small { color: #eaf4ff; font-weight: 600; }
  .page-title-wrap .note { color: #f0f7ff; font-weight: 500; }

  /* Shimmer effect (ringan) */
  .page-title-wrap::after {
    content: "";
    position: absolute;
    top: -40%;
    left: -20%;
    width: 40%;
    height: 200%;
    transform: rotate(25deg);
    background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.35) 50%, rgba(255,255,255,0) 100%);
    animation: shimmer 2.6s infinite;
  }
  @keyframes shimmer {
    0%   { left: -40%; }
    60%  { left: 120%; }
    100% { left: 120%; }
  }

  .box-primary { border-top: 3px solid #1e88e5; border-radius: 10px; overflow: hidden; }
  .box .box-header.with-border { border-bottom: 1px solid #eef2f7; }
  .box .box-header .box-title { font-weight: 800; }
  .box .box-header .box-title i { margin-right: 6px; }

  .helper-badge {
    display: inline-block;
    background: #e3f2fd;
    color: #0d47a1;
    border: 1px solid #90caf9;
    border-radius: 999px;
    padding: 2px 10px;
    font-size: 12px;
    margin-left: 6px;
    font-weight: 700;
  }

  /* ALERT INFO: pastikan teks kontras & terbaca (no low-contrast) */
  .alert-info {
    background: #e8f2ff; /* solid sebagai dasar */
    background-image: linear-gradient(180deg, #e3f2fd, #f3f8ff);
    border: 1px solid #90caf9;
    color: #0b3c91; /* kontras default */
    font-weight: 600;
  }
  /* Teks di dalam alert yang harus benar-benar hitam & terbaca */
  .alert-contrast-text {
    color: #111 !important; /* override agar hitam */
  }

  .btn-gradient {
    background: linear-gradient(90deg, #2e7d32, #43a047);
    color: #fff !important;
    border: none;
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .btn-gradient:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 18px rgba(67,160,71,.25);
  }
  .btn-gradient:disabled { opacity: .85; }

  .btn-template {
    background: linear-gradient(90deg, #1565c0, #1e88e5);
    color: #fff !important; /* tombol tetap putih dan kontras */
    border: none;
    padding: 4px 12px;
    border-radius: 8px;
    font-weight: 700;
  }
  .btn-template:hover { filter: brightness(1.05); }

  .note { font-size: 12px; color: #37474f; }

  /* Note penting yang lebih kontras */
  .note-contrast {
    background: #fff3e0;
    color: #b71c1c; /* lebih pekat agar terbaca */
    border-left: 4px solid #ef6c00;
    padding: 10px 12px;
    border-radius: 8px;
    margin-top: 10px;
    font-weight: 700;
  }

  /* DataTables override ringan agar serasi & readable */
  table.dataTable thead th, table.dataTable thead td {
    border-bottom: 1px solid #e0e0e0;
  }
  table.dataTable thead th {
    background: #f5f8ff;
    color: #0d47a1;
    font-weight: 800;
  }
  #jurusanTable { border-radius: 10px; overflow: hidden; }

  /* Badge jumlah jurusan (real-time) */
  .count-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e8f5e9;
    color: #1b5e20;
    font-weight: 800;
    border: 1px solid #a5d6a7;
    margin-left: 8px;
    font-size: 12px;
  }
  .count-badge .dot {
    width: 8px; height: 8px; border-radius: 50%; background: #43a047;
    box-shadow: 0 0 0 3px rgba(67,160,71, .15);
  }

  .swal2-popup { font-size: 14px !important; }

  /* Tooltip helper for Bootstrap 3 */
  .tooltip-inner { max-width: 260px; text-align: left; }
</style>

<div class="content-wrapper">

  <section class="content-header" style="margin-bottom:0;">
    <div class="page-title-wrap">
      <h1>Siswa <small>Import Data Siswa</small></h1>
      <div class="note">Gunakan file Excel (.xlsx) sesuai format. Sistem akan memproses dan menambahkan/ memperbarui data.</div>
    </div>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active"><i class="fa fa-upload"></i> Import Data Siswa</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">

      <!-- Kolom kiri: Upload -->
      <section class="col-lg-6">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">
              <i class="fa fa-file-excel-o"></i> Import Data Siswa
              <span class="helper-badge">.xlsx saja</span>
            </h3>
            <a href="siswa.php" class="btn btn-primary btn-sm pull-right">
              <i class="fa fa-reply"></i> &nbsp;Kembali
            </a>
          </div>
          <div class="box-body">

            <div class="alert alert-info text-center" style="margin-bottom:16px;">
              <div class="alert-contrast-text" style="margin-bottom:6px;">
                Pastikan anda mengimport menggunakan format data yang sesuai.
              </div>
              <div class="alert-contrast-text">
                Download template import
                <b>
                  <a
                    class="btn btn-template btn-xs"
                    href="../import_siswa.xlsx"
                    target="_blank"
                    data-toggle="tooltip"
                    data-placement="bottom"
                    title="Header wajib: NIS, NAMA, STATUS, JURUSAN_ID — isi sesuai daftar jurusan di kanan"
                  >
                    <i class="fa fa-download"></i> &nbsp;Unduh Template
                  </a>
                </b>
              </div>
            </div>

            <form id="importForm" action="siswa_import_act.php" method="post" enctype="multipart/form-data" novalidate>
              <?php echo epoin_csrf_field(); ?>

              <div class="form-group" style="margin-bottom:16px;">
                <label for="berkas">
                  Upload File Excel (.xlsx)
                  <i
                    class="fa fa-question-circle"
                    data-toggle="tooltip"
                    data-placement="right"
                    title="Pilih file .xlsx maksimal 5 MB. Sistem akan melakukan INSERT (jika NIS baru) atau UPDATE (jika NIS sudah ada)."
                  ></i>
                </label>
                <input
                  type="file"
                  class="form-control"
                  id="berkas"
                  name="berkas"
                  accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                  required
                >
                <div class="note" style="margin-top:6px;">
                  Maksimum ukuran file <b>5 MB</b>. Hanya format <b>.xlsx</b>.
                </div>
              </div>

              <div class="form-group">
                <button id="submitBtn" type="submit" class="btn btn-gradient btn-block">
                  <i class="fa fa-upload"></i> &nbsp;IMPORT SISWA
                </button>
              </div>
            </form>

            <div id="clientHint" class="text-muted note" style="display:none;">
              Memvalidasi file... mohon tunggu.
            </div>

          </div>
        </div>
      </section>

      <!-- Kolom kanan: Panduan + Tabel Jurusan -->
      <section class="col-lg-6">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title">
              <i class="fa fa-info-circle"></i> Panduan Mengisi Excel
              <span id="jurusanCountBadge" class="count-badge" data-toggle="tooltip" data-placement="left" title="Jumlah jurusan yang tersedia saat ini">
                <span class="dot"></span><span id="jurusanCountText">0 Jurusan</span>
              </span>
            </h3>
          </div>
          <div class="box-body">

            <div class="alert alert-success" style="margin-bottom:16px;">
              <b>PANDUAN MENGISI FILE EXCEL UNTUK DIIMPORT</b>
              <ul style="margin-top:8px; margin-bottom:8px;">
                <li>Kolom <b>Status</b>: Isi salah satu dari <b>aktif</b>, <b>tamat</b>, <b>pindah</b>, atau <b>dikeluarkan</b>.</li>
                <li>Kolom <b>Jurusan</b>: Isi dengan <b>NOMOR ID</b> sesuai tabel <b>Daftar Jurusan / Tingkat Kelas</b> di bawah.</li>
              </ul>

              <!-- Keterangan penting yang lebih kontras -->
              <div class="note-contrast">
                <i class="fa fa-exclamation-triangle"></i>
                Penamaan header kolom di Excel harus persis seperti template.
                Jika institusi Anda memakai istilah “Tingkat Kelas”, nilai yang dimasukkan tetap <b>ID</b> dari daftar di bawah.
              </div>
            </div>

            <div class="table-responsive">
              <table id="jurusanTable" class="table table-bordered table-striped" style="width:100%;">
                <thead>
                  <tr>
                    <th width="25%" data-toggle="tooltip" title="Masukkan angka ini ke kolom JURUSAN_ID di Excel">NOMOR ID</th>
                    <th data-toggle="tooltip" title="Nama Jurusan / Tingkat Kelas yang tersedia">Daftar Jurusan / Tingkat Kelas</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Ambil data jurusan dengan urutan ID agar konsisten
                  $result = mysqli_query($koneksi, "SELECT jurusan_id, jurusan_nama FROM jurusan ORDER BY jurusan_id ASC");
                  if ($result) {
                    while ($d = mysqli_fetch_assoc($result)) {
                      $jid = htmlspecialchars($d['jurusan_id'], ENT_QUOTES, 'UTF-8');
                      $jnm = htmlspecialchars($d['jurusan_nama'], ENT_QUOTES, 'UTF-8');
                      echo "<tr><td>{$jid}</td><td>{$jnm}</td></tr>";
                    }
                  } else {
                    echo '<tr><td colspan="2"><span class="text-danger">Gagal memuat data jurusan. Periksa koneksi/SQL.</span></td></tr>';
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

<?php include 'footer.php'; ?>

<!-- ================= Scripts Tambahan (CDN) ================= -->
<!-- jQuery biasanya sudah ada di AdminLTE; jika belum, pastikan di-load sebelum ini -->
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<!-- SweetAlert2 untuk konfirmasi -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  (function() {
    // Inisialisasi DataTable + badge jumlah jurusan real-time (terlihat / total)
    $(function(){
      var table = $('#jurusanTable').DataTable({
        pageLength: 8,
        lengthChange: false,
        language: {
          search: "Cari:",
          emptyTable: "Tidak ada data jurusan.",
          paginate: { previous: "Sebelumnya", next: "Berikutnya" },
          info: "Menampilkan _START_ - _END_ dari _TOTAL_ jurusan"
        }
      });

      function updateJurusanBadge() {
        var info = table.page.info();
        var visible = info.recordsDisplay; // jumlah setelah filter
        var total = info.recordsTotal;     // jumlah total
        var text = (visible === total)
          ? (total + " Jurusan")
          : (visible + " / " + total + " Jurusan");
        $('#jurusanCountText').text(text);
      }
      table.on('draw.dt', updateJurusanBadge);
      updateJurusanBadge();
    });

    // Aktifkan tooltip Bootstrap
    $(function () {
      $('[data-toggle="tooltip"]').tooltip();
    });

    // Validasi Klien: hanya .xlsx dan maks 5MB
    const MAX_MB = 5;
    const MAX_BYTES = MAX_MB * 1024 * 1024;

    const form = document.getElementById('importForm');
    const fileInput = document.getElementById('berkas');
    const submitBtn = document.getElementById('submitBtn');
    const clientHint = document.getElementById('clientHint');

    function isValidXlsx(file) {
      if (!file) return false;
      const name = (file.name || '').toLowerCase();
      const okExt = name.endsWith('.xlsx');
      // Beberapa browser memberi tipe mime yang bervariasi, jadi cek ekstensi lebih andal
      return okExt;
    }

    form.addEventListener('submit', function(e){
      e.preventDefault();

      const file = fileInput.files && fileInput.files[0];
      if (!file) {
        Swal.fire('File belum dipilih', 'Silakan pilih file .xlsx untuk diunggah.', 'warning');
        return;
      }

      if (!isValidXlsx(file)) {
        Swal.fire('Format tidak didukung', 'Hanya file Excel berformat .xlsx yang diperbolehkan.', 'error');
        return;
      }

      if (file.size > MAX_BYTES) {
        Swal.fire('File terlalu besar', 'Maksimum ukuran file adalah ' + MAX_MB + ' MB.', 'error');
        return;
      }

      // Konfirmasi sebelum import
      Swal.fire({
        title: 'Konfirmasi Import',
        html: 'Anda akan mengimport data dari file: <b>' + (file.name || '(tanpa nama)') + '</b><br>Pastikan kolom sesuai template.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, lanjutkan',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
          clientHint.style.display = 'block';
          form.submit();
        }
      });
    });
  })();
</script>
