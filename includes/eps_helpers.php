<?php
/**
 * EPS Generic DB Helpers (reusable)
 * - deteksi kolom fleksibel untuk pengampu_mapel & kelas_wali
 * - resolver nama guru pengampu walau set/TP belum dibuat
 * - pilih default kelas berdasarkan wali (TA aktif)
 *
 * Aman untuk di-include berkali-kali (function_exists guards).
 */

if (!function_exists('eps_db_one')) {
  function eps_db_one(mysqli $db, $sql, $default = null){
    $r = @mysqli_query($db, $sql);
    if(!$r) return $default;
    $row = @mysqli_fetch_row($r);
    return $row ? $row[0] : $default;
  }
}

if (!function_exists('eps_db_row')) {
  function eps_db_row(mysqli $db, $sql){
    $r = @mysqli_query($db, $sql);
    if(!$r) return null;
    $row = @mysqli_fetch_assoc($r);
    return $row ?: null;
  }
}

if (!function_exists('eps_table_exists')) {
  function eps_table_exists(mysqli $db, $name){
    $safe = @mysqli_real_escape_string($db, (string)$name);
    $r = @mysqli_query($db, "SHOW TABLES LIKE '{$safe}'");
    return ($r && mysqli_num_rows($r) > 0);
  }
}

if (!function_exists('eps_table_columns')) {
  function eps_table_columns(mysqli $db, $name){
    $cols = [];
    $safe = @mysqli_real_escape_string($db, (string)$name);
    $r = @mysqli_query($db, "SHOW COLUMNS FROM `{$safe}`");
    if($r){ while($row = @mysqli_fetch_assoc($r)){ $cols[] = $row['Field']; } }
    return $cols;
  }
}

if (!function_exists('eps_pick_col')) {
  /** pilih kolom pertama yang tersedia dari kandidat */
  function eps_pick_col(array $cols, array $candidates, $fallback = null){
    foreach($candidates as $c){ if(in_array($c,$cols,true)) return $c; }
    return $fallback;
  }
}

if (!function_exists('eps_user_name')) {
  function eps_user_name(mysqli $db, $user_id){
    if(!$user_id) return '';
    $uid = (int)$user_id;
    return (string)eps_db_one($db, "SELECT user_nama FROM user WHERE user_id={$uid} LIMIT 1", '');
  }
}

/** ----------------------- Pengampu Mapper ----------------------- */
if (!function_exists('eps_pengampu_schema')) {
  /**
   * Deteksi skema tabel pengampu_mapel secara fleksibel.
   * Return: ['exists'=>bool, 'table'=>'pengampu_mapel', 'pk'=>'id|pengampu_id|...', 'ta'=>'ta_id', 'kelas'=>'kelas_id', 'mapel'=>'mapel_id', 'guru'=>'guru_user_id|user_id|...']
   */
  function eps_pengampu_schema(mysqli $db){
    $tbl = 'pengampu_mapel';
    if(!eps_table_exists($db,$tbl)) return ['exists'=>false];

    $cols = eps_table_columns($db,$tbl);
    $pk   = eps_pick_col($cols, ['id','pengampu_id','id_pengampu']);
    $ta   = eps_pick_col($cols, ['ta_id','tahunajaran_id','tahun_ajaran_id','id_ta','ta']);
    $kelas= eps_pick_col($cols, ['kelas_id','id_kelas','kelas','idkelas']);
    $mapel= eps_pick_col($cols, ['mapel_id','id_mapel','mapel']);
    $guru = eps_pick_col($cols, ['guru_user_id','user_id','pengampu_user_id','guru_id','id_guru','id_user']);

    return [
      'exists'=>true, 'table'=>$tbl,
      'pk'=>$pk, 'ta'=>$ta, 'kelas'=>$kelas, 'mapel'=>$mapel, 'guru'=>$guru
    ];
  }
}

