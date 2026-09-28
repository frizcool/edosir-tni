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
                $regData = [
                    'nrp'           => $nrp,
                    'nama'          => $nama,
                    'golongan'      => $golongan,
                    'pangkat_id'    => (int)($_POST['pangkat_id'] ?? 0) ?: null,
                    'pangkat'       => trim($_POST['pangkat'] ?? ''),
                    'korp_id'       => (int)($_POST['korp_id'] ?? 0) ?: null,
                    'korp'          => trim($_POST['korp'] ?? ''),
                    'kotama_id'     => (int)($_POST['kotama_id'] ?? 0) ?: null,
                    'kotama'        => trim($_POST['kotama'] ?? ''),
                    'satuan_id'     => (int)($_POST['satuan_id'] ?? 0) ?: null,
                    'satuan'        => trim($_POST['satuan'] ?? ''),
                    'jabatan'       => $jabatan,
                    'tmt_jabatan'   => $tmt_jabatan,
                    'tmt_pangkat'   => $tmt_pangkat,
                    'tempat_lahir'  => $tempat_lahir,
                    'tanggal_lahir' => $tanggal_lahir,
                    'jenis_kelamin' => $jenis_kelamin,
                    'tmt_pensiun_proyeksi' => hitung_proyeksi_pensiun($tanggal_lahir, $golongan),
                ];

                // Selesaikan relasi alami ke master data
                resolve_and_save_personel_relations($pdo, $regData);

                $stmt = $pdo->prepare(
                    "INSERT INTO personel (nrp, nama, golongan, pangkat_id, pangkat, korp_id, korp, satuan_id, satuan, kotama_id, kotama, jabatan, tmt_jabatan, tmt_pangkat, tempat_lahir, tanggal_lahir, jenis_kelamin, tmt_pensiun_proyeksi)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->execute([
                    $regData['nrp'], $regData['nama'], $regData['golongan'],
                    $regData['pangkat_id'], $regData['pangkat'],
                    $regData['korp_id'], $regData['korp'],
                    $regData['satuan_id'], $regData['satuan'],
                    $regData['kotama_id'], $regData['kotama'],
                    $regData['jabatan'], $regData['tmt_jabatan'], $regData['tmt_pangkat'],
                    $regData['tempat_lahir'], $regData['tanggal_lahir'], $regData['jenis_kelamin'],
                    $regData['tmt_pensiun_proyeksi']
                ]);
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

// Master Data Relasional untuk Pendaftaran
$masterPangkatAll = get_master_pangkat_list($pdo);
$masterKorpAll    = get_master_korp_list($pdo);
$masterKotamaAll  = get_master_kotama_list($pdo);
$masterSatuanAll  = get_master_satuan_list($pdo);

$korpByKategori = [];
foreach ($masterKorpAll as $mk) {
    $korpByKategori[$mk['kategori']][] = $mk;
}

