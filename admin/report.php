<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$jenis = $_GET['jenis'] ?? 'kelengkapan';
$satuan = trim($_GET['satuan'] ?? '');

$totalWajibDosir = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn() ?: 33;

$sql = "
    SELECT p.*,
           COUNT(DISTINCT CASE WHEN f.status='approved' AND m.wajib=1 THEN f.dosir_kode END) as terisi_dosir
    FROM personel p
    LEFT JOIN dosir_files f ON f.personel_id = p.id
    LEFT JOIN dosir_master m ON m.kode = f.dosir_kode
    WHERE p.status_dinas = 'Aktif'
";
$params = [];
if ($satuan !== '') { $sql .= " AND p.satuan = ?"; $params[] = $satuan; }
$sql .= " GROUP BY p.id ORDER BY p.satuan, p.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personelList = $stmt->fetchAll();

$satuanOptions = $pdo->query("SELECT DISTINCT satuan FROM personel WHERE satuan IS NOT NULL AND satuan<>'' ORDER BY satuan")->fetchAll(PDO::FETCH_COLUMN);

$rows = [];
foreach ($personelList as $p) {
    $terisi = (int)($p['terisi_dosir'] ?? 0);
    $k = [
        'terisi' => $terisi,
        'total'  => $totalWajibDosir,
        'persen' => round(($terisi / $totalWajibDosir) * 100, 1)
    ];
    $tglPensiun = prediksi_pensiun($p['golongan'], $p['tanggal_lahir']);
    $sisaBulan = bulan_menuju_pensiun($tglPensiun);
    $lamaJabatan = lama_jabatan_tahun($p['tmt_jabatan']);
    $rows[] = compact('p', 'k', 'tglPensiun', 'sisaBulan', 'lamaJabatan');
}

$pageTitle = 'Laporan';
include __DIR__ . '/../includes/header.php';
?>

<div class="card no-print" style="margin-bottom:16px;">
  <form method="get" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(180px,1fr) auto;gap:16px;align-items:end;">
    <div>
      <label>Jenis Laporan</label>
      <select name="jenis">
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
    <div>
      <label class="label-spacer">&nbsp;</label>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn" type="submit" style="min-width:100px;">Tampilkan</button>
        <a href="<?= BASE_URL ?>/admin/report_export.php?jenis=<?= urlencode($jenis) ?>&satuan=<?= urlencode($satuan) ?>" class="btn btn-outline">
          📥 Ekspor CSV
        </a>
        <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Cetak</button>
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
      <div style="color:var(--text-dim);font-size:12.5px;margin-top:2px;"><?= htmlspecialchars(get_setting($pdo, 'instansi', 'TNI Angkatan Darat')) ?> &middot; Satuan: <?= $satuan !== '' ? htmlspecialchars($satuan) : 'Seluruh Satuan' ?> &middot; Dicetak: <?= date('d-m-Y H:i') ?></div>
    </div>
  </div>

  <?php if ($jenis === 'kelengkapan'): ?>
  <table>
    <thead><tr><th>No</th><th>NRP</th><th>Nama</th><th>Pangkat</th><th>Satuan</th><th>Terisi</th><th>Persen</th></tr></thead>
    <tbody>
      <?php $no=1; foreach ($rows as $r): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($r['p']['nrp']) ?></td>
        <td><?= htmlspecialchars($r['p']['nama']) ?></td>
        <td><?= htmlspecialchars($r['p']['pangkat'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['p']['satuan'] ?? '-') ?></td>
        <td><?= $r['k']['terisi'] ?>/<?= $totalWajibDosir ?></td>
        <td><?= $r['k']['persen'] ?>%</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif ($jenis === 'pensiun'): ?>
  <table>
    <thead><tr><th>No</th><th>NRP</th><th>Nama</th><th>Golongan</th><th>Tgl Lahir</th><th>Proyeksi Pensiun</th><th>Sisa Waktu</th></tr></thead>
    <tbody>
      <?php $no=1; foreach ($rows as $r): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($r['p']['nrp']) ?></td>
        <td><?= htmlspecialchars($r['p']['nama']) ?></td>
        <td><?= htmlspecialchars($r['p']['golongan']) ?></td>
        <td><?= fmt_tgl($r['p']['tanggal_lahir']) ?></td>
        <td><?= fmt_tgl($r['tglPensiun']) ?></td>
        <td><?= $r['sisaBulan'] !== null ? $r['sisaBulan'].' bulan' : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php else: ?>
  <table>
    <thead><tr><th>No</th><th>NRP</th><th>Nama</th><th>Jabatan</th><th>TMT Jabatan</th><th>Lama Menjabat</th></tr></thead>
    <tbody>
      <?php $no=1; foreach ($rows as $r): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($r['p']['nrp']) ?></td>
        <td><?= htmlspecialchars($r['p']['nama']) ?></td>
        <td><?= htmlspecialchars($r['p']['jabatan'] ?? '-') ?></td>
        <td><?= fmt_tgl($r['p']['tmt_jabatan']) ?></td>
        <td><?= $r['lamaJabatan'] ?? '-' ?> tahun <?= ($r['lamaJabatan'] ?? 0) > BATAS_TAHUN_JABATAN ? '⚠' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <div style="margin-top:30px;display:flex;justify-content:flex-end;">
    <div style="text-align:center;">
      <div>Mengetahui,</div>
      <div style="height:70px;"></div>
      <div>( ......................................... )</div>
    </div>
  </div>
</div>

<style>
@media print {
  .sidebar, .topbar, .no-print, .footer { display: none !important; }
  .main, .content { padding: 0 !important; }
  body { background: #fff; color: #000; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
