<?php
// --- helper include koneksi ---
function include_first(array $paths) {
  foreach ($paths as $p) {
    if (file_exists($p)) { require_once $p; return true; }
  }
  return false;
}
$ok = include_first([
  __DIR__.'/../config/koneksi.php',
  __DIR__.'/../config/db.php',
  __DIR__.'/config/koneksi.php',
  __DIR__.'/config/db.php',
  __DIR__.'/../koneksi.php',
]);
require_once __DIR__.'/includes/deskripsi_helper.php';

if (!$ok && !isset($pdo) && !isset($conn) && !isset($koneksi)) {
  die('<b>Koneksi DB tidak ditemukan.</b> Letakkan file koneksi di /epoin/config/koneksi.php atau /epoin/config/db.php');
}
if (isset($koneksi) && !isset($conn)) $conn = $koneksi; // alias

// --- wrapper query universal ---
function q_rows($sql, $params = []) {
  global $pdo, $conn;
  if (isset($pdo)) {
    $st = $pdo->prepare($sql); $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }
  $st = $conn->prepare($sql);
  if ($params) { $st->bind_param(str_repeat('s', count($params)), ...array_values($params)); }
  $st->execute(); $res = $st->get_result();
  return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function q_one($sql, $params = []) { $r = q_rows($sql, $params); return $r[0] ?? null; }

// --- data filter ---
$kelas  = q_rows('SELECT kelas_id, kelas_nama FROM kelas ORDER BY tingkat, kelas_nama');
$mapel  = q_rows('SELECT mapel_id, mapel_nama FROM mapel ORDER BY mapel_nama');

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
$mapel_id = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;

$pts_set_id = 0; $pts_set = null;
if ($kelas_id && $mapel_id) {
  $pts_set = q_one('SELECT * FROM nilai_pts_set WHERE kelas_id=? AND mapel_id=? ORDER BY updated_at DESC, pts_set_id DESC LIMIT 1', [$kelas_id, $mapel_id]);
  if ($pts_set) $pts_set_id = (int)$pts_set['pts_set_id'];
}

$rows = [];
if ($pts_set_id) {
  $rows = q_rows("
    SELECT s.siswa_id, s.siswa_nama, s.nisn, s.nis,
           np.pts_id, v.deskripsi_optimal, v.deskripsi_perlu,
           rpd.deskripsi_final, rpd.is_manual, rpd.is_locked
    FROM nilai_pts np
    JOIN siswa s ON s.siswa_id = np.siswa_id
    LEFT JOIN v_rapor_pts_deskripsi v ON v.pts_id = np.pts_id
    LEFT JOIN rapor_pts_deskripsi rpd ON rpd.pts_id = np.pts_id
    WHERE np.pts_set_id = ?
    ORDER BY s.siswa_nama ASC", [$pts_set_id]);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Generate Deskripsi Rapor STS/PTS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <style>
    body { background:#f6f8fb }
    .page-title{font-weight:700}
    .toolbar{position:sticky;top:0;z-index:100;background:#f6f8fb;padding:.75rem 0}
    .desc-cell{white-space:pre-line}
    .badge-opt{background:#16a34a}
    .badge-per{background:#ef4444}
  </style>
</head>
<body>
<div class="container-fluid py-3">
  <div class="d-flex align-items-center mb-3">
    <i class="fa-solid fa-note-sticky me-2 text-primary"></i>
    <h1 class="page-title h3 mb-0">Generate Deskripsi Rapor STS/PTS</h1>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-3 align-items-end" method="get">
        <div class="col-md-4">
          <label class="form-label">Pilih Kelas</label>
          <select name="kelas_id" class="form-select" required>
            <option value="">-- pilih --</option>
            <?php foreach($kelas as $k): ?>
              <option value="<?= (int)$k['kelas_id']; ?>" <?= $kelas_id==$k['kelas_id']?'selected':''; ?>>
                <?= htmlspecialchars($k['kelas_nama']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Pilih Mapel</label>
          <select name="mapel_id" class="form-select" required>
            <option value="">-- pilih --</option>
            <?php foreach($mapel as $m): ?>
              <option value="<?= (int)$m['mapel_id']; ?>" <?= $mapel_id==$m['mapel_id']?'selected':''; ?>>
                <?= htmlspecialchars($m['mapel_nama']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass me-1"></i> Tampilkan</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($pts_set_id): ?>
    <div class="toolbar d-flex gap-2">
      <span class="badge rounded-pill text-bg-secondary"><i class="fa-solid fa-layer-group me-1"></i> PTS Set #<?= $pts_set_id; ?></span>
      <span class="badge rounded-pill text-bg-info"><i class="fa-solid fa-chalkboard-user me-1"></i> Kelas: <?= htmlspecialchars($pts_set['kelas_id']); ?></span>
      <span class="badge rounded-pill text-bg-info"><i class="fa-solid fa-book me-1"></i> Mapel: <?= htmlspecialchars($pts_set['mapel_id']); ?></span>
      <button id="btnGenerateAll" class="btn btn-success btn-sm ms-auto"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate Massal</button>
      <button id="btnExport" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-export me-1"></i> Export CSV</button>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table id="tbl" class="table table-striped align-middle">
            <thead class="table-primary">
              <tr>
                <th style="width:44px">No</th>
                <th>Nama Siswa</th>
                <th>NISN</th>
                <th>NIS</th>
                <th>Deskripsi Ketercapaian Pembelajaran</th>
                <th style="width:160px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php $no=1; foreach($rows as $r):
                $final = $r['deskripsi_final'] ?: build_deskripsi_paragraph($r['deskripsi_optimal'] ?? '', $r['deskripsi_perlu'] ?? '');
              ?>
              <tr data-pts-id="<?= (int)$r['pts_id']; ?>">
                <td><?= $no++; ?></td>
                <td><?= htmlspecialchars($r['siswa_nama']); ?></td>
                <td><?= htmlspecialchars($r['nisn']); ?></td>
                <td><?= htmlspecialchars($r['nis']); ?></td>
                <td class="desc-cell">
                  <div class="small mb-1">
                    <?php if (trim((string)$r['deskripsi_optimal'])!==''): ?>
                      <span class="badge badge-opt"><i class="fa-solid fa-circle-check me-1"></i>Optimal</span>
                    <?php endif; ?>
                    <?php if (trim((string)$r['deskripsi_perlu'])!==''): ?>
                      <span class="badge badge-per"><i class="fa-solid fa-triangle-exclamation me-1"></i>Perlu</span>
                    <?php endif; ?>
                    <?php if ((int)$r['is_manual']===1): ?>
                      <span class="badge text-bg-warning"><i class="fa-solid fa-pen"></i> Manual</span>
                    <?php endif; ?>
                  </div>
                  <div class="desc-text"><?= nl2br(htmlspecialchars($final)); ?></div>
                </td>
                <td>
                  <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary btnEdit"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-outline-success btnRegen"><i class="fa-solid fa-rotate"></i></button>
                    <button class="btn btn-outline-primary btnCopy"><i class="fa-solid fa-copy"></i></button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php elseif ($kelas_id || $mapel_id): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-circle-info me-1"></i> Belum ada <b>PTS Set</b> untuk kombinasi tersebut.</div>
  <?php endif; ?>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="mdlEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-pen me-1"></i>Edit Deskripsi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <textarea id="txtEdit" class="form-control" rows="8"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button id="btnSave" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  const ptsSetId = <?= (int)$pts_set_id ?>;
  $('#tbl').DataTable({ pageLength: 25, order:[[1,'asc']] });

  // Generate massal
  $('#btnGenerateAll').on('click', function(){
    if(!ptsSetId) return;
    if(!confirm('Generate deskripsi untuk semua siswa pada PTS Set #'+ptsSetId+'?')) return;
    const btn = $(this).prop('disabled', true).text('Generating ...');
    $.post('ajax/deskripsi_pts_generate.php', { pts_set_id: ptsSetId }, function(res){
      alert(res.message || 'Berhasil.');
      location.reload();
    }, 'json').fail(function(xhr){ alert('Gagal: ' + (xhr.responseText || xhr.statusText)); })
      .always(()=>btn.prop('disabled', false).html('<i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate Massal'));
  });

  // Regenerate per siswa
  let curPtsId = 0; let editModal = new bootstrap.Modal(document.getElementById('mdlEdit'));
  $('#tbl').on('click','.btnRegen', function(){
    const tr = $(this).closest('tr'); const ptsId = tr.data('pts-id');
    $.post('ajax/deskripsi_pts_generate.php', { pts_id: ptsId }, function(res){
      tr.find('.desc-text').text(res.deskripsi_final);
      tr.find('.badge.text-bg-warning').remove();
    }, 'json').fail(function(xhr){ alert('Gagal: ' + (xhr.responseText || xhr.statusText)); });
  });

  // Edit manual
  $('#tbl').on('click','.btnEdit', function(){
    const tr = $(this).closest('tr'); curPtsId = tr.data('pts-id');
    $('#txtEdit').val(tr.find('.desc-text').text()); editModal.show();
  });
  $('#btnSave').on('click', function(){
    const text = $('#txtEdit').val();
    $.post('ajax/deskripsi_pts_save.php', { pts_id: curPtsId, deskripsi_final: text }, function(){
      const tr = $('#tbl tr[data-pts-id="'+curPtsId+'"]');
      tr.find('.desc-text').text(text);
      if(tr.find('.badge.text-bg-warning').length===0){
        tr.find('.small').append(' <span class="badge text-bg-warning"><i class="fa-solid fa-pen"></i> Manual</span>');
      }
      editModal.hide();
    }, 'json').fail(function(xhr){ alert('Gagal: ' + (xhr.responseText || xhr.statusText)); });
  });

  // Copy
  $('#tbl').on('click','.btnCopy', function(){
    const text = $(this).closest('tr').find('.desc-text').text();
    navigator.clipboard.writeText(text).then(()=>{
      const btn = $(this), old = btn.html();
      btn.html('<i class="fa-solid fa-check"></i> Copied'); setTimeout(()=>btn.html(old), 1200);
    });
  });

  // Export CSV
  $('#btnExport').on('click', function(){
    let csv = 'No,Nama Siswa,NISN,NIS,Deskripsi\n';
    $('#tbl tbody tr').each(function(i){
      const tds = $(this).children('td');
      csv += [i+1,$(tds[1]).text(),$(tds[2]).text(),$(tds[3]).text(),'"'+$(tds[4]).find('.desc-text').text().replaceAll('"','""')+'"'].join(',')+'\n';
    });
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob); const a = document.createElement('a');
    a.href = url; a.download = 'deskripsi_pts_'+ptsSetId+'.csv'; a.click(); URL.revokeObjectURL(url);
  });
});
</script>
</body>
</html>
