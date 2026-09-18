<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$fileName = basename(trim($_GET['file'] ?? ''));

if ($fileName === '') {
    http_response_code(400);
    die('Nama berkas cadangan tidak valid.');
}

$filePath = BACKUP_DIR . '/' . $fileName;

$realBackupDir = realpath(BACKUP_DIR);
$realTarget    = realpath($filePath);

if (!$realTarget || strpos($realTarget, $realBackupDir) !== 0 || !file_exists($realTarget)) {
    http_response_code(404);
    die('Berkas cadangan tidak ditemukan.');
}

$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'sql') $mime = 'text/plain; charset=UTF-8';
elseif ($ext === 'zip') $mime = 'application/zip';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($realTarget));

readfile($realTarget);
exit;
