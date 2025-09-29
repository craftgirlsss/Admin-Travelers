<?php
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
 * @param string $action_type Tipe aksi (e.g., 'disable', 'approve', 'delete').
 * @param string $target_table Tabel yang menjadi target aksi (e.g., 'users', 'trips', 'providers').
 * @param int $target_id ID baris target yang diubah.
 * @param mysqli $conn Objek koneksi database.
 * @param string $description Deskripsi tambahan (opsional).
 */
function log_admin_activity($action_type, $target_table, $target_id, $conn, $description = null) {
    if (!isset($_SESSION['user_id'])) {
        // Jangan catat jika ID Admin tidak diketahui
        return;
    }

    $admin_id = $_SESSION['user_id'];
    
    // Gunakan prepared statement untuk keamanan
    $stmt = $conn->prepare("INSERT INTO admin_activities (admin_id, action_type, target_table, target_id, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssi", $admin_id, $action_type, $target_table, $target_id, $description);

    try {
        $stmt->execute();
    } catch (Exception $e) {
        // Opsional: Log error database, tetapi jangan hentikan eksekusi utama
        // echo "Error logging activity: " . $e->getMessage();
    } finally {
        $stmt->close();
    }
}
?>