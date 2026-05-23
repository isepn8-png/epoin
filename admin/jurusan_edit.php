<?php
// ========== Edit Tingkat Kelas / Jurusan ==========
include 'header.php';

// Pastikan koneksi dari header tersedia
if (!isset($koneksi)) {
  die('Koneksi database ($koneksi) tidak ditemukan. Pastikan header.php men-define $koneksi.');
}

// ---- CSRF token (konsisten dengan halaman daftar) ----
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

// Helper escape
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Ambil dan validasi ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil data jurusan via prepared statement
$jurusan = null;
if ($id > 0 && ($stmt = mysqli_prepare($koneksi, "SELECT jurusan_id, jurusan_nama FROM jurusan WHERE jurusan_id = ? LIMIT 1"))) {
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $jid, $jnama);
  if (mysqli_stmt_fetch($stmt)) { $jurusan = ['jurusan_id' => $jid, 'jurusan_nama' => $jnama]; }
  mysqli_stmt_close($stmt);
}
?>

<style>
  /* ===== EPS Theme (konsisten) ===== */
  :root{
    --blue-50:#f0f6ff; --blue-100:#e3efff; --blue-400:#8fbaff; --blue-500:#4f9cf9; --blue-600:#2d6cdf; --blue-700:#1f5ac8;
    --ink-900:#0b1220; --ink-800:#1e293b; --ink-700:#334155; --line:#dbe5ff;
    --bg-page:linear-gradient(180deg,#f8fbff 0%, #f3f7ff 100%);
    --bg-card:#fff; --bg-row:#f8fbff; --bg-hover:#eef4ff;
    --radius-lg:16px; --radius-md:12px; --radius-pill:999px;
    --shadow-lg:0 10px 30px rgba(45,108,223,.12);
    --grad-primary:linear-gradient(90deg, var(--blue-600), var(--blue-500));
    --grad-primary-hover:linear-gradient(90deg, var(--blue-700), var(--blue-600));
    --fs-xs:clamp(11px,.85vw,12px); --fs-2xl:clamp(22px,2.6vw,28px);
  }
  @keyframes textFade{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }

  .content-wrapper{ background:var(--bg-page); }

  /* ==== Full-width container (preferensi tersimpan) ==== */
  @media (min-width:1200px){
    .content .row .col-lg-12{ float:none; width:100%; }
  }

  /* ===== Header Halaman (judul atas) ===== */
  .page-title{
    display:flex; align-items:center; gap:12px;
    font-size:var(--fs-2xl); font-weight:800; color:var(--ink-900);
    letter-spacing:.2px; animation:textFade .6s ease-out both;
  }
  .title-icon{
    width:40px;height:40px;border-radius:12px;
    display:inline-flex;align-items:center;justify-content:center;
    background:var(--blue-100); color:var(--blue-600);
    box-shadow:inset 0 0 0 1px var(--line);
  }
  .badge-chip{
    background:var(--blue-50); border:1px solid var(--line); color:var(--ink-700);
    border-radius:var(--radius-pill); padding:4px 10px;
    display:inline-flex; align-items:center; gap:6px;
    font-size:var(--fs-xs); font-weight:700; white-space:nowrap;
  }
  .badge-chip i{ color:var(--blue-600); }
  .breadcrumb>li+li:before{content:"› ";color:var(--ink-700)}

  /* ===== Box (panel) ===== */
  .box{ border:0;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;position:relative;background:var(--bg-card); }
  .box:before{ content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--blue-600),var(--blue-400),var(--blue-600));opacity:.9; }

  /* ===== Ribbon Title untuk judul panel "Edit ..." ===== */
  .panel-head{ position:relative; background:var(--bg-card); border-bottom:1px solid var(--line); padding:16px 18px 18px 18px; }
  .panel-title-flag{
    display:inline-flex; align-items:center; gap:10px;
    padding:8px 14px; border-radius:14px;
    background:var(--grad-primary); color:#fff; font-weight:800; letter-spacing:.2px;
    box-shadow:0 8px 20px rgba(45,108,223,.25);
    position:relative; isolation:isolate;
  }
  .panel-title-flag .ico{
    width:28px; height:28px; border-radius:9px;
    background:rgba(255,255,255,.15);
    display:inline-flex; align-items:center; justify-content:center;
  }
  .panel-title-flag .spark{
    position:absolute; right:-8px; top:50%; transform:translateY(-50%);
    width:8px; height:8px; border-radius:50%; background:#fff; opacity:.7;
    filter:blur(.2px);
    box-shadow:0 0 0 6px rgba(255,255,255,.24);
    animation:panelPing 2.4s ease-out infinite;
  }
  @keyframes panelPing{
    0%{ transform:translateY(-50%) scale(.6); opacity:.9 }
    70%{ transform:translateY(-50%) scale(1.8); opacity:0 }
    100%{ opacity:0 }
  }
  .panel-underline{
    position:absolute; left:18px; right:18px; bottom:0; height:2px;
    background:linear-gradient(90deg, rgba(45,108,223,.0), rgba(45,108,223,.7), rgba(45,108,223,.0));
    background-size:200% 100%; animation:flow 3.8s linear infinite;
  }
  @keyframes flow{ 0%{background-position:0 0} 100%{background-position:200% 0} }

  /* Tombol */
  .btn-grad{
    background:var(--grad-primary); color:#fff; border:0; border-radius:var(--radius-md);
    padding:9px 12px; font-weight:700; box-shadow:0 8px 20px rgba(45,108,223,.25);
    transition:transform .15s ease, filter .2s ease;
  }
  .btn-grad:hover{ filter:brightness(1.06); transform:translateY(-1px); }
  .btn-soft{
    background:#eef2f7; color:#0b1220; border:0; border-radius:var(--radius-md);
    padding:9px 12px; font-weight:700;
  }
  .btn-soft:hover{ filter:brightness(0.98); }

  /* Form */
  .hint{ color:#64748b; font-size:12px; }
  .form-control{ box-shadow:none; border-radius:12px; border:1px solid var(--line); }
  .form-control:focus{ border-color: var(--blue-500); box-shadow: 0 0 0 3px rgba(79,156,249,.15); }

  .alert{ border-radius:12px; }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-graduation-cap"></i></span>
      <span>Tingkat Kelas / Jurusan</span>
      <span class="badge-chip"><i class="fa fa-database"></i> Data Master</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li><a href="jurusan.php">Tingkat Kelas / Jurusan</a></li>
      <li class="active">Edit</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- full width -->
      <section class="col-lg-12">
        <div class="box">

          <!-- Ribbon Title (judul panel edit) -->
          <div class="panel-head">
            <div class="panel-title-flag">
              <span class="ico"><i class="fa fa-edit"></i></span>
              <span>Edit Tingkat Kelas / Jurusan</span>
              <i class="spark"></i>
            </div>
            <a href="jurusan.php" class="btn btn-soft btn-sm pull-right"><i class="fa fa-reply"></i> &nbsp;Kembali</a>
            <span class="panel-underline"></span>
          </div>

          <div class="box-body" style="color:var(--ink-800);">
            <?php if (!$jurusan): ?>
              <div class="alert alert-danger">
                Data tidak ditemukan. <a href="jurusan.php" class="alert-link">Kembali ke daftar</a>.
              </div>
            <?php else: ?>
              <form action="jurusan_update.php" method="post" autocomplete="off" id="form-edit" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$jurusan['jurusan_id']; ?>">

                <div class="form-group has-feedback">
                  <label for="jurusan_nama">Nama Tingkat Kelas / Jurusan</label>
                  <input
                    type="text"
                    class="form-control"
                    id="jurusan_nama"
                    name="nama"
                    required
                    minlength="2"
                    maxlength="100"
                    placeholder="Contoh: Kelas 7 / XI RPL / Desain Komunikasi Visual"
                    value="<?php echo e($jurusan['jurusan_nama']); ?>">
                  <span class="glyphicon glyphicon-education form-control-feedback" aria-hidden="true"></span>
                  <p class="hint">Gunakan format yang konsisten agar mudah dicari. Hindari duplikasi penamaan.</p>
                </div>

                <div class="form-group" style="display:flex; gap:8px; justify-content:flex-end;">
                  <a href="jurusan.php" class="btn btn-soft btn-sm"><i class="fa fa-times"></i> Batal</a>
                  <button type="submit" class="btn btn-grad btn-sm"><i class="fa fa-save"></i> Simpan Perubahan</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </section>

</div>

<script>
(function(){
  // Validasi ringan sisi klien (pertahankan)
  var form = document.getElementById('form-edit');
  if (form) {
    form.addEventListener('submit', function(e){
      var nama = document.getElementById('jurusan_nama');
      var val = (nama.value || '').trim();
      if (val.length < 2) {
        e.preventDefault();
        alert('Nama minimal 2 karakter.');
        nama.focus();
      }
    });
  }
})();
</script>

<?php include 'footer.php'; ?>
