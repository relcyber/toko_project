<?php
session_start();
include 'koneksi.php';

// 1. Validasi login khusus Karyawan Toko
if (!isset($_SESSION['login']) or !in_array(strtolower(trim($_SESSION['role'])), ['karyawan toko', 'karyawan'])) {
    header("Location: login.php");
    exit;
}

$id_pengguna = $_SESSION['id_pengguna'] ?? '';
$id_user_esc = mysqli_real_escape_string($koneksi, $id_pengguna);

// 2. Deteksi nama kolom pada tabel transaksi
function ambilInfoKolom($koneksi, $tabel) {
    $info = [];
    try {
        $q = mysqli_query($koneksi, "SHOW COLUMNS FROM `$tabel`");
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $info[] = $r;
            }
        }
    } catch (Exception $e) {
        $info = [];
    }
    return $info;
}

function cariKolomMetode($info_kolom) {
    $kandidat = [
        'metode_pembayaran',
        'metode_bayar',
        'metode',
        'jenis_pembayaran',
        'jenis_bayar',
        'cara_pembayaran',
        'cara_bayar',
        'tipe_pembayaran',
        'tipe_bayar',
        'pembayaran'
    ];
    $semua_field = array_column($info_kolom, 'Field');
    foreach ($kandidat as $k) {
        if (in_array($k, $semua_field)) {
            return $k;
        }
    }
    foreach ($info_kolom as $col) {
        $f = strtolower($col['Field']);
        $t = strtolower($col['Type']);
        $is_text = (strpos($t, 'varchar') !== false or strpos($t, 'enum') !== false or strpos($t, 'text') !== false or strpos($t, 'char') !== false);
        if ($is_text && !in_array($f, ['id_transaksi', 'id_toko', 'id_pengguna', 'id_user', 'id_karyawan', 'status'])) {
            if (strpos($f, 'metode') !== false or strpos($f, 'bayar') !== false or strpos($f, 'pembayaran') !== false or strpos($f, 'jenis') !== false or strpos($f, 'cara') !== false) {
                return $col['Field'];
            }
        }
    }
    return null;
}

$info_transaksi  = ambilInfoKolom($koneksi, 'transaksi');
$kolom_tabel     = array_column($info_transaksi, 'Field');

$col_user = null;
foreach (['id_pengguna', 'id_user', 'id_karyawan', 'karyawan_id'] as $k) {
    if (in_array($k, $kolom_tabel)) {
        $col_user = $k;
        break;
    }
}

$col_tanggal = in_array('tanggal_transaksi', $kolom_tabel) ? 'tanggal_transaksi' : (in_array('tgl_transaksi', $kolom_tabel) ? 'tgl_transaksi' : 'tanggal');
$col_total   = 'total';
foreach (['total', 'total_harga', 'total_pembayaran', 'total_bayar'] as $k) {
    if (in_array($k, $kolom_tabel)) {
        $col_total = $k;
        break;
    }
}
$col_bayar = cariKolomMetode($info_transaksi);

// 3. Ambil daftar transaksi milik karyawan yang sedang login
$where_user = ($col_user !== null && $id_pengguna !== '') ? "WHERE `$col_user` = '$id_user_esc'" : "";
$order_by   = in_array('id_transaksi', $kolom_tabel) ? "ORDER BY id_transaksi DESC" : "";

$daftar_transaksi = false;
if (!empty($kolom_tabel)) {
    $daftar_transaksi = mysqli_query($koneksi, "SELECT * FROM transaksi $where_user $order_by");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan - TokoKu</title>
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
                <h2>Transaksi Penjualan</h2>
                <p>Daftar transaksi yang dilakukan oleh Anda.</p>
            </div>

            <a href="buat_transaksi.php" class="btn-primary">+ Buat Transaksi Baru</a>

            <table>
                <thead>
                    <tr>
                        <th style="width: 65px;">No</th>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Total</th>
                        <th>Pembayaran</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if ($daftar_transaksi && mysqli_num_rows($daftar_transaksi) > 0):
                        while ($row = mysqli_fetch_assoc($daftar_transaksi)):
                            $id_trx   = $row['id_transaksi'] ?? '-';
                            $tgl_raw  = $row[$col_tanggal] ?? '';
                            $tgl_fmt  = $tgl_raw ? date('d-m-Y H:i', strtotime($tgl_raw)) : '-';
                            $total    = (float) ($row[$col_total] ?? 0);
                            $metode   = ($col_bayar !== null && !empty($row[$col_bayar])) ? $row[$col_bayar] : '-';
                            $status   = $row['status'] ?? 'Selesai';
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($id_trx); ?></td>
                            <td><?php echo htmlspecialchars($tgl_fmt); ?></td>
                            <td>Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($metode); ?></td>
                            <td><?php echo htmlspecialchars($status); ?></td>
                        </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <tr>
                            <td colspan="6" class="empty-row">Belum ada data transaksi penjualan yang Anda lakukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>