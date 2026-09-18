<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$status = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending','approved','rejected'];
if (!in_array($status, $allowedStatus, true)) $status = 'pending';

$stmt = $pdo->prepare("
    SELECT f.*, p.nama, p.nrp, p.satuan, m.nama_dosir
    FROM dosir_files f
    JOIN personel p ON p.id = f.personel_id
    JOIN dosir_master m ON m.kode = f.dosir_kode
    WHERE f.status = ?
    ORDER BY f.uploaded_at ASC
");
$stmt->execute([$status]);
$list = $stmt->fetchAll();

$pageTitle = 'Verifikasi Dosir';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom:16px;">
  <div style="display:flex;gap:10px;">
    <a href="?status=pending" class="btn <?= $status==='pending'?'':'btn-outline' ?>">Menunggu</a>
    <a href="?status=approved" class="btn <?= $status==='approved'?'':'btn-outline' ?>">Disetujui</a>
    <a href="?status=rejected" class="btn <?= $status==='rejected'?'':'btn-outline' ?>">Ditolak</a>
  </div>
</div>

<div class="card">
  <table>
    <thead><tr><th>Personel</th><th>NRP</th><th>Dosir</th><th>Berkas</th><th>Diunggah</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (empty($list)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--text-dim);">Tidak ada data</td></tr>
    <?php endif; ?>
    <?php foreach ($list as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['nama']) ?><div style="font-size:11px;color:var(--text-dim);"><?= htmlspecialchars($row['satuan']) ?></div></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($row['nrp']) ?></td>
        <td>DOSIR <?= $row['dosir_kode'] ?><div style="font-size:11px;color:var(--text-dim);"><?= htmlspecialchars($row['nama_dosir']) ?></div></td>
        <td>
          <a href="<?= dosir_url($row['file_path']) ?>" target="_blank"><?= htmlspecialchars($row['file_name']) ?></a>
          <?php if (!empty($row['signature_code'])): ?>
            <br><a href="<?= BASE_URL ?>/verify.php?code=<?= urlencode($row['signature_code']) ?>" target="_blank" class="watermark-stamp" style="text-decoration:none;font-size:10px;" title="Verifikasi TTE Digital">✓ TTE: <?= htmlspecialchars($row['signature_code']) ?></a>
          <?php elseif ($row['status'] === 'approved'): ?>
            <br><span class="badge badge-approved" style="font-size:10px;">SAH (TANPA WATERMARK)</span>
          <?php elseif ($row['status'] === 'pending'): ?>
            <br><span style="font-size:10px;color:var(--danger);font-weight:600;">(Watermark Belum Verifikasi)</span>
          <?php endif; ?>
        </td>
        <td><?= fmt_tgl($row['uploaded_at']) ?></td>
        <td><span class="badge badge-<?= $row['status'] ?>"><?= strtoupper($row['status']) ?></span></td>
        <td>
          <?php if ($row['status'] === 'pending'): ?>
          <form method="post" action="<?= BASE_URL ?>/admin/dosir_verify_process.php" style="display:flex;gap:6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <input type="hidden" name="status" value="<?= $status ?>">
            <input type="hidden" name="catatan_verifikasi" value="">
            <button type="submit" name="action" value="approved" class="btn" style="padding:6px 10px;font-size:12px;background:var(--ok);">Setujui</button>
            <button type="button" class="btn btn-danger" style="padding:6px 10px;font-size:12px;" onclick="rejectFile(this.form)">Tolak...</button>
          </form>
          <?php else: ?>
            <span style="color:var(--text-dim);font-size:12px;"><?= $row['catatan_verifikasi'] ? htmlspecialchars($row['catatan_verifikasi']) : '-' ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function rejectFile(form) {
  const note = prompt('Masukkan alasan / catatan penolakan berkas (misal: Scan buram, halaman tidak lengkap, dll):');
  if (note === null) return;
  form.catatan_verifikasi.value = note;
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'action';
  input.value = 'rejected';
  form.appendChild(input);
  form.submit();
}
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
