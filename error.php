<?php
/**
 * TRISULA TNI AD - MASTER ERROR HANDLING TEMPLATE
 * Handles 401 Unauthorized, 403 Forbidden, 404 Not Found, 500 Server Error
 * Compatible with Apache ErrorDocument directives & direct PHP abort() calls.
 */

// 1. Tentukan Kode Status HTTP
$code = 404;
if (isset($errorCode) && is_numeric($errorCode)) {
    $code = (int) $errorCode;
} elseif (isset($_GET['code']) && is_numeric($_GET['code'])) {
    $code = (int) $_GET['code'];
} elseif (isset($_SERVER['REDIRECT_STATUS']) && is_numeric($_SERVER['REDIRECT_STATUS'])) {
    $code = (int) $_SERVER['REDIRECT_STATUS'];
}

// Validasi kode status yang didukung
$supportedCodes = [400, 401, 403, 404, 500, 503];
if (!in_array($code, $supportedCodes, true)) {
    $code = 404;
}

// Kirim header status HTTP resmi
if (!headers_sent()) {
    http_response_code($code);
}

// 2. Muat Konfigurasi Sistem dengan Aman (Fallback jika Database Mati)
$appName       = 'TRISULA TNI AD';
$appBrandTitle = 'TRISULA';
$appBrandSub   = 'TNI AD';
$baseUrl       = '';
$appLogo       = null;

// Coba muat config jika belum dimuat
if (!defined('APP_ROOT')) {
    $configPath = __DIR__ . '/config/config.php';
    if (file_exists($configPath)) {
        try {
            // Tangani silent error jika DB mati saat dipanggil
            @require_once $configPath;
        } catch (Throwable $e) {
            // Tetap gunakan fallback
        }
    }
}

if (defined('BASE_URL')) {
    $baseUrl = BASE_URL;
} else {
    // Deteksi cerdas fallback jika config.php gagal dimuat
    $serverName = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    $isLocal = in_array(strtolower(explode(':', $serverName)[0]), ['localhost', '127.0.0.1', '::1'], true);
    $baseUrl = $isLocal ? '/edosir-tni' : '';
}
if (defined('APP_NAME')) {
    $appName = APP_NAME;
}
if (isset($pdo) && function_exists('get_setting')) {
    try {
        $appBrandTitle = get_setting($pdo, 'app_brand_title', 'TRISULA');
        $appBrandSub   = get_setting($pdo, 'app_brand_sub', 'TNI AD');
        if (function_exists('app_logo_url')) {
            $appLogo = app_logo_url();
        }
    } catch (Throwable $e) {
        // Fallback jika DB mati
    }
}

