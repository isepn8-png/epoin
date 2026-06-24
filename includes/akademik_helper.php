<?php
/**
 * includes/akademik_helper.php
 * ----------------------------------------------------------------------------
 * SUMBER TUNGGAL (single source of truth) untuk logika Tahun Ajaran & Semester.
 *
 * Konvensi semester (dipakai konsisten di SELURUH modul EPOIN):
 *   - Semester 1 = GANJIL  = Juli–Desember   (bulan 7..12)
 *   - Semester 2 = GENAP   = Januari–Juni    (bulan 1..6)
 *
 * File ini di-load otomatis oleh koneksi.php sehingga fungsi epoin_*() tersedia
 * di mana saja. Semua fungsi diberi prefix `epoin_` dan dijaga `function_exists`
 * agar tidak bentrok dengan definisi lokal lama (current_semester / default_semester).
 *
 * Dibuat: 2026-06-24 — konsolidasi 4 varian logika semester yang tersebar di
 * ~12 file (date('n')>=7?1:2, default_semester(), current_semester(), dst).
 */

if (!function_exists('epoin_current_semester')) {
    /**
     * Semester yang sedang berjalan berdasarkan BULAN kalender.
     * Jul–Des → 1 (Ganjil), Jan–Jun → 2 (Genap).
     *
     * @param int|null $month Bulan 1..12. Null = bulan sekarang.
     */
    function epoin_current_semester($month = null): int
    {
        $m = ($month === null) ? (int) date('n') : (int) $month;
        return ($m >= 7 && $m <= 12) ? 1 : 2;
    }
}

if (!function_exists('epoin_resolve_semester')) {
    /**
     * Tentukan semester aktif untuk sebuah halaman:
     *   1. Hormati ?semester=1|2 dari URL bila valid (user override manual).
     *   2. Bila tidak ada/invalid → fallback ke semester berjalan (otomatis).
     *
     * @param int|null $fallback Default bila URL kosong. Null = epoin_current_semester().
     */
    function epoin_resolve_semester($fallback = null): int
    {
        $fallback = ($fallback === null) ? epoin_current_semester() : (int) $fallback;
        if (isset($_GET['semester']) && is_numeric($_GET['semester'])) {
            $s = (int) $_GET['semester'];
            if ($s === 1 || $s === 2) {
                return $s;
            }
        }
        return ($fallback === 1 || $fallback === 2) ? $fallback : epoin_current_semester();
    }
}

if (!function_exists('epoin_ta_years')) {
    /**
     * Ambil dua tahun dari nama TA "2025/2026" → [2025, 2026].
     * Bila format tak terbaca, derive dari tahun ajaran berjalan
     * (Jul ke atas = tahun ini sebagai y1, selain itu tahun lalu).
     *
     * @return array{0:int,1:int} [y1, y2]
     */
    function epoin_ta_years($ta_nama, $nowTs = null): array
    {
        if (preg_match('/(\d{4})\D+(\d{4})/', (string) $ta_nama, $m)) {
            $y1 = (int) $m[1];
            $y2 = (int) $m[2];
            if ($y2 !== $y1 + 1) {
                $y2 = $y1 + 1;
            }
            return [$y1, $y2];
        }
        $ts = ($nowTs === null) ? time() : (int) $nowTs;
        $Y  = (int) date('Y', $ts);
        $M  = (int) date('n', $ts);
        $y1 = ($M >= 7) ? $Y : $Y - 1;
        return [$y1, $y1 + 1];
    }
}

if (!function_exists('epoin_semester_range')) {
    /**
     * Rentang tanggal sebuah semester pada TA tertentu.
     *   Sem 1 (Ganjil): y1-07-01 .. y1-12-31
     *   Sem 2 (Genap) : y2-01-01 .. y2-06-30
     *
     * @return array{0:string,1:string} [start 'Y-m-d', end 'Y-m-d']
     */
    function epoin_semester_range($ta_nama, $sem): array
    {
        [$y1, $y2] = epoin_ta_years($ta_nama);
        if ((int) $sem === 2) {
            return ["$y2-01-01", "$y2-06-30"];
        }
        return ["$y1-07-01", "$y1-12-31"];
    }
}

if (!function_exists('epoin_semester_label')) {
    /**
     * Label semester untuk UI.
     *   style 'full'  → "Semester 1 (Ganjil · Jul–Des)"
     *   style 'short' → "Ganjil" / "Genap"
     *   style 'range' → "Jul–Des" / "Jan–Jun"
     *   style 'num'   → "1" / "2"
     */
    function epoin_semester_label($sem, string $style = 'full'): string
    {
        $sem = ((int) $sem === 2) ? 2 : 1;
        switch ($style) {
            case 'short':
                return $sem === 1 ? 'Ganjil' : 'Genap';
            case 'range':
                return $sem === 1 ? 'Jul–Des' : 'Jan–Jun';
            case 'num':
                return (string) $sem;
            default:
                return $sem === 1
                    ? 'Semester 1 (Ganjil · Jul–Des)'
                    : 'Semester 2 (Genap · Jan–Jun)';
        }
    }
}

if (!function_exists('epoin_ta_aktif')) {
    /**
     * Baris TA aktif (ta_status=1) terbaru. Fallback ke TA terbaru bila tak ada
     * yang aktif. Mengembalikan ['ta_id'=>int, 'ta_nama'=>string] atau null.
     */
    function epoin_ta_aktif($koneksi): ?array
    {
        if (!($koneksi instanceof mysqli)) {
            return null;
        }
        $r = @mysqli_query($koneksi, "SELECT ta_id, ta_nama FROM ta WHERE ta_status=1 ORDER BY ta_id DESC LIMIT 1");
        $row = $r ? mysqli_fetch_assoc($r) : null;
        if (!$row) {
            $r = @mysqli_query($koneksi, "SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC LIMIT 1");
            $row = $r ? mysqli_fetch_assoc($r) : null;
        }
        return $row ?: null;
    }
}
