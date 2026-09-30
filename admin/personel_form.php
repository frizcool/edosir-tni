<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$id = (int) ($_GET['id'] ?? 0);
$p = [
    'nrp' => '', 'nama' => '', 'golongan' => 'Perwira',
    'pangkat_id' => null, 'pangkat' => '',
    'korp_id' => null, 'korp' => '',
    'kotama_id' => null, 'kotama' => '',
    'satuan_id' => null, 'satuan' => '',
    'jabatan' => '', 'tmt_jabatan' => '', 'tmt_pangkat' => '',
    'tanggal_lahir' => '', 'tempat_lahir' => '',
    'jenis_kelamin' => 'L', 'agama' => '', 'status_kawin' => '',
    'alamat' => '', 'no_hp' => '', 'email' => '', 'status_dinas' => 'Aktif'
];

$userAccount = null;
if ($id) {
    $found = get_personel_lengkap($pdo, $id);
    if ($found) {
        $p = $found;
    }

    $stmtU = $pdo->prepare("SELECT * FROM users WHERE personel_id=?");
    $stmtU->execute([$id]);
    $userAccount = $stmtU->fetch();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pangkatId = (int)($_POST['pangkat_id'] ?? 0) ?: null;
    $satuanId  = (int)($_POST['satuan_id'] ?? 0) ?: null;
    $kotamaId  = (int)($_POST['kotama_id'] ?? 0) ?: null;

    // Jika kotama_id belum diisi tapi satuan_id ada, ambil kotama_id dari master_satuan
    if (!$kotamaId && $satuanId) {
        $stK = $pdo->prepare("SELECT kotama_id FROM master_satuan WHERE id = ?");
        $stK->execute([$satuanId]);
        $kotamaId = $stK->fetchColumn() ?: null;
    }

    // Ambil golongan dari master_pangkat untuk kalkulasi proyeksi pensiun
    $golongan = $_POST['golongan'] ?? 'Perwira';
    if ($pangkatId) {
        $stG = $pdo->prepare("SELECT golongan FROM master_pangkat WHERE id = ?");
        $stG->execute([$pangkatId]);
        $golongan = $stG->fetchColumn() ?: $golongan;
    }

    $data = [
        'nrp'         => trim($_POST['nrp']),
        'nama'        => trim($_POST['nama']),
        'pangkat_id'  => $pangkatId,
        'korp_id'     => (int)($_POST['korp_id'] ?? 0) ?: null,
        'satuan_id'   => $satuanId,
        'kotama_id'   => $kotamaId,
        'jabatan'     => trim($_POST['jabatan'] ?? ''),
        'tmt_jabatan' => $_POST['tmt_jabatan'] ?: null,
        'tmt_pangkat' => $_POST['tmt_pangkat'] ?: null,
        'tanggal_lahir' => $_POST['tanggal_lahir'] ?: null,
        'tempat_lahir'  => trim($_POST['tempat_lahir'] ?? ''),
        'jenis_kelamin' => $_POST['jenis_kelamin'] ?? 'L',
        'agama'         => trim($_POST['agama'] ?? ''),
        'status_kawin'  => trim($_POST['status_kawin'] ?? ''),
        'alamat'        => trim($_POST['alamat'] ?? ''),
        'no_hp'         => trim($_POST['no_hp'] ?? ''),
        'email'         => trim($_POST['email'] ?? ''),
        'status_dinas'  => $_POST['status_dinas'] ?? 'Aktif',
        'tmt_pensiun_proyeksi' => hitung_proyeksi_pensiun($_POST['tanggal_lahir'] ?: null, $golongan),
    ];

    // Sinkronisasi teks satuan manual jika satuan_id belum terisi
    $customSatText = trim($_POST['satuan'] ?? '');
    if (!$satuanId && $customSatText !== '' && $customSatText !== 'custom') {
        $data['satuan'] = $customSatText;
    }

    // Selesaikan relasi master rujukan (master_pangkat, master_korp, master_satuan, master_kotama)
    resolve_and_save_personel_relations($pdo, $data);

    $statusAkun = $_POST['status_akun'] ?? 'approved';
    if (!in_array($statusAkun, ['approved', 'pending'], true)) {
        $statusAkun = 'approved';
    }

    // Cek upload foto jika ada
    if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['foto'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $imgInfo = @getimagesize($f['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (in_array($ext, $allowed, true) && $imgInfo && in_array($imgInfo['mime'], $allowedMimes, true) && $f['size'] <= 2 * 1024 * 1024) {
            $dir = UPLOAD_DIR . '/foto_profil';
            ensure_dir($dir);
            $newName = 'foto_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $dir . '/' . $newName)) {
                $data['foto'] = 'uploads/foto_profil/' . $newName;
            }
        } else {
            $error = 'Berkas foto tidak valid atau melebihi batas 2 MB. Gunakan format JPG, PNG, atau WEBP asli.';
        }
    }

    if ($data['nrp'] === '' || $data['nama'] === '') {
        $error = 'NRP dan Nama wajib diisi.';
    } else {
        // Cek duplikasi NRP
        $chkSql = "SELECT id FROM personel WHERE nrp = ?";
        $chkParams = [$data['nrp']];
        if ($id) {
            $chkSql .= " AND id != ?";
            $chkParams[] = $id;
        }
        $chk = $pdo->prepare($chkSql);
        $chk->execute($chkParams);

        if ($chk->fetch()) {
            $error = 'NRP ' . htmlspecialchars($data['nrp']) . ' sudah terdaftar pada personel lain.';
        } else {
            try {
                // Periksa skema tabel personel saat ini untuk toleransi terhadap migrasi database
                $personelCols = [];
                try {
                    $colStmt = $pdo->query("SHOW COLUMNS FROM personel");
                    while ($colRow = $colStmt->fetch(PDO::FETCH_ASSOC)) {
                        $personelCols[] = $colRow['Field'];
                    }
                } catch (Throwable $e) {}

                // Saring field yang valid untuk tabel personel
                $fieldsToSave = [];
                foreach ($data as $colName => $colVal) {
                    if (empty($personelCols) || in_array($colName, $personelCols, true)) {
                        $fieldsToSave[$colName] = $colVal;
                    }
                }
                // Tambahkan kolom warisan jika tabel produksi belum di-drop kolom lama
                $legacyCols = ['golongan', 'pangkat', 'korp', 'satuan', 'kotama'];
                foreach ($legacyCols as $lc) {
                    if (in_array($lc, $personelCols, true) && !isset($fieldsToSave[$lc])) {
                        $fieldsToSave[$lc] = $data[$lc] ?? '';
                    }
                }

                if ($id) {
                    $sets = implode(',', array_map(fn($k) => "`$k`=?", array_keys($fieldsToSave)));
                    $stmt = $pdo->prepare("UPDATE personel SET $sets WHERE id=?");
                    $stmt->execute([...array_values($fieldsToSave), $id]);

                    // Pastikan akun user tetap sinkron dengan NRP
                    ensure_personel_user($pdo, $id, $data['nrp'], 'approved');

                    log_activity($pdo, $admin['id'], 'UPDATE_PERSONEL', "Update personel #$id ({$data['nrp']})");
                    set_flash('success', 'Data personel dan relasi master berhasil diperbarui.');
                    redirect('/admin/personel_detail.php?id=' . $id);
                } else {
                    $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($fieldsToSave)));
                    $marks = implode(',', array_fill(0, count($fieldsToSave), '?'));
                    $stmt = $pdo->prepare("INSERT INTO personel ($cols) VALUES ($marks)");
                    $stmt->execute(array_values($fieldsToSave));
                    $newId = (int)$pdo->lastInsertId();

                    // Otomatis buatkan akun user untuk data personel baru
                    ensure_personel_user($pdo, $newId, $data['nrp'], $statusAkun);

                    log_activity($pdo, $admin['id'], 'CREATE_PERSONEL', "Tambah personel baru #$newId (NRP {$data['nrp']}) - Akun $statusAkun");
                    set_flash('success', "Personel baru berhasil ditambahkan dan akun login (NRP: {$data['nrp']}) otomatis dibuatkan dengan status " . strtoupper($statusAkun) . ".");
                    redirect('/admin/personel_detail.php?id=' . $newId);
                }
            } catch (Throwable $e) {
                $error = 'Terjadi kesalahan sistem saat menyimpan data personel: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Master Data untuk Formulir Natural
$masterPangkatAll = get_master_pangkat_list($pdo);
$masterKorpAll    = get_master_korp_list($pdo);
$masterKotamaAll  = get_master_kotama_list($pdo);
$masterSatuanAll  = get_master_satuan_list($pdo);

// Kelompokkan Korp berdasarkan Kategori
$korpByKategori = [];
foreach ($masterKorpAll as $mk) {
    $korpByKategori[$mk['kategori']][] = $mk;
}

$pageTitle = $id ? 'Edit Personel' : 'Tambah Personel';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:820px;margin:0 auto;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid var(--border);padding-bottom:12px;">
    <div>
      <h3 style="margin:0;"><?= $pageTitle ?></h3>
      <div style="font-size:12.5px;color:var(--text-dim);margin-top:2px;">
        Formulir personel dengan integrasi relasi alami (natural master RDBMS TNI AD).
      </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/personel_list.php" class="btn btn-outline" style="font-size:12px;padding:6px 14px;">Kembali</a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="grid grid-2">
      <div>
        <label>NRP *</label>
        <input name="nrp" value="<?= htmlspecialchars($p['nrp']) ?>" placeholder="Nomor Registrasi Pokok" required>
      </div>
      <div>
        <label>Nama Lengkap *</label>
        <input name="nama" value="<?= htmlspecialchars($p['nama']) ?>" placeholder="Nama prajurit / PNS" required>
      </div>

      <!-- Golongan & Pangkat Cascading Natural -->
      <div>
        <label>Golongan Kepangkatan</label>
        <select name="golongan" id="golonganSelect" onchange="filterPangkatByGolongan()">
          <?php foreach (['Perwira','Bintara','Tamtama','PNS'] as $g): ?>
            <option value="<?= $g ?>" <?= $p['golongan']===$g?'selected':'' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Pangkat Resmi</label>
        <select name="pangkat_id" id="pangkatSelect" onchange="syncPangkatFields()">
          <option value="">-- Pilih Pangkat --</option>
          <?php foreach ($masterPangkatAll as $pkt): ?>
            <option value="<?= $pkt['id'] ?>"
                    data-golongan="<?= $pkt['golongan'] ?>"
                    data-singkatan="<?= htmlspecialchars($pkt['singkatan']) ?>"
                    <?= ((int)$p['pangkat_id'] === (int)$pkt['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($pkt['singkatan']) ?> - <?= htmlspecialchars($pkt['nama']) ?> (BUP: <?= $pkt['bup_usia'] ?> thn)
            </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="pangkat" id="pangkatText" value="<?= htmlspecialchars($p['pangkat']) ?>">
      </div>

      <!-- Korp Kecabangan Natural Grouped -->
      <div>
        <label>Korp / Kecabangan</label>
        <select name="korp_id" id="korpSelect" onchange="syncKorpFields()">
          <option value="">-- Tanpa Korp / Non-Kecabangan (cth: PNS) --</option>
          <?php foreach ($korpByKategori as $kat => $korpGroup): ?>
            <optgroup label="Kecabangan <?= htmlspecialchars($kat) ?>">
              <?php foreach ($korpGroup as $krp): ?>
                <option value="<?= $krp['id'] ?>"
                        data-kode="<?= htmlspecialchars($krp['kode']) ?>"
                        <?= ((int)$p['korp_id'] === (int)$krp['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($krp['kode']) ?> - <?= htmlspecialchars($krp['nama']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="korp" id="korpText" value="<?= htmlspecialchars($p['korp']) ?>">
      </div>

      <!-- Kotama Induk -->
      <div>
        <label>Kotama / Balakpus Induk</label>
        <select name="kotama_id" id="kotamaSelect" onchange="filterSatuanByKotama()">
          <option value="">-- Pilih Kotama / Balakpus --</option>
          <?php foreach ($masterKotamaAll as $kot): ?>
            <option value="<?= $kot['id'] ?>"
                    data-nama="<?= htmlspecialchars($kot['nama']) ?>"
                    <?= ((int)$p['kotama_id'] === (int)$kot['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($kot['kode']) ?> - <?= htmlspecialchars($kot['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="kotama" id="kotamaText" value="<?= htmlspecialchars($p['kotama']) ?>">
      </div>

      <!-- Satuan Organik Cascading -->
      <div style="grid-column:1/-1;">
        <label>Satuan Organik</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <select name="satuan_id" id="satuanSelect" onchange="syncSatuanFields()">
              <option value="">-- Pilih Satuan Organik Master --</option>
              <?php foreach ($masterSatuanAll as $sat): ?>
                <option value="<?= $sat['id'] ?>"
                        data-kotama="<?= $sat['kotama_id'] ?>"
                        data-nama="<?= htmlspecialchars($sat['nama']) ?>"
                        data-lokasi="<?= htmlspecialchars($sat['lokasi'] ?? '') ?>"
                        <?= ((int)$p['satuan_id'] === (int)$sat['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sat['nama']) ?> <?= !empty($sat['lokasi']) ? '('.htmlspecialchars($sat['lokasi']).')' : '' ?>
                </option>
              <?php endforeach; ?>
              <option value="custom" <?= (empty($p['satuan_id']) && !empty($p['satuan'])) ? 'selected' : '' ?>>-- Satuan Lainnya / Tulis Manual --</option>
            </select>
          </div>
          <div>
            <input name="satuan" id="satuanCustomInput" value="<?= htmlspecialchars($p['satuan']) ?>" placeholder="Nama Satuan (cth: Yonif 312/Kala Hitam)">
          </div>
        </div>
        <span style="font-size:11.5px;color:var(--text-dim);margin-top:4px;display:block;">
          Pilih dari master rujukan untuk relasi otomatis atau ketikkan satuan bila belum tercantum dalam master.
        </span>
      </div>

      <div><label>Jabatan</label><input name="jabatan" value="<?= htmlspecialchars($p['jabatan']) ?>" placeholder="cth: Pasi Intel / Danramil / Baur"></div>
      <div><label>TMT Jabatan</label><input type="date" name="tmt_jabatan" value="<?= htmlspecialchars($p['tmt_jabatan']) ?>"></div>
      <div><label>TMT Pangkat</label><input type="date" name="tmt_pangkat" value="<?= htmlspecialchars($p['tmt_pangkat']) ?>"></div>
      <div><label>Tempat Lahir</label><input name="tempat_lahir" value="<?= htmlspecialchars($p['tempat_lahir']) ?>"></div>
      <div><label>Tanggal Lahir</label><input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($p['tanggal_lahir']) ?>"></div>
      <div>
        <label>Jenis Kelamin</label>
        <select name="jenis_kelamin">
          <option value="L" <?= $p['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option>
          <option value="P" <?= $p['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option>
        </select>
      </div>
      <div><label>Agama</label><input name="agama" value="<?= htmlspecialchars($p['agama']) ?>"></div>
      <div><label>Status Kawin</label><input name="status_kawin" value="<?= htmlspecialchars($p['status_kawin']) ?>" placeholder="Kawin / Belum Kawin"></div>
      <div><label>No. HP</label><input name="no_hp" value="<?= htmlspecialchars($p['no_hp']) ?>"></div>
      <div><label>Email</label><input name="email" value="<?= htmlspecialchars($p['email']) ?>"></div>
      <div>
        <label>Status Dinas</label>
        <select name="status_dinas">
          <?php foreach (['Aktif','Pensiun','Meninggal','Pindah'] as $s): ?>
            <option <?= $p['status_dinas']===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="grid-column:1/-1;">
        <label>Pas Foto Prajurit (Opsional)</label>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
          <input type="file" name="foto" accept="image/*" style="flex:1;min-width:220px;">
          <?php $pFotoUrl = foto_url($p['foto'] ?? ''); ?>
          <?php if ($pFotoUrl): ?>
            <div style="display:flex;align-items:center;gap:10px;background:var(--panel-2);border:1px solid var(--border);padding:4px 12px;border-radius:8px;">
              <img src="<?= $pFotoUrl ?>" alt="Foto" style="width:34px;height:34px;border-radius:4px;object-fit:cover;border:1px solid var(--gold);">
              <span style="font-size:12px;color:var(--ok);">✔ Foto tersimpan</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div style="grid-column:1/-1;"><label>Alamat Domisili</label><textarea name="alamat" rows="2"><?= htmlspecialchars($p['alamat']) ?></textarea></div>

      <?php if (!$id): ?>
      <div style="grid-column:1/-1;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:12px 14px;">
        <label style="margin-bottom:6px;font-weight:600;color:var(--text);">Status Akun Login Personel</label>
        <select name="status_akun" style="margin-bottom:6px;">
          <option value="approved">Approved / Langsung Aktif (Personel dapat langsung login dengan NRP)</option>
          <option value="pending">Pending (Menunggu verifikasi admin sebelum dapat login)</option>
        </select>
        <span style="font-size:12px;color:var(--text-dim);">Akun login otomatis dibuatkan dengan <strong>Username = NRP</strong> dan <strong>Password = NRP</strong>.</span>
      </div>
      <?php else: ?>
      <div style="grid-column:1/-1;background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-size:12.5px;color:var(--text-dim);">
        Akun login: <strong><?= htmlspecialchars($userAccount['username'] ?? $p['nrp']) ?></strong> &middot; Status: <span class="badge badge-<?= $userAccount['status'] ?? 'pending' ?>"><?= strtoupper($userAccount['status'] ?? 'PENDING') ?></span> (Password default: NRP)
      </div>
      <?php endif; ?>
    </div>

    <div style="margin-top:24px;display:flex;gap:10px;border-top:1px solid var(--border);padding-top:16px;align-items:center;flex-wrap:wrap;">
      <button class="btn" type="submit" style="padding:0 24px;">💾 Simpan Data Personel</button>
      <a href="<?= BASE_URL ?>/admin/personel_list.php" class="btn btn-outline">Batal</a>
      <?php if ($id): ?>
        <button type="button" class="btn btn-outline btn-danger" style="margin-left:auto;" onclick="openDeleteModalForm()">
          🗑️ Hapus Personel Ini
        </button>
      <?php endif; ?>
    </div>
  </form>
</div>

<script>
// Filter Pangkat sesuai Golongan yang dipilih
function filterPangkatByGolongan() {
  var gol = document.getElementById('golonganSelect').value;
  var sel = document.getElementById('pangkatSelect');
  var opts = sel.querySelectorAll('option');

  var currentSelectedValid = false;
  opts.forEach(function(opt) {
    if (!opt.value) return; // Keep placeholder
    var optGol = opt.getAttribute('data-golongan');
    if (!gol || optGol === gol) {
      opt.style.display = '';
      if (opt.selected) currentSelectedValid = true;
    } else {
      opt.style.display = 'none';
      if (opt.selected) opt.selected = false;
    }
  });

  // Jika opsi aktif sebelumnya hilang karena ganti golongan, pilih opsi pertama yang cocok
  if (!currentSelectedValid) {
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

// Filter Satuan saat Kotama berubah
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

  if (opt && opt.value && opt.value !== 'custom') {
    customInput.value = opt.getAttribute('data-nama') || '';
    // Auto-select kotama jika satuan punya kotama
    var satKot = opt.getAttribute('data-kotama');
    if (satKot) {
      var kotSel = document.getElementById('kotamaSelect');
      if (!kotSel.value || kotSel.value !== satKot) {
        kotSel.value = satKot;
        filterSatuanByKotama();
      }
    }
  }
}

// Inisialisasi saat halaman pertama dibuka
document.addEventListener('DOMContentLoaded', function() {
  var gol = document.getElementById('golonganSelect').value;
  var selPkt = document.getElementById('pangkatSelect');
  selPkt.querySelectorAll('option').forEach(function(opt) {
    if (!opt.value) return;
    if (opt.getAttribute('data-golongan') !== gol) {
      opt.style.display = 'none';
    }
  });

  var kotamaId = document.getElementById('kotamaSelect').value;
  if (kotamaId) {
    filterSatuanByKotama();
  }
});
</script>

<?php if ($id): ?>
<!-- Modal Konfirmasi Hapus Personel Kaskade -->
<div class="modal-backdrop" id="modalDeletePersonelForm">
  <div class="modal-dialog" style="max-width:520px;">
    <div class="modal-header" style="border-bottom:1px solid rgba(220,53,69,0.3);background:rgba(220,53,69,0.08);">
      <h3 class="modal-title" style="color:var(--danger);display:flex;align-items:center;gap:8px;">
        ⚠️ Konfirmasi Hapus Personel
      </h3>
      <button type="button" class="modal-close" onclick="closeDeleteModalForm()">&times;</button>
    </div>
    <form method="post" action="<?= BASE_URL ?>/admin/personel_delete.php">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="redirect_to" value="/admin/personel_list.php">
      <div class="modal-body">
        <div style="background:rgba(220,53,69,0.1);border-left:4px solid var(--danger);padding:12px 16px;border-radius:4px;margin-bottom:16px;">
          <strong style="color:var(--danger);display:block;margin-bottom:4px;">TINDAKAN INI BERSIFAT PERMANEN & TIDAK DAPAT DIBATALKAN!</strong>
          <span style="font-size:13px;color:var(--text-dim);">
            Menghapus personel ini akan menghapus <strong>seluruh data terkait secara otomatis</strong> dari sistem pangkalan data dan server penyimpanan fisik.
          </span>
        </div>

        <div style="background:var(--panel-2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px;">
          <div style="font-size:12px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Personel yang akan dihapus:</div>
          <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:2px;"><?= htmlspecialchars($p['nama']) ?></div>
          <div style="font-size:13px;font-family:monospace;color:var(--gold);">NRP: <?= htmlspecialchars($p['nrp']) ?> &middot; <?= htmlspecialchars($p['pangkat'] ?? '-') ?></div>
        </div>

        <div style="font-size:13px;color:var(--text-dim);line-height:1.6;">
          <strong>Data yang akan ikut terhapus tuntas:</strong>
          <ul style="margin:8px 0 0 18px;padding:0;">
            <li>Seluruh berkas dokumen dosir digital (PDF aktif & master warkat raw di server)</li>
            <li>Pas foto profil prajurit di media penyimpanan server</li>
            <li>Akun akses pengguna sistem (users) & riwayat login</li>
            <li>Data riwayat dinas, jabatan, dan kelengkapan personel</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeDeleteModalForm()">Batal</button>
        <button type="submit" class="btn btn-danger" style="display:flex;align-items:center;gap:6px;">
          🗑️ Ya, Hapus Personel & Seluruh Dokumen
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteModalForm() {
  var modal = document.getElementById('modalDeletePersonelForm');
  if (modal) modal.classList.add('show');
}
function closeDeleteModalForm() {
  var modal = document.getElementById('modalDeletePersonelForm');
  if (modal) modal.classList.remove('show');
}
document.addEventListener('click', function(e) {
  if (e.target && e.target.classList && e.target.classList.contains('modal-backdrop')) {
    closeDeleteModalForm();
  }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDeleteModalForm();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

