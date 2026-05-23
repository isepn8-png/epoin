<?php include 'header.php'; ?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>
      RANKING SISWA
      <small>Prestasi & Pelanggaran</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Ranking</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-12">
        <div class="box box-primary">
          <div class="box-header">
            <h3 class="box-title">Ranking Berdasarkan Total Poin (Prestasi - Pelanggaran)</h3>
          </div>
          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-striped" id="table-datatable">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>NAMA SISWA</th>
                    <th class="text-center">NIS</th>
                    <th class="text-center">TOTAL PRESTASI</th>
                    <th class="text-center">TOTAL PELANGGARAN</th>
                    <th class="text-center">SKOR AKHIR</th>
                    <th class="text-center">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  if (!function_exists('epoin_h')) {
                    require_once __DIR__ . '/../includes/epoin_security.php';
                  }
                  $data = mysqli_query($koneksi,"SELECT 
                      siswa.siswa_id,
                      siswa.siswa_nama,
                      siswa.siswa_nis,
                      IFNULL((SELECT SUM(prestasi_point) FROM input_prestasi JOIN prestasi ON input_prestasi.prestasi = prestasi.prestasi_id WHERE input_prestasi.siswa = siswa.siswa_id),0) AS total_prestasi,
                      IFNULL((SELECT SUM(pelanggaran_point) FROM input_pelanggaran JOIN pelanggaran ON input_pelanggaran.pelanggaran = pelanggaran.pelanggaran_id WHERE input_pelanggaran.siswa = siswa.siswa_id),0) AS total_pelanggaran
                      FROM siswa
                  ");

                  $ranking = array();
                  while($d = mysqli_fetch_assoc($data)) {
                    $skor = $d['total_prestasi'] - $d['total_pelanggaran'];
                    $d['skor'] = $skor;
                    $ranking[] = $d;
                  }

                  usort($ranking, function($a, $b) {
                      return $b['skor'] - $a['skor'];
                  });

                  $no = 1;
                  foreach ($ranking as $d) {
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo epoin_h($d['siswa_nama']); ?></td>
                      <td class="text-center"><?php echo epoin_h($d['siswa_nis']); ?></td>
                      <td class="text-center"><?php echo (int)$d['total_prestasi']; ?></td>
                      <td class="text-center"><?php echo (int)$d['total_pelanggaran']; ?></td>
                      <td class="text-center"><?php echo (int)$d['skor']; ?></td>
                      <td class="text-center">
                        <a class="btn btn-success btn-sm" target="_blank" href="siswa_riwayat.php?id=<?php echo (int)$d['siswa_id'] ?>"><i class="fa fa-info-circle"></i> Detail</a>
                      </td>
                    </tr>
                    <?php 
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>
  </section>
</div>
<?php include 'footer.php'; ?>
