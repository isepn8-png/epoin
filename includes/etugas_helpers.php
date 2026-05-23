<?php
/**
 * E-Tugas helpers — Phase 1A foundation.
 * Safe to include multiple times (function_exists guards).
 */

if (!function_exists('etugas_h')) {
    function etugas_h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('etugas_escape')) {
    function etugas_escape($koneksi, $value)
    {
        return mysqli_real_escape_string($koneksi, (string) $value);
    }
}

if (!function_exists('etugas_user_is_admin')) {
    function etugas_user_is_admin()
    {
        if (function_exists('user_has_any_role')) {
            return user_has_any_role(['administrator', 'superadmin', 'admin']);
        }
        $roles = array_map('strtolower', (array) ($_SESSION['roles'] ?? []));
        $level = strtolower((string) ($_SESSION['level'] ?? ''));
        foreach (['administrator', 'superadmin', 'admin'] as $r) {
            if (in_array($r, $roles, true) || $level === $r) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('etugas_user_is_guru')) {
    function etugas_user_is_guru()
    {
        if (function_exists('user_has_role')) {
            return user_has_role('guru');
        }
        $roles = array_map('strtolower', (array) ($_SESSION['roles'] ?? []));
        $level = strtolower((string) ($_SESSION['level'] ?? ''));
        return in_array('guru', $roles, true) || $level === 'guru';
    }
}

if (!function_exists('etugas_get_active_ta')) {
    /**
     * @return array|null ['ta_id'=>int,'ta_nama'=>string] or null
     */
    function etugas_get_active_ta($koneksi)
    {
        if (!$koneksi instanceof mysqli) {
            return null;
        }

        $sql = "SELECT ta_id, ta_nama FROM ta WHERE ta_status = 1 ORDER BY ta_id DESC LIMIT 1";
        $res = mysqli_query($koneksi, $sql);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return [
                'ta_id' => (int) $row['ta_id'],
                'ta_nama' => (string) $row['ta_nama'],
            ];
        }

        $sql2 = "SELECT ta_id, ta_nama FROM ta ORDER BY ta_id DESC LIMIT 1";
        $res2 = mysqli_query($koneksi, $sql2);
        if ($res2 && ($row2 = mysqli_fetch_assoc($res2))) {
            return [
                'ta_id' => (int) $row2['ta_id'],
                'ta_nama' => (string) $row2['ta_nama'],
            ];
        }

        return null;
    }
}

if (!function_exists('etugas_get_siswa_kelas_aktif')) {
    /**
     * Resolve student's class for active TA (latest kelas_siswa row in that TA).
     *
     * @return array|null ['kelas_id','kelas_nama','ta_id','ta_nama','ks_id']
     */
    function etugas_get_siswa_kelas_aktif($koneksi, $siswa_id)
    {
        $siswa_id = (int) $siswa_id;
        if ($siswa_id <= 0 || !$koneksi instanceof mysqli) {
            return null;
        }

        $ta = etugas_get_active_ta($koneksi);
        $ta_id = $ta ? (int) $ta['ta_id'] : 0;

        if ($ta_id > 0) {
            $sql = "SELECT ks.ks_id, ks.ks_kelas AS kelas_id, k.kelas_nama, k.kelas_ta AS ta_id, t.ta_nama
                    FROM kelas_siswa ks
                    INNER JOIN kelas k ON k.kelas_id = ks.ks_kelas
                    LEFT JOIN ta t ON t.ta_id = k.kelas_ta
                    WHERE ks.ks_siswa = ?
                      AND k.kelas_ta = ?
                    ORDER BY ks.ks_id DESC
                    LIMIT 1";
            $stmt = mysqli_prepare($koneksi, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $siswa_id, $ta_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);
                if ($row) {
                    return [
                        'ks_id' => (int) $row['ks_id'],
                        'kelas_id' => (int) $row['kelas_id'],
                        'kelas_nama' => (string) ($row['kelas_nama'] ?? ''),
                        'ta_id' => (int) ($row['ta_id'] ?? $ta_id),
                        'ta_nama' => (string) ($row['ta_nama'] ?? ($ta['ta_nama'] ?? '')),
                    ];
                }
            }
        }

        $sqlFallback = "SELECT ks.ks_id, ks.ks_kelas AS kelas_id, k.kelas_nama, k.kelas_ta AS ta_id, t.ta_nama
                        FROM kelas_siswa ks
                        INNER JOIN kelas k ON k.kelas_id = ks.ks_kelas
                        LEFT JOIN ta t ON t.ta_id = k.kelas_ta
                        WHERE ks.ks_siswa = ?
                        ORDER BY ks.ks_id DESC
                        LIMIT 1";
        $stmt2 = mysqli_prepare($koneksi, $sqlFallback);
        if (!$stmt2) {
            return null;
        }
        mysqli_stmt_bind_param($stmt2, 'i', $siswa_id);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        $row2 = $result2 ? mysqli_fetch_assoc($result2) : null;
        mysqli_stmt_close($stmt2);
        if (!$row2) {
            return null;
        }

        return [
            'ks_id' => (int) $row2['ks_id'],
            'kelas_id' => (int) $row2['kelas_id'],
            'kelas_nama' => (string) ($row2['kelas_nama'] ?? ''),
            'ta_id' => (int) ($row2['ta_id'] ?? 0),
            'ta_nama' => (string) ($row2['ta_nama'] ?? ''),
        ];
    }
}

if (!function_exists('etugas_get_guru_scope')) {
    /**
     * Pengampu mapel rows for a guru (optional TA filter).
     *
     * @return array<int, array<string, mixed>>
     */
    function etugas_get_guru_scope($koneksi, $user_id, $ta_id = null)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !$koneksi instanceof mysqli) {
            return [];
        }

        $sql = "SELECT pm.id AS pengampu_id, pm.ta_id, pm.kelas_id, pm.mapel_id, pm.guru_user_id,
                       k.kelas_nama, m.mapel_nama, m.mapel_kode, t.ta_nama
                FROM pengampu_mapel pm
                INNER JOIN kelas k ON k.kelas_id = pm.kelas_id
                INNER JOIN mapel m ON m.mapel_id = pm.mapel_id
                LEFT JOIN ta t ON t.ta_id = pm.ta_id
                WHERE pm.guru_user_id = ?";
        $types = 'i';
        $params = [$user_id];

        if ($ta_id !== null && (int) $ta_id > 0) {
            $sql .= " AND pm.ta_id = ?";
            $types .= 'i';
            $params[] = (int) $ta_id;
        }

        $sql .= " ORDER BY t.ta_id DESC, k.kelas_nama ASC, m.mapel_nama ASC";

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return [];
        }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('etugas_is_valid_url')) {
    function etugas_is_valid_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('etugas_classify_link')) {
    function etugas_classify_link($url)
    {
        if (!etugas_is_valid_url($url)) {
            return 'lainnya';
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host);

        if (strpos($host, 'drive.google.com') !== false || strpos($host, 'docs.google.com') !== false) {
            if (strpos($host, 'docs.google.com') !== false && strpos($url, '/document/') !== false) {
                return 'docs';
            }
            return 'drive';
        }
        if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) {
            return 'youtube';
        }
        if (strpos($host, 'canva.com') !== false) {
            return 'canva';
        }

        return 'lainnya';
    }
}

if (!function_exists('etugas_table_exists')) {
    /**
     * Check table existence via information_schema (prepared). Avoid SHOW TABLES LIKE ? — not supported.
     */
    function etugas_table_exists($koneksi, $tableName)
    {
        if (!$koneksi instanceof mysqli) {
            return false;
        }

        $tableName = (string) $tableName;
        if ($tableName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            return false;
        }

        $sql = 'SELECT COUNT(*) AS total
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] etugas_table_exists prepare failed: ' . mysqli_error($koneksi));

            return false;
        }

        mysqli_stmt_bind_param($stmt, 's', $tableName);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[etugas] etugas_table_exists execute failed: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);

            return false;
        }

        $total = 0;
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $total = (int) ($row['total'] ?? 0);
        }
        mysqli_stmt_close($stmt);

        return $total > 0;
    }
}

if (!function_exists('etugas_tables_status')) {
    /**
     * @return array{ready: bool, missing: string[]}
     */
    function etugas_tables_status($koneksi)
    {
        $required = ['etugas', 'etugas_pengumpulan'];
        $missing = [];
        foreach ($required as $table) {
            if (!etugas_table_exists($koneksi, $table)) {
                $missing[] = $table;
            }
        }

        return [
            'ready' => empty($missing),
            'missing' => $missing,
        ];
    }
}

if (!function_exists('etugas_tables_ready')) {
    function etugas_tables_ready($koneksi)
    {
        return etugas_tables_status($koneksi)['ready'];
    }
}

/* ===================== Phase 1B: access, CSRF, validation ===================== */

if (!function_exists('etugas_is_local_env')) {
    function etugas_is_local_env()
    {
        if (defined('APP_ENV')) {
            return in_array(APP_ENV, ['local', 'development'], true);
        }
        $env = strtolower((string) (getenv('APP_ENV') ?: ''));
        return in_array($env, ['local', 'development'], true);
    }
}

if (!function_exists('etugas_require_access')) {
    function etugas_require_access()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!etugas_user_is_admin() && !etugas_user_is_guru()) {
            http_response_code(403);
            die('403 Forbidden: modul e-Tugas hanya untuk Administrator atau Guru.');
        }
    }
}

