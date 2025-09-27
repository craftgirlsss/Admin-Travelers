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
?>