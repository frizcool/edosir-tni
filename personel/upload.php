<?php
require_once __DIR__ . '/../config/config.php';
require_role('personel');

$u = current_user();
$personel_id = $u['personel_id'];
$dosirList = get_dosir_master($pdo);
$selectedKode = $_GET['kode'] ?? '';

// Ambil riwayat berkas untuk jenis dosir ini jika dipilih
$existingFiles = [];
if ($selectedKode) {
    $stmtE = $pdo->prepare("SELECT * FROM dosir_files WHERE personel_id=? AND dosir_kode=? ORDER BY uploaded_at DESC");
    $stmtE->execute([$personel_id, $selectedKode]);
    $existingFiles = $stmtE->fetchAll();
}

$pageTitle = 'Unggah Dosir';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:620px;">
  <h3 style="margin-top:0;">Unggah Berkas Dosir</h3>
  <p style="color:var(--text-dim);font-size:13px;line-height:1.5;">
    Berkas wajib dalam format <strong>PDF</strong> (maks. 10 MB). Dokumen tambahan pada jenis dosir yang sama
    otomatis diberi akhiran abjad (a, b, c, ...). Seluruh berkas berstatus
    <span class="badge badge-pending">PENDING</span> sebelum diverifikasi oleh Admin.
  </p>

  <?php if (!empty($existingFiles)): ?>
  <div style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:18px;">
    <strong style="font-size:13px;display:block;margin-bottom:8px;">Berkas Terunggah Sebelumnya pada Dosir Ini:</strong>
    <div style="display:flex;flex-direction:column;gap:6px;">
      <?php foreach ($existingFiles as $ef): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 8px;background:var(--panel);border-radius:6px;font-size:12px;">
        <div>
          <a href="<?= dosir_url($ef['file_path']) ?>" target="_blank" style="font-weight:600;color:var(--gold);text-decoration:underline;">
            📄 <?= htmlspecialchars($ef['file_name']) ?>
          </a>
          <span class="badge badge-<?= $ef['status'] ?>" style="font-size:10px;margin-left:6px;"><?= strtoupper($ef['status']) ?></span>
          <?php if (!empty($ef['catatan_verifikasi'])): ?>
            <div style="color:var(--danger);margin-top:2px;">Catatan Admin: <?= htmlspecialchars($ef['catatan_verifikasi']) ?></div>
          <?php endif; ?>
        </div>
        <span style="color:var(--text-dim);"><?= fmt_tgl($ef['uploaded_at']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= BASE_URL ?>/personel/upload_process.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Jenis Dosir *</label>
    <select name="kode" required onchange="if(this.value) window.location='?kode='+this.value">
      <option value="">-- Pilih Jenis Dosir --</option>
      <?php foreach ($dosirList as $d): ?>
        <option value="<?= $d['kode'] ?>" <?= $selectedKode === $d['kode'] ? 'selected' : '' ?>>
          DOSIR <?= $d['kode'] ?> - <?= htmlspecialchars($d['nama_dosir']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Keterangan Dokumen (Opsional)</label>
    <input type="text" name="keterangan" placeholder="cth: Ijazah SMA Legalitas, FC Akta Nikah, dll">

    <label>Pilih File PDF *</label>
    <input type="file" name="berkas" accept="application/pdf" required>

    <div style="display:flex;gap:10px;margin-top:6px;">
      <button type="submit" class="btn" style="flex:1;">Unggah Berkas PDF</button>
      <?php if ($selectedKode): ?>
        <a href="<?= BASE_URL ?>/personel/scan.php?kode=<?= $selectedKode ?>" class="btn btn-outline" style="text-align:center;">
          📷 Scan Kamera
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
