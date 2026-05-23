<?php
/**
 * usage_helper.php
 * Centralized hooks for logging Disk, Bandwidth, and Inode usage to `usage_log`.
 * Include this file early, e.g., in admin/header.php after including koneksi.php.
 */

if (!function_exists('usage_bootstrap')){
  function usage_bootstrap(mysqli $koneksi, int $sekolah_id){
    @mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS tenant_quota (
      id INT AUTO_INCREMENT PRIMARY KEY,
      sekolah_id INT NOT NULL UNIQUE,
      disk_limit_mb INT NOT NULL DEFAULT 512,
      bandwidth_limit_gb INT DEFAULT NULL,
      inode_limit INT DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    @mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS usage_log (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      sekolah_id INT NOT NULL,
      category ENUM('db','file','bandwidth','inode','other') NOT NULL DEFAULT 'other',
      action   ENUM('upload','import','insert','delete','remove','download','export','create','drop','other') NOT NULL DEFAULT 'other',
      bytes    BIGINT NOT NULL DEFAULT 0,
      objects  INT    NOT NULL DEFAULT 0,
      note     VARCHAR(255) NULL,
      meta     JSON NULL,
      occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_usage(sekolah_id, category, occurred_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    @mysqli_query($koneksi, "INSERT IGNORE INTO tenant_quota (sekolah_id) VALUES (".(int)$sekolah_id.")");
  }

  function usage_log_write(mysqli $koneksi, int $sekolah_id, string $category, string $action, int $bytes=0, int $objects=0, $note=null, $meta=null){
    $c = mysqli_real_escape_string($koneksi, $category);
    $a = mysqli_real_escape_string($koneksi, $action);
    $n = is_null($note) ? "NULL" : "'".mysqli_real_escape_string($koneksi,(string)$note)."'";
    $m = is_null($meta) ? "NULL" : "'".mysqli_real_escape_string($koneksi, json_encode($meta, JSON_UNESCAPED_UNICODE))."'";
    @mysqli_query($koneksi, "INSERT INTO usage_log (sekolah_id,category,action,bytes,objects,note,meta) VALUES (".(int)$sekolah_id.",'$c','$a',".(int)$bytes.",".(int)$objects.",$n,$m)");
  }

  /** Hook: Call after successfully moving an uploaded file */
  function usage_log_file_uploaded(mysqli $koneksi, int $sekolah_id, string $abs_path, string $note='upload'){
    $size = (int)@filesize($abs_path);
    usage_log_write($koneksi,$sekolah_id,'file','upload',$size,1,$note,['path'=>basename($abs_path)]);
  }

  /** Hook: Call before deleting a file */
  function usage_log_file_deleted(mysqli $koneksi, int $sekolah_id, string $abs_path, string $note='delete'){
    $size = (int)@filesize($abs_path);
    // negative delta so it subtracts from total
    usage_log_write($koneksi,$sekolah_id,'file','remove',-$size,-1,$note,['path'=>basename($abs_path)]);
  }

  /** Hook: Wrap a file download to log bandwidth */
  function send_download_and_log(mysqli $koneksi, int $sekolah_id, string $abs_path, ?string $download_name=null, bool $inline=false){
    if (!is_file($abs_path)) { http_response_code(404); exit('File not found'); }
    $len = (int)@filesize($abs_path);
    $name = $download_name ?: basename($abs_path);
    $disp = $inline ? 'inline' : 'attachment';
    header('Content-Type: application/octet-stream');
    header('Content-Length: '.$len);
    header('Content-Disposition: '.$disp.'; filename="'.$name.'"');
    header('X-Accel-Buffering: no');
    @ob_end_flush(); @flush();

    $fp = fopen($abs_path, 'rb');
    while(!feof($fp)){ print(fread($fp, 8192)); flush(); }
    fclose($fp);

    usage_log_write($koneksi,$sekolah_id,'bandwidth','download',$len,0,'download',['path'=>basename($abs_path)]);
    exit;
  }

  /** Optional: Record a DB snapshot (data+index length) */
  function usage_log_db_snapshot(mysqli $koneksi, int $sekolah_id){
    $dbq = mysqli_query($koneksi, "SELECT DATABASE() db"); $schema = ($dbq && $r=mysqli_fetch_assoc($dbq))? $r['db'] : '';
    if (!$schema) return;
    $q = mysqli_query($koneksi, "SELECT SUM(data_length+index_length) AS total FROM information_schema.tables WHERE table_schema='".mysqli_real_escape_string($koneksi,$schema)."'");
    $row = $q? mysqli_fetch_assoc($q) : null; $bytes = (int)($row['total'] ?? 0);
    usage_log_write($koneksi,$sekolah_id,'db','snapshot',$bytes,0,'db_size',['schema'=>$schema]);
  }
}
