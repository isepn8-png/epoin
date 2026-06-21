<?php 
include 'header.php';
include '../koneksi.php';
session_start();

// ==== util: cek apakah operator sekarang superadmin (opsional, sesuaikan session Anda)
function is_superadmin() {
  return isset($_SESSION['roles']) && in_array('superadmin', $_SESSION['roles']);
}

// ==== Ambil daftar role dari tabel roles (RBAC)
$roles = [];
$rolesSql = "SELECT role_id, role_key, role_name FROM roles";
if(!is_superadmin()){ $rolesSql .= " WHERE role_key <> 'superadmin'"; }
$rolesSql .= " ORDER BY role_name ASC";
if (isset($koneksi) && $koneksi instanceof mysqli) {
  if($q = $koneksi->query($rolesSql)){
    while($r = $q->fetch_assoc()){
      $roles[] = [
        'id'  => (int)$r['role_id'],
        'key' => $r['role_key'],
        'name'=> $r['role_name'],
      ];
    }
  }
}

// Fallback minimal jika tabel roles tidak tersedia
if (empty($roles)) {
  $roles = [
    ['id'=>0,'key'=>'administrator','name'=>'Administrator'],
    ['id'=>0,'key'=>'guru','name'=>'Guru'],
    ['id'=>0,'key'=>'tas','name'=>'TAS'],
    ['id'=>0,'key'=>'sekretaris','name'=>'Sekretaris'],
  ];
}

/* ==== DEFAULT AVATAR (dipakai jika belum/ tidak memilih foto) ==== */
$DEFAULT_AVATAR = '../gambar/sistem/user.png';
?>

<style>
/* ===== THEME POLISH (konsisten biru) ===== */
:root{
  --blue-500:#0B57D0;
  --blue-400:#3BA3FF;
  --blue-100:#E6EFFF;
  --blue-50:#F6F9FF;
  --border:#D7E6FF;
}

