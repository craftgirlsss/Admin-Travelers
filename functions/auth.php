<?php
// functions/auth.php

// Wajib dimulai di setiap file PHP yang menggunakan sesi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memastikan user saat ini memiliki role 'super_admin'.
 * Jika tidak, user akan diarahkan ke halaman login.
 */
function check_admin_access() {
    // 1. Cek apakah ada sesi user_id dan user_role
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        // Jika tidak ada sesi, redirect
        header('Location: ../index.php?error=Akses_diperlukan.');
        exit();
    }
    
    // 2. Cek apakah role user adalah 'super_admin'
    if ($_SESSION['user_role'] !== 'super_admin') {
        // Jika role bukan admin, redirect
        header('Location: ../index.php?error=Akses_ditolak.');
        // Hapus sesi yang tidak valid
        session_destroy(); 
        exit();
    }
}

/**
 * Mencatat setiap aksi Admin ke tabel admin_activities.
 * Digunakan setelah user login dan sesi admin_id sudah ada.
 * * @param string $action_type Tipe aksi (e.g., 'disable', 'approve', 'delete').
 * @param string $target_table Tabel yang menjadi target aksi (e.g., 'users', 'trips', 'providers').
 * @param int $target_id ID baris target yang diubah.
 * @param mysqli $conn Objek koneksi database.
 * @param string $description Deskripsi tambahan (opsional).
 */
/**
 * Menyimpan log aktivitas admin ke tabel 'logs'.
 * * @param string $action_type Jenis aksi (e.g., 'create', 'update', 'delete', 'approve').
 * @param string $resource_type Sumber daya (e.g., 'users', 'trips', 'bookings').
 * @param int $resource_id ID sumber daya yang terpengaruh.
 * @param mysqli $conn Objek koneksi database.
 * @param string $details Deskripsi tambahan aksi.
 * @return bool True jika logging berhasil.
 */
function log_admin_activity($action_type, $resource_type, $resource_id, $conn, $details = '') {
    // Pastikan admin sudah login
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
        // Jangan log jika admin_id tidak ditemukan atau bukan super_admin
        return false;
    }

    $admin_id = $_SESSION['user_id'];
    
    // Siapkan query INSERT
    $query = "INSERT INTO logs (admin_id, action_type, resource_type, resource_id, details) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    
    // Bind parameter
    // Tipe data: i=integer, s=string. (admin_id, action_type, resource_type, resource_id, details)
    $stmt->bind_param("issis", $admin_id, $action_type, $resource_type, $resource_id, $details);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Fungsi baru untuk mencatat aktivitas di halaman publik (Login, Forgot/Reset Password).
 * Fungsi ini memungkinkan admin_id NULL (0) jika aksi dilakukan oleh user yang belum terotentikasi.
 * * @param int $admin_id ID Admin yang mencoba beraksi (0 atau NULL jika tidak diketahui).
 * @param string $action_type Tipe aksi (e.g., 'login_success', 'password_reset_request').
 * @param string $description Deskripsi detail.
 * @param string $target_table Tabel yang relevan ('users', 'password_resets', dll).
 * @param int $target_id ID baris target (ID user, ID token, atau 0).
 * @param mysqli $conn Objek koneksi database.
 */
function log_admin_activity_public($admin_id, $action_type, $description, $target_table, $target_id, $conn) {
    
    // Konversi 0 menjadi NULL untuk DB jika admin_id adalah opsional/NULL di tabel
    $admin_id_to_bind = ($admin_id === 0 || $admin_id === NULL) ? NULL : $admin_id;

    // Gunakan prepared statement untuk keamanan
    // admin_id di bind_param harus menggunakan "i" (integer) karena tipenya bigint di DB
    $stmt = $conn->prepare("INSERT INTO admin_activities (admin_id, action_type, description, target_table, target_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

    // Jika admin_id NULL, kita harus menggunakan trik bind_param untuk parameter NULL
    if ($admin_id_to_bind === NULL) {
        $stmt->bind_param("isssi", $null_val, $action_type, $description, $target_table, $target_id);
        $null_val = $admin_id_to_bind;
    } else {
        $stmt->bind_param("isssi", $admin_id_to_bind, $action_type, $description, $target_table, $target_id);
    }

    try {
        $stmt->execute();
    } catch (Exception $e) {
        // Opsional: Log error database
        // error_log("Error logging public activity: " . $e->getMessage());
    } finally {
        $stmt->close();
    }
}

// Catatan: Anda juga harus menambahkan fungsi sanitize_input() ke file ini
// atau ke file utility lain yang di-require sebelum digunakan.
// Misalnya:
/*
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
*/
?>