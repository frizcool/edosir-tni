<?php
$u = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(APP_ROOT . '/assets/css/style.css') ?>">
</head>
<body>
<?php if ($u): ?>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">
      <?php $appLogo = app_logo_url(); ?>
      <?php if ($appLogo): ?>
        <img src="<?= $appLogo ?>" alt="Logo" class="brand-logo" style="width:36px;height:36px;object-fit:contain;border-radius:6px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.3));flex-shrink:0;">
      <?php else: ?>
        <span class="brand-badge">★</span>
      <?php endif; ?>
      <div>
        <div class="brand-title"><?= htmlspecialchars(get_setting($pdo, 'app_brand_title', 'TRISULA')) ?></div>
        <div class="brand-sub"><?= htmlspecialchars(get_setting($pdo, 'app_brand_sub', 'TNI AD')) ?></div>
      </div>
    </div>
    <nav class="nav">
      <?php 
      $currPath = $_SERVER['PHP_SELF'] ?? '';
      $isActive = fn($file) => strpos($currPath, $file) !== false ? ' active' : '';
      $badges = get_system_badge_counts($pdo, $u);
      ?>
      <?php if ($u['role'] === 'personel'): ?>
        <a href="<?= BASE_URL ?>/personel/dashboard.php" class="nav-link<?= $isActive('dashboard.php') ?>" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Dashboard</span>
          <?php if ($badges['rejected_dosirs'] > 0): ?>
            <span class="badge badge-rejected" style="font-size:10px;padding:2px 6px;font-weight:700;"><?= $badges['rejected_dosirs'] ?> Revisi</span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/personel/upload.php" class="nav-link<?= $isActive('upload.php') ?>">Unggah Dosir</a>
        <a href="<?= BASE_URL ?>/personel/scan.php" class="nav-link<?= $isActive('scan.php') ?>">Scan Kamera</a>
        <a href="<?= BASE_URL ?>/personel/profile.php" class="nav-link<?= $isActive('profile.php') ?>">Profil Saya</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link<?= $isActive('dashboard.php') ?>">Dashboard</a>
        <a href="<?= BASE_URL ?>/admin/users_approve.php" class="nav-link<?= $isActive('users_approve.php') ?>" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Approval Akun</span>
          <?php if ($badges['pending_users'] > 0): ?>
            <span class="badge badge-pending" style="font-size:10px;padding:2px 6px;font-weight:700;"><?= $badges['pending_users'] ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/admin/personel_list.php" class="nav-link<?= $isActive('personel_list.php') || $isActive('personel_detail.php') || $isActive('personel_form.php') ? ' active' : '' ?>">Data Personel</a>
        <a href="<?= BASE_URL ?>/admin/dosir_verify.php" class="nav-link<?= $isActive('dosir_verify.php') ?>" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Verifikasi Dosir</span>
          <?php if ($badges['pending_dosirs'] > 0): ?>
            <span class="badge badge-pending" style="font-size:10px;padding:2px 6px;font-weight:700;"><?= $badges['pending_dosirs'] ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/admin/bulk_download.php" class="nav-link<?= $isActive('bulk_download.php') ?>">Unduh Massal</a>
        <a href="<?= BASE_URL ?>/admin/report.php" class="nav-link<?= $isActive('report.php') ?>">Laporan</a>
        <a href="<?= BASE_URL ?>/admin/dosir_master.php" class="nav-link<?= $isActive('dosir_master.php') ?>">Master Dosir</a>
        <a href="<?= BASE_URL ?>/admin/activity_log.php" class="nav-link<?= $isActive('activity_log.php') ?>">Log Aktivitas</a>
        <a href="<?= BASE_URL ?>/admin/backup.php" class="nav-link<?= $isActive('backup.php') ?>">Backup Data</a>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="nav-link<?= $isActive('settings.php') ?>">Pengaturan</a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-foot">
      <button id="themeToggle" class="btn-ghost" type="button">🌓 Tema</button>
      <a href="<?= BASE_URL ?>/logout.php" class="btn-ghost">⏻ Keluar</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
      <div class="topbar-user">
        <?php $userFotoUrl = foto_url($u['foto'] ?? ''); ?>
        <?php if ($userFotoUrl): ?>
          <img src="<?= $userFotoUrl ?>" alt="Foto" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--gold);box-shadow:0 2px 6px rgba(0,0,0,.2);">
        <?php endif; ?>
        <span class="rank"><?= htmlspecialchars($u['pangkat'] ?? strtoupper($u['role'])) ?></span>
        <strong><?= htmlspecialchars($u['nama'] ?? $u['username']) ?></strong>
        <?php if (!empty($u['nrp'])): ?><span class="nrp"><?= htmlspecialchars($u['nrp']) ?></span><?php endif; ?>
      </div>
    </header>
    <main class="content">
      <?php $f = get_flash(); if ($f): ?>
        <div class="alert alert-<?= htmlspecialchars($f['type']) ?>"><?= htmlspecialchars($f['msg']) ?></div>
      <?php endif; ?>
<?php else: ?>
  <?php $f = get_flash(); if ($f): ?>
    <div class="alert alert-<?= htmlspecialchars($f['type']) ?>" style="margin:16px auto;max-width:420px;"><?= htmlspecialchars($f['msg']) ?></div>
  <?php endif; ?>
<?php endif; ?>
