<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$satuan = trim($_GET['satuan'] ?? '');

$totalWajibDosir = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn() ?: 33;

// Hitung total rekord untuk paginasi
$countSql = "SELECT COUNT(DISTINCT p.id) FROM personel p WHERE 1=1";
$countParams = [];
if ($q !== '') {
    $countSql .= " AND (p.nama LIKE ? OR p.nrp LIKE ?)";
    $countParams[] = "%$q%"; $countParams[] = "%$q%";
}
if ($satuan !== '') {
    $countSql .= " AND p.satuan = ?";
    $countParams[] = $satuan;
}
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($countParams);
$totalRecords = (int)$stmtCount->fetchColumn();

// Parameter Paginasi
$perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT p.*,
           COUNT(DISTINCT CASE WHEN f.status='approved' AND m.wajib=1 THEN f.dosir_kode END) as terisi_dosir
    FROM personel p
    LEFT JOIN dosir_files f ON f.personel_id = p.id
    LEFT JOIN dosir_master m ON m.kode = f.dosir_kode
    WHERE 1=1
";
$params = [];
if ($q !== '') {
    $sql .= " AND (p.nama LIKE ? OR p.nrp LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%";
}
if ($satuan !== '') {
    $sql .= " AND p.satuan = ?";
    $params[] = $satuan;
}
$sql .= " GROUP BY p.id ORDER BY p.nama ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$satuanOptions = $pdo->query("SELECT DISTINCT satuan FROM personel WHERE satuan IS NOT NULL AND satuan<>'' ORDER BY satuan")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Data Personel';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom:16px;">
  <form method="get" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) auto;gap:16px;align-items:end;">
    <div>
      <label>Cari Nama / NRP</label>
      <input name="q" placeholder="Ketik nama atau NRP..." value="<?= htmlspecialchars($q) ?>">
    </div>
    <div>
      <label>Satuan</label>
      <select name="satuan">
        <option value="">Semua Satuan</option>
        <?php foreach ($satuanOptions as $s): ?>
          <option value="<?= htmlspecialchars($s) ?>" <?= $satuan === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="label-spacer">&nbsp;</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn" type="submit" style="min-width:100px;">🔍 Cari</button>
        <?php if ($q !== '' || $satuan !== ''): ?>
          <a href="<?= BASE_URL ?>/admin/personel_list.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
    <strong>Total: <?= count($list) ?> personel</strong>
    <div style="display:flex;gap:8px;">
      <a href="<?= BASE_URL ?>/admin/personel_export.php?q=<?= urlencode($q) ?>&satuan=<?= urlencode($satuan) ?>" class="btn btn-outline" style="font-size:12.5px;">
        📥 Ekspor Excel / CSV
      </a>
      <a href="<?= BASE_URL ?>/admin/personel_form.php" class="btn">+ Tambah Personel</a>
    </div>
  </div>
  <table>
    <thead><tr><th>NRP</th><th>Nama</th><th>Pangkat</th><th>Satuan</th><th>Jabatan</th><th>Kelengkapan</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($list as $p):
        $terisi = (int)($p['terisi_dosir'] ?? 0);
        $persen = round(($terisi / $totalWajibDosir) * 100, 1);
      ?>
      <tr>
        <td style="font-family:monospace;"><?= htmlspecialchars($p['nrp']) ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <?php $pFotoUrl = foto_url($p['foto'] ?? ''); ?>
            <?php if ($pFotoUrl): ?>
              <img src="<?= $pFotoUrl ?>" alt="Foto" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid var(--gold);flex-shrink:0;">
            <?php endif; ?>
            <strong><?= htmlspecialchars($p['nama']) ?></strong>
          </div>
        </td>
        <td><?= htmlspecialchars($p['pangkat'] ?? '-') ?></td>

        <td><?= htmlspecialchars($p['satuan'] ?? '-') ?></td>
        <td><?= htmlspecialchars($p['jabatan'] ?? '-') ?></td>
        <td style="min-width:140px;">
          <div class="progress" style="height:10px;"><span style="width:<?= $persen ?>%;"></span></div>
          <span style="font-size:11.5px;color:var(--text-dim);"><?= $persen ?>% (<?= $terisi ?>/<?= $totalWajibDosir ?>)</span>
        </td>
        <td>
          <a href="<?= BASE_URL ?>/admin/personel_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline" style="padding:6px 10px;font-size:12px;">Detail</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Kontrol Paginasi Optimal -->
  <?= render_pagination($page, $totalPages, $totalRecords, $perPage, $_GET, [10, 25, 50, 100]) ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
