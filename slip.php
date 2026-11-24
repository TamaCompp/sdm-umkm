<?php
require_once 'config/koneksi.php';
require_once 'includes/auth.php';
wajib_login();   // pemilik dan karyawan sama-sama boleh membuka

$id = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn,
    "SELECT penggajian.*, karyawan.nama, karyawan.no_hp, users.username
     FROM penggajian
     INNER JOIN karyawan ON penggajian.id_karyawan = karyawan.id_karyawan
     INNER JOIN users    ON karyawan.id_user = users.id_user
     WHERE penggajian.id_gaji = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$g = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Karyawan hanya boleh melihat slip miliknya sendiri
if (!$g || ($_SESSION['role'] == 'karyawan' && $g['id_karyawan'] != $_SESSION['id_karyawan'])) {
    set_flash('danger', 'Slip gaji tidak ditemukan.');
    redirect($_SESSION['role'] == 'pemilik' ? 'pemilik/penggajian.php' : 'karyawan/gaji.php');
}

$judul = 'Slip Gaji ' . $g['nama'];
include 'includes/header.php';
?>

<style>
    /* Saat dicetak: sembunyikan navbar & tombol, kertas putih polos */
    @media print {
        .navbar, .no-print { display: none !important; }
        body { background: #fff !important; }
        .card { box-shadow: none !important; border: 1px solid #000 !important; }
    }
</style>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="no-print mb-3 d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Cetak</button>
            <a href="<?= BASE_URL . ($_SESSION['role'] == 'pemilik' ? 'pemilik/penggajian.php' : 'karyawan/gaji.php') ?>"
               class="btn btn-secondary">Kembali</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="text-center border-bottom pb-3 mb-3">
                    <h4 class="mb-0">SLIP GAJI</h4>
                    <small class="text-muted">Periode <?= tanggal_indo($g['periode_awal'], false) ?> &ndash; <?= tanggal_indo($g['periode_akhir'], false) ?></small>
                </div>

                <table class="table table-borderless table-sm mb-3">
                    <tr><td width="40%">Nama</td><td>: <b><?= e($g['nama']) ?></b></td></tr>
                    <tr><td>Username</td><td>: <?= e($g['username']) ?></td></tr>
                    <tr><td>Tipe upah</td><td>: <?= ucfirst($g['tipe_upah']) ?></td></tr>
                    <tr><td>Hari hadir</td><td>: <?= $g['hari_hadir'] ?> hari</td></tr>
                    <?php if ($g['tipe_upah'] == 'borongan'): ?>
                        <tr><td>Total unit</td><td>: <?= $g['total_unit'] ?> unit</td></tr>
                    <?php endif; ?>
                </table>

                <table class="table table-sm">
                    <tr>
                        <td>
                            Gaji kotor
                            <div class="small text-muted">
                                <?php if ($g['tipe_upah'] == 'bulanan'): ?>
                                    Upah bulanan tetap
                                <?php elseif ($g['tipe_upah'] == 'harian'): ?>
                                    <?= rupiah($g['nilai_upah']) ?> &times; <?= $g['hari_hadir'] ?> hari
                                <?php else: ?>
                                    <?= rupiah($g['nilai_upah']) ?> &times; <?= $g['total_unit'] ?> unit
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end"><?= rupiah($g['gaji_kotor']) ?></td>
                    </tr>
                    <tr>
                        <td>Potongan cicilan kasbon</td>
                        <td class="text-end text-danger">-<?= rupiah($g['potongan_kasbon']) ?></td>
                    </tr>
                    <tr class="table-light fs-5">
                        <th>GAJI BERSIH</th>
                        <th class="text-end"><?= rupiah($g['gaji_bersih']) ?></th>
                    </tr>
                </table>

                <div class="d-flex justify-content-between mt-5 text-center small">
                    <div>Diterima oleh,<br><br><br><br><b><?= e($g['nama']) ?></b></div>
                    <div>Dibuat tanggal <?= tanggal_indo(substr($g['tanggal_dibuat'], 0, 10), false) ?>,<br><br><br><br><b>Pemilik Usaha</b></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
