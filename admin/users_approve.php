<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');

    if ($id > 0) {
        if (in_array($action, ['approved', 'rejected', 'nonaktif'], true)) {
            $stmt = $pdo->prepare("UPDATE users SET status=?, catatan_approval=? WHERE id=? AND role='personel'");
            $stmt->execute([$action, $catatan, $id]);
            log_activity($pdo, $admin['id'], 'APPROVAL_USER', "User #$id diubah menjadi: $action");
            set_flash('success', 'Status akun personel berhasil diperbarui menjadi ' . strtoupper($action) . '.');
        } elseif ($action === 'reset_password') {
            // Ambil data user dan NRP
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, p.nrp 
                FROM users u LEFT JOIN personel p ON p.id = u.personel_id 
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $uRow = $stmt->fetch();
            if ($uRow) {
                $targetNrp = $uRow['nrp'] ?: $uRow['username'];
                reset_user_password_to_nrp($pdo, $id, $targetNrp);
                log_activity($pdo, $admin['id'], 'RESET_PASSWORD', "Reset password user #$id ke NRP ($targetNrp)");
                set_flash('success', "Kata sandi user '{$uRow['username']}' berhasil di-reset kembali ke NRP default ($targetNrp).");
            }
        }
    }
    redirect('/admin/users_approve.php');
}

// Filter status untuk daftar seluruh akun
$filterStatus = $_GET['status'] ?? 'all';

