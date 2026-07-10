<?php
// admin/kode_generator.php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../koneksi.php';
require_once __DIR__.'/../includes/epoin_security.php';

// [SECURITY] Generator kode lisensi = sensitif. Wajib login admin sebelum akses.
// PIN di bawah kini hanya faktor kedua (defense-in-depth), bukan satu-satunya gerbang.
epoin_staff_guard(true);

/* ===========================
   PIN GUARD (6 DIGIT)
   - Ubah PIN di $PIN_GUARD_SECRET
   - Simpan status di session: $_SESSION['KG_PIN_OK']
   =========================== */
$PIN_GUARD_ENABLED = true;
$PIN_GUARD_SECRET  = (string) epoin_env('KODE_PIN', '789789'); // set KODE_PIN di .env; fallback lama utk kompatibilitas
if (!isset($_SESSION['KG_PIN_OK'])) $_SESSION['KG_PIN_OK'] = false;

// optional: reset guard via ?reset_pin=1
if (isset($_GET['reset_pin'])) { unset($_SESSION['KG_PIN_OK']); header('Location: KODE.php'); exit; }

// terima submit pin
$pin_error = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pin_guard_submit'])) {
  // gabungkan 6 kotak input atau 1 input
  $pin = '';
  if (!empty($_POST['pin_digits']) && is_array($_POST['pin_digits'])) {
    foreach($_POST['pin_digits'] as $d){ $pin .= preg_replace('~\D~','',$d); }
  } else {
    $pin = preg_replace('~\D~','', $_POST['pin_guard_input'] ?? '');
  }
  if ($PIN_GUARD_ENABLED && $pin === $PIN_GUARD_SECRET) {
    $_SESSION['KG_PIN_OK'] = true;
    header('Location: KODE.php'); exit;
  } else {
    $pin_error = 'PIN salah. Coba lagi.';
  }
}

$__PIN_UNLOCKED = $_SESSION['KG_PIN_OK'] || !$PIN_GUARD_ENABLED;

// ===== Guard (hanya admin)
if (!function_exists('_is_admin')) { function _is_admin(){ return epoin_is_admin_session(); } }
if (!_is_admin()) { http_response_code(403); echo "Forbidden"; exit; }

