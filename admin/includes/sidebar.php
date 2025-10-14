<?php
// admin/includes/sidebar.php

// Dapatkan nama file saat ini (misal: "users.php")
$current_page = basename($_SERVER['PHP_SELF']);

// CATATAN PENTING:
// Pastikan TIDAK ADA SPASI, BARIS BARU, ATAU KARAKTER APAPUN
// SEBELUM TAG PEMBUKA PHP INI.
// Juga, HILANGKAN TAG PENUTUP PHP di akhir file ini untuk mencegah output tak terduga.
?>

<div id="sidebar-wrapper">
    
    <div class="sidebar-heading border-bottom border-secondary bg-dark text-white p-4">
        <h4 class="fw-bold mb-0">Travelers Admin</h4>
    </div>
    
    <div class="list-group list-group-flush list-group-nav">
        
        <a href="dashboard.php" class="list-group-item list-group-item-action sidebar-item 
            <?php echo ($current_page == 'dashboard.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-home fa-fw me-3"></i> Dashboard
        </a>
        
        <a href="users.php" class="list-group-item list-group-item-action sidebar-item 
            <?php echo ($current_page == 'users.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-users fa-fw me-3"></i> User & Provider
        </a>
        
        <a href="verification.php" class="list-group-item list-group-item-action sidebar-item 
            <?php echo ($current_page == 'verification.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-circle-check fa-fw me-3"></i> Verifikasi Provider
        </a>
        
        <a href="trips.php" class="list-group-item list-group-item-action sidebar-item 
            <?php echo ($current_page == 'trips.php' || $current_page == 'trip_detail.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-plane-departure fa-fw me-3"></i> Trip Management
        </a>
        
        <a href="bookings.php" class="list-group-item list-group-item-action sidebar-item
            <?php echo ($current_page == 'bookings.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-book-open fa-fw me-3"></i> Booking & Payment
        </a>

        <a href="drivers.php" class="list-group-item list-group-item-action sidebar-item
            <?php echo ($current_page == 'drivers.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-car fa-fw me-3"></i> Daftar Driver
        </a>

        <a href="reviews.php" class="list-group-item list-group-item-action sidebar-item
        <?php echo ($current_page == 'reviews.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-star fa-fw me-3"></i> Review Moderation
        </a>
        <a href="provider_tickets.php" class="list-group-item list-group-item-action sidebar-item
            <?php echo ($current_page == 'provider_tickets.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-headset fa-fw me-3"></i> Tiket Dukungan Provider
        </a>
        <a href="complaints.php" class="list-group-item list-group-item-action sidebar-item
            <?php echo ($current_page == 'complaints.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-comment-dots fa-fw me-3"></i> Chat Keluhan
        </a>
        
        <a href="logs.php" class="list-group-item list-group-item-action sidebar-item
            <?php echo ($current_page == 'logs.php') ? 'active-sidebar' : 'bg-dark text-light'; ?>">
            <i class="fas fa-history fa-fw me-3"></i> Admin Activity Log
        </a>
        
        <a href="../logout.php" class="list-group-item list-group-item-action sidebar-item bg-dark text-danger mt-4 sidebar-logout">
            <i class="fas fa-sign-out-alt fa-fw me-3"></i> Logout
        </a>
    </div>
</div>

<style>
    #sidebar-wrapper {
        min-height: 100vh;
        width: 250px; /* Lebar Sidebar */
        transition: margin 0.25s ease-out;
        background-color: #212529; /* Background gelap default Bootstrap */
    }
    .list-group-nav {
        padding-bottom: 20px; 
    }
    .sidebar-item {
        color: #adb5bd; /* Warna teks abu-abu terang */
        border: none;
        padding-top: 10px;
        padding-bottom: 10px;
        transition: background-color 0.1s;
    }
    .sidebar-item:hover {
        color: #ffffff;
        background-color: #343a40; /* Hover yang lebih terang dari background */
    }
    .active-sidebar {
        background-color: #0d6efd !important; /* Biru Primer Bootstrap */
        color: white !important;
        font-weight: bold;
    }
    .active-sidebar:hover {
        background-color: #0b5ed7 !important;
    }
    /* Anda mungkin perlu CSS tambahan jika menggunakan layout toggle */
    #wrapper.toggled #sidebar-wrapper {
        margin-left: 0;
    }
    /* Mengatasi konflik list-group-item dengan bg-dark */
    .list-group-item.bg-dark {
        border-color: rgba(255, 255, 255, 0.1);
    }
</style>