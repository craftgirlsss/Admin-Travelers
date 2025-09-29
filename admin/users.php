<?php
// admin/users.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

// Logika Aksi (Disable/Enable/Delete) tetap sama di sini...

if (isset($_GET['action']) && isset($_GET['id'])) {
    // ... (LOGIKA AKSI DISABLE/ENABLE/DELETE SEBELUMNYA) ...
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
            log_admin_activity($action, 'users', $user_id, $conn, "Status diubah menjadi " . $new_status);
        } else {
            $message = "Gagal mengubah status: " . $conn->error;
        }
        $stmt->close();

    } elseif ($action == 'delete') {
        // Soft Delete
        $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE id = ? AND role != 'super_admin'");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $message = "User ID " . $user_id . " berhasil dihapus (Soft Deleted).";
        } else {
            $message = "Gagal menghapus user: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: users.php?status_msg=" . urlencode($message));
    exit();
}


// ----------------------------------------------------
// QUERY DATA CLIENT
// ----------------------------------------------------
$client_query = "
    SELECT u.id, u.name, u.email, u.phone, u.status 
    FROM users u
    WHERE u.role IN ('client', 'customer') -- DIPERBAIKI: Sertakan kedua kemungkinan role
    ORDER BY u.created_at DESC
";
$client_result = $conn->query($client_query);
$clients = [];
if ($client_result) {
    while ($row = $client_result->fetch_assoc()) {
        $clients[] = $row;
    }
}


// ----------------------------------------------------
// QUERY DATA PROVIDER (Hanya yang Verified/Pending, TIDAK termasuk Unverified/Rejected)
// Kita anggap Provider yang *sudah di-review* layak tampil di sini.
// ----------------------------------------------------
$provider_query = "
    SELECT u.id, u.name, u.email, u.phone, u.status AS user_status, p.company_name, p.verification_status, p.id AS provider_db_id
    FROM users u
    JOIN providers p ON u.id = p.user_id 
    WHERE u.role = 'provider' AND p.verification_status = 'verified'
    ORDER BY p.verification_date DESC
";
$provider_result = $conn->query($provider_query);

$providers = [];
if ($provider_result) {
    while ($row = $provider_result->fetch_assoc()) {
        $providers[] = $row;
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
            <div class="card-header py-3">
                <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="client-tab" data-bs-toggle="tab" href="#client-list" role="tab">Daftar Client</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="provider-tab" data-bs-toggle="tab" href="#provider-list" role="tab">Daftar Provider (Verified)</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="userTabsContent">
                    
                    <div class="tab-pane fade show active" id="client-list" role="tabpanel">
                        <h6 class="m-0 font-weight-bold text-primary mb-3">Total Client: <?php echo count($clients); ?></h6>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Telepon</th>
                                        <th>Status Akun</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $user): 
                                        $status_badge = ($user['status'] == 'active') ? 'badge bg-success' : 'badge bg-warning text-dark';
                                        if ($user['status'] == 'deleted') $status_badge = 'badge bg-danger';
                                    ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td><span class="<?php echo $status_badge; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                        <td>
                                            <a href="user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">Detail & Aksi</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="provider-list" role="tabpanel">
                        <h6 class="m-0 font-weight-bold text-primary mb-3">Total Provider Terverifikasi: <?php echo count($providers); ?></h6>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID User</th>
                                        <th>Nama Perusahaan</th>
                                        <th>Email Kontak</th>
                                        <th>Status Verifikasi</th>
                                        <th>Status Akun</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($providers as $provider): 
                                        $user_status_badge = ($provider['user_status'] == 'active') ? 'badge bg-success' : 'badge bg-warning text-dark';
                                        if ($provider['user_status'] == 'deleted') $user_status_badge = 'badge bg-danger';

                                        $verification_badge = ($provider['verification_status'] == 'verified') ? 'badge bg-primary' : 'badge bg-warning text-dark';
                                    ?>
                                    <tr>
                                        <td><?php echo $provider['id']; ?></td>
                                        <td><?php echo htmlspecialchars($provider['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($provider['email']); ?></td>
                                        <td><span class="<?php echo $verification_badge; ?>"><?php echo ucfirst($provider['verification_status']); ?></span></td>
                                        <td><span class="<?php echo $user_status_badge; ?>"><?php echo ucfirst($provider['user_status']); ?></span></td>
                                        <td>
                                            <a href="user_detail.php?id=<?php echo $provider['id']; ?>" class="btn btn-info btn-sm">Detail & Aksi</a>
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

    </div>
</div>

<?php 
echo '</div>'; // Tutup div id="wrapper"
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> 
</body>
</html>