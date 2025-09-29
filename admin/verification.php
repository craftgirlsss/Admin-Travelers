<?php
// admin/verification.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

$status_msg = '';

// ID Admin yang sedang login (untuk mencatat siapa yang memverifikasi)
$admin_id = $_SESSION['user_id']; 

// ==========================================================
// LOGIKA AKSI VERIFIKASI / TOLAK
// ==========================================================
if (isset($_POST['action']) && isset($_POST['provider_id'])) {
    $provider_id = (int)$_POST['provider_id'];
    $action = $_POST['action'];
    $note = isset($_POST['verification_note']) ? $_POST['verification_note'] : NULL;
    $success = false;

    if ($action == 'verify') {
        $status = 'verified';
        $message = "Provider ID " . $provider_id . " berhasil di-VERIFIKASI.";
    } elseif ($action == 'reject') {
        $status = 'rejected';
        $message = "Provider ID " . $provider_id . " berhasil di-TOLAK.";
    }

    if (isset($status)) {
        // Gunakan Prepared Statement untuk update status dan mencatat auditor
        $stmt = $conn->prepare("
            UPDATE providers 
            SET verification_status = ?, 
                verification_note = ?,
                verified_by_admin_id = ?, 
                verification_date = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssii", $status, $note, $admin_id, $provider_id);
        
        if ($stmt->execute()) {
            $status_msg = $message;
            $success = true;
        } else {
            $status_msg = "Gagal melakukan aksi: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Redirect untuk menghindari resubmission
    $alert_type = $success ? 'success' : 'danger';
    header("Location: verification.php?status_msg=" . urlencode($status_msg) . "&alert_type=" . $alert_type);
    exit();
}

// ----------------------------------------------------
// QUERY DATA UNTUK TAMPILAN
// ----------------------------------------------------

$query = "
    SELECT 
        p.id AS provider_id,
        p.company_name, 
        p.verification_status, 
        p.created_at,
        u.name AS user_name,
        u.email,
        u.phone
    FROM providers p
    JOIN users u ON p.user_id = u.id
    WHERE p.verification_status IN ('unverified', 'pending', 'rejected')
    ORDER BY p.created_at ASC
";
$result = $conn->query($query);

$providers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $providers[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Verifikasi Provider</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Verifikasi Data Perusahaan</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Provider Perlu Verifikasi (<?php echo count($providers); ?>)</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID Provider</th>
                                <th>Nama Perusahaan</th>
                                <th>Email Kontak</th>
                                <th>Status Verifikasi</th>
                                <th>Tanggal Daftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $provider): 
                                $status_badge = 'bg-secondary';
                                if ($provider['verification_status'] == 'pending') $status_badge = 'bg-warning text-dark';
                                if ($provider['verification_status'] == 'rejected') $status_badge = 'bg-danger';
                            ?>
                            <tr>
                                <td><?php echo $provider['provider_id']; ?></td>
                                <td><?php echo htmlspecialchars($provider['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($provider['email']); ?></td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($provider['verification_status']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($provider['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-info btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $provider['provider_id']; ?>">Review</button>

                                    <?php if ($provider['verification_status'] !== 'verified'): ?>
                                        <button class="btn btn-primary btn-sm mb-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#actionModal<?php echo $provider['provider_id']; ?>">Aksi</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php 
                            // **********************************************
                            // MODAL AKSI (Untuk Verifikasi/Tolak)
                            // **********************************************
                            ?>
                            <div class="modal fade" id="actionModal<?php echo $provider['provider_id']; ?>" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
                              <div class="modal-dialog">
                                <form method="POST" action="verification.php">
                                <div class="modal-content">
                                  <div class="modal-header">
                                    <h5 class="modal-title" id="actionModalLabel">Aksi Verifikasi <?php echo $provider['company_name']; ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </div>
                                  <div class="modal-body">
                                    <p>Pilih aksi yang ingin Anda lakukan pada Provider ini:</p>
                                    <div class="mb-3">
                                        <label for="note" class="form-label">Catatan Admin (Wajib jika Tolak):</label>
                                        <textarea class="form-control" name="verification_note" rows="3"></textarea>
                                    </div>
                                    <input type="hidden" name="provider_id" value="<?php echo $provider['provider_id']; ?>">
                                  </div>
                                  <div class="modal-footer">
                                    <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Tolak Verifikasi Provider?')">Tolak</button>
                                    <button type="submit" name="action" value="verify" class="btn btn-success" onclick="return confirm('Verifikasi dan Setujui Provider?')">Verifikasi</button>
                                  </div>
                                </div>
                                </form>
                              </div>
                            </div>
                            <?php 
                            // **********************************************
                            // MODAL DETAIL (Diperlukan jika Provider mengunggah dokumen)
                            // **********************************************
                            // Asumsi: Kita hanya tampilkan info dasar, detail dokumen ada di kolom 'verification_docs_url'
                            ?>
                             <div class="modal fade" id="detailModal<?php echo $provider['provider_id']; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detail Provider</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>Nama Kontak:</strong> <?php echo htmlspecialchars($provider['user_name']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($provider['email']); ?></p>
                                            <p><strong>Telepon:</strong> <?php echo htmlspecialchars($provider['phone']); ?></p>
                                            <p><strong>Status:</strong> <span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($provider['verification_status']); ?></span></p>
                                            <hr>
                                            <p class="text-muted">Di sini seharusnya ada link untuk melihat dokumen yang diunggah Provider (KTP, SIUP, dll.) melalui kolom <code>verification_docs_url</code> di database.</p>
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
// Tutup div id="wrapper"
echo '</div>'; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>