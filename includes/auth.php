<?php
require_once __DIR__ . '/functions.php';

// Panggil di awal setiap halaman yang wajib login.
// wajib_login()           -> semua role boleh
// wajib_login('pemilik')  -> hanya pemilik
// wajib_login('karyawan') -> hanya karyawan
function wajib_login($role = null)
{
    if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }

    if ($role !== null && $_SESSION['role'] !== $role) {
        set_flash('danger', 'Anda tidak punya akses ke halaman tersebut.');
        redirect($_SESSION['role'] . '/dashboard.php');
    }
}
