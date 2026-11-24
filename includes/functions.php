<?php
// Mulai session sekali untuk semua halaman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona waktu Indonesia (WIB). Penting untuk jam absensi!
date_default_timezone_set('Asia/Jakarta');

// Alamat dasar aplikasi, dipakai untuk link & redirect dari folder mana pun
define('BASE_URL', '/sdm-umkm/');

// Amankan teks sebelum ditampilkan (mencegah XSS)
function e($teks)
{
    return htmlspecialchars($teks ?? '', ENT_QUOTES, 'UTF-8');
}

// Pindah halaman. Contoh: redirect('login.php') atau redirect('pemilik/dashboard.php')
function redirect($path)
{
    header("Location: " . BASE_URL . $path);
    exit;
}

// Format angka jadi rupiah. Contoh: rupiah(1500000) -> Rp1.500.000
function rupiah($angka)
{
    return "Rp" . number_format($angka, 0, ',', '.');
}

// Format tanggal Indonesia. Contoh: tanggal_indo('2026-11-25') -> Rabu, 25 November 2026
function tanggal_indo($tanggal, $pakai_hari = true)
{
    $hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
              'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $t = strtotime($tanggal);
    $hasil = date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
    return $pakai_hari ? $hari[(int) date('w', $t)] . ', ' . $hasil : $hasil;
}

// Pesan notifikasi sekali tampil (flash message)
function set_flash($tipe, $pesan)
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function tampil_flash()
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($f['tipe']) . ' alert-dismissible fade show">'
           . e($f['pesan'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Hapus file foto lama dari folder uploads/
function hapus_foto($nama_file)
{
    $path = __DIR__ . '/../uploads/' . $nama_file;
    if ($nama_file && file_exists($path)) {
        unlink($path);
    }
}
