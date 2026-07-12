<?php
// ===== ACTION HANDLER (di atas output HTML agar bisa redirect) =====
include '../koneksi.php';
session_start();
require_once __DIR__ . '/../includes/epoin_security.php';
if(!isset($_SESSION['level']) || $_SESSION['level'] !== "administrator"){
  header("location:../admin.php?alert=belum_login");
  exit;
}

function _post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function _int($v){ return (int)$v; }

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

/* ---------- Tambah penugasan ---------- */
if ($action === 'tambah') {
  if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !epoin_csrf_validate()){
    header("location: pengampu_mapel.php?alert=invalid");
    exit;
  }
  $ta    = _int(_post('ta_id'));
  $kelas = _int(_post('kelas_id'));
  $mapel = _int(_post('mapel_id'));
  $guru  = _int(_post('guru_user_id'));

  if($ta<=0 || $kelas<=0 || $mapel<=0 || $guru<=0){
    header("location: pengampu_mapel.php?alert=invalid");
    exit;
  }

  // Cek duplikasi: unik per (ta, kelas, mapel)
  $dup = mysqli_query($koneksi,"SELECT 1 FROM pengampu_mapel WHERE ta_id=$ta AND kelas_id=$kelas AND mapel_id=$mapel LIMIT 1");
  if($dup && mysqli_num_rows($dup)>0){
    header("location: pengampu_mapel.php?alert=duplikat");
    exit;
  }

  $ok = mysqli_query($koneksi,"INSERT INTO pengampu_mapel (ta_id,kelas_id,mapel_id,guru_user_id,created_at)
                               VALUES ($ta,$kelas,$mapel,$guru,NOW())");
  header("location: pengampu_mapel.php?alert=".($ok?'add_ok':'add_fail'));
  exit;
}

/* ---------- Edit penugasan ---------- */
if ($action === 'edit') {
  if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !epoin_csrf_validate()){
    header("location: pengampu_mapel.php?alert=invalid");
    exit;
  }
  $id    = _int(_post('id'));
  $ta    = _int(_post('ta_id'));
  $kelas = _int(_post('kelas_id'));
  $mapel = _int(_post('mapel_id'));
  $guru  = _int(_post('guru_user_id'));

  if($id<=0 || $ta<=0 || $kelas<=0 || $mapel<=0 || $guru<=0){
    header("location: pengampu_mapel.php?alert=invalid");
    exit;
  }

  // Cek duplikasi selain dirinya
  $dup = mysqli_query($koneksi,"SELECT 1 FROM pengampu_mapel WHERE ta_id=$ta AND kelas_id=$kelas AND mapel_id=$mapel AND id<>$id LIMIT 1");
  if($dup && mysqli_num_rows($dup)>0){
    header("location: pengampu_mapel.php?alert=duplikat");
    exit;
  }

  $ok = mysqli_query($koneksi,"UPDATE pengampu_mapel
                               SET ta_id=$ta, kelas_id=$kelas, mapel_id=$mapel, guru_user_id=$guru
                               WHERE id=$id");
  header("location: pengampu_mapel.php?alert=".($ok?'edit_ok':'edit_fail'));
  exit;
}

/* ---------- Hapus penugasan ---------- */
if ($action === 'hapus') {
  if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !epoin_csrf_validate()){
    header("location: pengampu_mapel.php?alert=invalid");
    exit;
  }
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if($id<=0){
    header("location: pengampu_mapel.php?alert=invalid");
    exit;
  }
  $ok = mysqli_query($koneksi,"DELETE FROM pengampu_mapel WHERE id=$id");
  header("location: pengampu_mapel.php?alert=".($ok?'del_ok':'del_fail'));
  exit;
}

/* ---------- Data referensi untuk form (TA / Kelas / Mapel / Guru) ---------- */
$ta_opt      = [];
$ta_aktif_id = 0;
$tas = mysqli_query($koneksi,"SELECT ta_id, ta_nama, ta_status FROM ta ORDER BY ta_status DESC, ta_nama ASC");
while($r = mysqli_fetch_assoc($tas)){
  $ta_opt[] = $r;
  if($r['ta_status']=='1'){ $ta_aktif_id = (int)$r['ta_id']; }
}

