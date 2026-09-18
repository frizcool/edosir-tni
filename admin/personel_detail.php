<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM personel WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { set_flash('error', 'Data personel tidak ditemukan.'); redirect('/admin/personel_list.php'); }

// Pastikan akun user login selalu tersedia untuk personel ini
$uAccountRes = ensure_personel_user($pdo, $id, $p['nrp'], 'approved');
$stmtU = $pdo->prepare("SELECT * FROM users WHERE personel_id=?");
$stmtU->execute([$id]);
$uAccount = $stmtU->fetch();

// Tangani aksi cepat kontrol akun user dari halaman detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action']) && $uAccount) {
    verify_csrf();
    $act = $_POST['user_action'];
    if ($act === 'approve') {
        $pdo->prepare("UPDATE users SET status='approved' WHERE id=?")->execute([$uAccount['id']]);
        log_activity($pdo, $admin['id'], 'APPROVAL_USER', "User #{$uAccount['id']} (NRP {$p['nrp']}) diverifikasi");
        set_flash('success', 'Akun login personel berhasil diverifikasi dan diaktifkan.');
    } elseif ($act === 'deactivate') {
        $pdo->prepare("UPDATE users SET status='nonaktif' WHERE id=?")->execute([$uAccount['id']]);
        log_activity($pdo, $admin['id'], 'APPROVAL_USER', "User #{$uAccount['id']} (NRP {$p['nrp']}) dinonaktifkan");
        set_flash('success', 'Akun login personel berhasil dinonaktifkan.');
    } elseif ($act === 'reset_pwd') {
        reset_user_password_to_nrp($pdo, $uAccount['id'], $p['nrp']);
        log_activity($pdo, $admin['id'], 'RESET_PASSWORD', "Reset password user #{$uAccount['id']} ke NRP {$p['nrp']}");
        set_flash('success', "Kata sandi akun berhasil di-reset kembali ke NRP default ({$p['nrp']}).");
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
      <a href="<?= BASE_URL ?>/admin/personel_form.php?id=<?= $p['id'] ?>" class="btn btn-outline">Edit Data</a>
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
      </div>
      <form method="post" style="display:flex;gap:6px;">
        <?= csrf_field() ?>
        <?php if ($uAccount['status'] !== 'approved'): ?>
          <button type="submit" name="user_action" value="approve" class="btn" style="padding:5px 10px;font-size:12px;background:var(--ok);" onclick="return confirm('Verifikasi dan aktifkan akun login personel ini?');">
            ✓ Verifikasi Akun
          </button>
        <?php else: ?>
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
      <tr><th>Golongan</th><td><?= htmlspecialchars($p['golongan']) ?></td><th>Korp</th><td><?= htmlspecialchars($p['korp'] ?? '-') ?></td></tr>
      <tr><th>Satuan</th><td><?= htmlspecialchars($p['satuan'] ?? '-') ?></td><th>Kotama</th><td><?= htmlspecialchars($p['kotama'] ?? '-') ?></td></tr>
      <tr><th>Jabatan</th><td><?= htmlspecialchars($p['jabatan'] ?? '-') ?></td><th>TMT Jabatan</th><td><?= fmt_tgl($p['tmt_jabatan']) ?></td></tr>
      <tr><th>TMT Pangkat</th><td><?= fmt_tgl($p['tmt_pangkat']) ?></td><th>Tgl Lahir</th><td><?= fmt_tgl($p['tanggal_lahir']) ?></td></tr>
      <tr><th>No. HP</th><td><?= htmlspecialchars($p['no_hp'] ?? '-') ?></td><th>Status Dinas</th><td><?= htmlspecialchars($p['status_dinas']) ?></td></tr>
      <tr><th>Email</th><td><?= htmlspecialchars($p['email'] ?? '-') ?></td><th>Tempat Lahir</th><td><?= htmlspecialchars($p['tempat_lahir'] ?? '-') ?></td></tr>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
