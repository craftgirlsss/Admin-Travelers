<?php
// index.php
session_start();

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
    
    // Sertakan koneksi database
    include 'config/database.php';
    
    // Ambil input dan bersihkan
    $email = sanitize_input($_POST['email']);
    $password_input = $_POST['password'];
    
    // --- OUTPUT DEBUG: Input dari Form ---
    // echo "DEBUG ➡️ Email Input: " . htmlspecialchars($email) . "<br>";
    // echo "DEBUG ➡️ Password Input (polos): " . htmlspecialchars($password_input) . "<br>";
    // ------------------------------------

    // 1. Gunakan Prepared Statement untuk mencari user berdasarkan email
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // --- OUTPUT DEBUG: User Ditemukan ---
        // echo "DEBUG 🟢 User Ditemukan di Database! <br>";
        // ------------------------------------
        
        $user = $result->fetch_assoc();
        
        // --- OUTPUT DEBUG: Hash dari Database ---
        // echo "DEBUG 🔑 Hash dari DB: " . htmlspecialchars($user['password']) . "<br>";
        // ---------------------------------------

        // 2. Verifikasi Password (menggunakan fungsi PHP yang aman)
        $is_password_valid = password_verify($password_input, $user['password']);

        // --- OUTPUT DEBUG: Hasil password_verify() ---
        // echo "DEBUG 🔍 Hasil password_verify(): ";
        // var_dump($is_password_valid);
        // echo "<br>";
        // ----------------------------------------------

        if ($is_password_valid) {
            
            // 3. Verifikasi Role (HARUS 'super_admin')
            if ($user['role'] === 'super_admin') {
                
                // BERHASIL LOGIN ADMIN: Set Session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                
                // Redirect ke Dashboard Admin (Ini akan menghentikan semua output debug!)
                header('Location: admin/dashboard.php');
                exit();
            } else {
                $error = "Akses Ditolak. Anda bukan Super Admin (Role: " . htmlspecialchars($user['role']) . ")";
            }
        } else {
            // Masuk ke sini jika password_verify gagal
            $error = "Email atau password salah.";
        }
    } else {
        // User tidak ditemukan
        $error = "Email atau password salah.";
    }
    $stmt->close();
    $conn->close();
}
// ===============================================
// LOGIKA PROSES LOGIN SELESAI
// ===============================================
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Super Admin - Travelers</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f9; }
        .login-box { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 300px; }
        .error { color: red; margin-bottom: 15px; text-align: center; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; background-color: #007bff; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Super Admin Login</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        
        <form method="POST" action="index.php">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>