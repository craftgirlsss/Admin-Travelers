<?php
// admin/bookings.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

// Inisialisasi pesan status
$status_msg = '';

// ==========================================================
// LOGIKA AKSI KONFIRMASI MANUAL (FORCE CONFIRM/PAID)
// ==========================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $success = false;
    $message_prefix = "Booking ID " . $booking_id;

    if ($action == 'force_confirm') {
        // Konfirmasi Booking dan Tandai Payment sebagai PAID secara manual
        
        // 1. Update status Booking menjadi confirmed
        $stmt1 = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
        $stmt1->bind_param("i", $booking_id);
        $stmt1->execute();
        $stmt1->close();
        
        // 2. Update status Payment menjadi paid dan set paid_at
        $stmt2 = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE booking_id = ? AND status != 'paid'");
        $stmt2->bind_param("i", $booking_id);
        $stmt2->execute();
        $stmt2->close();
        
        $status_msg = $message_prefix . " berhasil dikonfirmasi dan ditandai PAID secara manual.";
        $success = true;

    } elseif ($action == 'cancel') {
        // Batalkan Booking
        $stmt = $conn->prepare("UPDATE bookings SET status = 'canceled' WHERE id = ? AND status != 'canceled'");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
             // Opsional: Lakukan Refund Logic di sini jika status Payment sudah 'paid'
            $status_msg = $message_prefix . " berhasil dibatalkan (CANCELED).";
            $success = true;
        }
    }
    
    // Redirect untuk menghindari pengiriman ulang form
    if ($success) {
        header("Location: bookings.php?status_msg=" . urlencode($status_msg) . "&alert_type=success");
    } else {
        header("Location: bookings.php?status_msg=" . urlencode("Gagal melakukan aksi: " . $conn->error) . "&alert_type=danger");
    }
    exit();
}

// ----------------------------------------------------
// QUERY DATA UTAMA UNTUK TAMPILAN
// ----------------------------------------------------

$query = "
    SELECT 
        b.id AS booking_id,
        b.num_of_people,
        b.total_price,
        b.status AS booking_status,
        b.created_at,
        t.title AS trip_title,
        u.name AS client_name,
        p.method AS payment_method,
        p.status AS payment_status,
        p.paid_at
    FROM bookings b
    JOIN trips t ON b.trip_id = t.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON b.id = p.booking_id
    ORDER BY b.created_at DESC
";
$result = $conn->query($query);

$bookings = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen Booking & Payment</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Pusat Transaksi</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Semua Transaksi</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Trip</th>
                                <th>Client</th>
                                <th>Total Harga</th>
                                <th>Pax</th>
                                <th>Status Booking</th>
                                <th>Status Payment</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): 
                                // Tentukan Badge Status Booking
                                $booking_badge = 'bg-secondary';
                                if ($booking['booking_status'] == 'confirmed') $booking_badge = 'bg-success';
                                if ($booking['booking_status'] == 'pending') $booking_badge = 'bg-warning text-dark';
                                if ($booking['booking_status'] == 'canceled') $booking_badge = 'bg-danger';

                                // Tentukan Badge Status Payment
                                $payment_badge = 'bg-secondary';
                                if ($booking['payment_status'] == 'paid') $payment_badge = 'bg-primary';
                                if ($booking['payment_status'] == 'unpaid') $payment_badge = 'bg-warning text-dark';
                                if (!$booking['payment_status']) $payment_badge = 'bg-secondary'; // Jika belum ada payment record

                                $paid_at = $booking['paid_at'] ? date('d M H:i', strtotime($booking['paid_at'])) : 'N/A';
                            ?>
                            <tr>
                                <td><?php echo $booking['booking_id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['trip_title']); ?></td>
                                <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                <td>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($booking['num_of_people']); ?></td>
                                <td><span class="badge <?php echo $booking_badge; ?>"><?php echo ucfirst($booking['booking_status']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $payment_badge; ?>"><?php echo ucfirst($booking['payment_status'] ?: 'No Record'); ?></span>
                                    <small class="d-block text-muted"><?php echo $paid_at; ?></small>
                                </td>
                                <td>
                                    <?php if ($booking['booking_status'] == 'pending' || $booking['payment_status'] != 'paid'): ?>
                                        <a href="bookings.php?action=force_confirm&id=<?php echo $booking['booking_id']; ?>" 
                                           class="btn btn-primary btn-sm mb-1"
                                           onclick="return confirm('Yakin ingin KONFIRMASI BOOKING & TANDAI PAID secara manual?')"
                                        >Force Confirm</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['booking_status'] != 'canceled'): ?>
                                        <a href="bookings.php?action=cancel&id=<?php echo $booking['booking_id']; ?>" 
                                           class="btn btn-danger btn-sm mb-1" 
                                           onclick="return confirm('Yakin ingin MEMBATALKAN Booking ini?')"
                                        >Cancel</a>
                                    <?php endif; ?>

                                    <button class="btn btn-info btn-sm mb-1">Detail</button>
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