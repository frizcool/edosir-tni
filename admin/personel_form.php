<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$id = (int) ($_GET['id'] ?? 0);
$p = ['nrp'=>'','nama'=>'','golongan'=>'Perwira','pangkat'=>'','korp'=>'','satuan'=>'','kotama'=>'',
      'jabatan'=>'','tmt_jabatan'=>'','tmt_pangkat'=>'','tanggal_lahir'=>'','tempat_lahir'=>'',
      'jenis_kelamin'=>'L','agama'=>'','status_kawin'=>'','alamat'=>'','no_hp'=>'','email'=>'','status_dinas'=>'Aktif'];

$userAccount = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM personel WHERE id=?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $p = $found;

    $stmtU = $pdo->prepare("SELECT * FROM users WHERE personel_id=?");
    $stmtU->execute([$id]);
    $userAccount = $stmtU->fetch();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'nrp' => trim($_POST['nrp']), 'nama' => trim($_POST['nama']), 'golongan' => $_POST['golongan'],
        'pangkat' => trim($_POST['pangkat']), 'korp' => trim($_POST['korp']), 'satuan' => trim($_POST['satuan']),
        'kotama' => trim($_POST['kotama']), 'jabatan' => trim($_POST['jabatan']),
        'tmt_jabatan' => $_POST['tmt_jabatan'] ?: null, 'tmt_pangkat' => $_POST['tmt_pangkat'] ?: null,
        'tanggal_lahir' => $_POST['tanggal_lahir'] ?: null, 'tempat_lahir' => trim($_POST['tempat_lahir']),
        'jenis_kelamin' => $_POST['jenis_kelamin'], 'agama' => trim($_POST['agama']),
        'status_kawin' => trim($_POST['status_kawin']), 'alamat' => trim($_POST['alamat']),
        'no_hp' => trim($_POST['no_hp']), 'email' => trim($_POST['email']),
        'status_dinas' => $_POST['status_dinas'],
        'tmt_pensiun_proyeksi' => hitung_proyeksi_pensiun($_POST['tanggal_lahir'] ?: null, $_POST['golongan'] ?? 'Perwira'),
    ];

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
            if ($id) {
                $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE personel SET $sets WHERE id=?");
                $stmt->execute([...array_values($data), $id]);

                // Pastikan akun user tetap sinkron dengan NRP
                ensure_personel_user($pdo, $id, $data['nrp'], 'approved');

                log_activity($pdo, $admin['id'], 'UPDATE_PERSONEL', "Update personel #$id");
                set_flash('success', 'Data personel dan akun login berhasil diperbarui.');
                redirect('/admin/personel_detail.php?id=' . $id);
            } else {
                $cols = implode(',', array_keys($data));
                $marks = implode(',', array_fill(0, count($data), '?'));
                $stmt = $pdo->prepare("INSERT INTO personel ($cols) VALUES ($marks)");
                $stmt->execute(array_values($data));
                $newId = (int)$pdo->lastInsertId();

                // Otomatis buatkan akun user untuk data personel baru
                ensure_personel_user($pdo, $newId, $data['nrp'], $statusAkun);

                log_activity($pdo, $admin['id'], 'CREATE_PERSONEL', "Tambah personel baru #$newId (NRP {$data['nrp']}) - Akun $statusAkun");
                set_flash('success', "Personel baru berhasil ditambahkan dan akun login (NRP: {$data['nrp']}) otomatis dibuatkan dengan status " . strtoupper($statusAkun) . ".");
                redirect('/admin/personel_detail.php?id=' . $newId);
            }
        }
    }
}

$pageTitle = $id ? 'Edit Personel' : 'Tambah Personel';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:760px;">
  <h3 style="margin-top:0;"><?= $pageTitle ?></h3>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="grid grid-2">
      <div><label>NRP *</label><input name="nrp" value="<?= htmlspecialchars($p['nrp']) ?>" required></div>
      <div><label>Nama *</label><input name="nama" value="<?= htmlspecialchars($p['nama']) ?>" required></div>
      <div>
        <label>Golongan</label>
        <select name="golongan">
          <?php foreach (['Perwira','Bintara','Tamtama','PNS'] as $g): ?>
            <option <?= $p['golongan']===$g?'selected':'' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Pangkat</label>
        <input name="pangkat" list="pangkatList" value="<?= htmlspecialchars($p['pangkat']) ?>">
        <datalist id="pangkatList">
          <?php foreach (get_distinct_personel_field($pdo, 'pangkat') as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label>Korp</label>
        <input name="korp" list="korpList" value="<?= htmlspecialchars($p['korp']) ?>">
        <datalist id="korpList">
          <?php foreach (get_distinct_personel_field($pdo, 'korp') as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label>Satuan</label>
        <input name="satuan" list="satuanList" value="<?= htmlspecialchars($p['satuan']) ?>">
        <datalist id="satuanList">
          <?php foreach (get_distinct_personel_field($pdo, 'satuan') as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label>Kotama</label>
        <input name="kotama" list="kotamaList" value="<?= htmlspecialchars($p['kotama']) ?>">
        <datalist id="kotamaList">
          <?php foreach (get_distinct_personel_field($pdo, 'kotama') as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div><label>Jabatan</label><input name="jabatan" value="<?= htmlspecialchars($p['jabatan']) ?>"></div>
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
      <div><label>Status Kawin</label><input name="status_kawin" value="<?= htmlspecialchars($p['status_kawin']) ?>"></div>
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
    <div style="margin-top:20px;display:flex;gap:10px;">
      <button class="btn" type="submit" style="padding:0 24px;">💾 Simpan Data Personel</button>
      <a href="<?= BASE_URL ?>/admin/personel_list.php" class="btn btn-outline">Batal</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
