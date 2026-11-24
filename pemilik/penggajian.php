<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

// =====================================================================
// PROSES HITUNG GAJI (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $awal  = $_POST['awal'] ?? '';
    $akhir = $_POST['akhir'] ?? '';

    if (!strtotime($awal) || !strtotime($akhir) || $awal > $akhir) {
        set_flash('danger', 'Periode tidak valid.');
        redirect('pemilik/penggajian.php');
    }
    if ($akhir > date('Y-m-d')) {
        set_flash('danger', 'Tanggal akhir periode tidak boleh melewati hari ini.');
        redirect('pemilik/penggajian.php');
    }

    // Karyawan aktif yang sudah bekerja sebelum periode berakhir
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM karyawan WHERE status = 'aktif' AND tanggal_masuk <= ? ORDER BY nama");
    mysqli_stmt_bind_param($stmt, "s", $akhir);
    mysqli_stmt_execute($stmt);
    $daftar = mysqli_stmt_get_result($stmt);

    // Karyawan bulanan hanya digaji jika periode = 1 bulan kalender penuh (tgl 1 s.d. akhir bulan)
    $sebulan_penuh = (date('j', strtotime($awal)) == 1 && $akhir == date('Y-m-t', strtotime($awal)));

    $dihitung = 0;
    $dilewati = [];
    $bulanan_dilewati = [];

    while ($k = mysqli_fetch_assoc($daftar)) {
        $id = $k['id_karyawan'];

        if ($k['tipe_upah'] == 'bulanan' && !$sebulan_penuh) {
            $bulanan_dilewati[] = $k['nama'];
            continue;   // lanjut ke karyawan berikutnya
        }

        // 1. Cegah gaji dobel: lewati kalau periode ini bertabrakan dengan gaji yang sudah ada
        $stmt = mysqli_prepare($conn,
            "SELECT id_gaji FROM penggajian
             WHERE id_karyawan = ? AND periode_awal <= ? AND periode_akhir >= ?");
        mysqli_stmt_bind_param($stmt, "iss", $id, $akhir, $awal);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $dilewati[] = $k['nama'];
            continue;
        }

        // 2. Hitung hari hadir dan total unit dari absensi dalam periode
        $stmt = mysqli_prepare($conn,
            "SELECT status, jumlah_unit FROM absensi WHERE id_karyawan = ? AND tanggal BETWEEN ? AND ?");
        mysqli_stmt_bind_param($stmt, "iss", $id, $awal, $akhir);
        mysqli_stmt_execute($stmt);
        $absen = mysqli_stmt_get_result($stmt);

        $hari_hadir = 0;
        $total_unit = 0;
        while ($a = mysqli_fetch_assoc($absen)) {
            if ($a['status'] == 'hadir') {
                $hari_hadir++;
            }
            $total_unit += $a['jumlah_unit'];
        }

        // 3. Gaji kotor sesuai tipe upah
        if ($k['tipe_upah'] == 'bulanan') {
            $gaji_kotor = $k['nilai_upah'];
        } elseif ($k['tipe_upah'] == 'harian') {
            $gaji_kotor = $k['nilai_upah'] * $hari_hadir;
        } else { // borongan
            $gaji_kotor = $k['nilai_upah'] * $total_unit;
        }

        // 4. Potong kasbon (yang paling lama dulu)
        $potongan = 0;
        $stmt = mysqli_prepare($conn,
            "SELECT * FROM kasbon WHERE id_karyawan = ? AND status = 'disetujui' AND sisa > 0
             ORDER BY tanggal_pengajuan ASC, id_kasbon ASC");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $kasbon = mysqli_stmt_get_result($stmt);

        while ($kb = mysqli_fetch_assoc($kasbon)) {
            // bayar = yang paling kecil di antara: cicilan, sisa utang, sisa gaji
            $bayar = min($kb['cicilan'], $kb['sisa'], $gaji_kotor - $potongan);
            if ($bayar <= 0) {
                break;   // gaji sudah habis untuk potongan
            }
            $potongan += $bayar;
            $sisa_baru   = $kb['sisa'] - $bayar;
            $status_baru = ($sisa_baru <= 0) ? 'lunas' : 'disetujui';

            $upd = mysqli_prepare($conn, "UPDATE kasbon SET sisa = ?, status = ? WHERE id_kasbon = ?");
            mysqli_stmt_bind_param($upd, "dsi", $sisa_baru, $status_baru, $kb['id_kasbon']);
            mysqli_stmt_execute($upd);
        }

        $gaji_bersih = $gaji_kotor - $potongan;

        // 5. Simpan hasil
        $stmt = mysqli_prepare($conn,
            "INSERT INTO penggajian (id_karyawan, periode_awal, periode_akhir, tipe_upah, nilai_upah,
                                     hari_hadir, total_unit, gaji_kotor, potongan_kasbon, gaji_bersih)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isssdiiddd",
            $id, $awal, $akhir, $k['tipe_upah'], $k['nilai_upah'],
            $hari_hadir, $total_unit, $gaji_kotor, $potongan, $gaji_bersih);
        mysqli_stmt_execute($stmt);

        $dihitung++;
    }

    $pesan = "Gaji $dihitung karyawan berhasil dihitung.";
    if ($dilewati) {
        $pesan .= " Dilewati karena sudah digaji pada periode ini: " . implode(', ', $dilewati) . ".";
    }
    if ($bulanan_dilewati) {
        $pesan .= " Karyawan bulanan tidak dihitung karena periode bukan 1 bulan penuh: "
                . implode(', ', $bulanan_dilewati) . ".";
    }
    set_flash($dihitung > 0 ? 'success' : 'warning', $pesan);
    redirect("pemilik/penggajian.php?awal=$awal&akhir=$akhir");
}