if (!function_exists('eps_resolve_pengampu_user_id')) {
  /**
   * Cari user_id guru pengampu untuk (TA, kelas, mapel) dengan prioritas:
   * 1) nilai_pts_set.guru_user_id (jika ada set)
   * 2) pengampu_mapel (id terbaru)
   */
  function eps_resolve_pengampu_user_id(mysqli $db, $ta_id, $kelas_id, $mapel_id){
    $ta_id    = (int)$ta_id;
    $kelas_id = (int)$kelas_id;
    $mapel_id = (int)$mapel_id;

    // 1) dari nilai_pts_set
    if (eps_table_exists($db,'nilai_pts_set')){
      $gid = (int)eps_db_one($db, "SELECT guru_user_id FROM nilai_pts_set
                                   WHERE ta_id={$ta_id} AND kelas_id={$kelas_id} AND mapel_id={$mapel_id}
                                   ORDER BY pts_set_id DESC LIMIT 1", 0);
      if ($gid > 0) return $gid;
    }

    // 2) dari pengampu_mapel (fleksibel)
    $sch = eps_pengampu_schema($db);
    if (!$sch['exists']) return 0;

    $pk   = $sch['pk']   ?: 'id';
    $ta   = $sch['ta']   ?: 'ta_id';
    $kelas= $sch['kelas']?: 'kelas_id';
    $mapel= $sch['mapel']?: 'mapel_id';
    $guru = $sch['guru'] ?: 'guru_user_id';
    $tbl  = $sch['table'];

    $q = "SELECT {$guru} AS uid FROM `{$tbl}` WHERE {$kelas}={$kelas_id} AND {$mapel}={$mapel_id}";
    if($ta){ $q .= " AND {$ta}={$ta_id}"; }
    $q .= " ORDER BY {$pk} DESC LIMIT 1";

    return (int)eps_db_one($db, $q, 0);
  }
}

if (!function_exists('eps_resolve_pengampu_nama')) {
  /** Ambil nama guru pengampu (atau default jika tak ada). */
  function eps_resolve_pengampu_nama(mysqli $db, $ta_id, $kelas_id, $mapel_id, $default=''){
    $gid = eps_resolve_pengampu_user_id($db, $ta_id, $kelas_id, $mapel_id);
    if ($gid > 0){
      $nm = eps_user_name($db, $gid);
      if ($nm !== '') return $nm;
    }
    return $default;
  }
}

/** ----------------------- Wali Kelas Mapper ----------------------- */
if (!function_exists('eps_resolve_wali_kelas_id')) {
  /**
   * Dapatkan kelas_id yang diwalikan oleh user pada TA aktif.
   * Prioritas:
   * 1) tabel kelas_wali (fleksibel kolom)
   * 2) kolom wali_* di tabel kelas (fleksibel)
   * Return: kelas_id atau 0 jika tidak ada.
   */
  function eps_resolve_wali_kelas_id(mysqli $db, $user_id, $ta_id){
    $user_id = (int)$user_id; if($user_id<=0) return 0;
    $ta_id   = (int)$ta_id;

    // 1) kelas_wali (paling akurat)
    if (eps_table_exists($db,'kelas_wali')){
      $cols = eps_table_columns($db,'kelas_wali');
      $C_KELAS = eps_pick_col($cols, ['kelas_id','id_kelas','kelas']);
      $C_USER  = eps_pick_col($cols, ['wali_user_id','user_id','guru_user_id','wali_id','wali']);
      $C_TA    = eps_pick_col($cols, ['ta_id','tahunajaran_id','tahun_ajaran_id','id_ta','ta']);

      if ($C_KELAS && $C_USER){
        $q = "SELECT {$C_KELAS} FROM kelas_wali WHERE {$C_USER}={$user_id}";
        if($C_TA){ $q .= " AND {$C_TA}={$ta_id}"; }
        $q .= " ORDER BY {$C_KELAS} DESC LIMIT 1";
        $kid = (int)eps_db_one($db, $q, 0);
        if ($kid > 0) return $kid;
      }
    }

    // 2) kolom wali_* di kelas
    if (eps_table_exists($db,'kelas')){
      $cols = eps_table_columns($db,'kelas');
      $C_WALI = eps_pick_col($cols, ['kelas_wali','wali_user_id','wali_id','guru_wali','wali']);
      if ($C_WALI){
        $kid = (int)eps_db_one($db, "SELECT kelas_id FROM kelas
                                     WHERE kelas_ta={$ta_id} AND {$C_WALI}={$user_id}
                                     ORDER BY kelas_nama ASC LIMIT 1", 0);
        if ($kid > 0) return $kid;
      }
    }

    return 0;
  }
}
