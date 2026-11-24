<?php
require_once 'includes/functions.php';

// Sudah login -> ke dashboard sesuai role. Belum -> ke halaman login.
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    redirect($_SESSION['role'] . '/dashboard.php');
}
redirect('login.php');
