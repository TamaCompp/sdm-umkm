-- =====================================================================
-- Sistem Informasi Manajemen SDM UMKM
-- dengan Penggajian Fleksibel dan Pencatatan Kasbon
-- Contoh UMKM: Konveksi Berkah (usaha jahit pakaian)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_sdm_umkm;
USE db_sdm_umkm;

-- ---------------------------------------------------------------------
-- 1. users: akun login untuk pemilik dan karyawan
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id_user    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,              -- hasil password_hash()
    role       ENUM('pemilik', 'karyawan') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- 2. karyawan: data kepegawaian (1 karyawan = 1 akun users)
-- ---------------------------------------------------------------------
CREATE TABLE karyawan (
    id_karyawan   INT AUTO_INCREMENT PRIMARY KEY,
    id_user       INT NOT NULL UNIQUE,
    nama          VARCHAR(100) NOT NULL,
    no_hp         VARCHAR(20),
    alamat        TEXT,
    tipe_upah     ENUM('bulanan', 'harian', 'borongan') NOT NULL,
    nilai_upah    DECIMAL(12,2) NOT NULL,          -- per bulan / per hari / per unit
    foto          VARCHAR(255),
    tanggal_masuk DATE NOT NULL,
    status        ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 3. absensi: 1 baris per karyawan per hari
-- ---------------------------------------------------------------------
CREATE TABLE absensi (
    id_absensi  INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan INT NOT NULL,
    tanggal     DATE NOT NULL,
    jam_masuk   TIME,
    jam_pulang  TIME,
    jumlah_unit INT NOT NULL DEFAULT 0,            -- hanya dipakai karyawan borongan
    status      ENUM('hadir', 'izin', 'sakit') NOT NULL DEFAULT 'hadir',
    UNIQUE KEY satu_absen_per_hari (id_karyawan, tanggal),
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 4. kasbon: pinjaman gaji di muka, dicicil dari gaji
-- ---------------------------------------------------------------------
CREATE TABLE kasbon (
    id_kasbon         INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan       INT NOT NULL,
    tanggal_pengajuan DATE NOT NULL,
    jumlah            DECIMAL(12,2) NOT NULL,
    cicilan           DECIMAL(12,2) NOT NULL,      -- potongan per gajian
    sisa              DECIMAL(12,2) NOT NULL,      -- yang belum dibayar
    alasan            TEXT,
    status            ENUM('menunggu', 'disetujui', 'ditolak', 'lunas') NOT NULL DEFAULT 'menunggu',
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 5. penggajian: hasil hitung gaji per periode (disimpan, tidak dihitung ulang)
-- ---------------------------------------------------------------------
CREATE TABLE penggajian (
    id_gaji         INT AUTO_INCREMENT PRIMARY KEY,
    id_karyawan     INT NOT NULL,
    periode_awal    DATE NOT NULL,
    periode_akhir   DATE NOT NULL,
    tipe_upah       ENUM('bulanan', 'harian', 'borongan') NOT NULL,  -- salinan saat dihitung
    nilai_upah      DECIMAL(12,2) NOT NULL,                          -- salinan saat dihitung
    hari_hadir      INT NOT NULL DEFAULT 0,
    total_unit      INT NOT NULL DEFAULT 0,
    gaji_kotor      DECIMAL(12,2) NOT NULL,
    potongan_kasbon DECIMAL(12,2) NOT NULL DEFAULT 0,
    gaji_bersih     DECIMAL(12,2) NOT NULL,
    tanggal_dibuat  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_karyawan) REFERENCES karyawan(id_karyawan) ON DELETE CASCADE
);

-- =====================================================================
-- DATA CONTOH
-- Password akun pemilik : pemilik123
-- Password semua karyawan: karyawan123
-- =====================================================================

INSERT INTO users (id_user, username, password, role) VALUES
(1, 'pemilik', '$2y$10$zB/w26pZeSjD3R.7qctn7OUmZugqAhZqgT1ivgNd0Vf0c4VNud3oq', 'pemilik'),
(2, 'budi',    '$2y$10$26VdV8sSfP9jZ.YDuYgrSOPaF8FEw4S6BjgfdsFwAtLny4455fRRS', 'karyawan'),
(3, 'siti',    '$2y$10$26VdV8sSfP9jZ.YDuYgrSOPaF8FEw4S6BjgfdsFwAtLny4455fRRS', 'karyawan'),
(4, 'andi',    '$2y$10$26VdV8sSfP9jZ.YDuYgrSOPaF8FEw4S6BjgfdsFwAtLny4455fRRS', 'karyawan'),
(5, 'rina',    '$2y$10$26VdV8sSfP9jZ.YDuYgrSOPaF8FEw4S6BjgfdsFwAtLny4455fRRS', 'karyawan'),
(6, 'dedi',    '$2y$10$26VdV8sSfP9jZ.YDuYgrSOPaF8FEw4S6BjgfdsFwAtLny4455fRRS', 'karyawan');

INSERT INTO karyawan (id_karyawan, id_user, nama, no_hp, alamat, tipe_upah, nilai_upah, tanggal_masuk, status) VALUES
(1, 2, 'Budi Santoso',  '081234567801', 'Jl. Kaliurang Km 5, Sleman', 'bulanan',  3000000, '2024-03-01', 'aktif'),
(2, 3, 'Siti Aminah',   '081234567802', 'Jl. Gejayan No. 12, Sleman', 'harian',    100000, '2025-01-15', 'aktif'),
(3, 4, 'Andi Pratama',  '081234567803', 'Jl. Seturan Raya, Sleman',   'borongan',    5000, '2025-06-10', 'aktif'),
(4, 5, 'Rina Wulandari','081234567804', 'Jl. Babarsari No. 8, Sleman','borongan',    5000, '2025-08-01', 'aktif'),
(5, 6, 'Dedi Kurniawan','081234567805', 'Jl. Monjali No. 3, Sleman',  'harian',     90000, '2024-11-01', 'nonaktif');

-- Absensi minggu lalu (Senin 16 s.d. Sabtu 21 November 2026)
INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, jam_pulang, jumlah_unit, status) VALUES
-- Budi (bulanan)
(1, '2026-11-16', '07:55:00', '16:05:00', 0, 'hadir'),
(1, '2026-11-17', '07:58:00', '16:00:00', 0, 'hadir'),
(1, '2026-11-18', '08:02:00', '16:10:00', 0, 'hadir'),
(1, '2026-11-19', '07:50:00', '16:00:00', 0, 'hadir'),
(1, '2026-11-20', '07:57:00', '16:03:00', 0, 'hadir'),
(1, '2026-11-21', '08:00:00', '13:00:00', 0, 'hadir'),
-- Siti (harian): izin hari Kamis
(2, '2026-11-16', '08:00:00', '16:00:00', 0, 'hadir'),
(2, '2026-11-17', '08:05:00', '16:00:00', 0, 'hadir'),
(2, '2026-11-18', '08:00:00', '16:00:00', 0, 'hadir'),
(2, '2026-11-19', NULL,       NULL,       0, 'izin'),
(2, '2026-11-20', '07:59:00', '16:00:00', 0, 'hadir'),
(2, '2026-11-21', '08:00:00', '13:00:00', 0, 'hadir'),
-- Andi (borongan): unit = jumlah potong pakaian dijahit
(3, '2026-11-16', '08:10:00', '17:00:00', 42, 'hadir'),
(3, '2026-11-17', '08:00:00', '17:00:00', 45, 'hadir'),
(3, '2026-11-18', '08:05:00', '17:00:00', 40, 'hadir'),
(3, '2026-11-19', '08:00:00', '17:00:00', 48, 'hadir'),
(3, '2026-11-20', '08:00:00', '17:00:00', 44, 'hadir'),
(3, '2026-11-21', '08:00:00', '13:00:00', 25, 'hadir'),
-- Rina (borongan): sakit hari Rabu
(4, '2026-11-16', '08:00:00', '17:00:00', 38, 'hadir'),
(4, '2026-11-17', '08:00:00', '17:00:00', 40, 'hadir'),
(4, '2026-11-18', NULL,       NULL,        0, 'sakit'),
(4, '2026-11-19', '08:00:00', '17:00:00', 41, 'hadir'),
(4, '2026-11-20', '08:00:00', '17:00:00', 39, 'hadir'),
(4, '2026-11-21', '08:00:00', '13:00:00', 20, 'hadir'),
-- Hari ini (24 November 2026): Budi dan Siti sudah check-in, belum pulang
(1, '2026-11-24', '07:56:00', NULL, 0, 'hadir'),
(2, '2026-11-24', '08:01:00', NULL, 0, 'hadir');

-- Kasbon: satu untuk tiap status
INSERT INTO kasbon (id_karyawan, tanggal_pengajuan, jumlah, cicilan, sisa, alasan, status) VALUES
(2, '2026-11-10', 500000, 250000, 500000, 'Biaya sekolah anak',          'disetujui'),
(3, '2026-11-12', 300000, 150000, 300000, 'Servis motor',                'disetujui'),
(4, '2026-11-23', 200000, 100000, 200000, 'Keperluan keluarga',          'menunggu'),
(1, '2026-11-05', 1000000, 500000, 1000000, 'Renovasi rumah',            'ditolak'),
(3, '2026-09-01', 400000, 200000, 0,      'Biaya berobat orang tua',     'lunas');

-- Tabel penggajian sengaja dikosongkan.
-- Uji fitur Hitung Gaji dengan periode 2026-11-16 s.d. 2026-11-21. Hasil yang diharapkan:
--   Budi  (bulanan)  : dilewati, karena karyawan bulanan hanya digaji per 1 bulan penuh
--   Siti  (harian)   : 100.000 x 5 hari = 500.000   - kasbon 250.000 = 250.000
--   Andi  (borongan) : 5.000 x 244 unit = 1.220.000 - kasbon 150.000 = 1.070.000
--   Rina  (borongan) : 5.000 x 178 unit = 890.000   - kasbon 0       = 890.000 (kasbonnya masih menunggu)
--   Dedi  tidak dihitung karena nonaktif.
