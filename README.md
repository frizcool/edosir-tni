# TRISULA TNI AD
**TRISULA** (*Tata Kelola Rekam Informasi, Sistematika, & Unduhan Lengkap Arsip*) — Sistem Manajemen Dosir Elektronik & Arsip Digital Personel TNI AD berbasis PHP native (PDO) + MySQL, tema militer (dark/light mode).

## 1. Fitur Utama
- **Registrasi & approval akun** — personel mendaftar mandiri, akun aktif setelah disetujui admin.
- **33 jenis Dosir wajib** (sesuai daftar baku) dengan penamaan file otomatis `NRP_KODE[abjad].pdf`
  dan folder fisik `uploads/FOLDER 01` s.d. `FOLDER 33`. Berkas kedua dst pada jenis dosir yang sama
  otomatis diberi akhiran abjad (a, b, c, ...).
- **Verifikasi berkas & TTE resmi** — setiap berkas berstatus `pending → approved/rejected`. Saat disetujui,
  sistem membersihkan watermark awal dan membubuhkan **Sertifikasi Tanda Tangan Elektronik (TTE)**,
  nomor kode unik, nilai hash SHA-256, dan QR Code keabsahan yang dapat dipindai publik (`verify.php`).
- **Verifikasi massal (Bulk Verification)** — admin dapat memilih banyak berkas sekaligus menggunakan
  checkbox dengan toolbar aksi massal untuk menyetujui (TTE massal) atau menolak massal dengan catatan dinas.
- **Paginasi optimal & terpadu** — komponen paginasi pintar dengan windowing ellipsis, tombol batas,
  pemilihan jumlah baris dinamis (per-page: 10, 25, 50, 100), dan retensi filter pencarian otomatis
  pada seluruh tabel data (Daftar Personel, Verifikasi Dosir, Kontrol Akun, Log Aktivitas).
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
- **Log aktivitas & audit trail** — seluruh aksi penting (login, upload, approval, verifikasi tunggal/massal, backup)
  tercatat secara lengkap di `activity_log`.

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

## 3. Panduan Instalasi & Hosting
1. Salin / clone repositori ke web server (Apache/Nginx/cPanel + PHP 8.1+, ekstensi `pdo_mysql`, `gd`, `zip`, `fileinfo` aktif).
2. Buat database baru di MySQL/phpMyAdmin, lalu impor skema:
   ```bash
   mysql -u root -p edosir_tni < sql/schema.sql
   ```
3. Konfigurasi koneksi database:
   - Salin `config/database.example.php` menjadi `config/database.local.php`, lalu sesuaikan kredensial database hosting Anda.
   - Atau atur via Environment Variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
   - Nilai `BASE_URL` telah diatur untuk **otomatis terdeteksi** (baik dijalankan di root domain hosting maupun di subfolder seperti `/edosir-tni`).
4. Kredensial Akun Administrator Awal:
   - **Username**: `admin`
   - **Password**: `Admin#12345`
   *(Segera ubah kata sandi administrator setelah login pertama kali di menu Pengaturan / Profil).*
5. Izin Direktori Penyimpanan:
   - Pastikan direktori `uploads/`, `uploads/raw/`, `uploads/foto_profil/`, `backups/`, dan `exports/` memiliki izin tulis oleh web server (`chmod -R 775`).
6. Ketergantungan PDF & TTE (Vendor):
   - Pustaka `setasign/fpdi` dan `tecnickcom/tcpdf` sudah terpasang di folder `vendor/` untuk penerbitan Tanda Tangan Elektronik (TTE) & QR Code.
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
