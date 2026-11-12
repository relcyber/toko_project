<?php
session_start();
include 'koneksi.php';

// 1. Validasi login khusus Karyawan Toko
if (!isset($_SESSION['login']) || !in_array(strtolower(trim($_SESSION['role'])), ['karyawan toko', 'karyawan'])) {
    header("Location: login.php");
    exit;
}

$id_toko     = $_SESSION['id_toko'] ?? '';
$id_toko_esc = mysqli_real_escape_string($koneksi, $id_toko);

// 2. Deteksi kolom tabel produk agar sesuai dengan struktur tokoku.sql
$kolom_tabel = [];
try {
    $q_col = mysqli_query($koneksi, "SHOW COLUMNS FROM produk");
    if ($q_col) {
        while ($c = mysqli_fetch_assoc($q_col)) {
            $kolom_tabel[] = $c['Field'];
        }
    }
} catch (Exception $e) {
    $kolom_tabel = [];
}

$col_harga = in_array('harga', $kolom_tabel) ? 'harga' : (in_array('harga_jual', $kolom_tabel) ? 'harga_jual' : 'harga_produk');
$col_stok  = in_array('stok', $kolom_tabel) ? 'stok' : 'jumlah_stok';

// 3. Ambil daftar produk sesuai toko tempat karyawan bekerja
$where_toko = ($id_toko !== '' && in_array('id_toko', $kolom_tabel)) ? "WHERE id_toko = '$id_toko_esc'" : "";
$order_by   = in_array('id_produk', $kolom_tabel) ? "ORDER BY id_produk ASC" : "";

$daftar_produk = false;
try {
    $daftar_produk = mysqli_query($koneksi, "SELECT * FROM produk $where_toko $order_by");
} catch (Exception $e) {
    $daftar_produk = false;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Produk Toko - TokoKu</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f4f6f9;
            padding: 30px;
            color: #1e293b;
        }
        .wrapper {
            max-width: 1150px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-block;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 18px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .container-card {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }
        .page-header h2 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .page-header p {
            color: #475569;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .btn-primary {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff;
            text-decoration: none;
            padding: 11px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 22px;
            transition: background 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table thead {
            background-color: #1e3a8a;
            color: #ffffff;
        }
        table th, table td {
            padding: 13px 16px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }
        table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .status-tersedia {
            color: #16a34a;
            font-weight: 700;
        }
        .status-habis {
            color: #dc2626;
            font-weight: 700;
        }
        .empty-row {
            text-align: center;
            color: #64748b;
            padding: 20px;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <a href="dashboard_karyawan.php" class="back-link">&larr; Kembali ke Dashboard</a>

        <div class="container-card">
            <div class="page-header">
                <h2>Daftar Produk Toko</h2>
                <p>Berikut produk yang tersedia untuk transaksi penjualan.</p>
            </div>

            <a href="buat_transaksi.php" class="btn-primary">+ Buat Transaksi</a>

            <table>
                <thead>
                    <tr>
                        <th style="width: 65px;">No</th>
                        <th>ID Produk</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if ($daftar_produk && mysqli_num_rows($daftar_produk) > 0):
                        while ($row = mysqli_fetch_assoc($daftar_produk)):
                            $id_produk   = $row['id_produk'] ?? '-';
                            $nama_produk = $row['nama_produk'] ?? '-';
                            $harga       = (float) ($row[$col_harga] ?? 0);
                            $stok        = (int) ($row[$col_stok] ?? 0);
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($id_produk); ?></td>
                            <td><?php echo htmlspecialchars($nama_produk); ?></td>
                            <td>Rp <?php echo number_format($harga, 0, ',', '.'); ?></td>
                            <td><?php echo $stok; ?></td>
                            <td>
                                <?php if ($stok > 0): ?>
                                    <span class="status-tersedia">Tersedia</span>
                                <?php else: ?>
                                    <span class="status-habis">Habis</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <tr>
                            <td colspan="6" class="empty-row">Belum ada data produk yang tersedia pada toko ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>