<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>

<div class="content-wrapper prestasi-edit"><!-- scope styling -->

  <section class="content-header">
    <h1 class="title-wrap">
      <span><i class="fa fa-trophy" style="color:#16a34a"></i> Prestasi</span>
      <small class="sub">Edit Prestasi</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <!-- ===== THEME (Hijau) & UX polish ===== -->
  <style>
    :root{
      --g700:#15803d; --g600:#16a34a; --g500:#22c55e;
      --g300:#86efac; --g200:#bbf7d0; --g100:#dcfce7; --g50:#f0fdf4;
      --ink:#1b2559;
    }

    .prestasi-edit .content-wrapper{ animation:fadeLift .5s ease-out both; }
    @keyframes fadeLift{0%{opacity:0;transform:translateY(10px)}100%{opacity:1;transform:translateY(0)}}

    .prestasi-edit .title-wrap{ display:flex; align-items:center; gap:10px; }
    .prestasi-edit .title-wrap .sub{ color:#64748b; }

    /* Card */
    .prestasi-edit .box{ box-shadow:0 8px 24px rgba(22,163,74,.10); border-radius:12px; overflow:hidden; }
    .prestasi-edit .box .box-header{
      border-bottom:0;
      background:linear-gradient(90deg,var(--g50),#fff);
      border-top:3px solid var(--g600);
    }
    .prestasi-edit .box-body{ background:linear-gradient(180deg,#fff,var(--g50)); }

    /* Tombol kembali (hijau) */
    .prestasi-edit .btn-back{
      background:linear-gradient(90deg,var(--g500),var(--g600)); border-color:var(--g700); color:#fff !important;
      transition:transform .08s, box-shadow .2s, filter .2s;
    }
    .prestasi-edit .btn-back:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(22,163,74,.22); filter:saturate(1.05); }

    /* Tombol simpan (hijau kuat) */
    .prestasi-edit .btn-primary{
      background:linear-gradient(90deg,var(--g500),var(--g600)); border-color:var(--g700);
      transition:transform .08s, box-shadow .2s, filter .2s;
    }
    .prestasi-edit .btn-primary:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(22,163,74,.22); filter:saturate(1.05); }

    /* Input berikon */
    .prestasi-edit .input-group-addon{ background:#fff; border-right:0; }
    .prestasi-edit .form-control{ border-left:0; }
    .prestasi-edit .form-control:focus{
      border-color:var(--g500);
      box-shadow:0 0 0 2px rgba(34,197,94,.15);
    }
    .prestasi-edit .help-hint{ color:#6b7280; font-size:12px; margin-top:4px; }

    /* Header kecil di card */
    .prestasi-edit .box-title{ display:flex; align-items:center; gap:8px; margin:0; }
    .prestasi-edit .box-title .tag{
      display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700;
      color:#064e3b; background:linear-gradient(90deg,#dcfce7,#bbf7d0); border:1px solid rgba(16,185,129,.28);
    }

    @media (max-width:768px){
      .prestasi-edit .title-wrap .sub{ display:none; }
      .prestasi-edit .btn-back{ margin-top:8px; }
    }
  </style>
  <!-- ===== /THEME ===== -->

  <section class="content">
    <div class="row">
      <section class="col-lg-6">
        <div class="box">

          <div class="box-header">
            <h3 class="box-title">
              <i class="fa fa-edit" style="color:#16a34a"></i> Edit Prestasi
              <span class="tag"><i class="fa fa-trophy"></i> Form</span>
            </h3>
            <a href="prestasi.php" class="btn btn-success btn-sm btn-back pull-right">
              <i class="fa fa-reply"></i> &nbsp;Kembali
            </a>
          </div>

          <div class="box-body">
            <form action="prestasi_update.php" method="post" id="formEditPrestasi" autocomplete="off">
              <?php echo epoin_csrf_field(); ?>
              <?php 
              $id_prestasi = isset($_GET['id']) ? (int)$_GET['id'] : 0;
              $stmtPre = mysqli_prepare($koneksi, 'SELECT * FROM prestasi WHERE prestasi_id = ? LIMIT 1');
              if ($stmtPre) {
                mysqli_stmt_bind_param($stmtPre, 'i', $id_prestasi);
                mysqli_stmt_execute($stmtPre);
                $prestasi = mysqli_stmt_get_result($stmtPre);
              } else {
                $prestasi = false;
              }
              while($prestasi && ($s=mysqli_fetch_array($prestasi))){
                ?>
                <div class="form-group">
                  <label>Nama Prestasi</label>
                  <input type="hidden" name="id" value="<?php echo $s['prestasi_id'] ?>">
                  <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-trophy" style="color:#16a34a"></i></span>
                    <input type="text" class="form-control" name="nama" required="required"
                           placeholder="Masukkan Nama prestasi.." value="<?php echo $s['prestasi_nama'] ?>">
                  </div>
                  <div class="help-hint">Gunakan nama yang jelas dan ringkas.</div>
                </div>
                
                <div class="form-group">
                  <label>Point</label>
                  <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hashtag" style="color:#16a34a"></i></span>
                    <input type="number" class="form-control" name="point" required="required"
                           min="1" step="1" placeholder="Masukkan Jumlah Point.." value="<?php echo $s['prestasi_point'] ?>">
                    <span class="input-group-addon" style="font-weight:700; color:#064e3b;">Point</span>
                  </div>
                  <div class="help-hint">Sesuaikan point dengan bobot/level prestasi.</div>
                </div>

                <div class="form-group" style="margin-top:14px;">
                  <input type="submit" class="btn btn-primary" value="Simpan">
                </div>
                <?php 
              }
              ?>
            </form>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<script>
  // UX: fokus otomatis ke kolom nama saat halaman dimuat
  (function(){
    var el = document.querySelector('#formEditPrestasi input[name="nama"]');
    if (el){ setTimeout(function(){ try{ el.focus(); el.select(); }catch(e){} }, 180); }
  })();
</script>

<?php include 'footer.php'; ?>
