<?php
// db_check_nh.php — debug: inspeksi skema tabel (ADMIN ONLY)
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../koneksi.php'; // path benar (root), sebelumnya salah -> fatal
require_once __DIR__.'/../includes/epoin_security.php';
// [SECURITY] Endpoint debug ini membocorkan struktur DB; batasi hanya admin login.
epoin_staff_guard(true);

function cols($c,$t){$a=[];$q=@mysqli_query($c,"SHOW COLUMNS FROM `$t`");if($q){while($r=mysqli_fetch_assoc($q))$a[]=$r['Field'];}return $a;}
$tables=['mapel','kelas','kelas_siswa','siswa','tujuan_pembelajaran'];
$out=[];
foreach($tables as $t){ $out[$t]=[ 'exists'=> (bool)@mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$t)."'"),
                                    'columns'=> cols($koneksi,$t) ]; }
header('Content-Type: application/json'); echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
