<?php
// admin/drivers.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

// Pastikan akses admin
check_admin_access();

// URL BASE untuk gambar driver (Asumsi sama dengan base trip, atau sesuaikan jika beda)
define('BASE_IMAGE_URL', 'https://provider-travelers.karyadeveloperindonesia.com'); 

// ----------------------------------------------------
// LOGIKA PAGINATION & FILTER
// ----------------------------------------------------
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Filter opsional berdasarkan provider_id
$filter_provider_id = isset($_GET['provider_id']) ? (int)$_GET['provider_id'] : null;

// Tentukan kondisi WHERE utama (HANYA AMBIL DRIVER DARI PROVIDER TERVERIFIKASI)
$where_condition = "p.verification_status = 'verified'"; // <-- PERUBAHAN UTAMA DI SINI
if ($filter_provider_id) {
    // Jika filter spesifik diterapkan, tambahkan kondisi provider_id
    $where_condition .= " AND d.provider_id = " . $filter_provider_id;
}

// Sub-Query dasar
$base_query = "
    FROM drivers d
    JOIN providers p ON d.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE " . $where_condition;

// 1. Hitung total records
$total_result = $conn->query("SELECT COUNT(d.id) " . $base_query);
$total_records = $total_result->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// 2. Query data driver
$data_query = "
    SELECT 
        d.id, 
        d.name AS driver_name, 
        d.phone_number, 
        d.license_number, 
        d.photo_url, 
        d.license_photo_url,
        d.is_active,
        p.id AS provider_id,
        u.name AS provider_user_name,
        p.company_name
    " . $base_query . "
    ORDER BY p.company_name ASC, d.name ASC
    LIMIT $start, $limit
";

$result = $conn->query($data_query);

$drivers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
    }
}

// Query untuk mendapatkan daftar Provider (untuk filter dropdown)
// HANYA AMBIL PROVIDER YANG SUDAH TERVERIFIKASI
$provider_query = $conn->query("SELECT id, company_name FROM providers WHERE verification_status = 'verified' ORDER BY company_name ASC"); // <-- PERUBAHAN UTAMA DI SINI
$providers_list = [];
while ($row = $provider_query->fetch_assoc()) {
    $providers_list[] = $row;
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen Driver</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4 fw-light text-secondary">Daftar Driver Provider (<?php echo number_format($total_records); ?> Driver)</h1>
        <p class="text-info">Filter & Data Driver hanya menampilkan Driver yang terdaftar di <b>Provider yang sudah Verified</b>.</p> 
        
        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-steering-wheel me-2"></i> Data Driver</h6>
                
                <form method="GET" action="drivers.php" class="d-flex align-items-center">
                    <label for="provider_filter" class="me-2 text-muted small">Filter Provider (Verified):</label>
                    <select name="provider_id" id="provider_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Semua Provider --</option>
                        <?php foreach ($providers_list as $provider): ?>
                            <option value="<?php echo $provider['id']; ?>" 
                                <?php echo ($filter_provider_id == $provider['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($provider['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <div class="card-body">
                
                <?php if (empty($drivers)): ?>
                    <div class="alert alert-info text-center">Tidak ada data Driver yang ditemukan.</div>
                <?php endif; ?>

                <div class="row g-4">
                    <?php 
                    $current_provider_id = null;
                    foreach ($drivers as $driver): 
                        // Cek jika Provider berubah, tampilkan header Provider baru
                        if ($driver['provider_id'] != $current_provider_id):
                            if ($current_provider_id !== null): ?>
                                </div></div><hr class="my-4">
                            <?php endif; 
                            $current_provider_id = $driver['provider_id'];
                            ?>
                            <div class="col-12 mb-3">
                                <h4 class="fw-bold text-primary mb-1">
                                    <i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($driver['company_name']); ?> 
                                    <small class="text-muted"></small>
                                </h4>
                                <p class="text-muted small">Owner: <?php echo htmlspecialchars($driver['provider_user_name']); ?></p>
                                <div class="row g-3">
                        <?php endif; ?>
                        
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="card h-100 shadow border-0 driver-card overflow-hidden" style="border-radius: 12px;">
                                <div class="position-relative">
                                    <?php
                                    $photo_path = htmlspecialchars($driver['photo_url']);
                                    if (!empty($photo_path)) {
                                        $photo_src = BASE_IMAGE_URL . '/' . ltrim($photo_path, '/');
                                    } else {
                                        $photo_src = 'https://via.placeholder.com/150?text=Driver+Photo'; // Placeholder
                                    }
                                    ?>
                                    <img src="<?php echo $photo_src; ?>" class="card-img-top" alt="Foto Driver" style="height: 180px; object-fit: cover;">
                                    
                                    <span class="badge position-absolute bottom-0 start-0 m-2 p-2 
                                        <?php echo $driver['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <i class="fas fa-circle me-1"></i> <?php echo $driver['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                    </span>
                                </div>

                                <div class="card-body p-3 d-flex flex-column">
                                    <h5 class="card-title mb-1 text-truncate fw-bold"><?php echo htmlspecialchars($driver['driver_name']); ?></h5>
                                    <p class="card-text small text-muted mb-3">
                                        <i class="fas fa-mobile-alt me-1"></i> <?php echo htmlspecialchars($driver['phone_number']); ?>
                                    </p>
                                    
                                    <div class="mt-auto">
                                        <button class="btn btn-sm btn-outline-info w-100 mb-2" 
                                                type="button" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#driverModal<?php echo $driver['id']; ?>">
                                            <i class="fas fa-eye me-1"></i> Detail & Lisensi
                                        </button>
                                        <a href="driver_edit.php?id=<?php echo $driver['id']; ?>" class="btn btn-sm btn-warning w-100">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="driverModal<?php echo $driver['id']; ?>" tabindex="-1" aria-labelledby="driverModalLabel<?php echo $driver['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="driverModalLabel<?php echo $driver['id']; ?>">Detail Driver: <?php echo htmlspecialchars($driver['driver_name']); ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item"><strong>Provider:</strong> <?php echo htmlspecialchars($driver['company_name']); ?></li>
                                            <li class="list-group-item"><strong>Nomor SIM:</strong> <?php echo htmlspecialchars($driver['license_number']); ?></li>
                                            <li class="list-group-item"><strong>Status:</strong> 
                                                <span class="badge <?php echo $driver['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $driver['is_active'] ? 'Aktif' : 'Tidak Aktif'; ?>
                                                </span>
                                            </li>
                                            <li class="list-group-item">
                                                <strong>Foto Lisensi:</strong> 
                                                <?php 
                                                $license_path = htmlspecialchars($driver['license_photo_url']);
                                                if (!empty($license_path)) {
                                                    $license_src = BASE_IMAGE_URL . '/' . ltrim($license_path, '/');
                                                    echo '<br><img src="' . $license_src . '" class="img-fluid mt-2 rounded" alt="Foto Lisensi">';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                    <?php if (!empty($drivers)): ?>
                        </div></div><hr class="my-4">
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php 
                            $pagination_base_url = "drivers.php?provider_id=" . $filter_provider_id;
                            $prev_page = $page - 1;
                            $next_page = $page + 1;
                            ?>
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $pagination_base_url . "&page=" . $prev_page; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $pagination_base_url . "&page=" . $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $pagination_base_url . "&page=" . $next_page; ?>" aria-label="Next">
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