<?php
/**
 * admin/role_permission.php — Manajemen Role & Permission (Matrix UI)
 * Sub-fase 3 RBAC: BACA + TULIS role_permissions (kelola hak akses per role).
 *
 * PENTING: enforcement BELUM aktif. Halaman ini hanya mengelola DATA mapping
 * role->permission. Menu/handler belum di-gate oleh permission (Sub-fase 4-5).
 *
 * Guard : admin-only (epoin_staff_guard(true)).
 * CSRF  : epoin_csrf_validate() TANPA argumen. Tulis = prepared statement.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/epoin_security.php';
require_once __DIR__ . '/../koneksi.php';

// ---- Guard admin-only (redirect non-admin / belum login) ----
$ME = epoin_staff_guard(true);

// ===================== Load master data (untuk render + validasi AJAX) =====================
$ROLES = [];
$rsR = mysqli_query($koneksi,
  "SELECT r.role_id, r.role_key, r.role_name, r.role_desc, r.is_system,
          (SELECT COUNT(*) FROM user_roles ur     WHERE ur.role_id = r.role_id) AS user_count,
          (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.role_id) AS perm_count
   FROM roles r ORDER BY r.sort_order, r.role_id");
while ($row = mysqli_fetch_assoc($rsR)) $ROLES[(int)$row['role_id']] = $row;

$PERMS = [];
$rsP = mysqli_query($koneksi,
  "SELECT perm_id, perm_key, perm_name, perm_group, perm_type
   FROM permissions ORDER BY sort_order, perm_id");
while ($row = mysqli_fetch_assoc($rsP)) $PERMS[(int)$row['perm_id']] = $row;

$SUPER_ID = 0;
foreach ($ROLES as $rid => $r) { if ($r['role_key'] === 'superadmin') { $SUPER_ID = (int)$rid; break; } }

// ===================== BLOK AJAX (JSON) — sebelum output apa pun =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if (!epoin_csrf_validate()) {
    http_response_code(419);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'CSRF token invalid']); exit;
  }
  header('Content-Type: application/json; charset=utf-8');
  $act = $_POST['action'];

  // --- Simpan delta matrix (INSERT/DELETE role_permissions) ---
  if ($act === 'save_matrix') {
    $changes = json_decode($_POST['changes'] ?? '[]', true);
    if (!is_array($changes)) { echo json_encode(['ok'=>false,'msg'=>'Format perubahan tidak valid']); exit; }

    $ins = mysqli_prepare($koneksi, "INSERT IGNORE INTO role_permissions (role_id, perm_id) VALUES (?, ?)");
    $del = mysqli_prepare($koneksi, "DELETE FROM role_permissions WHERE role_id = ? AND perm_id = ?");
    if (!$ins || !$del) { echo json_encode(['ok'=>false,'msg'=>'Gagal menyiapkan query']); exit; }

    $applied = 0; $skipped = 0;
    mysqli_begin_transaction($koneksi);
    try {
      foreach ($changes as $c) {
        $rid   = (int)($c['role_id'] ?? 0);
        $pid   = (int)($c['perm_id'] ?? 0);
        $grant = !empty($c['granted']) ? 1 : 0;
        // Validasi: role & perm harus ada; superadmin (wildcard) dilindungi.
        if (!isset($ROLES[$rid]) || !isset($PERMS[$pid])) { $skipped++; continue; }
        if ($rid === $SUPER_ID) { $skipped++; continue; }
        if ($grant) { mysqli_stmt_bind_param($ins, 'ii', $rid, $pid); mysqli_stmt_execute($ins); }
        else        { mysqli_stmt_bind_param($del, 'ii', $rid, $pid); mysqli_stmt_execute($del); }
        $applied++;
      }
      mysqli_commit($koneksi);
    } catch (\Throwable $e) {
      mysqli_rollback($koneksi);
      echo json_encode(['ok'=>false,'msg'=>'Gagal menyimpan perubahan']); exit;
    }
    mysqli_stmt_close($ins); mysqli_stmt_close($del);

    // Jaga konsistensi cache: refresh perms sesi admin yang sedang login.
    if (function_exists('epoin_refresh_session_perms')) {
      epoin_refresh_session_perms((int)($_SESSION['id'] ?? 0));
    }

    // Hitung ulang jumlah permission per role (untuk update badge UI).
    $counts = [];
    $rc = mysqli_query($koneksi, "SELECT role_id, COUNT(*) AS n FROM role_permissions GROUP BY role_id");
    while ($row = mysqli_fetch_assoc($rc)) $counts[(int)$row['role_id']] = (int)$row['n'];

    echo json_encode(['ok'=>true, 'applied'=>$applied, 'skipped'=>$skipped, 'counts'=>$counts]); exit;
  }

  // --- Update deskripsi role ---
  if ($act === 'update_role_desc') {
    $rid  = (int)($_POST['role_id'] ?? 0);
    $desc = trim((string)($_POST['role_desc'] ?? ''));
    if (!isset($ROLES[$rid])) { echo json_encode(['ok'=>false,'msg'=>'Role tidak valid']); exit; }
    if (mb_strlen($desc) > 255) $desc = mb_substr($desc, 0, 255);
    $st = mysqli_prepare($koneksi, "UPDATE roles SET role_desc = ? WHERE role_id = ?");
    mysqli_stmt_bind_param($st, 'si', $desc, $rid);
    mysqli_stmt_execute($st); mysqli_stmt_close($st);
    echo json_encode(['ok'=>true, 'role_desc'=>$desc]); exit;
  }

  echo json_encode(['ok'=>false, 'msg'=>'Aksi tidak dikenal']); exit;
}

// ===================== Data untuk render matrix =====================
$GRANT = []; // [role_id][perm_id] = 1
$rg = mysqli_query($koneksi, "SELECT role_id, perm_id FROM role_permissions");
while ($row = mysqli_fetch_assoc($rg)) $GRANT[(int)$row['role_id']][(int)$row['perm_id']] = 1;

// Metadata grup (urutan tampil + label + ikon)
$GROUPS = [
  'umum'      => ['Umum',          'fa-gauge-high'],
  'master'    => ['Master Data',   'fa-database'],
  'kategori'  => ['Kategori Poin', 'fa-tags'],
  'poin'      => ['Kelola Poin',   'fa-star'],
  'absensi'   => ['Absensi',       'fa-user-check'],
  'penilaian' => ['Penilaian',     'fa-pen-to-square'],
  'ujian'     => ['Ujian & CBT',   'fa-laptop-code'],
  'sistem'    => ['Sistem',        'fa-gear'],
  'lainnya'   => ['Lainnya',       'fa-ellipsis'],
];

// Kelompokkan perm per grup (urut sesuai $PERMS yang sudah ORDER BY sort_order)
$byGroup = [];
foreach ($PERMS as $pid => $p) { $byGroup[$p['perm_group']][] = $pid; }

$PAGE_TITLE = 'Manajemen Role & Permission';
include __DIR__ . '/header.php';
?>

<style>
/* ===== Role & Permission Matrix — EPOIN ===== */
:root{
  --rp-blue:#2563eb; --rp-blue-d:#1d4ed8; --rp-ink:#0f172a; --rp-line:#e6ebf5;
  --rp-green:#16a34a; --rp-green-bg:#e9f9ef; --rp-amber:#b45309; --rp-amber-bg:#fef3c7;
}
.content-wrapper{ background:linear-gradient(180deg,#f7faff,#f3f7ff); }
.rp-title{ display:flex;align-items:center;gap:12px;font-size:clamp(20px,2.4vw,26px);font-weight:800;color:var(--rp-ink); }
.rp-title .ic{ width:42px;height:42px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:#e8f0fe;color:var(--rp-blue);box-shadow:inset 0 0 0 1px var(--rp-line); }
.rp-box{ border:0;border-radius:16px;box-shadow:0 10px 30px rgba(45,108,223,.12);background:#fff;overflow:hidden;margin-bottom:18px; }
.rp-box .nav-tabs-custom{ box-shadow:none;margin:0; }

.rp-toolbar{ display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 16px;border-bottom:1px solid var(--rp-line);background:#fbfdff; }
.rp-toolbar .spacer{ flex:1 1 auto; }
.rp-search{ position:relative; }
.rp-search input{ border:1px solid var(--rp-line);border-radius:10px;padding:8px 12px 8px 32px;min-width:230px;font-size:13px; }
.rp-search i{ position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8; }
.btn-rp{ border:1px solid var(--rp-line);background:#fff;border-radius:10px;padding:8px 12px;font-weight:600;font-size:13px;color:#334155; }
.btn-rp:hover{ background:#eef4ff;color:var(--rp-blue-d); }
.btn-rp-primary{ background:linear-gradient(90deg,var(--rp-blue-d),var(--rp-blue));color:#fff;border:0;box-shadow:0 8px 20px rgba(45,108,223,.25); }
.btn-rp-primary:hover{ filter:brightness(1.06);color:#fff; }
.btn-rp-primary:disabled{ opacity:.5;cursor:not-allowed;box-shadow:none; }
.dirty-badge{ display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:13px;color:#b45309; }
.dirty-badge.clean{ color:#64748b; }
.dirty-dot{ width:9px;height:9px;border-radius:50%;background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.18); }
.dirty-badge.clean .dirty-dot{ background:#cbd5e1;box-shadow:none; }

.rp-legend{ display:flex;gap:16px;flex-wrap:wrap;padding:8px 16px;font-size:12px;color:#475569;background:#fff;border-bottom:1px solid var(--rp-line); }
.rp-legend .lg{ display:inline-flex;align-items:center;gap:6px; }
.pill{ display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:6px;font-size:11px; }
.pill-menu{ color:var(--rp-blue);background:#e8f0fe; }
.pill-aksi{ color:var(--rp-amber);background:var(--rp-amber-bg); }
.sw{ width:14px;height:14px;border-radius:4px;display:inline-block;border:1px solid var(--rp-line); }
.sw-on{ background:var(--rp-green-bg);border-color:#bbf7d0; }
.sw-off{ background:#f1f5f9; }
.sw-lock{ background:#eef2ff;border-color:#c7d2fe; }

/* ----- Matrix layout (table-layout:fixed — kolom tidak melar) ----- */
.matrix-scroll{ overflow-x:auto; overflow-y:auto; max-height:72vh; }
table.matrix{ border-collapse:separate; border-spacing:0; table-layout:fixed; font-size:13px; }

/* Lebar kolom dikontrol via <colgroup> */
table.matrix col.col-perm{ width:270px; }
table.matrix col.col-role{ width:78px; }

/* Garis grid */
.matrix th,.matrix td{ border-bottom:1px solid #e8edf6; border-right:1px solid #e8edf6; box-sizing:border-box; background:#fff; }
.matrix th:last-child,.matrix td:last-child{ border-right:none; }

/* Sticky header atas */
.matrix thead th{ position:sticky; top:0; z-index:5; }
/* Sticky kolom kiri */
.matrix .sticky-left{ position:sticky; left:0; z-index:4; background:#fff; }
/* Corner = sticky atas + kiri */
.matrix thead th.corner{ left:0; z-index:8; background:#f1f6ff; text-align:left; padding:10px 14px; color:#1e293b; font-weight:800; font-size:12px; }

/* Header kolom role — seragam, tidak melar */
.role-col{ vertical-align:bottom; text-align:center; padding:10px 3px 8px; cursor:pointer; transition:background .15s; background:#f7faff; overflow:hidden; }
.role-col:hover{ background:#e9f1ff; }
.role-col.is-super{ cursor:default; background:#f5f3ff; }
.role-col .rn{ font-weight:700; color:#1e293b; font-size:11px; line-height:1.25; display:block; word-break:break-word; overflow-wrap:break-word; hyphens:auto; overflow:hidden; }
.role-col .rk{ display:block; font-size:9px; color:#94a3b8; margin-top:2px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.role-col .sysbadge{ display:inline-block; margin-top:2px; font-size:8px; font-weight:800; color:#7c3aed; background:#ede9fe; border-radius:5px; padding:1px 4px; }
.role-col .toggle-hint{ display:block; margin-top:4px; color:#94a3b8; font-size:9.5px; }
.role-col.is-super .lockwrap{ display:block; margin-top:3px; color:#6366f1; font-size:9.5px; font-weight:700; }

/* Group header row */
tr.group-row .group-th{ position:sticky; left:0; z-index:4; background:#eef4ff; cursor:pointer; padding:9px 14px; font-weight:800; color:#1e3a8a; overflow:hidden; }
tr.group-row .group-fill{ background:#eef4ff; }
tr.group-row .group-th .chev{ transition:transform .2s; margin-right:8px; color:#3b82f6; }
tr.group-row.open .group-th .chev{ transform:rotate(90deg); }
tr.group-row .gcount{ font-weight:700; color:#3b82f6; font-size:11px; background:#fff; border-radius:999px; padding:1px 8px; margin-left:8px; }

/* Permission rows */
tr.perm-row{ display:none; }
tr.perm-row.show{ display:table-row; }
tr.perm-row:hover td, tr.perm-row:hover th{ background:#f0f5ff !important; }

.perm-th.sticky-left{ background:#fff; text-align:left; padding:7px 14px; color:#0f172a; overflow:hidden; }
.perm-th .pname{ font-weight:600; font-size:12.5px; }
.perm-th code{ display:block; font-size:10px; color:#94a3b8; background:transparent; padding:0; margin-top:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ptype{ display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:5px; font-size:9.5px; margin-right:6px; vertical-align:middle; flex-shrink:0; }
.ptype-menu{ color:var(--rp-blue); background:#e8f0fe; }
.ptype-aksi{ color:var(--rp-amber); background:var(--rp-amber-bg); }

/* Sel data — checkbox tepat tengah */
.cell{ text-align:center; vertical-align:middle; padding:5px 0; transition:background .12s; }
.cell.granted{ background:var(--rp-green-bg); }
.cell.locked{ background:#eef2ff; color:#6366f1; }
.cell input[type=checkbox]{ width:16px; height:16px; cursor:pointer; accent-color:#16a34a; display:block; margin:0 auto; }
.cell.dirty{ box-shadow:inset 0 0 0 2px #f59e0b; }

/* Role list (Tab 2) */
.rolelist{ width:100%; font-size:13.5px; }
.rolelist th{ background:#f7faff; color:#334155; border-bottom:2px solid var(--rp-line); padding:10px 12px; text-align:left; }
.rolelist td{ border-bottom:1px solid #eef1f7; padding:10px 12px; vertical-align:middle; }
.rolelist tr:hover td{ background:#f8fbff; }
.chip{ display:inline-block; border-radius:999px; padding:2px 10px; font-size:11.5px; font-weight:700; }
.chip-sys{ background:#ede9fe; color:#7c3aed; }
.chip-num{ background:#e8f0fe; color:#1d4ed8; }
.chip-user{ background:#dcfce7; color:#15803d; }

@media (max-width:768px){
  table.matrix col.col-perm{ width:200px; }
  table.matrix col.col-role{ width:62px; }
}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1 class="rp-title">
      <span class="ic"><i class="fa-solid fa-user-shield"></i></span>
      <span>Manajemen Role &amp; Permission</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Home</a></li>
      <li><a href="manajemen_pengguna.php">Pengguna</a></li>
      <li class="active">Role &amp; Hak Akses</li>
    </ol>
  </section>

  <section class="content">

    <div class="callout" style="background:#fff7ed;border-left:4px solid #f59e0b;border-radius:10px;margin-bottom:14px;">
      <i class="fa fa-circle-info" style="color:#b45309"></i>
      <b>Catatan:</b> Halaman ini mengelola <b>data</b> hak akses (role &rarr; permission). Penerapan ke menu/halaman
      (enforcement) <b>belum diaktifkan</b> — akses aplikasi masih seperti sekarang. Perubahan di sini menjadi acuan saat enforcement dinyalakan.
    </div>

    <div class="rp-box">
      <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
          <li class="active"><a href="#tab-matrix" data-toggle="tab"><i class="fa fa-table-cells"></i> Matrix Permission</a></li>
          <li><a href="#tab-roles" data-toggle="tab"><i class="fa fa-user-tag"></i> Daftar Role</a></li>
        </ul>

        <div class="tab-content" style="padding:0;">
          <!-- ============== TAB 1: MATRIX ============== -->
          <div class="tab-pane active" id="tab-matrix">

            <div class="rp-toolbar">
              <div class="rp-search">
                <i class="fa fa-search"></i>
                <input type="text" id="permSearch" placeholder="Cari permission…" autocomplete="off">
              </div>
              <button type="button" class="btn-rp" id="btnExpandAll"><i class="fa fa-angles-down"></i> Buka semua</button>
              <button type="button" class="btn-rp" id="btnCollapseAll"><i class="fa fa-angles-up"></i> Tutup semua</button>
              <div class="spacer"></div>
              <span class="dirty-badge clean" id="dirtyBadge"><span class="dirty-dot"></span> <span id="dirtyText">Tidak ada perubahan</span></span>
              <button type="button" class="btn-rp" id="btnRevert" disabled><i class="fa fa-rotate-left"></i> Batalkan</button>
              <button type="button" class="btn-rp btn-rp-primary" id="btnSave" disabled><i class="fa fa-floppy-disk"></i> Simpan Perubahan</button>
            </div>

            <div class="rp-legend">
              <span class="lg"><span class="pill pill-menu"><i class="fa fa-eye"></i></span> Permission tipe <b>menu</b> (akses halaman)</span>
              <span class="lg"><span class="pill pill-aksi"><i class="fa fa-bolt"></i></span> Permission tipe <b>aksi</b> (CRUD/operasi)</span>
              <span class="lg"><span class="sw sw-on"></span> Diberikan</span>
              <span class="lg"><span class="sw sw-off"></span> Tidak</span>
              <span class="lg"><span class="sw sw-lock"></span> Superadmin (akses penuh, terkunci)</span>
            </div>

            <div class="matrix-scroll">
              <table class="matrix">
                <colgroup>
                  <col class="col-perm">
                  <?php foreach ($ROLES as $rid => $r): ?><col class="col-role"><?php endforeach; ?>
                </colgroup>
                <thead>
                  <tr>
                    <th class="corner">Permission \ Role</th>
                    <?php foreach ($ROLES as $rid => $r):
                      $isSuper = ((int)$rid === $SUPER_ID); ?>
                      <th class="role-col <?= $isSuper?'is-super':'' ?>"
                          data-role="<?= (int)$rid ?>"
                          title="<?= epoin_h($r['role_name']) ?> (<?= epoin_h($r['role_key']) ?>)">
                        <span class="rn"><?= epoin_h($r['role_name']) ?></span>
                        <span class="rk"><?= epoin_h($r['role_key']) ?></span>
                        <?php if ((int)$r['is_system'] === 1): ?><span class="sysbadge">SISTEM</span><?php endif; ?>
                        <?php if ($isSuper): ?>
                          <span class="lockwrap"><i class="fa fa-lock"></i> penuh</span>
                        <?php else: ?>
                          <span class="toggle-hint"><i class="fa fa-hand-pointer"></i> klik</span>
                        <?php endif; ?>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($GROUPS as $gkey => $gmeta):
                    if (empty($byGroup[$gkey])) continue;
                    $pids = $byGroup[$gkey]; ?>
                    <tr class="group-row" data-group="<?= epoin_h($gkey) ?>">
                      <th class="group-th">
                        <i class="fa fa-angle-right chev"></i>
                        <i class="fa <?= epoin_h($gmeta[1]) ?>"></i>
                        <?= epoin_h($gmeta[0]) ?>
                        <span class="gcount"><?= count($pids) ?> izin</span>
                      </th>
                      <td class="group-fill" colspan="<?= count($ROLES) ?>"></td>
                    </tr>
                    <?php foreach ($pids as $pid):
                      $p = $PERMS[$pid];
                      $isMenu = ($p['perm_type'] === 'menu'); ?>
                      <tr class="perm-row grp-<?= epoin_h($gkey) ?>" data-group="<?= epoin_h($gkey) ?>"
                          data-name="<?= epoin_h(mb_strtolower($p['perm_name'].' '.$p['perm_key'])) ?>">
                        <th class="perm-th sticky-left">
                          <span class="ptype <?= $isMenu?'ptype-menu':'ptype-aksi' ?>">
                            <i class="fa <?= $isMenu?'fa-eye':'fa-bolt' ?>"></i>
                          </span>
                          <span class="pname"><?= epoin_h($p['perm_name']) ?></span>
                          <code><?= epoin_h($p['perm_key']) ?></code>
                        </th>
                        <?php foreach ($ROLES as $rid => $r):
                          $isSuper = ((int)$rid === $SUPER_ID);
                          $on = $isSuper ? true : !empty($GRANT[$rid][$pid]); ?>
                          <?php if ($isSuper): ?>
                            <td class="cell granted locked" title="Superadmin: akses penuh">
                              <i class="fa fa-lock"></i>
                            </td>
                          <?php else: ?>
                            <td class="cell <?= $on?'granted':'' ?>" data-role="<?= (int)$rid ?>" data-perm="<?= (int)$pid ?>">
                              <input type="checkbox" class="chk" data-orig="<?= $on?1:0 ?>" <?= $on?'checked':'' ?>>
                            </td>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ============== TAB 2: DAFTAR ROLE ============== -->
          <div class="tab-pane" id="tab-roles">
            <div style="padding:14px 16px;">
              <table class="rolelist">
                <thead>
                  <tr>
                    <th style="width:1%">#</th>
                    <th>Role</th>
                    <th>Deskripsi</th>
                    <th style="width:1%;text-align:center">Permission</th>
                    <th style="width:1%;text-align:center">Pengguna</th>
                    <th style="width:1%;text-align:center">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $no=1; foreach ($ROLES as $rid => $r): ?>
                    <tr data-role="<?= (int)$rid ?>">
                      <td><?= $no++ ?></td>
                      <td>
                        <b><?= epoin_h($r['role_name']) ?></b>
                        <?php if ((int)$r['is_system'] === 1): ?> <span class="chip chip-sys">Sistem</span><?php endif; ?>
                        <div style="font-size:11px;color:#94a3b8;font-weight:600"><?= epoin_h($r['role_key']) ?></div>
                      </td>
                      <td class="role-desc-cell"><?= $r['role_desc'] !== null && $r['role_desc'] !== '' ? epoin_h($r['role_desc']) : '<span class="text-muted" style="color:#94a3b8">— belum ada —</span>' ?></td>
                      <td style="text-align:center"><span class="chip chip-num perm-count-badge"><?= (int)$r['perm_count'] ?></span></td>
                      <td style="text-align:center"><span class="chip chip-user"><?= (int)$r['user_count'] ?></span></td>
                      <td style="text-align:center">
                        <button type="button" class="btn-rp btn-edit-desc"
                                data-role="<?= (int)$rid ?>"
                                data-name="<?= epoin_h($r['role_name']) ?>"
                                data-desc="<?= epoin_h((string)$r['role_desc']) ?>">
                          <i class="fa fa-pen"></i> Deskripsi
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <p style="margin-top:12px;font-size:12px;color:#64748b">
                <i class="fa fa-circle-info"></i> Role bertanda <b>Sistem</b> tidak dapat dihapus. Superadmin selalu memiliki akses penuh (wildcard) dan terkunci di matrix.
              </p>
            </div>
          </div>

        </div>
      </div>
    </div>

  </section>
</div>

<?php include __DIR__ . '/footer.php'; ?>

<!-- SweetAlert2 (pastikan tersedia untuk toast/konfirmasi) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
  var CSRF_TOKEN = '<?= epoin_csrf_token() ?>';
  var SELF = 'role_permission.php';
  var $matrix = document.querySelector('table.matrix');

  /* ---------- Toast helper ---------- */
  function toast(icon, title){
    if (window.Swal){
      Swal.fire({ toast:true, position:'top-end', icon:icon, title:title, showConfirmButton:false, timer:2200, timerProgressBar:true });
    }
  }

  /* ---------- DIRTY tracking ---------- */
  var DIRTY = new Map(); // key "rid:pid" -> granted(0/1)
  function keyOf(rid,pid){ return rid+':'+pid; }
  function refreshDirtyUI(){
    var n = DIRTY.size;
    var badge=document.getElementById('dirtyBadge'), txt=document.getElementById('dirtyText');
    var save=document.getElementById('btnSave'), rev=document.getElementById('btnRevert');
    if (n>0){
      badge.classList.remove('clean'); txt.textContent = n+' perubahan belum disimpan';
      save.disabled=false; rev.disabled=false;
    } else {
      badge.classList.add('clean'); txt.textContent='Tidak ada perubahan';
      save.disabled=true; rev.disabled=true;
    }
  }
  function setCell(td, checked){
    var chk = td.querySelector('input.chk'); if(!chk) return;
    chk.checked = checked;
    td.classList.toggle('granted', checked);
    var rid=+td.dataset.role, pid=+td.dataset.perm, orig=(chk.dataset.orig==='1');
    if (checked===orig){ DIRTY.delete(keyOf(rid,pid)); td.classList.remove('dirty'); }
    else { DIRTY.set(keyOf(rid,pid), checked?1:0); td.classList.add('dirty'); }
  }

  /* ---------- Checkbox toggle ---------- */
  if ($matrix){
    $matrix.addEventListener('change', function(e){
      var chk = e.target.closest('input.chk'); if(!chk) return;
      var td = chk.closest('td.cell'); setCell(td, chk.checked); refreshDirtyUI();
    });

    /* ---------- Klik header role -> toggle seluruh kolom ---------- */
    $matrix.querySelectorAll('thead th.role-col:not(.is-super)').forEach(function(th){
      th.addEventListener('click', function(){
        var rid = th.dataset.role;
        var cells = $matrix.querySelectorAll('td.cell[data-role="'+rid+'"]');
        var allOn = true;
        cells.forEach(function(td){ var c=td.querySelector('input.chk'); if(c && !c.checked) allOn=false; });
        var target = !allOn; // kalau belum semua -> nyalakan semua; kalau semua -> matikan
        cells.forEach(function(td){ setCell(td, target); });
        refreshDirtyUI();
      });
    });
  }

  /* ---------- Expand / collapse grup ---------- */
  function setGroup(groupRow, open){
    groupRow.classList.toggle('open', open);
    var g = groupRow.dataset.group;
    document.querySelectorAll('tr.perm-row.grp-'+g).forEach(function(tr){ tr.classList.toggle('show', open); });
  }
  document.querySelectorAll('tr.group-row').forEach(function(gr){
    gr.addEventListener('click', function(){ setGroup(gr, !gr.classList.contains('open')); });
  });
  document.getElementById('btnExpandAll').addEventListener('click', function(){
    document.querySelectorAll('tr.group-row').forEach(function(gr){ setGroup(gr, true); });
  });
  document.getElementById('btnCollapseAll').addEventListener('click', function(){
    document.querySelectorAll('tr.group-row').forEach(function(gr){ setGroup(gr, false); });
  });

  /* ---------- Search ---------- */
  var searchEl = document.getElementById('permSearch');
  searchEl.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    if (!q){
      document.querySelectorAll('tr.perm-row').forEach(function(tr){ tr.style.display=''; tr.classList.remove('show'); });
      document.querySelectorAll('tr.group-row').forEach(function(gr){ setGroup(gr, false); gr.style.display=''; });
      return;
    }
    var groupHit = {};
    document.querySelectorAll('tr.perm-row').forEach(function(tr){
      var hit = tr.dataset.name.indexOf(q) !== -1;
      tr.style.display = hit ? 'table-row' : 'none';
      tr.classList.toggle('show', hit);
      if (hit) groupHit[tr.dataset.group] = true;
    });
    document.querySelectorAll('tr.group-row').forEach(function(gr){
      var has = !!groupHit[gr.dataset.group];
      gr.style.display = has ? '' : 'none';
      gr.classList.toggle('open', has);
    });
  });

  /* ---------- Simpan ---------- */
  document.getElementById('btnSave').addEventListener('click', function(){
    if (DIRTY.size===0) return;
    var changes=[];
    DIRTY.forEach(function(granted,k){ var p=k.split(':'); changes.push({role_id:+p[0],perm_id:+p[1],granted:granted}); });
    var btn=this; btn.disabled=true; btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> Menyimpan…';
    var fd=new FormData();
    fd.append('action','save_matrix'); fd.append('_csrf',CSRF_TOKEN); fd.append('changes',JSON.stringify(changes));
    fetch(SELF,{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.ok){
          // commit: jadikan state sekarang sbg "original"
          DIRTY.forEach(function(granted,k){
            var p=k.split(':'); var td=$matrix.querySelector('td.cell[data-role="'+p[0]+'"][data-perm="'+p[1]+'"]');
            if (td){ var c=td.querySelector('input.chk'); if(c) c.dataset.orig = granted?'1':'0'; td.classList.remove('dirty'); }
          });
          DIRTY.clear(); refreshDirtyUI();
          if (res.counts){ // update badge jumlah permission di Tab 2
            Object.keys(res.counts).forEach(function(rid){
              var row=document.querySelector('.rolelist tr[data-role="'+rid+'"] .perm-count-badge');
              if (row) row.textContent = res.counts[rid];
            });
          }
          toast('success','Tersimpan ('+res.applied+' perubahan)');
        } else {
          toast('error', res.msg || 'Gagal menyimpan');
        }
      })
      .catch(function(){ toast('error','Gangguan jaringan'); })
      .finally(function(){ btn.innerHTML='<i class="fa fa-floppy-disk"></i> Simpan Perubahan'; refreshDirtyUI(); });
  });

  /* ---------- Batalkan ---------- */
  document.getElementById('btnRevert').addEventListener('click', function(){
    DIRTY.forEach(function(granted,k){
      var p=k.split(':'); var td=$matrix.querySelector('td.cell[data-role="'+p[0]+'"][data-perm="'+p[1]+'"]');
      if (td){ var c=td.querySelector('input.chk'); var orig=(c.dataset.orig==='1'); c.checked=orig; td.classList.toggle('granted',orig); td.classList.remove('dirty'); }
    });
    DIRTY.clear(); refreshDirtyUI();
  });

  /* ---------- Peringatan kalau pindah halaman saat ada perubahan ---------- */
  window.addEventListener('beforeunload', function(e){ if (DIRTY.size>0){ e.preventDefault(); e.returnValue=''; } });

  /* ---------- Edit deskripsi role (Tab 2) ---------- */
  document.querySelectorAll('.btn-edit-desc').forEach(function(btn){
    btn.addEventListener('click', function(){
      var rid=btn.dataset.role, name=btn.dataset.name, desc=btn.dataset.desc||'';
      if (!window.Swal){ return; }
      Swal.fire({
        title:'Deskripsi: '+name,
        input:'textarea', inputValue:desc, inputAttributes:{maxlength:255,'aria-label':'Deskripsi role'},
        inputPlaceholder:'Tulis deskripsi singkat peran ini…',
        showCancelButton:true, confirmButtonText:'Simpan', cancelButtonText:'Batal',
        customClass:{popup:'swal2-brand'}
      }).then(function(r){
        if (!r.isConfirmed) return;
        var newDesc=(r.value||'').trim();
        var fd=new FormData(); fd.append('action','update_role_desc'); fd.append('_csrf',CSRF_TOKEN);
        fd.append('role_id',rid); fd.append('role_desc',newDesc);
        fetch(SELF,{method:'POST',body:fd,credentials:'same-origin'})
          .then(function(x){return x.json();})
          .then(function(res){
            if (res.ok){
              var cell=document.querySelector('.rolelist tr[data-role="'+rid+'"] .role-desc-cell');
              if (cell){ cell.textContent = res.role_desc || ''; if(!res.role_desc){ cell.innerHTML='<span style="color:#94a3b8">— belum ada —</span>'; } }
              btn.dataset.desc=res.role_desc||'';
              toast('success','Deskripsi diperbarui');
            } else { toast('error',res.msg||'Gagal'); }
          })
          .catch(function(){ toast('error','Gangguan jaringan'); });
      });
    });
  });

  refreshDirtyUI();
})();
</script>
