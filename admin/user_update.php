<?php
include '../koneksi.php';
session_start();

$id        = (int)($_POST['id'] ?? 0);
$nama      = trim($_POST['nama'] ?? '');
$username  = trim($_POST['username'] ?? '');
$password  = $_POST['password'] ?? '';
$roles     = $_POST['roles'] ?? []; // array of role_id
$removeFoto= (int)($_POST['remove_foto'] ?? 0);
$oldFoto   = $_POST['old_foto'] ?? '';
$resetDef  = (int)($_POST['reset_default'] ?? 0);
$redirect  = trim($_POST['redirect_to'] ?? 'manajemen_pengguna.php'); // <-- default ke menu baru

if(!$id || !$nama || !$username || empty($roles)){ 
  die("Input tidak lengkap."); 
}

// validasi username unik (kecuali dirinya)
$stmt = $koneksi->prepare("SELECT COUNT(*) c FROM user WHERE user_username=? AND user_id<>?");
$stmt->bind_param("si", $username, $id);
$stmt->execute();
$exists = (int)$stmt->get_result()->fetch_assoc()['c'];
if($exists){ die("Username sudah dipakai."); }

// handle foto
$newFotoName = $oldFoto;
$uploadDir = realpath(__DIR__ . '/../gambar/user');
if(!$uploadDir){ $uploadDir = __DIR__ . '/../gambar/user'; @mkdir($uploadDir,0775,true); }

if($removeFoto === 1){
  if($oldFoto && file_exists("$uploadDir/$oldFoto")) @unlink("$uploadDir/$oldFoto");
  $newFotoName = '';
}else if(isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK){
  $f = $_FILES['foto'];
  $mime = @mime_content_type($f['tmp_name']);
  if(strpos($mime,'image/')!==0){ die("File foto tidak valid."); }
  if($f['size'] > 2*1024*1024){ die("Ukuran foto maksimal 2MB."); }
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if(!in_array($ext,['jpg','jpeg','png'])){ die("Format foto harus JPG/PNG."); }
  $newFotoName = 'user_'.$id.'_'.time().'.'.$ext;
  move_uploaded_file($f['tmp_name'], "$uploadDir/$newFotoName");
  if($oldFoto && file_exists("$uploadDir/$oldFoto")) @unlink("$uploadDir/$oldFoto");
}

// logika password:
// - Jika tombol reset default ditekan -> pakai '12345678'
// - Jika field password diisi manual -> pakai nilai itu
// - Jika kosong & tidak reset -> tidak mengubah
$setPw = '';
if($resetDef === 1){
  $pwHash = md5('12345678'); // TODO: migrasi ke password_hash() bila modul login siap
  $setPw = ", user_password='$pwHash'";
}else if(strlen($password) >= 5){
  $pwHash = md5($password);
  $setPw = ", user_password='$pwHash'";
}

// tentukan user_level legacy dari role utama
$ids = array_map('intval',$roles);
$in = implode(',', $ids);
$q = $koneksi->query("SELECT role_id, role_key FROM roles WHERE role_id IN ($in) ORDER BY 
  FIELD(role_key,'administrator','guru','tas','sekretaris') DESC, role_key ASC");
$roleKeys = [];
while($r = $q->fetch_assoc()){ $roleKeys[] = $r['role_key']; }
$legacyLevel = $roleKeys ? $roleKeys[0] : 'user';

// update user
$namaEsc = $koneksi->real_escape_string($nama);
$userEsc = $koneksi->real_escape_string($username);
$fotoEsc = $koneksi->real_escape_string($newFotoName);
$sql = "UPDATE user SET user_nama='$namaEsc', user_username='$userEsc', user_foto='$fotoEsc', user_level='$legacyLevel' $setPw WHERE user_id=$id";
$koneksi->query($sql);

// refresh user_roles
$koneksi->query("DELETE FROM user_roles WHERE user_id=$id");
$ins = $koneksi->prepare("INSERT INTO user_roles(user_id, role_id) VALUES(?,?)");
foreach($ids as $rid){
  $ins->bind_param("ii", $id, $rid);
  $ins->execute();
}

// redirect ke halaman manajemen pengguna
header("Location: ".$redirect."?msg=updated");
exit;
