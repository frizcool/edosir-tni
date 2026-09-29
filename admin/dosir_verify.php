<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/watermark.php';
require_admin();

$admin = current_user();

// Parameter Filter & Paginasi
$status = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'pending';
}

$q         = trim($_GET['q'] ?? '');
$satuan    = trim($_GET['satuan'] ?? '');
$dosirKode = trim($_GET['dosir_kode'] ?? '');
$perPage   = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page      = max(1, (int)($_GET['page'] ?? 1));

// Hitung rekap jumlah status untuk tab navigasi
$countsRaw = $pdo->query("SELECT status, COUNT(*) as total FROM dosir_files GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$countPending  = (int)($countsRaw['pending'] ?? 0);
$countApproved = (int)($countsRaw['approved'] ?? 0);
$countRejected = (int)($countsRaw['rejected'] ?? 0);
$countAll      = $countPending + $countApproved + $countRejected;

// Query pembangun filter dinamis
$where = " WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $where .= " AND f.status = ?";
    $params[] = $status;
}

if ($q !== '') {
    $where .= " AND (p.nama LIKE ? OR p.nrp LIKE ? OR f.file_name LIKE ? OR f.original_name LIKE ? OR m.nama_dosir LIKE ?)";
    $likeQ = "%$q%";
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
}

if ($satuan !== '') {
    $where .= " AND ms.nama = ?";
    $params[] = $satuan;
}

if ($dosirKode !== '') {
    $where .= " AND f.dosir_kode = ?";
    $params[] = $dosirKode;
}

// 1. Hitung total rekord sesuai filter
$countSql = "
    SELECT COUNT(*) 
    FROM dosir_files f
    JOIN personel p ON p.id = f.personel_id
    LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
    JOIN dosir_master m ON m.kode = f.dosir_kode
    $where
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = (int)$stmtCount->fetchColumn();

