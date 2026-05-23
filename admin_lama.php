<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sistem Informasi E-Poin Siswa SMPN 1 Gunungtanjung</title>

  <!-- CSS -->
  <link rel="stylesheet" href="assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/bower_components/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="assets/bower_components/Ionicons/css/ionicons.min.css">
  <link rel="stylesheet" href="assets/dist/css/AdminLTE.min.css">
  <link rel="stylesheet" href="assets/plugins/iCheck/square/blue.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

  <!-- Custom Style -->
  <style>
    :root {
      --bg-color: #3a3a3a;
      --text-color: #ffffff;
      --form-bg: #f7f9fc;
      --form-text: #000000;
      --btn-admin-bg: #ff9800;
      --btn-admin-hover: #fb8c00;
      --btn-siswa-bg: #0d47a1;
      --btn-siswa-hover: #1565c0;
    }

    body {
      background-color: var(--bg-color);
      color: var(--text-color);
      font-family: 'Source Sans Pro', sans-serif;
      padding-top: 30px;
    }

    .login-box {
      max-width: 400px;
      margin: auto;
    }

    .login-box-body {
      background-color: var(--form-bg);
      color: var(--form-text);
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.15);
      position: relative;
    }

    .form-control {
      background-color: #f9f9f9;
      color: #000;
      border: 1px solid #ccc;
    }

    .form-control:focus {
      background-color: #fff;
      color: #000;
    }

    .btn-admin {
      background-color: var(--btn-admin-bg);
      border-color: var(--btn-admin-bg);
      color: white;
      font-weight: bold;
      transition: all 0.3s ease-in-out;
    }

    .btn-admin:hover {
      background-color: var(--btn-admin-hover);
      border-color: var(--btn-admin-hover);
    }

    .btn-siswa {
      background-color: var(--btn-siswa-bg);
      border-color: var(--btn-siswa-bg);
      color: white;
      margin-top: 10px;
      font-weight: bold;
      transition: all 0.3s ease-in-out;
    }

    .btn-siswa:hover {
      background-color: var(--btn-siswa-hover);
      border-color: var(--btn-siswa-hover);
    }

    .alert { color: white; }
    .alert-danger { background-color: #c0392b; }
    .alert-success { background-color: #27ae60; }
    .alert-warning { background-color: #f39c12; }

    #themeToggle {
      position: fixed;
      top: 10px;
      right: 15px;
      background: transparent;
      border: none;
      color: var(--text-color);
      font-size: 24px;
      cursor: pointer;
      z-index: 999;
    }

    .animated-logo {
      animation: pulseLogo 3s infinite ease-in-out;
    }

    @keyframes pulseLogo {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }

    @keyframes typing {
      from { width: 0; }
      to { width: 100%; }
    }

    @keyframes blink-caret {
      from, to { border-color: transparent; }
      50% { border-color: white; }
    }

    .typewriter-text {
      font-size: 14px;
      font-weight: bold;
      overflow: hidden;
      white-space: nowrap;
      border-right: .15em solid #fff;
      animation: typing 3s steps(40, end), blink-caret 0.75s step-end infinite;
      margin-bottom: 10px;
    }

    .login-title {
      font-size: 24px;
      font-weight: bold;
      margin-top: 20px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.6);
    }

    .guru-logo {
      width: 60px;
      position: absolute;
      top: -30px;
      right: -30px;
      background-color: #fff;
      padding: 8px;
      border-radius: 50%;
      box-shadow: 0 0 8px rgba(0,0,0,0.2);
    }

    @media (max-width: 480px) {
      h2, .typewriter-text { font-size: 14px; }
      .login-box-body { padding: 15px; }
      h2.text-bold {
        font-size: 6vw;
        line-height: 1.2;
      }
    }
  </style>
</head>
<body>
  <button id="themeToggle" title="Ganti Tema">🌙</button>
  <div class="container">
    <div class="login-box text-center">
      <div class="animate__animated animate__fadeInDown">
        <h2 class="text-bold">E-POIN SISWA<br>SMPN 1 GUNUNGTANJUNG</h2>
      </div>
      <p class="typewriter-text">INPUT POIN PRESTASI & PELANGGARAN SISWA</p>

      <?php 
      if(isset($_GET['alert'])){
        if($_GET['alert'] == "gagal"){
          echo "<div class='alert alert-danger'><b>LOGIN GAGAL</b><br> Username dan password salah</div>";
        }else if($_GET['alert'] == "logout"){
          echo "<div class='alert alert-success'>Anda telah berhasil logout</div>";
        }else if($_GET['alert'] == "belum_login"){
          echo "<div class='alert alert-warning'>Anda harus login untuk mengakses halaman admin</div>";
        }
      }
      ?>

      <div class="login-box-body animate__animated animate__fadeInUp">
        <img src="gambar/sistem/logo_guru_ico.png" alt="Guru" class="guru-logo">
        <center>
          <img src="gambar/sistem/logonesagun.png" class="img-responsive animated-logo" style="width: 150px;" alt="Logo">
        </center>
        <p class="login-title">LOGIN GURU</p>

        <form action="periksa_admin.php" method="POST">
          <div class="form-group has-feedback">
            <input type="text" class="form-control" placeholder="Username_guru" name="username" required autocomplete="off">
            <span class="glyphicon glyphicon-user form-control-feedback"></span>
          </div>
          <div class="form-group has-feedback">
            <input type="password" class="form-control" placeholder="Password" name="password" required autocomplete="off">
            <span class="glyphicon glyphicon-lock form-control-feedback"></span>
          </div>
          <button type="submit" class="btn btn-admin btn-block btn-flat">LOGIN</button>
        </form>

        <a href="../index.php" class="btn btn-siswa btn-block btn-flat">LOGIN SISWA</a>
      </div>
    </div>
  </div>

  <script>
    const toggleBtn = document.getElementById('themeToggle');

    function setTheme(mode) {
      if (mode === 'dark') {
        document.documentElement.style.setProperty('--bg-color', '#111');
        document.documentElement.style.setProperty('--text-color', '#fff');
        document.documentElement.style.setProperty('--form-bg', '#222');
        document.documentElement.style.setProperty('--form-text', '#eee');
        toggleBtn.textContent = '🌞';
        localStorage.setItem('theme', 'dark');
      } else {
        document.documentElement.style.setProperty('--bg-color', '#3a3a3a');
        document.documentElement.style.setProperty('--text-color', '#fff');
        document.documentElement.style.setProperty('--form-bg', '#f7f9fc');
        document.documentElement.style.setProperty('--form-text', '#000');
        toggleBtn.textContent = '🌙';
        localStorage.setItem('theme', 'light');
      }
    }

    function initTheme() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme) {
        setTheme(savedTheme);
      } else {
        const hour = new Date().getHours();
        setTheme((hour < 6 || hour >= 18) ? 'dark' : 'light');
      }
    }

    toggleBtn.addEventListener('click', () => {
      const current = localStorage.getItem('theme') || 'light';
      setTheme(current === 'light' ? 'dark' : 'light');
    });

    initTheme();
  </script>

  <script src="assets/bower_components/jquery/dist/jquery.min.js"></script>
  <script src="assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
</body>
</html>
