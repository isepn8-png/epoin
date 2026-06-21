<?php
/**
 * includes/jenjang_pembinaan_modal.php
 * Komponen shared: Stepper "Jenjang Pembinaan Peserta Didik"
 *
 * Variabel yang diharapkan diset oleh halaman pemanggil:
 *   int    $levelActive         — 0=aman, 1–6=Tingkat I–VI
 *   int    $negSaldo            — magnitude saldo negatif (0 jika aman)
 *   int    $saldo               — saldo bertanda (signed)
 *   string $jenjang_nama_siswa  — (opsional) nama siswa; muncul di header modal (konteks admin/guru)
 */
$levelActive        = isset($levelActive)        ? (int)$levelActive        : 0;
$negSaldo           = isset($negSaldo)           ? (int)$negSaldo           : 0;
$saldo              = isset($saldo)              ? (int)$saldo              : 0;
$jenjang_nama_siswa = isset($jenjang_nama_siswa) ? htmlspecialchars(trim((string)$jenjang_nama_siswa), ENT_QUOTES, 'UTF-8') : '';
?>
<style>
/* =========================================================
   Jenjang Pembinaan — Tombol trigger
   ========================================================= */
.btn-jenjang { font-size:16px; padding:10px 16px; }
.btn-jenjang i { vertical-align:baseline; }
@media (max-width:480px){
  .btn-jenjang { font-size:13px; padding:8px 12px; }
  .btn-jenjang i { font-size:14px; }
}
.jenjang-cta{
  display:block;width:100%;
  font-weight:900;letter-spacing:.3px;
  border:none;border-radius:16px;
  padding:16px 20px;
  background:linear-gradient(135deg,#2563eb,#1d4ed8);
  color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.35);
}
.jenjang-cta:hover{ background:linear-gradient(135deg,#1e40af,#2563eb); }

/* =========================================================
   Jenjang Pembinaan — Overlay + Modal shell
   ========================================================= */
.jenjang-overlay{ position:fixed; top:0; right:0; bottom:0; left:0; inset:0; margin:0;
  display:none; align-items:center; justify-content:center;
  padding:20px; box-sizing:border-box;
  background:rgba(2,6,23,.56); backdrop-filter:blur(3px);
  z-index:100000; opacity:0; transition:opacity .22s ease; }
.jenjang-overlay.show{ display:flex !important; align-items:center !important; justify-content:center !important; }
.jenjang-overlay.reveal{ opacity:1; }

.jenjang-modal{ width:960px;max-width:100%;max-height:calc(100vh - 40px);
  background:#fff;border-radius:20px;border:1px solid #e5e7eb;
  box-shadow:0 28px 80px rgba(2,6,23,.26);
  display:flex;flex-direction:column;overflow:hidden;
  transform:translateY(16px) scale(.985);transition:transform .25s ease; }
.jenjang-overlay.reveal .jenjang-modal{ transform:translateY(0) scale(1); }

.jenjang-head{ flex:0 0 auto;padding:18px 22px;
  background:linear-gradient(90deg,#0b3c7c 0%,#1e3a8a 100%);
  color:#fff;display:flex;align-items:center;justify-content:space-between;gap:12px;
  border-top-left-radius:20px;border-top-right-radius:20px;
  box-shadow:0 4px 14px rgba(2,6,23,.22); }
.jenjang-title{ margin:0;font-weight:900;font-size:20px;line-height:1.3; }
.jenjang-close{ border:none;background:#ffffff1a;color:#fff;width:40px;height:40px;
  border-radius:999px;cursor:pointer;font-size:16px;
  display:flex;align-items:center;justify-content:center;flex:0 0 40px;
  transition:background .2s ease,transform .2s ease; }
.jenjang-close:hover{ background:#ffffff33;transform:rotate(90deg); }

.jenjang-body{ flex:1 1 auto;overflow:hidden;min-height:0; }

/* =========================================================
   2-Kolom Layout
   ========================================================= */
.jst-layout{ display:grid;grid-template-columns:200px 1fr;height:100%;overflow:hidden; }

/* ── KIRI: Timeline ── */
.jst-timeline{ background:#f8fafc;border-right:1px solid #e5e7eb;
  display:flex;flex-direction:column;overflow:hidden; }
.jst-status-mini{ padding:14px 16px;border-bottom:1px solid #e5e7eb;flex:0 0 auto; }
.jst-saldo-text{ font-size:13px;color:#374151;font-weight:600; }
.jst-saldo-sub{ font-size:12px;color:#6b7280;margin-top:2px; }

.jst-steps{ list-style:none;padding:10px 0 16px;margin:0;
  position:relative;flex:1 1 auto;overflow-y:auto; }
.jst-steps::before{ content:'';position:absolute;left:33px;top:28px;bottom:28px;
  width:2px;background:#e2e8f0;z-index:0; }

.jst-step{ position:relative;display:flex;align-items:center;
  padding:7px 10px 7px 0;cursor:pointer;user-select:none; }
.jst-step:hover .jst-node{ transform:scale(1.1); }

.jst-node{ position:relative;z-index:1;flex:0 0 38px;width:38px;height:38px;
  margin-left:14px;border-radius:999px;
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:900;letter-spacing:.2px;
  background:#fff;color:#9ca3af;border:2.5px solid #d1d5db;
  box-shadow:0 2px 6px rgba(2,6,23,.07);
  transition:border-color .2s,background .2s,color .2s,transform .18s,box-shadow .2s; }

.jst-step.sel .jst-node{
  background:var(--sc,#22c55e);border-color:var(--sc,#22c55e);color:#fff;
  box-shadow:0 0 0 4px rgba(0,0,0,.07),0 4px 12px rgba(2,6,23,.14); }
.jst-step.sel .jst-lbl-name{ color:var(--sc,#22c55e);font-weight:800; }

.jst-step.is-current:not(.sel) .jst-node{
  border-color:var(--sc,#22c55e);color:var(--sc,#22c55e);
  animation:jst-pulse 2.2s ease-in-out infinite; }
@keyframes jst-pulse{
  0%,100%{ box-shadow:0 0 0 0 transparent,0 2px 6px rgba(2,6,23,.07); }
  50%{     box-shadow:0 0 0 7px rgba(2,6,23,.05),0 2px 8px rgba(2,6,23,.1); } }

.jst-lbl{ padding-left:11px;flex:1;min-width:0; }
.jst-lbl-name{ display:block;font-size:13px;font-weight:700;color:#374151;
  line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  transition:color .2s ease; }
.jst-lbl-range{ display:block;font-size:11px;color:#9ca3af;margin-top:2px;white-space:nowrap; }
.jst-now-badge{ display:inline-block;font-size:9px;font-weight:900;letter-spacing:.4px;
  text-transform:uppercase;background:var(--sc,#22c55e);color:#fff;
  padding:2px 7px;border-radius:999px;margin-top:3px; }

/* ── KANAN: Detail Panel ── */
.jst-detail{ overflow-y:auto;-webkit-overflow-scrolling:touch; }
.jst-detail-inner{ padding:26px 28px;opacity:1;transition:opacity .16s ease; }
.jst-detail-inner.fading{ opacity:0; }

.jst-dtitle{ font-size:1.6rem;font-weight:900;margin:0 0 12px;line-height:1.2; }
.jst-dchips{ display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px; }
.jst-chip{ display:inline-flex;align-items:center;gap:6px;padding:6px 13px;
  border-radius:999px;font-size:13px;font-weight:700;
  background:#f1f5f9;color:#334155;border:1px solid #e2e8f0; }
.jst-chip-range{ background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe; }
.jst-chip-prog{ background:#f0fdf4;color:#166534;border-color:#bbf7d0; }
.jst-chip-sp1{ background:#f59e0b;color:#fff;border:none; }
.jst-chip-sp2{ background:#fb923c;color:#fff;border:none; }
.jst-chip-sp3{ background:#ef4444;color:#fff;border:none; }
.jst-chip-sp4{ background:#f43f5e;color:#fff;border:none; }

.jst-dsub{ font-size:15.5px;line-height:1.65;color:#475569;margin:0 0 22px; }

.jst-sections{ display:flex;flex-direction:column;gap:12px; }
.jst-sec{ border-radius:14px;padding:16px 18px;border:1px solid transparent; }
.jst-sec-head{ display:flex;align-items:center;gap:10px;margin-bottom:8px; }
.jst-sec-ico{ width:32px;height:32px;border-radius:9px;
  display:flex;align-items:center;justify-content:center;
  font-size:14px;flex:0 0 32px; }
.jst-sec-lbl{ font-size:11.5px;font-weight:900;letter-spacing:.7px;text-transform:uppercase; }
.jst-sec-txt{ font-size:15px;line-height:1.65;color:#334155;margin:0; }

/* ── Responsive ── */
@media(max-width:700px){
  .jst-layout{ grid-template-columns:1fr;grid-template-rows:auto 1fr; }
  .jst-timeline{ border-right:none;border-bottom:1px solid #e5e7eb;flex:0 0 auto;overflow:hidden; }
  .jst-status-mini{ display:none; }
  .jst-steps{ display:flex;flex-direction:row;overflow-x:auto;overflow-y:hidden;
    padding:10px 12px 12px;scrollbar-width:thin; }
  .jst-steps::before{ display:none; }
  .jst-step{ flex-direction:column;align-items:center;padding:0 6px;min-width:64px;text-align:center; }
  .jst-node{ margin-left:0; }
  .jst-lbl{ padding-left:0;padding-top:5px; }
  .jst-lbl-name{ font-size:11.5px;white-space:normal; }
  .jst-lbl-range,.jst-now-badge{ display:none; }
  .jst-detail{ overflow-y:auto; }
  .jst-detail-inner{ padding:18px 16px; }
  .jst-dtitle{ font-size:1.3rem; }
  .jst-sec{ padding:13px 14px; }
  .jst-sec-txt{ font-size:14.5px; }
}
@media(max-width:480px){
  .jenjang-overlay{ padding:0; }
  .jenjang-modal{ border-radius:0;max-height:100vh; }
  .jenjang-head{ border-radius:0; }
}
</style>

<!-- ====== STEPPER: JENJANG PEMBINAAN ====== -->
<div id="JenjangSheet" class="jenjang-overlay" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="jenjang-modal" role="document">

    <div class="jenjang-head">
      <h3 class="jenjang-title">
        <i class="fa fa-sitemap" aria-hidden="true"></i> Jenjang Pembinaan Peserta Didik
        <?php if ($jenjang_nama_siswa !== ''): ?>
          <span style="font-size:15px;font-weight:600;opacity:.75;margin-left:6px;">— <?php echo $jenjang_nama_siswa; ?></span>
        <?php endif; ?>
      </h3>
      <button class="jenjang-close" type="button" title="Tutup" aria-label="Tutup" data-close-jenjang>
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
    </div>

    <div class="jenjang-body">
      <div class="jst-layout">

        <!-- ── KIRI: Timeline/Stepper ── -->
        <div class="jst-timeline">
          <div class="jst-status-mini">
            <?php if ($negSaldo > 0): ?>
              <div class="jst-saldo-text">Saldo: <b><?php echo ($saldo >= 0 ? '+' : '') . $saldo; ?></b></div>
              <div class="jst-saldo-sub">Defisit <b><?php echo $negSaldo; ?> poin</b></div>
            <?php else: ?>
              <div class="jst-saldo-text">Saldo: <b style="color:#22c55e">+<?php echo (int)$saldo; ?></b></div>
              <div class="jst-saldo-sub" style="color:#22c55e"><b>Status: Aman ✓</b></div>
            <?php endif; ?>
          </div>

          <ul class="jst-steps" id="jstSteps">
            <li class="jst-step<?php echo $levelActive === 0 ? ' is-current' : ''; ?>" data-step="0" style="--sc:#22c55e">
              <div class="jst-node"><i class="fa fa-check" aria-hidden="true"></i></div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Aman</span>
                <span class="jst-lbl-range">0 poin negatif</span>
                <?php if ($levelActive === 0) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
            <li class="jst-step<?php echo $levelActive === 1 ? ' is-current' : ''; ?>" data-step="1" style="--sc:#10b981">
              <div class="jst-node">I</div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Tingkat I</span>
                <span class="jst-lbl-range">1–20 poin</span>
                <?php if ($levelActive === 1) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
            <li class="jst-step<?php echo $levelActive === 2 ? ' is-current' : ''; ?>" data-step="2" style="--sc:#f59e0b">
              <div class="jst-node">II</div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Tingkat II</span>
                <span class="jst-lbl-range">21–40 poin</span>
                <?php if ($levelActive === 2) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
            <li class="jst-step<?php echo $levelActive === 3 ? ' is-current' : ''; ?>" data-step="3" style="--sc:#f97316">
              <div class="jst-node">III</div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Tingkat III</span>
                <span class="jst-lbl-range">41–60 poin</span>
                <?php if ($levelActive === 3) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
            <li class="jst-step<?php echo $levelActive === 4 ? ' is-current' : ''; ?>" data-step="4" style="--sc:#ef4444">
              <div class="jst-node">IV</div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Tingkat IV</span>
                <span class="jst-lbl-range">61–80 poin</span>
                <?php if ($levelActive === 4) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
            <li class="jst-step<?php echo $levelActive === 5 ? ' is-current' : ''; ?>" data-step="5" style="--sc:#b91c1c">
              <div class="jst-node">V</div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Tingkat V</span>
                <span class="jst-lbl-range">81–99 poin</span>
                <?php if ($levelActive === 5) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
            <li class="jst-step<?php echo $levelActive === 6 ? ' is-current' : ''; ?>" data-step="6" style="--sc:#7f1d1d">
              <div class="jst-node">VI</div>
              <div class="jst-lbl">
                <span class="jst-lbl-name">Tingkat VI</span>
                <span class="jst-lbl-range">≥100 poin</span>
                <?php if ($levelActive === 6) echo '<span class="jst-now-badge">Saat ini</span>'; ?>
              </div>
            </li>
          </ul>
        </div>

        <!-- ── KANAN: Detail Panel (dirender JS) ── -->
        <div class="jst-detail">
          <div id="jstDetailInner" class="jst-detail-inner"></div>
        </div>

      </div>
    </div>

  </div>
</div>
<!-- ====== /STEPPER JENJANG ====== -->
<script>
(function(){
  var sheet   = document.getElementById('JenjangSheet');
  var openBtn = document.getElementById('btnJenjang');
  if (!sheet || !openBtn) return;

  var ACTIVE = <?php echo (int)$levelActive; ?>;

  var D = [
    { label:'Aman', range:'0 poin negatif', sp:null, spCls:'',
      prog:'Apresiasi / Monitoring', color:'#22c55e',
      sub:'Saldo poin aman. Tidak ada tindakan pembinaan yang diperlukan saat ini.',
      tindakan:'Tidak ada tindakan pembinaan. Kondisi ini mencerminkan perilaku baik.',
      tujuan:'Menjaga saldo poin tetap positif dan terus meningkatkan prestasi.',
      catatan:'Poin prestasi dapat terus meningkatkan saldo positif.' },
    { label:'Tingkat I', range:'1–20 poin negatif', sp:'SP1', spCls:'jst-chip-sp1',
      prog:'Pembinaan Umum', color:'#10b981',
      sub:'Teguran ringan & <strong>pembinaan umum</strong>. Fokus kebiasaan baik, disiplin dasar, dan kerja sama (STP2K).',
      tindakan:'Teguran lisan/tertulis sebagai pengingat awal.',
      tujuan:'Edukasi dini & pembiasaan perilaku baik.',
      catatan:'Poin dapat kembali aman dengan mengumpulkan prestasi.' },
    { label:'Tingkat II', range:'21–40 poin negatif', sp:'SP1', spCls:'jst-chip-sp1',
      prog:'Pembinaan Umum / Panggilan Orang Tua', color:'#f59e0b',
      sub:'Peringatan <strong>SP1</strong> & pendampingan wali kelas. Evaluasi perilaku dan komitmen perbaikan.',
      tindakan:'SP1 tertulis & pertemuan singkat dengan orang tua.',
      tujuan:'Kolaborasi sekolah–orang tua untuk menghentikan pelanggaran berulang.',
      catatan:'Monitoring perilaku selama periode tertentu.' },
    { label:'Tingkat III', range:'41–60 poin negatif', sp:'SP2', spCls:'jst-chip-sp2',
      prog:'Panggilan Orang Tua', color:'#f97316',
      sub:'Peringatan <strong>SP2</strong> & rencana perbaikan terukur bersama guru/BK.',
      tindakan:'SP2 & rencana perbaikan (action plan) bersama wali kelas/BK.',
      tujuan:'Perubahan perilaku berkelanjutan dengan target yang terukur.',
      catatan:'Evaluasi berkala dan penilaian kemajuan.' },
    { label:'Tingkat IV', range:'61–80 poin negatif', sp:'SP3', spCls:'jst-chip-sp3',
      prog:'Pembinaan Khusus', color:'#ef4444',
      sub:'Pembinaan <strong>khusus terpadu</strong> dengan guru/BK, pemantauan ketat & komunikasi orang tua.',
      tindakan:'SP3 & program pembinaan terstruktur (coaching/konseling intensif).',
      tujuan:'Koreksi menyeluruh dengan pengawasan ketat.',
      catatan:'Pelanggaran lanjutan berisiko naik ke Tingkat V.' },
    { label:'Tingkat V', range:'81–99 poin negatif', sp:'SP4', spCls:'jst-chip-sp4',
      prog:'Konferensi Kasus', color:'#b91c1c',
      sub:'Sidang/konferensi pembinaan sekolah & rekomendasi tindakan. <strong>Monitoring harian</strong>.',
      tindakan:'SP4, konferensi kasus, penetapan sanksi tegas.',
      tujuan:'Keputusan tindak lanjut terakhir sebelum pemulangan.',
      catatan:'Dampak akademik bisa terjadi (mis. tidak naik kelas).' },
    { label:'Tingkat VI', range:'≥100 poin negatif', sp:'SP4', spCls:'jst-chip-sp4',
      prog:'Dikembalikan pada Orang Tua', color:'#7f1d1d',
      sub:'Tindak lanjut kebijakan sekolah (mis. <strong>SP3/keputusan akhir</strong>) sesuai peraturan.',
      tindakan:'Pemulangan ke orang tua sesuai ketentuan sekolah.',
      tujuan:'Keselamatan, tanggung jawab, dan pembinaan lanjutan di keluarga.',
      catatan:'Semua proses terdokumentasi & melibatkan pihak terkait.' }
  ];

  var inner  = document.getElementById('jstDetailInner');
  var curSel = -1;

  function fa(cls){ return '<i class="fa ' + cls + '" aria-hidden="true"></i>'; }

  function mkSec(bg, border, icoBg, icoColor, ico, labelTxt, txt){
    return '<div class="jst-sec" style="background:' + bg + ';border-color:' + border + '">'
      + '<div class="jst-sec-head">'
      + '<div class="jst-sec-ico" style="background:' + icoBg + ';color:' + icoColor + '">' + fa(ico) + '</div>'
      + '<span class="jst-sec-lbl" style="color:' + icoColor + '">' + labelTxt + '</span>'
      + '</div>'
      + '<p class="jst-sec-txt">' + txt + '</p>'
      + '</div>';
  }

  function renderDetail(idx, animate){
    var d = D[idx];
    if (!d || !inner) return;
    var spHtml = d.sp
      ? '<span class="jst-chip ' + d.spCls + '">' + fa('fa-certificate') + ' ' + d.sp + '</span>'
      : '';
    var html = '<h4 class="jst-dtitle" style="color:' + d.color + '">' + d.label + '</h4>'
      + '<div class="jst-dchips">'
      + '<span class="jst-chip jst-chip-range">' + fa('fa-bullseye') + ' ' + d.range + '</span>'
      + '<span class="jst-chip jst-chip-prog">' + fa('fa-graduation-cap') + ' ' + d.prog + '</span>'
      + spHtml
      + '</div>'
      + '<p class="jst-dsub">' + d.sub + '</p>'
      + '<div class="jst-sections">'
      + mkSec('#eff6ff','#bfdbfe','#dbeafe','#1d4ed8', 'fa-bolt',          'Tindakan', d.tindakan)
      + mkSec('#f0fdf4','#bbf7d0','#dcfce7','#16a34a', 'fa-list-ul',       'Tujuan',   d.tujuan)
      + mkSec('#faf5ff','#e9d5ff','#ede9fe','#6d28d9', 'fa-sticky-note-o', 'Catatan',  d.catatan)
      + '</div>';
    if (animate) {
      inner.classList.add('fading');
      setTimeout(function(){
        inner.innerHTML = html;
        inner.classList.remove('fading');
      }, 160);
    } else {
      inner.innerHTML = html;
    }
  }

  function selectStep(idx){
    sheet.querySelectorAll('.jst-step').forEach(function(s){ s.classList.remove('sel'); });
    var target = sheet.querySelector('.jst-step[data-step="' + idx + '"]');
    if (target) target.classList.add('sel');
    renderDetail(idx, curSel !== -1 && curSel !== idx);
    curSel = idx;
  }

  function openSheet(){
    if (sheet.parentNode !== document.body) document.body.appendChild(sheet);
    sheet.classList.add('show');
    document.body.style.overflow = 'hidden';
    setTimeout(function(){ sheet.classList.add('reveal'); }, 10);
    curSel = -1;
    selectStep(ACTIVE);
  }

  function closeSheet(){
    sheet.classList.remove('reveal');
    setTimeout(function(){
      sheet.classList.remove('show');
      document.body.style.overflow = '';
    }, 200);
  }

  /* Event delegation — aktif setelah appendChild ke body */
  sheet.addEventListener('click', function(e){
    if (e.target === sheet) { closeSheet(); return; }
    var step = e.target.closest('.jst-step');
    if (step) { selectStep(parseInt(step.dataset.step, 10) || 0); return; }
    if (e.target.closest('[data-close-jenjang]')) closeSheet();
  });

  openBtn.addEventListener('click', openSheet);
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && sheet.classList.contains('show')) closeSheet();
  });
})();
</script>
