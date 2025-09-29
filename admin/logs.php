<?php
// admin/logs.php
require_once '../functions/auth.php'; 
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once '../config/database.php';

check_admin_access();

// ----------------------------------------------------
// QUERY DATA LOG
// ----------------------------------------------------

$query = "
    SELECT 
        a.id, a.action_type, a.target_table, a.target_id, a.description, a.created_at,
        u.name AS admin_name
    FROM admin_activities a
    JOIN users u ON a.admin_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 500
";
$result = $conn->query($query);

$activities = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
}
?>

<div id="page-content-wrapper">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/all.min.css">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container-fluid">
            <h5 class="my-2">Admin Activity Log</h5>
        </div>
    </nav>
    
    <div class="container-fluid p-4">
        <h1 class="mt-4 mb-4">Riwayat Aktivitas Super Admin</h1>

        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">500 Aktivitas Terbaru</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Admin</th>
                                <th>Aksi</th>
                                <th>Target Tabel</th>
                                <th>Target ID</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $log): 
                                $action_badge = 'bg-secondary';
                                if (in_array($log['action_type'], ['approve', 'enable', 'verify', 'resolve'])) $action_badge = 'bg-success';
                                if (in_array($log['action_type'], ['disable', 'reject', 'suspend'])) $action_badge = 'bg-warning text-dark';
                                if (in_array($log['action_type'], ['delete'])) $action_badge = 'bg-danger';
                            ?>
                            <tr>
                                <td><?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                <td><span class="badge <?php echo $action_badge; ?>"><?php echo ucfirst($log['action_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['target_table']); ?></td>
                                <td><?php echo htmlspecialchars($log['target_id']); ?></td>
                                <td><?php echo htmlspecialchars($log['description'] ?: '-'); ?></td>
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
echo '</div>'; // Tutup div id="wrapper"
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>