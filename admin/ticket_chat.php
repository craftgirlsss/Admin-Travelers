<?php
// admin/ticket_chat.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$admin_id = $_SESSION['user_id']; 

if ($ticket_id === 0) {
    header("Location: provider_tickets.php?alert_type=danger&status_msg=" . urlencode("ID Tiket tidak valid."));
    exit;
}

// ----------------------------------------------------
// LOGIKA SUBMIT PESAN BARU
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // Masukkan pesan ke ticket_messages
        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_role, message) VALUES (?, 'admin', ?)");
        $stmt->bind_param("is", $ticket_id, $message);
        $stmt->execute();
        $stmt->close();

        // Update last_updated di provider_tickets_new
        $stmt_update = $conn->prepare("UPDATE provider_tickets_new SET last_updated = NOW(), status = 'open' WHERE id = ?");
        $stmt_update->bind_param("i", $ticket_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Redirect untuk refresh dan mencegah resubmit
        header("Location: ticket_chat.php?ticket_id=" . $ticket_id);
        exit;
    }
}

// ----------------------------------------------------
// QUERY DETAIL TIKET & PESAN
// ----------------------------------------------------
$ticket_query = "
    SELECT pt.*, p.company_name, u.name AS provider_user 
    FROM provider_tickets_new pt
    JOIN providers p ON pt.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE pt.id = ?
";
$stmt = $conn->prepare($ticket_query);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$ticket_result = $stmt->get_result();
$ticket = $ticket_result->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header("Location: provider_tickets.php?alert_type=danger&status_msg=" . urlencode("Tiket tidak ditemukan."));
    exit;
}

$messages_query = "SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY sent_at ASC";
$stmt_msg = $conn->prepare($messages_query);
$stmt_msg->bind_param("i", $ticket_id);
$stmt_msg->execute();
$messages_result = $stmt_msg->get_result();
$messages = [];
while ($row = $messages_result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt_msg->close();

// Logika untuk tombol Close/Reopen
if (isset($_GET['status_action'])) {
    $new_status = ($_GET['status_action'] == 'close') ? 'closed' : 'open';
    $message_status = ($_GET['status_action'] == 'close') ? 'Ditutup' : 'Dibuka Kembali';

    $stmt_status = $conn->prepare("UPDATE provider_tickets_new SET status = ?, last_updated = NOW() WHERE id = ?");
    $stmt_status->bind_param("si", $new_status, $ticket_id);
    $stmt_status->execute();
    $stmt_status->close();
    
    // Refresh
    header("Location: ticket_chat.php?ticket_id=" . $ticket_id . "&alert_type=success&status_msg=" . urlencode("Tiket berhasil " . $message_status . "."));
    exit;
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Chat Tiket #<?php echo $ticket_id; ?></h5>
            <div class="ms-auto">
                <?php if ($ticket['status'] != 'closed'): ?>
                    <a href="ticket_chat.php?ticket_id=<?php echo $ticket_id; ?>&status_action=close" class="btn btn-success btn-sm me-2" onclick="return confirm('Tutup Tiket ini?')">Tutup Tiket</a>
                <?php else: ?>
                    <a href="ticket_chat.php?ticket_id=<?php echo $ticket_id; ?>&status_action=reopen" class="btn btn-warning btn-sm me-2">Buka Kembali</a>
                <?php endif; ?>
                <a href="provider_tickets.php" class="btn btn-secondary btn-sm">Kembali ke Daftar</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <?php if (isset($_GET['status_msg'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['alert_type'] ?? 'success'); ?>" role="alert">
                <?php echo htmlspecialchars($_GET['status_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h5 class="m-0 font-weight-bold text-primary">Tiket: <?php echo htmlspecialchars($ticket['subject']); ?></h5>
                <small>Dari: <?php echo htmlspecialchars($ticket['company_name']); ?> (<?php echo htmlspecialchars($ticket['provider_user']); ?>) | Status: <span class="badge bg-danger"><?php echo ucfirst($ticket['status']); ?></span></small>
            </div>
            <div class="card-body">
                <div class="chat-box" style="height: 400px; overflow-y: scroll; border: 1px solid #ccc; padding: 15px; background-color: #f9f9f9;">
                    <?php foreach ($messages as $msg): 
                        // KOREKSI LOGIKA: Gunakan 'admin' sesuai ENUM database Anda
                        $is_admin = ($msg['sender_role'] == 'admin'); 
                        
                        // KOREKSI PENAMAAN: Tampilkan 'Admin' jika Admin, dan 'Nama Perusahaan' jika Provider
                        $sender_display_name = $is_admin ? 'Admin' : htmlspecialchars($ticket['company_name']);
                        
                        // Logika Styling Bubble
                        $alignment = $is_admin ? 'justify-content-end' : 'justify-content-start';
                        $bg_color = $is_admin ? 'bg-info text-dark' : 'bg-light'; // Admin diberi warna info/biru muda
                        $text_color = $is_admin ? 'text-white' : 'text-dark';
                        
                        // Logika Waktu
                        $timestamp = $msg['sent_at'] ?? ''; 
                        $time_display = !empty($timestamp) ? date('H:i', strtotime($timestamp)) : 'Waktu N/A';
                    ?>
                    
                        <div class="d-flex mb-3 <?php echo $alignment; ?>">
                            <div class="d-flex flex-column align-items-<?php echo $is_admin ? 'end' : 'start'; ?>" style="max-width: 75%;">
                                
                                <small class="text-muted mb-1 px-2">
                                    <?php echo $sender_display_name; ?> - <?php echo $time_display; ?>
                                </small>
                                
                                <div class="p-2 rounded-3 shadow-sm <?php echo $bg_color; ?> <?php echo $text_color; ?>">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                    <?php if (empty($messages)): ?>
                        <p class="text-center text-muted mt-5">Belum ada pesan dalam tiket ini.</p>
                    <?php endif; ?>
                </div>

                <?php if ($ticket['status'] != 'closed'): ?>
                    <form method="POST" action="ticket_chat.php?ticket_id=<?php echo $ticket_id; ?>" class="mt-4">
                        <div class="input-group">
                            <textarea name="message" class="form-control" rows="2" placeholder="Tulis balasan Anda di sini..."></textarea>
                            <button type="submit" class="btn btn-success">Kirim Balasan</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mt-4">Tiket ini sudah ditutup. Silakan buka kembali jika perlu membalas.</div>
                <?php endif; ?>

                        </div> 
                    </div> 
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