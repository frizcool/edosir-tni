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

$pageTitle = 'Masuk';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="card auth-card">
    <div style="text-align:center;margin-bottom:18px;">
      <?php $appLogo = app_logo_url(); ?>
      <?php if ($appLogo): ?>
        <img src="<?= $appLogo ?>" alt="Logo" style="height:60px;max-width:180px;object-fit:contain;margin:0 auto 10px;display:block;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.35));">
      <?php else: ?>
        <div class="brand-badge" style="margin:0 auto 10px;">★</div>
      <?php endif; ?>
      <h2 style="margin:0;"><?= htmlspecialchars(get_setting($pdo, 'app_name', APP_NAME)) ?></h2>
      <div style="color:var(--text-dim);font-size:13px;margin-top:3px;"><?= htmlspecialchars(get_setting($pdo, 'app_subtitle', 'Sistem Dosir Elektronik Personel')) ?></div>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <label>Username / NRP</label>
      <input type="text" name="username" placeholder="Masukkan NRP atau Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
      
      <label>Kata Sandi</label>
      <input type="password" name="password" placeholder="Kata Sandi (NRP untuk personel baru)" required>
      <span style="font-size:11.5px;color:var(--text-dim);display:block;margin-top:-8px;margin-bottom:14px;">
        Personel baru: gunakan NRP sebagai Username dan Kata Sandi awal setelah diverifikasi admin.
      </span>

      <button type="submit" class="btn" style="width:100%;">Masuk</button>
    </form>
    <p style="text-align:center;font-size:13px;margin-top:16px;color:var(--text-dim);">
      Belum punya akun? <a href="<?= BASE_URL ?>/register.php" style="color:var(--gold);font-weight:600;">Registrasi Personel</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
