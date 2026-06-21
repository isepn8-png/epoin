<?php
require_once __DIR__ . '/../includes/epoin_security.php';
include '../koneksi.php';
epoin_staff_guard(true);
epoin_require_post();
if (!epoin_csrf_validate()) {
    epoin_csrf_fail_redirect('user.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: user.php');
    exit;
}

// Ambil foto untuk dihapus filenya (dengan prepared statement)
$stmt = mysqli_prepare($koneksi, 'SELECT user_foto FROM user WHERE user_id = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if ($row && !empty($row['user_foto'])) {
        $foto = basename((string) $row['user_foto']); // basename: cegah path traversal
        $path = __DIR__ . '/../gambar/user/' . $foto;
        if ($foto !== '' && $foto !== '.' && file_exists($path)) {
            @unlink($path);
        }
    }
}

$stmt = mysqli_prepare($koneksi, 'DELETE FROM user WHERE user_id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: user.php');
