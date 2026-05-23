<?php
// exam_cbt.php — Soft-lock CBT (Fullscreen + anti-shortcut + pelanggaran)
// Minimal server-side demo
session_start();
header('X-Frame-Options: DENY');           // cegah clickjacking
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: fullscreen=(self), microphone=(), camera=()');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi']==='submit_ujian') {
  // TODO: proses jawaban/score di sini
  $_SESSION['ujian_selesai'] = true;
  echo "<!doctype html><meta charset='utf-8'><title>Ujian Selesai</title>
        <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:2rem}</style>
        <h1>Ujian Selesai</h1><p>Jawaban Anda telah disimpan.</p>
        <p><a href='index.php'>Kembali ke beranda</a></p>";
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CBT — Mode Ujian</title>
<style>
  :root{ --brand:#0ea5e9; --danger:#ef4444; --ok:#10b981; --ink:#0f172a; }
  * { box-sizing: border-box; }
  html,body { height:100%; }
  body {
    margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:#0b1220; color:#e5e7eb;
  }
  .wrap { max-width:1000px; margin:0 auto; padding:16px; }
  header {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    padding:12px 0; position:sticky; top:0; background:rgba(11,18,32,.9); backdrop-filter: blur(6px);
    border-bottom:1px solid rgba(255,255,255,.08); z-index:5;
  }
  .badge { padding:.25rem .5rem; border-radius:.5rem; font-size:.85rem; background:#0f172a; border:1px solid rgba(255,255,255,.08)}
  .badge.ok { color:#bbf7d0; border-color:rgba(16,185,129,.35); }
  .badge.warn { color:#fecaca; border-color:rgba(239,68,68,.35); }
  .btn {
    appearance:none; border:0; cursor:pointer; padding:.75rem 1rem; border-radius:.75rem;
    background:linear-gradient(135deg, #0ea5e9, #6366f1); color:#fff; font-weight:600;
    box-shadow: 0 8px 24px rgba(14,165,233,.25);
  }
  .btn[disabled]{opacity:.6; cursor:not-allowed}
  .grid { display:grid; gap:16px; grid-template-columns: 1fr; }
  @media (min-width: 800px){ .grid { grid-template-columns: 1fr 1fr; } }

  .card {
    background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.08);
    border-radius:16px; padding:16px;
  }
  .question { margin:0 0 .75rem 0; font-weight:600; }
  .footer { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:16px; }

  /* Overlay start exam */
  .overlay {
    position:fixed; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;
    background: radial-gradient(120% 120% at 10% 0%, #0ea5e9 0%, transparent 35%) , #0b1220;
    z-index:9999; text-align:center; padding:24px;
  }
  .overlay h1 { margin:0 0 .5rem 0; }
  .hint { opacity:.8; font-size:.95rem; max-width:720px; margin:0 auto 1rem; }
  .small { font-size:.85rem; opacity:.75; }
  .warn { color:#fecaca }
  .stat { display:flex; gap:10px; align-items:center; }
  .stat .dot { width:10px; height:10px; border-radius:50%; background:#10b981; box-shadow:0 0 0 4px rgba(16,185,129,.15); }

  /* Non-seleksi, anti-drag */
  body.noselect, body.noselect * {
    -webkit-user-select: none; -moz-user-select:none; -ms-user-select:none; user-select:none;
  }
</style>
</head>
<body>
<div class="overlay" id="overlay">
  <h1>Mode Ujian</h1>
  <p class="hint">Klik tombol di bawah untuk <b>masuk Fullscreen</b> dan memulai ujian.
    Selama ujian berlangsung: klik kanan, copy/paste, sebagian besar shortcut (Ctrl+U, Ctrl+W, F5, dll) akan diblokir. 
    Berpindah tab/jendela akan tercatat sebagai pelanggaran.</p>
  <button class="btn" id="btnStart">Mulai Ujian (Fullscreen)</button>
  <p class="small">Tip: jika fullscreen tertutup, Anda akan diminta kembali ke fullscreen dan pelanggaran bertambah.</p>
</div>

<header class="wrap">
  <div class="stat">
    <span class="dot" id="dotState"></span>
    <span id="statusText">Belum mulai</span>
  </div>
  <div>
    <span class="badge" id="timer">00:00</span>
    <span class="badge warn" id="violations">Pelanggaran: 0</span>
  </div>
</header>

<main class="wrap">
  <form id="frmUjian" method="post" action="">
    <input type="hidden" name="aksi" value="submit_ujian">
    <div class="grid">
      <div class="card">
        <p class="question">1) Ibu kota Indonesia adalah…</p>
        <label><input type="radio" name="q1" value="a"> Bandung</label><br>
        <label><input type="radio" name="q1" value="b"> Nusantara</label><br>
        <label><input type="radio" name="q1" value="c"> Jakarta</label>
      </div>
      <div class="card">
        <p class="question">2) 2 + 3 × 4 = …</p>
        <label><input type="radio" name="q2" value="a"> 20</label><br>
        <label><input type="radio" name="q2" value="b"> 14</label><br>
        <label><input type="radio" name="q2" value="c"> 24</label>
      </div>
      <!-- Tambah soal lain... -->
    </div>

    <div class="footer">
      <button type="button" class="btn" id="btnFinish">Selesai & Keluar</button>
      <span class="small">Hanya gunakan tombol ini untuk keluar. Menutup tab/jendela dianggap pelanggaran.</span>
    </div>
  </form>
</main>

<script>
(function(){
  const overlay = document.getElementById('overlay');
  const btnStart = document.getElementById('btnStart');
  const btnFinish = document.getElementById('btnFinish');
  const frm = document.getElementById('frmUjian');
  const timerEl = document.getElementById('timer');
  const violEl  = document.getElementById('violations');
  const statusText = document.getElementById('statusText');
  const dotState = document.getElementById('dotState');

  let examRunning = false;
  let startedAt = null;
  let timerInt = null;
  let wakeLock = null;
  let violations = Number(localStorage.getItem('exam_violations') || 0);
  const VIOLATION_LIMIT = 3;

  updateViolations(0);

  function fmtTime(sec){
    const m = Math.floor(sec/60).toString().padStart(2,'0');
    const s = (sec%60).toString().padStart(2,'0');
    return `${m}:${s}`;
  }

  function startTimer(){
    startedAt = Date.now();
    timerInt = setInterval(()=>{
      const elapsed = Math.floor((Date.now()-startedAt)/1000);
      timerEl.textContent = fmtTime(elapsed);
    }, 250);
  }

  function stopTimer(){
    clearInterval(timerInt);
    timerInt = null;
  }

  function setActiveUI(active){
    if (active) {
      dotState.style.background = '#10b981';
      statusText.textContent = 'Mode Ujian Aktif';
      document.body.classList.add('noselect');
    } else {
      dotState.style.background = '#ef4444';
      statusText.textContent = 'Mode Ujian Tidak Aktif';
      document.body.classList.remove('noselect');
    }
  }

  async function requestWakeLock(){
    try {
      if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen');
    } catch(e){}
  }

  async function lockKeys(){
    // Keyboard Lock API: bisa kunci Escape, F1..F12 (tidak semua kombinasi OS)
    try {
      if (navigator.keyboard && navigator.keyboard.lock) {
        await navigator.keyboard.lock(['Escape','F1','F2','F3','F4','F5','F11','F12']);
      }
    } catch(e){}
  }

  function preventContextAndSelect(){
    const prevent = (e)=>{ e.preventDefault(); e.stopPropagation(); return false; };
    document.addEventListener('contextmenu', prevent, {capture:true});
    document.addEventListener('selectstart', prevent, {capture:true});
    document.addEventListener('dragstart', prevent, {capture:true});
    document.addEventListener('copy', prevent, {capture:true});
    document.addEventListener('cut', prevent, {capture:true});
    document.addEventListener('paste', prevent, {capture:true});
  }

  function blockShortcuts(){
    document.addEventListener('keydown', function(e){
      // Kumpulan shortcut umum yang ingin diblok
      const key = e.key.toLowerCase();
      const ctrl = e.ctrlKey || e.metaKey; // macOS cmd
      const shift = e.shiftKey;
      const alt = e.altKey;

      // F keys / navigasi
      const fKeys = ['f1','f2','f3','f4','f5','f11','f12'];
      if (fKeys.includes(key)) { e.preventDefault(); e.stopPropagation(); return; }

      // Ctrl+... (banyak)
      if (ctrl) {
        const ctrlCombos = [
          'u','w','r','p','s','o','l','j','k','h','a','c','x','v','f','g','i'
        ];
        if (ctrlCombos.includes(key)) { e.preventDefault(); e.stopPropagation(); return; }

        // Ctrl+Tab & Ctrl+Shift+Tab (tab switch) — sering diambil alih browser; coba block
        if (key === 'tab') { e.preventDefault(); e.stopPropagation(); return; }
      }

      // Ctrl+Shift+I / J (DevTools)
      if (ctrl && shift && (key==='i' || key==='j' || key==='c')) {
        e.preventDefault(); e.stopPropagation(); return;
      }

      // Alt+Left/Right (navigasi history)
      if (alt && (key==='arrowleft' || key==='arrowright')) {
        e.preventDefault(); e.stopPropagation(); return;
      }
    }, {capture:true});
  }

  function trackVisibility(){
    document.addEventListener('visibilitychange', ()=>{
      if (!examRunning) return;
      if (document.hidden) {
        addViolation('Berpindah tab/jendela terdeteksi.');
      }
    });
    window.addEventListener('blur', ()=>{
      if (!examRunning) return;
      // Window kehilangan fokus (kemungkinan alt+tab)
      addViolation('Fokus jendela hilang.');
    });
    document.addEventListener('fullscreenchange', ()=>{
      if (!examRunning) return;
      if (!document.fullscreenElement) {
        addViolation('Fullscreen ditutup.');
        // Tampilkan overlay minta kembali Fullscreen
        overlay.style.display='flex';
      }
    });
  }

  function addViolation(reason){
    updateViolations(violations + 1, reason);
    if (violations >= VIOLATION_LIMIT) {
      // Auto submit & keluar
      try { document.exitFullscreen?.(); } catch(e){}
      try { navigator.keyboard?.unlock?.(); } catch(e){}
      stopTimer();
      examRunning = false;
      setActiveUI(false);
      alert('Batas pelanggaran terlampaui. Ujian akan dikumpulkan otomatis.');
      frm.submit();
    } else {
      // Peringatan
      alert((reason || 'Pelanggaran') + '\nPelanggaran: ' + violations + ' / ' + VIOLATION_LIMIT);
    }
  }

  function updateViolations(n, reason){
    violations = n;
    localStorage.setItem('exam_violations', String(violations));
    violEl.textContent = 'Pelanggaran: ' + violations + (reason ? ' ('+reason+')' : '');
    violEl.classList.toggle('warn', true);
  }

  async function enterFullscreen(){
    // Wajib dipanggil dari gesture (klik)
    const el = document.documentElement; // full page
    if (el.requestFullscreen) await el.requestFullscreen({navigationUI:'hide'});
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
  }
  async function exitFullscreen(){
    if (document.exitFullscreen) await document.exitFullscreen();
    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
  }

  // —— Lifecyle handlers ——
  btnStart.addEventListener('click', async ()=>{
    try {
      await enterFullscreen();
    } catch(e) {
      alert('Gagal masuk Fullscreen. Harap izinkan Fullscreen pada browser Anda.');
      return;
    }
    overlay.style.display='none';
    examRunning = true;
    setActiveUI(true);
    startTimer();
    requestWakeLock();
    lockKeys();
  });

  btnFinish.addEventListener('click', async ()=>{
    if (!confirm('Kumpulkan jawaban dan keluar dari ujian?')) return;
    try { await exitFullscreen(); } catch(e){}
    try { navigator.keyboard?.unlock?.(); } catch(e){}
    stopTimer();
    examRunning = false;
    setActiveUI(false);
    frm.submit();
  });

  // Lindungi dari accidental close/reload
  window.addEventListener('beforeunload', function (e) {
    if (!examRunning) return;
    e.preventDefault(); e.returnValue = '';
  });

  // Inits
  preventContextAndSelect();
  blockShortcuts();
  trackVisibility();
})();
</script>
</body>
</html>
