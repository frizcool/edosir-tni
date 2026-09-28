<?php
// =====================================================================
// FUNGSI BANTU (HELPERS)
// =====================================================================

/** Redirect helper */
function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Flash message sederhana via session */
function set_flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Catat aktivitas ke activity_log */
function log_activity($pdo, $user_id, $aktivitas, $keterangan = '') {
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, aktivitas, keterangan, ip_address) VALUES (?,?,?,?)");
    $stmt->execute([$user_id, $aktivitas, $keterangan, $_SERVER['REMOTE_ADDR'] ?? '']);
}

/** Nomor folder dosir, misal '5' -> 'FOLDER 05' */
function folder_dosir($kode) {
    return 'FOLDER ' . str_pad($kode, 2, '0', STR_PAD_LEFT);
}

/**
 * Tentukan slot abjad berikutnya untuk kombinasi personel+kode dosir.
 * File pertama tanpa abjad, berikutnya a, b, c, ...
 */
function next_abjad_slot($pdo, $personel_id, $kode) {
    $stmt = $pdo->prepare("SELECT abjad FROM dosir_files WHERE personel_id=? AND dosir_kode=?");
    $stmt->execute([$personel_id, $kode]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($existing)) {
        return null; // slot pertama, tanpa abjad
    }
    // cari huruf berikutnya yang belum dipakai
    foreach (range('a', 'z') as $letter) {
        if (!in_array($letter, $existing, true)) {
            return $letter;
        }
    }
    return null; // (praktis tidak pernah tercapai - 26 slot cukup)
}

/** Susun nama file fisik sesuai format {nrp}_{kode}{abjad}.pdf */
function build_dosir_filename($nrp, $kode, $abjad) {
    $kodePad = str_pad($kode, 2, '0', STR_PAD_LEFT);
    return $nrp . '_' . $kodePad . ($abjad ?? '') . '.pdf';
}

/**
 * URL aman ke berkas dosir melalui controller terproteksi (view_file.php)
 * Memastikan otentikasi dan otorisasi sebelum berkas dapat diakses.
 * Dilengkapi versi waktu modifikasi (&v=) agar dokumen yang telah diapprove
 * langsung termutakhirkan tanpa terhalang cache peramban/PDF viewer.
 */
function dosir_url($filePath, $fileId = null) {
    if (!$filePath && !$fileId) return '#';
    $clean = $filePath ? str_replace('\\', '/', $filePath) : '';
    if (strpos($clean, 'uploads/') === 0) {
        $clean = substr($clean, 8);
    }
    
    // Hitung hash/mtime berkas fisik jika ada
    $v = time();
    if ($clean) {
        $abs = UPLOAD_DIR . '/' . $clean;
        if (file_exists($abs)) {
            $v = filemtime($abs);
        }
    }

    if ($fileId) {
        return BASE_URL . '/view_file.php?id=' . (int)$fileId . '&v=' . $v;
    }
    return BASE_URL . '/view_file.php?file=' . rawurlencode($clean) . '&v=' . $v;
}

/**
 * URL aman ke berkas pas foto prajurit melalui controller view_file.php
 * Menyelesaikan masalah 403 forbidden akibat proteksi uploads/.htaccess
 */
function foto_url($fotoPath) {
    if (empty($fotoPath)) return '';
    $clean = str_replace('\\', '/', $fotoPath);
    if (strpos($clean, 'uploads/') === 0) {
        $clean = substr($clean, 8);
    }
    $abs = UPLOAD_DIR . '/' . $clean;
    if (!file_exists($abs)) return '';
    $v = filemtime($abs);
    return BASE_URL . '/view_file.php?file=' . rawurlencode($clean) . '&v=' . $v;
}

/**
 * URL logo aplikasi jika diatur, atau null jika memakai lambang default
 */
