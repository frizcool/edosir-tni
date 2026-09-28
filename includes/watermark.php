<?php
// =====================================================================
// MODUL WATERMARK & TANDA TANGAN ELEKTRONIK (TTE) DOKUMEN DOSIR
// Membutuhkan library FPDI & TCPDF (via vendor/autoload.php)
// =====================================================================

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Membubuhkan watermark diagonal "BELUM TERVERIFIKASI" pada berkas yang baru diunggah.
 *
 * @param string $inputPath  Path lengkap ke berkas PDF master/sumber
 * @param string $outputPath Path lengkap hasil simpan (bisa sama dengan $inputPath)
 * @param string $metaLabel  Keterangan tambahan (cth: Tanggal upload, NRP)
 * @param string $watermarkText Teks watermark (default: 'BELUM TERVERIFIKASI')
 * @return bool
 */
function apply_unverified_watermark($inputPath, $outputPath, $metaLabel = '', $watermarkText = 'BELUM TERVERIFIKASI') {
    if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
        if ($inputPath !== $outputPath && file_exists($inputPath)) {
            copy($inputPath, $outputPath);
        }
        return false;
    }
    if (!file_exists($inputPath)) {
        return false;
    }

    try {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $pageCount = $pdf->setSourceFile($inputPath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tplId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            $w = $size['width'];
            $h = $size['height'];

            // 1. Watermark Diagonal Transparan (Warna Oranye/Merah Peringatan Militer)
            $pdf->SetAlpha(0.24);
            $pdf->SetFont('helvetica', 'B', $orientation === 'L' ? 34 : 28);
            $pdf->SetTextColor(190, 48, 40); // Merah peringatan
            $pdf->StartTransform();
            $pdf->Rotate($orientation === 'L' ? 30 : 45, $w / 2, $h / 2);
            $textX = ($w / 2) - ($orientation === 'L' ? 85 : 75);
            $pdf->Text($textX, $h / 2, $watermarkText);
            $pdf->StopTransform();

            // 2. Banner Pita Peringatan Atas
            $pdf->SetAlpha(0.85);
            $pdf->SetFillColor(245, 235, 235);
            $pdf->SetTextColor(180, 40, 35);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY(0, 0);
            $pdf->Cell($w, 6, 'PERINGATAN: DOKUMEN INI BELUM DIVERIFIKASI / DALAM PENINJAUAN — BELUM MEMILIKI KEKUATAN HUKUM DINAS', 0, 0, 'C', true);

            // 3. Catatan Kaki Bawah
            $pdf->SetAlpha(0.9);
            $pdf->SetTextColor(130, 60, 50);
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY(5, $h - 7);
            $footerText = 'TRISULA TNI AD — Status: Pending Approval. Menunggu persetujuan Administrator Satuan.' . ($metaLabel ? ' (' . $metaLabel . ')' : '');
            $pdf->Cell($w - 10, 5, $footerText, 0, 0, 'L');
        }

        $tmp = $outputPath . '.tmp';
        $pdf->Output($tmp, 'F');

        if (file_exists($tmp) && filesize($tmp) > 0) {
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            rename($tmp, $outputPath);
            return true;
        }
        return false;
    } catch (\Throwable $e) {
        error_log('Watermark Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mengesahkan berkas dosir dengan Tanda Tangan Elektronik (TTE) Resmi.
 * Watermark "BELUM TERVERIFIKASI" otomatis HILANG karena mengambil berkas master bersih.
 *
 * @param string $rawMasterPath Path ke berkas master bersih (unwatermarked)
 * @param string $outputPath    Path berkas aktif yang akan dilihat/diunduh
 * @param array  $signData      Data TTE (code, hash, verify_url, nama_admin, pangkat_admin, nrp_admin, satuan_admin, date)
 * @return bool
 */
function apply_digital_signature_pdf($rawMasterPath, $outputPath, array $signData) {
    if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
        return false;
    }

    $source = file_exists($rawMasterPath) ? $rawMasterPath : $outputPath;
    if (!file_exists($source)) {
        return false;
    }

    try {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);

        $pageCount = $pdf->setSourceFile($source);

        $code        = $signData['code'] ?? ('TTE-' . date('Ymd-His'));
        $verifyUrl   = $signData['verify_url'] ?? 'https://tni.mil.id';
        $signerName  = $signData['nama_admin'] ?? 'Administrator Sistem';
        $signerRank  = $signData['pangkat_admin'] ?? 'Admin Pers';
        $signerNrp   = $signData['nrp_admin'] ?? '-';
        $signerUnit  = $signData['satuan_admin'] ?? 'Mabesad';
        $signDate    = $signData['date'] ?? date('d-m-Y H:i:s');
        $docHash     = $signData['hash'] ?? hash_file('sha256', $source);
        $shortHash   = substr($docHash, 0, 16) . '...' . substr($docHash, -8);

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tplId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);

            $w = $size['width'];
            $h = $size['height'];

            // 1. Running Footer Otentikasi pada Setiap Halaman
            $pdf->SetAlpha(1.0);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetTextColor(70, 95, 75); // Hijau tentara tenang
            $runningLine = "★ TRISULA TNI AD — DITANDATANGANI SECARA ELEKTRONIK (TTE) | KODE: $code | HASH: $shortHash | $signDate WIB";
            $pdf->SetXY(6, $h - 6.5);
            $pdf->Cell($w - 12, 4, $runningLine, 0, 0, 'L');

            // 2. Kotak Tanda Tangan Digital Resmi (Diletakkan pada Halaman Terakhir)
            if ($i === $pageCount) {
                // Dimensi & Posisi Kotak TTE
                $boxW = 88;
                $boxH = 38;
                $boxX = $w - $boxW - 8;
                $boxY = $h - $boxH - 9;

                // Pastikan tidak keluar margin halaman
                if ($boxY < 8) $boxY = 8;
                if ($boxX < 8) $boxX = 8;

                // Background & Border Kotak Sertifikat Digital
                $pdf->SetAlpha(0.96);
                $pdf->SetFillColor(248, 252, 248);  // Putih kehijauan sangat lembut
                $pdf->SetDrawColor(46, 85, 52);     // Border Hijau Army Resmi
                $pdf->SetLineWidth(0.4);
                $pdf->RoundedRect($boxX, $boxY, $boxW, $boxH, 2.5, '1111', 'DF');

                // Garis Aksen Emas di Atas Box
                $pdf->SetDrawColor(201, 168, 76);   // Gold TNI
                $pdf->SetLineWidth(0.8);
                $pdf->Line($boxX + 2, $boxY + 0.8, $boxX + $boxW - 2, $boxY + 0.8);

                // Header Kotak TTE
                $pdf->SetFont('helvetica', 'B', 6.5);
                $pdf->SetTextColor(30, 60, 35);
                $pdf->SetXY($boxX + 3, $boxY + 2.5);
                $pdf->Cell($boxW - 6, 3.5, 'TENTARA NASIONAL INDONESIA ANGKATAN DARAT', 0, 1, 'L');

                $pdf->SetFont('helvetica', 'B', 5.8);
                $pdf->SetTextColor(170, 135, 45); // Gold
                $pdf->SetXY($boxX + 3, $boxY + 6);
                $pdf->Cell($boxW - 6, 3, 'SERTIFIKASI TANDA TANGAN ELEKTRONIK (TTE)', 0, 1, 'L');

                // Gambar QR Code Native Menggunakan TCPDF 2D Barcode Engine
                $qrDim = 23;
                $qrX = $boxX + 3;
                $qrY = $boxY + 10;
                
                // Background putih di bawah QR
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Rect($qrX, $qrY, $qrDim, $qrDim, 'F');
                $pdf->write2DBarcode($verifyUrl, 'QRCODE,M', $qrX + 0.5, $qrY + 0.5, $qrDim - 1, $qrDim - 1);

                // Metadata Verifikator di Kanan QR Code
                $infoX = $qrX + $qrDim + 3;
                $infoW = $boxW - $qrDim - 8;

                $pdf->SetTextColor(90, 105, 95);
                $pdf->SetFont('helvetica', 'I', 5.5);
                $pdf->SetXY($infoX, $boxY + 9.5);
                $pdf->Cell($infoW, 3, 'Disahkan Secara Digital Oleh:', 0, 1, 'L');

                $pdf->SetTextColor(25, 45, 30);
                $pdf->SetFont('helvetica', 'B', 7.5);
                $pdf->SetXY($infoX, $boxY + 12.8);
                $pdf->Cell($infoW, 3.8, $signerName, 0, 1, 'L');

                $pdf->SetTextColor(60, 75, 65);
                $pdf->SetFont('helvetica', '', 6.2);
                $pdf->SetXY($infoX, $boxY + 16.8);
                $pdf->Cell($infoW, 3.2, ($signerRank ? $signerRank . ' ' : '') . ($signerNrp ? 'NRP ' . $signerNrp : ''), 0, 1, 'L');

                $pdf->SetXY($infoX, $boxY + 20);
                $pdf->Cell($infoW, 3.2, $signerUnit, 0, 1, 'L');

                $pdf->SetFont('helvetica', '', 5.8);
                $pdf->SetTextColor(100, 110, 105);
                $pdf->SetXY($infoX, $boxY + 23.5);
                $pdf->Cell($infoW, 3, 'Waktu: ' . $signDate . ' WIB', 0, 1, 'L');

                // Nomor Registrasi TTE
                $pdf->SetFont('courier', 'B', 6);
                $pdf->SetTextColor(160, 125, 35);
                $pdf->SetXY($infoX, $boxY + 26.8);
                $pdf->Cell($infoW, 3, $code, 0, 1, 'L');

                // Footer Kecil di Bawah Box
                $pdf->SetFont('helvetica', 'I', 4.8);
                $pdf->SetTextColor(110, 125, 115);
                $pdf->SetXY($boxX + 3, $boxY + $boxH - 3.8);
                $pdf->Cell($boxW - 6, 3, 'Scan QR Code untuk memverifikasi keabsahan dokumen di Portal TRISULA', 0, 0, 'C');
            }
        }

        $tmp = $outputPath . '.tmp';
        $pdf->Output($tmp, 'F');

        if (file_exists($tmp) && filesize($tmp) > 0) {
            if (file_exists($outputPath)) {
                @unlink($outputPath);
            }
            rename($tmp, $outputPath);
            return true;
        }
        return false;
    } catch (\Throwable $e) {
        error_log('TTE Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Wrapper kompatibilitas mundur
 */
function apply_watermark_pdf($absolutePath, $labelBawah = '', $watermarkText = 'TERVERIFIKASI') {
    $signData = [
        'code'          => 'TTE-' . date('Ymd-His'),
        'verify_url'    => BASE_URL . '/verify.php?code=LEGACY',
        'nama_admin'    => 'Administrator Satuan',
        'pangkat_admin' => 'Admin Pers',
        'nrp_admin'     => '99999999',
        'satuan_admin'  => 'Mabesad',
        'date'          => date('d-m-Y H:i:s'),
    ];
    return apply_digital_signature_pdf($absolutePath, $absolutePath, $signData);
}
