<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

// ---------- Filter (GET) ----------
// Default: Senin minggu ini s.d. hari ini
$dari   = $_GET['dari']   ?? date('Y-m-d', strtotime('monday this week'));
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$id_kar = (int) ($_GET['id_karyawan'] ?? 0);   // 0 = semua karyawan

if (!strtotime($dari) || !strtotime($sampai) || $dari > $sampai) {
    set_flash('danger', 'Rentang tanggal tidak valid.');
    $dari = $sampai = date('Y-m-d');
}

// Daftar karyawan untuk dropdown filter
$daftar_karyawan = mysqli_query($conn, "SELECT id_karyawan, nama, status FROM karyawan ORDER BY nama");

// ---------- Ambil absensi dalam rentang tanggal ----------
$stmt = mysqli_prepare($conn,
    "SELECT absensi.*, karyawan.nama, karyawan.tipe_upah
     FROM absensi
     INNER JOIN karyawan ON absensi.id_karyawan = karyawan.id_karyawan
     WHERE absensi.tanggal BETWEEN ? AND ?
     ORDER BY absensi.tanggal DESC, karyawan.nama");
mysqli_stmt_bind_param($stmt, "ss", $dari, $sampai);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// ---------- Susun detail + hitung ringkasan per karyawan ----------
$detail = [];
$ringkasan = [];
while ($r = mysqli_fetch_assoc($result)) {
    // Kalau memilih satu karyawan, lewati baris milik karyawan lain
    if ($id_kar != 0 && $r['id_karyawan'] != $id_kar) {
        continue;
    }
    $detail[] = $r;

    $id = $r['id_karyawan'];
    if (!isset($ringkasan[$id])) {
        $ringkasan[$id] = ['nama' => $r['nama'], 'tipe_upah' => $r['tipe_upah'],
                           'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'total_unit' => 0];
    }
    $ringkasan[$id][$r['status']]++;              // tambah 1 ke hadir / izin / sakit
    $ringkasan[$id]['total_unit'] += $r['jumlah_unit'];
}

$warna = ['hadir' => 'success', 'izin' => 'info', 'sakit' => 'warning'];

$judul = 'Rekap Absensi';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>
<h3 class="mb-3">Rekap Absensi</h3>

<!-- Filter -->
<form method="GET" class="card card-body border-0 shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Dari</label>
            <input type="date" name="dari" class="form-control" value="<?= e($dari) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Sampai</label>
            <input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Karyawan</label>
            <select name="id_karyawan" class="form-select">
                <option value="0">Semua karyawan</option>
                <?php while ($k = mysqli_fetch_assoc($daftar_karyawan)): ?>
                    <option value="<?= $k['id_karyawan'] ?>" <?= $id_kar == $k['id_karyawan'] ? 'selected' : '' ?>>
                        <?= e($k['nama']) ?><?= $k['status'] == 'nonaktif' ? ' (nonaktif)' : '' ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Tampilkan</button>
        </div>
    </div>
</form>

<!-- Ringkasan -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white"><b>Ringkasan</b>
        <span class="text-muted small"><?= tanggal_indo($dari, false) ?> s.d. <?= tanggal_indo($sampai, false) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Karyawan</th><th class="text-center">Hadir</th><th class="text-center">Izin</th>
                    <th class="text-center">Sakit</th><th class="text-center">Total Unit</th></tr>
            </thead>
            <tbody>
            <?php if (empty($ringkasan)): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada data absensi pada rentang ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($ringkasan as $r): ?>
                <tr>
                    <td><?= e($r['nama']) ?> <small class="text-muted">(<?= $r['tipe_upah'] ?>)</small></td>
                    <td class="text-center fw-bold text-success"><?= $r['hadir'] ?></td>
                    <td class="text-center"><?= $r['izin'] ?></td>
                    <td class="text-center"><?= $r['sakit'] ?></td>
                    <td class="text-center"><?= $r['tipe_upah'] == 'borongan' ? $r['total_unit'] : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><b>Detail Harian</b></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>Karyawan</th><th>Masuk</th><th>Pulang</th><th>Unit</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($detail as $r): ?>
                <tr>
                    <td><?= tanggal_indo($r['tanggal']) ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td><?= $r['jam_masuk'] ? substr($r['jam_masuk'], 0, 5) : '-' ?></td>
                    <td><?= $r['jam_pulang'] ? substr($r['jam_pulang'], 0, 5) : ($r['status'] == 'hadir' ? '<span class="text-muted">belum</span>' : '-') ?></td>
                    <td><?= $r['tipe_upah'] == 'borongan' && $r['status'] == 'hadir' ? $r['jumlah_unit'] : '-' ?></td>
                    <td><span class="badge bg-<?= $warna[$r['status']] ?>"><?= ucfirst($r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
