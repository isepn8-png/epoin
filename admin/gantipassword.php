<?php include 'header.php'; ?>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      Ganti Password
      <small>Ganti Password</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-5">

        <?php 
        // tetap pertahankan notifikasi lama (kompatibel backend)
        if(isset($_GET['alert'])){
          if($_GET['alert'] == "sukses"){
            echo "<div class='alert alert-success'>Password anda berhasil diganti!</div>";
          }
        }
        ?>

        <style>
          /* ====== Poles tampilan card ====== */
          .pw-box.box {
            border-radius:14px; overflow:hidden;
            box-shadow:0 14px 34px rgba(2,6,23,.08);
            border:0;
          }
          .pw-box .box-header{
            background:linear-gradient(135deg,#0ea5e9,#6366f1);
            color:#fff; border:0;
          }
          .pw-box .box-title{ font-weight:800; letter-spacing:.2px; }
          .pw-box .box-body{ background:linear-gradient(180deg,#ffffff,#f8fafc); }

          /* Input group (eye & copy & gen) */
          .input-group-lg .form-control{ height:46px; }
          .pw-req{ margin:10px 0 0; font-size:12.5px; color:#475569; }
          .pw-req .ok{ color:#16a34a; }
          .pw-req .no{ color:#b91c1c; }
          .pw-req i{ width:16px; text-align:center; }

          /* Meter kekuatan */
          .pw-meter{ height:8px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin-top:8px; }
          .pw-meter > span{ display:block; height:100%; width:0%; transition:width .25s ease; background:linear-gradient(90deg,#ef4444,#f59e0b,#eab308,#22c55e,#16a34a); }

          /* Alert kapslock & info */
          .caps-alert{ display:none; font-size:12px; color:#b91c1c; margin-top:6px; }
          .tiny-note{ font-size:12px; color:#64748b; }

          /* Toast sukses tambahan (selaras Bootstrap) */
          .toast-fixed{
            position:fixed; right:18px; bottom:18px; z-index:1050;
            background:#10b981; color:#fff; padding:10px 14px; border-radius:10px;
            box-shadow:0 10px 22px rgba(16,185,129,.2); display:none;
          }

          /* Animasi sedikit saat submit gagal */
          .shake{ animation:shake .28s linear 1; }
          @keyframes shake{ 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }

          @media (max-width:480px){
            .input-group-addon{ padding:6px 10px; }
          }
        </style>

        <div class="box pw-box box-primary">
          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-lock"></i> Ganti Password</h3>
          </div>
          <div class="box-body">
            <!-- tips kecil -->
            <p class="tiny-note">
              Gunakan password yang kuat & unik. Hindari tanggal lahir, nama panggilan, atau pola mudah ditebak.
            </p>

            <form id="pwForm" action="gantipassword_act.php" method="post" autocomplete="new-password">
              <?= epoin_csrf_field() ?>
              <div class="form-group">
                <label for="password" style="font-weight:700">Password Baru</label>
                <div class="input-group input-group-lg">
                  <input type="password" class="form-control" placeholder="Masukkan Password Baru .."
                         name="password" id="password" required="required" min="5" minlength="8" autocomplete="new-password" />
                  <span class="input-group-addon" title="Tampil/sembunyikan" style="cursor:pointer" id="togglePw"><i class="fa fa-eye"></i></span>
                  <span class="input-group-addon" title="Salin password" style="cursor:pointer" id="copyPw"><i class="fa fa-clipboard"></i></span>
                  <span class="input-group-addon" title="Buat password acak yang kuat" style="cursor:pointer" id="genPw"><i class="fa fa-magic"></i></span>
                </div>
                <div class="pw-meter" aria-hidden="true"><span id="meterFill"></span></div>
                <div class="caps-alert" id="capsAlert"><i class="fa fa-exclamation-circle"></i> CapsLock aktif.</div>

                <!-- checklist syarat -->
                <ul class="pw-req" id="pwReq">
                  <li><span class="mark"><i class="fa fa-close no"></i></span> Minimal 8 karakter</li>
                  <li><span class="mark"><i class="fa fa-close no"></i></span> Huruf besar <em>(A-Z)</em></li>
                  <li><span class="mark"><i class="fa fa-close no"></i></span> Huruf kecil <em>(a-z)</em></li>
                  <li><span class="mark"><i class="fa fa-close no"></i></span> Angka <em>(0-9)</em></li>
                  <li><span class="mark"><i class="fa fa-close no"></i></span> Simbol <em>(!@#$% …)</em></li>
                </ul>
              </div>

              <div class="form-group">
                <label for="password2" style="font-weight:700">Konfirmasi Password</label>
                <div class="input-group">
                  <input type="password" class="form-control" id="password2" placeholder="Ulangi password baru" autocomplete="new-password" />
                  <span class="input-group-addon" title="Tampil/sembunyikan" style="cursor:pointer" id="togglePw2"><i class="fa fa-eye"></i></span>
                </div>
                <div id="matchHint" class="tiny-note" style="margin-top:6px;"></div>
              </div>

              <div class="form-group" style="margin-top:14px;">
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                  <i class="fa fa-save"></i> Simpan
                </button>
              </div>
            </form>

            <!-- catatan keamanan -->
            <p class="tiny-note" style="margin:10px 0 0;">
              <i class="fa fa-info-circle"></i>
              Dengan menekan Simpan, password akan diperbarui dan Anda akan diminta menggunakan password baru saat login berikutnya.
            </p>
          </div>
        </div>

        <!-- toast sukses tambahan (selain alert GET) -->
        <div class="toast-fixed" id="toastOk"><i class="fa fa-check-circle"></i> &nbsp;Password berhasil diganti.</div>

      </section>
    </div>
  </section>

</div>
<?php include 'footer.php'; ?>

<script>
(function(){
  // elemen
  var $pw   = $('#password');
  var $pw2  = $('#password2');
  var $fill = $('#meterFill');
  var $req  = $('#pwReq');
  var $hint = $('#matchHint');

  // toggle show/hide
  $('#togglePw').on('click', function(){
    var t = $pw.attr('type') === 'password' ? 'text' : 'password';
    $pw.attr('type', t);
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });
  $('#togglePw2').on('click', function(){
    var t = $pw2.attr('type') === 'password' ? 'text' : 'password';
    $pw2.attr('type', t);
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // generator & copy
  function genPassword(len){
    len = len || 14;
    var sets = {
      l:'abcdefghijklmnopqrstuvwxyz',
      u:'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
      d:'0123456789',
      s:'!@#$%^&*()_+-=[]{};:,.?/|~'
    };
    var all = sets.l + sets.u + sets.d + sets.s;
    // pastikan tiap kategori terpakai
    var out = [
      sets.l[Math.floor(Math.random()*sets.l.length)],
      sets.u[Math.floor(Math.random()*sets.u.length)],
      sets.d[Math.floor(Math.random()*sets.d.length)],
      sets.s[Math.floor(Math.random()*sets.s.length)]
    ];
    for (var i=out.length; i<len; i++){
      out.push(all[Math.floor(Math.random()*all.length)]);
    }
    // shuffle sederhana
    for (var j=out.length-1; j>0; j--){
      var k = Math.floor(Math.random()*(j+1));
      var tmp = out[j]; out[j]=out[k]; out[k]=tmp;
    }
    return out.join('');
  }
  $('#genPw').on('click', function(){
    var p = genPassword(14);
    $pw.val(p).trigger('input');
    $pw2.val(p).trigger('input');
  });

  $('#copyPw').on('click', function(){
    if (!$pw.val()) return;
    $pw[0].select();
    try { document.execCommand('copy'); } catch(e){}
    // kecilkan feedback
    $(this).addClass('text-success');
    var self = this;
    setTimeout(function(){ $(self).removeClass('text-success'); }, 900);
  });

  // deteksi CapsLock
  function handleCaps(e){
    try{
      var on = e.getModifierState && e.getModifierState('CapsLock');
      $('#capsAlert').toggle(!!on);
    }catch(_){}
  }
  $pw.on('keyup keydown', handleCaps);
  $pw2.on('keyup keydown', handleCaps);

  // evaluasi kekuatan + checklist
  function evaluate(pw){
    var score = 0;
    var rules = [
      { re: /.{8,}/,                        idx:0 },
      { re: /[A-Z]/,                        idx:1 },
      { re: /[a-z]/,                        idx:2 },
      { re: /[0-9]/,                        idx:3 },
      { re: /[^A-Za-z0-9]/,                 idx:4 }
    ];
    // reset ikon
    $req.find('i').removeClass('fa-check ok').removeClass('fa-close no').addClass('fa-close no');

    rules.forEach(function(r){
      if (r.re.test(pw)){ score++; $req.find('li').eq(r.idx).find('i').removeClass('fa-close no').addClass('fa-check ok'); }
    });
    // bonus panjang >= 12
    if (pw.length >= 12) score++;

    // meter 0..5 => % lebar
    var pct = Math.min(100, (score/5)*100);
    $fill.css('width', pct + '%');

    // warna meter (merah->kuning->hijau)
    // sudah dihandle oleh gradient; kita tinggal set ARIA label:
    $fill.attr('aria-valuenow', pct);

    return score;
  }

  function checkMatch(){
    var p1 = $pw.val(), p2 = $pw2.val();
    if (!p2){ $hint.text(''); return true; }
    if (p1 === p2){ $hint.html('<span class="text-success"><i class="fa fa-check-circle"></i> Cocok</span>'); return true; }
    $hint.html('<span class="text-danger"><i class="fa fa-exclamation-circle"></i> Tidak cocok</span>');
    return false;
  }

  $pw.on('input', function(){ evaluate($pw.val()); checkMatch(); });
  $pw2.on('input', checkMatch);

  // validasi submit
  $('#pwForm').on('submit', function(e){
    var p1 = $pw.val();
    var p2 = $pw2.val();
    var okScore = evaluate(p1) >= 4; // minimal 4/5 syarat terpenuhi
    var okMatch = checkMatch();

    if (!okScore || !okMatch){
      e.preventDefault();
      // animasi kecil sebagai feedback
      $pw.closest('.form-group').addClass('shake');
      setTimeout(function(){ $pw.closest('.form-group').removeClass('shake'); }, 350);
      // fokuskan ke input pertama yang bermasalah
      if (!okScore) $pw.focus(); else $pw2.focus();
    }
  });

  // tampilkan toast sukses tambahan bila ada ?alert=sukses (backend lama)
  <?php if(isset($_GET['alert']) && $_GET['alert']=='sukses'): ?>
    $(function(){
      var $t = $('#toastOk').fadeIn(200);
      setTimeout(function(){ $t.fadeOut(350); }, 2500);
    });
  <?php endif; ?>
})();
</script>
