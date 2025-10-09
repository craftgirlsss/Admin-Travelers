<?php
// admin/provider_tickets.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

$status_msg = '';

// ----------------------------------------------------
// QUERY DATA TIKET PROVIDER
// ----------------------------------------------------
$query = "
    SELECT 
        pt.id AS ticket_id, 
        pt.subject, 
        pt.status AS ticket_status, 
        pt.last_updated,
        p.company_name, 
        u.name AS provider_user
    FROM provider_tickets_new pt
    JOIN providers p ON pt.provider_id = p.id
    JOIN users u ON p.user_id = u.id
    ORDER BY pt.last_updated DESC
";
$result = $conn->query($query);

$tickets = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Manajemen Tiket Provider</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Pusat Bantuan Provider</h1>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Daftar Tiket Dukungan</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subjek</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Update Terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): 
                                $status_badge = 'bg-secondary';
                                if ($ticket['ticket_status'] == 'open') $status_badge = 'bg-danger';
                                if ($ticket['ticket_status'] == 'in_progress') $status_badge = 'bg-warning text-dark';
                                if ($ticket['ticket_status'] == 'closed') $status_badge = 'bg-success';
                            ?>
                            <tr>
                                <td><?php echo $ticket['ticket_id']; ?></td>
                                <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['company_name']); ?> (<?php echo htmlspecialchars($ticket['provider_user']); ?>)</td>
                                <td><span class="badge <?php echo $status_badge; ?>"><?php echo ucfirst($ticket['ticket_status']); ?></span></td>
                                <td><?php echo date('d M Y H:i', strtotime($ticket['last_updated'])); ?></td>
                                <td>
                                    <a href="ticket_chat.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" 
                                       class="btn btn-primary btn-sm"
                                    >Lihat Chat</a>
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
echo '</div>'; 
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>