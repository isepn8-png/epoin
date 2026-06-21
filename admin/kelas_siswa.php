<?php include 'header.php'; ?>

<style>
  /* ================= THEME: Soft Blue Dashboard (match Siswa/Manajemen Kelas) ================= */
  :root{
    --bg-page:    #f5f9ff;
    --bg-card:    #ffffff;
    --bg-row:     #eef5ff;
    --bg-hover:   #e8f2ff;
    --border:     #dbeafe;

    --head-txt:   #0f2a56;
    --body-txt:   #000000;

    --accent-1:   #3b82f6;
    --accent-2:   #1d4ed8;
    --accent-3:   #93c5fd;

    --btn-add:    #16a34a;
    --btn-back:   #0ea5e9;

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
  .content-header{ border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 8px; }
  .content-header h1{
    color:#0b1220; font-weight: 800; letter-spacing:.2px;
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
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    /* Alignment rule: title stays left; actions push to far right */
    justify-content:flex-start;
  }
  .box-header .box-title{ margin:0; font-weight:800; color:#0f2a56; display:flex; align-items:center; gap:8px; }
  .box-header .box-title i{ color:#2563eb; }
  .box-header-actions{ margin-left:auto; display:inline-flex; align-items:center; gap:8px; }

  /* ===== Buttons ===== */
  .btn-back{ background: linear-gradient(90deg,#38bdf8,#0ea5e9); color:#fff; border:0; border-radius:12px; padding:8px 14px; box-shadow: var(--glow); }
  .btn-back:hover{ filter:brightness(1.05); }
  .btn-add{ background: linear-gradient(90deg,#22c55e,#16a34a); color:#fff !important; border:0; border-radius:12px; padding:8px 14px; box-shadow: var(--glow); display:inline-flex; align-items:center; gap:8px; }
  .btn-add:hover{ filter:brightness(1.05); transform: translateY(-1px); }

  /* ===== Tables ===== */
  .table > thead > tr > th,
  .table > tbody > tr > td{ color: var(--body-txt); }
  .table thead th{ background: linear-gradient(180deg,#f0f6ff 0%, #e8f2ff 100%); color:#0f2a56; border-bottom:1px solid var(--border) !important; }
  .table tbody tr:nth-child(odd){ background:#fff; }
  .table tbody tr:nth-child(even){ background: var(--bg-row); }
  .table tbody tr{ transition: background-color .15s ease, transform .06s ease; }
  .table tbody tr:hover{ background: var(--bg-hover) !important; }

  /* ===== Chips / Badges ===== */
  .pill-total{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:linear-gradient(90deg,#eaf2ff,#dbeafe); color:#1e3a8a; font-size:12px; border:1px solid var(--border); }

  /* ===== Modal ===== */
  .modal-header{ background: linear-gradient(180deg,#f0f6ff 0%, #ffffff 100%) !important; border-bottom:1px solid var(--border) !important; }
  .modal-title{ color:#0f2a56; font-weight:800; }

  /* ===== Misc ===== */
  .badge.bg-success{ background:#dcfce7; color:#065f46; }
  .badge.bg-info{ background:#e0f2fe; color:#0c4a6e; }

  /* Mobile tweaks */
  @media (max-width: 576px){
    .content-header h1{ gap:8px; }
    .title-badge{ font-size:10px; padding:3px 8px; }
    .box-header{ flex-wrap:wrap; }
    .box-header-actions{ margin-left:0; width:100%; display:flex; justify-content:flex-start; }
  }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1>
      <span class="title-icon"><i class="fa fa-university"></i></span>
      Kelas
      <small class="title-badge"><i class="fa fa-users"></i> Manajemen Siswa Kelas</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-6">
        <div class="box box-primary">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-info-circle"></i> Tentang Kelas</h3>
            <a href="kelas.php" class="btn btn-back btn-sm box-header-actions"><i class="fa fa-reply"></i>&nbsp; Kembali</a>
          </div>

          <div class="box-body">
            <?php
              // Guard: id aman (mencegah error 500 saat id kosong/invalid)
              $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
              $k = null;
              if (isset($koneksi) && $id > 0) {
                $kelas = mysqli_query($koneksi, "SELECT * FROM kelas, jurusan, ta WHERE kelas_ta=ta_id AND kelas_jurusan=jurusan_id AND kelas_id='".$id."'");
                if ($kelas) { $k = mysqli_fetch_assoc($kelas); }
              }
              $id_kelas = $k['kelas_id'] ?? 0;
              $id_ta    = $k['kelas_ta']   ?? 0;
            ?>

            <div class="table-responsive">
              <table class="table table-bordered">
                <tr>
                  <th width="30%">Nama Kelas</th>
                  <td><?php echo isset($k['kelas_nama']) ? $k['kelas_nama'] : '-'; ?></td>
                </tr>
                <tr>
                  <th>Tingkat Kelas</th>
                  <td><?php echo isset($k['jurusan_nama']) ? $k['jurusan_nama'] : '-'; ?></td>
                </tr>
                <tr>
                  <th>Tahun Ajaran</th>
                  <td><?php echo isset($k['ta_nama']) ? $k['ta_nama'] : '-'; ?></td>
                </tr>
              </table>
            </div>

          </div>

        </div>
      </section>
    </div>

    <!-- Form untuk menambahkan siswa ke kelas -->
    <div class="box box-primary">

      <div class="box-header">
        <h3 class="box-title"><i class="fa fa-users"></i> Siswa Kelas</h3>
        <div class="btn-group box-header-actions">
          <button type="button" class="btn btn-add btn-sm" data-toggle="modal" data-target="#modal_jurusan">
            <i class="fa fa-plus"></i> &nbsp;Tambahkan Siswa Ke Kelas
          </button>
        </div>

        <!-- Modal untuk memilih siswa -->
        <div class="modal fade" id="modal_jurusan">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">

              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-user-plus"></i> Pilih Siswa</h4>
              </div>

              <div class="modal-body">
                <form action="kelas_siswa_act.php" method="post">
                  <?= epoin_csrf_field() ?>
                  <input type="hidden" name="kelas" value="<?php echo (int)$id_kelas; ?>">
                  <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="table-datatable">
                      <thead>
                        <tr>
                          <th width="1%"><input type="checkbox" id="checkAll"></th>
                          <th>NAMA</th>
                          <th>NIS</th>
                          <th>TINGKAT/JURUSAN</th>
                          <th>STATUS</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                          if (isset($koneksi)) {
                            $data = mysqli_query($koneksi, "SELECT * FROM siswa, jurusan WHERE siswa_jurusan=jurusan_id AND siswa_id NOT IN (SELECT ks_siswa FROM kelas_siswa, kelas WHERE ks_kelas=kelas_id AND kelas_ta='".(int)$id_ta."') ORDER BY siswa_id DESC");
                            if ($data) {
                              while ($d = mysqli_fetch_array($data)) {
                        ?>
                          <tr>
                            <td><input type="checkbox" name="siswa[]" value="<?php echo $d['siswa_id']; ?>"></td>
                            <td><?php echo $d['siswa_nama']; ?></td>
                            <td><?php echo $d['siswa_nis']; ?></td>
                            <td><?php echo $d['jurusan_nama']; ?></td>
                            <td><span class="badge bg-success"><?php echo $d['siswa_status']; ?></span></td>
                          </tr>
                        <?php
                              }
                            }
                          }
                        ?>
                      </tbody>
                    </table>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambahkan Siswa</button>
                  </div>
                </form>
              </div>

            </div>
          </div>
        </div>

      </div>

      <!-- Tabel untuk menampilkan siswa yang sudah ada di kelas -->
      <div class="box-body">
        <div class="table-responsive">
          <h5 class="pill-total"><i class="fa fa-database"></i> Total Siswa:
            <?php
              if (isset($koneksi)) {
                $total = ['total'=>0];
                $total_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM siswa WHERE siswa_id IN (SELECT ks_siswa FROM kelas_siswa WHERE ks_kelas='".(int)$id_kelas."')");
                if ($total_siswa) { $total = mysqli_fetch_assoc($total_siswa) ?: $total; }
                echo (int)$total['total'];
              }
            ?>
          </h5>
          <table class="table table-bordered table-striped" id="table-datatable">
            <thead>
              <tr>
                <th width="1%">NO</th>
                <th><a href="#" class="sortable">NAMA</a></th>
                <th><a href="#" class="sortable">NIS</a></th>
                <th><a href="#" class="sortable">TINGKAT/JURUSAN</a></th>
                <th><a href="#" class="sortable">STATUS</a></th>
                <th width="10%">OPSI</th>
              </tr>
            </thead>
            <tbody>
              <?php
                if (isset($koneksi)) {
                  $no = 1;
                  $data2 = mysqli_query($koneksi, "SELECT * FROM siswa, jurusan WHERE siswa_jurusan=jurusan_id AND siswa_id IN (SELECT ks_siswa FROM kelas_siswa WHERE ks_kelas='".(int)$id."') ORDER BY siswa_id DESC");
                  if ($data2) {
                    while ($d = mysqli_fetch_array($data2)) {
              ?>
                <tr>
                  <td><?php echo $no++; ?></td>
                  <td><?php echo $d['siswa_nama']; ?></td>
                  <td><?php echo $d['siswa_nis']; ?></td>
                  <td><?php echo $d['jurusan_nama']; ?></td>
                  <td><span class="badge bg-info"><?php echo $d['siswa_status']; ?></span></td>
                  <td>
                    <a class="btn btn-danger btn-sm" href="kelas_siswa_keluarkan.php?siswa=<?php echo $d['siswa_id'] ?>&kelas=<?php echo $id_kelas ?>"><i class="fa fa-close"></i> Keluarkan</a>
                  </td>
                </tr>
              <?php
                    }
                  }
                }
              ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </section>

</div>

<script>
  // Script untuk "Select All" checkbox
  $("#checkAll").click(function () {
    $('input:checkbox').not(this).prop('checked', this.checked);
  });

  // Script untuk sorting tabel (klik judul kolom)
  $(".sortable").click(function() {
    var table = $(this).parents('table').eq(0);
    var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
    this.asc = !this.asc;
    if (!this.asc) { rows = rows.reverse(); }
    for (var i = 0; i < rows.length; i++) { table.append(rows[i]); }
  });

  function comparer(index) {
    return function(a, b) {
      var valA = getCellValue(a, index), valB = getCellValue(b, index);
      return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.toString().localeCompare(valB);
    }
  }

  function getCellValue(row, index) { return $(row).children('td').eq(index).text(); }
</script>

<?php include 'footer.php'; ?>
