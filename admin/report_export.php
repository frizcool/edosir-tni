<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$jenis = $_GET['jenis'] ?? 'kelengkapan';
$satuan = trim($_GET['satuan'] ?? '');
$totalWajibDosir = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn() ?: 33;
$batasJabatan = (int)get_setting($pdo, 'batas_tahun_jabatan', 2);

$sql = "
    SELECT p.*,
           COUNT(DISTINCT CASE WHEN f.status='approved' AND m.wajib=1 THEN f.dosir_kode END) as terisi_dosir
    FROM personel p
    LEFT JOIN dosir_files f ON f.personel_id = p.id
    LEFT JOIN dosir_master m ON m.kode = f.dosir_kode
    WHERE p.status_dinas = 'Aktif'
";
$params = [];
if ($satuan !== '') {
    $sql .= " AND p.satuan = ?";
    $params[] = $satuan;
}
$sql .= " GROUP BY p.id ORDER BY p.satuan, p.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personelList = $stmt->fetchAll();

$timestamp = date('Ymd_His');
$cleanSatuan = $satuan !== '' ? '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $satuan) : '_SemuaSatuan';
$filename = "Laporan_{$jenis}{$cleanSatuan}_{$timestamp}.csv";

log_activity($pdo, $admin['id'], 'EXPORT_LAPORAN', "Ekspor laporan $jenis ($satuan)");

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$filterPensiun = $_GET['filter_pensiun'] ?? 'all';
$filterJabatan = $_GET['filter_jabatan'] ?? 'all';

$out = fopen('php://output', 'w');
// UTF-8 BOM untuk kompatibilitas sempurna Microsoft Excel di Windows
fputs($out, "\xEF\xBB\xBF");

if ($jenis === 'kelengkapan') {
    fputcsv($out, ['No', 'NRP', 'Nama Lengkap', 'Pangkat', 'Korp', 'Satuan', 'Kotama', 'Jabatan', 'Berkas Terisi', 'Total Wajib', 'Persentase Kelengkapan', 'Status']);
    $no = 1;
    foreach ($personelList as $p) {
        $terisi = (int)($p['terisi_dosir'] ?? 0);
        $persen = round(($terisi / $totalWajibDosir) * 100, 1);
        $status = ($persen >= 100) ? 'LENGKAP' : 'BELUM LENGKAP';
        fputcsv($out, [
            $no++,
            "'" . $p['nrp'], // Force string di Excel
            $p['nama'],
            $p['pangkat'] ?? '-',
            $p['korp'] ?? '-',
            $p['satuan'] ?? '-',
            $p['kotama'] ?? '-',
            $p['jabatan'] ?? '-',
            $terisi,
            $totalWajibDosir,
            $persen . '%',
            $status
        ]);
    }
} elseif ($jenis === 'pensiun') {
    fputcsv($out, ['No', 'NRP', 'Nama Lengkap', 'Golongan', 'Pangkat', 'Satuan', 'Tanggal Lahir', 'Usia Saat Ini', 'Proyeksi Tanggal Pensiun', 'Sisa Bulan', 'Keterangan']);
    $no = 1;
    foreach ($personelList as $p) {
        $tglPensiun = $p['tmt_pensiun_proyeksi'] ?: hitung_proyeksi_pensiun($p['tanggal_lahir'], $p['golongan']);
        $sisaBulan = bulan_menuju_pensiun($tglPensiun);
        $usia = hitung_usia($p['tanggal_lahir']);

        if ($filterPensiun === '1th' && ($sisaBulan === null || $sisaBulan > 12)) continue;
        if ($filterPensiun === '2th' && ($sisaBulan === null || $sisaBulan > 24)) continue;
        if ($filterPensiun === '5th' && ($sisaBulan === null || $sisaBulan > 60)) continue;
        
        $ket = 'Normal';
        if ($sisaBulan !== null && $sisaBulan <= 0) {
            $ket = 'Sudah Memasuki Masa Pensiun';
        } elseif ($sisaBulan !== null && $sisaBulan <= 12) {
            $ket = 'Mendekati Pensiun (< 1 Tahun)';
        } elseif ($sisaBulan !== null && $sisaBulan <= 24) {
            $ket = 'Kandidat Pensiun (1-2 Tahun)';
        }

        fputcsv($out, [
            $no++,
            "'" . $p['nrp'],
            $p['nama'],
            $p['golongan'],
            $p['pangkat'] ?? '-',
            $p['satuan'] ?? '-',
            $p['tanggal_lahir'] ? date('d-m-Y', strtotime($p['tanggal_lahir'])) : '-',
            $usia !== null ? $usia . ' Th' : '-',
            $tglPensiun ? date('d-m-Y', strtotime($tglPensiun)) : '-',
            $sisaBulan !== null ? $sisaBulan . ' Bulan' : '-',
            $ket
        ]);
    }
} elseif ($jenis === 'jabatan') {
    fputcsv($out, ['No', 'NRP', 'Nama Lengkap', 'Pangkat', 'Satuan', 'Jabatan', 'TMT Jabatan', 'Lama Menjabat (Tahun)', 'Batas Standar (Tahun)', 'Peringatan Rotasi']);
    $no = 1;
    foreach ($personelList as $p) {
        $lama = lama_jabatan_tahun($p['tmt_jabatan']);

        if ($filterJabatan === 'tod' && ($lama === null || $lama <= $batasJabatan)) continue;
        if ($filterJabatan === '3th' && ($lama === null || $lama <= 3)) continue;

        $perluRotasi = ($lama !== null && $lama >= $batasJabatan) ? 'PERLU ROTASI / TOUR OF DUTY' : 'SESUAI MASA';
        fputcsv($out, [
            $no++,
            "'" . $p['nrp'],
            $p['nama'],
            $p['pangkat'] ?? '-',
            $p['satuan'] ?? '-',
            $p['jabatan'] ?? '-',
            $p['tmt_jabatan'] ? date('d-m-Y', strtotime($p['tmt_jabatan'])) : '-',
            $lama !== null ? $lama . ' Tahun' : '-',
            $batasJabatan . ' Tahun',
            $perluRotasi
        ]);
    }
}

fclose($out);
exit;
