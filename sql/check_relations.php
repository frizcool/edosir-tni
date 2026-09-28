<?php
require_once __DIR__ . '/../config/database.php';

$sql = "
    SELECT 
        TABLE_NAME, 
        COLUMN_NAME, 
        CONSTRAINT_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'edosir_tni' 
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
";

echo "=== FOREIGN KEYS AKTIF DI DATABASE edosir_tni ===\n";
foreach ($pdo->query($sql) as $r) {
    echo "  {$r['TABLE_NAME']}.{$r['COLUMN_NAME']} -> {$r['REFERENCED_TABLE_NAME']}.{$r['REFERENCED_COLUMN_NAME']} [{$r['CONSTRAINT_NAME']}]\n";
}

echo "\n=== PENGECEKAN RECORD YATIM (ORPHANED RECORDS) ===\n";
$checks = [
    'dosir_files.uploaded_by' => 'SELECT COUNT(*) FROM dosir_files WHERE uploaded_by IS NOT NULL AND uploaded_by NOT IN (SELECT id FROM users)',
    'dosir_files.verified_by' => 'SELECT COUNT(*) FROM dosir_files WHERE verified_by IS NOT NULL AND verified_by NOT IN (SELECT id FROM users)',
    'activity_log.user_id'    => 'SELECT COUNT(*) FROM activity_log WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)',
    'backup_log.created_by'   => 'SELECT COUNT(*) FROM backup_log WHERE created_by IS NOT NULL AND created_by NOT IN (SELECT id FROM users)',
    'users.personel_id'       => 'SELECT COUNT(*) FROM users WHERE personel_id IS NOT NULL AND personel_id NOT IN (SELECT id FROM personel)',
];

foreach ($checks as $label => $q) {
    $orphans = $pdo->query($q)->fetchColumn();
    echo "  $label orphaned: $orphans\n";
}