if (!function_exists('etugas_csrf_token')) {
    function etugas_csrf_token()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['etugas_csrf_token'])) {
            $_SESSION['etugas_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['etugas_csrf_token'];
    }
}

if (!function_exists('etugas_csrf_field')) {
    function etugas_csrf_field()
    {
        $t = etugas_csrf_token();
        return '<input type="hidden" name="etugas_csrf" value="' . etugas_h($t) . '">';
    }
}

if (!function_exists('etugas_verify_csrf')) {
    function etugas_verify_csrf($token = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $token ?? ($_POST['etugas_csrf'] ?? '');
        $expected = (string) ($_SESSION['etugas_csrf_token'] ?? '');
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}

if (!function_exists('etugas_valid_statuses')) {
    function etugas_valid_statuses()
    {
        return ['draft', 'aktif', 'ditutup', 'arsip'];
    }
}

if (!function_exists('etugas_is_valid_status')) {
    function etugas_is_valid_status($status)
    {
        return in_array((string) $status, etugas_valid_statuses(), true);
    }
}

if (!function_exists('etugas_flash_redirect')) {
    function etugas_flash_redirect($url, $type, $message)
    {
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $q = http_build_query([
            'alert' => $type,
            'msg' => $message,
        ]);
        header('Location: ' . $url . $sep . $q);
        exit;
    }
}

if (!function_exists('etugas_alert_from_request')) {
    function etugas_alert_from_request()
    {
        $type = isset($_GET['alert']) ? (string) $_GET['alert'] : '';
        $msg = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
        if ($type === '' || $msg === '') {
            return null;
        }
        return ['type' => $type, 'msg' => $msg];
    }
}

if (!function_exists('etugas_admin_context')) {
    /**
     * @return array{user_id:int,is_admin:bool,is_guru:bool,scope:array}
     */
    function etugas_admin_context($koneksi)
    {
        etugas_require_access();
        $userId = (int) ($_SESSION['id'] ?? 0);
        $isAdmin = etugas_user_is_admin();
        $isGuru = etugas_user_is_guru() && !$isAdmin;
        $scope = [];
        if ($isGuru && $userId > 0) {
            $scope = etugas_get_guru_scope($koneksi, $userId, null);
        }
        return [
            'user_id' => $userId,
            'is_admin' => $isAdmin,
            'is_guru' => $isGuru,
            'scope' => $scope,
        ];
    }
}

if (!function_exists('etugas_scope_has')) {
    function etugas_scope_has(array $scope, $taId, $kelasId, $mapelId)
    {
        $taId = (int) $taId;
        $kelasId = (int) $kelasId;
        $mapelId = (int) $mapelId;
        foreach ($scope as $row) {
            if ((int) ($row['ta_id'] ?? 0) === $taId
                && (int) ($row['kelas_id'] ?? 0) === $kelasId
                && (int) ($row['mapel_id'] ?? 0) === $mapelId) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('etugas_kelas_in_ta')) {
    function etugas_kelas_in_ta($koneksi, $kelasId, $taId)
    {
        $sql = 'SELECT COUNT(*) AS total FROM kelas WHERE kelas_id = ? AND kelas_ta = ?';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $kelasId, $taId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row && (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('etugas_fetch_by_id')) {
    function etugas_fetch_by_id($koneksi, $etugasId)
    {
        $etugasId = (int) $etugasId;
        if ($etugasId <= 0) {
            return null;
        }
        $sql = 'SELECT e.*, k.kelas_nama, m.mapel_nama, m.mapel_kode, t.ta_nama
                FROM etugas e
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                WHERE e.etugas_id = ?
                LIMIT 1';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $etugasId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('etugas_user_can_manage')) {
    function etugas_user_can_manage(array $ctx, array $row)
    {
        if (!empty($ctx['is_admin'])) {
            return true;
        }
        if (empty($ctx['is_guru'])) {
            return false;
        }
        $userId = (int) ($ctx['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        if ((int) ($row['guru_user_id'] ?? 0) !== $userId) {
            return false;
        }
        return etugas_scope_has(
            $ctx['scope'] ?? [],
            (int) ($row['ta_id'] ?? 0),
            (int) ($row['kelas_id'] ?? 0),
            (int) ($row['mapel_id'] ?? 0)
        );
    }
}

if (!function_exists('etugas_count_pengumpulan_for_task')) {
    function etugas_count_pengumpulan_for_task($koneksi, $etugasId)
    {
        $etugasId = (int) $etugasId;
        if ($etugasId <= 0) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) AS total FROM etugas_pengumpulan WHERE etugas_id = ?';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] count pengumpulan prepare: ' . mysqli_error($koneksi));
            return -1;
        }
        mysqli_stmt_bind_param($stmt, 'i', $etugasId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return -1;
        }
        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('etugas_map_pengumpulan_counts')) {
    /**
     * @param int[] $etugasIds
     * @return array<int,int> etugas_id => count
     */
    function etugas_map_pengumpulan_counts($koneksi, array $etugasIds)
    {
        $ids = [];
        foreach ($etugasIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT etugas_id, COUNT(*) AS cnt FROM etugas_pengumpulan
                WHERE etugas_id IN (' . $placeholders . ') GROUP BY etugas_id';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] map pengumpulan counts prepare: ' . mysqli_error($koneksi));
            return [];
        }
        $types = str_repeat('i', count($ids));
        etugas_bind_params($stmt, $types, $ids);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $out = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $out[(int) $row['etugas_id']] = (int) $row['cnt'];
            }
        }
        mysqli_stmt_close($stmt);
        return $out;
    }
}

if (!function_exists('etugas_delete_assignment_if_empty')) {
    /**
     * Permanently delete etugas row only when zero pengumpulan rows exist.
     *
     * @return array{ok:bool,reason:string}
     *   reason: deleted | not_found | has_submissions | db_error
     */
    function etugas_delete_assignment_if_empty($koneksi, $etugasId)
    {
        $etugasId = (int) $etugasId;
        if ($etugasId <= 0) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        if (!etugas_fetch_by_id($koneksi, $etugasId)) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        // Atomic: delete only when no pengumpulan row exists (race-safe vs count-then-delete).
        $sql = 'DELETE FROM etugas
                WHERE etugas_id = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM etugas_pengumpulan p WHERE p.etugas_id = etugas.etugas_id
                  )';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] delete prepare: ' . mysqli_error($koneksi));
            return ['ok' => false, 'reason' => 'db_error'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $etugasId);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('[etugas] delete execute: ' . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'reason' => 'db_error'];
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected >= 1) {
            return ['ok' => true, 'reason' => 'deleted'];
        }

        if (!etugas_fetch_by_id($koneksi, $etugasId)) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $count = etugas_count_pengumpulan_for_task($koneksi, $etugasId);
        if ($count > 0) {
            return ['ok' => false, 'reason' => 'has_submissions'];
        }

        return ['ok' => false, 'reason' => 'db_error'];
    }
}

if (!function_exists('etugas_validate_assignment')) {
    /**
     * @return array{ok:bool,errors:array<string,string>,data:array<string,mixed>}
     */
    function etugas_validate_assignment($koneksi, array $input, array $ctx)
    {
        $errors = [];
        $taId = (int) ($input['ta_id'] ?? 0);
        $kelasId = (int) ($input['kelas_id'] ?? 0);
        $mapelId = (int) ($input['mapel_id'] ?? 0);
        $judul = trim((string) ($input['judul'] ?? ''));
        $instruksi = trim((string) ($input['instruksi'] ?? ''));
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
        $allowText = !empty($input['allow_text']) ? 1 : 0;
        $allowLink = !empty($input['allow_link']) ? 1 : 0;
        $izinkanTerlambat = !empty($input['izinkan_terlambat']) ? 1 : 0;
        $deadlineRaw = trim((string) ($input['deadline_at'] ?? ''));
        $deadlineAt = null;

        if ($taId <= 0) {
            $errors['ta_id'] = 'Tahun ajaran wajib dipilih.';
        }
        if ($kelasId <= 0) {
            $errors['kelas_id'] = 'Kelas wajib dipilih.';
        }
        if ($mapelId <= 0) {
            $errors['mapel_id'] = 'Mapel wajib dipilih.';
        }
        if ($judul === '') {
            $errors['judul'] = 'Judul tugas wajib diisi.';
        } elseif (mb_strlen($judul) > 200) {
            $errors['judul'] = 'Judul maksimal 200 karakter.';
        }
        if (!$allowText && !$allowLink) {
            $errors['allow'] = 'Centang minimal satu jenis jawaban (teks atau link).';
        }
        if (!etugas_is_valid_status($status)) {
            $errors['status'] = 'Status tidak valid.';
        }

        if ($deadlineRaw !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $deadlineRaw);
            if (!$dt) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $deadlineRaw);
            }
            if (!$dt) {
                $errors['deadline_at'] = 'Format deadline tidak valid.';
            } else {
                $deadlineAt = $dt->format('Y-m-d H:i:s');
            }
        }

        if ($taId > 0 && $kelasId > 0 && !etugas_kelas_in_ta($koneksi, $kelasId, $taId)) {
            $errors['kelas_id'] = 'Kelas tidak termasuk tahun ajaran yang dipilih.';
        }

        if (empty($errors) && !empty($ctx['is_guru'])) {
            if (!etugas_scope_has($ctx['scope'] ?? [], $taId, $kelasId, $mapelId)) {
                $errors['mapel_id'] = 'Anda tidak ditugaskan mengampu kombinasi kelas/mapel ini.';
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'data' => [
                'ta_id' => $taId,
                'kelas_id' => $kelasId,
                'mapel_id' => $mapelId,
                'judul' => $judul,
                'instruksi' => $instruksi,
                'deadline_at' => $deadlineAt,
                'allow_text' => $allowText,
                'allow_link' => $allowLink,
                'izinkan_terlambat' => $izinkanTerlambat,
                'status' => $status,
            ],
        ];
    }
}

