# TRISULA TNI AD
**TRISULA** (*Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip*) — Sistem Manajemen Dosir Elektronik & Arsip Digital Personel TNI AD berbasis PHP native (PDO) + MySQL, tema militer (dark/light mode).

## 1. Fitur Utama
- **Registrasi & approval akun** — personel mendaftar mandiri, akun aktif setelah disetujui admin.
- **33 jenis Dosir wajib** (sesuai daftar baku) dengan penamaan file otomatis `NRP_KODE[abjad].pdf`
  dan folder fisik `uploads/FOLDER 01` s.d. `FOLDER 33`. Berkas kedua dst pada jenis dosir yang sama
  otomatis diberi akhiran abjad (a, b, c, ...).
- **Verifikasi berkas oleh admin** — setiap berkas berstatus `pending → approved/rejected`. Saat disetujui,
  sistem otomatis menambahkan **watermark "TERVERIFIKASI"** pada PDF (butuh library FPDI+TCPDF, lihat §4).
- **Dashboard kelengkapan** — personel hanya melihat dosirnya sendiri beserta persentase kelengkapan
  (jumlah jenis dosir approved / 33). Admin melihat seluruh personel + rata-rata kelengkapan satuan.
- **Prediksi pensiun otomatis**: Perwira 58 th, Bintara/Tamtama 56 th, PNS 60 th — dihitung dari tanggal lahir.
- **Deteksi jabatan > 2 tahun** — daftar kandidat rotasi jabatan di dashboard admin.
- **Scan dokumen via kamera** (HP/PC) — hasil foto otomatis digabung menjadi satu berkas PDF (jsPDF) lalu
  diunggah seperti upload biasa.
- **Role-based access** — role `admin` dan `personel`, dengan pemisahan tegas hak akses di setiap halaman.
- **Unduh massal (bulk download)** — admin memilih checklist jenis dosir + cakupan personel/satuan, sistem
  menghasilkan satu berkas ZIP terstruktur per personel.
- **Laporan resmi** — laporan kelengkapan dosir, proyeksi pensiun, dan lama jabatan; siap cetak (CSS print)
  dengan kop dan kolom tanda tangan.
- **Backup data** — backup database (mysqldump) dan/atau seluruh berkas dosir (ZIP), tercatat di riwayat backup.
- **Tema militer dark/light mode** — toggle tersimpan di localStorage pengguna.
- **Log aktivitas** — seluruh aksi penting (login, upload, approval, verifikasi, backup) tercatat di `activity_log`.

## 2. Struktur Folder
```
edosir-tni/
├── admin/              halaman khusus admin
├── personel/            halaman khusus personel
├── includes/            fungsi bersama (auth, functions, watermark, header/footer)
├── config/               konfigurasi DB & aplikasi
├── assets/               css & js (tema, scan kamera)
├── sql/schema.sql        skema database + seed 33 master dosir
├── uploads/FOLDER 01../33   berkas dosir personel (nama: NRP_KODE.pdf)
├── backups/               hasil backup database & berkas
├── exports/                hasil unduhan massal (zip sementara)
└── composer.json          dependensi watermark PDF (FPDI + TCPDF)
```

## 3. Instalasi
1. Salin seluruh folder ke web server (Apache/Nginx + PHP 8+, ekstensi `pdo_mysql`, `zip`, `fileinfo` aktif).
2. Buat database dan import skema:
   ```
   mysql -u root -p < sql/schema.sql
   ```
3. Sesuaikan kredensial di `config/database.php` dan nilai `BASE_URL` di `config/config.php`
   (path instalasi relatif, contoh `/edosir-tni`).
4. Buat password admin awal yang valid:
   ```
   php generate_admin_password.php Admin#12345Baru
   ```
   lalu jalankan query `UPDATE` yang ditampilkan agar akun `admin` bisa login.
5. Pastikan folder `uploads/`, `backups/`, `exports/` dapat ditulis oleh web server (`chmod 775`).
6. (Opsional, untuk watermark otomatis) pasang dependensi:
   ```
   composer require setasign/fpdi tecnickcom/tcpdf
   ```
   Tanpa langkah ini, verifikasi tetap berjalan normal namun watermark visual pada PDF tidak diterapkan.
7. (Opsional, untuk backup database via tombol admin) pastikan binary `mysqldump` tersedia di PATH server
   dan fungsi `exec()` tidak dinonaktifkan di `php.ini`.

## 4. Alur Kerja Ringkas
1. Personel registrasi mandiri → status akun `pending`.
2. Admin menyetujui akun di **Approval Akun**.
3. Personel login, mengunggah 33 dosir (atau scan via kamera) → status berkas `pending`.
4. Admin memverifikasi tiap berkas di **Verifikasi Dosir** → `approved` (watermark otomatis) / `rejected`.
5. Dashboard personel & admin menampilkan persentase kelengkapan real-time.
6. Admin dapat mengunduh massal, mencetak laporan, dan menjadwalkan backup rutin.

## 5. Keamanan
- Password di-hash dengan `password_hash()` (bcrypt).
- Folder `uploads/`, `backups/`, `exports/` diberi `.htaccess` yang memblokir eksekusi skrip.
- Seluruh query menggunakan prepared statement (PDO) untuk mencegah SQL injection.
- Disarankan menjalankan aplikasi di atas HTTPS dan membatasi akses jaringan internal (VPN/Intranet Kotama).

## 6. Pengembangan Lanjutan (saran)
- Tambahkan autentikasi dua faktor (OTP) untuk akun admin.
- Integrasi Single Sign-On dengan sistem SIP/SIMAK TNI AD bila tersedia.
- Notifikasi email/WhatsApp saat berkas disetujui/ditolak.
- Penjadwalan backup otomatis via cron (`php admin/backup.php` dapat diadaptasi menjadi skrip CLI).
