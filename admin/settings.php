<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$msg = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Simpan Pengaturan Identitas & Parameter Sistem
    if ($action === 'save_settings') {
        $appName        = trim($_POST['app_name'] ?? '');
        $appSubtitle    = trim($_POST['app_subtitle'] ?? '');
        $appBrandTitle  = trim($_POST['app_brand_title'] ?? '');
        $appBrandSub    = trim($_POST['app_brand_sub'] ?? '');
        $instansi       = trim($_POST['instansi'] ?? '');
        $batasJabatan   = max(1, (int)($_POST['batas_tahun_jabatan'] ?? 2));
        $watermarkText  = trim($_POST['watermark_text'] ?? '');

        if ($appName === '') {
            $error = 'Judul aplikasi tidak boleh kosong.';
        } else {
            // Tangani penghapusan logo jika diminta
            if (!empty($_POST['delete_logo'])) {
                $oldLogo = get_setting($pdo, 'app_logo', '');
                if ($oldLogo && file_exists(APP_ROOT . '/' . $oldLogo)) {
                    @unlink(APP_ROOT . '/' . $oldLogo);
                }
                update_setting($pdo, 'app_logo', '', 'general');
                log_activity($pdo, $admin['id'], 'DELETE_LOGO', 'Menghapus logo kustom aplikasi dan kembali ke logo default');
            }

            // Tangani unggahan logo aplikasi baru jika ada
            if (!empty($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES['app_logo'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $allowedExts = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
                $maxLogoSize = 3 * 1024 * 1024; // 3 MB

                $valid = false;
                if (in_array($ext, $allowedExts, true) && $f['size'] <= $maxLogoSize) {
                    if ($ext === 'svg') {
                        $svgContent = file_get_contents($f['tmp_name']);
                        if (strpos($svgContent, '<svg') !== false && stripos($svgContent, '<script') === false) {
                            $valid = true;
                        }
                    } else {
                        $img = @getimagesize($f['tmp_name']);
                        $allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];
                        if ($img && in_array($img['mime'], $allowedMimes, true)) {
                            $valid = true;
                        }
                    }
                }

                if ($valid) {
                    $imgDir = APP_ROOT . '/assets/img';
                    ensure_dir($imgDir);
                    $logoFileName = 'logo_' . time() . '.' . $ext;
                    $targetLogoPath = $imgDir . '/' . $logoFileName;

                    if (move_uploaded_file($f['tmp_name'], $targetLogoPath)) {
                        $oldLogo = get_setting($pdo, 'app_logo', '');
                        if ($oldLogo && file_exists(APP_ROOT . '/' . $oldLogo)) {
                            @unlink(APP_ROOT . '/' . $oldLogo);
                        }
                        update_setting($pdo, 'app_logo', 'assets/img/' . $logoFileName, 'general');
                        log_activity($pdo, $admin['id'], 'UPDATE_LOGO', "Memperbarui logo aplikasi ($logoFileName)");
                    } else {
                        $error = 'Gagal menyimpan berkas logo di server.';
                    }
                } else {
                    $error = 'Berkas logo tidak valid. Harap gunakan format PNG, JPG, WEBP, atau SVG asli (maks 3 MB).';
                }
            }

            update_setting($pdo, 'app_name', $appName, 'general');
            update_setting($pdo, 'app_subtitle', $appSubtitle, 'general');
            update_setting($pdo, 'app_brand_title', $appBrandTitle ?: 'E-DOSIR', 'general');
            update_setting($pdo, 'app_brand_sub', $appBrandSub ?: 'TNI AD', 'general');
            update_setting($pdo, 'instansi', $instansi ?: 'TNI Angkatan Darat', 'general');
            update_setting($pdo, 'batas_tahun_jabatan', (string)$batasJabatan, 'dosir');
            update_setting($pdo, 'watermark_text', $watermarkText ?: 'BELUM TERVERIFIKASI', 'dosir');

            if (!$error) {
                log_activity($pdo, $admin['id'], 'UPDATE_SETTINGS', "Memperbarui pengaturan aplikasi (Judul: $appName)");
                set_flash('success', 'Pengaturan aplikasi dan logo berhasil disimpan dan langsung diterapkan.');
                redirect('/admin/settings.php');
            }
        }
    }

    // 2. Ganti Kata Sandi Administrator
    elseif ($action === 'change_password') {
        $oldPass     = $_POST['old_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$admin['id']]);
        $currHash = $stmt->fetchColumn();

        if (!password_verify($oldPass, $currHash)) {
            $error = 'Kata sandi saat ini tidak sesuai.';
        } elseif (strlen($newPass) < 6) {
            $error = 'Kata sandi baru minimal 6 karakter.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Konfirmasi kata sandi baru tidak cocok.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$newHash, $admin['id']]);

            log_activity($pdo, $admin['id'], 'CHANGE_ADMIN_PASSWORD', 'Administrator memperbarui kata sandi akun');
            set_flash('success', 'Kata sandi administrator berhasil diperbarui.');
            redirect('/admin/settings.php');
        }
    }
}

// Ambil nilai pengaturan terkini
$currAppName        = get_setting($pdo, 'app_name', 'E-DOSIR TNI AD');
$currAppSubtitle    = get_setting($pdo, 'app_subtitle', 'Sistem Dosir Elektronik Personel');
$currAppBrandTitle  = get_setting($pdo, 'app_brand_title', 'E-DOSIR');
$currAppBrandSub    = get_setting($pdo, 'app_brand_sub', 'TNI AD');
$currInstansi       = get_setting($pdo, 'instansi', 'TNI Angkatan Darat');
$currBatasJabatan   = get_setting($pdo, 'batas_tahun_jabatan', '2');
$currWatermarkText  = get_setting($pdo, 'watermark_text', 'BELUM TERVERIFIKASI');
$currAppLogoUrl     = app_logo_url();

$pageTitle = 'Pengaturan Aplikasi';
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
  <div>
    <h2 style="margin:0;"><?= htmlspecialchars($pageTitle) ?></h2>
    <div style="color:var(--text-dim);font-size:13px;margin-top:2px;">
      Kelola logo instansi, judul sistem, parameter operasional dosir, dan keamanan akun.
    </div>
  </div>
  <div class="badge badge-approved" style="font-size:12px;padding:6px 12px;">
    ★ Hak Akses: Administrator
  </div>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid grid-2" style="align-items:start;">
  <!-- Kolom Kiri: Pengaturan Identitas & Parameter Aplikasi -->
  <div class="card">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border);">
      <span style="font-size:20px;color:var(--gold);">⚙️</span>
      <div>
        <h3 style="margin:0;font-size:16px;">Identitas &amp; Parameter Aplikasi</h3>
        <div style="font-size:12px;color:var(--text-dim);">Pengaturan ini akan langsung tampil di Header, Login, Dashboard, dan Laporan.</div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_settings">

      <!-- Bagian Upload & Pratinjau Logo Aplikasi -->
      <div style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:18px;">
        <strong style="font-size:13px;display:block;margin-bottom:10px;color:var(--gold);">★ Logo Resmi Aplikasi / Satuan:</strong>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
          <div id="logoPreviewBox" style="width:72px;height:72px;border-radius:10px;background:var(--panel);border:2px dashed var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
            <?php if ($currAppLogoUrl): ?>
              <img src="<?= $currAppLogoUrl ?>" alt="Logo Aplikasi" style="max-width:100%;max-height:100%;object-fit:contain;padding:4px;">
            <?php else: ?>
              <span class="brand-badge" style="width:46px;height:46px;font-size:22px;">★</span>
            <?php endif; ?>
          </div>

          <div style="flex:1;min-width:200px;">
            <label style="font-size:12px;margin-bottom:4px;font-weight:600;">Pilih Berkas Logo (PNG/JPG/WEBP/SVG, maks 3 MB)</label>
            <input type="file" name="app_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="padding:6px;font-size:12.5px;">
            <span style="font-size:11px;color:var(--text-dim);display:block;margin-top:4px;">
              Logo akan otomatis ditampilkan pada Sidebar, Halaman Login, Kartu Registrasi, Kop Cetak Laporan, dan Verifikasi TTE. Disarankan berformat PNG transparan.
            </span>
            <?php if ($currAppLogoUrl): ?>
              <div style="margin-top:8px;">
                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:12px;color:var(--danger);font-weight:600;">
                  <input type="checkbox" name="delete_logo" value="1" style="width:auto;margin:0;">
                  <span>✕ Hapus logo kustom (Gunakan kembali lambang bintang militer bawaan)</span>
                </label>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-weight:600;">Judul Utama Aplikasi (App Title) *</label>
        <input type="text" name="app_name" value="<?= htmlspecialchars($currAppName) ?>" required placeholder="Contoh: E-DOSIR TNI AD / E-DOSIR KODAM" autofocus>
        <span style="font-size:11.5px;color:var(--text-dim);display:block;margin-top:4px;">
          Ditampilkan di tab browser, halaman login, kop laporan, dan banner pusat komando.
        </span>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-weight:600;">Sub-Judul Aplikasi</label>
        <input type="text" name="app_subtitle" value="<?= htmlspecialchars($currAppSubtitle) ?>" placeholder="Contoh: Sistem Dosir Elektronik Personel">
        <span style="font-size:11.5px;color:var(--text-dim);display:block;margin-top:4px;">
          Ditampilkan di bawah judul login dan footer sistem.
        </span>
      </div>

      <div class="grid grid-2" style="margin-bottom:16px;">
        <div>
          <label style="font-weight:600;">Brand Sidebar Atas</label>
          <input type="text" name="app_brand_title" value="<?= htmlspecialchars($currAppBrandTitle) ?>" placeholder="E-DOSIR" required>
        </div>
        <div>
          <label style="font-weight:600;">Brand Sidebar Bawah</label>
          <input type="text" name="app_brand_sub" value="<?= htmlspecialchars($currAppBrandSub) ?>" placeholder="TNI AD" required>
        </div>
      </div>

      <div style="margin-bottom:16px;">
        <label style="font-weight:600;">Nama Instansi / Satuan Induk</label>
        <input type="text" name="instansi" value="<?= htmlspecialchars($currInstansi) ?>" placeholder="Contoh: TNI Angkatan Darat / Kodam Jaya">
        <span style="font-size:11.5px;color:var(--text-dim);display:block;margin-top:4px;">
          Ditampilkan pada form pendaftaran personel dan kop cetak laporan.
        </span>
      </div>

      <div style="padding:14px;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;margin-bottom:18px;">
        <strong style="font-size:13px;display:block;margin-bottom:10px;color:var(--gold);">📋 Parameter Operasional Dosir:</strong>
        
        <div class="grid grid-2">
          <div>
            <label style="font-size:12px;">Batas Peringatan Rotasi Jabatan (Tahun)</label>
            <input type="number" name="batas_tahun_jabatan" value="<?= htmlspecialchars($currBatasJabatan) ?>" min="1" max="10" required>
            <span style="font-size:11px;color:var(--text-dim);">Standar: 2 tahun</span>
          </div>
          <div>
            <label style="font-size:12px;">Teks Watermark Dokumen Pending (Belum Verifikasi)</label>
            <input type="text" name="watermark_text" value="<?= htmlspecialchars($currWatermarkText) ?>" placeholder="BELUM TERVERIFIKASI">
            <span style="font-size:11px;color:var(--text-dim);">Cap diagonal pada berkas baru diunggah. Saat berkas disetujui/diapprove, watermark otomatis lenyap digantikan TTE resmi.</span>
          </div>
        </div>
      </div>

      <button type="submit" class="btn" style="width:100%;padding:10px;">
        💾 Simpan Perubahan Pengaturan
      </button>
    </form>
  </div>

  <!-- Kolom Kanan: Keamanan Akun Administrator & Status Sistem -->
  <div style="display:flex;flex-direction:column;gap:20px;">
    <!-- Kartu Keamanan Sandi Admin -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border);">
        <span style="font-size:20px;color:var(--gold);">🛡️</span>
        <div>
          <h3 style="margin:0;font-size:16px;">Ganti Kata Sandi Administrator</h3>
          <div style="font-size:12px;color:var(--text-dim);">Perbarui kata sandi akun login Anda secara berkala.</div>
        </div>
      </div>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div style="margin-bottom:12px;">
          <label>Kata Sandi Saat Ini *</label>
          <input type="password" name="old_password" required placeholder="Masukkan kata sandi saat ini">
        </div>

        <div style="margin-bottom:12px;">
          <label>Kata Sandi Baru *</label>
          <input type="password" name="new_password" required placeholder="Minimal 6 karakter" minlength="6">
        </div>

        <div style="margin-bottom:16px;">
          <label>Konfirmasi Kata Sandi Baru *</label>
          <input type="password" name="confirm_password" required placeholder="Ulangi kata sandi baru" minlength="6">
        </div>

        <button type="submit" class="btn btn-outline" style="width:100%;padding:9px;">
          🔒 Perbarui Kata Sandi
        </button>
      </form>
    </div>

    <!-- Kartu Informasi Server & Environment -->
    <div class="card" style="background:var(--panel-2);">
      <h4 style="margin:0 0 10px;font-size:14px;color:var(--gold);">ℹ️ Informasi Lingkungan Server</h4>
      <table style="font-size:12px;margin:0;">
        <tr>
          <th style="padding:6px 0;color:var(--text-dim);">PHP Version</th>
          <td style="padding:6px 0;font-family:monospace;"><?= phpversion() ?></td>
        </tr>
        <tr>
          <th style="padding:6px 0;color:var(--text-dim);">Database Driver</th>
          <td style="padding:6px 0;font-family:monospace;">MySQL (PDO)</td>
        </tr>
        <tr>
          <th style="padding:6px 0;color:var(--text-dim);">Web Server</th>
          <td style="padding:6px 0;font-family:monospace;"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?></td>
        </tr>
        <tr>
          <th style="padding:6px 0;color:var(--text-dim);">Waktu Sistem</th>
          <td style="padding:6px 0;font-family:monospace;"><?= date('Y-m-d H:i:s') ?> WIB</td>
        </tr>
        <tr>
          <th style="padding:6px 0;color:var(--text-dim);">Upload Folder</th>
          <td style="padding:6px 0;font-family:monospace;"><?= is_writable(UPLOAD_DIR) ? '<span style="color:var(--ok);">Writable (OK)</span>' : '<span style="color:var(--danger);">Read-only</span>' ?></td>
        </tr>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const logoInput = document.querySelector('input[name="app_logo"]');
  const previewBox = document.getElementById('logoPreviewBox');
  if (logoInput && previewBox) {
    logoInput.addEventListener('change', function(e) {
      const file = e.target.files && e.target.files[0];
      if (file) {
        if (file.size > 3 * 1024 * 1024) {
          alert('Perhatian: Ukuran file logo maksimal 3 MB!');
          logoInput.value = '';
          return;
        }
        const reader = new FileReader();
        reader.onload = function(evt) {
          previewBox.innerHTML = '<img src="' + evt.target.result + '" alt="Pratinjau Logo" style="max-width:100%;max-height:100%;object-fit:contain;padding:4px;">';
        };
        reader.readAsDataURL(file);
      }
    });
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
