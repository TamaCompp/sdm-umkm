<?php
// Koneksi ke database. Jika nama DB / password MySQL berubah, cukup ubah di sini.
$conn = mysqli_connect("localhost", "root", "", "db_sdm_umkm");

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
