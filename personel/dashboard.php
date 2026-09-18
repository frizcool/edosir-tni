<?php
require_once __DIR__ . '/../config/config.php';
require_role('personel');

$u = current_user();
$personel_id = $u['personel_id'];

if (!$personel_id) {
    set_flash('error', 'Profil personel Anda belum tertaut. Hubungi admin.');
    redirect('/login.php');
}

$stmt = $pdo->prepare("SELECT * FROM personel WHERE id=?");
$stmt->execute([$personel_id]);
$p = $stmt->fetch();

if (!$p) {
    set_flash('error', 'Data personel tidak ditemukan.');
    redirect('/login.php');
}

$kelengkapan = hitung_kelengkapan($pdo, $personel_id);
$dosirList = get_dosir_master($pdo);

// Ambil seluruh file dosir personel
$stmtF = $pdo->prepare("SELECT * FROM dosir_files WHERE personel_id=? ORDER BY dosir_kode, abjad");
$stmtF->execute([$personel_id]);
$files = $stmtF->fetchAll();

$byKode = [];
$totalPending = 0;
$totalApproved = 0;
$totalRejected = 0;
$rejectedFiles = [];

foreach ($files as $f) {
    $byKode[$f['dosir_kode']][] = $f;
    if ($f['status'] === 'approved') $totalApproved++;
    elseif ($f['status'] === 'pending') $totalPending++;
    elseif ($f['status'] === 'rejected') {
        $totalRejected++;
        $rejectedFiles[] = $f;
    }
}

// Cari dosir yang belum pernah diunggah sama sekali
$missingDosirs = [];
foreach ($dosirList as $d) {
    if (empty($byKode[$d['kode']])) {
        $missingDosirs[] = $d;
    }
}

// Prediksi pensiun & lama jabatan
$tglPensiun = prediksi_pensiun($p['golongan'], $p['tanggal_lahir']);
$sisaBulanPensiun = bulan_menuju_pensiun($tglPensiun);
$lamaJabatan = lama_jabatan_tahun($p['tmt_jabatan']);
$usiaSekarang = hitung_usia($p['tanggal_lahir']);

$pageTitle = 'Dashboard Personel';
include __DIR__ . '/../includes/header.php';
?>

<div class="military-id-card" data-watermark="★ <?= htmlspecialchars(get_setting($pdo, 'app_brand_sub', 'TNI AD')) ?> ★" style="margin-bottom:22px;">
  <div class="id-card-layout">
    <div>
      <?php $pFotoUrl = foto_url($p['foto'] ?? ''); ?>
      <?php if ($pFotoUrl): ?>
        <img src="<?= $pFotoUrl ?>" alt="Foto" class="id-avatar">
      <?php else: ?>
        <div class="id-avatar">★</div>
      <?php endif; ?>
    </div>

    <div class="id-body">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
        <div>
          <div class="id-name"><?= htmlspecialchars($p['nama']) ?></div>
          <div class="id-sub">
            <?= htmlspecialchars($p['pangkat'] ?? '-') ?> <?= htmlspecialchars($p['korp'] ? '(' . $p['korp'] . ')' : '') ?> &middot; 
            <span style="font-family:monospace;"><?= htmlspecialchars($p['nrp']) ?></span>
          </div>
        </div>
        <span class="badge badge-approved" style="font-size:12px;">STATUS: <?= strtoupper($p['status_dinas']) ?></span>
      </div>

      <div class="id-meta-grid">
        <div class="id-meta-item">
          <span class="key">Satuan</span>
          <span class="val"><?= htmlspecialchars($p['satuan'] ?? '-') ?></span>
        </div>
        <div class="id-meta-item">
          <span class="key">Kotama / Balakpus</span>
          <span class="val"><?= htmlspecialchars($p['kotama'] ?? '-') ?></span>
        </div>
        <div class="id-meta-item">
          <span class="key">Jabatan</span>
          <span class="val"><?= htmlspecialchars($p['jabatan'] ?? '-') ?></span>
        </div>
        <div class="id-meta-item">
          <span class="key">Golongan &amp; TMT</span>
          <span class="val"><?= htmlspecialchars($p['golongan']) ?> (TMT: <?= fmt_tgl($p['tmt_pangkat']) ?>)</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Widget 2: Metrik Status Dosir Pribadi (Grid 4) -->
<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="card stat card-interactive">
    <span class="label">Kelengkapan Dosir</span>
    <span class="value gold"><?= $kelengkapan['persen'] ?>%</span>
    <div class="progress" style="margin-top:6px;height:10px;"><span style="width:<?= $kelengkapan['persen'] ?>%;"></span></div>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Dosir Terverifikasi</span>
    <span class="value ok"><?= $kelengkapan['terisi'] ?> / <?= $kelengkapan['total'] ?></span>
    <span style="font-size:11.5px;color:var(--text-dim);">Jenis dokumen sah</span>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Sedang Ditinjau Admin</span>
    <span class="value <?= $totalPending > 0 ? 'warn' : '' ?>"><?= $totalPending ?></span>
    <span style="font-size:11.5px;color:var(--text-dim);">Menunggu persetujuan</span>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Perlu Perbaikan</span>
    <span class="value <?= $totalRejected > 0 ? 'danger' : '' ?>"><?= $totalRejected ?></span>
    <span style="font-size:11.5px;color:var(--text-dim);"><?= $totalRejected > 0 ? 'Berkas ditolak verifikator' : 'Tidak ada berkas ditolak' ?></span>
  </div>
