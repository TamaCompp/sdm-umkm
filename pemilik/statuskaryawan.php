<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

// Halaman ini hanya memproses form POST, tidak punya tampilan
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    redirect('pemilik/karyawan.php');
}

$id = (int) ($_POST['id_karyawan'] ?? 0);

// Ambil status sekarang
$stmt = mysqli_prepare($conn, "SELECT nama, status FROM karyawan WHERE id_karyawan = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$k = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$k) {
    set_flash('danger', 'Karyawan tidak ditemukan.');
    redirect('pemilik/karyawan.php');
}

// Balik statusnya: aktif -> nonaktif, nonaktif -> aktif
$status_baru = ($k['status'] == 'aktif') ? 'nonaktif' : 'aktif';

$stmt = mysqli_prepare($conn, "UPDATE karyawan SET status = ? WHERE id_karyawan = ?");
mysqli_stmt_bind_param($stmt, "si", $status_baru, $id);
mysqli_stmt_execute($stmt);

if ($status_baru == 'nonaktif') {
    set_flash('warning', $k['nama'] . ' dinonaktifkan. Akunnya tidak bisa login dan tidak ikut penggajian.');
} else {
    set_flash('success', $k['nama'] . ' diaktifkan kembali.');
}
redirect('pemilik/karyawan.php');
