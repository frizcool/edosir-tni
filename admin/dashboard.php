<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();

// --- 1. STATISTIK UTAMA ---
$totalPersonel = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE status_dinas='Aktif'")->fetchColumn();
$totalUserPending = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='pending' AND role='personel'")->fetchColumn();
$totalDosirPending = (int)$pdo->query("SELECT COUNT(*) FROM dosir_files WHERE status='pending'")->fetchColumn();
$totalDosirApproved = (int)$pdo->query("SELECT COUNT(*) FROM dosir_files WHERE status='approved'")->fetchColumn();
$totalDosirRejected = (int)$pdo->query("SELECT COUNT(*) FROM dosir_files WHERE status='rejected'")->fetchColumn();
$totalDosirAll = (int)$pdo->query("SELECT COUNT(*) FROM dosir_files")->fetchColumn();

// Rata-rata kelengkapan seluruh personel
$avgStmt = $pdo->query("
    SELECT AVG(t.terisi) as avg_terisi FROM (
        SELECT personel_id, COUNT(DISTINCT dosir_kode) as terisi
        FROM dosir_files WHERE status='approved' GROUP BY personel_id
    ) t
");
$totalJenisWajib = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn() ?: 33;
$avgTerisi = $avgStmt->fetchColumn();
$avgPersen = $avgTerisi ? round(($avgTerisi / $totalJenisWajib) * 100, 1) : 0;

// Target total berkas ideal sesuai jumlah dosir wajib per personel aktif
$totalTargetBerkas = max(1, $totalPersonel * $totalJenisWajib);
$persenTargetTercapai = round(($totalDosirApproved / $totalTargetBerkas) * 100, 1);

// --- 2. DISTRIBUSI GOLONGAN (UNTUK DONUT CHART) ---
$golList = $pdo->query("
    SELECT mp.golongan, COUNT(*) as jml 
    FROM personel p 
    JOIN master_pangkat mp ON mp.id = p.pangkat_id
    WHERE p.status_dinas='Aktif' 
    GROUP BY mp.golongan
")->fetchAll(PDO::FETCH_KEY_PAIR);

$golColors = [
    'Perwira' => '#c9a84c', // Gold
    'Bintara' => '#6b8f3c', // Hijau Army
    'Tamtama' => '#4c8f5c', // Hijau Rumput
    'PNS'     => '#4a7bb0', // Biru
];
$totalGol = array_sum($golList) ?: 1;

// Hitung segmen SVG circle
$svgSegments = [];
$accumulatedPercent = 0;
$circumference = 2 * M_PI * 40; // r=40 -> ~251.327

foreach (['Perwira','Bintara','Tamtama','PNS'] as $g) {
    $count = $golList[$g] ?? 0;
    $pct = ($count / $totalGol);
    $dashLength = $pct * $circumference;
    $offset = -$accumulatedPercent * $circumference;
    $svgSegments[] = [
        'gol' => $g,
        'count' => $count,
        'pct' => round($pct * 100, 1),
        'color' => $golColors[$g] ?? '#888',
        'dash' => sprintf("%.2f %.2f", $dashLength, $circumference),
        'offset' => sprintf("%.2f", $offset),
    ];
    $accumulatedPercent += $pct;
}

// --- 3. KELENGKAPAN PER SATUAN (TOP 5 SATUAN) ---
$satuanStats = $pdo->query("
    SELECT ms.nama as satuan, COUNT(DISTINCT p.id) as total_p,
           COUNT(DISTINCT CASE WHEN f.status='approved' THEN CONCAT(p.id, '_', f.dosir_kode) END) as total_app
    FROM personel p
    JOIN master_satuan ms ON ms.id = p.satuan_id
    LEFT JOIN dosir_files f ON f.personel_id = p.id
    WHERE p.status_dinas='Aktif' AND ms.nama IS NOT NULL AND ms.nama != ''
    GROUP BY ms.nama
    ORDER BY total_p DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// --- 4. BERKAS DOSIR PENDING TERBARU (QUICK ACTION) ---
$recentPendingFiles = $pdo->query("
    SELECT f.*, p.nama, p.nrp, ms.nama as satuan, m.nama_dosir
    FROM dosir_files f
    JOIN personel p ON p.id = f.personel_id
    LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
    JOIN dosir_master m ON m.kode = f.dosir_kode
    WHERE f.status = 'pending'
    ORDER BY f.uploaded_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// --- 5. LOG AKTIVITAS TERBARU (LIVE AUDIT FEED) ---
$recentLogs = $pdo->query("
    SELECT l.*, u.username, p.nama, p.nrp
    FROM activity_log l
    LEFT JOIN users u ON u.id = l.user_id
    LEFT JOIN personel p ON p.id = u.personel_id
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// --- 6. PREDIKSI PENSIUN & ROTASI JABATAN (OPTIMASI QUERY SQL TERINDEX) ---
$stmtPensiun = $pdo->prepare("
    SELECT p.*, mp.golongan, p.tmt_pensiun_proyeksi as tgl_pensiun,
           TIMESTAMPDIFF(MONTH, CURDATE(), p.tmt_pensiun_proyeksi) as sisa_bulan
    FROM personel p
    LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
    WHERE p.status_dinas = 'Aktif'
      AND p.tmt_pensiun_proyeksi IS NOT NULL
      AND p.tmt_pensiun_proyeksi <= DATE_ADD(CURDATE(), INTERVAL 24 MONTH)
    ORDER BY p.tmt_pensiun_proyeksi ASC
    LIMIT 20
");
$stmtPensiun->execute();
$pensiunSoon = [];
foreach ($stmtPensiun->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $pensiunSoon[] = [
        'p'    => $row,
        'tgl'  => $row['tgl_pensiun'],
        'sisa' => max(0, (int)$row['sisa_bulan'])
    ];
}

$batasTahun = BATAS_TAHUN_JABATAN;
$stmtJabatan = $pdo->prepare("
    SELECT p.*, 
           ROUND(DATEDIFF(CURDATE(), p.tmt_jabatan) / 365.25, 1) as lama_tahun
    FROM personel p
    WHERE p.status_dinas = 'Aktif'
      AND p.tmt_jabatan IS NOT NULL
      AND p.tmt_jabatan <= DATE_SUB(CURDATE(), INTERVAL ? YEAR)
    ORDER BY p.tmt_jabatan ASC
    LIMIT 20
");
$stmtJabatan->execute([$batasTahun]);
$jabatanLama = [];
foreach ($stmtJabatan->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $jabatanLama[] = [
        'p'    => $row,
        'lama' => (float)$row['lama_tahun']
    ];
}

// Info backup terakhir
$lastBackup = $pdo->query("SELECT created_at, jenis, file_name FROM backup_log ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard Komando';
include __DIR__ . '/../includes/header.php';
?>

<!-- Banner Komando & Waktu Sistem -->
<div class="command-banner">
  <div>
    <div class="command-salute">★ PUSAT KOMANDO <?= strtoupper(htmlspecialchars(get_setting($pdo, 'app_name', APP_NAME))) ?></div>
    <div class="command-sub">
      Selamat datang, <strong><?= htmlspecialchars($admin['username']) ?></strong> &middot;
      <?= fmt_tgl(date('Y-m-d')) ?> &middot;
      <span id="digitalClock"><?= date('H:i') ?> WIB</span>
    </div>
  </div>
  <div class="system-status-pills">
    <div class="status-pill">
      <span class="pulse-dot"></span>
      <span>Database Online</span>
    </div>
    <div class="status-pill">
      <span style="color:var(--gold);">★</span>
      <span>Server Dosir Aktif</span>
    </div>
  </div>
</div>

<!-- 5 Kartu Metrik Utama -->
<div class="grid grid-5" style="margin-bottom:20px;">
  <div class="card stat card-interactive">
    <span class="label">Personel Aktif</span>
    <span class="value"><?= number_format($totalPersonel) ?></span>
    <span style="font-size:11.5px;color:var(--text-dim);">Prajurit &amp; PNS terdata</span>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Berkas Terverifikasi</span>
    <span class="value ok"><?= number_format($totalDosirApproved) ?></span>
    <span style="font-size:11.5px;color:var(--ok);"><?= $avgPersen ?>% rata-rata kelengkapan</span>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Menunggu Verifikasi</span>
    <span class="value warn"><?= number_format($totalDosirPending) ?></span>
    <span style="font-size:11.5px;color:<?= $totalDosirPending > 0 ? 'var(--warn)' : 'var(--text-dim)' ?>;">
      <?= $totalDosirPending > 0 ? '⚡ Memerlukan verifikasi' : 'Seluruh berkas terproses' ?>
    </span>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Approval Akun</span>
    <span class="value <?= $totalUserPending > 0 ? 'warn' : '' ?>"><?= number_format($totalUserPending) ?></span>
    <span style="font-size:11.5px;color:var(--text-dim);">Registrasi baru pending</span>
  </div>

  <div class="card stat card-interactive">
    <span class="label">Total Arsip Berkas</span>
    <span class="value gold"><?= number_format($totalDosirAll) ?></span>
    <span style="font-size:11.5px;color:var(--text-dim);"><?= number_format($totalDosirRejected) ?> berkas ditolak</span>
  </div>
</div>

<!-- Baris Widget Visual: Distribusi Golongan & Pipeline Dosir -->
<div class="grid grid-2" style="margin-bottom:20px;align-items:stretch;">
  <!-- Widget 1: Diagram Donut Distribusi Golongan -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3 style="margin:0;font-size:16px;">Distribusi Golongan Prajurit &amp; PNS</h3>
      <span style="font-size:12px;color:var(--gold);font-weight:600;">Total: <?= $totalPersonel ?></span>
    </div>
    
    <div class="chart-wrap">
      <!-- SVG Donut Chart -->
      <svg class="donut-svg" width="130" height="130" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="40" fill="transparent" stroke="var(--border)" stroke-width="14"></circle>
        <?php foreach ($svgSegments as $seg): ?>
          <?php if ($seg['count'] > 0): ?>
            <circle cx="50" cy="50" r="40" fill="transparent" 
                    stroke="<?= $seg['color'] ?>" stroke-width="14"
                    stroke-dasharray="<?= $seg['dash'] ?>"
                    stroke-dashoffset="<?= $seg['offset'] ?>"></circle>
          <?php endif; ?>
        <?php endforeach; ?>
      </svg>

      <!-- Legend -->
      <div class="donut-legend">
        <?php foreach ($svgSegments as $seg): ?>
        <div class="legend-row">
          <div class="legend-label">
            <span class="legend-color" style="background:<?= $seg['color'] ?>;"></span>
            <span><?= $seg['gol'] ?></span>
          </div>
          <strong><?= $seg['count'] ?> <span style="font-size:11px;color:var(--text-dim);">(<?= $seg['pct'] ?>%)</span></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Widget 2: Pipeline Status Berkas Dosir -->
  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;font-size:16px;">Pipeline Berkas Dosir</h3>
        <span style="font-size:12px;color:var(--text-dim);"><?= number_format($totalDosirAll) ?> Berkas Diunggah</span>
      </div>
      <p style="font-size:12.5px;color:var(--text-dim);margin:6px 0 14px;">
        Progres target kelengkapan seluruh prajurit aktif (target: <?= number_format($totalTargetBerkas) ?> berkas).
      </p>

      <!-- Visual Segmented Bar -->
      <?php 
        $pctApp = round(($totalDosirApproved / $totalTargetBerkas) * 100, 1);
        $pctPend = round(($totalDosirPending / $totalTargetBerkas) * 100, 1);
        $pctRej = round(($totalDosirRejected / $totalTargetBerkas) * 100, 1);
        $pctEmp = max(0, 100 - ($pctApp + $pctPend + $pctRej));
      ?>
      <div class="pipeline-bar">
        <div class="pipeline-seg approved" style="width:<?= $pctApp ?>%;" title="Terverifikasi: <?= $totalDosirApproved ?>"></div>
        <div class="pipeline-seg pending" style="width:<?= $pctPend ?>%;" title="Menunggu: <?= $totalDosirPending ?>"></div>
        <div class="pipeline-seg rejected" style="width:<?= $pctRej ?>%;" title="Ditolak: <?= $totalDosirRejected ?>"></div>
        <div class="pipeline-seg empty" style="width:<?= $pctEmp ?>%;" title="Belum Diunggah"></div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:10px;margin-top:14px;font-size:12.5px;">
        <div style="display:flex;align-items:center;gap:6px;">
          <span style="width:10px;height:10px;border-radius:2px;background:var(--ok);"></span>
          <span>Terverifikasi: <strong><?= $totalDosirApproved ?></strong></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
          <span style="width:10px;height:10px;border-radius:2px;background:var(--warn);"></span>
          <span>Menunggu: <strong><?= $totalDosirPending ?></strong></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
          <span style="width:10px;height:10px;border-radius:2px;background:var(--danger);"></span>
          <span>Perlu Revisi: <strong><?= $totalDosirRejected ?></strong></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px;">
          <span style="width:10px;height:10px;border-radius:2px;background:var(--border);"></span>
          <span>Belum Diunggah: <strong><?= max(0, $totalTargetBerkas - $totalDosirAll) ?></strong></span>
        </div>
      </div>
    </div>

    <div style="margin-top:14px;padding-top:10px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:12px;color:var(--text-dim);">Capaian Target:</span>
      <span style="font-size:14px;font-weight:700;color:var(--gold);"><?= $persenTargetTercapai ?>% Tercapai</span>
    </div>
  </div>
</div>

<!-- Baris Widget Operasional: Quick Verification & Live Activity Feed -->
<div class="grid grid-2" style="margin-bottom:20px;align-items:start;">
  <!-- Widget 3: Quick Action Verifikasi Berkas -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="margin:0;font-size:16px;">⚡ Verifikasi Cepat Berkas Pending</h3>
      <a href="<?= BASE_URL ?>/admin/dosir_verify.php" style="font-size:12px;color:var(--gold);text-decoration:underline;">Lihat Semua (<?= $totalDosirPending ?>)</a>
    </div>

    <?php if (empty($recentPendingFiles)): ?>
      <div style="text-align:center;padding:26px 10px;color:var(--text-dim);font-size:13px;">
        ✔ Tidak ada berkas dosir pending saat ini. Seluruh berkas telah diverifikasi.
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Personel</th>
            <th>Dosir</th>
            <th style="text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentPendingFiles as $f): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($f['nama']) ?></strong>
              <div style="font-size:11.5px;font-family:monospace;color:var(--gold);">NRP <?= htmlspecialchars($f['nrp']) ?></div>
            </td>
            <td>
              <span class="badge badge-pending" style="font-size:10.5px;">DOSIR <?= $f['dosir_kode'] ?></span>
              <div style="font-size:11px;color:var(--text-dim);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($f['nama_dosir']) ?>
              </div>
            </td>
            <td style="text-align:right;">
              <div style="display:inline-flex;gap:4px;">
                <a href="<?= dosir_url($f['file_path']) ?>" target="_blank" class="btn btn-outline" style="padding:4px 8px;font-size:11px;" title="Lihat PDF">
                  Buka
                </a>
                <form method="post" action="<?= BASE_URL ?>/admin/dosir_verify_process.php" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $f['id'] ?>">
                  <input type="hidden" name="status" value="pending">
                  <button type="submit" name="action" value="approved" class="btn" style="padding:4px 8px;font-size:11px;background:var(--ok);" title="Setujui Berkas">
                    ✓
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Widget 4: Live Activity Feed -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <h3 style="margin:0;font-size:16px;">📋 Log Aktivitas Sistem Terkini</h3>
      <a href="<?= BASE_URL ?>/admin/activity_log.php" style="font-size:12px;color:var(--gold);text-decoration:underline;">Buka Audit Trail</a>
    </div>

    <div class="activity-feed">
      <?php if (empty($recentLogs)): ?>
        <div style="text-align:center;padding:20px;color:var(--text-dim);font-size:13px;">Belum ada aktivitas terekam.</div>
      <?php endif; ?>
      <?php foreach ($recentLogs as $log): 
        $icon = '★';
        if (strpos($log['aktivitas'], 'LOGIN') !== false) $icon = '🔑';
        elseif (strpos($log['aktivitas'], 'REGISTER') !== false) $icon = '📝';
        elseif (strpos($log['aktivitas'], 'DOSIR') !== false) $icon = '📄';
        elseif (strpos($log['aktivitas'], 'APPROVAL') !== false) $icon = '🛡️';
        elseif (strpos($log['aktivitas'], 'BACKUP') !== false) $icon = '💾';
      ?>
      <div class="activity-item">
        <div class="activity-icon"><?= $icon ?></div>
        <div class="activity-content">
          <div class="activity-title" style="font-size:12.5px;">
            <?= htmlspecialchars($log['aktivitas']) ?> &middot; 
            <span style="color:var(--gold);"><?= htmlspecialchars($log['nama'] ?? $log['username'] ?? 'Sistem') ?></span>
          </div>
          <div class="activity-desc"><?= htmlspecialchars($log['keterangan'] ?? '-') ?></div>
          <div class="activity-time"><?= date('d-m-Y H:i', strtotime($log['created_at'])) ?> &middot; <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Baris Widget Peringatan: Pensiun & Rotasi Jabatan -->
<div class="grid grid-2" style="margin-bottom:20px;align-items:start;">
  <!-- Prediksi Pensiun -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <h3 style="margin:0;font-size:16px;">⏳ Proyeksi Pensiun ≤ 24 Bulan</h3>
      <span class="badge badge-pending"><?= count($pensiunSoon) ?> Personel</span>
    </div>
    <p style="font-size:12px;color:var(--text-dim);margin:0 0 10px;">Batas Pensiun: Perwira 58 th &middot; Bintara/Tamtama 56 th &middot; PNS 60 th</p>
    <table>
      <thead><tr><th>Nama / NRP</th><th>Gol.</th><th>Tgl Pensiun</th><th>Sisa</th></tr></thead>
      <tbody>
      <?php if (empty($pensiunSoon)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-dim);padding:14px;">Tidak ada personel dalam proyeksi pensiun 2 tahun ini.</td></tr>
      <?php endif; ?>
      <?php foreach (array_slice($pensiunSoon, 0, 6) as $row): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/admin/personel_detail.php?id=<?= $row['p']['id'] ?>" style="color:var(--text);text-decoration:underline;">
              <strong><?= htmlspecialchars($row['p']['nama']) ?></strong>
            </a>
            <div style="font-family:monospace;font-size:11.5px;color:var(--text-dim);"><?= htmlspecialchars($row['p']['nrp']) ?></div>
          </td>
          <td><?= htmlspecialchars($row['p']['golongan']) ?></td>
          <td><?= fmt_tgl($row['tgl']) ?></td>
          <td><span class="badge badge-pending"><?= $row['sisa'] ?> bln</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Jabatan > 2 Tahun -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <h3 style="margin:0;font-size:16px;">🔄 Masa Jabatan &gt; <?= BATAS_TAHUN_JABATAN ?> Tahun</h3>
      <span class="badge badge-rejected"><?= count($jabatanLama) ?> Kandidat</span>
    </div>
    <p style="font-size:12px;color:var(--text-dim);margin:0 0 10px;">Daftar personel untuk evaluasi tour of duty / rotasi dinas</p>
    <table>
      <thead><tr><th>Nama / NRP</th><th>Jabatan</th><th>Lama</th></tr></thead>
      <tbody>
      <?php if (empty($jabatanLama)): ?>
        <tr><td colspan="3" style="text-align:center;color:var(--text-dim);padding:14px;">Seluruh personel memegang jabatan ≤ <?= BATAS_TAHUN_JABATAN ?> tahun.</td></tr>
      <?php endif; ?>
      <?php foreach (array_slice($jabatanLama, 0, 6) as $row): ?>
        <tr>
          <td>
            <a href="<?= BASE_URL ?>/admin/personel_detail.php?id=<?= $row['p']['id'] ?>" style="color:var(--text);text-decoration:underline;">
              <strong><?= htmlspecialchars($row['p']['nama']) ?></strong>
            </a>
            <div style="font-family:monospace;font-size:11.5px;color:var(--text-dim);"><?= htmlspecialchars($row['p']['nrp']) ?></div>
          </td>
          <td><?= htmlspecialchars($row['p']['jabatan'] ?? '-') ?></td>
          <td><span class="badge badge-rejected"><?= $row['lama'] ?> th</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Baris Akses Cepat & Status Cadangan Data -->
<div class="card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
  <div>
    <strong>Akses Navigasi Cepat</strong>
    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>/admin/personel_list.php" class="btn btn-outline" style="font-size:12.5px;">+ Data Personel</a>
      <a href="<?= BASE_URL ?>/admin/users_approve.php" class="btn btn-outline" style="font-size:12.5px;">Approval Akun (<?= $totalUserPending ?>)</a>
      <a href="<?= BASE_URL ?>/admin/dosir_verify.php" class="btn btn-outline" style="font-size:12.5px;">Verifikasi Dosir (<?= $totalDosirPending ?>)</a>
      <a href="<?= BASE_URL ?>/admin/bulk_download.php" class="btn btn-outline" style="font-size:12.5px;">Unduh Massal</a>
      <a href="<?= BASE_URL ?>/admin/report.php" class="btn btn-outline" style="font-size:12.5px;">Cetak Laporan</a>
    </div>
  </div>

  <div style="text-align:right;">
    <div style="font-size:12px;color:var(--text-dim);">Cadangan Terakhir:</div>
    <div style="font-size:13px;font-weight:600;color:var(--text);">
      <?= $lastBackup ? fmt_tgl($lastBackup['created_at']) . ' (' . strtoupper($lastBackup['jenis']) . ')' : 'Belum pernah di-backup' ?>
    </div>
    <a href="<?= BASE_URL ?>/admin/backup.php" class="btn" style="padding:6px 14px;font-size:12px;margin-top:6px;">
      💾 Buat Backup Sekarang
    </a>
  </div>
</div>

<script>
setInterval(() => {
  const d = new Date();
  const h = String(d.getHours()).padStart(2, '0');
  const m = String(d.getMinutes()).padStart(2, '0');
  const el = document.getElementById('digitalClock');
  if (el) el.innerText = h + ':' + m + ' WIB';
}, 1000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
