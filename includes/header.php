<?php
// Isi $judul sebelum include file ini
$judul = $judul ?? 'SDM UMKM';
$role  = $_SESSION['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> | SDM UMKM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<?php if ($role): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL . $role ?>/dashboard.php">
            <i class="bi bi-people-fill"></i> SDM UMKM
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <?php if ($role === 'pemilik'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pemilik/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pemilik/karyawan.php">Karyawan</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pemilik/absensi.php">Absensi</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pemilik/kasbon.php">Kasbon</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>pemilik/penggajian.php">Penggajian</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>karyawan/dashboard.php">Absen Hari Ini</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>karyawan/absensi.php">Riwayat Absensi</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>karyawan/kasbon.php">Kasbon</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>karyawan/gaji.php">Slip Gaji</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white me-3">
                <i class="bi bi-person-circle"></i> <?= e($_SESSION['nama']) ?>
                <span class="badge bg-light text-primary ms-1"><?= e(ucfirst($role)) ?></span>
            </span>
            <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-light btn-sm"
               onclick="return confirm('Yakin ingin logout?')">Logout</a>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container py-4">
