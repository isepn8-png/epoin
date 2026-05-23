<?php include 'header.php'; ?>

<?php
// Helper escape
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// CSRF sederhana (sesuaikan dengan helper proyekmu bila ada)
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(32)); }
$CSRF_NAME  = '_csrf';
$CSRF_TOKEN = $_SESSION['_csrf'];
?>

<!-- ========= THEME (GREEN) + POLISH ========= -->
<style>
  /* Global vars supaya Select2 dropdown (di luar scope) tetap mewarisi warna */
  :root{
    --g900:#064e3b; --g800:#065f46; --g700:#047857; --g650:#0a7b5f;
    --g600:#059669; --g500:#10b981; --g400:#34d399; --g300:#6ee7b7;
    --g200:#dcfce7; --g100:#f0fdf4; --g050:#f6fef9;
    --border:#e5e7eb; --ink:#1f2937; --muted:#6b7280; --muted-2:#94a3b8;
  }

  .content-wrapper.form-prestasi-page{ position:relative; }

  .form-prestasi-page{ animation:fadeLift .45s ease-out both; }
  @keyframes fadeLift{from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:none}}

  /* Header hero */
  .form-prestasi-page .content-header{ padding-bottom:8px; }
  .hero-title{ display:flex; align-items:center; gap:10px; }
  .hero-title .hero-icon{
    width:42px; height:42px; border-radius:12px; display:inline-grid; place-items:center;
    background:linear-gradient(135deg, var(--g600), var(--g700)); color:#fff;
    box-shadow:0 10px 24px rgba(16,185,129,.25);
    animation:popIn .45s ease-out both .05s;
  }
  .hero-title small{ color:var(--muted-2); }
  @keyframes popIn{from{opacity:0; transform:scale(.85) translateY(4px)} to{opacity:1; transform:none}}

  /* Card/box */
  .form-prestasi-page .box{ border-radius:14px; overflow:hidden; box-shadow:0 8px 24px rgba(16,185,129,.18); }
  .form-prestasi-page .box .box-header{
    border:0; padding:16px 18px;
    background:linear-gradient(90deg, var(--g100), #fff 60%);
    border-top:4px solid var(--g600);
  }
  .form-prestasi-page .box .box-title{ margin:0; display:flex; align-items:center; gap:8px; color:var(--g800); }
  .form-prestasi-page .box .box-body{ background:linear-gradient(180deg,#fff,var(--g050)); padding:22px 18px 18px; }

  /* Tombol Kembali (sama seperti versi pelanggaran – pill abu lembut) */
  .form-prestasi-page .btn-back{
    background:linear-gradient(135deg, #eef2f7, #e5e9f1);
    color:#111 !important; border:0; border-radius:999px; font-weight:700;
    padding:10px 16px; box-shadow:0 12px 28px rgba(15, 23, 42, .12);
    transition:transform .1s ease, box-shadow .2s ease, filter .2s ease;
  }
  .form-prestasi-page .btn-back:hover{ transform:translateY(-2px); box-shadow:0 16px 34px rgba(15, 23, 42, .16); }

  /* Submit */
  .form-prestasi-page .btn-submit{
    background:linear-gradient(120deg, var(--g600), var(--g700));
    border:0; color:#fff!important; border-radius:10px; font-weight:800;
    padding:10px 16px; letter-spacing:.2px;
    box-shadow:0 8px 24px rgba(16,185,129,.26);
    transition:transform .1s, box-shadow .25s, filter .2s;
  }
  .form-prestasi-page .btn-submit:hover{ transform:translateY(-2px); box-shadow:0 12px 30px rgba(16,185,129,.32); filter:saturate(1.05);}
  .form-prestasi-page .btn-submit:active{ transform:translateY(0) scale(.98); }

  /* Label + ikon */
  .form-prestasi-page .form-group{ margin-bottom:14px; }
  .form-prestasi-page label{ font-weight:700; color:#064e3b; display:flex; align-items:center; gap:8px; }
  .form-prestasi-page .label-ico{
    color:#fff; background:linear-gradient(135deg, var(--g600), var(--g700));
    width:20px; height:20px; border-radius:6px; display:inline-grid; place-items:center; font-size:12px;
    box-shadow:0 4px 10px rgba(16,185,129,.25);
  }

  /* Input/Select native */
  .form-prestasi-page input[type="date"],
  .form-prestasi-page input[type="time"],
  .form-prestasi-page select.form-control{
    border-radius:10px; border:1.2px solid var(--border); box-shadow:none;
    transition:border-color .15s ease, box-shadow .2s ease;
  }
  .form-prestasi-page input[type="date"]:focus,
  .form-prestasi-page input[type="time"]:focus,
  .form-prestasi-page select.form-control:focus{
    border-color:var(--g600);
    box-shadow:0 0 0 3px rgba(16,185,129,.18);
  }

  /* ===== Select2: hover kontras hijau tua + teks putih bold ===== */
  .select2-container .select2-selection--single{
    height:38px; padding:5px 8px; border-radius:10px!important; border:1.2px solid var(--border);
    transition:border-color .15s, box-shadow .2s;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height:28px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height:36px; }
  .select2-container--default.select2-container--open .select2-selection--single{
    border-color:var(--g600)!important; box-shadow:0 0 0 3px rgba(16,185,129,.18);
  }
  .select2-container--open .select2-dropdown{ z-index:2050; }
  .select2-dropdown{ border:1px solid var(--g600); background:#fff; }

  .select2-container--default .select2-results__option--highlighted[aria-selected],
  .select2-container--default .select2-results__option--highlighted{
    background:linear-gradient(90deg, var(--g700,#047857), var(--g800,#065f46)) !important;
    color:#fff !important; font-weight:800;
  }
  .select2-container--default .select2-results__option[aria-selected=true]{
    background:linear-gradient(90deg, #fff, var(--g200,#dcfce7)) !important;
    color:#111 !important; font-weight:700;
  }
  .select2-results__option:hover{
    background:linear-gradient(90deg, var(--g700,#047857), var(--g800,#065f46)) !important;
    color:#fff !important; font-weight:800;
  }

  /* Loader kecil & hint */
  .hint{ display:flex; align-items:center; gap:10px; padding:10px 12px;
    background:linear-gradient(90deg, var(--g100), var(--g050));
    border:1.5px dashed var(--g400); border-radius:12px; color:#065f46;
    box-shadow:0 2px 8px rgba(16,185,129,.08); }
  .hint .dot{ width:12px; height:12px; border-radius:999px; background:var(--g500);
    box-shadow:0 0 0 0 rgba(16,185,129,.7); animation:dotPulse 1.25s ease-out infinite; }
  @keyframes dotPulse{0%{box-shadow:0 0 0 0 rgba(16,185,129,.6)}70%{box-shadow:0 0 0 8px rgba(16,185,129,0)}100%{box-shadow:0 0 0 0 rgba(16,185,129,0)}}

  .inline-loader{ display:inline-flex; align-items:center; gap:8px; padding:8px 10px;
    background:#fff; border:1px dashed var(--g600); border-radius:10px; color:#065f46; }
  .spinner{ width:16px; height:16px; border:2.5px solid rgba(16,185,129,.22); border-top-color:var(--g600);
    border-radius:50%; animation:spin 1s linear infinite; }
  @keyframes spin{ to{ transform:rotate(1turn);} }

  @media (prefers-reduced-motion:reduce){
    .hero-icon,.form-prestasi-page{ animation:none; }
    .hint .dot,.spinner{ animation:none; }
  }
</style>
<!-- ========= /THEME ========= -->

<div class="content-wrapper form-prestasi-page">

  <section class="content-header">
    <h1 class="hero-title">
      <span class="hero-icon"><i class="fa fa-trophy"></i></span>
      <span>Prestasi Siswa</span>
      <small>Input Prestasi</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-6">
        <div class="box box-primary">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-clipboard-check" style="color:var(--g700)"></i> Input Prestasi Baru</h3>
            <a href="input_prestasi.php" class="btn btn-sm pull-right btn-back" title="Kembali ke daftar">
              <i class="fa fa-reply"></i> &nbsp;Kembali
            </a>
          </div>

          <div class="box-body">
            <form action="input_prestasi_act.php" method="post" accept-charset="UTF-8" id="frmPrestasi" autocomplete="off">
              <input type="hidden" name="<?= e($CSRF_NAME) ?>" value="<?= e($CSRF_TOKEN) ?>">

              <!-- TA (auto pilih yang aktif) -->
              <div class="form-group">
                <label for="ta_select"><span class="label-ico"><i class="fa fa-calendar-check-o"></i></span> Tahun Ajaran</label>
                <select class="form-control pilih_ta" id="ta_select" name="ta" required aria-required="true">
                  <option value="">- Pilih Tahun Ajaran -</option>
                  <?php
                  $ta = mysqli_query($koneksi,"SELECT * FROM ta ORDER BY ta_status DESC, ta_nama DESC");
                  while($j = mysqli_fetch_assoc($ta)){
                    $id=(int)$j['ta_id']; $nama=e($j['ta_nama']); $aktif=($j['ta_status']=="1");
                    $label=$aktif ? "$nama (Aktif)" : $nama; $sel=$aktif ? 'selected' : '';
                    echo "<option value=\"$id\" $sel>$label</option>";
                  } ?>
                </select>
              </div>

              <!-- Kelas (AJAX) -->
              <div class="form-group">
                <label for="kelas_select"><span class="label-ico"><i class="fa fa-university"></i></span> Pilih Kelas</label>
                <div class="pilih_kelas" aria-live="polite"></div>
              </div>

              <!-- Hint: pilih kelas untuk memunculkan siswa -->
              <div class="form-group" id="hintKelas" style="display:none">
                <div class="hint"><span class="dot" aria-hidden="true"></span>
                  <div><b>Pilih Kelas</b> untuk memunculkan daftar siswa.</div>
                </div>
              </div>

              <!-- Siswa (AJAX) -->
              <div class="form-group">
                <label for="siswa_select"><span class="label-ico"><i class="fa fa-user"></i></span> Siswa</label>
                <div class="pilih_siswa" aria-live="polite"></div>
              </div>

              <div class="form-group">
                <label for="tgl_input"><span class="label-ico"><i class="fa fa-calendar"></i></span> Tanggal</label>
                <input type="date" class="form-control" id="tgl_input" name="tanggal" required>
              </div>

              <div class="form-group">
                <label for="jam_input"><span class="label-ico"><i class="fa fa-clock-o"></i></span> Jam</label>
                <input type="time" class="form-control" id="jam_input" name="jam" required>
              </div>

              <!-- Prestasi list -->
              <div class="form-group">
                <label for="prestasi_select"><span class="label-ico"><i class="fa fa-trophy"></i></span> Prestasi</label>
                <select id="prestasi_select" class="form-control" name="prestasi" required aria-label="Cari prestasi">
                  <option value=""> - Pilih Prestasi - </option>
                  <?php
                  $res = mysqli_query($koneksi,"SELECT prestasi_id, prestasi_nama, prestasi_point FROM prestasi ORDER BY prestasi_nama ASC");
                  while($j = mysqli_fetch_assoc($res)){
                    $id=(int)$j['prestasi_id']; $nama=e($j['prestasi_nama']); $p=(int)$j['prestasi_point'];
                    echo "<option value=\"$id\">$nama ($p Point)</option>";
                  } ?>
                </select>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-submit" id="btnSimpan">
                  <span class="btn-text"><i class="fa fa-check"></i> Simpan</span>
                  <span class="btn-wait" style="display:none"><i class="fa fa-spinner fa-spin"></i> Menyimpan…</span>
                </button>
              </div>

            </form>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<!-- ========= INTERAKSI: Select2 + AJAX (abort, hint, loader, anti double submit) ========= -->
<script>
$(function(){
  function initSelect2($el, placeholder){
    if(!$el || !$el.length) return;
    $el.select2({
      width: '100%',
      placeholder: placeholder || 'Pilih…',
      allowClear: true,
      minimumResultsForSearch: 0,
      dropdownParent: $('.content-wrapper.form-prestasi-page') // agar mewarisi warna
    }).on('select2:open', function(){
      var sb = document.querySelector('.select2-container--open .select2-search__field'); if(sb){ sb.focus(); }
    });
  }

  function loaderHTML(txt){ return '<span class="inline-loader"><span class="spinner"></span><span>'+(txt||'Memuat…')+'</span></span>'; }
  function toggleHintKelas(show){ $('#hintKelas').toggle(!!show); }

  let xhrKelas=null, xhrSiswa=null;

  function loadKelas(taId){
    if(xhrKelas){ xhrKelas.abort(); xhrKelas=null; }
    if(!taId){
      $('.pilih_kelas').empty(); $('.pilih_siswa').empty(); toggleHintKelas(true); return;
    }
    $('.pilih_kelas').html(loaderHTML('Memuat kelas…'));
    xhrKelas = $.get('ajax_kelas_by_ta.php', { ta: taId }, function(html){
      $('.pilih_kelas').html(html);
      var $kelas = $('#kelas_select'); initSelect2($kelas, 'Cari kelas…');

      $kelas.on('change', function(){
        var v=this.value;
        toggleHintKelas(!v);
        loadSiswa(v);
      });

      var current = $kelas.val();
      toggleHintKelas(!current);
      if(current){ loadSiswa(current); } else { $('.pilih_siswa').html(''); }
    }).fail(function(xhr){
      if(xhr.statusText==='abort') return;
      $('.pilih_kelas').html('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal memuat kelas ('+xhr.status+').</div>');
      toggleHintKelas(true);
    }).always(function(){ xhrKelas=null; });
  }

  function loadSiswa(kelasId){
    if(xhrSiswa){ xhrSiswa.abort(); xhrSiswa=null; }
    if(!kelasId){ $('.pilih_siswa').empty(); return; }
    $('.pilih_siswa').html(loaderHTML('Memuat siswa…'));
    xhrSiswa = $.get('ajax_siswa_by_kelas.php', { kelas: kelasId }, function(html){
      $('.pilih_siswa').html(html);
      initSelect2($('#siswa_select'), 'Cari siswa…');
    }).fail(function(xhr){
      if(xhr.statusText==='abort') return;
      $('.pilih_siswa').html('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal memuat siswa ('+xhr.status+').</div>');
    }).always(function(){ xhrSiswa=null; });
  }

  // Inisialisasi Select2 utk Prestasi
  initSelect2($('#prestasi_select'), 'Cari prestasi…');

  // TA change
  $('.pilih_ta').on('change', function(){ loadKelas(this.value); });

  // Auto-load jika TA aktif sudah terseleksi
  var initTa = $('.pilih_ta').val();
  if(initTa){ loadKelas(initTa); } else { toggleHintKelas(true); }

  // Anti double submit
  $('#frmPrestasi').on('submit', function(){
    $('#btnSimpan').prop('disabled', true);
    $('#btnSimpan .btn-text').hide(); $('#btnSimpan .btn-wait').show();
  });
});
</script>

<?php include 'footer.php'; ?>
