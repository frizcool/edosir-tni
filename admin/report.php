<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

// Normalisasi URL malformed (misal akses: report.php?report.php?print_all=1)
if (isset($_SERVER['QUERY_STRING']) && (strpos($_SERVER['QUERY_STRING'], 'report.php') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', 'report.php?report.php') !== false)) {
    $cleanQuery = preg_replace('/^report\.php\?+/i', '', $_SERVER['QUERY_STRING']);
    $cleanQuery = str_ireplace(['report.php?', 'report.php'], '', $cleanQuery);
    $cleanQuery = ltrim($cleanQuery, '?&');
    header('Location: ' . BASE_URL . '/admin/report.php' . ($cleanQuery !== '' ? '?' . $cleanQuery : ''), true, 302);
    exit;
}

$jenis          = $_GET['jenis'] ?? 'kelengkapan';
$satuan         = trim($_GET['satuan'] ?? '');
$filterPensiun  = $_GET['filter_pensiun'] ?? 'all';
$filterJabatan  = $_GET['filter_jabatan'] ?? 'all';

$totalWajibDosir = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn() ?: 33;
$batasJabatan    = (int)get_setting($pdo, 'batas_tahun_jabatan', 2);

$sql = "
    SELECT p.*,
           mp.singkatan as pangkat,
           mp.golongan,
           ms.nama as satuan,
           mkot.nama as kotama,
           COUNT(DISTINCT CASE WHEN f.status='approved' AND m.wajib=1 THEN f.dosir_kode END) as terisi_dosir
    FROM personel p
    LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
    LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
    LEFT JOIN master_kotama mkot ON COALESCE(ms.kotama_id, p.kotama_id) = mkot.id
    LEFT JOIN dosir_files f ON f.personel_id = p.id
    LEFT JOIN dosir_master m ON m.kode = f.dosir_kode
    WHERE p.status_dinas = 'Aktif'
";
$params = [];
if ($satuan !== '') { $sql .= " AND ms.nama = ?"; $params[] = $satuan; }
$sql .= " GROUP BY p.id ORDER BY ms.nama, p.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personelList = $stmt->fetchAll();

$satuanOptions = $pdo->query("SELECT DISTINCT ms.nama FROM master_satuan ms JOIN personel p ON p.satuan_id = ms.id ORDER BY ms.nama")->fetchAll(PDO::FETCH_COLUMN);

$rows = [];
foreach ($personelList as $p) {
    $terisi = (int)($p['terisi_dosir'] ?? 0);
    $k = [
        'terisi' => $terisi,
        'total'  => $totalWajibDosir,
        'persen' => round(($terisi / $totalWajibDosir) * 100, 1)
    ];
    $tglPensiun = $p['tmt_pensiun_proyeksi'] ?: hitung_proyeksi_pensiun($p['tanggal_lahir'], $p['golongan']);
    $sisaBulan = bulan_menuju_pensiun($tglPensiun);
    $lamaJabatan = lama_jabatan_tahun($p['tmt_jabatan']);

    // Terapkan Filter Tambahan Khusus
    if ($jenis === 'pensiun') {
        if ($filterPensiun === '1th' && ($sisaBulan === null || $sisaBulan > 12)) continue;
        if ($filterPensiun === '2th' && ($sisaBulan === null || $sisaBulan > 24)) continue;
        if ($filterPensiun === '5th' && ($sisaBulan === null || $sisaBulan > 60)) continue;
    } elseif ($jenis === 'jabatan') {
        if ($filterJabatan === 'tod' && ($lamaJabatan === null || $lamaJabatan <= $batasJabatan)) continue;
        if ($filterJabatan === '3th' && ($lamaJabatan === null || $lamaJabatan <= 3)) continue;
    }

    $rows[] = compact('p', 'k', 'tglPensiun', 'sisaBulan', 'lamaJabatan');
}

// Logika Paginasi Laporan Kedinasan
$totalRecords = count($rows);
$perPage = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$isPrintAll = (isset($_GET['all']) && $_GET['all'] == '1')
           || (isset($_GET['print_all']) && $_GET['print_all'] == '1')
           || (isset($_SERVER['QUERY_STRING']) && (strpos($_SERVER['QUERY_STRING'], 'print_all=1') !== false || strpos($_SERVER['QUERY_STRING'], 'all=1') !== false));
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$displayRows = $isPrintAll ? $rows : array_slice($rows, $offset, $perPage);

// Data Pejabat Penandatangan Laporan dari Pengaturan Sistem
$pejabatNama    = get_setting($pdo, 'pejabat_nama', 'HENDRA PRATAMA, S.I.P.');
$pejabatPangkat = get_setting($pdo, 'pejabat_pangkat', 'MAYOR INF');
$pejabatNrp     = get_setting($pdo, 'pejabat_nrp', '11040023450682');
$pejabatJabatan = get_setting($pdo, 'pejabat_jabatan', 'Perwira Personel / Verifikator');

$pageTitle = 'Laporan Kedinasan';
include __DIR__ . '/../includes/header.php';
?>

<?php if ($isPrintAll): ?>
<div class="alert alert-info no-print" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
  <div>
    <strong>🖨 Mode Cetak Seluruh Data Aktif:</strong> Menampilkan seluruh <strong><?= number_format($totalRecords) ?></strong> data personel tanpa batasan paginasi.
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <button class="btn btn-sm" type="button" onclick="window.print()">🖨 Cetak Sekarang</button>
    <a href="<?= BASE_URL ?>/admin/report.php?jenis=<?= urlencode($jenis) ?>&satuan=<?= urlencode($satuan) ?>&filter_pensiun=<?= urlencode($filterPensiun) ?>&filter_jabatan=<?= urlencode($filterJabatan) ?>" class="btn btn-outline btn-sm">
      ✕ Kembali ke Mode Normal
    </a>
  </div>
</div>
<?php endif; ?>

<div class="card no-print" style="margin-bottom:16px;">
  <form method="get" action="<?= BASE_URL ?>/admin/report.php" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)) auto;gap:16px;align-items:end;">
    <div>
      <label>Jenis Laporan</label>
      <select name="jenis" onchange="this.form.submit()">
        <option value="kelengkapan" <?= $jenis==='kelengkapan'?'selected':'' ?>>Kelengkapan Dosir</option>
        <option value="pensiun" <?= $jenis==='pensiun'?'selected':'' ?>>Proyeksi Pensiun</option>
        <option value="jabatan" <?= $jenis==='jabatan'?'selected':'' ?>>Lama Menjabat</option>
      </select>
    </div>

    <div>
      <label>Satuan</label>
      <select name="satuan">
        <option value="">Semua Satuan</option>
        <?php foreach ($satuanOptions as $s): ?><option <?= $satuan===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
      </select>
    </div>

    <?php if ($jenis === 'pensiun'): ?>
    <div>
      <label>Filter Waktu Pensiun</label>
      <select name="filter_pensiun">
        <option value="all" <?= $filterPensiun==='all'?'selected':'' ?>>Semua Personel</option>
        <option value="1th" <?= $filterPensiun==='1th'?'selected':'' ?>>Pensiun ≤ 1 Tahun (Kritis)</option>
        <option value="2th" <?= $filterPensiun==='2th'?'selected':'' ?>>Pensiun ≤ 2 Tahun (Perencanaan)</option>
        <option value="5th" <?= $filterPensiun==='5th'?'selected':'' ?>>Pensiun ≤ 5 Tahun</option>
      </select>
    </div>
    <?php elseif ($jenis === 'jabatan'): ?>
    <div>
      <label>Filter Masa Jabatan</label>
      <select name="filter_jabatan">
        <option value="all" <?= $filterJabatan==='all'?'selected':'' ?>>Semua Personel</option>
        <option value="tod" <?= $filterJabatan==='tod'?'selected':'' ?>>Kandidat TOD/TOA (&gt; <?= $batasJabatan ?> Tahun)</option>
        <option value="3th" <?= $filterJabatan==='3th'?'selected':'' ?>>Menjabat &gt; 3 Tahun</option>
      </select>
    </div>
    <?php endif; ?>

    <div>
      <label class="label-spacer">&nbsp;</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn" type="submit" style="min-width:100px;">Tampilkan</button>
        <a href="<?= BASE_URL ?>/admin/report_export.php?jenis=<?= urlencode($jenis) ?>&satuan=<?= urlencode($satuan) ?>&filter_pensiun=<?= urlencode($filterPensiun) ?>&filter_jabatan=<?= urlencode($filterJabatan) ?>" class="btn btn-outline">
          📥 Ekspor CSV
        </a>
        <a href="<?= BASE_URL ?>/admin/report.php?print_all=1&jenis=<?= urlencode($jenis) ?>&satuan=<?= urlencode($satuan) ?>&filter_pensiun=<?= urlencode($filterPensiun) ?>&filter_jabatan=<?= urlencode($filterJabatan) ?>" class="btn btn-outline" target="_blank">
          🖨 Cetak Semua
        </a>
      </div>
    </div>
  </form>
