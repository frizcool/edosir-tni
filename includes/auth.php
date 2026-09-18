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
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

function require_role($role) {
    require_login();
    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        die('Akses ditolak: halaman ini khusus untuk role ' . htmlspecialchars($role) . '.');
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

    $stmt = $pdo->prepare("SELECT u.*, p.nama, p.nrp, p.pangkat, p.foto
                            FROM users u
                            LEFT JOIN personel p ON p.id = u.personel_id
                            WHERE u.username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        record_failed_login($pdo, $ip, $username);
        $error = 'Username/NRP atau kata sandi salah.';
        return false;
    }
    if ($user['status'] === 'pending') {
        $error = 'Akun Anda masih menunggu verifikasi & persetujuan admin.';
        return false;
    }
    if ($user['status'] === 'rejected') {
        $error = 'Registrasi Anda ditolak admin. Hubungi staf pers / admin satuan.';
        return false;
    }
    if ($user['status'] === 'nonaktif') {
        $error = 'Akun Anda dinonaktifkan. Hubungi administrator sistem.';
        return false;
    }

    // Bersihkan riwayat kegagalan dan regenerasi ID sesi untuk mencegah session fixation
    clear_failed_logins($pdo, $ip, $username);
    session_regenerate_id(true);

    unset($user['password']);
    $_SESSION['user'] = $user;

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
