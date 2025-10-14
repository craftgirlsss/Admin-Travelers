<?php
// admin/bookings.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

// Inisialisasi pesan status
$status_msg = '';

// Tentukan tab saat ini
$current_tab = $_GET['tab'] ?? 'pending'; // Default tab: pending

// ==========================================================
// LOGIKA FILTER PENCARIAN
// ==========================================================

// Ambil nilai filter dari GET
$search_client_name = $_GET['client_name'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';

// ==========================================================
// LOGIKA AKSI KONFIRMASI MANUAL (FORCE CONFIRM/PAID) - Tidak berubah
// ==========================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $booking_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $success = false;
    $message_prefix = "Booking ID " . $booking_id;
    $alert_type = 'danger';

    if ($action == 'force_confirm') {
        // Konfirmasi Booking dan Tandai Payment sebagai PAID secara manual
        
        // 1. Update status Booking menjadi confirmed
        $stmt1 = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
        $stmt1->bind_param("i", $booking_id);
        $stmt1->execute();
        $stmt1->close();
        
        // 2. Update status Payment menjadi paid dan set paid_at 
        $stmt2 = $conn->prepare("
            INSERT INTO payments (booking_id, status, paid_at, method, amount) 
            VALUES (?, 'paid', NOW(), 'Manual Admin', (SELECT total_price FROM bookings WHERE id = ?))
            ON DUPLICATE KEY UPDATE status = 'paid', paid_at = NOW()
        ");
        $stmt2->bind_param("ii", $booking_id, $booking_id);
        $stmt2->execute();
        $stmt2->close();
        
        $status_msg = $message_prefix . " berhasil dikonfirmasi dan ditandai PAID secara manual.";
        $success = true; $alert_type = 'success';

    } elseif ($action == 'cancel') {
        // Batalkan Booking
        $stmt = $conn->prepare("UPDATE bookings SET status = 'canceled' WHERE id = ? AND status != 'canceled'");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $status_msg = $message_prefix . " berhasil dibatalkan (CANCELED).";
            $success = true; $alert_type = 'success';
        }
    }
    
    // Redirect mempertahankan tab saat ini dan filter pencarian
    $filter_params = http_build_query([
        'tab' => $current_tab,
        'client_name' => $search_client_name,
        'date_start' => $date_start,
        'date_end' => $date_end,
        'status_msg' => $status_msg,
        'alert_type' => $alert_type
    ]);
    header("Location: bookings.php?" . $filter_params);
    exit();
}

// ----------------------------------------------------
// LOGIKA FILTER TAB & QUERY DATA
// ----------------------------------------------------

$where_condition_tab = "";

// 1. Kondisi WHERE berdasarkan Tab
if ($current_tab === 'pending') {
    $where_condition_tab = "b.status = 'pending' AND t.start_date > CURDATE()";
    $tab_title = "Transaksi Pending (Menunggu Pembayaran)";
} elseif ($current_tab === 'paid') {
    $where_condition_tab = "p.status = 'paid' OR b.status = 'confirmed'"; 
    $tab_title = "Transaksi Paid (Lunas)";
} elseif ($current_tab === 'expired') {
    // Kriteria: Booking status PENDING DAN start_date trip SUDAH TERLEWAT
    $where_condition_tab = "b.status = 'pending' AND t.start_date < CURDATE()"; 
    $tab_title = "Transaksi Expired (Terlewat & Belum Dibayar)";
} elseif ($current_tab === 'canceled') { // <-- TAB BARU DITAMBAHKAN
    $where_condition_tab = "b.status = 'canceled'"; 
    $tab_title = "Canceled Order (Dibatalkan)";
} else {
    // Default: jika tab tidak valid, gunakan pending
    $current_tab = 'pending';
    $where_condition_tab = "b.status = 'pending'";
    $tab_title = "Transaksi Pending (Menunggu Pembayaran)";
}

$where_conditions = [$where_condition_tab];

// 2. Kondisi WHERE berdasarkan Pencarian Klien
if (!empty($search_client_name)) {
    // Gunakan LIKE untuk pencarian sebagian nama klien
    $search_client_name_safe = $conn->real_escape_string($search_client_name);
    $where_conditions[] = "u.name LIKE '%" . $search_client_name_safe . "%'";
}

// 3. Kondisi WHERE berdasarkan Rentang Tanggal Booking (b.created_at)
if (!empty($date_start)) {
    $date_start_safe = $conn->real_escape_string($date_start);
    $where_conditions[] = "DATE(b.created_at) >= '" . $date_start_safe . "'";
}
if (!empty($date_end)) {
    $date_end_safe = $conn->real_escape_string($date_end);
    $where_conditions[] = "DATE(b.created_at) <= '" . $date_end_safe . "'";
}

// Gabungkan semua kondisi WHERE
$final_where_clause = implode(" AND ", $where_conditions);

