<?php
// admin/HOSTING.php atau hosting.php (nama apa pun) — aman case-sensitive
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../koneksi.php';

// --------- util ----------
if (!function_exists('_is_admin')) { function _is_admin(){ return true; } }
function hs_esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hs_i($v,$d=0){ return isset($v) && $v!=='' ? (int)$v : $d; }
function hs_table_exists(mysqli $koneksi, string $table): bool {
  $table_esc = mysqli_real_escape_string($koneksi, $table);
  $qdb = mysqli_query($koneksi, "SELECT DATABASE() AS db");
  $db  = $qdb && ($r=mysqli_fetch_assoc($qdb)) ? $r['db'] : '';
  if ($db==='') return false;
  $db_esc = mysqli_real_escape_string($koneksi,$db);
  $q = mysqli_query($koneksi,"SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db_esc' AND TABLE_NAME='$table_esc' LIMIT 1");
  return $q && mysqli_fetch_row($q) ? true:false;
}
function hs_fmt_bytes($b){ $b=(float)$b; $u=['B','KB','MB','GB','TB']; $i=0; while($b>=1024 && $i<count($u)-1){$b/=1024;$i++;} $dec=$i>=2?2:0; return number_format($b,$dec).' '.$u[$i]; }
function hs_pct($used,$limit){ return ($limit>0? max(0,min(100,(int)round(($used/$limit)*100))):0); }
function hs_redirect($url){
  if (!headers_sent()){ header("Location: $url"); exit; }
  $u = hs_esc($url);
  echo "<script>location.replace('$u');</script><noscript><meta http-equiv=\"refresh\" content=\"0;url=$u\"></noscript>";
  exit;
}

// --------- CONST & self URL (fix 404 karena case) ----------
const HS_BASELINE_DAY = '2000-01-01';
$HS_SELF = basename($_SERVER['SCRIPT_NAME'] ?? 'HOSTING.php'); // nama file yang benar di server (dengan case)

// --------- cek tabel ----------
$HAS_TENANT = hs_table_exists($koneksi,'tenant_quota');
$HAS_ULOG   = hs_table_exists($koneksi,'usage_log');
$HAS_UDAY   = hs_table_exists($koneksi,'usage_daily');

// --------- tenant ----------
$sekolah_list=[]; $q=mysqli_query($koneksi,"SELECT sekolah_id,nama_sekolah FROM sekolah ORDER BY sekolah_id ASC");
while($q && $r=mysqli_fetch_assoc($q)){ $sekolah_list[]=$r; }
if (!$sekolah_list){ die('Data sekolah kosong.'); }
$sekolah_id = (int)($_GET['sid'] ?? $sekolah_list[0]['sekolah_id']);

