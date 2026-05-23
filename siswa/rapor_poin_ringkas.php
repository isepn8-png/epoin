<?php
include '../koneksi.php';
session_start();
if($_SESSION['level'] != "siswa"){ header("location:../index.php?alert=belum_login"); exit; }
$id_siswa = $_SESSION['id'];
$profil = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT * FROM siswa WHERE siswa_id='".intval($id_siswa)."'"));

$q_plus = mysqli_query($koneksi,"SELECT COALESCE(SUM(p.prestasi_point),0) AS plus_poin
                                 FROM input_prestasi ip JOIN prestasi p ON p.prestasi_id=ip.prestasi
                                 WHERE ip.siswa='".intval($id_siswa)."'");
$plus = (int)mysqli_fetch_assoc($q_plus)['plus_poin'];
$q_min = mysqli_query($koneksi,"SELECT COALESCE(SUM(pg.pelanggaran_point),0) AS minus_poin
                                FROM input_pelanggaran ig JOIN pelanggaran pg ON pg.pelanggaran_id=ig.pelanggaran
                                WHERE ig.siswa='".intval($id_siswa)."'");
$minus = (int)mysqli_fetch_assoc($q_min)['minus_poin'];
$total_poin = $plus - $minus;
?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rapor Poin Ringkas</title><style>body{font-family:Arial;margin:20px} .box{border:1px solid #ddd;border-radius:8px;padding:16px;max-width:720px;margin:auto}</style></head>
<body><div class="box">
  <h2>Rapor Poin Ringkas</h2>
  <p><strong><?php echo htmlspecialchars($profil['siswa_nama']); ?></strong> — NIS: <?php echo htmlspecialchars($profil['siswa_nis']); ?></p>
  <h3>Total Poin: <?php echo (int)$total_poin; ?></h3>
  <p>Prestasi (+): <?php echo (int)$plus; ?> | Pelanggaran (−): <?php echo (int)$minus; ?></p>
  <p>Generated at <?php echo date('d M Y H:i'); ?></p>
</div></body></html>