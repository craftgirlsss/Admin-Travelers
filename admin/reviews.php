<?php
// admin/reviews.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

$status_msg = '';

// ==========================================================
// LOGIKA AKSI MODERASI (Hide/Show/Delete)
// ==========================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $review_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $success = false;
    $message_prefix = "Review ID " . $review_id;

    if ($action == 'hide' || $action == 'show') {
        // Soft Delete (Mengubah visibilitas)
        $is_visible = ($action == 'show') ? 1 : 0;
        $status_text = ($action == 'show') ? 'DITAMPILKAN' : 'DISEMBUNYIKAN';
        
        $stmt = $conn->prepare("UPDATE reviews SET is_visible = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_visible, $review_id);
        
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil diubah status menjadi " . $status_text . ".";
            $success = true;
        }

    } elseif ($action == 'delete_permanent') {
        // Hapus Permanen (Gunakan dengan sangat hati-hati!)
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);

        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil DIHAPUS PERMANEN.";
            $success = true;
        }
    }
    
    // Redirect
    $alert_type = $success ? 'success' : 'danger';
    header("Location: reviews.php?status_msg=" . urlencode($status_msg) . "&alert_type=" . $alert_type);
    exit();
}

// ----------------------------------------------------
// QUERY DATA UTAMA UNTUK TAMPILAN
// ----------------------------------------------------

$query = "
    SELECT 
        r.id, r.rating, r.comment, r.created_at, r.is_visible,
        t.title AS trip_title,
        u.name AS reviewer_name,
        p.company_name AS provider_company
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN trips t ON r.trip_id = t.id
    JOIN providers p ON t.provider_id = p.id
    ORDER BY r.created_at DESC
";
$result = $conn->query($query);

$reviews = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen Review & Rating</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Pusat Moderasi Ulasan</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Semua Ulasan</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Rating</th>
                                <th>Ulasan Singkat</th>
                                <th>Trip</th>
                                <th>Reviewer</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): 
                                $status_badge = $review['is_visible'] ? 'bg-success' : 'bg-danger';
                                $status_text = $review['is_visible'] ? 'Tampil' : 'Sembunyi';
                            ?>
                            <tr>
                                <td><?php echo $review['id']; ?></td>
                                <td>
                                    <?php echo number_format($review['rating'], 1); ?> <i class="fas fa-star text-warning"></i>
                                </td>
                                <td><?php echo htmlspecialchars(substr($review['comment'], 0, 50)) . (strlen($review['comment']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($review['trip_title']); ?> (<?php echo htmlspecialchars($review['provider_company']); ?>)</td>
                                <td><?php echo htmlspecialchars($review['reviewer_name']); ?></td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span></td>
                                <td>
                                    <button class="btn btn-info btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $review['id']; ?>">Detail</button>

                                    <?php if ($review['is_visible']): ?>
                                        <a href="reviews.php?action=hide&id=<?php echo $review['id']; ?>" 
                                           class="btn btn-warning btn-sm mb-1" 
                                           onclick="return confirm('Sembunyikan ulasan ini dari publik? (Moderasi)')"
                                        >Sembunyikan</a>
                                    <?php else: ?>
                                        <a href="reviews.php?action=show&id=<?php echo $review['id']; ?>" 
                                           class="btn btn-success btn-sm mb-1" 
                                        >Tampilkan</a>
                                    <?php endif; ?>

                                    <a href="reviews.php?action=delete_permanent&id=<?php echo $review['id']; ?>" 
                                       class="btn btn-danger btn-sm mb-1" 
                                       onclick="return confirm('PERINGATAN! Hapus permanen ulasan ini?')"
                                    >Hapus</a>
                                </td>
                            </tr>

                            <?php 
                            // **********************************************
                            // MODAL DETAIL REVIEW
                            // **********************************************
                            ?>
                            <div class="modal fade" id="detailModal<?php echo $review['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detail Ulasan #<?php echo $review['id']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>Rating:</strong> <?php echo number_format($review['rating'], 1); ?> <i class="fas fa-star text-warning"></i></p>
                                            <p><strong>Trip:</strong> <?php echo htmlspecialchars($review['trip_title']); ?></p>
                                            <p><strong>Provider:</strong> <?php echo htmlspecialchars($review['provider_company']); ?></p>
                                            <p><strong>Reviewer:</strong> <?php echo htmlspecialchars($review['reviewer_name']); ?></p>
                                            <hr>
                                            <h6>Komentar Lengkap:</h6>
                                            <p class="border p-2 bg-light"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            <p class="small text-muted">Dibuat pada: <?php echo date('d M Y H:i', strtotime($review['created_at'])); ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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