function app_logo_url($db = null) {
    global $pdo;
    $db = $db ?? $pdo;
    if (!$db) return null;
    $logoRel = get_setting($db, 'app_logo', '');
    if ($logoRel && file_exists(APP_ROOT . '/' . $logoRel)) {
        return BASE_URL . '/' . $logoRel . '?v=' . filemtime(APP_ROOT . '/' . $logoRel);
    }
    return null;
}

/** CSRF Token Generator */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render hidden input CSRF */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Validasi token CSRF pada request POST */
function verify_csrf() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            die('Validasi keamanan gagal: Token CSRF tidak valid atau telah kadaluarsa. Silakan muat ulang halaman.');
        }
    }
}

/** Validasi tipe konten berkas PDF menggunakan finfo magic bytes */
function is_valid_pdf($tempFilePath) {
    if (!file_exists($tempFilePath) || !is_readable($tempFilePath)) {
        return false;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tempFilePath);
    return $mime === 'application/pdf';
}

/**
 * Cek apakah IP / Username sedang terkena batasan rate limit login
 * @return int 0 jika aman, atau jumlah sisa menit penangguhan jika terkunci
 */
function check_login_rate_limit($pdo, $ip, $username, $maxAttempts = 5, $lockoutMinutes = 15) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE (ip_address = ? OR username = ?) 
              AND attempt_time > (NOW() - INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, $username, $lockoutMinutes]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= $maxAttempts) {
            return $lockoutMinutes;
        }
    } catch (Exception $e) {
        // Fallback jika tabel belum siap
    }
    return 0;
}

/** Catat percobaan login yang gagal */
function record_failed_login($pdo, $ip, $username) {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
    }
}

/** Bersihkan riwayat login gagal setelah berhasil masuk */
function clear_failed_logins($pdo, $ip, $username) {
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
    }
}

/** Hitung persentase kelengkapan dosir seorang personel (berdasarkan dosir berstatus wajib) */
function hitung_kelengkapan($pdo, $personel_id) {
    static $totalWajib = null;
    if ($totalWajib === null) {
        try {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM dosir_master WHERE wajib=1")->fetchColumn();
            $totalWajib = $cnt > 0 ? $cnt : 33;
        } catch (Exception $e) {
            $totalWajib = 33;
        }
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT f.dosir_kode) 
         FROM dosir_files f
         JOIN dosir_master m ON m.kode = f.dosir_kode
         WHERE f.personel_id=? AND f.status='approved' AND m.wajib=1"
    );
    $stmt->execute([$personel_id]);
    $terisi = (int) $stmt->fetchColumn();
    $total = $totalWajib;
    return [
        'terisi'  => $terisi,
        'total'   => $total,
        'persen'  => round(($terisi / $total) * 100, 1),
    ];
}

/** Hitung usia dari tanggal lahir */
function hitung_usia($tanggal_lahir, $pada = null) {
    if (!$tanggal_lahir) return null;
    $lahir = new DateTime($tanggal_lahir);
    $now = $pada ? new DateTime($pada) : new DateTime();
    return $lahir->diff($now)->y;
}

/** Prediksi tanggal pensiun berdasarkan golongan & tanggal lahir (alias untuk hitung_proyeksi_pensiun) */
function prediksi_pensiun($golongan, $tanggal_lahir) {
    return hitung_proyeksi_pensiun($tanggal_lahir, $golongan);
}

/**
 * Menghitung Proyeksi Tanggal Pensiun Sesuai Golongan
 * Standar Naskah Sekolah Disinfolahtad Nomor: 61 - A – 009
 * Batas Usia Pensiun (BUP): Perwira 58 th, Bintara/Tamtama 56 th, PNS 60 th
 */
