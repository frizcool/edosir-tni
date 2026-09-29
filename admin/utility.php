<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$admin = current_user();
$tab = $_GET['tab'] ?? 'kotama';
if (!in_array($tab, ['kotama', 'satuan', 'pangkat', 'korp', 'sync'], true)) {
    $tab = 'kotama';
}

$error = null;
$success = null;

// Ekspor JSON Master Data
if (isset($_GET['action']) && $_GET['action'] === 'export_json') {
    $exportData = [
        'generated_at' => date('Y-m-d H:i:s'),
        'app' => 'E-Dosir TNI AD',
        'master_kotama'  => $pdo->query("SELECT * FROM master_kotama ORDER BY urutan ASC, kode ASC")->fetchAll(PDO::FETCH_ASSOC),
        'master_satuan'  => $pdo->query("SELECT * FROM master_satuan ORDER BY urutan ASC, kode ASC")->fetchAll(PDO::FETCH_ASSOC),
        'master_pangkat' => $pdo->query("SELECT * FROM master_pangkat ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC),
        'master_korp'    => $pdo->query("SELECT * FROM master_korp ORDER BY urutan ASC, kode ASC")->fetchAll(PDO::FETCH_ASSOC),
    ];
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="master_data_tni_ad_' . date('Ymd_His') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Handler POST Aksi CRUD & Utilitas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['action'] ?? '';

    // ==========================================
    // 1. AKSI MASTER KOTAMA
    // ==========================================
    if ($act === 'create_kotama') {
        $kode   = strtoupper(trim($_POST['kode'] ?? ''));
        $nama   = trim($_POST['nama'] ?? '');
        $tipe   = $_POST['tipe'] ?? 'Kotamabin';
        $urutan = (int)($_POST['urutan'] ?? 0);

        if ($kode === '' || $nama === '') {
            $error = 'Kode dan nama Kotama/Balakpus wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_kotama WHERE kode = ?");
            $chk->execute([$kode]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode Kotama '$kode' sudah terdaftar.";
            } else {
                $ins = $pdo->prepare("INSERT INTO master_kotama (kode, nama, tipe, urutan) VALUES (?, ?, ?, ?)");
                $ins->execute([$kode, $nama, $tipe, $urutan]);
                log_activity($pdo, $admin['id'], 'CREATE_KOTAMA', "Tambah Kotama #$kode: $nama");
                set_flash('success', "Kotama/Balakpus [$kode] $nama berhasil ditambahkan.");
                redirect('/admin/utility.php?tab=kotama');
            }
        }
    } elseif ($act === 'update_kotama') {
        $id     = (int)($_POST['id'] ?? 0);
        $kode   = strtoupper(trim($_POST['kode'] ?? ''));
        $nama   = trim($_POST['nama'] ?? '');
        $tipe   = $_POST['tipe'] ?? 'Kotamabin';
        $urutan = (int)($_POST['urutan'] ?? 0);

        if ($id <= 0 || $kode === '' || $nama === '') {
            $error = 'ID, kode, dan nama Kotama wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_kotama WHERE kode = ? AND id != ?");
            $chk->execute([$kode, $id]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode Kotama '$kode' sudah digunakan oleh entri lain.";
            } else {
                $upd = $pdo->prepare("UPDATE master_kotama SET kode=?, nama=?, tipe=?, urutan=? WHERE id=?");
                $upd->execute([$kode, $nama, $tipe, $urutan, $id]);
                log_activity($pdo, $admin['id'], 'UPDATE_KOTAMA', "Update Kotama #$id [$kode]");
                set_flash('success', "Data Kotama [$kode] berhasil diperbarui.");
                redirect('/admin/utility.php?tab=kotama');
            }
        }
    } elseif ($act === 'delete_kotama') {
        $id = (int)($_POST['id'] ?? 0);
        $cntSatuan = (int)$pdo->prepare("SELECT COUNT(*) FROM master_satuan WHERE kotama_id=?")->execute([$id]) ? $pdo->query("SELECT COUNT(*) FROM master_satuan WHERE kotama_id=$id")->fetchColumn() : 0;
        $cntPersonel = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE kotama_id=$id")->fetchColumn();

        if ($cntSatuan > 0 || $cntPersonel > 0) {
            set_flash('error', "Tidak dapat menghapus Kotama ini karena masih memiliki $cntSatuan Satuan bawahan atau $cntPersonel personel terkait.");
        } else {
            $pdo->prepare("DELETE FROM master_kotama WHERE id=?")->execute([$id]);
            log_activity($pdo, $admin['id'], 'DELETE_KOTAMA', "Hapus Kotama #$id");
            set_flash('success', "Kotama berhasil dihapus dari pangkalan data.");
        }
        redirect('/admin/utility.php?tab=kotama');
    }

    // ==========================================
    // 2. AKSI MASTER SATUAN
    // ==========================================
    elseif ($act === 'create_satuan') {
        $kotama_id = (int)($_POST['kotama_id'] ?? 0) ?: null;
        $kode      = strtoupper(trim($_POST['kode'] ?? ''));
        $nama      = trim($_POST['nama'] ?? '');
        $lokasi    = trim($_POST['lokasi'] ?? '');
        $urutan    = (int)($_POST['urutan'] ?? 0);

        if ($kode === '' || $nama === '') {
            $error = 'Kode dan nama satuan wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_satuan WHERE kode = ?");
            $chk->execute([$kode]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode Satuan '$kode' sudah terdaftar.";
            } else {
                $ins = $pdo->prepare("INSERT INTO master_satuan (kotama_id, kode, nama, lokasi, urutan) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$kotama_id, $kode, $nama, $lokasi, $urutan]);
                log_activity($pdo, $admin['id'], 'CREATE_SATUAN', "Tambah Satuan #$kode: $nama");
                set_flash('success', "Satuan [$kode] $nama berhasil ditambahkan.");
                redirect('/admin/utility.php?tab=satuan' . ($kotama_id ? "&kotama_id=$kotama_id" : ''));
            }
        }
    } elseif ($act === 'update_satuan') {
        $id        = (int)($_POST['id'] ?? 0);
        $kotama_id = (int)($_POST['kotama_id'] ?? 0) ?: null;
        $kode      = strtoupper(trim($_POST['kode'] ?? ''));
        $nama      = trim($_POST['nama'] ?? '');
        $lokasi    = trim($_POST['lokasi'] ?? '');
        $urutan    = (int)($_POST['urutan'] ?? 0);

        if ($id <= 0 || $kode === '' || $nama === '') {
            $error = 'ID, kode, dan nama satuan wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_satuan WHERE kode = ? AND id != ?");
            $chk->execute([$kode, $id]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode Satuan '$kode' sudah digunakan oleh entri lain.";
            } else {
                $upd = $pdo->prepare("UPDATE master_satuan SET kotama_id=?, kode=?, nama=?, lokasi=?, urutan=? WHERE id=?");
                $upd->execute([$kotama_id, $kode, $nama, $lokasi, $urutan, $id]);
                log_activity($pdo, $admin['id'], 'UPDATE_SATUAN', "Update Satuan #$id [$kode]");
                set_flash('success', "Data Satuan [$kode] berhasil diperbarui.");
                redirect('/admin/utility.php?tab=satuan' . ($kotama_id ? "&kotama_id=$kotama_id" : ''));
            }
        }
    } elseif ($act === 'delete_satuan') {
        $id = (int)($_POST['id'] ?? 0);
        $cntPersonel = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE satuan_id=$id")->fetchColumn();

        if ($cntPersonel > 0) {
            set_flash('error', "Tidak dapat menghapus Satuan ini karena masih terhubung dengan $cntPersonel prajurit.");
        } else {
            $pdo->prepare("DELETE FROM master_satuan WHERE id=?")->execute([$id]);
            log_activity($pdo, $admin['id'], 'DELETE_SATUAN', "Hapus Satuan #$id");
            set_flash('success', "Satuan berhasil dihapus.");
        }
        redirect('/admin/utility.php?tab=satuan');
    }

    // ==========================================
    // 3. AKSI MASTER PANGKAT
    // ==========================================
    elseif ($act === 'create_pangkat') {
        $golongan  = $_POST['golongan'] ?? 'Perwira';
        $kode      = strtoupper(trim($_POST['kode'] ?? ''));
        $nama      = trim($_POST['nama'] ?? '');
        $singkatan = trim($_POST['singkatan'] ?? '');
        $bup_usia  = (int)($_POST['bup_usia'] ?? 58);
        $urutan    = (int)($_POST['urutan'] ?? 0);

        if ($kode === '' || $nama === '' || $singkatan === '') {
            $error = 'Kode, nama resmi, dan singkatan pangkat wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_pangkat WHERE kode = ?");
            $chk->execute([$kode]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode pangkat '$kode' sudah terdaftar.";
            } else {
                $ins = $pdo->prepare("INSERT INTO master_pangkat (golongan, kode, nama, singkatan, urutan, bup_usia) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$golongan, $kode, $nama, $singkatan, $urutan, $bup_usia]);
                log_activity($pdo, $admin['id'], 'CREATE_PANGKAT', "Tambah Pangkat #$kode: $nama");
                set_flash('success', "Pangkat [$singkatan] $nama berhasil ditambahkan.");
                redirect('/admin/utility.php?tab=pangkat');
            }
        }
    } elseif ($act === 'update_pangkat') {
        $id        = (int)($_POST['id'] ?? 0);
        $golongan  = $_POST['golongan'] ?? 'Perwira';
        $kode      = strtoupper(trim($_POST['kode'] ?? ''));
        $nama      = trim($_POST['nama'] ?? '');
        $singkatan = trim($_POST['singkatan'] ?? '');
        $bup_usia  = (int)($_POST['bup_usia'] ?? 58);
        $urutan    = (int)($_POST['urutan'] ?? 0);

        if ($id <= 0 || $kode === '' || $nama === '' || $singkatan === '') {
            $error = 'Semua kolom wajib diisi dengan benar.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_pangkat WHERE kode = ? AND id != ?");
            $chk->execute([$kode, $id]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode pangkat '$kode' sudah dipakai.";
            } else {
                $upd = $pdo->prepare("UPDATE master_pangkat SET golongan=?, kode=?, nama=?, singkatan=?, urutan=?, bup_usia=? WHERE id=?");
                $upd->execute([$golongan, $kode, $nama, $singkatan, $urutan, $bup_usia, $id]);
                log_activity($pdo, $admin['id'], 'UPDATE_PANGKAT', "Update Pangkat #$id [$kode]");
                set_flash('success', "Pangkat [$singkatan] berhasil diperbarui.");
                redirect('/admin/utility.php?tab=pangkat');
            }
        }
    } elseif ($act === 'delete_pangkat') {
        $id = (int)($_POST['id'] ?? 0);
        $cntPersonel = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE pangkat_id=$id")->fetchColumn();
        if ($cntPersonel > 0) {
            set_flash('error', "Tidak dapat menghapus pangkat ini karena ada $cntPersonel prajurit berpangkat tersebut.");
        } else {
            $pdo->prepare("DELETE FROM master_pangkat WHERE id=?")->execute([$id]);
            log_activity($pdo, $admin['id'], 'DELETE_PANGKAT', "Hapus Pangkat #$id");
            set_flash('success', "Pangkat berhasil dihapus.");
        }
        redirect('/admin/utility.php?tab=pangkat');
    }

    // ==========================================
    // 4. AKSI MASTER KORP
    // ==========================================
    elseif ($act === 'create_korp') {
        $kode     = ucfirst(strtolower(trim($_POST['kode'] ?? '')));
        $nama     = trim($_POST['nama'] ?? '');
        $kategori = $_POST['kategori'] ?? 'Tempur';
        $urutan   = (int)($_POST['urutan'] ?? 0);

        if ($kode === '' || $nama === '') {
            $error = 'Kode dan nama korp wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_korp WHERE kode = ?");
            $chk->execute([$kode]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode korp '$kode' sudah terdaftar.";
            } else {
                $ins = $pdo->prepare("INSERT INTO master_korp (kode, nama, kategori, urutan) VALUES (?, ?, ?, ?)");
                $ins->execute([$kode, $nama, $kategori, $urutan]);
                log_activity($pdo, $admin['id'], 'CREATE_KORP', "Tambah Korp #$kode: $nama");
                set_flash('success', "Korp [$kode] $nama berhasil ditambahkan.");
                redirect('/admin/utility.php?tab=korp');
            }
        }
    } elseif ($act === 'update_korp') {
        $id       = (int)($_POST['id'] ?? 0);
        $kode     = ucfirst(strtolower(trim($_POST['kode'] ?? '')));
        $nama     = trim($_POST['nama'] ?? '');
        $kategori = $_POST['kategori'] ?? 'Tempur';
        $urutan   = (int)($_POST['urutan'] ?? 0);

        if ($id <= 0 || $kode === '' || $nama === '') {
            $error = 'ID, kode, dan nama korp wajib diisi.';
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM master_korp WHERE kode = ? AND id != ?");
            $chk->execute([$kode, $id]);
            if ($chk->fetchColumn() > 0) {
                $error = "Kode korp '$kode' sudah dipakai oleh entri lain.";
            } else {
                $upd = $pdo->prepare("UPDATE master_korp SET kode=?, nama=?, kategori=?, urutan=? WHERE id=?");
                $upd->execute([$kode, $nama, $kategori, $urutan, $id]);
                log_activity($pdo, $admin['id'], 'UPDATE_KORP', "Update Korp #$id [$kode]");
                set_flash('success', "Korp [$kode] berhasil diperbarui.");
                redirect('/admin/utility.php?tab=korp');
            }
        }
    } elseif ($act === 'delete_korp') {
        $id = (int)($_POST['id'] ?? 0);
        $cntPersonel = (int)$pdo->query("SELECT COUNT(*) FROM personel WHERE korp_id=$id")->fetchColumn();
        if ($cntPersonel > 0) {
            set_flash('error', "Tidak dapat menghapus Korp ini karena terdapat $cntPersonel prajurit dengan korp tersebut.");
        } else {
            $pdo->prepare("DELETE FROM master_korp WHERE id=?")->execute([$id]);
            log_activity($pdo, $admin['id'], 'DELETE_KORP', "Hapus Korp #$id");
            set_flash('success', "Korp berhasil dihapus.");
        }
        redirect('/admin/utility.php?tab=korp');
    }

    // ==========================================
    // 5. AKSI UTILITAS SINKRONISASI RELASI ALAMI
    // ==========================================
    elseif ($act === 'sync_relations') {
        $synced = sync_personel_natural_relations($pdo);
        log_activity($pdo, $admin['id'], 'SYNC_NATURAL_RELATIONS', "Sinkronisasi relasi alami $synced personel");
        set_flash('success', "Sinkronisasi relasi alami selesai diproses untuk $synced prajurit/pegawai.");
        redirect('/admin/utility.php?tab=sync');
    } elseif ($act === 'harmonize_strings') {
        harmonize_personel_text_strings($pdo);
        log_activity($pdo, $admin['id'], 'HARMONIZE_STRINGS', "Harmonisasi penulisan teks master personel");
        set_flash('success', "Seluruh penulisan string teks (Pangkat, Korp, Satuan, Kotama) telah diseragamkan dengan standar rujukan pangkalan data.");
        redirect('/admin/utility.php?tab=sync');
    } elseif ($act === 'quick_link_personel') {
        $pId = (int)($_POST['personel_id'] ?? 0);
        $pktId = (int)($_POST['pangkat_id'] ?? 0) ?: null;
        $krpId = (int)($_POST['korp_id'] ?? 0) ?: null;
        $satId = (int)($_POST['satuan_id'] ?? 0) ?: null;
        $kotId = (int)($_POST['kotama_id'] ?? 0) ?: null;

        if ($pId > 0) {
            $upd = $pdo->prepare("UPDATE personel SET pangkat_id=?, korp_id=?, satuan_id=?, kotama_id=? WHERE id=?");
            $upd->execute([$pktId, $krpId, $satId, $kotId, $pId]);
            harmonize_personel_text_strings($pdo);
            log_activity($pdo, $admin['id'], 'QUICK_LINK_PERSONEL', "Tautkan relasi alami personel #$pId");
            set_flash('success', "Relasi personel berhasil ditautkan.");
        }
        redirect('/admin/utility.php?tab=sync');
    }
}

// Data Pendukung untuk Tampilan
$dbStats = get_natural_database_stats($pdo);

// Ambil data sesuai tab
$kotamaList = [];
$satuanList = [];
$pangkatList = [];
$korpList = [];
$unlinkedPersonel = [];

if ($tab === 'kotama') {
    $kotamaList = $pdo->query("
        SELECT k.*, 
               COUNT(DISTINCT s.id) as total_satuan,
               COUNT(DISTINCT p.id) as total_personel
        FROM master_kotama k
        LEFT JOIN master_satuan s ON s.kotama_id = k.id
        LEFT JOIN personel p ON p.kotama_id = k.id
        GROUP BY k.id
        ORDER BY k.urutan ASC, k.nama ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tab === 'satuan') {
    $filterKotama = (int)($_GET['kotama_id'] ?? 0);
    $satSql = "
        SELECT s.*, 
               k.nama as nama_kotama, 
               k.kode as kode_kotama,
               COUNT(p.id) as total_personel
        FROM master_satuan s
        LEFT JOIN master_kotama k ON k.id = s.kotama_id
        LEFT JOIN personel p ON p.satuan_id = s.id
        WHERE 1=1
    ";
    $satParams = [];
    if ($filterKotama > 0) {
        $satSql .= " AND s.kotama_id = ?";
        $satParams[] = $filterKotama;
    }
    $satSql .= " GROUP BY s.id ORDER BY s.urutan ASC, s.nama ASC";
    $stmtSat = $pdo->prepare($satSql);
    $stmtSat->execute($satParams);
    $satuanList = $stmtSat->fetchAll(PDO::FETCH_ASSOC);

    $allKotamaOpts = $pdo->query("SELECT id, kode, nama FROM master_kotama ORDER BY urutan ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tab === 'pangkat') {
    $filterGol = $_GET['golongan'] ?? '';
    $pktSql = "
        SELECT p.*, COUNT(per.id) as total_personel
        FROM master_pangkat p
        LEFT JOIN personel per ON per.pangkat_id = p.id
        WHERE 1=1
    ";
    $pktParams = [];
    if ($filterGol !== '' && in_array($filterGol, ['Perwira', 'Bintara', 'Tamtama', 'PNS'], true)) {
        $pktSql .= " AND p.golongan = ?";
        $pktParams[] = $filterGol;
    }
    $pktSql .= " GROUP BY p.id ORDER BY p.urutan ASC";
    $stmtPkt = $pdo->prepare($pktSql);
    $stmtPkt->execute($pktParams);
    $pangkatList = $stmtPkt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tab === 'korp') {
    $filterKat = $_GET['kategori'] ?? '';
    $krpSql = "
        SELECT k.*, COUNT(p.id) as total_personel
        FROM master_korp k
        LEFT JOIN personel p ON p.korp_id = k.id
        WHERE 1=1
    ";
    $krpParams = [];
    if ($filterKat !== '') {
        $krpSql .= " AND k.kategori = ?";
        $krpParams[] = $filterKat;
    }
    $krpSql .= " GROUP BY k.id ORDER BY k.urutan ASC, k.kode ASC";
    $stmtKrp = $pdo->prepare($krpSql);
    $stmtKrp->execute($krpParams);
    $korpList = $stmtKrp->fetchAll(PDO::FETCH_ASSOC);
    $unlinkedPersonel = $pdo->query("
        SELECT p.id, p.nrp, p.nama, mp.golongan, mp.singkatan as pangkat, p.pangkat_id,
               mk.kode as korp, p.korp_id, ms.nama as satuan, p.satuan_id, mkot.nama as kotama, p.kotama_id
        FROM personel p
        LEFT JOIN master_pangkat mp ON mp.id = p.pangkat_id
        LEFT JOIN master_korp mk ON mk.id = p.korp_id
        LEFT JOIN master_satuan ms ON ms.id = p.satuan_id
        LEFT JOIN master_kotama mkot ON COALESCE(ms.kotama_id, p.kotama_id) = mkot.id
        WHERE p.pangkat_id IS NULL 
           OR (mp.golongan != 'PNS' AND p.korp_id IS NULL)
           OR p.satuan_id IS NULL 
           OR (p.kotama_id IS NULL AND ms.kotama_id IS NULL)
        ORDER BY p.nama ASC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    $allPangkatOpts = $pdo->query("SELECT id, singkatan, nama FROM master_pangkat ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);
    $allKorpOpts    = $pdo->query("SELECT id, kode, nama FROM master_korp ORDER BY urutan ASC, kode ASC")->fetchAll(PDO::FETCH_ASSOC);
    $allKotamaOpts  = $pdo->query("SELECT id, kode, nama FROM master_kotama ORDER BY urutan ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
    $allSatuanOpts  = $pdo->query("SELECT id, kode, nama, kotama_id FROM master_satuan ORDER BY urutan ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Utilitas Master Data Relasional';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header Banner & Quick Metrics -->
<div class="command-banner">
  <div>
    <div class="command-salute">Utilitas & Master Data Relasional</div>
    <div class="command-sub">
      Pengelolaan natural struktur kepangkatan, korp kecabangan, kotama, dan satuan organik TNI Angkatan Darat.
    </div>
  </div>
  <div class="system-status-pills">
    <div class="status-pill">
      <span class="pulse-dot"></span>
      <span>Integritas Relasi: <strong><?= $dbStats['personel_lengkap_pct'] ?>%</strong></span>
    </div>
    <a href="<?= BASE_URL ?>/admin/utility.php?action=export_json" class="btn btn-outline" style="padding:6px 14px;font-size:12px;" title="Unduh salinan berkas cadangan master JSON">
      📥 Unduh JSON
    </a>
  </div>
</div>

<!-- 4 Top Stat Cards -->
<div class="grid grid-4" style="margin-bottom:20px;">
  <div class="card card-interactive" style="border-left:4px solid var(--gold);">
    <div class="stat">
      <span class="label">Kotama & Balakpus</span>
      <span class="value gold"><?= $dbStats['total_kotama'] ?></span>
      <span style="font-size:12px;color:var(--text-dim);">Komando & Badan Pelaksana</span>
    </div>
  </div>
  <div class="card card-interactive" style="border-left:4px solid var(--accent);">
    <div class="stat">
      <span class="label">Satuan Organik</span>
      <span class="value ok"><?= $dbStats['total_satuan'] ?></span>
      <span style="font-size:12px;color:var(--text-dim);">Batalyon, Kodim, Den, Pus</span>
    </div>
  </div>
  <div class="card card-interactive" style="border-left:4px solid #63b3ed;">
    <div class="stat">
      <span class="label">Jenjang Kepangkatan</span>
      <span class="value" style="color:#63b3ed;"><?= $dbStats['total_pangkat'] ?></span>
      <span style="font-size:12px;color:var(--text-dim);">Pati, Pamen, Pama, Ba, Ta, PNS</span>
    </div>
  </div>
  <div class="card card-interactive" style="border-left:4px solid var(--warn);">
    <div class="stat">
      <span class="label">Korp Kecabangan</span>
      <span class="value warn"><?= $dbStats['total_korp'] ?></span>
      <span style="font-size:12px;color:var(--text-dim);">15 Korps Resmi TNI AD</span>
    </div>
  </div>
</div>

<!-- Navigation Tabs -->
<div class="tabs-nav">
  <a href="<?= BASE_URL ?>/admin/utility.php?tab=kotama" class="tab-btn <?= $tab === 'kotama' ? 'active' : '' ?>">
    🏛️ Kotama & Balakpus <span class="tab-badge"><?= $dbStats['total_kotama'] ?></span>
  </a>
  <a href="<?= BASE_URL ?>/admin/utility.php?tab=satuan" class="tab-btn <?= $tab === 'satuan' ? 'active' : '' ?>">
    ⚔️ Satuan Organik <span class="tab-badge"><?= $dbStats['total_satuan'] ?></span>
  </a>
  <a href="<?= BASE_URL ?>/admin/utility.php?tab=pangkat" class="tab-btn <?= $tab === 'pangkat' ? 'active' : '' ?>">
    🎖️ Master Pangkat <span class="tab-badge"><?= $dbStats['total_pangkat'] ?></span>
  </a>
  <a href="<?= BASE_URL ?>/admin/utility.php?tab=korp" class="tab-btn <?= $tab === 'korp' ? 'active' : '' ?>">
    🛡️ Master Korp <span class="tab-badge"><?= $dbStats['total_korp'] ?></span>
  </a>
  <a href="<?= BASE_URL ?>/admin/utility.php?tab=sync" class="tab-btn <?= $tab === 'sync' ? 'active' : '' ?>">
    🔄 Sinkronisasi & Integritas 
    <?php if ($dbStats['personel_lengkap_pct'] < 100): ?>
      <span class="tab-badge" style="background:rgba(201,143,63,0.25);color:var(--warn);border-color:var(--warn);"><?= $dbStats['personel_lengkap_pct'] ?>%</span>
    <?php else: ?>
      <span class="tab-badge" style="background:rgba(76,143,92,0.2);color:var(--ok);border-color:var(--ok);">100%</span>
    <?php endif; ?>
  </a>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- TAB 1: MASTER KOTAMA & BALAKPUS                          -->
<!-- ======================================================== -->
<?php if ($tab === 'kotama'): ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="margin:0;">Daftar Kotama & Balakpus TNI AD</h3>
      <div style="font-size:12.5px;color:var(--text-dim);margin-top:2px;">
        Entitas induk komando operasi, komando pembinaan, dan badan pelaksana pusat.
      </div>
    </div>
    <button type="button" class="btn" onclick="openModal('modalKotamaAdd')">➕ Tambah Kotama</button>
  </div>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th style="width:60px;">Urut</th>
          <th>Kode</th>
          <th>Nama Lengkap</th>
          <th>Tipe / Kategori</th>
          <th style="text-align:center;">Satuan Terhubung</th>
          <th style="text-align:center;">Personel</th>
          <th style="text-align:right;width:140px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($kotamaList as $k): ?>
        <tr>
          <td><span style="font-family:monospace;font-weight:600;color:var(--text-dim);"><?= $k['urutan'] ?></span></td>
          <td><strong style="color:var(--gold);"><?= htmlspecialchars($k['kode']) ?></strong></td>
          <td><strong><?= htmlspecialchars($k['nama']) ?></strong></td>
          <td>
            <span class="badge badge-info" style="font-size:11px;"><?= htmlspecialchars($k['tipe']) ?></span>
          </td>
          <td style="text-align:center;">
            <a href="<?= BASE_URL ?>/admin/utility.php?tab=satuan&kotama_id=<?= $k['id'] ?>" class="badge badge-approved" style="text-decoration:none;" title="Lihat satuan di bawah Kotama ini">
              <?= $k['total_satuan'] ?> Satuan
            </a>
          </td>
          <td style="text-align:center;">
            <span class="badge badge-nonaktif"><?= $k['total_personel'] ?> prajurit</span>
          </td>
          <td style="text-align:right;">
            <div style="display:inline-flex;gap:6px;">
              <button type="button" class="btn btn-outline" style="padding:4px 8px;font-size:12px;" onclick='editKotama(<?= json_encode($k, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>✏️</button>
              <form method="post" onsubmit="return confirm('Hapus Kotama [<?= htmlspecialchars($k['kode']) ?>]? Pastikan tidak ada satuan atau personel terkait.');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_kotama">
                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                <button type="submit" class="btn btn-outline btn-danger" style="padding:4px 8px;font-size:12px;">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah Kotama -->
<div class="modal-backdrop" id="modalKotamaAdd">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Tambah Kotama / Balakpus Baru</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalKotamaAdd')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_kotama">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Kode Singkat *</label>
          <input name="kode" placeholder="cth: KODAM III/SLW / KOSTRAD" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Lengkap Kotama / Balakpus *</label>
          <input name="nama" placeholder="cth: Komando Daerah Militer III/Siliwangi" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Tipe Satuan</label>
          <select name="tipe">
            <option value="Kotamabin">Kotamabin (Pembinaan Wilayah / Kodam)</option>
            <option value="Kotamaops">Kotamaops (Operasi / Kostrad, Kopassus)</option>
            <option value="Balakpus">Balakpus (Badan Pelaksana Pusat)</option>
            <option value="Mabesad">Mabesad (Markas Besar Angkatan Darat)</option>
          </select>
        </div>
        <div>
          <label>Urutan Tampilan</label>
          <input type="number" name="urutan" value="10" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalKotamaAdd')">Batal</button>
        <button type="submit" class="btn">💾 Simpan Kotama</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Kotama -->
<div class="modal-backdrop" id="modalKotamaEdit">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Edit Kotama / Balakpus</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalKotamaEdit')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_kotama">
      <input type="hidden" name="id" id="editKotamaId">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Kode Singkat *</label>
          <input name="kode" id="editKotamaKode" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Lengkap Kotama / Balakpus *</label>
          <input name="nama" id="editKotamaNama" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Tipe Satuan</label>
          <select name="tipe" id="editKotamaTipe">
            <option value="Kotamabin">Kotamabin (Pembinaan Wilayah / Kodam)</option>
            <option value="Kotamaops">Kotamaops (Operasi / Kostrad, Kopassus)</option>
            <option value="Balakpus">Balakpus (Badan Pelaksana Pusat)</option>
            <option value="Mabesad">Mabesad (Markas Besar Angkatan Darat)</option>
          </select>
        </div>
        <div>
          <label>Urutan Tampilan</label>
          <input type="number" name="urutan" id="editKotamaUrutan" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalKotamaEdit')">Batal</button>
        <button type="submit" class="btn">💾 Perbarui Kotama</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- TAB 2: MASTER SATUAN ORGANIK                             -->
<!-- ======================================================== -->
<?php elseif ($tab === 'satuan'): ?>
<div class="card" style="margin-bottom:16px;">
  <form method="get" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
    <input type="hidden" name="tab" value="satuan">
    <div style="flex:1;min-width:240px;max-width:420px;">
      <label>Filter Berdasarkan Kotama Induk</label>
      <select name="kotama_id" onchange="this.form.submit()">
        <option value="">-- Semua Kotama & Balakpus --</option>
        <?php foreach ($allKotamaOpts as $kot): ?>
          <option value="<?= $kot['id'] ?>" <?= ((int)($_GET['kotama_id'] ?? 0) === (int)$kot['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($kot['kode']) ?> - <?= htmlspecialchars($kot['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:8px;">
      <?php if (!empty($_GET['kotama_id'])): ?>
        <a href="<?= BASE_URL ?>/admin/utility.php?tab=satuan" class="btn btn-outline">Reset Filter</a>
      <?php endif; ?>
      <button type="button" class="btn" onclick="openModal('modalSatuanAdd')">➕ Tambah Satuan</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th style="width:50px;">Urut</th>
          <th>Kotama Induk</th>
          <th>Kode</th>
          <th>Nama Satuan Organik</th>
          <th>Lokasi / Garnisun</th>
          <th style="text-align:center;">Personel</th>
          <th style="text-align:right;width:120px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($satuanList)): ?>
          <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-dim);">Belum ada data satuan pada filter ini.</td></tr>
        <?php endif; ?>
        <?php foreach ($satuanList as $s): ?>
        <tr>
          <td><span style="font-family:monospace;color:var(--text-dim);"><?= $s['urutan'] ?></span></td>
          <td>
            <?php if (!empty($s['nama_kotama'])): ?>
              <span class="badge badge-info" style="font-size:11px;" title="<?= htmlspecialchars($s['nama_kotama']) ?>">
                <?= htmlspecialchars($s['kode_kotama'] ?: $s['nama_kotama']) ?>
              </span>
            <?php else: ?>
              <span style="color:var(--text-dim);font-size:12px;">(Pusat / Mandiri)</span>
            <?php endif; ?>
          </td>
          <td><strong style="color:var(--gold);"><?= htmlspecialchars($s['kode']) ?></strong></td>
          <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
          <td><span style="color:var(--text-dim);font-size:12.5px;">📍 <?= htmlspecialchars($s['lokasi'] ?: '-') ?></span></td>
          <td style="text-align:center;">
            <span class="badge badge-nonaktif"><?= $s['total_personel'] ?> prajurit</span>
          </td>
          <td style="text-align:right;">
            <div style="display:inline-flex;gap:6px;">
              <button type="button" class="btn btn-outline" style="padding:4px 8px;font-size:12px;" onclick='editSatuan(<?= json_encode($s) ?>)'>✏️</button>
              <form method="post" onsubmit="return confirm('Hapus Satuan [<?= htmlspecialchars($s['nama']) ?>]?');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_satuan">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-outline btn-danger" style="padding:4px 8px;font-size:12px;">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah Satuan -->
<div class="modal-backdrop" id="modalSatuanAdd">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Tambah Satuan Organik Baru</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalSatuanAdd')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_satuan">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Kotama Induk</label>
          <select name="kotama_id">
            <option value="">-- Tanpa Kotama / Satuan Mandiri --</option>
            <?php foreach ($allKotamaOpts as $kot): ?>
              <option value="<?= $kot['id'] ?>" <?= ((int)($_GET['kotama_id'] ?? 0) === (int)$kot['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($kot['kode']) ?> - <?= htmlspecialchars($kot['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:12px;">
          <label>Kode Singkat Satuan *</label>
          <input name="kode" placeholder="cth: YONIF 312 / KODIM 0605" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Lengkap Satuan *</label>
          <input name="nama" placeholder="cth: Batalyon Infanteri 312/Kala Hitam" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Lokasi / Garnisun</label>
          <input name="lokasi" placeholder="cth: Subang, Jawa Barat">
        </div>
        <div>
          <label>Urutan Tampilan</label>
          <input type="number" name="urutan" value="10" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalSatuanAdd')">Batal</button>
        <button type="submit" class="btn">💾 Simpan Satuan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Satuan -->
<div class="modal-backdrop" id="modalSatuanEdit">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Edit Satuan Organik</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalSatuanEdit')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_satuan">
      <input type="hidden" name="id" id="editSatuanId">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Kotama Induk</label>
          <select name="kotama_id" id="editSatuanKotamaId">
            <option value="">-- Tanpa Kotama / Satuan Mandiri --</option>
            <?php foreach ($allKotamaOpts as $kot): ?>
              <option value="<?= $kot['id'] ?>"><?= htmlspecialchars($kot['kode']) ?> - <?= htmlspecialchars($kot['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:12px;">
          <label>Kode Singkat Satuan *</label>
          <input name="kode" id="editSatuanKode" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Lengkap Satuan *</label>
          <input name="nama" id="editSatuanNama" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Lokasi / Garnisun</label>
          <input name="lokasi" id="editSatuanLokasi">
        </div>
        <div>
          <label>Urutan Tampilan</label>
          <input type="number" name="urutan" id="editSatuanUrutan" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalSatuanEdit')">Batal</button>
        <button type="submit" class="btn">💾 Perbarui Satuan</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- TAB 3: MASTER PANGKAT                                    -->
<!-- ======================================================== -->
<?php elseif ($tab === 'pangkat'): ?>
<div class="card" style="margin-bottom:16px;">
  <form method="get" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
    <input type="hidden" name="tab" value="pangkat">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=pangkat" class="btn <?= empty($_GET['golongan']) ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Semua Golongan</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=pangkat&golongan=Perwira" class="btn <?= ($_GET['golongan'] ?? '') === 'Perwira' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Perwira (Pati/Pamen/Pama)</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=pangkat&golongan=Bintara" class="btn <?= ($_GET['golongan'] ?? '') === 'Bintara' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Bintara</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=pangkat&golongan=Tamtama" class="btn <?= ($_GET['golongan'] ?? '') === 'Tamtama' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Tamtama</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=pangkat&golongan=PNS" class="btn <?= ($_GET['golongan'] ?? '') === 'PNS' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">PNS TNI AD</a>
    </div>
    <button type="button" class="btn" onclick="openModal('modalPangkatAdd')">➕ Tambah Pangkat</button>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th style="width:60px;">Hierarki</th>
          <th>Golongan</th>
          <th>Singkatan</th>
          <th>Nama Resmi Kepangkatan</th>
          <th>Kode Sistem</th>
          <th style="text-align:center;">BUP (Pensiun)</th>
          <th style="text-align:center;">Personel</th>
          <th style="text-align:right;width:120px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pangkatList as $p): ?>
        <tr>
          <td><span style="font-family:monospace;font-weight:700;color:var(--gold);"><?= $p['urutan'] ?></span></td>
          <td>
            <span class="badge <?= $p['golongan'] === 'Perwira' ? 'badge-gold' : ($p['golongan'] === 'Bintara' ? 'badge-info' : ($p['golongan'] === 'Tamtama' ? 'badge-approved' : 'badge-pending')) ?>">
              <?= htmlspecialchars($p['golongan']) ?>
            </span>
          </td>
          <td><strong style="color:var(--text);font-size:14px;"><?= htmlspecialchars($p['singkatan']) ?></strong></td>
          <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>
          <td><span style="font-family:monospace;color:var(--text-dim);font-size:12px;"><?= htmlspecialchars($p['kode']) ?></span></td>
          <td style="text-align:center;">
            <span class="badge badge-nonaktif"><?= $p['bup_usia'] ?> Tahun</span>
          </td>
          <td style="text-align:center;">
            <span class="badge badge-nonaktif"><?= $p['total_personel'] ?> prajurit</span>
          </td>
          <td style="text-align:right;">
            <div style="display:inline-flex;gap:6px;">
              <button type="button" class="btn btn-outline" style="padding:4px 8px;font-size:12px;" onclick='editPangkat(<?= json_encode($p) ?>)'>✏️</button>
              <form method="post" onsubmit="return confirm('Hapus Pangkat [<?= htmlspecialchars($p['singkatan']) ?>]?');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_pangkat">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-outline btn-danger" style="padding:4px 8px;font-size:12px;">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah Pangkat -->
<div class="modal-backdrop" id="modalPangkatAdd">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Tambah Pangkat Baru</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalPangkatAdd')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_pangkat">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Golongan</label>
          <select name="golongan">
            <option value="Perwira">Perwira</option>
            <option value="Bintara">Bintara</option>
            <option value="Tamtama">Tamtama</option>
            <option value="PNS">PNS</option>
          </select>
        </div>
        <div style="margin-bottom:12px;">
          <label>Kode Sistem * (Unik)</label>
          <input name="kode" placeholder="cth: KAPTEN / LETDA / SERMA" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Resmi *</label>
          <input name="nama" placeholder="cth: Kapten" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Singkatan Resmi *</label>
          <input name="singkatan" placeholder="cth: Kpt" required>
        </div>
        <div class="grid grid-2">
          <div>
            <label>Batas Usia Pensiun (BUP)</label>
            <input type="number" name="bup_usia" value="58" min="40" max="65" required>
          </div>
          <div>
            <label>Urutan Hierarki (1 = tertinggi)</label>
            <input type="number" name="urutan" value="10" min="1" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalPangkatAdd')">Batal</button>
        <button type="submit" class="btn">💾 Simpan Pangkat</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Pangkat -->
<div class="modal-backdrop" id="modalPangkatEdit">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Edit Pangkat</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalPangkatEdit')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_pangkat">
      <input type="hidden" name="id" id="editPangkatId">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Golongan</label>
          <select name="golongan" id="editPangkatGolongan">
            <option value="Perwira">Perwira</option>
            <option value="Bintara">Bintara</option>
            <option value="Tamtama">Tamtama</option>
            <option value="PNS">PNS</option>
          </select>
        </div>
        <div style="margin-bottom:12px;">
          <label>Kode Sistem * (Unik)</label>
          <input name="kode" id="editPangkatKode" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Resmi *</label>
          <input name="nama" id="editPangkatNama" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Singkatan Resmi *</label>
          <input name="singkatan" id="editPangkatSingkatan" required>
        </div>
        <div class="grid grid-2">
          <div>
            <label>Batas Usia Pensiun (BUP)</label>
            <input type="number" name="bup_usia" id="editPangkatBup" min="40" max="65" required>
          </div>
          <div>
            <label>Urutan Hierarki</label>
            <input type="number" name="urutan" id="editPangkatUrutan" min="1" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalPangkatEdit')">Batal</button>
        <button type="submit" class="btn">💾 Perbarui Pangkat</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- TAB 4: MASTER KORP KECABANGAN                            -->
<!-- ======================================================== -->
<?php elseif ($tab === 'korp'): ?>
<div class="card" style="margin-bottom:16px;">
  <form method="get" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;">
    <input type="hidden" name="tab" value="korp">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=korp" class="btn <?= empty($_GET['kategori']) ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Semua Korp</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=korp&kategori=Tempur" class="btn <?= ($_GET['kategori'] ?? '') === 'Tempur' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Satuan Tempur</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=korp&kategori=Bantuan Tempur" class="btn <?= ($_GET['kategori'] ?? '') === 'Bantuan Tempur' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Bantuan Tempur</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=korp&kategori=Bantuan Administrasi" class="btn <?= ($_GET['kategori'] ?? '') === 'Bantuan Administrasi' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Bantuan Administrasi</a>
      <a href="<?= BASE_URL ?>/admin/utility.php?tab=korp&kategori=Penerbad" class="btn <?= ($_GET['kategori'] ?? '') === 'Penerbad' ? '' : 'btn-outline' ?>" style="font-size:12px;padding:6px 12px;">Penerbad</a>
    </div>
    <button type="button" class="btn" onclick="openModal('modalKorpAdd')">➕ Tambah Korp</button>
  </form>
</div>

<div class="card">
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th style="width:60px;">Urut</th>
          <th>Kode Korp</th>
          <th>Nama Resmi Kecabangan</th>
          <th>Kategori Kecabangan</th>
          <th style="text-align:center;">Personel</th>
          <th style="text-align:right;width:120px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($korpList as $kr): ?>
        <tr>
          <td><span style="font-family:monospace;color:var(--text-dim);"><?= $kr['urutan'] ?></span></td>
          <td><strong style="color:var(--gold);font-size:15px;"><?= htmlspecialchars($kr['kode']) ?></strong></td>
          <td><strong><?= htmlspecialchars($kr['nama']) ?></strong></td>
          <td>
            <span class="badge <?= $kr['kategori'] === 'Tempur' ? 'badge-danger' : ($kr['kategori'] === 'Bantuan Tempur' ? 'badge-warn' : ($kr['kategori'] === 'Penerbad' ? 'badge-info' : 'badge-approved')) ?>">
              <?= htmlspecialchars($kr['kategori']) ?>
            </span>
          </td>
          <td style="text-align:center;">
            <span class="badge badge-nonaktif"><?= $kr['total_personel'] ?> prajurit</span>
          </td>
          <td style="text-align:right;">
            <div style="display:inline-flex;gap:6px;">
              <button type="button" class="btn btn-outline" style="padding:4px 8px;font-size:12px;" onclick='editKorp(<?= json_encode($kr) ?>)'>✏️</button>
              <form method="post" onsubmit="return confirm('Hapus Korp [<?= htmlspecialchars($kr['kode']) ?>]?');" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_korp">
                <input type="hidden" name="id" value="<?= $kr['id'] ?>">
                <button type="submit" class="btn btn-outline btn-danger" style="padding:4px 8px;font-size:12px;">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tambah Korp -->
<div class="modal-backdrop" id="modalKorpAdd">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Tambah Korp Baru</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalKorpAdd')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_korp">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Kode Korp * (cth: Inf, Kav, Arm, Arh)</label>
          <input name="kode" placeholder="cth: Inf" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Resmi Kecabangan *</label>
          <input name="nama" placeholder="cth: Infanteri" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Kategori Kecabangan</label>
          <select name="kategori">
            <option value="Tempur">Tempur</option>
            <option value="Bantuan Tempur">Bantuan Tempur</option>
            <option value="Bantuan Administrasi">Bantuan Administrasi</option>
            <option value="Penerbad">Penerbad</option>
          </select>
        </div>
        <div>
          <label>Urutan Tampilan</label>
          <input type="number" name="urutan" value="10" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalKorpAdd')">Batal</button>
        <button type="submit" class="btn">💾 Simpan Korp</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Korp -->
<div class="modal-backdrop" id="modalKorpEdit">
  <div class="modal-dialog">
    <div class="modal-header">
      <h4 class="modal-title">Edit Korp</h4>
      <button type="button" class="modal-close" onclick="closeModal('modalKorpEdit')">&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_korp">
      <input type="hidden" name="id" id="editKorpId">
      <div class="modal-body">
        <div style="margin-bottom:12px;">
          <label>Kode Korp *</label>
          <input name="kode" id="editKorpKode" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Nama Resmi Kecabangan *</label>
          <input name="nama" id="editKorpNama" required>
        </div>
        <div style="margin-bottom:12px;">
          <label>Kategori Kecabangan</label>
          <select name="kategori" id="editKorpKategori">
            <option value="Tempur">Tempur</option>
            <option value="Bantuan Tempur">Bantuan Tempur</option>
            <option value="Bantuan Administrasi">Bantuan Administrasi</option>
            <option value="Penerbad">Penerbad</option>
          </select>
        </div>
        <div>
          <label>Urutan Tampilan</label>
          <input type="number" name="urutan" id="editKorpUrutan" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalKorpEdit')">Batal</button>
        <button type="submit" class="btn">💾 Perbarui Korp</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- TAB 5: UTILITAS SINKRONISASI & INTEGRITAS                -->
<!-- ======================================================== -->
<?php elseif ($tab === 'sync'): ?>

<!-- Kartu Ringkasan Kesehatan Relasi Natural -->
<div class="card" style="margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:14px;">
    <div>
      <h3 style="margin:0;">Status Integritas Relasi Alami Pangkalan Data</h3>
      <div style="font-size:13px;color:var(--text-dim);margin-top:2px;">
        Evaluasi foreign keys relasional antara tabel <code>personel</code> dengan master data rujukan.
      </div>
    </div>
    <div style="font-size:24px;font-weight:700;color:<?= $dbStats['personel_lengkap_pct'] == 100 ? 'var(--ok)' : 'var(--warn)' ?>;">
      <?= $dbStats['personel_lengkap_pct'] ?>% Terelasi Alami
    </div>
  </div>

  <div class="progress" style="height:18px;margin-bottom:16px;">
    <span style="width:<?= $dbStats['personel_lengkap_pct'] ?>%;"></span>
  </div>

  <div class="grid grid-4" style="font-size:13px;">
    <div style="background:var(--panel-2);padding:10px 12px;border-radius:8px;border:1px solid var(--border);">
      <span style="color:var(--text-dim);display:block;font-size:11px;text-transform:uppercase;">Personel Lengkap</span>
      <strong style="color:var(--ok);font-size:16px;"><?= $dbStats['personel_lengkap'] ?></strong> / <?= $dbStats['total_personel'] ?> prajurit
    </div>
    <div style="background:var(--panel-2);padding:10px 12px;border-radius:8px;border:1px solid var(--border);">
      <span style="color:var(--text-dim);display:block;font-size:11px;text-transform:uppercase;">Belum Terhubung Pangkat</span>
      <strong style="color:<?= $dbStats['missing_pangkat'] ? 'var(--warn)' : 'var(--ok)' ?>;font-size:16px;"><?= $dbStats['missing_pangkat'] ?></strong>
    </div>
    <div style="background:var(--panel-2);padding:10px 12px;border-radius:8px;border:1px solid var(--border);">
      <span style="color:var(--text-dim);display:block;font-size:11px;text-transform:uppercase;">Belum Terhubung Korp</span>
      <strong style="color:<?= $dbStats['missing_korp'] ? 'var(--warn)' : 'var(--ok)' ?>;font-size:16px;"><?= $dbStats['missing_korp'] ?></strong>
    </div>
    <div style="background:var(--panel-2);padding:10px 12px;border-radius:8px;border:1px solid var(--border);">
      <span style="color:var(--text-dim);display:block;font-size:11px;text-transform:uppercase;">Belum Terhubung Satuan</span>
      <strong style="color:<?= $dbStats['missing_satuan'] ? 'var(--warn)' : 'var(--ok)' ?>;font-size:16px;"><?= $dbStats['missing_satuan'] ?></strong>
    </div>
  </div>
</div>

<!-- Aksi Utilitas Pemeliharaan -->
<div class="grid grid-2" style="margin-bottom:20px;">
  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <h4 style="margin:0 0 6px 0;">🔄 Sinkronisasi Relasi Otomatis</h4>
      <p style="font-size:13px;color:var(--text-dim);margin:0 0 16px 0;line-height:1.5;">
        Secara cerdas memetakan data teks lama personel (pangkat, korp, satuan, kotama) ke tabel relasional master rujukan dan mengisikan foreign keys yang belum terisi.
      </p>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="sync_relations">
      <button type="submit" class="btn" style="width:100%;padding:10px;">
        🚀 Jalankan Sinkronisasi Relasi Otomatis
      </button>
    </form>
  </div>

  <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
    <div>
      <h4 style="margin:0 0 6px 0;">✨ Harmonisasi Standar Teks</h4>
      <p style="font-size:13px;color:var(--text-dim);margin:0 0 16px 0;line-height:1.5;">
        Menyeragamkan penulisan string teks di tabel <code>personel</code> agar persis mengikuti nama resmi pada tabel master (menghilangkan salah ketik, huruf besar/kecil tidak beraturan).
      </p>
    </div>
    <form method="post" onsubmit="return confirm('Harmonisasikan seluruh teks personel dengan nama resmi master rujukan?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="harmonize_strings">
      <button type="submit" class="btn btn-outline" style="width:100%;padding:10px;border-color:var(--gold);color:var(--gold);">
        ✨ Harmonisasi Ejaan Teks Sekarang
      </button>
    </form>
  </div>
</div>

<!-- Tabel Diagnostik Personel Belum Terhubung Lengkap -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <div>
      <h4 style="margin:0;">Prajurit dengan Relasi Belum Lengkap</h4>
      <div style="font-size:12.5px;color:var(--text-dim);margin-top:2px;">
        Daftar personel yang memerlukan penyelarasan foreign key master rujukan.
      </div>
    </div>
  </div>

  <?php if (empty($unlinkedPersonel)): ?>
    <div style="text-align:center;padding:30px;color:var(--ok);">
      <div style="font-size:32px;margin-bottom:8px;">🎉</div>
      <strong>Luar Biasa! Semua personel telah terelasi secara alami 100% dengan data master rujukan.</strong>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>NRP / Nama</th>
            <th>Pangkat</th>
            <th>Korp</th>
            <th>Satuan</th>
            <th>Kotama</th>
            <th style="text-align:right;">Aksi Tautkan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($unlinkedPersonel as $up): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($up['nama']) ?></strong><br>
              <span style="font-family:monospace;font-size:12px;color:var(--text-dim);"><?= htmlspecialchars($up['nrp']) ?></span>
            </td>
            <td>
              <?php if ($up['pangkat_id']): ?>
                <span class="badge badge-approved">✓ <?= htmlspecialchars($up['pangkat']) ?></span>
              <?php else: ?>
                <span class="badge badge-rejected">✕ <?= htmlspecialchars($up['pangkat'] ?: 'Kosong') ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($up['golongan'] === 'PNS'): ?>
                <span class="badge badge-nonaktif">- (PNS Non-Korp)</span>
              <?php elseif ($up['korp_id']): ?>
                <span class="badge badge-approved">✓ <?= htmlspecialchars($up['korp']) ?></span>
              <?php else: ?>
                <span class="badge badge-rejected">✕ <?= htmlspecialchars($up['korp'] ?: 'Kosong') ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($up['satuan_id']): ?>
                <span class="badge badge-approved">✓ <?= htmlspecialchars($up['satuan']) ?></span>
              <?php else: ?>
                <span class="badge badge-rejected">✕ <?= htmlspecialchars($up['satuan'] ?: 'Kosong') ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($up['kotama_id']): ?>
                <span class="badge badge-approved">✓ <?= htmlspecialchars($up['kotama']) ?></span>
              <?php else: ?>
                <span class="badge badge-rejected">✕ <?= htmlspecialchars($up['kotama'] ?: 'Kosong') ?></span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <a href="<?= BASE_URL ?>/admin/personel_form.php?id=<?= $up['id'] ?>" class="btn btn-outline" style="padding:4px 10px;font-size:12px;">
                ✏️ Edit & Tautkan
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<script>
window.openModal = function(id) {
  var el = document.getElementById(id);
  if (el) el.classList.add('show');
};
window.closeModal = function(id) {
  var el = document.getElementById(id);
  if (el) el.classList.remove('show');
};
document.addEventListener('click', function(e) {
  var closeButton = e.target.closest('.modal-close');
  if (closeButton) {
    var modal = closeButton.closest('.modal-backdrop');
    if (modal) window.closeModal(modal.id);
    return;
  }

  if (e.target.classList.contains('modal-backdrop')) {
    window.closeModal(e.target.id);
  }
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    var modal = document.querySelector('.modal-backdrop.show');
    if (modal) window.closeModal(modal.id);
  }
});

// Edit Kotama Helper
function editKotama(k) {
  document.getElementById('editKotamaId').value = k.id;
  document.getElementById('editKotamaKode').value = k.kode;
  document.getElementById('editKotamaNama').value = k.nama;
  document.getElementById('editKotamaTipe').value = k.tipe;
  document.getElementById('editKotamaUrutan').value = k.urutan;
  openModal('modalKotamaEdit');
}

// Edit Satuan Helper
function editSatuan(s) {
  document.getElementById('editSatuanId').value = s.id;
  document.getElementById('editSatuanKotamaId').value = s.kotama_id || '';
  document.getElementById('editSatuanKode').value = s.kode;
  document.getElementById('editSatuanNama').value = s.nama;
  document.getElementById('editSatuanLokasi').value = s.lokasi || '';
  document.getElementById('editSatuanUrutan').value = s.urutan;
  openModal('modalSatuanEdit');
}

// Edit Pangkat Helper
function editPangkat(p) {
  document.getElementById('editPangkatId').value = p.id;
  document.getElementById('editPangkatGolongan').value = p.golongan;
  document.getElementById('editPangkatKode').value = p.kode;
  document.getElementById('editPangkatNama').value = p.nama;
  document.getElementById('editPangkatSingkatan').value = p.singkatan;
  document.getElementById('editPangkatBup').value = p.bup_usia;
  document.getElementById('editPangkatUrutan').value = p.urutan;
  openModal('modalPangkatEdit');
}

// Edit Korp Helper
function editKorp(k) {
  document.getElementById('editKorpId').value = k.id;
  document.getElementById('editKorpKode').value = k.kode;
  document.getElementById('editKorpNama').value = k.nama;
  document.getElementById('editKorpKategori').value = k.kategori;
  document.getElementById('editKorpUrutan').value = k.urutan;
  openModal('modalKorpEdit');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
