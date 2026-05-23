<?php
// ==== Matikan mysqli exception (hindari HTTP 500) ====
if (function_exists('mysqli_report')) { @mysqli_report(MYSQLI_REPORT_OFF); }

// admin/status_penilaian.php
// EPS — Status Penilaian Kelas per Mapel (Rapor/STS)
// Revisi (tambahan):
// • Default kelas mengikuti wali kelas (jika ada) pada TA aktif. Tetap bisa memilih kelas lain via modal picker.
// • Ambil nama guru pengampu pakai helper reusable (eps_helpers.php), jadi muncul walau nilai/TP belum dibuat.
// • REVISI TERBARU: Filter Semester UI + Auto Deteksi + URL Parameter binding (Full Support Multi-Semester)

include 'header.php'; // diasumsikan sudah start session & set $koneksi (mysqli)
require_once __DIR__ . '/../includes/eps_helpers.php'; // <-- HELPER BARU

// ===== Helpers dasar (dibungkus function_exists agar tidak bentrok dengan header.php) =====
if (!function_exists('esc')) {
  function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('db_one')) {
  function db_one($db, $sql, $default = null){
    if(!$db) return $default;
    $r = @mysqli_query($db, $sql);
    if(!$r) return $default;
    $row = mysqli_fetch_row($r);
    return $row ? $row[0] : $default;
  }
}
if (!function_exists('current_semester')) {
  function current_semester(){
    $m = (int)date('n'); // 1-12
    // Jul–Des = 1, Jan–Jun = 2
    return ($m >= 7 && $m <= 12) ? 1 : 2;
  }
}
if (!function_exists('table_exists')) {
  function table_exists($db, $name){
    if(!$db) return false;
    $name = @mysqli_real_escape_string($db, (string)$name);
    $r = @mysqli_query($db, "SHOW TABLES LIKE '{$name}'");
    return ($r && mysqli_num_rows($r) > 0);
  }
}

// ==== Ambil TA aktif (fallback ke TA terakhir bila belum diset) ====
$TA_ID = (int)db_one($koneksi, "SELECT ta_id FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1", 0);
if ($TA_ID <= 0) {
  $TA_ID = (int)db_one($koneksi, "SELECT ta_id FROM ta ORDER BY ta_id DESC LIMIT 1", 0);
}

// --- PENYEMPURNAAN SEMESTER: Prioritaskan GET parameter, fallback ke current_semester() ---
$default_semester_auto = current_semester();
$SEMESTER = isset($_GET['semester']) ? (int)$_GET['semester'] : $default_semester_auto;
if (!in_array($SEMESTER, [1, 2], true)) { $SEMESTER = $default_semester_auto; }
// ------------------------------------------------------------------------------------------

// ==== Ambil user_id dari sesi (beberapa kemungkinan key) ====
$USER_ID = 0;
if (isset($_SESSION['id'])) $USER_ID = (int)$_SESSION['id'];
elseif (isset($_SESSION['user_id'])) $USER_ID = (int)$_SESSION['user_id'];
elseif (isset($_SESSION['uid'])) $USER_ID = (int)$_SESSION['uid'];

// ==== Cek tabel inti agar query aman ====
$HAS_KELAS       = table_exists($koneksi,'kelas');
$HAS_KELAS_SISWA = table_exists($koneksi,'kelas_siswa');
$HAS_MAPEL       = table_exists($koneksi,'mapel');
$HAS_USER        = table_exists($koneksi,'user');

$HAS_PTS_SET     = table_exists($koneksi,'nilai_pts_set');
$HAS_PTS         = table_exists($koneksi,'nilai_pts');
$HAS_PTS_TP      = table_exists($koneksi,'nilai_pts_tp');

$HAS_PENGAMPU    = table_exists($koneksi,'pengampu_mapel'); // untuk fallback guru bila set belum ada

// ==== Daftar kelas di TA aktif ====
$kelas = array();
if ($HAS_KELAS) {
  $qk = @mysqli_query($koneksi, "SELECT kelas_id, kelas_nama FROM kelas WHERE kelas_ta='{$TA_ID}' ORDER BY kelas_nama ASC");
  if($qk){ while($row = mysqli_fetch_assoc($qk)){ $kelas[] = $row; } }
}
if(empty($kelas)){ $kelas[] = array('kelas_id'=>0,'kelas_nama'=>'(Belum ada kelas untuk TA aktif)'); }

