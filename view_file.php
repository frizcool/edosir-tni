<?php
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
        http_response_code(403);
        die('Akses Ditolak: Anda tidak memiliki hak untuk melihat berkas prajurit lain.');
    }
    $targetPhysicalPath = UPLOAD_DIR . '/' . $record['file_path'];
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
            $mimeType = in_array($ext, ['png','webp','gif'], true) ? "image/$ext" : 'image/jpeg';
        }
    }
}

if (!$targetPhysicalPath || !file_exists($targetPhysicalPath)) {
    http_response_code(404);
    die('Berkas tidak ditemukan atau telah dipindahkan dari arsip.');
}

// Pencegahan Directory Traversal
$realUploadDir = realpath(UPLOAD_DIR);
$realTarget    = realpath($targetPhysicalPath);

if (!$realTarget || strpos($realTarget, $realUploadDir) !== 0) {
    http_response_code(403);
    die('Akses ditolak: Percobaan akses path di luar direktori aman.');
}

// Alirkan berkas ke browser
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($realTarget) . '"');
header('Content-Length: ' . filesize($realTarget));
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
