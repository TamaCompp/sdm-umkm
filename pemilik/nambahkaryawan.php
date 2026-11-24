<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/auth.php';
wajib_login('pemilik');

$errors = [];

// Nilai awal form (kosong). Diisi ulang dari $_POST kalau ada error,
// supaya pemilik tidak perlu mengetik semuanya dari awal.
$data = [
    'nama' => '', 'no_hp' => '', 'alamat' => '', 'tipe_upah' => '',
    'nilai_upah' => '', 'tanggal_masuk' => date('Y-m-d'), 'username' => '',
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($data as $kolom => $nilai) {
        $data[$kolom] = trim($_POST[$kolom] ?? '');
    }
    $password = $_POST['password'] ?? '';

    // ---------- Validasi ----------
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
        // Username tidak boleh dipakai akun lain
        $stmt = mysqli_prepare($conn, "SELECT id_user FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $data['username']);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = "Username sudah dipakai.";
        }
    }
    if (strlen($password) < 8) {
        $errors[] = "Password minimal 8 karakter.";
    }

    // ---------- Upload foto (opsional, seperti Modul 8) ----------
    $foto = null;
    if (empty($errors) && isset($_FILES['foto']) && $_FILES['foto']['name'] != '') {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

        if ($_FILES['foto']['error'] != 0 || $_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $errors[] = "Ukuran foto maksimal 2 MB.";
        } elseif (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $errors[] = "Foto harus berformat .jpg, .jpeg, atau .png.";
        } else {
            $foto = 'karyawan_' . time() . '.' . $ext;   // nama unik berdasarkan waktu
            move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/' . $foto);
        }
    }

    // ---------- Simpan ----------
    if (empty($errors)) {
        // 1. Buat akun login
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, 'karyawan')");
        mysqli_stmt_bind_param($stmt, "ss", $data['username'], $hash);
        mysqli_stmt_execute($stmt);

        // 2. Ambil id akun yang baru dibuat (username pasti unik)
        $stmt = mysqli_prepare($conn, "SELECT id_user FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $data['username']);
        mysqli_stmt_execute($stmt);
        $id_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['id_user'];

        // 3. Buat data karyawan yang terhubung ke akun tadi
        $stmt = mysqli_prepare($conn,
            "INSERT INTO karyawan (id_user, nama, no_hp, alamat, tipe_upah, nilai_upah, foto, tanggal_masuk)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issssdss",
            $id_user, $data['nama'], $data['no_hp'], $data['alamat'],
            $data['tipe_upah'], $data['nilai_upah'], $foto, $data['tanggal_masuk']);
        mysqli_stmt_execute($stmt);

        set_flash('success', 'Karyawan ' . $data['nama'] . ' berhasil ditambahkan.');
        redirect('pemilik/karyawan.php');
    }
}

$judul = 'Tambah Karyawan';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> Tambah Karyawan</h5>
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
                            <input type="text" name="no_hp" class="form-control" value="<?= e($data['no_hp']) ?>" placeholder="08xxxxxxxxxx">
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
                                <option value="">-- Pilih --</option>
                                <?php foreach (['bulanan' => 'Bulanan (per bulan)', 'harian' => 'Harian (per hari hadir)', 'borongan' => 'Borongan (per unit)'] as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $data['tipe_upah'] == $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nilai Upah (Rp)</label>
                            <input type="number" name="nilai_upah" class="form-control" value="<?= e($data['nilai_upah']) ?>" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Masuk</label>
                            <input type="date" name="tanggal_masuk" class="form-control" value="<?= e($data['tanggal_masuk']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Foto <span class="text-muted">(opsional, .jpg/.png, maks 2 MB)</span></label>
                        <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png">
                    </div>

                    <h6 class="text-muted">Akun Login Karyawan</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= e($data['username']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                    <a href="karyawan.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
