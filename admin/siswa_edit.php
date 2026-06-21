<?php
include 'header.php';

// ====== Helper kecil & CSRF fallback ======
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(32)); }
$CSRF_NAME  = '_csrf';
$CSRF_TOKEN = $_SESSION['_csrf'];

include '../koneksi.php';

// ====== Ambil data siswa ======
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$q  = mysqli_query($koneksi, "SELECT * FROM siswa WHERE siswa_id='$id'");
$d  = mysqli_fetch_assoc($q);

if($d){
  $foto_now = (!empty($d['siswa_foto']) ? "../gambar/siswa/".$d['siswa_foto'] : "../gambar/sistem/user.png");
  $status   = strtolower($d['siswa_status'] ?? '');
} else {
  $foto_now = "../gambar/sistem/user.png";
  $status   = '';
}

// ====== util kelas status ======
function status_class($s){
  $s = strtolower(trim((string)$s));
  if($s==='aktif') return 'label-success';
  if($s==='tamat') return 'label-primary';
  if($s==='pindah') return 'label-info';
  if($s==='dikeluarkan') return 'label-danger';
  return 'label-default';
}
?>

<style>
:root{
  --o-50:#fff7ed;
  --o-100:#ffedd5;
  --o-200:#fed7aa;
  --o-300:#fdba74;
  --o-400:#fb923c;
  --o-500:#f97316; /* orange */
  --o-600:#ea580c;
  --o-700:#c2410c;
}

/* ====== THEME (ORANGE) ====== */
.box.box-info { border-top: 3px solid var(--o-500); }
.box-header.with-border{
  background: linear-gradient(90deg,var(--o-500),var(--o-400));
  color:#fff;
  padding:0; /* padding dipindah ke .header-row supaya posisi rapih */
  border-top-left-radius:3px; border-top-right-radius:3px;
}
/* Baris head: title nempel kiri, tombol kembali nempel kanan (ujung) */
.header-row{
  display:flex; align-items:center; justify-content:flex-start;
  gap:8px;
  padding:12px 16px; /* jarak kiri/kanan seragam */
  width:100%;
}
.header-row .box-title{
  margin:0; /* benar2 rata kiri dalam padding */
  display:flex; align-items:center; gap:10px; font-weight:700;
}
.header-row .box-title i{ font-size:18px; }
.header-actions{ margin-left:auto; } /* dorong ke ujung kanan */

