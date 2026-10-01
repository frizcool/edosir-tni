<?php
// =====================================================================
// AUTENTIKASI & OTORISASI
// =====================================================================

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function require_login() {
    global $pdo;
    if (!is_logged_in()) {
        redirect('/login.php');
    }

    // Proteksi Batas Waktu Ketidakaktifan Sesi (Session Idle Timeout)
    $timeoutMins = 30;
    if (isset($pdo)) {
        $settingMins = (int) get_setting($pdo, 'session_timeout_minutes', 30);
        if ($settingMins > 0) $timeoutMins = $settingMins;
    }
    $timeoutSeconds = $timeoutMins * 60;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
        $timedOutUser = $_SESSION['user']['id'] ?? null;
        if ($timedOutUser && isset($pdo)) {
            log_activity($pdo, $timedOutUser, 'SESSION_TIMEOUT', 'Sesi berakhir otomatis karena tidak aktif selama ' . $timeoutMins . ' menit');
        }
        unset($_SESSION['user']);
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        set_flash('error', 'Sesi Anda telah berakhir otomatis demi keamanan sistem militer. Silakan masuk kembali.');
        redirect('/login.php');
    }

    $_SESSION['last_activity'] = time();

    // Wajib ganti kata sandi: blokir akses ke halaman lain selain halaman ganti sandi
    if (!empty($_SESSION['user']['must_change_password'])) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $isAdmin    = ($_SESSION['user']['role'] ?? '') === 'admin';

        // Tentukan halaman tujuan yang diizinkan untuk role masing-masing
        $allowedPath = $isAdmin ? '/admin/settings.php' : '/personel/profile.php';

        // Izinkan akses ke halaman tujuan itu sendiri dan halaman logout
        if (strpos($currentUri, $allowedPath) === false && strpos($currentUri, '/logout.php') === false) {
            set_flash('error', 'Anda wajib memperbarui kata sandi terlebih dahulu sebelum dapat mengakses fitur lainnya.');
            if ($isAdmin) {
                redirect('/admin/settings.php?action=change_password_required#ganti-sandi');
            } else {
                redirect('/personel/profile.php?action=change_password_required');
            }
        }
    }
}

function require_role($role) {
    require_login();
    if ($_SESSION['user']['role'] !== $role) {
        abort(403, 'Akses ditolak: Halaman ini khusus untuk peran (role) ' . htmlspecialchars($role) . '.', 'Pembatasan Wewenang Peran');
    }
}

function require_admin() {
    require_role('admin');
}

/** Login: kembalikan true/false, isi $error jika gagal */
function do_login($pdo, $username, $password, &$error) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Cek proteksi brute force rate limit (5x gagal = kunci 15 menit)
    $lockout = check_login_rate_limit($pdo, $ip, $username);
    if ($lockout > 0) {
        $error = "Terlalu banyak percobaan login yang gagal. Akses ditangguhkan selama $lockout menit demi keamanan sistem.";
        return false;
    }

    $stmt = $pdo->prepare("SELECT u.*, p.nama, p.nrp, mp.singkatan as pangkat, p.foto
                            FROM users u
                            LEFT JOIN personel p ON p.id = u.personel_id
                            LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
                            WHERE u.username = ? OR p.nrp = ? OR (p.email IS NOT NULL AND p.email != '' AND p.email = ?)");
    $stmt->execute([$username, $username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        record_failed_login($pdo, $ip, $username);
        $error = 'Email/NRP/Username atau kata sandi salah.';
        return false;
    }
    if ($user['status'] === 'pending') {
        $error = 'Akun Anda masih menunggu verifikasi & persetujuan admin.';
        return false;
    }
    if ($user['status'] === 'rejected') {
        $reason = !empty($user['catatan_approval']) ? ' Catatan: ' . htmlspecialchars($user['catatan_approval']) : '';
        $error = 'Registrasi Anda ditolak admin.' . $reason . ' Hubungi staf pers / admin satuan.';
        return false;
    }
    if ($user['status'] === 'nonaktif') {
        $error = 'Akun Anda dinonaktifkan. Hubungi administrator sistem.';
        return false;
    }

    // Bersihkan riwayat kegagalan dan regenerasi ID sesi untuk mencegah session fixation
    clear_failed_logins($pdo, $ip, $username);
    if (!empty($user['username']) && $user['username'] !== $username) {
        clear_failed_logins($pdo, $ip, $user['username']);
    }
    session_regenerate_id(true);

    unset($user['password']);
    $_SESSION['user'] = $user;
    $_SESSION['last_activity'] = time();

    $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $upd->execute([$user['id']]);

    log_activity($pdo, $user['id'], 'LOGIN', 'Login berhasil');
    return true;
}

function do_logout($pdo) {
    if (is_logged_in()) {
        log_activity($pdo, $_SESSION['user']['id'], 'LOGOUT', 'Logout');
    }
    unset($_SESSION['user']);
    session_destroy();
}
