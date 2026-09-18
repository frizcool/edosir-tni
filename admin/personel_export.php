<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();

$q = trim($_GET['q'] ?? '');
$satuan = trim($_GET['satuan'] ?? '');
$golongan = trim($_GET['golongan'] ?? '');
$statusDinas = trim($_GET['status_dinas'] ?? '');

$sql = "SELECT p.*, u.status as status_user, u.username 
        FROM personel p 
        LEFT JOIN users u ON u.personel_id = p.id 
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (p.nama LIKE ? OR p.nrp LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($satuan !== '') {
    $sql .= " AND p.satuan = ?";
    $params[] = $satuan;
}
if ($golongan !== '') {
    $sql .= " AND p.golongan = ?";
    $params[] = $golongan;
}
if ($statusDinas !== '') {
    $sql .= " AND p.status_dinas = ?";
    $params[] = $statusDinas;
}

$sql .= " ORDER BY p.satuan ASC, p.golongan ASC, p.nama ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

// Log aktivitas ekspor
log_activity($pdo, $admin['id'], 'EXPORT_PERSONEL', 'Mengekspor data ' . count($list) . ' personel ke format CSV/Excel');

$filename = 'data_personel_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM untuk Microsoft Excel Windows agar karakter terbaca sempurna
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header Kolom
fputcsv($output, [
    'No',
    'NRP',
    'Nama Lengkap',
    'Golongan',
    'Pangkat',
    'Korp',
    'Satuan',
    'Kotama',
    'Jabatan',
    'TMT Jabatan',
    'Lama Menjabat (Tahun)',
    'TMT Pangkat',
    'Tanggal Lahir',
    'Usia (Tahun)',
    'Proyeksi Pensiun',
    'Sisa Menuju Pensiun (Bulan)',
    'Status Dinas',
    'Dosir Terisi (Jenis)',
    'Kelengkapan Dosir (%)',
    'Status Akun Login',
    'No. HP',
    'Email',
    'Alamat'
], ';');

$no = 1;
foreach ($list as $p) {
    $k = hitung_kelengkapan($pdo, $p['id']);
    $tglPensiun = prediksi_pensiun($p['golongan'], $p['tanggal_lahir']);
    $sisaBulan = bulan_menuju_pensiun($tglPensiun);
    $lamaJabatan = lama_jabatan_tahun($p['tmt_jabatan']);
    $usia = hitung_usia($p['tanggal_lahir']);

    fputcsv($output, [
        $no++,
        "'" . $p['nrp'], // Diberi prefix petik agar Excel tidak mengubah angka NRP menjadi notasi eksponen
        $p['nama'],
        $p['golongan'],
        $p['pangkat'] ?? '',
        $p['korp'] ?? '',
        $p['satuan'] ?? '',
        $p['kotama'] ?? '',
        $p['jabatan'] ?? '',
        $p['tmt_jabatan'] ?? '',
        $lamaJabatan ?? '',
        $p['tmt_pangkat'] ?? '',
        $p['tanggal_lahir'] ?? '',
        $usia ?? '',
        $tglPensiun ?? '',
        $sisaBulan ?? '',
        $p['status_dinas'] ?? 'Aktif',
        $k['terisi'] . ' / ' . $k['total'],
        $k['persen'] . '%',
        strtoupper($p['status_user'] ?? 'BELUM_ADA'),
        $p['no_hp'] ?? '',
        $p['email'] ?? '',
        $p['alamat'] ?? ''
    ], ';');
}

fclose($output);
exit;