</div>

<div class="card" id="printArea">
  <?php $reportLogo = app_logo_url($pdo); ?>
  <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:16px;padding-bottom:14px;border-bottom:2px solid var(--border);">
    <?php if ($reportLogo): ?>
      <img src="<?= htmlspecialchars($reportLogo) ?>" alt="Logo" style="height:52px;max-width:130px;object-fit:contain;">
    <?php endif; ?>
    <div style="text-align:center;">
      <h2 style="margin:0;font-size:18px;letter-spacing:0.5px;">LAPORAN <?= strtoupper(str_replace('_',' ', $jenis)) ?> &mdash; <?= strtoupper(htmlspecialchars(get_setting($pdo, 'app_name', APP_NAME))) ?></h2>
      <div style="color:var(--text-dim);font-size:12.5px;margin-top:2px;">
        <?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?> &middot; 
        Satuan: <?= $satuan !== '' ? htmlspecialchars($satuan) : 'Seluruh Satuan' ?> &middot; 
        Dicetak: <?= date('d-m-Y H:i') ?> WIB
        <?php if ($jenis === 'pensiun' && $filterPensiun !== 'all'): ?>
          &middot; <em>(Filter: <?= $filterPensiun === '1th' ? '≤ 1 Tahun' : ($filterPensiun === '2th' ? '≤ 2 Tahun' : '≤ 5 Tahun') ?>)</em>
        <?php elseif ($jenis === 'jabatan' && $filterJabatan !== 'all'): ?>
          &middot; <em>(Filter: <?= $filterJabatan === 'tod' ? '> ' . $batasJabatan . ' Thn' : '> 3 Thn' ?>)</em>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($jenis === 'kelengkapan'): ?>
  <table>
    <thead><tr><th>No</th><th>NRP</th><th>Nama Lengkap</th><th>Pangkat</th><th>Satuan</th><th>Terisi</th><th>Persentase</th></tr></thead>
    <tbody>
      <?php if (empty($displayRows)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:14px;">Tidak ada data personel yang cocok.</td></tr>
      <?php endif; ?>
      <?php $no = $isPrintAll ? 1 : ($offset + 1); foreach ($displayRows as $r): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($r['p']['nrp']) ?></td>
        <td><?= htmlspecialchars($r['p']['nama']) ?></td>
        <td><?= htmlspecialchars($r['p']['pangkat'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['p']['satuan'] ?? '-') ?></td>
        <td><?= $r['k']['terisi'] ?> / <?= $totalWajibDosir ?></td>
        <td><strong><?= $r['k']['persen'] ?>%</strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif ($jenis === 'pensiun'): ?>
  <table>
    <thead><tr><th>No</th><th>NRP</th><th>Nama Lengkap</th><th>Golongan</th><th>Tgl Lahir</th><th>Proyeksi Pensiun</th><th>Sisa Waktu</th></tr></thead>
    <tbody>
      <?php if (empty($displayRows)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:14px;">Tidak ada personel yang memenuhi kriteria pensiun ini.</td></tr>
      <?php endif; ?>
      <?php $no = $isPrintAll ? 1 : ($offset + 1); foreach ($displayRows as $r): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($r['p']['nrp']) ?></td>
        <td><?= htmlspecialchars($r['p']['nama']) ?></td>
        <td><?= htmlspecialchars($r['p']['golongan']) ?></td>
        <td><?= fmt_tgl($r['p']['tanggal_lahir']) ?></td>
        <td><strong><?= fmt_tgl($r['tglPensiun']) ?></strong></td>
        <td>
          <?php if ($r['sisaBulan'] !== null): ?>
            <span class="badge <?= $r['sisaBulan'] <= 12 ? 'badge-rejected' : ($r['sisaBulan'] <= 24 ? 'badge-pending' : '') ?>">
              <?= $r['sisaBulan'] ?> bulan
            </span>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php else: ?>
  <table>
    <thead><tr><th>No</th><th>NRP</th><th>Nama Lengkap</th><th>Jabatan</th><th>TMT Jabatan</th><th>Lama Menjabat</th><th>Status Evaluasi</th></tr></thead>
    <tbody>
      <?php if (empty($displayRows)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-dim);padding:14px;">Tidak ada personel yang memenuhi kriteria lama jabatan ini.</td></tr>
      <?php endif; ?>
      <?php $no = $isPrintAll ? 1 : ($offset + 1); foreach ($displayRows as $r): 
        $melebihiBatas = ($r['lamaJabatan'] ?? 0) > $batasJabatan;
      ?>
      <tr>
        <td><?= $no++ ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($r['p']['nrp']) ?></td>
        <td><?= htmlspecialchars($r['p']['nama']) ?></td>
        <td><?= htmlspecialchars($r['p']['jabatan'] ?? '-') ?></td>
        <td><?= fmt_tgl($r['p']['tmt_jabatan']) ?></td>
        <td><?= $r['lamaJabatan'] ?? '-' ?> tahun</td>
        <td>
          <?php if ($melebihiBatas): ?>
            <span class="badge badge-rejected">⚠ Kandidat TOD/TOA</span>
          <?php else: ?>
            <span class="badge badge-approved">Normal</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!$isPrintAll && $totalPages > 1): ?>
    <div class="no-print" style="margin-top:20px;">
      <?= render_pagination($page, $totalPages, $totalRecords, $perPage, $_GET, [10, 25, 50, 100]) ?>
    </div>
  <?php endif; ?>

  <!-- Kolom Tanda Tangan Kedinasan Resmi TNI AD -->
  <div style="margin-top:40px;display:flex;justify-content:flex-end;">
    <div style="text-align:center;min-width:260px;">
      <div>Jakarta, <?= fmt_tgl(date('Y-m-d')) ?></div>
      <div style="font-weight:600;margin-top:2px;"><?= htmlspecialchars($pejabatJabatan) ?>,</div>
      <div style="height:70px;"></div>
      <div style="font-weight:700;text-decoration:underline;"><?= htmlspecialchars($pejabatNama) ?></div>
      <div style="font-size:12.5px;"><?= htmlspecialchars($pejabatPangkat) ?> NRP <?= htmlspecialchars($pejabatNrp) ?></div>
    </div>
  </div>
</div>

<style>
@media print {
  .sidebar, .topbar, .no-print, .footer, nav, form { display: none !important; }
  .main, .content { padding: 0 !important; margin: 0 !important; }
  body { background: #fff !important; color: #000 !important; font-size: 11pt; }
  .card { border: none !important; box-shadow: none !important; background: #fff !important; padding: 0 !important; }
  table { width: 100% !important; border-collapse: collapse !important; }
  th, td { border: 1px solid #000 !important; padding: 4px 6px !important; font-size: 9pt !important; }
  thead { background: #1F3A5F !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  a[href]:after { content: none !important; }
}
</style>

<?php if ($isPrintAll): ?>
<script>
  // Mode cetak semua: auto-trigger print dialog setelah konten termuat
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 600);
  });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
