<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('karyawan');

$id_karyawan = $_SESSION['id_karyawan'];
$hari_ini    = date('Y-m-d');

// Ambil tipe upah karyawan (untuk tahu perlu input jumlah unit atau tidak)
$stmt = mysqli_prepare($conn, "SELECT tipe_upah FROM karyawan WHERE id_karyawan = ?");
mysqli_stmt_bind_param($stmt, "i", $id_karyawan);
mysqli_stmt_execute($stmt);
$tipe_upah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['tipe_upah'];

// Ambil absensi hari ini (bisa null kalau belum absen)
function absen_hari_ini($conn, $id_karyawan, $tanggal)
{
    $stmt = mysqli_prepare($conn, "SELECT * FROM absensi WHERE id_karyawan = ? AND tanggal = ?");
    mysqli_stmt_bind_param($stmt, "is", $id_karyawan, $tanggal);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}
$absen = absen_hari_ini($conn, $id_karyawan, $hari_ini);

// ---------- Proses tombol ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $jam  = date('H:i:s');

    if ($aksi == 'checkin' && !$absen) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO absensi (id_karyawan, tanggal, jam_masuk, status) VALUES (?, ?, ?, 'hadir')");
        mysqli_stmt_bind_param($stmt, "iss", $id_karyawan, $hari_ini, $jam);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Check-in berhasil pukul ' . substr($jam, 0, 5) . '. Selamat bekerja!');

    } elseif ($aksi == 'checkout' && $absen && $absen['status'] == 'hadir' && !$absen['jam_pulang']) {
        $unit = 0;
        if ($tipe_upah == 'borongan') {
            $unit = (int) ($_POST['jumlah_unit'] ?? 0);
            if ($unit < 0 || $unit > 10000) {
                set_flash('danger', 'Jumlah unit tidak valid.');
                redirect('karyawan/dashboard.php');
            }
        }
        $stmt = mysqli_prepare($conn,
            "UPDATE absensi SET jam_pulang = ?, jumlah_unit = ? WHERE id_absensi = ?");
        mysqli_stmt_bind_param($stmt, "sii", $jam, $unit, $absen['id_absensi']);
        mysqli_stmt_execute($stmt);
        set_flash('success', 'Check-out berhasil pukul ' . substr($jam, 0, 5) . '. Terima kasih!');

    } elseif (($aksi == 'izin' || $aksi == 'sakit') && !$absen) {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO absensi (id_karyawan, tanggal, status) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $id_karyawan, $hari_ini, $aksi);
        mysqli_stmt_execute($stmt);
        set_flash('info', 'Hari ini tercatat ' . $aksi . '. Semoga lekas beres!');

    } else {
        set_flash('warning', 'Aksi tidak bisa dilakukan. Absensi hari ini mungkin sudah tercatat.');
    }
    redirect('karyawan/dashboard.php');   // PRG: refresh tidak mengirim ulang form
}

$judul = 'Absen Hari Ini';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm border-0 text-center">
            <div class="card-body p-4">
                <h5 class="mb-1">Halo, <?= e($_SESSION['nama']) ?>!</h5>
                <div class="text-muted"><?= tanggal_indo($hari_ini) ?></div>
                <div class="display-5 fw-bold my-3" id="jam"><?= date('H:i') ?></div>

                <?php if (!$absen): ?>
                    <!-- Belum absen sama sekali -->
                    <p class="text-muted">Anda belum absen hari ini.</p>
                    <form method="POST">
                        <button name="aksi" value="checkin" class="btn btn-success btn-lg w-100 mb-2">
                            <i class="bi bi-box-arrow-in-right"></i> Check-in Sekarang
                        </button>
                        <div class="d-flex gap-2">
                            <button name="aksi" value="izin" class="btn btn-outline-secondary w-50"
                                    onclick="return confirm('Catat hari ini sebagai IZIN?')">Izin</button>
                            <button name="aksi" value="sakit" class="btn btn-outline-secondary w-50"
                                    onclick="return confirm('Catat hari ini sebagai SAKIT?')">Sakit</button>
                        </div>
                    </form>

                <?php elseif ($absen['status'] != 'hadir'): ?>
                    <!-- Izin / sakit -->
                    <div class="alert alert-info mb-0">Hari ini tercatat <b><?= e($absen['status']) ?></b>.</div>

                <?php elseif (!$absen['jam_pulang']): ?>
                    <!-- Sudah check-in, belum check-out -->
                    <p>Check-in pukul <b><?= substr($absen['jam_masuk'], 0, 5) ?></b></p>
                    <form method="POST" onsubmit="return confirm('Check-out sekarang?')">
                        <?php if ($tipe_upah == 'borongan'): ?>
                            <div class="mb-3 text-start">
                                <label class="form-label">Jumlah unit dikerjakan hari ini</label>
                                <input type="number" name="jumlah_unit" class="form-control form-control-lg"
                                       min="0" max="10000" required placeholder="contoh: 40">
                            </div>
                        <?php endif; ?>
                        <button name="aksi" value="checkout" class="btn btn-danger btn-lg w-100">
                            <i class="bi bi-box-arrow-right"></i> Check-out
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Sudah lengkap -->
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> Absensi hari ini lengkap.<br>
                        Masuk <b><?= substr($absen['jam_masuk'], 0, 5) ?></b> &middot;
                        Pulang <b><?= substr($absen['jam_pulang'], 0, 5) ?></b>
                        <?php if ($tipe_upah == 'borongan'): ?>
                            &middot; <b><?= $absen['jumlah_unit'] ?></b> unit
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-center mt-3"><a href="absensi.php">Lihat riwayat absensi &rarr;</a></p>
    </div>
</div>

<script>
// Jam berjalan (hanya tampilan; jam yang disimpan tetap jam server)
setInterval(function () {
    const d = new Date();
    document.getElementById('jam').textContent =
        String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}, 1000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
