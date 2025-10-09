<?php
// admin/user_detail.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    header("Location: users.php?status_msg=" . urlencode("ID User tidak ditemukan."));
    exit;
}

// ==========================================================
// LOGIKA AKSI DISABLE/ENABLE/DELETE (Dipindahkan dari users.php)
// ==========================================================
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $message = "";

    if ($action == 'disable' || $action == 'enable') {
        $new_status = ($action == 'enable') ? 'active' : 'disabled';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role != 'super_admin'");
        $stmt->bind_param("si", $new_status, $user_id);
        
        if ($stmt->execute()) {
            $message = "User ID " . $user_id . " berhasil diubah status menjadi " . $new_status . ".";
            // Panggil Logger di sini (jika sudah diimplementasikan)
        } else {
            $message = "Gagal mengubah status: " . $conn->error;
        }
        $stmt->close();

    } elseif ($action == 'delete') {
        $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE id = ? AND role != 'super_admin'");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $message = "User ID " . $user_id . " berhasil dihapus (Soft Deleted).";
        } else {
            $message = "Gagal menghapus user: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: user_detail.php?id=" . $user_id . "&status_msg=" . urlencode($message));
    exit;
}


// ----------------------------------------------------
// QUERY DATA USER UTAMA & PROVIDER DETAIL (Jika ada)
// ----------------------------------------------------

$query = "
    SELECT 
        u.*, 
        p.company_name, p.address, p.description AS provider_desc, p.verification_status, p.verification_note,
        p.verified_by_admin_id, p.verification_date,
        
        -- Tambahkan semua kolom baru dari tabel providers di sini:
        p.owner_name, p.entity_type, p.business_license_path, p.ktp_path, p.phone_number,
        p.province, p.city, p.district, p.village, p.postal_code, p.rt, p.rw, p.company_logo_path
        
    FROM users u
    LEFT JOIN providers p ON u.id = p.user_id
    WHERE u.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: users.php?status_msg=" . urlencode("User tidak ditemukan di database."));
    exit;
}

// Helper untuk Badge Status
$status_badge = ($user['status'] == 'active') ? 'bg-success' : 'bg-warning text-dark';
if ($user['status'] == 'deleted') $status_badge = 'bg-danger';

