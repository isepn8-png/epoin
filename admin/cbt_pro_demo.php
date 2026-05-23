<?php
/* ============================================================
   CBT Pro — Demo/Prototype Single Page
   - Sidebar interaktif (SPA-like) dengan 12 menu utama
   - Seksi konten switch via JS (data-section)
   - UI modern: gradient, glass, badges, cards, toasts
   - Chart analitik (dummy) + simulasi monitoring live
   - Tidak ada koneksi DB (pure front-end demo)
   ============================================================ */
$title = 'CBT Pro (Demo Project)';
?><!doctype html>
<html lang="id" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>

  <!-- Bootstrap 5 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <style>
    :root{
      --grad-1: #00c6ff;
      --grad-2: #0072ff;
      --grad-3: #36d1dc;
      --grad-4: #5b86e5;
      --glass-bg: rgba(255,255,255,.05);
      --glass-br: 18px;
      --soft-shadow: 0 10px 30px rgba(0,0,0,.25);
    }
    html,body{height:100%;}
    body{
      background:
        radial-gradient(1200px 600px at -10% -10%, rgba(0,255,255,.08), transparent 60%),
        radial-gradient(900px 500px at 110% 10%, rgba(0,128,255,.08), transparent 60%),
        linear-gradient(120deg, #0b1220, #0b1020 40%);
      overflow: hidden;
    }
    .app{
      display:grid;
      grid-template-columns: 290px 1fr;
      grid-template-rows: 64px 1fr;
      grid-template-areas:
        "sidebar header"
        "sidebar main";
      height:100%;
    }
    /* Header */
    .app-header{
      grid-area: header;
      display:flex; align-items:center; justify-content:space-between;
      padding: 10px 18px;
      background: linear-gradient(90deg, rgba(0,0,0,.35), rgba(255,255,255,.05));
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .brand{
      display:flex; align-items:center; gap:.8rem;
      font-weight:700;
      letter-spacing:.3px;
    }
    .brand .logo{
      width:40px;height:40px;border-radius:12px;
      background: conic-gradient(from 200deg, var(--grad-1), var(--grad-2), var(--grad-3), var(--grad-4), var(--grad-1));
      position:relative; box-shadow: var(--soft-shadow);
    }
    .brand .logo::after{
      content:""; position:absolute; inset:3px; border-radius:10px;
      background: radial-gradient(120% 120% at 10% -20%, rgba(255,255,255,.85), rgba(255,255,255,.15) 45%, transparent 60%);
      mix-blend-mode: screen;
    }

    /* Sidebar */
    .sidebar{
      grid-area: sidebar; overflow:auto;
      background: linear-gradient(180deg, rgba(0,0,0,.5), rgba(255,255,255,.02));
      border-right:1px solid rgba(255,255,255,.08);
      padding:12px;
    }
    .sidebar::-webkit-scrollbar{ width:8px; }
    .sidebar::-webkit-scrollbar-thumb{ background: rgba(255,255,255,.1); border-radius:6px; }

    .nav-title{ font-size:.78rem; letter-spacing:.12em; color:#cbd5e1; opacity:.8; margin:10px 10px 6px; }
    .menu-btn{
      width:100%; text-align:left; border:0; padding:10px 12px; border-radius:14px;
      background: transparent; color:#e5e7eb; position:relative;
      display:flex; align-items:center; gap:.75rem; transition: .2s;
    }
    .menu-btn .fa, .menu-btn .fas, .menu-btn .far{ width:22px; text-align:center; opacity:.9; }
    .menu-btn.active, .menu-btn:hover{
      background: linear-gradient(90deg, rgba(0,114,255,.22), rgba(0,198,255,.16));
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
    }
    .menu-badge{
      margin-left:auto; font-size:.7rem; background:#0ea5e9; color:#001b2a;
    }

    /* Main */
    .app-main{
      grid-area: main; overflow:auto; padding:16px 18px 28px;
    }
    .glass{
      background: var(--glass-bg);
      border:1px solid rgba(255,255,255,.08);
      border-radius: var(--glass-br);
      box-shadow: var(--soft-shadow);
    }
    .hero{
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      padding:18px 20px; margin-bottom:18px;
      background:
        linear-gradient(90deg, rgba(0,198,255,.15), rgba(91,134,229,.15)),
        var(--glass-bg);
      border:1px solid rgba(255,255,255,.06);
      border-radius:20px;
    }
    .hero h1{ font-size:1.2rem; margin:0; }
    .chip{
      display:inline-flex; align-items:center; gap:.35rem;
      padding:6px 10px; border-radius:999px;
      background:linear-gradient(90deg, rgba(0,198,255,.25), rgba(0,114,255,.25));
      border:1px solid rgba(255,255,255,.1); font-size:.8rem;
    }
    .section{ display:none; animation: fade .25s ease-out; }
    .section.active{ display:block; }
    @keyframes fade { from{ opacity:0; transform: translateY(4px);} to{opacity:1; transform:none;} }

    .kpi-card .value{
      font-weight:800; font-size:1.4rem;
    }
    .table thead th{ background: rgba(255,255,255,.04); }
    .floating-cta{
      position: fixed; right:22px; bottom:22px; z-index:99;
      background: linear-gradient(135deg, var(--grad-2), var(--grad-1));
      border:0; border-radius:16px; box-shadow: var(--soft-shadow); padding:12px 16px;
      color:#001a2b; font-weight:700;
    }
    .status-dot{ width:8px; height:8px; border-radius:50%; display:inline-block; margin-right:6px; }
    .dot-online{ background:#22c55e; } .dot-idle{ background:#eab308; } .dot-off{ background:#ef4444; }
    .badge-soft{ background: rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12); }
    .form-control, .form-select{ background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); }
    .toast-container{ z-index: 1056; }
    @media (max-width: 980px){
      .app{ grid-template-columns: 86px 1fr; }
      .menu-text{ display:none; }
      .nav-title{ display:none; }
    }
  </style>
</head>
<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="d-flex align-items-center gap-2 px-2 py-2">
      <div class="logo"></div>
      <div class="fw-bold fs-6">INUSI <span class="text-info">CBT</span></div>
    </div>

    <div class="nav-title">UJIAN ONLINE</div>
    <button class="menu-btn" data-section="gform">
      <i class="fa fa-link"></i><span class="menu-text">CBT GForm</span>
      <span class="badge menu-badge">existing</span>
    </button>

    <div class="nav-title">CBT PRO (BANK SOAL)</div>
    <button class="menu-btn active" data-section="dash"><i class="fa fa-chart-pie"></i><span class="menu-text">Dashboard CBT</span></button>
    <button class="menu-btn" data-section="bank"><i class="fa fa-database"></i><span class="menu-text">Bank Soal</span></button>
    <button class="menu-btn" data-section="paket"><i class="fa fa-layer-group"></i><span class="menu-text">Paket & Ujian</span></button>
    <button class="menu-btn" data-section="sesi"><i class="fa fa-calendar-check"></i><span class="menu-text">Sesi & Jadwal</span></button>
    <button class="menu-btn" data-section="monitor"><i class="fa fa-desktop"></i><span class="menu-text">Monitoring Live</span><span class="badge menu-badge">LIVE</span></button>
    <button class="menu-btn" data-section="pelanggaran"><i class="fa fa-shield-halved"></i><span class="menu-text">Pelanggaran</span></button>
    <button class="menu-btn" data-section="koreksi"><i class="fa fa-pen-ruler"></i><span class="menu-text">Penilaian & Koreksi</span></button>
    <button class="menu-btn" data-section="hasil"><i class="fa fa-table"></i><span class="menu-text">Hasil & Rekap</span></button>
    <button class="menu-btn" data-section="analitik"><i class="fa fa-chart-line"></i><span class="menu-text">Analitik & Kualitas Butir</span></button>
    <button class="menu-btn" data-section="susulan"><i class="fa fa-clock-rotate-left"></i><span class="menu-text">Izin & Susulan</span></button>
    <button class="menu-btn" data-section="template"><i class="fa fa-clone"></i><span class="menu-text">Template & Preset</span></button>
    <button class="menu-btn" data-section="pengaturan"><i class="fa fa-gear"></i><span class="menu-text">Pengaturan CBT</span></button>
  </aside>

  <!-- HEADER -->
  <header class="app-header">
    <div class="brand">
      <div class="logo"></div>
      <div>
        <div class="small text-secondary">Project Preview</div>
        <div class="fs-6">CBT Pro (Bank Soal)</div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="chip"><i class="fa fa-shield"></i> Guard: Aktif</span>
      <span class="chip"><i class="fa fa-signal"></i> Realtime</span>
      <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#aboutModal"><i class="fa fa-circle-info me-1"></i>Tentang Demo</button>
    </div>
  </header>

  <!-- MAIN -->
  <main class="app-main">

    <!-- HERO -->
    <div class="hero">
      <div>
        <h1 class="mb-1">Selamat datang di <span class="text-info fw-bold">CBT Pro</span> — simulasi menu & fitur</h1>
        <div class="text-secondary">Prototype interaktif untuk pratinjau alur <b>Bank Soal → Paket → Sesi → Monitoring → Hasil</b>.</div>
      </div>
      <div class="text-end">
        <div class="mb-2"><span class="badge rounded-pill bg-success">Stabil</span> <span class="badge rounded-pill bg-warning text-dark">Prototype</span></div>
        <a href="#!" class="btn btn-outline-light btn-sm"><i class="fa fa-book-open-reader me-2"></i>Panduan Singkat</a>
      </div>
    </div>

    <!-- SECTION: CBT GFORM (placeholder) -->
    <section id="sec-gform" class="section glass p-3">
      <h5 class="mb-3"><i class="fa fa-link me-2"></i>CBT GForm</h5>
      <p class="text-secondary">Ini adalah rute lama/eksisting untuk ujian berbasis tautan Google Form. Tetap tersedia berdampingan dengan CBT Pro.</p>
      <ul class="mb-0">
        <li>Jadwal, Tambah (Single/Kolektif), Bank Link, Monitoring, Hasil & Pelanggaran.</li>
        <li>Gunakan CBT Pro jika membutuhkan kontrol per-soal, token, dan analitik lengkap.</li>
      </ul>
    </section>

    <!-- SECTION: DASHBOARD -->
    <section id="sec-dash" class="section active">
      <div class="row g-3 mb-3">
        <?php
          $cards = [
            ['title'=>'Sesi Aktif','value'=>'3','icon'=>'fa-rocket','class'=>'bg-info text-dark'],
            ['title'=>'Peserta Online','value'=>'92','icon'=>'fa-users','class'=>'bg-success text-dark'],
            ['title'=>'Submit Hari Ini','value'=>'137','icon'=>'fa-check-double','class'=>'bg-primary'],
            ['title'=>'Pelanggaran','value'=>'12','icon'=>'fa-shield-halved','class'=>'bg-warning text-dark']
          ];
          foreach($cards as $c): ?>
          <div class="col-6 col-lg-3">
            <div class="glass p-3 kpi-card">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <div class="value"><?= $c['value'] ?></div>
                  <div class="text-secondary small"><?= $c['title'] ?></div>
                </div>
                <div class="badge <?= $c['class'] ?> rounded-pill"><i class="fa <?= $c['icon'] ?>"></i></div>
              </div>
              <div class="progress mt-3" role="progressbar" aria-label="progress">
                <div class="progress-bar" style="width: <?= rand(35,90) ?>%"></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-3">
        <div class="col-lg-7">
          <div class="glass p-3">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="fa fa-chart-line me-2"></i>Tren Nilai Rata-rata (Dummy)</h6>
              <div class="text-secondary small">Mingguan • Matematika 8A</div>
            </div>
            <canvas id="chartNilai" height="120" class="mt-3"></canvas>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="glass p-3">
            <h6 class="mb-2"><i class="fa fa-bullhorn me-2"></i>Aksi Cepat</h6>
            <div class="row g-2">
              <div class="col-6"><a class="btn btn-outline-info w-100" data-section-jump="sesi"><i class="fa fa-calendar-plus me-1"></i>Buat Sesi</a></div>
              <div class="col-6"><a class="btn btn-outline-light w-100" data-section-jump="bank"><i class="fa fa-file-circle-plus me-1"></i>Tambah Soal</a></div>
              <div class="col-6"><a class="btn btn-outline-success w-100" data-section-jump="monitor"><i class="fa fa-desktop me-1"></i>Monitoring</a></div>
              <div class="col-6"><a class="btn btn-outline-warning w-100" data-section-jump="hasil"><i class="fa fa-download me-1"></i>Export Nilai</a></div>
            </div>
            <hr>
            <p class="small text-secondary mb-0">Gunakan menu di kiri untuk menjelajah semua fitur CBT Pro.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION: BANK SOAL -->
    <section id="sec-bank" class="section">
      <div class="glass p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0"><i class="fa fa-database me-2"></i>Bank Soal</h5>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-light"><i class="fa fa-upload me-1"></i>Import (Word/CSV/GIFT)</button>
            <button class="btn btn-sm btn-info text-dark"><i class="fa fa-file-circle-plus me-1"></i>Soal Baru</button>
          </div>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-lg-3"><input class="form-control" placeholder="Cari stem/ID/KD…"></div>
          <div class="col-lg-3">
            <select class="form-select">
              <option>Semua Mapel</option><option>Matematika</option><option>Bahasa Indonesia</option>
            </select>
          </div>
          <div class="col-lg-3">
            <select class="form-select">
              <option>Semua Tipe</option><option>Pilihan Ganda</option><option>Isian Singkat</option><option>Esai</option><option>Menjodohkan</option>
            </select>
          </div>
          <div class="col-lg-3">
            <select class="form-select">
              <option>Level Kognitif</option><option>C1</option><option>C2</option><option>C3</option><option>C4</option>
            </select>
          </div>
        </div>
      </div>
      <div class="glass p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr>
              <th style="width:72px">ID</th><th>Stem</th><th>Tipe</th><th>KD/Indikator</th><th>Level</th><th>Media</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody>
              <?php for($i=1;$i<=6;$i++): ?>
              <tr>
                <td>SOAL<?= 100+$i ?></td>
                <td>Contoh stem soal dengan <em>tag</em> penting dan dukungan LaTeX \(a^2+b^2=c^2\).</td>
                <td><span class="badge badge-soft">PG</span></td>
                <td>KD 3.<?= rand(1,6) ?> – Indikator <?= rand(1,3) ?></td>
                <td><span class="badge bg-info text-dark">C<?= rand(1,4) ?></span></td>
                <td><?= rand(0,1)?'Gambar':'Teks' ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-light"><i class="fa fa-eye"></i></button>
                  <button class="btn btn-sm btn-outline-info text-dark"><i class="fa fa-copy"></i></button>
                  <button class="btn btn-sm btn-outline-warning text-dark"><i class="fa fa-pen"></i></button>
                </td>
              </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="small text-secondary mt-2">Tip: gunakan <b>Tag</b> untuk memudahkan komposisi paket (filter by KD/kognitif).</div>
    </section>

    <!-- SECTION: PAKET & UJIAN -->
    <section id="sec-paket" class="section">
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="glass p-3 h-100">
            <h5 class="mb-3"><i class="fa fa-layer-group me-2"></i>Paket Ujian</h5>
            <div class="mb-2 d-flex gap-2">
              <input class="form-control" placeholder="Cari paket…">
              <button class="btn btn-info text-dark"><i class="fa fa-plus me-1"></i>Paket Baru</button>
            </div>
            <div class="list-group list-group-flush">
              <?php
              $paket = ['PAT MTK 8A','PAS B.Indo 7B','PTS IPA 9','UH Trigonometri 8C'];
              foreach($paket as $p): ?>
                <a href="#!" class="list-group-item list-group-item-action bg-transparent text-light d-flex justify-content-between align-items-center">
                  <span><i class="fa fa-box-open me-2 text-info"></i><?= $p ?></span>
                  <span class="badge bg-success">Komplit</span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="glass p-3 h-100">
            <h6 class="mb-2">Aturan Paket (Preview)</h6>
            <div class="row g-2 mb-2">
              <div class="col-6"><span class="badge badge-soft">Random Soal</span></div>
              <div class="col-6"><span class="badge badge-soft">Random Opsi</span></div>
              <div class="col-6"><span class="badge badge-soft">Token</span></div>
              <div class="col-6"><span class="badge badge-soft">Back Nav Off</span></div>
            </div>
            <div class="progress" role="progressbar"><div class="progress-bar" style="width: 68%"></div></div>
            <div class="text-secondary small mt-2">Komposisi: 25 PG, 5 Esai • Durasi: 90 menit • KKM 75</div>
            <hr>
            <div class="d-flex gap-2">
              <button class="btn btn-outline-light btn-sm"><i class="fa fa-wrench me-1"></i>Atur Komposisi</button>
              <button class="btn btn-outline-info btn-sm text-dark"><i class="fa fa-flask me-1"></i>Uji Paket</button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION: SESI & JADWAL -->
    <section id="sec-sesi" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-calendar-check me-2"></i>Sesi & Jadwal</h5>
      </div>
      <div class="glass p-3">
        <div class="row g-2">
          <div class="col-lg-3"><input class="form-control" placeholder="Tanggal"></div>
          <div class="col-lg-3"><select class="form-select"><option>Ruang/Lab</option><option>Lab 1</option><option>Lab 2</option></select></div>
          <div class="col-lg-3"><select class="form-select"><option>Paket</option><option>PAT MTK 8A</option></select></div>
          <div class="col-lg-3"><button class="btn btn-info text-dark w-100"><i class="fa fa-plus me-1"></i>Buat Sesi</button></div>
        </div>
        <hr>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Waktu</th><th>Ruang</th><th>Kelas</th><th>Paket</th><th>Token</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <tr><td>08:00–09:30</td><td>Lab 1</td><td>8A</td><td>PAT MTK 8A</td><td><code>AX9Q</code></td><td><span class="badge bg-success">Siap</span></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-success"><i class="fa fa-play"></i></button>
                  <button class="btn btn-sm btn-outline-warning text-dark"><i class="fa fa-key"></i></button>
                  <button class="btn btn-sm btn-outline-light"><i class="fa fa-qrcode"></i></button></td></tr>
              <tr><td>10:00–11:30</td><td>Lab 2</td><td>8C</td><td>UH Trigonometri</td><td><code>7MPT</code></td><td><span class="badge bg-secondary">Terjadwal</span></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-success"><i class="fa fa-play"></i></button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- SECTION: MONITORING -->
    <section id="sec-monitor" class="section">
      <div class="glass p-3 mb-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="fa fa-desktop me-2"></i>Monitoring Live</h5>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-warning text-dark btn-sm"><i class="fa fa-bullhorn me-1"></i>Broadcast</button>
          <button class="btn btn-outline-light btn-sm"><i class="fa fa-stopwatch me-1"></i>Beri Waktu +5’</button>
          <button class="btn btn-outline-danger btn-sm"><i class="fa fa-flag-checkered me-1"></i>Paksa Selesai</button>
        </div>
      </div>
      <div class="glass p-3">
        <div class="table-responsive">
          <table class="table table-sm align-middle" id="tblMonitor">
            <thead><tr><th>#</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Progres</th><th>Waktu</th><th>IP</th></tr></thead>
            <tbody><!-- diisi JS --></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- SECTION: PELANGGARAN -->
    <section id="sec-pelanggaran" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-shield-halved me-2"></i>Log Pelanggaran</h5>
      </div>
      <div class="glass p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Nama</th><th>Jenis</th><th>Detail</th><th>Penalti</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <tr><td>08:41:12</td><td>Rama 8A</td><td>Keluar FS</td><td>Visibility hidden 3 detik</td><td><span class="badge bg-warning text-dark">-2</span></td><td class="text-end"><button class="btn btn-sm btn-outline-light">Bukti</button></td></tr>
              <tr><td>08:55:39</td><td>Rani 8A</td><td>Alt+Tab</td><td>Switch 1 kali</td><td><span class="badge bg-warning text-dark">-1</span></td><td class="text-end"><button class="btn btn-sm btn-outline-light">Bukti</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- SECTION: KOREKSI -->
    <section id="sec-koreksi" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-pen-ruler me-2"></i>Penilaian & Koreksi</h5>
      </div>
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="glass p-3 h-100">
            <h6>Koreksi Esai (Rubrik)</h6>
            <div class="mb-2 small text-secondary">Soal: Jelaskan perbedaan <em>array</em> dan <em>object</em>.</div>
            <div class="table-responsive mb-2">
              <table class="table table-sm">
                <thead><tr><th>Kriteria</th><th>Bobot</th><th>Skor</th></tr></thead>
                <tbody>
                  <tr><td>Kelengkapan</td><td>40%</td><td><input type="number" class="form-control form-control-sm" value="3" min="0" max="4"></td></tr>
                  <tr><td>Ketepatan</td><td>40%</td><td><input type="number" class="form-control form-control-sm" value="3" min="0" max="4"></td></tr>
                  <tr><td>Bahasa</td><td>20%</td><td><input type="number" class="form-control form-control-sm" value="4" min="0" max="4"></td></tr>
                </tbody>
              </table>
            </div>
            <textarea class="form-control" rows="3" placeholder="Catatan untuk siswa…"></textarea>
            <div class="mt-2 d-flex gap-2">
              <button class="btn btn-success btn-sm text-dark"><i class="fa fa-check me-1"></i>Simpan Nilai</button>
              <button class="btn btn-outline-light btn-sm"><i class="fa fa-rotate me-1"></i>Regrade</button>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="glass p-3 h-100">
            <h6>Penilaian Otomatis (Objektif)</h6>
            <p class="small text-secondary mb-2">Hasil otomatis PG/BS/Menjodohkan & Isian (pattern).</p>
            <div class="d-flex align-items-center gap-3">
              <div><span class="display-6 fw-bold text-success">86</span><div class="small text-secondary">Nilai</div></div>
              <div class="flex-grow-1">
                <div class="progress"><div class="progress-bar bg-success" style="width: 86%"></div></div>
                <div class="small text-secondary mt-1">Benar 43/50 • Salah 7</div>
              </div>
            </div>
            <hr>
            <button class="btn btn-outline-warning text-dark btn-sm"><i class="fa fa-pen me-1"></i>Moderasi Nilai</button>
            <button class="btn btn-outline-light btn-sm"><i class="fa fa-file-export me-1"></i>Export Detail Jawaban</button>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION: HASIL & REKAP -->
    <section id="sec-hasil" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-table me-2"></i>Hasil & Rekap</h5>
      </div>
      <div class="glass p-3">
        <div class="row g-2 mb-2">
          <div class="col-lg-3"><select class="form-select"><option>Kelas</option><option>8A</option><option>8B</option></select></div>
          <div class="col-lg-3"><select class="form-select"><option>Mapel</option><option>Matematika</option></select></div>
          <div class="col-lg-3"><select class="form-select"><option>Paket</option><option>PAT 8A</option></select></div>
          <div class="col-lg-3 d-flex gap-2">
            <button class="btn btn-outline-light w-100"><i class="fa fa-file-arrow-down me-1"></i>Export XLSX</button>
            <button class="btn btn-outline-light w-100"><i class="fa fa-file-pdf me-1"></i>Export PDF</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Nama</th><th>Nilai</th><th>Status</th><th>Remedial</th></tr></thead>
            <tbody>
              <tr><td>1</td><td>Arman</td><td>92</td><td><span class="badge bg-success">Tuntas</span></td><td>-</td></tr>
              <tr><td>2</td><td>Nisa</td><td>68</td><td><span class="badge bg-danger">Tidak</span></td><td><button class="btn btn-sm btn-outline-warning text-dark">Rencanakan</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- SECTION: ANALITIK -->
    <section id="sec-analitik" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-chart-line me-2"></i>Analitik & Kualitas Butir</h5>
      </div>
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="glass p-3">
            <h6 class="mb-2">Distribusi Nilai (Dummy)</h6>
            <canvas id="chartDistribusi" height="120"></canvas>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="glass p-3 h-100">
            <h6 class="mb-2">Kualitas Butir Sampel</h6>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Item</th><th>p</th><th>Daya Beda</th><th>Status</th></tr></thead>
                <tbody>
                  <tr><td>SOAL101</td><td>0.82</td><td>0.36</td><td><span class="badge bg-success">Baik</span></td></tr>
                  <tr><td>SOAL115</td><td>0.92</td><td>0.08</td><td><span class="badge bg-warning text-dark">Revisi</span></td></tr>
                  <tr><td>SOAL127</td><td>0.34</td><td>0.12</td><td><span class="badge bg-danger">Sulit</span></td></tr>
                </tbody>
              </table>
            </div>
            <div class="small text-secondary mt-2">p = proporsi benar; Daya beda ≈ rpbis. Rekomendasi otomatis tersedia di fase implementasi.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION: SUSULAN -->
    <section id="sec-susulan" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-clock-rotate-left me-2"></i>Izin & Susulan</h5>
      </div>
      <div class="glass p-3">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>Nama</th><th>Alasan</th><th>Bukti</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
              <tr><td>Rafi</td><td>Sakit</td><td>Surat dokter</td><td><span class="badge bg-secondary">Menunggu</span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-success text-dark">Approve</button>
                  <button class="btn btn-sm btn-outline-light">Tolak</button>
                </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- SECTION: TEMPLATE -->
    <section id="sec-template" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-clone me-2"></i>Template & Preset</h5>
      </div>
      <div class="glass p-3">
        <div class="row g-3">
          <div class="col-lg-4"><div class="glass p-3 h-100">
            <h6 class="mb-2">Preset “UNBK Style”</h6>
            <ul class="small mb-2">
              <li>One-by-one, back off</li><li>Random soal & opsi</li><li>Token aktif</li><li>Guard ketat</li>
            </ul>
            <button class="btn btn-outline-info text-dark btn-sm w-100"><i class="fa fa-check me-1"></i>Pakai Preset</button>
          </div></div>
          <div class="col-lg-4"><div class="glass p-3 h-100">
            <h6 class="mb-2">Preset “Latihan Bebas”</h6>
            <ul class="small mb-2">
              <li>Scroll bebas, back on</li><li>Tanpa token</li><li>Jawaban & pembahasan tampil</li>
            </ul>
            <button class="btn btn-outline-info text-dark btn-sm w-100">Pakai Preset</button>
          </div></div>
          <div class="col-lg-4"><div class="glass p-3 h-100">
            <h6 class="mb-2">Preset “Esai Fokus”</h6>
            <ul class="small mb-2">
              <li>Esai + rubrik</li><li>Time per-section</li><li>Moderasi ganda</li>
            </ul>
            <button class="btn btn-outline-info text-dark btn-sm w-100">Pakai Preset</button>
          </div></div>
        </div>
      </div>
    </section>

    <!-- SECTION: PENGATURAN -->
    <section id="sec-pengaturan" class="section">
      <div class="glass p-3 mb-3">
        <h5 class="mb-0"><i class="fa fa-gear me-2"></i>Pengaturan CBT</h5>
      </div>
      <div class="glass p-3">
        <div class="row g-3">
          <div class="col-lg-4">
            <label class="form-label small">Ambang Pelanggaran</label>
            <input type="number" class="form-control" value="3" min="1" max="9">
          </div>
          <div class="col-lg-4">
            <label class="form-label small">Penalti per Pelanggaran</label>
            <select class="form-select"><option>-2 poin</option><option>-5 poin</option><option>Diskualifikasi</option></select>
          </div>
          <div class="col-lg-4">
            <label class="form-label small">Mode</label>
            <select class="form-select"><option>Ketat (disarankan)</option><option>Sedang</option><option>Longgar</option></select>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" checked id="swEmbed">
              <label class="form-check-label" for="swEmbed">Izinkan Embed Mode (PWA & preload media)</label>
            </div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-success text-dark"><i class="fa fa-floppy-disk me-1"></i>Simpan</button>
            <button class="btn btn-outline-light"><i class="fa fa-rotate me-1"></i>Reset</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Floating CTA -->
    <button class="floating-cta" data-section-jump="sesi"><i class="fa fa-rocket me-2"></i>Mulai Sesi Cepat</button>

  </main>
</div>

<!-- About Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass">
      <div class="modal-header border-0">
        <h6 class="modal-title"><i class="fa fa-circle-info me-2"></i>Tentang Demo CBT Pro</h6>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Halaman ini adalah <b>simulasi navigasi & fitur</b> CBT Pro. Gunanya untuk presentasi, eksplorasi UX, dan menjadi dasar implementasi modul sebenarnya.</p>
        <ul class="mb-0">
          <li>Semua data masih dummy.</li>
          <li>Live monitoring mensimulasikan progres & status peserta.</li>
          <li>Grafik analitik menampilkan contoh tampilan report.</li>
        </ul>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-outline-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Toasts -->
<div class="toast-container position-fixed top-0 end-0 p-3">
  <div id="toastInfo" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body"><i class="fa fa-sparkles me-2"></i>Selamat datang! Klik menu kiri untuk mencoba tiap fitur.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===== Helper switch section ===== */
const btns = document.querySelectorAll('.menu-btn');
const sections = {
  gform: document.getElementById('sec-gform'),
  dash: document.getElementById('sec-dash'),
  bank: document.getElementById('sec-bank'),
  paket: document.getElementById('sec-paket'),
  sesi: document.getElementById('sec-sesi'),
  monitor: document.getElementById('sec-monitor'),
  pelanggaran: document.getElementById('sec-pelanggaran'),
  koreksi: document.getElementById('sec-koreksi'),
  hasil: document.getElementById('sec-hasil'),
  analitik: document.getElementById('sec-analitik'),
  susulan: document.getElementById('sec-susulan'),
  template: document.getElementById('sec-template'),
  pengaturan: document.getElementById('sec-pengaturan')
};
function setActive(sec){
  // toggle buttons
  btns.forEach(b => {
    b.classList.toggle('active', b.dataset.section===sec);
  });
  // toggle sections
  Object.entries(sections).forEach(([k,el]) => el.classList.toggle('active', k===sec));
  // scroll to top
  document.querySelector('.app-main').scrollTo({top:0, behavior:'smooth'});
}
btns.forEach(b => b.addEventListener('click', () => setActive(b.dataset.section)));
document.querySelectorAll('[data-section-jump]').forEach(a => a.addEventListener('click', (e)=>{ e.preventDefault(); setActive(a.dataset.sectionJump); }));

/* ===== Toast welcome ===== */
const t = new bootstrap.Toast(document.getElementById('toastInfo'), {delay:2200});
setTimeout(()=>t.show(), 600);

/* ===== Chart: Tren Nilai ===== */
const chartNilai = new Chart(document.getElementById('chartNilai'),{
  type:'line',
  data:{
    labels:['M1','M2','M3','M4','M5','M6','M7'],
    datasets:[{label:'Nilai', data:[72,74,78,80,77,84,86]}]
  },
  options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{min:50,max:100}}}
});

