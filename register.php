<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect($_SESSION['user']['role'] === 'admin' ? '/admin/dashboard.php' : '/personel/dashboard.php');
}

$error   = null;
$success = null;
$successData = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $nrp           = strtoupper(trim($_POST['nrp'] ?? ''));
    $nama          = trim($_POST['nama'] ?? '');
    $golongan      = trim($_POST['golongan'] ?? '');
    $pangkat_id    = (int)($_POST['pangkat_id'] ?? 0) ?: null;
    $korp_id       = (int)($_POST['korp_id'] ?? 0) ?: null;
    $kotama_id     = (int)($_POST['kotama_id'] ?? 0) ?: null;
    $satuan_id_raw = trim($_POST['satuan_id'] ?? '');
    $satuan_id     = ($satuan_id_raw !== '' && $satuan_id_raw !== 'custom') ? ((int)$satuan_id_raw ?: null) : null;
    $satuan_custom = trim($_POST['satuan'] ?? '');
    $jabatan       = trim($_POST['jabatan'] ?? '');
    $tmt_jabatan   = !empty($_POST['tmt_jabatan']) ? $_POST['tmt_jabatan'] : null;
    $tmt_pangkat   = !empty($_POST['tmt_pangkat']) ? $_POST['tmt_pangkat'] : null;
    $tempat_lahir  = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = !empty($_POST['tanggal_lahir']) ? $_POST['tanggal_lahir'] : null;
    $jenis_kelamin = in_array($_POST['jenis_kelamin'] ?? 'L', ['L', 'P'], true) ? $_POST['jenis_kelamin'] : 'L';
    $no_hp         = trim($_POST['no_hp'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $password_conf = $_POST['password_confirm'] ?? '';

    // Validasi Data Wajib
    if ($nrp === '' || $nama === '' || $golongan === '') {
        $error = 'NRP/NIP, Nama Lengkap, dan Golongan Kepangkatan wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9\-\.\/]{4,20}$/', $nrp)) {
        $error = 'Format NRP tidak valid. NRP harus terdiri dari 4-20 karakter alfanumerik tanpa spasi.';
    } elseif (empty($pangkat_id)) {
        $error = 'Pangkat Resmi wajib dipilih sesuai golongan kepangkatan.';
    } elseif ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password))) {
        $error = 'Kata sandi minimal 8 karakter dan harus mengandung kombinasi huruf dan angka.';
    } elseif ($password !== '' && $password !== $password_conf) {
        $error = 'Konfirmasi kata sandi tidak cocok. Harap periksa kembali.';
    } else {
        try {
            // Cek duplikasi di tabel personel dan users
            $chk = $pdo->prepare("SELECT COUNT(*) FROM personel WHERE nrp = ?");
            $chk->execute([$nrp]);
            $chkUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $chkUser->execute([$nrp]);

            if ($chk->fetchColumn() > 0 || $chkUser->fetchColumn() > 0) {
                $error = 'NRP/NIP ' . htmlspecialchars($nrp) . ' sudah terdaftar pada sistem. Silakan login atau hubungi Administrator Satuan bila Anda memerlukan bantuan akun.';
            } else {
                $pdo->beginTransaction();

                $regData = [
                    'nrp'                  => $nrp,
                    'nama'                 => $nama,
                    'golongan'             => $golongan,
                    'pangkat_id'           => $pangkat_id,
                    'pangkat'              => '',
                    'korp_id'              => ($golongan === 'PNS') ? null : $korp_id,
                    'korp'                 => '',
                    'kotama_id'            => $kotama_id,
                    'kotama'               => '',
                    'satuan_id'            => $satuan_id,
                    'satuan'               => $satuan_custom,
                    'jabatan'              => $jabatan,
                    'tmt_jabatan'          => $tmt_jabatan,
                    'tmt_pangkat'          => $tmt_pangkat,
                    'tempat_lahir'         => $tempat_lahir,
                    'tanggal_lahir'        => $tanggal_lahir,
                    'jenis_kelamin'        => $jenis_kelamin,
                    'no_hp'                => $no_hp,
                    'email'                => $email,
                    'tmt_pensiun_proyeksi' => hitung_proyeksi_pensiun($tanggal_lahir, $golongan),
                ];

                // Selesaikan relasi alami ke master data (otomatis registrasi satuan kustom bila baru)
                resolve_and_save_personel_relations($pdo, $regData);

                // Deteksi kolom riil pada tabel personel (kompatibilitas fleksibel baik sebelum/sesudah migrasi 3NF)
                $personelCols = [];
                try {
                    $personelCols = $pdo->query("SHOW COLUMNS FROM personel")->fetchAll(PDO::FETCH_COLUMN);
                } catch (Throwable $eCols) {
                    $personelCols = [];
                }

                $candidateFields = [
                    'nrp'                  => $regData['nrp'],
                    'nama'                 => $regData['nama'],
                    'pangkat_id'           => $regData['pangkat_id'],
                    'korp_id'              => $regData['korp_id'],
                    'satuan_id'            => $regData['satuan_id'],
                    'kotama_id'            => $regData['kotama_id'],
                    'jabatan'              => $regData['jabatan'],
                    'tmt_jabatan'          => $regData['tmt_jabatan'],
                    'tmt_pangkat'          => $regData['tmt_pangkat'],
                    'tempat_lahir'         => $regData['tempat_lahir'],
                    'tanggal_lahir'        => $regData['tanggal_lahir'],
                    'jenis_kelamin'        => $regData['jenis_kelamin'],
                    'no_hp'                => $regData['no_hp'],
                    'email'                => $regData['email'],
                    'status_dinas'         => 'Aktif',
                    'tmt_pensiun_proyeksi' => $regData['tmt_pensiun_proyeksi'],
                    // Kolom warisan (fallback aman jika hosting belum drop kolom denormalisasi)
                    'golongan'             => $regData['golongan'],
                    'pangkat'              => $regData['pangkat'],
                    'korp'                 => $regData['korp'],
                    'satuan'               => $regData['satuan'],
                    'kotama'               => $regData['kotama'],
                ];

                $fieldsToInsert = [];
                foreach ($candidateFields as $colName => $colVal) {
                    if (empty($personelCols) || in_array($colName, $personelCols, true)) {
                        $fieldsToInsert[$colName] = $colVal;
                    }
                }

                $colsPart  = implode(',', array_map(fn($c) => "`$c`", array_keys($fieldsToInsert)));
                $marksPart = implode(',', array_fill(0, count($fieldsToInsert), '?'));

                $stmt = $pdo->prepare("INSERT INTO personel ($colsPart) VALUES ($marksPart)");
                $stmt->execute(array_values($fieldsToInsert));
                $personel_id = (int)$pdo->lastInsertId();

                // Otomatis buatkan akun user (username = NRP, password kustom atau fallback hash(NRP), status = pending)
                $userAccount = ensure_personel_user($pdo, $personel_id, $nrp, 'pending', $password ?: null);

                $pdo->commit();

                // Catat log aktivitas sistem
                $logUserId = !empty($userAccount['user_id']) ? (int)$userAccount['user_id'] : null;
                log_activity($pdo, $logUserId, 'REGISTER', "Pendaftaran personel baru: NRP $nrp ($nama)");

                $successData = [
                    'nrp'      => $nrp,
                    'nama'     => $nama,
                    'pangkat'  => $regData['pangkat'] ?: $golongan,
                    'satuan'   => $regData['satuan'] ?: 'TNI AD',
                    'password' => !empty($password) ? 'Kata sandi pribadi yang Anda tentukan' : (!empty($userAccount['temporary_password']) ? 'Sandi acak sementara: ' . $userAccount['temporary_password'] . ' (Wajib diganti saat login)' : 'Sandi acak dibuat otomatis'),
                ];
                $success = "Pendaftaran Akun Dosir Berhasil!";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Register Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $error = 'Terjadi kendala teknis saat memproses registrasi ke pangkalan data. Silakan periksa kembali data isian Anda atau laporkan ke Administrator Satuan.';
        }
    }
}

