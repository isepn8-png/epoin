<?php
header("Location: login.php" . (isset($_GET['alert']) ? "?alert=" . urlencode($_GET['alert']) : ""));
exit;
