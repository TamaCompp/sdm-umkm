<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

// Ambil semua karyawan + username-nya (INNER JOIN: setiap karyawan pasti punya akun)
$sql = "SELECT karyawan.*, users.username
        FROM karyawan
        INNER JOIN users ON karyawan.id_user = users.id_user
        ORDER BY karyawan.status ASC, karyawan.nama ASC";
$result = mysqli_query($conn, $sql);

// Satuan upah sesuai tipe, untuk ditampilkan di tabel
$satuan = ['bulanan' => '/bulan', 'harian' => '/hari', 'borongan' => '/unit'];
$warna  = ['bulanan' => 'primary', 'harian' => 'success', 'borongan' => 'warning'];

$judul = 'Data Karyawan';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Data Karyawan</h3>
    <a href="nambahkaryawan.php" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Tambah Karyawan
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Karyawan</th>
                    <th>No. HP</th>
                    <th>Tipe Upah</th>
                    <th class="text-end">Upah</th>
                    <th>Tgl Masuk</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($result) == 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada karyawan.</td></tr>
            <?php endif; ?>

            <?php while ($k = mysqli_fetch_assoc($result)): ?>
                <tr class="<?= $k['status'] == 'nonaktif' ? 'text-muted' : '' ?>">
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($k['foto'] && file_exists(__DIR__ . '/../uploads/' . $k['foto'])): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= e($k['foto']) ?>"
                                     class="rounded-circle me-2" width="40" height="40" style="object-fit: cover;">
                            <?php else: ?>
                                <!-- tidak ada foto: tampilkan huruf pertama nama -->
                                <div class="rounded-circle bg-secondary text-white me-2 d-flex align-items-center justify-content-center"
                                     style="width: 40px; height: 40px;">
                                    <?= e(strtoupper(substr($k['nama'], 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?= e($k['nama']) ?></div>
                                <small class="text-muted">@<?= e($k['username']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= e($k['no_hp']) ?></td>
                    <td><span class="badge bg-<?= $warna[$k['tipe_upah']] ?>"><?= ucfirst($k['tipe_upah']) ?></span></td>
                    <td class="text-end"><?= rupiah($k['nilai_upah']) ?><small class="text-muted"><?= $satuan[$k['tipe_upah']] ?></small></td>
                    <td><?= date('d-m-Y', strtotime($k['tanggal_masuk'])) ?></td>
                    <td>
                        <?php if ($k['status'] == 'aktif'): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center text-nowrap">
                        <a href="editkaryawan.php?id=<?= $k['id_karyawan'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <form action="statuskaryawan.php" method="POST" class="d-inline"
                              onsubmit="return confirm('Ubah status <?= e($k['nama']) ?>?')">
                            <input type="hidden" name="id_karyawan" value="<?= $k['id_karyawan'] ?>">
                            <?php if ($k['status'] == 'aktif'): ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-x"></i> Nonaktifkan</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-person-check"></i> Aktifkan</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
