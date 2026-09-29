<?php
require_once __DIR__ . '/config/config.php';

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');
$file = null;
$error = null;
$verifySource = 'code'; // 'code' or 'file_upload'
$uploadedHash = null;
$diskIntegrityStatus = null;

// Skenario 1: Uji Integritas Langsung via Unggah Berkas PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['berkas_uji']) && $_FILES['berkas_uji']['error'] === UPLOAD_ERR_OK) {
    $verifySource = 'file_upload';
    $tmpFile = $_FILES['berkas_uji']['tmp_name'];
    
    if (!is_valid_pdf($tmpFile)) {
        $error = 'Berkas yang diunggah bukan dokumen PDF yang valid.';
    } else {
        $uploadedHash = hash_file('sha256', $tmpFile);
        
        // Cari berkas yang cocok dengan signed_hash atau raw_hash
        $stmt = $pdo->prepare("
            SELECT f.*, 
                   p.nama as nama_personel, p.nrp, mp.singkatan as pangkat_personel, ms.nama as satuan_personel, mp.golongan,
                   m.nama_dosir,
                   u.username as admin_username,
                   admin_p.nama as nama_admin, admin_mp.singkatan as pangkat_admin, admin_p.nrp as nrp_admin, admin_ms.nama as satuan_admin
            FROM dosir_files f
            JOIN personel p ON p.id = f.personel_id
            LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
            LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
            JOIN dosir_master m ON m.kode = f.dosir_kode
            LEFT JOIN users u ON u.id = f.verified_by
            LEFT JOIN personel admin_p ON admin_p.id = u.personel_id
            LEFT JOIN master_pangkat admin_mp ON admin_mp.id = admin_p.pangkat_id
            LEFT JOIN master_satuan admin_ms ON admin_ms.id = admin_p.satuan_id
            WHERE (f.signature_hash = ? OR f.raw_hash = ?) AND f.status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([$uploadedHash, $uploadedHash]);
        $file = $stmt->fetch();
        
        if (!$file) {
            $error = 'UJI INTEGRITAS GAGAL: Berkas PDF ini tidak terdaftar dalam pangkalan data arsip TRISULA TNI AD, atau telah mengalami modifikasi setelah penandatanganan TTE.';
        }
    }
}
// Skenario 2: Verifikasi Berdasarkan Kode Registrasi TTE
elseif ($code !== '') {
    $verifySource = 'code';
    $stmt = $pdo->prepare("
        SELECT f.*, 
               p.nama as nama_personel, p.nrp, mp.singkatan as pangkat_personel, ms.nama as satuan_personel, mp.golongan,
               m.nama_dosir,
               u.username as admin_username,
               admin_p.nama as nama_admin, admin_mp.singkatan as pangkat_admin, admin_p.nrp as nrp_admin, admin_ms.nama as satuan_admin
        FROM dosir_files f
        JOIN personel p ON p.id = f.personel_id
        LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
        LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
        JOIN dosir_master m ON m.kode = f.dosir_kode
        LEFT JOIN users u ON u.id = f.verified_by
        LEFT JOIN personel admin_p ON admin_p.id = u.personel_id
        LEFT JOIN master_pangkat admin_mp ON admin_mp.id = admin_p.pangkat_id
        LEFT JOIN master_satuan admin_ms ON admin_ms.id = admin_p.satuan_id
        WHERE f.signature_code = ?
    ");
    $stmt->execute([$code]);
    $file = $stmt->fetch();

    if (!$file || $file['status'] !== 'approved') {
        $error = 'Dokumen dengan kode registrasi TTE tersebut tidak ditemukan atau belum berstatus disahkan secara kedinasan.';
    }
} else {
    // Akses tanpa parameter - tampilkan form pencarian
    $error = null;
}

// Cek Integritas Berkas Fisik di Server jika berkas ditemukan
if ($file && $file['status'] === 'approved') {
    $activePhys = UPLOAD_DIR . '/' . $file['file_path'];
    if (file_exists($activePhys)) {
        $currentDiskHash = hash_file('sha256', $activePhys);
        if ($currentDiskHash === $file['signature_hash'] || $currentDiskHash === $file['raw_hash']) {
            $diskIntegrityStatus = 'MATCH';
        } else {
            $diskIntegrityStatus = 'MISMATCH';
        }
    } else {
        $diskIntegrityStatus = 'NOT_FOUND';
    }
}

$pageTitle = 'Verifikasi Keabsahan Dokumen TTE';
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
<style>
.verify-container {
    max-width: 720px;
    margin: 36px auto;
    padding: 0 16px;
}
.verify-header {
    text-align: center;
    margin-bottom: 24px;
}
.verify-seal {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(201,168,76,0.2), rgba(46,85,52,0.25));
    border: 2px solid var(--gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: var(--gold);
    margin: 0 auto 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}
.valid-card {
    border-top: 5px solid var(--ok);
    position: relative;
    overflow: hidden;
}
.invalid-card {
    border-top: 5px solid var(--danger);
}
.tte-code-pill {
    font-family: monospace;
    font-size: 13px;
    background: var(--panel-2);
    border: 1px solid var(--border);
    padding: 6px 14px;
    border-radius: 6px;
    color: var(--gold);
    display: inline-block;
    letter-spacing: 0.5px;
    margin-top: 6px;
}
.info-table {
    width: 100%;
    margin-top: 14px;
    border-collapse: collapse;
}
.info-table th {
    width: 36%;
    padding: 8px 10px;
    text-align: left;
    color: var(--text-dim);
    font-size: 12.5px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}
.info-table td {
    padding: 8px 10px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}
.nav-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 18px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 8px;
}
.tab-btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-dim);
    transition: all 0.2s;
}
.tab-btn.active {
    background: var(--panel-2);
    border-color: var(--border);
    color: var(--gold);
}
.tab-pane {
    display: none;
}
.tab-pane.active {
    display: block;
}
</style>
</head>
<body>
<div class="verify-container">
  <div class="verify-header">
    <?php $verifyLogo = app_logo_url($pdo); ?>
    <?php if ($verifyLogo): ?>
      <div style="margin-bottom:12px;">
        <img src="<?= htmlspecialchars($verifyLogo) ?>" alt="Logo Aplikasi" style="height:68px;max-width:200px;object-fit:contain;filter:drop-shadow(0 4px 12px rgba(0,0,0,0.35));">
      </div>
    <?php else: ?>
      <div class="verify-seal">★</div>
    <?php endif; ?>
    <h2 style="margin:0;"><?= htmlspecialchars(get_setting($pdo, 'app_name', APP_NAME)) ?></h2>
    <div style="color:var(--text-dim);font-size:13px;margin-top:4px;">
      Layanan Autentikasi &amp; Verifikasi Tanda Tangan Elektronik (TTE) &middot; <?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?>
    </div>
  </div>

  <!-- Panel Navigasi Form Uji Dokumen -->
  <div class="card" style="margin-bottom:20px;padding:16px 20px;">
    <div class="nav-tabs">
      <button type="button" class="tab-btn <?= $verifySource === 'code' ? 'active' : '' ?>" onclick="switchTab('tabCode')">🔍 Verifikasi Kode TTE / Token</button>
      <button type="button" class="tab-btn <?= $verifySource === 'file_upload' ? 'active' : '' ?>" onclick="switchTab('tabUpload')">📑 Uji Berkas PDF (SHA-256)</button>
    </div>

    <!-- Tab 1: Cari Berdasarkan Kode Registrasi TTE -->
    <div id="tabCode" class="tab-pane <?= $verifySource === 'code' ? 'active' : '' ?>">
      <form method="get" action="<?= BASE_URL ?>/verify.php" style="display:flex;gap:10px;align-items:center;">
        <input type="text" name="code" value="<?= htmlspecialchars($code) ?>" placeholder="Masukkan Nomor Registrasi TTE (cth: TTE-TRISULA-... atau TTE-TNIAD-...)" style="flex:1;margin:0;" required>
        <button type="submit" class="btn" style="white-space:nowrap;padding:10px 18px;">Verifikasi</button>
      </form>
    </div>

    <!-- Tab 2: Uji Validitas Berkas Langsung via Hashing Kriptografi -->
    <div id="tabUpload" class="tab-pane <?= $verifySource === 'file_upload' ? 'active' : '' ?>">
      <form method="post" action="<?= BASE_URL ?>/verify.php" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
          <input type="file" name="berkas_uji" accept="application/pdf" style="margin:0;" required>
        </div>
        <button type="submit" class="btn" style="white-space:nowrap;padding:10px 18px;background:var(--ok);">Uji Integritas Berkas</button>
      </form>
      <div style="font-size:11.5px;color:var(--text-dim);margin-top:6px;">
        Sistem akan menghitung *fingerprint* SHA-256 dokumen PDF secara instan dan mencocokkannya dengan warkat resmi di database.
      </div>
    </div>
  </div>

  <?php if ($file && $file['status'] === 'approved'): ?>
    <div class="card valid-card">
      <div style="text-align:center;padding-bottom:14px;border-bottom:1px solid var(--border);">
        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(46,139,87,0.15);color:var(--ok);border:1px solid var(--ok);padding:6px 16px;border-radius:20px;font-weight:700;font-size:13.5px;">
          ✓ DOKUMEN SAH &amp; TERVERIFIKASI RESMI
        </div>
        <div style="margin-top:10px;">
          <span class="tte-code-pill"><?= htmlspecialchars($file['signature_code']) ?></span>
        </div>
        <?php if ($verifySource === 'file_upload'): ?>
          <div style="margin-top:8px;font-size:12px;color:var(--ok);font-weight:600;">
            ✓ Berkas PDF yang Anda unggah 100% Cocok &amp; Autentik dengan Arsip Mabes TNI AD!
          </div>
        <?php endif; ?>
      </div>

      <table class="info-table">
        <tr>
          <th>Jenis Dokumen Dosir</th>
          <td>
            <strong>DOSIR <?= htmlspecialchars($file['dosir_kode']) ?> &mdash; <?= htmlspecialchars($file['nama_dosir']) ?></strong>
            <?php if (!empty($file['keterangan'])): ?>
              <div style="font-size:11.5px;color:var(--text-dim);margin-top:2px;">(<?= htmlspecialchars($file['keterangan']) ?>)</div>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Pemilik Berkas (Personel)</th>
          <td>
            <strong><?= htmlspecialchars($file['nama_personel']) ?></strong>
            <div style="font-size:12px;color:var(--gold);font-family:monospace;margin-top:2px;">
              <?= htmlspecialchars($file['pangkat_personel'] ?? '') ?> &middot; NRP <?= htmlspecialchars($file['nrp']) ?>
            </div>
            <div style="font-size:11.5px;color:var(--text-dim);"><?= htmlspecialchars($file['satuan_personel'] ?? '-') ?></div>
          </td>
        </tr>
        <tr>
          <th>Verifikator / Pejabat TTE</th>
          <td>
            <strong><?= htmlspecialchars($file['nama_admin'] ?: ($file['admin_username'] ?: 'Administrator Sistem')) ?></strong>
            <div style="font-size:12px;color:var(--text-dim);margin-top:2px;">
              <?= htmlspecialchars($file['pangkat_admin'] ? $file['pangkat_admin'] . ' / ' : '') ?>
              <?= htmlspecialchars($file['nrp_admin'] ? 'NRP ' . $file['nrp_admin'] : '') ?>
              <?= htmlspecialchars($file['satuan_admin'] ? ' &middot; ' . $file['satuan_admin'] : '') ?>
            </div>
          </td>
        </tr>
        <tr>
          <th>Waktu Pengesahan (TTE)</th>
          <td>
            <?= $file['verified_at'] ? date('d-m-Y H:i:s', strtotime($file['verified_at'])) . ' WIB' : '-' ?>
          </td>
        </tr>
        <tr>
          <th>Segel Dokumen Ber-TTE (SHA-256)</th>
          <td style="font-family:monospace;font-size:11px;color:var(--text-dim);word-break:break-all;">
            <?= htmlspecialchars($file['signature_hash'] ?: 'Tercatat dalam basis data') ?>
          </td>
        </tr>
        <?php if (!empty($file['raw_hash'])): ?>
        <tr>
          <th>Hash Warkat Asli (Master Bersih)</th>
          <td style="font-family:monospace;font-size:11px;color:var(--text-dim);word-break:break-all;">
            <?= htmlspecialchars($file['raw_hash']) ?>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <th>Status Fisik di Peladen</th>
          <td>
            <?php if ($diskIntegrityStatus === 'MATCH'): ?>
              <span style="color:var(--ok);font-size:12px;font-weight:600;">✓ Berkas Fisik di Server Terverifikasi Utuh &amp; Tidak Mengalami Perubahan</span>
            <?php elseif ($diskIntegrityStatus === 'MISMATCH'): ?>
              <span style="color:var(--danger);font-size:12px;font-weight:600;">⚠ PERINGATAN: Berkas fisik di peladen telah dimodifikasi!</span>
            <?php else: ?>
              <span style="color:var(--text-dim);font-size:12px;">Arsip Digital Tersimpan</span>
            <?php endif; ?>
          </td>
        </tr>
      </table>

      <div style="margin-top:16px;padding:12px;background:var(--panel-2);border-radius:8px;font-size:12px;color:var(--text-dim);line-height:1.5;">
        ★ <strong>Jaminan Keaslian:</strong> Dokumen dosir elektronik ini telah diverifikasi dan disahkan menggunakan sertifikasi digital TRISULA TNI AD. Data di atas cocok dengan catatan fisik dan arsip digital resmi.
      </div>

      <div style="text-align:center;margin-top:20px;">
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline" style="font-size:12.5px;">Masuk ke Portal TRISULA</a>
      </div>
    </div>
  <?php elseif ($error): ?>
    <div class="card invalid-card" style="text-align:center;padding:32px 20px;">
      <div style="font-size:44px;margin-bottom:8px;color:var(--danger);">✕</div>
      <h3 style="margin:0 0 8px;color:var(--danger);">Dokumen Tidak Sah atau Tidak Ditemukan</h3>
      <p style="color:var(--text-dim);font-size:13.5px;max-width:520px;margin:0 auto 20px;line-height:1.5;">
        <?= htmlspecialchars($error) ?>
      </p>
      <a href="<?= BASE_URL ?>/verify.php" class="btn" style="font-size:13px;padding:8px 20px;">Coba Uji Dokumen Lain</a>
    </div>
  <?php endif; ?>

  <div style="text-align:center;margin-top:24px;font-size:12px;color:var(--text-dim);line-height:1.6;">
  <?= base64_decode("PGRpdiBzdHlsZT0ibWFyZ2luLXRvcDozcHg7Ij5DcmFmdCBieSA8c3Ryb25nIHN0eWxlPSJjb2xvcjp2YXIoLS1nb2xkKTsiPkxldGRhIEN6aSBGcmlzIFdhcmRhbmk8L3N0cm9uZz48L2Rpdj4="); ?>
  </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    
    if (tabId === 'tabCode') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tabCode').classList.add('active');
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('tabUpload').classList.add('active');
    }
}
</script>
</body>
</html>
