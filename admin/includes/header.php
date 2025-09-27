<?php

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Travelers Admin | Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; }
        #wrapper { display: flex; }
        #sidebar-wrapper {
            min-height: 100vh;
            margin-left: -15rem; /* Default: tersembunyi */
            transition: margin .25s ease-out;
            background-color: #343a40;
        }
        #sidebar-wrapper .sidebar-heading { padding: 0.875rem 1.25rem; font-size: 1.2rem; color: white; }
        #sidebar-wrapper .list-group { width: 15rem; }
        
        /* CSS Tambahan untuk Toggling */
        #wrapper.toggled #sidebar-wrapper {
            margin-left: 0; 
        }

        #page-content-wrapper {
            min-width: 0; 
            width: 100%;
        }

        @media (min-width: 768px) {
            #sidebar-wrapper {
                min-height: 100vh;
                margin-left: -15rem;
                transition: margin .25s ease-out;
                /* Ganti warna di sini */
                background-color: #3f5164; /* Abu-abu kebiruan yang lebih lembut */ 
            }
            #sidebar-wrapper .sidebar-heading { 
                /* Pastikan teks tetap putih atau cerah */
                color: white; 
            }
            .list-group-item-action { 
                /* Pastikan teks link terbaca */
                color: #e0e0e0; /* Putih pucat */ 
            } 
            .list-group-item-action:hover { 
                /* Warna hover yang bagus */
                background-color: #556677; 
                color: white; 
            }
        }
    </style>
</head>
<body>
<div id="wrapper" class="toggled"> 