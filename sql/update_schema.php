<?php
require_once __DIR__ . '/../config/database.php';

echo "Memeriksa skema database...\n";

// 1. Cek kolom di tabel dosir_files
$stmt = $pdo->query("DESCRIBE dosir_files");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('raw_file_path', $columns)) {
    echo "Menambahkan kolom raw_file_path...\n";
    $pdo->exec("ALTER TABLE dosir_files ADD COLUMN raw_file_path VARCHAR(255) NULL AFTER file_path");
} else {
    echo "Kolom raw_file_path sudah ada.\n";
}

if (!in_array('signature_code', $columns)) {
    echo "Menambahkan kolom signature_code...\n";
    $pdo->exec("ALTER TABLE dosir_files ADD COLUMN signature_code VARCHAR(60) NULL AFTER is_watermarked");
} else {
    echo "Kolom signature_code sudah ada.\n";
}

if (!in_array('signature_hash', $columns)) {
    echo "Menambahkan kolom signature_hash...\n";
    $pdo->exec("ALTER TABLE dosir_files ADD COLUMN signature_hash VARCHAR(64) NULL AFTER signature_code");
} else {
    echo "Kolom signature_hash sudah ada.\n";
}

// 2. Buat tabel login_attempts jika belum ada
$pdo->exec("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(50) NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time),
        INDEX idx_user_time (username, attempt_time)
    ) ENGINE=InnoDB;
");
echo "Tabel login_attempts siap.\n";

// 3. Pastikan relasi natural & foreign keys terpasang
require_once __DIR__ . '/migrate_natural_relations.php';

echo "Migrasi database selesai sukses!\n";
