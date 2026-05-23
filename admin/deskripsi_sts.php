<?php
// admin/deskripsi_sts.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/helpers/deskripsi_sts_helper.php';
// Jika proyek Anda punya sistem auth/role, panggil di sini (mis: require_login();)

// Ambil pilihan kelas & mapel untuk filter
$kelas = db()->query("SELECT kelas_id, kelas_nama FROM kelas ORDER BY tingkat, kelas_nama");
$mapel = db()->query("SELECT mapel_id, mapel_nama FROM mapel ORDER BY mapel_nama");
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Generate Deskripsi STS</title>
  <link href="/assets/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/datatables.min.css" rel="stylesheet">
  <link href="/assets/fontawesome.min.css" rel="stylesheet">
  <style>
    .page-title {font-weight:700}
    .badge-status {font-size:.75rem}
    textarea.desc {min-width:420px; min-height:72px}
    .sticky-toolbar{position:sticky;top:0;z-index:9;background:var(--bs-body-bg);padding:8px 0}
  </style>
</head>
<body class="container-fluid py-3">
  <div class="d-flex align-items-center gap-3 mb-3">
    <i class="fa-solid fa-clipboard-list fa-lg text-primary"></i>
    <h1 class="page-title m-0">Generate Deskripsi STS</h1>
    <span class="badge text-bg-info badge-status">Admin</span>
  </div>

  <div class="card mb-3 shadow-sm">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Pilih Kelas</label>
          <select id="filterKelas" class="form-select">
            <option value="">— pilih —</option>
            <?php while($r=$kelas->fetch_assoc()): ?>
              <option value="<?= (int)$r['kelas_id'] ?>"><?= htmlspecialchars($r['kelas_nama']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Pilih Mapel</label>
          <select id="filterMapel" class="form-select">
            <option value="">— pilih —</option>
            <?php while($m=$mapel->fetch_assoc()): ?>
              <option value="<?= (int)$m['mapel_id'] ?>"><?= htmlspecialchars($m['mapel_nama']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Semester</label>
          <select id="filterSemester" class="form-select">
            <option value="1">Ganjil</option>
            <option value="2">Genap</option>
          </select>
        </div>
        <div class="col-md-4 text-end">
          <button id="btnTampil" class="btn btn-primary"><i class="fa fa-eye"></i> Tampilkan</button>
          <button id="btnGenerateAll" class="btn btn-success" disabled><i class="fa fa-wand-magic-sparkles"></i> Generate (Semua)</button>
          <button id="btnSimpanAll" class="btn btn-warning" disabled><i class="fa fa-floppy-disk"></i> Simpan</button>
        </div>
      </div>
    </div>
  </div>

  <div class="sticky-toolbar mb-2 d-none" id="toolbarInfo">
    <span class="badge text-bg-secondary">Klik <i class="fa fa-wand-magic-sparkles"></i> untuk regenerasi per siswa. Kolom kuning = sudah disunting manual.</span>
  </div>

  <div class="card shadow-sm">
    <div class="card-header fw-semibold">Data Deskripsi Ketercapaian Pembelajaran</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table id="tblData" class="table table-striped table-bordered m-0 align-middle" style="width:100%">
          <thead class="table-primary">
            <tr>
              <th style="width:48px">No</th>
              <th>Nama Siswa</th>
              <th style="width:130px">NISN</th>
              <th>Deskripsi</th>
              <th style="width:120px">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>

<script src="/assets/jquery.min.js"></script>
<script src="/assets/bootstrap.bundle.min.js"></script>
<script src="/assets/datatables.min.js"></script>
<script>
let DT, gContext = { kelas_id:null, mapel_id:null, semester:1, pts_set_id:null };

function enableActions(en) {
  $('#btnGenerateAll').prop('disabled', !en);
  $('#btnSimpanAll').prop('disabled', !en);
}

$('#btnTampil').on('click', function(){
  gContext.kelas_id = $('#filterKelas').val();
  gContext.mapel_id = $('#filterMapel').val();
  gContext.semester = $('#filterSemester').val();
  if(!gContext.kelas_id || !gContext.mapel_id){
    alert('Pilih kelas & mapel dulu.');
    return;
  }

  if (DT) { DT.destroy(); $('#tblData tbody').empty(); }
  enableActions(false); $('#toolbarInfo').addClass('d-none');

  $.get('ajax/sts_deskripsi_list.php', gContext, function(res){
    if(!res.ok){ alert(res.msg||'Gagal load data'); return; }
    gContext.pts_set_id = res.pts_set_id;

    res.rows.forEach((r,idx)=>{
      let manualCls = r.source_enum==='manual' ? 'bg-warning-subtle' : '';
      $('#tblData tbody').append(`
        <tr data-pts-id="${r.pts_id}">
          <td class="text-center">${idx+1}</td>
          <td>${r.nama_siswa}</td>
          <td>${r.nisn||''}</td>
          <td>
            <textarea class="form-control desc ${manualCls}" rows="3">${r.deskripsi||''}</textarea>
            <div class="small mt-1">
              <span class="badge text-bg-info me-1">Nilai: ${r.nilai??'-'}</span>
              <span class="badge text-bg-light border">Status: ${r.source_enum}</span>
            </div>
          </td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-success btn-gen" title="Generate"><i class="fa fa-wand-magic-sparkles"></i></button>
            <button class="btn btn-sm btn-outline-secondary btn-copy" title="Copy"><i class="fa fa-copy"></i></button>
          </td>
        </tr>`);
    });

    DT = new $.fn.dataTable.Api($('#tblData').DataTable({
      pageLength: 25,
      order: [[1,'asc']],
      language: { url: '/assets/i18n/id.json' }
    }));

    enableActions(true); $('#toolbarInfo').removeClass('d-none');
  }, 'json');
});

// Generate satu baris
$('#tblData').on('click', '.btn-gen', function(){
  const tr = $(this).closest('tr');
  const pts_id = tr.data('pts-id');
  $.post('ajax/sts_deskripsi_generate.php', { pts_id }, function(res){
    if(!res.ok){ alert(res.msg||'Gagal generate'); return; }
    tr.find('textarea.desc').val(res.deskripsi).removeClass('bg-warning-subtle');
  }, 'json');
});

// Copy
$('#tblData').on('click', '.btn-copy', function(){
  const ta = $(this).closest('tr').find('textarea.desc')[0];
  ta.select(); document.execCommand('copy');
});

// Generate semua
$('#btnGenerateAll').on('click', function(){
  if(!confirm('Generate ulang untuk semua siswa pada tampilan ini?')) return;
  const ids = $('#tblData tbody tr').map(function(){ return $(this).data('pts-id'); }).get();
  $.post('ajax/sts_deskripsi_generate.php', { pts_ids: JSON.stringify(ids) }, function(res){
    if(!res.ok){ alert(res.msg||'Gagal generate massal'); return; }
    res.items.forEach(it=>{
      const tr = $('#tblData tbody tr[data-pts-id="'+it.pts_id+'"]');
      tr.find('textarea.desc').val(it.deskripsi).removeClass('bg-warning-subtle');
    });
  }, 'json');
});

// Simpan semua (bulk)
$('#btnSimpanAll').on('click', function(){
  const payload = [];
  $('#tblData tbody tr').each(function(){
    payload.push({
      pts_id: $(this).data('pts-id'),
      deskripsi: $(this).find('textarea.desc').val()
    });
  });
  $.ajax({
    url: 'ajax/sts_deskripsi_save.php',
    method: 'POST',
    data: { pts_set_id: gContext.pts_set_id, items: JSON.stringify(payload) },
    dataType: 'json',
    success: function(res){
      if(!res.ok){ alert(res.msg||'Gagal menyimpan'); return; }
      alert('Deskripsi berhasil disimpan!');
    }
  });
});
</script>
</body>
</html>