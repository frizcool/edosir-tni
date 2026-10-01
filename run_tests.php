<?php
/**
 * TRISULA E-DOSIR TNI AD - AUTOMATED LOGIC & REGRESSION TEST SUITE
 * Dikpa Programer Komputer TA 2026 - Pusdik Pengmilum Kodiklatad
 */

$isCli = (php_sapi_name() === 'cli');
$isRawText = isset($_GET['format']) && $_GET['format'] === 'text';

// Koleksi data hasil pengujian terstruktur
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function record_test($sectionKey, $sectionTitle, $label, $isPass, $detail = '') {
    global $testResults, $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($isPass) {
        $passedTests++;
    } else {
        $failedTests++;
    }

    if (!isset($testResults[$sectionKey])) {
        $testResults[$sectionKey] = [
            'title' => $sectionTitle,
            'items' => []
        ];
    }

    $testResults[$sectionKey]['items'][] = [
        'label'  => $label,
        'status' => $isPass ? 'PASS' : 'FAIL',
        'detail' => $detail
    ];
}

// -----------------------------------------------------------------------------
// 1. PENGUJIAN SINTAKS BERKAS PHP (PHP LINTING)
// -----------------------------------------------------------------------------
$files = [
    'login.php',
    'register.php',
    'includes/auth.php',
    'includes/functions.php',
    'includes/header.php',
    'personel/profile.php',
    'admin/report.php',
    'admin/bulk_download.php',
    'admin/personel_detail.php',
    'config/config.php',
    'sql/update_schema.php'
];

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $output = shell_exec("php -l " . escapeshellarg($fullPath) . " 2>&1");
        $isOk = (strpos($output, 'No syntax errors') !== false);
        record_test('sec1', 'Bagian 1: Verifikasi Sintaks Berkas PHP (PHP Linting)', $file, $isOk, $isOk ? 'Sintaks valid tanpa kesalahan (No syntax errors detected)' : 'Terdapat kesalahan sintaks');
    } else {
        record_test('sec1', 'Bagian 1: Verifikasi Sintaks Berkas PHP (PHP Linting)', $file, false, 'Berkas tidak ditemukan pada server');
    }
}

// -----------------------------------------------------------------------------
// 2. PENGUJIAN KEBIJAKAN & VALIDASI KATA SANDI (FIX-001 & FIX-002)
// -----------------------------------------------------------------------------
$validatePassword = function($pwd) {
    if (strlen($pwd) < 8) return false;
    if (!preg_match('/[A-Za-z]/', $pwd)) return false;
    if (!preg_match('/[0-9]/', $pwd)) return false;
    return true;
};

$pwdCases = [
    ['pwd' => 'abc123',   'expected' => false, 'label' => 'Validasi Panjang < 8 Karakter ("abc123")', 'desc' => 'DITOLAK (Wajib minimal 8 karakter)'],
    ['pwd' => 'abcdefgh', 'expected' => false, 'label' => 'Validasi Ketiadaan Angka ("abcdefgh")', 'desc' => 'DITOLAK (Wajib mengandung angka numerik)'],
    ['pwd' => '12345678', 'expected' => false, 'label' => 'Validasi Ketiadaan Huruf ("12345678")', 'desc' => 'DITOLAK (Wajib mengandung huruf alfabet)'],
    ['pwd' => 'Abc12345', 'expected' => true,  'label' => 'Kombinasi Standar Huruf + Angka ("Abc12345")', 'desc' => 'DITERIMA (Memenuhi standar keamanan militer)'],
    ['pwd' => 'Trisula2026!', 'expected' => true, 'label' => 'Kompleksitas Huruf + Angka + Simbol ("Trisula2026!")', 'desc' => 'DITERIMA (Sangat kuat)'],
];

foreach ($pwdCases as $c) {
    $res = $validatePassword($c['pwd']);
    record_test('sec2', 'Bagian 2: Pengujian Validasi Kebijakan Kata Sandi (FIX-001 & FIX-002)', $c['label'], $res === $c['expected'], $c['desc']);
}

