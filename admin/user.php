<?php include 'header.php'; ?>

<style>
/* ====== EPS polish — Manajemen Pengguna ====== */
:root{
  --blue-50:#f0f6ff; --blue-100:#e3efff; --blue-200:#cfe3ff; --blue-300:#b9d6ff;
  --blue-400:#8fbaff; --blue-500:#4f9cf9; --blue-600:#2d6cdf; --blue-700:#1f5ac8;
  --ink-900:#0b1220; --ink-800:#1e293b; --ink-700:#334155; --line:#dbe5ff;
  --bg-page:linear-gradient(180deg,#f8fbff 0%, #f3f7ff 100%);
  --bg-card:#fff; --bg-row:#f8fbff; --bg-hover:#eef4ff;
  --radius-lg:16px; --radius-md:12px; --radius-pill:999px;
  --shadow-lg:0 10px 30px rgba(45,108,223,.12);
  --grad-primary:linear-gradient(90deg, var(--blue-600), var(--blue-500));
  --fs-xs:clamp(11px,.85vw,12px); --fs-2xl:clamp(22px,2.6vw,28px);
}
@keyframes textFade{ from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.content-wrapper{ background:var(--bg-page); }

@media (min-width:1200px){ .content .row .col-lg-12{ float:none; width:100%; } }
@media (min-width:992px){ .content{ padding-left:15px; padding-right:15px; } }

/* Page title */
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

/* Box */
.box{ border:0;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);overflow:hidden;position:relative;background:var(--bg-card); }
.box:before{ content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--blue-600),var(--blue-400),var(--blue-600));opacity:.9; }
.box-header{ background:var(--bg-card);border-bottom:1px solid var(--line);padding:14px 18px; }
.box-title{ display:flex;align-items:center;gap:8px;font-weight:800;color:var(--ink-900); }
.box-title i{ color:var(--blue-600); }

