<?php
// admin/tools/reset_epoin.php
require_once '../koneksi.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Batasi ke admin saja (sesuaikan logika auth kamu)
if (!isset($_SESSION['level']) || $_SESSION['level'] !== 'administrator') {
  http_response_code(403);
  die('Akses khusus administrator.');
}

function db(){ global $koneksi; return $koneksi; }
function dbname(){
  $r = mysqli_query(db(),"SELECT DATABASE()"); $row = mysqli_fetch_row($r);
  return $row ? $row[0] : '';
}
function tbl_exists($t){
  $t = mysqli_real_escape_string(db(), $t);
  $d = mysqli_real_escape_string(db(), dbname());
  $sql = "SELECT 1 FROM information_schema.tables
          WHERE table_schema='$d' AND table_name='$t' LIMIT 1";
  $r = mysqli_query(db(), $sql);
  return $r && mysqli_num_rows($r) > 0;
}
function trunc_if_exists($t, &$log){
  if (tbl_exists($t)) {
    mysqli_query(db(),"SET FOREIGN_KEY_CHECKS=0");
    $ok = mysqli_query(db(),"TRUNCATE TABLE `$t`");
    mysqli_query(db(),"SET FOREIGN_KEY_CHECKS=1");
    $log[] = [$t, $ok ? 'OK' : 'GAGAL: '.mysqli_error(db())];
  }
}

$CANDIDATE_TX = [
  // transaksi pelanggaran
  'pelanggaran_siswa','transaksi_pelanggaran','riwayat_pelanggaran',
  // transaksi prestasi
  'prestasi_siswa','transaksi_prestasi','riwayat_prestasi',
  // rekap/akumulasi
  'poin_siswa','rekap_poin','rekap_siswa','log_poin'
];

$CANDIDATE_SISWA = [
  // relasi dulu
  'siswa_kelas','riwayat_kelas',
  // inti siswa
  'siswa'
];

$done = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $scope = $_POST['scope'] ?? '';
  if ($scope==='tx' || $scope==='all') {
    foreach($CANDIDATE_TX as $t) trunc_if_exists($t, $done);
  }
  if ($scope==='siswa' || $scope==='all') {
    foreach($CANDIDATE_SISWA as $t) trunc_if_exists($t, $done);
  }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reset E-Poin</title>
<link rel="stylesheet" href="../assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
<style>
  body{padding:24px}
  .card{border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
  .mini{font-size:12px;color:#666}
</style>
</head>
<body>
  <h3>Reset Data E-Poin</h3>
  <p class="mini">Selalu backup database terlebih dulu sebelum reset.</p>

  <div class="card">
    <form method="post">
      <div class="form-group">
        <label>Pilih cakupan reset:</label>
        <select name="scope" class="form-control" required>
          <option value="">— pilih —</option>
          <option value="tx">Level 1: Transaksi Poin saja (pelanggaran+prestasi+rekap)</option>
          <option value="siswa">Level 2: Data Siswa (termasuk relasi)</option>
          <option value="all">FULL: Transaksi Poin + Data Siswa</option>
        </select>
      </div>
      <button class="btn btn-danger" onclick="return confirm('Yakin reset? Tindakan ini tidak bisa dibatalkan.')">
        Reset Sekarang
      </button>
    </form>
  </div>

  <?php if($done): ?>
  <div class="card">
    <h4>Hasil</h4>
    <table class="table table-bordered table-condensed">
      <thead><tr><th>Tabel</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($done as $row): ?>
        <tr><td><?php echo htmlspecialchars($row[0]); ?></td><td><?php echo htmlspecialchars($row[1]); ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="mini">Hanya tabel yang ditemukan di database yang diproses.</p>
  </div>
  <?php endif; ?>

  <div class="card">
    <h4>Catatan</h4>
    <ul>
      <li>Master data <b>tidak</b> dihapus: <code>pelanggaran</code>, <code>prestasi</code>, <code>kelas</code>, <code>jurusan</code>, <code>ta</code>, dll.</li>
      <li>Setelah reset transaksi, dashboard bisa perlu refresh rekap (kalau sistem kamu pakai tabel rekap). Kalau top-10 masih tidak berubah, periksa query dashboard atau jalankan SQL agregasi ulang.</li>
    </ul>
  </div>
</body>
</html>
