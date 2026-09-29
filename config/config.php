<?php
// =====================================================================
// KONFIGURASI UMUM APLIKASI
// =====================================================================
date_default_timezone_set('Asia/Jakarta');

define('APP_ROOT', dirname(__DIR__));

// Deteksi BASE_URL otomatis (Kompatibel dengan Localhost Subfolder & Hosting Root/Domain)
if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase !== false) {
        define('BASE_URL', rtrim($envBase, '/'));
    } else {
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
        $appRoot = str_replace('\\', '/', APP_ROOT);
        if ($docRoot && strpos($appRoot, $docRoot) === 0) {
            $subPath = substr($appRoot, strlen($docRoot));
            define('BASE_URL', rtrim($subPath, '/'));
        } else {
            define('BASE_URL', '/edosir-tni');
        }
    }
}

// Pengaturan Pelaporan Error (Aman untuk Hosting Produksi, Mencegah Kebocoran Informasi)
$isProduction = (getenv('APP_ENV') === 'production') || (isset($_SERVER['SERVER_NAME']) && !in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1'], true));
if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('BACKUP_DIR', APP_ROOT . '/backups');
define('EXPORT_DIR', APP_ROOT . '/exports');

define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB per file
define('ALLOWED_UPLOAD_EXT', ['pdf']);        // seluruh berkas dosir WAJIB dalam bentuk PDF
                                               // hasil scan foto/kamera dikonversi ke PDF di sisi klien (jsPDF)

// Batas usia pensiun (tahun) sesuai golongan
define('USIA_PENSIUN', [
    'Perwira'  => 58,
    'Bintara'  => 56,
    'Tamtama'  => 56,
    'PNS'      => 60,
]);

// Proteksi Keamanan Sesi Cookie & HTTP Headers
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// HTTP Security Headers (Defensive in-depth)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';

// Pengaturan dinamis dari database (dapat diubah Administrator di menu Pengaturan)
$dynamicAppName = get_setting($pdo, 'app_name', 'TRISULA TNI AD');
define('APP_NAME', $dynamicAppName ?: 'TRISULA TNI AD');

$dynamicBatasTahun = (int) get_setting($pdo, 'batas_tahun_jabatan', 2);
define('BATAS_TAHUN_JABATAN', $dynamicBatasTahun > 0 ? $dynamicBatasTahun : 2);

