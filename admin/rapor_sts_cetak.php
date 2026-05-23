<?php
/**
 * EPS — Rapor STS: Preview & Panel Cetak
 * v2025-10-19.r10
 * - Hapus kolom "Cetak Ulang" pada tabel Daftar Siswa (beserta tombol & handler per-baris)
 * - Versi r9 lainnya tetap dipertahankan (mobile-first form, Preview badge, dll)
 * - Bind_param FIX (iiiiiis) tetap
 * - REVISI TERBARU: Penambahan Filter Semester di UI & Sinkronisasi ke Modul Print/Generate
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (function_exists('mysqli_report')) @mysqli_report(MYSQLI_REPORT_OFF);

function _json_out($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr); exit; }
function _ok($data=array(), $extra=array()){ return array_merge(array('ok'=>true,'data'=>$data), $extra); }
function _fail($msg='Terjadi kesalahan'){ return array('ok'=>false,'error'=>$msg); }

// Helper auto-deteksi semester berjalan
function current_semester(){
  $m = (int)date('n'); // 1-12
  return ($m >= 7 && $m <= 12) ? 1 : 2; // Jul–Des = 1, Jan–Jun = 2
}

$IS_AJAX = isset($_GET['ajax']) && $_GET['ajax'] !== '';

/* ===================== KONEKSI DB ===================== */
$DB=null;
if ($IS_AJAX){
  require_once __DIR__ . '/../koneksi.php';
  $DB = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : (isset($mysqli)? $mysqli : null);
  if(!$DB) _json_out(_fail('Koneksi DB tidak tersedia'));
}else{
  $page_title    = 'Cetak Nilai Rapor Siswa';
  $judul_halaman = $page_title;
  $PAGE_TITLE    = $page_title;
  include __DIR__ . '/header.php';
  $DB = isset($koneksi) && $koneksi instanceof mysqli ? $koneksi : (isset($mysqli)? $mysqli : null);
  if(!$DB){
    echo '<div class="content-wrapper"><section class="content"><div class="alert alert-danger m-3">Koneksi database tidak tersedia.</div></section></div>';
    include __DIR__ . '/footer.php'; exit;
  }
}
@mysqli_set_charset($DB,'utf8mb4');

// Semester Aktif untuk Default Dropdown
$SEMESTER_AKTIF = current_semester();

