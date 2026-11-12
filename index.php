<?php
session_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TokoKu - Beranda</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="logo">TokoKu</div>
    <div class="nav-menu">
    <a href="index.php">Home</a>
    <a href="login.php">Login</a>
    </div>
</nav>

<section class="hero">
    <div class="hero-content">
    <h1>Selamat Datang di TokoKu</h1>
    <p>Sistem Informasi Penjualan dan Manajemen Toko</p>
    <a href="login.php" class="btn">Login Sekarang</a>
    </div>
</section>

<section class="about">
    <h2>Tentang TokoKu</h2>
    <p>
        TokoKu merupakan aplikasi berbasis web untuk membantu
        Pengelolaan produk, transaksi penjualan, dan laporan toko.
    </p>
</section>

<footer>
    <p>&copy; 2026 TokoKu All Rights Reserved</p>
</footer>
</body>
</html>