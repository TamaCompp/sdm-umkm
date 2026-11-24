<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('karyawan');

$stmt = mysqli_prepare($conn,
    "SELECT * FROM penggajian WHERE id_karyawan = ? ORDER BY periode_akhir DESC");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_karyawan']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$judul = 'Slip Gaji';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>
<h3 class="mb-3">Slip Gaji Saya</h3>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Periode</th><th class="text-end">Gaji Kotor</th><th class="text-end">Potong Kasbon</th>
                    <th class="text-end">Gaji Bersih</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) == 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada slip gaji.</td></tr>
            <?php endif; ?>
            <?php while ($g = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= tanggal_indo($g['periode_awal'], false) ?> &ndash; <?= tanggal_indo($g['periode_akhir'], false) ?></td>
                    <td class="text-end"><?= rupiah($g['gaji_kotor']) ?></td>
                    <td class="text-end text-danger"><?= $g['potongan_kasbon'] > 0 ? '-' . rupiah($g['potongan_kasbon']) : '-' ?></td>
                    <td class="text-end fw-bold"><?= rupiah($g['gaji_bersih']) ?></td>
                    <td><a href="<?= BASE_URL ?>slip.php?id=<?= $g['id_gaji'] ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> Lihat</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