function hitung_proyeksi_pensiun(?string $tanggalLahir, string $golongan): ?string {
    if (!$tanggalLahir) return null;
    $usiaPensiun = USIA_PENSIUN[$golongan] ?? 56;
    try {
        $lahir = new DateTime($tanggalLahir);
        $lahir->modify('+' . $usiaPensiun . ' years');
        return $lahir->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

/** Sisa waktu (dalam bulan) menuju pensiun; negatif = sudah lewat */
function bulan_menuju_pensiun($tanggal_pensiun) {
    if (!$tanggal_pensiun) return null;
    $now = new DateTime();
    $target = new DateTime($tanggal_pensiun);
    $diff = $now->diff($target);
    $bulan = ($diff->y * 12) + $diff->m;
    return $diff->invert ? -$bulan : $bulan;
}

/** Lama menjabat (tahun, dibulatkan 1 desimal) sejak tmt_jabatan */
function lama_jabatan_tahun($tmt_jabatan) {
    if (!$tmt_jabatan) return null;
    $tmt = new DateTime($tmt_jabatan);
    $now = new DateTime();
    $diff = $tmt->diff($now);
    return round($diff->y + ($diff->m / 12), 1);
}

/**
 * Deteksi Masa Jabatan Melebihi Batas Tertentu (Baku: 2 Tahun)
 * Mendukung Perencanaan Mutasi / Tour of Duty & Tour of Area (TOD/TOA)
 */
function is_jabatan_melebihi_batas(?string $tmtJabatan, int $batasTahun = 2): bool {
    if (!$tmtJabatan) return false;
    try {
        $tmt = new DateTime($tmtJabatan);
        $sekarang = new DateTime();
        $interval = $tmt->diff($sekarang);
        return ($interval->y >= $batasTahun && !$interval->invert);
    } catch (Exception $e) {
        return false;
    }
}

/** Ambil daftar 33 master dosir */
function get_dosir_master($pdo) {
    return $pdo->query("SELECT * FROM dosir_master ORDER BY urutan")->fetchAll();
}

/** Format tanggal Indonesia singkat */
function fmt_tgl($tgl) {
    if (!$tgl) return '-';
    $bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $d = new DateTime($tgl);
    return $d->format('d') . ' ' . $bulan[(int)$d->format('n')] . ' ' . $d->format('Y');
}

/** Buat direktori jika belum ada */
function ensure_dir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

/** Validasi ekstensi file upload */
function is_allowed_ext($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_UPLOAD_EXT, true);
}

/**
 * Memastikan akun user di tabel users tersedia untuk suatu data personel.
 * Username & password default otomatis menggunakan NRP.
 *
 * @param PDO $pdo
 * @param int $personel_id
 * @param string $nrp
 * @param string $status 'pending'|'approved'|'rejected'|'nonaktif'
 * @return array ['user_id' => int, 'created' => bool]
 */
function ensure_personel_user($pdo, $personel_id, $nrp, $status = 'pending') {
    $nrp = trim($nrp);
    if ($nrp === '' || !$personel_id) {
        return ['user_id' => 0, 'created' => false];
    }

    // Cek apakah sudah ada user terkait personel_id ini
    $stmt = $pdo->prepare("SELECT id, username, personel_id FROM users WHERE personel_id = ?");
    $stmt->execute([$personel_id]);
    $user = $stmt->fetch();

    if ($user) {
        // Jika username berbeda dari NRP (misal NRP diedit), sinkronkan username
        if ($user['username'] !== $nrp) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$nrp, $user['id']]);
            if (!$chk->fetch()) {
                $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $upd->execute([$nrp, $user['id']]);
            }
        }
        return ['user_id' => (int)$user['id'], 'created' => false];
    }

    // Cek apakah ada akun dengan username = NRP yang belum terhubung personel_id
    $stmt2 = $pdo->prepare("SELECT id, personel_id FROM users WHERE username = ?");
    $stmt2->execute([$nrp]);
    $user2 = $stmt2->fetch();

    if ($user2) {
        $upd = $pdo->prepare("UPDATE users SET personel_id = ? WHERE id = ?");
        $upd->execute([$personel_id, $user2['id']]);
        return ['user_id' => (int)$user2['id'], 'created' => false];
    }

    // Jika belum ada, buatkan akun baru: username = NRP, password = hash(NRP)
    $hash = password_hash($nrp, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO users (username, password, role, personel_id, status) VALUES (?, ?, 'personel', ?, ?)");
    $ins->execute([$nrp, $hash, $personel_id, $status]);
    $userId = (int)$pdo->lastInsertId();

    return ['user_id' => $userId, 'created' => true];
}

