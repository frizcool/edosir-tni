<?php
// =====================================================================
// GENERATOR DOKUMENTASI SISTEM PROGRAM APLIKASI TRISULA TNI AD
// Sesuai Naskah Sekolah Kadisinfolahtad Nomor: 61 - A – 009 (KEP/23/IV/2022)
// Format Keluaran: Microsoft Word (.docx)
// =====================================================================

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table as TableStyle;

echo "Memulai penyusunan Dokumentasi Sistem TRISULA TNI AD dalam format Word (.docx)...\n";

$phpWord = new PhpWord();

// ---------------------------------------------------------------------
// 1. PENGATURAN GAYA & FONT STANDAR MILITER TNI AD
// ---------------------------------------------------------------------
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(12);

// Definisi Font Style
$fTitleKop     = ['name' => 'Times New Roman', 'size' => 12, 'bold' => true];
$fDocTitle     = ['name' => 'Times New Roman', 'size' => 15, 'bold' => true];
$fDocSub       = ['name' => 'Times New Roman', 'size' => 12, 'bold' => true, 'italic' => true];
$fRahasia      = ['name' => 'Times New Roman', 'size' => 10, 'bold' => true, 'color' => 'B22222'];
$fBabTitle     = ['name' => 'Times New Roman', 'size' => 13, 'bold' => true];
$fHeading1     = ['name' => 'Times New Roman', 'size' => 12, 'bold' => true];
$fHeading2     = ['name' => 'Times New Roman', 'size' => 12, 'bold' => true];
$fNormal       = ['name' => 'Times New Roman', 'size' => 11.5];
$fNormalBold   = ['name' => 'Times New Roman', 'size' => 11.5, 'bold' => true];
$fNormalItalic = ['name' => 'Times New Roman', 'size' => 11.5, 'italic' => true];
$fTableHead    = ['name' => 'Times New Roman', 'size' => 10, 'bold' => true];
$fTableCell    = ['name' => 'Times New Roman', 'size' => 9.5];
$fTableCellB   = ['name' => 'Times New Roman', 'size' => 9.5, 'bold' => true];
$fTableCellCode= ['name' => 'Consolas', 'size' => 8.5];
$fCodeBlock    = ['name' => 'Consolas', 'size' => 8.5, 'color' => '1B3B1B'];

// Definisi Paragraph Style
$pCenter = ['alignment' => Jc::CENTER, 'spaceAfter' => 60];
$pJustify = ['alignment' => Jc::BOTH, 'spaceAfter' => 80, 'lineHeight' => 1.15];
$pLeft = ['alignment' => Jc::START, 'spaceAfter' => 60];
$pRight = ['alignment' => Jc::END, 'spaceAfter' => 60];
$pIndent1 = ['alignment' => Jc::BOTH, 'indent' => 0.5, 'spaceAfter' => 60, 'lineHeight' => 1.15];
$pIndent2 = ['alignment' => Jc::BOTH, 'indent' => 0.9, 'spaceAfter' => 60, 'lineHeight' => 1.15];

// Definisi Style Tabel
$tableStyleBorder = [
    'borderColor' => '000000',
    'borderSize'  => 6,
    'cellMargin'  => 60,
    'alignment'   => JcTable::CENTER,
];
$phpWord->addTableStyle('MilitaryTable', $tableStyleBorder);

$tableStyleBox = [
    'borderColor' => '000000',
    'borderSize'  => 12,
    'cellMargin'  => 80,
    'alignment'   => JcTable::CENTER,
];
$phpWord->addTableStyle('BoxTable', $tableStyleBox);

$cellHeadBg = ['bgColor' => 'EAEAEA'];
$cellLightGreen = ['bgColor' => 'F2F6F2'];

// ---------------------------------------------------------------------
// 2. HALAMAN JUDUL (COVER)
// ---------------------------------------------------------------------
$coverSection = $phpWord->addSection([
    'paperSize'    => 'A4',
    'marginTop'    => Converter::cmToTwip(2.0),
    'marginBottom' => Converter::cmToTwip(2.0),
    'marginLeft'   => Converter::cmToTwip(3.0),
    'marginRight'  => Converter::cmToTwip(2.0),
]);

// Header Rahasia Cover
$cHeader = $coverSection->addHeader();
$cHeader->addText('RAHASIA', $fRahasia, $pCenter);

// Footer Rahasia Cover
$cFooter = $coverSection->addFooter();
$cFooter->addText('RAHASIA', $fRahasia, $pCenter);

// KOP Dinas TNI AD
$coverSection->addText('MARKAS BESAR TNI ANGKATAN DARAT', $fTitleKop, $pCenter);
$coverSection->addText('DINAS INFORMASI DAN PENGOLAHAN DATA', $fTitleKop, $pCenter);
$coverSection->addTextBreak(2);

// Gambar Logo jika tersedia
$logoPath = __DIR__ . '/assets/img/logo_1789696457.png';
if (file_exists($logoPath)) {
    $coverSection->addImage($logoPath, [
        'width'         => 110,
        'height'        => 110,
        'alignment'     => Jc::CENTER,
        'marginTop'     => 10,
        'marginBottom'  => 10,
    ]);
} else {
    $coverSection->addText('★ ★ ★', ['size' => 24, 'bold' => true, 'color' => 'B8860B'], $pCenter);
}

$coverSection->addTextBreak(2);

$coverSection->addText('DOKUMENTASI SISTEM PROGRAM APLIKASI', $fDocTitle, $pCenter);
$coverSection->addTextBreak(1);
$coverSection->addText('TRISULA TNI AD', ['name' => 'Times New Roman', 'size' => 20, 'bold' => true, 'color' => '224222'], $pCenter);
$coverSection->addText('(TATA KELOLA REKAM INFORMASI, SISTEMATIKA, & UNDUHAN LENGKAP ARSIP)', $fDocSub, $pCenter);
$coverSection->addTextBreak(1);
$coverSection->addText('Sistem Dosir Elektronik, Digitalisasi 33 Berkas Baku Induk, Verifikasi Berjenjang, dan Otentikasi Tanda Tangan Elektronik (TTE) Personel Militer & PNS TNI AD', ['name' => 'Times New Roman', 'size' => 11, 'italic' => true], $pCenter);

$coverSection->addTextBreak(4);

$coverSection->addText('Disusun Sebagai Dokumentasi Teknis dan Operasional Pembinaan Sistem Informasi TNI AD', ['name' => 'Times New Roman', 'size' => 11], $pCenter);
$coverSection->addText('Berdasarkan Naskah Sekolah Kadisinfolahtad Nomor: 61 - A – 009 (KEP/23/IV/2022)', ['name' => 'Times New Roman', 'size' => 10.5, 'bold' => true], $pCenter);

$coverSection->addTextBreak(3);

$coverSection->addText('Disusun Oleh :', $fNormalBold, $pCenter);
$coverSection->addText('LETDA CZI FRIS WARDANI', ['name' => 'Times New Roman', 'size' => 12, 'bold' => true], $pCenter);
$coverSection->addText('Perwira Pertama Korps Zeni TNI Angkatan Darat', $fNormalItalic, $pCenter);

$coverSection->addTextBreak(2);
$coverSection->addText('JAKARTA', $fNormalBold, $pCenter);
$coverSection->addText('2026', $fNormalBold, $pCenter);


// ---------------------------------------------------------------------
// 3. SECTION UTAMA DOKUMENTASI (ISI)
// ---------------------------------------------------------------------
$mainSection = $phpWord->addSection([
    'paperSize'    => 'A4',
    'marginTop'    => Converter::cmToTwip(2.5),
    'marginBottom' => Converter::cmToTwip(2.5),
    'marginLeft'   => Converter::cmToTwip(3.0),
    'marginRight'  => Converter::cmToTwip(2.0),
]);

// Header Halaman Isi
$mHeader = $mainSection->addHeader();
$mHeader->addText('RAHASIA', $fRahasia, $pCenter);

// Footer Halaman Isi (Halaman Dinamis)
$mFooter = $mainSection->addFooter();
$mFooterTable = $mFooter->addTable(['width' => 100 * 50, 'alignment' => JcTable::CENTER]);
$mFooterTable->addRow();
$mFooterTable->addCell(5000)->addText('RAHASIA', $fRahasia, $pLeft);
$mFooterTable->addCell(5000)->addPreserveText('Halaman {PAGE} dari {NUMPAGES}', ['name' => 'Times New Roman', 'size' => 9], $pRight);

// ---------------------------------------------------------------------
// LEMBAR BAGIAN 1: FORMULIR DOKUMENTASI PROGRAM (GAMBAR-1 DISINFOLAHTAD)
// ---------------------------------------------------------------------
$mainSection->addText('LEMBAR DOKUMENTASI PROGRAM', $fBabTitle, $pCenter);
$mainSection->addText('Format Standar Naskah Sekolah Disinfolahtad (Gambar - 1)', $fNormalItalic, $pCenter);
$mainSection->addTextBreak(1);

$boxTable = $mainSection->addTable('BoxTable');
$boxTable->addRow();
$cellBox = $boxTable->addCell(9200);

$cellBox->addText('DOKUMENTASI PROGRAM', ['name' => 'Times New Roman', 'size' => 13, 'bold' => true], $pCenter);
$cellBox->addTextBreak(1);

$tMeta = $cellBox->addTable(['alignment' => JcTable::CENTER]);

$metaRows = [
    ['NAMA APLIKASI', ':', 'TRISULA TNI AD'],
    ['NAMA PROGRAM', ':', 'SISTEM DOSIR ELEKTRONIK & VERIFIKASI TTE PERSONEL'],
    ['FUNGSI PROGRAM', ':', 'Tata Kelola dan Digitalisasi 33 Jenis Dosir Baku, Verifikasi Dokumen Berjenjang, Pengesahan Tanda Tangan Elektronik (TTE) Ber-QR Code, Pemantauan Kelengkapan Dosir, Deteksi Masa Jabatan > 2 Tahun, dan Proyeksi Batas Usia Pensiun Prajurit & PNS TNI AD.'],
    ['NAMA PEMROGRAM', ':', 'Letda Czi Fris Wardani'],
    ['BAHASA PROGRAM', ':', 'PHP 8.3 (Native PDO), JavaScript (ES6 / jsPDF), HTML5, CSS3, MySQL'],
    ['LOKASI PROGRAM', ':', 'd:\\xampp\\htdocs\\edosir-tni'],
    ['TANGGAL SELESAI', ':', '21 September 2026'],
];

foreach ($metaRows as $mr) {
    $tMeta->addRow();
    $tMeta->addCell(2200)->addText($mr[0], $fNormalBold, $pLeft);
    $tMeta->addCell(250)->addText($mr[1], $fNormalBold, $pCenter);
    $tMeta->addCell(6400)->addText($mr[2], $fNormal, $pJustify);
}

$cellBox->addTextBreak(1);
$cellBox->addText('ISI DOKUMENTASI :', ['name' => 'Times New Roman', 'size' => 11.5, 'bold' => true], $pLeft);

$isiItems = [
    '1. NARASI PROGRAM',
    '2. DIAGRAM ARUS DATA (DFD)',
    '3. DIAGRAM HUBUNGAN ENTITY (ERD)',
    '4. KAMUS DATA / STRUKTUR FILE',
    '5. SPESIFIKASI PROGRAM',
    '6. BENTUK TAMPILAN DAN CETAKAN',
    '7. LISTING PROGRAM',
];

foreach ($isiItems as $item) {
    $cellBox->addText('   ' . $item, $fNormalBold, $pLeft);
}

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// DAFTAR ISI
// ---------------------------------------------------------------------
$mainSection->addText('DAFTAR ISI', $fBabTitle, $pCenter);
$mainSection->addTextBreak(1);

