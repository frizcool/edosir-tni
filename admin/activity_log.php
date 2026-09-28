<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$aktivitasFilter = trim($_GET['aktivitas'] ?? '');
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(10, min(100, (int)($_GET['per_page'] ?? 30)));
$offset = ($page - 1) * $limit;

$sqlWhere = " WHERE 1=1";
$params = [];

if ($aktivitasFilter !== '') {
    $sqlWhere .= " AND l.aktivitas = ?";
    $params[] = $aktivitasFilter;
}

if ($q !== '') {
    $sqlWhere .= " AND (l.keterangan LIKE ? OR l.ip_address LIKE ? OR u.username LIKE ? OR p.nama LIKE ? OR p.nrp LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// Count total
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM activity_log l
    LEFT JOIN users u ON u.id = l.user_id
    LEFT JOIN personel p ON p.id = u.personel_id
    $sqlWhere
");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch data
$sql = "
    SELECT l.*, u.username, u.role, p.nama, p.nrp
    FROM activity_log l
    LEFT JOIN users u ON u.id = l.user_id
    LEFT JOIN personel p ON p.id = u.personel_id
    $sqlWhere
    ORDER BY l.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct activities for filter dropdown
$distinctAktivitas = $pdo->query("SELECT DISTINCT aktivitas FROM activity_log WHERE aktivitas IS NOT NULL AND aktivitas != '' ORDER BY aktivitas")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Log Aktivitas Sistem';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-bottom:18px;">
  <form method="get" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) auto;gap:16px;align-items:end;">
    <div>
      <label>Filter Jenis Aktivitas</label>
      <select name="aktivitas">
        <option value="">-- Semua Jenis Aktivitas --</option>
        <?php foreach ($distinctAktivitas as $act): ?>
          <option value="<?= htmlspecialchars($act) ?>" <?= $aktivitasFilter === $act ? 'selected' : '' ?>>
            <?= htmlspecialchars($act) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label>Pencarian (User / Keterangan / IP)</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama, NRP, IP, dsb...">
    </div>

    <div>
      <label class="label-spacer">&nbsp;</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <button type="submit" class="btn" style="min-width:120px;">Terapkan Filter</button>
        <?php if ($aktivitasFilter !== '' || $q !== ''): ?>
          <a href="<?= BASE_URL ?>/admin/activity_log.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
    <div>
      <h3 style="margin:0;">Audit Trail Sistem (<?= number_format($totalRows) ?> catatan)</h3>
      <div style="color:var(--text-dim);font-size:12.5px;margin-top:2px;">
        Merekam seluruh aktivitas penting (login, pendaftaran, unggah dosir, verifikasi, dsb).
      </div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:170px;">Waktu Kejadian</th>
        <th style="width:150px;">Aktivitas</th>
        <th>Pengguna / Personel</th>
        <th>Keterangan</th>
        <th style="width:120px;">Alamat IP</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-dim);padding:24px;">Tidak ada log aktivitas yang sesuai.</td></tr>
      <?php endif; ?>
      <?php foreach ($logs as $row): 
        $badgeClass = 'badge-pending';
        if (strpos($row['aktivitas'], 'LOGIN') !== false || strpos($row['aktivitas'], 'APPROVAL') !== false || strpos($row['aktivitas'], 'VERIFIKASI') !== false) {
            $badgeClass = 'badge-approved';
        } elseif (strpos($row['aktivitas'], 'REJECT') !== false || strpos($row['aktivitas'], 'NONAKTIF') !== false) {
            $badgeClass = 'badge-rejected';
        }
      ?>
      <tr>
        <td style="font-size:12.5px;color:var(--text-dim);">
          <?= date('d-m-Y H:i:s', strtotime($row['created_at'])) ?>
        </td>
        <td>
          <span class="badge <?= $badgeClass ?>" style="font-size:11px;">
            <?= htmlspecialchars($row['aktivitas']) ?>
          </span>
        </td>
        <td>
          <?php if (!empty($row['nama'])): ?>
            <strong><?= htmlspecialchars($row['nama']) ?></strong>
            <div style="font-size:11.5px;font-family:monospace;color:var(--gold);">NRP: <?= htmlspecialchars($row['nrp'] ?? $row['username']) ?></div>
          <?php elseif (!empty($row['username'])): ?>
            <strong><?= htmlspecialchars($row['username']) ?></strong>
            <?php if (!empty($row['role'])): ?><span style="font-size:11px;color:var(--text-dim);">(<?= htmlspecialchars($row['role']) ?>)</span><?php endif; ?>
          <?php else: ?>
            <span style="color:var(--text-dim);font-size:12px;">Sistem / Tamu</span>
          <?php endif; ?>
        </td>
        <td style="font-size:13px;line-height:1.4;">
          <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
        </td>
        <td style="font-family:monospace;font-size:12px;color:var(--text-dim);">
          <?= htmlspecialchars($row['ip_address'] ?: '127.0.0.1') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?= render_pagination($page, $totalPages, $totalRows, $limit, $_GET, [15, 30, 50, 100]) ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