/* Button */
.btn-grad{
  background:var(--grad-primary); color:#fff; border:0; border-radius:var(--radius-md);
  padding:9px 12px; font-weight:700; box-shadow:0 8px 20px rgba(45,108,223,.25);
  transition:transform .15s ease, filter .2s ease;
}
.btn-grad:hover{ filter:brightness(1.06); transform:translateY(-1px); color:#fff; }

/* Table */
.table-eps>thead>tr>th{
  background:linear-gradient(180deg,#f7faff 0%, #f1f6ff 100%);
  color:#1e293b; border-bottom:1px solid var(--line)!important;
}
.table-eps>tbody>tr:nth-child(odd){ background:#fff; }
.table-eps>tbody>tr:nth-child(even){ background:var(--bg-row); }
.table-eps>tbody>tr{ transition:background .18s ease, box-shadow .18s ease; }
.table-eps>tbody>tr:hover{ background:var(--bg-hover)!important; box-shadow:inset 3px 0 0 0 var(--blue-500); }
.table-eps td{ color:#000; }

/* Avatar */
.user-avatar{
  width:32px; height:32px; border-radius:50%; object-fit:cover;
  border:2px solid var(--line); display:block; margin:0 auto;
}

/* Level badges */
.badge-lvl{
  display:inline-block; padding:2px 10px; border-radius:999px;
  font-size:12px; font-weight:700; border:1px solid transparent;
}
.badge-lvl-administrator{ background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
.badge-lvl-superadmin{ background:#fae8ff; color:#86198f; border-color:#f0abfc; }
.badge-lvl-guru{ background:#ede9fe; color:#6d28d9; border-color:#ddd6fe; }
.badge-lvl-siswa{ background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
.badge-lvl-operator{ background:#ffedd5; color:#c2410c; border-color:#fed7aa; }
.badge-lvl-default{ background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
</style>

<div class="content-wrapper">

  <section class="content-header">
    <h1 class="page-title">
      <span class="title-icon"><i class="fa fa-users"></i></span>
      <span>Manajemen Pengguna</span>
      <span class="badge-chip"><i class="fa fa-database"></i> Data Master</span>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Manajemen Pengguna</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-12">
        <div class="box">

          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-list-ul"></i> Daftar Pengguna</h3>
            <div class="pull-right">
              <a href="user_tambah.php" class="btn btn-grad btn-sm">
                <i class="fa fa-plus"></i> &nbsp;Tambah Pengguna Baru
              </a>
            </div>
          </div>

          <div class="box-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover table-eps" id="table-datatable" style="width:100%; border-color:var(--line);">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>NAMA</th>
                    <th>USERNAME</th>
                    <th>LEVEL</th>
                    <th width="80px">FOTO</th>
                    <th width="10%">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no=1;
                  $data = mysqli_query($koneksi,"SELECT * FROM user");
                  while($d = mysqli_fetch_array($data)){
                    $lvl_raw  = strtolower(trim($d['user_level'] ?? ''));
                    $lvl_safe = preg_replace('/[^a-z0-9]/', '', $lvl_raw);
                    $badge_cls = 'badge-lvl badge-lvl-' . ($lvl_safe ?: 'default');
                  ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td><?php echo epoin_h($d['user_nama']); ?></td>
                      <td><?php echo epoin_h($d['user_username']); ?></td>
                      <td>
                        <span class="<?php echo $badge_cls; ?>">
                          <?php echo epoin_h($d['user_level']); ?>
                        </span>
                      </td>
                      <td>
                        <?php if($d['user_foto'] == ""){ ?>
                          <img class="user-avatar" src="../gambar/sistem/user.png" alt="foto">
                        <?php }else{ ?>
                          <img class="user-avatar" src="../gambar/user/<?php echo epoin_h($d['user_foto']); ?>" alt="foto">
                        <?php } ?>
                      </td>
                      <td>
                        <a class="btn btn-warning btn-sm" href="user_edit.php?id=<?php echo (int)$d['user_id']; ?>" title="Edit"><i class="fa fa-cog"></i></a>
                        <?php if($d['user_id'] != 1){ ?>
                          <form class="eps-del-form" action="user_hapus.php" method="post" style="display:inline">
                            <?= epoin_csrf_field() ?>
                            <input type="hidden" name="id" value="<?php echo (int)$d['user_id']; ?>">
                            <button type="button" class="btn btn-danger btn-sm btn-del-confirm"
                                    data-nama="<?php echo epoin_h($d['user_nama'] ?? ''); ?>"
                                    title="Hapus pengguna">
                              <i class="fa fa-trash"></i>
                            </button>
                          </form>
                        <?php } ?>
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

<?php include 'footer.php'; ?>

<script>
$(function(){
  if ($.fn.dataTable && $.fn.dataTable.ext) { $.fn.dataTable.ext.errMode = 'console'; }
  var $tbl = $('#table-datatable');
  if ($.fn.DataTable && $.fn.DataTable.isDataTable($tbl)) {
    try { $tbl.DataTable().destroy(); } catch(e){}
    $tbl.find('thead th').removeClass('sorting sorting_asc sorting_desc sorting_disabled');
  }
  if ($.fn.DataTable) {
    $tbl.DataTable({
      destroy: true,
      autoWidth: false,
      order: [[1,'asc']],
      columnDefs: [{ targets:[0,4,5], orderable:false }],
      pageLength: 25,
      lengthMenu: [[10,25,50,-1],[10,25,50,'Semua']],
      dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" + "rt" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
      language: {
        search: 'Cari:',
        lengthMenu: 'Tampil _MENU_ data',
        info: 'Menampilkan _START_–_END_ dari _TOTAL_ pengguna',
        infoEmpty: 'Tidak ada data',
        zeroRecords: 'Tidak ditemukan pengguna yang cocok',
        infoFiltered: '(difilter dari total _MAX_ data)',
        paginate: { first:'Pertama', last:'Terakhir', next:'Berikutnya', previous:'Sebelumnya' }
      }
    });
  }
});
</script>
