<?php
session_start();
include 'koneksi.php';

// 1. Validasi login khusus Karyawan Toko
if (!isset($_SESSION['login']) or !in_array(strtolower(trim($_SESSION['role'])), ['karyawan toko', 'karyawan'])) {
    header("Location: login.php");
    exit;
}

$nama_karyawan = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Karyawan';
$id_pengguna   = $_SESSION['id_pengguna'] ?? '';
$id_toko       = $_SESSION['id_toko'] ?? '';

$id_user_esc = mysqli_real_escape_string($koneksi, $id_pengguna);
$id_toko_esc = mysqli_real_escape_string($koneksi, $id_toko);

// Fungsi bantu cek kolom di tabel
function cekKolom($koneksi, $tabel, $kolom) {
    try {
        $res = mysqli_query($koneksi, "SHOW COLUMNS FROM `$tabel` LIKE '$kolom'");
        return ($res && mysqli_num_rows($res) > 0);
    } catch (Exception $e) {
        return false;
    }
}

// Fungsi bantu ambil satu angka dari database
function ambilAngka($koneksi, $sql) {
    try {
        $res = mysqli_query($koneksi, $sql);
        if ($res) {
            $row = mysqli_fetch_row($res);
            return $row[0] ?? 0;
        }
    } catch (Exception $e) {
        return 0;
    }
    return 0;
}

// 2. Hitung Total Produk Toko dari database
if ($id_toko !== '' && cekKolom($koneksi, 'produk', 'id_toko')) {
    $total_produk = (int) ambilAngka($koneksi, "SELECT COUNT(*) FROM produk WHERE id_toko = '$id_toko_esc'");
} else {
    $total_produk = (int) ambilAngka($koneksi, "SELECT COUNT(*) FROM produk");
}

// 3. Hitung Transaksi Anda & Total Penjualan Anda dari database (otomatis 0 jika belum ada transaksi)
$col_user = null;
foreach (['id_pengguna', 'id_user', 'id_karyawan', 'karyawan_id'] as $ku) {
    if (cekKolom($koneksi, 'transaksi', $ku)) {
        $col_user = $ku;
        break;
    }
}

$col_total = 'total';
foreach (['total', 'total_harga', 'total_pembayaran', 'total_bayar'] as $kt) {
    if (cekKolom($koneksi, 'transaksi', $kt)) {
        $col_total = $kt;
        break;
    }
}

$ada_status = cekKolom($koneksi, 'transaksi', 'status');

if ($col_user !== null && $id_pengguna !== '') {
    $where_trx     = "WHERE `$col_user` = '$id_user_esc'";
    $where_selesai = $ada_status ? "WHERE `$col_user` = '$id_user_esc' AND status = 'Selesai'" : $where_trx;
} else {
    $where_trx     = "";
    $where_selesai = $ada_status ? "WHERE status = 'Selesai'" : "";
}

$transaksi_anda  = (int) ambilAngka($koneksi, "SELECT COUNT(*) FROM transaksi $where_trx");
$total_penjualan = (float) ambilAngka($koneksi, "SELECT COALESCE(SUM(`$col_total`), 0) FROM transaksi $where_selesai");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan - TokoKu</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            display: flex;
            background-color: #f4f6f9;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 250px;
            background-color: #1e3a8a;
            color: white;
            padding-top: 20px;
            flex-shrink: 0;
        }
        .sidebar h2 {
            padding: 0 20px 20px 20px;
            font-size: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        .sidebar ul {
            list-style-type: none;
        }
        .sidebar ul li a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            font-size: 15px;
        }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background-color: rgba(255,255,255,0.1);
        }

        /* Main Content Styling */
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        /* Welcome Card */
        .welcome-card {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .welcome-card h3 {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .welcome-card p {
            color: #475569;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Stats Cards */
        .stats-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            flex: 1;
        }
        .stat-card p {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .stat-card h4 {
            font-size: 32px;
            color: #1e3a8a;
        }

        /* Button Styling */
        .btn-primary {
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            transition: background 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>

    <!-- Sidebar Menu -->
    <div class="sidebar">
        <h2>TokoKu</h2>
        <ul>
            <li><a href="dashboard_karyawan.php" class="active">Dashboard</a></li>
            <li><a href="data_produk.php">Data Produk</a></li>
            <li><a href="transaksi.php">Transaksi</a></li>
            <li><a href="buat_transaksi.php">Buat Transaksi</a></li>
            <li><a href="riwayat_transaksi.php">Riwayat Transaksi</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Area Sambutan -->
        <div class="welcome-card">
            <h3>Dashboard Karyawan</h3>
            <p>Selamat datang, <?php echo htmlspecialchars($nama_karyawan); ?>.</p>
            <p>Silakan lakukan aktivitas penjualan melalui menu TokoKu.</p>
        </div>

        <!-- Area Informasi Angka -->
        <div class="stats-grid">
            <div class="stat-card">
                <p>Total Produk Toko</p>
                <h4><?php echo $total_produk; ?></h4>
            </div>
            <div class="stat-card">
                <p>Transaksi Anda</p>
                <h4><?php echo $transaksi_anda; ?></h4>
            </div>
            <div class="stat-card">
                <p>Total Penjualan Anda</p>
                <h4>Rp <?php echo number_format($total_penjualan, 0, ',', '.'); ?></h4>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <a href="buat_transaksi.php" class="btn-primary">+ Buat Transaksi Baru</a>
    </div>

</body>
</html>