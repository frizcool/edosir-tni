<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT p.*,
           mp.nama as pangkat_resmi, mp.singkatan as pangkat, mp.bup_usia as pangkat_bup, mp.golongan,
           mk.kode as korp, mk.nama as korp_nama, mk.kategori as korp_kategori,
           ms.nama as satuan, ms.nama as satuan_nama, ms.lokasi as satuan_lokasi,
           mkot.nama as kotama, mkot.nama as kotama_nama, mkot.tipe as kotama_tipe
    FROM personel p
    LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
    LEFT JOIN master_korp mk ON mk.id = p.korp_id
    LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
    LEFT JOIN master_kotama mkot ON COALESCE(ms.kotama_id, p.kotama_id) = mkot.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { set_flash('error', 'Data personel tidak ditemukan.'); redirect('/admin/personel_list.php'); }

// Ambil akun user login jika ada, atau buatkan jika belum pernah ada
$stmtU = $pdo->prepare("SELECT * FROM users WHERE personel_id=?");
$stmtU->execute([$id]);
$uAccount = $stmtU->fetch();

if (!$uAccount) {
    ensure_personel_user($pdo, $id, $p['nrp'], 'approved');
    $stmtU->execute([$id]);
    $uAccount = $stmtU->fetch();
}

// Tangani aksi cepat kontrol akun user dari halaman detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action']) && $uAccount) {
    verify_csrf();
    $act = $_POST['user_action'];
    if ($act === 'approve') {
        $pdo->prepare("UPDATE users SET status='approved' WHERE id=?")->execute([$uAccount['id']]);
        log_activity($pdo, $admin['id'], 'APPROVAL_USER', "User #{$uAccount['id']} (NRP {$p['nrp']}) diverifikasi");
        set_flash('success', 'Akun login personel berhasil diverifikasi dan diaktifkan.');
    } elseif ($act === 'reject') {
        $catatan = trim($_POST['catatan'] ?? '');
        $pdo->prepare("UPDATE users SET status='rejected', catatan_approval=? WHERE id=?")->execute([$catatan !== '' ? $catatan : null, $uAccount['id']]);
        $logTxt = "User #{$uAccount['id']} (NRP {$p['nrp']}) ditolak" . ($catatan !== '' ? " (Alasan: $catatan)" : '');
        log_activity($pdo, $admin['id'], 'APPROVAL_USER', $logTxt);
        set_flash('success', 'Akun login personel berhasil ditolak.');
    } elseif ($act === 'deactivate') {
        $pdo->prepare("UPDATE users SET status='nonaktif' WHERE id=?")->execute([$uAccount['id']]);
        log_activity($pdo, $admin['id'], 'APPROVAL_USER', "User #{$uAccount['id']} (NRP {$p['nrp']}) dinonaktifkan");
        set_flash('success', 'Akun login personel berhasil dinonaktifkan.');
    } elseif ($act === 'reset_pwd') {
        $tempPass = reset_user_password_to_nrp($pdo, $uAccount['id'], $p['nrp']);
        log_activity($pdo, $admin['id'], 'RESET_PASSWORD', "Reset password user #{$uAccount['id']} ke sandi sementara acak");
        set_flash('success', "Kata sandi akun berhasil di-reset ke sandi acak sementara: <strong>" . htmlspecialchars($tempPass) . "</strong>. Pengguna wajib mengganti kata sandi ini saat pertama kali login.", true);
    }
    redirect('/admin/personel_detail.php?id=' . $id);
}

$k = hitung_kelengkapan($pdo, $id);
$dosirList = get_dosir_master($pdo);

$stmtF = $pdo->prepare("SELECT * FROM dosir_files WHERE personel_id=? ORDER BY dosir_kode, abjad");
$stmtF->execute([$id]);
$files = $stmtF->fetchAll();
$byKode = [];
foreach ($files as $f) { $byKode[$f['dosir_kode']][] = $f; }

$tglPensiun = prediksi_pensiun($p['golongan'], $p['tanggal_lahir']);
$sisaBulan = bulan_menuju_pensiun($tglPensiun);
$lamaJabatan = lama_jabatan_tahun($p['tmt_jabatan']);

$pageTitle = 'Detail Personel';
include __DIR__ . '/../includes/header.php';
?>

