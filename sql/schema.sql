-- =====================================================================
-- TRISULA TNI AD - SKEMA BASIS DATA RELASIONAL ALAMI (NATURAL RDBMS)
-- Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip
-- =====================================================================
CREATE DATABASE IF NOT EXISTS edosir_tni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edosir_tni;

SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------
-- 1. MASTER 33 JENIS DOSIR BAKU TNI AD (Tabel Referensi Mandiri)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dosir_master (
  kode VARCHAR(2) PRIMARY KEY,        -- '01'..'33'
  nama_dosir VARCHAR(180) NOT NULL,
  urutan INT NOT NULL,
  wajib TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO dosir_master (kode, nama_dosir, urutan) VALUES
('01','SURAT LAMARAN',1),
('02','AKTE KELAHIRAN/KENAL YBS',2),
('03','AKTE KELAHIRAN/KENAL ANAK',3),
('04','DAFTAR RIWAYAT HIDUP',4),
('05','IJASAH STTB (DIKUM)',5),
('06','SURAT KETERANGAN DARI KEPOLISIAN',6),
('07','KEP PENGANGKATAN PERTAMA',7),
('08','KEP PENETAPAN GAJI (INPASSING)',8),
('09','KPI/KPS',9),
('10','KEP PENEMPATAN DALAM JABATAN',10),
('11','KARTU ASABRI',11),
('12','BERITA ACARA SUMPAH PRAJURIT',12),
('13','KARTU DAKTILOSKOPI',13),
('14','SURAT IJIN NIKAH, FC AKTA NIKAH',14),
('15','KEPUTUSAN TAHORNEG',15),
('16','IJASAH DIKMIL/SAR/TUK/CAB',16),
('17','SKEP/KEP IKATAN DINAS',17),
('18','SPRIN PENUGASAN/PENGEMBALIAN DARI TUGAS DALAM/LUAR NEGERI',18),
('19','KEP ALIH STATUS PRAJURIT WAJIB MENJADI SUKARELA',19),
('20','KEP/SPRIN PINDAH KECABANGAN',20),
('21','KEP PEMBERIAN HUKUMAN',21),
('22','LAPORAN PENGEMBANGAN DIRI',22),
('23','SURAT KEMATIAN ISTRI/SUAMI/ANAK',23),
('24','KEP PEMBERHENTIAN SEMENTARA DARI JABATAN (SKORSING)',24),
('25','KEP PENGANGKATAN KEMBALI DALAM JABATAN',25),
('26','KEP PERUBAHAN NAMA YBS/PERNYATAAN PERUBAHAN NAMA ISTRI/SUAMI/ANAK',26),
('27','KEP TAMBAH GELAR',27),
('28','KEP PINDAH AGAMA',28),
('29','KEP KENAIKAN/PENURUNAN PANGKAT',29),
('30','KEP RALAT',30),
('31','KEP PENSIUN',31),
('32','DOKUMEN AUTENTIK LAIN',32),
('33','FOTO',33)
ON DUPLICATE KEY UPDATE nama_dosir = VALUES(nama_dosir), urutan = VALUES(urutan);

-- ---------------------------------------------------------------------
-- 2. MASTER KOTAMA & BALAKPUS (Komando Utama & Badan Pelaksana Pusat)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_kotama (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) UNIQUE NOT NULL,
  nama VARCHAR(150) NOT NULL,
  tipe ENUM('Kotamaops', 'Kotamabin', 'Balakpus', 'Mabesad') NOT NULL DEFAULT 'Kotamabin',
  urutan INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO master_kotama (kode, nama, tipe, urutan) VALUES
('MABESAD', 'Mabes TNI AD', 'Mabesad', 1),
('KOSTRAD', 'Komando Cadangan Strategis AD (Kostrad)', 'Kotamaops', 2),
('KOPASSUS', 'Komando Pasukan Khusus (Kopassus)', 'Kotamaops', 3),
('KODIKLATAD', 'Kodiklat TNI AD', 'Kotamabin', 4),
('PUSZIAD', 'Pusat Zeni TNI AD (Pusziad)', 'Balakpus', 5),
('PUSPOMAD', 'Pusat Polisi Militer AD (Puspomad)', 'Balakpus', 6),
('PUSHUBAD', 'Pusat Perhubungan AD (Pushubad)', 'Balakpus', 7),
('PUSPALAD', 'Pusat Peralatan AD (Puspalad)', 'Balakpus', 8),
('PUSBEKANGAD', 'Pusat Pembekalan Angkutan AD (Pusbekangad)', 'Balakpus', 9),
('PUSKESAD', 'Pusat Kesehatan AD (Puskesad)', 'Balakpus', 10),
('PUSPENERBAD', 'Pusat Penerbangan AD (Puspenerbad)', 'Balakpus', 11),
('KODAM-I', 'Kodam I/Bukit Barisan', 'Kotamabin', 12),
('KODAM-II', 'Kodam II/Sriwijaya', 'Kotamabin', 13),
('KODAM-III', 'Kodam III/Siliwangi', 'Kotamabin', 14),
('KODAM-IV', 'Kodam IV/Diponegoro', 'Kotamabin', 15),
('KODAM-V', 'Kodam V/Brawijaya', 'Kotamabin', 16),
('KODAM-VI', 'Kodam VI/Mulawarman', 'Kotamabin', 17),
('KODAM-IX', 'Kodam IX/Udayana', 'Kotamabin', 18),
('KODAM-XII', 'Kodam XII/Tanjungpura', 'Kotamabin', 19),
('KODAM-XIII', 'Kodam XIII/Merdeka', 'Kotamabin', 20),
('KODAM-XIV', 'Kodam XIV/Hasanuddin', 'Kotamabin', 21),
('KODAM-XV', 'Kodam XV/Pattimura', 'Kotamabin', 22),
('KODAM-XVII', 'Kodam XVII/Cenderawasih', 'Kotamabin', 23),
('KODAM-XVIII', 'Kodam XVIII/Kasuari', 'Kotamabin', 24),
('KODAM-JAYA', 'Kodam Jaya/Jayakarta', 'Kotamabin', 25),
('KODAM-IM', 'Kodam Iskandar Muda', 'Kotamabin', 26)
ON DUPLICATE KEY UPDATE nama = VALUES(nama), tipe = VALUES(tipe), urutan = VALUES(urutan);

-- ---------------------------------------------------------------------
-- 3. MASTER SATUAN ORGANIK (Relasi Natural Hierarkis ke Kotama)
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 4. MASTER PANGKAT (Jenjang Baku & Batas Usia Pensiun TNI AD)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_pangkat (
  id INT AUTO_INCREMENT PRIMARY KEY,
  golongan ENUM('Perwira', 'Bintara', 'Tamtama', 'PNS') NOT NULL,
  kode VARCHAR(30) UNIQUE NOT NULL,
  nama VARCHAR(60) NOT NULL,
  singkatan VARCHAR(20) NOT NULL,
  urutan INT NOT NULL,
  bup_usia INT NOT NULL DEFAULT 56,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO master_pangkat (golongan, kode, nama, singkatan, urutan, bup_usia) VALUES
('Tamtama', 'PRADA', 'Prajurit Dua', 'Prada', 1, 56),
('Tamtama', 'PRATU', 'Prajurit Satu', 'Pratu', 2, 56),
('Tamtama', 'PRAKA', 'Prajurit Kepala', 'Praka', 3, 56),
('Tamtama', 'KOPDA', 'Kopral Dua', 'Kopda', 4, 56),
('Tamtama', 'KOPTU', 'Kopral Satu', 'Koptu', 5, 56),
('Tamtama', 'KOPKA', 'Kopral Kepala', 'Kopka', 6, 56),
('Bintara', 'SERDA', 'Sersan Dua', 'Serda', 7, 56),
('Bintara', 'SERTU', 'Sersan Satu', 'Sertu', 8, 56),
('Bintara', 'SERKA', 'Sersan Kepala', 'Serka', 9, 56),
('Bintara', 'SERMA', 'Sersan Mayor', 'Serma', 10, 56),
('Bintara', 'PELDA', 'Pembantu Letnan Dua', 'Pelda', 11, 56),
('Bintara', 'PELTU', 'Pembantu Letnan Satu', 'Peltu', 12, 56),
('Perwira', 'LETDA', 'Letnan Dua', 'Letda', 13, 58),
('Perwira', 'LETTU', 'Letnan Satu', 'Lettu', 14, 58),
('Perwira', 'KAPTEN', 'Kapten', 'Kapten', 15, 58),
('Perwira', 'MAYOR', 'Mayor', 'Mayor', 16, 58),
('Perwira', 'LETKOL', 'Letnan Kolonel', 'Letkol', 17, 58),
('Perwira', 'KOLONEL', 'Kolonel', 'Kolonel', 18, 58),
('Perwira', 'BRIGJEN', 'Brigadir Jenderal TNI', 'Brigjen TNI', 19, 58),
('Perwira', 'MAYJEN', 'Mayor Jenderal TNI', 'Mayjen TNI', 20, 58),
('Perwira', 'LETJEN', 'Letnan Jenderal TNI', 'Letjen TNI', 21, 58),
('Perwira', 'JENDERAL', 'Jenderal TNI', 'Jenderal TNI', 22, 58),
('PNS', 'PENGATUR_MUDA_IIA', 'Pengatur Muda (II/a)', 'II/a', 30, 60),
('PNS', 'PENGATUR_MUDA_TK_IIB', 'Pengatur Muda Tk. I (II/b)', 'II/b', 31, 60),
('PNS', 'PENGATUR_IIC', 'Pengatur (II/c)', 'II/c', 32, 60),
('PNS', 'PENGATUR_TK_IID', 'Pengatur Tk. I (II/d)', 'II/d', 33, 60),
('PNS', 'PENATA_MUDA_IIIA', 'Penata Muda (III/a)', 'III/a', 34, 60),
('PNS', 'PENATA_MUDA_TK_IIIB', 'Penata Muda Tk. I (III/b)', 'III/b', 35, 60),
('PNS', 'PENATA_IIIC', 'Penata (III/c)', 'III/c', 36, 60),
('PNS', 'PENATA_TK_IIID', 'Penata Tk. I (III/d)', 'III/d', 37, 60),
('PNS', 'PEMBINA_IVA', 'Pembina (IV/a)', 'IV/a', 38, 60),
('PNS', 'PEMBINA_TK_IVB', 'Pembina Tk. I (IV/b)', 'IV/b', 39, 60),
('PNS', 'PEMBINA_UTAMA_MUDA_IVC', 'Pembina Utama Muda (IV/c)', 'IV/c', 40, 60),
('PNS', 'PEMBINA_UTAMA_MADYA_IVD', 'Pembina Utama Madya (IV/d)', 'IV/d', 41, 60),
('PNS', 'PEMBINA_UTAMA_IVE', 'Pembina Utama (IV/e)', 'IV/e', 42, 60)
ON DUPLICATE KEY UPDATE golongan = VALUES(golongan), nama = VALUES(nama), singkatan = VALUES(singkatan), urutan = VALUES(urutan), bup_usia = VALUES(bup_usia);

-- ---------------------------------------------------------------------
-- 5. MASTER KORP KECABANGAN TNI AD
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_korp (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(15) UNIQUE NOT NULL,
  nama VARCHAR(80) NOT NULL,
  kategori ENUM('Tempur', 'Bantuan Tempur', 'Bantuan Administrasi', 'Penerbad') NOT NULL DEFAULT 'Tempur',
  urutan INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO master_korp (kode, nama, kategori, urutan) VALUES
('Inf', 'Infanteri', 'Tempur', 1),
('Kav', 'Kavaleri', 'Tempur', 2),
('Arm', 'Artileri Medan', 'Tempur', 3),
('Arh', 'Artileri Pertahanan Udara', 'Tempur', 4),
('Czi', 'Zeni', 'Bantuan Tempur', 5),
('Chb', 'Perhubungan', 'Bantuan Tempur', 6),
('Cpal', 'Peralatan', 'Bantuan Tempur', 7),
('Cba', 'Pembekalan Angkutan', 'Bantuan Tempur', 8),
('Cpm', 'Polisi Militer', 'Bantuan Administrasi', 9),
('Caj', 'Ajudan Jenderal', 'Bantuan Administrasi', 10),
('Ckm', 'Kesehatan Militer', 'Bantuan Administrasi', 11),
('Cku', 'Keuangan', 'Bantuan Administrasi', 12),
('Chk', 'Hukum', 'Bantuan Administrasi', 13),
('Ctp', 'Topografi', 'Bantuan Administrasi', 14),
('Cpn', 'Penerbangan Angkatan Darat', 'Penerbad', 15)
ON DUPLICATE KEY UPDATE nama = VALUES(nama), kategori = VALUES(kategori), urutan = VALUES(urutan);

-- ---------------------------------------------------------------------
-- 6. PERSONEL (Entitas Pokok Prajurit & PNS TNI AD)
--    Relasi Natural Relasional:
--    - pangkat_id -> master_pangkat.id
--    - korp_id    -> master_korp.id
--    - satuan_id  -> master_satuan.id
--    - kotama_id  -> master_kotama.id
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS personel (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nrp VARCHAR(20) UNIQUE NOT NULL,
  nama VARCHAR(120) NOT NULL,
  golongan ENUM('Perwira','Bintara','Tamtama','PNS') NOT NULL,
  pangkat_id INT NULL,
  pangkat VARCHAR(50) NULL,
  korp_id INT NULL,
  korp VARCHAR(50) NULL,
  satuan_id INT NULL,
  satuan VARCHAR(150) NULL,
  kotama_id INT NULL,
  kotama VARCHAR(150) NULL,
  jabatan VARCHAR(150) NULL,
  tmt_jabatan DATE NULL,
  tmt_pangkat DATE NULL,
  tanggal_lahir DATE NULL,
  tempat_lahir VARCHAR(100) NULL,
  jenis_kelamin ENUM('L','P') DEFAULT 'L',
  agama VARCHAR(30) NULL,
  status_kawin VARCHAR(30) NULL,
  alamat TEXT NULL,
  no_hp VARCHAR(20) NULL,
  email VARCHAR(100) NULL,
  foto VARCHAR(255) NULL,
  status_dinas ENUM('Aktif','Pensiun','Meninggal','Pindah') DEFAULT 'Aktif',
  tmt_pensiun_proyeksi DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_personel_pangkat FOREIGN KEY (pangkat_id) REFERENCES master_pangkat(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_personel_korp FOREIGN KEY (korp_id) REFERENCES master_korp(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_personel_satuan FOREIGN KEY (satuan_id) REFERENCES master_satuan(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_personel_kotama FOREIGN KEY (kotama_id) REFERENCES master_kotama(id) ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_personel_satuan (satuan),
  INDEX idx_personel_gol (golongan),
  INDEX idx_personel_status_dinas (status_dinas),
  INDEX idx_personel_pensiun (tmt_pensiun_proyeksi),
  INDEX idx_personel_tmt_jab (tmt_jabatan)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. USERS (Akun Pengguna Sistem - Relasi Natural 1:1 ke Personel)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','personel') NOT NULL DEFAULT 'personel',
  personel_id INT NULL,
  status ENUM('pending','approved','rejected','nonaktif') NOT NULL DEFAULT 'pending',
  catatan_approval TEXT NULL,
  last_login DATETIME NULL,
  theme_pref ENUM('dark','light') DEFAULT 'dark',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_personel (personel_id),
  CONSTRAINT fk_users_personel 
    FOREIGN KEY (personel_id) REFERENCES personel(id) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. DOSIR_FILES (Metadata Arsip Berkas Digital & Pengesahan TTE)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dosir_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  personel_id INT NOT NULL,
  dosir_kode VARCHAR(2) NOT NULL,
  abjad VARCHAR(1) NULL,               -- NULL untuk file pertama, 'a','b','c'... untuk berikutnya
  file_name VARCHAR(150) NOT NULL,     -- nama file fisik, misal 21110005656_05a.pdf
  file_path VARCHAR(255) NOT NULL,     -- path relatif dari root uploads/
  raw_file_path VARCHAR(255) NULL,     -- path file master bersih (sebelum watermark/tte)
  original_name VARCHAR(255) NULL,
  keterangan VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  catatan_verifikasi TEXT NULL,
  is_watermarked TINYINT(1) DEFAULT 0,
  signature_code VARCHAR(60) NULL,     -- kode TTE resmi misal TTE-TRISULA-2026-XXXX
  signature_hash VARCHAR(64) NULL,     -- SHA256 hash dokumen output PDF ber-TTE resmi
  raw_hash VARCHAR(64) NULL,           -- SHA256 hash dokumen master bersih (original warkat)
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_by INT NULL,
  verified_at DATETIME NULL,
  UNIQUE KEY uniq_dosir_slot (personel_id, dosir_kode, abjad),
  INDEX idx_dosir_status (status),
  INDEX idx_dosir_sigcode (signature_code),
  CONSTRAINT fk_files_personel 
    FOREIGN KEY (personel_id) REFERENCES personel(id) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_files_master 
    FOREIGN KEY (dosir_kode) REFERENCES dosir_master(kode) 
    ON UPDATE CASCADE,
  CONSTRAINT fk_files_uploader 
    FOREIGN KEY (uploaded_by) REFERENCES users(id) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_files_verifier 
    FOREIGN KEY (verified_by) REFERENCES users(id) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. ACTIVITY_LOG (Audit Trail Aktivitas Sistem)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_act_user (user_id),
  INDEX idx_act_created (created_at),
  INDEX idx_act_action (action),
  CONSTRAINT fk_activity_user 
    FOREIGN KEY (user_id) REFERENCES users(id) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. BACKUP_LOG (Log Riwayat Cadangan Data)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS backup_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(150) NOT NULL,
  filepath VARCHAR(255) NOT NULL,
  filesize BIGINT NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_backup_user 
    FOREIGN KEY (created_by) REFERENCES users(id) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. LOGIN_ATTEMPTS (Pencegahan Serangan Brute Force)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  username VARCHAR(50) NOT NULL,
  attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_address, attempt_time),
  INDEX idx_user_time (username, attempt_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. SETTINGS (Pengaturan Konfigurasi & Identitas Sistem)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value TEXT NULL,
  setting_group VARCHAR(50) DEFAULT 'general',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('app_name', 'TRISULA TNI AD', 'general'),
('app_subtitle', 'Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip', 'general'),
('app_brand_title', 'TRISULA', 'general'),
('app_brand_sub', 'TNI AD', 'general'),
('instansi', 'TNI Angkatan Darat', 'general'),
('watermark_text', 'TRISULA TERVERIFIKASI', 'dosir'),
('batas_tahun_jabatan', '2', 'dosir'),
('pejabat_nama', 'HENDRA PRATAMA, S.I.P.', 'laporan'),
('pejabat_pangkat', 'MAYOR INF', 'laporan'),
('pejabat_nrp', '11040023450682', 'laporan'),
('pejabat_jabatan', 'Perwira Personel / Verifikator', 'laporan'),
('session_timeout_minutes', '30', 'security')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ---------------------------------------------------------------------
-- AKUN ADMINISTRATOR DEFAULT AWAL (Bila Belum Ada)
-- username: admin / password hash: Admin#12345 (bcrypt)
-- ---------------------------------------------------------------------
INSERT INTO users (username, password, role, status)
SELECT 'admin', '$2y$10$92Iun1J0v8H4kU8s5m3zVeQyQwq2mQ0m3E4kzYQxK9c1yA9m4Kx1S', 'admin', 'approved'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

SET foreign_key_checks = 1;