$tDaftarIsi = $mainSection->addTable(['alignment' => JcTable::CENTER, 'width' => 9200]);
$daftarIsiRows = [
    ['BAB I', 'PENDAHULUAN', '1'],
    ['', '1. Umum', '1'],
    ['', '2. Maksud dan Tujuan', '1'],
    ['', '3. Ruang Lingkup dan Tata Urut', '2'],
    ['', '4. Referensi', '2'],
    ['', '5. Pengertian', '2'],
    ['BAB II', 'MATERI DOKUMENTASI', '3'],
    ['', '6. Umum', '3'],
    ['', '7. Tujuan Pembuatan Dokumentasi Sistem', '3'],
    ['', '8. Cara Pengisian', '3'],
    ['BAB III', 'PERILAKU TERHADAP DOKUMEN / KETENTUAN DOKUMEN', '4'],
    ['', '9. Umum', '4'],
    ['', '10. Narasi Program', '4'],
    ['', '11. Diagram Arus Data (Data Flow Diagram)', '6'],
    ['', '12. Diagram Hubungan Entity (ERD)', '8'],
    ['', '13. Kamus Data / Struktur File', '9'],
    ['', '14. Spesifikasi Program', '14'],
    ['', '15. Bentuk Tampilan dan Cetakan', '19'],
    ['', '16. Listing Program', '23'],
    ['BAB IV', 'PENUTUP', '28'],
    ['', '17. Penutup', '28'],
];

foreach ($daftarIsiRows as $di) {
    $tDaftarIsi->addRow();
    $bld = ($di[0] !== '') ? true : false;
    $tDaftarIsi->addCell(1200)->addText($di[0], $bld ? $fNormalBold : $fNormal, $pLeft);
    $tDaftarIsi->addCell(7000)->addText($di[1], $bld ? $fNormalBold : $fNormal, $pLeft);
    $tDaftarIsi->addCell(1000)->addText($di[2], $bld ? $fNormalBold : $fNormal, $pRight);
}

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// BAB I PENDAHULUAN
// ---------------------------------------------------------------------
$mainSection->addText('BAB I', $fBabTitle, $pCenter);
$mainSection->addText('PENDAHULUAN', $fBabTitle, $pCenter);
$mainSection->addTextBreak(1);

$mainSection->addText('1.   Umum.', $fHeading1, $pLeft);
$mainSection->addText('a.   Dokumentasi sistem merupakan salah satu kegiatan fundamental dalam penyelenggaraan pembinaan sistem informasi di lingkungan TNI AD pada setiap tahapan daur hidup pengembangan perangkat lunak (System Development Life Cycle). Dokumentasi sistem dirancang secara kolaboratif oleh Analis Sistem (Nalsis) dan Pemrogram, selanjutnya dihimpun menjadi satu berkas terstruktur yang menjadi tanggung jawab resmi Pemrogram guna menjamin transparansi, akuntabilitas, dan keterpeliharaan sistem.', $fNormal, $pJustify);
$mainSection->addText('b.   Prajurit dan Pegawai Negeri Sipil (PNS) TNI Angkatan Darat dalam pelaksanaan dinas keprajuritan memiliki 33 (tiga puluh tiga) jenis dokumen induk administrasi atau dosir wajib. Selama ini, tata kelola dosir fisik menghadapi kerentanan risiko kerusakan fisik akibat lapuk, kelembapan, bencana alam, serta lambatnya proses pencarian berkas saat pengusulan kenaikan pangkat (UKP), penugasan operasi, maupun persiapan masa purna tugas.', $fNormal, $pJustify);
$mainSection->addText('c.   Untuk mengatasi tantangan tersebut, dibangun sistem TRISULA TNI AD (Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip). Sistem ini mendigitalisasi seluruh 33 dosir prajurit, memfasilitasi pemindaian langsung melalui kamera perangkat bergerak, menerapkan verifikasi administratif bertingkat oleh Staf Personel, serta mengesahkan dokumen melalui Tanda Tangan Elektronik (TTE) ber-QR Code dengan verifikasi integritas cryptographic hash SHA-256.', $fNormal, $pJustify);
$mainSection->addText('d.   Agar operasional, pengawasan, pemeliharaan (*har*), dan pengembangan aplikasi TRISULA TNI AD dapat berjalan secara berkelanjutan dan berdaya guna, maka disusunlah Buku Dokumentasi Sistem ini dengan berpedoman pada kaidah resmi pembinaan sistem informasi TNI Angkatan Darat.', $fNormal, $pJustify);

$mainSection->addTextBreak(1);
$mainSection->addText('2.   Maksud dan Tujuan.', $fHeading1, $pLeft);
$mainSection->addText('a.   Maksud. Dokumentasi Sistem Program Aplikasi TRISULA TNI AD ini disusun dengan maksud untuk memberikan gambaran, uraian teknis, logika pemrograman, arsitektur basis data, serta spesifikasi modul sistem secara utuh dan transparan.', $fNormal, $pJustify);
$mainSection->addText('b.   Tujuan. Dokumentasi ini disusun dengan tujuan sebagai pedoman baku bagi Analis Sistem, Pemrogram, Administrator Satuan (Staf Personel/Infolahta), Teknisi Komputer, Auditor Inspektorat, serta pimpinan satuan dalam mengoperasikan, mengevaluasi, memelihara, dan mengembangkan aplikasi TRISULA TNI AD di masa depan.', $fNormal, $pJustify);

$mainSection->addTextBreak(1);
$mainSection->addText('3.   Ruang Lingkup dan Tata Urut.', $fHeading1, $pLeft);
$mainSection->addText('a.   Ruang Lingkup. Dokumentasi ini mencakup keseluruhan daur rancang bangun aplikasi TRISULA TNI AD, meliputi narasi logika fungsional, diagram arus data (DFD), model hubungan entitas (ERD), kamus data terinci, spesifikasi form dan prosedur pengolahan, bentuk tampilan visual dan cetakan laporan kedinasan, serta potongan kode program inti (core source code).', $fNormal, $pJustify);
$mainSection->addText('b.   Tata Urut. Naskah dokumentasi sistem ini disusun dengan tata urut sebagai berikut :', $fNormal, $pJustify);
$mainSection->addText('     1)   BAB I     PENDAHULUAN', $fNormalBold, $pLeft);
$mainSection->addText('     2)   BAB II    MATERI DOKUMENTASI', $fNormalBold, $pLeft);
$mainSection->addText('     3)   BAB III   PERILAKU TERHADAP DOKUMEN / KETENTUAN DOKUMEN', $fNormalBold, $pLeft);
$mainSection->addText('     4)   BAB IV    PENUTUP', $fNormalBold, $pLeft);

$mainSection->addTextBreak(1);
$mainSection->addText('4.   Referensi.', $fHeading1, $pLeft);
$mainSection->addText('Penyusunan dokumentasi sistem ini didasarkan pada rujukan resmi kedinasan :', $fNormal, $pJustify);
$mainSection->addText('a.   Surat Keputusan Kasad Nomor Skep/45/II/2005 tanggal 14 Maret 2005 tentang Buku Petunjuk Teknik Penyusunan Dokumentasi Sistem.', $fNormal, $pJustify);
$mainSection->addText('b.   Keputusan Kadisinfolahtad Nomor Kep/23/IV/2022 tanggal 4 April 2022 tentang Naskah Sekolah Pengetahuan Dokumentasi Program Aplikasi untuk Pendidikan Perwira TNI AD Nomor Kode: 61 - A – 009.', $fNormal, $pJustify);
$mainSection->addText('c.   Keputusan Kasad Nomor Kep/548/VI/2016 tanggal 24 Juni 2016 tentang Buku Petunjuk Teknis Tulisan Dinas di Lingkungan TNI Angkatan Darat.', $fNormal, $pJustify);
$mainSection->addText('d.   Buku Petunjuk Teknis TNI AD tentang Pembinaan Karier dan Administrasi Dosir Prajurit Angkatan Darat.', $fNormal, $pJustify);

$mainSection->addTextBreak(1);
$mainSection->addText('5.   Pengertian.', $fHeading1, $pLeft);
$mainSection->addText('a.   Narasi Program adalah penjelasan terstruktur mengenai fungsi program, file input, urutan proses pengolahan, algoritma perhitungan, dan keluaran yang dihasilkan sistem.', $fNormal, $pJustify);
$mainSection->addText('b.   Input adalah data atau berkas yang dimasukkan ke dalam sistem, baik berupa data identitas pokok prajurit maupun berkas digital PDF 33 jenis dosir.', $fNormal, $pJustify);
$mainSection->addText('c.   Output adalah keluaran hasil olahan sistem, baik berupa tampilan antarmuka visual (display), berkas PDF ber-TTE resmi, maupun lembar laporan siap cetak berkop dinas.', $fNormal, $pJustify);
$mainSection->addText('d.   Dosir Elektronik adalah kumpulan naskah/dokumen digital autentik riwayat hidup prajurit yang dihimpun secara sistematis dari awal pengangkatan hingga purna tugas.', $fNormal, $pJustify);
$mainSection->addText('e.   Tanda Tangan Elektronik (TTE) adalah tanda tangan yang terdiri atas informasi elektronik yang dilekatkan, terasosiasi atau terkait dengan informasi elektronik lainnya sebagai alat verifikasi dan autentikasi keabsahan dokumen dinas.', $fNormal, $pJustify);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// BAB II MATERI DOKUMENTASI
// ---------------------------------------------------------------------
$mainSection->addText('BAB II', $fBabTitle, $pCenter);
$mainSection->addText('MATERI DOKUMENTASI', $fBabTitle, $pCenter);
$mainSection->addTextBreak(1);

$mainSection->addText('6.   Umum.', $fHeading1, $pLeft);
$mainSection->addText('Materi dokumentasi diberi judul resmi "DOKUMENTASI PROGRAM", yang terdiri atas 2 (dua) bagian utama terpadu :', $fNormal, $pJustify);
$mainSection->addText('a.   Bagian 1 : Keterangan-Keterangan tentang Program. Berisi ringkasan identitas manajerial sistem informasi meliputi nama aplikasi, nama modul program, fungsi ringkas, penanggung jawab/pemrogram, bahasa dan lingkungan pemrograman, direktori instalasi, dan tanggal selesai pengembangan.', $fNormal, $pJustify);
$mainSection->addText('b.   Bagian 2 : Ketentuan Dokumen / Isi Dokumen. Merupakan inti materi teknis yang memuat narasi program, diagram arus data, diagram hubungan entitas, kamus data struktur tabel, formulir spesifikasi program, bentuk tampilan dan cetakan, serta listing program.', $fNormal, $pJustify);

$mainSection->addTextBreak(1);
$mainSection->addText('7.   Tujuan Pembuatan Dokumentasi Sistem.', $fHeading1, $pLeft);
$mainSection->addText('Berdasarkan doktrin pembinaan sistem informasi TNI AD, ada 3 (tiga) tujuan strategis pembuatan dokumentasi sistem :', $fNormal, $pJustify);
$mainSection->addText('a.   Untuk Menjelaskan Cara Kerja Sistem. Dokumentasi menyederhanakan sistem pengolahan data dosir yang kompleks (melibatkan penomoran otomatis 33 berkas, cryptographic hashing, dan watermarking) menjadi uraian yang mudah dipahami dalam waktu singkat oleh staf manajerial maupun teknisi baru.', $fNormal, $pJustify);
$mainSection->addText('b.   Sebagai Alat dalam Merancang dan Mengembangkan Sistem. Rancangan arsitektur dan relasi basis data yang terdokumentasi rapi mencegah hilangnya memori institusional, mempermudah koordinasi antar pemrogram militer, serta menjamin kelancaran modifikasi sistem di masa mendatang.', $fNormal, $pJustify);
$mainSection->addText('c.   Sebagai Alat bagi Auditor dan Pengawas Internal. Memberikan dasar pijakan bagi Inspektorat (Itjenad/Itdam) dan Staf Intelijen/Sandi dalam mengevaluasi kepatuhan sistem terhadap prosedur pengendalian internal (internal control), keamanan data personel, dan integritas legalitas TTE.', $fNormal, $pJustify);

