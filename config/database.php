<?php
// =====================================================================
// KONFIGURASI KONEKSI DATABASE (TRISULA TNI AD)
// Mendukung Environment Variables & Override File Lokal (Production-Ready)
// =====================================================================

// Muat konfigurasi override lokal jika ada (misal kredensial hosting)
if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
}

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'edosir_tni');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    $errorCode = 500;
    $customTitle = 'Gangguan Koneksi Pangkalan Data';
    $customMessage = 'Terjadi gangguan koneksi ke pangkalan data sistem TRISULA TNI AD. Silakan hubungi Administrator Satuan / IT Support.';
    $customDetail = 'MySQL DB Connection Exception: Target Refused / Down';
    $errorFile = dirname(__DIR__) . '/error.php';
    if (file_exists($errorFile)) {
        include $errorFile;
        exit;
    }
    http_response_code(500);
    die('Terjadi gangguan koneksi ke pangkalan data sistem TRISULA TNI AD. Silakan hubungi Administrator Satuan / IT Support.');
}