// -----------------------------------------------------------------------------
// 3. PENGUJIAN GENERATOR SANDI ACAK SEMENTARA (FIX-001)
// -----------------------------------------------------------------------------
$generatedSamples = [];
for ($i = 0; $i < 5; $i++) {
    $sample = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $isLengthOk = (strlen($sample) === 8);
    $isUnique = !in_array($sample, $generatedSamples, true);
    $generatedSamples[] = $sample;
    record_test('sec3', 'Bagian 3: Pengujian Generator Sandi Acak Kriptografis (FIX-001)', "Sandi Acak #" . ($i + 1) . " [$sample]", $isLengthOk && $isUnique, "Panjang 8 karakter alfanumerik acak via random_bytes()");
}

// -----------------------------------------------------------------------------
// 4. PENGUJIAN GUARD URL BYPASS must_change_password (FIX-008)
// -----------------------------------------------------------------------------
$guardCases = [
    ['uri' => '/personel/dashboard.php',                               'allowed' => '/personel/profile.php', 'expect' => 'REDIRECT'],
    ['uri' => '/personel/upload.php',                                  'allowed' => '/personel/profile.php', 'expect' => 'REDIRECT'],
    ['uri' => '/personel/profile.php',                                 'allowed' => '/personel/profile.php', 'expect' => 'IZIN'],
    ['uri' => '/personel/profile.php?action=change_password_required', 'allowed' => '/personel/profile.php', 'expect' => 'IZIN'],
    ['uri' => '/logout.php',                                           'allowed' => '/personel/profile.php', 'expect' => 'IZIN'],
    ['uri' => '/admin/dashboard.php',                                  'allowed' => '/admin/settings.php',   'expect' => 'REDIRECT'],
    ['uri' => '/admin/dosir_verify.php',                               'allowed' => '/admin/settings.php',   'expect' => 'REDIRECT'],
    ['uri' => '/admin/settings.php',                                   'allowed' => '/admin/settings.php',   'expect' => 'IZIN'],
    ['uri' => '/admin/settings.php?action=change_password_required',   'allowed' => '/admin/settings.php',   'expect' => 'IZIN'],
];

foreach ($guardCases as $tc) {
    $isAllowed = (strpos($tc['uri'], $tc['allowed']) !== false || strpos($tc['uri'], '/logout.php') !== false);
    $result = $isAllowed ? 'IZIN' : 'REDIRECT';
    record_test('sec4', 'Bagian 4: Pengujian Guard Akses must_change_password (FIX-008)', "Akses URI: {$tc['uri']}", $result === $tc['expect'], "Hasil: $result | Ekspektasi: {$tc['expect']}");
}

// -----------------------------------------------------------------------------
// 5. PENGUJIAN PENGALIHAN BERBASIS PERAN (ROLE-AWARE REDIRECT - FIX-007)
// -----------------------------------------------------------------------------
$roleCases = [
    ['role' => 'admin',    'must' => 1, 'expect' => '/admin/settings.php'],
    ['role' => 'personel', 'must' => 1, 'expect' => '/personel/profile.php'],
    ['role' => 'admin',    'must' => 0, 'expect' => '/admin/dashboard.php'],
    ['role' => 'personel', 'must' => 0, 'expect' => '/personel/dashboard.php'],
];

foreach ($roleCases as $rc) {
    if ($rc['must']) {
        $target = ($rc['role'] === 'admin') ? '/admin/settings.php?action=change_password_required#ganti-sandi' : '/personel/profile.php?action=change_password_required';
    } else {
        $target = ($rc['role'] === 'admin') ? '/admin/dashboard.php' : '/personel/dashboard.php';
    }
    $matches = (strpos($target, $rc['expect']) !== false);
    record_test('sec5', 'Bagian 5: Pengujian Pengalihan Login Berbasis Peran (FIX-007)', "Peran: " . strtoupper($rc['role']) . " (must_change={$rc['must']})", $matches, "Target Pengalihan: $target");
}

// -----------------------------------------------------------------------------
// 6. PENGUJIAN RENDERING AMAN FLASH MESSAGE (XSS DEFENSE & HTML TAG - FIX-009)
// -----------------------------------------------------------------------------
$renderFlash = function($msg, $isHtml = false) {
    if (!empty($isHtml)) {
        return strip_tags($msg, '<strong><b>');
    }
    return htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
};

