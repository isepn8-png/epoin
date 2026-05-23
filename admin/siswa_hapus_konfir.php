<?php include 'header.php'; ?>
<style>
  /* ================== EPS — Poles UI Halaman Hapus Siswa ==================
     - Fokus tampilan (elegan, modern, interaktif), logic tidak diubah
     - Perbaikan UTAMA: scope variabel -> .eps-del { ... } (bukan ".eps-del :root")
  ======================================================================= */

  /* Token tema halaman ini */
  .eps-del{
    --blue-700:#0B57D0; --blue-600:#2E7CF7; --blue-500:#5EA0FF;
    --ink-900:#0B1220;  --ink-700:#1F2937;  --ink-600:#334155;
    --danger-700:#DC2626; --danger-600:#EF4444; --danger-200:#FECACA;
    --pill-rad:999px; --rad-16:16px; --rad-12:12px;
    --shadow-lg:0 20px 40px rgba(11,87,208,.18);
    --shadow-sm:0 10px 18px rgba(15,23,42,.12);
  }

  /* ---------- Page Title (ikon + badge) ---------- */
  .eps-del .page-title{
    display:flex; align-items:center; gap:14px; padding:10px 0 8px;
  }
  .eps-del .title-icon{
    width:44px; height:44px; border-radius:14px;
    background: linear-gradient(140deg,var(--blue-700),var(--blue-600));
    display:grid; place-items:center; color:#fff; font-size:20px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.22), var(--shadow-sm);
  }
  .eps-del .title-text h1{
    margin:0; font-family: Inter, Segoe UI, system-ui, -apple-system, Roboto, Arial, sans-serif;
    font-weight:800; letter-spacing:.2px; color:var(--ink-900); font-size:22px;
  }
  .eps-del .title-badge{
    display:inline-block; margin-top:2px;
    background:#F6F9FF; color:var(--blue-700);
    border:1px solid #D7E6FF; border-radius:999px;
    padding:2px 8px; font-size:11px; font-weight:700;
  }

  /* ---------- Box polishing ---------- */
  .eps-del .box.eps-box{
    border-radius: var(--rad-16);
    overflow: hidden;
    border:1px solid rgba(15,23,42,.06);
    box-shadow: var(--shadow-lg);
    background:#fff;
  }
  .eps-del .box.eps-box .box-header{
    position:relative; border-bottom:1px solid rgba(2,6,23,.06);
    padding:16px 18px;
    background:
      linear-gradient(180deg,rgba(246,249,255,.92),rgba(255,255,255,.98)),
      linear-gradient(120deg,var(--blue-700),var(--blue-500));
  }
  .eps-del .box.eps-box .box-header h3{
    margin:0; font-weight:800; color:#0f172a; letter-spacing:.2px;
  }
  .eps-del .grad-bar{
    position:absolute; left:0; right:0; bottom:0; height:3px;
    background:linear-gradient(90deg,var(--blue-700),var(--blue-600),var(--blue-500));
  }
  .eps-del .box.eps-box .box-body{ padding:20px 18px 22px; }

  /* ---------- Warning callout ---------- */
  .eps-del .warn{
    border-radius: var(--rad-12);
    padding:14px 14px 12px; margin:12px 0 16px;
    background:
      linear-gradient(180deg, rgba(254,242,242,.85), rgba(255,255,255,.96)),
      linear-gradient(140deg, #fff, #fff);
    border:1px solid rgba(239,68,68,.30);
  }
  .eps-del .warn .w-head{
    display:flex; align-items:center; gap:10px; color:#991b1b; font-weight:800;
  }
  .eps-del .warn .w-head .w-icon{
    width:28px; height:28px; border-radius:8px; color:#fff;
    background:linear-gradient(140deg,var(--danger-700),var(--danger-600));
    display:grid; place-items:center;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.25);
  }
  .eps-del .warn ul{
    margin:10px 0 0 22px; color:#581c1c; font-size:13px;
  }

  /* ---------- Actions ---------- */
  .eps-del .eps-actions{
    display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:16px; flex-wrap:wrap;
  }
  .eps-del .btn-back{
    border-radius: var(--pill-rad);
    font-weight:800; letter-spacing:.2px;
    border:1px solid rgba(2,6,23,.08);
    background:linear-gradient(180deg,rgba(255,255,255,.65),rgba(255,255,255,.9));
    color:#0f172a !important;
    box-shadow: 0 8px 18px rgba(2,6,23,.08);
    transition: transform .15s ease, box-shadow .2s ease;
  }
  .eps-del .btn-back:hover{ transform: translateY(-1px); box-shadow: 0 10px 20px rgba(2,6,23,.12); }

  .eps-del .btn-hapus{
    border-radius: var(--pill-rad);
    font-weight:900; letter-spacing:.3px; color:#fff !important;
    background:linear-gradient(135deg,var(--danger-700),var(--danger-600));
    box-shadow: 0 14px 28px rgba(220,38,38,.28), inset 0 0 0 1px rgba(255,255,255,.16);
    border:none;
    transition: transform .15s ease, box-shadow .2s ease;
  }
  .eps-del .btn-hapus:hover{
    transform: translateY(-1px);
    box-shadow: 0 18px 34px rgba(220,38,38,.34), inset 0 0 0 1px rgba(255,255,255,.22);
  }
  .eps-del .btn-hapus.disabled, .eps-del .btn-hapus[disabled]{ opacity:.9; cursor:not-allowed; }

  /* ---------- Micro animation ---------- */
  @keyframes floatUp{ from{ transform: translateY(6px); opacity:.0 } to{ transform:none; opacity:1 } }
  .eps-del .floatUp{ animation: floatUp .36s ease both; }
