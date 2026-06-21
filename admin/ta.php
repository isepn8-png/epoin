<?php include 'header.php'; ?>

<style>
/* ====== EPS polish (fallback bila eps-ui.css belum terload) ====== */
:root{
  --blue-50:#f0f6ff; --blue-100:#e3efff; --blue-200:#cfe3ff; --blue-300:#b9d6ff;
  --blue-400:#8fbaff; --blue-500:#4f9cf9; --blue-600:#2d6cdf; --blue-700:#1f5ac8;
  --ink-900:#0b1220; --ink-800:#1e293b; --ink-700:#334155; --line:#dbe5ff;
  --bg-page:linear-gradient(180deg,#f8fbff 0%, #f3f7ff 100%);
  --bg-card:#fff; --bg-row:#f8fbff; --bg-hover:#eef4ff;
  --radius-lg:16px; --radius-md:12px; --radius-pill:999px;
  --shadow-lg:0 10px 30px rgba(45,108,223,.12);
  --grad-primary:linear-gradient(90deg, var(--blue-600), var(--blue-500));
  --grad-primary-hover:linear-gradient(90deg, var(--blue-700), var(--blue-600));
  --fs-xs:clamp(11px,.85vw,12px); --fs-2xl:clamp(22px,2.6vw,28px);
}
@keyframes textFade{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }
.content-wrapper{ background:var(--bg-page); }

/* ==== Full-width container (hilangkan offset/penyempitan) ==== */
@media (min-width:1200px){
  .content .row .col-lg-12{ float:none; width:100%; }
}
@media (min-width:992px){
  .content { padding-left:15px; padding-right:15px; }
}

/* Page title + badge */
.page-title{
  display:flex; align-items:center; gap:12px;
  font-size:var(--fs-2xl); font-weight:800; color:var(--ink-900);
  letter-spacing:.2px; animation:textFade .6s ease-out both;
}
.title-icon{
  width:40px;height:40px;border-radius:12px;
  display:inline-flex;align-items:center;justify-content:center;
  background:var(--blue-100); color:var(--blue-600);
  box-shadow:inset 0 0 0 1px var(--line);
}
.badge-chip{
  background:var(--blue-50); border:1px solid var(--line); color:var(--ink-700);
  border-radius:var(--radius-pill); padding:4px 10px;
  display:inline-flex; align-items:center; gap:6px;
  font-size:var(--fs-xs); font-weight:700; white-space:nowrap;
}
.badge-chip i{ color:var(--blue-600); }

/* Box / Card */
.box{ border:0;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;position:relative;background:var(--bg-card); }
.box:before{ content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--blue-600),var(--blue-400),var(--blue-600));opacity:.9; }
.box-header{ background:var(--bg-card);border-bottom:1px solid var(--line);padding:14px 18px; }
.box-title{ display:flex;align-items:center;gap:8px;font-weight:800;color:var(--ink-900); }
.box-title i{ color:var(--blue-600); }

/* Buttons */
.btn-grad{
  background:var(--grad-primary); color:#fff; border:0; border-radius:var(--radius-md);
  padding:9px 12px; font-weight:700; box-shadow:0 8px 20px rgba(45,108,223,.25);
  transition:transform .15s ease, filter .2s ease;
}
.btn-grad:hover{ filter:brightness(1.06); transform:translateY(-1px); }

