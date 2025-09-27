<?php
// admin/trips.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

// Inisialisasi pesan status
$status_msg = '';

// ==========================================================
// LOGIKA AKSI MODERASI
// ==========================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $trip_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $success = false;
    $message_prefix = "Trip ID " . $trip_id;

    if ($action == 'approve') {
        // Set is_approved menjadi TRUE
        $stmt = $conn->prepare("UPDATE trips SET is_approved = TRUE, rejection_reason = NULL WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil di-APPROVE.";
            $success = true;
        }

    } elseif ($action == 'suspend') {
        // Set trip_status menjadi suspended
        $stmt = $conn->prepare("UPDATE trips SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil di-SUSPEND.";
            $success = true;
        }

    } elseif ($action == 'delete') {
        // Hard Delete (gunakan dengan hati-hati!)
        // Harus hapus data anak terlebih dahulu (trip_images, bookings, reviews)
        
        // Asumsi struktur DB Anda menggunakan ON DELETE CASCADE, 
        // sehingga menghapus trip akan menghapus data di trip_images, bookings, reviews secara otomatis.
        
        $stmt = $conn->prepare("DELETE FROM trips WHERE id = ?");
        $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil diHAPUS PERMANEN.";
            $success = true;
        }
    }
    
    // Redirect untuk menghindari pengiriman ulang form
    if ($success) {
        header("Location: trips.php?status_msg=" . urlencode($status_msg) . "&alert_type=success");
    } else {
        header("Location: trips.php?status_msg=" . urlencode("Gagal melakukan aksi: " . $conn->error) . "&alert_type=danger");
    }
    exit();
}

// ----------------------------------------------------
// QUERY DATA UTAMA UNTUK TAMPILAN
// ----------------------------------------------------

$query = "
    SELECT 
        t.id, 
        t.title, 
        t.location, 
        t.price, 
        t.status AS trip_status,
        t.is_approved,
        t.created_at,
        u.name AS provider_name, 
        (SELECT COUNT(b.id) FROM bookings b WHERE b.trip_id = t.id) AS total_bookings,
        (SELECT AVG(r.rating) FROM reviews r WHERE r.trip_id = t.id) AS avg_rating
    FROM trips t
    JOIN providers p ON t.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    ORDER BY t.created_at DESC
";
$result = $conn->query($query);

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
        <h1 class="mt-4 mb-4">Pusat Kontrol Trip</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Semua Trip</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Judul Trip</th>
                                <th>Provider</th>
                                <th>Harga</th>
                                <th>Rating</th>
                                <th>Bookings</th>
                                <th>Status Trip</th>
                                <th>Moderasi</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trips as $trip): 
                                // Tentukan Badge Status Trip
                                $status_badge = 'bg-secondary';
                                if ($trip['trip_status'] == 'available') $status_badge = 'bg-success';
                                if ($trip['trip_status'] == 'suspended') $status_badge = 'bg-warning text-dark';
                                if ($trip['trip_status'] == 'canceled') $status_badge = 'bg-danger';

                                // Tentukan Badge Moderasi
                                $moderation_badge = 'bg-danger';
                                $moderation_text = 'Pending';
                                if ($trip['is_approved']) {
                                    $moderation_badge = 'bg-primary';
                                    $moderation_text = 'Approved';
                                }
                            ?>
                            <tr>
                                <td><?php echo $trip['id']; ?></td>
                                <td><?php echo htmlspecialchars($trip['title']); ?></td>
                                <td><?php echo htmlspecialchars($trip['provider_name']); ?></td>
                                <td>Rp <?php echo number_format($trip['price'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($trip['avg_rating'], 1) ?: 'N/A'; ?> <i class="fas fa-star text-warning"></i></td>
                                <td><?php echo number_format($trip['total_bookings']); ?></td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($trip['trip_status']); ?></span></td>
                                <td><span class="badge <?php echo $moderation_badge; ?>"><?php echo $moderation_text; ?></span></td>
                                <td>
                                    <?php if (!$trip['is_approved']): ?>
                                        <a href="trips.php?action=approve&id=<?php echo $trip['id']; ?>" 
                                           class="btn btn-primary btn-sm mb-1"
                                        >Approve</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($trip['trip_status'] != 'suspended'): ?>
                                        <a href="trips.php?action=suspend&id=<?php echo $trip['id']; ?>" 
                                           class="btn btn-warning btn-sm mb-1" 
                                           onclick="return confirm('Yakin ingin MENANGGUHKAN (SUSPEND) Trip ini?')"
                                        >Suspend</a>
                                    <?php endif; ?>

                                    <a href="trips.php?action=delete&id=<?php echo $trip['id']; ?>" 
                                       class="btn btn-danger btn-sm mb-1" 
                                       onclick="return confirm('PERINGATAN! Yakin ingin HAPUS PERMANEN Trip ini?')"
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
// Tutup div id="wrapper"
echo '</div>'; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>