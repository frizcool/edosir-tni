<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect($_SESSION['user']['role'] === 'admin' ? '/admin/dashboard.php' : '/personel/dashboard.php');
}

$error = null;
$success = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $nrp           = trim($_POST['nrp'] ?? '');
    $nama          = trim($_POST['nama'] ?? '');
    $golongan      = $_POST['golongan'] ?? '';
    $pangkat       = trim($_POST['pangkat'] ?? '');
    $korp          = trim($_POST['korp'] ?? '');
    $satuan        = trim($_POST['satuan'] ?? '');
    $kotama        = trim($_POST['kotama'] ?? '');
    $jabatan       = trim($_POST['jabatan'] ?? '');
    $tmt_jabatan   = $_POST['tmt_jabatan'] ?: null;
    $tmt_pangkat   = $_POST['tmt_pangkat'] ?: null;
    $tempat_lahir  = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?: null;
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';

    if ($nrp === '' || $nama === '' || $golongan === '') {
        $error = 'NRP, Nama Lengkap, dan Golongan wajib diisi.';
    } elseif (!preg_match('/^[A-Za-z0-9\-\.\/]{4,30}$/', $nrp)) {
        $error = 'Format NRP tidak valid. NRP harus terdiri dari huruf atau angka (4-30 karakter).';
    } else {
        // Cek duplikasi di tabel personel dan users
        $chk = $pdo->prepare("SELECT COUNT(*) FROM personel WHERE nrp = ?");
        $chk->execute([$nrp]);
        $chkUser = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $chkUser->execute([$nrp]);

        if ($chk->fetchColumn() > 0 || $chkUser->fetchColumn() > 0) {
            $error = 'NRP ' . htmlspecialchars($nrp) . ' sudah terdaftar. Hubungi admin bila Anda memerlukan bantuan akun.';
        } else {
            $pdo->beginTransaction();
            try {
                $tmt_pensiun_proyeksi = hitung_proyeksi_pensiun($tanggal_lahir, $golongan);
                $stmt = $pdo->prepare(
                    "INSERT INTO personel (nrp, nama, golongan, pangkat, korp, satuan, kotama, jabatan, tmt_jabatan, tmt_pangkat, tempat_lahir, tanggal_lahir, jenis_kelamin, tmt_pensiun_proyeksi)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([$nrp, $nama, $golongan, $pangkat, $korp, $satuan, $kotama, $jabatan, $tmt_jabatan, $tmt_pangkat, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $tmt_pensiun_proyeksi]);
                $personel_id = (int)$pdo->lastInsertId();

                // Otomatis buatkan akun user: username = NRP, password = hash(NRP), status = pending
                ensure_personel_user($pdo, $personel_id, $nrp, 'pending');

                $pdo->commit();
                log_activity($pdo, null, 'REGISTER', "Registrasi baru: NRP $nrp ($nama)");
                $success = "Registrasi berhasil! Akun Anda telah dibuat dengan Username dan Kata Sandi awal berupa NRP ($nrp). Akun sedang menunggu verifikasi/persetujuan Admin sebelum dapat digunakan untuk masuk.";
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Register Error: ' . $e->getMessage());
                $error = 'Gagal menyimpan data registrasi ke pangkalan data. Silakan periksa kembali data Anda atau hubungi Admin Satuan.';
            }
        }
    }
}

$satuanSuggestions = get_distinct_personel_field($pdo, 'satuan');
$kotamaSuggestions = get_distinct_personel_field($pdo, 'kotama');
$korpSuggestions = get_distinct_personel_field($pdo, 'korp');
$pangkatSuggestions = get_distinct_personel_field($pdo, 'pangkat');