/**
 * Reset password akun user personel kembali ke NRP default
 */
function reset_user_password_to_nrp($pdo, $user_id, $nrp) {
    $hash = password_hash(trim($nrp), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    return $stmt->execute([$hash, $user_id]);
}

/**
 * Mengambil nilai pengaturan sistem dari database dengan static cache
 */
function get_setting($pdo, $key, $default = '', $refresh = false) {
    static $cache = null;
    if ($cache === null || $refresh) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $cache = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Menyimpan atau memperbarui nilai pengaturan sistem di database
 */
function update_setting($pdo, $key, $value, $group = 'general') {
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_group)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)
    ");
    $result = $stmt->execute([$key, $value, $group]);
    if ($result) {
        get_setting($pdo, '', '', true);
    }
    return $result;
}

/**
 * Mengambil nilai unik dari kolom tabel personel untuk saran dropdown / autocomplete datalist
 */
function get_distinct_personel_field($pdo, $fieldName) {
    $allowed = ['satuan', 'kotama', 'korp', 'pangkat', 'agama', 'status_kawin'];
    if (!in_array($fieldName, $allowed, true)) {
        return [];
    }
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT $fieldName 
            FROM personel 
            WHERE $fieldName IS NOT NULL AND $fieldName != '' 
            ORDER BY $fieldName ASC
        ");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Pembersihan otomatis berkas arsip unduh massal yang telah usang di direktori exports/
 * Menjaga efisiensi kapasitas media penyimpanan server (Garbage Collection)
 */
function cleanup_expired_exports($maxAgeSeconds = 86400) {
    if (!defined('EXPORT_DIR') || !is_dir(EXPORT_DIR)) return;
    try {
        $now = time();
        $files = scandir(EXPORT_DIR);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitignore') continue;
            $path = EXPORT_DIR . '/' . $f;
            if (is_file($path) && ($now - filemtime($path) > $maxAgeSeconds)) {
                @unlink($path);
            }
        }
    } catch (Exception $e) {
        // Abaikan kegagalan I/O minor
    }
}

/**
 * Mengambil jumlah lencana (badge counters) untuk notifikasi menu navigasi
 */
function get_system_badge_counts($pdo, $user = null) {
    static $badges = null;
    if ($badges !== null) return $badges;

    $badges = [
        'pending_users'    => 0,
        'pending_dosirs'   => 0,
        'rejected_dosirs'  => 0,
    ];

    if (!$pdo) return $badges;

    try {
        if (!$user) {
            $user = $_SESSION['user'] ?? null;
        }

        if ($user && $user['role'] === 'admin') {
            $badges['pending_users'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM users WHERE status='pending' AND role='personel'"
            )->fetchColumn();

            $badges['pending_dosirs'] = (int)$pdo->query(
                "SELECT COUNT(*) FROM dosir_files WHERE status='pending'"
            )->fetchColumn();
        } elseif ($user && $user['role'] === 'personel' && !empty($user['personel_id'])) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM dosir_files WHERE personel_id=? AND status='rejected'"
            );
            $stmt->execute([$user['personel_id']]);
            $badges['rejected_dosirs'] = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
    }

    return $badges;
}

/**
 * =====================================================================
 * MASTER DATA RUJUKAN RELASIONAL ALAMI (NATURAL RDBMS) TNI AD
 * Pangkat, Korp, Kotama, Satuan, & Penyelarasan Otomatis
 * =====================================================================
 */