.content-wrapper{ background:#f5f7fb; }

/* ---- Page Title: ikon + badge + font tebal ---- */
.page-title{
  display:flex; align-items:center; gap:12px; margin:0 0 6px;
  color:#0b1b32; font-weight:800; letter-spacing:.2px;
}
.title-icon{
  width:44px;height:44px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;
  background:var(--blue-100); box-shadow:inset 0 0 0 1px var(--border);
}
.title-icon i{ color:var(--blue-500); font-size:18px; }
.title-badge{
  display:inline-flex; align-items:center; gap:6px;
  background:var(--blue-50); color:#1f2d3d; border:1px solid var(--border);
  border-radius:999px; padding:4px 10px; font-weight:700; font-size:12px;
}

/* ---- Box polish ---- */
.box.box-info { border-top: 3px solid #00c0ef; border-radius:14px; overflow:hidden; }
.box-header.with-border {
  background: linear-gradient(90deg,#00c0ef,#3c8dbc); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
}
.box .box-title{ margin:0; font-weight:800; display:flex; align-items:center; gap:8px; }
.box .box-title i{ font-size:16px; }

/* ---- Back button: soft light ---- */
.btn-soft-light{
  color:#3c8dbc !important; background:#fff; border:1px solid rgba(255,255,255,.2);
  border-radius:10px; padding:6px 10px; box-shadow:0 6px 14px rgba(0,0,0,.08);
}
.btn-soft-light:hover{ color:#fff !important; background:#34a6c9; border-color:#34a6c9; }

/* ---- Modern primary (tanpa mengubah .btn-primary asli) ---- */
.btn-grad-primary{
  background: linear-gradient(90deg, var(--blue-500), var(--blue-400)) !important;
  border:0 !important; color:#fff !important; border-radius:12px;
  box-shadow: 0 10px 22px rgba(11,87,208,.28);
}
.btn-grad-primary:hover{ filter:brightness(1.06); }

/* ---- Avatar drop area (bingkai dipertahankan) ---- */
#avatarDrop {
  padding:12px; border:2px dashed #bcd; border-radius:12px; background:#fff;
  box-shadow:0 6px 18px rgba(2,6,23,.06);
  text-align:center;
}
#avatarPreview{
  width:180px; height:180px; object-fit:cover; border-radius:50%;
  display:block; margin:10px auto 12px;
}
#avatarDrop .btn-group{ margin-top:2px; }
#avatarDrop .help-block{ margin:6px 0 0; }

/* ---- Password strength meter ---- */
.strength-wrap{ margin-top:8px; }
.strength-bar{ height:8px; background:#eee; border-radius:6px; overflow:hidden; }
.strength-fill{ height:100%; width:0%; transition:width .3s ease; }
.strength-label{ font-size:12px; margin-top:4px; color:#666; }
.strength-weak  { background:#ff9b8a; }
.strength-fair  { background:#ffd280; }
.strength-good  { background:#b6e3a8; }
.strength-strong{ background:#7fd1ae; }

/* ---- Shimmer indikasi ada perubahan ---- */
.btn-shimmer { position: relative; overflow: hidden; }
.btn-shimmer.unsaved::after{
  content:''; position:absolute; top:0; left:-150%;
  height:100%; width:150%;
  background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.35) 45%, transparent 60%);
  animation: shimmerMove 2s linear infinite;
}
@keyframes shimmerMove { 0%{ left:-150%; } 100%{ left:150%; } }

/* ---- Spinner saat submit ---- */
.btn .spinner {
  display:none; margin-right:6px; width:16px; height:16px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%;
  animation: spin .8s linear infinite;
}
@keyframes spin { to{ transform: rotate(360deg);} }
.btn.loading .spinner { display:inline-block; }

/* ---- Responsive tweak ---- */
@media (max-width:991px){
  .box .box-title{ font-size:16px; }
}

/* Ratakan teks header kiri & tombol kanan */
.box-header.with-border{ justify-content:flex-start !important; text-align:left; }
.box-header.with-border .box-title{ margin-right:auto; }
.box-header.with-border .pull-right{ float:none !important; margin-left:auto; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <!-- Judul dipoles: ikon + badge; teks tetap sama -->
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-user-plus"></i></span>
      <span>Manajemen Pengguna</span>
      <span class="title-badge"><i class="fa fa-id-card"></i> Tambah Pengguna Baru</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Manajemen Pengguna</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- Panel dibuat full width pada layar besar -->
      <section class="col-lg-12 col-md-12 col-sm-12">       
        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-user-plus"></i> Tambah Pengguna</h3>
            <a href="manajemen_pengguna.php" class="btn btn-soft-light btn-sm pull-right">
              <i class="fa fa-reply"></i> &nbsp; Kembali
            </a> 
          </div>

          <div class="box-body">
            <form action="user_act.php" method="post" enctype="multipart/form-data" id="formAddUser" autocomplete="off">
              <?php echo epoin_csrf_field(); ?>
              <!-- redirect tujuan daftar manajemen -->
              <input type="hidden" name="redirect_to" value="manajemen_pengguna.php">
              <!-- level legacy untuk kompatibilitas backend lama -->
              <input type="hidden" name="level" id="legacy_level" value="">
              <!-- flag reset default (kalau ingin dibaca backend) -->
              <input type="hidden" name="reset_default" id="reset_default" value="0">

              <div class="row">
                <div class="col-sm-8">
                  <div class="form-group">
                    <label>Nama <span class="text-danger">*</span></label>
                    <!-- Samakan ukuran dengan Username: keduanya input-lg -->
                    <input type="text" class="form-control input-lg track-change" name="nama" required placeholder="Masukkan Nama ..">
                  </div>

                  <div class="form-group">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control input-lg track-change" name="username" required placeholder="Masukkan Username .." autocomplete="username">
                  </div>

                  <!-- PASSWORD PANEL (modern & interaktif) -->
                  <div class="form-group">
                    <label>Kata Sandi <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <input type="password" class="form-control" name="password" id="pw" required minlength="5" placeholder="Minimal 5 karakter" autocomplete="new-password">
                      <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="togglePw" title="Tampilkan/Sembunyikan"><i class="fa fa-eye"></i></button>
                      </span>
                    </div>
                    <div class="strength-wrap">
                      <div class="strength-bar"><div id="strengthFill" class="strength-fill"></div></div>
                      <div class="strength-label" id="strengthLabel">Kekuatan sandi: -</div>
                    </div>
                    <div style="margin-top:8px" class="btn-group">
                      <button type="button" class="btn btn-info btn-sm" id="genStrong"><i class="fa fa-magic"></i> Generate kuat</button>
                      <button type="button" class="btn btn-warning btn-sm" id="resetDefault"><i class="fa fa-undo"></i> Reset default (12345678)</button>
                      <button type="button" class="btn btn-default btn-sm" id="copyPw"><i class="fa fa-copy"></i> Salin</button>
                    </div>
                    <small class="text-muted" style="display:block;margin-top:6px">
                      Rekomendasi: ≥10 karakter, campur huruf besar/kecil, angka, simbol. (Anda bisa pakai tombol “Generate kuat”.)
                    </small>
                  </div>

                  <div class="form-group">
                    <label>Peran (Role) <span class="text-danger">*</span></label>
                    <select class="form-control track-change" name="roles[]" id="roles" multiple required>
                      <?php foreach($roles as $r): ?>
                        <option 
                          value="<?= (int)$r['id'] ?>" 
                          data-role-key="<?= htmlspecialchars($r['key']) ?>">
                          <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['key']) ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Pilih minimal satu peran. Multi-role didukung.</small>
                  </div>
                </div>

                <div class="col-sm-4">
                  <label>Foto Profil</label>
                  <div id="avatarDrop" class="well text-center">
                    <!-- DEFAULT AVATAR: gunakan user.png -->
                    <img id="avatarPreview" src="<?= htmlspecialchars($DEFAULT_AVATAR) ?>" alt="Avatar Default" class="img-responsive img-circle"/>
                    <p class="help-block">Tarik & lepas foto ke sini atau klik tombol di bawah.</p>
                    <input type="file" name="foto" id="fotoInput" accept="image/*" style="display:none;">
                    <div class="btn-group">
                      <button type="button" class="btn btn-info btn-sm" id="pickFoto"><i class="fa fa-image"></i> Pilih Foto</button>
                      <button type="button" class="btn btn-default btn-sm" id="hapusFoto"><i class="fa fa-trash"></i> Hapus</button>
                    </div>
                    <p class="help-block">Format JPG/PNG, ukuran maks 2 MB.</p>
                  </div>
                </div>
              </div>

              <hr>
              <div class="text-right">
                <!-- Tambah kelas gradien modern (tanpa mengubah id/kelas lama) -->
                <button type="submit" class="btn btn-primary btn-lg btn-shimmer btn-grad-primary" id="btnSimpan">
                  <span class="spinner"></span>
                  <i class="fa fa-save"></i> Simpan
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
// ====== PASSWORD UI ======
const pw = document.getElementById('pw');
const fill = document.getElementById('strengthFill');
const label = document.getElementById('strengthLabel');

function scorePassword(s){
  let score = 0;
  if(!s) return 0;
  const uniq = new Set(s).size;
  score += Math.min(40, uniq*2);            // keunikan
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
  fill.className = 'strength-fill';
  let text='-';
  if(v <= 30){ fill.classList.add('strength-weak'); text='Lemah'; }
  else if(v <= 55){ fill.classList.add('strength-fair'); text='Cukup'; }
  else if(v <= 80){ fill.classList.add('strength-good'); text='Baik'; }
  else { fill.classList.add('strength-strong'); text='Kuat'; }
  fill.style.width = v + '%';
  label.textContent = 'Kekuatan sandi: ' + text;
}
pw.addEventListener('input', () => { renderStrength(pw.value); markUnsaved(); });

document.getElementById('togglePw').addEventListener('click', function(){
  pw.type = pw.type === 'password' ? 'text' : 'password';
  this.querySelector('i').className = pw.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
});

document.getElementById('genStrong').addEventListener('click', () => {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()-_=+[]{}';
  let out=''; for(let i=0; i<14; i++){ out += chars[Math.floor(Math.random()*chars.length)]; }
  pw.value = out; renderStrength(out); markUnsaved(); alert('Sandi kuat sudah dibuat. Jangan lupa simpan.');
});

document.getElementById('resetDefault').addEventListener('click', () => {
  pw.value = '12345678';
  renderStrength(pw.value);
  document.getElementById('reset_default').value = '1';
  markUnsaved();
  alert('Sandi akan di-set ke default: 12345678 saat disimpan.');
});

document.getElementById('copyPw').addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(pw.value || '');
    alert('Tersalin.');
  } catch(e){ alert('Gagal menyalin.'); }
});

// ====== FOTO interaktif ======
document.getElementById('pickFoto').onclick = () => document.getElementById('fotoInput').click();
const drop = document.getElementById('avatarDrop');
const input = document.getElementById('fotoInput');
const preview = document.getElementById('avatarPreview');
const DEFAULT_AVATAR = <?= json_encode($DEFAULT_AVATAR) ?>;

['dragenter','dragover'].forEach(evt => drop.addEventListener(evt, e => {e.preventDefault();drop.style.background='#f6fbff';}));
['dragleave','drop'].forEach(evt => drop.addEventListener(evt, e => {e.preventDefault();drop.style.background='';}));
drop.addEventListener('drop', e => { input.files = e.dataTransfer.files; handleFile(input.files[0]); });
input.addEventListener('change', e => handleFile(e.target.files[0]));

function handleFile(file){
  if(!file) return;
  if(!file.type.match(/^image\//)) { alert('File harus gambar'); input.value=''; preview.src = DEFAULT_AVATAR; return; }
  if(file.size > 2*1024*1024){ alert('Ukuran maksimal 2MB'); input.value=''; preview.src = DEFAULT_AVATAR; return; }
  const reader = new FileReader();
  reader.onload = e => { preview.src = e.target.result; markUnsaved(); };
  reader.readAsDataURL(file);
}

document.getElementById('hapusFoto').onclick = () => {
  preview.src = DEFAULT_AVATAR;           // kembali ke avatar default
  input.value = '';                       // kosongkan input file
  markUnsaved();
};

// ====== Multi-select roles: Select2 jika tersedia
$(function(){
  if($.fn.select2){
    $('#roles').select2({ placeholder: 'Pilih peran', width: '100%' });
  }
});

// ====== Penentuan 'level' legacy sebelum submit (kompatibel user_act.php lama)
function computeLegacyLevel(){
  const sel = Array.from(document.querySelectorAll('#roles option:checked'));
  if(sel.length === 0) return '';
  const keys = sel.map(o => (o.dataset.roleKey || '').toLowerCase());
  const prio = ['administrator','guru','tas','sekretaris'];
  for(const p of prio){ if(keys.includes(p)) return p; }
  return keys[0] || 'user';
}

// ====== Shimmer saat ada perubahan + spinner saat submit
const btnSimpan = document.getElementById('btnSimpan');
function markUnsaved(){ btnSimpan.classList.add('unsaved'); }
document.querySelectorAll('.track-change, #roles').forEach(el=>{
  el.addEventListener('change', markUnsaved);
  el.addEventListener('input', markUnsaved);
});

// spinner saat submit + validasi roles + isi legacy level
document.getElementById('formAddUser').addEventListener('submit', function(e){
  const hasRole = Array.from(document.querySelectorAll('#roles option:checked')).length > 0;
  if(!hasRole){
    e.preventDefault();
    alert('Pilih minimal satu peran.');
    return;
  }
  document.getElementById('legacy_level').value = computeLegacyLevel();
  btnSimpan.classList.add('loading');
  btnSimpan.disabled = true;
});

// render awal strength
renderStrength('');
</script>

<?php include 'footer.php'; ?>
