<?php
// admin/trip_detail.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';
// BASE URL yang Anda tambahkan
define('BASE_IMAGE_URL', 'https://provider-travelers.karyadeveloperindonesia.com'); 

check_admin_access();

$trip_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($trip_id === 0) {
    header("Location: trips.php?alert_type=danger&status_msg=" . urlencode("ID Trip tidak valid."));
    exit;
}

// ----------------------------------------------------
// QUERY DETAIL TRIP, PROVIDER, dan GAMBAR
// ----------------------------------------------------
$query = "
    SELECT 
        t.*, 
        p.company_name, p.phone_number, p.user_id,
        u.name AS provider_user, 
        t.status AS trip_status
    FROM trips t
    JOIN providers p ON t.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE t.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip) {
    header("Location: trips.php?alert_type=danger&status_msg=" . urlencode("Trip tidak ditemukan."));
    exit;
}

// Ambil Gambar Trip
$images_query = "SELECT image_url FROM trip_images WHERE trip_id = ?";
$stmt_img = $conn->prepare($images_query);
$stmt_img->bind_param("i", $trip_id);
$stmt_img->execute();
$images = $stmt_img->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_img->close();

// Tentukan Badge Moderasi
$moderation_badge = 'bg-secondary';
if ($trip['approval_status'] == 'approved') $moderation_badge = 'bg-primary';
if ($trip['approval_status'] == 'suspended') $moderation_badge = 'bg-warning text-dark';
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Detail Trip #<?php echo $trip_id; ?></h5>
            <div class="ms-auto">
                <a href="trips.php" class="btn btn-secondary btn-sm">Kembali ke Trip Management</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4"><?php echo htmlspecialchars($trip['title']); ?></h1>
        
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-info">Status & Moderasi</h6>
                <div>
                    <?php if ($trip['approval_status'] != 'approved'): ?>
                        <a href="trips.php?action=approve&id=<?php echo $trip_id; ?>" class="btn btn-primary btn-sm me-2">Approve Trip</a>
                    <?php endif; ?>
                    <?php if ($trip['approval_status'] != 'suspended'): ?>
                        <a href="trips.php?action=suspend&id=<?php echo $trip_id; ?>" class="btn btn-warning btn-sm me-2" onclick="return confirm('Suspend Trip?')">Suspend Trip</a>
                    <?php endif; ?>
                    <a href="trips.php?action=delete&id=<?php echo $trip_id; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus Permanen Trip?')">Hapus Permanen</a>
                </div>
            </div>
            <div class="card-body row">
                <div class="col-md-4"><strong>Status Moderasi:</strong> <span class="badge <?php echo $moderation_badge; ?>"><?php echo ucfirst($trip['approval_status']); ?></span></div>
                <div class="col-md-4"><strong>Status Ketersediaan:</strong> <span class="badge bg-success"><?php echo ucfirst($trip['trip_status']); ?></span></div>
                <div class="col-md-4"><strong>Dibuat Pada:</strong> <?php echo date('d M Y H:i', strtotime($trip['created_at'])); ?></div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Informasi Trip</h6></div>
                    <div class="card-body">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Lokasi Utama:</strong> <?php echo htmlspecialchars($trip['location']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Durasi:</strong> <?php echo htmlspecialchars($trip['duration']); ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Titik Kumpul (Nama):</strong> <?php echo htmlspecialchars($trip['gathering_point_name'] ?? '-'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Titik Kumpul (URL Map):</strong> 
                                    <?php if (!empty($trip['gathering_point_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($trip['gathering_point_url']); ?>" target="_blank">Lihat Map</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Tanggal Mulai:</strong> <?php echo $trip['start_date'] ? date('d M Y', strtotime($trip['start_date'])) : '-'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Tanggal Selesai:</strong> <?php echo $trip['end_date'] ? date('d M Y', strtotime($trip['end_date'])) : '-'; ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Waktu Keberangkatan:</strong> <?php echo $trip['departure_time'] ?? '-'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Waktu Kepulangan:</strong> <?php echo $trip['return_time'] ?? '-'; ?></p>
                            </div>
                        </div>
                        <hr>
                        <h6>Deskripsi Lengkap:</h6>
                        <p><?php echo nl2br(htmlspecialchars($trip['description'])); ?></p>
                        <hr>
                        <h6>Detail Harga & Peserta</h6>
                        <p><strong>Harga Normal:</strong> Rp <?php echo number_format($trip['price'], 0, ',', '.'); ?></p>
                        <p><strong>Diskon:</strong> <?php echo $trip['discount_price'] > 0 ? 'Rp ' . number_format($trip['discount_price'], 0, ',', '.') : 'Tidak ada diskon'; ?></p>
                        <p><strong>Harga Akhir:</strong> Rp <?php echo number_format($trip['price'] - ($trip['discount_price'] ?? 0), 0, ',', '.'); ?></p>
                        <p><strong>Maks. Peserta:</strong> <?php echo number_format($trip['max_participants']); ?></p>
                        <p><strong>Sudah Dipesan:</strong> <?php echo number_format($trip['booked_participants']); ?></p>
                    </div>
                </div>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Gambar Trip (<?php echo count($images); ?> Gambar)</h6></div>
                    <div class="card-body row">
                        <?php if (!empty($images)): ?>
                            <?php foreach ($images as $img): 
                                $image_url = htmlspecialchars($img['image_url']);
                                // MENGGABUNGKAN BASE_IMAGE_URL
                                $image_url = BASE_IMAGE_URL . '/' . ltrim($image_url, '/');
                            ?>
                                <div class="col-6 col-md-4 col-lg-3 mb-4"> 
                                    <a href="<?php echo $image_url; ?>" target="_blank" class="d-block text-decoration-none">
                                        <div class="image-preview-container position-relative overflow-hidden border rounded" 
                                             style="height: 150px; background-color: #f8f9fa;">
                                            <img src="<?php echo $image_url; ?>" 
                                                 class="img-fluid w-100 h-100" 
                                                 alt="Gambar Trip"
                                                 style="object-fit: cover;">
                                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 text-white opacity-0 transition-opacity"
                                                 onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                                                <i class="fas fa-search-plus fa-lg"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-muted">Tidak ada gambar yang diunggah untuk trip ini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-success">Detail Provider</h6></div>
                    <div class="card-body">
                        <p><strong>Perusahaan:</strong> <?php echo htmlspecialchars($trip['company_name']); ?></p>
                        <p><strong>Pengguna (Akun):</strong> <?php echo htmlspecialchars($trip['provider_user']); ?></p>
                        <p><strong>Telepon:</strong> <?php echo htmlspecialchars($trip['phone_number']); ?></p>
                        <p><strong>ID Provider:</strong> #<?php echo htmlspecialchars($trip['provider_id']); ?></p>
                        <hr>
                        <a href="user_detail.php?id=<?php echo htmlspecialchars($trip['user_id']); ?>" class="btn btn-outline-success w-100">Lihat Detail Provider Lengkap</a>
                    </div>
                </div>
                
                <?php if ($trip['rejection_reason']): ?>
                <div class="card shadow mb-4 border-left-danger">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Alasan Penolakan Terakhir</h6></div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($trip['rejection_reason'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php 
echo '</div>'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>