if (!function_exists('etugas_parse_kelas_ids')) {
    /**
     * Parse kelas_ids[] from POST (unique positive integers).
     *
     * @return int[]
     */
    function etugas_parse_kelas_ids(array $input)
    {
        $raw = $input['kelas_ids'] ?? null;
        if (!is_array($raw)) {
            $one = (int) ($input['kelas_id'] ?? 0);
            return $one > 0 ? [$one] : [];
        }
        $ids = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}

if (!function_exists('etugas_assignment_is_duplicate')) {
    /**
     * True if draft/aktif task with same TA, kelas, mapel, judul, deadline exists.
     */
    function etugas_assignment_is_duplicate($koneksi, $taId, $kelasId, $mapelId, $judul, $deadlineAt)
    {
        $taId = (int) $taId;
        $kelasId = (int) $kelasId;
        $mapelId = (int) $mapelId;
        $judul = (string) $judul;

        $sql = 'SELECT COUNT(*) AS total FROM etugas
                WHERE ta_id = ? AND kelas_id = ? AND mapel_id = ?
                  AND judul = ? AND status IN (\'draft\', \'aktif\')';
        $types = 'iiis';
        $params = [$taId, $kelasId, $mapelId, $judul];

        if ($deadlineAt === null || $deadlineAt === '') {
            $sql .= ' AND deadline_at IS NULL';
        } else {
            $sql .= ' AND deadline_at = ?';
            $types .= 's';
            $params[] = $deadlineAt;
        }

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] duplicate check prepare: ' . mysqli_error($koneksi));
            return false;
        }
        etugas_bind_params($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row && (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('etugas_validate_assignment_create')) {
    /**
     * Validate create form with multiple kelas_ids[].
     *
     * @return array{ok:bool,errors:array<string,string>,data:array<string,mixed>}
     */
    function etugas_validate_assignment_create($koneksi, array $input, array $ctx)
    {
        $errors = [];
        $taId = (int) ($input['ta_id'] ?? 0);
        $mapelId = (int) ($input['mapel_id'] ?? 0);
        $kelasIds = etugas_parse_kelas_ids($input);
        $judul = trim((string) ($input['judul'] ?? ''));
        $instruksi = trim((string) ($input['instruksi'] ?? ''));
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
        $allowText = !empty($input['allow_text']) ? 1 : 0;
        $allowLink = !empty($input['allow_link']) ? 1 : 0;
        $izinkanTerlambat = !empty($input['izinkan_terlambat']) ? 1 : 0;
        $deadlineRaw = trim((string) ($input['deadline_at'] ?? ''));
        $deadlineAt = null;

        if ($taId <= 0) {
            $errors['ta_id'] = 'Tahun ajaran wajib dipilih.';
        }
        if ($mapelId <= 0) {
            $errors['mapel_id'] = 'Mapel wajib dipilih.';
        }
        if ($kelasIds === []) {
            $errors['kelas_ids'] = 'Pilih minimal satu kelas tujuan.';
        }
        if ($judul === '') {
            $errors['judul'] = 'Judul tugas wajib diisi.';
        } elseif (mb_strlen($judul) > 200) {
            $errors['judul'] = 'Judul maksimal 200 karakter.';
        }
        if (!$allowText && !$allowLink) {
            $errors['allow'] = 'Centang minimal satu jenis jawaban (teks atau link).';
        }
        if (!etugas_is_valid_status($status)) {
            $errors['status'] = 'Status tidak valid.';
        }

        if ($deadlineRaw !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $deadlineRaw);
            if (!$dt) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $deadlineRaw);
            }
            if (!$dt) {
                $errors['deadline_at'] = 'Format deadline tidak valid.';
            } else {
                $deadlineAt = $dt->format('Y-m-d H:i:s');
            }
        }

        if (empty($errors) && $kelasIds !== []) {
            foreach ($kelasIds as $kelasId) {
                if (!etugas_kelas_in_ta($koneksi, $kelasId, $taId)) {
                    $errors['kelas_ids'] = 'Salah satu kelas tidak termasuk tahun ajaran yang dipilih.';
                    break;
                }
                if (!empty($ctx['is_guru']) && !etugas_scope_has($ctx['scope'] ?? [], $taId, $kelasId, $mapelId)) {
                    $errors['kelas_ids'] = 'Anda tidak berhak membuat tugas untuk salah satu kelas yang dipilih.';
                    break;
                }
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'data' => [
                'ta_id' => $taId,
                'kelas_ids' => $kelasIds,
                'mapel_id' => $mapelId,
                'judul' => $judul,
                'instruksi' => $instruksi,
                'deadline_at' => $deadlineAt,
                'allow_text' => $allowText,
                'allow_link' => $allowLink,
                'izinkan_terlambat' => $izinkanTerlambat,
                'status' => $status,
            ],
        ];
    }
}

if (!function_exists('etugas_create_assignments_batch')) {
    /**
     * Insert one etugas row per kelas_id; skip duplicates; rollback on insert failure.
     *
     * @param array<string,mixed> $data from etugas_validate_assignment_create
     * @param int[] $kelasIds
     * @return array{ok:bool,created:int,skipped:int,error?:string}
     */
    function etugas_create_assignments_batch($koneksi, array $data, array $kelasIds, $userId)
    {
        $userId = (int) $userId;
        $created = 0;
        $skipped = 0;

        $sql = 'INSERT INTO etugas (
                    ta_id, kelas_id, mapel_id, guru_user_id, judul, instruksi, deadline_at,
                    allow_text, allow_link, izinkan_terlambat, status, created_by, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] batch insert prepare: ' . mysqli_error($koneksi));
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'error' => 'Gagal menyiapkan penyimpanan tugas.'];
        }

        $deadline = $data['deadline_at'];
        $taId = (int) $data['ta_id'];
        $mapelId = (int) $data['mapel_id'];
        $judul = (string) $data['judul'];
        $instruksi = (string) $data['instruksi'];
        $allowText = (int) $data['allow_text'];
        $allowLink = (int) $data['allow_link'];
        $izinkanTerlambat = (int) $data['izinkan_terlambat'];
        $status = (string) $data['status'];

        mysqli_begin_transaction($koneksi);

        foreach ($kelasIds as $kelasId) {
            $kelasId = (int) $kelasId;
            if (etugas_assignment_is_duplicate($koneksi, $taId, $kelasId, $mapelId, $judul, $deadline)) {
                $skipped++;
                continue;
            }

            mysqli_stmt_bind_param(
                $stmt,
                'iiiisssiiisii',
                $taId,
                $kelasId,
                $mapelId,
                $userId,
                $judul,
                $instruksi,
                $deadline,
                $allowText,
                $allowLink,
                $izinkanTerlambat,
                $status,
                $userId,
                $userId
            );

            if (!mysqli_stmt_execute($stmt)) {
                error_log('[etugas] batch insert execute: ' . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                mysqli_rollback($koneksi);
                return [
                    'ok' => false,
                    'created' => 0,
                    'skipped' => 0,
                    'error' => 'Gagal menyimpan tugas. Tidak ada data yang disimpan.',
                ];
            }
            $created++;
        }

        mysqli_stmt_close($stmt);
        mysqli_commit($koneksi);

        return ['ok' => true, 'created' => $created, 'skipped' => $skipped];
    }
}

if (!function_exists('etugas_format_batch_create_message')) {
    function etugas_format_batch_create_message($created, $skipped)
    {
        $created = (int) $created;
        $skipped = (int) $skipped;
        $parts = [];
        if ($created > 0) {
            $parts[] = $created . ' tugas dibuat';
        }
        if ($skipped > 0) {
            $parts[] = $skipped . ' dilewati karena sudah ada';
        }
        if ($parts === []) {
            return 'Tidak ada tugas baru dibuat.';
        }
        return implode(', ', $parts) . '.';
    }
}

if (!function_exists('etugas_list_ta_options')) {
    function etugas_list_ta_options($koneksi)
    {
        $rows = [];
        $res = mysqli_query($koneksi, 'SELECT ta_id, ta_nama, ta_status FROM ta ORDER BY ta_status DESC, ta_nama ASC');
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return $rows;
    }
}

