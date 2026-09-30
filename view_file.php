<?php
/**
 * TRISULA TNI AD - CONTROLLER PENGALIRAN BERKAS TERAUTENTIKASI (SECURE STREAMING)
 * Mengalirkan dokumen dosir militer PDF dan foto profil secara aman dengan
 * validasi wewenang, pencegahan traversal direktori, dan proteksi anti-cache.
 */
require_once __DIR__ . '/config/config.php';
require_login();

$u = current_user();
$fileId = (int)($_GET['id'] ?? 0);
$filePath = trim($_GET['file'] ?? '');

$record = null;

if ($fileId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM dosir_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $record = $stmt->fetch();
} elseif ($filePath !== '') {
    // Normalisasi slash
    $normPath = str_replace('\\', '/', $filePath);
    // Hapus kemungkinan prefix uploads/
    if (strpos($normPath, 'uploads/') === 0) {
        $normPath = substr($normPath, 8);
    }
    $stmt = $pdo->prepare("SELECT * FROM dosir_files WHERE file_path = ? OR file_name = ?");
    $stmt->execute([$normPath, basename($normPath)]);
    $record = $stmt->fetch();
}

// Jika bukan file dosir langsung, cek apakah foto profil prajurit
$targetPhysicalPath = null;
$mimeType = 'application/pdf';

if ($record) {
    // Cek Otorisasi: Admin boleh lihat semua, personel HANYA dosirnya sendiri
    if ($u['role'] !== 'admin' && (int)$record['personel_id'] !== (int)($u['personel_id'] ?? 0)) {
        abort(403, 'Akses Ditolak: Anda tidak memiliki hak wewenang untuk melihat berkas arsip prajurit lain.', 'Pelanggaran Privasi Berkas');
    }

    // Resolusi path fleksibel untuk toleransi berbagai penamaan folder di uploads
    $relFile = str_replace('\\', '/', $record['file_path']);
    $cleanRel = preg_replace('#^uploads/#i', '', ltrim($relFile, '/'));
    $candidates = [
        UPLOAD_DIR . '/' . $cleanRel,
        APP_ROOT . '/' . $cleanRel,
        UPLOAD_DIR . '/' . $relFile,
        APP_ROOT . '/' . $relFile,
    ];
    foreach ($candidates as $cand) {
        if (file_exists($cand) && is_file($cand)) {
            $targetPhysicalPath = $cand;
            break;
        }
    }
} else {
    // Cek foto profil khusus
    $normPath = str_replace('\\', '/', $filePath);
    if (strpos($normPath, 'foto_profil/') !== false) {
        $cleanRel = 'foto_profil/' . basename($normPath);
        $candidate = UPLOAD_DIR . '/' . $cleanRel;
        if (file_exists($candidate)) {
            // Cek otorisasi foto
            if ($u['role'] === 'admin') {
                $targetPhysicalPath = $candidate;
            } else {
                // Pastikan foto milik personel yang login
                $stmtFoto = $pdo->prepare("SELECT id FROM personel WHERE id = ? AND foto LIKE ?");
                $stmtFoto->execute([$u['personel_id'], "%$cleanRel%"]);
                if ($stmtFoto->fetch()) {
                    $targetPhysicalPath = $candidate;
                }
            }
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            $mimeType = in_array($ext, ['png', 'webp', 'gif'], true) ? "image/$ext" : 'image/jpeg';
        }
    }
}

if (!$targetPhysicalPath || !file_exists($targetPhysicalPath)) {
    abort(404, 'Berkas dosir fisik tidak ditemukan pada sistem penyimpanan atau telah dipindahkan dari arsip.');
}

$realTarget = realpath($targetPhysicalPath);
if (!$realTarget || !is_file($realTarget)) {
    abort(404, 'Berkas fisik tidak dapat diakses pada disk server.');
}

// Pencegahan Directory Traversal (Normalisasi Path Lintas Sistem Operasi Linux & Windows)
$normUploadDir = str_replace('\\', '/', strtolower(realpath(UPLOAD_DIR) ?: UPLOAD_DIR));
$normTarget    = str_replace('\\', '/', strtolower($realTarget));

if (strpos($normTarget, $normUploadDir) !== 0) {
    abort(403, 'Akses ditolak: Percobaan akses path di luar direktori aman terdeteksi.', 'Directory Traversal Prevented');
}

$downloadName = !empty($record['original_name']) ? $record['original_name'] : (!empty($record['file_name']) ? $record['file_name'] : basename($realTarget));
$downloadName = preg_replace('/[^\w\.\-\s]/u', '_', $downloadName);

// Bersihkan semua output buffer sebelum mengirim header biner
while (ob_get_level()) {
    ob_end_clean();
}

// Alirkan berkas ke browser
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($realTarget));
header('Accept-Ranges: bytes');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realTarget)) . ' GMT');

if (strpos($mimeType, 'image/') === 0) {
    // Foto profil prajurit diizinkan cache dengan revalidasi timestamp &v=
    header('Cache-Control: private, max-age=86400, must-revalidate');
} else {
    // Berkas dokumen dosir militer wajib zero-cache agar pergantian status (watermark hilang) instan
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
    header('Pragma: no-cache');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
}

readfile($realTarget);
exit;
