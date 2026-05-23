<?php include 'header.php'; ?>
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!-- ====== STYLE ====== -->
<style>
  :root{ --accent:#00c48c; --danger:#ff4757; }

  .box.box-primary{border:0;border-radius:16px;overflow:hidden;box-shadow:0 10px 24px rgba(0,0,0,.08)}
  .box.box-primary .box-header{
    background:linear-gradient(135deg,#6a11cb,#2575fc);color:#fff;padding:14px 20px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  }
  .left-head{display:flex;align-items:center;gap:10px;min-width:0;flex:1 1 auto;}
  .header-actions{margin-left:auto;flex:0 0 auto;}
  .header-actions .btn{white-space:nowrap;}
  .box-title{margin:0;font-weight:700;letter-spacing:.3px;display:flex;align-items:center;gap:8px}

  .form-group label{font-weight:600;color:#334155}
  .help-inline{display:block;color:#607d8b;font-size:12px;margin-top:6px}
  .input-group .input-group-addon{background:#f8fafc;border-color:#e2e8f0;color:#64748b}
  .form-control{border-radius:10px;border-color:#e2e8f0;box-shadow:none;transition:.2s ease}
  .form-control:focus{border-color:#9aa7ff;box-shadow:0 0 0 3px rgba(59,91,253,.12)}
  .btn-outline{background:#fff;border:1px solid #cbd5e1;color:#334155}
  .btn-outline:hover{border-color:#94a3b8;background:#f8fafc}
  .btn-gradient{background:linear-gradient(135deg,var(--accent),#2ecc71);color:#fff;border:none}
  .btn-gradient:hover{opacity:.94}
  .req{display:inline-block;background:#ffe8e8;color:#e74c3c;border-radius:6px;font-size:11px;padding:2px 6px;margin-left:6px}

  /* ====== RADIO PILLS ====== */
  .choice-group{display:flex;flex-wrap:wrap;gap:8px}
  .choice-pill{
    position:relative; display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:999px; border:1px solid #cbd5e1;
    background:#fff; color:#334155; cursor:pointer; font-weight:600; user-select:none;
    transition:.15s ease;
  }
  .choice-pill:hover{background:#f8fafc;border-color:#94a3b8}
  .choice-pill input{ /* radio asli: tetap fokusable tapi tak terlihat */
    position:absolute; inset:0; opacity:0; cursor:pointer; margin:0;
  }
  .choice-pill .mark{width:18px;height:18px;border-radius:4px;border:2px solid #94a3b8;display:inline-block;flex:0 0 auto}
  .choice-pill .text{line-height:1}
  .choice-pill.is-active{background:#eef7ff;border-color:#7c9cff;color:#1e40af;box-shadow:0 0 0 3px rgba(124,156,255,.15)}
  .choice-pill.is-active .mark{background:#3b82f6;border-color:#3b82f6}

  /* Warna aksen khusus status saat aktif */
  .choice-pill.status-aktif.is-active{background:#eafaf1;border-color:#4ade80;color:#166534}
  .choice-pill.status-tamat.is-active{background:#eaf2ff;border-color:#60a5fa;color:#1e3a8a}
  .choice-pill.status-pindah.is-active{background:#f3e8ff;border-color:#c084fc;color:#581c87}
  .choice-pill.status-dikeluarkan.is-active{background:#fee2e2;border-color:#f87171;color:#7f1d1d}

  /* Meter password */
  .pw-meter{height:8px;border-radius:8px;background:#e9eef5;overflow:hidden;margin-top:8px}
  .pw-meter-fill{height:100%;width:0%;transition:width .3s ease,background .3s ease;background:#ff6b6b}
  .pw-tips{font-size:12px;color:#607d8b;margin-top:6px}
  .caps-indicator{font-size:11px;color:#d35400;display:none;margin-top:4px}

  /* Dropzone */
  .dropzone{border:2px dashed #b6c3ff;background:#f8faff;border-radius:14px;padding:18px;text-align:center;cursor:pointer;transition:.2s}
  .dropzone.dragover{background:#eef2ff;border-color:#7c8cff}
  .dz-icon{font-size:36px;margin-bottom:6px;color:#4a6cff}
  .preview-wrap{display:flex;align-items:center;gap:12px;margin-top:10px}
  .preview-wrap img{width:64px;height:64px;border-radius:10px;object-fit:cover;border:1px solid #e2e8f0}
  .file-name{font-size:12px;color:#475569}
  .file-err{font-size:12px;color:var(--danger);display:none;margin-top:6px}
  .note-muted{color:#708090;font-size:12px}
  .action-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>

<div class="content-wrapper">
  <section class="content-header">
    <h1>Siswa <small>Tambah Siswa Baru</small></h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Dashboard</li>
    </ol>
  </section>

  <section class="content">
    <div class="row">
      <section class="col-lg-8">
        <div class="box box-primary">

          <div class="box-header">
            <div class="left-head">
              <i class="fa fa-user-plus" style="font-size:20px;"></i>
              <h3 class="box-title">Tambah Siswa Baru</h3>
            </div>
            <div class="header-actions">
              <a href="siswa.php" class="btn btn-sm btn-outline" style="background:#ffffff22;color:#fff;border:1px solid #ffffff55;">
                <i class="fa fa-reply"></i> &nbsp;Kembali
              </a>
            </div>
          </div>

          <div class="box-body">
            <form action="siswa_act.php" method="post" enctype="multipart/form-data" id="form-tambah" novalidate>

              <!-- NAMA -->
              <div class="form-group">
                <label>Nama <span class="req">wajib</span></label>
                <div class="input-group">
                  <span class="input-group-addon"><i class="fa fa-id-card-o"></i></span>
                  <input type="text" class="form-control" name="nama" required
                         placeholder="Masukkan Nama Lengkap .." autocomplete="name" maxlength="100"
                         aria-describedby="help-nama">
                </div>
                <small id="help-nama" class="help-inline">Gunakan huruf & spasi (otomatis merapikan spasi berlebih saat simpan).</small>
              </div>

              <!-- NIS -->
              <div class="form-group">
                <label>NIS <span class="req">wajib</span></label>
                <div class="input-group">
                  <span class="input-group-addon"><i class="fa fa-hashtag"></i></span>
                  <input type="text" class="form-control" name="nis" required
                         inputmode="numeric" pattern="[0-9]{1,}" maxlength="25"
                         placeholder="Masukkan NIS .." aria-describedby="help-nis">
                </div>
                <small id="help-nis" class="help-inline">Hanya angka. Leading zero (jika ada) tetap disimpan.</small>
              </div>

              <!-- JURUSAN / TINGKAT KELAS (RADIO PILLS) -->
              <div class="form-group">
                <label>Jurusan / Tingkat Kelas <span class="req">wajib</span></label>
                <div class="choice-group" id="jurusan-group" aria-label="Pilih jurusan / tingkat kelas">
                  <?php
                  // jurusan(jurusan_id, jurusan_nama) -> kirim jurusan_id ke kolom siswa_jurusan (varchar) :contentReference[oaicite:1]{index=1}
                  $qJur = mysqli_query($koneksi, "SELECT jurusan_id, jurusan_nama FROM jurusan ORDER BY jurusan_nama ASC");
                  if($qJur && mysqli_num_rows($qJur)>0):
                    $first = true;
                    while($row = mysqli_fetch_assoc($qJur)):
                      $id   = (int)$row['jurusan_id'];
                      $nama = htmlspecialchars($row['jurusan_nama'], ENT_QUOTES, 'UTF-8');
                  ?>
                    <label class="choice-pill">
                      <input type="radio" name="jurusan" value="<?php echo $id; ?>" <?php echo $first?'required':''; $first=false; ?>>
                      <span class="mark" aria-hidden="true"></span>
                      <span class="text"><?php echo $nama; ?></span>
                    </label>
                  <?php
                    endwhile;
                  else:
                    echo '<div class="note-muted">Data Jurusan/Tingkat Kelas belum ada.</div>';
                    if(!$qJur){ echo "<script>console.error('SQL jurusan error: ".mysqli_error($koneksi)."');</script>"; }
                  endif;
                  ?>
                </div>
                <small class="help-inline">Klik salah satu jurusan / tingkat kelas.</small>
              </div>

              <!-- PASSWORD -->
              <div class="form-group">
                <label>Password <span class="req">wajib</span></label>
                <div class="input-group">
                  <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                  <input type="password" class="form-control" name="password" id="password"
                         required minlength="8" maxlength="128"
                         placeholder="Minimal 8 karakter" aria-describedby="pw-help">
                  <span class="input-group-btn" style="width:1%;">
                    <button class="btn btn-default" type="button" id="btn-toggle-pw" title="Tampilkan/sembunyikan">
                      <i class="fa fa-eye"></i>
                    </button>
                  </span>
                </div>
                <div class="pw-meter" aria-hidden="true"><div class="pw-meter-fill" id="pw-meter"></div></div>
                <div class="pw-tips" id="pw-tips">Gunakan kombinasi huruf besar, kecil, angka, & simbol.</div>
                <div class="caps-indicator" id="caps-indicator"><i class="fa fa-warning"></i> Caps Lock aktif.</div>
                <div class="action-bar" style="margin-top:8px;">
                  <button type="button" class="btn btn-outline btn-xs" id="btn-gen-pw" title="Buat password acak kuat">
                    <i class="fa fa-magic"></i> Generate
                  </button>
                  <button type="button" class="btn btn-outline btn-xs" id="btn-copy-pw" title="Salin password">
                    <i class="fa fa-clipboard"></i> Salin
                  </button>
                </div>
              </div>

              <!-- KONFIRMASI PASSWORD -->
              <div class="form-group">
                <label>Konfirmasi Password <span class="req">wajib</span></label>
                <div class="input-group">
                  <span class="input-group-addon"><i class="fa fa-unlock-alt"></i></span>
                  <input type="password" class="form-control" id="confirm_password" placeholder="Ulangi password" required>
                </div>
                <small id="confirm-help" class="help-inline">Harus sama persis dengan password di atas.</small>
              </div>

              <!-- STATUS SISWA (RADIO PILLS) -->
              <div class="form-group">
                <label>Status siswa <span class="req">wajib</span></label>
                <div class="choice-group" id="status-group" aria-label="Pilih status siswa">
                  <?php
                  $status_list = [
                    'aktif'       => ['label'=>'Aktif',       'cls'=>'status-aktif'],
                    'tamat'       => ['label'=>'Tamat',       'cls'=>'status-tamat'],
                    'pindah'      => ['label'=>'Pindah',      'cls'=>'status-pindah'],
                    'dikeluarkan' => ['label'=>'Dikeluarkan', 'cls'=>'status-dikeluarkan'],
                  ];
                  $first = true;
                  foreach($status_list as $val=>$meta):
                  ?>
                    <label class="choice-pill <?php echo $meta['cls']; ?>">
                      <input type="radio" name="status" value="<?php echo $val; ?>" <?php echo $first?'required':''; $first=false; ?>>
                      <span class="mark" aria-hidden="true"></span>
                      <span class="text"><?php echo $meta['label']; ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <small class="help-inline">Pilih salah satu status.</small>
              </div>

              <!-- FOTO -->
              <div class="form-group">
                <label>Foto (opsional)</label>
                <div class="dropzone" id="dropzone">
                  <div class="dz-icon"><i class="fa fa-cloud-upload"></i></div>
                  <div><strong>Drag & Drop</strong> foto ke sini, atau <u>klik untuk pilih</u>.</div>
                  <div class="note-muted">Format: JPG/PNG/GIF • Maks 2 MB • Rasio 1:1 disarankan</div>
                  <input type="file" name="foto" accept="image/*" class="form-control" id="foto-input" style="display:none;">
                </div>
                <div class="preview-wrap" id="preview-wrap" style="display:none;">
                  <img id="preview-img" alt="Preview foto">
                  <div>
                    <div class="file-name" id="file-name">nama-file.jpg</div>
                    <button type="button" class="btn btn-xs btn-outline" id="btn-ganti-foto"><i class="fa fa-refresh"></i> Ganti</button>
                    <button type="button" class="btn btn-xs btn-outline" id="btn-hapus-foto"><i class="fa fa-trash"></i> Hapus</button>
                  </div>
                </div>
                <div class="file-err" id="file-err"><i class="fa fa-times-circle"></i> File tidak valid. Pastikan format gambar & ukuran ≤ 2 MB.</div>
              </div>

              <!-- ACTIONS -->
              <div class="form-group" style="margin-top:18px;">
                <div class="action-bar">
                  <button type="submit" class="btn btn-gradient" id="btn-submit">
                    <i class="fa fa-save"></i> Simpan (Ctrl+S)
                  </button>
                  <button type="reset" class="btn btn-outline" id="btn-reset">
                    <i class="fa fa-undo"></i> Reset
                  </button>
                  <a href="siswa.php" class="btn btn-outline">
                    <i class="fa fa-reply"></i> Kembali
                  </a>
                </div>
                <small class="help-inline">Data akan divalidasi terlebih dahulu sebelum dikirim.</small>
              </div>

            </form>
          </div>

        </div>
      </section>

      <!-- Panel tips kanan -->
      <section class="col-lg-4">
        <div class="box" style="border-radius:16px;overflow:hidden;box-shadow:0 10px 24px rgba(0,0,0,.06)">
          <div class="box-header" style="background:linear-gradient(135deg,#ff9a9e,#fad0c4);color:#fff;">
            <div class="left-head">
              <i class="fa fa-lightbulb-o" style="font-size:18px;"></i>
              <h3 class="box-title" style="margin:0;">Tips Input Cepat</h3>
            </div>
          </div>
          <div class="box-body">
            <ul style="padding-left:18px;line-height:1.7">
              <li><b>Ctrl + S</b> untuk Simpan.</li>
              <li>Klik badge untuk memilih jurusan & status.</li>
              <li>Password minimal <b>8 karakter</b> — gunakan <b>Generate</b>.</li>
              <li>Upload foto: cukup <b>drag & drop</b>.</li>
            </ul>
          </div>
        </div>
      </section>

    </div>
  </section>
</div>

<!-- ====== LIBRARIES (CDN) ====== -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
(function(){
  'use strict';

  /* ====== Aktif/nonaktifkan tampilan pill saat radio berubah ====== */
  function wirePillRadios(groupSelector){
    const group = document.querySelector(groupSelector);
    if(!group) return;
    const update = () => {
      group.querySelectorAll('.choice-pill').forEach(lbl=>{
        const inp = lbl.querySelector('input[type="radio"]');
        lbl.classList.toggle('is-active', inp && inp.checked);
      });
    };
    group.addEventListener('change', (e)=>{
      if(e.target && e.target.matches('input[type="radio"]')) update();
    });
    // dukung klik di seluruh label (input sudah full-size opacity:0)
    group.addEventListener('click', (e)=>{
      const lbl = e.target.closest('.choice-pill');
      if(!lbl) return;
      const inp = lbl.querySelector('input[type="radio"]');
      if(inp){ inp.checked = true; inp.dispatchEvent(new Event('change', {bubbles:true})); }
    });
    // inisiasi awal (kalau ada default)
    update();
  }
  wirePillRadios('#jurusan-group');
  wirePillRadios('#status-group');

  // NIS angka saja & rapikan nama
  $('[name="nis"]').on('input', function(){ this.value = this.value.replace(/\D+/g,''); });
  $('[name="nama"]').on('blur',  function(){ this.value = this.value.replace(/\s+/g,' ').trim(); });

  // Password show/hide
  $('#btn-toggle-pw').on('click', function(){
    const inp = $('#password')[0];
    inp.type = (inp.type === 'password') ? 'text' : 'password';
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });

  // Meter password
  const pwInput = document.getElementById('password');
  const pwMeter = document.getElementById('pw-meter');
  const pwTips  = document.getElementById('pw-tips');
  const capsInd = document.getElementById('caps-indicator');
  function updateStrength(pw){
    let score = 0;
    if (pw && typeof zxcvbn === 'function'){ score = zxcvbn(pw).score; }
    else { score = (pw.length >= 12) ? 3 : (pw.length >= 8 ? 2 : 1);
           if(/[A-Z]/.test(pw)&&/[a-z]/.test(pw)&&/\d/.test(pw)&&/[^A-Za-z0-9]/.test(pw)) score++; }
    const widths=['8%','25%','50%','75%','100%'], colors=['#ff6b6b','#ffa502','#f1c40f','#2ed573','#2ecc71'];
    pwMeter.style.width = widths[score]; pwMeter.style.background = colors[score];
    const labels=['Sangat lemah','Lemah','Cukup','Kuat','Sangat kuat'];
    pwTips.textContent = 'Kekuatan password: ' + labels[score] + '. Gunakan huruf besar, kecil, angka, & simbol.';
  }
  pwInput.addEventListener('input', function(){ updateStrength(this.value); validateConfirm(); });
  pwInput.addEventListener('keyup', function(e){
    capsInd.style.display = (e.getModifierState && e.getModifierState('CapsLock')) ? 'block' : 'none';
  });

  // Generate / Copy password
  $('#btn-gen-pw').on('click', function(){
    const p = genStrongPassword(14);
    $('#password').val(p).trigger('input');
    $('#confirm_password').val('').focus();
  });
  function genStrongPassword(len){
    const U='ABCDEFGHJKLMNPQRSTUVWXYZ', L='abcdefghijkmnopqrstuvwxyz', N='23456789', S='!@#$%^&*()-_=+[]{}:,.?';
    let all=U+L+N+S, out=U[Math.random()*U.length|0]+L[Math.random()*L.length|0]+N[Math.random()*N.length|0]+S[Math.random()*S.length|0];
    for(let i=4;i<len;i++) out+=all[Math.random()*all.length|0];
    return out.split('').sort(()=>.5-Math.random()).join('');
  }
  $('#btn-copy-pw').on('click', async function(){
    const val = $('#password').val();
    if(!val){ Swal.fire({icon:'info',title:'Tidak ada password',timer:1500,showConfirmButton:false}); return; }
    try{ await navigator.clipboard.writeText(val);
      Swal.fire({icon:'success',title:'Password disalin',timer:1300,showConfirmButton:false});
    }catch(e){ window.prompt('Salin secara manual:', val); }
  });

  // Konfirmasi password
  function validateConfirm(){
    const pw=$('#password').val(), cf=$('#confirm_password').val(), help=$('#confirm-help');
    if(!cf){ help.text('Harus sama persis dengan password di atas.').css('color','#607d8b'); return true; }
    if(pw===cf){ help.text('Cocok.').css('color','#2ecc71'); return true; }
    help.text('Tidak cocok.').css('color','#e74c3c'); return false;
  }
  $('#confirm_password').on('input', validateConfirm);

  // Dropzone upload
  const dz=document.getElementById('dropzone'), fotoInput=document.getElementById('foto-input'),
        previewWrap=document.getElementById('preview-wrap'), previewImg=document.getElementById('preview-img'),
        fileNameEl=document.getElementById('file-name'), fileErr=document.getElementById('file-err');
  function showPreview(file){ const url=URL.createObjectURL(file); previewImg.src=url; fileNameEl.textContent=file.name+' • '+(file.size/1024).toFixed(0)+' KB'; previewWrap.style.display='flex'; }
  function clearPreview(){ fotoInput.value=''; previewWrap.style.display='none'; previewImg.src=''; fileNameEl.textContent=''; }
  function validateImage(file){ if(!file) return true; const okType=/image\/(jpeg|png|gif)/.test(file.type), okSize=file.size<=2*1024*1024; fileErr.style.display=(okType&&okSize)?'none':'block'; return okType&&okSize; }
  dz.addEventListener('click', ()=> fotoInput.click());
  dz.addEventListener('dragover', (e)=>{ e.preventDefault(); dz.classList.add('dragover'); });
  dz.addEventListener('dragleave', ()=> dz.classList.remove('dragover'));
  dz.addEventListener('drop', (e)=>{ e.preventDefault(); dz.classList.remove('dragover'); const file=e.dataTransfer.files[0]; if(validateImage(file)){ const dt=new DataTransfer(); dt.items.add(file); fotoInput.files=dt.files; showPreview(file);} });
  fotoInput.addEventListener('change', function(){ const file=this.files[0]; if(validateImage(file)){ showPreview(file);} else{ clearPreview(); } });
  $('#btn-ganti-foto').on('click', ()=> fotoInput.click());
  $('#btn-hapus-foto').on('click', ()=> clearPreview());

  // Ctrl+S submit
  document.addEventListener('keydown', function(e){ if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){ e.preventDefault(); $('#btn-submit').trigger('click'); } });

  // Submit + validasi
  $('#form-tambah').on('submit', function(e){
    e.preventDefault();
    const form=this;
    if(!form.checkValidity()){ form.reportValidity(); return; }
    if(!validateConfirm()){
      Swal.fire({icon:'error', title:'Konfirmasi password belum cocok', text:'Mohon samakan password Anda.'}); return;
    }
    const f=fotoInput.files[0]; if(f && !validateImage(f)){
      Swal.fire({icon:'error', title:'Foto tidak valid', text:'Gunakan JPG/PNG/GIF, ukuran maksimal 2 MB.'}); return;
    }
    Swal.fire({title:'Simpan data siswa?', html:'<div style="font-size:13px;color:#607d8b">Pastikan data sudah benar.</div>', icon:'question', showCancelButton:true, confirmButtonText:'Simpan', cancelButtonText:'Batal'})
    .then((res)=>{ if(res.isConfirmed){
      const nama=form.querySelector('[name="nama"]');
      nama.value=(nama.value||'').replace(/\s+/g,' ').trim();
      $('#btn-submit').prop('disabled',true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
      form.submit(); // akan mengirim: nama, nis, jurusan, status, password, (foto)
    }});
  });

})();
</script>

<?php include 'footer.php'; ?>
