<?php include 'header.php'; ?>
<div class="content-wrapper">
  <section class="content-header">
    <h1>Pengumuman <small>Informasi untuk siswa</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Pengumuman</li>
    </ol>
  </section>
  <section class="content">
<?php
function table_exists($koneksi,$name){
  $q = mysqli_query($koneksi,"SHOW TABLES LIKE '".mysqli_real_escape_string($koneksi,$name)."'");
  return $q && mysqli_num_rows($q)>0;
}
$rows = [];
if(table_exists($koneksi,'pengumuman')){
  $q = mysqli_query($koneksi,"SELECT id, judul, isi, mulai, sampai, audience
                              FROM pengumuman
                              WHERE (audience='all' OR audience='siswa')
                                AND (mulai IS NULL OR mulai<=CURDATE())
                                AND (sampai IS NULL OR sampai>=CURDATE())
                              ORDER BY id DESC LIMIT 50");
  while($r=mysqli_fetch_assoc($q)) $rows[]=$r;
}
?>
<div class="box box-primary" style="border-radius:12px;">
  <div class="box-header with-border"><h3 class="box-title">Daftar Pengumuman</h3></div>
  <div class="box-body">
    <?php if(empty($rows)){ ?>
      <div class="text-center text-muted" style="padding:20px;"><i class="fa fa-bullhorn fa-2x"></i><br>Belum ada pengumuman aktif.</div>
    <?php } else { foreach($rows as $p){ ?>
      <div class="callout callout-info">
        <h4 style="margin-top:0;"><?php echo htmlspecialchars($p['judul']); ?></h4>
        <p><small>Berlaku: <?php echo $p['mulai']?date('d M Y',strtotime($p['mulai'])):'—'; ?> s/d <?php echo $p['sampai']?date('d M Y',strtotime($p['sampai'])):'—'; ?></small></p>
        <div><?php echo nl2br(htmlspecialchars($p['isi'])); ?></div>
      </div>
    <?php } } ?>
  </div>
</div>
  </section>
</div>
<?php include 'footer.php'; ?>