// 4. Query Data
$query = "
    SELECT 
        b.id AS booking_id,
        b.num_of_people,
        b.total_price,
        b.status AS booking_status,
        b.created_at,
        t.title AS trip_title,
        t.start_date,
        u.name AS client_name,
        p.method AS payment_method,
        p.status AS payment_status,
        p.paid_at
    FROM bookings b
    JOIN trips t ON b.trip_id = t.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE " . $final_where_clause . "
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
        <h1 class="mt-4 mb-3">Pusat Transaksi</h1>

        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4 p-3">
            <h6 class="text-primary mb-3"><i class="fas fa-filter me-2"></i> Filter Pencarian</h6>
            <form method="GET" action="bookings.php" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">

                <div class="col-md-4">
                    <label for="client_name" class="form-label small text-muted">Nama Klien</label>
                    <input type="text" class="form-control form-control-sm" id="client_name" name="client_name" 
                           value="<?php echo htmlspecialchars($search_client_name); ?>" placeholder="Cari nama client...">
                </div>
                
                <div class="col-md-3">
                    <label for="date_start" class="form-label small text-muted">Tanggal Booking Dari</label>
                    <input type="date" class="form-control form-control-sm" id="date_start" name="date_start" 
                           value="<?php echo htmlspecialchars($date_start); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="date_end" class="form-label small text-muted">Tanggal Booking Sampai</label>
                    <input type="date" class="form-control form-control-sm" id="date_end" name="date_end" 
                           value="<?php echo htmlspecialchars($date_end); ?>">
                </div>

                <div class="col-md-2 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-search"></i> Cari</button>
                    <a href="bookings.php?tab=<?php echo $current_tab; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </form>
        </div>
        <div class="card shadow mb-4">
            <div class="card-header p-0 bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'pending') ? 'active' : ''; ?>" 
                           href="bookings.php?tab=pending" role="tab">
                            <i class="fas fa-hourglass-half me-1"></i> Pending
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'paid') ? 'active' : ''; ?>" 
                           href="bookings.php?tab=paid" role="tab">
                            <i class="fas fa-check-circle me-1"></i> Paid
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'expired') ? 'active' : ''; ?>" 
                           href="bookings.php?tab=expired" role="tab">
                            <i class="fas fa-calendar-times me-1"></i> Expired Trip
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_tab === 'canceled') ? 'active' : ''; ?>" 
                           href="bookings.php?tab=canceled" role="tab">
                            <i class="fas fa-times-circle me-1"></i> Canceled Order </a>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <h5 class="text-dark mb-4"><?php echo $tab_title; ?></h5>
                
                <?php if (empty($bookings)): ?>
                    <div class="alert alert-info text-center">
                        <?php 
                        $info_msg = "Tidak ada data transaksi untuk tab **" . $tab_title . "** saat ini.";
                        if (!empty($search_client_name) || !empty($date_start) || !empty($date_end)) {
                            $info_msg .= " Coba reset filter pencarian Anda.";
                        }
                        echo $info_msg;
                        ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Trip & Start Date</th>
                                    <th>Client</th>
                                    <th>Total Harga</th>
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
                                    if (!$booking['payment_status']) $payment_badge = 'bg-info'; 

                                    $paid_at = $booking['paid_at'] ? date('d M H:i', strtotime($booking['paid_at'])) : 'N/A';
                                    $start_date_formatted = date('d M Y', strtotime($booking['start_date']));
                                ?>
                                <tr>
                                    <td><?php echo $booking['booking_id']; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['trip_title']); ?>
                                        <small class="d-block text-muted">Mulai: <?php echo $start_date_formatted; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                    <td>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></td>
                                    <td><span class="badge <?php echo $booking_badge; ?>"><?php echo ucfirst($booking['booking_status']); ?></span></td>
                                    <td>
                                        <span class="badge <?php echo $payment_badge; ?>"><?php echo ucfirst($booking['payment_status'] ?: 'No Record'); ?></span>
                                        <small class="d-block text-muted">Lunas: <?php echo $paid_at; ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        // Aksi Force Confirm hanya muncul di Pending & Paid
                                        if ($booking['booking_status'] == 'pending' && in_array($current_tab, ['pending', 'paid'])): ?>
                                            <a href="bookings.php?action=force_confirm&id=<?php echo $booking['booking_id']; ?>&tab=<?php echo $current_tab; ?>" 
                                               class="btn btn-primary btn-sm mb-1"
                                               onclick="return confirm('KONFIRMASI MANUAL: Yakin booking ini sudah terbayar?')"
                                            >Force Paid & Confirm</a>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Aksi Cancel tidak muncul jika status sudah Canceled
                                        if ($booking['booking_status'] != 'canceled'): ?>
                                            <a href="bookings.php?action=cancel&id=<?php echo $booking['booking_id']; ?>&tab=<?php echo $current_tab; ?>" 
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