// ===== Helpers
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function table_exists_local($db,$name){
  $q=@mysqli_query($db,"SHOW TABLES LIKE '".mysqli_real_escape_string($db,$name)."'");
  return $q && mysqli_num_rows($q)>0;
}
function ensure_license_codes_table($db){
  if (table_exists_local($db,'license_codes')) return;
  @mysqli_query($db, "CREATE TABLE IF NOT EXISTS license_codes(
    code_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    code_type ENUM('ACT','RENEW') NOT NULL,
    license_until DATE NOT NULL,
    valid_from DATE NULL,
    valid_until DATE NULL,
    max_uses INT NOT NULL DEFAULT 1,
    used_count INT NOT NULL DEFAULT 0,
    bound_domain VARCHAR(255) NULL,
    bound_school_id INT NULL,
    status ENUM('new','used','revoked') NOT NULL DEFAULT 'new',
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Random block base36 (A–Z,0–9)
function rand_block($len=5){
  $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  $out=''; for($i=0;$i<$len;$i++){ $out .= $chars[random_int(0, strlen($chars)-1)]; }
  return $out;
}
// Checksum base36 (crc32 → base36 fixed length)
function checksum36($payload, $len=4){
  $crc = sprintf('%u', crc32($payload));
  $b36 = strtoupper(base_convert($crc, 10, 36));
  return str_pad(substr($b36, -$len), $len, '0', STR_PAD_LEFT);
}

// ====== Generator format
// License Key: EPS-XXXXX-XXXXX-XXXXX-XXXXX-CCCC
function make_license_key(){
  $base = 'EPS-'.rand_block().'-'.rand_block().'-'.rand_block().'-'.rand_block();
  return $base.'-'.checksum36($base,4);
}
// Activation/Renew Code: ACT-YYYYMMDD-XXXXX-CCCC  /  RENEW-YYYYMMDD-XXXXX-CCCC
function make_act_code($type='ACT', $until=''){
  $type = ($type==='RENEW') ? 'RENEW' : 'ACT';
  $date = preg_replace('~[^0-9]~','',$until); // YYYY-MM-DD → YYYYMMDD
  if (strlen($date)===8 && strpos($until,'-')!==false) { /* ok */ }
  elseif (preg_match('~^[0-9]{8}$~',$until)) { $date = $until; }
  else { $date = date('Ymd'); }
  $base = $type.'-'.$date.'-'.rand_block();
  return $base.'-'.checksum36($base,4);
}

// ====== Handle POST (HANYA DIPROSES JIKA PIN SUDAH BENAR)
$generated_keys = [];
$generated_codes = [];

// simpan hasil terakhir di session agar bisa digabung
if (!isset($_SESSION['last_license_keys'])) $_SESSION['last_license_keys'] = [];
if (!isset($_SESSION['last_act_codes']))   $_SESSION['last_act_codes']   = [];

if ($__PIN_UNLOCKED && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['aksi'])){
  // Generate License Keys (tanpa DB)
  if (($_POST['aksi'] ?? '') === 'gen_license'){
    $n = max(1, min(100, (int)($_POST['jumlah'] ?? 1)));
    for($i=0;$i<$n;$i++){ $generated_keys[] = make_license_key(); }
    $_SESSION['last_license_keys'] = $generated_keys; // simpan utk gabungan
  }

  // Generate Activation/Renew Codes + simpan ke DB
  if (($_POST['aksi'] ?? '') === 'gen_codes'){
    ensure_license_codes_table($koneksi);

    $type      = (strtoupper(trim($_POST['code_type'] ?? 'ACT'))==='RENEW') ? 'RENEW' : 'ACT';
    // UI sekarang hanya 2 tanggal: valid_from & valid_until
    $vf        = trim($_POST['valid_from'] ?? '');
    $vu        = trim($_POST['valid_until'] ?? '');
    // safety: kalau license_until tidak dikirim, samakan dengan valid_until
    $until     = trim($_POST['license_until'] ?? '') ?: $vu;

    $max_uses  = max(1, (int)($_POST['max_uses'] ?? 1));
    $domain    = trim($_POST['bound_domain'] ?? '');
    $school_id = (int)($_POST['bound_school_id'] ?? 0);
    $note      = trim($_POST['note'] ?? '');
    $jumlah    = max(1, min(200, (int)($_POST['jumlah'] ?? 1)));

    // normalisasi tanggal
    $until_sql = $until ? date('Y-m-d', strtotime($until)) : date('Y-m-d');
    $vf_sql    = $vf    ? date('Y-m-d', strtotime($vf))    : null;
    $vu_sql    = $vu    ? date('Y-m-d', strtotime($vu))    : null;

    for($i=0;$i<$jumlah;$i++){
      // buat code unik
      do {
        $code = make_act_code($type, str_replace('-','',$until_sql));
        $esc  = mysqli_real_escape_string($koneksi,$code);
        $q    = mysqli_query($koneksi,"SELECT 1 FROM license_codes WHERE code='$esc' LIMIT 1");
      } while ($q && mysqli_fetch_row($q));

      // insert
      $sql = sprintf(
        "INSERT INTO license_codes
         (code,code_type,license_until,valid_from,valid_until,max_uses,used_count,bound_domain,bound_school_id,status,note)
         VALUES ('%s','%s','%s',%s,%s,%d,0,%s,%s,'new',%s)",
         mysqli_real_escape_string($koneksi,$code),
         $type,
         mysqli_real_escape_string($koneksi,$until_sql),
         $vf_sql ? "'".mysqli_real_escape_string($koneksi,$vf_sql)."'" : "NULL",
         $vu_sql ? "'".mysqli_real_escape_string($koneksi,$vu_sql)."'" : "NULL",
         $max_uses,
         $domain ? "'".mysqli_real_escape_string($koneksi,$domain)."'" : "NULL",
         $school_id ? (int)$school_id : "NULL",
         $note ? "'".mysqli_real_escape_string($koneksi,$note)."'" : "NULL"
      );
      mysqli_query($koneksi,$sql);

      $generated_codes[] = [
        'code'=>$code,
        'type'=>$type,
        'license_until'=>$until_sql,
        'valid_from'=>$vf_sql,
        'valid_until'=>$vu_sql,
        'max_uses'=>$max_uses,
        'bound_domain'=>$domain,
        'bound_school_id'=>$school_id
      ];
    }
    $_SESSION['last_act_codes'] = array_column($generated_codes, 'code'); // simpan utk gabungan
  }
}

// ===== UI
include __DIR__.'/header.php';
?>

<!-- ====== styling kecil agar compact & responsif + PIN overlay ====== -->
<style>
  .date-grid .form-group { margin-bottom:10px; }
  .date-grid .label-compact { font-size:12px; line-height:1.2; white-space:nowrap; }
  .date-grid .control-compact { height:34px; padding:4px 8px; font-size:12px; }
  @media (max-width: 767px){
    .date-grid .help-block-xs-hide { display:none; }
  }
  .copy-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }

  /* ===== PIN Overlay ===== */
  <?php if (!$__PIN_UNLOCKED): ?>
  .content-wrapper{ filter: blur(6px) brightness(0.95); pointer-events:none; user-select:none; }
  <?php endif; ?>
  #pinOverlay{
    position: fixed; inset: 0; z-index: 9999;
    display: <?php echo $__PIN_UNLOCKED ? 'none' : 'flex'; ?>;
    align-items: center; justify-content: center;
    background: rgba(15,23,42,0.55); backdrop-filter: blur(2px);
  }
  #pinPanel{
    width: 92%; max-width: 420px; border-radius: 14px; overflow: hidden;
    background: #ffffff; border: 1px solid #e5e7eb; box-shadow: 0 20px 40px rgba(2,6,23,.2);
  }
  #pinHead{
    padding: 14px 16px; background: linear-gradient(90deg,#0ea5e9,#6366f1); color:#fff; font-weight:800;
  }
  #pinBody{ padding: 16px; }
  .pin-inputs{ display:flex; gap:8px; justify-content:center; }
  .pin-inputs input{
    width:42px; height:48px; text-align:center; font-size:22px; font-weight:800; letter-spacing:1px;
    border:1px solid #cbd5e1; border-radius:10px; outline:none;
  }
  .pin-inputs input:focus{ border-color:#60a5fa; box-shadow: 0 0 0 4px rgba(59,130,246,.20); }
  .pin-actions{ display:flex; gap:8px; justify-content:center; margin-top:12px; }
  .pin-actions button{ border-radius:10px; padding:8px 14px; font-weight:800; }
  .pin-hint{ text-align:center; color:#64748b; font-size:12px; margin-top:8px; }
  .pin-error{ text-align:center; color:#b91c1c; font-weight:700; margin-top:8px; }
</style>

<!-- ===== PIN Overlay Markup ===== -->
<div id="pinOverlay">
  <div id="pinPanel">
    <div id="pinHead"><i class="fa fa-lock"></i> Keamanan · Masukkan PIN 6 Digit</div>
    <div id="pinBody">
      <form method="post" autocomplete="off" id="pinForm">
        <input type="hidden" name="pin_guard_submit" value="1">
        <div class="pin-inputs">
          <?php for($i=0;$i<6;$i++): ?>
            <input type="password" inputmode="numeric" pattern="\d*" maxlength="1" class="pinbox" name="pin_digits[]">
          <?php endfor; ?>
        </div>
        <div class="pin-actions">
          <button class="btn btn-primary" type="submit"><i class="fa fa-unlock"></i> Buka</button>
          <button class="btn btn-default" type="button" id="pinClear"><i class="fa fa-eraser"></i> Hapus</button>
        </div>
        <?php if ($pin_error): ?><div class="pin-error"><?php echo esc($pin_error); ?></div><?php endif; ?>
        <div class="pin-hint">Halaman generator dikunci. Masukkan PIN untuk melanjutkan.</div>
      </form>
    </div>
  </div>
</div>

<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fa fa-key" style="color:#0ea5e9"></i> Generator Kode (Internal)</h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Generator Kode</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">

      <!-- License Key -->
      <div class="col-md-6">
        <div class="box box-primary">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-id-badge"></i> License Key</h3>
          </div>
          <form class="box-body" method="post" autocomplete="off">
            <input type="hidden" name="aksi" value="gen_license">
            <div class="form-group">
              <label>Jumlah</label>
              <input type="number" name="jumlah" class="form-control" value="1" min="1" max="100">
              <p class="help-block">Format: <code>EPS-XXXXX-XXXXX-XXXXX-XXXXX-CCCC</code> (CCCC = checksum).</p>
            </div>
            <button class="btn btn-primary"><i class="fa fa-magic"></i> Generate</button>
          </form>

          <?php if ($generated_keys): ?>
          <div class="box-footer">
            <div class="copy-toolbar">
              <label style="margin:0;">Hasil</label>
              <button type="button" id="btnCopyLicense" class="btn btn-default btn-xs" title="Salin">
                <i class="fa fa-copy"></i>
              </button>
            </div>
            <textarea id="licenseOutput" class="form-control" rows="8"><?php echo esc(implode("\n",$generated_keys)); ?></textarea>
            <p id="copyMsg" class="help-block" style="display:none;">Tersalin ke clipboard.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Activation / Renew Codes -->
      <div class="col-md-6">
        <div class="box box-success">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-shield"></i> Kode Aktivasi / Perpanjang</h3>
          </div>
          <form id="formCodes" class="box-body" method="post" autocomplete="off">
            <input type="hidden" name="aksi" value="gen_codes">
            <!-- license_until disamakan dengan valid_until (diset via JS) -->
            <input type="hidden" name="license_until" id="license_until_hidden">

            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>Jenis Kode</label>
                  <select name="code_type" class="form-control">
                    <option value="ACT">Aktivasi (ACT)</option>
                    <option value="RENEW">Perpanjang (RENEW)</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>Jumlah</label>
                  <input type="number" name="jumlah" class="form-control" value="1" min="1" max="200">
                </div>
              </div>
            </div>

            <!-- ====== Hanya 2 tanggal di UI: Mulai & Berakhir ====== -->
            <div class="row date-grid">
              <div class="col-xs-6 col-sm-6">
                <div class="form-group">
                  <label class="label-compact">Mulai Aktivasi <span class="text-muted">(valid_from)</span></label>
                  <input type="date" name="valid_from" id="valid_from" class="form-control control-compact" required>
                  <p class="help-block help-block-xs-hide">Default: hari ini.</p>
                </div>
              </div>
              <div class="col-xs-6 col-sm-6">
                <div class="form-group">
                  <label class="label-compact">Berakhir / Kedaluwarsa <span class="text-muted">(valid_until)</span></label>
                  <input type="date" name="valid_until" id="valid_until" class="form-control control-compact" required>
                  <p class="help-block help-block-xs-hide">Default: +1 tahun dari Mulai.</p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm-4">
                <div class="form-group">
                  <label>Masa Aktivasi</label>
                  <select id="act_years" class="form-control">
                    <option value="1" selected>1 Tahun</option>
                    <option value="2">2 Tahun</option>
                    <option value="3">3 Tahun</option>
                  </select>
                  <p class="help-block">Mengisi otomatis tanggal berakhir.</p>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm-4">
                <div class="form-group">
                  <label>Maks. Pemakaian (max_uses)</label>
                  <input type="number" name="max_uses" class="form-control" value="1" min="1" max="50">
                </div>
              </div>
              <div class="col-sm-8">
                <div class="form-group">
                  <label>Kunci Domain (opsional)</label>
                  <input type="text" name="bound_domain" class="form-control" placeholder="contoh: smpn1.sch.id">
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label>Kunci Sekolah ID (opsional)</label>
                  <input type="number" name="bound_school_id" class="form-control" placeholder="ID sesuai tabel sekolah">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label>Catatan</label>
                  <input type="text" name="note" class="form-control" placeholder="Catatan internal">
                </div>
              </div>
            </div>

            <button class="btn btn-success"><i class="fa fa-magic"></i> Generate & Simpan</button>
            <p class="help-block" style="margin-top:6px;">
              Format: <code>ACT-YYYYMMDD-XXXXX-CCCC</code> / <code>RENEW-YYYYMMDD-XXXXX-CCCC</code>. Kode otomatis disimpan ke tabel <code>license_codes</code>.
            </p>
          </form>

          <?php if ($generated_codes): ?>
          <div class="box-footer">
            <div class="copy-toolbar">
              <label style="margin:0;">Hasil (tersimpan di DB)</label>
              <button type="button" id="btnCopyCodes" class="btn btn-default btn-xs" title="Salin">
                <i class="fa fa-copy"></i>
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-striped table-condensed">
                <thead>
                  <tr>
                    <th>Kode</th><th>Jenis</th><th>License Until</th><th>Valid From</th><th>Valid Until</th><th>Max Uses</th><th>Domain</th><th>Sekolah ID</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($generated_codes as $row): ?>
                    <tr>
                      <td><code><?php echo esc($row['code']); ?></code></td>
                      <td><?php echo esc($row['type']); ?></td>
                      <td><?php echo esc($row['license_until']); ?></td>
                      <td><?php echo esc($row['valid_from'] ?: '—'); ?></td>
                      <td><?php echo esc($row['valid_until'] ?: '—'); ?></td>
                      <td><?php echo (int)$row['max_uses']; ?></td>
                      <td><?php echo esc($row['bound_domain'] ?: '—'); ?></td>
                      <td><?php echo $row['bound_school_id'] ? (int)$row['bound_school_id'] : '—'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <textarea id="codesOutput" class="form-control" rows="6"><?php
              echo esc(implode("\n", array_map(function($r){ return $r['code']; }, $generated_codes)));
            ?></textarea>
            <p id="copyMsgCodes" class="help-block" style="display:none;">Kode tersalin ke clipboard.</p>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- ====== Blok Gabungan (License Key + Kode) ====== -->
      <?php
        $lastKeys  = $_SESSION['last_license_keys'] ?? [];
        $lastCodes = $_SESSION['last_act_codes'] ?? [];
        $minPair   = min(count($lastKeys), count($lastCodes));
      ?>
      <?php if ($minPair > 0): ?>
      <div class="col-md-12">
        <div class="box box-info">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-link"></i> Gabungan (License Key + Kode Aktivasi)</h3>
          </div>
          <div class="box-body">
            <div class="copy-toolbar">
              <span>Siap salin – dipadankan berdasarkan urutan generate terakhir.</span>
              <button type="button" id="btnCopyCombo" class="btn btn-default btn-xs" title="Salin Gabungan">
                <i class="fa fa-copy"></i>
              </button>
            </div>
            <?php
              $lines = [];
              for($i=0;$i<$minPair;$i++){
                $lines[] = "License Key: ".$lastKeys[$i]." | Kode: ".$lastCodes[$i];
              }
            ?>
            <textarea id="comboOutput" class="form-control" rows="<?php echo max(3, min(12,$minPair+1)); ?>"><?php echo esc(implode("\n",$lines)); ?></textarea>
            <p id="copyMsgCombo" class="help-block" style="display:none;">Gabungan tersalin ke clipboard.</p>
            <p class="help-block">Catatan: Gabungan memakai hasil terakhir yang Anda generate (disimpan sementara di sesi).</p>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </section>
</div>

<!-- JS kecil: copy icon + auto tanggal default + masa aktivasi + sync license_until + PIN -->
<script>
  function copyFrom(idTextarea, idMsg){
    var ta = document.getElementById(idTextarea);
    if(!ta) return;
    var showMsg = function(){
      if(!idMsg) return;
      var el = document.getElementById(idMsg);
      if(el){ el.style.display='block'; setTimeout(function(){ el.style.display='none'; }, 1400); }
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(ta.value || '').then(showMsg);
    } else {
      ta.focus(); ta.select();
      try { document.execCommand('copy'); showMsg(); } catch(err){}
    }
  }

  (function(){
    var b1 = document.getElementById('btnCopyLicense');
    if(b1){ b1.addEventListener('click', function(e){ e.preventDefault(); copyFrom('licenseOutput','copyMsg'); }); }
    var b2 = document.getElementById('btnCopyCodes');
    if(b2){ b2.addEventListener('click', function(e){ e.preventDefault(); copyFrom('codesOutput','copyMsgCodes'); }); }
    var b3 = document.getElementById('btnCopyCombo');
    if(b3){ b3.addEventListener('click', function(e){ e.preventDefault(); copyFrom('comboOutput','copyMsgCombo'); }); }
  })();

  // ===== Auto default 2 tanggal + Masa Aktivasi + sinkron license_until_hidden =====
  (function(){
    function pad(n){ return n<10 ? '0'+n : n; }
    function fmt(d){ return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); }
    function addYears(base, yrs){
      var d = new Date(base.getTime());
      var m = d.getMonth();
      d.setFullYear(d.getFullYear()+yrs);
      if (d.getMonth() !== m) { d.setDate(0); }
      return d;
    }

    var today = new Date();
    var vf = document.getElementById('valid_from');
    var vu = document.getElementById('valid_until');
    var yrsSel = document.getElementById('act_years');
    var luHidden = document.getElementById('license_until_hidden');

    if (vf && !vf.value) vf.value = fmt(today);
    function computeVU(){
      var years = parseInt(yrsSel ? yrsSel.value : '1', 10) || 1;
      var start = vf && vf.value ? new Date(vf.value) : today;
      if (isNaN(start.getTime())) start = today;
      var end = addYears(start, years);
      if (vu) vu.value = fmt(end);
      if (luHidden) luHidden.value = fmt(end);
    }
    computeVU();

    if (yrsSel) yrsSel.addEventListener('change', computeVU);
    if (vf)     vf.addEventListener('change', computeVU);
    if (vu)     vu.addEventListener('change', function(){ if(luHidden) luHidden.value = vu.value; });

    var form = document.getElementById('formCodes');
    if (form) form.addEventListener('submit', function(){ if(luHidden && vu){ luHidden.value = vu.value; } });
  })();

  // ===== PIN UX: auto fokus & auto-advance 6 kotak =====
  (function(){
    var boxes = document.querySelectorAll('.pinbox');
    if(!boxes.length) return;
    boxes[0].focus();
    boxes.forEach(function(inp, idx){
      inp.addEventListener('input', function(e){
        this.value = this.value.replace(/\D/g,'').slice(0,1);
        if (this.value && idx < boxes.length-1) boxes[idx+1].focus();
      });
      inp.addEventListener('keydown', function(e){
        if(e.key==='Backspace' && !this.value && idx>0){ boxes[idx-1].focus(); }
      });
    });
    var clr = document.getElementById('pinClear');
    if (clr) clr.addEventListener('click', function(){
      boxes.forEach(b=>b.value=''); boxes[0].focus();
    });
  })();
</script>

<?php include __DIR__.'/footer.php'; ?>
