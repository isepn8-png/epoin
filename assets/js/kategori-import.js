/**
 * kategori-import.js — Modul import Excel/CSV bersama utk Jenis Prestasi & Jenis Pelanggaran.
 * Alur: pilih/drag file -> Preview (AJAX, tanpa tulis DB) -> tinjau tabel -> Eksekusi Import (AJAX, commit).
 * Dipakai oleh: admin/prestasi.php, admin/pelanggaran.php (lihat kategori_import_act.php utk backend).
 */
(function () {
  'use strict';

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
  }

  function statusMeta(s) {
    var map = {
      ok:     { cls: 'kimp-st-ok', label: 'Baru' },
      update: { cls: 'kimp-st-update', label: 'Update' },
      skip:   { cls: 'kimp-st-skip', label: 'Dilewati' },
      error:  { cls: 'kimp-st-error', label: 'Error' }
    };
    return map[s] || { cls: '', label: s };
  }

  window.EpoinKategoriImport = function (opts) {
    var jenis = opts.jenis;
    var sfx = '_' + jenis;
    var el = function (id) { return document.getElementById(id + sfx); };

    var drop     = el('kimpDrop');
    var fileInp  = el('kimpFile');
    var fnameEl  = el('kimpFilename');
    var modeSel  = el('kimpMode');
    var defInp   = el('kimpDefault');
    var btnPrev  = el('kimpBtnPreview');
    var btnExec  = el('kimpBtnExec');
    var btnReset = el('kimpBtnReset');
    var sumEl    = el('kimpSummary');
    var bodyEl   = el('kimpPreviewBody');
    if (!drop || !fileInp) { return; } // markup belum ada di halaman ini

    var token = null;

    function resetPreview() {
      token = null;
      if (btnExec) btnExec.disabled = true;
      if (sumEl) sumEl.innerHTML = '';
      if (bodyEl) bodyEl.innerHTML = '<tr><td colspan="4" class="text-center kimp-empty">Belum ada preview. Pilih file lalu klik Preview.</td></tr>';
    }

    function setFile(file) {
      if (!file) return;
      try {
        var dt = new DataTransfer();
        dt.items.add(file);
        fileInp.files = dt.files;
      } catch (e) { /* browser lama: input file tetap dipakai langsung via drop handler fallback */ }
      fnameEl.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
      fnameEl.style.display = 'flex';
      resetPreview();
    }

    drop.addEventListener('click', function () { fileInp.click(); });
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('kimp-drop-active'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('kimp-drop-active'); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      drop.classList.remove('kimp-drop-active');
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) { setFile(e.dataTransfer.files[0]); }
    });
    fileInp.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        fnameEl.textContent = this.files[0].name + ' (' + Math.round(this.files[0].size / 1024) + ' KB)';
        fnameEl.style.display = 'flex';
        resetPreview();
      }
    });

    if (btnReset) {
      btnReset.addEventListener('click', function () {
        fileInp.value = '';
        fnameEl.style.display = 'none';
        modeSel.value = 'skip';
        defInp.value = '';
        resetPreview();
      });
    }

    function renderPreview(res) {
      var s = res.summary;
      sumEl.innerHTML =
        '<span class="kimp-chip kimp-chip-ok">' + s.baru + ' baru</span>' +
        (s.update > 0 ? '<span class="kimp-chip kimp-chip-update">' + s.update + ' update</span>' : '') +
        (s.lewati > 0 ? '<span class="kimp-chip kimp-chip-skip">' + s.lewati + ' dilewati</span>' : '') +
        (s.error > 0 ? '<span class="kimp-chip kimp-chip-error">' + s.error + ' error</span>' : '');

      if (!res.preview.length) {
        bodyEl.innerHTML = '<tr><td colspan="4" class="text-center kimp-empty">Tidak ada baris data.</td></tr>';
        return;
      }
      var html = '';
      res.preview.forEach(function (row) {
        var st = statusMeta(row.status);
        html += '<tr class="' + (row.status === 'error' ? 'kimp-row-error' : '') + '">' +
          '<td>' + row.line + '</td>' +
          '<td>' + escapeHtml(row.nama) + '</td>' +
          '<td>' + (row.poin !== null ? row.poin : '&mdash;') + '</td>' +
          '<td><span class="kimp-badge-st ' + st.cls + '" title="' + escapeHtml(row.reason || '') + '">' + st.label + '</span></td>' +
          '</tr>';
      });
      if (res.preview_truncated) {
        html += '<tr><td colspan="4" class="text-center text-muted" style="font-size:11px;">&hellip; dan ' +
          (res.summary.total - res.preview.length) + ' baris lainnya (tetap ikut diproses saat eksekusi)</td></tr>';
      }
      bodyEl.innerHTML = html;
    }

    if (btnPrev) {
      btnPrev.addEventListener('click', function () {
        if (!fileInp.files || !fileInp.files[0]) { alert('Pilih file dulu (klik atau tarik-lepas ke area unggah).'); return; }
        var fd = new FormData();
        fd.append('aksi', 'preview_excel');
        fd.append('jenis', jenis);
        fd.append('mode', modeSel.value);
        fd.append('default_poin', defInp.value);
        fd.append('file', fileInp.files[0]);
        fd.append('_csrf', opts.csrfToken);

        var origHtml = btnPrev.innerHTML;
        btnPrev.disabled = true;
        btnPrev.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';

        fetch('kategori_import_act.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { throw new Error('Respons server tidak valid.'); }); })
          .then(function (res) {
            btnPrev.disabled = false; btnPrev.innerHTML = origHtml;
            if (!res.ok) { alert(res.msg || 'Gagal memproses file.'); return; }
            token = res.token;
            renderPreview(res);
            if (btnExec) btnExec.disabled = (res.summary.baru + res.summary.update) === 0;
          })
          .catch(function () {
            btnPrev.disabled = false; btnPrev.innerHTML = origHtml;
            alert('Gagal terhubung ke server. Periksa koneksi lalu coba lagi.');
          });
      });
    }

    if (btnExec) {
      btnExec.addEventListener('click', function () {
        if (!token) return;
        if (!confirm('Eksekusi import sekarang? Data akan langsung ditulis ke database.')) return;
        var fd = new FormData();
        fd.append('aksi', 'eksekusi_import');
        fd.append('jenis', jenis);
        fd.append('token', token);
        fd.append('_csrf', opts.csrfToken);

        var origHtml = btnExec.innerHTML;
        btnExec.disabled = true;
        btnExec.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menyimpan...';

        fetch('kategori_import_act.php', { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { throw new Error('Respons server tidak valid.'); }); })
          .then(function (res) {
            if (!res.ok) {
              alert(res.msg || 'Gagal mengeksekusi import.');
              btnExec.disabled = false; btnExec.innerHTML = origHtml;
              return;
            }
            alert(res.msg);
            window.location.reload();
          })
          .catch(function () {
            alert('Gagal terhubung ke server. Periksa koneksi lalu coba lagi.');
            btnExec.disabled = false; btnExec.innerHTML = origHtml;
          });
      });
    }

    resetPreview();
  };
})();
