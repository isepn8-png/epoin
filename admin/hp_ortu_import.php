<?php
// ============================================================
// admin/hp_ortu_import.php — Upload & import HP Ortu via Excel
// Format Excel: kolom NIS + HP_ORTU (2 kolom)
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/epoin_security.php';
epoin_staff_guard(false);

// ===== Download template =====
if (isset($_GET['template'])) {
  $autoload = __DIR__ . '/../vendor/autoload.php';
  if (!file_exists($autoload)) { die('Vendor tidak ditemukan.'); }
  require_once $autoload;
  $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
  $sheet = $sp->getActiveSheet();
  $sheet->setTitle('Template');
  // Header
  $sheet->setCellValue('A1', 'NIS');
  $sheet->setCellValue('B1', 'HP_ORTU');
  // Style header
  $hStyle = [
    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF']],
    'fill' => ['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'1e88e5']],
    'alignment' => ['horizontal'=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
  ];
  $sheet->getStyle('A1:B1')->applyFromArray($hStyle);
  $sheet->getColumnDimension('A')->setWidth(22);
  $sheet->getColumnDimension('B')->setWidth(22);
  // Contoh data
  $sheet->setCellValue('A2', '20250001');
  $sheet->setCellValue('B2', '081234567890');
  $sheet->setCellValue('A3', '20250002');
  $sheet->setCellValue('B3', '6281234567891');
  // Note row
  $sheet->setCellValue('A5', 'CATATAN:');
  $sheet->setCellValue('B5', 'Kolom NIS harus cocok persis dengan data di sistem. HP_ORTU: format 08xx atau 628xx.');
  $sheet->getStyle('A5')->getFont()->setBold(true);
  $sheet->mergeCells('B5:D5');

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="template_hp_ortu.xlsx"');
  header('Cache-Control: max-age=0');
  $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sp);
  $writer->save('php://output');
  exit;
}

include 'header.php';
require_once __DIR__ . '/../koneksi.php';
?>
<style>
.page-title-wrap{position:relative;background:linear-gradient(90deg,#059669,#10b981);color:#fff;border-radius:14px;padding:18px 22px;margin-bottom:18px;box-shadow:0 10px 24px rgba(5,150,105,.18);overflow:hidden}
.page-title-wrap h1{margin:0;font-weight:800;letter-spacing:.2px;text-shadow:0 1px 2px rgba(0,0,0,.15)}
.page-title-wrap small{color:#d1fae5;font-weight:600}
.page-title-wrap::after{content:"";position:absolute;top:-40%;left:-20%;width:40%;height:200%;transform:rotate(25deg);background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.28),rgba(255,255,255,0));animation:shimmer 2.6s infinite}
@keyframes shimmer{0%{left:-40%}60%{left:120%}100%{left:120%}}
.box-green{border-top:3px solid #059669;border-radius:10px;overflow:hidden}
.btn-green-grad{background:linear-gradient(90deg,#047857,#059669);color:#fff!important;border:none;transition:transform .12s,box-shadow .12s}
.btn-green-grad:hover{transform:translateY(-1px);box-shadow:0 8px 16px rgba(5,150,105,.25)}
.note-contrast{background:#fff3e0;color:#b71c1c;border-left:4px solid #ef6c00;padding:10px 12px;border-radius:8px;margin-top:10px;font-weight:700;font-size:12px}
.col-info{background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:3px 8px;font-weight:700;color:#1b5e20;font-size:12px}
</style>

<div class="content-wrapper">
  <section class="content-header" style="margin-bottom:0">
    <div class="page-title-wrap">
      <h1><i class="fa fa-phone"></i> Import HP Orang Tua <small>via Excel</small></h1>
      <div style="font-size:13px;color:#d1fae5;margin-top:4px">Upload file Excel berisi NIS + nomor HP orang tua untuk diperbarui massal.</div>
    </div>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="siswa.php">Data Siswa</a></li>
      <li class="active">Import HP Ortu</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">

      <!-- Kolom kiri: Upload -->
      <section class="col-lg-6">
        <div class="box box-green">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-upload"></i> Upload File Excel</h3>
            <a href="siswa.php" class="btn btn-default btn-sm pull-right"><i class="fa fa-reply"></i> Kembali</a>
          </div>
          <div class="box-body">

            <div class="alert alert-info" style="margin-bottom:14px">
              <div style="margin-bottom:8px;font-weight:600">
                Download template Excel terlebih dahulu, isi kolom NIS dan HP, lalu upload kembali.
              </div>
              <a href="hp_ortu_import.php?template=1" class="btn btn-primary btn-sm">
                <i class="fa fa-download"></i> &nbsp;Unduh Template
              </a>
            </div>

            <form id="formImport" action="hp_ortu_import_act.php" method="post" enctype="multipart/form-data" novalidate>
              <?php echo epoin_csrf_field(); ?>

              <div class="form-group">
                <label>Upload File Excel (.xlsx)</label>
                <input type="file" class="form-control" name="berkas" id="berkas"
                       accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <small class="text-muted">Maksimum 5 MB. Hanya format <b>.xlsx</b>.</small>
              </div>

              <button id="submitBtn" type="submit" class="btn btn-green-grad btn-block">
                <i class="fa fa-upload"></i> &nbsp;IMPORT HP ORTU
              </button>
            </form>

          </div>
        </div>
      </section>

      <!-- Kolom kanan: Panduan -->
      <section class="col-lg-6">
        <div class="box box-green">
          <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-info-circle"></i> Format & Panduan</h3>
          </div>
          <div class="box-body">

            <div class="alert alert-success">
              <b>FORMAT FILE EXCEL</b>
              <ul style="margin-top:8px;margin-bottom:6px">
                <li>Kolom <span class="col-info">NIS</span> — NIS siswa (harus cocok persis dengan data di sistem)</li>
                <li>Kolom <span class="col-info">HP_ORTU</span> — Nomor HP orang tua (format 08xx atau 628xx)</li>
                <li>Baris ke-1 adalah header, data mulai baris ke-2</li>
                <li>Baris yang NIS-nya tidak ditemukan akan di-<b>skip</b></li>
              </ul>
              <div class="note-contrast">
                <i class="fa fa-exclamation-triangle"></i>
                Nama header kolom di Excel harus persis: <b>NIS</b> dan <b>HP_ORTU</b> (huruf besar, tanpa spasi).
              </div>
            </div>

            <div class="alert alert-warning" style="margin-bottom:0">
              <b><i class="fa fa-lightbulb-o"></i> Cara mudah:</b>
              <ol style="margin-top:6px;margin-bottom:0;font-size:13px">
                <li>Klik <b>Unduh Template</b> di sebelah kiri</li>
                <li>Isi kolom NIS dan HP_ORTU di Excel</li>
                <li>Simpan sebagai <b>.xlsx</b></li>
                <li>Upload dan klik IMPORT</li>
              </ol>
            </div>

          </div>
        </div>
      </section>

    </div>
  </section>
</div>

<?php include 'footer.php'; ?>

<script>
(function(){
  var form = document.getElementById('formImport');
  var btn  = document.getElementById('submitBtn');
  var finput = document.getElementById('berkas');
  if(!form||!btn||!finput) return;

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var f = finput.files && finput.files[0];
    if(!f){ alert('Pilih file .xlsx terlebih dahulu.'); return; }
    if(!f.name.toLowerCase().endsWith('.xlsx')){ alert('Hanya file .xlsx yang diperbolehkan.'); return; }
    if(f.size > 5*1024*1024){ alert('Ukuran file melebihi 5 MB.'); return; }
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
    form.submit();
  });
})();
</script>