// Sesi / User info jika ada
$currentUser = null;
if (function_exists('current_user')) {
    $currentUser = current_user();
} elseif (isset($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
}

// 3. Konfigurasi Konten Spesifik Tiap Error Code
$errorMap = [
    400 => [
        'badge'       => 'PERMINTAAN TIDAK VALID // BAD REQUEST',
        'substatus'   => '400 - INVALID_REQUEST_SYNTAX',
        'title'       => 'Permintaan Data Tidak Sesuai Format',
        'desc'        => 'Format permintaan yang dikirimkan oleh peramban atau klien tidak dapat diproses oleh server sistem pertahanan TRISULA TNI AD.',
        'btn_primary' => ['label' => 'Kembali ke Beranda', 'url' => $baseUrl . '/index.php'],
        'btn_secondary' => ['label' => 'Halaman Sebelumnya', 'url' => 'javascript:history.back()'],
    ],
    401 => [
        'badge'       => 'OTENTIKASI DIPERLUKAN // ACCESS UNAUTHORIZED',
        'substatus'   => '401 - ACCESS_TOKEN_REQUIRED',
        'title'       => 'Sesi Pengguna Tidak Terverifikasi',
        'desc'        => 'Akses ke berkas atau modul ini memerlukan otentikasi identitas yang sah. Sesi Anda mungkin telah kedaluwarsa demi keamanan militer atau Anda belum masuk ke sistem TRISULA TNI AD.',
        'btn_primary' => ['label' => 'Masuk ke Sistem (Login)', 'url' => $baseUrl . '/login.php'],
        'btn_secondary' => ['label' => 'Kembali ke Beranda', 'url' => $baseUrl . '/index.php'],
    ],
    403 => [
        'badge'       => 'PROTOKOL KEAMANAN AKTIF // ACCESS RESTRICTED',
        'substatus'   => '403 - PRIVILEGE_INSUFFICIENT',
        'title'       => 'Akses Dibatasi oleh Protokol Keamanan',
        'desc'        => 'Sistem mendeteksi bahwa akun atau peran Anda tidak memiliki wewenang (Clearance Level) yang mencukupi untuk membuka dokumen arsip atau area administratif ini.',
        'btn_primary' => ['label' => 'Kembali ke Dashboard', 'url' => $baseUrl . '/index.php'],
        'btn_secondary' => ['label' => 'Ganti Akun / Keluar', 'url' => $baseUrl . '/logout.php'],
    ],
    404 => [
        'badge'       => 'SUMBER DAYA NIHIL // RESOURCE NOT FOUND',
        'substatus'   => '404 - DOSIR_OR_ROUTE_UNAVAILABLE',
        'title'       => 'Dokumen atau Halaman Tidak Ditemukan',
        'desc'        => 'Alamat tautan atau berkas dosir digital yang Anda cari tidak terdaftar pada pangkalan data kami. Dokumen mungkin telah dipindahkan, diarsipkan, atau alamat yang dimasukkan keliru.',
        'btn_primary' => ['label' => 'Kembali ke Beranda', 'url' => $baseUrl . '/index.php'],
        'btn_secondary' => ['label' => 'Uji Verifikasi QR', 'url' => $baseUrl . '/verify.php'],
    ],
    500 => [
        'badge'       => 'ANOMALI SISTEM PUSAT // SERVER INTERNAL ERROR',
        'substatus'   => '500 - CORE_ANOMALY_RECORDED',
        'title'       => 'Terjadi Hambatan Pemrosesan Sistem',
        'desc'        => 'Server mendeteksi hambatan pada saat mengeksekusi instruksi data atau koneksi pangkalan data pangkalan induk. Kode insiden dan waktu kejadian telah dicatat oleh sistem keamanan.',
        'btn_primary' => ['label' => 'Muat Ulang Halaman', 'url' => 'javascript:location.reload()'],
        'btn_secondary' => ['label' => 'Kembali ke Beranda', 'url' => $baseUrl . '/index.php'],
    ],
    503 => [
        'badge'       => 'PEMELIHARAAN SISTEM // SERVICE UNAVAILABLE',
        'substatus'   => '503 - SYSTEM_MAINTENANCE_MODE',
        'title'       => 'Layanan Sedang dalam Pemeliharaan',
        'desc'        => 'Sistem TRISULA TNI AD sedang menjalani proses sinkronisasi pangkalan data rutin atau peningkatan keamanan. Silakan akses kembali beberapa saat lagi.',
        'btn_primary' => ['label' => 'Cek Ulang Layanan', 'url' => 'javascript:location.reload()'],
        'btn_secondary' => ['label' => 'Kembali ke Beranda', 'url' => $baseUrl . '/index.php'],
    ]
];

$activeConfig = $errorMap[$code] ?? $errorMap[404];

// Izinkan penyesuaian kustom jika dipanggil dari fungsi PHP (misal abort())
$displayTitle = isset($customTitle) && $customTitle !== '' ? $customTitle : $activeConfig['title'];
$displayDesc  = isset($customMessage) && $customMessage !== '' ? $customMessage : $activeConfig['desc'];

// 4. Buat Kode Pelaporan Insiden Unik
$reqUri       = $_SERVER['REQUEST_URI'] ?? '/';
$clientIp     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$timestampStr = date('d M Y, H:i:s') . ' WIB';
$incidentHash = 'TRISULA-SEC-' . strtoupper(substr(md5($reqUri . $timestampStr . $clientIp . $code), 0, 8));

$cssVer = file_exists(__DIR__ . '/assets/css/error-modern.css') ? filemtime(__DIR__ . '/assets/css/error-modern.css') : time();
$jsVer  = file_exists(__DIR__ . '/assets/js/error-modern.js') ? filemtime(__DIR__ . '/assets/js/error-modern.js') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= $code ?> &bull; <?= htmlspecialchars($displayTitle) ?> | <?= htmlspecialchars($appName) ?></title>
  <link rel="icon" type="image/png" href="<?= $appLogo ?: ($baseUrl . '/assets/img/logo_1789696457.png') ?>">
  
  <!-- Modern Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Rajdhani:wght@500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  
  <!-- Error Stylesheet -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/error-modern.css?v=<?= $cssVer ?>">
</head>
<body data-error="<?= $code ?>">

  <div class="error-wrapper">
    <!-- Tactical Radar Canvas -->
    <canvas id="radarCanvas"></canvas>

    <!-- Topbar Header -->
    <header class="error-topbar">
      <a href="<?= $baseUrl ?>/index.php" class="brand-link" title="Beranda Trisula TNI AD">
        <?php if ($appLogo): ?>
          <img src="<?= $appLogo ?>" alt="Logo Trisula" class="brand-logo">
        <?php else: ?>
          <img src="<?= $baseUrl ?>/assets/img/logo_1789696457.png" alt="Logo TNI" class="brand-logo">
        <?php endif; ?>
        <div class="brand-text">
          <span class="brand-title"><?= htmlspecialchars($appBrandTitle) ?></span>
          <span class="brand-sub"><?= htmlspecialchars($appBrandSub) ?></span>
        </div>
      </a>

      <div class="topbar-right-info">
        <div class="system-status-indicator">
          <span class="status-beacon"></span>
          <span>SYSTEM GATEWAY <?= $code ?></span>
        </div>
      </div>
    </header>

    <!-- Main Content Center -->
    <main class="error-main">
      <div class="error-card-container" id="errorCardContainer">
        <div class="error-card">
          
          <!-- Status Pill Badge -->
          <div class="status-pill">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <?php if ($code === 401): ?>
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              <?php elseif ($code === 403): ?>
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <line x1="9" y1="9" x2="15" y2="15"></line>
                <line x1="15" y1="9" x2="9" y2="15"></line>
              <?php elseif ($code === 500 || $code === 503): ?>
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
              <?php else: /* 404 & default */ ?>
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                <line x1="11" y1="8" x2="11" y2="12"></line>
                <line x1="11" y1="14" x2="11.01" y2="14"></line>
              <?php endif; ?>
            </svg>
            <span><?= htmlspecialchars($activeConfig['badge']) ?></span>
          </div>

          <!-- Big Holographic Status Code -->
          <div class="error-code-wrapper">
            <span class="error-code-number" data-text="<?= $code ?>"><?= $code ?></span>
          </div>

          <!-- Title and Description -->
          <h1 class="error-title"><?= htmlspecialchars($displayTitle) ?></h1>
          <p class="error-description"><?= htmlspecialchars($displayDesc) ?></p>

          <!-- Tactical Diagnostic Telemetry Box -->
          <div class="tactical-diagnostic-box">
            <div class="diagnostic-row">
              <span class="diagnostic-label">KODE INSIDEN</span>
              <span class="diagnostic-value diagnostic-incident-code" id="incidentCodeVal"><?= htmlspecialchars($incidentHash) ?></span>
            </div>
            <div class="diagnostic-row">
              <span class="diagnostic-label">TARGET URI</span>
              <span class="diagnostic-value"><?= htmlspecialchars(mb_strimwidth($reqUri, 0, 48, '...')) ?></span>
            </div>
            <div class="diagnostic-row">
              <span class="diagnostic-label">WAKTU SISTEM</span>
              <span class="diagnostic-value"><?= htmlspecialchars($timestampStr) ?></span>
            </div>
            <div class="diagnostic-row">
              <span class="diagnostic-label">IDENTITAS KLIEN</span>
              <span class="diagnostic-value"><?= htmlspecialchars($clientIp) ?> (<?= htmlspecialchars($currentUser ? ($currentUser['nama'] ?? $currentUser['username']) . ' &bull; ' . strtoupper($currentUser['role']) : 'Tamu / Belum Login') ?>)</span>
            </div>
            <?php if (!empty($customDetail)): ?>
              <div class="diagnostic-row">
                <span class="diagnostic-label">CATATAN TEKNIS</span>
                <span class="diagnostic-value" style="color:var(--status-color);"><?= htmlspecialchars($customDetail) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Action Buttons Group -->
          <div class="error-actions-group">
            <!-- Primary Action Button -->
            <a href="<?= htmlspecialchars($activeConfig['btn_primary']['url']) ?>" class="error-btn error-btn-primary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <?php if ($code === 401): ?>
                  <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                  <polyline points="10 17 15 12 10 7"></polyline>
                  <line x1="15" y1="12" x2="3" y2="12"></line>
                <?php elseif ($code === 500): ?>
                  <path d="M23 4v6h-6"></path>
                  <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                <?php else: ?>
                  <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                  <polyline points="9 22 9 12 15 12 15 22"></polyline>
                <?php endif; ?>
              </svg>
              <span><?= htmlspecialchars($activeConfig['btn_primary']['label']) ?></span>
            </a>

            <!-- Secondary Action Button -->
            <a href="<?= htmlspecialchars($activeConfig['btn_secondary']['url']) ?>" class="error-btn error-btn-secondary">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
              </svg>
              <span><?= htmlspecialchars($activeConfig['btn_secondary']['label']) ?></span>
            </a>

            <!-- Back to previous button -->
            <button type="button" class="error-btn error-btn-ghost" onclick="window.history.back()">
              <span>Kembali</span>
            </button>
          </div>

        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="error-footer">
      <div>
        &copy; <?= date('Y') ?> <strong><?= htmlspecialchars($appName) ?></strong> &bull; Tata Kelola Arsip Dosir Elektronik TNI AD
      </div>
    </footer>
  </div>

  <!-- Error Interaction JS -->
  <script src="<?= $baseUrl ?>/assets/js/error-modern.js?v=<?= $jsVer ?>"></script>
</body>
</html>
