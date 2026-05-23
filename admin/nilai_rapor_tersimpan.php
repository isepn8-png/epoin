<?php
/**
 * EPS — Data Nilai Rapor & Deskripsi yang Tersimpan (STS)
 * Revisi: ikon & badge di judul halaman, hapus ikon judul tabel,
 * bersihkan jeda/indent awal deskripsi (tanpa baris kosong lewat pre-line wrapper),
 * hilangkan teks bantuan & badge SET, tombol "kembali ke atas",
 * RESPONSIF: tabel tetap utuh di mobile (horizontal scroll), judul mengecil di mobile.
 * REVISI TERBARU: Full Support Multi-Semester, Perbaikan Logika Dropdown (Tarik dari Master), Urutan Filter
 */

$page_title    = 'Data Nilai Rapor & Deskripsi yang Tersimpan';
$judul_halaman = $page_title;
$PAGE_TITLE    = $page_title;

include 'header.php'; // menyediakan $koneksi (mysqli)
if (function_exists('mysqli_report')) @mysqli_report(MYSQLI_REPORT_OFF);

/* ================= Helpers ================= */
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function db_all(mysqli $db, $sql){ $res=@mysqli_query($db,$sql); if(!$res) return []; $o=[]; while($r=@mysqli_fetch_assoc($res)) $o[]=$r; @mysqli_free_result($res); return $o; }
function db_row(mysqli $db, $sql){ $res=@mysqli_query($db,$sql); if(!$res) return null; $r=@mysqli_fetch_assoc($res); @mysqli_free_result($res); return $r?:null; }
function db_one_val(mysqli $db, $sql, $def=null){ $r=db_row($db,$sql); if(!$r) return $def; $v=array_shift($r); return $v===null?$def:$v; }
function table_cols(mysqli $db,$name){ $cols=[]; $res=@mysqli_query($db,"SHOW COLUMNS FROM `$name`"); if($res){ while($r=@mysqli_fetch_assoc($res)) $cols[]=$r['Field']; @mysqli_free_result($res);} return $cols; }
function table_exists(mysqli $db,$name){ $safe=mysqli_real_escape_string($db,$name); $q=@mysqli_query($db,"SHOW FULL TABLES LIKE '$safe'"); $ok=$q && mysqli_num_rows($q)>0; if($q) @mysqli_free_result($q); return $ok; }
function pick_col(array $cols,array $cands){ foreach($cands as $c){ if(in_array($c,$cols,true)) return $c; } return null; }