/* ===== Chart: Distribusi Nilai ===== */
const chartDistribusi = new Chart(document.getElementById('chartDistribusi'),{
  type:'bar',
  data:{
    labels:['<60','60-69','70-79','80-89','90+'],
    datasets:[{label:'Jumlah Siswa', data:[4,7,18,22,9]}]
  },
  options:{responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
});

/* ===== Simulasi Monitoring Table ===== */
const tblBody = document.querySelector('#tblMonitor tbody');
const names = ['Arman','Rani','Bagas','Naya','Rio','Salsa','Dimas','Nabila','Rama','Dewi','Tito','Rafa','Sari','Gilang','Putri','Reno','Alya','Zidan','Riko','Anin'];
function rand(min,max){ return Math.floor(Math.random()*(max-min+1))+min; }
const rows = names.slice(0,14).map((n,i)=>({
  no:i+1, nama:n+' 8A', kelas:'8A', prog: rand(5,30), time: 90-rand(0,10), stat:'online', ip:`192.168.0.${rand(10,230)}`
}));
function statusDot(s){
  const cls = s==='online'?'dot-online':(s==='idle'?'dot-idle':'dot-off');
  const text = s[0].toUpperCase()+s.slice(1);
  return `<span class="status-dot ${cls}"></span>${text}`;
}
function drawRows(){
  tblBody.innerHTML = rows.map(r=>`
    <tr>
      <td>${r.no}</td>
      <td>${r.nama}</td>
      <td>${r.kelas}</td>
      <td>${statusDot(r.stat)}</td>
      <td><div class="progress" style="height:8px"><div class="progress-bar" style="width:${r.prog}%"></div></div></td>
      <td>${r.time}’</td>
      <td><code>${r.ip}</code></td>
    </tr>`).join('');
}
drawRows();
// tick update
setInterval(()=>{
  rows.forEach(r=>{
    r.prog = Math.min(100, r.prog + rand(0,3));
    r.time = Math.max(0, r.time - 1);
    // random status flip
    const k = rand(1,20);
    if(k===3) r.stat = 'idle';
    if(k===4) r.stat = 'offline';
    if(k>4) r.stat = 'online';
  });
  drawRows();
}, 1500);
</script>
</body>
</html>
