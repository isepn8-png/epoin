<?php include 'header.php'; ?>
<?php require_once __DIR__ . '/../includes/epoin_security.php'; ?>
<div class="content-wrapper">

  <section class="content-header">
    <h1>
      LAPORAN TAHUNAN
      <small>Rekap Poin Pelanggaran & Prestasi Siswa</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Rekap Tahunan</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-12">

        <div class="box box-primary">
          <div class="box-header">
            <h3 class="box-title">Filter Tahun</h3>
          </div>
          <div class="box-body">
            <form method="get" action="">
              <div class="form-group col-md-4">
                <label>Pilih Tahun</label>
                <input type="number" name="tahun" class="form-control" min="2000" max="2100" value="<?php echo epoin_h(isset($_GET['tahun']) ? (string)(int)$_GET['tahun'] : date('Y')); ?>" required>
              </div>
              <div class="form-group col-md-4">
                <label>&nbsp;</label><br>
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
              </div>
            </form>
          </div>
        </div>

        <div class="box box-primary">
          <div class="box-header">
            <h3 class="box-title">Rekapitulasi Tahunan</h3>
          </div>
          <div class="box-body">

            <?php
            if (isset($_GET['tahun'])) {
              $tahun = (int) $_GET['tahun'];
              if ($tahun < 2000 || $tahun > 2100) {
                echo '<div class="alert alert-warning">Tahun tidak valid. Gunakan rentang 2000–2100.</div>';
              } else {
                $query = "
                  SELECT
                      s.siswa_nis AS nis,
                      s.siswa_nama AS nama_siswa,
                      COALESCE(SUM(CASE WHEN YEAR(ig.waktu) = ? THEN pg.pelanggaran_point ELSE 0 END), 0) AS total_pelanggaran,
                      COALESCE(SUM(CASE WHEN YEAR(ip.waktu) = ? THEN pr.prestasi_point ELSE 0 END), 0) AS total_prestasi
                  FROM siswa s
                  LEFT JOIN input_pelanggaran ig ON s.siswa_id = ig.siswa
                  LEFT JOIN pelanggaran pg ON ig.pelanggaran = pg.pelanggaran_id
                  LEFT JOIN input_prestasi ip ON s.siswa_id = ip.siswa
                  LEFT JOIN prestasi pr ON ip.prestasi = pr.prestasi_id
                  GROUP BY s.siswa_id, s.siswa_nis, s.siswa_nama
                  ORDER BY s.siswa_nama ASC
                ";
                $stmt = mysqli_prepare($koneksi, $query);
                $result = false;
                if ($stmt) {
                  mysqli_stmt_bind_param($stmt, 'ii', $tahun, $tahun);
                  mysqli_stmt_execute($stmt);
                  $result = mysqli_stmt_get_result($stmt);
                }
                if ($result === false) {
                  echo '<div class="alert alert-danger">Gagal memuat data rekap. Silakan coba lagi.</div>';
                } else {
              ?>

              <div class="row">
                <div class="col-lg-6">
                  <table class="table table-bordered">
                    <tr>
                      <th width="30%">TAHUN</th>
                      <th width="1%">:</th>
                      <td><?php echo epoin_h((string) $tahun); ?></td>
                    </tr>
                  </table>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-bordered table-striped" id="table-datatable">
                  <thead>
                    <tr>
                      <th width="1%">NO</th>
                      <th>NAMA SISWA</th>
                      <th class="text-center">NIS</th>
                      <th class="text-center">TOTAL PRESTASI</th>
                      <th class="text-center">TOTAL PELANGGARAN</th>
                      <th class="text-center">OPSI</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;
                    while ($data = mysqli_fetch_assoc($result)) {
                      ?>
                      <tr>
                        <td><?php echo (int) $no++; ?></td>
                        <td><?php echo epoin_h($data['nama_siswa'] ?? ''); ?></td>
                        <td class="text-center"><?php echo epoin_h($data['nis'] ?? ''); ?></td>
                        <td class="text-center"><?php echo (int) ($data['total_prestasi'] ?? 0); ?></td>
                        <td class="text-center"><?php echo (int) ($data['total_pelanggaran'] ?? 0); ?></td>
                        <td class="text-center">
                          <a class="btn btn-success btn-sm" target="_blank" href="siswa_riwayat.php?nis=<?php echo urlencode((string) ($data['nis'] ?? '')); ?>"><i class="fa fa-info-circle"></i> Detail</a>
                        </td>
                      </tr>
                      <?php
                    }
                    mysqli_stmt_close($stmt);
                    ?>
                  </tbody>
                </table>
              </div>

              <?php
                }
              }
            } else {
              ?>
              <div class="alert alert-info text-center">
                Silahkan filter tahun terlebih dahulu.
              </div>
              <?php
            }
            ?>

          </div>
        </div>

      </section>
    </div>
  </section>
</div>
<?php include 'footer.php'; ?>
