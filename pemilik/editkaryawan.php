<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

// ---------- Ambil data lama berdasarkan ?id= ----------
$id = (int) ($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn,
    "SELECT karyawan.*, users.username
     FROM karyawan INNER JOIN users ON karyawan.id_user = users.id_user
     WHERE karyawan.id_karyawan = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$lama) {
    set_flash('danger', 'Karyawan tidak ditemukan.');
    redirect('pemilik/karyawan.php');
}

$errors = [];
$data = [
    'nama'          => $lama['nama'],
    'no_hp'         => $lama['no_hp'],
    'alamat'        => $lama['alamat'],
    'tipe_upah'     => $lama['tipe_upah'],
    'nilai_upah'    => $lama['nilai_upah'],
    'tanggal_masuk' => $lama['tanggal_masuk'],
    'username'      => $lama['username'],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($data as $kolom => $nilai) {
        $data[$kolom] = trim($_POST[$kolom] ?? '');
    }
    $password = $_POST['password'] ?? '';   // kosong = password tidak diganti

    // ---------- Validasi (sama seperti tambah) ----------
    if ($data['nama'] == '' || strlen($data['nama']) > 100) {
        $errors[] = "Nama wajib diisi (maksimal 100 karakter).";
    }
    if ($data['no_hp'] != '' && (!is_numeric($data['no_hp']) || strlen($data['no_hp']) < 8)) {
        $errors[] = "No. HP hanya boleh angka (minimal 8 digit).";
    }
    if (!in_array($data['tipe_upah'], ['bulanan', 'harian', 'borongan'])) {
        $errors[] = "Tipe upah wajib dipilih.";
    }
    if (!is_numeric($data['nilai_upah']) || $data['nilai_upah'] <= 0) {
        $errors[] = "Nilai upah harus angka lebih dari 0.";
    }
    if (!strtotime($data['tanggal_masuk'])) {
        $errors[] = "Tanggal masuk tidak valid.";
    }
    if (strlen($data['username']) < 3 || strlen($data['username']) > 50 || strpos($data['username'], ' ') !== false) {
        $errors[] = "Username 3-50 karakter dan tidak boleh mengandung spasi.";
    } else {
        // Username tidak boleh sama dengan akun LAIN (akun sendiri boleh)
        $stmt = mysqli_prepare($conn, "SELECT id_user FROM users WHERE username = ? AND id_user != ?");
        mysqli_stmt_bind_param($stmt, "si", $data['username'], $lama['id_user']);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = "Username sudah dipakai karyawan lain.";
        }
    }
    if ($password != '' && strlen($password) < 8) {
        $errors[] = "Password baru minimal 8 karakter.";
    }

    // ---------- Foto baru (opsional) ----------
    $foto = $lama['foto'];   // default: tetap pakai foto lama
    if (empty($errors) && isset($_FILES['foto']) && $_FILES['foto']['name'] != '') {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        if ($_FILES['foto']['error'] != 0 || $_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Ukuran foto maksimal 2 MB.";
        } elseif (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $errors[] = "Foto harus berformat .jpg, .jpeg, atau .png.";
        } else {
            $foto = 'karyawan_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/' . $foto);
            hapus_foto($lama['foto']);   // buang file foto lama
        }
    }

    // ---------- Simpan ----------
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn,
            "UPDATE karyawan
             SET nama = ?, no_hp = ?, alamat = ?, tipe_upah = ?, nilai_upah = ?, foto = ?, tanggal_masuk = ?
             WHERE id_karyawan = ?");
        mysqli_stmt_bind_param($stmt, "ssssdssi",
            $data['nama'], $data['no_hp'], $data['alamat'], $data['tipe_upah'],
            $data['nilai_upah'], $foto, $data['tanggal_masuk'], $id);
        mysqli_stmt_execute($stmt);

        if ($password != '') {
            // Ganti username + password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, password = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $data['username'], $hash, $lama['id_user']);
        } else {
            // Ganti username saja
            $stmt = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, "si", $data['username'], $lama['id_user']);
        }
        mysqli_stmt_execute($stmt);

        set_flash('success', 'Data ' . $data['nama'] . ' berhasil diperbarui.');
        redirect('pemilik/karyawan.php');
    }
}

$judul = 'Edit Karyawan';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-pencil"></i> Edit Karyawan: <?= e($lama['nama']) ?></h5>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>

                <form method="POST" enctype="multipart/form-data">
                    <h6 class="text-muted">Data Karyawan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" value="<?= e($data['nama']) ?>" maxlength="100" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. HP</label>
                            <input type="text" name="no_hp" class="form-control" value="<?= e($data['no_hp']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2"><?= e($data['alamat']) ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipe Upah</label>
                            <select name="tipe_upah" class="form-select" required>
                                <?php foreach (['bulanan' => 'Bulanan (per bulan)', 'harian' => 'Harian (per hari hadir)', 'borongan' => 'Borongan (per unit)'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $data['tipe_upah'] == $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nilai Upah (Rp)</label>
                            <input type="number" name="nilai_upah" class="form-control" value="<?= e((float) $data['nilai_upah']) ?>" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Masuk</label>
                            <input type="date" name="tanggal_masuk" class="form-control" value="<?= e($data['tanggal_masuk']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Foto</label>
                        <?php if ($lama['foto'] && file_exists(__DIR__ . '/../uploads/' . $lama['foto'])): ?>
                            <div class="mb-2">
                                <img src="<?= BASE_URL ?>uploads/<?= e($lama['foto']) ?>" class="rounded" height="90">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png">
                        <div class="form-text">Biarkan kosong jika foto tidak diganti.</div>
                    </div>

                    <h6 class="text-muted">Akun Login Karyawan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= e($data['username']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diganti">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
                    <a href="karyawan.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
