<?php
// =====================================================================
// KONFIGURASI KONEKSI DATABASE
// =====================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'edosir_tni');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
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
    http_response_code(500);
    die('Terjadi gangguan koneksi ke pangkalan data sistem TRISULA TNI AD. Silakan hubungi Administrator Satuan / IT Support.');
}
