<?php
require_once __DIR__ . '/../config/config.php';


echo "=== MEMULAI PENINGKATAN SKEMA DATABASE TRISULA TNI AD ===\n";

// 1. Cek dan tambah kolom raw_hash pada dosir_files
try {
    $cols = $pdo->query("DESCRIBE dosir_files")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('raw_hash', $cols, true)) {
        $pdo->exec("ALTER TABLE dosir_files ADD COLUMN raw_hash VARCHAR(64) NULL AFTER signature_hash");
        echo "✓ Kolom dosir_files.raw_hash berhasil ditambahkan.\n";
    } else {
        echo "- Kolom dosir_files.raw_hash sudah tersedia.\n";
    }
} catch (Exception $e) {
    echo "! Error raw_hash: " . $e->getMessage() . "\n";
}

// 2. Cek dan tambah index pada tabel personel
try {
    $indexes = $pdo->query("SHOW INDEX FROM personel")->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('idx_personel_pensiun', $indexes, true)) {
        $pdo->exec("ALTER TABLE personel ADD INDEX idx_personel_pensiun (tmt_pensiun_proyeksi)");
        echo "✓ Index idx_personel_pensiun berhasil dibuat.\n";
    }
    if (!in_array('idx_personel_tmt_jab', $indexes, true)) {
        $pdo->exec("ALTER TABLE personel ADD INDEX idx_personel_tmt_jab (tmt_jabatan)");
        echo "✓ Index idx_personel_tmt_jab berhasil dibuat.\n";
    }
} catch (Exception $e) {
    echo "! Error indexes: " . $e->getMessage() . "\n";
}

// 3. Update tmt_pensiun_proyeksi untuk seluruh personel yang ada
try {
    $personels = $pdo->query("SELECT id, golongan, tanggal_lahir FROM personel WHERE tanggal_lahir IS NOT NULL")->fetchAll();
    $upd = $pdo->prepare("UPDATE personel SET tmt_pensiun_proyeksi = ? WHERE id = ?");
    $updatedCount = 0;
    foreach ($personels as $p) {
        $proyeksi = prediksi_pensiun($p['golongan'], $p['tanggal_lahir']);
        if ($proyeksi) {
            $upd->execute([$proyeksi, $p['id']]);
            $updatedCount++;
        }
    }
    echo "✓ Pemutakhiran tmt_pensiun_proyeksi selesai ($updatedCount personel terupdate).\n";
} catch (Exception $e) {
    echo "! Error kalkulasi pensiun: " . $e->getMessage() . "\n";
}

// 4. Inisialisasi pengaturan pejabat penandatangan laporan & session timeout
$defaultSettings = [
    'pejabat_nama' => ['HENDRA PRATAMA, S.I.P.', 'laporan'],
    'pejabat_pangkat' => ['MAYOR INF', 'laporan'],
    'pejabat_nrp' => ['11040023450682', 'laporan'],
    'pejabat_jabatan' => ['Perwira Personel / Verifikator', 'laporan'],
    'session_timeout_minutes' => ['30', 'security']
];

foreach ($defaultSettings as $key => [$val, $grp]) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
            $ins->execute([$key, $val, $grp]);
            echo "✓ Setting default '$key' berhasil ditambahkan.\n";
        }
    } catch (Exception $e) {
        echo "! Error setting $key: " . $e->getMessage() . "\n";
    }
}

// 5. Backfill hash untuk file dosir yang sudah ada
try {
    $files = $pdo->query("SELECT id, file_path, raw_file_path, signature_hash, raw_hash, status FROM dosir_files")->fetchAll();
    $updHash = $pdo->prepare("UPDATE dosir_files SET raw_hash = ?, signature_hash = ? WHERE id = ?");
    $backfilled = 0;
    foreach ($files as $f) {
        $rawPath = !empty($f['raw_file_path']) ? UPLOAD_DIR . '/' . $f['raw_file_path'] : null;
        $activePath = UPLOAD_DIR . '/' . $f['file_path'];

        $rawHash = ($rawPath && file_exists($rawPath)) ? hash_file('sha256', $rawPath) : ($f['raw_hash'] ?: null);
        $activeHash = file_exists($activePath) ? hash_file('sha256', $activePath) : $f['signature_hash'];

        if ($rawHash !== $f['raw_hash'] || ($f['status'] === 'approved' && $activeHash !== $f['signature_hash'])) {
            $sigHashToStore = ($f['status'] === 'approved') ? $activeHash : ($f['signature_hash'] ?: null);
            $updHash->execute([$rawHash, $sigHashToStore, $f['id']]);
            $backfilled++;
        }
    }
    echo "✓ Backfill dual-hash selesai ($backfilled berkas disinkronkan).\n";
} catch (Exception $e) {
    echo "! Error backfill hash: " . $e->getMessage() . "\n";
}

echo "=== PENINGKATAN SKEMA DATABASE SELESAI SUKSES ===\n";
