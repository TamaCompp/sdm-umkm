<?php
require_once 'includes/functions.php';

// Hapus semua data session
session_unset();
session_destroy();

// Session baru hanya untuk pesan "berhasil logout"
session_start();
set_flash('info', 'Anda berhasil logout.');
redirect('login.php');