// ===================== HANDLERS =====================
if ($_SERVER['REQUEST_METHOD']==='POST' && _is_admin()){
  $aksi = $_POST['aksi'] ?? '';
  $back = $HS_SELF.'?sid='.$sekolah_id;   // <-- pakai nama file yang sedang dibuka (case-safe)

  if ($aksi==='save_quota' && $HAS_TENANT){
    $disk_mb = hs_i($_POST['disk_limit_mb'], 500);
    $bw_gb   = ($_POST['bandwidth_limit_gb']==='' ? null : hs_i($_POST['bandwidth_limit_gb'],0));
    $inode   = ($_POST['inode_limit']==='' ? null : hs_i($_POST['inode_limit'],0));
    $sid = (int)$sekolah_id;

    $chk = mysqli_query($koneksi,"SELECT id FROM tenant_quota WHERE sekolah_id=$sid LIMIT 1");
    if ($chk && $row=mysqli_fetch_assoc($chk)){
      $id=(int)$row['id'];
      mysqli_query($koneksi,"UPDATE tenant_quota SET disk_limit_mb=$disk_mb,
        bandwidth_limit_gb=".($bw_gb===null?'NULL':$bw_gb).",
        inode_limit=".($inode===null?'NULL':$inode).",
        updated_at=NOW() WHERE id=$id");
    } else {
      mysqli_query($koneksi,"INSERT INTO tenant_quota(sekolah_id,disk_limit_mb,bandwidth_limit_gb,inode_limit,created_at,updated_at)
        VALUES($sid,$disk_mb,".($bw_gb===null?'NULL':$bw_gb).",".($inode===null?'NULL':$inode).",NOW(),NOW())");
    }
    hs_redirect($back.'&ok=quota_saved');
  }

  if ($aksi==='save_baseline' && $HAS_UDAY){
    $seed_disk_mb = hs_i($_POST['seed_disk_mb'],0);
    $seed_bw_mb   = hs_i($_POST['seed_bw_mb'],0);
    $seed_inode   = hs_i($_POST['seed_inode'],0);
    $note         = trim($_POST['seed_note'] ?? '');

    $seed_disk_bytes = $seed_disk_mb*1024*1024;
    $seed_bw_bytes   = $seed_bw_mb*1024*1024;
    $sid = (int)$sekolah_id;

    $cek = mysqli_query($koneksi,"SELECT 1 FROM usage_daily WHERE sekolah_id=$sid AND `day`='".HS_BASELINE_DAY."' LIMIT 1");
    if ($cek && mysqli_fetch_row($cek)){
      mysqli_query($koneksi,"UPDATE usage_daily SET disk_delta_bytes=$seed_disk_bytes,bandwidth_bytes=$seed_bw_bytes,inode_delta=$seed_inode
        WHERE sekolah_id=$sid AND `day`='".HS_BASELINE_DAY."'");
    } else {
      mysqli_query($koneksi,"INSERT INTO usage_daily(sekolah_id,`day`,disk_delta_bytes,bandwidth_bytes,inode_delta)
        VALUES($sid,'".HS_BASELINE_DAY."',$seed_disk_bytes,$seed_bw_bytes,$seed_inode)");
    }
    if ($HAS_ULOG && ($seed_disk_bytes>0 || $seed_bw_bytes>0 || $seed_inode>0)){
      $uid=(int)($_SESSION['user_id'] ?? 0);
      $meta=mysqli_real_escape_string($koneksi,json_encode(['reason'=>$note,'source'=>'baseline']));
      mysqli_query($koneksi,"INSERT INTO usage_log(sekolah_id,user_id,occurred_at,action,bytes,note,metadata)
        VALUES($sid,$uid,NOW(),'other',".($seed_disk_bytes+$seed_bw_bytes).",'baseline','$meta')");
    }
    hs_redirect($back.'&ok=baseline_saved');
  }

  if ($aksi==='rebuild_daily' && $HAS_UDAY && $HAS_ULOG){
    $sid=(int)$sekolah_id;
    mysqli_query($koneksi,"DELETE FROM usage_daily WHERE sekolah_id=$sid AND `day` <> '".HS_BASELINE_DAY."'");
    $q = mysqli_query($koneksi,"SELECT DATE(occurred_at) d,
      GREATEST(0,SUM(CASE WHEN action IN ('upload','import','insert') THEN bytes WHEN action IN ('delete','remove') THEN -bytes ELSE 0 END)) disk_sum,
      GREATEST(0,SUM(CASE WHEN action IN ('download','export') THEN bytes ELSE 0 END)) bw_sum,
      SUM(CASE WHEN action='create_object' THEN 1 WHEN action='delete_object' THEN -1 ELSE 0 END) inode_sum
      FROM usage_log WHERE sekolah_id=$sid GROUP BY DATE(occurred_at)");
    while($q && $r=mysqli_fetch_assoc($q)){
      $d = mysqli_real_escape_string($koneksi,$r['d']);
      mysqli_query($koneksi,"INSERT INTO usage_daily(sekolah_id,`day`,disk_delta_bytes,bandwidth_bytes,inode_delta)
        VALUES($sid,'$d',".(int)$r['disk_sum'].",".(int)$r['bw_sum'].",".(int)$r['inode_sum'].")");
    }
    hs_redirect($back.'&ok=rebuild_ok');
  }

  if ($aksi==='reset_usage' && $HAS_ULOG && $HAS_UDAY){
    $confirm=trim($_POST['confirm']??''); $pin6=trim($_POST['pin6']??''); $PIN='123456';
    if ($confirm!=='RESET' || $pin6!==$PIN){ hs_redirect($back.'&err=bad_confirm'); }
    $sid=(int)$sekolah_id;
    mysqli_query($koneksi,"DELETE FROM usage_daily WHERE sekolah_id=$sid AND `day` <> '".HS_BASELINE_DAY."'");
    mysqli_query($koneksi,"DELETE FROM usage_log WHERE sekolah_id=$sid");
    hs_redirect($back.'&ok=reset_ok');
  }
}

// ===================== DATA VIEW =====================
$disk_limit_mb=500; $bandwidth_limit_gb=null; $inode_limit=null;
if ($HAS_TENANT){
  $qs=mysqli_query($koneksi,"SELECT disk_limit_mb,bandwidth_limit_gb,inode_limit FROM tenant_quota WHERE sekolah_id=".$sekolah_id." LIMIT 1");
  if($qs && $row=mysqli_fetch_assoc($qs)){
    $disk_limit_mb=(int)$row['disk_limit_mb'];
    $bandwidth_limit_gb=($row['bandwidth_limit_gb']!==null?(int)$row['bandwidth_limit_gb']:null);
    $inode_limit=($row['inode_limit']!==null?(int)$row['inode_limit']:null);
  }
}

$disk_used_bytes=0; $bw_used_bytes=0; $inode_used=0;
$curr_seed=['disk_mb'=>0,'bw_mb'=>0,'inode'=>0];

if ($HAS_UDAY){
  $qd=mysqli_query($koneksi,"SELECT
    COALESCE(SUM(CASE WHEN `day`='".HS_BASELINE_DAY."' THEN disk_delta_bytes END),0) seed_disk,
    COALESCE(SUM(CASE WHEN `day`='".HS_BASELINE_DAY."' THEN bandwidth_bytes END),0) seed_bw,
    COALESCE(SUM(CASE WHEN `day`='".HS_BASELINE_DAY."' THEN inode_delta END),0) seed_inode,
    COALESCE(SUM(CASE WHEN `day`<>'".HS_BASELINE_DAY."' THEN disk_delta_bytes END),0) day_disk,
    COALESCE(SUM(CASE WHEN `day`<>'".HS_BASELINE_DAY."' THEN bandwidth_bytes END),0) day_bw,
    COALESCE(SUM(CASE WHEN `day`<>'".HS_BASELINE_DAY."' THEN inode_delta END),0) day_inode
  FROM usage_daily WHERE sekolah_id=".$sekolah_id);
  if($qd && $r=mysqli_fetch_assoc($qd)){
    $disk_used_bytes+=(int)$r['day_disk']+(int)$r['seed_disk'];
    $bw_used_bytes  +=(int)$r['day_bw']  +(int)$r['seed_bw'];
    $inode_used     +=(int)$r['day_inode']+(int)$r['seed_inode'];

    $curr_seed['disk_mb']=(int)round($r['seed_disk']/(1024*1024));
    $curr_seed['bw_mb']  =(int)round($r['seed_bw']  /(1024*1024));
    $curr_seed['inode']  =(int)$r['seed_inode'];
  }
}
if ($HAS_ULOG){
  $qt=mysqli_query($koneksi,"SELECT
    GREATEST(0,SUM(CASE WHEN action IN ('upload','import','insert') THEN bytes WHEN action IN ('delete','remove') THEN -bytes ELSE 0 END)) disk_used,
    GREATEST(0,SUM(CASE WHEN action IN ('download','export') THEN bytes ELSE 0 END)) bw_used,
    SUM(CASE WHEN action='create_object' THEN 1 WHEN action='delete_object' THEN -1 ELSE 0 END) inode_used
  FROM usage_log WHERE sekolah_id=".$sekolah_id." AND DATE(occurred_at)=CURDATE()");
  if($qt && $r=mysqli_fetch_assoc($qt)){
    $disk_used_bytes+=(int)$r['disk_used'];
    $bw_used_bytes  +=(int)$r['bw_used'];
    $inode_used     +=(int)$r['inode_used'];
  }
}

$disk_limit_bytes=$disk_limit_mb*1024*1024;
$pct_disk = hs_pct($disk_used_bytes,$disk_limit_bytes);
$pct_bw   = $bandwidth_limit_gb? hs_pct($bw_used_bytes,$bandwidth_limit_gb*1024*1024*1024):0;
$pct_inode= $inode_limit? hs_pct(max(0,$inode_used),$inode_limit):0;

$logs=[]; if($HAS_ULOG){
  $ql=mysqli_query($koneksi,"SELECT occurred_at,action,bytes,note FROM usage_log WHERE sekolah_id=".$sekolah_id." ORDER BY occurred_at DESC,id DESC LIMIT 20");
  while($ql && $r=mysqli_fetch_assoc($ql)){ $logs[]=$r; }
}

// ===================== VIEW =====================
include __DIR__ . '/header.php';
?>
<style>
#hostingWrap .card{ border:1px solid #e5e7eb; border-radius:12px; background:#fff; box-shadow:0 6px 22px rgba(0,0,0,.04); }
#hostingWrap .chip{ display:inline-flex; align-items:center; gap:6px; font-weight:700; padding:4px 10px; border-radius:999px; background:#f1f5f9; border:1px solid #e2e8f0; }
#hostingWrap .meter{ height:16px; background:#f1f5f9; border-radius:999px; overflow:hidden; border:1px solid #e5e7eb; position:relative; }
#hostingWrap .bar{ height:100%; width:0; transition:width .4s ease; }
#hostingWrap .bar.disk{ background:linear-gradient(90deg,#06b6d4,#3b82f6); }
#hostingWrap .bar.bw  { background:linear-gradient(90deg,#22c55e,#84cc16); }
#hostingWrap .bar.inode{ background:linear-gradient(90deg,#f97316,#f43f5e); }
#hostingWrap .section-title{ font-weight:800; color:#0f172a; }
#hostingWrap .db-note{ color:#64748b; font-size:12px; }
#hostingWrap .form-control{ border-radius:10px; border:1px solid #cbd5e1; }
#hostingWrap .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
#hostingWrap .grid-3{ display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; }
@media (max-width:991px){ #hostingWrap .grid-2, #hostingWrap .grid-3{ grid-template-columns:1fr; } }
.content-wrapper#hostingWrap { padding-top:10px; }
</style>

<div class="content-wrapper" id="hostingWrap">
  <section class="content-header">
    <h1 class="section-title"><i class="fa-solid fa-server"></i> Pengaturan Hosting</h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Hosting</li>
    </ol>
  </section>

  <section class="content">
    <?php
      if (isset($_GET['ok'])){
        $m=['quota_saved'=>'Kuota berhasil disimpan.','baseline_saved'=>'Baseline berhasil disimpan.','rebuild_ok'=>'Rebuild usage_daily selesai.','reset_ok'=>'Pemakaian berhasil di-reset.'][$_GET['ok']] ?? 'Berhasil.';
        echo '<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button><i class="fa fa-check-circle"></i> '.hs_esc($m).'</div>';
      }
      if (isset($_GET['err'])){
        $m = ($_GET['err']==='bad_confirm') ? 'Konfirmasi/ PIN salah.' : 'Terjadi kesalahan.';
        echo '<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">×</button><i class="fa fa-exclamation-triangle"></i> '.hs_esc($m).'</div>';
      }
    ?>

    <div class="card" style="padding:14px; margin-bottom:14px;">
      <div style="display:flex; align-items:center; gap:10px;">
        <div style="font-weight:800; font-size:16px;"><i class="fa fa-database"></i> Limit & Hosting</div>
        <div style="margin-left:auto; font-size:12px;">
          Tenant:
          <form method="get" style="display:inline;">
            <select name="sid" onchange="this.form.submit()" style="color:#111; border-radius:8px; padding:2px 6px;">
              <?php foreach($sekolah_list as $s): ?>
                <option value="<?= (int)$s['sekolah_id'] ?>" <?= (int)$s['sekolah_id']===$sekolah_id?'selected':''; ?>><?= hs_esc($s['nama_sekolah']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
      </div>

      <div class="grid-3" style="margin-top:12px;">
        <div class="card" style="padding:14px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div><b><i class="fa fa-hdd"></i> Disk Space</b></div>
            <span class="chip"><?= hs_fmt_bytes($disk_used_bytes) ?> / <?= number_format($disk_limit_mb) ?> MB</span>
          </div>
          <div class="meter" style="margin-top:6px;"><div class="bar disk" id="barDisk"></div></div>
          <div class="db-note" style="margin-top:6px; display:flex; justify-content:space-between;">
            <span><?= $pct_disk ?>%</span>
            <span>tersisa: <b><?= hs_fmt_bytes(max(0,$disk_limit_bytes-$disk_used_bytes)) ?></b></span>
          </div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div><b><i class="fa fa-wave-square"></i> Bandwidth</b></div>
            <span class="chip"><?= hs_fmt_bytes($bw_used_bytes) ?> / <?= $bandwidth_limit_gb!==null?(int)$bandwidth_limit_gb.' GB':'—' ?></span>
          </div>
          <div class="meter" style="margin-top:6px;"><div class="bar bw" id="barBw"></div></div>
          <div class="db-note" style="margin-top:6px; display:flex; justify-content:space-between;">
            <span><?= $pct_bw ?>%</span>
            <span>tersisa: <b><?= $bandwidth_limit_gb!==null? hs_fmt_bytes(max(0,$bandwidth_limit_gb*1024*1024*1024 - $bw_used_bytes)) : '—' ?></b></span>
          </div>
        </div>

        <div class="card" style="padding:14px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div><b><i class="fa fa-cubes"></i> Objek (inode)</b></div>
            <span class="chip"><?= max(0,$inode_used) ?> / <?= $inode_limit!==null?(int)$inode_limit:'—' ?></span>
          </div>
          <div class="meter" style="margin-top:6px;"><div class="bar inode" id="barInode"></div></div>
          <div class="db-note" style="margin-top:6px; display:flex; justify-content:space-between;">
            <span><?= $pct_inode ?>%</span>
            <span>tersisa: <b><?= $inode_limit!==null? max(0,$inode_limit - max(0,$inode_used)) : '—' ?></b></span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card" style="padding:14px;">
        <h4 class="section-title"><i class="fa fa-sliders"></i> Pengaturan Kuota</h4>
        <?php if(!$HAS_TENANT): ?><div class="alert alert-danger" style="margin-top:8px;">Tabel <code>tenant_quota</code> belum tersedia.</div><?php endif; ?>
        <form method="post" action="<?= hs_esc($HS_SELF) ?>?sid=<?= (int)$sekolah_id ?>" autocomplete="off" style="margin-top:8px;">
          <input type="hidden" name="aksi" value="save_quota">
          <div class="grid-3">
            <div><label>Disk Limit (MB)</label><input type="number" class="form-control" name="disk_limit_mb" value="<?= (int)$disk_limit_mb ?>" min="1" required></div>
            <div><label>Bandwidth Limit (GB)</label><input type="number" class="form-control" name="bandwidth_limit_gb" value="<?= $bandwidth_limit_gb!==null?(int)$bandwidth_limit_gb:'' ?>" placeholder="kosongkan bila tidak dibatasi"></div>
            <div><label>Inode Limit</label><input type="number" class="form-control" name="inode_limit" value="<?= $inode_limit!==null?(int)$inode_limit:'' ?>" placeholder="kosongkan bila tidak dibatasi"></div>
          </div>
          <div class="text-right" style="margin-top:10px;"><button class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Simpan Kuota</button></div>
        </form>
        <div class="db-note" style="margin-top:8px;">Tips: atur Disk konservatif (mis. 500–1000 MB/tenant). Bandwidth & Inode opsional.</div>
      </div>

      <div class="card" style="padding:14px;">
        <h4 class="section-title"><i class="fa fa-weight-hanging"></i> Baseline / Seed Awal</h4>
        <div class="db-note">Isi “sudah terpakai” awal (mis. 30 MB) agar sesuai file lama yang memang ada di hosting Anda.</div>
        <?php if(!$HAS_UDAY): ?><div class="alert alert-danger" style="margin-top:8px;">Tabel <code>usage_daily</code> belum tersedia.</div><?php endif; ?>
        <form method="post" action="<?= hs_esc($HS_SELF) ?>?sid=<?= (int)$sekolah_id ?>" autocomplete="off" style="margin-top:8px;">
          <input type="hidden" name="aksi" value="save_baseline">
          <div class="grid-3">
            <div><label>Disk (MB)</label><input type="number" class="form-control" name="seed_disk_mb" value="<?= (int)$curr_seed['disk_mb'] ?>" min="0"></div>
            <div><label>Bandwidth (MB)</label><input type="number" class="form-control" name="seed_bw_mb" value="<?= (int)$curr_seed['bw_mb'] ?>" min="0"></div>
            <div><label>Objek (inode)</label><input type="number" class="form-control" name="seed_inode" value="<?= (int)$curr_seed['inode'] ?>" min="0"></div>
          </div>
          <div style="margin-top:8px;"><label>Catatan (opsional)</label><input type="text" class="form-control" name="seed_note" placeholder="mis. seed onboarding / file lama di host"></div>
          <div class="db-note" style="margin-top:6px;">Baseline disimpan pada tanggal khusus <code><?= HS_BASELINE_DAY ?></code> agar aman dari proses <i>rebuild</i>.</div>
          <div class="text-right" style="margin-top:10px;"><button class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Simpan Baseline</button></div>
        </form>
      </div>
    </div>

    <div class="grid-2" style="margin-top:12px;">
      <div class="card" style="padding:14px;">
        <h4 class="section-title"><i class="fa fa-wrench"></i> Tools</h4>
        <div class="db-note">Operasi pemeliharaan data pemakaian.</div>

        <form method="post" action="<?= hs_esc($HS_SELF) ?>?sid=<?= (int)$sekolah_id ?>" class="form-inline" style="margin-top:8px;">
          <input type="hidden" name="aksi" value="rebuild_daily">
          <button class="btn btn-default btn-sm" type="submit"><i class="fa fa-sync"></i> Rebuild usage_daily dari usage_log</button>
          <span class="db-note" style="margin-left:8px;">Mengagregasi ulang per hari & tetap menjaga baseline.</span>
        </form>

        <hr>
        <form method="post" action="<?= hs_esc($HS_SELF) ?>?sid=<?= (int)$sekolah_id ?>" style="margin-top:0;">
          <input type="hidden" name="aksi" value="reset_usage">
          <div class="grid-3">
            <div><label>Ketik: RESET</label><input type="text" class="form-control" name="confirm" placeholder="RESET"></div>
            <div><label>PIN (6 digit)</label><input type="password" class="form-control" name="pin6" maxlength="6" placeholder="123456"></div>
            <div style="display:flex; align-items:flex-end;"><button class="btn btn-danger btn-sm" type="submit"><i class="fa fa-trash"></i> Reset Pemakaian</button></div>
          </div>
          <div class="db-note">Menghapus <code>usage_log</code> dan <code>usage_daily</code> (kecuali baseline).</div>
        </form>
      </div>

      <div class="card" style="padding:14px;">
        <h4 class="section-title"><i class="fa fa-list"></i> Riwayat Aktivitas (20 terakhir)</h4>
        <?php if(!$HAS_ULOG): ?><div class="alert alert-danger" style="margin-top:8px;">Tabel <code>usage_log</code> belum tersedia.</div><?php endif; ?>
        <div style="max-height:340px; overflow:auto; margin-top:8px;">
          <table class="table table-striped">
            <thead><tr><th>Waktu</th><th>Aksi</th><th>Bytes</th><th>Catatan</th></tr></thead>
            <tbody>
              <?php if($logs): foreach($logs as $lg): ?>
                <tr>
                  <td><small><?= hs_esc($lg['occurred_at']) ?></small></td>
                  <td><code><?= hs_esc($lg['action']) ?></code></td>
                  <td><?= hs_fmt_bytes((int)$lg['bytes']) ?></td>
                  <td><?= hs_esc($lg['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-muted">Belum ada data.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="db-note" style="margin-top:10px;">
      <i class="fa fa-circle-info"></i> Meter membaca <b><?= $HAS_UDAY?'histori aktivitas (usage_daily + log hari ini)':'log aktivitas / fallback' ?></b>.
      Pada InnoDB, penghapusan baris tidak selalu langsung mengecilkan ukuran file fisik hingga dilakukan OPTIMIZE/PURGE.
    </div>
  </section>
</div>

<script>
(function(){
  function w(id,p){ var el=document.getElementById(id); if(el){ el.style.width=p+'%'; } }
  w('barDisk',  <?= (int)$pct_disk  ?>);
  w('barBw',    <?= (int)$pct_bw    ?>);
  w('barInode', <?= (int)$pct_inode ?>);
})();
</script>

<?php include __DIR__ . '/footer.php';
