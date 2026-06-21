<?php
$PAGE_TITLE = 'Pengumuman';
include 'header.php';

if (!function_exists('h')) { function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('table_exists')) {
  function table_exists($koneksi,$name){
    $q = mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
    return $q && mysqli_num_rows($q)>0;
  }
}

$rows = [];
if (table_exists($koneksi,'pengumuman')) {
  $q = mysqli_query($koneksi,"SELECT id, judul, isi, mulai, sampai, audience
                              FROM pengumuman
                              WHERE (audience='all' OR audience='siswa')
                                AND (mulai IS NULL OR mulai<=CURDATE())
                                AND (sampai IS NULL OR sampai>=CURDATE())
                              ORDER BY id DESC LIMIT 50");
  while($r = mysqli_fetch_assoc($q)) $rows[] = $r;
}

// Tandai "Baru" bila mulai dalam 3 hari terakhir
$now = time();
?>
<style>
  .pg-fadein{opacity:0; transform:translateY(14px); animation:pgUp .5s ease forwards;}
  @keyframes pgUp{to{opacity:1; transform:none;}}

  .pg-hero{
    border-radius:16px; padding:20px 22px; color:#fff; position:relative; overflow:hidden;
    background:linear-gradient(135deg,#6366f1,#0ea5e9);
    box-shadow:0 12px 28px rgba(14,165,233,.22); margin-bottom:18px;
  }
  .pg-hero .ic-bg{position:absolute; right:-6px; bottom:-14px; font-size:96px; opacity:.16}
  .pg-hero h2{margin:0 0 4px; font-weight:800; font-size:22px}
  .pg-hero p{margin:0; opacity:.95; font-size:13.5px}
  .pg-hero .pg-count{
    display:inline-flex; align-items:center; gap:8px; margin-top:12px;
    background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.25);
    padding:6px 12px; border-radius:999px; font-weight:700; font-size:13px;
  }

  .pg-toolbar{margin-bottom:16px}
  .pg-search{
    position:relative; max-width:420px;
  }
  .pg-search input{
    width:100%; border:1px solid #e2e8f0; border-radius:999px;
    padding:10px 16px 10px 40px; font-size:14px; transition:box-shadow .2s, border-color .2s;
  }
  .pg-search input:focus{outline:none; border-color:#6366f1; box-shadow:0 0 0 4px rgba(99,102,241,.13)}
  .pg-search i{position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#94a3b8}

  .pg-card{
    background:#fff; border:1px solid #eef0f4; border-radius:14px; overflow:hidden;
    box-shadow:0 6px 18px rgba(15,23,42,.05); margin-bottom:14px;
    transition:transform .18s ease, box-shadow .18s ease;
  }
  .pg-card:hover{transform:translateY(-2px); box-shadow:0 12px 26px rgba(15,23,42,.10)}
  .pg-accent{height:5px; background:linear-gradient(90deg,#6366f1,#0ea5e9)}
  .pg-body{padding:16px 18px}
  .pg-card h4{margin:0 0 6px; font-weight:800; color:#0f172a; font-size:17px; display:flex; align-items:center; gap:8px; flex-wrap:wrap}
  .pg-badge-new{
    background:#fee2e2; color:#b91c1c; font-size:10.5px; font-weight:800;
    padding:2px 8px; border-radius:999px; letter-spacing:.4px; text-transform:uppercase;
  }
  .pg-meta{font-size:12px; color:#94a3b8; margin:0 0 10px; display:flex; align-items:center; gap:6px; flex-wrap:wrap}
  .pg-meta .dot{width:4px; height:4px; border-radius:50%; background:#cbd5e1}
  .pg-content{color:#334155; font-size:14px; line-height:1.6}

  .pg-empty{
    text-align:center; padding:48px 20px; color:#94a3b8;
    background:#fff; border:1px dashed #e2e8f0; border-radius:14px;
  }
  .pg-empty .ic{
    width:72px; height:72px; border-radius:50%; margin:0 auto 14px;
    background:linear-gradient(135deg,#eef2ff,#e0f2fe);
    display:flex; align-items:center; justify-content:center;
  }
  .pg-empty .ic i{font-size:30px; color:#6366f1}
  .pg-empty h4{color:#475569; font-weight:800; margin:0 0 4px}

  .pg-noresult{display:none; text-align:center; padding:28px; color:#94a3b8}

  @media(max-width:480px){
    .pg-hero h2{font-size:19px}
    .pg-hero .ic-bg{font-size:78px}
  }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1><i class="fas fa-bullhorn" style="color:#6366f1;margin-right:8px"></i>Pengumuman <small>Informasi untuk siswa</small></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><i class="fa fa-home"></i> Dashboard</a></li>
      <li class="active">Pengumuman</li>
    </ol>
  </section>

  <section class="content">

    <div class="pg-hero pg-fadein">
      <i class="fas fa-bullhorn ic-bg"></i>
      <h2>Papan Pengumuman</h2>
      <p>Informasi terbaru dari sekolah yang berlaku khusus untuk siswa.</p>
      <div class="pg-count">
        <i class="fas fa-layer-group"></i>
        <?= count($rows) ?> pengumuman aktif
      </div>
    </div>

    <?php if (empty($rows)): ?>
      <div class="pg-empty pg-fadein">
        <div class="ic"><i class="fas fa-inbox"></i></div>
        <h4>Belum ada pengumuman aktif</h4>
        <p>Pengumuman dari sekolah akan tampil di sini saat dipublikasikan.</p>
      </div>
    <?php else: ?>

      <div class="pg-toolbar pg-fadein">
        <div class="pg-search">
          <i class="fas fa-search"></i>
          <input type="text" id="pgSearch" placeholder="Cari pengumuman&hellip;" aria-label="Cari pengumuman">
        </div>
      </div>

      <div id="pgList">
        <?php foreach ($rows as $i => $p):
          $isNew = !empty($p['mulai']) && (($now - strtotime($p['mulai'])) <= 3*86400) && (strtotime($p['mulai']) <= $now);
          $blob = strtolower(($p['judul'] ?? '').' '.($p['isi'] ?? ''));
        ?>
        <div class="pg-card pg-fadein pg-item" data-search="<?= h($blob) ?>" style="animation-delay:<?= min($i*60,360) ?>ms">
          <div class="pg-accent"></div>
          <div class="pg-body">
            <h4>
              <?= h($p['judul']) ?>
              <?php if ($isNew): ?><span class="pg-badge-new">Baru</span><?php endif; ?>
            </h4>
            <p class="pg-meta">
              <i class="far fa-calendar-alt"></i>
              <span>Berlaku: <?= $p['mulai'] ? date('d M Y', strtotime($p['mulai'])) : '—' ?> s/d <?= $p['sampai'] ? date('d M Y', strtotime($p['sampai'])) : '—' ?></span>
            </p>
            <div class="pg-content"><?= nl2br(h($p['isi'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="pg-noresult" id="pgNoResult">
        <i class="fas fa-search" style="font-size:24px;display:block;margin-bottom:8px"></i>
        Tidak ada pengumuman yang cocok dengan pencarian.
      </div>

    <?php endif; ?>

  </section>
</div>

<script>
(function(){
  var input = document.getElementById('pgSearch');
  if (!input) return;
  var items = Array.prototype.slice.call(document.querySelectorAll('.pg-item'));
  var noRes = document.getElementById('pgNoResult');

  input.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    var shown = 0;
    items.forEach(function(el){
      var match = !q || (el.getAttribute('data-search') || '').indexOf(q) !== -1;
      el.style.display = match ? '' : 'none';
      if (match) shown++;
    });
    noRes.style.display = shown === 0 ? 'block' : 'none';
  });
})();
</script>

<?php include 'footer.php'; ?>