</style>

<div class="content-wrapper eps-del">
  <section class="content-header">
    <div class="page-title">
      <div class="title-icon"><i class="fa fa-user-times"></i></div>
      <div class="title-text">
        <h1>Hapus Siswa</h1>
        <span class="title-badge">Data Siswa</span>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="row">
      <!-- Sesuai preferensi: full-width di layar besar -->
      <section class="col-lg-12">
        <div class="box box-primary eps-box floatUp">
          <div class="box-header with-border">
            <h3 class="box-title">Yakin ingin menghapus siswa?</h3>
            <div class="grad-bar"></div>
          </div>

          <div class="box-body">
            <div class="warn">
              <div class="w-head">
                <div class="w-icon"><i class="fa fa-exclamation-triangle"></i></div>
                <div>Ini tindakan permanen—tidak bisa dibatalkan.</div>
              </div>
              <ul>
                <li>Semua data yang berhubungan dengan siswa ini akan ikut terhapus.</li>
              </ul>
            </div>

            <p style="margin:10px 0 0; color:#0f172a;">
              Dengan menghapus, <b>semua data yang berhubungan dengan siswa ini akan ikut dihapus.</b>
            </p>

            <div class="eps-actions">
              <a href="siswa.php" class="btn btn-danger btn-sm btn-back">
                <i class="fa fa-reply"></i> &nbsp;Kembali
              </a>

              <?php $idd = isset($_GET['id']) ? (int)$_GET['id'] : 0; ?>
              <form id="formHapusSiswa" class="pull-right" action="siswa_hapus_aksi.php" method="post" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo $idd; ?>">
                <button type="submit" class="btn btn-success btn-sm btn-hapus" aria-label="Hapus permanen">
                  <i class="fa fa-check"></i> &nbsp;Hapus Permanen
                </button>
              </form>
            </div>
          </div>
        </div>
      </section>
    </div>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var f = document.getElementById('formHapusSiswa');
  if(!f) return;

  f.addEventListener('submit', function(e){
    if(!confirm('Hapus permanen data siswa? Tindakan ini tidak bisa dibatalkan.')){
      e.preventDefault();
      return;
    }
    var btn = f.querySelector('button[type=submit]');
    if(btn){ btn.disabled = true; btn.classList.add('disabled'); }

    if (window.EPS && typeof EPS.showLoader === 'function') {
      EPS.showLoader('Menghapus data siswa... Mohon tunggu');
    } else {
      if (!document.getElementById('pageLoader')) {
        var style = document.createElement('style');
        style.textContent = '@keyframes __epsspin{to{transform:rotate(360deg)}}';
        document.head.appendChild(style);

        var overlay = document.createElement('div');
        overlay.id = 'pageLoader';
        overlay.setAttribute('style',
          'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;' +
          'background:rgba(2,6,23,.45);backdrop-filter:blur(2px)'
        );

        var spinner = document.createElement('div');
        spinner.setAttribute('style',
          'width:56px;height:56px;border-radius:50%;border:6px solid rgba(255,255,255,.35);' +
          'border-top-color:#fff;animation:__epsspin 1s linear infinite'
        );

        var msg = document.createElement('div');
        msg.textContent = 'Menghapus data siswa... Mohon tunggu';
        msg.setAttribute('style','color:#fff;margin-top:12px;font-weight:700;letter-spacing:.2px');

        overlay.appendChild(spinner);
        overlay.appendChild(msg);
        document.body.appendChild(overlay);
      }
    }
  });
});
</script>

<?php include 'footer.php'; ?>
