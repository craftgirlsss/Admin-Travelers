<?php
// admin/trips.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

define('BASE_IMAGE_URL', 'https://provider-travelers.karyadeveloperindonesia.com'); 

check_admin_access();

// Inisialisasi pesan status
$status_msg = '';
$admin_id = $_SESSION['user_id']; 

// ==========================================================
// LOGIKA AKSI MODERASI (Tetap di atas untuk Redirect)
// ==========================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $trip_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $success = false;
    $message_prefix = "Trip ID " . $trip_id;
    $alert_type = 'danger';
    
    if (!function_exists('log_admin_activity')) {
        function log_admin_activity($type, $table, $id, $conn, $desc) {}
    }

    // Logika Approve/Suspend/Delete... (Sama seperti sebelumnya)
    if ($action == 'approve') {
        $stmt = $conn->prepare("UPDATE trips SET approval_status = 'approved', rejection_reason = NULL, is_approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil di-APPROVE.";
            log_admin_activity('approve', 'trips', $trip_id, $conn, "Trip disetujui, approval_status: approved.");
            $success = true; $alert_type = 'success';
        } else { $status_msg = "Gagal approve: " . $stmt->error; }

    } elseif ($action == 'suspend') {
        $stmt = $conn->prepare("UPDATE trips SET approval_status = 'suspended', is_approved = 0 WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil di-SUSPEND.";
            log_admin_activity('suspend', 'trips', $trip_id, $conn, "Trip ditangguhkan, approval_status: suspended.");
            $success = true; $alert_type = 'warning';
        } else { $status_msg = "Gagal suspend: " . $stmt->error; }

    } elseif ($action == 'delete') {
        // Hard Delete
        $stmt = $conn->prepare("DELETE FROM trips WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil diHAPUS PERMANEN.";
            log_admin_activity('delete', 'trips', $trip_id, $conn, "Trip dihapus permanen dari database.");
            $success = true; $alert_type = 'success';
        } else { $status_msg = "Gagal hapus: " . $stmt->error; }
    }
    
    // Redirect mempertahankan tab saat ini jika ada
    $current_tab = isset($_GET['tab']) ? "&tab=" . urlencode($_GET['tab']) : "";
    $final_msg = $success ? $status_msg : "Gagal melakukan aksi: " . ($stmt ? $stmt->error : $conn->error);
    header("Location: trips.php?status_msg=" . urlencode($final_msg) . "&alert_type=" . urlencode($alert_type) . $current_tab);
    exit();
}

// ----------------------------------------------------
// LOGIKA TAB & PAGINATION
// ----------------------------------------------------
$current_tab = $_GET['tab'] ?? 'active'; // Default tab: active
$limit = 5; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Tentukan kondisi WHERE berdasarkan tab
$where_condition = "p.verification_status = 'verified' ";
$order_by = "t.created_at DESC";

// Tambahkan LEFT JOIN untuk trip_images
$join_images = "LEFT JOIN trip_images ti ON t.id = ti.trip_id AND ti.is_main = 1";

if ($current_tab === 'active') {
    $where_condition .= " AND t.is_deleted = 0 AND t.end_date >= CURDATE()";
    $tab_title = "Trip Aktif";
} elseif ($current_tab === 'inactive') {
    $where_condition .= " AND t.is_deleted = 0 AND t.end_date < CURDATE()";
    $tab_title = "Trip Non-aktif (Selesai)";
} elseif ($current_tab === 'deleted') {
    $where_condition .= " AND t.is_deleted = 1";
    $tab_title = "Trip Dihapus Provider";
} else {
    $where_condition .= " AND t.is_deleted = 0 AND t.end_date >= CURDATE()";
    $tab_title = "Trip Aktif";
    $current_tab = 'active';
}

// Sub-Query dasar untuk menghitung dan mengambil data
$base_query = "
    FROM trips t
    JOIN providers p ON t.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    " . $join_images . "
    WHERE " . $where_condition . "
    GROUP BY t.id
