<?php include 'header.php'; ?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Ganti Password <small>Keamanan Akun</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Ganti Password</li>
    </ol>
  </section>
  <section class="content">
<?php
$id = $_SESSION['id'];
$err = $ok = null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $old     = (string)($_POST['old_password'] ?? '');
  $new     = (string)($_POST['new_password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');
  $sid     = (int)$id;

  if($sid <= 0)                 $err = "Sesi tidak valid. Silakan login ulang.";
  elseif(strlen($new) < 6)      $err = "Password baru minimal 6 karakter.";
  elseif($new !== $confirm)     $err = "Konfirmasi password tidak cocok.";
  else{
    // Ambil hash lama — prepared statement (anti SQL injection)
    $hash = '';
    $stmt = mysqli_prepare($koneksi,"SELECT siswa_password FROM siswa WHERE siswa_id=? LIMIT 1");
    if($stmt){
      mysqli_stmt_bind_param($stmt,'i',$sid);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);
      $row = $res ? mysqli_fetch_assoc($res) : null;
      $hash = $row ? (string)$row['siswa_password'] : '';
      mysqli_stmt_close($stmt);
    }
    // Verifikasi password lama: bcrypt (akun migrasi) atau MD5 legacy — selaras periksa_login.php
    if(preg_match('/^\$2y\$\d{2}\$/',$hash)){
      $verified = password_verify($old,$hash);
    }else{
      $verified = ($hash !== '' && hash_equals($hash, md5($old)));
    }
    if(!$verified){
      $err = "Password lama salah.";
    }else{
      // Simpan sebagai bcrypt (sekaligus upgrade hash lama)
      $newHash = password_hash($new, PASSWORD_DEFAULT);
      $up = mysqli_prepare($koneksi,"UPDATE siswa SET siswa_password=? WHERE siswa_id=?");
      if($up){
        mysqli_stmt_bind_param($up,'si',$newHash,$sid);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
      }
      $ok = "Password berhasil diperbarui.";
    }
  }
}
?>
<style>
  /* ====== Tampilan elegan & colorful ====== */
  .fadein{opacity:0; transform:translateY(8px); transition:opacity .6s, transform .6s;}
  .fadein.show{opacity:1; transform:none;}

  .auth-card{
    border:0; border-radius:16px; overflow:hidden;
    box-shadow:0 12px 28px rgba(0,0,0,.08);
    background:#fff;
  }
  .auth-head{
    background:linear-gradient(135deg,#60a5fa,#2563eb);
    color:#fff; padding:18px 20px; position:relative;
  }
  .auth-head .icon-bg{
    position:absolute; right:16px; bottom:8px; font-size:48px; opacity:.18;
  }
  .auth-body{padding:18px 18px 20px;}
  .hint{color:#e5e7eb}

  /* Input & tombol */
  .input-group .btn{border-radius:8px}
  .form-control{border-radius:10px; transition:box-shadow .2s}
  .form-control:focus{box-shadow:0 0 0 4px rgba(59,130,246,.15); border-color:#60a5fa}

  /* Strength meter */
  .strength-wrap{margin-top:8px}
  .strength-bar{
    height:8px; border-radius:999px; background:#e5e7eb; overflow:hidden; position:relative;
  }
  .strength-bar > span{
    position:absolute; left:0; top:0; bottom:0; width:0%;
    transition:width .35s ease; border-radius:999px;
  }
  .s-weak{background:#ef4444}
  .s-fair{background:#f59e0b}
  .s-good{background:#10b981}
  .s-strong{background:#22c55e}
  .strength-label{font-size:12px; margin-top:6px; font-weight:700}
  .strength-label.weak{color:#ef4444}
  .strength-label.fair{color:#f59e0b}
  .strength-label.good{color:#10b981}
  .strength-label.strong{color:#16a34a}

  /* Checklist kriteria */
  .req-list{list-style:none; padding-left:0; margin:8px 0 0; display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:6px}
  .req-list li{font-size:12px; color:#6b7280; display:flex; align-items:center; gap:6px}
  .req-list .ok{color:#16a34a}
  .req-list .no{color:#9ca3af}

  /* Match & Caps lock */
  .match-pill{
    display:inline-flex; align-items:center; gap:6px;
    border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700;
  }
  .match-yes{background:#dcfce7; color:#047857}
  .match-no{background:#fee2e2; color:#b91c1c}
  .caps-warn{font-size:12px; color:#b91c1c; margin-top:6px; display:none}
  .caps-warn.show{display:block}

  /* Alerts */
  .alert{border-radius:12px}
  .alert-success{background:#ecfdf5; color:#065f46; border-color:#a7f3d0}
  .alert-danger{background:#fef2f2; color:#991b1b; border-color:#fecaca}

  /* Tombol aksi */
  .btn-primary{
    background:linear-gradient(135deg,#2563eb,#1d4ed8); border:0; border-radius:12px;
    box-shadow:0 8px 18px rgba(37,99,235,.25);
  }
  .btn-primary:hover{filter:brightness(1.05)}
  .btn-ghost{
    background:#f3f4f6; border:1px solid #e5e7eb; color:#111; border-radius:12px;
  }

  /* Inline success (bawah tombol) */
  .ok-inline{display:none; margin-top:12px; padding:14px 16px; border-radius:12px;
             background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;
             font-size:18px; font-weight:800}
  .ok-inline.show{display:block}

  .d-none{display:none!important;}

  @media (max-width: 768px){
    .req-list{grid-template-columns:1fr}
  }
</style>

<div class="row fadein" id="pwRoot">
  <section class="col-lg-6">
    <div class="box auth-card">
      <div class="auth-head">
        <div style="font-weight:800;font-size:18px">Ubah Password</div>
        <div class="hint">Jaga keamanan akun Anda dengan password yang kuat</div>
        <i class="fa fa-shield icon-bg"></i>
      </div>
      <div class="auth-body">
        <?php if($err){ ?><div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?php echo $err; ?></div><?php } ?>

        <form method="post" autocomplete="off" id="formChangePw">
          <!-- Password lama -->
          <div class="form-group">
            <label>Password Lama</label>
            <div class="input-group">
              <input type="password" id="old_password" name="old_password" class="form-control" required>
              <span class="input-group-btn">
                <button class="btn btn-ghost toggle-eye" type="button" data-target="#old_password" title="Lihat/sembunyikan">
                  <i class="fa fa-eye"></i>
                </button>
              </span>
            </div>
            <div id="capsOld" class="caps-warn"><i class="fa fa-exclamation-triangle"></i> Caps Lock aktif</div>
          </div>

          <!-- Password baru -->
          <div class="form-group">
            <label>Password Baru</label>
            <div class="input-group">
              <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6" aria-describedby="pwActions">
              <span class="input-group-btn" id="pwActions">
                <!-- HAPUS tombol Buat Password Acak -->
                <button class="btn btn-ghost toggle-eye" type="button" data-target="#new_password" title="Lihat/sembunyikan"><i class="fa fa-eye"></i></button>
                <button class="btn btn-ghost" type="button" id="btnCopy" title="Salin password"><i class="fa fa-clipboard"></i></button>
              </span>
            </div>
            <small class="text-muted">Gunakan campuran huruf, angka, dan simbol.</small>

            <!-- Strength meter -->
            <div class="strength-wrap">
              <div class="strength-bar"><span id="bar" class="s-weak" style="width:0%"></span></div>
              <div id="barLabel" class="strength-label weak">Kekuatan: -</div>
            </div>

            <!-- Kriteria -->
            <ul class="req-list" id="reqList">
              <li><i class="fa fa-circle no" id="rLen"></i> Minimal 6 karakter</li>
              <li><i class="fa fa-circle no" id="rUpper"></i> Ada huruf besar</li>
              <li><i class="fa fa-circle no" id="rNum"></i> Ada angka</li>
              <li><i class="fa fa-circle no" id="rSym"></i> Ada simbol (!@#...)</li>
            </ul>

            <div id="capsNew" class="caps-warn"><i class="fa fa-exclamation-triangle"></i> Caps Lock aktif</div>
          </div>

          <!-- Konfirmasi -->
          <div class="form-group">
            <label>Konfirmasi Password Baru</label>
            <div class="input-group">
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
              <span class="input-group-btn">
                <button class="btn btn-ghost toggle-eye" type="button" data-target="#confirm_password" title="Lihat/sembunyikan"><i class="fa fa-eye"></i></button>
              </span>
            </div>

            <!-- Badge cocok/tidak cocok -->
            <div style="margin-top:8px">
              <span id="matchWrap" class="d-none">
                <span id="matchBadge" class="match-pill"></span>
              </span>
            </div>

            <div id="capsConfirm" class="caps-warn"><i class="fa fa-exclamation-triangle"></i> Caps Lock aktif</div>
          </div>

          <button class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>

          <!-- Notif sukses di BAWAH tombol, font lebih besar -->
          <div id="okInline" class="ok-inline <?php echo $ok ? 'show' : ''; ?>">
            <i class="fa fa-check-circle"></i> <?php echo $ok ? $ok : ''; ?>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
  // Fade-in
  document.addEventListener('DOMContentLoaded', function(){
    setTimeout(function(){ document.getElementById('pwRoot').classList.add('show'); }, 50);
  });

  // === Toggle Eye ===
  $(document).on('click','.toggle-eye',function(){
    var tgt = $(this).data('target'); var $i = $(tgt);
    var tp = $i.attr('type')==='password' ? 'text' : 'password';
    $i.attr('type', tp);
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // === Strength Meter util sederhana ===
  function scorePassword(pw){
    var score = 0;
    if (pw.length >= 6) score += 1;
    if (/[A-Z]/.test(pw)) score += 1;
    if (/\d/.test(pw)) score += 1;
    if (/[^A-Za-z0-9]/.test(pw)) score += 1;
    if (pw.length >= 10) score += 1; // bonus panjang
    return Math.min(score, 4); // 0..4
  }
  function updateStrength(pw){
    var s = scorePassword(pw), pct = [0,25,50,75,100][s];
    var bar = document.getElementById('bar');
    var lbl = document.getElementById('barLabel');
    bar.className = ''; // reset
    lbl.className = 'strength-label';
    if(s<=1){ bar.classList.add('s-weak'); lbl.classList.add('weak'); lbl.textContent = 'Kekuatan: Lemah'; }
    else if(s===2){ bar.classList.add('s-fair'); lbl.classList.add('fair'); lbl.textContent = 'Kekuatan: Cukup'; }
    else if(s===3){ bar.classList.add('s-good'); lbl.classList.add('good'); lbl.textContent = 'Kekuatan: Baik'; }
    else { bar.classList.add('s-strong'); lbl.classList.add('strong'); lbl.textContent = 'Kekuatan: Sangat Baik'; }
    bar.style.width = pct+'%';

    // Checklist
    document.getElementById('rLen').className = (pw.length>=6)?'fa fa-check ok':'fa fa-circle no';
    document.getElementById('rUpper').className = (/[A-Z]/.test(pw))?'fa fa-check ok':'fa fa-circle no';
    document.getElementById('rNum').className = (/\d/.test(pw))?'fa fa-check ok':'fa fa-circle no';
    document.getElementById('rSym').className = (/[^A-Za-z0-9]/.test(pw))?'fa fa-check ok':'fa fa-circle no';
  }

  // === Match badge logic (diperbaiki) ===
  function updateMatch(){
    var a = document.getElementById('new_password').value;
    var b = document.getElementById('confirm_password').value;
    var wrap = document.getElementById('matchWrap');
    var m = document.getElementById('matchBadge');

    // Sembunyikan jika salah satu kosong
    if(!a || !b){
      wrap.classList.add('d-none');
      return;
    }

    // Tampilkan hanya ketika kedua field terisi
    wrap.classList.remove('d-none');

    if(a === b){
      // Cocok → badge hijau
      m.className = 'match-pill match-yes';
      m.innerHTML = '<i class="fa fa-check"></i> Cocok';
    }else{
      // Berbeda → tampilkan "Belum cocok"
      m.className = 'match-pill match-no';
      m.innerHTML = '<i class="fa fa-times"></i> Belum cocok';
    }
  }

  // === Caps Lock warning ===
  function bindCapsWarn(inputId, warnId){
    var input = document.getElementById(inputId);
    var warn  = document.getElementById(warnId);
    if(!input || !warn) return;
    input.addEventListener('keyup', function(e){
      if (e.getModifierState && e.getModifierState('CapsLock')) warn.classList.add('show');
      else warn.classList.remove('show');
    });
    input.addEventListener('focusout', function(){ warn.classList.remove('show'); });
  }

  // === Copy password ===
  (function(){
    var np = document.getElementById('new_password');
    var cp = document.getElementById('confirm_password');
    if(np){ np.addEventListener('input', function(){ updateStrength(this.value); updateMatch(); }); }
    if(cp){ cp.addEventListener('input', updateMatch); }

    bindCapsWarn('old_password','capsOld');
    bindCapsWarn('new_password','capsNew');
    bindCapsWarn('confirm_password','capsConfirm');

    var btnCopy = document.getElementById('btnCopy');
    if(btnCopy){
      btnCopy.addEventListener('click', function(){
        if(!np.value) return;
        try{
          navigator.clipboard.writeText(np.value);
          btnCopy.innerHTML = '<i class="fa fa-check"></i>';
          setTimeout(function(){ btnCopy.innerHTML = '<i class="fa fa-clipboard"></i>'; }, 1200);
        }catch(e){}
      });
    }
  })();
</script>
  </section>
</div>
<?php include 'footer.php'; ?>
