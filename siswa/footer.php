<?php if (!isset($THEME_BRAND)) { require_once __DIR__.'/../includes/theme_brand.php'; } ?>
<?php
  // Versi aplikasi (fallback jika konstanta tidak ada)
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
        <!-- REVISI: Link “E-Poin” sekarang membuka modal About (#epoinAboutModal) yang dipanggil dari include -->
        <a href="#"
           data-toggle="modal"
           data-target="#epoinAboutModal"
           title="Tentang E-Poin Siswa & Modul Terintegrasi">E-Poin</a>
        <span class="sep">|</span>
        <!-- Info versi -->
        <a href="#" id="epoinVersionLink" data-toggle="modal" data-target="#epoinVersionModal"
           data-current-version="<?php echo htmlspecialchars($APP_VERSION, ENT_QUOTES, 'UTF-8'); ?>"
           title="Lihat informasi versi & pembaruan">
          Versi <?php echo htmlspecialchars($APP_VERSION, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <span class="sep">|</span>
        <a href="#" data-toggle="modal" data-target="#privacyModal" title="Kebijakan Privasi">Privasi</a>
        <span class="sep">|</span>
        <a href="#" data-toggle="modal" data-target="#termsModal" title="Syarat & Ketentuan">S&amp;K</a>
        <span class="sep">|</span>
        <a href="#" data-toggle="modal" data-target="#tentangEPOIN" title="Hubungi Kami">Hubungi Kami</a>
      </div>
    </footer>
  </footer>

</div>

<!-- =============== Modal Styling (global) =============== -->
<style>
  .modal{ z-index:2050; } .modal-backdrop{ z-index:2000; }
  .modal-content{ background:#ffffff; color:#0f172a; box-shadow:0 20px 60px rgba(0,0,0,.35); }
  .modal-content hr{ border-top:1px solid rgba(15,23,42,.12); }
  .sep{ margin:0 1px; color:#9aa4b2; }

  /* Hero gradient dan komponen bersama (SAMA DENGAN FOOTER ADMIN) */
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
  /* === Badge versi responsif === */
  .version-badge{
    display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px;
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22);
    font-weight:700; letter-spacing:.1px; line-height:1; font-size:clamp(10px, 1.05vw, 12px);
    white-space:nowrap; max-width:100%; overflow:hidden; text-overflow:ellipsis;
  }

  .callout{ border-left:4px solid #0B57D0; background:#F6F9FF; padding:10px 12px; border-radius:6px; color:#0f172a; }
  .btn-pill{border-radius:999px; font-weight:600; letter-spacing:.2px; box-shadow:0 6px 18px rgba(11,87,208,.25);}
  .btn-grad-blue{background:linear-gradient(90deg,#0B57D0,#3BA3FF); color:#fff; border:0;} .btn-grad-blue:hover{ filter:brightness(1.06); color:#fff; }
  .btn-soft{ background:#eef5ff; color:#0B57D0; border:1px solid #cfe0ff; }

  /* Loader cek versi & progres update (SAMA DENGAN FOOTER ADMIN) */
  .check-loader{display:none; text-align:center; margin:10px 0 4px;}
  .loader-stack{position:relative; display:inline-block; width:66px; height:66px;}
  .loader-donut{ position:absolute; inset:0; border-radius:50%; background: conic-gradient(from 0deg, rgba(59,163,255,1) 0 120deg, rgba(59,163,255,.15) 120deg 360deg); -webkit-mask: radial-gradient(circle at center, transparent 23px, #000 24px); mask: radial-gradient(circle at center, transparent 23px, #000 24px); animation: spin 1s linear infinite; box-shadow: 0 8px 24px rgba(11,87,208,.25); }
  .loader-radar{ position:absolute; inset:6px; border-radius:50%; border:2px dashed rgba(11,87,208,.35); animation: spin 4s linear infinite reverse; }
  .loader-pulse{ position:absolute; inset:-6px; border-radius:50%; background: radial-gradient(circle, rgba(59,163,255,.35), rgba(59,163,255,0) 60%); animation: pulse 1.8s ease-in-out infinite; filter: blur(2px); }
  @keyframes spin { to { transform: rotate(360deg);} } @keyframes pulse { 0%,100%{opacity:.35; transform:scale(.95);} 50%{opacity:.7; transform:scale(1.05);} }
  .loader-caption{font-size:12px; margin-top:8px; color:#0B57D0; font-weight:700; letter-spacing:.3px;}
  .checkmark{ display:none; font-size:20px; color:#22c55e; }
</style>

<!-- ==========================================================
     INCLUDE Modal "Tentang E-Poin" (standalone dari includes/)
     ========================================================== -->
<?php include __DIR__.'/../includes/modal_tentang_epoin.php'; ?>

<!-- =============== MODAL “Hubungi Kami” (TETAP) =============== -->
<div class="modal fade" id="tentangEPOIN" tabindex="-1" role="dialog" aria-labelledby="tentangEPOINLabel">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <!-- HERO -->
      <div class="version-hero">
        <div class="clearfix">
          <div style="float:left">
            <div style="font-size:12px; opacity:.9">E-POIN Siswa</div>
            <h4 id="tentangEPOINLabel" style="margin:2px 0 6px; font-weight:600; letter-spacing:.3px">Hubungi Kami</h4>
            <span class="version-badge">Bantuan implementasi & kustomisasi</span>
          </div>
        </div>
      </div>
      <!-- Header lama disembunyikan (tetap ada agar tidak “mengurangi” skrip) -->
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

<!-- =============== MODAL PRIVASI (TETAP) =============== -->
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

<!-- =============== MODAL S&K (TETAP) =============== -->
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

<!-- =============== MODAL INFO VERSI & UPDATER (TETAP) =============== -->
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

        <!-- Loader cek pembaruan -->
        <div id="checkLoaderWrap" class="check-loader">
          <div class="loader-stack"><div class="loader-donut"></div><div class="loader-radar"></div><div class="loader-pulse"></div></div>
          <div class="loader-caption">Mengecek pembaruan…</div>
        </div>

        <!-- Ringkas Rilis -->
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

        <!-- Progres update -->
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

<!-- ====== LIBRARY ASLI (TETAP) ====== -->
<script src="../assets/bower_components/jquery/dist/jquery.min.js"></script>
<script src="../assets/bower_components/jquery-ui/jquery-ui.min.js"></script>

<script>
  $.widget.bridge('uibutton', $.ui.button);
</script>

<script src="../assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script src="../assets/bower_components/raphael/raphael.min.js"></script>
<script src="../assets/bower_components/morris.js/morris.min.js"></script>

<script src="../assets/bower_components/jquery-sparkline/dist/jquery.sparkline.min.js"></script>

<script src="../assets/bower_components/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="../assets/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>

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

<!-- ====== SCRIPT ASLI ANDA (TETAP) + LOGIKA MODAL ====== -->
<script>
  $(document).ready(function(){

   $('#table-datatable').DataTable({
    'paging'      : true,
    'lengthChange': false,
    'searching'   : true,
    'ordering'    : false,
    'info'        : true,
    'autoWidth'   : true,
    "pageLength": 50
   });

  });

  $('#datepicker').datepicker({
    autoclose: true,
    format: 'dd/mm/yyyy',
  }).datepicker("setDate", new Date());

  $('.datepicker2').datepicker({
    autoclose: true,
    format: 'yyyy/mm/dd',
  });

  $(document).ready(function(){
    $("#pesan_pilih_tujuan").on("change",function(){
      var pilih = $(this).val();
      var data = "tujuan="+pilih;
      if(pilih.length > 0){
        $.ajax({
          url: "pesan_ajax_pilih_tujuan.php",
          method: "POST",
          data:data,
          success: function(result){
            $(".tampil_tujuan").html(result);
          }});
      }
    });

    $("body").on("change",".pilih_ta",function(){
      var pilih = $(this).val();
      var data = "ta="+pilih;
      if(pilih.length > 0){
        $.ajax({
          url: "ajax_get_kelas.php",
          method: "POST",
          data:data,
          success: function(result){
            $(".pilih_kelas").html(result);
            $(".pilih_siswa").html('');
          }});
      }
    });

    $("body").on("change",".pilih_kelas",function(){
      var pilih = $(this).val();
      var data = "kelas="+pilih;
      if(pilih.length > 0){
        $.ajax({
          url: "ajax_get_siswa.php",
          method: "POST",
          data:data,
          success: function(result){
            $(".pilih_siswa").html(result);
          }});
      }
    });

    /* ===== Pasang semua modal di body + navigasi antar modal ===== */
    $('#tentangEPOIN, #epoinVersionModal, #privacyModal, #termsModal, #epoinAboutModal').appendTo('body');

    // Link “Baca Apa itu E-Poin?” di modal Hubungi Kami membuka About
    $(document).on('click','a[data-open-about]', function(e){
      e.preventDefault();
      var $src = $('#tentangEPOIN'), $dst = $('#epoinAboutModal');
      $dst.modal('show');
      if ($src.hasClass('in') || $src.hasClass('show')) { $src.modal('hide'); }
    });

    // Perbaiki body locking saat tutup-buka banyak modal
    $('body').on('hidden.bs.modal', '.modal', function () {
      if ($('.modal.in, .modal.show').length) $('body').addClass('modal-open');
      else { $('.modal-backdrop').remove(); $('body').removeClass('modal-open'); }
    });

    /* ====== Cek & Update Versi (UI/loader persis admin) ====== */
    var $versionLink   = $('#epoinVersionLink');
    var currentVersion = ($versionLink.data('current-version') || '').toString().trim();
    var latestVersion  = '3.3.0'; // simulasi versi terbaru

    var isChecking=false, isUpdating=false, updateDone=false;

    function cmpVer(a,b){
      var pa=a.split('.').map(Number), pb=b.split('.').map(Number);
      for(var i=0;i<Math.max(pa.length,pb.length);i++){ var x=pa[i]||0, y=pb[i]||0; if(x<y)return -1; if(x>y)return 1; }
      return 0;
    }
    function setReleaseHeading(version, isLatest){
      $('#releaseHeading').text((isLatest?'Ringkas Rilis Terbaru':'Ringkas Rilis (Versi Saat Ini)') + ' — v ' + (version||'-'));
    }
    function resetUpdaterUI(){
      $('#updateStatusCallout').show().html('<strong>Status:</strong> Tekan <em>Cek Pembaruan</em> untuk melihat apakah ada versi terbaru yang tersedia.');
      $('#latestInfo, #availableCallout, #upToDateCallout').hide();
      $('#updateProgressWrap, #updateDots, #updateSuccess').hide();
      $('#updateProgressBar').css('width','0%').attr('aria-valuenow',0);
      $('#checkLoaderWrap').hide();
      $('#btnCheckUpdate').prop('disabled',false).html('Cek Pembaruan');
      $('#btnStartUpdate').prop('disabled',true).removeClass('btn-soft').addClass('btn-grad-blue').text('Perbarui Sekarang').removeAttr('data-state');
      isChecking=false; isUpdating=false; updateDone=false;
    }

    $('#epoinVersionModal').on('show.bs.modal', function(){
      $('#currentVersionText').text(currentVersion || '-');
      resetUpdaterUI(); setReleaseHeading(currentVersion || '-', false);
    });

    $('#btnCheckUpdate').on('click', function(){
      if(isChecking) return; isChecking = true;
      var $btn=$(this), orig=$btn.html();
      $('#updateStatusCallout, #latestInfo, #availableCallout, #upToDateCallout').hide();
      $('#checkLoaderWrap').show();
      $btn.prop('disabled',true).html('<span class="btn-spinner"></span>Mengecek…');
      var delay = 1200 + Math.floor(Math.random()*1000);
      setTimeout(function(){
        $('#checkLoaderWrap').hide(); $('#latestInfo').show();
        if(!currentVersion){
          $('#availableCallout').show().html('ℹ️ Tidak dapat membaca versi saat ini. Definisikan <code>EPOIN_VERSION</code> di <em>includes/theme_brand.php</em>.');
          $('#btnStartUpdate').prop('disabled',true); setReleaseHeading('-',false);
        } else if (cmpVer(currentVersion, latestVersion) < 0){
          $('#availableCallout').show(); $('#latestVersionText').text(latestVersion);
          $('#btnStartUpdate').prop('disabled',false); setReleaseHeading(latestVersion,true);
        } else { $('#upToDateCallout').show(); $('#btnStartUpdate').prop('disabled',true); setReleaseHeading(currentVersion,false); }
        $btn.prop('disabled',false).html(orig); isChecking=false;
      }, delay);
    });

    $('#btnStartUpdate').on('click', function(){
      var $btn=$(this);
      if(updateDone){ $btn.prop('disabled',true); $('#epoinVersionModal').modal('hide'); return; }
      if(isUpdating || $btn.prop('disabled')) return; isUpdating=true;

      $btn.prop('disabled',true).text('Memperbarui…');
      $('#availableCallout').hide(); $('#updateProgressWrap').show(); $('#updateDots').show().text('Mengunduh ');
      var dots=['•','••','•••','••••'], di=0, dotTimer=setInterval(function(){ $('#updateDots').html('Mengunduh <span class="dot">'+dots[di%dots.length]+'</span>'); di++; },300);
      var p=0, timer=setInterval(function(){
        p += Math.floor(Math.random()*14)+6; if(p>100)p=100;
        $('#updateProgressBar').css('width', p+'%').attr('aria-valuenow', p);
        if(p>=100){ clearInterval(timer); setTimeout(function(){
          clearInterval(dotTimer); $('#updateDots').hide(); $('#updateSuccess').show().text('✔ Update berhasil dipasang ke ' + latestVersion);
          currentVersion = latestVersion; $('#currentVersionText').text(currentVersion);
          $versionLink.data('current-version', currentVersion).html('Versi ' + currentVersion);
          updateDone=true; isUpdating=false;
          $btn.text('Selesai').removeClass('btn-grad-blue').addClass('btn-soft').prop('disabled',false).attr('data-state','done');
          setReleaseHeading(currentVersion,false);
        },600);}
      },420);
    });

    /* ====== Tap feedback kartu benefit (sesuai admin) ====== */
    (function benefitTapFeedback(){
      var items = document.querySelectorAll('#epoinAboutModal .eps-plus-head .eps-benefit');
      if(!items.length) return;
      items.forEach(function(el){
        el.addEventListener('touchstart', function(){
          el.classList.add('is-tap');
          setTimeout(function(){ el.classList.remove('is-tap'); }, 650);
        }, {passive:true});
      });
    })();

    /* ====== Netralisir padding-right Bootstrap saat modal ====== */
    $(document).on('shown.bs.modal', '.modal', function(){
      $('body, .main-header, .main-footer, .content-wrapper, .right-side').css('padding-right','0');
    });
    $(document).on('hidden.bs.modal', '.modal', function(){
      $('body, .main-header, .main-footer, .content-wrapper, .right-side').css('padding-right','');
    });

  });
</script>

<!-- ============== ANTI-JEDUG / RIGHT GAP FIX (PASTEKAN DI PALING BAWAH) ============== -->
<style>
  /* 1) Tampilkan scrollbar vertikal permanen agar lebar viewport tidak berubah saat modal muncul */
  html { overflow-y: scroll; }

  /* 2) Netralisir kompensasi padding-right dari Bootstrap saat .modal-open */
  body.modal-open { padding-right: 0 !important; }

  .modal-open .navbar-fixed-top,
  .modal-open .navbar-fixed-bottom,
  .modal-open .main-header,
  .modal-open .main-footer,
  .modal-open .content-wrapper,
  .modal-open .right-side,
  .modal-open .wrapper {
    padding-right: 0 !important;
    margin-right: 0 !important;
    width: 100% !important;
  }

  /* 3) Pastikan backdrop/overflow tidak membuat konten terasa “mengecil” */
  .modal { overflow-y: auto; }

  /* 4) AdminLTE: cegah background control-sidebar “mengintip” di kanan */
  .control-sidebar-bg { right: -230px; }
  .modal-open .control-sidebar-bg { right: -230px !important; }
</style>

<!-- Font Awesome 4 (agar ikon WA muncul, sama seperti admin) -->
<link rel="stylesheet" href="../assets/bower_components/font-awesome/css/font-awesome.min.css">

</body>
</html>
