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
        if (!empty($_SESSION['user']['must_change_password'])) {
            set_flash('error', 'Demi keamanan, Anda wajib memperbarui kata sandi sebelum melanjutkan.');
            if ($_SESSION['user']['role'] === 'admin') {
                redirect('/admin/settings.php?action=change_password_required#ganti-sandi');
            } else {
                redirect('/personel/profile.php?action=change_password_required');
            }
        }
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

// Default: Alur aplikasi tampil pertama kali sebelum user mengklik Login.
// Tampilkan login form secara langsung bila:
// 1. Terdapat error submission login ($error)
// 2. Terdapat pesan flash ($flash)
// 3. Dipanggil dengan parameter ?action=login atau ?login=1
$showLoginByDefault = !empty($error) || !empty($flash) || (isset($_GET['action']) && $_GET['action'] === 'login') || (isset($_GET['login']) && $_GET['login'] == '1');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <?= render_seo_tags($pdo, ['title' => 'Masuk Portal', 'is_public' => true, 'type' => 'website']) ?>
  
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
            <button type="button" class="link-effect" id="navHomeLink" title="Beranda & Alur Prosedur">Home</button>
          </li>
          <li class="nav-item">
            <button type="button" class="link-effect" id="navWorkflowBtn" title="Lihat Alur Sistem E-Dosir">Alur Sistem</button>
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

    <!-- Main Content: Center Area (Workflow Showcase & Floating Glass Login Modal) -->
    <main class="hero-content">
      
      <!-- =================================================================
           1. ALUR PROSEDUR SISTEM APLIKASI (Ditampilkan Default Sebelum Klik Login)
           ================================================================= -->
      <div class="workflow-container" id="workflowContainer" <?= $showLoginByDefault ? 'style="display:none;"' : '' ?>>
        
        <!-- Header Alur Aplikasi -->
        <div class="workflow-header">
          <div class="workflow-badge-pill">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            <span>Standar Operasional Prosedur &bull; E-Dosir TNI AD</span>
          </div>
          <h1 class="workflow-title">Alur & Tata Kelola Sistem <?= htmlspecialchars($appBrandTitle) ?></h1>
          <p class="workflow-subtitle">
            Tata kelola terpadu rekam berkas warkat dosir digital personel militer, mulai dari pendaftaran akun hingga penerbitan Sertifikasi Tanda Tangan Elektronik (TTE) resmi ber-QR Code.
          </p>
        </div>

        <!-- 6 Tahapan Grid Alur Sistem -->
        <div class="workflow-grid">
          
          <!-- Tahap 1: Registrasi Personel -->
          <div class="workflow-step-card">
            <div class="workflow-card-top">
              <span class="workflow-step-num">TAHAP 01</span>
              <div class="workflow-icon-box" title="Registrasi Akun">📝</div>
            </div>
            <h3 class="workflow-card-title">Registrasi Personel</h3>
            <p class="workflow-card-desc">
              Prajurit dan PNS TNI AD mendaftar akun baru dengan mengisi data dinas (NRP, nama lengkap, golongan kepangkatan, korp, dan satuan).
            </p>
            <div class="workflow-callout-box callout-info">
              ⏳ <strong>Status Akun Awal:</strong> <em>Pending</em> (Menunggu validasi data dinas oleh Staf Personel).
            </div>
          </div>

          <!-- Tahap 2: Validasi Admin & Izin Login -->
          <div class="workflow-step-card">
            <div class="workflow-card-top">
              <span class="workflow-step-num">TAHAP 02</span>
              <div class="workflow-icon-box" title="Validasi Akun Admin">🛡️</div>
            </div>
            <h3 class="workflow-card-title">Validasi Akun oleh Admin</h3>
            <p class="workflow-card-desc">
              Administrator (Staf Personel Satuan) memverifikasi identitas prajurit pada sistem pangkalan induk dan menyetujui akun dinas.
            </p>
            <div class="workflow-callout-box callout-warning">
              🔒 <strong>Ketentuan Akses:</strong> <strong>User hanya dapat login setelah divalidasi oleh Admin</strong>. Kata sandi awal otomatis menggunakan NRP Anda.
            </div>
          </div>

          <!-- Tahap 3: Login Personel -->
          <div class="workflow-step-card">
            <div class="workflow-card-top">
              <span class="workflow-step-num">TAHAP 03</span>
              <div class="workflow-icon-box" title="Masuk Portal">🔐</div>
            </div>
            <h3 class="workflow-card-title">Login Personel Terverifikasi</h3>
            <p class="workflow-card-desc">
              Setelah akun disetujui admin, personel dapat masuk ke portal TRISULA untuk mengelola dokumen dan memeriksa kelengkapan dosirnya.
            </p>
            <div class="workflow-callout-box callout-success" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <span>✓ Akun aktif siap digunakan</span>
              <button type="button" class="btn" style="padding:4px 10px;font-size:11px;background:var(--primary-gold);color:#000;font-weight:700;border:none;border-radius:6px;cursor:pointer;" data-action="open-login">
                Buka Login &rarr;
              </button>
            </div>
          </div>

          <!-- Tahap 4: Upload / Scan Berkas Dosir -->
          <div class="workflow-step-card">
            <div class="workflow-card-top">
              <span class="workflow-step-num">TAHAP 04</span>
              <div class="workflow-icon-box" title="Unggah Dosir">📂</div>
            </div>
            <h3 class="workflow-card-title">Upload & Scan Berkas Dosir</h3>
            <p class="workflow-card-desc">
              Personel mengunggah berkas riwayat kedinasan sesuai 33 kategori dosir resmi (format PDF standar atau hasil scan kamera langsung via jsPDF).
            </p>
            <div class="workflow-callout-box callout-warning">
              ⚠️ <strong>Status Berkas Awal:</strong> Otomatis diberi cap tanda <em>"Belum Terverifikasi Dokumen"</em>.
            </div>
          </div>

          <!-- Tahap 5: Verifikasi Dokumen oleh Admin -->
          <div class="workflow-step-card">
            <div class="workflow-card-top">
              <span class="workflow-step-num">TAHAP 05</span>
              <div class="workflow-icon-box" title="Verifikasi Staf Pers">⚖️</div>
            </div>
            <h3 class="workflow-card-title">Verifikasi Dokumen Staf Pers</h3>
            <p class="workflow-card-desc">
              Tim verifikator memeriksa kelengkapan warkat, kejelasan cap dinas, keabsahan tanda tangan pejabat, dan nomor surat keputusan resmi.
            </p>
            <div class="workflow-callout-box callout-info">
              🔍 <strong>Pemeriksaan:</strong> Verifikator menentukan kelayakan dokumen untuk diterbitkan segel digital atau dikembalikan.
            </div>
          </div>

          <!-- Tahap 6: Keputusan Verifikasi & TTE (Branching) -->
          <div class="workflow-step-card workflow-step-6-card">
            <div class="workflow-card-top">
              <span class="workflow-step-num" style="background:rgba(212,175,55,0.25);">TAHAP 06 &bull; HASIL KEPUTUSAN VERIFIKASI</span>
              <div class="workflow-icon-box" title="Keputusan TTE">🔀</div>
            </div>
            <h3 class="workflow-card-title">Penerbitan TTE Resmi atau Perbaikan Dokumen</h3>
            <p class="workflow-card-desc" style="margin-bottom:6px;">
              Berdasarkan hasil uji berkas oleh Staf Verifikator, sistem menerapkan salah satu dari dua ketentuan status berikut:
            </p>
            
            <div class="decision-branches-grid">
              <!-- Cabang A: Approved -> TTE -->
              <div class="branch-box branch-approved">
                <div style="display:flex;align-items:center;gap:6px;font-weight:700;color:#4ade80;margin-bottom:6px;font-size:13.5px;">
                  <span>✓ JIKA DISETUJUI (APPROVE)</span>
                </div>
                <div style="color:var(--text-muted);font-size:12px;line-height:1.5;">
                  Cap awal dibersihkan dan digantikan <strong>Sertifikasi Tanda Tangan Elektronik (TTE) Resmi</strong>. Sistem menerbitkan kode registrasi unik, nilai hash SHA-256 integritas dokumen, dan <strong>QR Code segel digital</strong> yang dapat diuji keabsahannya secara online.
                </div>
              </div>

              <!-- Cabang B: Rejected / Belum Terverifikasi -->
              <div class="branch-box branch-rejected">
                <div style="display:flex;align-items:center;gap:6px;font-weight:700;color:#f87171;margin-bottom:6px;font-size:13.5px;">
                  <span>✕ JIKA DITOLAK / TIDAK SESUAI</span>
                </div>
                <div style="color:var(--text-muted);font-size:12px;line-height:1.5;">
                  Berkas <strong>tetap bertanda "Belum Terverifikasi Dokumen"</strong> disertai catatan dinas perbaikan dari admin. Personel dapat melihat alasan penolakan dan mengunggah ulang dokumen perbaikan yang sah.
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Tombol Aksi Utama di Bawah Alur -->
        <div class="workflow-cta-bar">
          <button type="button" class="cta-btn-login" id="workflowLoginBtn">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
              <polyline points="10 17 15 12 10 7"/>
              <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            <span>Masuk ke Sistem (Login)</span>
          </button>

          <a href="<?= BASE_URL ?>/register.php" class="cta-btn-register">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="8.5" cy="7" r="4"/>
              <line x1="20" y1="8" x2="20" y2="14"/>
              <line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
            <span>Registrasi Personel Baru</span>
          </a>

          <a href="<?= BASE_URL ?>/verify.php" class="cta-btn-verify">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7"/>
              <rect x="14" y="3" width="7" height="7"/>
              <rect x="14" y="14" width="7" height="7"/>
              <rect x="3" y="14" width="7" height="7"/>
            </svg>
            <span>Uji Keabsahan QR Code</span>
          </a>
        </div>

      </div>

      <!-- =================================================================
           2. FORMULIR LOGIN (Tampil Ketika Tombol Login Diklik atau Ada Error)
           ================================================================= -->
      <div class="login-card-container" id="loginCardContainer" <?= !$showLoginByDefault ? 'style="display:none;"' : '' ?>>
        <div class="glass-card" id="glassCard">
          <!-- Dynamic Light Specular Reflection Layer -->
          <div class="glass-card-glare"></div>

          <!-- Close Button 'X' at Top Right (Kembali ke Alur Aplikasi) -->
          <button type="button" class="card-close-btn" id="cardCloseBtn" title="Kembali ke Alur Aplikasi">
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

            <!-- Field 1: Email / Username / NRP -->
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

            <!-- Field 2: Password -->
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

            <!-- Row: Remember Me & Forgot Password -->
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

            <!-- Submit Button: "Login" -->
            <button type="submit" class="btn-submit-login" id="submitLoginBtn">
              <span class="spinner-icon"></span>
              <span id="submitBtnText">Login</span>
            </button>
          </form>

          <!-- Card Footer -->
          <div class="card-footer">
            Don't have an account? <a href="<?= BASE_URL ?>/register.php">Register</a>
          </div>

          <!-- Back to Workflow Link -->
          <div style="text-align:center;">
            <button type="button" class="back-to-workflow-link" id="backToWorkflowBtn">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
              </svg>
              <span>Kembali ke Alur Prosedur Aplikasi</span>
            </button>
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
          Catatan: Untuk personel yang baru mendaftar atau di-reset oleh Admin, kata sandi awal adalah <strong>sandi acak sementara (8 karakter)</strong> yang diberikan oleh Administrator Satuan. Anda wajib menggantinya saat pertama kali login.
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
