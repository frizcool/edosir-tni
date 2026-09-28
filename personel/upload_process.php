<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/watermark.php';
require_role('personel');

$u = current_user();
$personel_id = $u['personel_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/personel/upload.php');

// Validasi Keamanan CSRF
verify_csrf();

$kode = $_POST['kode'] ?? '';
$keterangan = trim($_POST['keterangan'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM dosir_master WHERE kode=?");
$stmt->execute([$kode]);
$dosir = $stmt->fetch();

if (!$dosir) {
    set_flash('error', 'Jenis dosir tidak valid.');
    redirect('/personel/upload.php');
}

if (empty($_FILES['berkas']) || $_FILES['berkas']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error', 'Berkas gagal diunggah. Coba lagi.');
    redirect('/personel/upload.php?kode=' . urlencode($kode));
}

$file = $_FILES['berkas'];

// Validasi Ekstensi & MIME Type Magic Bytes (Mencegah file palsu)
if (!is_allowed_ext($file['name']) || !is_valid_pdf($file['tmp_name'])) {
    set_flash('error', 'Hanya berkas dokumen PDF asli yang diperbolehkan.');
    redirect('/personel/upload.php?kode=' . urlencode($kode));
}
if ($file['size'] > MAX_UPLOAD_SIZE) {
    set_flash('error', 'Ukuran berkas melebihi batas maksimal 10 MB.');
    redirect('/personel/upload.php?kode=' . urlencode($kode));
}

// Ambil NRP personel
$stmtP = $pdo->prepare("SELECT nrp, nama FROM personel WHERE id=?");
$stmtP->execute([$personel_id]);
$personelRow = $stmtP->fetch();
$nrp = $personelRow['nrp'] ?? '00000000';

// Tentukan slot abjad untuk dosir berikutnya (a, b, c...)
$abjad = next_abjad_slot($pdo, $personel_id, $kode);
$fileName = build_dosir_filename($nrp, $kode, $abjad);

$folder = folder_dosir($kode);

// 1. Simpan Berkas Master Bersih (Clean Original) di folder raw/
$rawDir = UPLOAD_DIR . '/raw/' . $folder;
ensure_dir($rawDir);
$rawPath = $rawDir . '/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $rawPath)) {
    set_flash('error', 'Gagal menyimpan berkas master di server.');
    redirect('/personel/upload.php?kode=' . urlencode($kode));
}

// 2. Buat Berkas Aktif yang Diberi Watermark "BELUM TERVERIFIKASI"
$activeDir = UPLOAD_DIR . '/' . $folder;
ensure_dir($activeDir);
$activePath = $activeDir . '/' . $fileName;

$metaLabel = "NRP $nrp &middot; " . date('d-m-Y H:i');
$wmText = get_setting($pdo, 'watermark_text', 'BELUM TERVERIFIKASI');
$watermarked = apply_unverified_watermark($rawPath, $activePath, $metaLabel, $wmText);

if (!$watermarked) {
    // Fallback jika pustaka rendering belum aktif
    copy($rawPath, $activePath);
}

$activeRelPath = $folder . '/' . $fileName;
$rawRelPath    = 'raw/' . $folder . '/' . $fileName;
$rawHash       = file_exists($rawPath) ? hash_file('sha256', $rawPath) : null;

// 3. Simpan Catatan Berkas ke Basis Data (is_watermarked = 1 karena status masih pending)
$ins = $pdo->prepare("
    INSERT INTO dosir_files (
        personel_id, dosir_kode, abjad, file_name, file_path, raw_file_path,
        original_name, keterangan, status, is_watermarked, raw_hash, uploaded_by
    ) VALUES (?,?,?,?,?,?,?,?,'pending',1,?,?)
");
$ins->execute([
    $personel_id, $kode, $abjad, $fileName, $activeRelPath, $rawRelPath,
    $file['name'], $keterangan, $rawHash, $u['id']
]);

log_activity($pdo, $u['id'], 'UPLOAD_DOSIR', "Unggah DOSIR $kode ($fileName) - Berkas berwatermark 'BELUM TERVERIFIKASI'");

set_flash('success', "Berkas $fileName berhasil diunggah dengan status 'BELUM TERVERIFIKASI' dan sedang menunggu verifikasi admin.");
redirect('/personel/dashboard.php');
