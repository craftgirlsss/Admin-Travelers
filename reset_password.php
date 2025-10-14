<?php
// reset_password.php

// Pastikan file-file yang diperlukan (koneksi DB, sanitize, dan logging) dimuat di awal.
require_once 'config/database.php';
require_once 'functions/auth.php'; // Asumsi file ini berisi sanitize_input() dan log_admin_activity_public()

// HAPUS BLOK DEKLARASI ULANG FUNGSI:
// if (!function_exists('sanitize_input')) { ... }
// Karena blok ini menyebabkan Fatal Error jika fungsi sudah di-load dari functions/auth.php.

$token = sanitize_input($_GET['token'] ?? '');
$user_id = 0;
$error = '';
$success = false;

// 1. Verifikasi Token dan Kedaluwarsa
if (!empty($token)) {
    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE reset_token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_id = $result->fetch_assoc()['user_id'];
    } else {
        $error = "Tautan reset tidak valid atau sudah kadaluarsa. Silakan ajukan permintaan baru.";
        // *** LOG RESET GAGAL: TOKEN INVALID/KADALUARSA ***
        // ID Admin tidak diketahui pada tahap ini, jadi gunakan 0
        log_admin_activity_public(0, 'password_reset_failed', 'Reset password gagal: Token tidak valid/kedaluwarsa (Token: ' . $token . ')', 'password_resets', 0, $conn);
    }
    $stmt->close();
} else {
    $error = "Token reset tidak ditemukan.";
    // *** LOG RESET GAGAL: TOKEN HILANG ***
    log_admin_activity_public(0, 'password_reset_failed', 'Reset password gagal: Token tidak ditemukan dalam URL', 'password_resets', 0, $conn);
}

// ----------------------------------------------------
// LOGIKA SUBMIT PASSWORD BARU
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password harus memiliki minimal 8 karakter.";
    } else {
        // Hashing password baru
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password di tabel users
        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt_update->bind_param("si", $hashed_password, $user_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Hapus token dari tabel password_resets agar tidak bisa digunakan lagi
        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE reset_token = ?");
        $stmt_delete->bind_param("s", $token);
        $stmt_delete->execute();
        $stmt_delete->close();

        // *** LOG RESET SUKSES ***
        log_admin_activity_public($user_id, 'password_reset_success', 'Password berhasil diubah menggunakan token reset', 'users', $user_id, $conn);

        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .auth-card {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: white;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <h3 class="text-center mb-4 text-primary">Atur Ulang Password</h3>

        <?php if ($success): ?>
            <div class="alert alert-success text-center">
                Password Anda berhasil direset! Silakan <a href="index.php">Login</a>.
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger text-center">
                <?php echo $error; ?>
            </div>
        <?php elseif ($user_id > 0): ?>
            <p class="text-muted text-center mb-4">Masukkan password baru Anda.</p>
            <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>">
                <div class="mb-3">
                    <label for="password" class="form-label">Password Baru</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8">
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mt-2">Ubah Password</button>
            </form>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>