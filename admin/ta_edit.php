<?php include 'header.php'; ?>

<style>
/* ====== EPS polish (fallback bila eps-ui.css belum terload) ====== */
:root{
  --blue-50:#f0f6ff; --blue-100:#e3efff; --blue-200:#cfe3ff; --blue-300:#b9d6ff;
  --blue-400:#8fbaff; --blue-500:#4f9cf9; --blue-600:#2d6cdf; --blue-700:#1f5ac8;
  --ink-900:#0b1220; --ink-800:#1e293b; --ink-700:#334155; --line:#dbe5ff;
  --bg-page:linear-gradient(180deg,#f8fbff 0%, #f3f7ff 100%);
  --bg-card:#fff; --bg-row:#f8fbff; --bg-hover:#eef4ff;
  --radius-lg:16px; --radius-md:12px; --radius-pill:999px;
  --shadow-lg:0 10px 30px rgba(45,108,223,.12);
  --grad-primary:linear-gradient(90deg, var(--blue-600), var(--blue-500));
  --grad-primary-hover:linear-gradient(90deg, var(--blue-700), var(--blue-600));
  --fs-xs:clamp(11px,.85vw,12px); --fs-2xl:clamp(22px,2.6vw,28px);
}

/* ==== Full-width container (preferensi tersimpan) ==== */
.content-wrapper{ background:var(--bg-page); }
@keyframes textFade{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }
@media (min-width:1200px){ .content .row .col-lg-12{ float:none; width:100%; } }

/* Page title (atas halaman) */
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

/* Box / Card */
.box{ border:0;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;position:relative;background:var(--bg-card); }
.box:before{ content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--blue-600),var(--blue-400),var(--blue-600));opacity:.9; }

/* ===== RIBBON PANEL TITLE (eye-catching & elegan) ===== */
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
/* underline gradien kecil yang bergerak */
.panel-underline{
  position:absolute; left:18px; right:18px; bottom:0; height:2px;
  background:linear-gradient(90deg, rgba(45,108,223,.0), rgba(45,108,223,.7), rgba(45,108,223,.0));
  background-size:200% 100%;
  animation:flow 3.8s linear infinite;
}
@keyframes flow{ 0%{background-position:0 0} 100%{background-position:200% 0} }

/* Buttons */
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
.form-control{ border-radius:12px; border:1px solid var(--line); box-shadow:none; }
.form-control:focus{ border-color:var(--blue-500); box-shadow:0 0 0 3px rgba(79,156,249,.15); }
.help-hint{ color:#64748b; font-size:12px; }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-calendar"></i></span>
      <span>Tahun Ajaran</span>
      <span class="badge-chip"><i class="fa fa-database"></i> Data Master</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li><a href="ta.php">Tahun Ajaran</a></li>
      <li class="active">Edit</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-12"><!-- full width -->
        <div class="box">

          <!-- ===== Ribbon Title di header panel ===== -->
          <div class="panel-head">
            <div class="panel-title-flag" aria-label="Judul Panel">
              <span class="ico"><i class="fa fa-pencil"></i></span>
              <span>Edit Tahun Ajaran</span>
              <i class="spark"></i>
            </div>
            <a href="ta.php" class="btn btn-soft btn-sm pull-right"><i class="fa fa-reply"></i> &nbsp;Kembali</a>
            <span class="panel-underline"></span>
          </div>

          <div class="box-body">
            <?php
              $id_ta = isset($_GET['id']) ? (int)$_GET['id'] : 0;
              $ta = mysqli_query($koneksi,"SELECT * FROM ta WHERE ta_id=".$id_ta." LIMIT 1");
              if($ta && mysqli_num_rows($ta)){
                $s = mysqli_fetch_assoc($ta);
            ?>
            <form action="ta_update.php" method="post" autocomplete="off">
              <input type="hidden" name="id" value="<?php echo (int)$s['ta_id']; ?>">

              <div class="form-group">
                <label>Nama Tahun Ajaran</label>
                <input
                  type="text"
                  class="form-control"
                  name="nama"
                  required
                  pattern="^[0-9]{4}/[0-9]{4}$"
                  title="Format wajib: 2024/2025"
                  placeholder="Misal: 2024/2025"
                  value="<?php echo htmlspecialchars($s['ta_nama']); ?>">
                <div class="help-hint">Gunakan format <b>YYYY/YYYY</b> (contoh: <i>2024/2025</i>).</div>
              </div>

              <div class="form-group">
                <label>Status</label>
                <select class="form-control" name="status" required>
                  <option value="1" <?php echo ($s['ta_status']=='1'?'selected':''); ?>>Aktif / Sedang Berjalan</option>
                  <option value="0" <?php echo ($s['ta_status']=='0'?'selected':''); ?>>Selesai / Telah Berlalu</option>
                </select>
              </div>

              <div class="form-group" style="display:flex; gap:8px; justify-content:flex-end;">
                <a href="ta.php" class="btn btn-soft btn-sm"><i class="fa fa-times"></i> Batal</a>
                <button type="submit" class="btn btn-grad btn-sm"><i class="fa fa-save"></i> Simpan Perubahan</button>
              </div>
            </form>
            <?php } else { ?>
              <div class="alert alert-warning" style="border-radius:12px;">
                <i class="fa fa-info-circle"></i> Data Tahun Ajaran tidak ditemukan.
              </div>
              <a href="ta.php" class="btn btn-soft btn-sm"><i class="fa fa-reply"></i> Kembali</a>
            <?php } ?>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<?php include 'footer.php'; ?>