// Master Data Relasional untuk Dropdown Pendaftaran
$masterPangkatAll = get_master_pangkat_list($pdo);
$masterKorpAll    = get_master_korp_list($pdo);
$masterKotamaAll  = get_master_kotama_list($pdo);
$masterSatuanAll  = get_master_satuan_list($pdo);

$korpByKategori = [];
foreach ($masterKorpAll as $mk) {
    $korpByKategori[$mk['kategori']][] = $mk;
}

$appName        = get_setting($pdo, 'app_name', APP_NAME);
$appBrandTitle  = get_setting($pdo, 'app_brand_title', 'TRISULA');
$appBrandSub    = get_setting($pdo, 'app_brand_sub', 'TNI AD');
$appLogo        = app_logo_url();
$cssVersion     = file_exists(__DIR__ . '/assets/css/login-modern.css') ? filemtime(__DIR__ . '/assets/css/login-modern.css') : time();
$jsVersion      = file_exists(__DIR__ . '/assets/js/login-modern.js') ? filemtime(__DIR__ . '/assets/js/login-modern.js') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <?= render_seo_tags($pdo, ['title' => 'Registrasi Personel Baru', 'is_public' => true, 'type' => 'website']) ?>

  <!-- Modern Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">

  <!-- Modern Tactical CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login-modern.css?v=<?= $cssVersion ?>">