$mainSection->addTextBreak(1);
$mainSection->addText('8.   Cara Pengisian.', $fHeading1, $pLeft);
$mainSection->addText('Ketentuan pengisian formulir dokumentasi Bagian 1 dilaksanakan sebagai berikut :', $fNormal, $pJustify);
$mainSection->addText('a.   Nama Aplikasi : Diisi dengan nama resmi aplikasi, yaitu "TRISULA TNI AD" (Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip).', $fNormal, $pJustify);
$mainSection->addText('b.   Nama Program : Diisi nama komponen fungsional yang dibangun, yaitu "SISTEM DOSIR ELEKTRONIK & VERIFIKASI TTE PERSONEL".', $fNormal, $pJustify);
$mainSection->addText('c.   Fungsi Program : Menguraikan secara komprehensif kemampuan sistem dalam digitalisasi 33 dosir, verifikasi bertingkat, penandatanganan elektronik, deteksi pensiun, serta rotasi jabatan.', $fNormal, $pJustify);
$mainSection->addText('d.   Nama Pemrogram : Diisi personel yang ditunjuk dan bertanggung jawab dalam perancangan kode, yaitu Letda Czi Fris Wardani.', $fNormal, $pJustify);
$mainSection->addText('e.   Bahasa Program : Diisi lingkungan teknologi yang digunakan, yaitu PHP 8.3 Native PDO, JavaScript, HTML5, CSS3, dan MySQL.', $fNormal, $pJustify);
$mainSection->addText('f.   Lokasi Program : Diisi direktori penyimpanan aplikasi di peladen (web server), yaitu "d:\\xampp\\htdocs\\edosir-tni".', $fNormal, $pJustify);
$mainSection->addText('g.   Tanggal Selesai : Diisi tanggal finalisasi uji fungsi dan penerbitan sistem, yaitu 21 September 2026.', $fNormal, $pJustify);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// BAB III PERILAKU TERHADAP DOKUMEN / KETENTUAN DOKUMEN
// ---------------------------------------------------------------------
$mainSection->addText('BAB III', $fBabTitle, $pCenter);
$mainSection->addText('PERILAKU TERHADAP DOKUMEN / KETENTUAN DOKUMEN', $fBabTitle, $pCenter);
$mainSection->addTextBreak(1);

$mainSection->addText('9.   Umum.', $fHeading1, $pLeft);
$mainSection->addText('Isi dokumentasi program TRISULA TNI AD merupakan kumpulan dokumen teknis terpadu yang memuat 7 (tujuh) instrumen pokok rancang bangun perangkat lunak, yang diuraikan secara mendalam pada pasal-pasal berikut.', $fNormal, $pJustify);

$mainSection->addTextBreak(1);
$mainSection->addText('10.  Narasi Program.', $fHeading1, $pLeft);
$mainSection->addText('Narasi program memberikan gambaran menyeluruh tentang fungsi, file input, rincian proses, dan keluaran sistem aplikasi TRISULA TNI AD :', $fNormal, $pJustify);

// Sub a. Fungsi
$mainSection->addText('a.   Fungsi Program.', $fHeading2, $pLeft);
$mainSection->addText('Program aplikasi TRISULA TNI AD berfungsi sebagai sarana terpadu pembinaan administrasi personel militer dan PNS di jajaran TNI Angkatan Darat melalui mekanisme digitalisasi warkat induk dosir. Fungsi utama sistem mencakup :', $fNormal, $pJustify);
$mainSection->addText('     1)   Pendaftaran akun personel secara mandiri berbasis NRP/NIP dengan kontrol persetujuan bertingkat oleh Administrator Satuan.', $fNormal, $pJustify);
$mainSection->addText('     2)   Pengelolaan 33 jenis dosir baku personel TNI AD dari Dosir 01 (Surat Lamaran) hingga Dosir 33 (Foto dinas terbaru).', $fNormal, $pJustify);
$mainSection->addText('     3)   Standarisasi penamaan berkas fisik otomatis berformat "{NRP}_{KODE}[abjad].pdf" yang tersimpan pada struktur folder terisolasi "uploads/FOLDER 01" s.d. "FOLDER 33".', $fNormal, $pJustify);
$mainSection->addText('     4)   Pemindaian dokumen langsung menggunakan kamera gawai/komputer (camera scan) dengan konversi otomatis citra multi-halaman menjadi satu file PDF standar.', $fNormal, $pJustify);
$mainSection->addText('     5)   Verifikasi berkas oleh Administrator dengan pembubuhan Sertifikasi Tanda Tangan Elektronik (TTE) ber-QR Code dan tanda air (watermark) dinamis.', $fNormal, $pJustify);
$mainSection->addText('     6)   Layanan publik pemeriksaan keaslian dokumen secara cepat melalui pemindaian QR Code pada portal publik (verify.php).', $fNormal, $pJustify);
$mainSection->addText('     7)   Pemantauan persentase kelengkapan dosir personel secara real-time dan rekapitulasi rata-rata satuan.', $fNormal, $pJustify);
$mainSection->addText('     8)   Kalkulasi cerdas batas usia pensiun (BUP) otomatis berdasarkan golongan kepangkatan (Perwira 58 th, Bintara/Tamtama 56 th, PNS 60 th).', $fNormal, $pJustify);
$mainSection->addText('     9)   Deteksi dini prajurit yang telah menduduki jabatan lebih dari 2 (dua) tahun guna mendukung perencanaan Tour of Duty dan Tour of Area (TOD/TOA).', $fNormal, $pJustify);
$mainSection->addText('     10)  Pengunduhan massal (bulk download) berkas dosir per satuan/personel dalam satu arsip terkompresi ZIP berstruktur rapi.', $fNormal, $pJustify);
$mainSection->addText('     11)  Penerbitan laporan resmi siap cetak berkop dinas staf personel lengkap dengan kolom tanda tangan komando.', $fNormal, $pJustify);
$mainSection->addText('     12)  Pencadangan pangkalan data (mysqldump) dan berkas digital secara mandiri disertai pencatatan riwayat audit (audit trail log).', $fNormal, $pJustify);

// Sub b. Input
$mainSection->addTextBreak(1);
$mainSection->addText('b.   Input Program.', $fHeading2, $pLeft);
$mainSection->addText('File dan tabel basis data yang menjadi masukan bagi sistem TRISULA TNI AD meliputi :', $fNormal, $pJustify);

$tInput = $mainSection->addTable('MilitaryTable');
$tInput->addRow();
$tInput->addCell(500, $cellHeadBg)->addText('No', $fTableHead, $pCenter);
$tInput->addCell(2200, $cellHeadBg)->addText('Nama File / Tabel', $fTableHead, $pCenter);
$tInput->addCell(2000, $cellHeadBg)->addText('Media Simpan', $fTableHead, $pCenter);
$tInput->addCell(1500, $cellHeadBg)->addText('Panjang Record', $fTableHead, $pCenter);
$tInput->addCell(3000, $cellHeadBg)->addText('Keterangan / Fungsi Masukan', $fTableHead, $pCenter);

$inputData = [
    ['1', 'personel', 'Harddisk (MySQL DB)', '1.250 Byte', 'Data induk identitas prajurit/PNS (NRP, Nama, Pangkat, Korps, Satuan, Jabatan, TMT, Tgl Lahir)'],
    ['2', 'users', 'Harddisk (MySQL DB)', '420 Byte', 'Data kredensial akun, hash password bcrypt, role (admin/personel), status approval'],
    ['3', 'dosir_master', 'Harddisk (MySQL DB)', '240 Byte', 'Tabel referensi baku 33 jenis dosir (Kode 01 s.d. 33, nama berkas, status wajib)'],
    ['4', 'dosir_files', 'Harddisk (MySQL DB)', '980 Byte', 'Metadata berkas terunggah, status verifikasi, hash SHA-256, kode TTE, catatan verifikasi'],
    ['5', 'uploads/FOLDER 01..33', 'Harddisk (Storage PDF)', 'Variabel (s.d. 10 MB)', 'File fisik digital PDF dokumen 33 dosir hasil unggahan/scan kamera prajurit'],
    ['6', 'settings', 'Harddisk (MySQL DB)', '280 Byte', 'Pengaturan dinamis identitas aplikasi, brand, watermark, logo, dan batas tahun jabatan'],
    ['7', 'activity_log', 'Harddisk (MySQL DB)', '360 Byte', 'Perekaman log audit seluruh aksi mutasi data, login, approval, dan verifikasi berkas'],
    ['8', 'login_attempts', 'Harddisk (MySQL DB)', '160 Byte', 'Perekaman log percobaan login untuk proteksi keamanan brute-force attack'],
];

foreach ($inputData as $id) {
    $tInput->addRow();
    $tInput->addCell(500)->addText($id[0], $fTableCell, $pCenter);
    $tInput->addCell(2200)->addText($id[1], $fTableCellB, $pLeft);
    $tInput->addCell(2000)->addText($id[2], $fTableCell, $pLeft);
    $tInput->addCell(1500)->addText($id[3], $fTableCell, $pCenter);
    $tInput->addCell(3000)->addText($id[4], $fTableCell, $pLeft);
}

// Sub c. Proses
$mainSection->addTextBreak(1);
$mainSection->addText('c.   Proses Pengolahan.', $fHeading2, $pLeft);
$mainSection->addText('Rangkaian tahapan pengolahan data yang diselenggarakan oleh aplikasi TRISULA TNI AD dijabarkan sebagai berikut :', $fNormal, $pJustify);
$mainSection->addText('     1)   Tahap Registrasi & Approval : Personel mengisi formulir pendaftaran. Sistem memvalidasi format NRP dan mencegah duplikasi data. Rekord personel tersimpan dan akun pengguna otomatis dibuat dengan status "pending". Administrator melakukan telaah dan persetujuan (approval) sehingga akun aktif.', $fNormal, $pJustify);
$mainSection->addText('     2)   Tahap Pengunggahan / Pemindaian Dosir : Personel memilih jenis dosir dari 33 pilihan baku. Berkas diunggah dalam format PDF atau dipindai langsung lewat kamera. Sistem memeriksa ekstensi file, MIME type (application/pdf), serta batas ukuran maksimal (10 MB). File master bersih disimpan pada raw_file_path.', $fNormal, $pJustify);
$mainSection->addText('     3)   Tahap Penamaan File & Struktur Direktori Otomatis : Sistem membentuk nama file baku "{NRP}_{KODE}.pdf". Apabila personel mengunggah lebih dari satu berkas pada jenis dosir yang sama (misalnya sertifikat dikmil lanjutan), sistem secara otomatis memberikan indeks abjad "{NRP}_{KODE}a.pdf", "{NRP}_{KODE}b.pdf", dst., dan menempatkannya ke dalam folder fisik "uploads/FOLDER {kode}/".', $fNormal, $pJustify);
$mainSection->addText('     4)   Tahap Watermarking Pra-Verifikasi : Sebelum berkas disetujui, sistem membubuhkan tanda air visual status "PENDING APPROVAL" dan catatan kaki peninjauan agar dokumen belum memiliki kekuatan hukum sebelum divalidasi.', $fNormal, $pJustify);
$mainSection->addText('     5)   Tahap Verifikasi & Penerbitan TTE : Administrator meneliti keabsahan isi berkas. Jika disetujui (Approved), sistem mengambil berkas master bersih, mengkalkulasi cryptographic hash SHA-256 dokumen, men-generate kode unik TTE resmi ("TTE-TRISULA-YYYYMM-ID-TOKEN"), menyusun tautan QR Code verifikasi publik, serta menyematkan running header dan stempel digital TTE pada halaman terakhir PDF menggunakan pustaka FPDI dan TCPDF.', $fNormal, $pJustify);
$mainSection->addText('     6)   Tahap Analitik & Monitoring : Sistem menghitung rasio kelengkapan berkas personel (Approved / 33 * 100%). Menghitung selisih tahun antara TMT Jabatan dengan tanggal sekarang; jika > 2 tahun, ditandai sebagai kandidat rotasi jabatan. Menghitung tanggal lahir terhadap batas usia pensiun sesuai golongan guna memproyeksikan TMT Pensiun.', $fNormal, $pJustify);
$mainSection->addText('     7)   Tahap Pelaporan & Ekspor : Mengompilasi data dalam format cetak standar militer berkop dinas Staf Personel serta menyediakan layanan pengemasan bulk download ZIP per prajurit.', $fNormal, $pJustify);

