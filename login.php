<?php
require_once 'config/koneksi.php';
require_once 'includes/functions.php';

// Sudah login? Langsung ke dashboard
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    redirect($_SESSION['role'] . '/dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Ambil akun + data karyawan (LEFT JOIN: pemilik tidak punya data karyawan)
    $stmt = mysqli_prepare($conn,
        "SELECT users.id_user, users.username, users.password, users.role,
                karyawan.id_karyawan, karyawan.nama, karyawan.status
         FROM users
         LEFT JOIN karyawan ON karyawan.id_user = users.id_user
         WHERE users.username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Username atau password salah.";
    } elseif ($user['role'] === 'karyawan' && $user['status'] !== 'aktif') {
        $error = "Akun Anda sudah dinonaktifkan. Hubungi pemilik usaha.";
    } else {
        // Login berhasil: simpan data penting di session
        session_regenerate_id(true);
        $_SESSION['login']       = true;
        $_SESSION['id_user']     = $user['id_user'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['nama']        = $user['nama'] ?? $user['username'];
        $_SESSION['id_karyawan'] = $user['id_karyawan'];   // null untuk pemilik

        redirect($user['role'] . '/dashboard.php');
    }
}

$judul = 'Login';
include 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <i class="bi bi-people-fill text-primary" style="font-size: 3rem;"></i>
                    <h4 class="mt-2 mb-0">SDM UMKM</h4>
                    <small class="text-muted">Manajemen karyawan, absensi, kasbon &amp; gaji</small>
                </div>

                <?php tampil_flash(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control"
                               value="<?= e($username) ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                <p class="text-center mt-3 mb-0 small">
                    Pemilik usaha baru? <a href="register.php">Daftar di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
