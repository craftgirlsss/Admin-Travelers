<?php
    // dashboard.php
    require_once '../functions/auth.php'; 
    require_once 'includes/header.php';
    require_once 'includes/sidebar.php';
    require_once '../config/database.php';
    check_admin_access();

    // 1. Data untuk Card Ringkasan
    $total_users = $conn->query("SELECT COUNT(id) FROM users")->fetch_row()[0];
    $total_trips = $conn->query("
    SELECT COUNT(id) 
    FROM trips 
    WHERE 
        approval_status = 'approved' 
        AND is_deleted = 0 
        AND end_date >= CURDATE()
    ")->fetch_row()[0];
    $pending_bookings = $conn->query("
        SELECT COUNT(b.id) 
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id 
        WHERE 
            b.status = 'pending' 
            AND t.start_date >= CURDATE()
    ")->fetch_row()[0];
    $open_complaints = $conn->query("SELECT COUNT(id) FROM complaints WHERE status = 'open'")->fetch_row()[0];
    // Data Trip Belum Diapprove (is_approved = 0 dan is_deleted = 0)
    // Berdasarkan struktur, approval_status juga bisa 'pending'
    $pending_trips = $conn->query("
        SELECT COUNT(id) 
            FROM trips 
            WHERE 
                approval_status = 'pending' 
                AND is_deleted = 0
                AND start_date >= CURDATE()
            ")->fetch_row()[0]; 
    
    // 2. Data untuk Aktivitas Terbaru (Ambil 8 log terbaru)
    // Ambil log aktivitas terbaru dari tabel admin_activities
    $logs_query = "SELECT 
                        aa.action_type, 
                        aa.description, 
                        aa.created_at, 
                        u.name AS admin_name 
                    FROM admin_activities aa
                    LEFT JOIN users u ON aa.admin_id = u.id
                    ORDER BY aa.created_at DESC 
                    LIMIT 8";
    $logs_result = $conn->query($logs_query);
    $recent_logs = [];
    if ($logs_result) {
        while($row = $logs_result->fetch_assoc()) {
            $recent_logs[] = $row;
        }
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <title>Dashboard Super Admin</title>
    </head>
<body>
    <div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
        <div class="container-fluid">
            <h5 class="my-2 text-dark fw-light">Dashboard Utama</h5>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                    <li class="nav-item active"><span class="nav-link fw-bold text-primary">Selamat Datang, Super Admin!</span></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-2 mb-4 fw-light text-secondary">Ringkasan Travelers</h1>
        
        <div class="row g-4 mb-5">
            
            <div class="col-xl-3 col-md-6">
                <div class="card card-fresh shadow-sm h-100 border-start border-5 border-primary">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-primary fw-bold text-uppercase mb-1 fs-6">Total Pengguna</p>
                                <h2 class="mb-0 fw-bold"><?php echo number_format($total_users); ?></h2>
                            </div>
                            <i class="fas fa-users fa-3x text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card card-fresh shadow-sm h-100 border-start border-5 border-success">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-success fw-bold text-uppercase mb-1 fs-6">Trip Aktif</p>
                                <h2 class="mb-0 fw-bold"><?php echo number_format($total_trips); ?></h2>
                            </div>
                            <i class="fas fa-plane-departure fa-3x text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-fresh shadow-sm h-100 border-start border-5 border-warning">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-warning fw-bold text-uppercase mb-1 fs-6">Booking Pending</p>
                                <h2 class="mb-0 fw-bold"><?php echo number_format($pending_bookings); ?></h2>
                            </div>
                            <i class="fas fa-calendar-check fa-3x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-fresh shadow-sm h-100 border-start border-5 border-danger">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-danger fw-bold text-uppercase mb-1 fs-6">Keluhan Terbuka</p>
                                <h2 class="mb-0 fw-bold"><?php echo number_format($open_complaints); ?></h2>
                            </div>
                            <i class="fas fa-comments fa-3x text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card card-fresh shadow-sm h-100 border-start border-5 border-info">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-info fw-bold text-uppercase mb-1 fs-6">Trip Menunggu Verifikasi</p>
                                <h2 class="mb-0 fw-bold"><?php echo number_format($pending_trips); ?></h2>
                            </div>
                            <i class="fas fa-certificate fa-3x text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2"></i> 8 Aktivitas Admin Terbaru</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (!empty($recent_logs)): ?>
                                <?php foreach ($recent_logs as $log): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold text-dark">
                                                <?php echo htmlspecialchars($log['action_type']); ?>
                                                <small class="text-muted fw-normal">(oleh: <?php echo htmlspecialchars($log['admin_name'] ?? 'Sistem/Non-Login'); ?>)</small>
                                            </div>
                                            <span class="text-muted small"><?php echo htmlspecialchars($log['description']); ?></span>
                                        </div>
                                        <span class="badge bg-light text-secondary rounded-pill"><?php echo date('d M, H:i', strtotime($log['created_at'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-center text-muted">Belum ada aktivitas admin tercatat.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="card-footer text-end bg-light border-0">
                        <a href="logs.php" class="text-decoration-none small fw-bold text-primary">Lihat Semua Log Aktivitas <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<style>
    /* Styling Tambahan untuk Dashboard Fresh Look */
    .card-fresh {
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .card-fresh:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .card-fresh .card-body {
        padding: 1.5rem;
    }
    /* Mengubah opacity ikon pada card */
    .card-fresh i.fa-3x {
        opacity: 0.2; 
        transition: opacity 0.3s;
    }
    .card-fresh:hover i.fa-3x {
        opacity: 0.5;
    }
    /* Membuat border-start lebih tebal */
    .border-start {
        border-left-width: 0.5rem !important;
    }
</style>