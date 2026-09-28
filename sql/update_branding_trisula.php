<?php
// =====================================================================
// MIGRASI IDENTITAS SISTEM: TRISULA TNI AD
// Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip
// =====================================================================
require_once __DIR__ . '/../config/database.php';

echo "Memperbarui pengaturan identitas aplikasi ke TRISULA TNI AD...\n";

$settings = [
    'app_name'        => 'TRISULA TNI AD',
    'app_subtitle'    => 'Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip',
    'app_brand_title' => 'TRISULA',
    'app_brand_sub'   => 'TNI AD',
    'watermark_text'  => 'TRISULA TERVERIFIKASI',
];

$stmt = $pdo->prepare("
    INSERT INTO settings (setting_key, setting_value, setting_group) 
    VALUES (?, ?, 'general')
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");

foreach ($settings as $key => $val) {
    $stmt->execute([$key, $val]);
    echo " [OK] Setting '$key' diperbarui menjadi: $val\n";
}

echo "Pembaruan identitas TRISULA berhasil diterapkan ke pangkalan data!\n";