$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// 2. Ambil data dengan LIMIT & OFFSET paginasi
$sql = "
    SELECT f.*, p.nama, p.nrp, mp.singkatan as pangkat, ms.nama as satuan, m.nama_dosir,
           u_ver.username as verifikator_username,
           p_ver.nama as verifikator_nama
    FROM dosir_files f
    JOIN personel p ON p.id = f.personel_id
    LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
    LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
    JOIN dosir_master m ON m.kode = f.dosir_kode
    LEFT JOIN users u_ver ON u_ver.id = f.verified_by
    LEFT JOIN personel p_ver ON p_ver.id = u_ver.personel_id
    $where
    ORDER BY " . ($status === 'pending' ? "f.uploaded_at ASC" : "f.verified_at DESC, f.uploaded_at DESC") . "
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data opsi dropdown
$satuanOptions = $pdo->query("SELECT DISTINCT ms.nama FROM master_satuan ms JOIN personel p ON p.satuan_id = ms.id ORDER BY ms.nama ASC")->fetchAll(PDO::FETCH_COLUMN);
$dosirMasterList = $pdo->query("SELECT kode, nama_dosir FROM dosir_master ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Verifikasi Dokumen Dosir';
include __DIR__ . '/../includes/header.php';
?>

<div class="command-banner" style="margin-bottom:16px;">
  <div>
    <div style="font-size:11px;color:var(--gold);letter-spacing:1.5px;font-weight:700;">PUSAT KENDALI VERIFIKASI DOKUMEN &amp; TTE</div>
    <h2 style="margin:2px 0 0;font-size:20px;">Verifikasi &amp; Pengesahan Digital Berkas Dosir</h2>
  </div>
</div>

<!-- Navigasi Tab Status -->
<div class="card" style="margin-bottom:16px;padding:12px 16px;">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="?status=pending<?= $q ? '&q=' . urlencode($q) : '' ?><?= $satuan ? '&satuan=' . urlencode($satuan) : '' ?>" class="btn <?= $status === 'pending' ? '' : 'btn-outline' ?>" style="font-size:12.5px;padding:6px 14px;">
        ⏳ Menunggu Verifikasi <span style="background:var(--warn);color:#121810;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;margin-left:4px;"><?= $countPending ?></span>
      </a>
      <a href="?status=approved<?= $q ? '&q=' . urlencode($q) : '' ?><?= $satuan ? '&satuan=' . urlencode($satuan) : '' ?>" class="btn <?= $status === 'approved' ? '' : 'btn-outline' ?>" style="font-size:12.5px;padding:6px 14px;">
        ✓ Disetujui (TTE Sah) <span style="background:var(--ok);color:#fff;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;margin-left:4px;"><?= $countApproved ?></span>
      </a>
      <a href="?status=rejected<?= $q ? '&q=' . urlencode($q) : '' ?><?= $satuan ? '&satuan=' . urlencode($satuan) : '' ?>" class="btn <?= $status === 'rejected' ? '' : 'btn-outline' ?>" style="font-size:12.5px;padding:6px 14px;">
        ✕ Ditolak <span style="background:var(--danger);color:#fff;padding:2px 6px;border-radius:10px;font-size:11px;font-weight:700;margin-left:4px;"><?= $countRejected ?></span>
      </a>
      <a href="?status=all<?= $q ? '&q=' . urlencode($q) : '' ?><?= $satuan ? '&satuan=' . urlencode($satuan) : '' ?>" class="btn <?= $status === 'all' ? '' : 'btn-outline' ?>" style="font-size:12.5px;padding:6px 14px;">
        📂 Semua Berkas <span style="background:var(--panel-2);color:var(--text);padding:2px 6px;border-radius:10px;font-size:11px;border:1px solid var(--border);margin-left:4px;"><?= $countAll ?></span>
      </a>
    </div>

    <div style="font-size:12px;color:var(--text-dim);">
      Status Aktif: <strong style="color:var(--gold);"><?= strtoupper($status) ?></strong>
    </div>
  </div>
</div>

<!-- Panel Pencarian & Filter Canggih -->
<div class="card" style="margin-bottom:16px;">
  <form method="get" action="<?= BASE_URL ?>/admin/dosir_verify.php" style="display:grid;grid-template-columns:minmax(180px,1.5fr) minmax(150px,1fr) minmax(150px,1fr) auto;gap:14px;align-items:end;">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <?php if (isset($_GET['per_page'])): ?>
      <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
    <?php endif; ?>

    <div>
      <label style="font-size:12px;">Cari Personel / Berkas</label>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik nama, NRP, atau nama file...">
    </div>

    <div>
      <label style="font-size:12px;">Kategori Dosir (33 Baku)</label>
      <select name="dosir_kode">
        <option value="">-- Semua Kategori Dosir --</option>
        <?php foreach ($dosirMasterList as $dm): ?>
          <option value="<?= $dm['kode'] ?>" <?= $dosirKode === $dm['kode'] ? 'selected' : '' ?>>
            <?= $dm['kode'] ?> - <?= htmlspecialchars($dm['nama_dosir']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label style="font-size:12px;">Satuan Organik</label>
      <select name="satuan">
        <option value="">-- Semua Satuan --</option>
        <?php foreach ($satuanOptions as $sat): ?>
          <option value="<?= htmlspecialchars($sat) ?>" <?= $satuan === $sat ? 'selected' : '' ?>>
            <?= htmlspecialchars($sat) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display:flex;gap:8px;">
      <button type="submit" class="btn" style="min-width:90px;height:38px;">🔍 Filter</button>
      <?php if ($q !== '' || $satuan !== '' || $dosirKode !== ''): ?>
        <a href="<?= BASE_URL ?>/admin/dosir_verify.php?status=<?= urlencode($status) ?>" class="btn btn-outline" style="height:38px;display:flex;align-items:center;" title="Reset Filter">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ===================================================================== -->
<!-- FORM & BILAH AKSI MASAL (BULK VERIFICATION BAR)                       -->
<!-- ===================================================================== -->
<form id="bulkForm" method="post" action="<?= BASE_URL ?>/admin/dosir_verify_process.php">
  <?= csrf_field() ?>
  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
  <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
  <input type="hidden" name="satuan" value="<?= htmlspecialchars($satuan) ?>">
  <input type="hidden" name="dosir_kode" value="<?= htmlspecialchars($dosirKode) ?>">
  <input type="hidden" name="page" value="<?= (int)$page ?>">
  <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
  <input type="hidden" name="catatan_verifikasi" id="bulkCatatan" value="">
  <input type="hidden" name="action" id="bulkActionInput" value="">

  <!-- Floating Bulk Action Toolbar -->
  <div id="bulkBar" class="bulk-bar">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <span class="bulk-badge">
        <span id="selectedCount">0</span> Berkas Dipilih
      </span>
      <span style="font-size:12.5px;color:var(--text-dim);">
        Pilih aksi serentak untuk seluruh berkas dosir yang telah dicentang:
      </span>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <button type="button" class="btn" style="background:var(--ok);padding:7px 16px;font-size:12.5px;" onclick="confirmBulkApprove(this)">
        ✓ Setujui Massal (TTE)
      </button>
      <button type="button" class="btn btn-danger" style="padding:7px 16px;font-size:12.5px;" onclick="openBulkRejectModal()">
        ✕ Tolak Massal...
      </button>
      <button type="button" class="btn btn-outline" style="padding:7px 14px;font-size:12.5px;color:var(--gold);border-color:var(--gold);" onclick="confirmBulkRestamp()" title="Perbarui Barcode QR Code Berkas Terpilih ke Domain Hosting">
        🔄 Perbarui QR Massal
      </button>
      <button type="button" class="btn btn-outline" style="padding:7px 12px;font-size:12.5px;" onclick="clearSelection()">
        Batal Pilih
      </button>
    </div>
  </div>

  <!-- Tabel Data Berkas Dosir -->
  <div class="card" style="padding:0;overflow:hidden;">
    <div class="table-responsive">
      <table style="width:100%;margin:0;">
        <thead>
          <tr>
            <th style="width:40px;text-align:center;">
              <input type="checkbox" id="selectAllCheckbox" class="chk-box-custom" title="Pilih Semua Berkas di Halaman Ini" onchange="toggleSelectAll(this)">
            </th>
            <th style="min-width:180px;">Personel</th>
            <th style="width:120px;">NRP / NIP</th>
            <th style="min-width:180px;">Jenis Dosir</th>
            <th style="min-width:200px;">Berkas Fisik &amp; Kriptografi</th>
            <th style="width:130px;">Waktu Unggah</th>
            <th style="width:110px;text-align:center;">Status</th>
            <th style="width:160px;text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($list)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:36px;color:var(--text-dim);">
              <div style="font-size:28px;margin-bottom:8px;">📑</div>
              <strong>Tidak ada berkas dosir ditemukan</strong>
              <div style="font-size:12px;margin-top:4px;">Coba ubah kriteria pencarian atau pilih tab status lainnya.</div>
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($list as $row): ?>
          <tr id="row-<?= $row['id'] ?>">
            <td style="text-align:center;">
              <input type="checkbox" name="ids[]" value="<?= $row['id'] ?>" class="chk-box-custom item-checkbox" onchange="updateSelectionState()">
            </td>
            <td>
              <strong><?= htmlspecialchars($row['nama']) ?></strong>
              <div style="font-size:11px;color:var(--text-dim);">
                <?= htmlspecialchars($row['pangkat'] ?: '-') ?> &middot; <?= htmlspecialchars($row['satuan'] ?: '-') ?>
              </div>
            </td>
            <td style="font-family:monospace;font-weight:600;font-size:12px;">
              <?= htmlspecialchars($row['nrp']) ?>
            </td>
            <td>
              <span class="badge" style="background:var(--panel-2);border:1px solid var(--border);color:var(--gold);font-family:monospace;">
                DOSIR <?= $row['dosir_kode'] ?>
              </span>
              <div style="font-size:11.5px;color:var(--text);margin-top:2px;">
                <?= htmlspecialchars($row['nama_dosir']) ?>
              </div>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <a href="<?= dosir_url($row['file_path'], $row['id']) ?>" target="_blank" style="color:var(--accent);font-weight:600;text-decoration:underline;font-size:12px;" title="Buka berkas di tab baru">
                  📄 <?= htmlspecialchars($row['file_name']) ?>
                </a>
              </div>

              <?php if (!empty($row['signature_code'])): ?>
                <div style="margin-top:4px;">
                  <a href="<?= BASE_URL ?>/verify.php?code=<?= urlencode($row['signature_code']) ?>" target="_blank" class="badge" style="background:rgba(76,143,92,0.18);border:1px solid var(--ok);color:var(--ok);font-size:10px;text-decoration:none;" title="Verifikasi Keabsahan TTE">
                    ✓ <?= htmlspecialchars($row['signature_code']) ?>
                  </a>
                </div>
              <?php elseif ($row['status'] === 'approved'): ?>
                <div style="margin-top:3px;">
                  <span class="badge badge-approved" style="font-size:9.5px;">TERVERIFIKASI RESMI</span>
                </div>
              <?php elseif ($row['status'] === 'pending'): ?>
                <div style="margin-top:3px;font-size:10.5px;color:var(--danger);font-weight:600;">
                  ⚠️ Cap Belum Terverifikasi
                </div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-dim);">
              <?= fmt_tgl($row['uploaded_at']) ?>
            </td>
            <td style="text-align:center;">
              <span class="badge badge-<?= $row['status'] ?>" style="font-size:11px;">
                <?= strtoupper($row['status']) ?>
              </span>
            </td>
            <td style="text-align:right;">
              <?php if ($row['status'] === 'pending'): ?>
                <div style="display:inline-flex;gap:4px;">
                  <button type="button" class="btn" style="padding:4px 9px;font-size:11.5px;background:var(--ok);" onclick="singleApprove(<?= $row['id'] ?>)" title="Setujui dan Terbitkan TTE">
                    ✓ Setujui
                  </button>
                  <button type="button" class="btn btn-danger" style="padding:4px 9px;font-size:11.5px;" onclick="singleReject(<?= $row['id'] ?>)" title="Tolak Berkas">
                    ✕ Tolak
                  </button>
                </div>
              <?php else: ?>
                <div style="font-size:11.5px;color:var(--text-dim);line-height:1.3;">
                  <?php if ($row['catatan_verifikasi']): ?>
                    <span title="<?= htmlspecialchars($row['catatan_verifikasi']) ?>" style="cursor:help;">
                      💬 <?= htmlspecialchars(mb_strimwidth($row['catatan_verifikasi'], 0, 22, '...')) ?>
                    </span>
                  <?php else: ?>
                    &ndash;
                  <?php endif; ?>
                  <?php if ($row['verifikator_username']): ?>
                    <div style="font-size:10px;color:var(--text-dim);">Oleh: <?= htmlspecialchars($row['verifikator_nama'] ?: $row['verifikator_username']) ?></div>
                  <?php endif; ?>
                  <?php if ($row['status'] === 'approved'): ?>
                    <div style="margin-top:4px;">
                      <button type="button" class="btn btn-outline" style="padding:2px 7px;font-size:10px;" onclick="singleRestamp(<?= $row['id'] ?>)" title="Stempel ulang barcode QR Code ke domain hosting">
                        🔄 Perbarui QR
                      </button>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginasi Optimal & Terpadu -->
    <div style="padding:0 16px 14px;">
      <?= render_pagination($page, $totalPages, $totalRecords, $perPage, $_GET, [15, 25, 50, 100]) ?>
    </div>
  </div>
</form>

<!-- Single Action Form (Terpisah agar aman dari manipulasi) -->
<form id="singleActionForm" method="post" action="<?= BASE_URL ?>/admin/dosir_verify_process.php" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="singleId">
  <input type="hidden" name="action" id="singleAction">
  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
  <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
  <input type="hidden" name="satuan" value="<?= htmlspecialchars($satuan) ?>">
  <input type="hidden" name="dosir_kode" value="<?= htmlspecialchars($dosirKode) ?>">
  <input type="hidden" name="page" value="<?= (int)$page ?>">
  <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
  <input type="hidden" name="catatan_verifikasi" id="singleCatatan">
</form>

<!-- Modal Input Alasan Penolakan Massal -->
<div id="bulkRejectModal" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:9999;align-items:center;justify-content:center;">
  <div class="card" style="width:100%;max-width:480px;background:var(--panel);border:1px solid var(--danger);box-shadow:0 10px 30px rgba(0,0,0,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="margin:0;color:var(--danger);font-size:16px;">✕ Tolak Berkas Dosir Massal</h3>
      <button type="button" class="btn-ghost" style="font-size:18px;cursor:pointer;" onclick="closeBulkRejectModal()">&times;</button>
    </div>
    <p style="font-size:13px;color:var(--text);margin-top:0;">
      Anda akan menolak <strong id="modalRejectCount" style="color:var(--gold);">0</strong> berkas dosir terpilih secara serentak. Masukkan catatan atau alasan penolakan agar personel dapat memperbaikinya:
    </p>
    <div style="margin-bottom:16px;">
      <label style="font-size:12px;font-weight:600;">Catatan / Alasan Penolakan Dinas *</label>
      <textarea id="modalRejectNote" rows="3" placeholder="Contoh: Hasil pindaian buram/miring, stempel tidak terbaca, berkas bukan SK asli..." style="width:100%;padding:10px;font-size:13px;border-radius:6px;border:1px solid var(--border);background:var(--panel-2);color:var(--text);"></textarea>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px;">
      <button type="button" class="btn btn-outline" onclick="closeBulkRejectModal()">Batal</button>
      <button type="button" class="btn btn-danger" onclick="submitBulkReject()">Tolak Seluruh Berkas</button>
    </div>
  </div>
</div>

<script>
// Logika Manajemen Checkbox & Bilah Aksi Massal
function updateSelectionState() {
  const checkboxes = document.querySelectorAll('.item-checkbox:checked');
  const count = checkboxes.length;
  const bulkBar = document.getElementById('bulkBar');
  const countSpan = document.getElementById('selectedCount');
  const selectAll = document.getElementById('selectAllCheckbox');

  countSpan.textContent = count;

  if (count > 0) {
    bulkBar.classList.add('active');
  } else {
    bulkBar.classList.remove('active');
  }

  const allItems = document.querySelectorAll('.item-checkbox');
  if (allItems.length > 0) {
    selectAll.checked = (count === allItems.length);
    selectAll.indeterminate = (count > 0 && count < allItems.length);
  }
}

function toggleSelectAll(masterCheckbox) {
  const allItems = document.querySelectorAll('.item-checkbox');
  allItems.forEach(cb => {
    cb.checked = masterCheckbox.checked;
  });
  updateSelectionState();
}

function clearSelection() {
  const allItems = document.querySelectorAll('.item-checkbox');
  allItems.forEach(cb => { cb.checked = false; });
  const selectAll = document.getElementById('selectAllCheckbox');
  if (selectAll) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  }
  updateSelectionState();
}