if (!function_exists('etugas_form_matrix')) {
    /**
     * Build TA / kelas / mapel options for forms (guru = scope only).
     *
     * @return array{ta:array,kelas:array,mapel:array,combos:array<int,array{ta_id,kelas_id,mapel_id}>}
     */
    function etugas_form_matrix($koneksi, array $ctx)
    {
        if (!empty($ctx['is_admin'])) {
            $ta = etugas_list_ta_options($koneksi);
            $kelas = [];
            $resK = mysqli_query($koneksi, 'SELECT k.kelas_id, k.kelas_nama, k.kelas_ta, j.jurusan_nama
                FROM kelas k LEFT JOIN jurusan j ON j.jurusan_id = k.kelas_jurusan
                ORDER BY k.kelas_ta DESC, k.kelas_nama ASC');
            if ($resK) {
                while ($r = mysqli_fetch_assoc($resK)) {
                    $kelas[] = $r;
                }
            }
            $mapel = [];
            $resM = mysqli_query($koneksi, 'SELECT mapel_id, mapel_kode, mapel_nama FROM mapel ORDER BY mapel_nama ASC');
            if ($resM) {
                while ($r = mysqli_fetch_assoc($resM)) {
                    $mapel[] = $r;
                }
            }
            $combos = [];
            foreach ($kelas as $k) {
                foreach ($mapel as $m) {
                    $combos[] = [
                        'ta_id' => (int) $k['kelas_ta'],
                        'kelas_id' => (int) $k['kelas_id'],
                        'mapel_id' => (int) $m['mapel_id'],
                    ];
                }
            }
            return ['ta' => $ta, 'kelas' => $kelas, 'mapel' => $mapel, 'combos' => $combos];
        }

        $scope = $ctx['scope'] ?? [];
        $taIds = [];
        $kelas = [];
        $mapel = [];
        $combos = [];
        $mapelSeen = [];
        $kelasSeen = [];
        $taSeen = [];

        foreach ($scope as $s) {
            $taId = (int) ($s['ta_id'] ?? 0);
            $kelasId = (int) ($s['kelas_id'] ?? 0);
            $mapelId = (int) ($s['mapel_id'] ?? 0);
            if ($taId && !isset($taSeen[$taId])) {
                $taSeen[$taId] = ['ta_id' => $taId, 'ta_nama' => (string) ($s['ta_nama'] ?? ''), 'ta_status' => 1];
            }
            if ($kelasId && !isset($kelasSeen[$kelasId])) {
                $kelasSeen[$kelasId] = [
                    'kelas_id' => $kelasId,
                    'kelas_nama' => (string) ($s['kelas_nama'] ?? ''),
                    'kelas_ta' => $taId,
                ];
            }
            if ($mapelId && !isset($mapelSeen[$mapelId])) {
                $mapelSeen[$mapelId] = [
                    'mapel_id' => $mapelId,
                    'mapel_kode' => (string) ($s['mapel_kode'] ?? ''),
                    'mapel_nama' => (string) ($s['mapel_nama'] ?? ''),
                ];
            }
            $combos[] = ['ta_id' => $taId, 'kelas_id' => $kelasId, 'mapel_id' => $mapelId];
        }

        return [
            'ta' => array_values($taSeen),
            'kelas' => array_values($kelasSeen),
            'mapel' => array_values($mapelSeen),
            'combos' => $combos,
        ];
    }
}

if (!function_exists('etugas_status_badge')) {
    function etugas_status_badge($status)
    {
        $status = (string) $status;
        $map = [
            'draft' => ['label' => 'Draft', 'class' => 'label-default'],
            'aktif' => ['label' => 'Aktif', 'class' => 'label-success'],
            'ditutup' => ['label' => 'Ditutup', 'class' => 'label-warning'],
            'arsip' => ['label' => 'Arsip', 'class' => 'label-default'],
        ];
        $m = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'label-default'];
        return '<span class="label ' . etugas_h($m['class']) . ' etugas-badge-' . etugas_h($status) . '">'
            . etugas_h($m['label']) . '</span>';
    }
}

if (!function_exists('etugas_jenis_label')) {
    function etugas_jenis_label($allowText, $allowLink)
    {
        $parts = [];
        if ($allowText) {
            $parts[] = 'Teks';
        }
        if ($allowLink) {
            $parts[] = 'Link';
        }
        return $parts ? implode(' + ', $parts) : '-';
    }
}