// ==== Tentukan default kelas: prioritas wali kelas jika ada ====
if (isset($_GET['kelas_id'])) {
  $kelas_id = (int)$_GET['kelas_id'];
} else {
  $kelas_id = 0;
  $kelas_id = eps_resolve_wali_kelas_id($koneksi, $USER_ID, $TA_ID) ?: 0;  // <-- pakai helper
  if ($kelas_id <= 0) { $kelas_id = (int)$kelas[0]['kelas_id']; }
}

// nama kelas sesuai id
$kelas_nama = '';
foreach($kelas as $k){ if((int)$k['kelas_id']===$kelas_id){ $kelas_nama = $k['kelas_nama']; break; } }
if($kelas_nama===''){ $kelas_id = (int)$kelas[0]['kelas_id']; $kelas_nama = $kelas[0]['kelas_nama']; }

// ==== Hitung jumlah siswa kelas ====
$jumlah_siswa = 0;
if ($HAS_KELAS_SISWA && $kelas_id>0){
  $jumlah_siswa = (int)db_one($koneksi, "SELECT COUNT(*) FROM kelas_siswa WHERE ks_kelas='{$kelas_id}'", 0);
}

// ==== Daftar mapel ====
$mapel = array();
if ($HAS_MAPEL){
  $qm = @mysqli_query($koneksi, "SELECT mapel_id, mapel_kode, mapel_nama FROM mapel ORDER BY mapel_id ASC");
  if($qm){ while($row = mysqli_fetch_assoc($qm)){ $mapel[] = $row; } }
}
?>
<style>
/* Animasi fade + slide-up untuk judul & konten saat load */
@keyframes epsFadeSlideUp { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }
.eps-animate-intro { animation: epsFadeSlideUp .55s ease-out both; }

