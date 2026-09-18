<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$msg = null; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $jenis = $_POST['jenis'] ?? 'full';
    ensure_dir(BACKUP_DIR);
    $timestamp = date('Ymd_His');

    try {
        if ($jenis === 'database' || $jenis === 'full') {
            $dbFile = BACKUP_DIR . "/db_{$timestamp}.sql";
            // Deteksi path mysqldump (termasuk path XAMPP di Windows)
            $mysqldumpBin = 'mysqldump';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                if (file_exists('d:/xampp/mysql/bin/mysqldump.exe')) {
                    $mysqldumpBin = '"d:\\xampp\\mysql\\bin\\mysqldump.exe"';
                } elseif (file_exists('c:/xampp/mysql/bin/mysqldump.exe')) {
                    $mysqldumpBin = '"c:\\xampp\\mysql\\bin\\mysqldump.exe"';
                }
            }

            $cmd = sprintf(
                '%s --host=%s --user=%s %s %s > %s 2>&1',
                $mysqldumpBin,
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                DB_PASS !== '' ? '--password=' . escapeshellarg(DB_PASS) : '',
                escapeshellarg(DB_NAME),
                escapeshellarg($dbFile)
            );

            if (function_exists('exec')) {
                exec($cmd, $out, $code);
                if ($code === 0 && file_exists($dbFile) && filesize($dbFile) > 0) {
                    $pdo->prepare("INSERT INTO backup_log (file_name, size_bytes, jenis, created_by) VALUES (?,?,?,?)")
                        ->execute([basename($dbFile), filesize($dbFile), 'database', $admin['id']]);
                } else {
                    throw new Exception('mysqldump gagal dijalankan. Pastikan binary mysqldump tersedia di PATH server, atau lakukan backup manual via phpMyAdmin.');
                }
            } else {
                throw new Exception('Fungsi exec() dinonaktifkan di server ini. Lakukan backup database secara manual (phpMyAdmin / CLI).');
            }
        }

        if ($jenis === 'files' || $jenis === 'full') {
            $zipFile = BACKUP_DIR . "/files_{$timestamp}.zip";
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Gagal membuat arsip ZIP berkas dosir.');
            }
            $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $file) {
                $localPath = substr($file->getPathname(), strlen(UPLOAD_DIR) + 1);
                $zip->addFile($file->getPathname(), $localPath);
            }
            $zip->close();
            $pdo->prepare("INSERT INTO backup_log (file_name, size_bytes, jenis, created_by) VALUES (?,?,?,?)")
                ->execute([basename($zipFile), filesize($zipFile), 'files', $admin['id']]);
        }

        log_activity($pdo, $admin['id'], 'BACKUP', "Backup jenis: $jenis");
        $msg = 'Proses backup selesai.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$history = $pdo->query("SELECT b.*, u.username FROM backup_log b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.created_at DESC LIMIT 30")->fetchAll();

$pageTitle = 'Backup Data';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">Buat Backup Baru</h3>
  <p style="color:var(--text-dim);font-size:13px;">
    Backup database menghasilkan berkas .sql (struktur + data). Backup berkas menghasilkan arsip ZIP
    dari seluruh folder <code>uploads/</code> (semua dosir personel). Disarankan menjadwalkan proses ini
    secara berkala melalui cron job di server.
  </p>
  <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <select name="jenis" style="max-width:240px;margin-bottom:0;">
      <option value="full">Database + Berkas (Full)</option>
      <option value="database">Database Saja</option>
      <option value="files">Berkas Saja</option>
    </select>
    <button class="btn" type="submit">🗄 Jalankan Backup</button>
  </form>
</div>

<div class="card">
  <h3 style="margin-top:0;">Riwayat Backup</h3>
  <table>
    <thead><tr><th>Nama Berkas</th><th>Jenis</th><th>Ukuran</th><th>Oleh</th><th>Waktu</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php if (empty($history)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-dim);">Belum ada riwayat backup</td></tr>
      <?php endif; ?>
      <?php foreach ($history as $h): ?>
      <tr>
        <td><?= htmlspecialchars($h['file_name']) ?></td>
        <td><span class="badge badge-approved"><?= strtoupper($h['jenis']) ?></span></td>
        <td><?= number_format($h['size_bytes'] / 1024 / 1024, 2) ?> MB</td>
        <td><?= htmlspecialchars($h['username'] ?? '-') ?></td>
        <td><?= fmt_tgl($h['created_at']) ?></td>
        <td><a href="<?= BASE_URL ?>/admin/download_backup.php?file=<?= rawurlencode($h['file_name']) ?>" class="btn btn-outline" style="padding:5px 10px;font-size:12px;">Unduh</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