/* ===================== BOOTSTRAP TABEL PENDUKUNG ===================== */
@mysqli_query($DB, "CREATE TABLE IF NOT EXISTS rapor_sts_print_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sekolah_id INT DEFAULT 1,
  kelas_id INT NULL,
  margin_kiri INT DEFAULT 20,
  margin_kanan INT DEFAULT 20,
  margin_atas INT DEFAULT 20,
  margin_bawah INT DEFAULT 10,
  halaman_pertama INT DEFAULT 1,
  ttd_opt ENUM('none','walikelas','kepsek','walikelas_kepsek') DEFAULT 'none',
  updated_by INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($DB, "CREATE TABLE IF NOT EXISTS rapor_sts_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  siswa_id INT NOT NULL,
  kelas_id INT NOT NULL,
  tahun_ajaran INT NULL,
  semester TINYINT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_hash CHAR(64) NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_siswa_kelas (siswa_id, kelas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($DB, "CREATE TABLE IF NOT EXISTS rapor_sts_publish (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  siswa_id INT NOT NULL,
  kelas_id INT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pub (siswa_id, kelas_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* =========================== ROUTER AJAX =========================== */
if ($IS_AJAX){
  $act = $_GET['ajax'];

  if ($act==='kelas'){
    $rows=[]; $q=@mysqli_query($DB,"SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama ASC");
    if($q){ while($r=@mysqli_fetch_assoc($q)) $rows[]=$r; }
    _json_out(_ok($rows));
  }

  // PADA BAGIAN SISWA, SEHARUSNYA TARIK FILE SESUAI SEMESTER, TAPI TABEL FILE CUMA BISA 1 UNIQUE UNTUK SMT INI. 
  // Kita sesuaikan query untuk mencari file berdasarkan semester juga jika tersedia.
  if ($act==='siswa'){
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : $SEMESTER_AKTIF;
    
    if($kelas_id<=0) _json_out(_fail('Kelas tidak valid'));

    // Left join file rapor difilter berdasarkan semester (jika tersedia kolomnya)
    $sql="SELECT s.siswa_id, s.siswa_nama, s.siswa_nis, k.kelas_nama,
                 f.file_path, IFNULL(p.is_published,0) AS is_published
          FROM kelas_siswa ks
          JOIN siswa s ON s.siswa_id=ks.ks_siswa
          JOIN kelas k ON k.kelas_id=ks.ks_kelas
          LEFT JOIN rapor_sts_files f ON f.siswa_id=s.siswa_id AND f.kelas_id=k.kelas_id AND f.semester=?
          LEFT JOIN rapor_sts_publish p ON p.siswa_id=s.siswa_id AND p.kelas_id=k.kelas_id
          WHERE ks.ks_kelas=? ORDER BY s.siswa_nama ASC";
    $st=@mysqli_prepare($DB,$sql); if(!$st) _json_out(_fail('Query gagal dipersiapkan'));
    @mysqli_stmt_bind_param($st,'ii',$semester,$kelas_id);
    @mysqli_stmt_execute($st);
    $res=@mysqli_stmt_get_result($st);

    $out=[]; if($res){ while($r=@mysqli_fetch_assoc($res)){ $r['siswa_nisn']='-'; $out[]=$r; } }
    _json_out(_ok($out));
  }

  if ($act==='save_setting' && $_SERVER['REQUEST_METHOD']==='POST'){
    $margin_kiri=max(0,(int)($_POST['margin_kiri']??20));
    $margin_kanan=max(0,(int)($_POST['margin_kanan']??20));
    $margin_atas=max(0,(int)($_POST['margin_atas']??20));
    $margin_bawah=max(0,(int)($_POST['margin_bawah']??10));
    $halaman_pertama=max(1,(int)($_POST['halaman_pertama']??1));
    $ttd_opt=isset($_POST['ttd_opt'])?(string)$_POST['ttd_opt']:'none';
    $kelas_id=isset($_POST['kelas_id']) && $_POST['kelas_id']!=='' ? (int)$_POST['kelas_id'] : null;
    $allowed=['none','walikelas','kepsek','walikelas_kepsek'];
    if(!in_array($ttd_opt,$allowed,true)) $ttd_opt='none';

    if($kelas_id){
      $st=@mysqli_prepare($DB,"INSERT INTO rapor_sts_print_config
        (sekolah_id,kelas_id,margin_kiri,margin_kanan,margin_atas,margin_bawah,halaman_pertama,ttd_opt,updated_by)
        VALUES(1,?,?,?,?,?,?,?,NULL)
        ON DUPLICATE KEY UPDATE
          margin_kiri=VALUES(margin_kiri), margin_kanan=VALUES(margin_kanan), margin_atas=VALUES(margin_atas),
          margin_bawah=VALUES(margin_bawah), halaman_pertama=VALUES(halaman_pertama),
          ttd_opt=VALUES(ttd_opt), updated_at=CURRENT_TIMESTAMP");
      @mysqli_stmt_bind_param($st,'iiiiiis',$kelas_id,$margin_kiri,$margin_kanan,$margin_atas,$margin_bawah,$halaman_pertama,$ttd_opt);
      @mysqli_stmt_execute($st);
    }else{
      @mysqli_query($DB,"DELETE FROM rapor_sts_print_config WHERE kelas_id IS NULL LIMIT 1");
      $st=@mysqli_prepare($DB,"INSERT INTO rapor_sts_print_config
        (sekolah_id,kelas_id,margin_kiri,margin_kanan,margin_atas,margin_bawah,halaman_pertama,ttd_opt,updated_by)
        VALUES(1,NULL,?,?,?,?,?,?,NULL)");
      @mysqli_stmt_bind_param($st,'iiiiis',$margin_kiri,$margin_kanan,$margin_atas,$margin_bawah,$halaman_pertama,$ttd_opt);
      @mysqli_stmt_execute($st);
    }
    _json_out(_ok([],['message'=>'Pengaturan tersimpan.']));
  }

  if ($act==='toggle_publish' && $_SERVER['REQUEST_METHOD']==='POST'){
    $siswa_id=(int)($_POST['siswa_id']??0);
    $kelas_id=(int)($_POST['kelas_id']??0);
    $val=(int)($_POST['val']??0);
    if($siswa_id<=0 || $kelas_id<=0) _json_out(_fail('Data tidak valid'));
    $st=@mysqli_prepare($DB,"INSERT INTO rapor_sts_publish (siswa_id,kelas_id,is_published)
      VALUES(?,?,?) ON DUPLICATE KEY UPDATE is_published=VALUES(is_published),updated_at=CURRENT_TIMESTAMP");
    @mysqli_stmt_bind_param($st,'iii',$siswa_id,$kelas_id,$val);
    @mysqli_stmt_execute($st);
    _json_out(_ok([],['message'=>'Status berhasil diperbarui']));
  }

  _json_out(_fail('Aksi tidak dikenali'));
}

/* ===================== DROPDOWN KELAS (server render) ===================== */
$kelas_opts=[]; $q=@mysqli_query($DB,"SELECT kelas_id, kelas_nama FROM kelas ORDER BY kelas_nama ASC");
if($q){ while($r=@mysqli_fetch_assoc($q)) $kelas_opts[]=$r; }
?>
<style>
  :root{
    --eps-blue:#0B57D0; --eps-blue2:#1E66F5;
    --ink:#0f172a; --muted:#64748b; --line:#eef2f7; --soft:#f5f8ff; --brand:#0057ff;
    --success:#16a34a; --danger:#ef4444; --amber:#f59e0b; --bg:#f6f9ff; --chip:#F6F9FF; --chipb:#D7E6FF;
  }
  .eps-title-h1{margin:0 0 2px;font-weight:700;font-size:22px;color:var(--ink); letter-spacing:.1px}
  .eps-sub{font-size:12px;color:var(--muted)}

  .eps-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 10px 28px rgba(15,76,161,.08);overflow:hidden;margin-bottom:16px}
  .eps-card .eps-hd{background:linear-gradient(90deg,var(--eps-blue),var(--eps-blue2));color:#fff;font-weight:800;padding:12px 14px;letter-spacing:.3px}
  .eps-card .eps-bd{padding:14px}

  /* ====== Form Pengaturan: Mobile-first responsive ====== */
  .grid{display:grid;gap:10px}
  .grid-6{grid-template-columns:repeat(6,minmax(0,1fr))}
  @media (max-width: 1280px){ .grid-6{grid-template-columns:repeat(3,minmax(0,1fr))} }
  @media (max-width: 992px){  .grid-6{grid-template-columns:repeat(2,minmax(0,1fr))} }
  @media (max-width: 600px){  .grid-6{grid-template-columns:1fr} }

  .field{display:flex;flex-direction:column;gap:6px; min-width:0}
  .field label{font-size:12px;color:#334155;font-weight:700}
  .field input[type=number], .field select{width:100%;height:40px;border:1px solid #d9e2f1;border-radius:12px;padding:8px 10px;outline:none}
  .field input:focus, .field select:focus{box-shadow:0 0 0 3px rgba(59,130,246,.15);border-color:#c6d7ff}
  @media (max-width: 768px){
    #formSetting .field[style*="grid-column:span 3"]{grid-column:span 1 !important}
    #formSetting .field[style*="grid-column:span 3"]{display:flex;flex-wrap:wrap}
    #formSetting .field[style*="grid-column:span 3"] .btn{flex:1 1 48%; min-width:140px}
  }
  @media (max-width: 480px){
    #formSetting .field[style*="grid-column:span 3"] .btn{flex:1 1 100%}
  }

  .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .btn{position:relative;display:inline-flex;align-items:center;gap:10px;border:0;border-radius:12px;padding:10px 14px;font-weight:800;cursor:pointer;transition:transform .06s ease, box-shadow .12s ease, opacity .2s}
  .btn:active{transform:translateY(1px)}
  .btn .fa{font-size:14px}
  .btn-eps{color:#fff;background:linear-gradient(90deg,var(--eps-blue),var(--eps-blue2));box-shadow:0 8px 18px rgba(30,102,245,.25)}
  .btn-eps:hover{box-shadow:0 10px 22px rgba(30,102,245,.35)}
  .btn-soft{border:1px solid var(--chipb);background:var(--soft);color:#0B57D0;font-weight:800}
  .btn-ghost{background:#f1f5f9;color:#64748b;border:1px dashed #cbd5e1}
  .btn[disabled], .btn.is-disabled{opacity:.55;cursor:not-allowed;filter:grayscale(.2)}
  .btn-tip{position:relative}
  .btn-tip[aria-disabled="true"]::after{
    content:attr(data-tip); position:absolute; top:calc(100% + 6px); left:0;
    background:#111;color:#fff;font-size:11px;padding:6px 8px;border-radius:8px;white-space:nowrap;opacity:0;transform:translateY(-2px);pointer-events:none;transition:all .15s;
  }
  .btn-tip[aria-disabled="true"]:hover::after{opacity:1;transform:translateY(0)}

  .ico{width:20px;height:20px;border-radius:6px;display:inline-grid;place-items:center}
  .ico-bolt{background:linear-gradient(135deg,#FFF7ED,#FFEDD5); color:#d97706; box-shadow:inset 0 0 0 1px #fed7aa}
  .ico-print{background:rgba(255,255,255,.25); color:#fff}
  .btn-ghost .ico-bolt{background:#e2e8f0; color:#94a3b8; box-shadow:none}

  .muted{color:#6b7280;font-size:13px}

  .tbl-wrap{border:1px solid var(--line);border-radius:14px;overflow:auto;background:#fff}
  table.eps{width:100%;border-collapse:collapse}
  table.eps thead th{background:#f3f6fb;padding:10px;text-transform:uppercase;font-size:12px;letter-spacing:.04em}
  table.eps tbody td{padding:10px;border-top:1px solid var(--line)}
  table.eps tbody tr:hover{background:#fafafa}

  /* Badge/Chip */
  .chip{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:var(--chip);border:1px solid var(--chipb);color:#0B57D0;font-weight:800;font-size:12px;text-decoration:none}
  .chip i{font-size:12px}
  .chip-preview{background:linear-gradient(180deg,#EEF6FF,#F8FBFF);border-color:#CFE2FF;color:#0B57D0}
  .chip-pdf{background:#FFF1F2;border-color:#FECACA;color:#B91C1C}

  .callout{
    display:flex; align-items:center; gap:10px; padding:10px 12px; border:1px dashed #c7d8ff; background:linear-gradient(90deg,#EEF5FF,#F9FBFF);
    border-radius:12px; color:#0B57D0; font-weight:700;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.6);
  }
  .callout .pulse{width:10px;height:10px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.6);animation:pulse 1.8s infinite}
  @keyframes pulse{to{box-shadow:0 0 0 14px rgba(34,197,94,0)}}

  .eps-toast{position:fixed;right:18px;top:18px;z-index:9999;min-width:220px;max-width:360px;background:var(--success);color:#fff;border-radius:14px;box-shadow:0 10px 28px rgba(22,163,74,.35);padding:12px 14px;display:flex;align-items:center;gap:10px;font-weight:800;opacity:0;transform:translateY(-8px);transition:opacity .12s,transform .12s}
  .eps-toast.show{opacity:1;transform:translateY(0)}
  .eps-toast .ico{width:22px;height:22px;display:grid;place-items:center;background:rgba(255,255,255,.2);border-radius:999px}
  .eps-toast.err{background:var(--danger);box-shadow:0 10px 28px rgba(239,68,68,.35)}

  .eps-modal{position:fixed;inset:0;background:rgba(17,24,39,.55);display:none;align-items:center;justify-content:center;z-index:9998}
  .eps-modal.show{display:flex}
  .eps-modal .box{background:#fff;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.2);padding:18px 20px;min-width:320px;display:flex;gap:12px;align-items:center}
  .spinner{width:30px;height:30px;border-radius:50%;border:3px solid #e5e7eb;border-top-color:#2563eb;animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}

  .spin-left{animation:spinL .6s ease}
  @keyframes spinL{from{transform:rotate(0)} to{transform:rotate(-360deg)}}

  #btnTop{
    position:fixed; right:18px; bottom:22px; width:38px; height:38px; border-radius:999px; border:0;
    background:linear-gradient(180deg,#3b82f6,#1d4ed8); color:#fff; box-shadow:0 10px 22px rgba(37,99,235,.35);
    display:none; align-items:center; justify-content:center; cursor:pointer; z-index:9997;
  }
  #btnTop.show{display:flex}
  #btnTop:active{transform:translateY(1px)}
  #btnTop .fa{font-size:16px}
</style>

<div class="content-wrapper">

  <section class="content-header" style="padding:15px 15px 0 15px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <span style="width:36px;height:36px;border-radius:10px;background:#E8F1FF;color:#0B57D0;display:inline-flex;align-items:center;justify-content:center">
        <i class="fa fa-print"></i>
      </span>
      <div>
        <h1 class="eps-title-h1">Cetak Nilai Rapor Siswa</h1>
        <div class="eps-sub">Panel pengaturan & pratinjau rapor STS</div>
      </div>
    </div>
  </section>

  <section class="content" style="padding:10px 15px 15px 15px">

    <div class="eps-card">
      <div class="eps-hd"><i class="fa fa-sliders"></i> &nbsp;Pengaturan Hasil Cetak</div>
      <div class="eps-bd">
        <form id="formSetting" class="grid grid-6" onsubmit="return false;">
          <div class="field">
            <label><i class="fa fa-file-o"></i> Ukuran Kertas</label>
            <select disabled><option>A4</option></select>
            <div class="muted">Saat ini terkunci ke A4</div>
          </div>
          <div class="field"><label><i class="fa fa-arrows-h"></i> Margin Kiri (mm)</label><input type="number" id="mLeft" value="20" min="0"></div>
          <div class="field"><label><i class="fa fa-arrows-h"></i> Margin Kanan (mm)</label><input type="number" id="mRight" value="20" min="0"></div>
          <div class="field"><label><i class="fa fa-arrows-v"></i> Margin Atas (mm)</label><input type="number" id="mTop" value="20" min="0"></div>
          <div class="field"><label><i class="fa fa-arrows-v"></i> Margin Bawah (mm)</label><input type="number" id="mBottom" value="10" min="0"></div>
          <div class="field"><label><i class="fa fa-hashtag"></i> Halaman Pertama</label><input type="number" id="firstPage" value="1" min="1"></div>
          
          <div class="field">
            <label><i class="fa fa-calendar-check-o"></i> Pilih Semester</label>
            <select id="optSemester">
                <option value="1" <?php echo $SEMESTER_AKTIF == 1 ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?php echo $SEMESTER_AKTIF == 2 ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
            </select>
          </div>

          <div class="field">
            <label><i class="fa fa-university"></i> Pilih Kelas</label>
            <select id="optKelas">
              <option value="">— Pilih Kelas —</option>
              <?php foreach($kelas_opts as $k): ?>
                <option value="<?php echo (int)$k['kelas_id']; ?>"><?php echo htmlspecialchars($k['kelas_nama']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="grid-column:span 1">
            <label><i class="fa fa-pencil-square-o"></i> Isi Tanda Tangan</label>
            <select id="optTTD">
              <option value="none">Tanpa Tanda Tangan</option>
              <option value="walikelas">Wali Kelas</option>
              <option value="kepsek">Kepala Sekolah</option>
              <option value="walikelas_kepsek">Wali Kelas & Kepala Sekolah</option>
            </select>
            <div class="muted">Scan TTD tersimpan → disisipkan otomatis di atas nama & NIP.</div>
          </div>
          <div class="field" style="grid-column:span 3; display:flex; align-items:end; gap:8px">
            <button type="button" class="btn btn-soft" id="btnReset">
              <i class="fa fa-undo" id="icoReset"></i> Reset
            </button>
            <button type="button" class="btn btn-eps" id="btnSimpan"><i class="fa fa-save"></i> Simpan Pengaturan</button>
          </div>
        </form>
      </div>
    </div>

    <div class="callout" id="calloutKelas" style="margin-bottom:10px">
      <div class="pulse"></div>
      <div><i class="fa fa-info-circle"></i> &nbsp;<span id="infoKelas">Pilih kelas terlebih dahulu agar tombol Generate & Cetak aktif.</span></div>
    </div>

    <div class="toolbar" style="margin-bottom:10px">
      <button class="btn btn-ghost btn-tip" id="btnGenerate" aria-disabled="true" disabled data-tip="Pilih kelas dulu">
        <span class="ico ico-bolt"><i class="fa fa-bolt"></i></span>
        <span>Generate Rapor Kelas ini</span>
      </button>
      <button class="btn btn-ghost btn-tip" id="btnCetak" aria-disabled="true" disabled data-tip="Pilih kelas dulu">
        <span class="ico ico-print"><i class="fa fa-print"></i></span>
        <span>Cetak Langsung Rapor</span>
      </button>
    </div>

    <div class="eps-card">
      <div class="eps-hd"><i class="fa fa-users"></i> &nbsp;Daftar Siswa</div>
      <div class="eps-bd">
        <div class="tbl-wrap">
          <table class="eps" id="tbl">
            <thead>
              <tr>
                <th style="width:50px">NO</th>
                <th>NAMA SISWA</th>
                <th>NISN</th>
                <th>NIS</th>
                <th>ROMBEL</th>
                <th>FILE PDF/HTML</th>
                <th style="width:200px">TAMPILKAN PADA SISWA</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="7" style="text-align:center;padding:22px;color:#6b7280">Menunggu kelas dipilih…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="epsToast" class="eps-toast" style="display:none">
      <div class="ico">✓</div><div class="txt">Tersimpan</div>
    </div>

    <div id="modalLoading" class="eps-modal" aria-hidden="true">
      <div class="box">
        <div class="spinner"></div>
        <div>
          <div style="font-weight:800;color:#111">Sedang memproses…</div>
          <div class="muted" id="modalText">Generate rapor kelas sedang berjalan. Mohon tunggu.</div>
        </div>
      </div>
    </div>

    <button id="btnTop" title="Kembali ke atas" aria-label="Kembali ke atas"><i class="fa fa-arrow-up"></i></button>

  </section>
</div>

<script>
(function(){
  const ENDPOINT = '<?php echo basename(__FILE__); ?>';
  const $  = (q,root=document)=>root.querySelector(q);
  const $$ = (q,root=document)=>Array.from(root.querySelectorAll(q));

  function api(act, params={}, method='GET'){
    let url = ENDPOINT + '?ajax=' + encodeURIComponent(act);
    const opt = { method: method, headers: {} };
    if (method === 'POST') { opt.headers['Content-Type']='application/x-www-form-urlencoded; charset=UTF-8'; opt.body = new URLSearchParams(params).toString(); }
    else { const qs = new URLSearchParams(params).toString(); if (qs) url += '&' + qs; }
    return fetch(url, opt).then(r=>r.json());
  }
  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  // Toast
  const toastEl = $('#epsToast'); let toastTimer=null;
  function showToast(msg,type='success'){
    toastEl.querySelector('.txt').textContent = msg||'';
    toastEl.style.display='flex';
    toastEl.classList.remove('err'); toastEl.querySelector('.ico').textContent = '✓';
    if(type==='error'){ toastEl.classList.add('err'); toastEl.querySelector('.ico').textContent='!'; }
    requestAnimationFrame(()=>toastEl.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer=setTimeout(()=>{ toastEl.classList.remove('show'); setTimeout(()=>toastEl.style.display='none',150); }, 2000);
  }

  // Modal loader
  const modal = $('#modalLoading'); const modalText = $('#modalText');
  function showModal(text){ modalText.textContent = text || 'Sedang diproses…'; modal.classList.add('show'); }
  function hideModal(){ modal.classList.remove('show'); }

  // Controls
  const optKelas   = $('#optKelas');
  const optSemester= $('#optSemester'); // Tambahan: Dropdown Semester
  const infoKelas  = $('#infoKelas');
  const callout    = $('#calloutKelas');
  const tblBody    = $('#tbl tbody');
  const btnGen     = $('#btnGenerate');
  const btnCetak   = $('#btnCetak');

  function setToolbarEnabled(enabled, kelasText='', semesterText=''){
    if (enabled){
      btnGen.disabled=false; btnCetak.disabled=false;
      btnGen.classList.remove('btn-ghost','is-disabled'); btnCetak.classList.remove('btn-ghost','is-disabled');
      btnGen.classList.add('btn-eps'); btnCetak.classList.add('btn-eps');
      btnGen.setAttribute('aria-disabled','false'); btnCetak.setAttribute('aria-disabled','false');
      btnGen.removeAttribute('data-tip'); btnCetak.removeAttribute('data-tip');
      infoKelas.textContent = 'Kelas terpilih: ' + kelasText + ' | Semester: ' + semesterText;
      callout.style.display = 'none';
    }else{
      btnGen.disabled=true; btnCetak.disabled=true;
      btnGen.classList.add('btn-ghost','is-disabled'); btnCetak.classList.add('btn-ghost','is-disabled');
      btnGen.classList.remove('btn-eps'); btnCetak.classList.remove('btn-eps');
      btnGen.setAttribute('aria-disabled','true'); btnCetak.setAttribute('aria-disabled','true');
      btnGen.setAttribute('data-tip','Pilih kelas dulu'); btnCetak.setAttribute('data-tip','Pilih kelas dulu');
      infoKelas.textContent = 'Pilih kelas terlebih dahulu agar tombol Generate & Cetak aktif.';
      callout.style.display = 'flex';
    }
  }
  setToolbarEnabled(false);

  // Trigger Reload Data Jika Kelas / Semester Diubah
  optKelas.addEventListener('change', loadSiswa);
  optSemester.addEventListener('change', loadSiswa); 

  async function loadSiswa(){
    const kid = optKelas.value;
    const smt = optSemester.value; // Ambil nilai semester
    const ktext = optKelas.options[optKelas.selectedIndex]?.text || '';
    const smtext = optSemester.options[optSemester.selectedIndex]?.text || '';
    
    setToolbarEnabled(!!kid, ktext, smtext);

    if (!kid) {
      tblBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:22px;color:#6b7280">Menunggu kelas dipilih…</td></tr>`;
      return;
    }
    tblBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:22px;color:#6b7280">Memuat data…</td></tr>`;
    try{
      // Mengirim kelas_id DAN semester ke backend
      const res = await api('siswa', {kelas_id: kid, semester: smt});
      if (!res.ok) throw new Error(res.error || 'Gagal memuat');
      if (!(res.data && res.data.length)) {
        tblBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:22px;color:#6b7280">Belum ada siswa di kelas ini.</td></tr>`;
        return;
      }
      let i=1, html='';
      res.data.forEach(s=>{
        const isPdf = (s.file_path||'').toLowerCase().endsWith('.pdf');
        
        // --- PENYEMPURNAAN TOMBOL PREVIEW ---
        // Jika file PDF belum digenerate, ubah href untuk mencetak langsung dengan membawa ID Siswa, Kelas & Semester ke cetak.php!
        let linkCetakFallback = `rapor_sts_print.php?kelas_id=${encodeURIComponent(kid)}&siswa_id=${encodeURIComponent(s.siswa_id)}&semester=${encodeURIComponent(smt)}`;
        
        const link = s.file_path
          ? (isPdf
              ? `<a class="chip chip-pdf" href="${s.file_path}" target="_blank"><i class="fa fa-file-pdf-o"></i> Lihat PDF</a>`
              : `<a class="chip chip-preview" href="${s.file_path}" target="_blank"><i class="fa fa-eye"></i> Preview HTML</a>`)
          : `<a class="chip chip-preview" href="${linkCetakFallback}" target="_blank" title="Cetak langsung tanpa simpan file"><i class="fa fa-eye"></i> Web Preview</a>`;
        
        html += `<tr>
          <td>${i++}</td>
          <td><b>${escapeHtml(s.siswa_nama||'')}</b></td>
          <td>-</td>
          <td>${escapeHtml(s.siswa_nis||'-')}</td>
          <td><span class="chip">${escapeHtml(s.kelas_nama||'-')}</span></td>
          <td>${link}</td>
          <td>
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="checkbox" class="tgl" data-sid="${s.siswa_id}" data-kid="${kid}" ${Number(s.is_published)===1?'checked':''}>
              <span>${Number(s.is_published)===1?'<span class="chip" style="background:#ecfdf5;border-color:#bbf7d0;color:#047857">Tampil</span>':'<span class="chip" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c">Sembunyi</span>'}</span>
            </label>
          </td>
        </tr>`;
      });
      tblBody.innerHTML = html;
      showToast('Data siswa dimuat');
    }catch(e){
      tblBody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:22px;color:#ef4444">Error: ${e.message}</td></tr>`;
      showToast(e.message || 'Gagal memuat', 'error');
    }
  }

  // Simpan pengaturan
  $('#btnSimpan').addEventListener('click', async (ev)=>{
    const btn = ev.currentTarget; btn.disabled = true;
    const payload = {
      margin_kiri: $('#mLeft').value||20,
      margin_kanan: $('#mRight').value||20,
      margin_atas: $('#mTop').value||20,
      margin_bawah: $('#mBottom').value||10,
      halaman_pertama: $('#firstPage').value||1,
      ttd_opt: $('#optTTD').value,
      kelas_id: $('#optKelas').value || ''
    };
    try{
      const res = await api('save_setting', payload, 'POST');
      if(!res.ok) throw new Error(res.error||'Gagal menyimpan');
      showToast(res.message || 'Tersimpan');
    }catch(e){ showToast(e.message || 'Gagal menyimpan', 'error'); }
    btn.disabled=false;
  });

  // Reset + animasi rotate kiri pada ikon
  $('#btnReset').addEventListener('click', ()=>{
    $('#mLeft').value=20; $('#mRight').value=20; $('#mTop').value=20; $('#mBottom').value=10; $('#firstPage').value=1; $('#optTTD').value='none';
    const ico = $('#icoReset');
    if (ico){
      ico.classList.remove('spin-left'); void ico.offsetWidth;
      ico.classList.add('spin-left');
      setTimeout(()=>ico.classList.remove('spin-left'), 650);
    }
    showToast('Pengaturan direset');
  });

  // Toggle publish
  document.addEventListener('change', async (e)=>{
    const t=e.target.closest('.tgl');
    if(t){
      const val = t.checked?1:0;
      try{
        const res = await api('toggle_publish', {siswa_id:t.dataset.sid, kelas_id:t.dataset.kid, val:val}, 'POST');
        if(!res.ok) throw new Error(res.error||'Gagal');
        const chip = t.nextElementSibling;
        chip.innerHTML = t.checked
          ? '<span class="chip" style="background:#ecfdf5;border-color:#bbf7d0;color:#047857">Tampil</span>'
          : '<span class="chip" style="background:#fef2f2;border-color:#fecaca;color:#b91c1c">Sembunyi</span>';
        showToast(t.checked?'Ditampilkan ke siswa':'Disembunyikan dari siswa');
      }catch(err){
        t.checked=!t.checked;
        showToast(err.message||'Gagal mengubah status','error');
      }
    }
  });

  // 1) Generate Rapor Kelas ini (Menyertakan Semester)
  $('#btnGenerate').addEventListener('click', async ()=>{
    const kid = $('#optKelas').value;
    const smt = $('#optSemester').value; // Mengambil semester
    if (!kid){ showToast('Pilih kelas dulu', 'error'); return; }
    showModal('Generate rapor kelas sedang berjalan. Mohon tunggu…');
    try{
      const resp = await fetch('rapor_sts_generate.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        // Menambahkan semester ke parameter payload Generate
        body:new URLSearchParams({kelas_id:kid, semester:smt, to_pdf:1}).toString() 
      });
      const txt = await resp.text();
      let res = null;
      try { res = JSON.parse(txt); } catch(e){ throw new Error('Server mengirim respons non-JSON:\n'+txt.slice(0,300)); }
      if(!res.ok) throw new Error(res.error||'Gagal generate');
      hideModal();
      showToast(`Generate selesai: ${res.generated} OK${res.failed? ', '+res.failed+' gagal':''}`);
      $('#optKelas').dispatchEvent(new Event('change')); // Trigger reload data siswa
    }catch(e){
      hideModal();
      showToast(e.message || 'Gagal generate', 'error');
    }
  });

  // 2) Cetak Langsung Rapor (Menyertakan Semester)
  $('#btnCetak').addEventListener('click', ()=>{
    const kid = $('#optKelas').value;
    const smt = $('#optSemester').value; // Mengambil semester
    if (!kid){ showToast('Pilih kelas dulu', 'error'); return; }
    // Modifikasi URL Print membawa semester
    window.open('rapor_sts_print.php?kelas_id='+encodeURIComponent(kid)+'&semester='+encodeURIComponent(smt), '_blank');
  });

  // FAB Back To Top
  const btnTop = $('#btnTop');
  window.addEventListener('scroll', ()=>{
    if (window.scrollY > 420) btnTop.classList.add('show'); else btnTop.classList.remove('show');
  });
  btnTop.addEventListener('click', ()=>window.scrollTo({top:0, behavior:'smooth'}));

})();
</script>

<?php include __DIR__ . '/footer.php'; ?>