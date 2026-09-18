<?php
require_once __DIR__ . '/config/config.php';

$code = trim($_GET['code'] ?? '');
$file = null;
$error = null;

if ($code !== '') {
    $stmt = $pdo->prepare("
        SELECT f.*, 
               p.nama as nama_personel, p.nrp, p.pangkat as pangkat_personel, p.satuan as satuan_personel, p.golongan,
               m.nama_dosir,
               u.username as admin_username,
               admin_p.nama as nama_admin, admin_p.pangkat as pangkat_admin, admin_p.nrp as nrp_admin, admin_p.satuan as satuan_admin
        FROM dosir_files f
        JOIN personel p ON p.id = f.personel_id
        JOIN dosir_master m ON m.kode = f.dosir_kode
        LEFT JOIN users u ON u.id = f.verified_by
        LEFT JOIN personel admin_p ON admin_p.id = u.personel_id
        WHERE f.signature_code = ?
    ");
    $stmt->execute([$code]);
    $file = $stmt->fetch();

    if (!$file || $file['status'] !== 'approved') {
        $error = 'Dokumen dengan kode verifikasi tersebut tidak ditemukan atau belum berstatus disahkan.';
    }
} else {
    $error = 'Kode verifikasi Tanda Tangan Elektronik (TTE) tidak disertakan.';
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
    max-width: 680px;
    margin: 40px auto;
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
    padding: 5px 12px;
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
    width: 38%;
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
      Layanan Otentikasi &amp; Verifikasi Tanda Tangan Elektronik (TTE) &middot; <?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?>
    </div>
  </div>

  <?php if ($file && $file['status'] === 'approved'): ?>
    <div class="card valid-card">
      <div style="text-align:center;padding-bottom:14px;border-bottom:1px solid var(--border);">
        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(46,139,87,0.15);color:var(--ok);border:1px solid var(--ok);padding:6px 14px;border-radius:20px;font-weight:700;font-size:13px;">
          ✓ DOKUMEN SAH &amp; TERVERIFIKASI SECARA ELEKTRONIK
        </div>
        <div style="margin-top:10px;">
          <span class="tte-code-pill"><?= htmlspecialchars($file['signature_code']) ?></span>
        </div>
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
          <th>Integritas Dokumen (SHA-256)</th>
          <td style="font-family:monospace;font-size:11px;color:var(--text-dim);word-break:break-all;">
            <?= htmlspecialchars($file['signature_hash'] ?: 'Tercatat dalam basis data') ?>
          </td>
        </tr>
      </table>

      <div style="margin-top:16px;padding:12px;background:var(--panel-2);border-radius:8px;font-size:12px;color:var(--text-dim);line-height:1.5;">
        ★ <strong>Jaminan Keaslian:</strong> Dokumen dosir elektronik ini telah diverifikasi dan disahkan menggunakan sertifikasi digital internal E-DOSIR TNI AD. Data di atas cocok dengan catatan fisik dan arsip digital resmi.
      </div>

      <div style="text-align:center;margin-top:20px;">
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline" style="font-size:12.5px;">Masuk ke Portal E-DOSIR</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card invalid-card" style="text-align:center;padding:32px 20px;">
      <div style="font-size:44px;margin-bottom:8px;color:var(--danger);">✕</div>
      <h3 style="margin:0 0 8px;color:var(--danger);">Dokumen Tidak Sah atau Tidak Ditemukan</h3>
      <p style="color:var(--text-dim);font-size:13.5px;max-width:480px;margin:0 auto 20px;line-height:1.5;">
        <?= htmlspecialchars($error ?: 'Kode verifikasi yang dipindai tidak terdaftar dalam pangkalan data E-DOSIR TNI AD atau berkas belum disahkan secara resmi.') ?>
      </p>
      <a href="<?= BASE_URL ?>/login.php" class="btn" style="font-size:13px;padding:8px 20px;">Kembali ke Halaman Utama</a>
    </div>
  <?php endif; ?>

  <div style="text-align:center;margin-top:24px;font-size:12px;color:var(--text-dim);line-height:1.6;">
  <?= base64_decode("PGRpdiBzdHlsZT0ibWFyZ2luLXRvcDozcHg7Ij5DcmFmdCBieSA8c3Ryb25nIHN0eWxlPSJjb2xvcjp2YXIoLS1nb2xkKTsiPkxldGRhIEN6aSBGcmlzIFdhcmRhbmk8L3N0cm9uZz48L2Rpdj4="); ?>
  </div>
</div>
</body>
</html>