/** Ambil daftar master pangkat (opsional difilter berdasarkan golongan) */
function get_master_pangkat_list($pdo, $golongan = null) {
    try {
        if ($golongan) {
            $stmt = $pdo->prepare("SELECT * FROM master_pangkat WHERE golongan = ? ORDER BY urutan ASC");
            $stmt->execute([$golongan]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $pdo->query("SELECT * FROM master_pangkat ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/** Ambil daftar master korp/kecabangan resmi TNI AD */
function get_master_korp_list($pdo, $kategori = null) {
    try {
        if ($kategori) {
            $stmt = $pdo->prepare("SELECT * FROM master_korp WHERE kategori = ? ORDER BY urutan ASC, kode ASC");
            $stmt->execute([$kategori]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $pdo->query("SELECT * FROM master_korp ORDER BY urutan ASC, kode ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/** Ambil daftar master Kotama/Balakpus TNI AD */
function get_master_kotama_list($pdo) {
    try {
        return $pdo->query("SELECT * FROM master_kotama ORDER BY urutan ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/** Ambil daftar master Satuan organik (opsional difilter berdasarkan Kotama) */
function get_master_satuan_list($pdo, $kotama_id = null) {
    try {
        if ($kotama_id) {
            $stmt = $pdo->prepare("
                SELECT s.*, k.nama as nama_kotama, k.kode as kode_kotama 
                FROM master_satuan s 
                LEFT JOIN master_kotama k ON k.id = s.kotama_id 
                WHERE s.kotama_id = ? 
                ORDER BY s.urutan ASC, s.nama ASC
            ");
            $stmt->execute([$kotama_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $pdo->query("
            SELECT s.*, k.nama as nama_kotama, k.kode as kode_kotama 
            FROM master_satuan s 
            LEFT JOIN master_kotama k ON k.id = s.kotama_id 
            ORDER BY s.urutan ASC, s.nama ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/** Sinkronisasi otomatis data string personel ke relasi Foreign Key master rujukan */
function sync_personel_natural_relations($pdo) {
    try {
        $pangkatList = $pdo->query("SELECT id, singkatan, nama, kode FROM master_pangkat")->fetchAll(PDO::FETCH_ASSOC);
        $korpList = $pdo->query("SELECT id, kode, nama FROM master_korp")->fetchAll(PDO::FETCH_ASSOC);
        $kotamaList = $pdo->query("SELECT id, kode, nama FROM master_kotama")->fetchAll(PDO::FETCH_ASSOC);
        $satuanList = $pdo->query("SELECT id, kode, nama, kotama_id FROM master_satuan")->fetchAll(PDO::FETCH_ASSOC);

        $personelAll = $pdo->query("SELECT id, pangkat, korp, satuan, kotama, pangkat_id, korp_id, satuan_id, kotama_id FROM personel")->fetchAll(PDO::FETCH_ASSOC);

        $upd = $pdo->prepare("UPDATE personel SET pangkat_id = ?, korp_id = ?, satuan_id = ?, kotama_id = ? WHERE id = ?");
        $count = 0;

        foreach ($personelAll as $p) {
            $pktId = $p['pangkat_id'];
            $krpId = $p['korp_id'];
            $satId = $p['satuan_id'];
            $kotId = $p['kotama_id'];

            if (!$pktId && !empty($p['pangkat'])) {
                foreach ($pangkatList as $mp) {
                    if (strcasecmp($mp['singkatan'], trim($p['pangkat'])) === 0 || strcasecmp($mp['kode'], trim($p['pangkat'])) === 0 || stripos(trim($p['pangkat']), $mp['singkatan']) !== false) {
                        $pktId = $mp['id'];
                        break;
                    }
                }
            }

            if (!$krpId && !empty($p['korp'])) {
                foreach ($korpList as $mk) {
                    if (strcasecmp($mk['kode'], trim($p['korp'])) === 0 || strcasecmp($mk['nama'], trim($p['korp'])) === 0) {
                        $krpId = $mk['id'];
                        break;
                    }
                }
            }

            if (!$kotId && !empty($p['kotama'])) {
                foreach ($kotamaList as $mkot) {
                    if (strcasecmp($mkot['nama'], trim($p['kotama'])) === 0 || strcasecmp($mkot['kode'], trim($p['kotama'])) === 0 || stripos(trim($p['kotama']), $mkot['kode']) !== false) {
                        $kotId = $mkot['id'];
                        break;
                    }
                }
            }

            if (!$satId && !empty($p['satuan'])) {
                foreach ($satuanList as $msat) {
                    if (strcasecmp($msat['nama'], trim($p['satuan'])) === 0 || strcasecmp($msat['kode'], trim($p['satuan'])) === 0 || stripos(trim($p['satuan']), $msat['nama']) !== false) {
                        $satId = $msat['id'];
                        if (!$kotId && $msat['kotama_id']) {
                            $kotId = $msat['kotama_id'];
                        }
                        break;
                    }
                }
            }

            $upd->execute([$pktId, $krpId, $satId, $kotId, $p['id']]);
            $count++;
        }
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Harmonisasi teks standar personel dari data master rujukan
 * Menyelaraskan ejaan string 'pangkat', 'korp', 'satuan', 'kotama' agar seragam dengan master
 */
function harmonize_personel_text_strings($pdo) {
    try {
        $count = 0;
        // Harmonisasi pangkat
        $pdo->query("
            UPDATE personel p
            JOIN master_pangkat mp ON mp.id = p.pangkat_id
            SET p.pangkat = mp.singkatan
            WHERE p.pangkat_id IS NOT NULL
        ");
        // Harmonisasi korp
        $pdo->query("
            UPDATE personel p
            JOIN master_korp mk ON mk.id = p.korp_id
            SET p.korp = mk.kode
            WHERE p.korp_id IS NOT NULL
        ");
        // Harmonisasi kotama
        $pdo->query("
            UPDATE personel p
            JOIN master_kotama mkot ON mkot.id = p.kotama_id
            SET p.kotama = mkot.nama
            WHERE p.kotama_id IS NOT NULL
        ");
        // Harmonisasi satuan
        $pdo->query("
            UPDATE personel p
            JOIN master_satuan ms ON ms.id = p.satuan_id
            SET p.satuan = ms.nama
            WHERE p.satuan_id IS NOT NULL
        ");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mengambil ringkasan statistik kesehatan relasi alami pangkalan data
 */
function get_natural_database_stats($pdo) {
    $stats = [
        'total_pangkat' => 0,
        'total_korp' => 0,
        'total_kotama' => 0,
        'total_satuan' => 0,
        'total_personel' => 0,
        'personel_lengkap' => 0,
        'personel_lengkap_pct' => 0,
        'missing_pangkat' => 0,
        'missing_korp' => 0,
        'missing_satuan' => 0,
        'missing_kotama' => 0,
    ];
    try {
        $stats['total_pangkat'] = (int)$pdo->query("SELECT COUNT(*) FROM master_pangkat")->fetchColumn();
        $stats['total_korp'] = (int)$pdo->query("SELECT COUNT(*) FROM master_korp")->fetchColumn();
        $stats['total_kotama'] = (int)$pdo->query("SELECT COUNT(*) FROM master_kotama")->fetchColumn();
        $stats['total_satuan'] = (int)$pdo->query("SELECT COUNT(*) FROM master_satuan")->fetchColumn();
        $stats['total_personel'] = (int)$pdo->query("SELECT COUNT(*) FROM personel")->fetchColumn();

        if ($stats['total_personel'] > 0) {
            $stats['personel_lengkap'] = (int)$pdo->query("
                SELECT COUNT(*) FROM personel 
                WHERE pangkat_id IS NOT NULL 
                  AND satuan_id IS NOT NULL 
                  AND kotama_id IS NOT NULL
                  AND (golongan = 'PNS' OR korp_id IS NOT NULL)
            ")->fetchColumn();
            $stats['personel_lengkap_pct'] = round(($stats['personel_lengkap'] / $stats['total_personel']) * 100, 1);

            $stats['missing_pangkat'] = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE pangkat_id IS NULL")->fetchColumn();
            $stats['missing_korp']    = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE golongan != 'PNS' AND korp_id IS NULL")->fetchColumn();
            $stats['missing_satuan']  = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE satuan_id IS NULL")->fetchColumn();
            $stats['missing_kotama']  = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE kotama_id IS NULL")->fetchColumn();
        }
    } catch (Exception $e) {
    }
    return $stats;
}

/**
 * Menyelesaikan dan menyinkronkan data ID dan teks relasi natural pada formulir personel
 */
function resolve_and_save_personel_relations($pdo, &$data) {
    // 1. Pangkat
    if (!empty($data['pangkat_id'])) {
        $stmt = $pdo->prepare("SELECT singkatan, golongan FROM master_pangkat WHERE id = ?");
        $stmt->execute([$data['pangkat_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['pangkat'] = $row['singkatan'];
            if (empty($data['golongan'])) {
                $data['golongan'] = $row['golongan'];
            }
        }
    } elseif (!empty($data['pangkat'])) {
        $stmt = $pdo->prepare("SELECT id, singkatan FROM master_pangkat WHERE singkatan = ? OR kode = ? LIMIT 1");
        $stmt->execute([$data['pangkat'], $data['pangkat']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['pangkat_id'] = $row['id'];
            $data['pangkat'] = $row['singkatan'];
        }
    }

    // 2. Korp
    if (!empty($data['korp_id'])) {
        $stmt = $pdo->prepare("SELECT kode FROM master_korp WHERE id = ?");
        $stmt->execute([$data['korp_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['korp'] = $row['kode'];
        }
    } elseif (!empty($data['korp'])) {
        $stmt = $pdo->prepare("SELECT id, kode FROM master_korp WHERE kode = ? OR nama = ? LIMIT 1");
        $stmt->execute([$data['korp'], $data['korp']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['korp_id'] = $row['id'];
            $data['korp'] = $row['kode'];
        }
    }

    // 3. Kotama
    if (!empty($data['kotama_id'])) {
        $stmt = $pdo->prepare("SELECT nama FROM master_kotama WHERE id = ?");
        $stmt->execute([$data['kotama_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['kotama'] = $row['nama'];
        }
    } elseif (!empty($data['kotama'])) {
        $stmt = $pdo->prepare("SELECT id, nama FROM master_kotama WHERE nama = ? OR kode = ? LIMIT 1");
        $stmt->execute([$data['kotama'], $data['kotama']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['kotama_id'] = $row['id'];
            $data['kotama'] = $row['nama'];
        }
    }

    // 4. Satuan
    if (!empty($data['satuan_id'])) {
        $stmt = $pdo->prepare("SELECT nama, kotama_id FROM master_satuan WHERE id = ?");
        $stmt->execute([$data['satuan_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['satuan'] = $row['nama'];
            if (empty($data['kotama_id']) && $row['kotama_id']) {
                $data['kotama_id'] = $row['kotama_id'];
                $stmtK = $pdo->prepare("SELECT nama FROM master_kotama WHERE id = ?");
                $stmtK->execute([$row['kotama_id']]);
                $data['kotama'] = $stmtK->fetchColumn() ?: $data['kotama'];
            }
        }
    } elseif (!empty($data['satuan'])) {
        $stmt = $pdo->prepare("SELECT id, nama, kotama_id FROM master_satuan WHERE nama = ? OR kode = ? LIMIT 1");
        $stmt->execute([$data['satuan'], $data['satuan']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data['satuan_id'] = $row['id'];
            $data['satuan'] = $row['nama'];
            if (empty($data['kotama_id']) && $row['kotama_id']) {
                $data['kotama_id'] = $row['kotama_id'];
                $stmtK = $pdo->prepare("SELECT nama FROM master_kotama WHERE id = ?");
                $stmtK->execute([$row['kotama_id']]);
                $data['kotama'] = $stmtK->fetchColumn() ?: $data['kotama'];
            }
        }
    }
}
