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

<style>
  /* === GLOBAL THEME VARS (agar terbaca oleh dropdown Select2 di manapun) === */
  :root{
    --r950:#3b0a0a; --r900:#541212; --r850:#6b0f0f; --r800:#801515;
    --r700:#b11616; --r600:#d61f1f; --r550:#e52a2a; --r500:#ef2f2f;
    --r200:#fdeaea; --r100:#fff6f6; --r050:#fffafa;
    --border:#e5e7eb; --muted:#6b7280; --muted-2:#94a3b8;
  }

  .content-wrapper.input-pelanggaran-page{ --ink:#1b2559; position: relative; }
  .input-pelanggaran-page{ --shadow:0 8px 24px rgba(209,38,38,.18); --shadow-2:0 12px 28px rgba(209,38,38,.24); animation:ipl-fade .45s ease-out both; }
  @keyframes ipl-fade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

  .input-pelanggaran-page .title-wrap{ display:flex; align-items:center; gap:10px; }
  .input-pelanggaran-page .title-wrap .sub{ color:var(--muted-2); }

  .input-pelanggaran-page .hero-ico{
    width:42px;height:42px;border-radius:12px;display:inline-grid;place-items:center;
    background:linear-gradient(135deg,var(--r600),var(--r700)); color:#fff;
    box-shadow:0 10px 24px rgba(209, 38, 38, .25);
    animation:ipl-pop .45s ease-out both .05s;
  }
  @keyframes ipl-pop{from{opacity:0; transform:scale(.85) translateY(4px)} to{opacity:1; transform:none}}

  .input-pelanggaran-page .box{ border-radius:14px; overflow:hidden; box-shadow:var(--shadow); }
  .input-pelanggaran-page .box .box-header{
    border:0; padding:16px 18px;
    background:linear-gradient(90deg,var(--r100),#fff 60%);
    border-top:4px solid var(--r600);
  }
  .input-pelanggaran-page .box .box-title{ margin:0; display:flex; align-items:center; gap:8px; }
  .input-pelanggaran-page .box .box-body{ background:linear-gradient(180deg,#fff,var(--r050)); padding:22px 18px 18px; }

  /* Tombol kembali */
  .input-pelanggaran-page .btn-back{
    background:linear-gradient(135deg,#e5e7eb,#d1d5db);
    color:#111!important; border:0; border-radius:999px; font-weight:700;
    padding:10px 14px; box-shadow:0 6px 14px rgba(17,24,39,.12);
    transition:transform .1s, box-shadow .2s, filter .2s;
  }
  .input-pelanggaran-page .btn-back:hover{ transform:translateY(-2px); box-shadow:0 10px 20px rgba(17,24,39,.18); }

  /* Submit */
  .input-pelanggaran-page .btn-submit{
    background:linear-gradient(120deg,var(--r600),var(--r700));
    border:0; color:#fff!important; border-radius:10px; font-weight:800;
    padding:10px 16px; letter-spacing:.2px;
    box-shadow:var(--shadow);
    transition:transform .1s, box-shadow .25s, filter .2s;
  }
  .input-pelanggaran-page .btn-submit:hover{ transform:translateY(-2px); box-shadow:var(--shadow-2); filter:saturate(1.05); }
  .input-pelanggaran-page .btn-submit:active{ transform:translateY(0) scale(.98); }

  /* Form polish */
  .input-pelanggaran-page .form-group{ margin-bottom:14px; }
  .input-pelanggaran-page label{ font-weight:700; color:#111827; display:flex; align-items:center; gap:8px; }
  .input-pelanggaran-page .label-ico{
    color:#fff; background:linear-gradient(135deg,var(--r600),var(--r700)); width:20px; height:20px;
    border-radius:6px; display:inline-grid; place-items:center; font-size:12px; box-shadow:0 4px 10px rgba(209,38,38,.25);
  }
  .input-pelanggaran-page input[type="date"],
  .input-pelanggaran-page input[type="time"],
  .input-pelanggaran-page select.form-control{
    border-radius:10px; border:1.2px solid var(--border); box-shadow:none;
    transition:border-color .15s, box-shadow .2s;
  }
  .input-pelanggaran-page input[type="date"]:focus,
  .input-pelanggaran-page input[type="time"]:focus,
  .input-pelanggaran-page select.form-control:focus{
    border-color:var(--r600);
    box-shadow:0 0 0 3px rgba(214,31,31,.15);
  }

  /* === Select2: kontras kuat untuk hover/aktif === */
  .select2-container .select2-selection--single{
    height:38px; padding:5px 8px; border-radius:10px!important; border:1.2px solid var(--border);
    transition:border-color .15s, box-shadow .2s;
  }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height:28px; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height:36px; }
  .select2-container--default.select2-container--open .select2-selection--single{
    border-color:var(--r600)!important; box-shadow:0 0 0 3px rgba(214,31,31,.15);
  }
  .select2-container--open .select2-dropdown{ z-index:2050; }
  .select2-dropdown{ border:1px solid var(--r600); background:#fff; }

  .select2-container--default .select2-results__option--highlighted[aria-selected],
  .select2-container--default .select2-results__option--highlighted{
    background:linear-gradient(90deg,var(--r700,#b11616),var(--r800,#801515))!important;
    color:#fff!important; font-weight:800;
  }
  .select2-container--default .select2-results__option[aria-selected=true]{
    background:linear-gradient(90deg,#fff,var(--r200,#fdeaea))!important;
    color:#111!important; font-weight:700;
  }
  .select2-results__option:hover{
    background:linear-gradient(90deg,var(--r700,#b11616),var(--r800,#801515))!important;
    color:#fff!important; font-weight:800;
  }

  /* Loader kecil */
  .ipl-inline-loader{
    display:inline-flex; align-items:center; gap:8px; padding:8px 10px;
    background:#fff; border:1px dashed var(--r600); border-radius:10px; color:#7f1d1d;
  }
  .ipl-spinner{
    width:16px; height:16px; border:2.5px solid rgba(214,31,31,.22);
    border-top-color:var(--r600); border-radius:50%; animation:ipl-spin 1s linear infinite;
  }
  @keyframes ipl-spin{ to{ transform:rotate(1turn);} }

  .ipl-changed{ animation:ipl-pulse 600ms ease-out 1; }
  @keyframes ipl-pulse{ 0%{box-shadow:0 0 0 0 rgba(214,31,31,0)} 40%{box-shadow:0 0 0 6px rgba(214,31,31,.12)} 100%{box-shadow:0 0 0 0 rgba(214,31,31,0)} }

  /* ===== HINT: "Pilih Kelas..." versi MERAH ===== */
  .ipl-hint{
    display:flex; align-items:center; gap:10px; padding:10px 12px;
    /* latar merah muda lembut + tetap kontras */
    background:linear-gradient(90deg, var(--r100), var(--r050));
    border:1.5px dashed var(--r500);
    border-radius:12px;
    color:var(--r900);
    box-shadow:0 2px 8px rgba(239, 68, 68, .08);
  }
  .ipl-hint .dot{
    width:12px; height:12px; border-radius:999px;
    background:var(--r600);
    /* sorotan kedip merah */
    box-shadow:0 0 0 0 rgba(214, 31, 31, .7);
    animation:dotPulse 1.25s ease-out infinite;
  }
  @keyframes dotPulse{
    0%{box-shadow:0 0 0 0 rgba(16,185,129,.0)}
    70%{box-shadow:0 0 0 8px rgba(214,31,31,0)}
    100%{box-shadow:0 0 0 0 rgba(214,31,31,0)}
  }

  @media (prefers-reduced-motion:reduce){
    .input-pelanggaran-page,.input-pelanggaran-page .hero-ico,.input-pelanggaran-page .btn-submit{ animation:none; transition:none; }
    .ipl-hint .dot{ animation:none; }
  }
</style>

<div class="content-wrapper input-pelanggaran-page">
  <section class="content-header">
    <h1 class="title-wrap">
      <span class="hero-ico"><i class="fa fa-exclamation-triangle"></i></span>
      <span>Pelanggaran Siswa</span>
      <small class="sub">Input Pelanggaran</small>
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
            <h3 class="box-title"><i class="fa fa-gavel" style="color:var(--r700)"></i> Input Pelanggaran Baru</h3>
            <a href="input_pelanggaran.php" class="btn btn-sm pull-right btn-back" title="Kembali ke daftar"><i class="fa fa-reply"></i> &nbsp;Kembali</a>
          </div>

          <div class="box-body">
            <form action="input_pelanggaran_act.php" method="post" accept-charset="UTF-8" id="frmPelanggaran" autocomplete="off">
              <input type="hidden" name="<?= e($CSRF_NAME) ?>" value="<?= e($CSRF_TOKEN) ?>">

              <div class="form-group">
                <label for="ta_select"><span class="label-ico"><i class="fa fa-calendar"></i></span> Tahun Ajaran</label>
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

              <div class="form-group">
                <label for="kelas_select"><span class="label-ico"><i class="fa fa-university"></i></span> Pilih Kelas</label>
                <div class="pilih_kelas" aria-live="polite"><!-- select kelas via AJAX --></div>
              </div>

              <div class="form-group" id="hintKelasWrap" style="display:none">
                <div class="ipl-hint" role="status" aria-live="polite">
                  <span class="dot" aria-hidden="true"></span>
                  <div><b>Pilih <span>Kelas</span></b> <small>untuk memunculkan daftar siswa.</small></div>
                </div>
              </div>

              <div class="form-group">
                <label for="siswa_select"><span class="label-ico"><i class="fa fa-user"></i></span> Siswa</label>
                <div class="pilih_siswa" aria-live="polite"><!-- select siswa via AJAX --></div>
              </div>

              <div class="form-group">
                <label for="tgl_input"><span class="label-ico"><i class="fa fa-calendar-o"></i></span> Tanggal</label>
                <input type="date" class="form-control" id="tgl_input" name="tanggal" required>
              </div>

              <div class="form-group">
                <label for="jam_input"><span class="label-ico"><i class="fa fa-clock-o"></i></span> Jam</label>
                <input type="time" class="form-control" id="jam_input" name="jam" required>
              </div>

              <div class="form-group">
                <label for="pelanggaran_select"><span class="label-ico"><i class="fa fa-ban"></i></span> Pelanggaran</label>
                <select id="pelanggaran_select" class="form-control" name="pelanggaran" required aria-label="Cari pelanggaran">
                  <option value=""> - Pilih Pelanggaran - </option>
                  <?php 
                  $pel = mysqli_query($koneksi,"SELECT pelanggaran_id,pelanggaran_nama,pelanggaran_point FROM pelanggaran ORDER BY pelanggaran_nama ASC");
                  while($j = mysqli_fetch_assoc($pel)){
                    $id=(int)$j['pelanggaran_id']; $nama=e($j['pelanggaran_nama']); $poin=(int)$j['pelanggaran_point'];
                    echo "<option value=\"$id\">$nama ($poin Point)</option>";
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

<script>
$(function(){
  function initSelect2($el, placeholder){
    if(!$el || !$el.length) return;
    $el.select2({
      width: '100%',
      placeholder: placeholder || 'Pilih…',
      allowClear: true,
      minimumResultsForSearch: 0,
      dropdownParent: $('.content-wrapper.input-pelanggaran-page')
    }).on('select2:open', function(){
      var sb = document.querySelector('.select2-container--open .select2-search__field'); if(sb){ sb.focus(); }
    }).on('change', function(){
      $(this).closest('.select2-container').addClass('ipl-changed');
      var el = this; setTimeout(function(){ $(el).closest('.select2-container').removeClass('ipl-changed'); }, 650);
    });
  }

  function loaderHTML(text){ return '<span class="ipl-inline-loader"><span class="ipl-spinner"></span><span>'+(text||'Memuat…')+'</span></span>'; }
  function showHintKelas(show){ $('#hintKelasWrap').toggle(!!show); }

  let xhrKelas=null, xhrSiswa=null;

  function loadKelas(taId){
    if(xhrKelas){ xhrKelas.abort(); xhrKelas=null; }
    if(!taId){ $('.pilih_kelas').empty(); $('.pilih_siswa').empty(); showHintKelas(true); return; }
    $('.pilih_kelas').html(loaderHTML('Memuat kelas…'));
    xhrKelas = $.get('ajax_kelas_by_ta.php', { ta: taId }, function(html){
      $('.pilih_kelas').html(html);
      var $kelas = $('#kelas_select'); initSelect2($kelas, 'Cari kelas…');

      $kelas.on('change', function(){ var v=this.value; showHintKelas(!v); loadSiswa(v); });

      var current=$kelas.val();
      showHintKelas(!current);
      if(current){ loadSiswa(current); } else { $('.pilih_siswa').html(''); }
    }).fail(function(xhr){
      if (xhr.statusText==='abort') return;
      $('.pilih_kelas').html('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal memuat kelas ('+xhr.status+').</div>');
      showHintKelas(true);
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
      if (xhr.statusText==='abort') return;
      $('.pilih_siswa').html('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal memuat siswa ('+xhr.status+').</div>');
    }).always(function(){ xhrSiswa=null; });
  }

  // Inisialisasi
  initSelect2($('#pelanggaran_select'), 'Cari pelanggaran…');
  $('.pilih_ta').on('change', function(){ loadKelas(this.value); });

  var initTa = $('.pilih_ta').val();
  if(initTa){ loadKelas(initTa); } else { showHintKelas(true); }

  $('#frmPelanggaran').on('submit', function(){
    $('#btnSimpan').prop('disabled', true);
    $('#btnSimpan .btn-text').hide(); $('#btnSimpan .btn-wait').show();
  });
});
</script>

<?php include 'footer.php'; ?>