// Query akun pending
$pending = $pdo->query("
    SELECT u.*, p.nama, p.nrp, p.pangkat, p.satuan
    FROM users u LEFT JOIN personel p ON p.id = u.personel_id
    WHERE u.status = 'pending' AND u.role = 'personel'
    ORDER BY u.created_at ASC
")->fetchAll();

// Query seluruh akun personel
$sqlAll = "
    SELECT u.*, p.nama, p.nrp, p.pangkat, p.satuan, p.id as p_id
    FROM users u LEFT JOIN personel p ON p.id = u.personel_id
    WHERE u.role = 'personel'
";
$paramsAll = [];
if ($filterStatus !== 'all' && in_array($filterStatus, ['approved','pending','rejected','nonaktif'], true)) {
    $sqlAll .= " AND u.status = ?";
    $paramsAll[] = $filterStatus;
}
$sqlAll .= " ORDER BY u.updated_at DESC";
$stmtAll = $pdo->prepare($sqlAll);
$stmtAll->execute($paramsAll);
$allUsers = $stmtAll->fetchAll();

$counts = $pdo->query("
    SELECT status, COUNT(*) as total 
    FROM users WHERE role='personel' 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Manajemen & Approval Akun Personel';
include __DIR__ . '/../includes/header.php';
?>

<!-- Bagian 1: Akun Menunggu Verifikasi -->
<div class="card" style="margin-bottom:24px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <div>
      <h3 style="margin:0;">Menunggu Verifikasi (<?= count($pending) ?>)</h3>
      <div style="color:var(--text-dim);font-size:13px;margin-top:3px;">
        Akun baru hasil registrasi personel yang belum dapat login sebelum Anda verifikasi.
      </div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Username (NRP)</th>
        <th>Nama Lengkap</th>
        <th>Pangkat</th>
        <th>Satuan</th>
        <th>Tanggal Daftar</th>
        <th style="text-align:center;">Aksi Verifikasi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pending)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:20px;">Tidak ada akun yang menunggu verifikasi saat ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($pending as $row): ?>
      <tr>
        <td style="font-family:monospace;font-weight:600;"><?= htmlspecialchars($row['username']) ?></td>
        <td><strong><?= htmlspecialchars($row['nama'] ?? '-') ?></strong></td>
        <td><?= htmlspecialchars($row['pangkat'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
        <td><?= fmt_tgl($row['created_at']) ?></td>
        <td style="text-align:center;">
          <form method="post" style="display:inline-flex;gap:6px;">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
            <button name="action" value="approved" class="btn" style="padding:6px 12px;font-size:12px;background:var(--ok);" onclick="return confirm('Verifikasi dan aktifkan akun ini agar personel dapat login?');">
              ✓ Setujui (Verifikasi)
            </button>
            <button name="action" value="rejected" class="btn btn-danger" style="padding:6px 12px;font-size:12px;" onclick="return confirm('Tolak registrasi akun ini?');">
              ✕ Tolak
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Bagian 2: Seluruh Akun Personel & Kontrol Admin -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
    <div>
      <h3 style="margin:0;">Kontrol Seluruh Akun Personel</h3>
      <div style="color:var(--text-dim);font-size:13px;margin-top:2px;">
        Total: <?= array_sum($counts) ?> akun (Approved: <?= $counts['approved'] ?? 0 ?>, Pending: <?= $counts['pending'] ?? 0 ?>, Nonaktif: <?= $counts['nonaktif'] ?? 0 ?>, Ditolak: <?= $counts['rejected'] ?? 0 ?>)
      </div>
    </div>
    
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <a href="?status=all" class="btn <?= $filterStatus==='all'?'':'btn-outline' ?>" style="padding:6px 12px;font-size:12px;">Semua</a>
      <a href="?status=approved" class="btn <?= $filterStatus==='approved'?'':'btn-outline' ?>" style="padding:6px 12px;font-size:12px;">Approved (<?= $counts['approved'] ?? 0 ?>)</a>
      <a href="?status=pending" class="btn <?= $filterStatus==='pending'?'':'btn-outline' ?>" style="padding:6px 12px;font-size:12px;">Pending (<?= $counts['pending'] ?? 0 ?>)</a>
      <a href="?status=nonaktif" class="btn <?= $filterStatus==='nonaktif'?'':'btn-outline' ?>" style="padding:6px 12px;font-size:12px;">Nonaktif (<?= $counts['nonaktif'] ?? 0 ?>)</a>
      <a href="?status=rejected" class="btn <?= $filterStatus==='rejected'?'':'btn-outline' ?>" style="padding:6px 12px;font-size:12px;">Ditolak (<?= $counts['rejected'] ?? 0 ?>)</a>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Username (NRP)</th>
        <th>Nama Personel</th>
        <th>Pangkat / Satuan</th>
        <th>Status Akun</th>
        <th>Login Terakhir</th>
        <th style="text-align:right;">Kontrol Akun</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($allUsers)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:18px;">Tidak ada akun dengan status ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($allUsers as $row): ?>
      <tr>
        <td style="font-family:monospace;font-weight:600;">
          <?php if (!empty($row['p_id'])): ?>
            <a href="<?= BASE_URL ?>/admin/personel_detail.php?id=<?= $row['p_id'] ?>" style="color:var(--gold);text-decoration:underline;">
              <?= htmlspecialchars($row['username']) ?>
            </a>
          <?php else: ?>
            <?= htmlspecialchars($row['username']) ?>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($row['nama'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['pangkat'] ?? '-') ?> &middot; <?= htmlspecialchars($row['satuan'] ?? '-') ?></td>
        <td>
          <span class="badge badge-<?= $row['status'] ?>">
            <?= strtoupper($row['status']) ?>
          </span>
        </td>
        <td><?= $row['last_login'] ? fmt_tgl($row['last_login']) . ' ' . date('H:i', strtotime($row['last_login'])) : '<span style="color:var(--text-dim);font-size:12px;">Belum pernah</span>' ?></td>
        <td style="text-align:right;">
          <form method="post" style="display:inline-flex;gap:4px;justify-content:flex-end;">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $row['id'] ?>">

            <?php if ($row['status'] !== 'approved'): ?>
              <button name="action" value="approved" class="btn" style="padding:5px 9px;font-size:11.5px;background:var(--ok);" title="Verifikasi / Aktifkan akun agar bisa login" onclick="return confirm('Verifikasi & aktifkan akun <?= htmlspecialchars($row['username']) ?>?');">
                ✓ Verifikasi
              </button>
            <?php endif; ?>

            <?php if ($row['status'] === 'approved'): ?>
              <button name="action" value="nonaktif" class="btn btn-outline" style="padding:5px 9px;font-size:11.5px;" title="Nonaktifkan akun sementara" onclick="return confirm('Nonaktifkan akun <?= htmlspecialchars($row['username']) ?>? Personel tidak akan bisa login.');">
                Nonaktifkan
              </button>
            <?php endif; ?>

            <?php if ($row['status'] === 'nonaktif'): ?>
              <button name="action" value="approved" class="btn" style="padding:5px 9px;font-size:11.5px;" onclick="return confirm('Aktifkan kembali akun <?= htmlspecialchars($row['username']) ?>?');">
                Aktifkan
              </button>
            <?php endif; ?>

            <button name="action" value="reset_password" class="btn btn-outline" style="padding:5px 9px;font-size:11.5px;" title="Reset password ke NRP default" onclick="return confirm('Reset kata sandi akun <?= htmlspecialchars($row['username']) ?> kembali ke NRP default?');">
              Reset Sandi
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
