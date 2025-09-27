<?php
    require_once '../functions/auth.php'; 
    require_once 'includes/header.php';
    require_once 'includes/sidebar.php';
    require_once '../config/database.php';
    check_admin_access();

    $total_users = $conn->query("SELECT COUNT(id) FROM users")->fetch_row()[0];
    $total_trips = $conn->query("SELECT COUNT(id) FROM trips WHERE status = 'available'")->fetch_row()[0];
    $pending_bookings = $conn->query("SELECT COUNT(id) FROM bookings WHERE status = 'pending'")->fetch_row()[0];
    $open_complaints = $conn->query("SELECT COUNT(id) FROM complaints WHERE status = 'open'")->fetch_row()[0];
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
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Dashboard Utama</h5>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                    <li class="nav-item active"><span class="nav-link">Selamat Datang, Super Admin!</span></li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Ringkasan Travelers</h1>
        
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Pengguna (Client & Provider)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_users); ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Trip Aktif / Tersedia</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_trips); ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-plane-departure fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Booking Pending</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_bookings); ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-calendar-check fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Keluhan Terbuka (Open)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($open_complaints); ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-comments fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Aktivitas Terbaru</h6></div>
                    <div class="card-body">
                        <p>Area ini bisa diisi dengan daftar booking terbaru, review yang belum dimoderasi, atau log admin.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>