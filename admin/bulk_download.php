<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$dosirList = get_dosir_master($pdo);
$personelPreset = (int) ($_GET['personel_id'] ?? 0);

$satuanOptions = $pdo->query("SELECT DISTINCT ms.nama FROM master_satuan ms JOIN personel p ON p.satuan_id = ms.id ORDER BY ms.nama")->fetchAll(PDO::FETCH_COLUMN);
$personelOptions = $pdo->query("SELECT id, nama, nrp FROM personel ORDER BY nama")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $kodeTerpilih = $_POST['kode'] ?? [];          // array kode dosir yang dicentang
    $personelTerpilih = $_POST['personel_ids'] ?? []; // array id personel (jika kosong = semua sesuai filter satuan)
    $satuan = trim($_POST['satuan'] ?? '');
    $hanyaApproved = isset($_POST['hanya_approved']);

    if (empty($kodeTerpilih)) {
        set_flash('error', 'Pilih minimal satu jenis dosir untuk diunduh.');
        redirect('/admin/bulk_download.php');
    }

    $sql = "SELECT f.*, p.nrp, p.nama FROM dosir_files f JOIN personel p ON p.id=f.personel_id LEFT JOIN master_satuan ms ON ms.id=p.satuan_id WHERE 1=1";
    $params = [];

    $inKode = implode(',', array_fill(0, count($kodeTerpilih), '?'));
    $sql .= " AND f.dosir_kode IN ($inKode)";
    $params = array_merge($params, $kodeTerpilih);

    if (!empty($personelTerpilih)) {
        $inP = implode(',', array_fill(0, count($personelTerpilih), '?'));
        $sql .= " AND f.personel_id IN ($inP)";
        $params = array_merge($params, $personelTerpilih);
    } elseif ($satuan !== '') {
        $sql .= " AND ms.nama = ?";
        $params[] = $satuan;
    }

    if ($hanyaApproved) {
        $sql .= " AND f.status = 'approved'";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    if (empty($files)) {
        set_flash('error', 'Tidak ada berkas yang sesuai dengan filter yang dipilih.');
        redirect('/admin/bulk_download.php');
    }

    ensure_dir(EXPORT_DIR);
    cleanup_expired_exports(); // Hapus berkas ekspor kadaluarsa (> 24 jam)
    @set_time_limit(300);      // Berikan waktu proses cukup untuk arsip besar

    $zipName = 'dosir_export_' . date('Ymd_His') . '.zip';
    $zipPath = EXPORT_DIR . '/' . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        set_flash('error', 'Gagal membuat berkas ZIP.');
        redirect('/admin/bulk_download.php');
    }

    foreach ($files as $f) {
        $abs = UPLOAD_DIR . '/' . $f['file_path'];
        if (file_exists($abs)) {
            // struktur dalam zip: NRP_Nama/FOLDER kode/namafile.pdf
            $inZipDir = $f['nrp'] . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $f['nama']);
            $zip->addFile($abs, $inZipDir . '/' . $f['file_path']);
        }
    }
    $zip->close();

    log_activity($pdo, $admin['id'], 'BULK_DOWNLOAD', 'Unduh massal: ' . count($files) . ' berkas -> ' . $zipName);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    exit;
}

$pageTitle = 'Unduh Massal Dosir';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h3 style="margin-top:0;">Unduh Massal Berkas Dosir</h3>
  <p style="color:var(--text-dim);font-size:13px;">Pilih jenis dosir dan cakupan personel, lalu unduh sebagai satu berkas ZIP.</p>

  <form method="post">
    <?= csrf_field() ?>
    <div class="grid grid-2">
      <div>
        <label>Satuan (jika tidak memilih personel spesifik)</label>
        <select name="satuan">
          <option value="">Semua Satuan</option>
          <?php foreach ($satuanOptions as $s): ?><option><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
        </select>

        <label>Atau Pilih Personel Tertentu (opsional, bisa multi)</label>
        <select name="personel_ids[]" multiple size="8">
          <?php foreach ($personelOptions as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $personelPreset === (int)$p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['nama']) ?> (<?= htmlspecialchars($p['nrp']) ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <label style="margin-top:14px;"><input type="checkbox" name="hanya_approved" checked style="width:auto;display:inline-block;margin-right:6px;">Hanya berkas berstatus disetujui</label>
      </div>

      <div>
        <label>Checklist Jenis Dosir</label>
        <div style="max-height:340px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:10px;">
          <label style="display:block;margin-bottom:6px;"><input type="checkbox" onclick="document.querySelectorAll('.chk-dosir').forEach(c=>c.checked=this.checked)" style="width:auto;display:inline-block;margin-right:6px;"><strong>Pilih Semua</strong></label>
          <?php foreach ($dosirList as $d): ?>
            <label style="display:block;margin-bottom:6px;font-size:13px;">
              <input type="checkbox" class="chk-dosir" name="kode[]" value="<?= $d['kode'] ?>" style="width:auto;display:inline-block;margin-right:6px;">
              DOSIR <?= $d['kode'] ?> - <?= htmlspecialchars($d['nama_dosir']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button type="submit" class="btn" style="margin-top:16px;">⬇ Unduh ZIP</button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