$kelas_opt = [];
$kelas_q = mysqli_query($koneksi,"SELECT kelas_id, kelas_nama, kelas_ta FROM kelas ORDER BY kelas_nama ASC");
while($r = mysqli_fetch_assoc($kelas_q)){ $kelas_opt[] = $r; }

$mapel_opt = [];
$mapel_q = mysqli_query($koneksi,"SELECT mapel_id, mapel_kode, mapel_nama FROM mapel ORDER BY mapel_nama ASC");
while($r = mysqli_fetch_assoc($mapel_q)){ $mapel_opt[] = $r; }

// Ambil semua user kandidat guru (ikutkan level)
$guru_opt = [];
$guru_q = mysqli_query($koneksi,"SELECT user_id, user_nama, IFNULL(user_level,'') AS user_level FROM user ORDER BY user_nama ASC");
while($r = mysqli_fetch_assoc($guru_q)){ $guru_opt[] = $r; }

/* ---------- Helper badge per level ---------- */
function role_badge_meta(?string $lvl): array {
  $key = strtolower(trim((string)$lvl));
  switch ($key) {
    case 'administrator':
    case 'admin':
      return ['label' => 'Administrator', 'class' => 'badge-role badge-admin'];
    case 'guru':
    case 'guru_mapel':
      return ['label' => 'Guru', 'class' => 'badge-role badge-guru'];
    case 'wali':
    case 'wali_kelas':
    case 'walikelas':
      return ['label' => 'Wali Kelas', 'class' => 'badge-role badge-wali'];
    case 'operator':
    case 'tu':
      return ['label' => 'Operator', 'class' => 'badge-role badge-operator'];
    case 'staf':
    case 'staff':
    case 'pembina':
      return ['label' => 'Staf', 'class' => 'badge-role badge-staf'];
    default:
      return ['label' => ($lvl ? ucfirst($lvl) : 'Pengguna'), 'class' => 'badge-role badge-neutral'];
  }
}

include 'header.php';
?>

<style>
/* ====== EPS: per-page polish (fallback bila eps-ui.css belum terload) ====== */
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
  --fs-xs:clamp(11px,.85vw,12px); --fs-md:clamp(13px,1.1vw,14px); --fs-2xl:clamp(22px,2.6vw,28px);
}
@keyframes textFade{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }
.content-wrapper{ background:var(--bg-page); }
.page-title{
  display:flex; align-items:center; gap:12px;
  font-size:var(--fs-2xl); font-weight:800; color:var(--ink-900);
  letter-spacing:.2px; animation:textFade .6s ease-out both;
}
.title-icon{ width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:var(--blue-100);color:var(--blue-600);box-shadow:inset 0 0 0 1px var(--line); }
.badge-chip{ background:var(--blue-50); border:1px solid var(--line); color:var(--ink-700); border-radius:var(--radius-pill); padding:4px 10px; display:inline-flex; align-items:center; gap:6px; font-size:var(--fs-xs); font-weight:700; white-space:nowrap; }
.badge-chip i{ color:var(--blue-600); }

.box{ border:0;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;position:relative;background:var(--bg-card); }
.box:before{ content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--blue-600),var(--blue-400),var(--blue-600));opacity:.9; }
.box-header{ background:var(--bg-card);border-bottom:1px solid var(--line);padding:14px 18px; }
.box-title{ display:flex;align-items:center;gap:8px;font-weight:800;color:var(--ink-900); }
.box-title i{ color:var(--blue-600); }

.btn-grad{ background:var(--grad-primary); color:#fff; border:0; border-radius:var(--radius-md);
  padding:9px 12px; font-weight:700; box-shadow:0 8px 20px rgba(45,108,223,.25);
  transition:transform .15s ease, filter .2s ease; }
.btn-grad:hover{ filter:brightness(1.06); transform:translateY(-1px); }

