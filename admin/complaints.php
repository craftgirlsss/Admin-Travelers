<?php
// admin/complaints.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

$status_msg = '';
$admin_id = $_SESSION['user_id']; 

// ==========================================================
// LOGIKA AKSI STATUS / CATATAN
// ==========================================================
if (isset($_POST['action']) && isset($_POST['complaint_id'])) {
    $complaint_id = (int)$_POST['complaint_id'];
    $action = $_POST['action'];
    $note = trim($_POST['admin_notes'] ?? '');
    $success = false;
    $new_status = '';

    if ($action == 'in_progress') {
        $new_status = 'in_progress';
        $message = "Keluhan ID " . $complaint_id . " kini berstatus SEDANG DIPROSES.";
    } elseif ($action == 'resolve') {
        $new_status = 'resolved';
        $message = "Keluhan ID " . $complaint_id . " berhasil DISELESAIKAN.";
    }
    
    // Perbarui status dan tambahkan catatan admin
    if (!empty($new_status)) {
        // Gabungkan catatan lama dengan catatan baru (untuk histori)
        // Jika Anda hanya ingin menimpa, hapus bagian CONCAT()
        $stmt = $conn->prepare("
            UPDATE complaints 
            SET status = ?, 
                admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[Admin ID: {$admin_id} - " . date('Y-m-d H:i') . "] ', ?), 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $new_status, $note, $complaint_id);
        
        if ($stmt->execute()) {
            $status_msg = $message;
            $success = true;
        } else {
            $status_msg = "Gagal melakukan aksi: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Redirect
    $alert_type = $success ? 'success' : 'danger';
    header("Location: complaints.php?status_msg=" . urlencode($status_msg) . "&alert_type=" . $alert_type);
    exit();
}

// ----------------------------------------------------
// QUERY DATA UTAMA UNTUK TAMPILAN
// ----------------------------------------------------

$query = "
    SELECT 
        c.id, c.subject, c.description, c.status, c.admin_notes, c.created_at,
        u.name AS complainant_name, u.email AS complainant_email
        /* Jika ada trip_id/booking_id di complaints, JOIN di sini */
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.status ASC, c.created_at DESC
";
$result = $conn->query($query);

$complaints = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen Keluhan</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Pusat Resolusi Keluhan</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Keluhan Terbaru</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subjek</th>
                                <th>Pelapor</th>
                                <th>Status</th>
                                <th>Diajukan</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): 
                                $status_badge = 'bg-secondary';
                                if ($c['status'] == 'open') $status_badge = 'bg-danger';
                                if ($c['status'] == 'in_progress') $status_badge = 'bg-warning text-dark';
                                if ($c['status'] == 'resolved') $status_badge = 'bg-success';
                            ?>
                            <tr>
                                <td><?php echo $c['id']; ?></td>
                                <td><?php echo htmlspecialchars($c['subject']); ?></td>
                                <td><?php echo htmlspecialchars($c['complainant_name']); ?></td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                <td><?php echo date('d M Y H:i', strtotime($c['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $c['id']; ?>">Lihat/Aksi</button>
                                </td>
                            </tr>

                            <?php 
                            // **********************************************
                            // MODAL DETAIL & AKSI
                            // **********************************************
                            ?>
                            <div class="modal fade" id="detailModal<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <form method="POST" action="complaints.php">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Keluhan ID #<?php echo $c['id']; ?> - <?php echo htmlspecialchars($c['subject']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <h6>Detail Pelapor:</h6>
                                            <p><strong>Nama:</strong> <?php echo htmlspecialchars($c['complainant_name']); ?> (<?php echo htmlspecialchars($c['complainant_email']); ?>)</p>
                                            <hr>
                                            <h6>Deskripsi Keluhan:</h6>
                                            <p class="border p-2 bg-light"><?php echo nl2br(htmlspecialchars($c['description'])); ?></p>
                                            <hr>
                                            <h6>Catatan Admin (Riwayat Tindakan):</h6>
                                            <pre class="border p-2 bg-light small"><?php echo htmlspecialchars($c['admin_notes'] ?: 'Belum ada catatan.'); ?></pre>
                                            <hr>
                                            
                                            <?php if ($c['status'] != 'resolved'): ?>
                                            <div class="mb-3">
                                                <label for="new_notes" class="form-label">Tambahkan Catatan Baru:</label>
                                                <textarea class="form-control" name="admin_notes" rows="3" required></textarea>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="complaint_id" value="<?php echo $c['id']; ?>">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                            
                                            <?php if ($c['status'] == 'open'): ?>
                                                <button type="submit" name="action" value="in_progress" class="btn btn-warning">Proses Sekarang</button>
                                            <?php endif; ?>
                                            
                                            <?php if ($c['status'] != 'resolved'): ?>
                                                <button type="submit" name="action" value="resolve" class="btn btn-success" onclick="return confirm('Yakin keluhan ini sudah terselesaikan?')">Tandai Selesai</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    </form>
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