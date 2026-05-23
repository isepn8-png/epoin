<?php if (($_GET['alert'] ?? '')==='gagal'): ?>
  <div style="background:#fef2f2;color:#7f1d1d;border:1px solid #fecaca;border-radius:8px;padding:10px 12px;margin-bottom:12px">
    <b>Login gagal.</b>
    <?= htmlspecialchars($_GET['msg'] ?? 'Login gagal. Periksa kembali username, kata sandi, dan peran login Anda.', ENT_QUOTES, 'UTF-8'); ?>
  </div>
<?php endif; ?>

<?php
header("Location: login.php" . (isset($_GET['alert']) ? "?alert=" . urlencode($_GET['alert']) : ""));
exit;
