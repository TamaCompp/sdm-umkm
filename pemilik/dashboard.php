<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

$hari_ini = date('Y-m-d');

// ---------- 4 angka ringkasan ----------
$q = mysqli_query($conn, "SELECT id_karyawan FROM karyawan WHERE status = 'aktif'");
$total_karyawan = mysqli_num_rows($q);

$stmt = mysqli_prepare($conn, "SELECT id_absensi FROM absensi WHERE tanggal = ? AND status = 'hadir'");
mysqli_stmt_bind_param($stmt, "s", $hari_ini);
mysqli_stmt_execute($stmt);
$hadir_hari_ini = mysqli_num_rows(mysqli_stmt_get_result($stmt));

$q = mysqli_query($conn, "SELECT id_kasbon FROM kasbon WHERE status = 'menunggu'");
$kasbon_menunggu = mysqli_num_rows($q);

$sisa_kasbon = 0;
$q = mysqli_query($conn, "SELECT sisa FROM kasbon WHERE status = 'disetujui'");
while ($r = mysqli_fetch_assoc($q)) {
    $sisa_kasbon += $r['sisa'];
}

// ---------- Karyawan aktif yang BELUM absen hari ini ----------
// LEFT JOIN + IS NULL = "cari yang tidak punya pasangan"
$stmt = mysqli_prepare($conn,
    "SELECT karyawan.nama, karyawan.no_hp, karyawan.tipe_upah
     FROM karyawan
     LEFT JOIN absensi ON absensi.id_karyawan = karyawan.id_karyawan AND absensi.tanggal = ?
     WHERE karyawan.status = 'aktif' AND absensi.id_absensi IS NULL
     ORDER BY karyawan.nama");
mysqli_stmt_bind_param($stmt, "s", $hari_ini);
mysqli_stmt_execute($stmt);
$belum_absen = mysqli_stmt_get_result($stmt);

// ---------- Absensi hari ini ----------
$stmt = mysqli_prepare($conn,
    "SELECT absensi.*, karyawan.nama, karyawan.tipe_upah
     FROM absensi INNER JOIN karyawan ON absensi.id_karyawan = karyawan.id_karyawan
     WHERE absensi.tanggal = ?
     ORDER BY absensi.jam_masuk");
mysqli_stmt_bind_param($stmt, "s", $hari_ini);
mysqli_stmt_execute($stmt);
$absen_hari_ini = mysqli_stmt_get_result($stmt);

// ---------- Penggajian terakhir ----------
// Urutkan dari periode terbaru; baris pertama = periode terakhir
$gaji_terakhir = null;
$q = mysqli_query($conn, "SELECT periode_awal, periode_akhir, gaji_bersih FROM penggajian ORDER BY periode_akhir DESC");
while ($r = mysqli_fetch_assoc($q)) {
    if ($gaji_terakhir == null) {
        $gaji_terakhir = ['periode_awal' => $r['periode_awal'], 'periode_akhir' => $r['periode_akhir'],
                          'total' => 0, 'jumlah' => 0];
    }
    // Jumlahkan hanya baris yang periodenya sama dengan periode terakhir
    if ($r['periode_awal'] == $gaji_terakhir['periode_awal'] && $r['periode_akhir'] == $gaji_terakhir['periode_akhir']) {
        $gaji_terakhir['total'] += $r['gaji_bersih'];
        $gaji_terakhir['jumlah']++;
    }
}

$warna = ['hadir' => 'success', 'izin' => 'info', 'sakit' => 'warning'];

$judul = 'Dashboard Pemilik';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>

<div class="mb-4">
    <h3 class="mb-0">Selamat datang, <?= e($_SESSION['nama']) ?>!</h3>
    <span class="text-muted"><?= tanggal_indo($hari_ini) ?></span>
</div>

<!-- Kartu ringkasan -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <a href="karyawan.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Karyawan Aktif</small><i class="bi bi-people text-primary fs-4"></i>
                </div>
                <div class="fs-2 fw-bold text-dark"><?= $total_karyawan ?></div>
            </div></div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="absensi.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Hadir Hari Ini</small><i class="bi bi-calendar-check text-success fs-4"></i>
                </div>
                <div class="fs-2 fw-bold text-dark"><?= $hadir_hari_ini ?> <small class="fs-6 text-muted">/ <?= $total_karyawan ?></small></div>
            </div></div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="kasbon.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?= $kasbon_menunggu > 0 ? 'border-start border-warning border-4' : '' ?>"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Kasbon Menunggu</small><i class="bi bi-hourglass-split text-warning fs-4"></i>
                </div>
                <div class="fs-2 fw-bold text-dark"><?= $kasbon_menunggu ?></div>
            </div></div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a href="kasbon.php?status=disetujui" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Kasbon Belum Lunas</small><i class="bi bi-cash-coin text-danger fs-4"></i>
                </div>
                <div class="fs-4 fw-bold text-danger"><?= rupiah($sisa_kasbon) ?></div>
            </div></div>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Belum absen -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <b><i class="bi bi-exclamation-circle text-danger"></i> Belum Absen Hari Ini</b>
                <span class="badge bg-danger"><?= mysqli_num_rows($belum_absen) ?></span>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (mysqli_num_rows($belum_absen) == 0): ?>
                    <li class="list-group-item text-success"><i class="bi bi-check-circle"></i> Semua karyawan sudah absen.</li>
                <?php endif; ?>
                <?php while ($k = mysqli_fetch_assoc($belum_absen)): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= e($k['nama']) ?> <small class="text-muted">(<?= $k['tipe_upah'] ?>)</small></span>
                        <?php if ($k['no_hp']): ?>
                            <small class="text-muted"><i class="bi bi-telephone"></i> <?= e($k['no_hp']) ?></small>
                        <?php endif; ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <!-- Absensi hari ini -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><b><i class="bi bi-clock-history"></i> Absensi Hari Ini</b></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Karyawan</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (mysqli_num_rows($absen_hari_ini) == 0): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada yang absen.</td></tr>
                    <?php endif; ?>
                    <?php while ($a = mysqli_fetch_assoc($absen_hari_ini)): ?>
                        <tr>
                            <td><?= e($a['nama']) ?></td>
                            <td><?= $a['jam_masuk'] ? substr($a['jam_masuk'], 0, 5) : '-' ?></td>
                            <td><?= $a['jam_pulang'] ? substr($a['jam_pulang'], 0, 5) : ($a['status'] == 'hadir' ? '<span class="text-muted">bekerja</span>' : '-') ?></td>
                            <td><span class="badge bg-<?= $warna[$a['status']] ?>"><?= ucfirst($a['status']) ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Penggajian terakhir -->
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <?php if ($gaji_terakhir): ?>
            <div>
                <small class="text-muted">Penggajian terakhir</small>
                <div class="fw-semibold">
                    <?= tanggal_indo($gaji_terakhir['periode_awal'], false) ?> &ndash; <?= tanggal_indo($gaji_terakhir['periode_akhir'], false) ?>
                    &middot; <?= $gaji_terakhir['jumlah'] ?> karyawan &middot; <?= rupiah($gaji_terakhir['total']) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-muted">Belum pernah melakukan penggajian.</div>
        <?php endif; ?>
        <a href="penggajian.php" class="btn btn-primary"><i class="bi bi-calculator"></i> Hitung Gaji</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
