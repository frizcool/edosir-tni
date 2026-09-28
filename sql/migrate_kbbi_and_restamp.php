<?php
/**
 * Script Penyelarasan Bahasa KBBI & Re-stamping TTE (Eliminasi '?')
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/watermark.php';

echo "=== 1. MEMPERBARUI MASTER 33 DOSIR SESUAI KBBI ===\n";
$updates = [
    '02' => 'AKTA KELAHIRAN/KENAL YBS',
    '03' => 'AKTA KELAHIRAN/KENAL ANAK',
    '05' => 'IJAZAH STTB (DIKUM)',
    '14' => 'SURAT IZIN NIKAH, FC AKTA NIKAH',
    '16' => 'IJAZAH DIKMIL/SAR/TUK/CAB',
];

$stmtMaster = $pdo->prepare("UPDATE dosir_master SET nama_dosir = ? WHERE kode = ?");
foreach ($updates as $kode => $namaBaru) {
    $stmtMaster->execute([$namaBaru, $kode]);
    echo "  [OK] Master Dosir $kode diselaraskan menjadi: $namaBaru\n";
}

echo "\n=== 2. MEMPERBARUI DOSIR_FILES (IJAZAH & AKTA) ===\n";
$stmtFiles = $pdo->query("SELECT id, original_name, keterangan FROM dosir_files WHERE original_name LIKE '%ijasah%' OR original_name LIKE '%akte%' OR keterangan LIKE '%ijasah%' OR keterangan LIKE '%akte%' OR original_name LIKE '%akta%' OR original_name LIKE '%ijazah%'");
$files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

$searchWords  = ['AKTE', 'Akte', 'akte', 'IJASAH', 'Ijasah', 'ijasah'];
$replaceWords = ['AKTA', 'Akta', 'akta', 'IJAZAH', 'Ijazah', 'ijazah'];

$updFile = $pdo->prepare("UPDATE dosir_files SET original_name = ?, keterangan = ? WHERE id = ?");
foreach ($files as $f) {
    $orig = $f['original_name'];
    $ket  = $f['keterangan'];

    if ($orig) {
        $orig = str_replace($searchWords, $replaceWords, $orig);
    }
    if ($ket) {
        $ket = str_replace($searchWords, $replaceWords, $ket);
    }

    $updFile->execute([$orig, $ket, $f['id']]);
    echo "  [OK] File ID {$f['id']}: original_name='{$orig}', keterangan='{$ket}'\n";
}

echo "\n=== 3. RE-STAMPING TTE PADA SELURUH BERKAS APPROVED (ELIMINASI '?') ===\n";
$stmtApproved = $pdo->query("
    SELECT df.*, p.nama, p.pangkat, p.nrp, p.satuan 
    FROM dosir_files df 
    LEFT JOIN personel p ON p.id = df.personel_id 
    WHERE df.status = 'approved'
");
$approvedRows = $stmtApproved->fetchAll(PDO::FETCH_ASSOC);

$reStampedCount = 0;
$updHash = $pdo->prepare("UPDATE dosir_files SET signature_hash = ? WHERE id = ?");

foreach ($approvedRows as $r) {
    $id = $r['id'];
    $rawRel = $r['raw_file_path'];
    $outRel = $r['file_path'];

    if (!$rawRel || !file_exists(UPLOAD_DIR . '/' . $rawRel)) {
        echo "  [SKIP] ID $id: raw file master tidak ditemukan ($rawRel)\n";
        continue;
    }

    $rawMasterPath = UPLOAD_DIR . '/' . $rawRel;
    $outputPath    = UPLOAD_DIR . '/' . $outRel;

    $signatureCode = $r['signature_code'] ?: ("TTE-TNIAD-" . date('Ym') . "-{$id}-" . strtoupper(substr(md5((string)$id), 0, 6)));
    $docHash = hash_file('sha256', $rawMasterPath);

    // URL Verifikasi
    $verifyUrl = 'http://localhost' . BASE_URL . '/verify.php?code=' . urlencode($signatureCode);

    // Ambil info penandatangan admin jika ada
    $adminNama = 'Administrator Sistem';
    $adminPangkat = 'Admin Pers';
    $adminNrp = '-';
    $adminSatuan = 'Mabesad';

    if (!empty($r['verified_by'])) {
        $st = $pdo->prepare("SELECT u.username, p.nama, p.pangkat, p.nrp, p.satuan FROM users u LEFT JOIN personel p ON p.id = u.personel_id WHERE u.id = ?");
        $st->execute([$r['verified_by']]);
        $adm = $st->fetch();
        if ($adm) {
            $adminNama = !empty($adm['nama']) ? $adm['nama'] : $adm['username'];
            $adminPangkat = !empty($adm['pangkat']) ? $adm['pangkat'] : 'Admin Pers';
            $adminNrp = !empty($adm['nrp']) ? $adm['nrp'] : ($adm['username'] ?? '-');
            $adminSatuan = !empty($adm['satuan']) ? $adm['satuan'] : 'TNI Angkatan Darat';
        }
    }

    $signDate = $r['verified_at'] ? date('d-m-Y H:i:s', strtotime($r['verified_at'])) : date('d-m-Y H:i:s');

    $signData = [
        'code'          => $signatureCode,
        'hash'          => $docHash,
        'verify_url'    => $verifyUrl,
        'nama_admin'    => $adminNama,
        'pangkat_admin' => $adminPangkat,
        'nrp_admin'     => $adminNrp,
        'satuan_admin'  => $adminSatuan,
        'date'          => $signDate,
    ];

    $ok = apply_digital_signature_pdf($rawMasterPath, $outputPath, $signData);
    if ($ok) {
        @touch($outputPath);
        $newSignedHash = hash_file('sha256', $outputPath);
        $updHash->execute([$newSignedHash, $id]);
        $reStampedCount++;
        echo "  [OK] ID $id ({$r['file_name']}) disahkan ulang dengan TTE bersih tanpa '?' | Kode: $signatureCode\n";
    } else {
        echo "  [FAIL] ID $id gagal disahkan ulang.\n";
    }
}

echo "\n=== SELESAI: $reStampedCount berkas berhasil di-stamp ulang secara bersih! ===\n";
