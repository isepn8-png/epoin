<?php include 'header.php'; ?>

<style>
  /* ================= THEME: Soft Blue Dashboard (selaras halaman Siswa/Manajemen Kelas) ================= */
  :root{
    --bg-page:    #f5f9ff;
    --bg-card:    #ffffff;
    --bg-row:     #eef5ff;
    --bg-hover:   #e8f2ff;
    --border:     #dbeafe;

    --head-txt:   #0f2a56;
    --body-txt:   #0b1220;

    --accent-1:   #3b82f6;
    --accent-2:   #1d4ed8;
    --accent-3:   #93c5fd;

    --btn-back:   #0ea5e9;
    --btn-add:    #2563eb;

    --glow:       0 10px 30px rgba(59,130,246,.25);
    --glow-soft:  0 6px 18px rgba(59,130,246,.18);
    --card-shadow:0 8px 22px rgba(15,42,86,.08);
  }

  .content-wrapper{
    background:
      radial-gradient(1200px 420px at 80% -50%, rgba(147,197,253,.25), transparent 60%),
      radial-gradient(900px 360px at -10% 10%, rgba(191,219,254,.25), transparent 60%),
      var(--bg-page);
    min-height: 100vh;
  }

  /* ===== Header / Title Area ===== */
  .content-header{ border-bottom:1px solid var(--border); padding-bottom:10px; margin-bottom:8px; }
  .content-header h1{
    color:var(--body-txt); font-weight:800; letter-spacing:.2px;
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    opacity:0; transform: translateY(6px); animation: textFade .6s ease-out .05s forwards;
  }
  .title-icon{
    display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:12px;
    background: linear-gradient(135deg, #e0ecff, #f0f7ff);
    color:#1e3a8a; box-shadow: var(--glow-soft);
  }
  .title-badge{
    display:inline-flex; align-items:center; gap:6px;
    background:linear-gradient(90deg,#2563eb,#1d4ed8); color:#ffffff;
    border-radius:999px; padding:3px 10px; font-weight:700; line-height:1;
    font-size: clamp(10px, 1.6vw, 11px); border:0; box-shadow: 0 4px 12px rgba(29,78,216,.25);
  }
  .title-badge i{ font-size:12px; }
  .breadcrumb > li + li:before { content: "› "; color:#64748b; }
  .breadcrumb > li > a, .breadcrumb > .active{ color:#475569; opacity:0; transform: translateY(4px); animation: textFade .5s ease-out .12s forwards; }

  @keyframes textFade{ from{opacity:0; transform: translateY(6px);} to{opacity:1; transform: translateY(0);} }

  /* ===== Box / Card ===== */
  .box{ border-top:0; box-shadow: var(--card-shadow); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
  .box-header{
    background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%);
    color: var(--head-txt);
    border-bottom: 1px solid var(--border);
    padding: 14px 15px;
    display:flex; align-items:center; justify-content:flex-start; gap:10px; flex-wrap:wrap;
  }
  .box-header .box-title{ margin:0; font-weight:800; color:#0f2a56; display:flex; align-items:center; gap:8px; }
  .box-header .box-title i{ color:#2563eb; }
  .box-header-actions{ margin-left:auto; display:flex; gap:8px; }

  /* ===== Buttons ===== */
  .btn-back{ background: linear-gradient(90deg,#38bdf8,#0ea5e9); color:#fff; border:0; border-radius:12px; padding:8px 14px; box-shadow: var(--glow); }
  .btn-back:hover{ filter:brightness(1.06); transform: translateY(-1px); }
  .btn-add{
    background: linear-gradient(90deg,#3b82f6,#1d4ed8); color:#fff !important; border:0; border-radius:12px;
    padding:10px 16px; box-shadow: var(--glow); display:inline-flex; align-items:center; gap:8px;
    font-weight:700;
  }
  .btn-add:hover{ filter:brightness(1.05); transform: translateY(-1px); }

  /* ===== Form ===== */
  .form-group > label{ font-weight:700; color:#0f2a56; display:flex; gap:8px; align-items:center; }
  .form-control{ border-radius:10px; border:1px solid var(--border); }
  .form-control:focus{ border-color:#93c5fd; box-shadow:0 0 0 3px rgba(147,197,253,.35); }

  /* ===== Helper Chip ===== */
  .helper-chip{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:linear-gradient(90deg,#eaf2ff,#dbeafe); color:#1e3a8a; font-size:12px; border:1px solid var(--border); }

  /* ===== Tables (kalau ada di halaman ini) ===== */
  .table > thead > tr > th,
  .table > tbody > tr > td{ color: var(--body-txt); }
  .table thead th{ background: linear-gradient(180deg,#f0f6ff 0%, #e8f2ff 100%); color:#0f2a56; border-bottom:1px solid var(--border) !important; }
  .table tbody tr:nth-child(odd){ background:#fff; }
  .table tbody tr:nth-child(even){ background: var(--bg-row); }
  .table tbody tr{ transition: background-color .15s ease, transform .06s ease; }
  .table tbody tr:hover{ background: var(--bg-hover) !important; }

  /* Mobile tweaks */
  @media (max-width: 576px){ .content-header h1{ gap:8px; } .title-badge{ font-size:10px; padding:3px 8px; } }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      <span class="title-icon"><i class="fa fa-university"></i></span>
      Kelas
      <small class="title-badge"><i class="fa fa-plus-circle"></i> Tambah Kelas Baru</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-6 col-lg-offset-3">       
        <div class="box box-primary">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-plus-square"></i> Tambah Kelas Baru</h3>
            <div class="box-header-actions">
              <a href="kelas.php" class="btn btn-back btn-sm"><i class="fa fa-reply"></i> Kembali</a>
            </div>
          </div>

          <div class="box-body">
            <div style="margin-bottom:8px;">
              <span class="helper-chip"><i class="fa fa-info-circle"></i> Isi data kelas dengan lengkap, lalu klik <b>Simpan</b>.</span>
            </div>

            <form action="kelas_act.php" method="post">
              <?= epoin_csrf_field() ?>

              <div class="form-group">
                <label><i class="fa fa-tag"></i> Nama Kelas</label>
                <input type="text" class="form-control" name="nama" required="required" placeholder="Masukkan Nama Kelas ..">
              </div>

              <div class="form-group">
                <label><i class="fa fa-sitemap"></i> Tingkat Kelas</label>
                <select class="form-control" name="jurusan" required="required">
                  <option value=""> - Pilih Tingkat Kelas - </option>
                  <?php 
                  $jurusan = mysqli_query($koneksi,"select * from jurusan");
                  while($j = mysqli_fetch_array($jurusan)){
                    ?>
                    <option value="<?php echo $j['jurusan_id'] ?>"><?php echo $j['jurusan_nama'] ?></option>
                    <?php 
                  }
                  ?>
                </select>
              </div>

              <div class="form-group">
                <label><i class="fa fa-calendar"></i> Tahun Ajaran</label>
                <select class="form-control" name="ta" required="required">
                  <?php 
                  $ta = mysqli_query($koneksi,"select * from ta");
                  while($j = mysqli_fetch_array($ta)){
                    ?>
                    <option value="<?php echo $j['ta_id'] ?>"><?php echo $j['ta_nama'] ?> <?php if($j['ta_status'] == "1"){ echo "(Aktif)"; } ?></option>
                    <?php 
                  }
                  ?>
                </select>
              </div>

              <div class="form-group" style="display:flex; gap:8px;">
                <input type="submit" class="btn btn-add" value="Simpan">
                <a href="kelas.php" class="btn btn-default">Batal</a>
              </div>
            </form>
          </div>

        </div>
      </section>
    </div>
  </section>

</div>
<?php include 'footer.php'; ?>