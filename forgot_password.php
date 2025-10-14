<?php
// forgot_password.php

// Pastikan file-file yang diperlukan (koneksi DB, sanitize, dan logging) dimuat di awal.
require_once 'config/database.php';
require_once 'functions/auth.php'; // Asumsi file ini berisi sanitize_input() dan log_admin_activity_public()

// HAPUS BLOK DEKLARASI ULANG FUNGSI:
// if (!function_exists('sanitize_input')) { ... }
// Karena blok ini menyebabkan Fatal Error jika fungsi sudah di-load dari functions/auth.php.

// Fungsi untuk mengirim notifikasi WA
function send_whatsapp_notification($to, $message) {
    // API Key dan Auth Key yang Anda sediakan
    $appkey = '160fe907-3e2e-42c6-8dcd-d41fe0f642fd';
    $authkey = 'EKPcyLZAeecp7g9DMKfc6gNTWayIFFsSHJPb8c9Q2e89FNyz4v';

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://app.wapanels.com/api/create-message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'appkey' => $appkey,
            'authkey' => $authkey,
            'to' => $to, 
            'message' => $message,
            'sandbox' => 'false'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

$message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);

    // 1. Cari User berdasarkan Email
    $stmt = $conn->prepare("SELECT id, name, phone FROM users WHERE email = ? AND (role = 'super_admin' OR role = 'admin')");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user = $user_result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $user_id = $user['id'];
        $user_name = $user['name'];
        // Format nomor telepon menjadi +62 (asumsi format DB 08...)
        $user_phone = str_replace('08', '+628', $user['phone']); 

        // 2. Buat Token Reset yang unik
        $token = bin2hex(random_bytes(30));
        
        // Waktu kedaluwarsa (1 jam dari sekarang)
        $expires_at = date('Y-m-d H:i:s', time() + 3600); 

        // 3. Simpan Token ke tabel password_resets
        // Kolom di DB: user_id, reset_token, expires_at
        $stmt_insert = $conn->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iss", $user_id, $token, $expires_at);
        $stmt_insert->execute();
        $reset_id = $conn->insert_id; // Ambil ID baris baru untuk logging
        $stmt_insert->close();

        // 4. Kirim Konfirmasi dan Link Reset
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

        // --- Notifikasi Email (Placeholder) ---
        $email_subject = "Permintaan Reset Password Admin Travelers";
        $email_body = "Hai $user_name,\n\nKami menerima permintaan reset password untuk akun Anda. Klik link ini untuk melanjutkan:\n$reset_link\n\nLink ini akan kadaluarsa dalam 1 jam.";
        // mail($email, $email_subject, $email_body); 
        
        // --- Notifikasi WhatsApp ---
        $wa_message = "Hai $user_name, kami mendeteksi adanya permintaan reset password pada akun Admin Anda. \n\nKlik link berikut untuk melakukan reset:\n$reset_link\n\nLink ini berlaku 1 jam. Abaikan pesan ini jika Anda tidak merasa melakukan permintaan reset.";
        send_whatsapp_notification($user_phone, $wa_message);

        // *** LOG PERMINTAAN RESET SUKSES ***
        log_admin_activity_public($user_id, 'password_reset_request', 'Permintaan reset password dikirim melalui email/WA', 'password_resets', $reset_id, $conn);

        $message = "Link reset password telah dikirimkan ke email Anda dan notifikasi dikirimkan ke WhatsApp Anda. Silakan cek.";
        $alert_type = 'success';

    } else {
        $message = "Email tidak ditemukan atau bukan akun Admin.";
        $alert_type = 'danger';
        
        // *** LOG PERMINTAAN RESET GAGAL ***
        log_admin_activity_public(0, 'password_reset_request_failed', 'Permintaan reset password gagal (Email: ' . $email . ')', 'users', 0, $conn);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password Admin</title>
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
        <h3 class="text-center mb-4 text-primary">Lupa Password Admin</h3>
        <p class="text-muted text-center mb-4">Masukkan email akun Admin Anda untuk mendapatkan tautan reset.</p>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $alert_type; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <div class="mb-3">
                <label for="email" class="form-label">Alamat Email</label>
                <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mt-2">Kirim Tautan Reset</button>
            <a href="index.php" class="btn btn-link w-100 mt-3 text-decoration-none">Kembali ke Login</a>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>