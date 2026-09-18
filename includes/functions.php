<?php
// =====================================================================
// FUNGSI BANTU (HELPERS)
// =====================================================================

/** Redirect helper */
function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Flash message sederhana via session */
function set_flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Catat aktivitas ke activity_log */
function log_activity($pdo, $user_id, $aktivitas, $keterangan = '') {
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, aktivitas, keterangan, ip_address) VALUES (?,?,?,?)");
    $stmt->execute([$user_id, $aktivitas, $keterangan, $_SERVER['REMOTE_ADDR'] ?? '']);
}

/** Nomor folder dosir, misal '5' -> 'FOLDER 05' */
function folder_dosir($kode) {
    return 'FOLDER ' . str_pad($kode, 2, '0', STR_PAD_LEFT);
}

/**
 * Tentukan slot abjad berikutnya untuk kombinasi personel+kode dosir.
 * File pertama tanpa abjad, berikutnya a, b, c, ...
 */
function next_abjad_slot($pdo, $personel_id, $kode) {
    $stmt = $pdo->prepare("SELECT abjad FROM dosir_files WHERE personel_id=? AND dosir_kode=?");
    $stmt->execute([$personel_id, $kode]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($existing)) {
        return null; // slot pertama, tanpa abjad
    }
    // cari huruf berikutnya yang belum dipakai
    foreach (range('a', 'z') as $letter) {
        if (!in_array($letter, $existing, true)) {
            return $letter;
        }
    }
    return null; // (praktis tidak pernah tercapai - 26 slot cukup)
}

/** Susun nama file fisik sesuai format {nrp}_{kode}{abjad}.pdf */
function build_dosir_filename($nrp, $kode, $abjad) {
    $kodePad = str_pad($kode, 2, '0', STR_PAD_LEFT);
    return $nrp . '_' . $kodePad . ($abjad ?? '') . '.pdf';
}

/**
 * URL aman ke berkas dosir melalui controller terproteksi (view_file.php)
 * Memastikan otentikasi dan otorisasi sebelum berkas dapat diakses.
 * Dilengkapi versi waktu modifikasi (&v=) agar dokumen yang telah diapprove
 * langsung termutakhirkan tanpa terhalang cache peramban/PDF viewer.
 */
function dosir_url($filePath, $fileId = null) {
    if (!$filePath && !$fileId) return '#';
    $clean = $filePath ? str_replace('\\', '/', $filePath) : '';
    if (strpos($clean, 'uploads/') === 0) {
        $clean = substr($clean, 8);
    }
    
    // Hitung hash/mtime berkas fisik jika ada
    $v = time();
    if ($clean) {
        $abs = UPLOAD_DIR . '/' . $clean;
        if (file_exists($abs)) {
            $v = filemtime($abs);
        }
    }

    if ($fileId) {
        return BASE_URL . '/view_file.php?id=' . (int)$fileId . '&v=' . $v;
    }
    return BASE_URL . '/view_file.php?file=' . rawurlencode($clean) . '&v=' . $v;
}

/**
 * URL aman ke berkas pas foto prajurit melalui controller view_file.php
 * Menyelesaikan masalah 403 forbidden akibat proteksi uploads/.htaccess
 */
function foto_url($fotoPath) {
    if (empty($fotoPath)) return '';
    $clean = str_replace('\\', '/', $fotoPath);
    if (strpos($clean, 'uploads/') === 0) {
        $clean = substr($clean, 8);
    }
    $abs = UPLOAD_DIR . '/' . $clean;
    if (!file_exists($abs)) return '';
    $v = filemtime($abs);
    return BASE_URL . '/view_file.php?file=' . rawurlencode($clean) . '&v=' . $v;
}

/**
 * URL logo aplikasi jika diatur, atau null jika memakai lambang default
 */
function app_logo_url($db = null) {
    global $pdo;
    $db = $db ?? $pdo;
    if (!$db) return null;
    $logoRel = get_setting($db, 'app_logo', '');
    if ($logoRel && file_exists(APP_ROOT . '/' . $logoRel)) {
        return BASE_URL . '/' . $logoRel . '?v=' . filemtime(APP_ROOT . '/' . $logoRel);
    }
    return null;
}

