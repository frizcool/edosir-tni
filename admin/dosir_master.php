<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$msg = null;
$error = null;

// Edit atau Tambah Master Dosir
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $kode = trim($_POST['kode'] ?? '');
        $namaDosir = trim($_POST['nama_dosir'] ?? '');
        $wajib = isset($_POST['wajib']) ? 1 : 0;
        $urutan = (int)($_POST['urutan'] ?? 0);

        if ($kode === '' || $namaDosir === '') {
            $error = 'Kode dan nama jenis dosir tidak boleh kosong.';
        } else {
            $stmt = $pdo->prepare("UPDATE dosir_master SET nama_dosir=?, wajib=?, urutan=? WHERE kode=?");
            $stmt->execute([$namaDosir, $wajib, $urutan, $kode]);
            log_activity($pdo, $admin['id'], 'UPDATE_MASTER_DOSIR', "Memperbarui master dosir #$kode: $namaDosir");
            set_flash('success', "Data Master Dosir [$kode] berhasil diperbarui.");
            redirect('/admin/dosir_master.php');
        }
    } elseif ($action === 'create') {
        $kode = trim($_POST['kode'] ?? '');
        $namaDosir = trim($_POST['nama_dosir'] ?? '');
        $wajib = isset($_POST['wajib']) ? 1 : 0;
        $urutan = (int)($_POST['urutan'] ?? 99);

        // Format kode 2 digit
        $kodePad = str_pad($kode, 2, '0', STR_PAD_LEFT);

        if ($kodePad === '' || $namaDosir === '') {
            $error = 'Kode dan nama jenis dosir wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM dosir_master WHERE kode=?");
            $chk->execute([$kodePad]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode dosir '$kodePad' sudah ada di database.";
            } else {
                $ins = $pdo->prepare("INSERT INTO dosir_master (kode, nama_dosir, urutan, wajib) VALUES (?,?,?,?)");
                $ins->execute([$kodePad, $namaDosir, $urutan, $wajib]);
                log_activity($pdo, $admin['id'], 'CREATE_MASTER_DOSIR', "Menambah master dosir #$kodePad: $namaDosir");
                set_flash('success', "Jenis dosir baru [$kodePad] berhasil ditambahkan.");
                redirect('/admin/dosir_master.php');
            }
        }
    }
}

