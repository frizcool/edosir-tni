-- =====================================================================
-- E-DOSIR TNI AD - SKEMA DATABASE
-- =====================================================================
CREATE DATABASE IF NOT EXISTS edosir_tni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edosir_tni;

-- ---------------------------------------------------------------------
-- USERS (akun login) - role: admin / personel
-- ---------------------------------------------------------------------
CREATE TABLE users (
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PERSONEL (data induk personel)
-- ---------------------------------------------------------------------
CREATE TABLE personel (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nrp VARCHAR(20) UNIQUE NOT NULL,
  nama VARCHAR(120) NOT NULL,
  golongan ENUM('Perwira','Bintara','Tamtama','PNS') NOT NULL,
  pangkat VARCHAR(50) NULL,
  korp VARCHAR(50) NULL,
  satuan VARCHAR(150) NULL,
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
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE users
  ADD CONSTRAINT fk_users_personel FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- MASTER 33 JENIS DOSIR
-- ---------------------------------------------------------------------
CREATE TABLE dosir_master (
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
('33','FOTO',33);

-- ---------------------------------------------------------------------
-- FILE DOSIR YANG DIUNGGAH
-- penamaan file: {nrp}_{kode}{abjad}.pdf  -> disimpan di uploads/FOLDER {kode}/
-- ---------------------------------------------------------------------
CREATE TABLE dosir_files (
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
  signature_code VARCHAR(60) NULL,     -- kode TTE resmi misal TTE-TNIAD-2026-XXXX
  signature_hash VARCHAR(64) NULL,     -- SHA256 hash dokumen terverifikasi
  uploaded_by INT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_by INT NULL,
  verified_at DATETIME NULL,
  FOREIGN KEY (personel_id) REFERENCES personel(id) ON DELETE CASCADE,
  FOREIGN KEY (dosir_kode) REFERENCES dosir_master(kode),
  UNIQUE KEY uniq_dosir_slot (personel_id, dosir_kode, abjad)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LOG PERCOBAAN LOGIN (Rate Limiting & Keamanan)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  username VARCHAR(50) NOT NULL,
  attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_address, attempt_time),
  INDEX idx_user_time (username, attempt_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LOG AKTIVITAS (audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  aktivitas VARCHAR(150) NOT NULL,
  keterangan TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- LOG BACKUP
-- ---------------------------------------------------------------------
CREATE TABLE backup_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_name VARCHAR(255) NOT NULL,
  size_bytes BIGINT DEFAULT 0,
  jenis ENUM('database','files','full') DEFAULT 'full',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- PENGATURAN APLIKASI (SETTINGS)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value TEXT NULL,
  setting_group VARCHAR(50) DEFAULT 'general',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('app_name', 'E-DOSIR TNI AD', 'general'),
('app_subtitle', 'Sistem Dosir Elektronik Personel', 'general'),
('app_brand_title', 'E-DOSIR', 'general'),
('app_brand_sub', 'TNI AD', 'general'),
('instansi', 'TNI Angkatan Darat', 'general'),
('watermark_text', 'E-DOSIR TERVERIFIKASI', 'dosir'),
('batas_tahun_jabatan', '2', 'dosir');

-- ---------------------------------------------------------------------
-- AKUN ADMIN DEFAULT (username: admin / password: Admin#12345 -- GANTI SEGERA)
-- hash di bawah untuk 'Admin#12345' (bcrypt)
-- ---------------------------------------------------------------------
INSERT INTO users (username, password, role, status) VALUES
('admin', '$2y$10$92Iun1J0v8H4kU8s5m3zVeQyQwq2mQ0m3E4kzYQxK9c1yA9m4Kx1S', 'admin', 'approved');
-- CATATAN: hash contoh di atas belum tentu valid untuk instalasi Anda.
-- Jalankan generate_admin_password.php (disertakan) untuk membuat hash yang benar, lalu UPDATE baris ini.