</div>

<!-- Widget 3: Peringatan Catatan Revisi Berkas (Jika Ada Berkas Ditolak) -->
<?php if (!empty($rejectedFiles)): ?>
<div class="card" style="border-left:5px solid var(--danger);margin-bottom:20px;background:rgba(176,69,63,0.06);">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
    <span style="font-size:20px;color:var(--danger);">⚠️</span>
    <h3 style="margin:0;color:var(--danger);font-size:16px;">Pemberitahuan Revisi Berkas Dosir</h3>
  </div>
  <p style="font-size:13px;color:var(--text);margin:0 0 12px;">
    Terdapat <?= count($rejectedFiles) ?> berkas yang ditolak oleh verifikator admin. Silakan periksa catatan dan unggah ulang perbaikannya:
  </p>
  
  <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($rejectedFiles as $rf): 
      $masterName = '';
      foreach ($dosirList as $dl) { if ($dl['kode'] === $rf['dosir_kode']) { $masterName = $dl['nama_dosir']; break; } }
    ?>
    <div style="background:var(--panel);border:1px solid var(--border);border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
      <div>
        <strong style="color:var(--danger);">DOSIR <?= $rf['dosir_kode'] ?> &mdash; <?= htmlspecialchars($masterName) ?></strong>
        <div style="font-size:12.5px;color:var(--text);margin-top:2px;">
          Catatan Admin: <em><?= !empty($rf['catatan_verifikasi']) ? htmlspecialchars($rf['catatan_verifikasi']) : 'Berkas belum memenuhi syarat verifikasi.' ?></em>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/personel/upload.php?kode=<?= $rf['dosir_kode'] ?>" class="btn" style="padding:6px 14px;font-size:12px;background:var(--accent);">
        Unggah Perbaikan
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Baris Widget: Proyeksi Karir & Dosir Prioritas Belum Diunggah -->
<div class="grid grid-2" style="margin-bottom:20px;align-items:start;">
  <!-- Widget 4: Proyeksi Karir & Kedinasan -->
  <div class="card">
    <h3 style="margin-top:0;font-size:16px;">⏱️ Proyeksi Karir &amp; Masa Dinas</h3>
    <table style="margin-top:10px;">
      <tr>
        <th>Lama Menjabat Saat Ini</th>
        <td>
          <strong><?= $lamaJabatan ?? '-' ?> Tahun</strong>
          <span style="font-size:11.5px;color:var(--text-dim);">(Sejak <?= fmt_tgl($p['tmt_jabatan']) ?>)</span>
        </td>
      </tr>
      <tr>
        <th>TMT Pangkat Terakhir</th>
        <td><?= fmt_tgl($p['tmt_pangkat']) ?></td>
      </tr>
      <tr>
        <th>Usia Saat Ini</th>
        <td><?= $usiaSekarang ?? '-' ?> Tahun</td>
      </tr>
      <tr>
        <th>Batas Usia Pensiun</th>
        <td>
          <strong style="color:var(--gold);"><?= fmt_tgl($tglPensiun) ?></strong>
          <?php if ($sisaBulanPensiun !== null): ?>
            <span class="badge <?= $sisaBulanPensiun <= 24 ? 'badge-pending' : 'badge-approved' ?>">
              <?= $sisaBulanPensiun ?> bulan lagi
            </span>
          <?php endif; ?>
        </td>
      </tr>
    </table>
    <div style="margin-top:12px;display:flex;gap:8px;">
      <a href="<?= BASE_URL ?>/personel/profile.php" class="btn btn-outline" style="font-size:12px;flex:1;text-align:center;">
        Buka Profil Saya &amp; Kontak
      </a>
    </div>
  </div>

  <!-- Widget 5: Dosir Prioritas Belum Diunggah -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <h3 style="margin:0;font-size:16px;">📄 Dosir yang Belum Diunggah</h3>
      <span style="font-size:12px;color:var(--warn);font-weight:600;"><?= count($missingDosirs) ?> Belum Ada</span>
    </div>
    <p style="font-size:12px;color:var(--text-dim);margin:0 0 10px;">Lengkapi seluruh jenis dosir untuk kesiapan administrasi kenaikan pangkat &amp; pensiun.</p>

    <?php if (empty($missingDosirs)): ?>
      <div style="text-align:center;padding:24px 10px;color:var(--ok);font-size:13px;">
        🎉 Selamat! Seluruh <?= count($dosirList) ?> jenis dosir telah memiliki berkas terunggah.
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <?php foreach (array_slice($missingDosirs, 0, 5) as $md): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:var(--panel-2);border:1px solid var(--border);border-radius:6px;font-size:12.5px;">
          <div>
            <strong>DOSIR <?= $md['kode'] ?></strong>: <?= htmlspecialchars($md['nama_dosir']) ?>
          </div>
          <div style="display:flex;gap:4px;">
            <a href="<?= BASE_URL ?>/personel/upload.php?kode=<?= $md['kode'] ?>" class="btn" style="padding:4px 10px;font-size:11.5px;">
              Unggah
            </a>
            <a href="<?= BASE_URL ?>/personel/scan.php?kode=<?= $md['kode'] ?>" class="btn btn-outline" style="padding:4px 10px;font-size:11.5px;" title="Scan via kamera">
              📷 Scan
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Widget 6: Checklist Master Dosir dengan Filter -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <div>
      <h3 style="margin:0;font-size:16px;">Checklist Dosir Personel</h3>
      <div style="font-size:12.5px;color:var(--text-dim);margin-top:2px;">
        Klik berkas untuk melihat pratinjau dokumen PDF terunggah.
      </div>
    </div>
    
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <button type="button" class="btn btn-outline filter-tab-btn active" onclick="filterDosir('all', this)" style="padding:5px 12px;font-size:12px;">Semua (<?= count($dosirList) ?>)</button>
      <button type="button" class="btn btn-outline filter-tab-btn" onclick="filterDosir('approved', this)" style="padding:5px 12px;font-size:12px;">Terverifikasi (<?= $totalApproved ?>)</button>
      <button type="button" class="btn btn-outline filter-tab-btn" onclick="filterDosir('pending', this)" style="padding:5px 12px;font-size:12px;">Pending (<?= $totalPending ?>)</button>
      <button type="button" class="btn btn-outline filter-tab-btn" onclick="filterDosir('missing', this)" style="padding:5px 12px;font-size:12px;">Belum Ada (<?= count($missingDosirs) ?>)</button>
    </div>
  </div>

  <div id="dosirChecklistContainer">
    <?php foreach ($dosirList as $d):
        $kode = $d['kode'];
        $entries = $byKode[$kode] ?? [];
        $hasApproved = false;
        $hasPending = false;
        $hasRejected = false;
        foreach ($entries as $e) {
            if ($e['status'] === 'approved') $hasApproved = true;
            if ($e['status'] === 'pending') $hasPending = true;
            if ($e['status'] === 'rejected') $hasRejected = true;
        }

        $rowClass = 'status-missing';
        if ($hasApproved) $rowClass = 'status-approved';
        elseif ($hasPending) $rowClass = 'status-pending';
        elseif ($hasRejected) $rowClass = 'status-rejected';
    ?>
    <div class="dosir-item dosir-row <?= $rowClass ?>" style="padding:10px 0;">
      <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:8px;">
          <strong>DOSIR <?= $kode ?></strong> &mdash; <?= htmlspecialchars($d['nama_dosir']) ?>
          <?php if ($hasApproved): ?>
            <span class="badge badge-approved" style="font-size:10.5px;">Sah</span>
          <?php endif; ?>
        </div>

        <?php if ($entries): ?>
          <div style="margin-top:5px;display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($entries as $e): ?>
              <a href="<?= dosir_url($e['file_path']) ?>" target="_blank"
                 class="badge badge-<?= $e['status'] ?>" style="text-decoration:none;font-size:11px;"
                 title="<?= htmlspecialchars($e['keterangan'] ?? '') ?>">
                 📄 <?= htmlspecialchars($e['file_name']) ?>
                 <?php if (!empty($e['signature_code'])): ?>
                   &middot; <span style="color:var(--gold);font-weight:700;">★ TTE</span>
                 <?php elseif ($e['status'] === 'pending'): ?>
                   &middot; <span style="opacity:0.85;">(Belum Verifikasi)</span>
                 <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="font-size:11.5px;color:var(--danger);margin-top:3px;">Belum ada dokumen yang diunggah</div>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:6px;align-items:center;">
        <a href="<?= BASE_URL ?>/personel/upload.php?kode=<?= $kode ?>" class="btn btn-outline" style="padding:5px 10px;font-size:11.5px;">
          + Unggah
        </a>
        <a href="<?= BASE_URL ?>/personel/scan.php?kode=<?= $kode ?>" class="btn btn-outline" style="padding:5px 10px;font-size:11.5px;" title="Scan Kamera">
          📷
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function filterDosir(type, btn) {
  document.querySelectorAll('.filter-tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const rows = document.querySelectorAll('.dosir-row');
  rows.forEach(r => {
    if (type === 'all') {
      r.style.display = 'flex';
    } else if (type === 'approved') {
      r.style.display = r.classList.contains('status-approved') ? 'flex' : 'none';
    } else if (type === 'pending') {
      r.style.display = r.classList.contains('status-pending') ? 'flex' : 'none';
    } else if (type === 'missing') {
      r.style.display = r.classList.contains('status-missing') ? 'flex' : 'none';
    }
  });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
