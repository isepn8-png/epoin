<?php
// siswa/cbt.php — Web-view wrapper untuk CBT NESAGUN (sistem terpisah)
// Menampilkan situs CBT dalam bingkai (preview) + tombol buka di jendela penuh.
// Login & ujian dilakukan di jendela penuh (first-party) karena cookie CSRF
// situs CBT ber-SameSite=Strict (diblokir browser bila di dalam iframe lintas-situs).
$PAGE_TITLE = 'CBT NESAGUN';
include 'header.php';

$CBT_URL = 'https://cbt.smpn1gunungtanjung.sch.id/';
?>
<style>
  .cbt-fadein{opacity:0; transform:translateY(14px); animation:cbtUp .5s ease forwards;}
  @keyframes cbtUp{to{opacity:1; transform:none;}}

  .cbt-hero{
    border-radius:16px; padding:20px 22px; color:#fff; position:relative; overflow:hidden;
    background:linear-gradient(135deg,#0ea5e9,#6366f1);
    box-shadow:0 12px 28px rgba(99,102,241,.22); margin-bottom:16px;
    display:flex; align-items:center; gap:18px; flex-wrap:wrap;
  }
  .cbt-hero .ic-bg{position:absolute; right:-10px; bottom:-18px; font-size:104px; opacity:.15}
  .cbt-hero .htext{flex:1 1 280px; min-width:240px}
  .cbt-hero h2{margin:0 0 4px; font-weight:800; font-size:22px}
  .cbt-hero p{margin:0; opacity:.95; font-size:13.5px; max-width:560px}
  .cbt-cta{
    display:inline-flex; align-items:center; gap:10px; cursor:pointer;
    background:#fff; color:#1d4ed8; border:0; border-radius:999px;
    padding:13px 22px; font-weight:800; font-size:15px;
    box-shadow:0 10px 24px rgba(0,0,0,.18); transition:transform .15s ease, box-shadow .15s ease;
  }
  .cbt-cta:hover{transform:translateY(-2px); box-shadow:0 14px 30px rgba(0,0,0,.24)}
  .cbt-cta i{font-size:17px}

  .cbt-note{
    background:#fffbeb; border:1px solid #fde68a; color:#92400e;
    border-radius:12px; padding:12px 16px; font-size:13px; margin-bottom:16px;
    display:flex; gap:10px; align-items:flex-start;
  }
  .cbt-note i{margin-top:2px}

  .cbt-frame-wrap{
    background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden;
    box-shadow:0 8px 22px rgba(15,23,42,.06);
  }
  .cbt-frame-bar{
    display:flex; align-items:center; gap:10px; padding:10px 14px;
    border-bottom:1px solid #eef0f4; background:#f8fafc;
  }
  .cbt-dots{display:flex; gap:6px}
  .cbt-dots span{width:11px; height:11px; border-radius:50%}
  .cbt-dots .r{background:#fca5a5}.cbt-dots .y{background:#fcd34d}.cbt-dots .g{background:#86efac}
  .cbt-url{
    flex:1; font-size:12.5px; color:#64748b; background:#fff; border:1px solid #e5e7eb;
    border-radius:999px; padding:5px 14px; font-family:monospace; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
  }
  .cbt-frame-bar .mini{font-size:12px; padding:5px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; background:#e0e7ff; color:#3730a3}
  .cbt-frame-bar .mini:hover{background:#c7d2fe}

  .cbt-stage{position:relative; height:72vh; min-height:460px; background:#f1f5f9}
  .cbt-stage iframe{width:100%; height:100%; border:0; display:block}
  .cbt-loading,.cbt-blocked{
    position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
    text-align:center; color:#64748b; padding:24px; background:#f8fafc;
  }
  .cbt-blocked{display:none}
  .cbt-spin{width:42px; height:42px; border-radius:50%; border:4px solid #c7d2fe; border-top-color:#6366f1; animation:cbtspin 1s linear infinite; margin-bottom:14px}
  @keyframes cbtspin{to{transform:rotate(360deg)}}
  .cbt-blocked .ic{width:70px;height:70px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin-bottom:14px}
  .cbt-blocked .ic i{font-size:28px;color:#dc2626}
  .cbt-blocked h4{color:#334155;font-weight:800;margin:0 0 6px}

  @media(max-width:480px){
    .cbt-hero h2{font-size:19px}
    .cbt-stage{height:64vh; min-height:380px}
    .cbt-url{display:none}
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fas fa-laptop-code" style="color:#6366f1;margin-right:8px"></i>CBT NESAGUN <small>Computer Based Test</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Dashboard</a></li>
      <li class="active">CBT NESAGUN</li>
    </ol>
  </section>

  <section class="content">

    <div class="cbt-hero cbt-fadein">
      <i class="fas fa-laptop-code ic-bg"></i>
      <div class="htext">
        <h2>Ujian Berbasis Komputer</h2>
        <p>CBT NESAGUN berjalan pada sistem terpisah. Untuk <b>login & mengerjakan ujian</b>, buka di jendela penuh agar berjalan optimal dan aman.</p>
      </div>
      <button class="cbt-cta" id="btnOpenFull" type="button">
        <i class="fas fa-expand"></i> Buka di Layar Penuh
      </button>
    </div>

    <div class="cbt-note cbt-fadein">
      <i class="fas fa-info-circle"></i>
      <div>
        <b>Penting:</b> Bingkai di bawah hanya untuk pratinjau. <b>Login harus dilakukan di jendela penuh</b>
        (tombol di atas) — sistem keamanan CBT memblokir login bila dijalankan di dalam bingkai.
      </div>
    </div>

    <div class="cbt-frame-wrap cbt-fadein">
      <div class="cbt-frame-bar">
        <div class="cbt-dots"><span class="r"></span><span class="y"></span><span class="g"></span></div>
        <div class="cbt-url"><?= htmlspecialchars($CBT_URL, ENT_QUOTES, 'UTF-8') ?></div>
        <button class="mini" id="btnReload" type="button"><i class="fa fa-rotate-right"></i> Muat ulang</button>
        <button class="mini" id="btnOpenFull2" type="button"><i class="fa fa-up-right-from-square"></i> Layar Penuh</button>
      </div>
      <div class="cbt-stage">
        <div class="cbt-loading" id="cbtLoading">
          <div class="cbt-spin"></div>
          <div>Memuat pratinjau CBT&hellip;</div>
        </div>
        <div class="cbt-blocked" id="cbtBlocked">
          <div class="ic"><i class="fas fa-shield-halved"></i></div>
          <h4>Pratinjau tidak dapat ditampilkan</h4>
          <p>Sistem CBT tidak mengizinkan tampilan dalam bingkai.<br>Silakan gunakan tombol <b>Buka di Layar Penuh</b>.</p>
          <button class="cbt-cta" style="margin-top:10px;color:#1d4ed8" id="btnOpenFull3" type="button">
            <i class="fas fa-expand"></i> Buka di Layar Penuh
          </button>
        </div>
        <iframe id="cbtFrame"
                src="<?= htmlspecialchars($CBT_URL, ENT_QUOTES, 'UTF-8') ?>"
                referrerpolicy="no-referrer"
                loading="lazy"
                title="Pratinjau CBT NESAGUN"></iframe>
      </div>
    </div>

  </section>
</div>

<script>
(function(){
  var URL = <?= json_encode($CBT_URL, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var frame   = document.getElementById('cbtFrame');
  var loading = document.getElementById('cbtLoading');
  var blocked = document.getElementById('cbtBlocked');
  var loaded  = false;

  // Buka di jendela maksimal (near-fullscreen, first-party → cookie login jalan normal)
  function openFull(){
    var w = (screen && screen.availWidth)  ? screen.availWidth  : 1280;
    var h = (screen && screen.availHeight) ? screen.availHeight : 800;
    var feat = 'noopener,noreferrer,scrollbars=yes,resizable=yes,width='+w+',height='+h+',left=0,top=0';
    var win = window.open(URL, 'cbt_nesagun', feat);
    if (win) { try { win.moveTo(0,0); win.resizeTo(w,h); } catch(e){} win.focus(); }
    else { window.open(URL, '_blank', 'noopener'); } // fallback bila popup diblok
  }

  ['btnOpenFull','btnOpenFull2','btnOpenFull3'].forEach(function(id){
    var b = document.getElementById(id); if (b) b.addEventListener('click', openFull);
  });

  document.getElementById('btnReload').addEventListener('click', function(){
    loaded = false; loading.style.display='flex'; blocked.style.display='none';
    frame.src = URL; armTimeout();
  });

  frame.addEventListener('load', function(){ loaded = true; loading.style.display='none'; });

  // Jika iframe tak kunjung load (kemungkinan diblokir), tampilkan fallback
  function armTimeout(){
    setTimeout(function(){
      if (!loaded){ loading.style.display='none'; blocked.style.display='flex'; }
    }, 6000);
  }
  armTimeout();
})();
</script>

<?php include 'footer.php'; ?>