<div class="grid grid-3" style="margin-bottom:20px;">
  <div class="card" style="grid-column:span 2;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div style="display:flex;align-items:center;gap:14px;">
        <?php $pFotoUrl = foto_url($p['foto'] ?? ''); ?>
        <?php if ($pFotoUrl): ?>
          <img src="<?= $pFotoUrl ?>" alt="Foto" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--gold);box-shadow:0 2px 8px rgba(0,0,0,.25);">
        <?php else: ?>
          <div style="width:52px;height:52px;border-radius:50%;background:var(--panel-2);border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--gold);font-size:22px;">★</div>
        <?php endif; ?>
        <div>
          <h2 style="margin:0;"><?= htmlspecialchars($p['nama']) ?></h2>
          <div style="color:var(--text-dim);margin-top:2px;">
            <?= htmlspecialchars($p['pangkat'] ?? '-') ?> &middot; <span style="font-family:monospace;"><?= htmlspecialchars($p['nrp']) ?></span>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>/admin/personel_form.php?id=<?= $p['id'] ?>" class="btn btn-outline">✏️ Edit Data</a>
        <button type="button" class="btn btn-outline btn-danger" onclick="openDeleteModalDetail()">
          🗑️ Hapus Personel
        </button>
      </div>
    </div>

    <!-- Panel Akun Login Personel -->
    <?php if ($uAccount): ?>
    <div style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-top:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <div>
        <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.5px;">Akun Login Sistem</div>
        <div style="font-size:14px;margin-top:2px;">
          Username: <strong style="font-family:monospace;"><?= htmlspecialchars($uAccount['username']) ?></strong> &middot;
          Status: <span class="badge badge-<?= $uAccount['status'] ?>"><?= strtoupper($uAccount['status']) ?></span>
          <span style="font-size:12px;color:var(--text-dim);margin-left:6px;">(Password default: NRP)</span>
        </div>
        <?php if ($uAccount['status'] === 'rejected' && !empty($uAccount['catatan_approval'])): ?>
          <div style="font-size:12px;color:var(--danger);margin-top:4px;">
            <strong>Catatan Penolakan:</strong> <?= htmlspecialchars($uAccount['catatan_approval']) ?>
          </div>
        <?php endif; ?>
      </div>
      <form method="post" id="detailUserActionForm" style="display:flex;gap:6px;">
        <?= csrf_field() ?>
        <input type="hidden" name="catatan" id="detailCatatanInput" value="">
        <?php if ($uAccount['status'] !== 'approved'): ?>
          <button type="submit" name="user_action" value="approve" class="btn" style="padding:5px 10px;font-size:12px;background:var(--ok);" onclick="return confirm('Verifikasi dan aktifkan akun login personel ini?');">
            ✓ Verifikasi Akun
          </button>
        <?php endif; ?>
        <?php if ($uAccount['status'] === 'pending'): ?>
          <button type="button" class="btn btn-danger" style="padding:5px 10px;font-size:12px;" onclick="
            var r = prompt('Masukkan alasan penolakan pendaftaran akun:');
            if (r !== null) {
              document.getElementById('detailCatatanInput').value = r.trim();
              var act = document.createElement('input');
              act.type = 'hidden';
              act.name = 'user_action';
              act.value = 'reject';
              document.getElementById('detailUserActionForm').appendChild(act);
              document.getElementById('detailUserActionForm').submit();
            }
          ">
            ✕ Tolak Akun
          </button>
        <?php endif; ?>
        <?php if ($uAccount['status'] === 'approved'): ?>
          <button type="submit" name="user_action" value="deactivate" class="btn btn-outline" style="padding:5px 10px;font-size:12px;" onclick="return confirm('Nonaktifkan akun personel ini?');">
            Nonaktifkan
          </button>
        <?php endif; ?>
        <button type="submit" name="user_action" value="reset_pwd" class="btn btn-outline" style="padding:5px 10px;font-size:12px;" onclick="return confirm('Reset kata sandi akun ini kembali ke NRP default?');">
          Reset Sandi ke NRP
        </button>
      </form>
    </div>
    <?php endif; ?>

    <table style="margin-top:16px;">
      <tr>
        <th>Golongan</th>
        <td><span class="badge badge-info"><?= htmlspecialchars($p['golongan']) ?></span></td>
        <th>Korp / Kecabangan</th>
        <td>
          <strong><?= htmlspecialchars($p['korp'] ?: '-') ?></strong>
          <?php if (!empty($p['korp_nama'])): ?>
            <span style="color:var(--text-dim);font-size:12px;">(<?= htmlspecialchars($p['korp_nama']) ?><?= !empty($p['korp_kategori']) ? ' &middot; ' . htmlspecialchars($p['korp_kategori']) : '' ?>)</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>Satuan Organik</th>
        <td>
          <strong><?= htmlspecialchars($p['satuan'] ?: '-') ?></strong>
          <?php if (!empty($p['satuan_lokasi'])): ?>
            <span style="color:var(--text-dim);font-size:12px;">(📍 <?= htmlspecialchars($p['satuan_lokasi']) ?>)</span>
          <?php endif; ?>
        </td>
        <th>Kotama / Balakpus</th>
        <td>
          <strong><?= htmlspecialchars($p['kotama'] ?: '-') ?></strong>
          <?php if (!empty($p['kotama_tipe'])): ?>
            <span class="badge badge-nonaktif" style="font-size:10.5px;"><?= htmlspecialchars($p['kotama_tipe']) ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <th>Pangkat</th>
        <td>
          <strong><?= htmlspecialchars($p['pangkat'] ?: '-') ?></strong>
          <?php if (!empty($p['pangkat_resmi'])): ?>
            <span style="color:var(--text-dim);font-size:12px;">(<?= htmlspecialchars($p['pangkat_resmi']) ?><?= !empty($p['pangkat_bup']) ? ' &middot; BUP ' . $p['pangkat_bup'] . ' th' : '' ?>)</span>
          <?php endif; ?>
        </td>
        <th>TMT Pangkat</th>
        <td><?= fmt_tgl($p['tmt_pangkat']) ?></td>
      </tr>
      <tr><th>Jabatan</th><td><?= htmlspecialchars($p['jabatan'] ?? '-') ?></td><th>TMT Jabatan</th><td><?= fmt_tgl($p['tmt_jabatan']) ?></td></tr>
      <tr><th>Tgl Lahir</th><td><?= fmt_tgl($p['tanggal_lahir']) ?></td><th>Tempat Lahir</th><td><?= htmlspecialchars($p['tempat_lahir'] ?? '-') ?></td></tr>
      <tr><th>No. HP</th><td><?= htmlspecialchars($p['no_hp'] ?? '-') ?></td><th>Status Dinas</th><td><span class="badge <?= ($p['status_dinas'] ?? '') === 'Aktif' ? 'badge-approved' : 'badge-pending' ?>"><?= htmlspecialchars($p['status_dinas']) ?></span></td></tr>
      <tr><th>Email</th><td><?= htmlspecialchars($p['email'] ?? '-') ?></td><th>Jenis Kelamin</th><td><?= ($p['jenis_kelamin'] ?? 'L') === 'L' ? 'Laki-laki' : 'Perempuan' ?></td></tr>
    </table>
  </div>

  <div class="card">
    <div class="stat" style="margin-bottom:14px;">
      <span class="label">Kelengkapan Dosir</span>
      <span class="value gold"><?= $k['persen'] ?>%</span>
      <div class="progress"><span style="width:<?= $k['persen'] ?>%;"></span></div>
      <span style="font-size:12px;color:var(--text-dim);"><?= $k['terisi'] ?> / <?= $k['total'] ?> jenis dosir</span>
    </div>
    <div class="stat" style="margin-bottom:14px;">
      <span class="label">Proyeksi Pensiun</span>
      <span class="value" style="font-size:18px;"><?= fmt_tgl($tglPensiun) ?></span>
      <?php if ($sisaBulan !== null): ?>
        <span class="badge <?= $sisaBulan <= 24 ? 'badge-pending' : 'badge-approved' ?>"><?= $sisaBulan ?> bulan lagi</span>
      <?php endif; ?>
    </div>
    <div class="stat">
      <span class="label">Lama Menjabat</span>
      <span class="value" style="font-size:18px;"><?= $lamaJabatan ?? '-' ?> tahun</span>
      <?php if ($lamaJabatan !== null && $lamaJabatan > BATAS_TAHUN_JABATAN): ?>
        <span class="badge badge-rejected">Kandidat Rotasi</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <strong>Checklist 33 Dosir</strong>
    <a href="<?= BASE_URL ?>/admin/bulk_download.php?personel_id=<?= $p['id'] ?>" class="btn btn-outline">Unduh Massal Personel Ini</a>
  </div>
  <div style="margin-top:12px;">
    <?php foreach ($dosirList as $d):
        $kode = $d['kode'];
        $entries = $byKode[$kode] ?? [];
    ?>
    <div class="dosir-item">
      <div style="flex:1;">
        <strong>DOSIR <?= $kode ?></strong> &mdash; <?= htmlspecialchars($d['nama_dosir']) ?>
        <?php if (empty($entries)): ?>
          <div style="font-size:12px;color:var(--danger);margin-top:2px;">Belum diunggah</div>
        <?php else: ?>
          <div style="margin-top:4px;display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($entries as $e): ?>
              <a href="<?= dosir_url($e['file_path']) ?>" target="_blank"
                 class="badge badge-<?= $e['status'] ?>" style="text-decoration:none;">
                 <?= htmlspecialchars($e['file_name']) ?>
                 <?php if (!empty($e['signature_code'])): ?>
                   &middot; ★ TTE
                 <?php elseif ($e['status'] === 'pending'): ?>
                   &middot; Belum Verifikasi
                 <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal Konfirmasi Hapus Personel Kaskade -->
