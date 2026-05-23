<?php include 'header.php'; ?>

<?php
// ==== Helper escape ====
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ==== CSRF sederhana (samakan dg helper proyekmu bila ada) ====
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(32)); }
$CSRF_NAME  = '_csrf';
$CSRF_TOKEN = $_SESSION['_csrf'];

// ==== Ambil ID & data awal (aman) ====
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger">ID tidak valid.</div></section></div>';
  include 'footer.php'; exit;
}

$sql = "SELECT ip.*,
               s.siswa_id, s.siswa_nama, s.siswa_nis,
               p.pelanggaran_id, p.pelanggaran_nama, p.pelanggaran_point,
               k.kelas_id, k.kelas_nama,
               j.jurusan_id, j.jurusan_nama,
               t.ta_id, t.ta_nama, t.ta_status
        FROM input_pelanggaran ip
        JOIN siswa s       ON ip.siswa       = s.siswa_id
        JOIN pelanggaran p ON ip.pelanggaran = p.pelanggaran_id
        JOIN kelas k       ON ip.kelas       = k.kelas_id
        JOIN jurusan j     ON k.kelas_jurusan= j.jurusan_id
        JOIN ta t          ON k.kelas_ta     = t.ta_id
        WHERE ip.id = ? LIMIT 1";

if (!$stmt = mysqli_prepare($koneksi, $sql)) {
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger">Gagal menyiapkan query.</div></section></div>';
  include 'footer.php'; exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$pel = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$pel) {
  echo '<div class="content-wrapper"><section class="content"><div class="alert alert-warning">Data tidak ditemukan.</div></section></div>';
  include 'footer.php'; exit;
}

// Nilai awal untuk preselect JS
$INIT_TA     = (int)$pel['ta_id'];
$INIT_KELAS  = (int)$pel['kelas_id'];
$INIT_SISWA  = (int)$pel['siswa_id'];

