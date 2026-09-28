<?php
// =====================================================================
// MIGRASI STRUKTUR BASIS DATA RELASIONAL ALAMI (NATURAL RDBMS)
// MASTER PANGKAT, KORP, KOTAMA, SATUAN & SINKRONISASI TABEL PERSONEL
// TRISULA TNI AD
// =====================================================================
require_once __DIR__ . '/../config/database.php';

$pdo->exec("SET foreign_key_checks = 0;");

echo "Memulai migrasi basis data relasional alami untuk Pangkat, Korp, Satuan, dan Kotama...\n";

// 1. Buat Tabel master_kotama
$pdo->exec("
CREATE TABLE IF NOT EXISTS master_kotama (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(30) UNIQUE NOT NULL,
    nama VARCHAR(150) NOT NULL,
    tipe ENUM('Kotamaops', 'Kotamabin', 'Balakpus', 'Mabesad') NOT NULL DEFAULT 'Kotamabin',
    urutan INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[1/6] Tabel master_kotama siap.\n";

// 2. Buat Tabel master_satuan dengan Relasi Natural ke master_kotama
$pdo->exec("
CREATE TABLE IF NOT EXISTS master_satuan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kotama_id INT NULL,
    kode VARCHAR(50) UNIQUE NOT NULL,
    nama VARCHAR(150) NOT NULL,
    lokasi VARCHAR(100) NULL,
    urutan INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_satuan_kotama FOREIGN KEY (kotama_id) 
        REFERENCES master_kotama(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[2/6] Tabel master_satuan siap.\n";

// 3. Buat Tabel master_pangkat
$pdo->exec("
CREATE TABLE IF NOT EXISTS master_pangkat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    golongan ENUM('Perwira', 'Bintara', 'Tamtama', 'PNS') NOT NULL,
    kode VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(60) NOT NULL,
    singkatan VARCHAR(20) NOT NULL,
    urutan INT NOT NULL,
    bup_usia INT NOT NULL DEFAULT 56,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[3/6] Tabel master_pangkat siap.\n";

// 4. Buat Tabel master_korp
$pdo->exec("
CREATE TABLE IF NOT EXISTS master_korp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(15) UNIQUE NOT NULL,
    nama VARCHAR(80) NOT NULL,
    kategori ENUM('Tempur', 'Bantuan Tempur', 'Bantuan Administrasi', 'Penerbad') NOT NULL DEFAULT 'Tempur',
    urutan INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[4/6] Tabel master_korp siap.\n";

// 5. Perluas Kolom Relasi Natural pada Tabel personel
// Cek apakah kolom-kolom FK sudah ada
$cols = $pdo->query("SHOW COLUMNS FROM personel")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('pangkat_id', $cols, true)) {
    $pdo->exec("ALTER TABLE personel ADD COLUMN pangkat_id INT NULL AFTER golongan");
}
if (!in_array('korp_id', $cols, true)) {
    $pdo->exec("ALTER TABLE personel ADD COLUMN korp_id INT NULL AFTER pangkat");
}
if (!in_array('satuan_id', $cols, true)) {
    $pdo->exec("ALTER TABLE personel ADD COLUMN satuan_id INT NULL AFTER korp");
}
if (!in_array('kotama_id', $cols, true)) {
    $pdo->exec("ALTER TABLE personel ADD COLUMN kotama_id INT NULL AFTER satuan");
}

// Pasang constraint FK jika belum ada
try {
    $pdo->exec("ALTER TABLE personel ADD CONSTRAINT fk_personel_pangkat FOREIGN KEY (pangkat_id) REFERENCES master_pangkat(id) ON DELETE SET NULL ON UPDATE CASCADE");
} catch (Exception $e) { /* sudah ada */ }

try {
    $pdo->exec("ALTER TABLE personel ADD CONSTRAINT fk_personel_korp FOREIGN KEY (korp_id) REFERENCES master_korp(id) ON DELETE SET NULL ON UPDATE CASCADE");
} catch (Exception $e) { /* sudah ada */ }

try {
    $pdo->exec("ALTER TABLE personel ADD CONSTRAINT fk_personel_satuan FOREIGN KEY (satuan_id) REFERENCES master_satuan(id) ON DELETE SET NULL ON UPDATE CASCADE");
} catch (Exception $e) { /* sudah ada */ }

try {
    $pdo->exec("ALTER TABLE personel ADD CONSTRAINT fk_personel_kotama FOREIGN KEY (kotama_id) REFERENCES master_kotama(id) ON DELETE SET NULL ON UPDATE CASCADE");
} catch (Exception $e) { /* sudah ada */ }

echo "[5/6] Kolom relasi natural Foreign Key pada tabel personel siap.\n";

// 6. Isi / Seed Data Master Baku TNI AD
echo "[6/6] Memasukkan data master rujukan resmi TNI AD...\n";

// 6a. Master Pangkat
$pangkatData = [
    // Tamtama
    ['Tamtama', 'PRADA', 'Prajurit Dua', 'Prada', 1, 56],
    ['Tamtama', 'PRATU', 'Prajurit Satu', 'Pratu', 2, 56],
    ['Tamtama', 'PRAKA', 'Prajurit Kepala', 'Praka', 3, 56],
    ['Tamtama', 'KOPDA', 'Kopral Dua', 'Kopda', 4, 56],
    ['Tamtama', 'KOPTU', 'Kopral Satu', 'Koptu', 5, 56],
    ['Tamtama', 'KOPKA', 'Kopral Kepala', 'Kopka', 6, 56],

    // Bintara
    ['Bintara', 'SERDA', 'Sersan Dua', 'Serda', 7, 56],
    ['Bintara', 'SERTU', 'Sersan Satu', 'Sertu', 8, 56],
    ['Bintara', 'SERKA', 'Sersan Kepala', 'Serka', 9, 56],
    ['Bintara', 'SERMA', 'Sersan Mayor', 'Serma', 10, 56],
    ['Bintara', 'PELDA', 'Pembantu Letnan Dua', 'Pelda', 11, 56],
    ['Bintara', 'PELTU', 'Pembantu Letnan Satu', 'Peltu', 12, 56],

    // Perwira Pertama (Pama)
    ['Perwira', 'LETDA', 'Letnan Dua', 'Letda', 13, 58],
    ['Perwira', 'LETTU', 'Letnan Satu', 'Lettu', 14, 58],
    ['Perwira', 'KAPTEN', 'Kapten', 'Kapten', 15, 58],

    // Perwira Menengah (Pamen)
    ['Perwira', 'MAYOR', 'Mayor', 'Mayor', 16, 58],
    ['Perwira', 'LETKOL', 'Letnan Kolonel', 'Letkol', 17, 58],
    ['Perwira', 'KOLONEL', 'Kolonel', 'Kolonel', 18, 58],

    // Perwira Tinggi (Pati)
    ['Perwira', 'BRIGJEN', 'Brigadir Jenderal TNI', 'Brigjen TNI', 19, 58],
    ['Perwira', 'MAYJEN', 'Mayor Jenderal TNI', 'Mayjen TNI', 20, 58],
    ['Perwira', 'LETJEN', 'Letnan Jenderal TNI', 'Letjen TNI', 21, 58],
    ['Perwira', 'JENDERAL', 'Jenderal TNI', 'Jenderal TNI', 22, 58],

    // PNS
    ['PNS', 'PENGATUR_MUDA_IIA', 'Pengatur Muda (II/a)', 'II/a', 30, 60],
    ['PNS', 'PENGATUR_MUDA_TK_IIB', 'Pengatur Muda Tk. I (II/b)', 'II/b', 31, 60],
    ['PNS', 'PENGATUR_IIC', 'Pengatur (II/c)', 'II/c', 32, 60],
    ['PNS', 'PENGATUR_TK_IID', 'Pengatur Tk. I (II/d)', 'II/d', 33, 60],
    ['PNS', 'PENATA_MUDA_IIIA', 'Penata Muda (III/a)', 'III/a', 34, 60],
    ['PNS', 'PENATA_MUDA_TK_IIIB', 'Penata Muda Tk. I (III/b)', 'III/b', 35, 60],
    ['PNS', 'PENATA_IIIC', 'Penata (III/c)', 'III/c', 36, 60],
    ['PNS', 'PENATA_TK_IIID', 'Penata Tk. I (III/d)', 'III/d', 37, 60],
    ['PNS', 'PEMBINA_IVA', 'Pembina (IV/a)', 'IV/a', 38, 60],
    ['PNS', 'PEMBINA_TK_IVB', 'Pembina Tk. I (IV/b)', 'IV/b', 39, 60],
    ['PNS', 'PEMBINA_UTAMA_MUDA_IVC', 'Pembina Utama Muda (IV/c)', 'IV/c', 40, 60],
    ['PNS', 'PEMBINA_UTAMA_MADYA_IVD', 'Pembina Utama Madya (IV/d)', 'IV/d', 41, 60],
    ['PNS', 'PEMBINA_UTAMA_IVE', 'Pembina Utama (IV/e)', 'IV/e', 42, 60],
];

$stmtPkt = $pdo->prepare("
    INSERT INTO master_pangkat (golongan, kode, nama, singkatan, urutan, bup_usia)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        golongan = VALUES(golongan),
        nama = VALUES(nama),
        singkatan = VALUES(singkatan),
        urutan = VALUES(urutan),
        bup_usia = VALUES(bup_usia)
");
foreach ($pangkatData as $row) {
    $stmtPkt->execute($row);
}
echo "  - Master pangkat berhasil di-seed (" . count($pangkatData) . " data pangkat).\n";

// 6b. Master Korp Resmi Kecabangan TNI AD
$korpData = [
    // Tempur
    ['Inf', 'Infanteri', 'Tempur', 1],
    ['Kav', 'Kavaleri', 'Tempur', 2],
    ['Arm', 'Artileri Medan', 'Tempur', 3],
    ['Arh', 'Artileri Pertahanan Udara', 'Tempur', 4],
    
    // Bantuan Tempur
    ['Czi', 'Zeni', 'Bantuan Tempur', 5],
    ['Chb', 'Perhubungan', 'Bantuan Tempur', 6],
    ['Cpal', 'Peralatan', 'Bantuan Tempur', 7],
    ['Cba', 'Pembekalan Angkutan', 'Bantuan Tempur', 8],
    
    // Bantuan Administrasi
    ['Cpm', 'Polisi Militer', 'Bantuan Administrasi', 9],
    ['Caj', 'Ajudan Jenderal', 'Bantuan Administrasi', 10],
    ['Ckm', 'Kesehatan Militer', 'Bantuan Administrasi', 11],
    ['Cku', 'Keuangan', 'Bantuan Administrasi', 12],
    ['Chk', 'Hukum', 'Bantuan Administrasi', 13],
    ['Ctp', 'Topografi', 'Bantuan Administrasi', 14],

    // Penerbad
    ['Cpn', 'Penerbangan Angkatan Darat', 'Penerbad', 15],
];

$stmtKorp = $pdo->prepare("
    INSERT INTO master_korp (kode, nama, kategori, urutan)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        nama = VALUES(nama),
        kategori = VALUES(kategori),
        urutan = VALUES(urutan)
");
foreach ($korpData as $row) {
    $stmtKorp->execute($row);
}
echo "  - Master korp berhasil di-seed (" . count($korpData) . " kecabangan korps).\n";

// 6c. Master Kotama (Komando Utama & Balakpus TNI AD)
$kotamaData = [
    ['MABESAD', 'Mabes TNI AD', 'Mabesad', 1],
    ['KOSTRAD', 'Komando Cadangan Strategis AD (Kostrad)', 'Kotamaops', 2],
    ['KOPASSUS', 'Komando Pasukan Khusus (Kopassus)', 'Kotamaops', 3],
    ['KODIKLATAD', 'Kodiklat TNI AD', 'Kotamabin', 4],
    ['PUSZIAD', 'Pusat Zeni TNI AD (Pusziad)', 'Balakpus', 5],
    ['PUSPOMAD', 'Pusat Polisi Militer AD (Puspomad)', 'Balakpus', 6],
    ['PUSHUBAD', 'Pusat Perhubungan AD (Pushubad)', 'Balakpus', 7],
    ['PUSPALAD', 'Pusat Peralatan AD (Puspalad)', 'Balakpus', 8],
    ['PUSBEKANGAD', 'Pusat Pembekalan Angkutan AD (Pusbekangad)', 'Balakpus', 9],
    ['PUSKESAD', 'Pusat Kesehatan AD (Puskesad)', 'Balakpus', 10],
    ['PUSPENERBAD', 'Pusat Penerbangan AD (Puspenerbad)', 'Balakpus', 11],
    ['KODAM-I', 'Kodam I/Bukit Barisan', 'Kotamabin', 12],
    ['KODAM-II', 'Kodam II/Sriwijaya', 'Kotamabin', 13],
    ['KODAM-III', 'Kodam III/Siliwangi', 'Kotamabin', 14],
    ['KODAM-IV', 'Kodam IV/Diponegoro', 'Kotamabin', 15],
    ['KODAM-V', 'Kodam V/Brawijaya', 'Kotamabin', 16],
    ['KODAM-VI', 'Kodam VI/Mulawarman', 'Kotamabin', 17],
    ['KODAM-IX', 'Kodam IX/Udayana', 'Kotamabin', 18],
    ['KODAM-XII', 'Kodam XII/Tanjungpura', 'Kotamabin', 19],
    ['KODAM-XIII', 'Kodam XIII/Merdeka', 'Kotamabin', 20],
    ['KODAM-XIV', 'Kodam XIV/Hasanuddin', 'Kotamabin', 21],
    ['KODAM-XV', 'Kodam XV/Pattimura', 'Kotamabin', 22],
    ['KODAM-XVII', 'Kodam XVII/Cenderawasih', 'Kotamabin', 23],
    ['KODAM-XVIII', 'Kodam XVIII/Kasuari', 'Kotamabin', 24],
    ['KODAM-JAYA', 'Kodam Jaya/Jayakarta', 'Kotamabin', 25],
    ['KODAM-IM', 'Kodam Iskandar Muda', 'Kotamabin', 26],
];

$stmtKotama = $pdo->prepare("
    INSERT INTO master_kotama (kode, nama, tipe, urutan)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        nama = VALUES(nama),
        tipe = VALUES(tipe),
        urutan = VALUES(urutan)
");
foreach ($kotamaData as $row) {
    $stmtKotama->execute($row);
}
echo "  - Master kotama berhasil di-seed (" . count($kotamaData) . " kotama/balakpus).\n";

// Dapatkan mapping ID Kotama
$kotamaMap = $pdo->query("SELECT kode, id FROM master_kotama")->fetchAll(PDO::FETCH_KEY_PAIR);

// 6d. Master Satuan Organik Berelasi ke Kotama
$satuanData = [
    // Pusziad
    [$kotamaMap['PUSZIAD'] ?? null, 'DITZIAD', 'Ditziad Mabesad', 'Jakarta Pusat', 1],
    [$kotamaMap['PUSZIAD'] ?? null, 'PUSDIKZI', 'Pusdikzi Kodiklatad', 'Bogor', 2],

    // Kodam I/Bukit Barisan
    [$kotamaMap['KODAM-I'] ?? null, 'KAZIDAM-I', 'Kazidam I/Bukit Barisan', 'Medan', 10],
    [$kotamaMap['KODAM-I'] ?? null, 'DENZIBANG-1-I', 'Denzibang 1/I Medan', 'Medan', 11],
    [$kotamaMap['KODAM-I'] ?? null, 'DENZIBANG-2-I', 'Denzibang 2/I Pematangsiantar', 'P. Siantar', 12],
    [$kotamaMap['KODAM-I'] ?? null, 'DENZIBANG-3-I', 'Denzibang 3/I Padang', 'Padang', 13],
    [$kotamaMap['KODAM-I'] ?? null, 'DENZIBANG-4-I', 'Denzibang 4/I Sibolga', 'Sibolga', 14],
    [$kotamaMap['KODAM-I'] ?? null, 'DENZIBANG-5-I', 'Denzibang 5/I Pekanbaru', 'Pekanbaru', 15],
    [$kotamaMap['KODAM-I'] ?? null, 'DENZIBANG-6-I', 'Denzibang 6/I Batam', 'Batam', 16],
    [$kotamaMap['KODAM-I'] ?? null, 'YONZIPUR-1', 'Yonzipur 1/Dhira Dharma', 'Medan', 17],
    [$kotamaMap['KODAM-I'] ?? null, 'KODIM-0201', 'Kodim 0201/Medan', 'Medan', 18],
    [$kotamaMap['KODAM-I'] ?? null, 'KOREM-022', 'Korem 022/Pantai Timur', 'P. Siantar', 19],
    [$kotamaMap['KODAM-I'] ?? null, 'KOREM-023', 'Korem 023/Kawal Samudera', 'Sibolga', 20],
    [$kotamaMap['KODAM-I'] ?? null, 'KOREM-031', 'Korem 031/Wira Bima', 'Pekanbaru', 21],
    [$kotamaMap['KODAM-I'] ?? null, 'KOREM-032', 'Korem 032/Wirabraja', 'Padang', 22],
    [$kotamaMap['KODAM-I'] ?? null, 'KOREM-033', 'Korem 033/Wira Pratama', 'Tanjung Pinang', 23],
    [$kotamaMap['KODAM-I'] ?? null, 'YONIF-100', 'Yonif 100/Prajurit Setia', 'Namusira-sira', 24],

    // Kostrad
    [$kotamaMap['KOSTRAD'] ?? null, 'YONZIPUR-9', 'Yonzipur 9/Lang-Lang Bhuana Kostrad', 'Ujungberung, Bandung', 30],
    [$kotamaMap['KOSTRAD'] ?? null, 'YONZIPUR-10', 'Yonzipur 10/Jaladri Palaka Kostrad', 'Pasuruan', 31],

    // Kodam Jaya
    [$kotamaMap['KODAM-JAYA'] ?? null, 'DENZIBANG-1-JAYA', 'Denzibang 1/Jaya', 'Jakarta', 40],
    [$kotamaMap['KODAM-JAYA'] ?? null, 'DENZIBANG-2-JAYA', 'Denzibang 2/Jaya', 'Jakarta', 41],
    [$kotamaMap['KODAM-JAYA'] ?? null, 'YONZIPUR-11', 'Yonzipur 11/Durdhaga Wighra', 'Matraman', 42],

    // Kodam XVII/Cenderawasih
    [$kotamaMap['KODAM-XVII'] ?? null, 'YONIF-TP-815', 'Yonif Tp 815', 'Jayapura', 50],
];

$stmtSatuan = $pdo->prepare("
    INSERT INTO master_satuan (kotama_id, kode, nama, lokasi, urutan)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        kotama_id = VALUES(kotama_id),
        nama = VALUES(nama),
        lokasi = VALUES(lokasi),
        urutan = VALUES(urutan)
");
foreach ($satuanData as $row) {
    $stmtSatuan->execute($row);
}
echo "  - Master satuan berhasil di-seed (" . count($satuanData) . " satuan organik).\n";

// 7. Sinkronisasi Natural Otomatis Personel Eksisting
echo "\nMenyelaraskan data personel eksisting ke relasi master_pangkat, master_korp, master_satuan, master_kotama...\n";

// Map seluruh master
$pangkatList = $pdo->query("SELECT id, singkatan, nama, kode FROM master_pangkat")->fetchAll(PDO::FETCH_ASSOC);
$korpList = $pdo->query("SELECT id, kode, nama FROM master_korp")->fetchAll(PDO::FETCH_ASSOC);
$kotamaList = $pdo->query("SELECT id, kode, nama FROM master_kotama")->fetchAll(PDO::FETCH_ASSOC);
$satuanList = $pdo->query("SELECT id, kode, nama, kotama_id FROM master_satuan")->fetchAll(PDO::FETCH_ASSOC);

$personelAll = $pdo->query("SELECT id, pangkat, korp, satuan, kotama FROM personel")->fetchAll(PDO::FETCH_ASSOC);

$updStmt = $pdo->prepare("
    UPDATE personel 
    SET pangkat_id = ?, korp_id = ?, satuan_id = ?, kotama_id = ?
    WHERE id = ?
");

$syncedCount = 0;
foreach ($personelAll as $p) {
    $matchedPktId = null;
    $matchedKorpId = null;
    $matchedSatId = null;
    $matchedKotId = null;

    // Match Pangkat
    $pStr = trim($p['pangkat'] ?? '');
    if ($pStr !== '') {
        foreach ($pangkatList as $mp) {
            if (strcasecmp($mp['singkatan'], $pStr) === 0 || strcasecmp($mp['kode'], $pStr) === 0 || stripos($pStr, $mp['singkatan']) !== false) {
                $matchedPktId = $mp['id'];
                break;
            }
        }
    }

    // Match Korp
    $kStr = trim($p['korp'] ?? '');
    if ($kStr !== '') {
        foreach ($korpList as $mk) {
            if (strcasecmp($mk['kode'], $kStr) === 0 || strcasecmp($mk['nama'], $kStr) === 0) {
                $matchedKorpId = $mk['id'];
                break;
            }
        }
    }

    // Match Kotama
    $kotStr = trim($p['kotama'] ?? '');
    if ($kotStr !== '') {
        foreach ($kotamaList as $mkot) {
            if (strcasecmp($mkot['nama'], $kotStr) === 0 || strcasecmp($mkot['kode'], $kotStr) === 0 || stripos($kotStr, '17') !== false && $mkot['kode'] === 'KODAM-XVII') {
                $matchedKotId = $mkot['id'];
                break;
            }
        }
    }

    // Match Satuan
    $satStr = trim($p['satuan'] ?? '');
    if ($satStr !== '') {
        foreach ($satuanList as $msat) {
            if (strcasecmp($msat['nama'], $satStr) === 0 || strcasecmp($msat['kode'], $satStr) === 0 || stripos($satStr, '815') !== false && $msat['kode'] === 'YONIF-TP-815') {
                $matchedSatId = $msat['id'];
                if (!$matchedKotId && $msat['kotama_id']) {
                    $matchedKotId = $msat['kotama_id'];
                }
                break;
            }
        }
    }

    $updStmt->execute([$matchedPktId, $matchedKorpId, $matchedSatId, $matchedKotId, $p['id']]);
    $syncedCount++;
}

echo "  - Berhasil menyelaraskan $syncedCount rekaman personel ke relasi natural basis data.\n";
$pdo->exec("SET foreign_key_checks = 1;");
echo "Migrasi basis data relasional alami SELESAI DENGAN SUKSES!\n";