/* Tombol kembali */
.btn-back{
  color:var(--o-600) !important; background:#fff; border:0;
  padding:6px 10px; border-radius:4px; line-height:1.2;
}
.btn-back:hover{ color:#fff !important; background:var(--o-500); }

/* Ubah warna utama tombol simpan jadi orange */
.btn-primary{ background:var(--o-500); border-color:var(--o-500); }
.btn-primary:hover{ background:var(--o-600); border-color:var(--o-600); }

/* ------ Header Accent Badge (tetap dipakai di judul) ------ */
.page-accent{
  display:inline-block; padding:.25rem .6rem; font-weight:600; border-radius:999px;
  background:linear-gradient(135deg,var(--o-500),var(--o-400)); color:#fff; font-size:12px;
  box-shadow:0 4px 14px rgba(249,115,22,.25);
}

/* ====== Page Title (judul besar + icon + badge) ====== */
.page-title{
  display:flex; align-items:center; gap:10px;
  font-weight:800; color:#111827;
  letter-spacing:.2px;
  margin:0;
}
.title-icon{
  width:38px; height:38px; border-radius:12px;
  display:inline-flex; align-items:center; justify-content:center;
  background:var(--o-100); color:var(--o-600);
  box-shadow:inset 0 0 0 1px var(--o-200);
}
.title-icon i{ font-size:18px; }

/* ====== Mini Profile Card ====== */
.profile-card{
  border-radius:16px; background:linear-gradient(180deg,#f8fafc,#f1f5f9);
  box-shadow:0 10px 20px rgba(2,6,23,.06), inset 0 0 0 1px #e5e7eb;
  padding:16px; display:flex; gap:14px; align-items:center; margin-bottom:12px;
}
.avatar-lg{
  width:72px; height:72px; border-radius:50%; object-fit:cover;
  box-shadow:0 6px 16px rgba(0,0,0,.12);
}
.status-dot{
  display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px;
}
.sd-aktif{ background:#10b981; }
.sd-tamat{ background:#3b82f6; }
.sd-pindah{ background:#06b6d4; }
.sd-dikeluarkan{ background:#ef4444; }
.sd-default{ background:#94a3b8; }

/* ====== Form Sections ====== */
.form-section{ margin-bottom:16px; }
.form-section h4{
  font-size:13px; text-transform:uppercase; letter-spacing:.08em; color:#64748b;
  margin:0 0 8px; font-weight:700;
}

/* ====== Input Icon & Password Tools ====== */
.input-icon{ position:relative; }
.input-icon .toggle-password{
  position:absolute; right:10px; top:50%; transform:translateY(-50%);
  border:none; background:transparent; color:#475569; padding:4px 6px; cursor:pointer;
}

.strength-wrap{ margin-top:8px; }
.strength-bar{ height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
.strength-fill{ height:100%; width:0%; transition:width .25s ease; }
.strength-weak  { background:#fca5a5; }
.strength-fair  { background:#fdba74; }
.strength-good  { background:#a7f3d0; }
.strength-strong{ background:#86efac; }
.strength-label{ font-size:12px; color:#64748b; margin-top:4px; }

/* ====== Upload / Drag & Drop ====== */
#avatarDrop{
  padding:12px; border:2px dashed #f1c891; border-radius:12px; background:#fff;
  text-align:center;
}
#avatarDrop.dragover{ background:#fff7ed; border-color:var(--o-400); }
.preview-note{ font-size:12px; color:#64748b; }

/* ====== Status Badges as Radios ====== */
.badge-pills{
  display:flex; flex-wrap:wrap; gap:8px;
}
.badge-pill{
  position:relative; display:inline-flex; align-items:center; gap:6px;
  padding:6px 10px; border-radius:999px; cursor:pointer; user-select:none;
  font-size:12px; font-weight:700; border:1px solid transparent; transition:.15s ease;
}
.badge-pill input{ position:absolute; inset:0; opacity:0; cursor:pointer; }
.badge-aktif{ background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
.badge-tamat{ background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
.badge-pindah{ background:#ecfeff; color:#0e7490; border-color:#a5f3fc; }
.badge-dikeluarkan{ background:#fef2f2; color:#b91c1c; border-color:#fecaca; }

/* hover umum */
.badge-pill:hover{
  box-shadow:0 0 0 2px var(--o-200) inset;
  border-color: var(--o-300);
}

/* ACTIVE = oranye jelas + efek hover */
.badge-pill.active{
  background: linear-gradient(135deg,var(--o-500),var(--o-400));
  color:#fff;
  border-color: var(--o-500);
  box-shadow:0 4px 10px rgba(249,115,22,.25);
}
.badge-pill.active .status-dot{ background:#fff; }
.badge-pill.active:hover{
  background: linear-gradient(135deg,var(--o-600),var(--o-500));
  border-color: var(--o-600);
}

/* ====== Sticky Footer Actions ====== */
.sticky-actions{
  position:sticky; bottom:0; background:#fff; padding:10px; border-top:1px solid #e5e7eb;
  display:flex; gap:8px; justify-content:flex-end; z-index:5;
}

/* ====== Unsaved Shimmer + Submit Spinner ====== */
.btn-shimmer{ position:relative; overflow:hidden; }
.btn-shimmer.unsaved::after{
  content:''; position:absolute; top:0; left:-150%;
  height:100%; width:150%;
  background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.35) 45%, transparent 60%);
  animation: shimmerMove 2s linear infinite;
}
@keyframes shimmerMove { 0%{ left:-150%; } 100%{ left:150%; } }

.btn .spinner{
  display:none; margin-right:6px; width:16px; height:16px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%;
  animation: spin .8s linear infinite;
}
@keyframes spin { to{ transform: rotate(360deg);} }
.btn.loading .spinner{ display:inline-block; }

/* ====== Mobile polish ====== */
@media (max-width:576px){
  .header-row{ padding:10px 12px; }
  .btn-back{ padding:4px 8px; font-size:12px; }
  .profile-card{ padding:12px; }
  .sticky-actions{ padding:8px; }
}

/* ====== Full-width layout: jangan menyempit di layar besar ====== */
@media (min-width:1200px){
  .content .row .col-lg-12{ float:none; width:100%; }
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <!-- Judul dipoles: icon + badge, warna & teks tetap -->
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-graduation-cap"></i></span>
      <span>Siswa</span>
      <span class="page-accent">Edit Siswa</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- Full width agar tidak menyempit -->
      <section class="col-lg-12">
        <div class="box box-info">
          <div class="box-header with-border">
            <!-- Satu baris: judul rata kiri, tombol kembali di sudut kanan -->
            <div class="header-row">
              <h3 class="box-title">
                <i class="fa fa-graduation-cap"></i> Edit Siswa
              </h3>
              <a href="siswa.php" class="btn btn-back btn-sm header-actions" title="Kembali">
                <i class="fa fa-arrow-left"></i> &nbsp;Kembali
              </a>
            </div>
          </div>

          <div class="box-body">
            <?php if(!$d): ?>
              <div class="alert alert-danger"><b>Data tidak ditemukan.</b> <a href="siswa.php" class="alert-link">Kembali</a></div>
            <?php else: ?>

              <!-- Kartu profil -->
              <div class="profile-card">
                <img src="<?php echo e($foto_now); ?>" class="avatar-lg" id="avatar-current" alt="Foto siswa">
                <div>
                  <div style="font-weight:800;font-size:18px;"><?php echo e($d['siswa_nama']); ?></div>
                  <div style="color:#475569;">
                    <span class="status-dot <?php echo 'sd-'.($status?:'default'); ?>" id="status-dot"></span>
                    <span class="label <?php echo status_class($status); ?> rounded" id="status-badge">
                      <?php echo e($d['siswa_status'] ?: 'tidak diketahui'); ?>
                    </span>
                  </div>
                  <div style="font-size:12px;color:#64748b;margin-top:4px;">
                    NIS: <code id="nis-badge"><?php echo e($d['siswa_nis']); ?></code>
                    <button type="button" class="btn btn-default btn-xs" id="copyNis" title="Salin NIS"><i class="fa fa-copy"></i></button>
                  </div>
                </div>
              </div>

              <!-- FORM EDIT -->
              <form action="siswa_update.php" method="post" enctype="multipart/form-data" id="form-edit" autocomplete="off">
                <input type="hidden" name="id" value="<?php echo (int)$d['siswa_id']; ?>">
                <input type="hidden" name="<?php echo e($CSRF_NAME); ?>" value="<?php echo e($CSRF_TOKEN); ?>">

                <div class="row">
                  <div class="col-sm-7">
                    <div class="form-section">
                      <h4>Identitas</h4>

                      <div class="form-group">
                        <label><i class="fa fa-user"></i> &nbsp;Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control input-lg track-change" name="nama" required placeholder="Masukkan nama lengkap"
                               value="<?php echo e($d['siswa_nama']); ?>">
                      </div>

                      <div class="form-group">
                        <label><i class="fa fa-id-card-o"></i> &nbsp;NIS <span class="text-danger">*</span></label>
                        <div class="input-group">
                          <input type="text" class="form-control track-change" name="nis" required placeholder="Masukkan NIS"
                                 inputmode="numeric" pattern="[0-9]{1,}" value="<?php echo e($d['siswa_nis']); ?>" id="nis-input">
                          <span class="input-group-btn">
                            <button class="btn btn-default" type="button" id="copyNisField" title="Salin"><i class="fa fa-copy"></i></button>
                          </span>
                        </div>
                        <small class="text-muted">Hanya angka. Gunakan format asli (leading zero tetap disimpan).</small>
                      </div>

                      <div class="form-group">
                        <label><i class="fa fa-book"></i> &nbsp;Jurusan / Tingkat <span class="text-danger">*</span></label>
                        <select class="form-control track-change" name="jurusan" id="select-jurusan" required>
                          <option value=""> - Pilih Jurusan - </option>
                          <?php
                            $jurusan = mysqli_query($koneksi,"SELECT * FROM jurusan ORDER BY jurusan_nama ASC");
                            while($j = mysqli_fetch_assoc($jurusan)):
                          ?>
                            <option value="<?php echo (int)$j['jurusan_id']; ?>"
                              <?php echo ($d['siswa_jurusan']===$j['jurusan_id']) ? "selected" : ""; ?>>
                              <?php echo e($j['jurusan_nama']); ?>
                            </option>
                          <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Gunakan kolom pencarian untuk menemukan jurusan dengan cepat.</small>
                      </div>
                    </div>

                    <div class="form-section">
                      <h4><i class="fa fa-phone" style="color:var(--o-500);margin-right:4px"></i>Kontak Orang Tua / Wali</h4>
                      <div class="form-group">
                        <label><i class="fa fa-whatsapp"></i> &nbsp;No. HP / WA Orang Tua <span class="text-muted" style="font-weight:400">(opsional)</span></label>
                        <div class="input-group">
                          <span class="input-group-addon"><i class="fa fa-whatsapp" style="color:#25d366"></i></span>
                          <input type="text" class="form-control track-change" name="hp_ortu" id="hp_ortu"
                                 placeholder="Contoh: 081234567890 atau 6281234567890"
                                 inputmode="tel" maxlength="20"
                                 value="<?php echo e($d['hp_ortu'] ?? ''); ?>">
                        </div>
                        <small class="text-muted">
                          Format 08xx (min 10 digit) atau 628xx. Digunakan untuk notifikasi WhatsApp ke orang tua.
                        </small>
                        <div id="hpOrtuErr" style="color:#dc2626;font-size:12px;margin-top:4px;display:none"></div>
                      </div>
                    </div>

                    <div class="form-section">
                      <h4>Keamanan Akun</h4>

                      <div class="form-group">
                        <label><i class="fa fa-lock"></i> &nbsp;Kata Sandi (opsional)</label>
                        <div class="input-group">
                          <input type="password" class="form-control track-change" name="password" id="pwd" placeholder="Biarkan kosong bila tidak diubah" minlength="8" autocomplete="new-password">
                          <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="togglePw" title="Tampilkan / Sembunyikan"><i class="fa fa-eye"></i></button>
                          </span>
                        </div>

                        <div class="strength-wrap">
                          <div class="strength-bar"><div id="pwd-strength" class="strength-fill"></div></div>
                          <div class="strength-label" id="pwd-strength-label">Kekuatan sandi: -</div>
                        </div>

                        <div style="margin-top:8px" class="btn-group">
                          <button type="button" class="btn btn-info btn-sm" id="genStrong"><i class="fa fa-magic"></i> Generate kuat</button>
                          <button type="button" class="btn btn-warning btn-sm" id="resetDefault"><i class="fa fa-undo"></i> Reset default (12345678)</button>
                          <button type="button" class="btn btn-default btn-sm" id="copyPw"><i class="fa fa-copy"></i> Salin</button>
                        </div>
                        <small class="text-muted" style="display:block;margin-top:6px">
                          Kosongkan jika tidak ingin mengubah sandi. Rekomendasi: 10+ karakter dengan huruf besar/kecil, angka, dan simbol.
                        </small>
                      </div>
                    </div>

                    <div class="form-section">
                      <h4>Status</h4>
                      <?php
                        $ops = ['aktif','tamat','pindah','dikeluarkan'];
                      ?>
                      <div class="badge-pills" id="status-pills">
                        <?php foreach($ops as $op): 
                          $active = ($status===$op);
                          $classes = 'badge-pill badge-'.$op.($active?' active':'');
                        ?>
                          <label class="<?php echo $classes; ?>" title="<?php echo ucfirst($op); ?>">
                            <input type="radio" name="status" value="<?php echo e($op); ?>" <?php echo $active?'checked':''; ?>>
                            <span class="status-dot <?php echo 'sd-'.$op; ?>"></span>
                            <?php echo ucfirst($op); ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                      <small class="text-muted">Klik salah satu badge untuk memilih status siswa.</small>
                    </div>
                  </div>

                  <div class="col-sm-5">
                    <div class="form-section">
                      <h4>Ganti Foto</h4>
                      <div class="form-group">
                        <div id="avatarDrop">
                          <img id="preview" src="<?php echo e($foto_now); ?>" class="img-circle" style="width:120px;height:120px;object-fit:cover;display:block;margin:0 auto 8px" alt="Preview">
                          <p class="help-block" style="margin:6px 0 10px">Tarik & lepas foto ke sini atau klik tombol di bawah.</p>
                          <input type="file" name="foto" id="foto" accept="image/*" style="display:none;">
                          <div class="btn-group">
                            <button type="button" class="btn btn-info btn-sm" id="pickFoto"><i class="fa fa-image"></i> Pilih Foto</button>
                            <button type="button" class="btn btn-default btn-sm" id="revertFoto"><i class="fa fa-undo"></i> Kembalikan</button>
                          </div>
                          <div class="preview-note" style="margin-top:6px;">Format JPG/PNG/GIF, ukuran maks 2 MB.</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="sticky-actions">
                  <a href="siswa.php" class="btn btn-default"><i class="fa fa-times"></i> Batal</a>
                  <button type="submit" class="btn btn-primary btn-shimmer" id="btn-submit">
                    <span class="spinner"></span>
                    <i class="fa fa-save"></i> Simpan Perubahan
                  </button>
                </div>
              </form>

            <?php endif; // data ada ?>
          </div>
        </div>
      </section>
    </div>
  </section>
</div>

<?php include 'footer.php'; ?>

<script>
// ====== Select2 bila tersedia ======
$(function(){
  if ($.fn.select2) {
    $('#select-jurusan').select2({ width:'100%', placeholder:'Pilih jurusan' });
  }
});

// ====== Copy util ======
function copyText(txt){
  if(!txt){ alert('Tidak ada teks untuk disalin.'); return; }
  navigator.clipboard.writeText(txt).then(()=> alert('Tersalin.')).catch(()=> alert('Gagal menyalin.'));
}
document.getElementById('copyNis')?.addEventListener('click', function(){
  const val = document.getElementById('nis-badge')?.innerText || '';
  copyText(val.trim());
});
document.getElementById('copyNisField')?.addEventListener('click', function(){
  const val = document.getElementById('nis-input')?.value || '';
  copyText(val.trim());
});

// ====== Password: toggle + strength + tools ======
const pwInput = document.getElementById('pwd');
const fillBar = document.getElementById('pwd-strength');
const label   = document.getElementById('pwd-strength-label');

function scorePassword(s){
  let score = 0;
  if(!s) return 0;
  const uniq = new Set(s).size;
  score += Math.min(40, uniq*2);
  if(s.length >= 8)  score += 20;
  if(s.length >= 12) score += 15;
  if(/[a-z]/.test(s)) score += 8;
  if(/[A-Z]/.test(s)) score += 8;
  if(/[0-9]/.test(s)) score += 8;
  if(/[^A-Za-z0-9]/.test(s)) score += 11;
  return Math.min(100, score);
}
function renderStrength(s){
  const v = scorePassword(s);
  fillBar.className = 'strength-fill';
  let text='-';
  if(v <= 30){ fillBar.classList.add('strength-weak'); text='Lemah'; }
  else if(v <= 55){ fillBar.classList.add('strength-fair'); text='Cukup'; }
  else if(v <= 80){ fillBar.classList.add('strength-good'); text='Baik'; }
  else { fillBar.classList.add('strength-strong'); text='Kuat'; }
  fillBar.style.width = v + '%';
  label.textContent = 'Kekuatan sandi: ' + text;
}
pwInput?.addEventListener('input', function(){ renderStrength(this.value); markUnsaved(); });
renderStrength('');

document.getElementById('togglePw')?.addEventListener('click', function(){
  if(!pwInput) return;
  pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
  this.querySelector('i').className = (pwInput.type==='password') ? 'fa fa-eye' : 'fa fa-eye-slash';
});

document.getElementById('genStrong')?.addEventListener('click', () => {
  if(!pwInput) return;
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+[]{}';
  let out=''; for(let i=0;i<14;i++){ out += chars[Math.floor(Math.random()*chars.length)]; }
  pwInput.value = out; renderStrength(out); markUnsaved(); alert('Sandi kuat dibuat. Jangan lupa simpan.');
});
document.getElementById('resetDefault')?.addEventListener('click', () => {
  if(!pwInput) return;
  pwInput.value = '12345678'; renderStrength(pwInput.value); markUnsaved();
  alert('Sandi akan disetel ke default: 12345678 saat disimpan.');
});
document.getElementById('copyPw')?.addEventListener('click', () => copyText(pwInput?.value || ''));

// ====== Foto: drag & drop + preview + revert ======
(function(){
  const drop = document.getElementById('avatarDrop');
  const input = document.getElementById('foto');
  const preview = document.getElementById('preview');
  const revertBtn = document.getElementById('revertFoto');
  const pickBtn   = document.getElementById('pickFoto');
  const currentSrc = preview?.src || '';

  if(!drop || !input || !preview) return;

  // click picker
  pickBtn?.addEventListener('click', ()=> input.click());

  // revert preview to current
  revertBtn?.addEventListener('click', ()=>{
    preview.src = currentSrc;
    input.value = '';
  });

  // drag & drop
  ['dragenter','dragover'].forEach(evt => drop.addEventListener(evt, e => {
    e.preventDefault(); e.stopPropagation(); drop.classList.add('dragover');
  }));
  ['dragleave','drop'].forEach(evt => drop.addEventListener(evt, e => {
    e.preventDefault(); e.stopPropagation(); drop.classList.remove('dragover');
  }));
  drop.addEventListener('drop', e => { if(e.dataTransfer.files?.[0]) { input.files = e.dataTransfer.files; handleFile(input.files[0]); } });
  input.addEventListener('change', e => handleFile(e.target.files?.[0]));

  function handleFile(f){
    if(!f){ return; }
    const okType = /image\/(jpeg|png|gif)/i.test(f.type);
    if(!okType){ alert('Format tidak didukung. Gunakan JPG/PNG/GIF.'); input.value=''; return; }
    if(f.size > 2*1024*1024){ alert('Ukuran file melebihi 2MB.'); input.value=''; return; }
    const reader = new FileReader();
    reader.onload = ev => {
      preview.src = ev.target.result;
      document.getElementById('avatar-current')?.setAttribute('src', ev.target.result);
      markUnsaved();
    };
    reader.readAsDataURL(f);
  }
})();

// ====== Status pills -> sinkron ke kartu profil ======
(function(){
  const pills = document.getElementById('status-pills');
  const badge = document.getElementById('status-badge');
  const dot   = document.getElementById('status-dot');

  const labelMap = {
    'aktif':       { badge:'label-success', dot:'sd-aktif', text:'Aktif' },
    'tamat':       { badge:'label-primary', dot:'sd-tamat', text:'Tamat' },
    'pindah':      { badge:'label-info',    dot:'sd-pindah', text:'Pindah' },
    'dikeluarkan': { badge:'label-danger',  dot:'sd-dikeluarkan', text:'Dikeluarkan' }
  };

  function apply(val){
    if(!badge || !dot) return;
    badge.textContent = labelMap[val]?.text || '-';
    badge.className = 'label rounded ' + (labelMap[val]?.badge || 'label-default');
    dot.className = 'status-dot ' + (labelMap[val]?.dot || 'sd-default');
  }

  pills?.addEventListener('change', function(e){
    if(e.target && e.target.name === 'status'){
      // aktifkan visual aktif (badge aktif oranye)
      [...pills.querySelectorAll('.badge-pill')].forEach(el => el.classList.remove('active'));
      e.target.closest('.badge-pill')?.classList.add('active');
      apply(e.target.value);
      markUnsaved();
    }
  });
})();

// ====== Shimmer saat ada perubahan ======
const btnSimpan = document.getElementById('btn-submit');
function markUnsaved(){ btnSimpan?.classList.add('unsaved'); }
document.querySelectorAll('.track-change, #select-jurusan').forEach(el=>{
  el.addEventListener('change', markUnsaved);
  el.addEventListener('input', markUnsaved);
});

// ====== Anti double-submit + spinner ======
(function(){
  const form = document.getElementById('form-edit');
  const btn  = document.getElementById('btn-submit');
  if(!form || !btn) return;
  form.addEventListener('submit', function(){
    btn.classList.add('loading');
    btn.disabled = true;
    btn.querySelector('.spinner').style.display = 'inline-block';
  });
})();

/* ====== (BARU) Shortcut: Ctrl+S / Cmd+S untuk Simpan Cepat ======
   - Menekan Ctrl+S (Windows/Linux) atau Cmd+S (macOS) akan memicu submit form.
   - Mencegah default "Save Page" di browser.
   - Menghormati state tombol: tidak submit bila tombol sedang disabled/loading.
*/
(function(){
  function isSaveCombo(e){
    const key = (e.key || '').toLowerCase();
    return (e.ctrlKey || e.metaKey) && (key === 's' || e.keyCode === 83);
  }
  document.addEventListener('keydown', function(e){
    if (!isSaveCombo(e)) return;
    e.preventDefault();
    const form = document.getElementById('form-edit');
    const btn  = document.getElementById('btn-submit');
    if(!form || !btn) return;
    if(btn.disabled) return;
    // Gunakan requestSubmit agar event submit & HTML5 validation tetap berjalan
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit(btn);
    } else {
      form.submit();
    }
  }, true);
})();

// ====== Validasi HP Ortu: jika diisi wajib min 10 digit ======
(function(){
  var hpField = document.getElementById('hp_ortu');
  var hpErr   = document.getElementById('hpOrtuErr');
  if (!hpField) return;
  function validateHp(){
    var digits = (hpField.value || '').replace(/\D/g, '');
    var msg = (digits !== '' && digits.length < 10) ? 'Nomor HP minimal 10 digit.' : '';
    hpField.setCustomValidity(msg);
    if (hpErr) { hpErr.textContent = msg; hpErr.style.display = msg ? 'block' : 'none'; }
  }
  hpField.addEventListener('input', validateHp);
  hpField.addEventListener('blur',  validateHp);
})();
</script>
