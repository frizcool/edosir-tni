<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect($_SESSION['user']['role'] === 'admin' ? '/admin/dashboard.php' : '/personel/dashboard.php');
}

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Username/NRP dan kata sandi wajib diisi.';
    } elseif (do_login($pdo, $username, $password, $error)) {
        redirect($_SESSION['user']['role'] === 'admin' ? '/admin/dashboard.php' : '/personel/dashboard.php');
    }
}

$flash = get_flash();
$appName = get_setting($pdo, 'app_name', APP_NAME);
$appSubtitle = get_setting($pdo, 'app_subtitle', 'Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip');
$appBrandTitle = get_setting($pdo, 'app_brand_title', 'TRISULA');
$appBrandSub = get_setting($pdo, 'app_brand_sub', 'TNI AD');
$appLogo = app_logo_url();
$cssVersion = file_exists(__DIR__ . '/assets/css/login-modern.css') ? filemtime(__DIR__ . '/assets/css/login-modern.css') : time();
$jsVersion = file_exists(__DIR__ . '/assets/js/login-modern.js') ? filemtime(__DIR__ . '/assets/js/login-modern.js') : time();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Masuk | <?= htmlspecialchars($appName) ?></title>
  <link rel="icon" type="image/png" href="<?= $appLogo ?: (BASE_URL . '/assets/img/logo_1789696457.png') ?>">
  
  <!-- Modern Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
  
  <!-- Modern Animated Login CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login-modern.css?v=<?= $cssVersion ?>">
