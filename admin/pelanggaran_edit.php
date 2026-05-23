<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>

<div class="content-wrapper pelanggaran-edit"><!-- scope styling -->

  <section class="content-header">
    <h1 class="title-wrap">
      <span><i class="fa fa-gavel" style="color:#dc2626"></i> Pelanggaran</span>
      <small class="sub">Edit Pelanggaran</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <!-- ===== THEME (Merah) & UX polish ===== -->
  <style>
    :root{
      --r700:#b91c1c; --r600:#dc2626; --r500:#ef4444;
      --r300:#fca5a5; --r200:#fecaca; --r100:#fee2e2; --r50:#fef2f2;
      --ink:#1b2559;
    }

    .pelanggaran-edit .content-wrapper{ animation:fadeLift .5s ease-out both; }
    @keyframes fadeLift{0%{opacity:0;transform:translateY(10px)}100%{opacity:1;transform:translateY(0)}}

    .pelanggaran-edit .title-wrap{ display:flex; align-items:center; gap:10px; }
    .pelanggaran-edit .title-wrap .sub{ color:#64748b; }

    /* Card */
    .pelanggaran-edit .box{ box-shadow:0 8px 24px rgba(220,38,38,.10); border-radius:12px; overflow:hidden; }
    .pelanggaran-edit .box .box-header{
      border-bottom:0;
      background:linear-gradient(90deg,var(--r50),#fff);
      border-top:3px solid var(--r600);
    }
    .pelanggaran-edit .box-body{ background:linear-gradient(180deg,#fff,var(--r50)); }

    /* Tombol kembali (hijau agar kontras) */
    .pelanggaran-edit .btn-back{
      background:linear-gradient(90deg,#10b981,#059669); border-color:#047857; color:#fff !important;
      transition:transform .08s, box-shadow .2s, filter .2s;
    }
    .pelanggaran-edit .btn-back:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(16,185,129,.22); filter:saturate(1.05); }

    /* Tombol simpan (merah) */
    .pelanggaran-edit .btn-primary{
      background:linear-gradient(90deg,var(--r500),var(--r600)); border-color:var(--r700);
      transition:transform .08s, box-shadow .2s, filter .2s;
    }
    .pelanggaran-edit .btn-primary:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(220,38,38,.22); filter:saturate(1.05); }

    /* Input berikon */
    .pelanggaran-edit .input-group-addon{ background:#fff; border-right:0; }
    .pelanggaran-edit .form-control{ border-left:0; }
    .pelanggaran-edit .form-control:focus{
      border-color:var(--r500);
      box-shadow:0 0 0 2px rgba(239,68,68,.15);
    }
    .pelanggaran-edit .help-hint{ color:#6b7280; font-size:12px; margin-top:4px; }

    /* Header kecil di card */
    .pelanggaran-edit .box-title{ display:flex; align-items:center; gap:8px; margin:0; }
    .pelanggaran-edit .box-title .tag{
      display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700;
      color:#7f1d1d; background:linear-gradient(90deg,#ffe4e6,#fecaca); border:1px solid rgba(239,68,68,.28);
    }

    @media (max-width:768px){
      .pelanggaran-edit .title-wrap .sub{ display:none; }
      .pelanggaran-edit .btn-back{ margin-top:8px; }
    }
  </style>
  <!-- ===== /THEME ===== -->

  <section class="content">
    <div class="row">
      <section class="col-lg-6">
        <div class="box">

          <div class="box-header">
            <h3 class="box-title">
              <i class="fa fa-edit" style="color:#dc2626"></i> Edit Pelanggaran
              <span class="tag"><i class="fa fa-gavel"></i> Form</span>
            </h3>
            <a href="pelanggaran.php" class="btn btn-success btn-sm btn-back pull-right">
              <i class="fa fa-reply"></i> &nbsp;Kembali
            </a>
          </div>

          <div class="box-body">
            <form action="pelanggaran_update.php" method="post" id="formEditPelanggaran" autocomplete="off">
              <?php echo epoin_csrf_field(); ?>
              <?php 
              $id_pelanggaran = isset($_GET['id']) ? (int)$_GET['id'] : 0;
              $stmtPel = mysqli_prepare($koneksi, 'SELECT * FROM pelanggaran WHERE pelanggaran_id = ? LIMIT 1');
              if ($stmtPel) {
                mysqli_stmt_bind_param($stmtPel, 'i', $id_pelanggaran);
                mysqli_stmt_execute($stmtPel);
                $pelanggaran = mysqli_stmt_get_result($stmtPel);
              } else {
                $pelanggaran = false;
              }
              while($pelanggaran && ($s=mysqli_fetch_array($pelanggaran))){
                ?>
                <div class="form-group">
                  <label>Nama Pelanggaran</label>
                  <input type="hidden" name="id" value="<?php echo $s['pelanggaran_id'] ?>">
                  <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-exclamation-triangle" style="color:#dc2626"></i></span>
                    <input type="text" class="form-control" name="nama" required="required"
                           placeholder="Masukkan Nama pelanggaran.." value="<?php echo $s['pelanggaran_nama'] ?>">
                  </div>
                  <div class="help-hint">Gunakan nama yang jelas dan ringkas.</div>
                </div>
                
                <div class="form-group">
                  <label>Point</label>
                  <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hashtag" style="color:#dc2626"></i></span>
                    <input type="number" class="form-control" name="point" required="required"
                           min="1" step="1" placeholder="Masukkan Jumlah Point.." value="<?php echo $s['pelanggaran_point'] ?>">
                    <span class="input-group-addon" style="font-weight:700;color:#7f1d1d;">Point</span>
                  </div>
                  <div class="help-hint">Semakin berat pelanggaran, semakin besar poinnya.</div>
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
  // UX: fokus otomatis ke nama saat halaman dibuka
  (function(){
    var el = document.querySelector('#formEditPelanggaran input[name="nama"]');
    if (el){ setTimeout(function(){ try{ el.focus(); el.select(); }catch(e){} }, 180); }
  })();
</script>

<?php include 'footer.php'; ?>