.table-eps>thead>tr>th{ background:linear-gradient(180deg,#f7faff 0%, #f1f6ff 100%); color:#1e293b; border-bottom:1px solid var(--line)!important; }
.table-eps>tbody>tr:nth-child(odd){ background:#fff; }
.table-eps>tbody>tr:nth-child(even){ background:var(--bg-row); }
.table-eps>tbody>tr{ transition:background .18s ease, box-shadow .18s ease; }
.table-eps>tbody>tr:hover{ background:var(--bg-hover)!important; box-shadow:inset 3px 0 0 0 var(--blue-500); cursor:pointer; }
.table-eps td{ color:#000; }

.badge-aktif{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; background:#22c55e1a; color:#16a34a; border:1px solid #22c55e55; }

/* ===== Badge per level guru ===== */
.badge-role{
  display:inline-flex; align-items:center; gap:6px;
  padding:2px 10px; border-radius:999px;
  font-size:12px; font-weight:700; line-height:1.6;
  border:1px solid transparent; white-space:nowrap;
  margin-left:6px;
}
.badge-admin{    background:#e8f0ff; color:#1f5ac8; border-color:#c9dafc; }
.badge-guru{     background:#f3e8ff; color:#7c3aed; border-color:#e1c9ff; }
.badge-wali{     background:#eafaf1; color:#0f9d58; border-color:#c9f1da; }
.badge-operator{ background:#fff3e6; color:#e8790a; border-color:#ffe0bf; }
.badge-staf{     background:#e6fbff; color:#0ea5b7; border-color:#bff4fc; }
.badge-neutral{  background:#f1f5f9; color:#334155; border-color:#e2e8f0; }

/* Modal & Select2 rapi */
.modal-content{ border-radius:16px;border:0;box-shadow:var(--shadow-lg); }
.modal-header{ background:linear-gradient(180deg,var(--blue-50),#fff);border-bottom:1px solid var(--line)!important;color:var(--ink-900) }
.modal-title i{ color:var(--blue-600) }
.modal-body .form-control{ border-radius:12px;border:1px solid var(--line);box-shadow:none }
.modal-body .form-control:focus{ border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(79,156,249,.15) }
.select2-container--default .select2-selection--single{ border-radius:12px; border:1px solid var(--line); height:38px; }
.select2-container--default .select2-selection--single .select2-selection__rendered{ line-height:36px; }
.select2-container--default .select2-selection--single .select2-selection__arrow{ height:36px; }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-users-gear"></i></span>
      <span>Pengampu Mapel</span>
      <span class="badge-chip"><i class="fa fa-layer-group"></i> Penugasan guru per TA • Kelas • Mapel</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Pengampu Mapel</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-12">
        <div class="box">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-list-ul"></i> Data Penugasan</h3>
            <div class="pull-right">
              <button type="button" class="btn btn-grad btn-sm" data-toggle="modal" data-target="#modal_tambah">
                <i class="fa fa-plus"></i> &nbsp;Tambah Penugasan
              </button>
            </div>
          </div>

          <div class="box-body">

            <?php
              $alert = isset($_GET['alert']) ? $_GET['alert'] : '';
              if($alert){
                $msg = ''; $cls='info';
                if($alert==='add_ok'){ $msg='Penugasan berhasil ditambahkan.'; $cls='success'; }
                elseif($alert==='add_fail'){ $msg='Penugasan gagal ditambahkan.'; $cls='danger'; }
                elseif($alert==='edit_ok'){ $msg='Penugasan berhasil diperbarui.'; $cls='success'; }
                elseif($alert==='edit_fail'){ $msg='Penugasan gagal diperbarui.'; $cls='danger'; }
                elseif($alert==='del_ok'){ $msg='Penugasan berhasil dihapus.'; $cls='success'; }
                elseif($alert==='del_fail'){ $msg='Penugasan gagal dihapus.'; $cls='danger'; }
                elseif($alert==='duplikat'){ $msg='Kombinasi TA + Kelas + Mapel sudah ada.'; $cls='warning'; }
                elseif($alert==='invalid'){ $msg='Data tidak lengkap atau tidak valid.'; $cls='warning'; }
                if($msg){
                  echo '<div class="alert alert-'.$cls.' alert-dismissible" style="border-radius:12px;">
                          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>'
                          .htmlspecialchars($msg).'</div>';
                }
              }
            ?>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-eps nowrap" id="table-datatable" style="width:100%; border-color:var(--line);">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>TAHUN AJARAN</th>
                    <th>KELAS</th>
                    <th>MAPEL</th>
                    <th>GURU</th>
                    <th width="12%">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no=1;
                  $sql = "SELECT pm.id,
                                 t.ta_id, t.ta_nama, t.ta_status,
                                 k.kelas_id, k.kelas_nama,
                                 m.mapel_id, m.mapel_kode, m.mapel_nama,
                                 u.user_id, u.user_nama, IFNULL(u.user_level,'') AS user_level
                          FROM pengampu_mapel pm
                          JOIN ta t     ON t.ta_id = pm.ta_id
                          JOIN kelas k  ON k.kelas_id = pm.kelas_id
                          JOIN mapel m  ON m.mapel_id = pm.mapel_id
                          JOIN user  u  ON u.user_id = pm.guru_user_id
                          ORDER BY t.ta_status DESC, t.ta_nama DESC, k.kelas_nama ASC, m.mapel_nama ASC";
                  $res = mysqli_query($koneksi,$sql);
                  while($r = mysqli_fetch_assoc($res)){ ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td>
                        <?php echo htmlspecialchars($r['ta_nama']); ?>
                        <?php if($r['ta_status']=='1') echo ' <span class="badge-aktif">Aktif</span>'; ?>
                      </td>
                      <td><?php echo htmlspecialchars($r['kelas_nama']); ?></td>
                      <td>
                        <?php echo htmlspecialchars($r['mapel_nama']); ?>
                        <?php if(!empty($r['mapel_kode'])) echo ' <span class="badge-chip" style="margin-left:6px"><i class="fa fa-tag"></i>'.htmlspecialchars($r['mapel_kode']).'</span>'; ?>
                      </td>
                      <td>
                        <?php
                          echo htmlspecialchars($r['user_nama']);
                          $meta = role_badge_meta($r['user_level'] ?? '');
                          echo ' <span class="'.$meta['class'].'" title="Level: '.htmlspecialchars($meta['label']).'">'.
                               '<i class="fa fa-user-shield"></i> '.htmlspecialchars($meta['label']).'</span>';
                        ?>
                      </td>
                      <td>
                        <div class="btn-group" role="group" aria-label="opsi">
                          <button
                            type="button"
                            class="btn btn-warning btn-sm btn-edit"
                            title="Edit penugasan"
                            data-id="<?php echo (int)$r['id']; ?>"
                            data-ta="<?php echo (int)$r['ta_id']; ?>"
                            data-kelas="<?php echo (int)$r['kelas_id']; ?>"
                            data-mapel="<?php echo (int)$r['mapel_id']; ?>"
                            data-guru="<?php echo (int)$r['user_id']; ?>">
                            <i class="fa fa-cog"></i>
                          </button>
                          <form method="post" action="pengampu_mapel.php" class="eps-del-form" style="display:inline">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <?php echo epoin_csrf_field(); ?>
                            <button type="button"
                              class="btn btn-danger btn-sm btn-del-confirm"
                              title="Hapus penugasan"
                              data-nama="<?php echo htmlspecialchars($r['mapel_nama'].' — '.$r['kelas_nama'], ENT_QUOTES, 'UTF-8'); ?>">
                              <i class="fa fa-trash"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>

          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<!-- ===== Modal TAMBAH ===== -->
<div class="modal fade" id="modal_tambah" tabindex="-1" role="dialog" aria-labelledby="addLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="pengampu_mapel.php" autocomplete="off" id="formTambah">
        <input type="hidden" name="action" value="tambah">
        <?php echo epoin_csrf_field(); ?>
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title" id="addLabel"><i class="fa fa-plus-circle"></i> Tambah Penugasan</h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Tahun Ajaran</label>
            <select class="form-control sel2" name="ta_id" id="add_ta" required>
              <option value="">- Pilih TA -</option>
              <?php foreach($ta_opt as $ta){ ?>
                <option value="<?php echo (int)$ta['ta_id']; ?>" <?php echo ($ta_aktif_id==(int)$ta['ta_id']?'selected':''); ?>>
                  <?php echo htmlspecialchars($ta['ta_nama']); ?> <?php echo ($ta['ta_status']=='1'?'(Aktif)':''); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="form-group">
            <label>Kelas</label>
            <select class="form-control sel2" name="kelas_id" id="add_kelas" required>
              <option value="">- Pilih Kelas -</option>
              <?php foreach($kelas_opt as $k){ ?>
                <option value="<?php echo (int)$k['kelas_id']; ?>" data-ta="<?php echo (int)$k['kelas_ta']; ?>">
                  <?php echo htmlspecialchars($k['kelas_nama']); ?>
                </option>
              <?php } ?>
            </select>
            <small class="text-muted">Daftar kelas otomatis difilter sesuai TA terpilih.</small>
          </div>

          <div class="form-group">
            <label>Mata Pelajaran</label>
            <select class="form-control sel2" name="mapel_id" id="add_mapel" required>
              <option value="">- Pilih Mapel -</option>
              <?php foreach($mapel_opt as $m){ ?>
                <option value="<?php echo (int)$m['mapel_id']; ?>">
                  <?php echo htmlspecialchars($m['mapel_nama']); ?><?php echo $m['mapel_kode']?' ('.htmlspecialchars($m['mapel_kode']).')':''; ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="form-group">
            <label>Guru Pengampu</label>
            <select class="form-control sel2" name="guru_user_id" id="add_guru" required>
              <option value="">- Pilih Guru -</option>
              <?php foreach($guru_opt as $g){ ?>
                <option value="<?php echo (int)$g['user_id']; ?>">
                  <?php echo htmlspecialchars($g['user_nama']); ?><?php echo $g['user_level']?' ('.htmlspecialchars($g['user_level']).')':''; ?>
                </option>
              <?php } ?>
            </select>
          </div>

        </div>
        <div class="modal-footer" style="display:flex; gap:8px; justify-content:flex-end;">
          <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius:10px;">Batal</button>
          <button type="submit" class="btn btn-grad btn-sm"><i class="fa fa-save"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Modal EDIT ===== -->
<div class="modal fade" id="modal_edit" tabindex="-1" role="dialog" aria-labelledby="editLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post" action="pengampu_mapel.php" autocomplete="off" id="formEdit">
        <input type="hidden" name="action" value="edit">
        <?php echo epoin_csrf_field(); ?>
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title" id="editLabel"><i class="fa fa-pen-to-square"></i> Edit Penugasan</h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Tahun Ajaran</label>
            <select class="form-control sel2" name="ta_id" id="edit_ta" required>
              <option value="">- Pilih TA -</option>
              <?php foreach($ta_opt as $ta){ ?>
                <option value="<?php echo (int)$ta['ta_id']; ?>">
                  <?php echo htmlspecialchars($ta['ta_nama']); ?> <?php echo ($ta['ta_status']=='1'?'(Aktif)':''); ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="form-group">
            <label>Kelas</label>
            <select class="form-control sel2" name="kelas_id" id="edit_kelas" required>
              <option value="">- Pilih Kelas -</option>
              <?php foreach($kelas_opt as $k){ ?>
                <option value="<?php echo (int)$k['kelas_id']; ?>" data-ta="<?php echo (int)$k['kelas_ta']; ?>">
                  <?php echo htmlspecialchars($k['kelas_nama']); ?>
                </option>
              <?php } ?>
            </select>
            <small class="text-muted">Daftar kelas otomatis difilter sesuai TA terpilih.</small>
          </div>

          <div class="form-group">
            <label>Mata Pelajaran</label>
            <select class="form-control sel2" name="mapel_id" id="edit_mapel" required>
              <option value="">- Pilih Mapel -</option>
              <?php foreach($mapel_opt as $m){ ?>
                <option value="<?php echo (int)$m['mapel_id']; ?>">
                  <?php echo htmlspecialchars($m['mapel_nama']); ?><?php echo $m['mapel_kode']?' ('.htmlspecialchars($m['mapel_kode']).')':''; ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div class="form-group">
            <label>Guru Pengampu</label>
            <select class="form-control sel2" name="guru_user_id" id="edit_guru" required>
              <option value="">- Pilih Guru -</option>
              <?php foreach($guru_opt as $g){ ?>
                <option value="<?php echo (int)$g['user_id']; ?>">
                  <?php echo htmlspecialchars($g['user_nama']); ?><?php echo $g['user_level']?' ('.htmlspecialchars($g['user_level']).')':''; ?>
                </option>
              <?php } ?>
            </select>
          </div>

        </div>
        <div class="modal-footer" style="display:flex; gap:8px; justify-content:flex-end;">
          <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius:10px;">Batal</button>
          <button type="submit" class="btn btn-grad btn-sm"><i class="fa fa-save"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script>
$(function () {
  // ===== DataTables (setelah footer.php agar pakai DT 1.13.4) =====
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  var $tbl = $('#table-datatable');
  if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
    try { $tbl.DataTable().destroy(); } catch(e){}
    $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
  }
  if ($.fn.DataTable) {
    var t = $tbl.DataTable({
      destroy: true,
      autoWidth: false,
      order: [[1,'desc'],[2,'asc'],[3,'asc']], // TA terbaru, Kelas, Mapel
      columnDefs: [{ targets:[0,5], orderable:false }],
      pageLength: 10,
      lengthMenu: [[10,25,50,-1],[10,25,50,'Semua']],
      dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" + "rt" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampilkan _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        zeroRecords: 'Tidak ditemukan data yang cocok',
        infoFiltered: '(difilter dari total _MAX_ data)',
        paginate: { first:'Pertama', last:'Terakhir', next:'Berikutnya', previous:'Sebelumnya' }
      }
    });
    t.on('order.dt search.dt', function () {
      var i = 1;
      t.column(0, { search:'applied', order:'applied' }).nodes().each(function (cell) { cell.innerHTML = i++; });
    }).draw();
  }

  // ===== Select2 helper =====
  function initSel2($el, parent){
    if(!$el || !$el.length) return;
    $el.select2({
      width:'100%',
      minimumResultsForSearch:0,
      dropdownParent: parent || $('body'),
      placeholder: 'Pilih...'
    });
    $el.on('select2:open', function(){
      var sb = document.querySelector('.select2-container--open .select2-search__field');
      if (sb) sb.focus();
    });
  }

  // ===== Filter Kelas by TA (client-side, pakai data-ta pada <option>) =====
  function filterKelas($kelas, taId){
    $kelas.find('option').each(function(){
      var $opt = $(this);
      var dta = $opt.data('ta');
      if (!$opt.val()) { $opt.prop('disabled', false).show(); return; }
      if (!taId)       { $opt.prop('disabled', false).show(); return; }
      var match = String(dta) === String(taId);
      $opt.prop('disabled', !match);
      $opt.toggle(match);
    });
    var cur = $kelas.val();
    if (cur) {
      var curOpt = $kelas.find('option[value="'+cur+'"]');
      if (curOpt.length && curOpt.is(':disabled')) {
        $kelas.val('').trigger('change.select2');
      }
    }
  }

  // ===== Modal Tambah =====
  $('#modal_tambah').on('shown.bs.modal', function(){
    var $m = $(this);
    var $ta=$('#add_ta'), $kelas=$('#add_kelas'), $mapel=$('#add_mapel'), $guru=$('#add_guru');
    initSel2($ta,$m); initSel2($kelas,$m); initSel2($mapel,$m); initSel2($guru,$m);
    filterKelas($kelas, $ta.val());
    $ta.off('change._f').on('change._f', function(){ filterKelas($kelas, this.value); });
  });

  // ===== Modal Edit =====
  $(document).on('click', '.btn-edit', function(){
    var id=this.getAttribute('data-id'), ta=this.getAttribute('data-ta'),
        kelas=this.getAttribute('data-kelas'), mapel=this.getAttribute('data-mapel'),
        guru=this.getAttribute('data-guru');
    $('#edit_id').val(id);
    var $m = $('#modal_edit');
    $m.modal('show');
    $m.on('shown.bs.modal', function(){
      var $ta=$('#edit_ta'), $kelas=$('#edit_kelas'), $mapel=$('#edit_mapel'), $guru=$('#edit_guru');
      if(!$ta.hasClass('select2-hidden-accessible'))    initSel2($ta,$m);
      if(!$kelas.hasClass('select2-hidden-accessible')) initSel2($kelas,$m);
      if(!$mapel.hasClass('select2-hidden-accessible')) initSel2($mapel,$m);
      if(!$guru.hasClass('select2-hidden-accessible'))  initSel2($guru,$m);
      $ta.val(ta).trigger('change.select2');
      filterKelas($kelas, ta);
      $kelas.val(kelas).trigger('change.select2');
      $mapel.val(mapel).trigger('change.select2');
      $guru.val(guru).trigger('change.select2');
      $ta.off('change._f').on('change._f', function(){ filterKelas($kelas, this.value); });
    });
  });

});
</script>
