<?php
require_once 'config/koneksi.php';
require_once 'includes/functions.php';

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    redirect($_SESSION['role'] . '/dashboard.php');
}

// Register hanya dibuka jika BELUM ada akun pemilik.
// Akun karyawan dibuat oleh pemilik lewat menu Tambah Karyawan.
$cek = mysqli_query($conn, "SELECT id_user FROM users WHERE role = 'pemilik'");
$sudah_ada_pemilik = mysqli_num_rows($cek) > 0;

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$sudah_ada_pemilik) {
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';

    if (strlen($username) < 3 || strlen($username) > 50 || strpos($username, ' ') !== false) {
        $errors[] = "Username 3-50 karakter dan tidak boleh mengandung spasi.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password minimal 8 karakter.";
    } elseif ($password !== $konfirmasi) {
        $errors[] = "Konfirmasi password tidak sama.";
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, 'pemilik')");
        mysqli_stmt_bind_param($stmt, "ss", $username, $hash);
        mysqli_stmt_execute($stmt);

        set_flash('success', 'Akun pemilik berhasil dibuat. Silakan login.');
        redirect('login.php');
    }
}

$judul = 'Daftar Pemilik';
include 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="mb-1">Daftar Akun Pemilik</h4>
                <p class="text-muted small mb-4">Dipakai sekali saat pertama kali memakai sistem.</p>

                <?php if ($sudah_ada_pemilik): ?>
                    <div class="alert alert-info mb-0">
                        Akun pemilik sudah terdaftar. Akun karyawan dibuat oleh pemilik
                        melalui menu <b>Karyawan</b>.
                        <div class="mt-2"><a href="login.php">&larr; Kembali ke login</a></div>
                    </div>
                <?php else: ?>
                    <?php foreach ($errors as $err): ?>
                        <div class="alert alert-danger py-2"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= e($username) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" name="konfirmasi" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Daftar</button>
                    </form>
                    <p class="text-center mt-3 mb-0 small"><a href="login.php">Sudah punya akun? Login</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
