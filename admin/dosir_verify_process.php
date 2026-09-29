<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/watermark.php';
require_admin();

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/dosir_verify.php');
}

// Validasi Keamanan CSRF
verify_csrf();

$action = $_POST['action'] ?? '';

// Helper parameter redirect agar filter dan halaman pengguna tetap terjaga
$redirectParams = [];
if (!empty($_POST['status'])) $redirectParams['status'] = $_POST['status'];
if (!empty($_POST['q'])) $redirectParams['q'] = $_POST['q'];
if (!empty($_POST['satuan'])) $redirectParams['satuan'] = $_POST['satuan'];
if (!empty($_POST['dosir_kode'])) $redirectParams['dosir_kode'] = $_POST['dosir_kode'];
if (!empty($_POST['page'])) $redirectParams['page'] = (int)$_POST['page'];
if (!empty($_POST['per_page'])) $redirectParams['per_page'] = (int)$_POST['per_page'];

$redirectUrl = '/admin/dosir_verify.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : '');

/**
 * Logika inti untuk memverifikasi atau menolak satu berkas dosir
 *
 * @param PDO $pdo
 * @param int $id
 * @param string $action 'approved'|'rejected'
 * @param array $admin
 * @param string $catatan
 * @return array ['success' => bool, 'code' => string, 'file_name' => string, 'error' => string]
 */
function process_single_dosir_verification(PDO $pdo, int $id, string $action, array $admin, string $catatan = '') {
    $stmt = $pdo->prepare("SELECT * FROM dosir_files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();

    if (!$file) {
        return ['success' => false, 'error' => 'Berkas tidak ditemukan'];
    }

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
        $signatureCode = "TTE-TRISULA-{$yearMonth}-{$id}-{$randToken}";
        $docHash = file_exists($rawMasterPath) ? hash_file('sha256', $rawMasterPath) : '';

        // URL Verifikasi Publik untuk QR Code
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $verifyUrl = $scheme . '://' . $host . BASE_URL . '/verify.php?code=' . urlencode($signatureCode);

        // Ambil Profil Lengkap Administrator Verifikator
        $stmtAdmin = $pdo->prepare("
            SELECT p.nama, mp.singkatan as pangkat, p.nrp, ms.nama as satuan 
            FROM users u 
            LEFT JOIN personel p ON p.id = u.personel_id 
            LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
            LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
            WHERE u.id = ?
        ");
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

        // 3. Terapkan Tanda Tangan Elektronik dari berkas master bersih
        $ok = apply_digital_signature_pdf($rawMasterPath, $outputPath, $signData);
        if ($ok && file_exists($outputPath)) {
            @touch($outputPath);
        }

        // Hitung hash integritas dokumen akhir yang telah dibubuhi TTE
        $signedHash = file_exists($outputPath) ? hash_file('sha256', $outputPath) : $docHash;

        $upd = $pdo->prepare("
            UPDATE dosir_files 
            SET status = 'approved', 
                catatan_verifikasi = ?, 
                verified_by = ?, 
                verified_at = NOW(),
                is_watermarked = 0,
                signature_code = ?,
                signature_hash = ?,
                raw_hash = ?
            WHERE id = ?
        ");
        $upd->execute([$catatan !== '' ? $catatan : null, $admin['id'], $signatureCode, $signedHash, $docHash, $id]);

        return ['success' => true, 'code' => $signatureCode, 'file_name' => $file['file_name']];
    } elseif ($action === 'rejected') {
        $upd = $pdo->prepare("
            UPDATE dosir_files 
            SET status = 'rejected', 
                catatan_verifikasi = ?, 
                verified_by = ?, 
                verified_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$catatan !== '' ? $catatan : null, $admin['id'], $id]);

        return ['success' => true, 'code' => null, 'file_name' => $file['file_name']];
    }

    return ['success' => false, 'error' => 'Aksi verifikasi tidak valid'];
}

// =====================================================================
// A. AKSI VERIFIKASI MASSAL (BULK VERIFICATION)
// =====================================================================
if ($action === 'bulk_approve' || $action === 'bulk_reject') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        set_flash('error', 'Pilih minimal satu berkas dosir untuk diproses secara massal.');
        redirect($redirectUrl);
    }

    $cleanIds = array_values(array_filter(array_map('intval', $ids)));
    if (empty($cleanIds)) {
        set_flash('error', 'Tidak ada ID berkas valid yang dipilih.');
        redirect($redirectUrl);
    }

    $targetAction = ($action === 'bulk_approve') ? 'approved' : 'rejected';
    $catatan = trim($_POST['catatan_verifikasi'] ?? '');

    $successCount = 0;
    $failCount = 0;

    foreach ($cleanIds as $fileId) {
        $res = process_single_dosir_verification($pdo, $fileId, $targetAction, $admin, $catatan);
        if ($res['success']) {
            $successCount++;
        } else {
            $failCount++;
        }
    }

    if ($targetAction === 'approved') {
        log_activity($pdo, $admin['id'], 'BULK_VERIFIKASI_DOSIR', "Verifikasi massal: $successCount berkas disetujui TTE (Gagal: $failCount)");
        set_flash('success', "Verifikasi Massal Berhasil! Sebanyak $successCount berkas dosir telah disetujui dan disahkan dengan Tanda Tangan Elektronik (TTE) resmi." . ($failCount > 0 ? " ($failCount berkas gagal)" : ''));
    } else {
        log_activity($pdo, $admin['id'], 'BULK_REJECT_DOSIR', "Penolakan massal: $successCount berkas ditolak (Catatan: $catatan)");
        set_flash('warning', "Penolakan Massal Selesai. Sebanyak $successCount berkas dosir telah ditolak dengan catatan verifikasi." . ($failCount > 0 ? " ($failCount berkas gagal)" : ''));
    }

    redirect($redirectUrl);
}

// =====================================================================
// B. AKSI VERIFIKASI TUNGGAL (SINGLE ACTION)
// =====================================================================
$id = (int)($_POST['id'] ?? 0);
if (!$id || !in_array($action, ['approved', 'rejected'], true)) {
    redirect($redirectUrl);
}

$catatan = trim($_POST['catatan_verifikasi'] ?? '');
$res = process_single_dosir_verification($pdo, $id, $action, $admin, $catatan);

if ($res['success']) {
    if ($action === 'approved') {
        log_activity($pdo, $admin['id'], 'VERIFIKASI_DOSIR', "Dosir file #$id approved (TTE: {$res['code']})");
        set_flash('success', "Berkas '{$res['file_name']}' berhasil disetujui! Watermark telah dihilangkan dan digantikan Sertifikasi Tanda Tangan Elektronik (TTE: {$res['code']}).");
    } else {
        log_activity($pdo, $admin['id'], 'VERIFIKASI_DOSIR', "Dosir file #$id rejected");
        set_flash('success', "Berkas '{$res['file_name']}' berhasil ditolak. Personel dapat melihat catatan verifikasi dan mengunggah perbaikan.");
    }
} else {
    set_flash('error', 'Gagal memproses berkas: ' . ($res['error'] ?? 'Terjadi kesalahan sistem.'));
}

redirect($redirectUrl);