";

// 1. Hitung total records
// Menggunakan subquery untuk COUNT agar GROUP BY tetap bekerja dengan benar
$total_result = $conn->query("SELECT COUNT(*) FROM (SELECT t.id " . $base_query . ") AS count_alias");
$total_records = $total_result->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// 2. Query data dengan limit
$data_query = "
    SELECT 
        t.id, 
        t.title, 
        t.description, /* Tambahkan deskripsi jika ingin ditampilkan */
        t.location, 
        t.price, 
        t.status AS trip_status,
        t.approval_status,   
        t.created_at,
        t.end_date,
        p.verification_status,
        u.name AS provider_name, 
        u.id AS user_id,
        ti.image_url, /* <--- FOTO UTAMA DITAMBAHKAN */
        (SELECT COUNT(b.id) FROM bookings b WHERE b.trip_id = t.id) AS total_bookings,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.trip_id = t.id) AS avg_rating
    " . $base_query . "
    ORDER BY " . $order_by . "
    LIMIT $start, $limit
";

$result = $conn->query($data_query);

$trips = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $trips[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen Trip</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-3 fw-light text-secondary">Pusat Kontrol Trip</h1>
        <p class="text-info">Filter Dasar: Hanya menampilkan Trip dari Provider yang sudah **Verified**.</p> 
        
        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header p-0 bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'active') ? 'active' : ''; ?>" 
                           href="trips.php?tab=active" role="tab">
                            <i class="fas fa-running me-1"></i> Trip Aktif
                            <?php if ($current_tab === 'active'): ?><span class="badge bg-primary ms-1"><?php echo number_format($total_records); ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'inactive') ? 'active' : ''; ?>" 
                           href="trips.php?tab=inactive" role="tab">
                            <i class="fas fa-check-double me-1"></i> Trip Selesai
                            <?php if ($current_tab === 'inactive'): ?><span class="badge bg-secondary ms-1"><?php echo number_format($total_records); ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'deleted') ? 'active' : ''; ?>" 
                           href="trips.php?tab=deleted" role="tab">
                            <i class="fas fa-trash-alt me-1"></i> Dihapus Provider
                            <?php if ($current_tab === 'deleted'): ?><span class="badge bg-danger ms-1"><?php echo number_format($total_records); ?></span><?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <h5 class="text-dark mb-4"><?php echo $tab_title; ?></h5>
                
                <div class="row g-4">
                    <?php if (empty($trips)): ?>
                        <div class="col-12"><div class="alert alert-info text-center">Tidak ada data Trip untuk tab **<?php echo $tab_title; ?>** saat ini.</div></div>
                    <?php endif; ?>

                    <?php foreach ($trips as $trip): 
                        // Tentukan Badge Status Trip (ketersediaan)
                        $status_badge_class = 'bg-secondary';
                        if ($trip['trip_status'] == 'available') $status_badge_class = 'bg-success';
                        if ($trip['trip_status'] == 'suspended') $status_badge_class = 'bg-warning text-dark';
                        if ($trip['trip_status'] == 'canceled') $status_badge_class = 'bg-danger';

                        // Tentukan Badge Moderasi (approval_status)
                        $moderation_badge_class = 'bg-danger';
                        $moderation_icon = 'fas fa-hourglass-half';
                        if ($trip['approval_status'] == 'approved') {
                            $moderation_badge_class = 'bg-primary';
                            $moderation_icon = 'fas fa-check-circle';
                        } elseif ($trip['approval_status'] == 'suspended') {
                            $moderation_badge_class = 'bg-warning text-dark';
                            $moderation_icon = 'fas fa-ban';
                        }
                        
                        // LOGIKA PERBAIKAN URL GAMBAR DI SINI
                        $image_path = htmlspecialchars($trip['image_url']);
                        if (!empty($image_path)) {
                            // Gabungkan Base URL dengan path dari database
                            // ltrim() menghapus '/' di awal path jika ada untuk menghindari double slash
                            $image_src = BASE_IMAGE_URL . '/' . ltrim($image_path, '/');
                        } else {
                            // Path Gambar Default jika tidak ada
                            $image_src = '../assets/img/default-trip.jpg'; 
                        }
                    ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="card h-100 shadow border-0 card-trip">
                            <?php if ($image_src): ?>
                                <img src="<?php echo $image_src; ?>" class="card-img-top img-fluid" alt="<?php echo htmlspecialchars($trip['title']); ?>" style="height: 180px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <span class="badge position-absolute top-0 end-0 mt-2 me-2 <?php echo $status_badge_class; ?>"><?php echo ucfirst($trip['trip_status']); ?></span>
                                
                                <h5 class="card-title text-primary mb-1"><?php echo htmlspecialchars($trip['title']); ?></h5>
                                <p class="card-subtitle mb-2 text-muted small">
                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($trip['location']); ?>
                                </p>
                                
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        <span class="fw-bold text-success">Harga</span>
                                        <span class="fw-bold">Rp <?php echo number_format($trip['price'], 0, ',', '.'); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        <span>Provider</span>
                                        <span><?php echo htmlspecialchars($trip['provider_name']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        <span><i class="fas fa-star text-warning"></i> Rating</span>
                                        <span><?php echo number_format($trip['avg_rating'] ?? 0, 1) ?: 'N/A'; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        <span><i class="fas fa-book-reader"></i> Bookings</span>
                                        <span><?php echo number_format($trip['total_bookings'] ?? 0); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        <span class="fw-bold">Moderasi</span>
                                        <span class="badge <?php echo $moderation_badge_class; ?>"><i class="<?php echo $moderation_icon; ?> me-1"></i> <?php echo ucfirst($trip['approval_status']); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                                        <span class="fw-bold">Tanggal Berakhir</span>
                                        <span class="text-danger"><?php echo date('d M Y', strtotime($trip['end_date'])); ?></span>
                                    </li>
                                </ul>

                                <div class="mt-auto d-grid gap-2">
                                    <a href="trip_detail.php?id=<?php echo $trip['id']; ?>" class="btn btn-info btn-sm">Detail Trip</a>
                                    
                                    <?php if ($current_tab !== 'deleted'): // Hanya tampilkan tombol aksi untuk tab Active & Inactive ?>
                                        <div class="btn-group" role="group">
                                            <?php if ($trip['approval_status'] != 'approved'): ?>
                                                <a href="trips.php?action=approve&id=<?php echo $trip['id']; ?>&tab=<?php echo $current_tab; ?>" class="btn btn-primary btn-sm">Approve</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($trip['approval_status'] != 'suspended'): ?>
                                                <a href="trips.php?action=suspend&id=<?php echo $trip['id']; ?>&tab=<?php echo $current_tab; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Yakin ingin SUSPEND Trip ini?')" title="Suspend Trip">Suspend</a>
                                            <?php endif; ?>

                                            <a href="trips.php?action=delete&id=<?php echo $trip['id']; ?>&tab=<?php echo $current_tab; ?>" class="btn btn-danger btn-sm" onclick="return confirm('PERINGATAN! Yakin ingin HAPUS PERMANEN Trip ini?')" title="Hapus Permanen"><i class="fas fa-trash"></i></a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                            <div class="card-footer text-muted small py-2 bg-light">
                                Dibuat: <?php echo date('d M Y', strtotime($trip['created_at'])); ?>
                                <a href="user_detail.php?id=<?php echo $trip['user_id']; ?>" class="float-end" title="Lihat Provider"><i class="fas fa-eye"></i> Provider</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="trips.php?page=<?php echo $page - 1; ?>&tab=<?php echo $current_tab; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="trips.php?page=<?php echo $i; ?>&tab=<?php echo $current_tab; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="trips.php?page=<?php echo $page + 1; ?>&tab=<?php echo $current_tab; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<?php 
// Tutup div id="wrapper"
echo '</div>'; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>