// Konfirmasi Verifikasi / Persetujuan Massal
function confirmBulkApprove(btn) {
  const checked = document.querySelectorAll('.item-checkbox:checked');
  if (checked.length === 0) {
    alert('Pilih minimal satu berkas dosir terlebih dahulu.');
    return;
  }

  const msg = `Apakah Anda yakin ingin MEMVERIFIKASI & MENANDATANGANI (TTE) ${checked.length} berkas dosir terpilih secara serentak?\n\nSistem akan secara otomatis:\n1. Mengambil berkas master bersih.\n2. Mengkalkulasi nilai hash SHA-256.\n3. Menerbitkan kode registrasi TTE resmi.\n4. Membubuhkan stempel digital QR Code ke berkas PDF.`;
  
  if (confirm(msg)) {
    document.getElementById('bulkActionInput').value = 'bulk_approve';
    
    // Tampilkan indikator proses
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '⏳ Memproses TTE...';
    }
    
    document.getElementById('bulkForm').submit();
  }
}

// Modal Penolakan Massal
function openBulkRejectModal() {
  const checked = document.querySelectorAll('.item-checkbox:checked');
  if (checked.length === 0) {
    alert('Pilih minimal satu berkas dosir terlebih dahulu.');
    return;
  }
  document.getElementById('modalRejectCount').textContent = checked.length;
  document.getElementById('modalRejectNote').value = '';
  document.getElementById('bulkRejectModal').style.display = 'flex';
}

