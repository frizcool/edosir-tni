<?php
require_once __DIR__ . '/../config/config.php';
require_role('personel');

$u = current_user();
$personel_id = $u['personel_id'];

$stmt = $pdo->prepare("SELECT * FROM v_personel_lengkap WHERE id=?");
$stmt->execute([$personel_id]);
$p = $stmt->fetch();

$msg = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'update_profile';


    // 1. Update Data Kontak & Pribadi
    if ($action === 'update_profile') {
        $no_hp = trim($_POST['no_hp'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $agama = trim($_POST['agama'] ?? '');
        $status_kawin = trim($_POST['status_kawin'] ?? '');

        $upd = $pdo->prepare("UPDATE personel SET no_hp=?, email=?, alamat=?, agama=?, status_kawin=? WHERE id=?");
        $upd->execute([$no_hp, $email, $alamat, $agama, $status_kawin, $personel_id]);
        log_activity($pdo, $u['id'], 'UPDATE_PROFIL', 'Personel memperbarui data kontak');
        $msg = 'Data kontak dan alamat berhasil diperbarui.';

        $stmt->execute([$personel_id]);
        $p = $stmt->fetch();
    }

    // 2. Upload Pas Foto Profil Prajurit
    elseif ($action === 'upload_foto') {
        if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['foto'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $imgInfo = @getimagesize($f['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($ext, $allowed, true) || !$imgInfo || !in_array($imgInfo['mime'], $allowedMimes, true)) {
                $error = 'Format foto tidak valid. Pastikan mengunggah file gambar asli (JPG, PNG, atau WEBP).';
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $error = 'Ukuran foto maksimal 2 MB.';
            } else {
                $dir = UPLOAD_DIR . '/foto_profil';
                ensure_dir($dir);
                $newName = 'foto_' . $personel_id . '_' . date('Ymd_His') . '.' . $ext;
                $target = $dir . '/' . $newName;

                if (move_uploaded_file($f['tmp_name'], $target)) {
                    $relPath = 'uploads/foto_profil/' . $newName;
                    $pdo->prepare("UPDATE personel SET foto=? WHERE id=?")->execute([$relPath, $personel_id]);
                    $_SESSION['user']['foto'] = $relPath;
                    log_activity($pdo, $u['id'], 'UPDATE_FOTO', 'Personel mengunggah pas foto baru');
                    $msg = 'Pas foto profil berhasil diperbarui.';

                    $stmt->execute([$personel_id]);
                    $p = $stmt->fetch();
                } else {
                    $error = 'Gagal menyimpan file foto ke server.';
                }
            }
        } else {
            $error = 'Pilih file pas foto terlebih dahulu.';
        }
    }

    // 3. Ganti Kata Sandi
    elseif ($action === 'change_password') {
        $oldPass = $_POST['password_current'] ?? '';
        $newPass = $_POST['password_new'] ?? '';
        $confirmPass = $_POST['password_confirm'] ?? '';

        // Ambil password hash saat ini dari users
        $stmtUser = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmtUser->execute([$u['id']]);
        $currHash = $stmtUser->fetchColumn();

        if (!password_verify($oldPass, $currHash)) {
            $error = 'Kata sandi saat ini tidak cocok.';
        } elseif (strlen($newPass) < 6) {
            $error = 'Kata sandi baru minimal 6 karakter.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Konfirmasi kata sandi baru tidak cocok.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $u['id']]);
            log_activity($pdo, $u['id'], 'GANTI_PASSWORD', 'Personel mengganti kata sandi akun');
            $msg = 'Kata sandi akun Anda berhasil diperbarui!';
        }
    }
}

$golPensiun = prediksi_pensiun($p['golongan'], $p['tanggal_lahir']);

$pageTitle = 'Profil Saya';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid grid-2">
  <!-- Kolom Kiri: Foto & Kedinasan -->
  <div style="display:flex;flex-direction:column;gap:18px;">
    <!-- Kartu Foto Prajurit -->
    <div class="card" style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
      <div style="flex-shrink:0;">
        <?php $pFotoUrl = foto_url($p['foto'] ?? ''); ?>
        <?php if ($pFotoUrl): ?>
          <img src="<?= $pFotoUrl ?>" alt="Foto Prajurit" style="width:100px;height:120px;border-radius:8px;object-fit:cover;border:2px solid var(--gold);box-shadow:var(--shadow);">
        <?php else: ?>
          <div style="width:100px;height:120px;border-radius:8px;background:var(--panel-2);border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-dim);font-size:32px;">
            ★
          </div>
        <?php endif; ?>
      </div>

      <div style="flex:1;min-width:180px;">
        <h4 style="margin:0 0 4px;font-size:16px;">Pas Foto Resmi</h4>
        <div style="font-size:12px;color:var(--text-dim);margin-bottom:12px;">Format JPG/PNG, seragam dinas, maks 2 MB.</div>
        <form method="post" enctype="multipart/form-data" style="margin:0;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload_foto">
          <input type="file" name="foto" accept="image/*" required style="padding:6px;font-size:12px;margin-bottom:8px;">
          <button type="submit" class="btn" style="padding:6px 14px;font-size:12px;">Upload Foto</button>
        </form>
      </div>
    </div>

    <!-- Kartu Data Kedinasan -->
    <div class="card">
      <h3 style="margin-top:0;">Data Kedinasan</h3>
      <table>
        <tr><th>NRP</th><td style="font-family:monospace;font-weight:600;color:var(--gold);"><?= htmlspecialchars($p['nrp']) ?></td></tr>
        <tr><th>Nama Lengkap</th><td><strong><?= htmlspecialchars($p['nama']) ?></strong></td></tr>
        <tr><th>Golongan</th><td><?= htmlspecialchars($p['golongan']) ?></td></tr>
        <tr><th>Pangkat</th><td><?= htmlspecialchars($p['pangkat'] ?? '-') ?></td></tr>
        <tr><th>Korp</th><td><?= htmlspecialchars($p['korp'] ?? '-') ?></td></tr>
        <tr><th>Satuan</th><td><?= htmlspecialchars($p['satuan'] ?? '-') ?></td></tr>
        <tr><th>Kotama</th><td><?= htmlspecialchars($p['kotama'] ?? '-') ?></td></tr>
        <tr><th>Jabatan</th><td><?= htmlspecialchars($p['jabatan'] ?? '-') ?></td></tr>
        <tr><th>TMT Jabatan</th><td><?= fmt_tgl($p['tmt_jabatan']) ?></td></tr>
        <tr><th>TMT Pangkat</th><td><?= fmt_tgl($p['tmt_pangkat']) ?></td></tr>
        <tr><th>Tanggal Lahir</th><td><?= fmt_tgl($p['tanggal_lahir']) ?></td></tr>
        <tr><th>Proyeksi Pensiun</th><td><strong style="color:var(--gold);"><?= fmt_tgl($golPensiun) ?></strong></td></tr>
      </table>
      <p style="font-size:12px;color:var(--text-dim);margin-top:12px;line-height:1.4;">
        Perubahan data kedinasan (pangkat, jabatan, satuan, dsb) hanya dapat dilakukan oleh admin satuan berdasarkan
        dokumen SK/Sprin resmi yang diunggah pada dosir terkait.
      </p>
    </div>
  </div>

  <!-- Kolom Kanan: Kontak & Keamanan Password -->
  <div style="display:flex;flex-direction:column;gap:18px;">
    <!-- Kartu Kontak & Pribadi -->
    <div class="card">
      <h3 style="margin-top:0;">Data Kontak &amp; Pribadi</h3>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="grid grid-2">
          <div>
            <label>No. HP / WhatsApp</label>
            <input name="no_hp" value="<?= htmlspecialchars($p['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
          </div>
          <div>
            <label>Alamat Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($p['email'] ?? '') ?>" placeholder="nama@email.com">
          </div>
          <div>
            <label>Agama</label>
            <input name="agama" value="<?= htmlspecialchars($p['agama'] ?? '') ?>" placeholder="Islam, Kristen, Katolik, dll">
          </div>
          <div>
            <label>Status Perkawinan</label>
            <input name="status_kawin" value="<?= htmlspecialchars($p['status_kawin'] ?? '') ?>" placeholder="Kawin / Belum Kawin">
          </div>
          <div style="grid-column:1/-1;">
            <label>Alamat Domisili</label>
            <textarea name="alamat" rows="2" placeholder="Alamat tempat tinggal saat ini"><?= htmlspecialchars($p['alamat'] ?? '') ?></textarea>
          </div>
        </div>
        <button class="btn" type="submit" style="width:100%;margin-top:14px;">Simpan Perubahan Kontak</button>
      </form>
    </div>

    <!-- Kartu Ganti Kata Sandi -->
    <div class="card">
      <h3 style="margin-top:0;">Keamanan &amp; Kata Sandi</h3>
      <p style="font-size:12.5px;color:var(--text-dim);margin-top:2px;">
        Ganti kata sandi awal (NRP) dengan kata sandi pribadi yang aman dan mudah Anda ingat.
      </p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        
        <label>Kata Sandi Saat Ini *</label>
        <input type="password" name="password_current" placeholder="Masukkan kata sandi lama / NRP" required>

        <label>Kata Sandi Baru * (Min 6 karakter)</label>
        <input type="password" name="password_new" placeholder="Masukkan kata sandi baru" required>

        <label>Ulangi Kata Sandi Baru *</label>
        <input type="password" name="password_confirm" placeholder="Ulangi kata sandi baru" required>

        <button class="btn btn-outline" type="submit">Perbarui Kata Sandi</button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