/* Progress mini bar */
.eps-mini-progress{width:100%;height:8px;border-radius:999px;background:#eef2ff;overflow:hidden}
.eps-mini-progress>i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#36d399,#16a34a)}
.eps-mini-progress.warning>i{background:linear-gradient(90deg,#fbbf24,#f59e0b)}
.eps-mini-progress.danger>i{background:linear-gradient(90deg,#f87171,#ef4444)}

/* Tabel responsif */
@media (max-width: 991px){ .table-responsive{ border:0; } table.table td, table.table th { white-space: nowrap; } }
</style>

<div class="content-wrapper">
  <section class="content-header eps-animate-intro" style="margin-bottom:8px">
    <h1 style="display:flex;align-items:center;gap:10px;font-weight:800">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;background:#E6EFFF;border:1px solid #D7E6FF;color:#0B57D0">
        <i class="fa fa-tasks"></i>
      </span>
      <span>Status Penilaian</span>
      <small class="label bg-light-blue" style="margin-left:8px;border-radius:999px;">TA Aktif</small>
    </h1>
  </section>

  <section class="content eps-animate-intro">
    <div class="row">
      <section class="col-lg-12">
        <div class="box" style="border-radius:12px; overflow:visible; box-shadow:0 10px 30px rgba(11,87,208,.08);">
          <div class="box-header with-border" style="background:linear-gradient(90deg,#1976ff,#0ea5e9);color:#fff">
            <h3 class="box-title" style="font-weight:800"><i class="fa fa-sliders"></i> Filter Penilaian</h3>
          </div>
          <div class="box-body" style="background:#f8fbff;border-top:1px solid #e6efff">
            
            <div class="row">
                <div class="col-md-6 col-sm-12">
                    <div class="form-group">
                      <label><i class="fa fa-university"></i> Pilih Kelas</label>
                      <div class="input-group">
                        <input id="kelasPicker" class="form-control" placeholder="Klik tombol pilih…" readonly value="<?php echo esc($kelas_nama); ?>">
                        <span class="input-group-btn">
                          <button id="btnPickKelas" class="btn btn-default" type="button"><i class="fa fa-search"></i> Pilih</button>
                        </span>
                      </div>
                      <select id="selKelas" class="form-control" style="display:none">
                        <?php foreach($kelas as $k): ?>
                          <option value="<?php echo (int)$k['kelas_id']; ?>" <?php echo ((int)$k['kelas_id']===$kelas_id?'selected':''); ?>><?php echo esc($k['kelas_nama']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                </div>

                <div class="col-md-4 col-sm-12">
                    <div class="form-group">
                        <label><i class="fa fa-calendar-check-o"></i> Pilih Semester</label>
                        <select id="semesterFilter" class="form-control">
                            <option value="1" <?php echo $SEMESTER == 1 ? 'selected' : ''; ?>>Semester 1 (Ganjil)</option>
                            <option value="2" <?php echo $SEMESTER == 2 ? 'selected' : ''; ?>>Semester 2 (Genap)</option>
                        </select>
                    </div>
                </div>
            </div>

            <?php
              $total_target = max(1, count($mapel)*max(1,$jumlah_siswa));
              $total_nilai=0; $total_tp=0;
              foreach($mapel as $m){
                $mid = (int)$m['mapel_id'];
                $set_id = 0;
                if ($HAS_PTS_SET){
                  // Query pengecekan status (sudah memanggil variabel $SEMESTER yang difilter dari UI)
                  $sql_set = "SELECT pts_set_id FROM nilai_pts_set
                              WHERE ta_id='{$TA_ID}' AND semester='{$SEMESTER}' AND kelas_id='{$kelas_id}' AND mapel_id='{$mid}'
                              ORDER BY pts_set_id DESC LIMIT 1";
                  $set_id = (int)db_one($koneksi,$sql_set,0);
                }
                if($set_id && $HAS_PTS){
                  $total_nilai += (int)db_one($koneksi,"SELECT COUNT(DISTINCT siswa_id) FROM nilai_pts WHERE pts_set_id='{$set_id}'",0);
                  if ($HAS_PTS_TP){
                    $total_tp    += (int)db_one($koneksi,"SELECT COUNT(DISTINCT np.siswa_id)
                                                  FROM nilai_pts np JOIN nilai_pts_tp t ON t.pts_id=np.pts_id
                                                  WHERE np.pts_set_id='{$set_id}'",0);
                  }
                }
              }
              $pN = min(100, (int)round(($total_nilai/$total_target)*100));
              $pD = min(100, (int)round(($total_tp/$total_target)*100));
            ?>
            <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap">
              <span class="label" style="background:#F6F9FF;border:1px solid #D7E6FF;color:#0B57D0;padding:8px 12px;border-radius:12px;display:inline-block;font-weight:700">Ringkasan Nilai: <?php echo $pN; ?>%</span>
              <span class="label" style="background:#F6F9FF;border:1px solid #D7E6FF;color:#0B57D0;padding:8px 12px;border-radius:12px;display:inline-block;font-weight:700">Ringkasan Deskripsi/TP: <?php echo $pD; ?>%</span>
              <span class="label" style="background:#F6F9FF;border:1px solid #D7E6FF;color:#0B57D0;padding:8px 12px;border-radius:12px;display:inline-block;font-weight:700">Siswa di kelas: <?php echo $jumlah_siswa; ?></span>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="row">
      <section class="col-lg-12">
        <div class="box" style="border-radius:16px;box-shadow:0 10px 30px rgba(11,87,208,.08);overflow:hidden;">
          <div class="box-header" style="padding:0">
            <div style="padding:14px 16px;background:#0B57D0;color:#fff;font-weight:800;letter-spacing:.3px">
              Status Penilaian Kelas <?php echo esc($kelas_nama); ?> — Semester <?php echo $SEMESTER; ?>
            </div>
          </div>
          <div class="box-body" style="padding:0">
            <div class="table-responsive">
              <table class="table table-striped table-hover" style="margin:0">
                <thead style="background:#073f9c;color:#fff">
                  <tr>
                    <th style="width:56px;text-align:center">No</th>
                    <th>Nama Mapel</th>
                    <th style="width:144px">Rombel</th>
                    <th style="width:260px;white-space:nowrap">Guru</th>
                    <th style="width:240px">Nilai Rapor</th>
                    <th style="width:240px">Deskripsi (TP)</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $no = 1;
                  foreach($mapel as $m){
                    $mid = (int)$m['mapel_id'];

                    // --- Ambil set untuk progres berdasarkan SEMESTER AKTIF UI ---
                    $set_id = 0;
                    if ($HAS_PTS_SET){
                      $sql_set = "SELECT pts_set_id FROM nilai_pts_set
                                  WHERE ta_id='{$TA_ID}' AND semester='{$SEMESTER}' AND kelas_id='{$kelas_id}' AND mapel_id='{$mid}'
                                  ORDER BY pts_set_id DESC LIMIT 1";
                      $set_id = (int)db_one($koneksi,$sql_set,0);
                    }

                    // --- NAMA GURU PENGAMPU: pakai helper reusable ---
                    $guru = eps_resolve_pengampu_nama($koneksi, $TA_ID, $kelas_id, $mid, '(Belum ditetapkan)');

                    // --- Hitung progres ---
                    $cnt_nilai = 0; $cnt_tp = 0;
                    if ($set_id>0 && $HAS_PTS){
                      $cnt_nilai = (int)db_one($koneksi,"SELECT COUNT(DISTINCT siswa_id) FROM nilai_pts WHERE pts_set_id='{$set_id}'",0);
                      if ($HAS_PTS_TP){
                        $cnt_tp    = (int)db_one($koneksi,"SELECT COUNT(DISTINCT np.siswa_id)
                                                          FROM nilai_pts np JOIN nilai_pts_tp t ON t.pts_id=np.pts_id
                                                          WHERE np.pts_set_id='{$set_id}'",0);
                      }
                    }

                    // Status + persen
                    $clsNilai = ($cnt_nilai <= 0) ? 'danger' : (($cnt_nilai < $jumlah_siswa) ? 'warning' : 'success');
                    $clsTP    = ($cnt_tp   <= 0) ? 'danger' : (($cnt_tp   < $jumlah_siswa) ? 'warning' : 'success');

                    $labelNilai = ($jumlah_siswa>0) ? "{$cnt_nilai} / {$jumlah_siswa}" : "{$cnt_nilai}";
                    $labelTP    = ($jumlah_siswa>0) ? "{$cnt_tp} / {$jumlah_siswa}" : "{$cnt_tp}";

                    $pNilai = ($jumlah_siswa>0)? (int)round(($cnt_nilai/$jumlah_siswa)*100) : 0;
                    $pTP    = ($jumlah_siswa>0)? (int)round(($cnt_tp/$jumlah_siswa)*100) : 0;

                    // Nama guru merah bila BELUM input nilai ATAU BELUM input TP
                    $need_red = ($pNilai<=0 || $pTP<=0);
                    $guru_style = ($need_red ? 'color:#d12c2c;font-weight:700;' : '') . 'white-space:nowrap';
                ?>
                  <tr>
                    <td style="text-align:center"><?php echo $no++; ?></td>
                    <td>
                      <div style="font-weight:700"><?php echo esc($m['mapel_nama']); ?></div>
                      <div style="font-size:12px;color:#697386">
                        Kode: <?php echo esc($m['mapel_kode']); ?>
                        <?php echo $set_id ? "· Set#{$set_id}" : "· Nilai/TP belum ada"; ?>
                      </div>
                    </td>
                    <td><?php echo esc($kelas_nama); ?></td>
                    <td><span style="<?php echo $guru_style; ?>"><?php echo esc($guru); ?></span></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span class="label label-<?php echo $clsNilai; ?>" style="display:inline-block;border-radius:999px;padding:6px 10px;font-weight:700"><?php echo $labelNilai; ?></span>
                        <div class="eps-mini-progress <?php echo $clsNilai; ?>" title="<?php echo $pNilai; ?>%" style="min-width:120px;max-width:160px;flex:1">
                          <i style="width:<?php echo max(0,min(100,$pNilai)); ?>%"></i>
                        </div>
                        <span style="font-size:12px;color:#697386;font-weight:700;min-width:28px;text-align:right"><?php echo $pNilai; ?>%</span>
                      </div>
                    </td>
                    <td>
                      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span class="label label-<?php echo $clsTP; ?>" style="display:inline-block;border-radius:999px;padding:6px 10px;font-weight:700"><?php echo $labelTP; ?></span>
                        <div class="eps-mini-progress <?php echo $clsTP; ?>" title="<?php echo $pTP; ?>%" style="min-width:120px;max-width:160px;flex:1">
                          <i style="width:<?php echo max(0,min(100,$pTP)); ?>%"></i>
                        </div>
                        <span style="font-size:12px;color:#697386;font-weight:700;min-width:28px;text-align:right"><?php echo $pTP; ?>%</span>
                      </div>
                    </td>
                  </tr>
                <?php } // end foreach mapel ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="box-footer" style="padding:12px 16px;background:#fafcff;border-top:1px solid #eef3ff">
            <div style="font-size:12px;color:#5b6b8a">
              <b>Keterangan:</b>
              <span class="label label-success" style="margin-left:6px">Lengkap</span>
              <span class="label label-warning" style="margin-left:6px">Sebagian</span>
              <span class="label label-danger"  style="margin-left:6px">Belum ada</span>
            </div>
          </div>
        </div>
      </section>
    </div>

  </section>
</div>

<div class="modal fade" id="kelasPickModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="border-radius:12px">
      <div class="modal-header" style="background:#0B57D0;color:#fff;border-top-left-radius:12px;border-top-right-radius:12px">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:1"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Pilih Kelas</h4>
      </div>
      <div class="modal-body">
        <input id="kelasSearchModal" class="form-control" placeholder="Ketik untuk mencari…" style="margin-bottom:10px">
        <div id="kelasListModal" class="list-group" style="max-height:60vh;overflow:auto;margin-bottom:0"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  // Data kelas ke JS
  var kelasData = <?php echo json_encode(array_map(function($r){ return ['id'=>(int)$r['kelas_id'],'nama'=>$r['kelas_nama']]; }, $kelas)); ?>;

  var sel = document.getElementById('selKelas');
  var inp = document.getElementById('kelasPicker');
  var btn = document.getElementById('btnPickKelas');
  var selSemester = document.getElementById('semesterFilter');

  function escapeHtml(s){ return (s||'').replace(/[&<>"']/g,function(m){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[m];}).replace(/'/g,'&#39;'); }
  function rebuildList(filter){
    var list = document.getElementById('kelasListModal');
    var q = (filter||'').toLowerCase().trim();
    var html = '';
    kelasData.forEach(function(it){
      var name = String(it.nama||'');
      if(q && name.toLowerCase().indexOf(q) < 0) return;
      html += '<a class="list-group-item pickable" data-id="'+it.id+'" data-name="'+escapeHtml(name)+'">'+escapeHtml(name)+'</a>';
    });
    if(!html) html = '<div class="list-group-item text-muted">Tidak ada data.</div>';
    list.innerHTML = html;
  }

  if(btn){ btn.addEventListener('click', function(){
    rebuildList('');
    if (window.jQuery) { jQuery('#kelasPickModal').modal('show'); setTimeout(function(){ var s=document.getElementById('kelasSearchModal'); if(s) s.focus(); }, 300); }
  }); }

  document.getElementById('kelasSearchModal').addEventListener('input', function(){ rebuildList(this.value); });
  
  document.getElementById('kelasListModal').addEventListener('click', function(e){
    var t = e.target; while(t && !t.classList.contains('pickable')) t = t.parentElement;
    if(!t) return; var id = t.getAttribute('data-id'); var name = t.getAttribute('data-name');
    if(sel) sel.value = String(id);
    if(inp) inp.value = name || '';
    if (window.jQuery) { jQuery('#kelasPickModal').modal('hide'); }
    
    // Redirect saat kelas dipilih (pertahankan filter semester jika ada)
    var qs = new URLSearchParams(window.location.search); 
    qs.set('kelas_id', id);
    if(selSemester) qs.set('semester', selSemester.value);
    
    window.location.href = window.location.pathname + '?' + qs.toString();
  });

  // Event Listener untuk Dropdown Semester (Reload otomatis)
  if(selSemester){
      selSemester.addEventListener('change', function(){
          var id_kelas = sel ? sel.value : '';
          var qs = new URLSearchParams(window.location.search); 
          if(id_kelas) qs.set('kelas_id', id_kelas);
          qs.set('semester', this.value);
          window.location.href = window.location.pathname + '?' + qs.toString();
      });
  }

});
</script>

<?php include 'footer.php'; ?>