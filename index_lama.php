<!-- VERSI FINAL LOGIN E-POIN SISWA (dengan efek tombol lebih elegan) -->
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

  <style>
    :root {
      --bg-color: #001f3f;
      --text-color: #ffffff;
      --form-bg: #ffffff;
      --form-text: #000000;
      --btn-bg: #004085;
      --btn-hover: #003060;
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
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
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

    .btn-primary,
    .btn-secondary {
      font-weight: bold;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
      transition: all 0.3s ease;
    }

    .btn-primary {
      background-color: var(--btn-bg);
      border-color: var(--btn-bg);
    }

    .btn-primary:hover {
      background-color: var(--btn-hover);
      transform: scale(1.02);
    }

    .btn-secondary {
      margin-top: 10px;
      background-color: #6c757d;
      color: white;
      border: none;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
      transform: scale(1.02);
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
      font-size: 22px;
      font-weight: bold;
      color: #333;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
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
      <p class="typewriter-text">Poin Prestasi & Poin Pelanggaran Siswa</p>

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
        <center>
          <img src="gambar/sistem/logonesagun.png" class="img-responsive animated-logo" style="width: 150px;" alt="Logo">
        </center>
        <br>
        <!-- Judul Login -->
        <div class="animate__animated animate__fadeInUp animate__delay-1s login-title">
          LOGIN SISWA
        </div>
        <marquee behavior="scroll" direction="left" scrollamount="5" font-size:14px;">
        Login untuk melihat poin pelanggaran dan prestasi Anda. Jaga sikap, tingkatkan prestasi !
        </marquee>

        <!-- Form Login -->
        <form action="periksa_login.php" method="POST">
          <div class="form-group has-feedback">
            <input type="number" class="form-control" placeholder="NIS" name="nis" required autocomplete="off">
            <span class="glyphicon glyphicon-user form-control-feedback"></span>
          </div>
          <div class="form-group has-feedback">
            <input type="password" class="form-control" placeholder="Password" name="password" required autocomplete="off">
            <span class="glyphicon glyphicon-lock form-control-feedback"></span>
          </div>
          <button type="submit" class="btn btn-primary btn-block btn-flat">LOGIN</button>
        </form>

        <!-- Tombol Login Guru -->
        <a href="https://epoin.smpn1gunungtanjung.sch.id/admin" class="btn btn-secondary btn-block btn-flat">
          <i class="fa fa-user-secret"></i> LOGIN GURU
        </a>
      </div>
    </div>
  </div>

  <!-- JS Tema -->
  <script>
    const toggleBtn = document.getElementById('themeToggle');
    function setTheme(mode) {
      if (mode === 'dark') {
        document.documentElement.style.setProperty('--bg-color', '#111');
        document.documentElement.style.setProperty('--text-color', '#fff');
        document.documentElement.style.setProperty('--form-bg', '#222');
        document.documentElement.style.setProperty('--form-text', '#eee');
        document.documentElement.style.setProperty('--btn-bg', '#444');
        document.documentElement.style.setProperty('--btn-hover', '#333');
        toggleBtn.textContent = '🌞';
        localStorage.setItem('theme', 'dark');
      } else {
        document.documentElement.style.setProperty('--bg-color', '#001f3f');
        document.documentElement.style.setProperty('--text-color', '#fff');
        document.documentElement.style.setProperty('--form-bg', '#ffffff');
        document.documentElement.style.setProperty('--form-text', '#000000');
        document.documentElement.style.setProperty('--btn-bg', '#004085');
        document.documentElement.style.setProperty('--btn-hover', '#003060');
        toggleBtn.textContent = '🌙';
        localStorage.setItem('theme', 'light');
      }
    }
    function initTheme() {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme) setTheme(savedTheme);
      else setTheme((new Date().getHours() < 6 || new Date().getHours() >= 18) ? 'dark' : 'light');
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