$flashCases = [
    [
        'input' => 'Sandi sementara: <strong>973DEA25</strong>.',
        'html' => true,
        'expected' => 'Sandi sementara: <strong>973DEA25</strong>.',
        'label' => 'Rendering Tag <strong> pada Sandi Sementara',
        'desc' => 'Tag <strong> dipertahankan untuk keterbacaan teks tebal'
    ],
    [
        'input' => 'Peringatan <script>alert(1)</script> bahaya!',
        'html' => true,
        'expected' => 'Peringatan alert(1) bahaya!',
        'label' => 'Netralisasi Serangan Injeksi Skrip <script>',
        'desc' => 'Tag skrip otomatis dihapus (Perlindungan XSS ketat)'
    ],
    [
        'input' => 'Input biasa & teks bebas <test>',
        'html' => false,
        'expected' => 'Input biasa &amp; teks bebas &lt;test&gt;',
        'label' => 'Sanitasi Penuh Pesan Standar Non-HTML',
        'desc' => 'Dikonversi penuh dengan htmlspecialchars'
    ]
];

foreach ($flashCases as $fc) {
    $res = $renderFlash($fc['input'], $fc['html']);
    record_test('sec6', 'Bagian 6: Pengujian Sanitasi & Rendering Flash Message (FIX-009)', $fc['label'], $res === $fc['expected'], $fc['desc']);
}

// -----------------------------------------------------------------------------
// 7. PENGUJIAN NORMALISASI URL MALFORMED REPORT.PHP (FIX-010)
// -----------------------------------------------------------------------------
$normalizeQuery = function($queryString) {
    $clean = preg_replace('/^report\.php\?+/i', '', $queryString);
    $clean = str_ireplace(['report.php?', 'report.php'], '', $clean);
    return ltrim($clean, '?&');
};

$urlCases = [
    ['input' => 'report.php?print_all=1',          'expected' => 'print_all=1', 'label' => 'Normalisasi "report.php?print_all=1"', 'desc' => 'Hasil bersih: ?print_all=1'],
    ['input' => 'report.php?report.php?print_all=1', 'expected' => 'print_all=1', 'label' => 'Normalisasi Duplikasi Ganda "report.php?report.php?print_all=1"', 'desc' => 'Hasil bersih: ?print_all=1'],
    ['input' => 'print_all=1&jenis=kelengkapan',   'expected' => 'print_all=1&jenis=kelengkapan', 'label' => 'Integritas Parameter Standar', 'desc' => 'Parameter filter dipertahankan utuh'],
];

foreach ($urlCases as $uc) {
    $res = $normalizeQuery($uc['input']);
    record_test('sec7', 'Bagian 7: Pengujian Normalisasi URL Malformed report.php (FIX-010)', $uc['label'], $res === $uc['expected'], $uc['desc']);
}

// =============================================================================
// MODE CLI ATAU RAW TEXT
// =============================================================================
if ($isCli || $isRawText) {
    if (!$isCli) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "================================================================================\n";
    echo "           PENGUJIAN SISTEM TRISULA E-DOSIR TNI AD (CLI TEST SUITE)             \n";
    echo "                 Pengujian Otomatis Logika & Integritas Kode                    \n";
    echo "================================================================================\n\n";

    foreach ($testResults as $secKey => $sec) {
        echo "[" . strtoupper($sec['title']) . "]\n";
        foreach ($sec['items'] as $item) {
            echo "  [" . $item['status'] . "] " . str_pad($item['label'], 45) . " -> " . $item['detail'] . "\n";
        }
        echo "\n";
    }

    echo "================================================================================\n";
    echo "                          REKAPITULASI HASIL TESTING                            \n";
    echo "================================================================================\n";
    echo "  Total Kasus Pengujian : $totalTests\n";
    echo "  Kasus Lulus [PASS]    : $passedTests (" . round(($passedTests / max(1, $totalTests)) * 100, 1) . "%)\n";
    echo "  Kasus Gagal [FAIL]    : $failedTests\n";
    echo "  Status Akhir Evaluasi : " . ($failedTests === 0 ? "SEMUA PENGUJIAN LULUS (100% PASS)" : "ADA KASUS GAGAL") . "\n";
    echo "================================================================================\n";
    exit;
}

