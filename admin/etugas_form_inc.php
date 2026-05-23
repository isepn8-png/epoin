<?php
/**
 * Shared form fields for etugas_tambah / etugas_edit.
 * Expects: $formData, $formErrors, $matrix, $isEdit
 */
$fd = is_array($formData ?? null) ? $formData : [];
$fe = is_array($formErrors ?? null) ? $formErrors : [];
$matrix = is_array($matrix ?? null) ? $matrix : ['ta' => [], 'kelas' => [], 'mapel' => [], 'combos' => []];
$isEdit = !empty($isEdit);

$selectedKelasIds = [];
if (!$isEdit) {
    if (!empty($fd['kelas_ids']) && is_array($fd['kelas_ids'])) {
        foreach ($fd['kelas_ids'] as $kid) {
            $kid = (int) $kid;
            if ($kid > 0) {
                $selectedKelasIds[$kid] = true;
            }
        }
    } elseif (!empty($fd['kelas_id'])) {
        $selectedKelasIds[(int) $fd['kelas_id']] = true;
    }
}
?>
<style>
.etugas-kelas-panel { border:1px solid #e2e8f0; border-radius:8px; padding:12px; background:#f8fafc; max-height:280px; overflow-y:auto; }
.etugas-kelas-grid { display:flex; flex-wrap:wrap; gap:6px 16px; margin:0; padding:0; list-style:none; }
.etugas-kelas-grid li { min-width:140px; }
.etugas-kelas-grid label { font-weight:normal; margin:0; cursor:pointer; }
.etugas-kelas-toolbar { margin-bottom:10px; }
.etugas-kelas-toolbar .btn { margin-right:6px; margin-bottom:4px; }
.etugas-kelas-count { font-size:12px; color:#64748b; margin-top:8px; }
@media (max-width:768px) { .etugas-kelas-grid li { min-width:100%; } }
</style>

<div class="row etugas-form-row">
  <div class="col-md-6">
    <div class="form-group<?= isset($fe['ta_id']) ? ' has-error' : '' ?>">
      <label for="ta_id">Tahun ajaran <span class="text-danger" aria-hidden="true">*</span></label>
      <select class="form-control" id="ta_id" name="ta_id" required aria-required="true"
              aria-describedby="ta_id_help<?= isset($fe['ta_id']) ? ' ta_id_err' : '' ?>">
        <option value="">— Pilih tahun ajaran —</option>
        <?php foreach ($matrix['ta'] as $t): ?>
        <option value="<?= (int) $t['ta_id'] ?>"
          <?= ((int)($fd['ta_id'] ?? 0) === (int)$t['ta_id']) ? 'selected' : '' ?>>
          <?= etugas_h($t['ta_nama']) ?><?= ($t['ta_status'] ?? '') == '1' ? ' (aktif)' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <p id="ta_id_help" class="help-block">Tugas dikaitkan ke tahun ajaran rombel.</p>
      <?php if (isset($fe['ta_id'])): ?><p id="ta_id_err" class="help-block text-danger"><?= etugas_h($fe['ta_id']) ?></p><?php endif; ?>
    </div>

    <?php if ($isEdit): ?>
    <div class="form-group<?= isset($fe['kelas_id']) ? ' has-error' : '' ?>">
      <label for="kelas_id">Kelas <span class="text-danger" aria-hidden="true">*</span></label>
      <select class="form-control" id="kelas_id" name="kelas_id" required aria-required="true">
        <option value="">— Pilih kelas —</option>
        <?php foreach ($matrix['kelas'] as $k): ?>
        <option value="<?= (int) $k['kelas_id'] ?>"
          data-ta="<?= (int) ($k['kelas_ta'] ?? 0) ?>"
          <?= ((int)($fd['kelas_id'] ?? 0) === (int)$k['kelas_id']) ? 'selected' : '' ?>>
          <?= etugas_h($k['kelas_nama']) ?>
          <?php if (!empty($k['jurusan_nama'])): ?> (<?= etugas_h($k['jurusan_nama']) ?>)<?php endif; ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($fe['kelas_id'])): ?><p class="help-block text-danger"><?= etugas_h($fe['kelas_id']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="form-group<?= isset($fe['mapel_id']) ? ' has-error' : '' ?>">
      <label for="mapel_id">Mata pelajaran <span class="text-danger" aria-hidden="true">*</span></label>
      <select class="form-control" id="mapel_id" name="mapel_id" required aria-required="true"
              <?= $isEdit ? '' : 'aria-describedby="mapel_id_help"' ?>>
        <option value="">— Pilih mapel —</option>
        <?php foreach ($matrix['mapel'] as $m): ?>
        <option value="<?= (int) $m['mapel_id'] ?>"
          <?= ((int)($fd['mapel_id'] ?? 0) === (int)$m['mapel_id']) ? 'selected' : '' ?>>
          <?= etugas_h($m['mapel_kode'] ?? '') ?> — <?= etugas_h($m['mapel_nama']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if (!$isEdit): ?>
      <p id="mapel_id_help" class="help-block">Pilih mapel terlebih dahulu, lalu pilih kelas tujuan di bawah.</p>
      <?php endif; ?>
      <?php if (isset($fe['mapel_id'])): ?><p class="help-block text-danger"><?= etugas_h($fe['mapel_id']) ?></p><?php endif; ?>
      <?php if (isset($fe['allow'])): ?><p class="help-block text-danger"><?= etugas_h($fe['allow']) ?></p><?php endif; ?>
    </div>

    <?php if (!$isEdit): ?>
    <div class="form-group<?= isset($fe['kelas_ids']) ? ' has-error' : '' ?>" id="etugas_kelas_field">
      <label>Kelas tujuan <span class="text-danger" aria-hidden="true">*</span></label>
      <p class="help-block" style="margin-top:0">
        Pilih satu atau beberapa kelas. Sistem akan membuat tugas yang sama untuk setiap kelas yang dipilih.
      </p>
      <div class="etugas-kelas-toolbar">
        <button type="button" class="btn btn-default btn-xs" id="etugas_kelas_select_all">
          <i class="fa fa-check-square-o"></i> Pilih semua
        </button>
        <button type="button" class="btn btn-default btn-xs" id="etugas_kelas_clear_all">
          <i class="fa fa-square-o"></i> Hapus pilihan
        </button>
      </div>
      <div class="etugas-kelas-panel" role="group" aria-labelledby="etugas_kelas_legend">
        <span id="etugas_kelas_legend" class="sr-only">Daftar kelas tujuan</span>
        <ul class="etugas-kelas-grid" id="etugas_kelas_grid">
          <?php foreach ($matrix['kelas'] as $k):
            $kid = (int) $k['kelas_id'];
            $checked = !empty($selectedKelasIds[$kid]);
          ?>
          <li class="etugas-kelas-item" data-ta="<?= (int) ($k['kelas_ta'] ?? 0) ?>" data-kelas="<?= $kid ?>">
            <label>
              <input type="checkbox" name="kelas_ids[]" value="<?= $kid ?>"
                     class="etugas-kelas-cb" <?= $checked ? 'checked' : '' ?>>
              <?= etugas_h($k['kelas_nama']) ?>
              <?php if (!empty($k['jurusan_nama'])): ?>
                <span class="text-muted">(<?= etugas_h($k['jurusan_nama']) ?>)</span>
              <?php endif; ?>
            </label>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="etugas-kelas-count" id="etugas_kelas_count" aria-live="polite">0 kelas dipilih</p>
      </div>
      <?php if (isset($fe['kelas_ids'])): ?><p class="help-block text-danger"><?= etugas_h($fe['kelas_ids']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="form-group<?= isset($fe['judul']) ? ' has-error' : '' ?>">
      <label for="judul">Judul tugas <span class="text-danger" aria-hidden="true">*</span></label>
      <input type="text" class="form-control" id="judul" name="judul" maxlength="200" required
             value="<?= etugas_h($fd['judul'] ?? '') ?>" placeholder="Contoh: Tugas praktik Presentasi Canva">
      <?php if (isset($fe['judul'])): ?><p class="help-block text-danger"><?= etugas_h($fe['judul']) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="col-md-6">
    <div class="form-group">
      <label for="instruksi">Instruksi</label>
      <textarea class="form-control" id="instruksi" name="instruksi" rows="5"
                placeholder="Jelaskan apa yang harus dikumpulkan siswa."><?= etugas_h($fd['instruksi'] ?? '') ?></textarea>
    </div>

    <div class="form-group<?= isset($fe['deadline_at']) ? ' has-error' : '' ?>">
      <label for="deadline_at">Deadline</label>
      <input type="datetime-local" class="form-control" id="deadline_at" name="deadline_at"
             value="<?= etugas_h(etugas_deadline_for_input($fd['deadline_at'] ?? '')) ?>">
      <p class="help-block">Kosongkan jika tidak ada batas waktu. Waktu mengikuti server.</p>
      <?php if (isset($fe['deadline_at'])): ?><p class="help-block text-danger"><?= etugas_h($fe['deadline_at']) ?></p><?php endif; ?>
    </div>

    <fieldset class="form-group">
      <legend class="control-label" style="border:0;font-size:14px;margin-bottom:8px">Jenis pengumpulan</legend>
      <div class="checkbox">
        <label>
          <input type="checkbox" name="allow_text" value="1" <?= !empty($fd['allow_text']) ? 'checked' : '' ?>>
          Izinkan jawaban teks
        </label>
      </div>
      <div class="checkbox">
        <label>
          <input type="checkbox" name="allow_link" value="1" <?= !empty($fd['allow_link']) ? 'checked' : '' ?>>
          Izinkan kirim link
        </label>
      </div>
      <p class="help-block">Link cocok untuk video Google Drive, YouTube, Canva, atau dokumen online.</p>
    </fieldset>

    <div class="checkbox">
      <label>
        <input type="checkbox" name="izinkan_terlambat" value="1" <?= !empty($fd['izinkan_terlambat']) ? 'checked' : '' ?>>
        Izinkan pengumpulan terlambat
      </label>
    </div>

    <div class="form-group<?= isset($fe['status']) ? ' has-error' : '' ?>">
      <label for="status">Status</label>
      <select class="form-control" id="status" name="status" required>
        <?php foreach (etugas_valid_statuses() as $st): ?>
        <option value="<?= etugas_h($st) ?>" <?= (($fd['status'] ?? 'draft') === $st) ? 'selected' : '' ?>>
          <?= etugas_h(ucfirst($st)) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($fe['status'])): ?><p class="help-block text-danger"><?= etugas_h($fe['status']) ?></p><?php endif; ?>
      <?php if ($isEdit): ?>
      <p class="help-block">Untuk mengarsipkan, gunakan tombol Arsipkan di daftar tugas (bukan hapus permanen).</p>
      <p class="help-block text-muted">
        <i class="fa fa-info-circle"></i>
        Jika tugas dibuat untuk banyak kelas, masing-masing kelas menjadi data tugas terpisah dan dapat diedit per kelas.
      </p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  var combos = <?= json_encode($matrix['combos'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  var isEdit = <?= $isEdit ? 'true' : 'false' ?>;
  var ta = document.getElementById('ta_id');
  var mapel = document.getElementById('mapel_id');
  if (!ta || !mapel) return;

  if (isEdit) {
    var kelas = document.getElementById('kelas_id');
    if (!kelas) return;
    function allowedKelas(taId) {
      var set = {};
      combos.forEach(function(c){
        if (String(c.ta_id) === String(taId)) set[c.kelas_id] = true;
      });
      return set;
    }
    function allowedMapel(taId, kelasId) {
      var set = {};
      combos.forEach(function(c){
        if (String(c.ta_id) === String(taId) && String(c.kelas_id) === String(kelasId)) set[c.mapel_id] = true;
      });
      return set;
    }
    function filterKelas() {
      var taId = ta.value;
      var allow = allowedKelas(taId);
      Array.prototype.forEach.call(kelas.options, function(opt, idx){
        if (idx === 0) return;
        var ok = !taId || allow[opt.value];
        opt.hidden = !ok;
        opt.disabled = !ok;
      });
      if (kelas.selectedOptions[0] && kelas.selectedOptions[0].disabled) kelas.value = '';
      filterMapel();
    }
    function filterMapel() {
      var taId = ta.value;
      var kelasId = kelas.value;
      var allow = allowedMapel(taId, kelasId);
      Array.prototype.forEach.call(mapel.options, function(opt, idx){
        if (idx === 0) return;
        var ok = !kelasId || allow[opt.value];
        opt.hidden = !ok;
        opt.disabled = !ok;
      });
      if (mapel.selectedOptions[0] && mapel.selectedOptions[0].disabled) mapel.value = '';
    }
    ta.addEventListener('change', filterKelas);
    kelas.addEventListener('change', filterMapel);
    filterKelas();
    return;
  }

  var grid = document.getElementById('etugas_kelas_grid');
  var countEl = document.getElementById('etugas_kelas_count');
  var btnAll = document.getElementById('etugas_kelas_select_all');
  var btnClear = document.getElementById('etugas_kelas_clear_all');
  if (!grid) return;

  function allowedMapelForTa(taId) {
    var set = {};
    combos.forEach(function(c){
      if (String(c.ta_id) === String(taId)) set[c.mapel_id] = true;
    });
    return set;
  }
  function allowedKelasForTaMapel(taId, mapelId) {
    var set = {};
    combos.forEach(function(c){
      if (String(c.ta_id) === String(taId) && String(c.mapel_id) === String(mapelId)) set[c.kelas_id] = true;
    });
    return set;
  }
  function filterMapelCreate() {
    var taId = ta.value;
    var allow = allowedMapelForTa(taId);
    Array.prototype.forEach.call(mapel.options, function(opt, idx){
      if (idx === 0) return;
      var ok = !taId || allow[opt.value];
      opt.hidden = !ok;
      opt.disabled = !ok;
    });
    if (mapel.selectedOptions[0] && mapel.selectedOptions[0].disabled) mapel.value = '';
    filterKelasCheckboxes();
  }
  function visibleItems() {
    return Array.prototype.filter.call(grid.querySelectorAll('.etugas-kelas-item'), function(li){
      return li.style.display !== 'none';
    });
  }
  function updateCount() {
    if (!countEl) return;
    var n = grid.querySelectorAll('.etugas-kelas-cb:checked').length;
    var vis = visibleItems().length;
    countEl.textContent = n + ' kelas dipilih' + (vis ? ' (dari ' + vis + ' kelas tersedia)' : '');
  }
  function filterKelasCheckboxes() {
    var taId = ta.value;
    var mapelId = mapel.value;
    var allow = (taId && mapelId) ? allowedKelasForTaMapel(taId, mapelId) : {};
    Array.prototype.forEach.call(grid.querySelectorAll('.etugas-kelas-item'), function(li){
      var kid = li.getAttribute('data-kelas');
      var ok = taId && mapelId && allow[kid];
      li.style.display = ok ? '' : 'none';
      if (!ok) {
        var cb = li.querySelector('.etugas-kelas-cb');
        if (cb) cb.checked = false;
      }
    });
    updateCount();
  }
  function setAllVisible(checked) {
    visibleItems().forEach(function(li){
      var cb = li.querySelector('.etugas-kelas-cb');
      if (cb) cb.checked = checked;
    });
    updateCount();
  }
  if (btnAll) btnAll.addEventListener('click', function(e){ e.preventDefault(); setAllVisible(true); });
  if (btnClear) btnClear.addEventListener('click', function(e){ e.preventDefault(); setAllVisible(false); });
  grid.addEventListener('change', function(e){
    if (e.target && e.target.classList.contains('etugas-kelas-cb')) updateCount();
  });
  ta.addEventListener('change', filterMapelCreate);
  mapel.addEventListener('change', filterKelasCheckboxes);
  filterMapelCreate();
})();
</script>
