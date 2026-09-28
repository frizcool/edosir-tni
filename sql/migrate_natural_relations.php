<?php
// =====================================================================
// MIGRASI RELASI BASIS DATA ALAMI (NATURAL RELATIONAL MIGRATION)
// TRISULA TNI AD
// =====================================================================
require_once __DIR__ . '/../config/database.php';

echo "Memulai migrasi penyempurnaan relasi basis data...\n";

// 1. Bersihkan record yatim (orphaned records) sebelum memasang constraint FK
echo "[1/6] Memeriksa dan membersihkan baris yatim (orphaned records)...\n";

$cleanedActivity = $pdo->exec("
    UPDATE activity_log 
    SET user_id = NULL 
    WHERE user_id IS NOT NULL 
      AND user_id NOT IN (SELECT id FROM users)
");
echo "  - Diperbarui $cleanedActivity baris pada activity_log (user_id diset NULL).\n";

$cleanedUpload = $pdo->exec("
    UPDATE dosir_files 
    SET uploaded_by = NULL 
    WHERE uploaded_by IS NOT NULL 
      AND uploaded_by NOT IN (SELECT id FROM users)
");
echo "  - Diperbarui $cleanedUpload baris pada dosir_files.uploaded_by (diset NULL).\n";

$cleanedVerify = $pdo->exec("
    UPDATE dosir_files 
    SET verified_by = NULL 
    WHERE verified_by IS NOT NULL 
      AND verified_by NOT IN (SELECT id FROM users)
");
echo "  - Diperbarui $cleanedVerify baris pada dosir_files.verified_by (diset NULL).\n";

$cleanedBackup = $pdo->exec("
    UPDATE backup_log 
    SET created_by = NULL 
    WHERE created_by IS NOT NULL 
      AND created_by NOT IN (SELECT id FROM users)
");
echo "  - Diperbarui $cleanedBackup baris pada backup_log.created_by (diset NULL).\n";


// 2. Pasang constraint UNIQUE pada users.personel_id (Kardinalitas 1:1 Alami)
echo "[2/6] Memeriksa constraint UNIQUE pada users.personel_id...\n";
$uqCheck = $pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = 'edosir_tni' 
      AND TABLE_NAME = 'users' 
      AND COLUMN_NAME = 'personel_id' 
      AND NON_UNIQUE = 0
")->fetchColumn();

if ($uqCheck == 0) {
    echo "  - Menambahkan UNIQUE KEY uq_users_personel (personel_id)...\n";
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT uq_users_personel UNIQUE (personel_id)");
} else {
    echo "  - UNIQUE KEY pada users.personel_id sudah aktif.\n";
}


// 3. Pasang Foreign Keys pada dosir_files (uploaded_by & verified_by)
echo "[3/6] Menghubungkan relasi dosir_files ke users...\n";

// Helper function to check if constraint exists
function constraintExists($pdo, $table, $constraintName) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = 'edosir_tni' 
          AND TABLE_NAME = ? 
          AND CONSTRAINT_NAME = ?
    ");
    $stmt->execute([$table, $constraintName]);
    return $stmt->fetchColumn() > 0;
}

if (!constraintExists($pdo, 'dosir_files', 'fk_dosir_uploaded_by')) {
    echo "  - Menambahkan FK fk_dosir_uploaded_by...\n";
    $pdo->exec("
        ALTER TABLE dosir_files 
        ADD CONSTRAINT fk_dosir_uploaded_by 
        FOREIGN KEY (uploaded_by) REFERENCES users(id) 
        ON DELETE SET NULL ON UPDATE CASCADE
    ");
} else {
    echo "  - FK fk_dosir_uploaded_by sudah ada.\n";
}

if (!constraintExists($pdo, 'dosir_files', 'fk_dosir_verified_by')) {
    echo "  - Menambahkan FK fk_dosir_verified_by...\n";
    $pdo->exec("
        ALTER TABLE dosir_files 
        ADD CONSTRAINT fk_dosir_verified_by 
        FOREIGN KEY (verified_by) REFERENCES users(id) 
        ON DELETE SET NULL ON UPDATE CASCADE
    ");
} else {
    echo "  - FK fk_dosir_verified_by sudah ada.\n";
}


// 4. Pasang Foreign Key pada activity_log
echo "[4/6] Menghubungkan relasi activity_log ke users...\n";
if (!constraintExists($pdo, 'activity_log', 'fk_activity_user')) {
    echo "  - Menambahkan FK fk_activity_user...\n";
    $pdo->exec("
        ALTER TABLE activity_log 
        ADD CONSTRAINT fk_activity_user 
        FOREIGN KEY (user_id) REFERENCES users(id) 
        ON DELETE SET NULL ON UPDATE CASCADE
    ");
} else {
    echo "  - FK fk_activity_user sudah ada.\n";
}


// 5. Pasang Foreign Key pada backup_log
echo "[5/6] Menghubungkan relasi backup_log ke users...\n";
if (!constraintExists($pdo, 'backup_log', 'fk_backup_user')) {
    echo "  - Menambahkan FK fk_backup_user...\n";
    $pdo->exec("
        ALTER TABLE backup_log 
        ADD CONSTRAINT fk_backup_user 
        FOREIGN KEY (created_by) REFERENCES users(id) 
        ON DELETE SET NULL ON UPDATE CASCADE
    ");
} else {
    echo "  - FK fk_backup_user sudah ada.\n";
}


// 6. Pasang Indeks Kinerja Pencarian (Query Performance Indexes)
echo "[6/6] Memeriksa dan menambahkan indeks performa kueri...\n";

function indexExists($pdo, $table, $indexName) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = 'edosir_tni' 
          AND TABLE_NAME = ? 
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $indexName]);
    return $stmt->fetchColumn() > 0;
}

$indexes = [
    ['dosir_files', 'idx_dosir_status', 'status'],
    ['dosir_files', 'idx_dosir_sigcode', 'signature_code'],
    ['personel', 'idx_personel_satuan', 'satuan'],
    ['personel', 'idx_personel_gol', 'golongan'],
    ['personel', 'idx_personel_status_dinas', 'status_dinas'],
];

foreach ($indexes as $idx) {
    list($table, $name, $col) = $idx;
    if (!indexExists($pdo, $table, $name)) {
        echo "  - Menambahkan indeks $name pada $table($col)...\n";
        $pdo->exec("ALTER TABLE $table ADD INDEX $name ($col)");
    } else {
        echo "  - Indeks $name pada $table sudah ada.\n";
    }
}

echo "\nSelamat! Seluruh relasi natural dan integritas referensial basis data berhasil diterapkan 100%!\n";