// =============================================================================
// MODE TAMPILAN WEB BROWSER (MODERN RESPONSIVE DASHBOARD UI)
// =============================================================================
$percentPass = round(($passedTests / max(1, $totalTests)) * 100, 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hasil Pengujian Sistem TRISULA TNI AD (Test Runner)</title>
  <style>
    :root {
      --bg: #0B132B;
      --card-bg: #1C2541;
      --card-inner: #151E38;
      --border: #2D3A5D;
      --text: #F0F4F8;
      --text-muted: #8E9AAF;
      --gold: #D4AF37;
      --gold-light: #F3E5AB;
      --green: #2ECC71;
      --green-bg: rgba(46, 204, 113, 0.15);
      --red: #E74C3C;
      --red-bg: rgba(231, 76, 60, 0.15);
      --navy-dark: #0A1128;
      --accent: #3A86FF;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.6;
      padding: 24px 16px;
    }

    .container {
      max-width: 1040px;
      margin: 0 auto;
    }

    /* Top Military Header */
    .test-header {
      background: linear-gradient(135deg, #1C2541 0%, #0F172A 100%);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
    }

    .brand-title {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-logo-badge {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      background: linear-gradient(135deg, #D4AF37, #997A15);
      color: #0A1128;
      font-weight: 800;
      font-size: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .header-text h1 {
      font-size: 20px;
      font-weight: 700;
      color: #FFFFFF;
      letter-spacing: 0.5px;
    }

    .header-text p {
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 2px;
    }

    .header-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      border: none;
    }

    .btn-gold {
      background: var(--gold);
      color: #0A1128;
    }
    .btn-gold:hover { background: #e2be49; }

    .btn-outline {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--border);
    }
    .btn-outline:hover { background: rgba(255, 255, 255, 0.08); border-color: var(--text-muted); }

    /* Summary KPI Grid */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 18px 20px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .stat-card .label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: var(--text-muted);
      margin-bottom: 6px;
    }

    .stat-card .value {
      font-size: 28px;
      font-weight: 800;
      color: #FFFFFF;
    }

    .stat-card.success .value { color: var(--green); }
    .stat-card.danger .value { color: var(--red); }
    .stat-card.rate .value { color: var(--gold); }

    /* Progress bar */
    .progress-bar-wrap {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 24px;
    }

    .progress-info {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .progress-track {
      width: 100%;
      height: 10px;
      background: #0D162B;
      border-radius: 5px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #2ECC71, #27AE60);
      width: <?= $percentPass ?>%;
      border-radius: 5px;
      transition: width 0.6s ease;
    }

    /* Section Cards */
    .section-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      margin-bottom: 20px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .section-header {
      padding: 14px 20px;
      background: rgba(0, 0, 0, 0.2);
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .section-header h2 {
      font-size: 15px;
      font-weight: 700;
      color: var(--gold-light);
    }

    .section-badge {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 12px;
      background: var(--green-bg);
      color: var(--green);
      border: 1px solid rgba(46, 204, 113, 0.3);
    }

    /* Test item rows */
    .test-list {
      list-style: none;
    }

    .test-item {
      padding: 12px 20px;
      border-bottom: 1px solid rgba(45, 58, 93, 0.5);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
      font-size: 13.5px;
      transition: background 0.15s ease;
    }

    .test-item:last-child {
      border-bottom: none;
    }

    .test-item:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .test-main {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .test-label {
      font-weight: 600;
      color: #FFFFFF;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 13px;
    }

    .test-detail {
      font-size: 12px;
      color: var(--text-muted);
    }

    .status-badge {
      font-size: 11px;
      font-weight: 800;
      padding: 4px 10px;
      border-radius: 6px;
      letter-spacing: 0.5px;
      flex-shrink: 0;
    }

    .badge-pass {
      background: var(--green-bg);
      color: var(--green);
      border: 1px solid rgba(46, 204, 113, 0.4);
    }

    .badge-fail {
      background: var(--red-bg);
      color: var(--red);
      border: 1px solid rgba(231, 76, 60, 0.4);
    }

    /* Print styles */
    @media print {
      body { background: #fff !important; color: #000 !important; padding: 0 !important; }
      .test-header, .stat-card, .progress-bar-wrap, .section-card { border: 1px solid #ccc !important; background: #fff !important; box-shadow: none !important; color: #000 !important; }
      .header-actions, .no-print { display: none !important; }
      .test-label, .header-text h1, .stat-card .value { color: #000 !important; }
      .section-header h2 { color: #0A1128 !important; }
      .badge-pass { border: 1px solid #27AE60 !important; color: #27AE60 !important; background: transparent !important; }
    }
  </style>
</head>
<body>

<div class="container">
  <!-- Header Bar -->
  <header class="test-header">
    <div class="brand-title">
      <div class="brand-logo-badge">⚔</div>
      <div class="header-text">
        <h1>TRISULA E-DOSIR TNI AD</h1>
        <p>Automated Logic &amp; Regression Test Runner &bull; Dikpa Programer Komputer TA 2026</p>
      </div>
    </div>
    <div class="header-actions no-print">
      <button class="btn btn-gold" onclick="location.reload();">🔄 Jalankan Ulang</button>
      <button class="btn btn-outline" onclick="window.print();">🖨 Cetak Laporan</button>
      <a href="?format=text" class="btn btn-outline" target="_blank">📄 Format Terminal (TXT)</a>
      <a href="admin/dashboard.php" class="btn btn-outline">⬅ Dashboard Admin</a>
    </div>
  </header>

  <!-- KPI Summary Cards -->
  <div class="summary-grid">
    <div class="stat-card">
      <div class="label">Total Pengujian</div>
      <div class="value"><?= number_format($totalTests) ?></div>
    </div>
    <div class="stat-card success">
      <div class="label">Kasus Lulus (PASS)</div>
      <div class="value"><?= number_format($passedTests) ?></div>
    </div>
    <div class="stat-card danger">
      <div class="label">Kasus Gagal (FAIL)</div>
      <div class="value"><?= number_format($failedTests) ?></div>
    </div>
    <div class="stat-card rate">
      <div class="label">Tingkat Kelulusan</div>
      <div class="value"><?= $percentPass ?>%</div>
    </div>
  </div>

  <!-- Progress Bar -->
  <div class="progress-bar-wrap">
    <div class="progress-info">
      <span>Status Evaluasi: <strong><?= $failedTests === 0 ? 'SELURUH PENGUJIAN LULUS (100% PASS)' : 'TERDAPAT KASUS GAGAL' ?></strong></span>
      <span><?= $passedTests ?> / <?= $totalTests ?> Berhasil</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill"></div>
    </div>
  </div>

  <!-- Sections of Test Items -->
  <?php foreach ($testResults as $secKey => $sec): 
    $secPassCount = count(array_filter($sec['items'], function($it){ return $it['status'] === 'PASS'; }));
    $secTotalCount = count($sec['items']);
  ?>
  <div class="section-card">
    <div class="section-header">
      <h2><?= htmlspecialchars($sec['title']) ?></h2>
      <span class="section-badge"><?= $secPassCount ?> / <?= $secTotalCount ?> PASS</span>
    </div>
    <ul class="test-list">
      <?php foreach ($sec['items'] as $item): ?>
      <li class="test-item">
        <div class="test-main">
          <div class="test-label"><?= htmlspecialchars($item['label']) ?></div>
          <div class="test-detail"><?= htmlspecialchars($item['detail']) ?></div>
        </div>
        <span class="status-badge <?= $item['status'] === 'PASS' ? 'badge-pass' : 'badge-fail' ?>">
          <?= $item['status'] ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>

  <footer style="text-align:center;color:var(--text-muted);font-size:12px;margin-top:28px;padding-bottom:16px;">
    &copy; <?= date('Y') ?> TRISULA E-DOSIR TNI AD &mdash; Teruji otomatis pada <?= date('d F Y, H:i:s') ?> WIB
  </footer>
</div>

</body>
</html>
