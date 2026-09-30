<?php
// =====================================================================
// KONFIGURASI UMUM APLIKASI
// =====================================================================
date_default_timezone_set('Asia/Jakarta');

define('APP_ROOT', dirname(__DIR__));

// =====================================================================
// =====================================================================
// POLIFILL KOMPATIBILITAS VERSI PHP (< PHP 8.0 / HOSTING PHP 7.4)
// =====================================================================
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        $len = strlen($needle);
        return $len === 0 || (strlen($haystack) >= $len && substr_compare($haystack, $needle, -$len, $len) === 0);
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return (string)$needle === '' || strpos($haystack, $needle) !== false;
    }
}

// =====================================================================
// PEMUAT VARIABEL LINGKUNGAN (.env LOADER MANDIRI)
// =====================================================================
if (!function_exists('load_environment_file')) {
    function load_environment_file(string $filePath): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            // Abaikan baris kosong atau komentar
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($envKey, $envVal) = explode('=', $line, 2);
                $envKey = trim($envKey);
                $envVal = trim($envVal);

                // Hilangkan pembungkus tanda petik jika ada
                $valLen = strlen($envVal);
                if ($valLen >= 2) {
                    $firstChar = $envVal[0];
                    $lastChar  = $envVal[$valLen - 1];
                    if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                        $envVal = substr($envVal, 1, -1);
                    }
                }

                // Daftarkan ke getenv(), $_ENV, dan $_SERVER jika belum diset dari level web server
                if (getenv($envKey) === false) {
                    putenv("{$envKey}={$envVal}");
                }
                if (!isset($_ENV[$envKey])) {
                    $_ENV[$envKey] = $envVal;
                }
                if (!isset($_SERVER[$envKey])) {
                    $_SERVER[$envKey] = $envVal;
                }
            }
        }
    }
}
load_environment_file(APP_ROOT . '/.env');

// Pengalihan Paksa HTTPS jika diaktifkan di .env (Production Hosting)
$forceHttps = filter_var(getenv('FORCE_HTTPS'), FILTER_VALIDATE_BOOLEAN);
$isHttps = (
    (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
    (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443')
);

if ($forceHttps && !$isHttps && isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

// Deteksi BASE_URL otomatis (Kompatibel dengan Localhost Subfolder & Hosting Root/Domain)
if (!defined('BASE_URL')) {
    $envBase = getenv('BASE_URL');
    if ($envBase !== false && $envBase !== '') {
        define('BASE_URL', rtrim($envBase, '/'));
    } elseif ($envBase === '') {
        define('BASE_URL', '');
    } else {
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
        $appRoot = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
        $detectedBase = null;
        if ($docRoot && strpos($appRoot, $docRoot) === 0) {
            $detectedBase = rtrim(substr($appRoot, strlen($docRoot)), '/');
        }

        if ($detectedBase !== null && $detectedBase !== '') {
            define('BASE_URL', $detectedBase);
        } else {
            $serverName = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? '');
            $isLocal = in_array(strtolower(explode(':', $serverName)[0]), ['localhost', '127.0.0.1', '::1'], true);
            define('BASE_URL', $isLocal ? '/edosir-tni' : '');
        }
    }
}

// Pengaturan Pelaporan Error (Aman untuk Hosting Produksi, Mencegah Kebocoran Informasi)
$appEnv = getenv('APP_ENV') ?: 'production';
$appDebug = getenv('APP_DEBUG');
$isProduction = ($appEnv === 'production') || (isset($_SERVER['SERVER_NAME']) && !in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1'], true));

if ($appDebug !== false && $appDebug !== null) {
    $isDebug = filter_var($appDebug, FILTER_VALIDATE_BOOLEAN);
} else {
    $isDebug = !$isProduction;
}

if ($isProduction && !$isDebug) {
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

// =====================================================================
// GLOBAL EXCEPTION & FATAL ERROR HANDLER (Menangani Error 500 Terpadu)
// =====================================================================
set_exception_handler(function (\Throwable $e) use ($isDebug) {
    error_log('Uncaught Exception [500]: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level()) {
        ob_end_clean();
    }
    $detail = $isDebug ? ($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) : '';
    $errorFile = defined('APP_ROOT') ? APP_ROOT . '/error.php' : dirname(__DIR__) . '/error.php';
    $errorCode = 500;
    $customMessage = 'Terjadi anomali pemrosesan instruksi pada server.';
    $customTitle = 'Anomali Sistem Pusat';
    $customDetail = $detail;
    if (file_exists($errorFile)) {
        include $errorFile;
        exit;
    }
    http_response_code(500);
    die('500 - Internal Server Error');
});

register_shutdown_function(function () use ($isDebug) {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('Fatal Error [500]: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        while (ob_get_level()) {
            ob_end_clean();
        }
        $detail = $isDebug ? ($err['message'] . "\n" . $err['file'] . ':' . $err['line']) : '';
        $errorFile = defined('APP_ROOT') ? APP_ROOT . '/error.php' : dirname(__DIR__) . '/error.php';
        $errorCode = 500;
        $customMessage = 'Terjadi kegagalan fatal pada server saat memproses permintaan.';
        $customTitle = 'Kegagalan Fatal Server';
        $customDetail = $detail;
        if (file_exists($errorFile)) {
            include $errorFile;
            exit;
        }
        http_response_code(500);
        die('500 - Fatal Server Error');
    }
});

define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('BACKUP_DIR', APP_ROOT . '/backups');
define('EXPORT_DIR', APP_ROOT . '/exports');

$envMaxUpload = (int)getenv('MAX_UPLOAD_SIZE');
define('MAX_UPLOAD_SIZE', $envMaxUpload > 0 ? $envMaxUpload : 10 * 1024 * 1024); // Default: 10 MB per file
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
$sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 7200);
if ($sessionLifetime <= 0) {
    $sessionLifetime = 7200;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    session_set_cookie_params([
        'lifetime' => 0, // Cookie sesi browser (otomatis hangus saat browser ditutup)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
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
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';

// Otomatis pastikan view relasional v_personel_lengkap terpasang di database hosting
if (isset($pdo)) {
    ensure_v_personel_lengkap($pdo);
}

// Pengaturan dinamis dari database (dapat diubah Administrator di menu Pengaturan)
$dynamicAppName = get_setting($pdo, 'app_name', 'TRISULA TNI AD');
define('APP_NAME', $dynamicAppName ?: 'TRISULA TNI AD');

$dynamicBatasTahun = (int) get_setting($pdo, 'batas_tahun_jabatan', 2);
define('BATAS_TAHUN_JABATAN', $dynamicBatasTahun > 0 ? $dynamicBatasTahun : 2);

