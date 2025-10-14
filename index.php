<?php
// index.php
session_start();

// Pastikan file-file yang diperlukan (termasuk koneksi DB dan fungsi logging/sanitasi) dimuat di awal.
// Asumsi: 'functions/auth.php' sekarang berisi sanitize_input() dan log_admin_activity_public()
require_once 'functions/auth.php'; 
require_once 'config/database.php'; 

// Hentikan debugging output jika sudah berhasil login
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'super_admin') {
    header('Location: admin/dashboard.php');
    exit();
}

// ===============================================
// LOGIKA PROSES LOGIN DIMULAI DI SINI
// ===============================================

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Koneksi DB sudah tersedia melalui require_once 'config/database.php'
    
    // Pastikan sanitize_input() tersedia dari functions/auth.php
    $email = sanitize_input($_POST['email']);
    $password_input = $_POST['password'];
    $admin_id_attempt = 0; // Default ID jika user tidak ditemukan

    // 1. Gunakan Prepared Statement untuk mencari user berdasarkan email
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $admin_id_attempt = $user['id']; // Ambil ID user untuk logging jika gagal
        
        // 2. Verifikasi Password
        if (password_verify($password_input, $user['password'])) {
            
            // 3. Verifikasi Role (HARUS 'super_admin')
            if ($user['role'] === 'super_admin') {
                
                // BERHASIL LOGIN ADMIN: Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                
                // *** LOG SUKSES ***
                log_admin_activity_public($user['id'], 'login_success', 'Admin login berhasil', 'users', $user['id'], $conn);
                
                header('Location: admin/dashboard.php');
                exit();
            } else {
                $error = "Akses Ditolak. Anda bukan Super Admin.";
                // *** LOG GAGAL: ROLE TIDAK SESUAI ***
                log_admin_activity_public($user['id'], 'login_failed', 'Login gagal: Role tidak diizinkan', 'users', $user['id'], $conn);
            }
        } else {
            // Masuk ke sini jika password_verify gagal
            $error = "Email atau password salah.";
            // *** LOG GAGAL: PASSWORD SALAH ***
            log_admin_activity_public($admin_id_attempt, 'login_failed', 'Login gagal: Password salah', 'users', $admin_id_attempt, $conn);
        }
    } else {
        // User tidak ditemukan
        $error = "Email atau password salah.";
        // *** LOG GAGAL: USER TIDAK DITEMUKAN ***
        log_admin_activity_public(0, 'login_failed', 'Login gagal: User tidak ditemukan (Email: ' . $email . ')', 'users', 0, $conn);
    }
    $stmt->close();
    // Koneksi $conn akan ditutup di akhir skrip atau oleh database.php jika didefinisikan sebagai include, 
    // tetapi kita hapus penutupan manual untuk menghindari konflik, karena koneksi mungkin dibutuhkan di tempat lain.
    // $conn->close(); 
}
// ===============================================
// LOGIKA PROSES LOGIN SELESAI
// ===============================================
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Super Admin - Travelers</title>
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
        <h3 class="text-center mb-4 text-primary">Super Admin Login</h3>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="index.php">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
            
            <div class="text-center">
                <a href="forgot_password.php" class="text-muted text-decoration-none">Lupa Password?</a>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>