$pageTitle = 'Registrasi Personel';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="card auth-card" style="width:680px;">
    <div style="text-align:center;margin-bottom:16px;">
      <?php $appLogo = app_logo_url(); ?>
      <?php if ($appLogo): ?>
        <img src="<?= $appLogo ?>" alt="Logo" style="height:56px;max-width:180px;object-fit:contain;margin:0 auto 10px;display:block;filter:drop-shadow(0 4px 8px rgba(0,0,0,0.35));">
      <?php else: ?>
        <div class="brand-badge" style="margin:0 auto 10px;">★</div>
      <?php endif; ?>
      <h2 style="margin:0;">Registrasi Personel</h2>
      <div style="color:var(--text-dim);font-size:13px;margin-top:4px;">
        Pendaftaran Akun Dosir Elektronik &middot; <?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?>
      </div>
    </div>

    <div style="background:var(--panel-2);border:1px solid var(--border);border-left:4px solid var(--gold);padding:12px 14px;border-radius:8px;margin-bottom:18px;font-size:13px;line-height:1.5;">
      <strong>Ketentuan Akun Login:</strong><br>
      Username dan Kata Sandi awal otomatis menggunakan <strong>NRP</strong> Anda.<br>
      <span style="color:var(--text-dim);">Setelah registrasi, akun berstatus <em>Pending</em> dan harus diverifikasi oleh Admin sebelum Anda dapat masuk.</span>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" style="line-height:1.6;"><?= htmlspecialchars($success) ?></div>
      <div style="text-align:center;margin-top:20px;">
        <a href="<?= BASE_URL ?>/login.php" class="btn" style="padding:10px 24px;">Ke Halaman Login</a>
      </div>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="grid grid-2">
        <div>
          <label>NRP *</label>
          <input name="nrp" value="<?= htmlspecialchars($_POST['nrp'] ?? '') ?>" placeholder="Masukkan NRP (sebagai Username & Password)" required autofocus>
        </div>
        <div>
          <label>Golongan *</label>
          <select name="golongan" required>
            <option value="">-- Pilih Golongan --</option>
            <?php foreach (['Perwira','Bintara','Tamtama','PNS'] as $g): ?>
              <option value="<?= $g ?>" <?= ($_POST['golongan'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="grid-column:1/-1;">
          <label>Nama Lengkap *</label>
          <input name="nama" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" placeholder="Nama lengkap beserta gelar (jika ada)" required>
        </div>
        <div>
          <label>Pangkat</label>
          <input name="pangkat" list="pangkatList" value="<?= htmlspecialchars($_POST['pangkat'] ?? '') ?>" placeholder="cth: Kapten Inf / Serka / Praka">
          <datalist id="pangkatList">
            <?php foreach ($pangkatSuggestions as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label>Korp</label>
          <input name="korp" list="korpList" value="<?= htmlspecialchars($_POST['korp'] ?? '') ?>" placeholder="cth: Inf, Kav, Arm, Arh, Cba, dsb">
          <datalist id="korpList">
            <?php foreach ($korpSuggestions as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label>Jenis Kelamin</label>
          <select name="jenis_kelamin">
            <option value="L" <?= ($_POST['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
          </select>
        </div>
        <div>
          <label>Satuan</label>
          <input name="satuan" list="satuanList" value="<?= htmlspecialchars($_POST['satuan'] ?? '') ?>" placeholder="cth: Yonif 403 / Kodim 0734">
          <datalist id="satuanList">
            <?php foreach ($satuanSuggestions as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label>Kotama / Balakpus</label>
          <input name="kotama" list="kotamaList" value="<?= htmlspecialchars($_POST['kotama'] ?? '') ?>" placeholder="cth: Kodam IV/Diponegoro">
          <datalist id="kotamaList">
            <?php foreach ($kotamaSuggestions as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label>Jabatan</label>
          <input name="jabatan" value="<?= htmlspecialchars($_POST['jabatan'] ?? '') ?>" placeholder="cth: Pasi Intel / Danramil">
        </div>
        <div>
          <label>TMT Jabatan</label>
          <input type="date" name="tmt_jabatan" value="<?= htmlspecialchars($_POST['tmt_jabatan'] ?? '') ?>">
        </div>
        <div>
          <label>TMT Pangkat</label>
          <input type="date" name="tmt_pangkat" value="<?= htmlspecialchars($_POST['tmt_pangkat'] ?? '') ?>">
        </div>
        <div>
          <label>Tempat Lahir</label>
          <input name="tempat_lahir" value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>" placeholder="cth: Jakarta / Surabaya">
        </div>
        <div>
          <label>Tanggal Lahir</label>
          <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn" style="width:100%;margin-top:10px;">Daftar Personel</button>
    </form>
    <p style="text-align:center;font-size:13px;margin-top:16px;color:var(--text-dim);">
      Sudah punya akun yang diverifikasi? <a href="<?= BASE_URL ?>/login.php" style="color:var(--gold);font-weight:600;">Masuk di sini</a>
    </p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