/** NORMALISASI TANPA JEDA/INDENT */
function norm_desc($txt){
  $txt = (string)$txt;
  $txt = str_replace(["\r\n","\r"], "\n", $txt);
  $txt = preg_replace('/^\xEF\xBB\xBF/u', '', $txt);                        // BOM
  $txt = preg_replace('/^[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $txt);        // zero-width
  $txt = preg_replace('/^\h+/u', '', $txt);                                 // spasi awal
  $txt = preg_replace('/^\R+/u', '', $txt);                                 // baris kosong awal
  $txt = preg_replace('/^[\h\p{Zs}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+/mu', '', $txt);
  $txt = preg_replace('/[ \t\p{Zs}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]+$/mu', '', $txt);
  $txt = preg_replace("/\n{2,}/", "\n", $txt);                                // runtuhkan blank line ganda
  return trim($txt);
}
function join_deskripsi_plain($opt,$perlu){
  $opt = norm_desc($opt);
  $per = norm_desc($perlu);
  if($opt!=='' && $per!=='') return norm_desc($opt."\n".$per);
  if($opt!=='') return $opt;
  if($per!=='') return $per;
  return '';
}

/** Ambil info role & id guru dari session secara toleran */
$ROLE = '';
foreach (['role','level','hak_akses','akses','tipe'] as $k) if(isset($_SESSION[$k])) { $ROLE = strtolower((string)$_SESSION[$k]); break; }
$GURU_ID = 0;
foreach (['id_guru','guru_id','pengguna_id','user_id','id_user'] as $k) if(isset($_SESSION[$k])) { $GURU_ID = (int)$_SESSION[$k]; break; }

$is_admin = in_array($ROLE, ['admin','superadmin','super admin','administrator','super','root'], true);

/* ================= Cari tabel pengampu (kelas-mapel-guru) & wali kelas (dinamis) ================= */
function find_pengampu_mapping(mysqli $db){
  $cands = ['mapel_pengampu','guru_mapel','mapel_guru','mengajar','guru_mengajar','pengampu_mapel','mapel_ajar','ajar_mapel','kbm','jadwal_mapel','jadwal'];
  foreach($cands as $t){
    if(!table_exists($db,$t)) continue;
    $cols = table_cols($db,$t);
    $g = pick_col($cols,['guru_id','id_guru']);
    $k = pick_col($cols,['kelas_id','id_kelas']);
    $m = pick_col($cols,['mapel_id','id_mapel']);
    $ta= pick_col($cols,['ta_id','id_ta','tahun_ajaran_id','tahunajaran_id']);
    $sm= pick_col($cols,['semester','smt','id_semester']);
    if($g && $k && $m) return ['t'=>$t,'g'=>$g,'k'=>$k,'m'=>$m,'ta'=>$ta,'sm'=>$sm];
  }
  return null;
}
function get_allowed_pairs(mysqli $db, $guru_id, $TA_ID, $SM){
  $pairs=[]; $pm = find_pengampu_mapping($db); if(!$pm) return $pairs;
  // Pengampu biasanya tidak membatasi semester secara ketat di tabel jadwal, jadi $SM bisa opsional
  $w=""; if($pm['ta']) $w.=" AND `{$pm['ta']}`={$TA_ID} "; 
  $rows = db_all($db,"SELECT DISTINCT `{$pm['k']}` AS kelas_id, `{$pm['m']}` AS mapel_id FROM `{$pm['t']}` WHERE `{$pm['g']}`={$guru_id} {$w}");
  foreach($rows as $r){ $pairs[(int)$r['kelas_id']][]=(int)$r['mapel_id']; }
  return $pairs;
}
function get_wali_kelas_ids(mysqli $db, $guru_id, $TA_ID, $SM){
  $ids=[];
  if(table_exists($db,'kelas')){
    $cols=table_cols($db,'kelas'); $w=pick_col($cols,['wali_id','id_wali','wali_kelas_id','guru_wali_id']);
    if($w){ foreach(db_all($db,"SELECT kelas_id FROM kelas WHERE `$w`={$guru_id}") as $r) $ids[]=(int)$r['kelas_id']; }
  }
  if(!$ids && table_exists($db,'wali_kelas')){
    $cols=table_cols($db,'wali_kelas');
    $g=pick_col($cols,['guru_id','id_guru']); $k=pick_col($cols,['kelas_id','id_kelas']);
    $ta=pick_col($cols,['ta_id','id_ta']); $sm=pick_col($cols,['semester','smt','id_semester']);
    if($g && $k){
      $w="WHERE `$g`={$guru_id}"; if($ta) $w.=" AND `$ta`={$TA_ID}"; // if($sm) $w.=" AND `$sm`={$SM}";
      foreach(db_all($db,"SELECT DISTINCT `$k` AS kelas_id FROM wali_kelas {$w}") as $r) $ids[]=(int)$r['kelas_id'];
    }
  }
  return array_values(array_unique($ids));
}

/* ================= TA aktif & semester otomatis ================= */
$TA = db_row($koneksi,"SELECT * FROM ta WHERE ta_status=1 LIMIT 1");
if(!$TA) $TA = db_row($koneksi,"SELECT * FROM ta ORDER BY ta_id DESC LIMIT 1");
$TA_ID = (int)($TA['ta_id']??0);

// --- PENYEMPURNAAN SEMESTER: Prioritaskan GET parameter dari UI ---
$default_semester_auto = (int) (date('n') >= 7 ? 1 : 2);
$SEM_AUTOMATIS = isset($_GET['semester']) ? (int)$_GET['semester'] : $default_semester_auto;
if (!in_array($SEM_AUTOMATIS, [1, 2], true)) { $SEM_AUTOMATIS = $default_semester_auto; }
// ------------------------------------------------------------------

/* ================= Batasan kelas/mapel sesuai pengampu ================= */
$allowedPairs = []; $waliKelasIds = [];
if(!$is_admin && $GURU_ID>0){
  $allowedPairs = get_allowed_pairs($koneksi, $GURU_ID, $TA_ID, $SEM_AUTOMATIS);
  $waliKelasIds = get_wali_kelas_ids($koneksi, $GURU_ID, $TA_ID, $SEM_AUTOMATIS);
}

/* ================= PENGAMBILAN DATA KELAS (DARI MASTER KELAS, BUKAN DARI NILAI) ================= */
$kelasWhere = "kelas_ta={$TA_ID}";
if(!$is_admin && $GURU_ID>0){
  $allowedKelas = array_values(array_unique(array_merge(array_keys($allowedPairs), $waliKelasIds)));
  if($allowedKelas){ $in = implode(',', array_map('intval',$allowedKelas)); $kelasWhere .= " AND kelas_id IN ($in)"; }
  else{ $kelasWhere .= " AND 1=0"; }
}
$kelasList = db_all($koneksi,"SELECT kelas_id, kelas_nama FROM kelas WHERE {$kelasWhere} ORDER BY kelas_nama");
$KELAS=[]; foreach($kelasList as $r){ $KELAS[(int)$r['kelas_id']]=$r['kelas_nama']; }

$kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
if($kelas_id===0 && $KELAS){ $kelas_id=(int)array_key_first($KELAS); }
// Fallback jika id yang diset tidak valid
if(!array_key_exists($kelas_id, $KELAS) && $KELAS) { $kelas_id=(int)array_key_first($KELAS); }

/* ================= PENGAMBILAN DATA MAPEL (DARI PENGAMPU / MASTER, BUKAN DARI NILAI) ================= */
$MAPEL=[];
if ($is_admin || in_array($kelas_id, $waliKelasIds, true)) {
    // Admin atau Wali Kelas melihat semua mapel yang ada di tabel master/pengampu untuk kelas tersebut
    // Kita ambil dari pengampu mapel yang ada di kelas ini saja biar tidak kepanjangan
    $mapelList = db_all($koneksi, "
        SELECT DISTINCT m.mapel_id, m.mapel_nama
        FROM pengampu_mapel p
        JOIN mapel m ON m.mapel_id=p.mapel_id
        WHERE p.ta_id={$TA_ID} AND p.kelas_id={$kelas_id}
        ORDER BY m.mapel_nama
    ");
    // Jika ternyata belum di-set di pengampu, tarik dari master
    if(empty($mapelList)) {
        $mapelList = db_all($koneksi, "SELECT mapel_id, mapel_nama FROM mapel ORDER BY mapel_nama");
    }
} else {
    // Guru hanya melihat mapel yang dia ampu di kelas tersebut
    $allowedMapel = $allowedPairs[$kelas_id] ?? [];
    if($allowedMapel){ 
        $in = implode(',', array_map('intval',$allowedMapel)); 
        $mapelList = db_all($koneksi, "SELECT mapel_id, mapel_nama FROM mapel WHERE mapel_id IN ($in) ORDER BY mapel_nama");
    } else {
        $mapelList = [];
    }
}

foreach($mapelList as $r){ $MAPEL[(int)$r['mapel_id']]=$r['mapel_nama']; }
$mapel_id = isset($_GET['mapel_id']) ? (int)$_GET['mapel_id'] : 0;
if($mapel_id===0 && $MAPEL){ $mapel_id=(int)array_key_first($MAPEL); }
// Fallback jika mapel yg diset tidak ada
if(!array_key_exists($mapel_id, $MAPEL) && $MAPEL) { $mapel_id=(int)array_key_first($MAPEL); }


/* ================= Kolom siswa (toleran) ================= */
$sCols  = table_cols($koneksi,'siswa');
$cNama  = pick_col($sCols,['siswa_nama','nama','nama_lengkap','nama_siswa']) ?: 'siswa_nama';
$cNIS   = pick_col($sCols,['siswa_nis','nis','NIS','nis_local']) ?: 'siswa_nis';
$cNISN  = pick_col($sCols,['nisn','siswa_nisn','NISN','nisn_siswa']);

/* ================= Tentukan pts_set_id terbaru yang berisi nilai ================= */
$pts_set_id = 0;
if($kelas_id>0 && $mapel_id>0){
  $pts_set_id = (int) db_one_val($koneksi,"
    SELECT s.pts_set_id
    FROM nilai_pts_set s
    JOIN nilai_pts np ON np.pts_set_id=s.pts_set_id
    WHERE s.ta_id={$TA_ID} AND s.semester={$SEM_AUTOMATIS}
      AND s.kelas_id={$kelas_id} AND s.mapel_id={$mapel_id}
    GROUP BY s.pts_set_id
    ORDER BY MAX(np.updated_at) DESC, s.updated_at DESC, s.pts_set_id DESC
    LIMIT 1
  ", 0);
  if ($pts_set_id===0){
    $pts_set_id = (int) db_one_val($koneksi,"
      SELECT s.pts_set_id
      FROM nilai_pts_set s
      WHERE s.ta_id={$TA_ID} AND s.semester={$SEM_AUTOMATIS}
        AND s.kelas_id={$kelas_id} AND s.mapel_id={$mapel_id}
      ORDER BY s.updated_at DESC, s.pts_set_id DESC
      LIMIT 1
    ", 0);
  }
}

/* ================= Ambil data ================= */
$data=[]; $emptyCount=0;
if($pts_set_id>0){
  $has_rpd   = table_exists($koneksi,'rapor_pts_deskripsi');
  $has_viewf = table_exists($koneksi,'v_rapor_pts_deskripsi_final');

  $npCols = table_cols($koneksi,'nilai_pts');
  $selVFO = $has_viewf ? "vf.deskripsi_optimal" : (in_array('deskripsi_optimal',$npCols,true) ? "np.deskripsi_optimal" : "NULL");
  $selVFP = $has_viewf ? "vf.deskripsi_perlu"   : (in_array('deskripsi_perlu',$npCols,true)   ? "np.deskripsi_perlu"   : "NULL");
  $joinVF = $has_viewf ? "LEFT JOIN v_rapor_pts_deskripsi_final vf ON vf.pts_id=np.pts_id" : "";

  $selRPD  = $has_rpd ? "rpd.deskripsi_final" : "NULL AS deskripsi_final";
  $joinRPD = $has_rpd ? "LEFT JOIN rapor_pts_deskripsi rpd ON rpd.pts_id=np.pts_id" : "";

  $selNISN = $cNISN ? "s.`$cNISN` AS nisn" : "NULL AS nisn";

  $data = db_all($koneksi,"
    SELECT
      np.pts_id,
      s.`$cNama` AS nama,
      $selNISN,
      s.`$cNIS`  AS nis,
      np.nilai   AS nilai_rapor,
      $selRPD, $selVFO AS deskripsi_optimal, $selVFP AS deskripsi_perlu
    FROM nilai_pts np
    JOIN siswa s ON s.siswa_id=np.siswa_id
    $joinRPD
    $joinVF
    WHERE np.pts_set_id={$pts_set_id}
    ORDER BY s.`$cNama` ASC
  ");
}

/* ================== WRAPPER FALLBACK ================== */
echo '<div class="content-wrapper">'; // open
?>
<section class="content-header" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
  <span style="width:34px;height:34px;border-radius:10px;background:#E8F1FF;color:#0B57D0;display:inline-flex;align-items:center;justify-content:center">
    <i class="fa fa-book"></i>
  </span>
  <div>
    <h1 class="eps-title-h1" style="margin:0 0 4px;color:#111"><?= esc($page_title) ?></h1>
    <span class="eps-subbadge" style="display:inline-block;padding:3px 8px;border-radius:999px;background:#E8F7ED;color:#1B7D2E;border:1px solid #BEE5C8;font-size:12px">
      STS · data tersimpan (TA aktif · Semester <?= $SEM_AUTOMATIS ?>)
    </span>
  </div>
</section>

<section class="content">
  <style>
    /* Judul responsif */
    .eps-title-h1{font-weight:600;font-size:20px}
    @media(max-width:768px){ .eps-title-h1{font-size:16.5px} .eps-subbadge{font-size:11px} }

    /* Tema visual */
    .box-eps{background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(15,76,129,.10);overflow:hidden}
    .box-eps .box-hd{padding:10px 14px;background:linear-gradient(90deg,#0B57D0,#2D7AF0);color:#fff;display:flex;align-items:center;justify-content:space-between}
    .box-eps .box-hd .title{font-weight:700}
    .box-eps .box-bd{padding:12px 14px}
    .filter-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
    .filter-row .form-control{min-width:220px}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-weight:600;font-size:12px}
    .pill.info{background:#E8F1FF;color:#0B57D0;border:1px solid #CFE2FF}
    .pill.warn{background:#FFF7E6;color:#9A5B00;border:1px solid #FFE0AE}
    .pill.danger{background:#FEF2F2;color:#B91C1C;border:1px solid #FECACA}

    /* Tabel – tetap utuh, horizontal scroll saat layar kecil */
    .table-responsive-x{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
    table.table-eps{width:100%;border-collapse:separate;border-spacing:0;min-width:940px} /* min-width memicu scroll di HP */
    table.table-eps thead th{background:#0B57D0;color:#fff;font-weight:700;padding:10px;border-right:1px solid rgba(255,255,255,.15);font-size:13.5px}
    table.table-eps thead th:last-child{border-right:none}
    table.table-eps tbody td{padding:10px;border-bottom:1px solid #EEF2F7;vertical-align:middle;font-size:13.5px}
    table.table-eps tbody tr:nth-child(odd){background:#FCFDFF}
    table.table-eps tbody tr:hover{background:#F3F8FF}

    .td-nilai{font-weight:700}
    .td-desc{display:flex;align-items:center}
    .td-desc .desc-text{white-space:pre-line;line-height:1.45}

    .chip-warning{display:inline-block;padding:2px 8px;border-radius:999px;background:#FFF4E5;color:#9A5B00;border:1px solid #FFD9A6;font-weight:600;font-size:12px;margin-left:6px}
    .alert-info-eps{background:#E8F1FF;border:1px solid #CFE2FF;color:#0B57D0;border-radius:12px;padding:10px 12px;margin:10px 0}

    /* Mobile tweaks: kompak, tetap scroll horizontal, tidak ubah struktur tabel */
    @media(max-width:768px){
      .filter-row .form-control{min-width:100px; width:100%}
      .filter-row > div {flex: 1 1 100%;}
      table.table-eps thead th, table.table-eps tbody td{padding:8px 10px;font-size:12.5px}
      .td-nilai{font-size:12.5px}
    }

    body.sidebar-mini .content-wrapper{min-height:calc(100vh - 100px)}
    /* Tombol back-to-top */
    #backToTop{position:fixed;right:18px;bottom:22px;width:42px;height:42px;border:none;border-radius:50%;background:linear-gradient(180deg,#2D7AF0,#0B57D0);color:#fff;display:none;align-items:center;justify-content:center;box-shadow:0 10px 20px rgba(11,87,208,.3);cursor:pointer;z-index:9999}
    #backToTop i{font-size:18px}
    #backToTop:hover{transform:translateY(-2px)}
  </style>

  <div class="box-eps">
    <div class="box-hd">
      <div class="title">Filter Data</div>
      <div></div>
    </div>
    <div class="box-bd">
      <form method="get" class="filter-row">
        
        <div style="flex:1;">
          <label class="text-muted" style="font-size:12px">Pilih Semester</label>
          <select name="semester" class="form-control" onchange="this.form.submit()">
            <option value="1" <?= $SEM_AUTOMATIS === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
            <option value="2" <?= $SEM_AUTOMATIS === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
          </select>
        </div>

        <div style="flex:1;">
          <label class="text-muted" style="font-size:12px">Pilih Kelas</label>
          <select name="kelas_id" class="form-control" onchange="this.form.submit()">
            <?php if(empty($KELAS)): ?>
              <option value="">— Tidak ada kelas —</option>
            <?php else: ?>
              <?php foreach($KELAS as $id=>$nm): ?>
                <option value="<?= $id ?>" <?= $id===$kelas_id?'selected':'' ?>><?= esc($nm) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div style="flex:1;">
          <label class="text-muted" style="font-size:12px">Pilih Mapel</label>
          <select name="mapel_id" class="form-control" onchange="this.form.submit()">
            <?php if(empty($MAPEL)): ?>
              <option value="">— Tidak ada mapel —</option>
            <?php else: ?>
              <?php foreach($MAPEL as $id=>$nm): ?>
                <option value="<?= $id ?>" <?= $id===$mapel_id?'selected':'' ?>><?= esc($nm) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

      </form>
    </div>
  </div>

  <?php if($data): ?>
    <?php
      $jumlah_siswa = count($data);
      foreach($data as $r){
        $final = norm_desc($r['deskripsi_final'] ?? '');
        $opt   = norm_desc($r['deskripsi_optimal'] ?? '');
        $per   = norm_desc($r['deskripsi_perlu'] ?? '');
        if($final==='' && $opt==='' && $per==='') $emptyCount++;
      }
    ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 2px">
      <span class="pill info"><i class="fa fa-users"></i> Siswa: <b><?= (int)$jumlah_siswa ?></b></span>
      <span class="pill <?= $emptyCount? 'warn':'info' ?>">
        <i class="fa <?= $emptyCount? 'fa-exclamation-triangle':'fa-info-circle' ?>"></i>
        Deskripsi kosong: <b><?= (int)$emptyCount ?></b>
      </span>
    </div>

    <?php if($emptyCount>0): ?>
      <div class="alert-info-eps">
        <b>Info:</b> Ada <b><?= (int)$emptyCount ?></b> siswa yang <u>deskripsinya masih kosong</u>.
        Ini berarti guru mapel <b>belum men-generate</b> deskripsi di halaman <i>Input Nilai STS</i>.
        Saran: buka halaman Input Nilai STS untuk <b>kelas <?= esc($KELAS[$kelas_id]??'-') ?></b> dan
        <b>mapel <?= esc($MAPEL[$mapel_id]??'-') ?></b>, lalu klik <b>“Generate Otomatis”</b>
        atau isi manual sebelum cetak rapor.
      </div>
    <?php endif; ?>

    <div class="box-eps" style="margin-top:14px">
      <div class="box-hd">
        <div class="title">Rekap Nilai & Deskripsi</div>
        <div style="opacity:.9">
          Kelas: <b><?= esc($KELAS[$kelas_id]??'-') ?></b> &nbsp;•&nbsp;
          Mapel: <b><?= esc($MAPEL[$mapel_id]??'-') ?></b>
        </div>
      </div>
      <div class="box-bd" style="padding:0">
        <div class="table-responsive-x">
          <table class="table table-eps">
            <thead>
              <tr>
                <th style="width:64px;text-align:center">No</th>
                <th>Nama Siswa</th>
                <th style="width:160px">NISN</th>
                <th style="width:140px">NIS</th>
                <th style="width:110px">Nilai Rapor</th>
                <th style="min-width:520px">Deskripsi Ketercapaian Pembelajaran</th>
              </tr>
            </thead>
            <tbody>
              <?php $no=1; foreach($data as $r):
                $final = norm_desc($r['deskripsi_final'] ?? '');
                if($final===''){ $final = join_deskripsi_plain($r['deskripsi_optimal'] ?? '', $r['deskripsi_perlu'] ?? ''); }
                $final   = norm_desc($final);
                $isEmpty = ($final==='');
                $nilai_int = number_format((float)$r['nilai_rapor'], 0, ',', '.');
              ?>
              <tr>
                <td style="text-align:center"><?= $no++ ?></td>
                <td><?= esc($r['nama']) ?></td>
                <td><?= esc($r['nisn']) ?></td>
                <td><?= esc($r['nis']) ?></td>
                <td class="td-nilai"><?= $nilai_int ?></td>
                <td class="td-desc">
                  <?php if($isEmpty): ?>
                    <em class="text-muted">— deskripsi belum digenerate —</em>
                    <span class="chip-warning">Belum digenerate</span>
                  <?php else: ?>
                    <div class="desc-text"><?= esc($final) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="box-eps" style="margin-top:14px">
      <div class="box-hd"><div class="title">Rekap Nilai & Deskripsi</div></div>
      <div class="box-bd" style="text-align:center; padding: 40px 20px;">
        <i class="fa fa-folder-open-o" style="font-size: 48px; color: #cbd5e1; margin-bottom: 12px;"></i>
        <h4 style="color: #475569; font-weight: 700; margin-bottom: 8px;">Belum ada data nilai</h4>
        <p style="color: #64748b; font-size: 14px; margin-bottom:0;">
            Belum ada nilai yang diinput atau disimpan oleh Guru untuk <br>
            <b>Kelas <?= esc($KELAS[$kelas_id]??'-') ?> - Mapel <?= esc($MAPEL[$mapel_id]??'-') ?></b> pada <b>Semester <?= $SEM_AUTOMATIS ?></b>.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <button id="backToTop" title="Kembali ke atas"><i class="fa fa-arrow-up"></i></button>
  <script>
    (function(){
      var btn = document.getElementById('backToTop');
      window.addEventListener('scroll', function(){
        if (window.scrollY > 300) btn.style.display = 'flex';
        else btn.style.display = 'none';
      });
      btn.addEventListener('click', function(e){
        e.preventDefault();
        window.scrollTo({top:0, behavior:'smooth'});
      });
    })();
  </script>
</section>

<?php
echo '</div>'; // close content-wrapper (fallback)
include 'footer.php';