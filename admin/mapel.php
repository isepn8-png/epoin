<?php
// ====== ACTION HANDLER (harus di atas output HTML agar bisa redirect) ======
include '../koneksi.php';
session_start();
require_once __DIR__ . '/../includes/epoin_security.php';
if(!isset($_SESSION['level']) || $_SESSION['level'] !== "administrator"){
  header("location:../admin.php?alert=belum_login");
  exit;
}

/* === (Opsional saat debug) aktifkan baris berikut ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

function _post($key, $default=''){ return isset($_POST[$key]) ? trim($_POST[$key]) : $default; }
function _int($v){ return (int)$v; }

// Tambah / Edit via POST, Hapus via GET (konfirmasi di client-side)
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Tambah mapel
if ($action === 'tambah') {
  $kode = strtoupper(_post('kode'));
  $nama = _post('nama');

  if($kode==='' || $nama===''){
    header("location: mapel.php?alert=invalid");
    exit;
  }
  if(strlen($kode) > 20)  $kode = substr($kode, 0, 20);
  if(strlen($nama) > 100) $nama = substr($nama, 0, 100);

  $kode_esc = mysqli_real_escape_string($koneksi,$kode);
  $nama_esc = mysqli_real_escape_string($koneksi,$nama);

  // Cek duplikasi KODE (unik)
  $dup = mysqli_query($koneksi,"SELECT 1 FROM mapel WHERE UPPER(mapel_kode)='$kode_esc' LIMIT 1");
  if($dup && mysqli_num_rows($dup)>0){
    header("location: mapel.php?alert=duplikat");
    exit;
  }

  $ok = mysqli_query($koneksi,"INSERT INTO mapel (mapel_kode,mapel_nama) VALUES ('$kode_esc','$nama_esc')");
  header("location: mapel.php?alert=".($ok?'add_ok':'add_fail'));
  exit;
}

// Edit mapel
if ($action === 'edit') {
  $id   = _int(_post('id'));
  $kode = strtoupper(_post('kode'));
  $nama = _post('nama');

  if($id<=0 || $kode==='' || $nama===''){
    header("location: mapel.php?alert=invalid");
    exit;
  }
  if(strlen($kode) > 20)  $kode = substr($kode, 0, 20);
  if(strlen($nama) > 100) $nama = substr($nama, 0, 100);

  $kode_esc = mysqli_real_escape_string($koneksi,$kode);
  $nama_esc = mysqli_real_escape_string($koneksi,$nama);

  // Cek duplikasi KODE selain dirinya sendiri
  $dup = mysqli_query($koneksi,"SELECT 1 FROM mapel WHERE UPPER(mapel_kode)='$kode_esc' AND mapel_id<>$id LIMIT 1");
  if($dup && mysqli_num_rows($dup)>0){
    header("location: mapel.php?alert=duplikat");
    exit;
  }

  $ok = mysqli_query($koneksi,"UPDATE mapel SET mapel_kode='$kode_esc', mapel_nama='$nama_esc' WHERE mapel_id=$id");
  header("location: mapel.php?alert=".($ok?'edit_ok':'edit_fail'));
  exit;
}

// Hapus mapel
if ($action === 'hapus') {
  if(($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !epoin_csrf_validate()){
    header("location: mapel.php?alert=invalid");
    exit;
  }
  $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if($id<=0){
    header("location: mapel.php?alert=invalid");
    exit;
  }

  // Tolak hapus jika sedang dipakai penugasan
  $in_use = mysqli_query($koneksi,"SELECT 1 FROM pengampu_mapel WHERE mapel_id=$id LIMIT 1");
  if($in_use && mysqli_num_rows($in_use)>0){
    header("location: mapel.php?alert=relasi");
    exit;
  }

  $ok = mysqli_query($koneksi,"DELETE FROM mapel WHERE mapel_id=$id");
  header("location: mapel.php?alert=".($ok?'del_ok':'del_fail'));
  exit;
}
?>
<?php include 'header.php'; ?>

<?php
// Hitung total data untuk badge
$total_mapel = 0;
$_qCount = mysqli_query($koneksi,"SELECT COUNT(*) AS c FROM mapel");
if($_qCount && ($__r = mysqli_fetch_assoc($_qCount))){ $total_mapel = (int)$__r['c']; }
?>

<style>
  :root{
    --blue-50:#f0f6ff; --blue-100:#e3efff; --blue-200:#cfe3ff; --blue-300:#b9d6ff;
    --blue-400:#8fbaff; --blue-500:#4f9cf9; --blue-600:#2d6cdf; --blue-700:#1f5ac8;
    --ink-900:#0b1220; --ink-800:#1e293b; --ink-700:#334155; --line:#dbe5ff;
    --bg-page:linear-gradient(180deg, #f8fbff 0%, #f3f7ff 100%);
    --bg-card:#fff; --bg-row:#f8fbff; --bg-hover:#eef4ff; --shadow:0 10px 30px rgba(45,108,223,.12);
    --radius-lg:16px; --btn-grad:linear-gradient(90deg, var(--blue-600), var(--blue-500));
    --btn-grad-hover:linear-gradient(90deg, var(--blue-700), var(--blue-600));
    --btn-edit:#f59e0b; --btn-del:#ef4444;
  }
  html, body, .content-wrapper{ font-family: Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; background:var(--bg-page); color:var(--ink-800); }

  @keyframes textFade{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }

  .content-header{ margin-bottom:8px; }
  .content-header h1{ display:flex; align-items:center; gap:12px; color:var(--ink-900); font-weight:800; letter-spacing:.2px; animation:textFade .6s ease-out both; }
  .title-icon{ width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:var(--blue-100);color:var(--blue-600);box-shadow:inset 0 0 0 1px var(--line); }
  .title-badge{ background:var(--blue-50);border:1px solid var(--line);color:var(--ink-700);border-radius:999px;padding:4px 10px;display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:clamp(9px,2.2vw,11px);white-space:nowrap; }
  .title-badge i{ color:var(--blue-600); }
  .breadcrumb>li+li:before{content:"› ";color:var(--ink-700)}

  .box{ border:0;border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;position:relative;background:var(--bg-card); }
  .box:before{ content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--blue-600),var(--blue-400),var(--blue-600));opacity:.9; }
  .box-header{ background:var(--bg-card);border-bottom:1px solid var(--line);padding:14px 18px; }
  .box-header .box-title{ display:flex;align-items:center;gap:8px;font-weight:800;color:var(--ink-900); }
  .box-header .box-title i{ color:var(--blue-600); }

  .pill-total{ display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:var(--blue-50);color:var(--ink-700);border:1px solid var(--line);font-weight:700;font-size:12px;white-space:nowrap; }
  .pill-total i{ color:var(--blue-600) }

  .btn-add{ background:var(--btn-grad);color:#fff;border:0;border-radius:12px;padding:10px 14px;display:inline-flex;align-items:center;gap:8px;font-weight:700;box-shadow:0 8px 20px rgba(45,108,223,.25);transition:transform .15s ease,filter .2s ease; }
  .btn-add:hover{ filter:brightness(1.06); transform:translateY(-1px); }

  .table-compact>thead>tr>th,.table-compact>tbody>tr>td{ padding:10px 12px!important;vertical-align:middle;white-space:nowrap; }
  #table-datatable{ table-layout:auto;border-color:var(--line);background:var(--bg-card); }
  #table-datatable thead th{ background:linear-gradient(180deg,#f7faff 0%,#f1f6ff 100%); color:var(--ink-800); border-bottom:1px solid var(--line)!important; }
  #table-datatable tbody tr:nth-child(odd){ background:#fff }
  #table-datatable tbody tr:nth-child(even){ background:var(--bg-row) }
  #table-datatable tbody tr{ transition:background .18s ease,box-shadow .18s ease }
  #table-datatable tbody tr:hover{ background:var(--bg-hover)!important; box-shadow:inset 3px 0 0 0 var(--blue-500); cursor:pointer; }
  #table-datatable tbody td{ color:#000 } /* isi tabel tetap hitam */

  /* ====== Opsi: Combo pill menyatu & selalu satu baris ====== */
  td.col-opsi{ white-space:nowrap !important; }
  .btn-combo{
    display:inline-flex; align-items:stretch; flex-wrap:nowrap;
    border-radius:9999px; overflow:hidden; box-shadow:0 6px 14px rgba(0,0,0,.08);
    border:0; background:transparent;
  }
  .btn-combo .btn{
    float:none; margin:0; border:0; border-radius:0; line-height:1; 
    padding:6px 10px; display:inline-flex; align-items:center; justify-content:center;
  }
  .btn-combo .btn:first-child{ border-radius:9999px 0 0 9999px; }
  .btn-combo .btn:last-child{ border-radius:0 9999px 9999px 0; }
  /* warna aksi */
  .btn-edit{ background:var(--btn-edit); color:#fff; }
  .btn-del { background:var(--btn-del);  color:#fff; }

  .btn-combo .btn:hover{ filter:brightness(1.06); }
  .btn-combo i{ pointer-events:none; } /* biar klik area icon tidak ganggu */

  .modal-content{ border-radius:16px;overflow:hidden;border:0;box-shadow:var(--shadow) }
  .modal-header{ background:linear-gradient(180deg,var(--blue-50),#fff);border-bottom:1px solid var(--line)!important;color:var(--ink-900) }
  .modal-title i{ color:var(--blue-600) }
  .modal-body .form-control{ border-radius:12px;border:1px solid var(--line);box-shadow:none }
  .modal-body .form-control:focus{ border-color:var(--blue-500);box-shadow:0 0 0 3px rgba(79,156,249,.15) }
  .modal-footer{ border-top:1px solid var(--line)!important }
  .btn-primary{ background:var(--btn-grad);border:0;border-radius:12px }
  .btn-primary:hover{ background:var(--btn-grad-hover) }

  .dataTables_wrapper .dataTables_filter label{ font-weight:700;color:var(--ink-700) }
  .dataTables_wrapper .dataTables_filter input{ border:1px solid var(--line)!important;background:#fff;border-radius:999px!important;padding:8px 12px!important;outline:none }
  .dataTables_wrapper .dataTables_length select{ border:1px solid var(--line)!important;border-radius:10px;padding:6px 10px;background:#fff }
  .dataTables_wrapper .dataTables_paginate .paginate_button{ border-radius:10px!important;border:0!important;background:#fff!important;color:var(--ink-800)!important;box-shadow:inset 0 0 0 1px var(--line);margin:0 2px!important }
  .dataTables_wrapper .dataTables_paginate .paginate_button.current{ background:var(--blue-600)!important;color:#fff!important;box-shadow:none }
  .dataTables_wrapper .dataTables_info{ color:var(--ink-700) }

  @media (max-width:480px){
    .pill-total{margin-top:8px;display:block}
    .box-header{padding:12px}
    /* Tabel akan scroll horizontal jika sempit; tombol tetap satu baris */
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>
      <span class="title-icon"><i class="fa fa-book"></i></span>
      Mata Pelajaran
      <small class="title-badge"><i class="fa fa-th-large"></i> Data Master</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-home"></i> Home</a></li>
      <li class="active">Mata Pelajaran</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-12">
        <div class="box">
          <div class="box-header">
            <h3 class="box-title"><i class="fa fa-list-ul"></i> Daftar Mata Pelajaran</h3>
            <div class="pull-right" style="display:flex; gap:10px; align-items:center;">
              <span class="pill-total" title="Total Mapel">
                <i class="fa fa-database"></i> Total: <?php echo (int)$total_mapel; ?>
              </span>
              <button type="button" class="btn btn-add" data-toggle="modal" data-target="#modal_tambah">
                <i class="fa fa-plus"></i> Tambah Mapel
              </button>
            </div>
          </div>

          <div class="box-body">
            <?php
              $alert = isset($_GET['alert']) ? $_GET['alert'] : '';
              if($alert){
                $msg = ''; $cls = 'info';
                if($alert==='add_ok'){ $msg='Mapel berhasil ditambahkan.'; $cls='success'; }
                elseif($alert==='add_fail'){ $msg='Mapel gagal ditambahkan.'; $cls='danger'; }
                elseif($alert==='edit_ok'){ $msg='Mapel berhasil diperbarui.'; $cls='success'; }
                elseif($alert==='edit_fail'){ $msg='Mapel gagal diperbarui.'; $cls='danger'; }
                elseif($alert==='del_ok'){ $msg='Mapel berhasil dihapus.'; $cls='success'; }
                elseif($alert==='del_fail'){ $msg='Mapel gagal dihapus.'; $cls='danger'; }
                elseif($alert==='duplikat'){ $msg='Kode mapel sudah digunakan.'; $cls='warning'; }
                elseif($alert==='relasi'){ $msg='Tidak bisa menghapus: mapel sedang dipakai penugasan.'; $cls='warning'; }
                elseif($alert==='invalid'){ $msg='Data tidak lengkap atau tidak valid.'; $cls='warning'; }
                if($msg){
                  echo '<div class="alert alert-'.$cls.' alert-dismissible" style="border-radius:12px;">
                          <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                          <i class="fa '.($cls==='success'?'fa-check-circle':($cls==='danger'?'fa-exclamation-triangle':'fa-info-circle')).'"></i> '
                          .htmlspecialchars($msg).'</div>';
                }
              }
            ?>

            <div class="row" style="margin-bottom:10px;">
              <div class="col-sm-6">
                <em class="table-hint"><i class="fa fa-info-circle" style="color:var(--blue-600)"></i> Gunakan kolom pencarian untuk menemukan data lebih cepat.</em>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-hover table-compact nowrap" id="table-datatable" style="width:100%; border-color:var(--line);">
                <thead>
                  <tr>
                    <th width="1%">NO</th>
                    <th>KODE</th>
                    <th>NAMA MAPEL</th>
                    <th width="12%">OPSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no=1;
                  $res = mysqli_query($koneksi,"SELECT * FROM mapel ORDER BY mapel_nama ASC, mapel_kode ASC");
                  while($r = mysqli_fetch_assoc($res)){ ?>
                    <tr>
                      <td><?php echo $no++; ?></td>
                      <td>
                        <span class="label" style="background:var(--blue-100); color:var(--ink-800); border:1px solid var(--line); border-radius:8px; padding:2px 6px; margin-right:6px;">
                          <i class="fa fa-tag" style="color:var(--blue-600)"></i>
                        </span>
                        <?php echo htmlspecialchars($r['mapel_kode']); ?>
                      </td>
                      <td><?php echo htmlspecialchars($r['mapel_nama']); ?></td>
                      <td class="col-opsi">
                        <div class="btn-combo" role="group" aria-label="Opsi">
                          <button
                            type="button"
                            class="btn btn-edit btn-sm"
                            data-toggle="tooltip" title="Edit mapel"
                            data-id="<?php echo (int)$r['mapel_id']; ?>"
                            data-kode="<?php echo htmlspecialchars($r['mapel_kode']); ?>"
                            data-nama="<?php echo htmlspecialchars($r['mapel_nama']); ?>">
                            <i class="fa fa-pencil"></i>
                          </button>
                          <form method="post" action="mapel.php" class="eps-del-form" style="display:inline">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?php echo (int)$r['mapel_id']; ?>">
                            <?php echo epoin_csrf_field(); ?>
                            <button type="button"
                              class="btn btn-del btn-sm btn-icon btn-del-confirm"
                              data-toggle="tooltip" title="Hapus mapel"
                              data-nama="<?php echo htmlspecialchars($r['mapel_nama'], ENT_QUOTES, 'UTF-8'); ?>">
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

<!-- ===== Modal: Tambah ===== -->
<div class="modal fade" id="modal_tambah" tabindex="-1" role="dialog" aria-labelledby="tambahLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="mapel.php" method="post" autocomplete="off">
        <input type="hidden" name="action" value="tambah">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title" id="tambahLabel"><i class="fa fa-plus-circle"></i> Tambah Mapel</h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Kode Mapel</label>
            <input type="text" class="form-control" name="kode" required placeholder="Contoh: MAT">
            <small class="text-muted">Maks 20 karakter. Contoh: MAT, IPA, IPS…</small>
          </div>
          <div class="form-group">
            <label>Nama Mapel</label>
            <input type="text" class="form-control" name="nama" required placeholder="Contoh: Matematika">
            <small class="text-muted">Maks 100 karakter.</small>
          </div>
        </div>
        <div class="modal-footer" style="display:flex; gap:8px; justify-content:flex-end;">
          <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius:10px;">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===== Modal: Edit ===== -->
<div class="modal fade" id="modal_edit" tabindex="-1" role="dialog" aria-labelledby="editLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="mapel.php" method="post" autocomplete="off" id="formEdit">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title" id="editLabel"><i class="fa fa-pencil-square-o"></i> Edit Mapel</h4>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Kode Mapel</label>
            <input type="text" class="form-control" name="kode" id="edit_kode" required>
            <small class="text-muted">Maks 20 karakter.</small>
          </div>
          <div class="form-group">
            <label>Nama Mapel</label>
            <input type="text" class="form-control" name="nama" id="edit_nama" required>
            <small class="text-muted">Maks 100 karakter.</small>
          </div>
        </div>
        <div class="modal-footer" style="display:flex; gap:8px; justify-content:flex-end;">
          <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="border-radius:10px;">Batal</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Inisialisasi DataTables + handler Edit -->
<script>
  $(function () {
    $('[data-toggle="tooltip"]').tooltip();

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
        order: [[2, 'asc']],
        columnDefs: [{ targets: [0,3], orderable: false }],
        pageLength: 10,
        lengthMenu: [[10,25,50,-1],[10,25,50,"Semua"]],
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" + "rt" + "<'row'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
          search: "Cari:",
          lengthMenu: "Tampil _MENU_ data",
          info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
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

    $(document).on('click', '.btn-edit', function(){
      var id   = this.getAttribute('data-id');
      var kode = this.getAttribute('data-kode');
      var nama = this.getAttribute('data-nama');
      $('#edit_id').val(id);
      $('#edit_kode').val(kode);
      $('#edit_nama').val(nama);
      $('#modal_edit').modal('show');
      setTimeout(function(){ $('#edit_kode').focus(); }, 300);
    });
  });
</script>

<?php include 'footer.php'; ?>