$pageTitle = 'Registrasi Personel';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="card auth-card" style="max-width:760px;width:100%;margin:0 auto;">
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
          <input name="nrp" value="<?= htmlspecialchars($_POST['nrp'] ?? '') ?>" placeholder="NRP (sebagai Username & Password)" required autofocus>
        </div>
        <div>
          <label>Golongan Kepangkatan *</label>
          <select name="golongan" id="golonganSelect" onchange="filterPangkatByGolongan()" required>
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
          <label>Pangkat Resmi</label>
          <select name="pangkat_id" id="pangkatSelect" onchange="syncPangkatFields()">
            <option value="">-- Pilih Pangkat --</option>
            <?php foreach ($masterPangkatAll as $pkt): ?>
              <option value="<?= $pkt['id'] ?>"
                      data-golongan="<?= $pkt['golongan'] ?>"
                      data-singkatan="<?= htmlspecialchars($pkt['singkatan']) ?>"
                      <?= ((int)($_POST['pangkat_id'] ?? 0) === (int)$pkt['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($pkt['singkatan']) ?> - <?= htmlspecialchars($pkt['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="pangkat" id="pangkatText" value="<?= htmlspecialchars($_POST['pangkat'] ?? '') ?>">
        </div>

        <div>
          <label>Korp / Kecabangan</label>
          <select name="korp_id" id="korpSelect" onchange="syncKorpFields()">
            <option value="">-- Tanpa Korp / Non-Kecabangan (PNS) --</option>
            <?php foreach ($korpByKategori as $kat => $korpGroup): ?>
              <optgroup label="Kecabangan <?= htmlspecialchars($kat) ?>">
                <?php foreach ($korpGroup as $krp): ?>
                  <option value="<?= $krp['id'] ?>"
                          data-kode="<?= htmlspecialchars($krp['kode']) ?>"
                          <?= ((int)($_POST['korp_id'] ?? 0) === (int)$krp['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($krp['kode']) ?> - <?= htmlspecialchars($krp['nama']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="korp" id="korpText" value="<?= htmlspecialchars($_POST['korp'] ?? '') ?>">
        </div>

        <div>
          <label>Kotama / Balakpus Induk</label>
          <select name="kotama_id" id="kotamaSelect" onchange="filterSatuanByKotama()">
            <option value="">-- Pilih Kotama / Balakpus --</option>
            <?php foreach ($masterKotamaAll as $kot): ?>
              <option value="<?= $kot['id'] ?>"
                      data-nama="<?= htmlspecialchars($kot['nama']) ?>"
                      <?= ((int)($_POST['kotama_id'] ?? 0) === (int)$kot['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($kot['kode']) ?> - <?= htmlspecialchars($kot['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="kotama" id="kotamaText" value="<?= htmlspecialchars($_POST['kotama'] ?? '') ?>">
        </div>

        <div>
          <label>Satuan Organik</label>
          <select name="satuan_id" id="satuanSelect" onchange="syncSatuanFields()">
            <option value="">-- Pilih Satuan Organik --</option>
            <?php foreach ($masterSatuanAll as $sat): ?>
              <option value="<?= $sat['id'] ?>"
                      data-kotama="<?= $sat['kotama_id'] ?>"
                      data-nama="<?= htmlspecialchars($sat['nama']) ?>"
                      <?= ((int)($_POST['satuan_id'] ?? 0) === (int)$sat['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sat['nama']) ?>
              </option>
            <?php endforeach; ?>
            <option value="custom" <?= (!empty($_POST['satuan']) && empty($_POST['satuan_id'])) ? 'selected' : '' ?>>-- Satuan Lainnya / Tulis Manual --</option>
          </select>
        </div>

        <div style="grid-column:1/-1;">
          <input name="satuan" id="satuanCustomInput" value="<?= htmlspecialchars($_POST['satuan'] ?? '') ?>" placeholder="Tuliskan nama satuan bila tidak ada di daftar..." style="<?= (!empty($_POST['satuan']) && empty($_POST['satuan_id'])) ? '' : 'display:none;' ?>">
        </div>

        <div>
          <label>Jenis Kelamin</label>
          <select name="jenis_kelamin">
            <option value="L" <?= ($_POST['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
            <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
          </select>
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
          <input name="tempat_lahir" value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>" placeholder="cth: Jakarta / Bandung">
        </div>
        <div>
          <label>Tanggal Lahir</label>
          <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn" style="width:100%;margin-top:16px;padding:12px;">Daftar Personel</button>
    </form>
    <p style="text-align:center;font-size:13px;margin-top:16px;color:var(--text-dim);">
      Sudah punya akun yang diverifikasi? <a href="<?= BASE_URL ?>/login.php" style="color:var(--gold);font-weight:600;">Masuk di sini</a>
    </p>
    <?php endif; ?>
  </div>
</div>

<script>
function filterPangkatByGolongan() {
  var gol = document.getElementById('golonganSelect').value;
  var sel = document.getElementById('pangkatSelect');
  var opts = sel.querySelectorAll('option');

  var currentSelectedValid = false;
  opts.forEach(function(opt) {
    if (!opt.value) return;
    var optGol = opt.getAttribute('data-golongan');
    if (!gol || optGol === gol) {
      opt.style.display = '';
      if (opt.selected) currentSelectedValid = true;
    } else {
      opt.style.display = 'none';
      if (opt.selected) opt.selected = false;
    }
  });

  if (!currentSelectedValid && gol) {
    for (var i = 0; i < opts.length; i++) {
      if (opts[i].value && opts[i].getAttribute('data-golongan') === gol) {
        opts[i].selected = true;
        break;
      }
    }
  }
  syncPangkatFields();
}

function syncPangkatFields() {
  var sel = document.getElementById('pangkatSelect');
  var opt = sel.options[sel.selectedIndex];
  if (opt && opt.value) {
    document.getElementById('pangkatText').value = opt.getAttribute('data-singkatan') || '';
  }
}

function syncKorpFields() {
  var sel = document.getElementById('korpSelect');
  var opt = sel.options[sel.selectedIndex];
  if (opt && opt.value) {
    document.getElementById('korpText').value = opt.getAttribute('data-kode') || '';
  } else {
    document.getElementById('korpText').value = '';
  }
}

function filterSatuanByKotama() {
  var kotamaId = document.getElementById('kotamaSelect').value;
  var selKot = document.getElementById('kotamaSelect');
  var optKot = selKot.options[selKot.selectedIndex];
  if (optKot && optKot.value) {
    document.getElementById('kotamaText').value = optKot.getAttribute('data-nama') || '';
  } else {
    document.getElementById('kotamaText').value = '';
  }

  var selSat = document.getElementById('satuanSelect');
  var optsSat = selSat.querySelectorAll('option');

  optsSat.forEach(function(opt) {
    if (!opt.value || opt.value === 'custom') return;
    var satKot = opt.getAttribute('data-kotama');
    if (!kotamaId || satKot === kotamaId || !satKot) {
      opt.style.display = '';
    } else {
      opt.style.display = 'none';
    }
  });
}

function syncSatuanFields() {
  var sel = document.getElementById('satuanSelect');
  var opt = sel.options[sel.selectedIndex];
  var customInput = document.getElementById('satuanCustomInput');

  if (opt && opt.value === 'custom') {
    customInput.style.display = '';
    customInput.focus();
  } else if (opt && opt.value) {
    customInput.style.display = 'none';
    customInput.value = opt.getAttribute('data-nama') || '';
    var satKot = opt.getAttribute('data-kotama');
    if (satKot) {
      var kotSel = document.getElementById('kotamaSelect');
      if (!kotSel.value || kotSel.value !== satKot) {
        kotSel.value = satKot;
        filterSatuanByKotama();
      }
    }
  } else {
    customInput.style.display = 'none';
    customInput.value = '';
  }
}

document.addEventListener('DOMContentLoaded', function() {
  var gol = document.getElementById('golonganSelect').value;
  if (gol) filterPangkatByGolongan();
  var kot = document.getElementById('kotamaSelect').value;
  if (kot) filterSatuanByKotama();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
