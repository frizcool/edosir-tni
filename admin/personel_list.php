<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$q = trim($_GET['q'] ?? '');
$satuan = trim($_GET['satuan'] ?? '');

$totalWajibDosir = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn() ?: 33;

// Hitung total rekord untuk paginasi
$countSql = "SELECT COUNT(DISTINCT p.id) FROM personel p LEFT JOIN master_satuan ms ON ms.id = p.satuan_id WHERE 1=1";
$countParams = [];
if ($q !== '') {
    $countSql .= " AND (p.nama LIKE ? OR p.nrp LIKE ?)";
    $countParams[] = "%$q%"; $countParams[] = "%$q%";
}
if ($satuan !== '') {
    $countSql .= " AND ms.nama = ?";
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
           mp.singkatan as pangkat,
           mp.golongan,
           ms.nama as satuan,
           COUNT(DISTINCT CASE WHEN f.status='approved' AND m.wajib=1 THEN f.dosir_kode END) as terisi_dosir
    FROM personel p
    LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
    LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
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
    $sql .= " AND ms.nama = ?";
    $params[] = $satuan;
}
$sql .= " GROUP BY p.id, mp.singkatan, mp.golongan, ms.nama ORDER BY p.nama ASC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$satuanOptions = $pdo->query("SELECT DISTINCT ms.nama FROM master_satuan ms JOIN personel p ON p.satuan_id = ms.id ORDER BY ms.nama")->fetchAll(PDO::FETCH_COLUMN);

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
    <strong>Menampilkan <?= count($list) ?> dari total <?= number_format($totalRecords, 0, ',', '.') ?> personel</strong>
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
          <div style="display:inline-flex;gap:6px;align-items:center;">
            <a href="<?= BASE_URL ?>/admin/personel_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline" style="padding:5px 9px;font-size:12px;" title="Lihat Profil & Dosir">👁️ Detail</a>
            <button type="button" class="btn btn-outline btn-danger" style="padding:5px 9px;font-size:12px;"
                    title="Hapus Personel dan Seluruh Berkas Terkait"
                    onclick="openDeleteModal(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($p['nrp'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['nama'], ENT_QUOTES) ?>', <?= (int)$terisi ?>)">
              🗑️ Hapus
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Kontrol Paginasi Optimal -->
  <?= render_pagination($page, $totalPages, $totalRecords, $perPage, $_GET, [10, 25, 50, 100]) ?>
</div>

<!-- Modal Konfirmasi Hapus Personel Kaskade -->
<div class="modal-backdrop" id="modalDeletePersonel">
  <div class="modal-dialog" style="max-width:520px;">
    <div class="modal-header" style="border-bottom:1px solid rgba(220,53,69,0.3);background:rgba(220,53,69,0.08);">
      <h3 class="modal-title" style="color:var(--danger);display:flex;align-items:center;gap:8px;">
        ⚠️ Konfirmasi Hapus Personel
      </h3>
      <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
    </div>
    <form method="post" action="<?= BASE_URL ?>/admin/personel_delete.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="delPersonelId" value="">
      <input type="hidden" name="redirect_to" value="/admin/personel_list.php<?= $q !== '' || $satuan !== '' || $page > 1 ? '?' . htmlspecialchars(http_build_query($_GET)) : '' ?>">
      <div class="modal-body">
        <div style="background:rgba(220,53,69,0.1);border-left:4px solid var(--danger);padding:12px 16px;border-radius:4px;margin-bottom:16px;">
          <strong style="color:var(--danger);display:block;margin-bottom:4px;">TINDAKAN INI BERSIFAT PERMANEN & TIDAK DAPAT DIBATALKAN!</strong>
          <span style="font-size:13px;color:var(--text-dim);">
            Menghapus personel ini akan menghapus <strong>seluruh data terkait secara otomatis</strong> dari sistem pangkalan data dan server penyimpanan fisik.
          </span>
        </div>

        <div style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px;">
          <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Personel yang akan dihapus:</div>
          <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:2px;" id="delPersonelNama">-</div>
          <div style="font-size:13px;font-family:monospace;color:var(--gold);" id="delPersonelNrp">-</div>
        </div>

        <div style="font-size:13px;color:var(--text-dim);line-height:1.6;">
          <strong>Data yang akan ikut terhapus tuntas:</strong>
          <ul style="margin:8px 0 0 18px;padding:0;">
            <li>Seluruh berkas dokumen dosir digital (PDF aktif & master warkat raw di server) <span id="delPersonelFiles" class="badge badge-warning" style="font-size:11px;margin-left:4px;"></span></li>
            <li>Pas foto profil prajurit di media penyimpanan server</li>
            <li>Akun akses pengguna (users) & riwayat upaya login prajurit</li>
            <li>Data riwayat dinas, jabatan, dan kelengkapan personel</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Batal</button>
        <button type="submit" class="btn btn-danger" style="display:flex;align-items:center;gap:6px;">
          🗑️ Ya, Hapus Personel & Seluruh Dokumen
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteModal(id, nrp, nama, files) {
  document.getElementById('delPersonelId').value = id;
  document.getElementById('delPersonelNama').textContent = nama;
  document.getElementById('delPersonelNrp').textContent = 'NRP: ' + nrp;
  document.getElementById('delPersonelFiles').textContent = files + ' Dokumen Terisi';
  var modal = document.getElementById('modalDeletePersonel');
  if (modal) modal.classList.add('show');
}
function closeDeleteModal() {
  var modal = document.getElementById('modalDeletePersonel');
  if (modal) modal.classList.remove('show');
}
document.addEventListener('click', function(e) {
  if (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) {
    closeDeleteModal();
  }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDeleteModal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