function closeBulkRejectModal() {
  document.getElementById('bulkRejectModal').style.display = 'none';
}

function submitBulkReject() {
  const note = document.getElementById('modalRejectNote').value.trim();
  if (note === '') {
    alert('Wajib memasukkan alasan / catatan penolakan.');
    document.getElementById('modalRejectNote').focus();
    return;
  }

  document.getElementById('bulkCatatan').value = note;
  document.getElementById('bulkActionInput').value = 'bulk_reject';
  closeBulkRejectModal();
  document.getElementById('bulkForm').submit();
}

// Aksi Tunggal (Row-level actions)
function singleApprove(id) {
  if (!confirm('Setujui berkas ini dan terbitkan Tanda Tangan Elektronik (TTE) resmi?')) {
    return;
  }
  document.getElementById('singleId').value = id;
  document.getElementById('singleAction').value = 'approved';
  document.getElementById('singleCatatan').value = '';
  document.getElementById('singleActionForm').submit();
}

function singleReject(id) {
  const note = prompt('Masukkan catatan / alasan penolakan berkas:');
  if (note === null) return;
  if (note.trim() === '') {
    alert('Alasan penolakan tidak boleh kosong.');
    return;
  }
  document.getElementById('singleId').value = id;
  document.getElementById('singleAction').value = 'rejected';
  document.getElementById('singleCatatan').value = note.trim();
  document.getElementById('singleActionForm').submit();
}

function singleRestamp(id) {
  if (!confirm('Perbarui barcode QR Code dokumen ini agar mengarah ke domain hosting?')) return;
  document.getElementById('singleId').value = id;
  document.getElementById('singleAction').value = 'restamp';
  document.getElementById('singleCatatan').value = '';
  document.getElementById('singleActionForm').submit();
}

function confirmBulkRestamp() {
  const checked = document.querySelectorAll('.row-select-chk:checked');
  if (checked.length === 0) {
    alert('Pilih minimal satu berkas dosir terlebih dahulu.');
    return;
  }
  if (!confirm('Perbarui dan stempel ulang barcode QR Code pada ' + checked.length + ' berkas terpilih agar mengarah ke domain hosting?')) {
    return;
  }
  document.getElementById('bulkActionInput').value = 'bulk_restamp';
  document.getElementById('bulkForm').submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
