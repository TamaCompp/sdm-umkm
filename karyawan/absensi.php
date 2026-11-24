<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('karyawan');

$id_karyawan = $_SESSION['id_karyawan'];

// Filter bulan, format YYYY-MM. Default bulan ini.
$bulan = $_GET['bulan'] ?? date('Y-m');
if (strlen($bulan) != 7 || !strtotime($bulan . '-01')) {
    $bulan = date('Y-m');
}
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));   // t = jumlah hari di bulan itu

$stmt = mysqli_prepare($conn,
    "SELECT * FROM absensi
     WHERE id_karyawan = ? AND tanggal BETWEEN ? AND ?
     ORDER BY tanggal DESC");
mysqli_stmt_bind_param($stmt, "iss", $id_karyawan, $awal, $akhir);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Hitung ringkasan sambil mengumpulkan baris
$rows = [];
$rekap = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'unit' => 0];
while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $rekap[$r['status']]++;
    $rekap['unit'] += $r['jumlah_unit'];
}

$judul = 'Riwayat Absensi';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Riwayat Absensi</h3>
    <form method="GET" class="d-flex gap-2">
        <input type="month" name="bulan" class="form-control" value="<?= e($bulan) ?>">
        <button class="btn btn-primary">Tampilkan</button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <small class="text-muted">Hadir</small><div class="fs-4 fw-bold text-success"><?= $rekap['hadir'] ?> hari</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <small class="text-muted">Izin</small><div class="fs-4 fw-bold"><?= $rekap['izin'] ?> hari</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <small class="text-muted">Sakit</small><div class="fs-4 fw-bold"><?= $rekap['sakit'] ?> hari</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <small class="text-muted">Total Unit</small><div class="fs-4 fw-bold text-primary"><?= $rekap['unit'] ?></div>
    </div></div></div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Unit</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada absensi di bulan ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= tanggal_indo($r['tanggal']) ?></td>
                    <td><?= $r['jam_masuk'] ? substr($r['jam_masuk'], 0, 5) : '-' ?></td>
                    <td><?= $r['jam_pulang'] ? substr($r['jam_pulang'], 0, 5) : '<span class="text-muted">belum</span>' ?></td>
                    <td><?= $r['jumlah_unit'] ?: '-' ?></td>
                    <td>
                        <?php $warna = ['hadir' => 'success', 'izin' => 'info', 'sakit' => 'warning']; ?>
                        <span class="badge bg-<?= $warna[$r['status']] ?>"><?= ucfirst($r['status']) ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