</head>
<body>

  <!-- Fullscreen Hero Wrapper with Trisula Background -->
  <div class="hero-wrapper" style="min-height:100vh;height:auto;overflow-y:auto;">
    <!-- Interactive Particle Canvas -->
    <canvas id="particlesCanvas"></canvas>

    <!-- Interactive Ambient Cursor Follower -->
    <div class="cursor-ambient-glow" id="cursorAmbientGlow"></div>

    <!-- Top Navigation Bar (Matching login.php) -->
    <header class="top-navbar" id="topNavbar">
      <a href="<?= BASE_URL ?>/login.php" class="nav-brand" title="Beranda Trisula TNI AD">
        <?php if ($appLogo): ?>
          <img src="<?= $appLogo ?>" alt="Logo Trisula" class="nav-brand-logo">
        <?php else: ?>
          <img src="<?= BASE_URL ?>/assets/img/logo_1789696457.png" alt="Logo TNI" class="nav-brand-logo">
        <?php endif; ?>
        <div class="nav-brand-text">
          <span class="nav-brand-title"><?= htmlspecialchars($appBrandTitle) ?></span>
          <span class="nav-brand-sub"><?= htmlspecialchars($appBrandSub) ?></span>
        </div>
      </a>

      <!-- Desktop Navigation Menu -->
      <nav>
        <ul class="nav-links" id="navLinksContainer">
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/login.php" class="link-effect" style="text-decoration:none;display:inline-block;padding:8px 12px;color:inherit;">Home</a>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/login.php?action=workflow" class="link-effect" style="text-decoration:none;display:inline-block;padding:8px 12px;color:inherit;">Alur Sistem</a>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navAboutBtn">About</button>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navServicesBtn">Services</button>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/verify.php" class="link-effect" style="text-decoration:none;display:inline-block;padding:8px 12px;color:inherit;">Verifikasi</a>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navContactBtn">Contact</button>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/login.php?action=login" class="nav-btn-login" style="text-decoration:none;display:inline-flex;align-items:center;">Masuk</a>
          </li>
        </ul>
      </nav>

      <!-- Mobile Hamburger Button -->
      <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Buka Menu Navigasi">
        <svg viewBox="0 0 24 24">
          <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
      </button>
    </header>

    <!-- Main Content: Registration Card -->
    <main style="position:relative;z-index:10;padding:20px 14px 60px;">
      <div class="reg-card-container">
        <div class="reg-glass-card">
          <!-- Ambient Glare -->
          <div class="glass-card-glare"></div>

          <!-- Header -->
          <div class="card-header" style="margin-bottom:20px;">
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(212,175,55,0.12);border:1px solid rgba(212,175,55,0.3);padding:4px 14px;border-radius:20px;font-family:var(--font-tech);font-size:12px;letter-spacing:1px;color:var(--primary-gold-bright);margin-bottom:10px;">
              <span>REGISTRASI PERSONEL &bull; DOSIR ELEKTRONIK</span>
            </div>
            <h1 class="card-title" style="font-size:26px;margin:0;">Pendaftaran Akun Personel</h1>
            <p class="card-subtitle" style="margin-top:6px;"><?= htmlspecialchars($appName) ?> &bull; <?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?></p>
          </div>

          <!-- Info Callout -->
          <div class="reg-info-callout">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--primary-gold);margin-top:2px;">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
              <strong>Ketentuan Akun Prajurit & PNS TNI AD:</strong><br>
              Username akun menggunakan <strong>NRP/NIP</strong> Anda. Kata sandi dapat ditentukan mandiri pada form atau otomatis menggunakan <strong>NRP</strong> jika dikosongkan. Setelah pendaftaran, akun berstatus <em>Pending</em> dan diverifikasi oleh Administrator/Staf Personel Satuan.
            </div>
          </div>

          <!-- Alert / Error Notification -->
          <?php if ($error): ?>
            <div class="auth-alert auth-alert-error" role="alert" style="margin-bottom:22px;">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              <div><?= htmlspecialchars($error) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($success && $successData): ?>
            <!-- Success Box -->
            <div class="reg-success-box">
              <div class="reg-success-icon">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </div>
              <h2 class="reg-success-title"><?= htmlspecialchars($success) ?></h2>
              <p style="color:var(--text-muted);font-size:13.5px;max-width:540px;margin:0 auto;line-height:1.5;">
                Data personel Anda telah berhasil tercatat dalam pangkalan data e-Dosir. Akun login Anda siap diverifikasi oleh Staf Personel Satuan.
              </p>

              <div class="reg-success-detail">
                <div class="detail-row">
                  <span class="detail-label">NRP / NIP</span>
                  <span class="detail-value"><?= htmlspecialchars($successData['nrp']) ?></span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Nama Lengkap</span>
                  <span class="detail-value"><?= htmlspecialchars($successData['nama']) ?></span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Pangkat & Satuan</span>
                  <span class="detail-value"><?= htmlspecialchars($successData['pangkat']) ?> &bull; <?= htmlspecialchars($successData['satuan']) ?></span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Status Akun Awal</span>
                  <span class="detail-value" style="color:#fbbf24;">MENUNGGU VERIFIKASI (PENDING)</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Kredensial Sandi</span>
                  <span class="detail-value" style="font-size:12px;"><?= htmlspecialchars($successData['password']) ?></span>
                </div>
              </div>

              <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>/login.php?action=login" class="btn-submit-login" style="max-width:260px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;padding:12px 24px;">
                  <span>Ke Halaman Login</span>
                </a>
                <a href="<?= BASE_URL ?>/login.php" class="btn-cancel" style="max-width:240px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15);color:var(--text-white);padding:12px 22px;border-radius:10px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:600;transition:all 0.2s;">
                  <span>Beranda Portal</span>
                </a>
              </div>
            </div>
          <?php else: ?>

            <!-- Registration Form -->
            <form method="post" action="<?= BASE_URL ?>/register.php" class="auth-form" id="registerForm" autocomplete="on">
              <?= csrf_field() ?>

              <div class="reg-grid">
                
                <!-- =======================================================
                     BAGIAN 1: IDENTITAS UTAMA & KREDENSIAL AKUN
                     ======================================================= -->
                <div class="reg-section-header">
                  <span class="reg-section-badge">BAGIAN 01</span>
                  <h3 class="reg-section-title">Identitas Pokok & Kata Sandi Akun</h3>
                </div>

                <!-- NRP / NIP -->
                <div>
                  <div class="form-group">
                    <label for="nrpInput" class="form-label">NRP / NIP Militer <span style="color:var(--primary-gold);">*</span></label>
                    <div class="input-container">
                      <input 
                        type="text" 
                        name="nrp" 
                        id="nrpInput" 
                        class="form-input" 
                        placeholder="cth: 1118001234 atau NIP PNS" 
                        value="<?= htmlspecialchars($_POST['nrp'] ?? '') ?>" 
                        required 
                        autofocus
                        maxlength="20"
                        autocomplete="username">
                      <span class="input-icon-right" title="NRP / NIP">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                          <line x1="7" y1="8" x2="17" y2="8"></line>
                          <line x1="7" y1="12" x2="13" y2="12"></line>
                        </svg>
                      </span>
                    </div>
                    <div class="form-helper">Digunakan sebagai Username login utama (4-20 karakter).</div>
                  </div>
                </div>

                <!-- Nama Lengkap -->
                <div>
                  <div class="form-group">
                    <label for="namaInput" class="form-label">Nama Lengkap & Gelar <span style="color:var(--primary-gold);">*</span></label>
                    <div class="input-container">
                      <input 
                        type="text" 
                        name="nama" 
                        id="namaInput" 
                        class="form-input" 
                        placeholder="Nama lengkap prajurit / PNS" 
                        value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" 
                        required
                        autocomplete="name">
                      <span class="input-icon-right">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                          <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Kata Sandi Opsional -->
                <div>
                  <div class="form-group">
                    <label for="regPassword" class="form-label">Kata Sandi Akun <span style="font-size:11px;color:var(--text-dim);">(Opsional)</span></label>
                    <div class="input-container">
                      <input 
                        type="password" 
                        name="password" 
                        id="regPassword" 
                        class="form-input" 
                        placeholder="Minimal 8 karakter kombinasi huruf & angka"
                        minlength="8"
                        autocomplete="new-password">
                      <button type="button" class="password-toggle-btn" id="toggleRegPassBtn" title="Tampilkan Kata Sandi" aria-label="Toggle Password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                      </button>
                    </div>
                    <div class="form-helper">Kosongkan jika ingin dibuatkan sandi acak sementara (wajib ganti saat pertama login).</div>
                  </div>
                </div>

                <!-- Konfirmasi Kata Sandi -->
                <div>
                  <div class="form-group">
                    <label for="regPasswordConf" class="form-label">Konfirmasi Kata Sandi</label>
                    <div class="input-container">
                      <input 
                        type="password" 
                        name="password_confirm" 
                        id="regPasswordConf" 
                        class="form-input" 
                        placeholder="Ketik ulang kata sandi baru"
                        minlength="8"
                        autocomplete="new-password">
                      <button type="button" class="password-toggle-btn" id="toggleRegPassConfBtn" title="Tampilkan Konfirmasi Sandi" aria-label="Toggle Confirm Password">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- =======================================================
                     BAGIAN 2: KEDINASAN, PANGKAT & KOTAMA/SATUAN
                     ======================================================= -->
                <div class="reg-section-header">
                  <span class="reg-section-badge">BAGIAN 02</span>
                  <h3 class="reg-section-title">Data Kedinasan & Kepangkatan Alami</h3>
                </div>

                <!-- Golongan Kepangkatan -->
                <div>
                  <div class="form-group">
                    <label for="golonganSelect" class="form-label">Golongan Kepangkatan <span style="color:var(--primary-gold);">*</span></label>
                    <select name="golongan" id="golonganSelect" class="form-select" onchange="filterPangkatByGolongan()" required>
                      <option value="">-- Pilih Golongan Kepangkatan --</option>
                      <?php foreach (['Perwira','Bintara','Tamtama','PNS'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($_POST['golongan'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <!-- Pangkat Resmi -->
                <div>
                  <div class="form-group">
                    <label for="pangkatSelect" class="form-label">Pangkat Resmi <span style="color:var(--primary-gold);">*</span></label>
                    <select name="pangkat_id" id="pangkatSelect" class="form-select" onchange="syncPangkatFields()" required>
                      <option value="">-- Pilih Pangkat Resmi --</option>
                      <?php foreach ($masterPangkatAll as $pkt): ?>
                        <option value="<?= $pkt['id'] ?>"
                                data-golongan="<?= $pkt['golongan'] ?>"
                                data-singkatan="<?= htmlspecialchars($pkt['singkatan']) ?>"
                                <?= ((int)($_POST['pangkat_id'] ?? 0) === (int)$pkt['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($pkt['singkatan']) ?> &mdash; <?= htmlspecialchars($pkt['nama']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="pangkat" id="pangkatText" value="<?= htmlspecialchars($_POST['pangkat'] ?? '') ?>">
                  </div>
                </div>

                <!-- Korp / Kecabangan -->
                <div>
                  <div class="form-group">
                    <label for="korpSelect" class="form-label">Korp / Kecabangan Militer</label>
                    <select name="korp_id" id="korpSelect" class="form-select" onchange="syncKorpFields()">
                      <option value="">-- Tanpa Korp / Non-Kecabangan (PNS) --</option>
                      <?php foreach ($korpByKategori as $kat => $korpGroup): ?>
                        <optgroup label="Kecabangan <?= htmlspecialchars($kat) ?>">
                          <?php foreach ($korpGroup as $krp): ?>
                            <option value="<?= $krp['id'] ?>"
                                    data-kode="<?= htmlspecialchars($krp['kode']) ?>"
                                    <?= ((int)($_POST['korp_id'] ?? 0) === (int)$krp['id']) ? 'selected' : '' ?>>
                              <?= htmlspecialchars($krp['kode']) ?> &mdash; <?= htmlspecialchars($krp['nama']) ?>
                            </option>
                          <?php endforeach; ?>
                        </optgroup>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="korp" id="korpText" value="<?= htmlspecialchars($_POST['korp'] ?? '') ?>">
                  </div>
                </div>

                <!-- Kotama Induk -->
                <div>
                  <div class="form-group">
                    <label for="kotamaSelect" class="form-label">Kotama / Balakpus Induk</label>
                    <select name="kotama_id" id="kotamaSelect" class="form-select" onchange="filterSatuanByKotama()">
                      <option value="">-- Pilih Kotama / Balakpus --</option>
                      <?php foreach ($masterKotamaAll as $kot): ?>
                        <option value="<?= $kot['id'] ?>"
                                data-nama="<?= htmlspecialchars($kot['nama']) ?>"
                                <?= ((int)($_POST['kotama_id'] ?? 0) === (int)$kot['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($kot['kode']) ?> &mdash; <?= htmlspecialchars($kot['nama']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="kotama" id="kotamaText" value="<?= htmlspecialchars($_POST['kotama'] ?? '') ?>">
                  </div>
                </div>

                <!-- Satuan Organik -->
                <div class="reg-grid-full">
                  <div class="form-group">
                    <label for="satuanSelect" class="form-label">Satuan Organik</label>
                    <select name="satuan_id" id="satuanSelect" class="form-select" onchange="syncSatuanFields()">
                      <option value="">-- Pilih Satuan Organik Master --</option>
                      <?php foreach ($masterSatuanAll as $sat): ?>
                        <option value="<?= $sat['id'] ?>"
                                data-kotama="<?= $sat['kotama_id'] ?>"
                                data-nama="<?= htmlspecialchars($sat['nama']) ?>"
                                <?= ((string)($_POST['satuan_id'] ?? '') === (string)$sat['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($sat['nama']) ?> <?= !empty($sat['lokasi']) ? '('.htmlspecialchars($sat['lokasi']).')' : '' ?>
                        </option>
                      <?php endforeach; ?>
                      <option value="custom" <?= (($_POST['satuan_id'] ?? '') === 'custom' || (!empty($_POST['satuan']) && empty($_POST['satuan_id']))) ? 'selected' : '' ?>>
                        -- Satuan Lainnya / Tulis Manual Baru --
                      </option>
                    </select>
                    <div style="margin-top:8px;" id="satuanCustomWrapper" style="<?= (($_POST['satuan_id'] ?? '') === 'custom' || (!empty($_POST['satuan']) && empty($_POST['satuan_id']))) ? '' : 'display:none;' ?>">
                      <input 
                        type="text" 
                        name="satuan" 
                        id="satuanCustomInput" 
                        class="form-input" 
                        value="<?= htmlspecialchars($_POST['satuan'] ?? '') ?>" 
                        placeholder="Ketikkan nama satuan dinas jika belum tercantum di master (cth: Yonif 312/Kala Hitam)"
                        style="<?= (($_POST['satuan_id'] ?? '') === 'custom' || (!empty($_POST['satuan']) && empty($_POST['satuan_id']))) ? '' : 'display:none;' ?>">
                    </div>
                  </div>
                </div>

                <!-- Jabatan -->
                <div>
                  <div class="form-group">
                    <label for="jabatanInput" class="form-label">Jabatan Dinas</label>
                    <input 
                      type="text" 
                      name="jabatan" 
                      id="jabatanInput" 
                      class="form-input" 
                      placeholder="cth: Pasi Intel / Danramil / Baur" 
                      value="<?= htmlspecialchars($_POST['jabatan'] ?? '') ?>">
                  </div>
                </div>

                <!-- TMT Jabatan & TMT Pangkat -->
                <div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                      <label class="form-label">TMT Jabatan</label>
                      <input type="date" name="tmt_jabatan" class="form-input" value="<?= htmlspecialchars($_POST['tmt_jabatan'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">TMT Pangkat</label>
                      <input type="date" name="tmt_pangkat" class="form-input" value="<?= htmlspecialchars($_POST['tmt_pangkat'] ?? '') ?>">
                    </div>
                  </div>
                </div>

                <!-- =======================================================
                     BAGIAN 3: DATA PRIBADI & KONTAK PERSONEL
                     ======================================================= -->
                <div class="reg-section-header">
                  <span class="reg-section-badge">BAGIAN 03</span>
                  <h3 class="reg-section-title">Data Pribadi & Jalur Komunikasi</h3>
                </div>

                <!-- Tempat & Tanggal Lahir -->
                <div>
                  <div class="form-group">
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" class="form-input" placeholder="Kota / Kabupaten Kelahiran" value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>">
                  </div>
                </div>

                <div>
                  <div class="form-group">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" id="tanggalLahirInput" class="form-input" value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>">
                  </div>
                </div>

                <!-- Jenis Kelamin -->
                <div>
                  <div class="form-group">
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-select">
                      <option value="L" <?= ($_POST['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                      <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                  </div>
                </div>

                <!-- No HP / WhatsApp -->
                <div>
                  <div class="form-group">
                    <label class="form-label">No. Handphone / WhatsApp</label>
                    <input 
                      type="tel" 
                      name="no_hp" 
                      class="form-input" 
                      placeholder="0812xxxxxxxx" 
                      value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                      maxlength="20">
                    <div class="form-helper">Untuk koordinasi verifikasi berkas oleh admin.</div>
                  </div>
                </div>

                <!-- Email Pribadi / Kedinasan -->
                <div class="reg-grid-full">
                  <div class="form-group">
                    <label class="form-label">Alamat Email <span style="font-size:11px;color:var(--text-dim);">(Opsional)</span></label>
                    <input 
                      type="email" 
                      name="email" 
                      class="form-input" 
                      placeholder="email@tniad.mil.id atau email@gmail.com" 
                      value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                      maxlength="100">
                  </div>
                </div>

              </div>

              <!-- Submit Button -->
              <div style="margin-top:28px;">
                <button type="submit" class="btn-submit-login" id="submitRegBtn">
                  <span class="spinner-icon"></span>
                  <span id="submitBtnText">Daftarkan Personel Baru</span>
                </button>
              </div>

              <div class="card-footer" style="margin-top:20px;">
                Sudah memiliki akun yang diverifikasi? <a href="<?= BASE_URL ?>/login.php?action=login" style="color:var(--primary-gold-bright);font-weight:700;">Masuk di sini</a>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </main>

    <!-- Modals (About, Services, Contact) Matching Login Portal -->
    <div class="modal-overlay" id="aboutModal" role="dialog" aria-modal="true">
      <div class="modal-card">
        <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
        <div class="modal-title">Tentang <?= htmlspecialchars($appBrandTitle) ?> TNI AD</div>
        <div class="modal-body">
          <p><strong>Aplikasi <?= htmlspecialchars($appName) ?></strong> adalah sistem informasi terintegrasi untuk pengelolaan rekam warkat dosir elektronik militer secara terpusat, tertib, dan akuntabel.</p>
          <div class="modal-highlight-box">
            <strong>"SATU DATA &bull; SATU SISTEM &bull; UNTUK TNI AD"</strong><br>
            Profesional &bull; Modern &bull; Adaptif
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="modal-btn modal-btn-primary" data-close-modal>Tutup</button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="servicesModal" role="dialog" aria-modal="true">
      <div class="modal-card">
        <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
        <div class="modal-title">Layanan E-Dosir TRISULA</div>
        <div class="modal-body">
          <p>Portal digital ini melayani 33 berkas warkat dosir prajurit, verifikasi berkas terpadu oleh Staf Personel Satuan, dan penerbitan tanda tangan digital (TTE) ber-QR Code resmi.</p>
        </div>
        <div class="modal-footer">
          <a href="<?= BASE_URL ?>/verify.php" class="modal-btn modal-btn-primary">Verifikasi QR Code</a>
          <button type="button" class="modal-btn modal-btn-secondary" data-close-modal>Tutup</button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="contactModal" role="dialog" aria-modal="true">
      <div class="modal-card">
        <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
        <div class="modal-title">Bantuan Staf Personel</div>
        <div class="modal-body">
          <p>Jika mengalami kendala aktivasi akun atau kelengkapan berkas dosir:</p>
          <div class="modal-highlight-box">
            <strong>Helpdesk Staf Personel (Spersad / Satuan)</strong><br>
            Email: <span style="color:var(--primary-gold);">support-trisula@tniad.mil.id</span><br>
            Jam Pelayanan: Senin &ndash; Jumat, 07.30 &ndash; 16.00 WIB
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="modal-btn modal-btn-primary" data-close-modal>Tutup</button>
        </div>
      </div>
    </div>

  </div>

  <!-- Interactive JavaScript -->
  <script>
  // 1. Filter Pangkat Berdasarkan Golongan
  function filterPangkatByGolongan() {
    var gol = document.getElementById('golonganSelect').value;
    var sel = document.getElementById('pangkatSelect');
    var opts = sel.querySelectorAll('option');

    var currentMatch = false;
    opts.forEach(function(opt) {
      if (!opt.value) return;
      var optGol = opt.getAttribute('data-golongan');
      if (!gol || optGol === gol) {
        opt.style.display = '';
        opt.disabled = false;
        if (opt.selected) currentMatch = true;
      } else {
        opt.style.display = 'none';
        opt.disabled = true;
        if (opt.selected) opt.selected = false;
      }
    });

    if (!currentMatch && gol) {
      sel.value = '';
    }
    syncPangkatFields();

    // Sesuaikan Korp untuk PNS
    var korpSel = document.getElementById('korpSelect');
    if (korpSel) {
      if (gol === 'PNS') {
        korpSel.value = '';
        korpSel.disabled = true;
      } else {
        korpSel.disabled = false;
      }
      syncKorpFields();
    }
  }

  function syncPangkatFields() {
    var sel = document.getElementById('pangkatSelect');
    var opt = sel.options[sel.selectedIndex];
    var txt = document.getElementById('pangkatText');
    if (txt) {
      txt.value = (opt && opt.value) ? (opt.getAttribute('data-singkatan') || '') : '';
    }
  }

  function syncKorpFields() {
    var sel = document.getElementById('korpSelect');
    var opt = sel.options[sel.selectedIndex];
    var txt = document.getElementById('korpText');
    if (txt) {
      txt.value = (opt && opt.value) ? (opt.getAttribute('data-kode') || '') : '';
    }
  }

  function filterSatuanByKotama() {
    var kotamaId = document.getElementById('kotamaSelect').value;
    var selKot = document.getElementById('kotamaSelect');
    var optKot = selKot.options[selKot.selectedIndex];
    var txtKot = document.getElementById('kotamaText');
    if (txtKot) {
      txtKot.value = (optKot && optKot.value) ? (optKot.getAttribute('data-nama') || '') : '';
    }

    var selSat = document.getElementById('satuanSelect');
    var optsSat = selSat.querySelectorAll('option');
    var currentSatValid = false;

    optsSat.forEach(function(opt) {
      if (!opt.value || opt.value === 'custom') {
        if (opt.selected) currentSatValid = true;
        return;
      }
      var satKot = opt.getAttribute('data-kotama');
      if (!kotamaId || !satKot || satKot === kotamaId) {
        opt.style.display = '';
        opt.disabled = false;
        if (opt.selected) currentSatValid = true;
      } else {
        opt.style.display = 'none';
        opt.disabled = true;
        if (opt.selected) opt.selected = false;
      }
    });

    if (!currentSatValid && kotamaId && selSat.value !== 'custom') {
      selSat.value = '';
    }
    syncSatuanFields();
  }

  function syncSatuanFields() {
    var sel = document.getElementById('satuanSelect');
    var opt = sel.options[sel.selectedIndex];
    var customInput = document.getElementById('satuanCustomInput');
    if (!customInput) return;

    if (opt && opt.value === 'custom') {
      customInput.style.display = '';
      customInput.focus();
    } else if (opt && opt.value) {
      customInput.style.display = 'none';
      customInput.value = opt.getAttribute('data-nama') || '';
      var satKot = opt.getAttribute('data-kotama');
      if (satKot) {
        var kotSel = document.getElementById('kotamaSelect');
        if (kotSel && kotSel.value !== satKot) {
          kotSel.value = satKot;
          filterSatuanByKotama();
        }
      }
    } else {
      customInput.style.display = 'none';
      customInput.value = '';
    }
  }

  // Toggle Password
  function initPassToggle(btnId, inputId) {
    var btn = document.getElementById(btnId);
    var input = document.getElementById(inputId);
    if (btn && input) {
      btn.addEventListener('click', function() {
        var isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
      });
    }
  }

  // Modals & Navigation
  document.addEventListener('DOMContentLoaded', function() {
    var gol = document.getElementById('golonganSelect').value;
    if (gol) filterPangkatByGolongan();
    var kot = document.getElementById('kotamaSelect').value;
    if (kot) filterSatuanByKotama();
    syncSatuanFields();

    initPassToggle('toggleRegPassBtn', 'regPassword');
    initPassToggle('toggleRegPassConfBtn', 'regPasswordConf');

    // Modals
    function openModal(id) {
      var m = document.getElementById(id);
      if (m) m.classList.add('active');
    }
    function closeModals() {
      document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
        m.classList.remove('active');
      });
    }

    var abBtn = document.getElementById('navAboutBtn');
    if (abBtn) abBtn.addEventListener('click', function() { openModal('aboutModal'); });
    var svBtn = document.getElementById('navServicesBtn');
    if (svBtn) svBtn.addEventListener('click', function() { openModal('servicesModal'); });
    var ctBtn = document.getElementById('navContactBtn');
    if (ctBtn) ctBtn.addEventListener('click', function() { openModal('contactModal'); });

    document.querySelectorAll('[data-close-modal]').forEach(function(b) {
      b.addEventListener('click', closeModals);
    });
    document.querySelectorAll('.modal-overlay').forEach(function(o) {
      o.addEventListener('click', function(e) {
        if (e.target === o) closeModals();
      });
    });

    // Mobile Menu
    var mobBtn = document.getElementById('mobileMenuBtn');
    var navLinks = document.getElementById('navLinksContainer');
    if (mobBtn && navLinks) {
      mobBtn.addEventListener('click', function() {
        navLinks.classList.toggle('mobile-open');
      });
    }

    // Submit Loader Animation
    var regForm = document.getElementById('registerForm');
    if (regForm) {
      regForm.addEventListener('submit', function() {
        var btn = document.getElementById('submitRegBtn');
        if (btn) btn.classList.add('loading');
      });
    }
  });
  </script>

  <!-- Load Particle Canvas and Ambient Follower from login-modern.js -->
  <script src="<?= BASE_URL ?>/assets/js/login-modern.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
