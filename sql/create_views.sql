-- =====================================================================
-- TRISULA TNI AD - PEMBUATAN DATABASE VIEW v_personel_lengkap
-- Jalankan skrip ini di phpMyAdmin cPanel jika diperlukan
-- =====================================================================

CREATE OR REPLACE VIEW v_personel_lengkap AS
SELECT 
    p.id,
    p.nrp,
    p.nama,
    p.pangkat_id,
    mp.kode AS pangkat_kode,
    mp.nama AS pangkat_nama,
    mp.singkatan AS pangkat,
    mp.singkatan AS pangkat_singkatan,
    mp.golongan,
    mp.golongan AS pangkat_golongan,
    mp.bup_usia,
    p.korp_id,
    mk.kode AS korp,
    mk.kode AS korp_kode,
    mk.nama AS korp_nama,
    mk.kategori AS korp_kategori,
    p.satuan_id,
    ms.kode AS satuan_kode,
    ms.nama AS satuan,
    ms.nama AS satuan_nama,
    ms.lokasi AS satuan_lokasi,
    COALESCE(ms.kotama_id, p.kotama_id) AS kotama_id,
    mkot.kode AS kotama_kode,
    mkot.nama AS kotama,
    mkot.nama AS kotama_nama,
    mkot.tipe AS kotama_tipe,
    p.jabatan,
    p.tmt_jabatan,
    p.tmt_pangkat,
    p.tanggal_lahir,
    p.tempat_lahir,
    p.jenis_kelamin,
    p.agama,
    p.status_kawin,
    p.alamat,
    p.no_hp,
    p.email,
    p.foto,
    p.status_dinas,
    p.tmt_pensiun_proyeksi,
    u.id AS user_id,
    u.username,
    u.role AS user_role,
    u.status AS user_status,
    u.last_login,
    p.created_at,
    p.updated_at
FROM personel p
LEFT JOIN master_pangkat mp ON p.pangkat_id = mp.id
LEFT JOIN master_korp mk ON p.korp_id = mk.id
LEFT JOIN master_satuan ms ON p.satuan_id = ms.id
LEFT JOIN master_kotama mkot ON COALESCE(ms.kotama_id, p.kotama_id) = mkot.id
LEFT JOIN users u ON u.personel_id = p.id;