/* Table */
.table-eps>thead>tr>th{
  background:linear-gradient(180deg,#f7faff 0%, #f1f6ff 100%);
  color:#1e293b; border-bottom:1px solid var(--line)!important;
}
.table-eps>tbody>tr:nth-child(odd){ background:#fff; }
.table-eps>tbody>tr:nth-child(even){ background:var(--bg-row); }
.table-eps>tbody>tr{ transition:background .18s ease, box-shadow .18s ease; }
.table-eps>tbody>tr:hover{ background:var(--bg-hover)!important; box-shadow:inset 3px 0 0 0 var(--blue-500); cursor:pointer; }
.table-eps td{ color:#000; }

/* Status badges */
.badge-aktif{ display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; background:#e8fff3; color:#16a34a; border:1px solid #c9f1da; }
.badge-selesai{ display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; background:#f1f5f9; color:#334155; border:1px solid #e2e8f0; }

/* Modal polish */
.modal-content{ border-radius:16px;border:0;box-shadow:var(--shadow-lg); }
.modal-header{ background:linear-gradient(180deg,var(--blue-50),#fff);border-bottom:1px solid var(--line)!important;color:var(--ink-900) }
.modal-title i{ color:var(--blue-600) }
.modal-body .form-control{ border-radius:12px;border:1px solid var(--line);box-shadow:none }
.modal-body .form-control:focus{ border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(79,156,249,.15) }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-calendar-alt"></i></span>
      <span>Tahun Ajaran</span>
      <span class="badge-chip"><i class="fa fa-database"></i> Data Master</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Tahun Ajaran</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <!-- FULL WIDTH: tidak ada offset -->
      <section class="col-lg-12">
        <div class="box">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-list-ul"></i> Daftar Tahun Ajaran</h3>
            <div class="pull-right">
              <button type="button" class="btn btn-grad btn-sm" data-toggle="modal" data-target="#modal_ta">
                <i class="fa fa-plus"></i> &nbsp;Tambah Tahun Ajaran Baru
              </button>
            </div>
          </div>

          <div class="box-body">
            <!-- Modal Tambah TA -->
            <div class="modal fade" id="modal_ta" tabindex="-1" role="dialog" aria-labelledby="taLabel">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="taLabel"><i class="fa fa-plus-circle"></i> Tahun Ajaran Baru</h4>
                  </div>
                  <div class="modal-body">
                    <form action="ta_act.php" method="post" autocomplete="off">
                      <?= epoin_csrf_field() ?>
                      <div class="form-group">
                        <label>Nama Tahun Ajaran</label>
                        <input type="text" class="form-control" name="nama" required placeholder="Misal: 2024/2025">
                      </div>
                      <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status" required>
                          <option value="1">Aktif / Sedang Berjalan</option>
                          <option value="0">Selesai / Telah Berlalu</option>
                        </select>
                      </div>
                      <div class="form-group" style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius:10px;">Batal</button>
                        <button type="submit" class="btn btn-grad btn-sm"><i class="fa fa-save"></i> Simpan</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-eps nowrap" id="table-datatable" style="width:100%; border-color:var(--line);">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>TAHUN AJARAN</th>
                    <th>STATUS</th>
                    <th width="12%">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT * FROM ta ORDER BY ta_status DESC, ta_nama DESC");
                  while($d = mysqli_fetch_array($data)){
                    ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo htmlspecialchars($d['ta_nama']); ?></td>
                      <td>
                        <?php
                          if($d['ta_status'] == "1"){
                            echo "<span class='badge-aktif'>Aktif / Sedang Berjalan</span>";
                          }else{
                            echo "<span class='badge-selesai'>Selesai / Telah Berlalu</span>";
                          }
                        ?>
                      </td>
                      <td>
                        <div class="btn-group" role="group" aria-label="opsi">
                          <a class="btn btn-warning btn-sm" title="Edit" href="ta_edit.php?id=<?php echo (int)$d['ta_id']; ?>">
                            <i class="fa fa-cog"></i>
                          </a>
                          <form class="eps-del-form" action="ta_hapus.php" method="post" style="display:inline">
                            <?= epoin_csrf_field() ?>
                            <input type="hidden" name="id" value="<?php echo (int)$d['ta_id']; ?>">
                            <button type="button" class="btn btn-danger btn-sm btn-del-confirm"
                                    data-nama="<?php echo epoin_h($d['ta_nama']); ?>"
                                    title="Hapus Tahun Ajaran">
                              <i class="fa fa-trash"></i>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>

          </div>

        </div>
      </section>
    </div>
  </section>

</div>

<script>
$(function(){
  // DataTables setup – full-width & konsisten
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  var $tbl = $('#table-datatable');
  if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
    try { $tbl.DataTable().destroy(); } catch(e){}
    $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
  }
  if ($.fn.DataTable) {
    var t = $tbl.DataTable({
      destroy: true,
      responsive: true,
      autoWidth: false,
      order: [[2,'desc'],[1,'desc']], // Status aktif dulu, lalu TA terbaru
      columnDefs: [{ targets:[0,3], orderable:false }],
      pageLength: 10,
      lengthMenu: [[10,25,50,-1],[10,25,50,"Semua"]],
      dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" + "rt" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
      language: {
        search: "Cari:",
        lengthMenu: "Tampilkan _MENU_ data",
        info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
        infoEmpty: "Tidak ada data",
        zeroRecords: "Tidak ditemukan data yang cocok",
        infoFiltered: "(difilter dari total _MAX_ data)",
        paginate: { first:"Pertama", last:"Terakhir", next:"Berikutnya", previous:"Sebelumnya" }
      }
    });
    t.on('order.dt search.dt', function () {
      var i = 1;
      t.column(0, { search:'applied', order:'applied' }).nodes().each(function (cell) { cell.innerHTML = i++; });
    }).draw();
  }
});
</script>

<?php include 'footer.php'; ?>
