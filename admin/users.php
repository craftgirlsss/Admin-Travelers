<?php
// admin/users.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

// Panggil fungsi keamanan di awal file
check_admin_access();

// Logika untuk Aksi (Disable/Enable/Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $message = "";

    // Gunakan Prepared Statements untuk keamanan
    if ($action == 'disable' || $action == 'enable') {
        $new_status = ($action == 'enable') ? 'active' : 'disabled';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'super_admin'");
        $stmt->bind_param("si", $new_status, $user_id);
        
        if ($stmt->execute()) {
            $message = "User ID " . $user_id . " berhasil diubah status menjadi " . $new_status . ".";
        } else {
            $message = "Gagal mengubah status: " . $conn->error;
        }
        $stmt->close();

    } elseif ($action == 'delete') {
        // Implementasi Soft Delete: Set kolom status/is_active menjadi 'deleted' 
        // ATAU tambahkan kolom `deleted_at` dan set timestamp.
        // Kita gunakan set status='deleted' untuk kesederhanaan.
        
        $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE id = ? AND role != 'super_admin'");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $message = "User ID " . $user_id . " berhasil dihapus (Soft Deleted).";
        } else {
            $message = "Gagal menghapus user: " . $conn->error;
        }
        $stmt->close();
    }
    // Redirect untuk menghindari pengiriman ulang form (Post/Redirect/Get pattern)
    header("Location: users.php?status_msg=" . urlencode($message));
    exit();
}

// ----------------------------------------------------
// QUERY DATA UTAMA
// ----------------------------------------------------

// Mengambil semua user (Client & Provider), tidak termasuk Super Admin
$users_query = "
    SELECT u.id, u.name, u.email, u.phone, u.role, u.status, p.company_name 
    FROM users u
    LEFT JOIN providers p ON u.id = p.user_id 
    WHERE u.role != 'super_admin'
    ORDER BY u.role, u.created_at DESC
";
$result = $conn->query($users_query);

$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen User & Provider</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Pengaturan Akun Pengguna</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Semua Pengguna</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama / Perusahaan</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): 
                                // Tentukan nama yang ditampilkan berdasarkan role
                                $display_name = ($user['role'] == 'provider' && $user['company_name']) ? $user['company_name'] : $user['name'];
                                // Tentukan warna status
                                $status_badge = '';
                                if ($user['status'] == 'active') {
                                    $status_badge = 'badge bg-success';
                                } elseif ($user['status'] == 'disabled') {
                                    $status_badge = 'badge bg-warning text-dark';
                                } elseif ($user['status'] == 'deleted') {
                                    $status_badge = 'badge bg-danger';
                                }
                            ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($display_name); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><span class="<?php echo $status_badge; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <a href="users.php?action=disable&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-warning btn-sm" 
                                           onclick="return confirm('Yakin ingin MENONAKTIFKAN user ini?')"
                                        >Disable</a>
                                    <?php elseif ($user['status'] == 'disabled'): ?>
                                        <a href="users.php?action=enable&id=<?php echo $user['id']; ?>" 
                                           class="btn btn-success btn-sm"
                                        >Enable</a>
                                    <?php endif; ?>
                                    
                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Yakin ingin MENGHAPUS user ini? (Soft Delete)')"
                                    >Hapus</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
// Jika Anda tidak menggunakan file footer, pastikan ada penutup div dan script JS di sini
// Jika menggunakan header.php seperti yang saya tunjukkan, Anda hanya perlu tag penutup:
echo '</div>'; // Tutup div id="wrapper"
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>