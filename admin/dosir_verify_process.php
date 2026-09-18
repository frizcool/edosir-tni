<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/watermark.php';
require_admin();

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/dosir_verify.php');

// Validasi Keamanan CSRF
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$backStatus = $_POST['status'] ?? 'pending';

if (!$id || !in_array($action, ['approved', 'rejected'], true)) {
    redirect('/admin/dosir_verify.php?status=' . urlencode($backStatus));
}

$stmt = $pdo->prepare("SELECT * FROM dosir_files WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    set_flash('error', 'Berkas tidak ditemukan.');
    redirect('/admin/dosir_verify.php?status=' . urlencode($backStatus));
}

$catatan = trim($_POST['catatan_verifikasi'] ?? '');

if ($action === 'approved') {
    // 1. Tentukan berkas master bersih (unwatermarked original)
    $rawMasterPath = null;
    $rawRel = 'raw/' . $file['file_path'];

    if (!empty($file['raw_file_path']) && file_exists(UPLOAD_DIR . '/' . $file['raw_file_path'])) {
        $rawMasterPath = UPLOAD_DIR . '/' . $file['raw_file_path'];
    } elseif (file_exists(UPLOAD_DIR . '/' . $rawRel)) {
        $rawMasterPath = UPLOAD_DIR . '/' . $rawRel;
        $pdo->prepare("UPDATE dosir_files SET raw_file_path = ? WHERE id = ?")->execute([$rawRel, $id]);
    } else {
        $activeCandidate = UPLOAD_DIR . '/' . $file['file_path'];
        $rawDir = dirname(UPLOAD_DIR . '/' . $rawRel);
        ensure_dir($rawDir);
        if (file_exists($activeCandidate)) {
            copy($activeCandidate, UPLOAD_DIR . '/' . $rawRel);
            $rawMasterPath = UPLOAD_DIR . '/' . $rawRel;
            $pdo->prepare("UPDATE dosir_files SET raw_file_path = ? WHERE id = ?")->execute([$rawRel, $id]);
        } else {
            $rawMasterPath = $activeCandidate;
        }
    }

    $outputPath = UPLOAD_DIR . '/' . $file['file_path'];

    // 2. Generate Nomor Registrasi TTE Unik & Hash Dokumen
    $yearMonth = date('Ym');
    $randToken = strtoupper(substr(md5(uniqid((string)$id, true)), 0, 6));
    $signatureCode = "TTE-TNIAD-{$yearMonth}-{$id}-{$randToken}";
    $docHash = file_exists($rawMasterPath) ? hash_file('sha256', $rawMasterPath) : '';

    // URL Verifikasi Publik untuk QR Code
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verifyUrl = $scheme . '://' . $host . BASE_URL . '/verify.php?code=' . urlencode($signatureCode);

    // Ambil Profil Lengkap Administrator Verifikator
    $stmtAdmin = $pdo->prepare("SELECT p.nama, p.pangkat, p.nrp, p.satuan FROM users u LEFT JOIN personel p ON p.id = u.personel_id WHERE u.id = ?");
    $stmtAdmin->execute([$admin['id']]);
    $adminInfo = $stmtAdmin->fetch();

    $namaAdmin    = !empty($adminInfo['nama']) ? $adminInfo['nama'] : ($admin['username'] ?: 'Administrator Sistem');
    $pangkatAdmin = !empty($adminInfo['pangkat']) ? $adminInfo['pangkat'] : 'Admin Pers';
    $nrpAdmin     = !empty($adminInfo['nrp']) ? $adminInfo['nrp'] : ($admin['username'] ?? '-');
    $satuanAdmin  = !empty($adminInfo['satuan']) ? $adminInfo['satuan'] : get_setting($pdo, 'instansi', 'TNI Angkatan Darat');

    $signData = [
        'code'          => $signatureCode,
        'hash'          => $docHash,
        'verify_url'    => $verifyUrl,
        'nama_admin'    => $namaAdmin,
        'pangkat_admin' => $pangkatAdmin,
        'nrp_admin'     => $nrpAdmin,
        'satuan_admin'  => $satuanAdmin,
        'date'          => date('d-m-Y H:i:s'),
    ];

    // 3. Terapkan Tanda Tangan Elektronik dari berkas master bersih (Watermark "BELUM TERVERIFIKASI" otomatis HILANG)
    $ok = apply_digital_signature_pdf($rawMasterPath, $outputPath, $signData);
    if ($ok && file_exists($outputPath)) {
        @touch($outputPath); // Segarkan timestamp file agar cache browser langsung ganti
    }

    $upd = $pdo->prepare("
        UPDATE dosir_files 
        SET status = 'approved', 
            catatan_verifikasi = ?, 
            verified_by = ?, 
            verified_at = NOW(),
            is_watermarked = 0,
            signature_code = ?,
            signature_hash = ?
        WHERE id = ?
    ");
    $upd->execute([$catatan !== '' ? $catatan : null, $admin['id'], $signatureCode, $docHash, $id]);

    set_flash('success', "Berkas disetujui! Watermark 'BELUM TERVERIFIKASI' telah dihilangkan dan digantikan Sertifikasi Tanda Tangan Elektronik (TTE: $signatureCode).");
} else {
    $upd = $pdo->prepare("
        UPDATE dosir_files 
        SET status = 'rejected', 
            catatan_verifikasi = ?, 
            verified_by = ?, 
            verified_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([$catatan !== '' ? $catatan : null, $admin['id'], $id]);

    set_flash('success', 'Berkas ditolak. Personel dapat membaca catatan verifikasi dan mengunggah berkas perbaikan.');
}

log_activity($pdo, $admin['id'], 'VERIFIKASI_DOSIR', "Dosir file #$id -> $action" . ($action === 'approved' ? " (TTE: $signatureCode)" : ''));
redirect('/admin/dosir_verify.php?status=' . urlencode($backStatus));