</head>
<body>

  <!-- Fullscreen Hero Wrapper with Trisula Background -->
  <div class="hero-wrapper">
    <!-- Interactive Particle Canvas -->
    <canvas id="particlesCanvas"></canvas>

    <!-- Interactive Ambient Cursor Follower -->
    <div class="cursor-ambient-glow" id="cursorAmbientGlow"></div>

    <!-- Top Navigation Bar (Exact Layout from Reference Image 1) -->
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

      <!-- Desktop Navigation Menu (Matching Reference Image 1) -->
      <nav>
        <ul class="nav-links" id="navLinksContainer">
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/login.php" class="link-effect" id="navHomeLink">Home</a>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navAboutBtn">About</button>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navServicesBtn">Services</button>
          </li>
          <li class="nav-item">
            <a href="<?= BASE_URL ?>/verify.php" class="link-effect" title="Validasi Keaslian Dokumen & QR Code">Verifikasi</a>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navContactBtn">Contact</button>
          </li>
          <li class="nav-item">
            <button type="button" class="nav-btn-login" id="navLoginBtn">Login</button>
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

    <!-- Main Content: Centered Floating Frosted Glass Login Modal -->
    <main class="hero-content">
      <div class="login-card-container" id="loginCardContainer">
        <div class="glass-card" id="glassCard">
          <!-- Dynamic Light Specular Reflection Layer -->
          <div class="glass-card-glare"></div>

          <!-- Close Button 'X' at Top Right (Exact Reference Image 1) -->
          <button type="button" class="card-close-btn" id="cardCloseBtn" title="Tutup Formulir untuk Melihat Wallpaper">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>

          <!-- Card Header (Centered Title Matching Image 1) -->
          <div class="card-header">
            <h1 class="card-title">Login</h1>
            <p class="card-subtitle"><?= htmlspecialchars($appName) ?> &bull; Sistem E-Dosir Militer</p>
          </div>

          <!-- Alert / Error Notification -->
          <?php if ($error): ?>
            <div class="auth-alert auth-alert-error" role="alert">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              <div><?= htmlspecialchars($error) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($flash): ?>
            <div class="auth-alert auth-alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="alert">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <?php if ($flash['type'] === 'success'): ?>
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                <?php else: ?>
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                <?php endif; ?>
              </svg>
              <div><?= htmlspecialchars($flash['msg']) ?></div>
            </div>
          <?php endif; ?>

          <!-- Login Form (Method POST with CSRF Protection) -->
          <form method="post" action="<?= BASE_URL ?>/login.php" class="auth-form" id="loginForm" autocomplete="on">
            <?= csrf_field() ?>

            <!-- Field 1: Email / Username / NRP (Matching Image 1) -->
            <div class="form-group">
              <label for="usernameInput" class="form-label">Email / NRP / Username</label>
              <div class="input-container">
                <input 
                  type="text" 
                  name="username" 
                  id="usernameInput" 
                  class="form-input" 
                  placeholder="Masukkan NRP atau Username" 
                  value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                  required 
                  autofocus
                  autocomplete="username">
                <span class="input-icon-right" title="Email atau NRP">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                  </svg>
                </span>
              </div>
            </div>

            <!-- Field 2: Password (Matching Image 1) -->
            <div class="form-group">
              <label for="passwordInput" class="form-label">Password</label>
              <div class="input-container">
                <input 
                  type="password" 
                  name="password" 
                  id="passwordInput" 
                  class="form-input" 
                  placeholder="Kata Sandi Akun" 
                  required 
                  autocomplete="current-password">
                <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Tampilkan / Sembunyikan Kata Sandi" aria-label="Toggle Kata Sandi">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                </button>
              </div>
              <div class="form-helper">
                Personel baru: gunakan NRP sebagai Kata Sandi awal setelah diverifikasi admin.
              </div>
            </div>

            <!-- Row: Remember Me & Forgot Password (Exact Reference Image 1) -->
            <div class="form-options-row">
              <label class="remember-me-label" for="rememberMeCheckbox">
                <input type="checkbox" id="rememberMeCheckbox" class="remember-me-input">
                <span class="custom-checkbox">
                  <svg viewBox="0 0 24 24" fill="none">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </span>
                <span>Remember me</span>
              </label>

              <button type="button" class="forgot-password-link" id="forgotPasswordBtn">
                Forgot Password?
              </button>
            </div>

            <!-- Submit Button: "Login" (Exact Style from Reference Image 1) -->
            <button type="submit" class="btn-submit-login" id="submitLoginBtn">
              <span class="spinner-icon"></span>
              <span id="submitBtnText">Login</span>
            </button>
          </form>

          <!-- Card Footer (Matching Image 1: "Don't have an account? Register") -->
          <div class="card-footer">
            Don't have an account? <a href="<?= BASE_URL ?>/register.php">Register</a>
          </div>
        </div>
      </div>
    </main>

    <!-- Floating Trigger when Card is Closed / Hidden -->
    <button type="button" class="card-restore-trigger" id="cardRestoreTrigger">
      <svg viewBox="0 0 24 24">
        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
      </svg>
      <span>Buka Formulir Login</span>
    </button>
  </div>

  <!-- =====================================================================
       MODAL: ABOUT (TENTANG APLIKASI TRISULA TNI AD)
       ===================================================================== -->
  <div class="modal-overlay" id="aboutModal" role="dialog" aria-modal="true">
    <div class="modal-card">
      <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        Tentang Trisula TNI AD
      </div>
      <div class="modal-body">
        <p><strong>Aplikasi TRISULA TNI AD</strong> adalah sistem informasi terintegrasi untuk pengelolaan rekam berkas dosir elektronik militer secara terpusat, tertib, dan akuntabel.</p>
        
        <div class="modal-highlight-box">
          <strong>"SATU DATA &bull; SATU SISTEM &bull; UNTUK TNI AD"</strong><br>
          Profesional &bull; Modern &bull; Adaptif
        </div>

        <h4>Prinsip Tata Kelola Dosir:</h4>
        <ul>
          <li><strong>Berkas Digital:</strong> Transformasi berkas fisik dosir kepangkatan, pendidikan, dan penugasan menjadi dokumen elektronik PDF aman berstandar ISO.</li>
          <li><strong>Tertib Administrasi:</strong> Verifikasi bertingkat oleh Staf Personel Satuan dan verifikator administrasi TNI AD.</li>
          <li><strong>Efisien:</strong> Akses cepat dokumen personel untuk kepentingan pembinaan karir, kenaikan pangkat, sekolah dinas, dan pensiun.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-primary" data-close-modal>Mengerti</button>
      </div>
    </div>
  </div>

  <!-- =====================================================================
       MODAL: SERVICES (LAYANAN SISTEM E-DOSIR)
       ===================================================================== -->
  <div class="modal-overlay" id="servicesModal" role="dialog" aria-modal="true">
    <div class="modal-card">
      <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
          <polyline points="2 17 12 22 22 17"></polyline>
          <polyline points="2 12 12 17 22 12"></polyline>
        </svg>
        Layanan Sistem TRISULA
      </div>
      <div class="modal-body">
        <p>Fasilitas dan modul layanan digital yang disediakan oleh sistem TRISULA TNI AD:</p>
        
        <h4>1. Unggah & Konversi Dosir Digital</h4>
        <p>Prajurit dan PNS TNI AD dapat mengunggah berkas riwayat hidup, surat keputusan (Skep), dan sertifikat dengan validasi format PDF otomatis serta kompresi dokumen terstandar.</p>

        <h4>2. Scan Kamera Langsung (jsPDF)</h4>
        <p>Fitur pemindaian fisik berkas langsung menggunakan kamera perangkat (ponsel/laptop) dengan deteksi kontras dan konversi otomatis menjadi berkas PDF dosir.</p>

        <h4>3. Validasi Keaslian & Segel Digital QR Code</h4>
        <p>Setiap dokumen yang telah diverifikasi dan disetujui staf admin dilengkapi dengan tanda tangan segel digital QR Code yang dapat diuji keasliannya melalui portal publik.</p>

        <h4>4. Unduh Berkas Massal & Rekapitulasi Karir</h4>
        <p>Memudahkan pejabat personel mengunduh berkas personel secara kolektif (Zip) untuk persiapan sidang Dewan Kehormatan Perwira/Jabatan (Wanjak).</p>
      </div>
      <div class="modal-footer">
        <a href="<?= BASE_URL ?>/verify.php" class="modal-btn modal-btn-primary">Coba Verifikasi QR</a>
        <button type="button" class="modal-btn modal-btn-secondary" data-close-modal>Tutup</button>
      </div>
    </div>
  </div>

  <!-- =====================================================================
       MODAL: CONTACT (BANTUAN & KONTAK IT STAF PERS)
       ===================================================================== -->
  <div class="modal-overlay" id="contactModal" role="dialog" aria-modal="true">
    <div class="modal-card">
      <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
        </svg>
        Bantuan & Layanan Kontak
      </div>
      <div class="modal-body">
        <p>Jika Anda mengalami kendala saat mengakses sistem atau memerlukan pendampingan teknis:</p>
        
        <div class="modal-highlight-box">
          <strong>Helpdesk Spersad / Staf Personel Satuan</strong><br>
          Gedung Staf Personel &bull; Mabesad / Satuan Kewilayahan<br>
          Email: <span style="color:var(--primary-gold);">support-trisula@tniad.mil.id</span><br>
          Jam Pelayanan: Senin &ndash; Jumat, 07.30 &ndash; 16.00 WIB
        </div>

        <h4>Langkah Cepat:</h4>
        <ul>
          <li>Untuk aktivasi akun yang belum diverifikasi, laporkan NRP kepada operator Spers satuan masing-masing.</li>
          <li>Untuk penggantian perangkat atau lupa kata sandi, ikuti prosedur pada menu <em>Forgot Password</em>.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-primary" data-close-modal>Tutup</button>
      </div>
    </div>
  </div>

  <!-- =====================================================================
       MODAL: FORGOT PASSWORD (PETUNJUK RESET SANDI)
       ===================================================================== -->
  <div class="modal-overlay" id="forgotPasswordModal" role="dialog" aria-modal="true">
    <div class="modal-card">
      <button type="button" class="modal-close-btn" data-close-modal title="Tutup Modal">&times;</button>
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        Pemulihan Kata Sandi
      </div>
      <div class="modal-body">
        <p>Sesuai dengan <strong>Standar Operasional Prosedur Keamanan Siber TNI AD</strong>, reset kata sandi akun sistem TRISULA dilakukan secara verifikatif demi melindungi kerahasiaan data personel militer.</p>
        
        <div class="modal-highlight-box">
          <strong>Petunjuk Reset Kata Sandi:</strong><br>
          1. Hubungi Administrator Satuan atau Staf Personel (Spers) tempat Anda berdinas.<br>
          2. Sertakan <strong>NRP</strong> dan <strong>Kartu Tanda Prajurit (KTA)</strong> yang sah.<br>
          3. Administrator akan me-reset kata sandi akun Anda kembali ke format standar atau membuatkan sandi sementara.
        </div>

        <p style="font-size:12.5px;color:var(--text-dim);margin-top:10px;">
          Catatan: Untuk personel yang baru mendaftar, kata sandi awal secara sistem adalah <strong>NRP</strong> Anda sendiri setelah akun disetujui (Approved) oleh Administrator.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn modal-btn-primary" data-close-modal>Tutup</button>
      </div>
    </div>
  </div>

  <!-- Modern Animated Login Javascript -->
  <script src="<?= BASE_URL ?>/assets/js/login-modern.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
