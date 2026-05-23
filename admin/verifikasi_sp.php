<?php
// verifikasi_sp.php — Halaman verifikasi SP sederhana
include '../koneksi.php';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id  = isset($_GET['v']) ? (int)$_GET['v'] : 0;
$nom = isset($_GET['n']) ? trim((string)$_GET['n']) : '';
$tgl = isset($_GET['t']) ? trim((string)$_GET['t']) : '';
$h   = isset($_GET['h']) ? trim((string)$_GET['h']) : '';

$expected = hash('sha256', $id.'|'.$nom.'|'.$tgl);
$hash_ok  = hash_equals($expected, $h);

$status   = 'INVALID';
$detail   = null;

if ($hash_ok && $id && $nom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$tgl)) {
  $q = mysqli_query($koneksi, "SELECT sl.*, s.siswa_nama, s.siswa_nis
    FROM sp_log sl
    LEFT JOIN siswa s ON s.siswa_id = sl.siswa_id
    WHERE sl.siswa_id='$id' AND sl.nomor='".mysqli_real_escape_string($koneksi,$nom)."' AND DATE(sl.tanggal)='".mysqli_real_escape_string($koneksi,$tgl)."'
    LIMIT 1");
  if ($q && $row = mysqli_fetch_assoc($q)) {
    $status = 'VALID';
    $detail = $row;
  }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Verifikasi SP</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{ --ink:#0f172a; --muted:#475569; --ok:#16a34a; --bad:#dc2626; --line:#e5e7eb;}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,'Open Sans','Helvetica Neue',sans-serif;background:#f8fafc;color:var(--ink);margin:0;padding:0}
    .wrap{max-width:720px;margin:24px auto;padding:16px}
    .card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 6px 18px rgba(2,6,23,.06);padding:18px}
    h1{margin:0 0 8px}
    .status{display:inline-block;border-radius:999px;padding:.2rem .6rem;font-weight:800;color:#fff}
    .ok{background:var(--ok)}
    .bad{background:var(--bad)}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border:1px solid var(--line);padding:8px 10px;vertical-align:top}
    th{width:220px;background:#f9fafb}
    .muted{color:var(--muted)}
    a{color:#2563eb;text-decoration:none}
    .foot{margin-top:14px;color:var(--muted);font-size:13px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Verifikasi Surat Peringatan (SP)</h1>
      <div class="muted">Token: <code><?php echo e(substr($h,0,16)); ?>…</code></div>
      <div style="margin-top:8px">
        <span class="status <?php echo $status==='VALID'?'ok':'bad'; ?>">
          <?php echo $status==='VALID'?'ASLI / TERDAFTAR':'TIDAK VALID'; ?>
        </span>
      </div>

      <?php if($status==='VALID' && $detail): ?>
        <table>
          <tr><th>Nama Siswa</th><td><?php echo e($detail['siswa_nama']); ?></td></tr>
          <tr><th>NIS</th><td><?php echo e($detail['siswa_nis']); ?></td></tr>
          <tr><th>Level SP</th><td><?php echo e($detail['sp_level']); ?></td></tr>
          <tr><th>Nomor Surat</th><td><?php echo e($detail['nomor']); ?></td></tr>
          <tr><th>Tanggal Penerbitan</th><td><?php echo e(date('d-m-Y', strtotime($detail['tanggal']))); ?></td></tr>
          <tr><th>Alasan Penerbitan</th><td><?php echo $detail['alasan']!==null && $detail['alasan']!=='' ? e($detail['alasan']) : '<span class="muted">—</span>'; ?></td></tr>
        </table>
        <div class="foot">Data ini diambil langsung dari sistem pada: <?php echo e(date('d-m-Y H:i')); ?></div>
      <?php else: ?>
        <p style="margin-top:12px">Data surat tidak ditemukan atau token tidak sesuai. Pastikan tautan/QR berasal dari surat resmi.</p>
      <?php endif; ?>

      <div class="foot">
        <a href="javascript:history.back()">← Kembali</a>
      </div>
    </div>
  </div>
</body>
</html>
