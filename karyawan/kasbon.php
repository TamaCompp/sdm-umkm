<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('karyawan');

$id_karyawan = $_SESSION['id_karyawan'];
$errors = [];
$form = ['jumlah' => '', 'cicilan' => '', 'alasan' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // ---------- Ajukan kasbon baru ----------
    if ($aksi == 'ajukan') {
        $form['jumlah']  = trim($_POST['jumlah'] ?? '');
        $form['cicilan'] = trim($_POST['cicilan'] ?? '');
        $form['alasan']  = trim($_POST['alasan'] ?? '');

        if (!is_numeric($form['jumlah']) || $form['jumlah'] < 10000 || $form['jumlah'] > 10000000) {
            $errors[] = "Jumlah kasbon antara Rp10.000 sampai Rp10.000.000.";
        }
        if (!is_numeric($form['cicilan']) || $form['cicilan'] <= 0) {
            $errors[] = "Cicilan per gajian harus lebih dari 0.";
        } elseif (is_numeric($form['jumlah']) && $form['cicilan'] > $form['jumlah']) {
            $errors[] = "Cicilan tidak boleh lebih besar dari jumlah kasbon.";
        }
        if ($form['alasan'] == '' || strlen($form['alasan']) > 255) {
            $errors[] = "Alasan wajib diisi (maksimal 255 karakter).";
        }

        // Tidak boleh mengajukan lagi kalau masih ada yang menunggu
        $stmt = mysqli_prepare($conn, "SELECT id_kasbon FROM kasbon WHERE id_karyawan = ? AND status = 'menunggu'");
        mysqli_stmt_bind_param($stmt, "i", $id_karyawan);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = "Masih ada pengajuan yang menunggu persetujuan.";
        }

        if (empty($errors)) {
            $hari_ini = date('Y-m-d');
            // sisa = jumlah, karena belum ada yang dibayar
            $stmt = mysqli_prepare($conn,
                "INSERT INTO kasbon (id_karyawan, tanggal_pengajuan, jumlah, cicilan, sisa, alasan, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'menunggu')");
            mysqli_stmt_bind_param($stmt, "isddds",
                $id_karyawan, $hari_ini, $form['jumlah'], $form['cicilan'], $form['jumlah'], $form['alasan']);
            mysqli_stmt_execute($stmt);

            set_flash('success', 'Pengajuan kasbon ' . rupiah($form['jumlah']) . ' terkirim. Tunggu persetujuan pemilik.');
            redirect('karyawan/kasbon.php');
        }
    }

    // ---------- Batalkan pengajuan yang masih menunggu ----------
    if ($aksi == 'batal') {
        $id_kasbon = (int) ($_POST['id_kasbon'] ?? 0);

        // Cek dulu: kasbon ini milik sendiri dan masih menunggu?
        $stmt = mysqli_prepare($conn,
            "SELECT id_kasbon FROM kasbon WHERE id_kasbon = ? AND id_karyawan = ? AND status = 'menunggu'");
        mysqli_stmt_bind_param($stmt, "ii", $id_kasbon, $id_karyawan);
        mysqli_stmt_execute($stmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM kasbon WHERE id_kasbon = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_kasbon);
            mysqli_stmt_execute($stmt);
            set_flash('info', 'Pengajuan kasbon dibatalkan.');
        } else {
            set_flash('warning', 'Pengajuan tidak bisa dibatalkan (mungkin sudah diproses).');
        }
        redirect('karyawan/kasbon.php');
    }
}

// ---------- Data untuk ditampilkan ----------
$stmt = mysqli_prepare($conn, "SELECT * FROM kasbon WHERE id_karyawan = ? ORDER BY tanggal_pengajuan DESC, id_kasbon DESC");
mysqli_stmt_bind_param($stmt, "i", $id_karyawan);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Kumpulkan riwayat sekaligus hitung sisa kasbon yang belum lunas
$riwayat = [];
$sisa_aktif = 0;
while ($k = mysqli_fetch_assoc($result)) {
    $riwayat[] = $k;
    if ($k['status'] == 'disetujui') {
        $sisa_aktif += $k['sisa'];
    }
}

$warna = ['menunggu' => 'warning', 'disetujui' => 'primary', 'ditolak' => 'danger', 'lunas' => 'success'];

$judul = 'Kasbon';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>
<h3 class="mb-3">Kasbon Saya</h3>

<div class="row g-3">
    <!-- Form pengajuan -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <small class="text-muted">Sisa kasbon yang belum lunas</small>
                <div class="fs-3 fw-bold <?= $sisa_aktif > 0 ? 'text-danger' : 'text-success' ?>"><?= rupiah($sisa_aktif) ?></div>
                <small class="text-muted">Dipotong otomatis dari gaji.</small>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><b><i class="bi bi-cash-coin"></i> Ajukan Kasbon</b></div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2 small"><?= e($err) ?></div>
                <?php endforeach; ?>
                <form method="POST">
                    <input type="hidden" name="aksi" value="ajukan">
                    <div class="mb-3">
                        <label class="form-label">Jumlah (Rp)</label>
                        <input type="number" name="jumlah" class="form-control" value="<?= e($form['jumlah']) ?>"
                               min="10000" max="10000000" step="1000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cicilan per gajian (Rp)</label>
                        <input type="number" name="cicilan" class="form-control" value="<?= e($form['cicilan']) ?>"
                               min="1000" step="1000" required>
                        <div class="form-text">Isi sama dengan jumlah jika ingin langsung lunas di gajian berikutnya.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alasan</label>
                        <textarea name="alasan" class="form-control" rows="2" maxlength="255" required><?= e($form['alasan']) ?></textarea>
                    </div>
                    <button class="btn btn-primary w-100">Kirim Pengajuan</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Riwayat -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><b>Riwayat Kasbon</b></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Tanggal</th><th class="text-end">Jumlah</th><th class="text-end">Cicilan</th>
                            <th class="text-end">Sisa</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($riwayat)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum pernah mengajukan kasbon.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($riwayat as $k): ?>
                        <tr>
                            <td><?= tanggal_indo($k['tanggal_pengajuan'], false) ?>
                                <div class="small text-muted"><?= e($k['alasan']) ?></div></td>
                            <td class="text-end"><?= rupiah($k['jumlah']) ?></td>
                            <td class="text-end"><?= rupiah($k['cicilan']) ?></td>
                            <td class="text-end fw-semibold"><?= $k['status'] == 'disetujui' ? rupiah($k['sisa']) : '-' ?></td>
                            <td><span class="badge bg-<?= $warna[$k['status']] ?>"><?= ucfirst($k['status']) ?></span></td>
                            <td>
                                <?php if ($k['status'] == 'menunggu'): ?>
                                    <form method="POST" onsubmit="return confirm('Batalkan pengajuan ini?')">
                                        <input type="hidden" name="aksi" value="batal">
                                        <input type="hidden" name="id_kasbon" value="<?= $k['id_kasbon'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Batal</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