// Helper untuk Badge Verifikasi Provider
$verification_badge = 'bg-secondary';
if ($user['role'] == 'provider') {
    if ($user['verification_status'] == 'verified') $verification_badge = 'bg-primary';
    if ($user['verification_status'] == 'rejected') $verification_badge = 'bg-danger';
    if ($user['verification_status'] == 'pending' || $user['verification_status'] == 'unverified') $verification_badge = 'bg-warning text-dark';
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Detail User ID #<?php echo $user['id']; ?></h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Inspeksi Akun: <?php echo htmlspecialchars($user['name']); ?></h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Informasi Akun Dasar</h6></div>
                    <div class="card-body">
                        <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Telepon:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                        <p><strong>Role:</strong> <span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></p>
                        <p><strong>Status Akun:</strong> <span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($user['status']); ?></span></p>
                        <p><strong>Tanggal Dibuat:</strong> <?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></p>
                        
                        <hr>
                        <button class="btn btn-primary" disabled><i class="fas fa-comment me-2"></i> Chat Direct (Segera Hadir)</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                 <div class="card shadow">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Aksi Admin</h6></div>
                    <div class="card-body">
                        <form method="POST" action="user_detail.php?id=<?php echo $user_id; ?>">
                            <div class="d-grid gap-2">
                                <?php if ($user['status'] == 'active'): ?>
                                    <button type="submit" name="action" value="disable" class="btn btn-warning btn-lg">DISABLE AKUN</button>
                                <?php elseif ($user['status'] == 'disabled'): ?>
                                    <button type="submit" name="action" value="enable" class="btn btn-success btn-lg">ENABLE AKUN</button>
                                <?php endif; ?>

                                <?php if ($user['status'] !== 'deleted'): ?>
                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-lg mt-3" 
                                            onclick="return confirm('PERINGATAN! Yakin ingin menghapus (Soft Delete) akun ini?')">SOFT DELETE AKUN</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($user['role'] == 'provider'): ?>
            <div class="card shadow mt-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-info">Detail Perusahaan (Provider)</h6></div>
                <div class="card-body">
                    <div class="row">
                        
                        <div class="col-md-6 border-end">
                            <h6>1. Informasi Dasar</h6>
                            <p><strong>Nama Perusahaan:</strong> <?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></p>
                            <p><strong>Nama Pemilik:</strong> <?php echo htmlspecialchars($user['owner_name'] ?? '-'); ?></p>
                            <p><strong>Tipe Entitas:</strong> <span class="badge bg-secondary"><?php echo strtoupper(htmlspecialchars($user['entity_type'] ?? 'N/A')); ?></span></p>
                            <p><strong>Telepon Kontak:</strong> <?php echo htmlspecialchars($user['phone_number'] ?? '-'); ?></p>
                            <p><strong>Logo Perusahaan:</strong> 
                                <?php if (!empty($user['company_logo_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($user['company_logo_path']); ?>" target="_blank" class="btn btn-sm btn-outline-info">Lihat Logo</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                            <hr>
                            <h6>Alamat Resmi</h6>
                            <p><?php echo htmlspecialchars($user['address'] ?? '-'); ?></p>
                            <p class="small text-muted">
                                Provinsi: <?php echo htmlspecialchars($user['province'] ?? '-'); ?> / Kota: <?php echo htmlspecialchars($user['city'] ?? '-'); ?><br>
                                Kec: <?php echo htmlspecialchars($user['district'] ?? '-'); ?> / Desa: <?php echo htmlspecialchars($user['village'] ?? '-'); ?><br>
                                Kode Pos: <?php echo htmlspecialchars($user['postal_code'] ?? '-'); ?> (RT/RW: <?php echo htmlspecialchars($user['rt'] ?? '-'); ?>/<?php echo htmlspecialchars($user['rw'] ?? '-'); ?>)
                            </p>
                            <hr>
                            <p><strong>Deskripsi Perusahaan:</strong></p>
                            <p class="bg-light p-2"><?php echo nl2br(htmlspecialchars($user['provider_desc'] ?? '-')); ?></p>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>2. Dokumen dan Legalitas</h6>
                            
                            <p><strong>Dokumen KTP (Pemilik):</strong></p>
                            <?php if (!empty($user['ktp_path'])): ?>
                                <a href="<?php echo htmlspecialchars($user['ktp_path']); ?>" target="_blank" class="btn btn-sm btn-primary mb-3"><i class="fas fa-file-alt me-2"></i> INSPEKSI KTP</a>
                            <?php else: ?>
                                <span class="text-danger">Dokumen KTP Belum Diunggah.</span>
                            <?php endif; ?>
                            
                            <p><strong>Izin Usaha (NIB/SIUP/Legalitas):</strong></p>
                            <?php if ($user['entity_type'] == 'company'): ?>
                                <?php if (!empty($user['business_license_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($user['business_license_path']); ?>" target="_blank" class="btn btn-sm btn-success mb-3"><i class="fas fa-file-contract me-2"></i> INSPEKSI LEGALITAS (COMPANY)</a>
                                <?php else: ?>
                                    <span class="text-danger">Izin Usaha (Company) Belum Diunggah.</span>
                                <?php endif; ?>
                            <?php elseif ($user['entity_type'] == 'umkm'): ?>
                                <span class="text-muted">Tidak Wajib: Tipe entitas adalah UMKM.</span>
                            <?php else: ?>
                                <span class="text-secondary">Tipe entitas tidak terdefinisi.</span>
                            <?php endif; ?>
                            
                            <hr>
                            <h6>3. Status Moderasi</h6>
                            <p><strong>Status Verifikasi:</strong> <span class="badge <?php echo $verification_badge; ?>"><?php echo ucfirst($user['verification_status'] ?: 'N/A'); ?></span></p>
                            <p><strong>Diverifikasi oleh:</strong> Admin ID #<?php echo htmlspecialchars($user['verified_by_admin_id'] ?: 'Belum'); ?></p>
                            <p><strong>Tanggal Verifikasi:</strong> <?php echo $user['verification_date'] ? date('d M Y', strtotime($user['verification_date'])) : '-'; ?></p>
                            <p><strong>Catatan Admin Terakhir:</strong> <i class="text-danger small"><?php echo htmlspecialchars($user['verification_note'] ?? '-'); ?></i></p>

                            <a href="verification.php?id=<?php echo $user_id; ?>" class="btn btn-warning btn-sm mt-2"><i class="fas fa-gavel me-2"></i> Pindah ke Panel Verifikasi</a>
                        </div>
                        
                    </div>
                </div>
            </div>
            <?php endif; ?>

    </div>
</div>

<?php 
echo '</div>'; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>