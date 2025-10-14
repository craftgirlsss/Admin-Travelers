<?php
// admin/users.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

// Pastikan fungsi log_admin_activity ada di 'functions/auth.php' atau file yang di-include
// Jika fungsi belum ada, sistem akan menghasilkan error Fatal Error.

check_admin_access();

// Inisialisasi variabel pencarian
$search_name = $_GET['search_name'] ?? '';

// ==========================================================
// LOGIKA AKSI (Disable/Enable/Delete) DENGAN LOGGING
// ==========================================================
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
            
            // --- IMPLEMENTASI LOGGING ---
            log_admin_activity($action, 'users', $user_id, $conn, "Status akun diubah menjadi " . strtoupper($new_status));
            
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
            
            // --- IMPLEMENTASI LOGGING ---
            // Status yang baru adalah 'deleted'
            log_admin_activity('soft_delete', 'users', $user_id, $conn, "Akun dihapus (Soft Delete), status diubah menjadi 'deleted'."); 
            
        } else {
            $message = "Gagal menghapus user: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Redirect mempertahankan filter pencarian
    header("Location: users.php?status_msg=" . urlencode($message) . "&search_name=" . urlencode($search_name));
    exit();
}


// ----------------------------------------------------
// LOGIKA FILTER PENCARIAN
// ----------------------------------------------------
$search_condition = "";
if (!empty($search_name)) {
    $search_name_safe = $conn->real_escape_string($search_name);
    // Cari berdasarkan nama user atau nama perusahaan (join ke provider)
    $search_condition = " AND (u.name LIKE '%" . $search_name_safe . "%' OR p.company_name LIKE '%" . $search_name_safe . "%')";
}


// ----------------------------------------------------
// QUERY DATA CLIENT
// ----------------------------------------------------
$client_query = "
    SELECT u.id, u.name, u.email, u.phone, u.status 
    FROM users u
    LEFT JOIN providers p ON u.id = p.user_id 
    WHERE u.role IN ('client', 'customer')
    " . $search_condition . "
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
// QUERY DATA PROVIDER (Hanya yang Verified)
// ----------------------------------------------------
$provider_query = "
    SELECT u.id, u.name, u.email, u.phone, u.status AS user_status, p.company_name, p.verification_status, p.id AS provider_db_id
    FROM users u
    JOIN providers p ON u.id = p.user_id 
    WHERE u.role = 'provider' AND p.verification_status = 'verified'
    " . str_replace("u.name", "u.name OR p.company_name", $search_condition) . "
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

        <div class="card shadow mb-4 p-3">
            <h6 class="text-primary mb-3"><i class="fas fa-search me-2"></i> Filter Pencarian</h6>
            <form method="GET" action="users.php" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="search_name" class="form-label small text-muted">Nama User / Perusahaan Provider</label>
                    <input type="text" class="form-control form-control-sm" id="search_name" name="search_name" 
                           value="<?php echo htmlspecialchars($search_name); ?>" placeholder="Cari nama...">
                </div>
                
                <div class="col-md-6 d-flex justify-content-start">
                    <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-search"></i> Cari</button>
                    <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-redo"></i> Reset Filter</a>
                </div>
            </form>
        </div>
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
                        
                        <?php if (empty($clients)): ?>
                            <div class="alert alert-info text-center">Tidak ada data Client yang ditemukan.</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($clients as $user): 
                                    $status_badge = ($user['status'] == 'active') ? 'bg-success' : 'bg-warning text-dark';
                                    if ($user['status'] == 'deleted') $status_badge = 'bg-danger';
                                ?>
                                <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between mb-2 p-3 rounded shadow-sm">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                                        <div>
                                            <h6 class="mb-0 text-dark"><?php echo htmlspecialchars($user['name']); ?> <small class="text-muted">(ID: <?php echo $user['id']; ?>)</small></h6>
                                            <small class="text-muted d-block"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?></small>
                                            <small class="text-muted d-block"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['phone']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center">
                                        <span class="badge me-3 <?php echo $status_badge; ?>"><?php echo ucfirst($user['status']); ?></span>
                                        <a href="user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-info-circle"></i> Detail & Aksi
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="provider-list" role="tabpanel">
                        <h6 class="m-0 font-weight-bold text-primary mb-3">Total Provider Terverifikasi: <?php echo count($providers); ?></h6>
                        
                        <?php if (empty($providers)): ?>
                            <div class="alert alert-info text-center">Tidak ada Provider Terverifikasi yang ditemukan.</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($providers as $provider): 
                                    $user_status_badge = ($provider['user_status'] == 'active') ? 'bg-success' : 'bg-warning text-dark';
                                    if ($provider['user_status'] == 'deleted') $user_status_badge = 'bg-danger';

                                    $verification_badge = ($provider['verification_status'] == 'verified') ? 'bg-primary' : 'bg-warning text-dark';
                                ?>
                                <div class="list-group-item list-group-item-action d-flex align-items-center justify-content-between mb-2 p-3 rounded shadow-sm">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-building fa-2x text-success me-3"></i>
                                        <div>
                                            <h6 class="mb-0 text-dark"><?php echo htmlspecialchars($provider['company_name']); ?> <small class="text-muted">(User: <?php echo htmlspecialchars($provider['name']); ?>)</small></h6>
                                            <small class="text-muted d-block"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($provider['email']); ?></small>
                                            <small class="text-muted d-block"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($provider['phone']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center">
                                        <span class="badge me-2 <?php echo $verification_badge; ?>">Verifikasi: <?php echo ucfirst($provider['verification_status']); ?></span>
                                        <span class="badge me-3 <?php echo $user_status_badge; ?>">Akun: <?php echo ucfirst($provider['user_status']); ?></span>
                                        <a href="user_detail.php?id=<?php echo $provider['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-info-circle"></i> Detail & Aksi
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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