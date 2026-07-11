<?php
// ===== login.php — Unified Login UI (bright blue gradient, ikon & caret) =====
// Logo & nama sekolah dinamis dari DB (tabel `sekolah`) via theme_brand.php — mendukung multi-sekolah.
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/theme_brand.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>E-POIN | Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS vendor (sesuaikan path bila perlu) -->
  <link rel="stylesheet" href="assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="assets/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,900">

  <style>
    :root{
      --bg-grad: linear-gradient(135deg,#eaf4ff 0%,#d3eaff 22%,#a8d4ff 48%,#72b5ff 74%,#3a8ef6 100%);
      --card-bg: rgba(255,255,255,.22);
      --card-br: 18px;
      --card-bdr: 1px solid rgba(255,255,255,.28);
      --text: #0f172a;
      --muted: rgba(15,23,42,.7);
      --btn: #2563eb; --btn-hover:#1d4ed8;
      --shadow: 0 24px 70px rgba(0,0,0,.35);
      --glass-blur: saturate(140%) blur(14px);
      --chip-bg: rgba(15,23,42,.55);
      --chip-brd: rgba(255,255,255,.28);
      --chip-text: #ffffff;
      --caret-color: #ffffff;
      --glint: rgba(255,255,255,.70);
      --accent: #3a8ef6;
      --accent-light: #52a8ff;
      --accent-glow: rgba(37,99,235,.35);
    }
    [data-theme="dark"]{
      --bg-grad: linear-gradient(135deg,#0b132b 0%, #1f3a93 55%, #0a2463 100%);
      --card-bg: rgba(17,24,39,.62);
      --card-bdr: 1px solid rgba(255,255,255,.15);
      --text: #e5e7eb; --muted: rgba(229,231,235,.78);
      --btn:#3b82f6; --btn-hover:#2563eb;
      --chip-bg: rgba(255,255,255,.08);
      --chip-brd: rgba(255,255,255,.12);
      --chip-text: #e5e7eb; --caret-color: #e5e7eb;
      --glint: rgba(255,255,255,.55);
      --accent: #3b82f6;
      --accent-light: #60a5fa;
      --accent-glow: rgba(59,130,246,.35);
    }

    html,body{ height:100%; }
    body{
      display:flex; align-items:center; justify-content:center;
      background: var(--bg-grad); background-attachment: fixed;
      color: var(--text);
      font-family: "Source Sans Pro","Helvetica Neue",Helvetica,Arial,sans-serif;
      padding:18px;
    }

    .login-wrap{ width:100%; max-width:520px; }
    .login-card{
      position:relative;
      padding:28px 26px 22px;
      border-radius: var(--card-br);
      background: var(--card-bg);
      border: var(--card-bdr);
      box-shadow: var(--shadow);
      -webkit-backdrop-filter: var(--glass-blur);
      backdrop-filter: var(--glass-blur);
      overflow:hidden;
    }

    .brand{ display:flex; flex-direction:column; align-items:center; gap:12px; margin-bottom:8px; }
    .animated-logo{
      width:110px; height:110px; object-fit:contain;
      animation: pulseLogo 3s infinite ease-in-out;
      filter: drop-shadow(0 6px 18px rgba(0,0,0,.25));
    }
    @keyframes pulseLogo{ 0%{transform:scale(1)} 50%{transform:scale(1.06)} 100%{transform:scale(1)} }

    .brand-title{
      text-align:center; font-weight:900; text-transform:uppercase; letter-spacing:.4px;
      line-height:1.2; margin:0; color:var(--text);
      text-shadow:0 1px 2px rgba(0,0,0,.12); font-size:26px;
      position:relative; overflow:hidden;
    }
    @media(max-width:480px){ .brand-title{ font-size:6vw; } }

    .brand-title .title-line{ display:block; opacity:0; transform:translateY(8px); animation:titleLineFade .72s ease-out forwards; }
    .brand-title .title-line:nth-child(2){ animation-delay=.15s; }
    @keyframes titleLineFade{ to{ opacity:1; transform:translateY(0); letter-spacing:.4px; } }

    .brand-title::after{
      content:''; position:absolute; top:0; bottom:0; left:-35%; width:28%;
      background:linear-gradient(75deg, rgba(255,255,255,0) 0%, var(--glint) 50%, rgba(255,255,255,0) 100%);
      transform:skewX(-18deg); animation:titleGlint 2.2s ease-out 1s 1; pointer-events:none;
    }
    @keyframes titleGlint{ 0%{left:-35%;opacity:0} 10%{opacity:1} 100%{left:130%;opacity:0} }

    /* ====== SUBTITLE ====== */
    .subtitle{
      text-align:center; margin:4px 0 16px; opacity:.9;
      white-space:nowrap; overflow:hidden; position:relative;
      will-change: width;
      font-size: clamp(12.5px, 2.9vw, 12.5px);
      line-height: 1.35;
    }
    .subtitle.typing-caret-glow{
      animation: typingSteps var(--typing-duration,3s) steps(var(--typing-chars,110)) both;
    }
    .subtitle.typing-caret-glow::after{
      content:""; position:absolute; top:50%; right:-.06em; transform:translateY(-50%);
      width:.12em; height:1.1em; border-radius:2px;
      background: linear-gradient(180deg, var(--accent-light) 0%, var(--accent) 100%);
      box-shadow: 0 0 8px var(--accent-glow), 0 0 16px var(--accent-glow);
      animation: caretGlowBlink .9s steps(1) infinite;
    }
    @keyframes typingSteps{ from{width:0} to{width:100%} }
    @keyframes caretGlowBlink{ 50%{ opacity:.25 } }

    .subtitle.typing-smooth{
      border-right:.15em solid rgba(0,0,0,.18);
      animation: typingSmooth var(--typing-duration,2.4s) cubic-bezier(.22,.7,.23,1) both,
                 blink .9s step-end infinite;
    }
    @keyframes typingSmooth{ from{width:0} to{width:100%} }
    @keyframes blink{ 50%{ border-color:transparent } }
    [data-theme="dark"] .subtitle.typing-smooth{ border-right-color: rgba(229,231,235,.35); }

    /* ====== Mode 2 baris (hp) ====== */
    .subtitle.multi{ white-space: normal; overflow: visible; }
    .subtitle.multi .subtitle-line{
      display:block; overflow:hidden; white-space:nowrap; width:0ch; will-change: width;
      margin:0 auto; max-width: 100%; position: relative;
    }
    @keyframes typeChars { from{ width:0ch } to{ width: calc(var(--chars,0) * 1ch) } }
    .subtitle.multi .subtitle-line.typing-caret-glow{
      animation: typeChars var(--dur,2s) steps(var(--chars,1)) both;
    }
    .subtitle.multi .subtitle-line.typing-caret-glow::after{
      content:""; position:absolute; top:50%; right:-.06em; transform:translateY(-50%);
      width:.12em; height:1.1em; border-radius:2px;
      background: linear-gradient(180deg, var(--accent-light) 0%, var(--accent) 100%);
      box-shadow: 0 0 8px var(--accent-glow), 0 0 16px var(--accent-glow);
      animation: caretGlowBlink .9s steps(1) infinite;
    }
    .subtitle.multi .subtitle-line.typing-smooth{
      border-right:.15em solid rgba(0,0,0,.18);
      animation: typeChars var(--dur,2s) steps(var(--chars,1)) both, blink .9s step-end infinite;
    }
    [data-theme="dark"] .subtitle.multi .subtitle-line.typing-smooth{
      border-right-color: rgba(229,231,235,.35);
    }
    .subtitle.multi .subtitle-line.ended::after{ display:none !important; }
    .subtitle.multi .subtitle-line.typing-smooth.ended{ border-right-color: transparent !important; }

    .form-control, .btn{ border-radius:10px }
    .form-group label{ font-weight:600; opacity:.95 }
    .input-icon-right{ position:relative; }
    .input-icon-right .toggle-eye{ position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; opacity:.9; }

    /* ====== Pemilihan role ====== */
    .select-role{
      position:relative; display:flex; align-items:center; gap:10px;
      padding:0 12px;
      background:var(--chip-bg); border-radius:10px; border:1px solid var(--chip-brd);
      min-height:48px; cursor:pointer;
      transition: box-shadow .25s ease, transform .25s ease, background .25s ease;
    }
    .select-role:focus-within{
      box-shadow: 0 0 0 3px rgba(37,99,235,.30), 0 10px 28px rgba(0,0,0,.18);
    }
    .select-role.is-selected{
      box-shadow: 0 8px 28px rgba(37,99,235,.28), inset 0 0 0 1px rgba(255,255,255,.2);
      transform: translateY(-1px);
    }
    .select-role .role-ico{
      font-size:18px; width:22px; text-align:center; color:#fff; flex:0 0 22px;
      filter: drop-shadow(0 1px 0 rgba(0,0,0,.2));
      transition: transform .4s cubic-bezier(.2,.7,.2,1.1);
    }
    .select-role.bounce .role-ico{ animation: rolePop .55s ease; }
    @keyframes rolePop{ 0%{transform:scale(1)} 40%{transform:scale(1.18)} 100%{transform:scale(1)} }

    .select-role select{
      -webkit-appearance:none; -moz-appearance:none; appearance:none;
      border:none; background:transparent; box-shadow:none;
      color:var(--chip-text); font-weight:700; width:100%;
      padding:12px 28px 12px 0;
      height:48px; line-height:24px; letter-spacing:.2px;
      flex:1 1 auto; cursor:pointer;
    }
    .select-role .select-caret{
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      color:var(--caret-color); pointer-events:none; font-size:14px; opacity:.9;
    }
    .select-role select option{ color:#0f172a; background:#ffffff; }

    #preRoleText.changed{ animation: fadeSlide .45s ease; }
    @keyframes fadeSlide{ from{opacity:0; transform:translateY(-4px)} to{opacity:1; transform:translateY(0)} }

    .top-actions{ position:absolute; right:12px; top:10px; display:flex; gap:6px; }
    .icon-btn{
      width:36px; height:36px; border-radius:999px; border:none; display:flex; align-items:center; justify-content:center;
      background:rgba(255,255,255,.65); cursor:pointer;
    }
    [data-theme="dark"] .icon-btn{ background:rgba(255,255,255,.12) }
    .icon-btn:hover{ filter:brightness(1.06) }

    .btn-primary{ background:var(--btn); border-color:var(--btn); font-weight:800; letter-spacing:.2px }
    .btn-primary:hover{ background:var(--btn-hover); border-color:var(--btn-hover) }

    .help{ font-size:11px; color:var(--muted); margin-top:6px }
    .msg{ margin:6px 0 10px; color:#e53935; min-height:1em; font-size:12px; }
    .identity-label{ display:block; font-weight:600; opacity:.95; margin:10px 0 6px; }

    .footer-links{ margin-top:12px; display:flex; justify-content:space-between; align-items:center; gap:10px; }

    /* ================= POLISH: tombol footer ================= */
    /* (1) Beranda TARTIB → ikon saja, elegan */
    .home-btn{
      display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:12px; text-decoration:none; font-weight:800;
      background:#0ea5e9; color:white; box-shadow:0 12px 28px rgba(14,165,233,.35);
    }
    .home-btn:hover{ text-decoration:none; filter:brightness(1.05) }
    .home-btn .fa{ font-size:18px }

    .home-btn.icon-only{
      width:44px; height:44px; padding:0; border-radius:14px;
      display:inline-flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg,#06b6d4,#3b82f6);
      box-shadow: 0 12px 28px rgba(59,130,246,.35), inset 0 0 0 1px rgba(255,255,255,.18);
      transition: transform .18s ease, box-shadow .2s ease, filter .2s ease;
      position:relative; overflow:hidden;
    }
    .home-btn.icon-only:hover{ transform: translateY(-1px); filter:brightness(1.04) }
    .home-btn.icon-only:active{ transform: translateY(0) scale(.98) }
    .home-btn.icon-only .fa{ font-size:18px }

    /* (2) Tentang Aplikasi → interaktif, elegan, berwarna */

    /* Cara cepat: ubah font-size seluruh isi tombol */
.about-btn{
  font-size: 8px;   /* ganti angka sesuai kebutuhan, mis. 15px/16px */
}

/* Recommended: khusus teks "Tentang Aplikasi" agar menimpa Bootstrap .label */
.about-btn .label{
  font-size: 8px;        /* ubah sesuai selera */
  font-weight: 800;       /* samakan dengan tombol (opsional) */
  background: transparent;/* netralisir gaya label Bootstrap */
  padding: 0;             /* hilangkan padding bawaan label */
  line-height: 1.1;       /* rapikan vertikal (opsional) */
}

    .about-btn{
    --abg1:#0ea5e9; --abg2:#3b82f6; --abg3:#6366f1;
    background: linear-gradient(135deg,var(--abg1),var(--abg2) 55%,var(--abg3));
    color:#fff; border:none; border-radius:12px;
    padding:9px 14px; font-weight:800; letter-spacing:.2px;
    box-shadow: 0 12px 28px rgba(37,99,235,.35), inset 0 0 0 1px rgba(255,255,255,.18);
    transition: transform .18s ease, box-shadow .2s ease, filter .2s ease;
    display:inline-flex; align-items:center; gap:8px;
    -webkit-tap-highlight-color: transparent;

    /* ⬇️ Kunci shimmer & efek di dalam tombol */
    position: relative;
    overflow: hidden;          /* potong efek agar tidak melewati tombol */
    isolation: isolate;        /* pastikan blending/efek tidak ‘bocor’ ke luar */
    backface-visibility: hidden;
    will-change: transform;
    }
    .about-btn .fa{ font-size:14px }
    .about-btn:hover{
    transform: translateY(-1px);
    filter:brightness(1.03);
    box-shadow: 0 16px 32px rgba(37,99,235,.40);
    }
    .about-btn:active{ transform: translateY(0) scale(.98) }

    /* dynamic glow mengikuti kursor — tetap di dalam tombol */
    .about-btn .glow{
    position:absolute; inset:0;           /* semula -2px → 0 agar tidak melebihi */
    pointer-events:none; opacity:0;
    background: radial-gradient(140px 60px at var(--mx,50%) var(--my,50%), rgba(255,255,255,.35), transparent 45%);
    transition: opacity .18s ease;
    border-radius: inherit;               /* ikuti sudut tombol */
    }
    .about-btn:hover .glow{ opacity:1 }

    /* shimmer garis miring halus — dipotong oleh overflow tombol */
    .about-btn::after{
    content:""; position:absolute; inset:0; transform:translateX(-130%);
    background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.45) 45%, transparent 60%);
    transition: transform 900ms cubic-bezier(.22,.7,.23,1);
    border-radius: inherit;               /* ikuti sudut tombol */
    pointer-events:none;
    }
    .about-btn:hover::after{ transform:translateX(130%) }

    /* efek ripple klik — tetap di dalam tombol */
    .about-btn .ripple{
    position:absolute; width:8px; height:8px; border-radius:999px; pointer-events:none;
    transform: scale(0); background: rgba(255,255,255,.55); opacity:.8;
    animation: ripple .55s ease-out forwards;
    clip-path: inset(0 round 12px);       /* pastikan tak melewati radius */
    }
    @keyframes ripple{ to{ transform:scale(16); opacity:0 } }

    .note{ font-size:12px; color:var(--muted) }

    /* Toast */
    .toast{
      position:fixed; right:16px; bottom:16px; padding:10px 14px; background:#1e88e5; color:#fff;
      border-radius:6px; box-shadow:0 6px 20px rgba(0,0,0,.2); z-index:2000;
    }

    /* Pengaman z-index modal di atas backdrop */
    .modal{ z-index: 2050; }
    .modal-backdrop{ z-index: 2000; }

    /* === High-contrast modal (all themes) === */
    .modal-content{
      background:#ffffff; color:#0f172a; box-shadow:0 20px 60px rgba(0,0,0,.35);
    }
    .modal-content p, .modal-content li, .modal-content h4, .modal-content h5, .modal-content a{ color:#0f172a; }
    .modal-content hr{ border-top:1px solid rgba(15,23,42,.12); }
    .modal-header.bg-primary{ background:#1e88e5; color:#fff; }
    .modal-header .close{ color:#fff; opacity:1; }

    button:disabled, input:disabled{ opacity:.6; cursor:not-allowed; }

    /* ====== POLISH: Tentang Aplikasi (modal) ====== */
    /* (Catatan: selector di bawah tetap dibiarkan—tidak mengganggu komponen lain) */
    #tentangEPOIN .about-intro{ font-size:14.5px; color:#334155; margin-bottom:10px }
    #tentangEPOIN .about-chips{ display:flex; flex-wrap:wrap; gap:8px; margin:6px 0 14px }
    #tentangEPOIN .chip{
      padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;
      border:1px solid rgba(15,23,42,.12); background:#f8fafc; color:#0f172a;
    }
    #tentangEPOIN .about-list{ list-style:none; padding-left:0; margin:4px 0 12px }
    #tentangEPOIN .about-list li{
      display:flex; gap:10px; align-items:flex-start; margin:8px 0;
      opacity:0; transform:translateY(6px);
      animation: aboutFade .55s ease forwards;
    }
    #tentangEPOIN .about-list li i{ color:#22c55e; margin-top:2px }
    @keyframes aboutFade{ to{opacity:1; transform:translateY(0)} }
    #tentangEPOIN .about-list li:nth-child(1){ animation-delay:.05s }
    #tentangEPOIN .about-list li:nth-child(2){ animation-delay;.15s }
    #tentangEPOIN .about-list li:nth-child(3){ animation-delay:.25s }
    #tentangEPOIN .about-list li:nth-child(4){ animation-delay:.35s }

    #tentangEPOIN .btn-whatsapp{
      background:#25D366; border-color:#25D366; color:#fff; font-weight:800;
      display:inline-flex; align-items:center; gap:8px;
      box-shadow:0 10px 24px rgba(37,211,102,.25);
    }
    #tentangEPOIN .btn-whatsapp:hover{ filter:brightness(1.05) }
    #tentangEPOIN .btn-whatsapp .fa{ font-size:18px }
    #tentangEPOIN .trustline{ font-size:12px; color:#64748b; margin-top:8px }

    /* ====== Shimmer tombol login ====== */
    .btn-shimmer{ position:relative; overflow:hidden; }
    .btn-shimmer.shimmering::after{
      content:''; position:absolute; inset:0;
      transform: translateX(-150%);
      background: linear-gradient(110deg, transparent 0%, rgba(255,255,255,.35) 45%, transparent 60%);
      animation: shimmerMove 2s linear infinite;
      pointer-events:none;
    }
    @keyframes shimmerMove { 0%{ transform: translateX(-150%);} 100%{ transform: translateX(150%);} }

    /* ====== Hero kecil modal ====== */
    .version-hero{
      position:relative; background: linear-gradient(135deg,#0B57D0, #3BA3FF);
      color:#fff; padding:16px 18px; border-top-left-radius:6px; border-top-right-radius:6px; overflow:hidden;
    }
    .version-hero:after{
      content:""; position:absolute; inset:-40% -40% auto auto; width:220px; height:220px; border-radius:50%;
      background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.35), rgba(255,255,255,0)); filter: blur(6px);
      animation: pulseGlow 4s ease-in-out infinite;
    }
    @keyframes pulseGlow{ 0%,100%{ transform:scale(1); opacity:.7;} 50%{ transform:scale(1.08); opacity:1;} }
    .version-badge{ display:inline-block; padding:4px 10px; border-radius:999px; background: rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); font-weight:600; letter-spacing:.2px; }
    .callout{ border-left:4px solid #0B57D0; background:#F6F9FF; padding:10px 12px; border-radius:6px; color:#0f172a; }

    /* ====== (POIN 1) Paksa judul hero putih saja ====== */
    #tentangEPOIN .eps-title-white{ color:#fff !important; text-shadow:0 1px 1px rgba(0,0,0,.25); }

    /* ====== Smooth theme transitions ====== */
    body{ transition: background .5s ease, color .3s ease; }
    .login-card{ transition: background .4s ease, border-color .3s ease, box-shadow .4s ease; }
    .toast{ background: var(--btn); }

    /* ====== Theme Picker UI ====== */
    .theme-picker-wrap{ position:relative; display:inline-flex; }
    .theme-palette{
      display:none;
      position:absolute; right:-8px; top:50px;
      background: rgba(255,255,255,.95);
      -webkit-backdrop-filter: saturate(180%) blur(20px);
      backdrop-filter: saturate(180%) blur(20px);
      border-radius:20px; padding:12px 14px 10px;
      box-shadow: 0 12px 40px rgba(0,0,0,.22), 0 0 0 1px rgba(0,0,0,.06);
      z-index:200; flex-direction:column; gap:10px; min-width:160px;
      animation: pickerFadeIn .22s cubic-bezier(.2,.7,.2,1.1);
    }
    [data-theme="dark"] .theme-palette{
      background: rgba(15,23,42,.95);
      box-shadow: 0 12px 40px rgba(0,0,0,.5), 0 0 0 1px rgba(255,255,255,.1);
    }
    .theme-palette.show{ display:flex; }
    .palette-title{
      font-size:10px; font-weight:700; letter-spacing:.8px; text-transform:uppercase;
      color:var(--muted); margin-bottom:2px; text-align:center;
    }
    @keyframes pickerFadeIn{ from{opacity:0;transform:translateY(-8px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }

    .palette-row{ display:flex; flex-direction:row; gap:10px; align-items:center; justify-content:center; }

    /* ==== FIX UTAMA: span butuh display:inline-block untuk width/height ==== */
    .theme-dot{
      display:inline-block;
      width:34px; height:34px; border-radius:50%;
      border:3px solid transparent; cursor:pointer; flex-shrink:0;
      transition: transform .22s cubic-bezier(.2,.7,.2,1.1), border-color .2s ease, box-shadow .2s ease;
      position:relative;
      box-shadow: 0 4px 12px rgba(0,0,0,.18);
    }
    .theme-dot:hover{ transform:scale(1.22) translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.25); }
    .theme-dot.active{
      border-color: #fff;
      box-shadow: 0 0 0 2px var(--btn), 0 6px 16px rgba(0,0,0,.3);
      transform: scale(1.1);
    }
    .theme-dot::after{
      content:'\2713';
      position:absolute; top:50%; left:50%;
      transform:translate(-50%,-50%);
      color:#fff; font-size:14px; font-weight:900; opacity:0;
      transition: opacity .2s;
      text-shadow: 0 1px 3px rgba(0,0,0,.5);
      line-height:1;
    }
    .theme-dot.active::after{ opacity:1; }

    .theme-dot[data-color="blue"]  { background: linear-gradient(135deg, #60a5fa 0%, #2563eb 60%, #1d4ed8 100%); }
    .theme-dot[data-color="purple"]{ background: linear-gradient(135deg, #c084fc 0%, #9333ea 50%, #7c3aed 100%); }
    .theme-dot[data-color="emerald"]{ background: linear-gradient(135deg, #34d399 0%, #059669 50%, #047857 100%); }
    .theme-dot[data-color="amber"] { background: linear-gradient(135deg, #fde68a 0%, #f59e0b 50%, #d97706 100%); }
    .theme-dot[data-color="crimson"]{ background: linear-gradient(135deg, #f9a8d4 0%, #e11d48 50%, #9f1239 100%); }

    .dot-label{
      font-size:9px; font-weight:700; text-align:center; margin-top:4px;
      color:var(--muted); letter-spacing:.3px; white-space:nowrap;
    }
    .dot-wrap{ display:flex; flex-direction:column; align-items:center; gap:2px; }


    /* ====== Color Theme: Royal Purple ====== */
    [data-color="purple"]{
      --bg-grad: linear-gradient(135deg,#f3e8ff 0%,#e9d5ff 22%,#c4b5fd 48%,#a78bfa 74%,#8b5cf6 100%);
      --btn:#7c3aed; --btn-hover:#6d28d9;
      --accent:#8b5cf6; --accent-light:#a78bfa; --accent-glow:rgba(124,58,237,.35);
    }
    [data-color="purple"][data-theme="dark"]{
      --bg-grad: linear-gradient(135deg,#1a0533 0%, #2d0a5e 40%, #581c87 100%);
      --card-bg: rgba(26,5,51,.62);
      --btn:#a78bfa; --btn-hover:#8b5cf6;
      --accent:#a78bfa; --accent-light:#c4b5fd; --accent-glow:rgba(167,139,250,.35);
    }
    [data-color="purple"] .about-btn{ --abg1:#a855f7; --abg2:#7c3aed; --abg3:#6d28d9; }
    [data-color="purple"] .home-btn.icon-only{ background:linear-gradient(135deg,#a855f7,#7c3aed); box-shadow:0 12px 28px rgba(124,58,237,.35), inset 0 0 0 1px rgba(255,255,255,.18); }
    [data-color="purple"] .select-role:focus-within{ box-shadow:0 0 0 3px rgba(124,58,237,.30), 0 10px 28px rgba(0,0,0,.18); }
    [data-color="purple"] .select-role.is-selected{ box-shadow:0 8px 28px rgba(124,58,237,.28), inset 0 0 0 1px rgba(255,255,255,.2); }

    /* ====== Color Theme: Emerald Forest ====== */
    [data-color="emerald"]{
      --bg-grad: linear-gradient(135deg,#ecfdf5 0%,#a7f3d0 22%,#6ee7b7 48%,#34d399 74%,#10b981 100%);
      --btn:#059669; --btn-hover:#047857;
      --accent:#10b981; --accent-light:#34d399; --accent-glow:rgba(5,150,105,.35);
    }
    [data-color="emerald"][data-theme="dark"]{
      --bg-grad: linear-gradient(135deg,#022c22 0%, #064e3b 45%, #065f46 100%);
      --card-bg: rgba(2,44,34,.62);
      --btn:#34d399; --btn-hover:#10b981;
      --accent:#34d399; --accent-light:#6ee7b7; --accent-glow:rgba(52,211,153,.35);
    }
    [data-color="emerald"] .about-btn{ --abg1:#10b981; --abg2:#059669; --abg3:#047857; }
    [data-color="emerald"] .home-btn.icon-only{ background:linear-gradient(135deg,#34d399,#059669); box-shadow:0 12px 28px rgba(5,150,105,.35), inset 0 0 0 1px rgba(255,255,255,.18); }
    [data-color="emerald"] .select-role:focus-within{ box-shadow:0 0 0 3px rgba(5,150,105,.30), 0 10px 28px rgba(0,0,0,.18); }
    [data-color="emerald"] .select-role.is-selected{ box-shadow:0 8px 28px rgba(5,150,105,.28), inset 0 0 0 1px rgba(255,255,255,.2); }

    /* ====== Color Theme: Sunset Amber ====== */
    [data-color="amber"]{
      --bg-grad: linear-gradient(135deg,#fffbeb 0%,#fde68a 22%,#fcd34d 48%,#fbbf24 74%,#f59e0b 100%);
      --btn:#d97706; --btn-hover:#b45309;
      --accent:#f59e0b; --accent-light:#fbbf24; --accent-glow:rgba(217,119,6,.35);
      --text:#451a03; --muted:rgba(69,26,3,.7);
    }
    [data-color="amber"][data-theme="dark"]{
      --bg-grad: linear-gradient(135deg,#1c1105 0%, #451a03 45%, #78350f 100%);
      --card-bg: rgba(28,17,5,.62);
      --btn:#fbbf24; --btn-hover:#f59e0b;
      --accent:#fbbf24; --accent-light:#fde68a; --accent-glow:rgba(251,191,36,.35);
      --text:#fef3c7; --muted:rgba(254,243,199,.78);
    }
    [data-color="amber"] .about-btn{ --abg1:#f59e0b; --abg2:#d97706; --abg3:#b45309; }
    [data-color="amber"] .home-btn.icon-only{ background:linear-gradient(135deg,#fbbf24,#d97706); box-shadow:0 12px 28px rgba(217,119,6,.35), inset 0 0 0 1px rgba(255,255,255,.18); }
    [data-color="amber"] .select-role:focus-within{ box-shadow:0 0 0 3px rgba(217,119,6,.30), 0 10px 28px rgba(0,0,0,.18); }
    [data-color="amber"] .select-role.is-selected{ box-shadow:0 8px 28px rgba(217,119,6,.28), inset 0 0 0 1px rgba(255,255,255,.2); }

    /* ====== Color Theme: Crimson Flame (Ganti Slate) ====== */
    [data-color="crimson"]{
      --bg-grad: linear-gradient(135deg,#fff1f2 0%,#fecdd3 20%,#fda4af 42%,#fb7185 65%,#f43f5e 82%,#e11d48 100%);
      --btn:#e11d48; --btn-hover:#be123c;
      --accent:#e11d48; --accent-light:#fb7185; --accent-glow:rgba(225,29,72,.38);
    }
    [data-color="crimson"][data-theme="dark"]{
      --bg-grad: linear-gradient(135deg,#1a0010 0%, #3b0022 30%, #6b0031 60%, #9f1239 100%);
      --card-bg: rgba(26,0,16,.65);
      --btn:#fb7185; --btn-hover:#e11d48;
      --accent:#fb7185; --accent-light:#fda4af; --accent-glow:rgba(251,113,133,.38);
    }
    [data-color="crimson"] .about-btn{ --abg1:#fb7185; --abg2:#e11d48; --abg3:#be123c; }
    [data-color="crimson"] .home-btn.icon-only{ background:linear-gradient(135deg,#fb7185,#e11d48); box-shadow:0 12px 28px rgba(225,29,72,.38), inset 0 0 0 1px rgba(255,255,255,.18); }
    [data-color="crimson"] .select-role:focus-within{ box-shadow:0 0 0 3px rgba(225,29,72,.30), 0 10px 28px rgba(0,0,0,.18); }
    [data-color="crimson"] .select-role.is-selected{ box-shadow:0 8px 28px rgba(225,29,72,.28), inset 0 0 0 1px rgba(255,255,255,.2); }
  </style>
</head>
<body>

<div class="login-wrap">
  <div class="login-card animate__animated animate__fadeInDown">

    <div class="top-actions">
      <div class="theme-picker-wrap">
        <button id="colorToggle" class="icon-btn" title="Pilih tema warna"><span>🎨</span></button>
        <div id="themePalette" class="theme-palette">
          <div class="palette-title">Pilih Tema</div>
          <div class="palette-row">
            <div class="dot-wrap">
              <span class="theme-dot active" data-color="blue" title="Ocean Blue"></span>
              <div class="dot-label">Blue</div>
            </div>
            <div class="dot-wrap">
              <span class="theme-dot" data-color="purple" title="Royal Purple"></span>
              <div class="dot-label">Purple</div>
            </div>
            <div class="dot-wrap">
              <span class="theme-dot" data-color="emerald" title="Emerald Forest"></span>
              <div class="dot-label">Emerald</div>
            </div>
            <div class="dot-wrap">
              <span class="theme-dot" data-color="amber" title="Sunset Amber"></span>
              <div class="dot-label">Amber</div>
            </div>
            <div class="dot-wrap">
              <span class="theme-dot" data-color="crimson" title="Crimson Flame"></span>
              <div class="dot-label">Crimson</div>
            </div>
          </div>
        </div>
      </div>
      <button id="themeToggle" class="icon-btn" title="Toggle tema"><span id="themeIcon">🌙</span></button>
    </div>

    <div class="brand">
      <img src="<?php echo htmlspecialchars($THEME_BRAND['logo'] ?? 'gambar/sistem/logonesagun.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="animated-logo">
      <h2 class="brand-title animate__animated animate__fadeInDown" id="brandTitle">
        <span class="title-line">E-POIN Suite</span>
        <span class="title-line"><?php echo htmlspecialchars(strtoupper((string)($THEME_BRAND['subtitle'] ?? 'Sekolah')), ENT_QUOTES, 'UTF-8'); ?></span>
      </h2>
    </div>

    <!-- Subtitle -->
    <div class="subtitle typing-caret-glow" style="--typing-duration:3s; --typing-chars:120;">
      Suite terpadu untuk Disiplin, Prestasi, Absensi, dan CBT - administrasi jauh lebih ringan
    </div>

    <!-- alert -->
    <?php if(isset($_GET['alert'])): ?>
      <?php if($_GET['alert']=="gagal"): ?><div class="alert alert-danger">Login gagal. Periksa kembali username, kata sandi, dan peran login Anda.</div>
      <?php elseif($_GET['alert']=="logout"): ?><div class="alert alert-success">Anda telah logout.</div>
      <?php elseif($_GET['alert']=="belum_login"): ?><div class="alert alert-warning">Silakan login terlebih dahulu.</div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- FORM -->
    <form id="loginForm" method="POST" action="periksa_unified.php" class="animate__animated animate__fadeInUp">
      <div class="form-group">
        <label for="role">Login sebagai <span style="color:#e53935">*</span> (Wajib dipilih)</label>

        <!-- Role selector -->
        <div class="select-role" tabindex="0" aria-haspopup="listbox" aria-label="Pilih peran login">
          <span id="roleIcon" class="role-ico" aria-hidden="true">👤</span>
          <select id="role" name="role" required aria-describedby="roleMsg preRoleText" aria-label="Pilih peran untuk login">
            <option value="" selected disabled>— Pilih peran Anda —</option>
            <option value="siswa">Siswa</option>
            <option value="guru">Guru</option>
            <option value="tas">Staf TU/TAS</option>
            <option value="piket">Guru Piket</option>
            <option value="sekretaris">Pembimbing Eskul / Sekretaris</option>
            <option value="admin">Admin</option>
          </select>
          <span class="select-caret" aria-hidden="true">▾</span>
        </div>
        <div class="help" id="preRoleText" style="margin-bottom:6px" aria-live="polite">Pilih peran Anda terlebih dahulu.</div>
        <div id="roleMsg" class="msg" aria-live="polite"></div>
      </div>

      <!-- SISWA -->
      <div id="siswaFields" style="display:none">
        <div class="form-group has-feedback">
          <input type="number" class="form-control" name="nis" id="nis" placeholder="NIS" autocomplete="username" disabled>
          <span class="glyphicon glyphicon-user form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback input-icon-right">
          <input type="password" class="form-control" name="password_siswa" id="password_siswa" placeholder="Password" disabled>
          <i class="fa fa-eye-slash toggle-eye" data-target="#password_siswa" title="Lihat/sembunyikan sandi"></i>
        </div>
      </div>

      <!-- USER (Guru/TAS/Admin) -->
      <div id="userFields" style="display:none">
        <div class="form-group has-feedback">
          <input type="text" class="form-control" name="username" id="username" placeholder="Username" autocomplete="username" disabled>
          <span class="glyphicon glyphicon-user form-control-feedback"></span>
        </div>
        <div class="form-group has-feedback input-icon-right">
          <input type="password" class="form-control" name="password_user" id="password_user" placeholder="Password" autocomplete="current-password" disabled>
          <i class="fa fa-eye-slash toggle-eye" data-target="#password_user" title="Lihat/sembunyikan sandi"></i>
        </div>
      </div>

      <button id="loginBtn" type="submit" class="btn btn-primary btn-block btn-shimmer" disabled>LOGIN</button>
      <p class="help text-center">Tekan <b>Enter</b> untuk masuk. Pastikan memilih peran yang tepat.</p>

      <div class="footer-links">
        <!-- ELEGAN: ikon-only Beranda TARTIB -->
        <a class="home-btn icon-only" href="https://smpn1gunungtanjung.sch.id/tartib" target="_self" title="Beranda TARTIB">
          <i class="fa fa-home"></i>
        </a>
        <!-- INTERAKTIF: Tentang Aplikasi (memanggil modal include) -->
        <button type="button" class="btn btn-xs about-btn" data-toggle="modal" data-target="#epoinAboutModal" title="Tentang aplikasi">
         
          <span class="label">Tentang Aplikasi</span>
          <span class="glow" aria-hidden="true"></span>
        </button>
      </div>
    </form>

    <div id="toast" class="toast" hidden role="status" aria-live="polite"></div>

  </div>
</div>

<!-- ===================== REVISI INTI: gunakan include modal bersama ===================== -->
<?php include __DIR__.'/includes/modal_tentang_epoin.php'; ?>
<!-- =================================================================== -->

<!-- JS -->
<script src="assets/bower_components/jquery/dist/jquery.min.js"></script>
<script src="assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script>
  // Pastikan modal berada di <body> (ganti ke #epoinAboutModal)
  $('#epoinAboutModal').appendTo('body');

  /* === Interaksi mikro untuk tombol Tentang (glow & ripple) === */
  (function aboutButtonFX(){
    var btn = document.querySelector('.about-btn');
    if(!btn) return;
    btn.addEventListener('mousemove', function(e){
      var r = btn.getBoundingClientRect();
      btn.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      btn.style.setProperty('--my', (e.clientY - r.top)  + 'px');
    });
    btn.addEventListener('click', function(e){
      var r = btn.getBoundingClientRect();
      var s = Math.max(r.width, r.height);
      var ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.width = ripple.style.height = s + 'px';
      ripple.style.left = (e.clientX - r.left - s/2) + 'px';
      ripple.style.top  = (e.clientY - r.top  - s/2) + 'px';
      btn.appendChild(ripple);
      setTimeout(function(){ ripple.remove(); }, 600);
    }, {passive:true});
  })();

  function setSubtitleAnimByTheme(themeMode){
    const el = document.querySelector('.subtitle');
    if(!el) return;

    if (el.classList.contains('multi')){
      const lines = el.querySelectorAll('.subtitle-line');
      lines.forEach(line=>{
        line.classList.remove('typing-caret-glow','typing-smooth');
        if(themeMode === 'dark'){ line.classList.add('typing-smooth'); }
        else{ line.classList.add('typing-caret-glow'); }
      });
      return;
    }

    el.style.animation = 'none';
    void el.offsetWidth;
    el.classList.remove('typing-caret-glow','typing-smooth');
    if(themeMode === 'dark'){
      el.classList.add('typing-smooth');
      el.style.setProperty('--typing-duration','2.6s');
    }else{
      el.classList.add('typing-caret-glow');
      el.style.setProperty('--typing-duration','3s');
      el.style.setProperty('--typing-chars','120');
    }
    requestAnimationFrame(()=>{ el.style.animation = ''; });
  }

  // ===== Init Theme (Dark/Light + Color) =====
  (function initTheme(){
    const savedTheme = localStorage.getItem('theme') || 'light';
    const savedColor = localStorage.getItem('epoin_color') || 'blue';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.documentElement.setAttribute('data-color', savedColor);
    document.getElementById('themeIcon').textContent = (savedTheme==='dark') ? '☀️' : '🌙';
    setSubtitleAnimByTheme(savedTheme);
    document.querySelectorAll('.theme-dot').forEach(function(d){
      d.classList.toggle('active', d.dataset.color === savedColor);
    });
  })();

  // Dark/Light toggle
  document.getElementById('themeToggle').addEventListener('click', function(){
    const cur = document.documentElement.getAttribute('data-theme') || 'light';
    const next = (cur==='dark') ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('theme', next);
    document.getElementById('themeIcon').textContent = (next==='dark') ? '☀️' : '🌙';
    setSubtitleAnimByTheme(next);
  });

  // ===== Color Palette Picker =====
  document.getElementById('colorToggle').addEventListener('click', function(e){
    e.stopPropagation();
    document.getElementById('themePalette').classList.toggle('show');
  });
  document.querySelectorAll('.theme-dot').forEach(function(dot){
    dot.addEventListener('click', function(){
      var color = this.dataset.color;
      document.documentElement.setAttribute('data-color', color);
      localStorage.setItem('epoin_color', color);
      document.querySelectorAll('.theme-dot').forEach(function(d){ d.classList.remove('active'); });
      this.classList.add('active');
      document.getElementById('themePalette').classList.remove('show');
    });
  });
  document.addEventListener('click', function(e){
    var palette = document.getElementById('themePalette');
    var toggle = document.getElementById('colorToggle');
    if(palette && !palette.contains(e.target) && !toggle.contains(e.target)){
      palette.classList.remove('show');
    }
  });

  (function initResponsiveSubtitle(){
    const el = document.querySelector('.subtitle');
    if(!el) return;
    if(!el.dataset.fulltext){ el.dataset.fulltext = (el.textContent || '').trim(); }

    function splitToTwoLines(){
      const text = el.dataset.fulltext || '';
      if(!text) return;
      el.classList.add('multi');
      el.classList.remove('typing-caret-glow','typing-smooth');
      el.style.removeProperty('animation'); el.style.removeProperty('border-right'); el.innerHTML = '';

      const maxWidth = el.clientWidth || 320;
      const meas = document.createElement('span');
      const cs = window.getComputedStyle(el);
      meas.style.visibility='hidden'; meas.style.whiteSpace='nowrap'; meas.style.position='absolute';
      meas.style.left='-9999px'; meas.style.top='-9999px'; meas.style.font = cs.font; meas.style.fontSize = cs.fontSize;
      document.body.appendChild(meas);

      const words = text.split(/\s+/); let line1Words = [];
      for(let i=0;i<words.length;i++){
        const tryStr = (line1Words.concat(words[i])).join(' '); meas.textContent = tryStr;
        if(meas.offsetWidth <= maxWidth || line1Words.length===0){ line1Words.push(words[i]); } else { break; }
      }
      document.body.removeChild(meas);

      const l1Text = line1Words.join(' ').trim();
      const l2Text = words.slice(line1Words.length).join(' ').trim();
      if(!l2Text){
        el.classList.remove('multi'); el.textContent = text;
        const themeNow = document.documentElement.getAttribute('data-theme') || 'light';
        el.classList.add(themeNow==='dark' ? 'typing-smooth' : 'typing-caret-glow');
        return;
      }

      const l1 = document.createElement('span'); l1.className = 'subtitle-line';
      const l2 = document.createElement('span'); l2.className = 'subtitle-line';
      l1.textContent = l1Text; l2.textContent = l2Text;

      const n1 = l1Text.length, n2 = l2Text.length;
      l1.style.setProperty('--chars', n1); l2.style.setProperty('--chars', n2);
      const perChar = 0.027; const d1 = Math.max(1.2, +(n1*perChar).toFixed(2)); const d2 = Math.max(1.0, +(n2*perChar).toFixed(2));
      l1.style.setProperty('--dur', d1+'s'); l2.style.setProperty('--dur', d2+'s'); l2.style.animationDelay = (d1 + 0.20) + 's';

      const theme = document.documentElement.getAttribute('data-theme') || 'light';
      if(theme==='dark'){ l1.classList.add('typing-smooth'); l2.classList.add('typing-smooth'); }
      else{ l1.classList.add('typing-caret-glow'); l2.classList.add('typing-caret-glow'); }

      l1.addEventListener('animationend', (e)=>{ if(e.animationName === 'typeChars'){ l1.classList.add('ended'); } });
      el.appendChild(l1); el.appendChild(l2);
    }

    function restoreSingleLine(){
      const text = el.dataset.fulltext || '';
      el.classList.remove('multi'); el.innerHTML = text;
      const theme = document.documentElement.getAttribute('data-theme') || 'light';
      el.classList.remove('typing-caret-glow','typing-smooth');
      el.classList.add(theme==='dark' ? 'typing-smooth' : 'typing-caret-glow');
      el.style.removeProperty('border-right');
    }

    function applyMode(){
      const small = (el.clientWidth || window.innerWidth) <= 520;
      if(small && !el.classList.contains('multi')){ splitToTwoLines(); }
      else if(!small && el.classList.contains('multi')){ restoreSingleLine(); }
    }

    applyMode();
    function debounce(fn, wait){ let t; return ()=>{ clearTimeout(t); t=setTimeout(fn, wait); } }
    const reapply = debounce(applyMode, 120);
    window.addEventListener('resize,', reapply);
    window.addEventListener('orientationchange', reapply);
  })();

  (function fitSubtitleOneLine(){
    const el = document.querySelector('.subtitle'); if(!el) return;
    const baseSize = parseFloat(window.getComputedStyle(el).fontSize) || 16;
    const MIN = 10, STEP = 0.5; let lastApplied = baseSize;
    function isMulti(){ return el.classList.contains('multi'); }
    function fit(){
      if(isMulti()) return;
      el.style.animationPlayState = 'paused';
      el.style.fontSize = baseSize + 'px'; lastApplied = baseSize;
      while (el.scrollWidth > el.clientWidth && lastApplied > MIN){
        lastApplied -= STEP; el.style.fontSize = lastApplied + 'px'; if (lastApplied <= MIN) break;
      }
      requestAnimationFrame(() => { el.style.animationPlayState = 'running'; });
    }
    fit();
    function debounce(fn, wait){ let t; return function(){ clearTimeout(t); t=setTimeout(fn, wait); }; }
    const refit = debounce(fit, 120);
    window.addEventListener('resize', refit);
    window.addEventListener('orientationchange', refit);
  })();

  // ===== Ganti UI sesuai role =====
  const roleSel     = document.getElementById('role');
  const siswaFields = document.getElementById('siswaFields');
  const userFields  = document.getElementById('userFields');
  const roleIcon    = document.getElementById('roleIcon');

  const icons = {siswa:'🎒', guru:'🎓', tas:'🗂', sekretaris:'🗃', piket:'🛎', admin:'🛡'};
  const roleNames = {
    siswa:'Siswa', guru:'Guru', tas:'Staf TU/TAS',
    sekretaris:'Pembina Eskul / Sekretaris', piket:'Guru Piket', admin:'Admin'
  };

  function showToast(text){
    const toast = document.getElementById('toast');
    toast.textContent = text; toast.hidden = false;
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(()=>{ toast.hidden = true; }, 3000);
  }

  function setRoleUI(role){
    const selectWrap = document.querySelector('.select-role');
    const $loginBtn = $('#loginBtn'), $username = $('#username'), $pwdUser  = $('#password_user'),
          $nis = $('#nis'), $pwdSis = $('#password_siswa');

    roleIcon.textContent = icons[role] || '👤';

    if(role){ selectWrap.classList.add('is-selected','bounce'); setTimeout(()=> selectWrap.classList.remove('bounce'), 600); }
    else{ selectWrap.classList.remove('is-selected'); }

    if(!role){
      siswaFields.style.display='none'; userFields.style.display='none';
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $username.prop('required',false).prop('disabled',true);
      $pwdUser.prop('required',false).prop('disabled',true);
      $loginBtn.prop('disabled',true).text('LOGIN');

      const pre = $('#preRoleText');
      pre.text('Pilih peran Anda terlebih dahulu.').addClass('changed');
      setTimeout(()=> pre.removeClass('changed'), 460);
      setTimeout(()=> roleSel.focus(), 0);
      return;
    }

    localStorage.setItem('epoin_role', role);

    const pre = $('#preRoleText');
    pre.text('Peran dipilih: ' + (roleNames[role] || '—')).addClass('changed');
    setTimeout(()=> pre.removeClass('changed'), 460);

    $loginBtn.prop('disabled', false);

    if(role==='siswa'){
      siswaFields.style.display=''; userFields.style.display='none';
      $nis.prop('required',true).prop('disabled',false);
      $pwdSis.prop('required',true).prop('disabled',false);
      $username.prop('required',false).prop('disabled',true);
      $pwdUser.prop('required',false).prop('disabled',true);
      $loginBtn.text('Masuk sebagai Siswa');
      setTimeout(()=> $('#nis').trigger('focus'), 0);
    }else if(role==='guru'){
      siswaFields.style.display='none'; userFields.style.display='';
      $username.prop('required',true).prop('disabled',false);
      $pwdUser.prop('required',true).prop('disabled',false);
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $('#username').attr('placeholder','NIP / NUPTK / Username');
      $loginBtn.text('Masuk sebagai Guru');
      setTimeout(()=> $('#username').trigger('focus'), 0);
    }else if(role==='tas'){
      siswaFields.style.display='none'; userFields.style.display='';
      $username.prop('required',true).prop('disabled',false);
      $pwdUser.prop('required',true).prop('disabled',false);
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $('#username').attr('placeholder','NIP / NUPTK / Username');
      $loginBtn.text('Masuk sebagai Staf TU/TAS');
      setTimeout(()=> $('#username').trigger('focus'), 0);
    }else if(role==='piket'){
      siswaFields.style.display='none'; userFields.style.display='';
      $username.prop('required',true).prop('disabled',false);
      $pwdUser.prop('required',true).prop('disabled',false);
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $('#username').attr('placeholder','NIP / NUPTK / Username');
      $loginBtn.text('Masuk sebagai Guru Piket');
      setTimeout(()=> $('#username').trigger('focus'), 0);
    }else if(role==='sekretaris'){
      siswaFields.style.display='none'; userFields.style.display='';
      $username.prop('required',true).prop('disabled',false);
      $pwdUser.prop('required',true).prop('disabled',false);
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $('#username').attr('placeholder','NIP / NUPTK / NIS ');
      $loginBtn.text('Masuk sebagai Pembina Eskul / Sekretaris');
      setTimeout(()=> $('#username').trigger('focus'), 0);
    }else if(role==='admin'){
      siswaFields.style.display='none'; userFields.style.display='';
      $username.prop('required',true).prop('disabled',false);
      $pwdUser.prop('required',true).prop('disabled',false);
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $('#username').attr('placeholder','Username');
      $loginBtn.text('Masuk sebagai Admin');
      setTimeout(()=> $('#username').trigger('focus'), 0);
    }else{
      siswaFields.style.display='none'; userFields.style.display='';
      $username.prop('required',true).prop('disabled',false);
      $pwdUser.prop('required',true).prop('disabled',false);
      $nis.prop('required',false).prop('disabled',true);
      $pwdSis.prop('required',false).prop('disabled',true);
      $('#username').attr('placeholder','Username');
      $loginBtn.text('LOGIN');
      setTimeout(()=> $('#username').trigger('focus'), 0);
    }
    $('#roleMsg').text('');
  }

  (function initRole(){
    const savedRole = localStorage.getItem('epoin_role');
    if(savedRole && ['siswa','guru','tas','sekretaris','piket','admin'].includes(savedRole)){
      roleSel.value = savedRole; setRoleUI(savedRole);
    }else{ roleSel.value=''; setRoleUI(''); }
  })();

  roleSel.addEventListener('change', function(e){
    setRoleUI(e.target.value);
    $('#roleMsg').text('');
  });

  (function enlargeSelectHitArea(){
    const wrap = document.querySelector('.select-role');
    wrap.addEventListener('click', function(e){
      if(e.target.tagName.toLowerCase() !== 'select'){
        e.preventDefault(); roleSel.focus();
        if (typeof roleSel.showPicker === 'function') { try { roleSel.showPicker(); } catch(err){} }
        else { roleSel.click(); }
      }
    });
    wrap.addEventListener('keydown', function(e){
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault(); roleSel.focus();
        if (typeof roleSel.showPicker === 'function') { try { roleSel.showPicker(); } catch(err){} }
        else { roleSel.click(); }
      }
    });
  })();

  // (tetap) event kecil dari versi sebelumnya—tidak mengganggu jika elemen tidak ada
  $('#tentangEPOIN').on('shown.bs.modal', function(){
    $('#tentangEPOIN .about-list li').each(function(i){
      $(this).css('animation-delay', (i*120)+'ms');
    });
    ensureBadgeSingleLine();
  });

  $(document).on('click','.toggle-eye', function(){
    const target = $(this).data('target'), $inp = $(target), show = $inp.attr('type')==='password';
    $inp.attr('type', show ? 'text' : 'password'); $(this).toggleClass('fa-eye fa-eye-slash');
  });

  $('#loginForm').on('keypress', 'input', function(e){ if(e.which === 13){ $('#loginForm').trigger('submit'); } });

  $('#loginForm').on('submit', function(e){
    const role = $('#role').val();
    if(!role){
      e.preventDefault();
      $('#role').focus();
      const msg = 'Anda belum memilih peran. Silakan pilih peran login terlebih dahulu.';
      $('#roleMsg').text(msg); showToast(msg); return false;
    }
    if(role==='siswa'){
      if(!$('input[name="password"]').length){
        $('<input>').attr({type:'hidden', name:'password'}).val($('#password_siswa').val()).appendTo('#loginForm');
      }else{ $('input[name="password"]').val($('#password_siswa').val()); }
    }else{
      if(!$('input[name="password"]').length){
        $('<input>').attr({type:'hidden', name:'password'}).val($('#password_user').val()).appendTo('#loginForm');
      }else{ $('input[name="password"]').val($('#password_user').val()); }
    }
  });

  (function enableShimmerOnTyping(){
    const btn = document.getElementById('loginBtn'); let timer = null;
    function startShimmer(){
      btn.classList.add('shimmering');
      if(timer) clearTimeout(timer);
      timer = setTimeout(()=> btn.classList.remove('shimmering'), 1600);
    }
    ['#username','#password_user','#nis','#password_siswa'].forEach(sel=>{
      $(document).on('input', sel, startShimmer);
    });
  })();

  /* ====== Tambahan kecil: efek "tap" di mobile untuk kartu benefit (aman jika tidak ada) ====== */
  (function benefitTapFeedback(){
    var items = document.querySelectorAll('#tentangEPOIN .eps-plus-head .eps-benefit');
    if(!items.length) return;
    items.forEach(function(el){
      el.addEventListener('touchstart', function(){
        el.classList.add('is-tap');
        setTimeout(function(){ el.classList.remove('is-tap'); }, 650);
      }, {passive:true});
    });
  })();

  /* ====== Badge helper (aman jika elemen tidak ada) ====== */
  function ensureBadgeSingleLine(){
    var titles = document.querySelectorAll('#tentangEPOIN .eps-mod .title');
    titles.forEach(function(t){
      var badge = t.querySelector('.title-badge');
      if(!badge) return;
      badge.classList.remove('badge-condense-1','badge-condense-2','badge-condense-3');
      t.style.whiteSpace = 'nowrap';
      var levels = ['badge-condense-1','badge-condense-2','badge-condense-3'];
      for(var i=0;i<levels.length && t.scrollWidth>t.clientWidth;i++){
        badge.classList.add(levels[i]);
      }
    });
  }
  window.addEventListener('resize', ensureBadgeSingleLine);
  window.addEventListener('orientationchange', ensureBadgeSingleLine);
  document.addEventListener('DOMContentLoaded', ensureBadgeSingleLine);
</script>
</body>
</html>