// Format tanggal & jam dari kolom 'waktu'
$init_tanggal = date('Y-m-d', strtotime($pel['waktu']));
$init_jam     = date('H:i',   strtotime($pel['waktu']));
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

  /* === Select2 === */
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

  /* Loader & hint */
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

  .ipl-hint{
    display:flex; align-items:center; gap:10px; padding:10px 12px;
    background:linear-gradient(90deg, var(--r100), var(--r050));
    border:1.5px dashed var(--r500);
    border-radius:12px; color:var(--r900);
    box-shadow:0 2px 8px rgba(239, 68, 68, .08);
  }
  .ipl-hint .dot{
    width:12px; height:12px; border-radius:999px; background:var(--r600);
    box-shadow:0 0 0 0 rgba(214, 31, 31, .7);
    animation:dotPulse 1.25s ease-out infinite;
  }
  @keyframes dotPulse{
    0%{box-shadow:0 0 0 0 rgba(16,185,129,.0)}
    70%{box-shadow:0 0 0 8px rgba(214,31,31,0)}
    100%{box-shadow:0 0 0 0 rgba(214,31,31,0)}
  }

  /* === Bar terkunci (badge posisi saat ini) === */
  .ipl-lockline{
    display:flex; flex-wrap:wrap; gap:8px; align-items:center;
    background:linear-gradient(90deg,var(--r100),var(--r050));
    border:1.5px solid var(--r200); border-left:4px solid var(--r600);
    border-radius:12px; padding:10px 12px; box-shadow:0 2px 8px rgba(239,68,68,.06);
  }
  .ipl-lockline .pill{
    background:#fff; border:1px solid var(--border); border-radius:999px; padding:6px 10px;
    display:inline-flex; gap:6px; align-items:center; font-weight:700; color:#111827;
  }
  .ipl-mini-note{ font-size:.9em }

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
      <small class="sub">Edit Pelanggaran</small>
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
            <h3 class="box-title"><i class="fa fa-pencil" style="color:var(--r700)"></i> Edit Pelanggaran</h3>
            <a href="input_pelanggaran.php" class="btn btn-sm pull-right btn-back" title="Kembali ke daftar">
              <i class="fa fa-reply"></i> &nbsp;Kembali
            </a>
          </div>

          <div class="box-body">
            <form action="input_pelanggaran_update.php" method="post" accept-charset="UTF-8" id="frmPelanggaranEdit" autocomplete="off">
              <input type="hidden" name="id" value="<?= (int)$pel['id'] ?>">
              <input type="hidden" name="<?= e($CSRF_NAME) ?>" value="<?= e($CSRF_TOKEN) ?>">

              <!-- ==== Posisi Saat Ini (TERKUNCI) ==== -->
              <div class="form-group">
                <label><span class="label-ico"><i class="fa fa-lock"></i></span> Posisi Saat Ini</label>
                <div class="ipl-lockline" title="Dikunci agar tidak sengaja pindah kelas/siswa">
                  <span class="pill"><i class="fa fa-calendar"></i> <?= e($pel['ta_nama']) ?><?= $pel['ta_status']=='1' ? ' (Aktif)' : '' ?></span>
                  <span class="pill"><i class="fa fa-university"></i> <?= e($pel['jurusan_nama'].' | '.$pel['kelas_nama']) ?></span>
                  <span class="pill"><i class="fa fa-user"></i> <?= e($pel['siswa_nis'].' - '.$pel['siswa_nama']) ?></span>

                  <button type="button" id="btnUbahRelasi" class="btn btn-xs btn-warning pull-right"
                          data-toggle="tooltip" data-placement="left"
                          title="Klik untuk memindahkan data ke kelas/siswa lain secara sadar">
                    <i class="fa fa-exchange"></i> Ubah Kelas/Siswa
                  </button>
                </div>

                <!-- kirim nilai lama jika tetap terkunci -->
                <input type="hidden" name="ta"    id="ta_hidden"    value="<?= (int)$pel['ta_id'] ?>">
                <input type="hidden" name="kelas" id="kelas_hidden" value="<?= (int)$pel['kelas_id'] ?>">
                <input type="hidden" name="siswa" id="siswa_hidden" value="<?= (int)$pel['siswa_id'] ?>">

                <small class="text-muted">Relasi dikunci secara default. Gunakan tombol <b>Ubah Kelas/Siswa</b> bila memang ingin memindahkan catatan ini.</small>
              </div>

              <!-- ==== Area re-assign (disembunyikan sampai tombol ditekan) ==== -->
              <div id="reassignWrap" style="display:none">
                <div class="form-group">
                  <label for="ta_select"><span class="label-ico"><i class="fa fa-calendar"></i></span> Tahun Ajaran</label>
                  <!-- sengaja TANPA name: akan diisi name='ta' hanya saat reassign -->
                  <select class="form-control pilih_ta" id="ta_select" aria-required="true">
                    <option value="">- Pilih Tahun Ajaran -</option>
                    <?php
                    $ta = mysqli_query($koneksi,"SELECT * FROM ta ORDER BY ta_status DESC, ta_nama DESC");
                    while($j = mysqli_fetch_assoc($ta)){
                      $idta=(int)$j['ta_id']; $nama=e($j['ta_nama']); $aktif=($j['ta_status']=="1");
                      $label=$aktif ? "$nama (Aktif)" : $nama;
                      $sel = ($idta === $INIT_TA) ? 'selected' : '';
                      echo "<option value=\"$idta\" $sel>$label</option>";
                    }
                    ?>
                  </select>
                  <div class="help-inline text-danger ipl-mini-note" style="margin-top:6px">
                    <i class="fa fa-info-circle"></i> Mengganti <b>Tahun Ajaran/Kelas</b> akan me-reset pilihan siswa.
                  </div>
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
              </div>

              <div class="form-group">
                <label for="tgl_input"><span class="label-ico"><i class="fa fa-calendar-o"></i></span> Tanggal</label>
                <input type="date" class="form-control" id="tgl_input" name="tanggal" required value="<?= e($init_tanggal) ?>">
              </div>

              <div class="form-group">
                <label for="jam_input"><span class="label-ico"><i class="fa fa-clock-o"></i></span> Jam</label>
                <input type="time" class="form-control" id="jam_input" name="jam" required value="<?= e($init_jam) ?>">
              </div>

              <div class="form-group">
                <label for="pelanggaran_select"><span class="label-ico"><i class="fa fa-ban"></i></span> Pelanggaran</label>
                <select id="pelanggaran_select" class="form-control" name="pelanggaran" required aria-label="Cari pelanggaran">
                  <option value=""> - Pilih Pelanggaran - </option>
                  <?php
                  $pelanggaran = mysqli_query($koneksi,"SELECT pelanggaran_id,pelanggaran_nama,pelanggaran_point FROM pelanggaran ORDER BY pelanggaran_nama ASC");
                  while($j = mysqli_fetch_assoc($pelanggaran)){
                    $idp=(int)$j['pelanggaran_id'];
                    $nama=e($j['pelanggaran_nama']);
                    $poin=(int)$j['pelanggaran_point'];
                    $sel = ($idp === (int)$pel['pelanggaran_id']) ? 'selected' : '';
                    echo "<option value=\"$idp\" $sel>$nama ($poin Point)</option>";
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <button type="submit" class="btn btn-submit" id="btnSimpan">
                  <span class="btn-text"><i class="fa fa-save"></i> Simpan Perubahan</span>
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
  // Bootstrap tooltip (jika tersedia)
  if ($.fn.tooltip) { $('[data-toggle="tooltip"]').tooltip(); }

  // ====== UTIL ======
  function initSelect2($el, placeholder){
    if(!$el || !$el.length) return;
    $el.select2({
      width: '100%',
      placeholder: placeholder || 'Pilih…',
      allowClear: true,
      minimumResultsForSearch: 0,
      dropdownParent: $('.content-wrapper.input-pelanggaran-page')
    }).on('select2:open', function(){
      var sb = document.querySelector('.select2-container--open .select2-search__field');
      if(sb){ sb.focus(); }
    }).on('change', function(){
      $(this).closest('.select2-container').addClass('ipl-changed');
      var el = this; setTimeout(function(){ $(el).closest('.select2-container').removeClass('ipl-changed'); }, 650);
    });
  }

  function loaderHTML(text){ return '<span class="ipl-inline-loader"><span class="ipl-spinner"></span><span>'+(text||'Memuat…')+'</span></span>'; }
  function showHintKelas(show){ $('#hintKelasWrap').toggle(!!show); }

  let xhrKelas=null, xhrSiswa=null;

  // ====== AJAX LOADER DENGAN PRESELECT ======
  function loadKelas(taId, preKelas){
    if(xhrKelas){ xhrKelas.abort(); xhrKelas=null; }
    if(!taId){ $('.pilih_kelas').empty(); $('.pilih_siswa').empty(); showHintKelas(true); return; }
    $('.pilih_kelas').html(loaderHTML('Memuat kelas…'));
    xhrKelas = $.get('ajax_kelas_by_ta.php', { ta: taId }, function(html){
      $('.pilih_kelas').html(html);
      var $kelas = $('#kelas_select');
      if(!$kelas.length){ // jaga2 jika output tidak memakai id ini
        $kelas = $('.pilih_kelas select').first().attr('id','kelas_select');
      }
      $kelas.attr('name','kelas'); // pastikan terkirim
      initSelect2($kelas, 'Cari kelas…');

      // Handler perubahan kelas → muat siswa
      $kelas.off('change').on('change', function(){
        var v=this.value; showHintKelas(!v); loadSiswa(v);
      });

      // Preselect kelas jika ada
      if(preKelas){ $kelas.val(String(preKelas)).trigger('change.select2'); showHintKelas(false); loadSiswa(preKelas, INIT.siswa); }
      else { var current=$kelas.val(); showHintKelas(!current); if(current){ loadSiswa(current); } else { $('.pilih_siswa').html(''); } }
    }).fail(function(xhr){
      if (xhr.statusText==='abort') return;
      $('.pilih_kelas').html('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal memuat kelas ('+xhr.status+').</div>');
      showHintKelas(true);
    }).always(function(){ xhrKelas=null; });
  }

  function loadSiswa(kelasId, preSiswa){
    if(xhrSiswa){ xhrSiswa.abort(); xhrSiswa=null; }
    if(!kelasId){ $('.pilih_siswa').empty(); return; }
    $('.pilih_siswa').html(loaderHTML('Memuat siswa…'));
    xhrSiswa = $.get('ajax_siswa_by_kelas.php', { kelas: kelasId }, function(html){
      $('.pilih_siswa').html(html);
      var $siswa = $('#siswa_select');
      if(!$siswa.length){ // jaga2 jika output tidak memakai id ini
        $siswa = $('.pilih_siswa select').first().attr('id','siswa_select');
      }
      $siswa.attr('name','siswa'); // pastikan terkirim
      initSelect2($siswa, 'Cari siswa…');
      if(preSiswa){ $siswa.val(String(preSiswa)).trigger('change.select2'); }
    }).fail(function(xhr){
      if (xhr.statusText==='abort') return;
      $('.pilih_siswa').html('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> Gagal memuat siswa ('+xhr.status+').</div>');
    }).always(function(){ xhrSiswa=null; });
  }

  // ====== TOGGLE: terkunci → re-assign ======
  var reassign = false;

  function enterReassignMode(){
    if(reassign) return;
    reassign = true;
    $('#reassignWrap').slideDown(160);

    // Hidden lama dinonaktifkan supaya tidak dobel kirim
    $('#ta_hidden, #kelas_hidden, #siswa_hidden').prop('disabled', true);

    // Inisialisasi TA reassign
    var $ta = $('#ta_select');
    initSelect2($ta, 'Pilih tahun ajaran…');
    $ta.off('change').on('change', function(){ loadKelas(this.value); });

    // Muat kelas & siswa berdasar nilai awal
    var taId = $ta.val();
    if(taId){ loadKelas(taId, INIT.kelas); } else { $('.pilih_kelas').empty(); $('.pilih_siswa').empty(); showHintKelas(true); }
  }

  $('#btnUbahRelasi').on('click', function(){
    if(!reassign){
      if(confirm('Anda akan mengubah relasi TA/Kelas/Siswa untuk catatan ini. Lanjutkan?')){
        enterReassignMode();
      }
    }
  });

  // ====== INIT umum ======
  initSelect2($('#pelanggaran_select'), 'Cari pelanggaran…');

  // Nilai awal
  window.INIT = {
    ta:     <?= json_encode($INIT_TA) ?>,
    kelas:  <?= json_encode($INIT_KELAS) ?>,
    siswa:  <?= json_encode($INIT_SISWA) ?>
  };

  // (PENTING) Di mode TERKUNCI: kita tidak memuat Select2 kelas/siswa.
  // Nilai lama dikirim via hidden input.

  // Submit UX + penjagaan nilai
  $('#frmPelanggaranEdit').on('submit', function(){
    if(reassign){
      // Aktifkan name untuk TA reassign
      $('#ta_select').attr('name','ta');
      // Pastikan select kelas/siswa ada dan terisi
      var k = $('#kelas_select').val(), s = $('#siswa_select').val();
      if(!k || !s){
        alert('Pilih Kelas dan Siswa terlebih dahulu.');
        return false;
      }
    }else{
      // Tetap terkunci: hidden harus aktif
      $('#ta_hidden, #kelas_hidden, #siswa_hidden').prop('disabled', false);
    }

    $('#btnSimpan').prop('disabled', true);
    $('#btnSimpan .btn-text').hide(); $('#btnSimpan .btn-wait').show();
  });
});
</script>

<?php include 'footer.php'; ?>
