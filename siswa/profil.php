<!-- ====== BOX: Foto Profil ====== -->
<section class="content">
  <?php
    $id_siswa = (int)$_SESSION['id'];
    $p = mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT siswa_nama, siswa_foto FROM siswa WHERE siswa_id=$id_siswa"));
    $foto_now = !empty($p['siswa_foto']) ? "gambar/siswa/".htmlspecialchars($p['siswa_foto'],ENT_QUOTES,'UTF-8') : "gambar/sistem/user.png";
  ?>
  <div class="row">
    <div class="col-lg-6">
      <div class="box box-primary" style="border-radius:12px;">
        <div class="box-header with-border">
          <h3 class="box-title">Foto Profil</h3>
        </div>
        <div class="box-body">
          <div class="media" style="align-items:center;">
            <img src="../<?= $foto_now ?>" class="img-circle" style="width:80px;height:80px;object-fit:cover;margin-right:16px" alt="Foto">
            <div class="media-body">
              <p style="margin:0;"><strong><?= htmlspecialchars($p['siswa_nama'] ?? 'Siswa') ?></strong></p>
              <small>Format: JPG/PNG/GIF, maks 2MB. Disarankan rasio 1:1.</small>
            </div>
          </div>
          <hr>
          <form action="profil_update.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
              <label>Ganti Foto</label>
              <input type="file" name="foto" accept="image/*" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Foto</button>
            <?php if (!empty($p['siswa_foto'])): ?>
              <a href="profil_update.php?hapus=1" class="btn btn-default" onclick="return confirm('Hapus foto saat ini?')">
                <i class="fa fa-trash"></i> Hapus Foto
              </a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