/** CSRF Token Generator */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render hidden input CSRF */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Validasi token CSRF pada request POST */
function verify_csrf() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            die('Validasi keamanan gagal: Token CSRF tidak valid atau telah kadaluarsa. Silakan muat ulang halaman.');
        }
    }
}

/** Validasi tipe konten berkas PDF menggunakan finfo magic bytes */
function is_valid_pdf($tempFilePath) {
    if (!file_exists($tempFilePath) || !is_readable($tempFilePath)) {
        return false;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tempFilePath);
    return $mime === 'application/pdf';
}

/**
 * Cek apakah IP / Username sedang terkena batasan rate limit login
 * @return int 0 jika aman, atau jumlah sisa menit penangguhan jika terkunci
 */
function check_login_rate_limit($pdo, $ip, $username, $maxAttempts = 5, $lockoutMinutes = 15) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE (ip_address = ? OR username = ?) 
              AND attempt_time > (NOW() - INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, $username, $lockoutMinutes]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= $maxAttempts) {
            return $lockoutMinutes;
        }
    } catch (Exception $e) {
        // Fallback jika tabel belum siap
    }
    return 0;
}

/** Catat percobaan login yang gagal */
function record_failed_login($pdo, $ip, $username) {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
    }
}

/** Bersihkan riwayat login gagal setelah berhasil masuk */
function clear_failed_logins($pdo, $ip, $username) {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
    }
}

/** Hitung persentase kelengkapan dosir seorang personel (berdasarkan dosir berstatus wajib) */
function hitung_kelengkapan($pdo, $personel_id) {
    static $totalWajib = null;
    if ($totalWajib === null) {
        try {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn();
            $totalWajib = $cnt > 0 ? $cnt : 33;
        } catch (Exception $e) {
            $totalWajib = 33;
        }
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT f.dosir_kode) 
         FROM dosir_files f
         JOIN dosir_master m ON m.kode = f.dosir_kode
         WHERE f.personel_id=? AND f.status='approved' AND m.wajib=1"
    );
    $stmt->execute([$personel_id]);
    $terisi = (int) $stmt->fetchColumn();
    $total = $totalWajib;
    return [
        'terisi'  => $terisi,
        'total'   => $total,
        'persen'  => round(($terisi / $total) * 100, 1),
    ];
}

/** Hitung usia dari tanggal lahir */
function hitung_usia($tanggal_lahir, $pada = null) {
    if (!$tanggal_lahir) return null;
    $lahir = new DateTime($tanggal_lahir);
    $now = $pada ? new DateTime($pada) : new DateTime();
    return $lahir->diff($now)->y;
}

/** Prediksi tanggal pensiun berdasarkan golongan & tanggal lahir */
function prediksi_pensiun($golongan, $tanggal_lahir) {
    if (!$tanggal_lahir) return null;
    $usia_pensiun = USIA_PENSIUN[$golongan] ?? 58;
    $lahir = new DateTime($tanggal_lahir);
    $lahir->modify('+' . $usia_pensiun . ' years');
    return $lahir->format('Y-m-d');
}

/** Sisa waktu (dalam bulan) menuju pensiun; negatif = sudah lewat */
function bulan_menuju_pensiun($tanggal_pensiun) {
    if (!$tanggal_pensiun) return null;
    $now = new DateTime();
    $target = new DateTime($tanggal_pensiun);
    $diff = $now->diff($target);
    $bulan = ($diff->y * 12) + $diff->m;
    return $diff->invert ? -$bulan : $bulan;
}

/** Lama menjabat (tahun, dibulatkan 1 desimal) sejak tmt_jabatan */
function lama_jabatan_tahun($tmt_jabatan) {
    if (!$tmt_jabatan) return null;
    $tmt = new DateTime($tmt_jabatan);
    $now = new DateTime();
    $diff = $tmt->diff($now);
    return round($diff->y + ($diff->m / 12), 1);
}

