<?php 
include 'header.php';
include '../koneksi.php';
session_start();

// util: cek apakah operator sekarang superadmin (opsional, sesuaikan session-mu)
function is_superadmin() {
  return isset($_SESSION['roles']) && in_array('superadmin', $_SESSION['roles']);
}

$id = (int)($_GET['id'] ?? 0);

// ambil user
$stmt = $koneksi->prepare("SELECT user_id, user_nama, user_username, user_foto FROM user WHERE user_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if(!$user){ die("User tidak ditemukan"); }

// role yang tersedia
$rolesSql = "SELECT role_id, role_key, role_name FROM roles";
if(!is_superadmin()){ $rolesSql .= " WHERE role_key <> 'superadmin'"; }
$rolesSql .= " ORDER BY role_name ASC";
$roles = $koneksi->query($rolesSql);

// role milik user (untuk preselect)
$ownRoleIds = [];
$ur = $koneksi->prepare("SELECT role_id FROM user_roles WHERE user_id=?");
$ur->bind_param("i", $id);
$ur->execute();
$resUr = $ur->get_result();
while($r = $resUr->fetch_assoc()){ $ownRoleIds[] = (int)$r['role_id']; }

/* Default avatar (rapih seperti halaman lain) */
$DEFAULT_AVATAR = '../gambar/sistem/user.png';
$previewSrc = $user['user_foto'] ? ('../gambar/user/'.htmlspecialchars($user['user_foto'])) : $DEFAULT_AVATAR;
?>
<style>
/* ---- UI polish (tetap) ---- */
.box.box-info { border-top: 3px solid #00c0ef; }
.box-header.with-border { background: linear-gradient(90deg,#00c0ef,#3c8dbc); color:#fff; }
.badge-role { display:inline-block; padding:2px 8px; border-radius:12px; background:#eef5ff; color:#3c8dbc; font-size:12px; }

/* ====== HEADER PRESET (sesuai halaman lain) ====== */
.content-header h1.page-title{
  margin:0;
  display:flex; align-items:center; gap:12px;
  color:#0b1b32;
  font-family:"Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-weight:800;
  font-size:clamp(18px, 2.2vw, 26px);
  line-height:1.2;
  letter-spacing:.2px;
  opacity:0; transform:translateY(6px);
  animation:textFade .6s ease-out .05s forwards;
}
.title-icon{
  display:inline-flex; align-items:center; justify-content:center;
  width:40px; height:40px; border-radius:12px;
  background:linear-gradient(135deg,#e0ecff,#f0f7ff);
  color:#1e3a8a; box-shadow:0 6px 18px rgba(59,130,246,.18);
}
.title-badge{
  display:inline-flex; align-items:center; gap:6px;
  background:#F6F9FF; color:#1f2d3d; border:1px solid #D7E6FF;
  border-radius:9999px; padding:3px 10px; line-height:1;
  font-family:"Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  font-weight:700; font-size:10px; box-shadow:0 4px 12px rgba(29,78,216,.25);
  transform:translateY(-1px);
}
@keyframes textFade{ from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }

/* ====== Full-width konten panel ====== */
@media (min-width:1200px){
  /* pastikan tidak ada offset/centering custom */
  .content .row .col-lg-12{ float:none; width:100%; margin:0; }
}

/* password strength meter (tetap) */
.strength-wrap{ margin-top:8px; }
.strength-bar{ height:8px; background:#eee; border-radius:6px; overflow:hidden; }
.strength-fill{ height:100%; width:0%; transition:width .3s ease; }
.strength-label{ font-size:12px; margin-top:4px; color:#666; }
.strength-weak  { background:#ff9b8a; }
.strength-fair  { background:#ffd280; }
.strength-good  { background:#b6e3a8; }
.strength-strong{ background:#7fd1ae; }

/* shimmer indikasi ada perubahan (tetap) */
.btn-shimmer { position: relative; overflow: hidden; }
.btn-shimmer.unsaved::after{
  content:''; position:absolute; top:0; left:-150%;
  height:100%; width:150%;
  background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.3) 45%, transparent 60%);
  animation: shimmerMove 2s linear infinite;
}
@keyframes shimmerMove { 0%{ left:-150%; } 100%{ left:150%; } }

/* spinner saat submit (tetap) */
.btn .spinner {
  display:none; margin-right:6px; width:16px; height:16px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%;
  animation: spin .8s linear infinite;
}
@keyframes spin { to{ transform: rotate(360deg);} }
.btn.loading .spinner { display:inline-block; }

/* ====== Avatar drop (tetap) ====== */
#avatarDrop{
  padding:12px; border:2px dashed #f1c891; border-radius:12px; background:#fff; text-align:center;
}
#avatarDrop.dragover{ background:#fff7ed; border-color:#fb923c; }
#avatarPreview{
  width:160px; height:160px; object-fit:cover; border-radius:50%;
  display:block; margin:0 auto 8px; box-shadow:0 6px 16px rgba(0,0,0,.12);
}

/* ====== Select2 • peran(role) — teks hitam & terbaca ====== */
.select2-container--default .select2-selection--multiple{
  background:#fff; border:1px solid #cbd5e1; min-height:34px;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice{
  background:#eef2ff; border:1px solid #c7d2fe; color:#111827; font-weight:700;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove{
  color:#334155; margin-right:6px;
}
.select2-container--default .select2-results__option{ color:#0f172a; }
.select2-container--default .select2-search--inline .select2-search__field{ color:#0f172a; }

/* ====== Tombol Simpan modern (khusus tombol ini saja) ====== */
#btnSimpan{
  background: linear-gradient(90deg,#0ea5e9,#2563eb);
  border:0; color:#fff; font-weight:800;
  border-radius:12px; padding:10px 18px;
  box-shadow: 0 12px 28px rgba(14,165,233,.30);
  transition: transform .08s ease, box-shadow .12s ease, filter .12s ease;
}
#btnSimpan:hover{ filter:brightness(1.05); box-shadow:0 16px 34px rgba(14,165,233,.35); }
#btnSimpan:active{ transform: translateY(1px); box-shadow:0 10px 22px rgba(14,165,233,.28); }
#btnSimpan[disabled]{ filter:grayscale(.2) opacity(.85); }

/* ====== Responsif ====== */
@media (max-width:767px){
  .title-icon{ width:36px;height:36px; }
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-user"></i></span>
      Manajemen Pengguna
      <small class="title-badge"><i class="fa fa-pencil-square-o"></i> Edit Pengguna</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Manajemen Pengguna</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- FULL WIDTH: ubah ke col-lg-12 (tanpa offset) -->
      <section class="col-lg-12 col-md-12">       
        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-user"></i> &nbsp; Edit Pengguna</h3>
            <a href="manajemen_pengguna.php" class="btn btn-light btn-sm pull-right" style="color:#3c8dbc;background:#fff;border:0">
              <i class="fa fa-reply"></i> &nbsp; Kembali
            </a> 
          </div>

          <div class="box-body">
            <form action="user_update.php" method="post" enctype="multipart/form-data" id="formEditUser" autocomplete="off">
              <input type="hidden" name="id" value="<?= htmlspecialchars($user['user_id']) ?>">
              <input type="hidden" name="old_foto" value="<?= htmlspecialchars($user['user_foto'] ?? '') ?>">
              <input type="hidden" name="redirect_to" value="manajemen_pengguna.php"><!-- pastikan redirect ke menu baru -->
              <input type="hidden" name="reset_default" id="reset_default" value="0"><!-- flag reset -->
              <input type="hidden" name="remove_foto" id="remove_foto" value="0">

              <div class="row">
                <!-- kiri -->
                <div class="col-md-7 col-sm-12">
                  <div class="form-group">
                    <label>Nama <span class="text-danger">*</span></label>
                    <input type="text" class="form-control input-lg track-change" name="nama" value="<?= htmlspecialchars($user['user_nama']) ?>" required>
                  </div>

                  <div class="form-group">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control track-change" name="username" value="<?= htmlspecialchars($user['user_username']) ?>" required autocomplete="username">
                  </div>

                  <!-- PASSWORD PANEL -->
                  <div class="form-group">
                    <label>Kata Sandi</label>
                    <div class="input-group">
                      <input type="password" class="form-control" name="password" id="pw" minlength="5" placeholder="Kosongkan jika tidak diganti" autocomplete="new-password">
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
                      Tips: gunakan minimal 10 karakter, kombinasikan huruf besar, kecil, angka, dan simbol. Kosongkan jika tidak ingin mengganti.
                    </small>
                  </div>

                  <div class="form-group">
                    <label>Peran (Role) <span class="text-danger">*</span></label>
                    <select class="form-control track-change" name="roles[]" id="roles" multiple required>
                      <?php while($row = $roles->fetch_assoc()): ?>
                        <option value="<?= (int)$row['role_id'] ?>" <?= in_array((int)$row['role_id'],$ownRoleIds)?'selected':''; ?>>
                          <?= htmlspecialchars($row['role_name']) ?> (<?= htmlspecialchars($row['role_key']) ?>)
                        </option>
                      <?php endwhile; ?>
                    </select>
                    <small class="text-muted">Pilih satu atau lebih peran. Multi-role didukung.</small>
                  </div>
                </div>

                <!-- kanan: avatar -->
                <div class="col-md-5 col-sm-12">
                  <label>Foto Profil</label>
                  <div id="avatarDrop" class="text-center">
                    <img id="avatarPreview" src="<?= $previewSrc; ?>" alt="Foto profil" class="img-circle"/>
                    <p class="help-block" style="margin:10px 0">Tarik & lepas foto ke sini atau klik tombol di bawah.</p>
                    <input type="file" name="foto" id="fotoInput" accept="image/*" style="display:none;">
                    <div class="btn-group">
                      <button type="button" class="btn btn-info btn-sm" id="pickFoto"><i class="fa fa-image"></i> Pilih Foto</button>
                      <button type="button" class="btn btn-default btn-sm" id="hapusFoto"><i class="fa fa-trash"></i> Hapus</button>
                    </div>
                    <div class="text-muted" style="margin-top:6px;font-size:12px;">Format JPG/PNG, ukuran maks 2 MB.</div>
                  </div>
                </div>
              </div>

              <hr>
              <div class="text-right">
                <button type="submit" class="btn btn-primary btn-lg btn-shimmer" id="btnSimpan">
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
// ====== PASSWORD UI (tetap) ======
const pw = document.getElementById('pw');
const fill = document.getElementById('strengthFill');
const label = document.getElementById('strengthLabel');

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
  alert('Sandi akan di-reset ke default: 12345678 saat disimpan.');
});

document.getElementById('copyPw').addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(pw.value || '');
    alert('Tersalin.');
  } catch(e){ alert('Gagal menyalin.'); }
});

// ====== FOTO interaktif (tetap) ======
document.getElementById('pickFoto').onclick = () => document.getElementById('fotoInput').click();
const drop = document.getElementById('avatarDrop');
const input = document.getElementById('fotoInput');
const preview = document.getElementById('avatarPreview');
const DEFAULT_AVATAR = <?= json_encode($DEFAULT_AVATAR); ?>;

['dragenter','dragover'].forEach(evt => drop.addEventListener(evt, e => {e.preventDefault();drop.classList.add('dragover');}));
['dragleave','drop'].forEach(evt => drop.addEventListener(evt, e => {e.preventDefault();drop.classList.remove('dragover');}));
drop.addEventListener('drop', e => { input.files = e.dataTransfer.files; handleFile(input.files[0]); });
input.addEventListener('change', e => handleFile(e.target.files[0]));

function handleFile(file){
  if(!file) return;
  if(!file.type.match(/^image\//)) { alert('File harus gambar'); input.value=''; return; }
  if(file.size > 2*1024*1024){ alert('Ukuran maksimal 2MB'); input.value=''; return; }
  const reader = new FileReader();
  reader.onload = e => { preview.src = e.target.result; document.getElementById('remove_foto').value='0'; markUnsaved(); };
  reader.readAsDataURL(file);
}

document.getElementById('hapusFoto').onclick = () => {
  preview.src = DEFAULT_AVATAR;
  input.value = '';
  document.getElementById('remove_foto').value='1';
  markUnsaved();
};

// ====== Multi-select roles: Select2 jika tersedia (tetap)
$(function(){
  if($.fn.select2){
    $('#roles').select2({ placeholder: 'Pilih peran', width: '100%' });
  }
});

// ====== Shimmer saat ada perubahan + spinner saat submit (tetap)
const btnSimpan = document.getElementById('btnSimpan');
function markUnsaved(){ btnSimpan.classList.add('unsaved'); }

document.querySelectorAll('.track-change, #roles').forEach(el=>{
  el.addEventListener('change', markUnsaved);
  el.addEventListener('input', markUnsaved);
});

// spinner saat submit
document.getElementById('formEditUser').addEventListener('submit', function(){
  btnSimpan.classList.add('loading');
  btnSimpan.disabled = true;
});

// render awal strength
renderStrength('');

// ====== Shortcut CTRL+S / CMD+S untuk Simpan ======
document.addEventListener('keydown', function(e){
  const isSave = (e.key === 's' || e.key === 'S') && (e.ctrlKey || e.metaKey);
  if(!isSave) return;
  e.preventDefault();
  const form = document.getElementById('formEditUser');
  if(!form || btnSimpan.disabled) return;
  // trigger submit dengan aman
  btnSimpan.classList.add('loading');
  btnSimpan.disabled = true;
  if (form.requestSubmit) form.requestSubmit(); else form.submit();
});
</script>
<?php include 'footer.php'; ?>