// =====================================================================
// TAMPILAN (GET)
// =====================================================================

// Riwayat periode yang pernah dihitung (dikelompokkan per periode di PHP)
$riwayat = [];
$q = mysqli_query($conn, "SELECT periode_awal, periode_akhir, gaji_bersih FROM penggajian ORDER BY periode_akhir DESC");
while ($r = mysqli_fetch_assoc($q)) {
    $kunci = $r['periode_awal'] . '_' . $r['periode_akhir'];
    if (!isset($riwayat[$kunci])) {
        $riwayat[$kunci] = ['periode_awal' => $r['periode_awal'], 'periode_akhir' => $r['periode_akhir'],
                            'jumlah_karyawan' => 0, 'total_bersih' => 0];
    }
    $riwayat[$kunci]['jumlah_karyawan']++;
    $riwayat[$kunci]['total_bersih'] += $r['gaji_bersih'];
}

// Detail satu periode (jika dipilih lewat ?awal=&akhir=)
$awal  = $_GET['awal'] ?? '';
$akhir = $_GET['akhir'] ?? '';
$detail = null;
if (strtotime($awal) && strtotime($akhir)) {
    $stmt = mysqli_prepare($conn,
        "SELECT penggajian.*, karyawan.nama
         FROM penggajian INNER JOIN karyawan ON penggajian.id_karyawan = karyawan.id_karyawan
         WHERE penggajian.periode_awal = ? AND penggajian.periode_akhir = ?
         ORDER BY karyawan.nama");
    mysqli_stmt_bind_param($stmt, "ss", $awal, $akhir);
    mysqli_stmt_execute($stmt);
    $detail = mysqli_stmt_get_result($stmt);
}

// Nilai awal form: Senin minggu lalu s.d. Sabtu minggu lalu
$default_awal  = date('Y-m-d', strtotime('monday last week'));
$default_akhir = date('Y-m-d', strtotime('saturday last week'));
$satuan = ['bulanan' => '', 'harian' => ' hari', 'borongan' => ' unit'];

$judul = 'Penggajian';
include __DIR__ . '/../includes/header.php';
?>

<?php tampil_flash(); ?>
<h3 class="mb-3">Penggajian</h3>

<div class="row g-3">
    <!-- Form hitung -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white"><b><i class="bi bi-calculator"></i> Hitung Gaji</b></div>
            <div class="card-body">
                <form method="POST" onsubmit="return confirm('Hitung gaji untuk periode ini? Kasbon akan otomatis dipotong.')">
                    <div class="mb-2">
                        <label class="form-label small">Dari tanggal</label>
                        <input type="date" name="awal" class="form-control" value="<?= $default_awal ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Sampai tanggal</label>
                        <input type="date" name="akhir" class="form-control" value="<?= $default_akhir ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button class="btn btn-primary w-100">Hitung &amp; Simpan</button>
                </form>
                <div class="form-text mt-2">
                    Harian: upah &times; hari hadir. Borongan: tarif &times; total unit.
                    Bulanan: upah tetap, hanya jika periode 1 bulan penuh (tgl 1 s.d. akhir bulan).
                    Cicilan kasbon dipotong otomatis.
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white"><b>Riwayat Penggajian</b></div>
            <div class="list-group list-group-flush">
                <?php if (empty($riwayat)): ?>
                    <div class="list-group-item text-muted small">Belum ada penggajian.</div>
                <?php endif; ?>
                <?php foreach ($riwayat as $r): ?>
                    <?php $aktif = ($r['periode_awal'] == $awal && $r['periode_akhir'] == $akhir); ?>
                    <a href="?awal=<?= $r['periode_awal'] ?>&akhir=<?= $r['periode_akhir'] ?>"
                       class="list-group-item list-group-item-action <?= $aktif ? 'active' : '' ?>">
                        <div class="fw-semibold"><?= tanggal_indo($r['periode_awal'], false) ?> &ndash; <?= tanggal_indo($r['periode_akhir'], false) ?></div>
                        <small><?= $r['jumlah_karyawan'] ?> karyawan &middot; <?= rupiah($r['total_bersih']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Detail periode -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <?php if (!$detail): ?>
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-receipt" style="font-size: 2.5rem;"></i>
                    <p class="mt-2 mb-0">Hitung gaji atau pilih periode di Riwayat Penggajian.</p>
                </div>
            <?php else: ?>
                <div class="card-header bg-white">
                    <b>Periode <?= tanggal_indo($awal, false) ?> &ndash; <?= tanggal_indo($akhir, false) ?></b>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Karyawan</th><th>Dasar</th><th class="text-end">Gaji Kotor</th>
                                <th class="text-end">Potong Kasbon</th><th class="text-end">Gaji Bersih</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php $total = 0; while ($g = mysqli_fetch_assoc($detail)): $total += $g['gaji_bersih']; ?>
                            <tr>
                                <td><?= e($g['nama']) ?><div class="small text-muted"><?= ucfirst($g['tipe_upah']) ?></div></td>
                                <td class="small">
                                    <?php if ($g['tipe_upah'] == 'bulanan'): ?>
                                        Tetap
                                    <?php elseif ($g['tipe_upah'] == 'harian'): ?>
                                        <?= rupiah($g['nilai_upah']) ?> &times; <?= $g['hari_hadir'] ?> hari
                                    <?php else: ?>
                                        <?= rupiah($g['nilai_upah']) ?> &times; <?= $g['total_unit'] ?> unit
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= rupiah($g['gaji_kotor']) ?></td>
                                <td class="text-end text-danger"><?= $g['potongan_kasbon'] > 0 ? '-' . rupiah($g['potongan_kasbon']) : '-' ?></td>
                                <td class="text-end fw-bold"><?= rupiah($g['gaji_bersih']) ?></td>
                                <td><a href="<?= BASE_URL ?>slip.php?id=<?= $g['id_gaji'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-printer"></i> Slip</a></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr><th colspan="4" class="text-end">Total dibayarkan</th>
                                <th class="text-end"><?= rupiah($total) ?></th><th></th></tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