<div class="modal-backdrop" id="modalDeletePersonelDetail">
  <div class="modal-dialog" style="max-width:520px;">
    <div class="modal-header" style="border-bottom:1px solid rgba(220,53,69,0.3);background:rgba(220,53,69,0.08);">
      <h3 class="modal-title" style="color:var(--danger);display:flex;align-items:center;gap:8px;">
        ⚠️ Konfirmasi Hapus Personel
      </h3>
      <button type="button" class="modal-close" onclick="closeDeleteModalDetail()">&times;</button>
    </div>
    <form method="post" action="<?= BASE_URL ?>/admin/personel_delete.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $p['id'] ?>">
      <input type="hidden" name="redirect_to" value="/admin/personel_list.php">
      <div class="modal-body">
        <div style="background:rgba(220,53,69,0.1);border-left:4px solid var(--danger);padding:12px 16px;border-radius:4px;margin-bottom:16px;">
          <strong style="color:var(--danger);display:block;margin-bottom:4px;">TINDAKAN INI BERSIFAT PERMANEN & TIDAK DAPAT DIBATALKAN!</strong>
          <span style="font-size:13px;color:var(--text-dim);">
            Menghapus personel ini akan menghapus <strong>seluruh data terkait secara otomatis</strong> dari sistem pangkalan data dan server penyimpanan fisik.
          </span>
        </div>

        <div style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px;">
          <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Personel yang akan dihapus:</div>
          <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:2px;"><?= htmlspecialchars($p['nama']) ?></div>
          <div style="font-size:13px;font-family:monospace;color:var(--gold);">NRP: <?= htmlspecialchars($p['nrp']) ?> &middot; <?= htmlspecialchars($p['pangkat'] ?? '-') ?></div>
        </div>

        <div style="font-size:13px;color:var(--text-dim);line-height:1.6;">
          <strong>Data yang akan ikut terhapus tuntas:</strong>
          <ul style="margin:8px 0 0 18px;padding:0;">
            <li>Seluruh <?= count($files) ?> berkas dokumen dosir digital (PDF aktif & master warkat raw di server)</li>
            <li>Pas foto profil prajurit di media penyimpanan server</li>
            <li>Akun akses pengguna sistem (users) & riwayat login</li>
            <li>Data riwayat dinas, jabatan, dan kelengkapan personel</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeDeleteModalDetail()">Batal</button>
        <button type="submit" class="btn btn-danger" style="display:flex;align-items:center;gap:6px;">
          🗑️ Ya, Hapus Personel & Seluruh Dokumen
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteModalDetail() {
  var modal = document.getElementById('modalDeletePersonelDetail');
  if (modal) modal.classList.add('show');
}
function closeDeleteModalDetail() {
  var modal = document.getElementById('modalDeletePersonelDetail');
  if (modal) modal.classList.remove('show');
}
document.addEventListener('click', function(e) {
  if (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) {
    closeDeleteModalDetail();
  }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDeleteModalDetail();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

