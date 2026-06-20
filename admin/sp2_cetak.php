<?php
// sp2_cetak.php — Surat Peringatan 2; delegasi ke sp1_cetak.php dengan level SP2
$_GET['sp'] = 'SP2';
require __DIR__ . '/sp1_cetak.php';