// Sub d. Output
$mainSection->addTextBreak(1);
$mainSection->addText('d.   Output Program.', $fHeading2, $pLeft);
$mainSection->addText('Keluaran yang diproduksi oleh program aplikasi TRISULA TNI AD terdiri atas :', $fNormal, $pJustify);
$mainSection->addText('     1)   Keluaran Tampilan Layar (Display) :', $fNormalBold, $pLeft);
$mainSection->addText('          a)   Dashboard Personel : Visualisasi progress bar kelengkapan 33 dosir, status verifikasi tiap berkas (Pending, Approved, Rejected), dan rincian data prajurit.', $fNormal, $pJustify);
$mainSection->addText('          b)   Dashboard Administrator : Statistik rekapitulasi total personel, rata-rata persentase kelengkapan satuan, daftar tunggu approval akun, daftar berkas pending verifikasi, daftar prajurit siap rotasi (> 2 tahun), dan proyeksi pensiun tahun berjalan.', $fNormal, $pJustify);
$mainSection->addText('          c)   Halaman Verifikasi Publik (verify.php) : Tampilan sertifikasi keabsahan dokumen digital saat QR Code dipindai, memuat rincian nama pemilik berkas, jenis dosir, nama dan pangkat pejabat verifikator, waktu pengesahan, dan hash integritas SHA-256.', $fNormal, $pJustify);
$mainSection->addText('     2)   Keluaran Cetakan (Print Out) & Berkas Digital :', $fNormalBold, $pLeft);
$mainSection->addText('          a)   Berkas Dokumen PDF Ber-TTE Resmi : File PDF asli yang telah dibubuhi stempel digital dinas, running header pengesahan, kode registrasi TTE, dan QR Code keabsahan.', $fNormal, $pJustify);
$mainSection->addText('          b)   Laporan Resmi Rekapitulasi Kelengkapan Dosir Satuan siap cetak (Kop Staf Personel & Kolom Tanda Tangan Komandan).', $fNormal, $pJustify);
$mainSection->addText('          c)   Laporan Proyeksi Pensiun Prajurit & PNS TNI AD siap cetak.', $fNormal, $pJustify);
$mainSection->addText('          d)   Laporan Masa Jabatan Personel (> 2 Tahun) siap cetak.', $fNormal, $pJustify);
$mainSection->addText('          e)   Berkas Arsip ZIP Hasil Unduh Massal (Bulk Download) berisi folder-folder terstruktur dosir per prajurit.', $fNormal, $pJustify);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// 11. DIAGRAM ARUS DATA (DFD)
// ---------------------------------------------------------------------
$mainSection->addText('11.  Diagram Arus Data (Data Flow Diagram).', $fHeading1, $pLeft);
$mainSection->addText('Diagram arus data menggambarkan asal sumber perolehan data, proses transformasi komputasi, penyimpanan pada tabel basis data, serta distribusi informasi kepada para pengguna yang berhak.', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

$mainSection->addText('a.   Diagram Konteks (Context Diagram / DFD Level 0).', $fHeading2, $pLeft);
$mainSection->addText('Pada tingkat konteks, sistem TRISULA TNI AD berinteraksi dengan 4 (empat) entitas eksternal :', $fNormal, $pJustify);

$tDfd0 = $mainSection->addTable('MilitaryTable');
$tDfd0->addRow();
$tDfd0->addCell(2200, $cellHeadBg)->addText('Entitas Luar', $fTableHead, $pCenter);
$tDfd0->addCell(3500, $cellHeadBg)->addText('Arus Data Masukan (Input)', $fTableHead, $pCenter);
$tDfd0->addCell(3500, $cellHeadBg)->addText('Arus Data Keluaran (Output)', $fTableHead, $pCenter);

$dfd0Data = [
    ['Personel Prajurit / PNS TNI AD', 'Data diri registrasi, Berkas 33 dosir PDF, Hasil foto scan kamera, Kredensial login, Pembaruan profil', 'Status persetujuan akun, Dashboard progres 33 dosir, Catatan revisi verifikasi, Dokumen PDF sah ber-TTE'],
    ['Administrator Satuan (Spers / Infolahta)', 'Persetujuan akun pengguna, Keputusan verifikasi berkas (Approve/Reject), Catatan verifikasi, Parameter setting sistem', 'Statistik satuan, Berkas antrean verifikasi, Notifikasi masa jabatan > 2 th & pensiun, Laporan resmi cetak, File backup ZIP'],
    ['Pimpinan Satuan / Komandan', 'Permintaan laporan analisis kelengkapan dosir dan perencanaan personel', 'Laporan kelengkapan dosir satuan siap cetak, Daftar nominatif pensiun, Daftar proyeksi mutasi jabatan (TOD/TOA)'],
    ['Publik / Pemeriksa Dokumen', 'Pemindaian QR Code dokumen melalui kamera gawai (HTTP Request GET kode TTE)', 'Informasi sertifikasi digital keabsahan dokumen, Nama personel, Pejabat penandatangan, Waktu pengesahan, Hash SHA-256'],
];

foreach ($dfd0Data as $row) {
    $tDfd0->addRow();
    $tDfd0->addCell(2200)->addText($row[0], $fTableCellB, $pLeft);
    $tDfd0->addCell(3500)->addText($row[1], $fTableCell, $pLeft);
    $tDfd0->addCell(3500)->addText($row[2], $fTableCell, $pLeft);
}

$mainSection->addTextBreak(1);
$mainSection->addText('Bagan Konseptual Aliran Arus Data (DFD Level 0) :', $fNormalItalic, $pLeft);

$tBaganDfd = $mainSection->addTable('BoxTable');
$tBaganDfd->addRow();
$cBoxDfd = $tBaganDfd->addCell(9200, $cellLightGreen);
$cBoxDfd->addText('┌───────────────────────────┐                     ┌───────────────────────────┐', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│      PERSONEL TNI AD      │──(Data Registrasi/──>│                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│  (Prajurit & PNS Militer) │   Unggah 33 Dosir)   │                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│                           │<─(Status/Progress/───│                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('└───────────────────────────┘   Dokumen Ber-TTE)   │       SISTEM UTAMA        │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('                                                   │      TRISULA TNI AD       │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('┌───────────────────────────┐                      │                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│    ADMINISTRATOR PERS     │──(Approval/Verifikasi│  Tata Kelola Rekam        │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│   (Spers / Infolahtad)    │   Parameter Setting)─>  Informasi, Sistematika,  │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│                           │<─(Laporan/Bulk ZIP/──│  & Unduhan Lengkap Arsip  │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('└───────────────────────────┘   Monitoring Satuan) │                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('                                                   │                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('┌───────────────────────────┐                      │                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│   PUBLIK / PEMERIKSA QR   │──(Scan QR Code TTE)─>│                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('│                           │<─(Sertifikasi Sah)───│                           │', $fTableCellCode, $pCenter);
$cBoxDfd->addText('└───────────────────────────┘                     └───────────────────────────┘', $fTableCellCode, $pCenter);

$mainSection->addTextBreak(1);
$mainSection->addText('b.   Diagram Arus Data Rinci (DFD Level 1).', $fHeading2, $pLeft);
$mainSection->addText('Proses utama dipecah menjadi 7 (tujuh) sub-proses fungsional terintegrasi :', $fNormal, $pJustify);
$mainSection->addText('     1)   Proses 1.0 : Autentikasi Pengguna & Sesi Keamanan (Validasi NRP/Password, Rate Limiting, Pengendalian Hak Akses Role).', $fNormal, $pJustify);
$mainSection->addText('     2)   Proses 2.0 : Pengelolaan Pendaftaran & Persetujuan Akun (Pencatatan data personel, validasi NRP unik, otorisasi Admin).', $fNormal, $pJustify);
$mainSection->addText('     3)   Proses 3.0 : Unggah & Pemindaian 33 Dosir (Validasi PDF, pembagian sub-folder fisik, penamaan otomatis indeks abjad).', $fNormal, $pJustify);
$mainSection->addText('     4)   Proses 4.0 : Verifikasi Berkas & Pembubuhan TTE (Pemeriksaan admin, kalkulasi SHA-256, penerbitan QR Code, watermarking).', $fNormal, $pJustify);
$mainSection->addText('     5)   Proses 5.0 : Layanan Verifikasi Dokumen Publik (Pencarian kode TTE, validasi integritas hash, penampilan sertifikat keabsahan).', $fNormal, $pJustify);
$mainSection->addText('     6)   Proses 6.0 : Analitik Monitoring Personel (Perhitungan rasio kelengkapan, deteksi masa jabatan > 2 th, proyeksi pensiun).', $fNormal, $pJustify);
$mainSection->addText('     7)   Proses 7.0 : Pelaporan & Pemeliharaan Sistem (Ekspor print out dinas, kompresi bulk ZIP, pencadangan basis data, audit log).', $fNormal, $pJustify);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// 12. DIAGRAM HUBUNGAN ENTITY (ERD)
// ---------------------------------------------------------------------
$mainSection->addText('12.  Diagram Hubungan Entity (Entity Relationship Diagram - ERD).', $fHeading1, $pLeft);
$mainSection->addText('Diagram hubungan entitas memetakan arsitektur relasional pangkalan data (database) sistem TRISULA TNI AD, yang mengikat entitas personel, akun sistem, berkas dosir, master referensi, serta log audit keamanan.', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

$tErd = $mainSection->addTable('BoxTable');
$tErd->addRow();
$cErd = $tErd->addCell(9200, $cellLightGreen);

$cErd->addText('┌────────────────────────┐          1:1          ┌────────────────────────┐', $fTableCellCode, $pCenter);
$cErd->addText('│        PERSONEL        │◄─────────────────────►│         USERS          │', $fTableCellCode, $pCenter);
$cErd->addText('│────────────────────────│                       │────────────────────────│', $fTableCellCode, $pCenter);
$cErd->addText('│ PK : id                │                       │ PK : id                │', $fTableCellCode, $pCenter);
$cErd->addText('│      nrp (UNIQUE)      │                       │      username (UNIQUE) │', $fTableCellCode, $pCenter);
$cErd->addText('│      nama              │                       │      password (HASH)   │', $fTableCellCode, $pCenter);
$cErd->addText('│      golongan          │                       │      role (admin/pers) │', $fTableCellCode, $pCenter);
$cErd->addText('│      pangkat, korp     │                       │ FK : personel_id (UNIQ)│', $fTableCellCode, $pCenter);
$cErd->addText('│      satuan, jabatan   │                       │      status (approval) │', $fTableCellCode, $pCenter);
$cErd->addText('│      tmt_jabatan       │                       └───────────┬────────────┘', $fTableCellCode, $pCenter);
$cErd->addText('│      tanggal_lahir     │                                   │             ', $fTableCellCode, $pCenter);
$cErd->addText('└───────────┬────────────┘                                   │ 1:N         ', $fTableCellCode, $pCenter);
$cErd->addText('            │                                                +────────────┐', $fTableCellCode, $pCenter);
$cErd->addText('            │ 1:N                                            │            │', $fTableCellCode, $pCenter);
$cErd->addText('            ▼                                                ▼            ▼', $fTableCellCode, $pCenter);
$cErd->addText('┌────────────────────────┐                           ┌──────────────┐ ┌──────────────┐', $fTableCellCode, $pCenter);
$cErd->addText('│      DOSIR_FILES       │                           │ ACTIVITY_LOG │ │  BACKUP_LOG  │', $fTableCellCode, $pCenter);
$cErd->addText('│────────────────────────│                           │──────────────│ │──────────────│', $fTableCellCode, $pCenter);
$cErd->addText('│ PK : id                │                           │ PK : id      │ │ PK : id      │', $fTableCellCode, $pCenter);
$cErd->addText('│ FK : personel_id       │                           │ FK : user_id │ │ FK:created_by│', $fTableCellCode, $pCenter);
$cErd->addText('│ FK : dosir_kode ───────┼───────────┐               │    aktivitas │ │    file_name │', $fTableCellCode, $pCenter);
$cErd->addText('│      abjad             │           │               │    ip_address│ │    size_bytes│', $fTableCellCode, $pCenter);
$cErd->addText('│      file_name         │           │ N:1           │    created_at│ │    created_at│', $fTableCellCode, $pCenter);
$cErd->addText('│      file_path         │           ▼               └──────────────┘ └──────────────┘', $fTableCellCode, $pCenter);
$cErd->addText('│      raw_file_path     │ ┌───────────────────┐     ┌──────────────┐', $fTableCellCode, $pCenter);
$cErd->addText('│      status            │ │   DOSIR_MASTER    │     │LOGIN_ATTEMPTS│', $fTableCellCode, $pCenter);
$cErd->addText('│      signature_code    │ │───────────────────│     │──────────────│', $fTableCellCode, $pCenter);
$cErd->addText('│      signature_hash    │ │ PK : kode (01..33)│     │ PK : id      │', $fTableCellCode, $pCenter);
$cErd->addText('│ FK : uploaded_by ──────┼─┼────────────────────────►│    ip_address│', $fTableCellCode, $pCenter);
$cErd->addText('│ FK : verified_by ──────┼─┼────────────────────────►│    username  │', $fTableCellCode, $pCenter);
$cErd->addText('│      verified_at       │ │      nama_dosir   │     │    attempt   │', $fTableCellCode, $pCenter);
$cErd->addText('└────────────────────────┘ │      urutan, wajib│     └──────────────┘', $fTableCellCode, $pCenter);
$cErd->addText('                           └───────────────────┘                     ', $fTableCellCode, $pCenter);

$mainSection->addTextBreak(1);
$mainSection->addText('Penjelasan Kardinalitas Relasi Antar Tabel :', $fHeading2, $pLeft);
$mainSection->addText('1.   Tabel "personel" berelasi Satu ke Satu (1:1) dengan tabel "users" melalui foreign key `users.personel_id` dengan konstrain UNIQUE. Setiap personel memiliki satu akun login resmi.', $fNormal, $pJustify);
$mainSection->addText('2.   Tabel "personel" berelasi Satu ke Banyak (1:N) dengan tabel "dosir_files" melalui foreign key `dosir_files.personel_id` dengan kaskade ON DELETE CASCADE.', $fNormal, $pJustify);
$mainSection->addText('3.   Tabel "dosir_master" berelasi Satu ke Banyak (1:N) dengan tabel "dosir_files" melalui foreign key `dosir_files.dosir_kode` dengan ON UPDATE CASCADE ON DELETE RESTRICT.', $fNormal, $pJustify);
$mainSection->addText('4.   Tabel "users" berelasi Satu ke Banyak (1:N) dengan tabel "dosir_files" sebagai pengunggah (`dosir_files.uploaded_by`) dan sebagai verifikator TTE (`dosir_files.verified_by`).', $fNormal, $pJustify);
$mainSection->addText('5.   Tabel "users" berelasi Satu ke Banyak (1:N) dengan tabel "activity_log" (`activity_log.user_id`) untuk merekam seluruh tindakan audit trail pengguna.', $fNormal, $pJustify);
$mainSection->addText('6.   Tabel "users" berelasi Satu ke Banyak (1:N) dengan tabel "backup_log" (`backup_log.created_by`) untuk mencatat pembuat cadangan data.', $fNormal, $pJustify);
$mainSection->addText('7.   Tabel "settings" dan "login_attempts" beroperasi sebagai tabel penunjang sistem keamanan dan parameter operasional global.', $fNormal, $pJustify);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// 13. KAMUS DATA / STRUKTUR FILE
// ---------------------------------------------------------------------
$mainSection->addText('13.  Kamus Data / Struktur File.', $fHeading1, $pLeft);
$mainSection->addText('Kamus data memberikan spesifikasi teknis terperinci mengenai struktur tabel pangkalan data (MySQL), meliputi nama medan (field), uraian sebutan, tipe data, lebar/panjang memori, serta keterangan integritas kunci (Primary Key / Foreign Key).', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

// Helper function untuk render kamus data tabel
function renderKamusTable($section, $title, $fields, $fHead, $fCell, $fCellB, $headBg) {
    $section->addText($title, ['name' => 'Times New Roman', 'size' => 11, 'bold' => true], ['spaceAfter' => 40]);
    $t = $section->addTable('MilitaryTable');
    $t->addRow();
    $t->addCell(500, $headBg)->addText('No', $fHead, ['alignment' => Jc::CENTER]);
    $t->addCell(2200, $headBg)->addText('Nama Field', $fHead, ['alignment' => Jc::CENTER]);
    $t->addCell(3000, $headBg)->addText('Uraian / Sebutan', $fHead, ['alignment' => Jc::CENTER]);
    $t->addCell(1300, $headBg)->addText('Type', $fHead, ['alignment' => Jc::CENTER]);
    $t->addCell(1100, $headBg)->addText('Lebar', $fHead, ['alignment' => Jc::CENTER]);
    $t->addCell(1100, $headBg)->addText('Ket', $fHead, ['alignment' => Jc::CENTER]);

    foreach ($fields as $f) {
        $t->addRow();
        $t->addCell(500)->addText($f[0], $fCell, ['alignment' => Jc::CENTER]);
        $t->addCell(2200)->addText($f[1], $fCellB, ['alignment' => Jc::START]);
        $t->addCell(3000)->addText($f[2], $fCell, ['alignment' => Jc::START]);
        $t->addCell(1300)->addText($f[3], $fCell, ['alignment' => Jc::CENTER]);
        $t->addCell(1100)->addText($f[4], $fCell, ['alignment' => Jc::CENTER]);
        $t->addCell(1100)->addText($f[5], $fCell, ['alignment' => Jc::CENTER]);
    }
    $section->addTextBreak(1);
}

// 1. Tabel Personel
$fPersonel = [
    ['1', 'id', 'Nomor Urut Indeks Unik', 'INT', '11', 'PK, Auto'],
    ['2', 'nrp', 'Nomor Registrasi Pokok / NIP', 'VARCHAR', '20', 'UNIQUE'],
    ['3', 'nama', 'Nama Lengkap Prajurit / PNS', 'VARCHAR', '120', 'Not Null'],
    ['4', 'golongan', 'Golongan (Perwira/Bintara/Tamtama/PNS)', 'ENUM', '15', 'Not Null'],
    ['5', 'pangkat', 'Pangkat Terkini', 'VARCHAR', '50', 'Nullable'],
    ['6', 'korp', 'Korps Kecabangan (Czi, Inf, Kav, dll)', 'VARCHAR', '50', 'Nullable'],
    ['7', 'satuan', 'Nama Satuan Kerja Saat Ini', 'VARCHAR', '150', 'Nullable'],
    ['8', 'kotama', 'Komando Utama Pembina (Kodam/Korp)', 'VARCHAR', '150', 'Nullable'],
    ['9', 'jabatan', 'Jabatan Dinas yang Diduduki', 'VARCHAR', '150', 'Nullable'],
    ['10', 'tmt_jabatan', 'TMT Mulai Menjabat', 'DATE', '10', 'Nullable'],
    ['11', 'tmt_pangkat', 'TMT Pangkat Terkini', 'DATE', '10', 'Nullable'],
    ['12', 'tanggal_lahir', 'Tanggal Lahir Prajurit', 'DATE', '10', 'Nullable'],
    ['13', 'tempat_lahir', 'Tempat / Kota Kelahiran', 'VARCHAR', '100', 'Nullable'],
    ['14', 'jenis_kelamin', 'Jenis Kelamin (L / P)', 'ENUM', '1', 'Default L'],
    ['15', 'agama', 'Agama yang Dianut', 'VARCHAR', '30', 'Nullable'],
    ['16', 'status_kawin', 'Status Pernikahan', 'VARCHAR', '30', 'Nullable'],
    ['17', 'alamat', 'Alamat Tempat Tinggal Resmi', 'TEXT', '-', 'Nullable'],
    ['18', 'no_hp', 'Nomor Kontak Telepon / WA', 'VARCHAR', '20', 'Nullable'],
    ['19', 'email', 'Alamat Surel Resmi', 'VARCHAR', '100', 'Nullable'],
    ['20', 'foto', 'Path File Foto Personel', 'VARCHAR', '255', 'Nullable'],
    ['21', 'status_dinas', 'Status Dinas (Aktif/Pensiun/Pindah)', 'ENUM', '15', 'Default Aktif'],
    ['22', 'tmt_pensiun_proyeksi', 'Proyeksi TMT Pensiun Otomatis', 'DATE', '10', 'Nullable'],
    ['23', 'created_at', 'Waktu Perekaman Data Pertama', 'TIMESTAMP', '19', 'Auto Stamp'],
    ['24', 'updated_at', 'Waktu Pembaruan Data Terakhir', 'TIMESTAMP', '19', 'Auto Update'],
];
renderKamusTable($mainSection, 'a.   Struktur File / Tabel: PERSONEL (Data Induk Prajurit & PNS)', $fPersonel, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

// 2. Tabel Users
$fUsers = [
    ['1', 'id', 'Identitas Unik Pengguna', 'INT', '11', 'PK, Auto'],
    ['2', 'username', 'Nama Pengguna Login (NRP / Admin)', 'VARCHAR', '50', 'UNIQUE'],
    ['3', 'password', 'Kata Sandi Terenkripsi (bcrypt)', 'VARCHAR', '255', 'Not Null'],
    ['4', 'role', 'Peran Otoritas (admin / personel)', 'ENUM', '10', 'Not Null'],
    ['5', 'personel_id', 'Relasi ke Tabel Personel', 'INT', '11', 'FK, UNIQUE'],
    ['6', 'status', 'Status Akun (pending/approved)', 'ENUM', '15', 'Def \'pending\''],
    ['7', 'catatan_approval', 'Catatan Evaluasi Admin', 'TEXT', '-', 'Nullable'],
    ['8', 'last_login', 'Waktu Akses Terakhir', 'DATETIME', '19', 'Nullable'],
    ['9', 'theme_pref', 'Preferensi Tema (dark/light)', 'ENUM', '5', 'Def \'dark\''],
    ['10', 'created_at', 'Waktu Pendaftaran Akun', 'TIMESTAMP', '19', 'Auto Stamp'],
];
renderKamusTable($mainSection, 'b.   Struktur File / Tabel: USERS (Kredensial dan Otorisasi Akun)', $fUsers, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

$mainSection->addPageBreak();

// 3. Tabel Dosir Master
$fMaster = [
    ['1', 'kode', 'Kode Baku 2 Digit Dosir (\'01\'..\'33\')', 'VARCHAR', '2', 'PK'],
    ['2', 'nama_dosir', 'Nomenklatur Resmi Jenis Dokumen', 'VARCHAR', '180', 'Not Null'],
    ['3', 'urutan', 'Urutan Penyusunan Berkas (1 s.d. 33)', 'INT', '3', 'Not Null'],
    ['4', 'wajib', 'Status Wajib Administrasi (1 = Ya)', 'TINYINT', '1', 'Default 1'],
];
renderKamusTable($mainSection, 'c.   Struktur File / Tabel: DOSIR_MASTER (Master 33 Jenis Dosir Baku TNI AD)', $fMaster, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

// 4. Tabel Dosir Files
$fFiles = [
    ['1', 'id', 'Nomor Unik Berkas Dosir', 'INT', '11', 'PK, Auto'],
    ['2', 'personel_id', 'Pemilik Berkas (Relasi ke Personel)', 'INT', '11', 'FK, Not Null'],
    ['3', 'dosir_kode', 'Kode Jenis Dosir (Relasi Master)', 'VARCHAR', '2', 'FK, Not Null'],
    ['4', 'abjad', 'Indeks Berkas Lanjutan (NULL, \'a\', \'b\')', 'VARCHAR', '1', 'Nullable'],
    ['5', 'file_name', 'Nama File Fisik (NRP_KODE[abjad].pdf)', 'VARCHAR', '150', 'Not Null'],
    ['6', 'file_path', 'Path File Aktif Terverifikasi TTE', 'VARCHAR', '255', 'Not Null'],
    ['7', 'raw_file_path', 'Path File Master Asli Bersih', 'VARCHAR', '255', 'Nullable'],
    ['8', 'original_name', 'Nama Asli Berkas Saat Diunggah', 'VARCHAR', '255', 'Nullable'],
    ['9', 'keterangan', 'Keterangan Tambahan dari Personel', 'VARCHAR', '255', 'Nullable'],
    ['10', 'status', 'Status Verifikasi (pending/approved/rejected)', 'ENUM', '10', 'INDEX'],
    ['11', 'catatan_verifikasi', 'Catatan Evaluasi Verifikator', 'TEXT', '-', 'Nullable'],
    ['12', 'is_watermarked', 'Penanda Status Watermark Sementara', 'TINYINT', '1', 'Default 0'],
    ['13', 'signature_code', 'Kode Registrasi TTE (TTE-TRISULA-...)', 'VARCHAR', '60', 'INDEX'],
    ['14', 'signature_hash', 'Enkripsi Hash SHA-256 Integritas Berkas', 'VARCHAR', '64', 'Nullable'],
    ['15', 'uploaded_by', 'Relasi ke Akun Pengunggah Berkas', 'INT', '11', 'FK, Nullable'],
    ['16', 'uploaded_at', 'Waktu Berkas Diunggah', 'TIMESTAMP', '19', 'Auto Stamp'],
    ['17', 'verified_by', 'Relasi ke Akun Verifikator TTE', 'INT', '11', 'FK, Nullable'],
    ['18', 'verified_at', 'Waktu Pengesahan TTE Resmi', 'DATETIME', '19', 'Nullable'],
];
renderKamusTable($mainSection, 'd.   Struktur File / Tabel: DOSIR_FILES (Metadata Arsip & Sertifikasi TTE)', $fFiles, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

// 5. Tabel Settings
$fSettings = [
    ['1', 'setting_key', 'Kata Kunci Pengaturan Parameter', 'VARCHAR', '50', 'PK'],
    ['2', 'setting_value', 'Nilai Parameter Pengaturan', 'TEXT', '-', 'Nullable'],
    ['3', 'setting_group', 'Kelompok Setting (general / dosir)', 'VARCHAR', '50', 'Default general'],
    ['4', 'updated_at', 'Waktu Pembaruan Terakhir', 'TIMESTAMP', '19', 'Auto Update'],
];
renderKamusTable($mainSection, 'e.   Struktur File / Tabel: SETTINGS (Parameter Operasional & Brand Aplikasi)', $fSettings, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

// 6. Tabel Activity Log
$fActivity = [
    ['1', 'id', 'Nomor Unik Catatan Audit', 'INT', '11', 'PK, Auto'],
    ['2', 'user_id', 'Relasi ke Akun Pelaku Aksi', 'INT', '11', 'FK, Nullable'],
    ['3', 'aktivitas', 'Nama Tindakan (LOGIN, VERIFY, DLL)', 'VARCHAR', '150', 'INDEX'],
    ['4', 'keterangan', 'Rincian Catatan / Konteks Aksi', 'TEXT', '-', 'Nullable'],
    ['5', 'ip_address', 'Alamat IP Komputer Pengakses', 'VARCHAR', '45', 'Nullable'],
    ['6', 'created_at', 'Waktu Kejadian Aktivitas', 'TIMESTAMP', '19', 'INDEX'],
];
renderKamusTable($mainSection, 'f.   Struktur File / Tabel: ACTIVITY_LOG (Jejak Rekam / Audit Trail Sistem)', $fActivity, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

// 7. Tabel Backup Log
$fBackup = [
    ['1', 'id', 'Nomor Unik Log Backup', 'INT', '11', 'PK, Auto'],
    ['2', 'file_name', 'Nama Berkas Cadangan (.sql/.zip)', 'VARCHAR', '255', 'Not Null'],
    ['3', 'size_bytes', 'Ukuran Berkas Cadangan (Byte)', 'BIGINT', '20', 'Default 0'],
    ['4', 'jenis', 'Jenis (database/files/full)', 'ENUM', '10', 'Def \'full\''],
    ['5', 'created_by', 'Relasi ke Akun Pembuat Backup', 'INT', '11', 'FK, Nullable'],
    ['6', 'created_at', 'Waktu Pencadangan', 'TIMESTAMP', '19', 'Auto Stamp'],
];
renderKamusTable($mainSection, 'g.   Struktur File / Tabel: BACKUP_LOG (Riwayat Pencadangan Pangkalan Data)', $fBackup, $fTableHead, $fTableCell, $fTableCellB, $cellHeadBg);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// 14. SPESIFIKASI PROGRAM (GAMBAR-3 DISINFOLAHTAD)
// ---------------------------------------------------------------------
$mainSection->addText('14.  Spesifikasi Program.', $fHeading1, $pLeft);
$mainSection->addText('Spesifikasi program memuat rincian logika, masukan, form, lingkungan bahasa, serta urutan prosedur pengolahan dari masing-masing modul aplikasi TRISULA TNI AD berdasarkan standar Naskah Sekolah Gambar - 3.', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

// Helper function untuk Spesifikasi Program
function renderSpecProgram($section, $sistem, $namaProg, $namaForm, $fungsiProg, $bahasa, $pembuat, $prosedur, $fHead, $fCell, $fCellB) {
    $t = $section->addTable('BoxTable');
    
    // Header Bagian Atas
    $t->addRow();
    $c1 = $t->addCell(4600);
    $c1->addText('SISTEM : ' . $sistem, $fCellB);
    $c2 = $t->addCell(4600);
    $c2->addText('NAMA PROGRAM : ' . $namaProg, $fCellB);
    $c2->addText('NAMA FORM    : ' . $namaForm, $fCellB);

    // Header Bagian Tengah
    $t->addRow();
    $c3 = $t->addCell(4600);
    $c3->addText('FUNGSI PROGRAM :', $fCellB);
    $c3->addText($fungsiProg, $fCell);
    $c4 = $t->addCell(4600);
    $c4->addText('BAHASA     : ' . $bahasa, $fCellB);
    $c4->addText('DIBUAT OLEH: ' . $pembuat, $fCellB);

    // Bagian Prosedur Pengolahan
    $t->addRow();
    $c5 = $t->addCell(9200, ['gridSpan' => 2]);
    $c5->addText('PROSEDUR PENGOLAHAN :', $fCellB, ['spaceAfter' => 40]);
    foreach ($prosedur as $p) {
        $c5->addText('• ' . $p, $fCell, ['spaceAfter' => 20]);
    }
    
    $section->addTextBreak(1);
}

// 1. Modul Autentikasi & Login
$pLogin = [
    'Menerima masukan Username (NRP / Admin) dan Kata Sandi via POST Request.',
    'Memeriksa token keamanan Cross-Site Request Forgery (CSRF).',
    'Memeriksa tabel login_attempts guna mendeteksi serangan brute force (maksimal 5x gagal dalam 15 menit).',
    'Melakukan pencocokan akun pada tabel users berdasarkan username yang aktif.',
    'Memverifikasi hash kata sandi menggunakan fungsi password_verify() (algoritma bcrypt).',
    'Memeriksa status akun; apabila berstatus "pending" atau "rejected", sistem menolak login dan menampilkan pesan edukatif.',
    'Jika otentikasi berhasil: regenerasi session_id() baru, menetapkan variabel $_SESSION user, memperbarui last_login, dan mencatat log audit pada activity_log.',
    'Mengarahkan (redirect) pengguna sesuai perannya: role admin ke /admin/dashboard.php, role personel ke /personel/dashboard.php.',
];
renderSpecProgram(
    $mainSection,
    'APLIKASI TRISULA TNI AD',
    'AUTH_LOGIN.PHP',
    'FO_LOGIN_USER',
    'Autentikasi Hak Akses, Proteksi Serangan Brute Force, & Pengendalian Sesi Sesuai Role Pengguna',
    'PHP 8.3 PDO + BCrypt',
    'Letda Czi Fris Wardani',
    $pLogin,
    $fTableHead,
    $fTableCell,
    $fTableCellB
);

// 2. Modul Registrasi Personel
$pRegister = [
    'Menerima data masukan pendaftaran: NRP, Nama Lengkap, Golongan, Pangkat, Korps, Satuan, Kotama, Jabatan, TMT Jabatan, Tgl Lahir, dan Jenis Kelamin.',
    'Validasi format regex NRP: hanya memperbolehkan kombinasi alfanumerik (4-30 karakter).',
    'Memeriksa tabel personel dan tabel users untuk memastikan NRP belum pernah terdaftar sebelumnya.',
    'Membuka transaksi basis data (PDO beginTransaction).',
    'Menyimpan data identitas induk prajurit ke dalam tabel personel.',
    'Membuatkan akun login otomatis pada tabel users dengan ketentuan: username = NRP, password = hash(NRP), status = "pending", role = "personel".',
    'Commit transaksi basis data, mencatat log pendaftaran pada activity_log, dan menampilkan notifikasi sukses registrasi menunggu approval admin.',
];
renderSpecProgram(
    $mainSection,
    'APLIKASI TRISULA TNI AD',
    'REGISTER_PROCESS.PHP',
    'FO_REGISTRASI_PERSONEL',
    'Pendaftaran Mandiri Prajurit, Pembuatan Akun Otomatis Berbasis NRP, & Validasi Integritas Data',
    'PHP 8.3 PDO + MySQL Transaction',
    'Letda Czi Fris Wardani',
    $pRegister,
    $fTableHead,
    $fTableCell,
    $fTableCellB
);

$mainSection->addPageBreak();

// 3. Modul Unggah & Scan Dosir
$pUpload = [
    'Personel memilih kode jenis dosir (01 s.d. 33) yang akan diunggah.',
    'Menerima masukan berkas PDF baik melalui pengunggahan file konvensional maupun konversi hasil scan kamera (jsPDF client-side).',
    'Validasi file: ekstensi wajib .pdf, validasi tipe MIME via finfo_file(), dan batas ukuran maks 10 MB.',
    'Memeriksa apakah berkas pada slot dosir tersebut sudah ada pada tabel dosir_files.',
    'Jika berkas pertama: abjad bernilai NULL, nama file = "{NRP}_{KODE}.pdf".',
    'Jika berkas kedua dst: sistem menghitung urutan abjad otomatis (\'a\', \'b\', \'c\', ...) dan membentuk nama file "{NRP}_{KODE}{abjad}.pdf".',
    'Memastikan direktori tujuan uploads/FOLDER {kode}/ telah tercipta (jika belum, dibuat otomatis dengan izin 0755).',
    'Memindahkan berkas fisik ke direktori tujuan dan menyimpan berkas master bersih ke raw_file_path.',
    'Membubuhkan tanda air sementara (Pending Approval) dan mencatat metadata pada tabel dosir_files dengan status "pending".',
];
renderSpecProgram(
    $mainSection,
    'APLIKASI TRISULA TNI AD',
    'DOSIR_UPLOAD.PHP',
    'FO_UNGGAH_SCAN_DOSIR',
    'Digitalisasi 33 Dosir Baku, Penamaan File Otomatis Indeks Abjad, & Penataan Folder Fisik Terisolasi',
    'PHP 8.3 PDO + jsPDF Library',
    'Letda Czi Fris Wardani',
    $pUpload,
    $fTableHead,
    $fTableCell,
    $fTableCellB
);

// 4. Modul Verifikasi & Sertifikasi TTE
$pVerify = [
    'Administrator membuka antrean berkas dosir pending pada halaman dosir_verify.php.',
    'Admin meninjau kelayakan dan keaslian fisik warkat digital melalui pratinjau dokumen terintegrasi.',
    'Jika Admin memilih opsi REJECT: status berkas diubah menjadi "rejected", catatan evaluasi verifikasi disimpan, dan personel diberikan akses unggah ulang berkas perbaikan.',
    'Jika Admin memilih opsi APPROVE: sistem memproses sertifikasi digital resmi :',
    '   a) Mengambil berkas master asli bersih dari kolom raw_file_path (menghilangkan watermark pending secara permanen).',
    '   b) Menghitung nilai cryptographic checksum SHA-256 berkas master sebagai segel integritas anti-pemalsuan.',
    '   c) Meng-generate kode unik TTE resmi: "TTE-TRISULA-{YYYYMM}-{ID}-{TOKEN6}".',
    '   d) Membentuk URL verifikasi publik ber-QR Code: "https://domain/verify.php?code=...".',
    '   e) Mengambil data profil pejabat penandatangan (Nama Admin, Pangkat, NRP, Satuan).',
    '   f) Membuka berkas menggunakan pustaka FPDI + TCPDF; menyematkan Running Line Otentikasi pada seluruh halaman dan membubuhkan Kotak Stempel TTE Resmi ber-QR Code pada sudut kanan bawah halaman terakhir.',
    '   g) Menyimpan berkas hasil TTE, memperbarui status dosir_files menjadi "approved", mengisi signature_code dan signature_hash, serta mencatat log audit pada activity_log.',
];
renderSpecProgram(
    $mainSection,
    'APLIKASI TRISULA TNI AD',
    'DOSIR_VERIFY_PROCESS.PHP',
    'FO_VERIFIKASI_TTE_DOSIR',
    'Verifikasi Berkas, Perhitungan Checksum SHA-256, & Penerbitan Sertifikasi TTE Resmi Ber-QR Code',
    'PHP 8.3 PDO + FPDI + TCPDF',
    'Letda Czi Fris Wardani',
    $pVerify,
    $fTableHead,
    $fTableCell,
    $fTableCellB
);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// 15. BENTUK TAMPILAN DAN CETAKAN
// ---------------------------------------------------------------------
$mainSection->addText('15.  Bentuk Tampilan Dan Cetakan.', $fHeading1, $pLeft);
$mainSection->addText('Bentuk tampilan dan cetakan memuat rancangan antarmuka visual pada layar monitor (display) serta tata letak dokumen keluaran siap cetak melalui printer berformat kedinasan TNI Angkatan Darat.', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

$mainSection->addText('a.   Bentuk Tampilan Layar (Display Interfaces).', $fHeading2, $pLeft);

// Layout 1: Login
$mainSection->addText('1)   Bentuk Tampilan Antarmuka Halaman Masuk (Login Display) :', $fNormalBold, $pLeft);
$tLayLogin = $mainSection->addTable('BoxTable');
$tLayLogin->addRow();
$cL1 = $tLayLogin->addCell(9200, $cellLightGreen);
$cL1->addText('┌────────────────────────────────────────────────────────────────────────┐', $fTableCellCode, $pCenter);
$cL1->addText('│                               ★ ★ ★                                    │', $fTableCellCode, $pCenter);
$cL1->addText('│                           TRISULA TNI AD                               │', $fTableCellCode, $pCenter);
$cL1->addText('│     Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip  │', $fTableCellCode, $pCenter);
$cL1->addText('├────────────────────────────────────────────────────────────────────────┤', $fTableCellCode, $pCenter);
$cL1->addText('│  Username / NRP   : [                                                ] │', $fTableCellCode, $pCenter);
$cL1->addText('│  Kata Sandi       : [                                                ] │', $fTableCellCode, $pCenter);
$cL1->addText('│                     [          TOMBOL MASUK SISTEM         ]           │', $fTableCellCode, $pCenter);
$cL1->addText('│                                                                        │', $fTableCellCode, $pCenter);
$cL1->addText('│  Belum memiliki akun? Registrasi Personel Baru                         │', $fTableCellCode, $pCenter);
$cL1->addText('└────────────────────────────────────────────────────────────────────────┘', $fTableCellCode, $pCenter);

$mainSection->addTextBreak(1);

// Layout 2: Dashboard Personel
$mainSection->addText('2)   Bentuk Tampilan Dashboard Monitoring 33 Dosir Personel :', $fNormalBold, $pLeft);
$tLayDash = $mainSection->addTable('BoxTable');
$tLayDash->addRow();
$cL2 = $tLayDash->addCell(9200, $cellLightGreen);
$cL2->addText('┌────────────────────────────────────────────────────────────────────────┐', $fTableCellCode, $pCenter);
$cL2->addText('│ TRISULA TNI AD │ Dashboard | Unggah Dosir | Scan Kamera | Profil Saya   │', $fTableCellCode, $pCenter);
$cL2->addText('├────────────────────────────────────────────────────────────────────────┤', $fTableCellCode, $pCenter);
$cL2->addText('│ PROFIL PRAJURIT: Letda Czi Fris Wardani | NRP 2118... | Denzibang...   │', $fTableCellCode, $pCenter);
$cL2->addText('│ STATUS KELENGKAPAN DOSIR : [██████████████████░░░░░░░░░░] 64% (21/33)  │', $fTableCellCode, $pCenter);
$cL2->addText('├──────┬───────────────────────────────┬──────────────┬──────────────────┤', $fTableCellCode, $pCenter);
$cL2->addText('│ KODE │ NAMA JENIS DOSIR (33 BAKU)    │ STATUS       │ AKSI DOKUMEN     │', $fTableCellCode, $pCenter);
$cL2->addText('├──────┼───────────────────────────────┼──────────────┼──────────────────┤', $fTableCellCode, $pCenter);
$cL2->addText('│  01  │ SURAT LAMARAN                 │ APPROVED     │ [Lihat PDF TTE]  │', $fTableCellCode, $pCenter);
$cL2->addText('│  02  │ AKTE KELAHIRAN YBS            │ APPROVED     │ [Lihat PDF TTE]  │', $fTableCellCode, $pCenter);
$cL2->addText('│  05  │ IJASAH STTB (DIKUM)           │ PENDING      │ [Menunggu Admin] │', $fTableCellCode, $pCenter);
$cL2->addText('│  16  │ IJASAH DIKMIL (DIKPAPROG)     │ KOSONG       │ [Unggah / Scan]  │', $fTableCellCode, $pCenter);
$cL2->addText('└──────┴───────────────────────────────┴──────────────┴──────────────────┘', $fTableCellCode, $pCenter);

$mainSection->addTextBreak(1);

// Layout 3: Verifikasi Publik QR
$mainSection->addText('3)   Bentuk Tampilan Verifikasi Keabsahan TTE Publik (verify.php) :', $fNormalBold, $pLeft);
$tLayVer = $mainSection->addTable('BoxTable');
$tLayVer->addRow();
$cL3 = $tLayVer->addCell(9200, $cellLightGreen);
$cL3->addText('┌────────────────────────────────────────────────────────────────────────┐', $fTableCellCode, $pCenter);
$cL3->addText('│              ✓ DOKUMEN SAH & TERVERIFIKASI SECARA ELEKTRONIK           │', $fTableCellCode, $pCenter);
$cL3->addText('│                  KODE REGISTRASI: TTE-TRISULA-202609-12-8A9F           │', $fTableCellCode, $pCenter);
$cL3->addText('├────────────────────────────────────────────────────────────────────────┤', $fTableCellCode, $pCenter);
$cL3->addText('│ Jenis Dosir    : DOSIR 16 — IJASAH DIKMIL / SAR / TUK / CAB            │', $fTableCellCode, $pCenter);
$cL3->addText('│ Nama Personel  : Letda Czi Fris Wardani (NRP 2118...)                  │', $fTableCellCode, $pCenter);
$cL3->addText('│ Pangkat/Satuan : Letda Czi / Denzibang 3/I Jaya                        │', $fTableCellCode, $pCenter);
$cL3->addText('│ Pejabat TTE    : Mayor Inf Hendra Pratama (Kasi Pers)                  │', $fTableCellCode, $pCenter);
$cL3->addText('│ Waktu Pengesahan: 21 September 2026 14:15:30 WIB                       │', $fTableCellCode, $pCenter);
$cL3->addText('│ Integritas Dok : SHA256: 7d8f6b2c9a1e4d3f5a8b7c9e0d1f2a3b4c5d6e7f8a9b │', $fTableCellCode, $pCenter);
$cL3->addText('│ Jaminan Mutu   : Dokumen terdaftar sah dalam pangkalan data TRISULA.   │', $fTableCellCode, $pCenter);
$cL3->addText('└────────────────────────────────────────────────────────────────────────┘', $fTableCellCode, $pCenter);

$mainSection->addPageBreak();

$mainSection->addText('b.   Bentuk Cetakan Laporan Kedinasan (Printed Reports).', $fHeading2, $pLeft);
$mainSection->addText('Format laporan kedinasan dirancang dengan kepatuhan penuh terhadap Buku Petunjuk Teknis Tulisan Dinas TNI Angkatan Darat, memuat KOP Staf Personel, Nomor Register Rahasia, Isi Rekapitulasi Data, Tempat Tanggal Pembuatan, serta Tajuk Tanda Tangan Perwira Penanggung Jawab :', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

$tPrint = $mainSection->addTable('BoxTable');
$tPrint->addRow();
$cP = $tPrint->addCell(9200);

$cP->addText('MARKAS BESAR TNI ANGKATAN DARAT', $fTableCellB, $pLeft);
$cP->addText('STAF PERSONEL', $fTableCellB, $pLeft);
$cP->addText('─────────────────────────────────', $fTableCellCode, $pLeft);
$cP->addTextBreak(1);

$cP->addText('LAPORAN REKAPITULASI KELENGKAPAN DOSIR DIGITAL PRAJURIT', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true], $pCenter);
$cP->addText('SISTEM TRISULA TNI ANGKATAN DARAT', ['name' => 'Times New Roman', 'size' => 10, 'bold' => true], $pCenter);
$cP->addText('Periode : Triwulan III Tahun Anggaran 2026', ['name' => 'Times New Roman', 'size' => 9.5, 'italic' => true], $pCenter);
$cP->addTextBreak(1);

$cP->addText('┌────┬──────────────┬────────────────────────┬─────────┬───────────┬─────────┬────────────┐', $fTableCellCode, $pCenter);
$cP->addText('│ NO │     NRP      │ NAMA PRAJURIT / PNS    │ PANGKAT │  SATUAN   │ LENGKAP │ PERSENTASE │', $fTableCellCode, $pCenter);
$cP->addText('├────┼──────────────┼────────────────────────┼─────────┼───────────┼─────────┼────────────┤', $fTableCellCode, $pCenter);
$cP->addText('│ 1. │ 211801234509 │ Letda Czi Fris Wardani │ Letda   │ Denzibang │  33/33  │   100 %    │', $fTableCellCode, $pCenter);
$cP->addText('│ 2. │ 211902345610 │ Lettu Inf Danu Wijaya  │ Lettu   │ Yonif 201 │  31/33  │    94 %    │', $fTableCellCode, $pCenter);
$cP->addText('│ 3. │ 310503456711 │ Serma Agus Priyanto    │ Serma   │ Kodim 0501│  28/33  │    85 %    │', $fTableCellCode, $pCenter);
$cP->addText('│ 4. │ 311204567812 │ Koptu Bambang Sutrisno │ Koptu   │ Brigif 1  │  25/33  │    76 %    │', $fTableCellCode, $pCenter);
$cP->addText('└────┴──────────────┴────────────────────────┴─────────┴───────────┴─────────┴────────────┘', $fTableCellCode, $pCenter);

$cP->addTextBreak(1);

$cP->addText('                                                    Jakarta,   21 September 2026', $fTableCell, $pRight);
$cP->addText('                                                    Perwira Personel / Verifikator,', $fTableCell, $pRight);
$cP->addTextBreak(2);
$cP->addText('                                                    HENDRA PRATAMA, S.I.P.        ', $fTableCellB, $pRight);
$cP->addText('                                                    MAYOR INF NRP 11040023450682  ', $fTableCell, $pRight);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// 16. LISTING PROGRAM
// ---------------------------------------------------------------------
$mainSection->addText('16.  Listing Program.', $fHeading1, $pLeft);
$mainSection->addText('Listing program menyajikan kode sumber inti (core source code) yang merepresentasikan logika algoritma otentikasi TTE, pembubuhan watermark keabsahan ber-QR Code, penataan berkas 33 dosir baku, serta rumus proyeksi masa pensiun dan rotasi jabatan.', $fNormal, $pJustify);
$mainSection->addTextBreak(1);

// Listing 1: TTE & Watermark
$mainSection->addText('a.   Listing Modul Pembubuhan Tanda Tangan Elektronik (TTE) & QR Code (includes/watermark.php) :', $fNormalBold, $pLeft);
$tCode1 = $mainSection->addTable('BoxTable');
$tCode1->addRow();
$cCode1 = $tCode1->addCell(9200, $cellLightGreen);

$codeSnippet1 = <<<CODE
function apply_digital_signature_pdf(\$rawMasterPath, \$outputPath, array \$signData) {
    if (!class_exists('\\setasign\\Fpdi\\Tcpdf\\Fpdi')) return false;
    \$source = file_exists(\$rawMasterPath) ? \$rawMasterPath : \$outputPath;
    if (!file_exists(\$source)) return false;

    try {
        \$pdf = new \\setasign\\Fpdi\\Tcpdf\\Fpdi();
        \$pdf->setPrintHeader(false); \$pdf->setPrintFooter(false);
        \$pdf->SetMargins(0, 0, 0); \$pdf->SetAutoPageBreak(false, 0);

        \$pageCount = \$pdf->setSourceFile(\$source);
        \$code      = \$signData['code'];
        \$verifyUrl = \$signData['verify_url'];
        \$signDate  = \$signData['date'] ?? date('d-m-Y H:i:s');
        \$docHash   = \$signData['hash'] ?? hash_file('sha256', \$source);
        \$shortHash = substr(\$docHash, 0, 16) . '...' . substr(\$docHash, -8);

        for (\$i = 1; \$i <= \$pageCount; \$i++) {
            \$tplId = \$pdf->importPage(\$i);
            \$size  = \$pdf->getTemplateSize(\$tplId);
            \$pdf->AddPage(\$size['width'] > \$size['height'] ? 'L' : 'P', [\$size['width'], \$size['height']]);
            \$pdf->useTemplate(\$tplId);
            \$w = \$size['width']; \$h = \$size['height'];

            // 1. Running Header Otentikasi Pada Seluruh Halaman
            \$pdf->SetAlpha(1.0); \$pdf->SetFont('helvetica', '', 6.5); \$pdf->SetTextColor(70, 95, 75);
            \$line = "★ TRISULA TNI AD — DITANDATANGANI SECARA ELEKTRONIK (TTE) | KODE: \$code | HASH: \$shortHash | \$signDate WIB";
            \$pdf->SetXY(6, \$h - 6.5); \$pdf->Cell(\$w - 12, 4, \$line, 0, 0, 'L');

            // 2. Kotak Pengesahan TTE Resmi Ber-QR Code Pada Halaman Terakhir
            if (\$i === \$pageCount) {
                \$boxW = 88; \$boxH = 38; \$boxX = \$w - \$boxW - 8; \$boxY = \$h - \$boxH - 9;
                \$pdf->SetFillColor(255, 255, 255); \$pdf->SetDrawColor(46, 85, 52);
                \$pdf->Rect(\$boxX, \$boxY, \$boxW, \$boxH, 'DF');

                // Render Barcode QR Code Dinamis
                \$styleQr = ['border' => 0, 'padding' => 1, 'fgcolor' => [30, 60, 35], 'bgcolor' => [255, 255, 255]];
                \$pdf->write2DBarcode(\$verifyUrl, 'QRCODE,L', \$boxX + 2.5, \$boxY + 4, 25, 25, \$styleQr, 'N');

                // Rincian Pejabat Penandatangan
                \$pdf->SetFont('helvetica', 'B', 7); \$pdf->SetTextColor(35, 70, 40);
                \$pdf->SetXY(\$boxX + 29, \$boxY + 3.5);
                \$pdf->Cell(56, 3.5, 'DITANDATANGANI SECARA ELEKTRONIK', 0, 1, 'L');
                \$pdf->SetFont('helvetica', '', 6.2);
                \$pdf->SetXY(\$boxX + 29, \$boxY + 16.8);
                \$pdf->Cell(56, 3.2, \$signData['pangkat_admin'] . ' NRP ' . \$signData['nrp_admin'], 0, 1, 'L');
            }
        }
        \$tmp = \$outputPath . '.tmp';
        \$pdf->Output(\$tmp, 'F');
        if (file_exists(\$tmp)) { rename(\$tmp, \$outputPath); return true; }
        return false;
    } catch (\\Throwable \$e) { error_log(\$e->getMessage()); return false; }
}
CODE;

$cCode1->addText($codeSnippet1, $fCodeBlock);

$mainSection->addPageBreak();

// Listing 2: Proyeksi Pensiun & Deteksi Jabatan
$mainSection->addText('b.   Listing Perhitungan Proyeksi Batas Usia Pensiun & Rotasi Jabatan (includes/functions.php) :', $fNormalBold, $pLeft);
$tCode2 = $mainSection->addTable('BoxTable');
$tCode2->addRow();
$cCode2 = $tCode2->addCell(9200, $cellLightGreen);

$codeSnippet2 = <<<CODE
/**
 * Menghitung Proyeksi Tanggal Pensiun Berdasarkan Batas Usia Pensiun (BUP) Golongan
 * Aturan BUP TNI AD: Perwira = 58 Th, Bintara/Tamtama = 56 Th, PNS = 60 Th
 */
function hitung_proyeksi_pensiun(?string \$tanggalLahir, string \$golongan): ?string {
    if (!\$tanggalLahir) return null;
    \$usiaPensiun = USIA_PENSIUN[\$golongan] ?? 56;
    try {
        \$tgl = new DateTime(\$tanggalLahir);
        \$tgl->modify("+{\$usiaPensiun} years");
        // TMT Pensiun efektif berlaku pada hari pertama bulan berikutnya
        \$tgl->modify('first day of next month');
        return \$tgl->format('Y-m-d');
    } catch (Exception \$e) { return null; }
}

/**
 * Deteksi Prajurit yang Menduduki Jabatan Lebih dari Batas Tertentu (Baku: 2 Tahun)
 * Mendukung Perencanaan Mutasi / Tour of Duty & Tour of Area (TOD/TOA)
 */
function is_jabatan_melebihi_batas(?string \$tmtJabatan, int \$batasTahun = 2): bool {
    if (!\$tmtJabatan) return false;
    try {
        \$tmt = new DateTime(\$tmtJabatan);
        \$sekarang = new DateTime();
        \$interval = \$tmt->diff(\$sekarang);
        return (\$interval->y >= \$batasTahun && !\$interval->invert);
    } catch (Exception \$e) { return false; }
}
CODE;

$cCode2->addText($codeSnippet2, $fCodeBlock);

$mainSection->addTextBreak(1);

// Listing 3: Penamaan 33 Dosir Baku
$mainSection->addText('c.   Listing Logika Standarisasi Penamaan File 33 Dosir (personel/upload_process.php) :', $fNormalBold, $pLeft);
$tCode3 = $mainSection->addTable('BoxTable');
$tCode3->addRow();
$cCode3 = $tCode3->addCell(9200, $cellLightGreen);

$codeSnippet3 = <<<CODE
// Standarisasi Penamaan File Otomatis: {NRP}_{KODE}[abjad].pdf
// Folder Fisik Terisolasi: uploads/FOLDER {kode}/ & uploads/raw/FOLDER {kode}/
\$folder = folder_dosir(\$kode);
\$abjad = next_abjad_slot(\$pdo, \$personel_id, \$kode);
\$fileName = build_dosir_filename(\$nrp, \$kode, \$abjad);

// 1. Simpan Berkas Master Bersih di uploads/raw/
\$rawDir = UPLOAD_DIR . '/raw/' . \$folder;
ensure_dir(\$rawDir);
\$rawPath = \$rawDir . '/' . \$fileName;
move_uploaded_file(\$file['tmp_name'], \$rawPath);
\$rawHash = hash_file('sha256', \$rawPath);

// 2. Buat Berkas Aktif dengan Watermark Sementara
\$activeDir = UPLOAD_DIR . '/' . \$folder;
ensure_dir(\$activeDir);
\$activePath = \$activeDir . '/' . \$fileName;
apply_unverified_watermark(\$rawPath, \$activePath, \$metaLabel, \$wmText);

\$activeRelPath = \$folder . '/' . \$fileName;
\$rawRelPath    = 'raw/' . \$folder . '/' . \$fileName;
CODE;

$cCode3->addText($codeSnippet3, $fCodeBlock);

$mainSection->addPageBreak();

// ---------------------------------------------------------------------
// BAB IV PENUTUP
// ---------------------------------------------------------------------
$mainSection->addText('BAB IV', $fBabTitle, $pCenter);
$mainSection->addText('PENUTUP', $fBabTitle, $pCenter);
$mainSection->addTextBreak(1);

$mainSection->addText('17.  Penutup.', $fHeading1, $pLeft);
$mainSection->addText('a.   Kesimpulan. Pembangunan sistem aplikasi TRISULA TNI AD (Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip) berhasil mentransformasikan pembinaan administrasi dosir prajurit dari sistem manual berbasis warkat fisik menjadi pangkalan data digital terpadu, terverifikasi, aman, dan berkekuatan hukum melalui implementasi Tanda Tangan Elektronik (TTE) ber-QR Code dan hash SHA-256.', $fNormal, $pJustify);
$mainSection->addText('b.   Saran. Demi keandalan operasional dan keamanan jangka panjang, disarankan :', $fNormal, $pJustify);
$mainSection->addText('     1)   Melaksanakan sinkronisasi data secara berkala antara sistem TRISULA dengan Sistem Informasi Personel TNI AD (Sipersad) di tingkat Kotama.', $fNormal, $pJustify);
$mainSection->addText('     2)   Menerapkan koneksi jaringan tertutup (Intranet TNI AD / VPN Militer) dan sertifikat SSL/TLS (HTTPS) pada server produksi.', $fNormal, $pJustify);
$mainSection->addText('     3)   Mengembangkan fitur notifikasi otomatis via WhatsApp Gateway atau surel dinas kepada prajurit saat berkas dosirnya ditolak atau disetujui.', $fNormal, $pJustify);
$mainSection->addText('     4)   Menjadwalkan prosedur pencadangan data otomatis harian (automated daily backup cron) ke peladen cadangan (disaster recovery center).', $fNormal, $pJustify);
$mainSection->addText('c.   Demikian Buku Dokumentasi Sistem Program Aplikasi TRISULA TNI AD ini disusun dengan berpedoman pada Naskah Sekolah Kadisinfolahtad Nomor: 61 - A – 009 sebagai instrumen baku pertanggungjawaban teknis dan pembinaan sistem informasi di lingkungan TNI Angkatan Darat.', $fNormal, $pJustify);

$mainSection->addTextBreak(3);

// Kolom Tanda Tangan Pengesahan
$tSign = $mainSection->addTable(['alignment' => JcTable::CENTER, 'width' => 9200]);
$tSign->addRow();
$cSignLeft = $tSign->addCell(4600);
$cSignRight = $tSign->addCell(4600);

$cSignLeft->addText('Mengetahui / Mengesahkan :', $fNormalBold, $pCenter);
$cSignLeft->addText('Kepala Dinas Informasi dan Pengolahan Data', $fNormalBold, $pCenter);
$cSignLeft->addText('TNI Angkatan Darat,', $fNormalBold, $pCenter);
$cSignLeft->addTextBreak(3);
$cSignLeft->addText('FITRY TAUFIQ SAHARY, S.E., M.M.', ['name' => 'Times New Roman', 'size' => 11.5, 'bold' => true, 'underline' => 'single'], $pCenter);
$cSignLeft->addText('BRIGADIR JENDERAL TNI', $fNormalBold, $pCenter);

$cSignRight->addText('Jakarta,   21 September 2026', $fNormal, $pCenter);
$cSignRight->addText('Pembuat Dokumentasi / Pemrogram,', $fNormalBold, $pCenter);
$cSignRight->addText('Perwira Pertama Zeni TNI AD,', $fNormal, $pCenter);
$cSignRight->addTextBreak(3);
$cSignRight->addText('FRIS WARDANI', ['name' => 'Times New Roman', 'size' => 11.5, 'bold' => true, 'underline' => 'single'], $pCenter);
$cSignRight->addText('LETNAN DUA CZI NRP 211801234509', $fNormalBold, $pCenter);

// ---------------------------------------------------------------------
// 4. SIMPAN DOKUMEN .DOCX
// ---------------------------------------------------------------------
$outputDocxPath = __DIR__ . '/DOKUMENTASI_SISTEM_TRISULA_TNI_AD.docx';
echo "Menyimpan berkas dokumen Word ke: $outputDocxPath ...\n";

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($outputDocxPath);

if (file_exists($outputDocxPath) && filesize($outputDocxPath) > 0) {
    $fileSizeKb = round(filesize($outputDocxPath) / 1024, 2);
    echo "BERHASIL! Dokumen Word telah terbuat dengan sempurna: $outputDocxPath ($fileSizeKb KB)\n";
} else {
    echo "GAGAL membuat dokumen Word.\n";
}