/** Ambil daftar 33 master dosir */
function get_dosir_master($pdo) {
    return $pdo->query("SELECT * FROM dosir_master ORDER BY urutan")->fetchAll();
}

/** Format tanggal Indonesia singkat */
function fmt_tgl($tgl) {
    if (!$tgl) return '-';
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $d = new DateTime($tgl);
    return $d->format('d') . ' ' . $bulan[(int)$d->format('n')] . ' ' . $d->format('Y');
}

/** Buat direktori jika belum ada */
function ensure_dir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

/** Validasi ekstensi file upload */
function is_allowed_ext($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_UPLOAD_EXT, true);
}

/**
 * Memastikan akun user di tabel users tersedia untuk suatu data personel.
 * Username & password default otomatis menggunakan NRP.
 *
 * @param PDO $pdo
 * @param int $personel_id
 * @param string $nrp
 * @param string $status 'pending'|'approved'|'rejected'|'nonaktif'
 * @return array ['user_id' => int, 'created' => bool]
 */
function ensure_personel_user($pdo, $personel_id, $nrp, $status = 'pending') {
    $nrp = trim($nrp);
    if ($nrp === '' || !$personel_id) {
        return ['user_id' => 0, 'created' => false];
    }

    // Cek apakah sudah ada user terkait personel_id ini
    $stmt = $pdo->prepare("SELECT id, username, personel_id FROM users WHERE personel_id = ?");
    $stmt->execute([$personel_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Jika username berbeda dari NRP (misal NRP diedit), sinkronkan username
        if ($user['username'] !== $nrp) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$nrp, $user['id']]);
            if (!$chk->fetch()) {
                $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $upd->execute([$nrp, $user['id']]);
            }
        }
        return ['user_id' => (int)$user['id'], 'created' => false];
    }

    // Cek apakah ada akun dengan username = NRP yang belum terhubung personel_id
    $stmt2 = $pdo->prepare("SELECT id, personel_id FROM users WHERE username = ?");
    $stmt2->execute([$nrp]);
    $user2 = $stmt2->fetch();

    if ($user2) {
        $upd = $pdo->prepare("UPDATE users SET personel_id = ? WHERE id = ?");
        $upd->execute([$personel_id, $user2['id']]);
        return ['user_id' => (int)$user2['id'], 'created' => false];
    }

    // Jika belum ada, buatkan akun baru: username = NRP, password = hash(NRP)
    $hash = password_hash($nrp, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username, password, role, personel_id, status) VALUES (?, ?, 'personel', ?, ?)");
    $ins->execute([$nrp, $hash, $personel_id, $status]);
    $userId = (int)$pdo->lastInsertId();

    return ['user_id' => $userId, 'created' => true];
}

/**
 * Reset password akun user personel kembali ke NRP default
 */
function reset_user_password_to_nrp($pdo, $user_id, $nrp) {
    $hash = password_hash(trim($nrp), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    return $stmt->execute([$hash, $user_id]);
}

/**
 * Mengambil nilai pengaturan sistem dari database dengan static cache
 */
function get_setting($pdo, $key, $default = '', $refresh = false) {
    static $cache = null;
    if ($cache === null || $refresh) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $cache = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Menyimpan atau memperbarui nilai pengaturan sistem di database
 */
function update_setting($pdo, $key, $value, $group = 'general') {
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_group)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)
    ");
    $result = $stmt->execute([$key, $value, $group]);
    if ($result) {
        get_setting($pdo, '', '', true);
    }
    return $result;
}

/**
 * Mengambil nilai unik dari kolom tabel personel untuk saran dropdown / autocomplete datalist
 */
function get_distinct_personel_field($pdo, $fieldName) {
    $allowed = ['satuan', 'kotama', 'korp', 'pangkat', 'agama', 'status_kawin'];
    if (!in_array($fieldName, $allowed, true)) {
        return [];
    }
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT $fieldName 
            FROM personel 
            WHERE $fieldName IS NOT NULL AND $fieldName != '' 
            ORDER BY $fieldName ASC
        ");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Exception $e) {
        return [];
    }
}