// Ambil daftar master dosir beserta statistik berkas terunggah
$stmtList = $pdo->query("
    SELECT m.*, 
           COUNT(f.id) as total_file,
           COUNT(CASE WHEN f.status='approved' THEN 1 END) as total_sah,
           COUNT(CASE WHEN f.status='pending' THEN 1 END) as total_pending
    FROM dosir_master m
    LEFT JOIN dosir_files f ON f.dosir_kode = m.kode
    GROUP BY m.kode, m.nama_dosir, m.urutan, m.wajib
    ORDER BY m.urutan ASC, m.kode ASC
");
$masterList = $stmtList->fetchAll();

$pageTitle = 'Master Data Dosir';
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
  <div>
    <h2 style="margin:0;"><?= htmlspecialchars($pageTitle) ?></h2>
    <div style="color:var(--text-dim);font-size:13px;margin-top:2px;">
      Daftar 33 standar dokumen dosir militer dan pengelolaan kategori dokumen arsip personel.
    </div>
  </div>
  <button type="button" class="btn" onclick="document.getElementById('modalAdd').style.display='flex';" style="font-size:12.5px;">
    + Tambah Kategori Dosir
  </button>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
    <strong>Total: <?= count($masterList) ?> Kategori Dosir</strong>
    <span style="font-size:12px;color:var(--text-dim);">Folder Penyimpanan: <code>uploads/FOLDER {kode}/</code></span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:60px;">Urutan</th>
        <th style="width:70px;">Kode</th>
        <th>Nama Jenis Dokumen Dosir</th>
        <th style="width:100px;">Sifat</th>
        <th style="width:130px;text-align:center;">Statistik Arsip</th>
        <th style="width:100px;text-align:right;">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($masterList as $row): ?>
      <tr>
        <td style="font-family:monospace;color:var(--text-dim);"><?= str_pad($row['urutan'], 2, '0', STR_PAD_LEFT) ?></td>
        <td>
          <span class="badge" style="background:var(--panel-2);border:1px solid var(--border);font-family:monospace;font-weight:700;">
            <?= htmlspecialchars($row['kode']) ?>
          </span>
        </td>
        <td>
          <strong><?= htmlspecialchars($row['nama_dosir']) ?></strong>
          <div style="font-size:11px;color:var(--text-dim);margin-top:2px;">Folder: <?= folder_dosir($row['kode']) ?></div>
        </td>
        <td>
          <?php if ($row['wajib']): ?>
            <span class="badge badge-approved" style="font-size:11px;">Wajib</span>
          <?php else: ?>
            <span class="badge" style="font-size:11px;background:var(--panel-2);color:var(--text-dim);">Opsional</span>
          <?php endif; ?>
        </td>
        <td style="text-align:center;font-size:12px;">
          <strong><?= $row['total_file'] ?></strong> berkas
          <div style="font-size:11px;color:var(--ok);"><?= $row['total_sah'] ?> sah &middot; <?= $row['total_pending'] ?> pending</div>
        </td>
        <td style="text-align:right;">
          <button type="button" class="btn btn-outline" style="padding:4px 10px;font-size:11.5px;"
                  onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)">
            ✏️ Edit
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal Edit Master Dosir -->
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);align-items:center;justify-content:center;z-index:999;backdrop-filter:blur(3px);">
  <div class="card" style="width:500px;max-width:90%;">
    <h3 style="margin-top:0;">Edit Kategori Dosir</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="kode" id="edit_kode">

      <div style="margin-bottom:12px;">
        <label>Kode Dokumen</label>
        <input type="text" id="edit_kode_display" disabled style="background:var(--panel-2);font-family:monospace;">
      </div>

      <div style="margin-bottom:12px;">
        <label>Nama Jenis Dokumen Dosir *</label>
        <input type="text" name="nama_dosir" id="edit_nama_dosir" required>
      </div>

      <div class="grid grid-2" style="margin-bottom:14px;align-items:end;">
        <div>
          <label>Nomor Urut Tampilan</label>
          <input type="number" name="urutan" id="edit_urutan" min="1" max="99" required>
        </div>
        <div>
          <label class="label-spacer">&nbsp;</label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;height:42px;margin:0;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:0 12px;">
            <input type="checkbox" name="wajib" id="edit_wajib" value="1" style="width:auto;margin:0;">
            <span style="font-weight:600;">Dokumen Wajib</span>
          </label>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalEdit').style.display='none';">Batal</button>
        <button type="submit" class="btn">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah Master Dosir -->
<div id="modalAdd" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);align-items:center;justify-content:center;z-index:999;backdrop-filter:blur(3px);">
  <div class="card" style="width:500px;max-width:90%;">
    <h3 style="margin-top:0;">+ Tambah Kategori Dosir Baru</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="grid grid-2" style="margin-bottom:12px;">
        <div>
          <label>Kode Dosir (2 Digit) *</label>
          <input type="text" name="kode" placeholder="cth: 34" maxlength="2" required pattern="[0-9]{2}">
        </div>
        <div>
          <label>Nomor Urut</label>
          <input type="number" name="urutan" value="<?= count($masterList) + 1 ?>" min="1" max="99" required>
        </div>
      </div>

      <div style="margin-bottom:12px;">
        <label>Nama Jenis Dokumen Dosir *</label>
        <input type="text" name="nama_dosir" placeholder="cth: KEP PENGHARGAAN SATYA LANCANA" required>
      </div>

      <div style="margin-bottom:16px;">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
          <input type="checkbox" name="wajib" value="1" checked style="width:auto;margin:0;">
          <span>Dokumen Wajib (Diperhitungkan dalam persentase kelengkapan)</span>
        </label>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:8px;">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modalAdd').style.display='none';">Batal</button>
        <button type="submit" class="btn">Tambah Kategori</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(data) {
  document.getElementById('edit_kode').value = data.kode;
  document.getElementById('edit_kode_display').value = 'DOSIR ' + data.kode;
  document.getElementById('edit_nama_dosir').value = data.nama_dosir;
  document.getElementById('edit_urutan').value = data.urutan;
  document.getElementById('edit_wajib').checked = (parseInt(data.wajib) === 1);
  document.getElementById('modalEdit').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