if (!function_exists('etugas_build_list_where')) {
    /**
     * @return array{sql:string,types:string,params:array}
     */
    function etugas_build_list_where(array $ctx, array $filters)
    {
        $sql = ' WHERE 1=1';
        $types = '';
        $params = [];

        if (!empty($ctx['is_guru'])) {
            $sql .= ' AND e.guru_user_id = ?';
            $types .= 'i';
            $params[] = (int) $ctx['user_id'];

            $scope = $ctx['scope'] ?? [];
            if (empty($scope)) {
                $sql .= ' AND 1=0';
            } else {
                $parts = [];
                foreach ($scope as $s) {
                    $parts[] = '(e.ta_id = ? AND e.kelas_id = ? AND e.mapel_id = ?)';
                    $types .= 'iii';
                    $params[] = (int) ($s['ta_id'] ?? 0);
                    $params[] = (int) ($s['kelas_id'] ?? 0);
                    $params[] = (int) ($s['mapel_id'] ?? 0);
                }
                $sql .= ' AND (' . implode(' OR ', $parts) . ')';
            }
        }

        if (!empty($filters['ta_id'])) {
            $sql .= ' AND e.ta_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['ta_id'];
        }
        if (!empty($filters['kelas_id'])) {
            $sql .= ' AND e.kelas_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['kelas_id'];
        }
        if (!empty($filters['mapel_id'])) {
            $sql .= ' AND e.mapel_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['mapel_id'];
        }
        if (!empty($filters['status']) && etugas_is_valid_status($filters['status'])) {
            $sql .= ' AND e.status = ?';
            $types .= 's';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (e.judul LIKE ? OR e.instruksi LIKE ?)';
            $types .= 'ss';
            $like = '%' . (string) $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return ['sql' => $sql, 'types' => $types, 'params' => $params];
    }
}

if (!function_exists('etugas_count_summary')) {
    function etugas_count_summary($koneksi, array $ctx, array $filters)
    {
        $out = ['aktif' => 0, 'draft' => 0, 'ditutup' => 0, 'arsip' => 0, 'total' => 0];
        $base = etugas_build_list_where($ctx, array_diff_key($filters, ['status' => '']));
        foreach (array_keys($out) as $st) {
            if ($st === 'total') {
                continue;
            }
            $w = $base;
            $w['sql'] .= ' AND e.status = ?';
            $w['types'] .= 's';
            $w['params'][] = $st;
            $sql = 'SELECT COUNT(*) AS c FROM etugas e' . $w['sql'];
            $stmt = mysqli_prepare($koneksi, $sql);
            if ($stmt) {
                etugas_bind_params($stmt, $w['types'], $w['params']);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                $out[$st] = (int) ($row['c'] ?? 0);
                mysqli_stmt_close($stmt);
            }
        }
        $out['total'] = $out['aktif'] + $out['draft'] + $out['ditutup'] + $out['arsip'];
        return $out;
    }
}

if (!function_exists('etugas_bind_params')) {
    function etugas_bind_params($stmt, $types, array $params)
    {
        if ($types === '' || !$params) {
            return;
        }
        $refs = [];
        $refs[] = &$types;
        foreach ($params as $k => $v) {
            $refs[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

if (!function_exists('etugas_list_assignments')) {
    function etugas_list_assignments($koneksi, array $ctx, array $filters)
    {
        $w = etugas_build_list_where($ctx, $filters);
        $sql = 'SELECT e.*, k.kelas_nama, m.mapel_nama, m.mapel_kode, t.ta_nama
                FROM etugas e
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id'
            . $w['sql']
            . ' ORDER BY e.created_at DESC, e.etugas_id DESC';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] list prepare: ' . mysqli_error($koneksi));
            return [];
        }
        if ($w['types'] !== '') {
            etugas_bind_params($stmt, $w['types'], $w['params']);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('etugas_deadline_for_input')) {
    function etugas_deadline_for_input($deadlineAt)
    {
        if (!$deadlineAt) {
            return '';
        }
        $ts = strtotime((string) $deadlineAt);
        return $ts ? date('Y-m-d\TH:i', $ts) : '';
    }
}

/* ===================== Phase 2: siswa list & submission ===================== */

if (!function_exists('etugas_siswa_context')) {
    /**
     * @return array{siswa_id:int,kelas:array|null,tables_ready:bool}
     */
    function etugas_siswa_context($koneksi)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (($_SESSION['level'] ?? '') !== 'siswa') {
            http_response_code(403);
            die('403 Forbidden: halaman ini untuk akun siswa.');
        }
        $siswaId = (int) ($_SESSION['id'] ?? 0);
        $kelas = $siswaId > 0 ? etugas_get_siswa_kelas_aktif($koneksi, $siswaId) : null;

        return [
            'siswa_id' => $siswaId,
            'kelas' => $kelas,
            'tables_ready' => etugas_tables_ready($koneksi),
        ];
    }
}

if (!function_exists('etugas_format_datetime_id')) {
    function etugas_format_datetime_id($dt)
    {
        if (!$dt) {
            return '—';
        }
        $ts = strtotime((string) $dt);
        return $ts ? date('d/m/Y H:i', $ts) : etugas_h($dt);
    }
}

if (!function_exists('etugas_pengumpulan_status_badge')) {
    function etugas_pengumpulan_status_badge($status, $isTerlambat = 0)
    {
        $status = (string) $status;
        $map = [
            'terkirim' => ['label' => 'Terkirim', 'class' => 'label-info'],
            'ditinjau' => ['label' => 'Ditinjau', 'class' => 'label-primary'],
            'revisi' => ['label' => 'Perlu Revisi', 'class' => 'label-warning'],
            'selesai' => ['label' => 'Selesai', 'class' => 'label-success'],
        ];
        $html = '';
        if ($status && isset($map[$status])) {
            $m = $map[$status];
            $html = '<span class="label ' . etugas_h($m['class']) . '">' . etugas_h($m['label']) . '</span>';
        } elseif ($status === '') {
            $html = '<span class="label label-default">Belum dikumpulkan</span>';
        } else {
            $html = '<span class="label label-default">' . etugas_h(ucfirst($status)) . '</span>';
        }
        if ($isTerlambat) {
            $html .= ' <span class="label label-warning">Terlambat</span>';
        }
        return $html;
    }
}

if (!function_exists('etugas_is_safe_submission_link')) {
    function etugas_is_safe_submission_link($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if (mb_strlen($url) > 1000) {
            return false;
        }
        if (preg_match('/^\s*(javascript|data|file|vbscript):/i', $url)) {
            return false;
        }
        return etugas_is_valid_url($url);
    }
}

if (!function_exists('etugas_fetch_submission')) {
    function etugas_fetch_submission($koneksi, $etugasId, $siswaId)
    {
        $sql = 'SELECT * FROM etugas_pengumpulan WHERE etugas_id = ? AND siswa_id = ? LIMIT 1';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $etugasId, $siswaId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('etugas_fetch_task_for_siswa')) {
    /**
     * Task visible to siswa: same kelas/TA, status aktif or ditutup (not draft/arsip).
     */
    function etugas_fetch_task_for_siswa($koneksi, $etugasId, $siswaId, ?array $kelasInfo)
    {
        $etugasId = (int) $etugasId;
        $siswaId = (int) $siswaId;
        if ($etugasId <= 0 || $siswaId <= 0 || !$kelasInfo) {
            return null;
        }
        $kelasId = (int) ($kelasInfo['kelas_id'] ?? 0);
        $taId = (int) ($kelasInfo['ta_id'] ?? 0);
        if ($kelasId <= 0) {
            return null;
        }

        $sql = "SELECT e.*, m.mapel_nama, m.mapel_kode, k.kelas_nama, t.ta_nama
                FROM etugas e
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                WHERE e.etugas_id = ?
                  AND e.kelas_id = ?
                  AND e.ta_id = ?
                  AND e.status IN ('aktif', 'ditutup')
                LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $etugasId, $kelasId, $taId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('etugas_list_tasks_for_siswa')) {
    function etugas_list_tasks_for_siswa($koneksi, $siswaId, ?array $kelasInfo)
    {
        $siswaId = (int) $siswaId;
        if ($siswaId <= 0 || !$kelasInfo) {
            return [];
        }
        $kelasId = (int) ($kelasInfo['kelas_id'] ?? 0);
        $taId = (int) ($kelasInfo['ta_id'] ?? 0);
        if ($kelasId <= 0) {
            return [];
        }

        $sql = "SELECT e.*, m.mapel_nama, m.mapel_kode, k.kelas_nama, t.ta_nama,
                       p.pengumpulan_id, p.jawaban_teks, p.link_url, p.link_jenis, p.catatan_siswa,
                       p.status AS sub_status, p.is_terlambat, p.nilai, p.catatan_guru,
                       p.reviewed_at, p.created_at AS sub_created_at, p.updated_at AS sub_updated_at
                FROM etugas e
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                LEFT JOIN etugas_pengumpulan p ON p.etugas_id = e.etugas_id AND p.siswa_id = ?
                WHERE e.kelas_id = ?
                  AND e.ta_id = ?
                  AND e.status IN ('aktif', 'ditutup')
                ORDER BY e.deadline_at IS NULL, e.deadline_at ASC, e.etugas_id DESC";

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] siswa list prepare: ' . mysqli_error($koneksi));
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'iii', $siswaId, $kelasId, $taId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $submission = null;
                if (!empty($row['pengumpulan_id'])) {
                    $submission = [
                        'pengumpulan_id' => (int) $row['pengumpulan_id'],
                        'jawaban_teks' => $row['jawaban_teks'],
                        'link_url' => $row['link_url'],
                        'link_jenis' => $row['link_jenis'],
                        'catatan_siswa' => $row['catatan_siswa'],
                        'status' => $row['sub_status'],
                        'is_terlambat' => (int) $row['is_terlambat'],
                        'nilai' => $row['nilai'],
                        'catatan_guru' => $row['catatan_guru'],
                        'reviewed_at' => $row['reviewed_at'],
                        'created_at' => $row['sub_created_at'],
                        'updated_at' => $row['sub_updated_at'],
                    ];
                }
                $rows[] = etugas_enrich_siswa_task_row($row, $submission);
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('etugas_enrich_siswa_task_row')) {
    function etugas_enrich_siswa_task_row(array $task, ?array $submission)
    {
        $state = etugas_task_submission_state($task, $submission);
        $hasSubmission = $submission !== null && (
            trim((string) ($submission['jawaban_teks'] ?? '')) !== ''
            || trim((string) ($submission['link_url'] ?? '')) !== ''
        );

        return array_merge($task, [
            'submission' => $submission,
            'has_submission' => $hasSubmission,
            'sub_status' => $submission['status'] ?? null,
            'can_submit' => $state['can_submit'],
            'is_late' => !empty($state['is_late']),
            'block_reason' => (string) ($state['reason'] ?? ''),
        ]);
    }
}

if (!function_exists('etugas_task_submission_state')) {
    /**
     * @return array{can_submit:bool,is_late:bool,reason:string}
     */
    function etugas_task_submission_state(array $task, ?array $submission, $now = null)
    {
        $now = $now ?? time();
        $taskStatus = (string) ($task['status'] ?? '');
        $subStatus = $submission['status'] ?? null;

        if ($taskStatus === 'arsip' || $taskStatus === 'draft') {
            return ['can_submit' => false, 'is_late' => false, 'reason' => 'Tugas tidak tersedia.'];
        }
        if ($taskStatus === 'ditutup') {
            return ['can_submit' => false, 'is_late' => false, 'reason' => 'Tugas sudah ditutup oleh guru.'];
        }
        if ($taskStatus !== 'aktif') {
            return ['can_submit' => false, 'is_late' => false, 'reason' => 'Tugas tidak dapat dikumpulkan.'];
        }
        if ($subStatus === 'selesai') {
            return ['can_submit' => false, 'is_late' => false, 'reason' => 'Pengumpulan sudah selesai dan tidak dapat diubah.'];
        }

        $isLate = false;
        $deadline = !empty($task['deadline_at']) ? strtotime((string) $task['deadline_at']) : null;
        if ($deadline && $now > $deadline) {
            if (empty($task['izinkan_terlambat'])) {
                return [
                    'can_submit' => false,
                    'is_late' => false,
                    'reason' => 'Batas waktu pengumpulan telah lewat.',
                ];
            }
            $isLate = true;
        }

        return ['can_submit' => true, 'is_late' => $isLate, 'reason' => ''];
    }
}

if (!function_exists('etugas_siswa_summary')) {
    function etugas_siswa_summary(array $tasks)
    {
        $out = [
            'aktif' => 0,
            'belum' => 0,
            'sudah' => 0,
            'revisi_terlambat' => 0,
        ];
        foreach ($tasks as $t) {
            if (($t['status'] ?? '') === 'aktif') {
                $out['aktif']++;
            }
            $sub = $t['sub_status'] ?? null;
            if (!$t['has_submission']) {
                $out['belum']++;
            } elseif (in_array($sub, ['terkirim', 'ditinjau', 'selesai'], true)) {
                $out['sudah']++;
            }
            if ($sub === 'revisi' || !empty($t['is_late'])) {
                $out['revisi_terlambat']++;
            }
        }
        return $out;
    }
}

if (!function_exists('etugas_filter_siswa_tasks')) {
    function etugas_filter_siswa_tasks(array $tasks, array $filters)
    {
        $mapelId = (int) ($filters['mapel_id'] ?? 0);
        $statusFilter = (string) ($filters['status'] ?? '');

        return array_values(array_filter($tasks, function ($t) use ($mapelId, $statusFilter) {
            if ($mapelId > 0 && (int) ($t['mapel_id'] ?? 0) !== $mapelId) {
                return false;
            }
            switch ($statusFilter) {
                case 'belum':
                    return empty($t['has_submission']);
                case 'sudah':
                    return !empty($t['has_submission']);
                case 'revisi':
                    return ($t['sub_status'] ?? '') === 'revisi';
                case 'terlambat':
                    return !empty($t['is_late']);
                default:
                    return true;
            }
        }));
    }
}

if (!function_exists('etugas_mapel_options_from_tasks')) {
    function etugas_mapel_options_from_tasks(array $tasks)
    {
        $seen = [];
        $out = [];
        foreach ($tasks as $t) {
            $id = (int) ($t['mapel_id'] ?? 0);
            if ($id && !isset($seen[$id])) {
                $seen[$id] = true;
                $out[] = [
                    'mapel_id' => $id,
                    'mapel_nama' => (string) ($t['mapel_nama'] ?? ''),
                ];
            }
        }
        usort($out, function ($a, $b) {
            return strcmp($a['mapel_nama'], $b['mapel_nama']);
        });
        return $out;
    }
}

if (!function_exists('etugas_validate_submission')) {
    /**
     * @return array{ok:bool,errors:array<string,string>,data:array<string,mixed>}
     */
    function etugas_validate_submission(array $task, array $input)
    {
        $errors = [];
        $allowText = !empty($task['allow_text']);
        $allowLink = !empty($task['allow_link']);
        $jawaban = $allowText ? trim((string) ($input['jawaban_teks'] ?? '')) : '';
        $link = $allowLink ? trim((string) ($input['link_url'] ?? '')) : '';
        $catatan = trim((string) ($input['catatan_siswa'] ?? ''));

        if ($allowText && $jawaban !== '' && mb_strlen($jawaban) > 65535) {
            $errors['jawaban_teks'] = 'Jawaban teks terlalu panjang.';
        }
        if ($allowLink && $link !== '') {
            if (!etugas_is_safe_submission_link($link)) {
                $errors['link_url'] = 'Link tidak valid. Gunakan http:// atau https:// (mis. Google Drive, YouTube, Canva).';
            }
        }
        if ($catatan !== '' && mb_strlen($catatan) > 5000) {
            $errors['catatan_siswa'] = 'Catatan terlalu panjang.';
        }

        $hasText = $allowText && $jawaban !== '';
        $hasLink = $allowLink && $link !== '';
        if (!$hasText && !$hasLink) {
            $errors['submit'] = 'Isi minimal salah satu jawaban yang diizinkan (teks atau link).';
        }

        $linkJenis = 'lainnya';
        if ($hasLink) {
            $linkJenis = etugas_classify_link($link);
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'data' => [
                'jawaban_teks' => $hasText ? $jawaban : null,
                'link_url' => $hasLink ? $link : null,
                'link_jenis' => $linkJenis,
                'catatan_siswa' => $catatan !== '' ? $catatan : null,
            ],
        ];
    }
}

if (!function_exists('etugas_save_submission')) {
    function etugas_save_submission($koneksi, array $task, $siswaId, array $data, $isLate, $previousStatus = null)
    {
        $etugasId = (int) ($task['etugas_id'] ?? 0);
        $siswaId = (int) $siswaId;
        if ($etugasId <= 0 || $siswaId <= 0) {
            return false;
        }

        $newStatus = 'terkirim';
        if ($previousStatus === 'revisi') {
            $newStatus = 'terkirim';
        }

        $sql = 'INSERT INTO etugas_pengumpulan (
                    etugas_id, siswa_id, jawaban_teks, link_url, link_jenis, catatan_siswa,
                    status, is_terlambat
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    jawaban_teks = VALUES(jawaban_teks),
                    link_url = VALUES(link_url),
                    link_jenis = VALUES(link_jenis),
                    catatan_siswa = VALUES(catatan_siswa),
                    status = VALUES(status),
                    is_terlambat = VALUES(is_terlambat),
                    updated_at = CURRENT_TIMESTAMP';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] save submission prepare: ' . mysqli_error($koneksi));
            return false;
        }

        $isLateInt = $isLate ? 1 : 0;
        $jawabanTeks = $data['jawaban_teks'];
        $linkUrl = $data['link_url'];
        $linkJenis = (string) ($data['link_jenis'] ?? 'lainnya');
        $catatanSiswa = $data['catatan_siswa'];
        mysqli_stmt_bind_param(
            $stmt,
            'iisssssi',
            $etugasId,
            $siswaId,
            $jawabanTeks,
            $linkUrl,
            $linkJenis,
            $catatanSiswa,
            $newStatus,
            $isLateInt
        );

        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            error_log('[etugas] save submission execute: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

/* ===================== Phase 3A: guru/admin review ===================== */

if (!function_exists('etugas_valid_pengumpulan_statuses')) {
    function etugas_valid_pengumpulan_statuses()
    {
        return ['terkirim', 'ditinjau', 'revisi', 'selesai'];
    }
}

if (!function_exists('etugas_is_valid_pengumpulan_status')) {
    function etugas_is_valid_pengumpulan_status($status)
    {
        return in_array((string) $status, etugas_valid_pengumpulan_statuses(), true);
    }
}

if (!function_exists('etugas_user_can_review')) {
    /**
     * Review access uses pengampu_mapel scope for guru (not etugas.guru_user_id alone).
     */
    function etugas_user_can_review(array $ctx, array $taskRow)
    {
        if (!empty($ctx['is_admin'])) {
            return true;
        }
        if (empty($ctx['is_guru'])) {
            return false;
        }
        return etugas_scope_has(
            $ctx['scope'] ?? [],
            (int) ($taskRow['ta_id'] ?? 0),
            (int) ($taskRow['kelas_id'] ?? 0),
            (int) ($taskRow['mapel_id'] ?? 0)
        );
    }
}

if (!function_exists('etugas_build_review_scope_sql')) {
    /**
     * Guru: pengampu_mapel combos only. Admin: no extra restriction.
     *
     * @return array{sql:string,types:string,params:array}
     */
    function etugas_build_review_scope_sql(array $ctx, $alias = 'e')
    {
        $a = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $alias) ? $alias : 'e';
        if (!empty($ctx['is_admin'])) {
            return ['sql' => '', 'types' => '', 'params' => []];
        }
        $scope = $ctx['scope'] ?? [];
        if (empty($ctx['is_guru']) || empty($scope)) {
            return ['sql' => ' AND 1=0', 'types' => '', 'params' => []];
        }
        $parts = [];
        $types = '';
        $params = [];
        foreach ($scope as $s) {
            $parts[] = "({$a}.ta_id = ? AND {$a}.kelas_id = ? AND {$a}.mapel_id = ?)";
            $types .= 'iii';
            $params[] = (int) ($s['ta_id'] ?? 0);
            $params[] = (int) ($s['kelas_id'] ?? 0);
            $params[] = (int) ($s['mapel_id'] ?? 0);
        }
        return [
            'sql' => ' AND (' . implode(' OR ', $parts) . ')',
            'types' => $types,
            'params' => $params,
        ];
    }
}

if (!function_exists('etugas_list_tasks_for_review_dropdown')) {
    function etugas_list_tasks_for_review_dropdown($koneksi, array $ctx, array $filters)
    {
        $scope = etugas_build_review_scope_sql($ctx, 'e');
        $sql = 'SELECT e.etugas_id, e.judul, e.status, e.ta_id, e.kelas_id, e.mapel_id,
                       k.kelas_nama, m.mapel_nama, t.ta_nama
                FROM etugas e
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                WHERE e.status IN (\'aktif\', \'ditutup\', \'arsip\')'
            . $scope['sql'];
        $types = $scope['types'];
        $params = $scope['params'];

        if (!empty($filters['ta_id'])) {
            $sql .= ' AND e.ta_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['ta_id'];
        }
        if (!empty($filters['kelas_id'])) {
            $sql .= ' AND e.kelas_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['kelas_id'];
        }
        if (!empty($filters['mapel_id'])) {
            $sql .= ' AND e.mapel_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['mapel_id'];
        }
        $sql .= ' ORDER BY e.deadline_at IS NULL, e.deadline_at DESC, e.etugas_id DESC LIMIT 200';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] review tasks dropdown: ' . mysqli_error($koneksi));
            return [];
        }
        if ($types !== '') {
            etugas_bind_params($stmt, $types, $params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('etugas_review_list_ready')) {
    function etugas_review_list_ready(array $filters)
    {
        return (int) ($filters['etugas_id'] ?? 0) > 0;
    }
}

if (!function_exists('etugas_review_filter_query')) {
    function etugas_review_filter_query(array $filters, $page = 1)
    {
        $q = array_filter([
            'ta_id' => !empty($filters['ta_id']) ? (int) $filters['ta_id'] : null,
            'kelas_id' => !empty($filters['kelas_id']) ? (int) $filters['kelas_id'] : null,
            'mapel_id' => !empty($filters['mapel_id']) ? (int) $filters['mapel_id'] : null,
            'etugas_id' => !empty($filters['etugas_id']) ? (int) $filters['etugas_id'] : null,
            'sub_status' => ($filters['sub_status'] ?? '') !== '' ? (string) $filters['sub_status'] : null,
            'q' => ($filters['q'] ?? '') !== '' ? (string) $filters['q'] : null,
            'page' => $page > 1 ? $page : null,
        ], function ($v) {
            return $v !== null && $v !== '';
        });
        return http_build_query($q);
    }
}

if (!function_exists('etugas_list_review_rows')) {
    /**
     * All students in task class (LEFT JOIN pengumpulan).
     *
     * @return array<int, array<string, mixed>>
     */
    function etugas_list_review_rows($koneksi, array $ctx, array $filters)
    {
        $etugasId = (int) ($filters['etugas_id'] ?? 0);
        if ($etugasId <= 0) {
            return [];
        }

        $task = etugas_fetch_by_id($koneksi, $etugasId);
        if (!$task || !etugas_user_can_review($ctx, $task)) {
            return [];
        }

        $sql = "SELECT s.siswa_id, s.siswa_nama, s.siswa_nis,
                       k.kelas_id, k.kelas_nama,
                       e.etugas_id, e.judul AS tugas_judul, e.deadline_at,
                       m.mapel_id, m.mapel_nama, m.mapel_kode,
                       t.ta_id, t.ta_nama,
                       p.pengumpulan_id, p.jawaban_teks, p.link_url, p.link_jenis, p.catatan_siswa,
                       p.status AS sub_status, p.is_terlambat, p.nilai, p.catatan_guru,
                       p.created_at AS kumpul_created_at, p.updated_at AS kumpul_updated_at
                FROM etugas e
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                INNER JOIN kelas_siswa ks ON ks.ks_kelas = e.kelas_id
                INNER JOIN siswa s ON s.siswa_id = ks.ks_siswa
                LEFT JOIN etugas_pengumpulan p ON p.etugas_id = e.etugas_id AND p.siswa_id = s.siswa_id
                WHERE e.etugas_id = ?
                  AND k.kelas_ta = e.ta_id";

        $types = 'i';
        $params = [$etugasId];

        $statusFilter = (string) ($filters['sub_status'] ?? '');
        if ($statusFilter === 'belum') {
            $sql .= ' AND p.pengumpulan_id IS NULL';
        } elseif ($statusFilter === 'terlambat') {
            $sql .= ' AND p.is_terlambat = 1';
        } elseif ($statusFilter !== '' && etugas_is_valid_pengumpulan_status($statusFilter)) {
            $sql .= ' AND p.status = ?';
            $types .= 's';
            $params[] = $statusFilter;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (s.siswa_nama LIKE ? OR s.siswa_nis LIKE ?)';
            $types .= 'ss';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY s.siswa_nama ASC, s.siswa_id ASC';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] review list prepare: ' . mysqli_error($koneksi));
            return [];
        }
        etugas_bind_params($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $row['has_submission'] = !empty($row['pengumpulan_id']);
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('etugas_review_summary')) {
    function etugas_review_summary(array $rows)
    {
        $out = [
            'total' => count($rows),
            'sudah' => 0,
            'belum' => 0,
            'revisi' => 0,
            'selesai' => 0,
        ];
        foreach ($rows as $r) {
            if (empty($r['has_submission'])) {
                $out['belum']++;
                continue;
            }
            $out['sudah']++;
            $st = (string) ($r['sub_status'] ?? '');
            if ($st === 'revisi') {
                $out['revisi']++;
            }
            if ($st === 'selesai') {
                $out['selesai']++;
            }
        }
        return $out;
    }
}

if (!function_exists('etugas_review_status_display')) {
    function etugas_review_status_display(array $row)
    {
        if (empty($row['has_submission'])) {
            return '<span class="label label-default">Belum Mengumpulkan</span>';
        }
        return etugas_pengumpulan_status_badge(
            (string) ($row['sub_status'] ?? ''),
            (int) ($row['is_terlambat'] ?? 0)
        );
    }
}

if (!function_exists('etugas_fetch_pengumpulan_by_id')) {
    function etugas_fetch_pengumpulan_by_id($koneksi, $pengumpulanId)
    {
        $pengumpulanId = (int) $pengumpulanId;
        if ($pengumpulanId <= 0) {
            return null;
        }
        $sql = 'SELECT p.*, s.siswa_nama, s.siswa_nis,
                       e.etugas_id, e.judul, e.instruksi, e.deadline_at, e.status AS tugas_status,
                       e.ta_id, e.kelas_id, e.mapel_id, e.allow_text, e.allow_link, e.izinkan_terlambat,
                       k.kelas_nama, m.mapel_nama, m.mapel_kode, t.ta_nama,
                       u.user_nama AS reviewed_by_nama
                FROM etugas_pengumpulan p
                INNER JOIN siswa s ON s.siswa_id = p.siswa_id
                INNER JOIN etugas e ON e.etugas_id = p.etugas_id
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                LEFT JOIN user u ON u.user_id = p.reviewed_by
                WHERE p.pengumpulan_id = ?
                LIMIT 1';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $pengumpulanId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('etugas_fetch_review_student_view')) {
    /**
     * Student + task without submission (belum mengumpulkan).
     */
    function etugas_fetch_review_student_view($koneksi, $etugasId, $siswaId)
    {
        $etugasId = (int) $etugasId;
        $siswaId = (int) $siswaId;
        if ($etugasId <= 0 || $siswaId <= 0) {
            return null;
        }
        $sql = "SELECT s.siswa_id, s.siswa_nama, s.siswa_nis,
                       e.etugas_id, e.judul, e.instruksi, e.deadline_at, e.status AS tugas_status,
                       e.ta_id, e.kelas_id, e.mapel_id, e.allow_text, e.allow_link, e.izinkan_terlambat,
                       k.kelas_nama, m.mapel_nama, m.mapel_kode, t.ta_nama,
                       NULL AS pengumpulan_id
                FROM etugas e
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                INNER JOIN kelas_siswa ks ON ks.ks_kelas = e.kelas_id AND ks.ks_siswa = ?
                INNER JOIN siswa s ON s.siswa_id = ks.ks_siswa
                WHERE e.etugas_id = ?
                  AND k.kelas_ta = e.ta_id
                LIMIT 1";
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $siswaId, $etugasId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('etugas_validate_review_update')) {
    function etugas_validate_review_update(array $input)
    {
        $errors = [];
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $catatan = trim((string) ($input['catatan_guru'] ?? ''));
        $nilaiRaw = trim((string) ($input['nilai'] ?? ''));

        if (!etugas_is_valid_pengumpulan_status($status)) {
            $errors['status'] = 'Status penilaian tidak valid.';
        }
        if ($catatan !== '' && mb_strlen($catatan) > 10000) {
            $errors['catatan_guru'] = 'Catatan guru terlalu panjang.';
        }

        $nilai = null;
        if ($nilaiRaw !== '') {
            if (!is_numeric($nilaiRaw)) {
                $errors['nilai'] = 'Nilai harus berupa angka.';
            } else {
                $nilai = (float) $nilaiRaw;
                if ($nilai < 0 || $nilai > 100) {
                    $errors['nilai'] = 'Nilai harus antara 0 dan 100.';
                }
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'data' => [
                'status' => $status,
                'catatan_guru' => $catatan !== '' ? $catatan : null,
                'nilai' => $nilai,
            ],
        ];
    }
}

if (!function_exists('etugas_update_pengumpulan_review')) {
    function etugas_update_pengumpulan_review($koneksi, $pengumpulanId, array $data, $reviewerUserId)
    {
        $pengumpulanId = (int) $pengumpulanId;
        $reviewerUserId = (int) $reviewerUserId;
        if ($pengumpulanId <= 0 || $reviewerUserId <= 0) {
            return false;
        }

        $status = $data['status'];
        $catatan = $data['catatan_guru'];
        $nilai = $data['nilai'];

        if ($nilai === null) {
            $sql = 'UPDATE etugas_pengumpulan SET
                        status = ?,
                        catatan_guru = ?,
                        nilai = NULL,
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE pengumpulan_id = ?';
            $stmt = mysqli_prepare($koneksi, $sql);
            if (!$stmt) {
                error_log('[etugas] review update prepare: ' . mysqli_error($koneksi));
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'ssii', $status, $catatan, $reviewerUserId, $pengumpulanId);
        } else {
            $sql = 'UPDATE etugas_pengumpulan SET
                        status = ?,
                        catatan_guru = ?,
                        nilai = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE pengumpulan_id = ?';
            $stmt = mysqli_prepare($koneksi, $sql);
            if (!$stmt) {
                error_log('[etugas] review update prepare: ' . mysqli_error($koneksi));
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'ssdii', $status, $catatan, $nilai, $reviewerUserId, $pengumpulanId);
        }

        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            error_log('[etugas] review update execute: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

/* ===================== Phase 4A: rekap & CSV export ===================== */

if (!function_exists('etugas_parse_rekap_filters')) {
    function etugas_parse_rekap_filters(array $input)
    {
        $sub = trim((string) ($input['sub_status'] ?? ''));
        $allowed = array_merge(['belum', 'terlambat'], etugas_valid_pengumpulan_statuses());
        if ($sub !== '' && !in_array($sub, $allowed, true)) {
            $sub = '';
        }
        return [
            'ta_id' => (int) ($input['ta_id'] ?? 0),
            'kelas_id' => (int) ($input['kelas_id'] ?? 0),
            'mapel_id' => (int) ($input['mapel_id'] ?? 0),
            'etugas_id' => (int) ($input['etugas_id'] ?? 0),
            'sub_status' => $sub,
            'q' => trim((string) ($input['q'] ?? '')),
        ];
    }
}

if (!function_exists('etugas_rekap_list_ready')) {
    function etugas_rekap_list_ready(array $filters)
    {
        if ((int) ($filters['etugas_id'] ?? 0) > 0) {
            return true;
        }
        return (int) ($filters['kelas_id'] ?? 0) > 0 && (int) ($filters['mapel_id'] ?? 0) > 0;
    }
}

if (!function_exists('etugas_rekap_filter_query')) {
    function etugas_rekap_filter_query(array $filters, $page = 1)
    {
        return etugas_review_filter_query($filters, $page);
    }
}

if (!function_exists('etugas_rekap_summary')) {
    function etugas_rekap_summary(array $rows)
    {
        $out = etugas_review_summary($rows);
        $out['terlambat'] = 0;
        $uniqueSiswa = [];
        foreach ($rows as $r) {
            $sid = (int) ($r['siswa_id'] ?? 0);
            if ($sid > 0) {
                $uniqueSiswa[$sid] = true;
            }
            if (!empty($r['has_submission']) && !empty($r['is_terlambat'])) {
                $out['terlambat']++;
            }
        }
        $out['total_siswa'] = count($uniqueSiswa) ?: $out['total'];
        return $out;
    }
}

if (!function_exists('etugas_rekap_status_text')) {
    function etugas_rekap_status_text(array $row)
    {
        if (empty($row['has_submission'])) {
            return 'Belum Mengumpulkan';
        }
        $map = [
            'terkirim' => 'Terkirim',
            'ditinjau' => 'Ditinjau',
            'revisi' => 'Perlu Revisi',
            'selesai' => 'Selesai',
        ];
        $st = (string) ($row['sub_status'] ?? '');
        $label = $map[$st] ?? ucfirst($st);
        if (!empty($row['is_terlambat'])) {
            $label .= ' (Terlambat)';
        }
        return $label;
    }
}

if (!function_exists('etugas_rekap_apply_status_sql')) {
    /**
     * @param array{sql:string,types:string,params:array} $state
     * @return array{sql:string,types:string,params:array}
     */
    function etugas_rekap_apply_status_sql(array $filters, array $state)
    {
        $statusFilter = (string) ($filters['sub_status'] ?? '');
        if ($statusFilter === 'belum') {
            $state['sql'] .= ' AND p.pengumpulan_id IS NULL';
        } elseif ($statusFilter === 'terlambat') {
            $state['sql'] .= ' AND p.is_terlambat = 1';
        } elseif ($statusFilter !== '' && etugas_is_valid_pengumpulan_status($statusFilter)) {
            $state['sql'] .= ' AND p.status = ?';
            $state['types'] .= 's';
            $state['params'][] = $statusFilter;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $state['sql'] .= ' AND (s.siswa_nama LIKE ? OR s.siswa_nis LIKE ?)';
            $state['types'] .= 'ss';
            $like = '%' . $q . '%';
            $state['params'][] = $like;
            $state['params'][] = $like;
        }
        return $state;
    }
}

if (!function_exists('etugas_rekap_fetch_kelas_ta')) {
    function etugas_rekap_fetch_kelas_ta($koneksi, $kelasId)
    {
        $kelasId = (int) $kelasId;
        if ($kelasId <= 0) {
            return 0;
        }
        $sql = 'SELECT kelas_ta FROM kelas WHERE kelas_id = ? LIMIT 1';
        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'i', $kelasId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int) ($row['kelas_ta'] ?? 0);
    }
}

if (!function_exists('etugas_list_rekap_rows_kelas_mapel')) {
    function etugas_list_rekap_rows_kelas_mapel($koneksi, array $ctx, array $filters)
    {
        $kelasId = (int) ($filters['kelas_id'] ?? 0);
        $mapelId = (int) ($filters['mapel_id'] ?? 0);
        if ($kelasId <= 0 || $mapelId <= 0) {
            return [];
        }

        $taId = (int) ($filters['ta_id'] ?? 0);
        if ($taId <= 0) {
            $taId = etugas_rekap_fetch_kelas_ta($koneksi, $kelasId);
        }
        if ($taId <= 0) {
            return [];
        }

        if (!etugas_user_can_review($ctx, ['ta_id' => $taId, 'kelas_id' => $kelasId, 'mapel_id' => $mapelId])) {
            return [];
        }

        $scope = etugas_build_review_scope_sql($ctx, 'e');
        $sql = "SELECT s.siswa_id, s.siswa_nama, s.siswa_nis,
                       k.kelas_id, k.kelas_nama,
                       e.etugas_id, e.judul AS tugas_judul, e.deadline_at,
                       m.mapel_id, m.mapel_nama, m.mapel_kode,
                       t.ta_id, t.ta_nama,
                       p.pengumpulan_id, p.jawaban_teks, p.link_url, p.link_jenis, p.catatan_siswa,
                       p.status AS sub_status, p.is_terlambat, p.nilai, p.catatan_guru,
                       p.created_at AS kumpul_created_at, p.updated_at AS kumpul_updated_at
                FROM etugas e
                INNER JOIN kelas k ON k.kelas_id = e.kelas_id
                INNER JOIN mapel m ON m.mapel_id = e.mapel_id
                INNER JOIN ta t ON t.ta_id = e.ta_id
                INNER JOIN kelas_siswa ks ON ks.ks_kelas = e.kelas_id
                INNER JOIN siswa s ON s.siswa_id = ks.ks_siswa
                LEFT JOIN etugas_pengumpulan p ON p.etugas_id = e.etugas_id AND p.siswa_id = s.siswa_id
                WHERE e.kelas_id = ?
                  AND e.mapel_id = ?
                  AND e.ta_id = ?
                  AND k.kelas_ta = e.ta_id
                  AND e.status IN ('aktif', 'ditutup', 'arsip')"
            . $scope['sql'];

        $types = 'iii' . $scope['types'];
        $params = [$kelasId, $mapelId, $taId];
        $params = array_merge($params, $scope['params']);

        $state = etugas_rekap_apply_status_sql($filters, ['sql' => $sql, 'types' => $types, 'params' => $params]);
        $sql = $state['sql'] . ' ORDER BY e.judul ASC, s.siswa_nama ASC, s.siswa_id ASC';

        $stmt = mysqli_prepare($koneksi, $sql);
        if (!$stmt) {
            error_log('[etugas] rekap kelas/mapel prepare: ' . mysqli_error($koneksi));
            return [];
        }
        etugas_bind_params($stmt, $state['types'], $state['params']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $row['has_submission'] = !empty($row['pengumpulan_id']);
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('etugas_rekap_can_access_filters')) {
    function etugas_rekap_can_access_filters($koneksi, array $ctx, array $filters)
    {
        if (!etugas_rekap_list_ready($filters)) {
            return false;
        }
        if ((int) ($filters['etugas_id'] ?? 0) > 0) {
            $task = etugas_fetch_by_id($koneksi, (int) $filters['etugas_id']);
            return $task && etugas_user_can_review($ctx, $task);
        }
        $kelasId = (int) ($filters['kelas_id'] ?? 0);
        $mapelId = (int) ($filters['mapel_id'] ?? 0);
        $taId = (int) ($filters['ta_id'] ?? 0);
        if ($taId <= 0) {
            $taId = etugas_rekap_fetch_kelas_ta($koneksi, $kelasId);
        }
        return etugas_user_can_review($ctx, [
            'ta_id' => $taId,
            'kelas_id' => $kelasId,
            'mapel_id' => $mapelId,
        ]);
    }
}

if (!function_exists('etugas_list_rekap_rows')) {
    function etugas_list_rekap_rows($koneksi, array $ctx, array $filters)
    {
        if ((int) ($filters['etugas_id'] ?? 0) > 0) {
            return etugas_list_review_rows($koneksi, $ctx, $filters);
        }
        if ((int) ($filters['kelas_id'] ?? 0) > 0 && (int) ($filters['mapel_id'] ?? 0) > 0) {
            return etugas_list_rekap_rows_kelas_mapel($koneksi, $ctx, $filters);
        }
        return [];
    }
}

if (!function_exists('etugas_rekap_row_to_csv')) {
    function etugas_rekap_row_to_csv(array $row, $no)
    {
        $kumpul = '';
        if (!empty($row['kumpul_updated_at']) || !empty($row['kumpul_created_at'])) {
            $kumpul = etugas_format_datetime_id($row['kumpul_updated_at'] ?? $row['kumpul_created_at']);
        }
        $deadline = !empty($row['deadline_at']) ? etugas_format_datetime_id($row['deadline_at']) : '';
        $nilai = '';
        if (isset($row['nilai']) && $row['nilai'] !== null && $row['nilai'] !== '') {
            $nilai = (string) $row['nilai'];
        }
        return [
            (int) $no,
            (string) ($row['siswa_nis'] ?? ''),
            (string) ($row['siswa_nama'] ?? ''),
            (string) ($row['kelas_nama'] ?? ''),
            (string) ($row['mapel_nama'] ?? ''),
            (string) ($row['tugas_judul'] ?? ''),
            $deadline,
            etugas_rekap_status_text($row),
            $kumpul,
            !empty($row['is_terlambat']) ? 'Ya' : 'Tidak',
            $nilai,
            (string) ($row['catatan_guru'] ?? ''),
            (string) ($row['link_url'] ?? ''),
        ];
    }
}

if (!function_exists('etugas_rekap_csv_headers')) {
    function etugas_rekap_csv_headers()
    {
        return [
            'No',
            'NIS',
            'Nama Siswa',
            'Kelas',
            'Mapel',
            'Judul Tugas',
            'Deadline',
            'Status Pengumpulan',
            'Waktu Kumpul',
            'Terlambat',
            'Nilai',
            'Catatan Guru',
            'Link URL',
        ];
    }
}

if (!function_exists('etugas_rekap_export_filename')) {
    function etugas_rekap_export_filename()
    {
        return 'etugas-rekap-' . date('Ymd-Hi') . '.csv';
    }
}

if (!function_exists('etugas_send_rekap_csv')) {
    function etugas_send_rekap_csv(array $rows)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        $filename = etugas_rekap_export_filename();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        if (!$out) {
            return false;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, etugas_rekap_csv_headers());
        $no = 1;
        foreach ($rows as $row) {
            fputcsv($out, etugas_rekap_row_to_csv($row, $no++));
        }
        fclose($out);
        return true;
    }
}
