<?php
// epoin/includes/absensi_helper.php
// Kumpulan helper absensi (sinkronisasi Harian <-> Mapel)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../koneksi.php';

function __get_ta_aktif(){
  global $koneksi;
  $q = mysqli_query($koneksi,"SELECT ta_id FROM ta WHERE ta_status=1 LIMIT 1");
  if($q && $r=mysqli_fetch_assoc($q)) return (int)$r['ta_id'];
  return 0;
}

/**
 * Sinkronkan absensi harian dari sesi-sesi mapel di tanggal & kelas tertentu.
 * Lihat penjelasan parameter $metode pada data_absensi.php
 */
function sinkron_harian_dari_sesi($tanggal, $kelas_id, $metode='mayoritas'){
  global $koneksi;
  $user_id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
  $TA = __get_ta_aktif();
  $tanggal = mysqli_real_escape_string($koneksi, $tanggal);
  $kelas   = (int)$kelas_id;

  // Pastikan header harian ada
  $cek = mysqli_query($koneksi, "SELECT harian_id FROM absensi_harian 
                                 WHERE ta_id=$TA AND tanggal='$tanggal' AND kelas_id=$kelas LIMIT 1");
  if($cek && $row=mysqli_fetch_assoc($cek)){
    $harian_id = (int)$row['harian_id'];
  }else{
    $ok = mysqli_query($koneksi, "INSERT INTO absensi_harian(ta_id,tanggal,kelas_id,petugas_id,status,created_at)
                                  VALUES($TA,'$tanggal',$kelas,$user_id,'draft',NOW())");
    if(!$ok) return false;
    $harian_id = (int)mysqli_insert_id($koneksi);
  }

  $sql = "SELECT d.siswa_id,
                 SUM(CASE WHEN d.status='A' THEN 1 ELSE 0 END) AS a_cnt,
                 SUM(CASE WHEN d.status='I' THEN 1 ELSE 0 END) AS i_cnt,
                 SUM(CASE WHEN d.status='S' THEN 1 ELSE 0 END) AS s_cnt,
                 SUM(CASE WHEN d.status='H' THEN 1 ELSE 0 END) AS h_cnt
          FROM absensi_sesi s
          JOIN absensi_sesi_detail d ON d.sesi_id = s.sesi_id
          WHERE s.ta_id=$TA AND s.tanggal='$tanggal' AND s.kelas_id=$kelas AND s.status='final'
          GROUP BY d.siswa_id";
  $res = mysqli_query($koneksi, $sql);
  $updated = 0;
  while($r=mysqli_fetch_assoc($res)){
    $sid=(int)$r['siswa_id']; $a=(int)$r['a_cnt']; $i=(int)$r['i_cnt']; $s=(int)$r['s_cnt']; $h=(int)$r['h_cnt'];
    $st='H';
    if($metode==='any_alpha'){ $st = $a>0?'A':($i>0?'I':($s>0?'S':'H')); }
    else{
      $rank=['A'=>$a,'I'=>$i,'S'=>$s,'H'=>$h]; arsort($rank); $st=array_key_first($rank);
    }
    mysqli_query($koneksi, "INSERT INTO absensi_harian_detail(harian_id,siswa_id,status,updated_by,updated_at)
                            VALUES($harian_id,$sid,'$st',$user_id,NOW())
                            ON DUPLICATE KEY UPDATE status=VALUES(status),updated_by=$user_id,updated_at=NOW()");
    $updated++;
  }
  return $updated;
}

if (function_exists('usage_log_db_snapshot')) usage_log_db_snapshot($koneksi, $SEKOLAH_ID);

