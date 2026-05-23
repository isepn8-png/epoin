/**
 * HARDENING PACK — client_lock.js
 * Deterrent sisi klien: blok klik kanan, Ctrl+U/F12, Ctrl+S/P, Ctrl+Shift+I/J/C/K, dan deteksi DevTools heuristik.
 *
 * Cara pasang (footer, sebelum </body>):
 *   <script src="/assets/js/client_lock.js" data-lock="strict" data-allow-print="false" data-allow-copy="false"></script>
 *
 * Opsi data-atribut:
 *   data-lock: 'strict' | 'lenient' | 'off'   (default 'strict')
 *   data-allow-print="true"                   (default false)
 *   data-allow-copy="true"                    (default false → jika true, contextmenu dibiarkan)
 *
 * Kill switch global (runtime):
 *   window.__LOCK_DISABLED__ = true;  // matikan sementara
 */
(function () {
    'use strict';
    if (window.__LOCK_DISABLED__) return;

    var currentScript = document.currentScript || (function () { var s = document.getElementsByTagName('script'); return s[s.length - 1]; })();
    var mode = (currentScript && currentScript.dataset.lock) || 'strict';
    var allowPrint = (currentScript && currentScript.dataset.allowPrint === 'true');
    var allowCopy = (currentScript && currentScript.dataset.allowCopy === 'true');

    if (mode === 'off') return;

    // Blok klik kanan (kecuali allowCopy)
    if (!allowCopy) {
        document.addEventListener('contextmenu', function (e) { e.preventDefault(); }, { capture: true });
    }

    // Blok kombinasi tombol umum
    document.addEventListener('keydown', function (e) {
        var k = (e.key || '').toLowerCase();
        var ctrl = e.ctrlKey || e.metaKey; // dukung Cmd di Mac
        var sh = e.shiftKey;

        // F12
        if (k === 'f12' || e.keyCode === 123) { e.preventDefault(); e.stopPropagation(); return false; }

        // Ctrl+U (view-source), Ctrl+S (save)
        if (ctrl && (k === 'u' || k === 's')) { e.preventDefault(); e.stopPropagation(); return false; }

        // Ctrl+P (print) — blokir kalau tidak diizinkan
        if (!allowPrint && ctrl && k === 'p') { e.preventDefault(); e.stopPropagation(); return false; }

        // Ctrl+Shift+I/J/C/K (DevTools/Console)
        if (ctrl && sh && (k === 'i' || k === 'j' || k === 'c' || k === 'k')) { e.preventDefault(); e.stopPropagation(); return false; }
    }, { capture: true });

    // Heuristik DevTools (window gap); hanya warning lembut
    if (mode === 'strict') {
        var lastState = false;
        setInterval(function () {
            var wGap = Math.abs(window.outerWidth - window.innerWidth);
            var hGap = Math.abs(window.outerHeight - window.innerHeight);
            var isOpen = (wGap > 160 || hGap > 160);
            if (isOpen && !lastState) {
                lastState = true;
                console.warn('DevTools terdeteksi. Beberapa tindakan mungkin dibatasi.');
            } else if (!isOpen && lastState) {
                lastState = false;
            }
        }, 1200);
    }

    // API sederhana untuk toggle dari app
    window.LockProtector = {
        disable: function () { window.__LOCK_DISABLED__ = true; },
        enable: function () { window.__LOCK_DISABLED__ = false; }
    };
})();
