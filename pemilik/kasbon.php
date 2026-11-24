<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

// ---------- Proses Setujui / Tolak ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kasbon = (int) ($_POST['id_kasbon'] ?? 0);
    $aksi      = $_POST['aksi'] ?? '';

    if (in_array($aksi, ['disetujui', 'ditolak'])) {
        // Cek dulu: hanya kasbon yang masih 'menunggu' yang boleh diproses
        $stmt = mysqli_prepare($conn, "SELECT id_kasbon FROM kasbon WHERE id_kasbon = ? AND status = 'menunggu'");
        mysqli_stmt_bind_param($stmt, "i", $id_kasbon);
        mysqli_stmt_execute($stmt);

        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE kasbon SET status = ? WHERE id_kasbon = ?");
            mysqli_stmt_bind_param($stmt, "si", $aksi, $id_kasbon);
            mysqli_stmt_execute($stmt);
            set_flash($aksi == 'disetujui' ? 'success' : 'secondary', 'Kasbon berhasil ' . $aksi . '.');
        } else {
            set_flash('warning', 'Kasbon tidak ditemukan atau sudah diproses sebelumnya.');
        }
    }
    redirect('pemilik/kasbon.php');
}

// ---------- Filter status (GET) ----------
$filter = $_GET['status'] ?? 'semua';
if (!in_array($filter, ['semua', 'menunggu', 'disetujui', 'ditolak', 'lunas'])) {
    $filter = 'semua';
}

// Ambil SEMUA kasbon sekali, lalu dipilah di PHP
$result = mysqli_query($conn,
    "SELECT kasbon.*, karyawan.nama, karyawan.tipe_upah, karyawan.nilai_upah
     FROM kasbon INNER JOIN karyawan ON kasbon.id_karyawan = karyawan.id_karyawan
     ORDER BY kasbon.tanggal_pengajuan DESC, kasbon.id_kasbon DESC");

$menunggu = [];            // pengajuan yang perlu diputuskan
$semua = [];               // tabel bawah (sesuai filter)
$sisa_per_karyawan = [];   // sisa kasbon aktif per karyawan
$total_sisa = 0;

while ($k = mysqli_fetch_assoc($result)) {
    if ($k['status'] == 'menunggu') {
        $menunggu[] = $k;
    }
    if ($k['status'] == 'disetujui') {
        $id = $k['id_karyawan'];
        $sisa_per_karyawan[$id] = ($sisa_per_karyawan[$id] ?? 0) + $k['sisa'];
        $total_sisa += $k['sisa'];
    }
    if ($filter == 'semua' || $k['status'] == $filter) {
        $semua[] = $k;
    }
}

$warna = ['menunggu' => 'warning', 'disetujui' => 'primary', 'ditolak' => 'danger', 'lunas' => 'success'];
$satuan = ['bulanan' => '/bulan', 'harian' => '/hari', 'borongan' => '/unit'];

$judul = 'Kasbon';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Kasbon Karyawan</h3>
    <div class="text-end">
        <small class="text-muted">Total kasbon belum lunas</small>
        <div class="fs-4 fw-bold text-danger"><?= rupiah($total_sisa) ?></div>
    </div>
</div>

<!-- Pengajuan menunggu -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-warning-subtle">
        <b><i class="bi bi-hourglass-split"></i> Menunggu Persetujuan</b>
        <span class="badge bg-warning text-dark"><?= count($menunggu) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Karyawan</th><th class="text-end">Jumlah</th><th class="text-end">Cicilan</th>
                    <th>Alasan</th><th class="text-end">Kasbon aktif</th><th class="text-center">Keputusan</th></tr>
            </thead>
            <tbody>
            <?php if (empty($menunggu)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada pengajuan baru.</td></tr>
            <?php endif; ?>
            <?php foreach ($menunggu as $k): ?>
                <?php $aktif = $sisa_per_karyawan[$k['id_karyawan']] ?? 0; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($k['nama']) ?></div>
                        <small class="text-muted">Upah <?= rupiah($k['nilai_upah']) . $satuan[$k['tipe_upah']] ?> &middot;
                            diajukan <?= tanggal_indo($k['tanggal_pengajuan'], false) ?></small>
                    </td>
                    <td class="text-end fw-bold"><?= rupiah($k['jumlah']) ?></td>
                    <td class="text-end"><?= rupiah($k['cicilan']) ?></td>
                    <td class="small"><?= e($k['alasan']) ?></td>
                    <td class="text-end <?= $aktif > 0 ? 'text-danger' : 'text-muted' ?>"><?= rupiah($aktif) ?></td>
                    <td class="text-center text-nowrap">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Setujui kasbon <?= e($k['nama']) ?>?')">
                            <input type="hidden" name="id_kasbon" value="<?= $k['id_kasbon'] ?>">
                            <button name="aksi" value="disetujui" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Setujui</button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Tolak kasbon <?= e($k['nama']) ?>?')">
                            <input type="hidden" name="id_kasbon" value="<?= $k['id_kasbon'] ?>">
                            <button name="aksi" value="ditolak" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Tolak</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Semua kasbon -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <b>Semua Kasbon</b>
        <div class="btn-group btn-group-sm">
            <?php foreach (['semua', 'menunggu', 'disetujui', 'lunas', 'ditolak'] as $s): ?>
                <a href="?status=<?= $s ?>" class="btn btn-<?= $filter == $s ? 'primary' : 'outline-primary' ?>"><?= ucfirst($s) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>Karyawan</th><th class="text-end">Jumlah</th>
                    <th class="text-end">Cicilan</th><th class="text-end">Sisa</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (empty($semua)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada data.</td></tr>
            <?php endif; ?>
            <?php foreach ($semua as $k): ?>
                <tr>
                    <td><?= tanggal_indo($k['tanggal_pengajuan'], false) ?></td>
                    <td><?= e($k['nama']) ?><div class="small text-muted"><?= e($k['alasan']) ?></div></td>
                    <td class="text-end"><?= rupiah($k['jumlah']) ?></td>
                    <td class="text-end"><?= rupiah($k['cicilan']) ?></td>
                    <td class="text-end fw-semibold"><?= $k['status'] == 'disetujui' ? rupiah($k['sisa']) : '-' ?></td>
                    <td><span class="badge bg-<?= $warna[$k['status']] ?>"><?= ucfirst($k['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
