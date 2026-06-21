<?php if (!isset($THEME_BRAND)) { require_once __DIR__.'/../includes/theme_brand.php'; } ?>
<?php
  // Versi aplikasi (fallback jika EPOIN_VERSION belum didefinisikan)
  $APP_VERSION = defined('EPOIN_VERSION') ? EPOIN_VERSION : '3.2.9';
?>

<!-- /.content-wrapper -->
<footer class="main-footer">
  <footer class="text-center p-2" style="font-size: 13px; color: #555;">
    <div>
      Copyright &copy; <?php echo date('Y'); ?> <?php echo brand_school_name(); ?>
    </div>
    <div>
      Powered by
      <!-- LINK: E-Poin Siswa membuka modal "Tentang E-Poin" dari include -->
      <a href="#"
         data-toggle="modal"
         data-target="#epoinAboutModal"
         title="Tentang E-Poin Siswa & Modul Terintegrasi">
        E-Poin</a><span class="sep">|</span>

      <!-- Versi membuka modal pembaruan -->
      <a href="#"
         id="epoinVersionLink"
         data-toggle="modal"
         data-target="#epoinVersionModal"
         data-current-version="<?php echo htmlspecialchars($APP_VERSION, ENT_QUOTES, 'UTF-8'); ?>"
         title="Lihat informasi versi & pembaruan">
         Versi <?php echo htmlspecialchars($APP_VERSION, ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <span class="sep">|</span>

      <!-- Link PRIVASI -->
      <a href="#" data-toggle="modal" data-target="#privacyModal" title="Kebijakan Privasi">Privasi</a>
      <span class="sep">|</span>

      <!-- Link S&K -->
      <a href="#" data-toggle="modal" data-target="#termsModal" title="Syarat & Ketentuan">S&amp;K</a>
      <span class="sep">|</span>

      <!-- Link HUBUNGI KAMI -->
      <a href="#" data-toggle="modal" data-target="#tentangEPOIN" title="Hubungi Kami">Hubungi Kami</a>
    </div>
    <div>
      <!-- area tambahan -->
    </div>
  </footer>
</footer>

</div>

<!-- =============== Modal Styling (global) =============== -->
<style>
  .modal{ z-index:2050; } .modal-backdrop{ z-index:2000; }
  .modal-content{ background:#ffffff; color:#0f172a; box-shadow:0 20px 60px rgba(0,0,0,.35); }
  .modal-content hr{ border-top:1px solid rgba(15,23,42,.12); }

  /* ANTI-GESER KONTEN saat modal */
  html { overflow-y: scroll; }
  body.modal-no-shift { padding-right:0 !important; margin-right:0 !important; }
  body.modal-no-shift .navbar-fixed-top,
  body.modal-no-shift .navbar-fixed-bottom,
  body.modal-no-shift .main-header,
  body.modal-no-shift .content-wrapper,
  body.modal-no-shift .main-footer {
    padding-right:0 !important; margin-right:0 !important;
  }

  /* Hero gradient util */
  .version-hero{
    position:relative; background: linear-gradient(135deg,#0B57D0, #3BA3FF);
    color:#fff; padding:16px 18px; border-top-left-radius:6px; border-top-right-radius:6px; overflow:hidden;
  }
  .version-hero:after{
    content:""; position:absolute; inset:-40% -40% auto auto; width:220px; height:220px; border-radius:50%;
    background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.35), rgba(255,255,255,0)); filter: blur(6px);
    animation: pulseGlow 4s ease-in-out infinite;
  }
  @keyframes pulseGlow{ 0%,100%{ transform:scale(1); opacity:.7;} 50%{ transform:scale(1.08); opacity:1;} }
  .version-badge{ display:inline-block; padding:4px 10px; border-radius:999px; background: rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); font-weight:600; letter-spacing:.2px; }

  .callout{ border-left:4px solid #0B57D0; background:#F6F9FF; padding:10px 12px; border-radius:6px; color:#0f172a; }
  .sep{ margin:0 1px; color:#9aa4b2; }

  /* Loader "Cek Pembaruan" */
  .check-loader{display:none; text-align:center; margin:10px 0 4px;}
  .loader-stack{position:relative; display:inline-block; width:66px; height:66px;}
  .loader-donut{ position:absolute; inset:0; border-radius:50%; background: conic-gradient(from 0deg, rgba(59,163,255,1) 0 120deg, rgba(59,163,255,.15) 120deg 360deg); -webkit-mask: radial-gradient(circle at center, transparent 23px, #000 24px); mask: radial-gradient(circle at center, transparent 23px, #000 24px); animation: spin 1s linear infinite; box-shadow: 0 8px 24px rgba(11,87,208,.25); }
  .loader-radar{ position:absolute; inset:6px; border-radius:50%; border:2px dashed rgba(11,87,208,.35); animation: spin 4s linear infinite reverse; }
  .loader-pulse{ position:absolute; inset:-6px; border-radius:50%; background: radial-gradient(circle, rgba(59,163,255,.35), rgba(59,163,255,0) 60%); animation: pulse 1.8s ease-in-out infinite; filter: blur(2px); }
  @keyframes spin { to { transform: rotate(360deg);} } @keyframes pulse { 0%,100%{opacity:.35; transform:scale(.95);} 50%{opacity:.7; transform:scale(1.05);} }
  .loader-caption{font-size:12px; margin-top:8px; color:#0B57D0; font-weight:700; letter-spacing:.3px;}
  .btn-pill{border-radius:999px; font-weight:600; letter-spacing:.2px; box-shadow:0 6px 18px rgba(11,87,208,.25);}
  .btn-grad-blue{background:linear-gradient(90deg,#0B57D0,#3BA3FF); color:#fff; border:0;} .btn-grad-blue:hover{ filter:brightness(1.06); color:#fff; }
  .btn-soft{ background:#eef5ff; color:#0B57D0; border:1px solid #cfe0ff; }
  .checkmark{ display:none; font-size:20px; color:#22c55e; }

  /* Spinner tombol (agar terlihat saat cek pembaruan) */
  .btn-spinner{
    display:inline-block; width:14px; height:14px;
    border:2px solid rgba(255,255,255,.6); border-top-color:#fff;
    border-radius:50%; animation: spin 1s linear infinite;
    margin-right:6px; vertical-align:-2px;
  }
</style>

<!-- ==========================================================
     INCLUDE Modal "Tentang E-Poin" (standalone dari includes/)
     ========================================================== -->
<?php include __DIR__.'/../includes/modal_tentang_epoin.php'; ?>

<!-- =============== Modal "Hubungi Kami" (tetap) =============== -->
<div class="modal fade" id="tentangEPOIN" tabindex="-1" role="dialog" aria-labelledby="tentangEPOINLabel">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="version-hero">
        <div class="clearfix">
          <div style="float:left">
            <div style="font-size:13px; opacity:.9">E-POIN Siswa</div>
            <h4 id="tentangEPOINLabel" style="margin:2px 0 6px; font-weight:800; letter-spacing:.3px">Hubungi Kami</h4>
            <span class="version-badge">Bantuan implementasi & kustomisasi</span>
          </div>
        </div>
      </div>
      <div class="modal-header bg-primary" style="display:none">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Tentang E-POIN</h4>
      </div>
      <div class="modal-body">
        <p>Kami siap membantu kebutuhan sekolah Anda, konsultasi implementasi, migrasi data, pelatihan, kustomisasi modul, hingga integrasi dengan sistem yang sudah ada.</p>
        <p>Ingin melihat rangkuman fitur? <a href="#" data-open-about="true">Baca “Apa itu E-Poin?”</a></p>
        <hr>
        <h5 style="margin-top:10px;">📌 Hubungi Developer</h5>
        <p style="margin-bottom:8px;">Punya masukan atau butuh fitur khusus? Kami siap bantu.</p>
        <a href="https://wa.me/6285221990888" target="_blank" class="btn btn-grad-blue btn-pill">
          <img src="https://upload.wikimedia.org/wikipedia/commons/6/6b/WhatsApp.svg" width="18" style="margin-right:6px;vertical-align:middle"> WhatsApp
        </a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-pill" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- =============== Modal PRIVASI (tetap) =============== -->
<div class="modal fade" id="privacyModal" tabindex="-1" role="dialog" aria-labelledby="privacyModalLabel">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="version-hero">
        <div class="clearfix">
          <div style="float:left">
            <div style="font-size:13px; opacity:.9">E-POIN Siswa</div>
            <h4 id="privacyModalLabel" style="margin:2px 0 6px; font-weight:800; letter-spacing:.3px">Kebijakan Privasi</h4>
            <span class="version-badge">Transparansi & Kendali Data</span>
          </div>
          <div style="float:right; text-align:right">
            <div style="font-size:12px; opacity:.9">Sekolah</div>
            <div style="font-weight:700"><?php echo htmlspecialchars(brand_school_name(), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </div>
      <div class="modal-body">
        <div class="callout"><strong>Ringkas:</strong> Sekolah adalah <em>Pengendali Data</em>. E-POIN bertindak sebagai <em>Pengolah</em> sesuai instruksi sekolah.</div>
        <h5>1) Data yang diproses</h5>
        <ul>
          <li>Identitas siswa & wali (nis, nama, kelas, kontak opsional WA).</li>
          <li>Aktivitas akademik: prestasi, pelanggaran, kehadiran, catatan guru.</li>
          <li>Data ujian (jadwal, attempt, log kepatuhan) bila modul ujian diaktifkan.</li>
          <li>Log sistem (akses, perubahan data) untuk audit dan keamanan.</li>
        </ul>
        <h5>2) Tujuan & dasar pemrosesan</h5>
        <ul>
          <li>Pelaksanaan layanan pendidikan & pelaporan sekolah–orang tua.</li>
          <li>Keamanan & audit (RBAC, jejak aktivitas, pencegahan penyalahgunaan).</li>
          <li>Peningkatan layanan (telemetri agregat non-pribadi).</li>
        </ul>
        <h5>3) Berbagi data</h5>
        <ul>
          <li>Integrasi opsional: WhatsApp/Email gateway, Google (ekspor Sheets/PDF), <em>hanya</em> bila diaktifkan oleh admin.</li>
          <li>Tidak dijual kepada pihak ketiga.</li>
        </ul>
        <h5>4) Penyimpanan & keamanan</h5>
        <ul>
          <li>Transport HTTPS, kontrol akses berbasis peran, audit log, backup terjadwal.</li>
          <li>Retensi disesuaikan kebijakan sekolah; data dapat dihapus/anonimkan atas permintaan resmi.</li>
        </ul>
        <h5>5) Hak subjek data</h5>
        <ul>
          <li>Akses, perbaikan, penghapusan sesuai kebijakan sekolah & regulasi setempat.</li>
        </ul>
        <hr>
        <p style="font-size:12px;color:#667085">Dokumen ini adalah ringkasan operasional untuk penggunaan E-POIN di lingkungan sekolah. Versi formal dapat disediakan jika diperlukan.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-pill" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- =============== Modal S&K (tetap) =============== -->
<div class="modal fade" id="termsModal" tabindex="-1" role="dialog" aria-labelledby="termsModalLabel">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="version-hero">
        <div class="clearfix">
          <div style="float:left">
            <div style="font-size:13px; opacity:.9">E-POIN Siswa</div>
            <h4 id="termsModalLabel" style="margin:2px 0 6px; font-weight:800; letter-spacing:.3px">Syarat &amp; Ketentuan</h4>
            <span class="version-badge">Penggunaan Layanan Per Sekolah</span>
          </div>
          <div style="float:right; text-align:right">
            <div style="font-size:12px; opacity:.9">Tenant</div>
            <div style="font-weight:700"><?php echo htmlspecialchars(brand_school_name(), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </div>
      <div class="modal-body">
        <div class="callout"><strong>Lisensi:</strong> Komersial per sekolah (non-transferable). Dilarang redistribusi tanpa izin tertulis.</div>
        <h5>1) Akun & peran</h5>
        <ul>
          <li>Akses diberikan oleh admin sekolah. Pengguna wajib menjaga kerahasiaan kredensial.</li>
          <li>Hak akses mengikuti peran (admin, guru, wali kelas, siswa).</li>
        </ul>
        <h5>2) Penggunaan yang diizinkan</h5>
        <ul>
          <li>Pengelolaan data poin, absensi, ujian, dan pelaporan internal sekolah.</li>
          <li>Dilarang penyalahgunaan, scraping berlebihan, atau rekayasa balik tanpa izin.</li>
        </ul>
        <h5>3) Layanan & pembaruan</h5>
        <ul>
          <li>Pembaruan fitur/patch keamanan dapat dilakukan berkala; admin dapat menginisiasi dari menu “Versi”.</li>
          <li>Perubahan besar akan diinformasikan di catatan rilis.</li>
        </ul>
        <h5>4) Ketersediaan & dukungan</h5>
        <ul>
          <li>Target ketersediaan mengikuti infrastruktur hosting sekolah.</li>
          <li>Dukungan melalui kanal yang disepakati (mis. WhatsApp/Email).</li>
        </ul>
        <h5>5) Data & kepemilikan</h5>
        <ul>
          <li>Data tetap milik sekolah. E-POIN memproses sesuai instruksi sekolah.</li>
          <li>Backup disediakan sesuai kebijakan; pemulihan mengikuti prosedur yang berlaku.</li>
        </ul>
        <hr>
        <p style="font-size:12px;color:#667085">Dokumen ini ringkasan operasional. Ketentuan khusus dapat dituangkan dalam Perjanjian Layanan terpisah bila diperlukan.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default btn-pill" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- =============== Modal Info Versi & Updater (tetap) =============== -->
<div class="modal fade" id="epoinVersionModal" tabindex="-1" role="dialog" aria-labelledby="epoinVersionLabel">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="version-hero">
        <div class="clearfix">
          <div style="float:left">
            <div style="font-size:13px; opacity:.9">E-POIN Siswa</div>
            <h4 id="epoinVersionLabel" style="margin:2px 0 6px; font-weight:800; letter-spacing:.3px">Info Versi & Pembaruan</h4>
            <span class="version-badge">Saat ini: <span id="currentVersionText"><?php echo htmlspecialchars($APP_VERSION, ENT_QUOTES, 'UTF-8'); ?></span></span>
          </div>
          <div style="float:right; text-align:right">
            <div style="font-size:12px; opacity:.9">Sekolah</div>
            <div style="font-weight:700"><?php echo htmlspecialchars(brand_school_name(), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </div>

      <div class="modal-body">
        <div class="callout" id="updateStatusCallout"><strong>Status:</strong> Tekan <em>Cek Pembaruan</em> untuk melihat apakah ada versi terbaru yang tersedia.</div>

        <div id="checkLoaderWrap" class="check-loader">
          <div class="loader-stack"><div class="loader-donut"></div><div class="loader-radar"></div><div class="loader-pulse"></div></div>
          <div class="loader-caption">Mengecek pembaruan…</div>
        </div>

        <h5 style="margin:10px 0 6px" id="releaseHeading">Ringkas Rilis</h5>
        <ul class="changelog">
          <li>Peningkatan performa dashboard & cache query.</li>
          <li>Patch keamanan RBAC & penguatan CSP untuk CDN ikon/CSS.</li>
          <li>Ujian GForm : perbaikan <em>preview</em> bertanda tangan & log pelanggaran.</li>
          <li>Ekspor : template PDF/Excel lebih rapi, dukung logo sekolah otomatis.</li>
          <li>Util : perbaikan notifikasi & konsistensi badge “Data Master”.</li>
        </ul>

        <div id="latestInfo" style="display:none; margin-top:8px;">
          <div class="callout" id="availableCallout" style="display:none; border-left-color:#22c55e;">Versi terbaru tersedia: <strong id="latestVersionText">-</strong>. Rekomendasi: perbarui untuk fitur & patch terbaru.</div>
          <div class="callout" id="upToDateCallout" style="display:none; border-left-color:#16a34a;">✅ Anda sudah menggunakan versi terbaru.</div>
        </div>

        <div class="progress-wrap" id="updateProgressWrap">
          <div class="progress"><div class="progress-bar" id="updateProgressBar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div>
          <div class="loader-dots" id="updateDots">Mengunduh <span class="dot">•</span><span class="dot">•</span><span class="dot">•</span></div>
          <div class="checkmark" id="updateSuccess">✔ Update berhasil dipasang</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" id="btnCheckUpdate" class="btn btn-soft btn-pill">Cek Pembaruan</button>
        <button type="button" id="btnStartUpdate" class="btn btn-grad-blue btn-pill" disabled>Perbarui Sekarang</button>
        <button type="button" class="btn btn-default btn-pill" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================= JS LIBRARY SECTION ========================= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- (Biarkan Select2 ganda sesuai permintaan) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="../assets/bower_components/jquery-ui/jquery-ui.min.js"></script>
<script> $.widget.bridge('uibutton', $.ui.button); </script>
<script src="../assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script src="../assets/bower_components/raphael/raphael.min.js"></script>
<script src="../assets/bower_components/morris.js/morris.min.js"></script>
<script src="../assets/bower_components/jquery-sparkline/dist/jquery.sparkline.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap.min.js"></script>
<script src="../assets/plugins/jvectormap/jquery-jvectormap-1.2.2.min.js"></script>
<script src="../assets/plugins/jvectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../assets/bower_components/jquery-knob/dist/jquery.knob.min.js"></script>
<script src="../assets/bower_components/moment/min/moment.min.js"></script>
<script src="../assets/bower_components/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="../assets/bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
<script src="../assets/plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.all.min.js"></script>
<script src="../assets/bower_components/jquery-slimscroll/jquery.slimscroll.min.js"></script>
<script src="../assets/bower_components/fastclick/lib/fastclick.js"></script>
<script src="../assets/dist/js/adminlte.min.js"></script>
<script src="../assets/dist/js/pages/dashboard.js"></script>
<script src="../assets/dist/js/demo.js"></script>
<script src="../assets/bower_components/ckeditor/ckeditor.js"></script>

<style>
  /* Fullscreen loader */
  .page-loader{
    position:fixed; inset:0; z-index:9999;
    display:none; align-items:center; justify-content:center; flex-direction:column;
    background: rgba(2, 6, 23, .45); /* gelap transparan */
    backdrop-filter: blur(2px);
  }
  .page-loader .spinner{
    width:56px; height:56px; border-radius:50%;
    border:6px solid rgba(255,255,255,.35);
    border-top-color:#fff; animation:spin 1s linear infinite;
  }
  .page-loader .msg{ color:#fff; margin-top:12px; font-weight:700; letter-spacing:.2px }
  @keyframes spin{ to{ transform:rotate(360deg) } }
</style>

<!-- Loader element -->
<div id="pageLoader" class="page-loader" aria-hidden="true">
  <div class="spinner"></div>
  <div class="msg">Memproses...</div>
</div>

<script>
  // Helper global
  (function(){
    window.EPS = window.EPS || {};
    EPS.showLoader = function(msg){
      var el = document.getElementById('pageLoader');
      if(!el) return;
      el.querySelector('.msg').textContent = msg || 'Memproses...';
      el.style.display = 'flex';
    };
    EPS.hideLoader = function(){
      var el = document.getElementById('pageLoader');
      if(!el) return;
      el.style.display = 'none';
    };
  })();
</script>



<!-- ========================= CUSTOM SCRIPT SECTION ========================= -->
<script>
  $(document).ready(function(){

    // Pastikan semua modal terpasang di body (kecuali #epoinAboutModal sudah di-handle di include)
    $('#tentangEPOIN, #epoinVersionModal, #privacyModal, #termsModal').appendTo('body');

    // DataTable default (guard jika elemen ada)
    if ($('#table-datatable').length) {
      $('#table-datatable').DataTable({
        paging: true, lengthChange: false, searching: true,
        ordering: false, info: true, autoWidth: true, pageLength: 50
      });
    }

    // DataTable log (guard jika elemen ada)
    if ($('#logTable').length) {
      if ($.fn.DataTable.isDataTable('#logTable')) {
        $('#logTable').DataTable().destroy();
      }
      $('#logTable').DataTable({
        pageLength: 5, lengthMenu: [5,10,20,50],
        language: {
          lengthMenu: "Tampilkan _MENU_ data per halaman",
          zeroRecords: "Tidak ditemukan data yang sesuai",
          info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
          infoEmpty: "Tidak ada data tersedia",
          infoFiltered: "(disaring dari total _MAX_ data)",
          search: "Cari:",
          paginate: { first:"Awal", last:"Akhir", next:"Berikutnya", previous:"Sebelumnya" }
        }
      });
    }

    // Datepicker
    if ($.fn.datepicker) {
      $('#datepicker').datepicker({ autoclose: true, format: 'dd/mm/yyyy' }).datepicker("setDate", new Date());
      $('.datepicker2').datepicker({ autoclose: true, format: 'yyyy/mm/dd' });
    }

    // Ajax tujuan pesan siswa
    $("#pesan_pilih_tujuan").on("change", function(){
      var pilih = $(this).val(); if(pilih && pilih.length>0){
        $.post("pesan_ajax_pilih_tujuan.php", {tujuan:pilih}, function(result){ $(".tampil_tujuan").html(result); });
      }
    });

    // Load kelas by TA
    $("body").on("change", ".pilih_ta", function(){
      var pilih = $(this).val(); if(pilih && pilih.length>0){
        $.post("ajax_get_kelas.php", {ta:pilih}, function(result){ $(".pilih_kelas").html(result); $(".pilih_siswa").html(''); });
      }
    });

    // Load siswa by kelas
    $("body").on("change", ".pilih_kelas", function(){
      var pilih = $(this).val(); if(pilih && pilih.length>0){
        $.post("ajax_get_siswa.php", {kelas:pilih}, function(result){ $(".pilih_siswa").html(result); });
      }
    });

    /* Perpindahan modal langsung: Hubungi Kami -> About */
    $(document).on('click','a[data-open-about]', function(e){
      e.preventDefault();
      var $src = $('#tentangEPOIN');
      var $dst = $('#epoinAboutModal');
      $dst.modal('show');
      if ($src.hasClass('in') || $src.hasClass('show')) {
        $src.modal('hide');
      }
    });

    /* ANTI-GESER / ANTI "KOLOM PUTIH" SAAT MODAL DITUTUP */
    (function(){
      var FIX_TARGETS = 'body, .navbar-fixed-top, .navbar-fixed-bottom, .main-header, .content-wrapper, .main-footer';
      function clearCompensation(){
        $(FIX_TARGETS).each(function(){ this.style.paddingRight = ''; this.style.marginRight  = ''; });
        $('body').css('overflow','');
      }
      $(document).on('show.bs.modal', '.modal', function(){ $('body').addClass('modal-no-shift'); });
      $('body').on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.in, .modal.show').length) { $('body').addClass('modal-open'); }
        else { $('.modal-backdrop').remove(); $('body').removeClass('modal-open modal-no-shift'); clearCompensation(); }
      });
      setTimeout(clearCompensation, 0);
    })();

    /* ==================== Cek & Update Versi ==================== */
    var $versionLink   = $('#epoinVersionLink');
    var currentVersion = ($versionLink.data('current-version') || '').toString().trim();
    var latestVersion  = '3.3.0'; // simulasi

    var isChecking=false, isUpdating=false, updateDone=false;

    function cmpVer(a,b){
      var pa=a.split('.').map(Number), pb=b.split('.').map(Number);
      for(var i=0;i<Math.max(pa.length,pb.length);i++){ var x=pa[i]||0, y=pb[i]||0; if(x<y) return -1; if(x>y) return 1; }
      return 0;
    }
    function setReleaseHeading(version, isLatest){
      var title = isLatest ? 'Ringkas Rilis Terbaru' : 'Ringkas Rilis (Versi Saat Ini)';
      $('#releaseHeading').text(title + ' — v ' + (version || '-'));
    }
    function resetUpdaterUI(){
      $('#updateStatusCallout').show().html('<strong>Status:</strong> Tekan <em>Cek Pembaruan</em> untuk melihat apakah ada versi terbaru yang tersedia.');
      $('#latestInfo, #availableCallout, #upToDateCallout').hide();
      $('#updateProgressWrap, #updateDots, #updateSuccess').hide();
      $('#updateProgressBar').css('width','0%').attr('aria-valuenow', 0);
      $('#checkLoaderWrap').hide();
      var $btn = $('#btnCheckUpdate'); $btn.prop('disabled', false).html('Cek Pembaruan');
      var $start = $('#btnStartUpdate');
      $start.prop('disabled', true).removeClass('btn-soft').addClass('btn-grad-blue').text('Perbarui Sekarang').removeAttr('data-state');
      isChecking=false; isUpdating=false; updateDone=false;
    }

    $('#epoinVersionModal').on('show.bs.modal', function(){
      $('#currentVersionText').text(currentVersion || '-');
      resetUpdaterUI();
      setReleaseHeading(currentVersion || '-', false);
    });

    $('#btnCheckUpdate').on('click', function(){
      if (isChecking) return; isChecking = true;
      var $btn = $(this), originalHtml = $btn.html();
      $('#updateStatusCallout, #latestInfo, #availableCallout, #upToDateCallout').hide();
      $('#checkLoaderWrap').show();
      $btn.prop('disabled', true).html('<span class="btn-spinner"></span>Mengecek…');
      var checkDelay = 1200 + Math.floor(Math.random()*1000);
      setTimeout(function(){
        $('#checkLoaderWrap').hide(); $('#latestInfo').show();
        if (!currentVersion){
          $('#availableCallout').show().html('ℹ️ Tidak dapat membaca versi saat ini. Silakan definisikan <code>EPOIN_VERSION</code> di <em>includes/theme_brand.php</em>.');
          $('#btnStartUpdate').prop('disabled', true);
          setReleaseHeading('-', false);
        } else if (cmpVer(currentVersion, latestVersion) < 0){
          $('#availableCallout').show(); $('#latestVersionText').text(latestVersion); $('#btnStartUpdate').prop('disabled', false);
          setReleaseHeading(latestVersion, true);
        } else {
          $('#upToDateCallout').show(); $('#btnStartUpdate').prop('disabled', true);
          setReleaseHeading(currentVersion, false);
        }
        $btn.prop('disabled', false).html(originalHtml); isChecking = false;
      }, checkDelay);
    });

    $('#btnStartUpdate').on('click', function(){
      var $btn = $(this);
      if (updateDone){ $btn.prop('disabled', true); $('#epoinVersionModal').modal('hide'); return; }
      if (isUpdating || $btn.prop('disabled')) return;
      isUpdating = true;
      $btn.prop('disabled', true).text('Memperbarui…');
      $('#availableCallout').hide(); $('#updateProgressWrap').show(); $('#updateDots').show().text('Mengunduh ');
      var dots = ['•','••','•••','••••'], di=0, dotTimer=setInterval(function(){ $('#updateDots').html('Mengunduh <span class="dot">'+dots[di%dots.length]+'</span>'); di++; },300);
      var p=0, timer=setInterval(function(){
        p += Math.floor(Math.random()*14)+6; if(p>100)p=100;
        $('#updateProgressBar').css('width', p+'%').attr('aria-valuenow', p);
        if(p>=100){ clearInterval(timer); setTimeout(function(){
          clearInterval(dotTimer); $('#updateDots').hide(); $('#updateSuccess').show().text('✔ Update berhasil dipasang ke ' + latestVersion);
          currentVersion = latestVersion; $('#currentVersionText').text(currentVersion);
          $versionLink.data('current-version', currentVersion).html('Versi ' + currentVersion);
          updateDone = true; isUpdating = false;
          $btn.text('Selesai').removeClass('btn-grad-blue').addClass('btn-soft').prop('disabled', false).attr('data-state','done');
          setReleaseHeading(currentVersion, false);
        },600);}
      },420);
    });

    // Tidak perlu duplikasi “benefit tap feedback” untuk #epoinAboutModal karena sudah dikelola di file include.
  });
</script>
<!-- (Biarkan Select2 ganda sesuai permintaan) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.full.min.js"></script>
<link rel="stylesheet" href="../assets/bower_components/font-awesome/css/font-awesome.min.css">

<script>
<?php if (!empty($_SESSION['flash_ok'])): ?>
  toastr.success("<?= addslashes(strip_tags($_SESSION['flash_ok'])) ?>");
<?php unset($_SESSION['flash_ok']); endif; ?>

<?php if (!empty($_SESSION['flash_err'])): ?>
  toastr.error("<?= addslashes(strip_tags($_SESSION['flash_err'])) ?>");
<?php unset($_SESSION['flash_err']); endif; ?>
</script>

<script>
/* ===== EPOIN: SweetAlert2 konfirmasi hapus (POST+CSRF) ===== */
(function(){
  function attachDelConfirm(){
    document.querySelectorAll('.btn-del-confirm').forEach(function(btn){
      if (btn.dataset.epsInit) return;
      btn.dataset.epsInit = '1';
      btn.addEventListener('click', function(){
        var form = this.closest('form.eps-del-form');
        if (!form) return;
        var nama = this.dataset.nama || '';
        var msg  = nama ? 'Data <b>' + nama + '</b> akan dihapus permanen.' : 'Data akan dihapus permanen.';
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Konfirmasi Hapus',
            html: msg + '<br><small style="color:#6b7280">Tindakan ini tidak dapat dibatalkan.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-trash"></i> Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: { popup: 'swal2-brand' }
          }).then(function(r){ if (r.isConfirmed) form.submit(); });
        } else {
          if (confirm('Hapus ' + (nama || 'data') + '? Tindakan ini tidak dapat dibatalkan.')) form.submit();
        }
      });
    });
  }
  document.addEventListener('DOMContentLoaded', attachDelConfirm);
})();
</script>

</body>
</html>
