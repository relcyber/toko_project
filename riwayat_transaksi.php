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

// Fungsi bantu cek tabel dan struktur kolom database
function cekTabel($koneksi, $tabel) {
    $res = mysqli_query($koneksi, "SHOW TABLES LIKE '$tabel'");
    return ($res && mysqli_num_rows($res) > 0);
}

function ambilInfoKolom($koneksi, $tabel) {
    $info = [];
    if (cekTabel($koneksi, $tabel)) {
        $q = mysqli_query($koneksi, "SHOW COLUMNS FROM `$tabel`");
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $info[] = $r;
            }
        }
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

$info_transaksi = ambilInfoKolom($koneksi, 'transaksi');
$info_detail    = ambilInfoKolom($koneksi, 'detail_transaksi');
$info_produk    = ambilInfoKolom($koneksi, 'produk');

$kolom_transaksi = array_column($info_transaksi, 'Field');
$kolom_detail    = array_column($info_detail, 'Field');
$kolom_produk    = array_column($info_produk, 'Field');

// 2. Deteksi nama kolom pada tabel transaksi
$col_user = null;
foreach (['id_pengguna', 'id_user', 'id_karyawan', 'karyawan_id'] as $k) {
    if (in_array($k, $kolom_transaksi)) {
        $col_user = $k;
        break;
    }
}

$col_tanggal = in_array('tanggal_transaksi', $kolom_transaksi) ? 'tanggal_transaksi' : (in_array('tgl_transaksi', $kolom_transaksi) ? 'tgl_transaksi' : 'tanggal');

$col_total = 'total';
foreach (['total', 'total_harga', 'total_pembayaran', 'total_bayar'] as $k) {
    if (in_array($k, $kolom_transaksi)) {
        $col_total = $k;
        break;
    }
}

$col_metode    = cariKolomMetode($info_transaksi);
$col_bayar     = in_array('uang_dibayar', $kolom_transaksi) ? 'uang_dibayar' : (in_array('jumlah_bayar', $kolom_transaksi) ? 'jumlah_bayar' : 'bayar');
$col_kembalian = in_array('kembalian', $kolom_transaksi) ? 'kembalian' : 'uang_kembali';

// Deteksi nama kolom pada tabel detail_transaksi & produk
$dcol_jumlah   = in_array('jumlah', $kolom_detail) ? 'jumlah' : (in_array('qty', $kolom_detail) ? 'qty' : 'jumlah_beli');
$dcol_harga    = in_array('harga', $kolom_detail) ? 'harga' : (in_array('harga_satuan', $kolom_detail) ? 'harga_satuan' : 'harga_produk');
$dcol_subtotal = in_array('subtotal', $kolom_detail) ? 'subtotal' : (in_array('sub_total', $kolom_detail) ? 'sub_total' : 'total_harga');
$pcol_harga    = in_array('harga', $kolom_produk) ? 'harga' : (in_array('harga_jual', $kolom_produk) ? 'harga_jual' : 'harga_produk');

// 3. Ambil seluruh riwayat transaksi karyawan yang sedang login
$where_user = ($col_user !== null && $id_pengguna !== '') ? "WHERE `$col_user` = '$id_user_esc'" : "";
$order_by   = in_array('id_transaksi', $kolom_transaksi) ? "ORDER BY id_transaksi DESC" : "";

$daftar_transaksi = false;
if (!empty($kolom_transaksi)) {
    $daftar_transaksi = mysqli_query($koneksi, "SELECT * FROM transaksi $where_user $order_by");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - TokoKu</title>
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
            max-width: 1200px;
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
            overflow-x: auto;
        }
        .page-header h2 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 5px;
        }
        .page-header p {
            color: #475569;
            font-size: 14px;
            margin-bottom: 22px;
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
            padding: 13px 15px;
            text-align: left;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .item-detail-box {
            background-color: #eff6ff;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 8px;
            font-size: 13px;
            line-height: 1.5;
            color: #1e293b;
            min-width: 190px;
        }
        .item-detail-box:last-child {
            margin-bottom: 0;
        }
        .status-selesai {
            color: #16a34a;
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
                <h2>Riwayat Transaksi</h2>
                <p>Daftar seluruh transaksi yang pernah Anda lakukan.</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 55px;">No</th>
                        <th>ID Transaksi</th>
                        <th>Tanggal</th>
                        <th>Detail Produk</th>
                        <th>Total</th>
                        <th>Metode Bayar</th>
                        <th>Uang Dibayar</th>
                        <th>Kembalian</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    if ($daftar_transaksi && mysqli_num_rows($daftar_transaksi) > 0):
                        while ($row = mysqli_fetch_assoc($daftar_transaksi)):
                            $id_trx    = $row['id_transaksi'] ?? '-';
                            $id_trx_es = mysqli_real_escape_string($koneksi, $id_trx);
                            $tgl_raw   = $row[$col_tanggal] ?? '';
                            $tgl_fmt   = $tgl_raw ? date('d-m-Y H:i', strtotime($tgl_raw)) : '-';
                            $total     = (float) ($row[$col_total] ?? 0);
                            $metode    = ($col_metode !== null && !empty($row[$col_metode])) ? $row[$col_metode] : '-';
                            $dibayar   = isset($row[$col_bayar]) ? (float) $row[$col_bayar] : $total;
                            $kembalian = isset($row[$col_kembalian]) ? (float) $row[$col_kembalian] : max(0, $dibayar - $total);
                            $status    = $row['status'] ?? 'Selesai';

                            // Ambil rincian produk untuk transaksi ini
                            $items = [];
                            if (!empty($kolom_detail) && in_array('id_transaksi', $kolom_detail)) {
                                $q_det = mysqli_query($koneksi, "SELECT d.*, p.nama_produk, p.`$pcol_harga` AS harga_master 
                                                                 FROM detail_transaksi d 
                                                                 LEFT JOIN produk p ON d.id_produk = p.id_produk 
                                                                 WHERE d.id_transaksi = '$id_trx_es'");
                                if ($q_det) {
                                    while ($d = mysqli_fetch_assoc($q_det)) {
                                        $nama_p   = $d['nama_produk'] ?? ($d['id_produk'] ?? 'Produk');
                                        $jml_p    = (int) ($d[$dcol_jumlah] ?? 1);
                                        $hrg_p    = isset($d[$dcol_harga]) ? (float) $d[$dcol_harga] : (float) ($d['harga_master'] ?? 0);
                                        $sub_p    = isset($d[$dcol_subtotal]) ? (float) $d[$dcol_subtotal] : ($hrg_p * $jml_p);
                                        $items[]  = [
                                            'nama'     => $nama_p,
                                            'jumlah'   => $jml_p,
                                            'harga'    => $hrg_p,
                                            'subtotal' => $sub_p
                                        ];
                                    }
                                }
                            } elseif (isset($row['id_produk']) && $row['id_produk'] !== '') {
                                $id_p_es = mysqli_real_escape_string($koneksi, $row['id_produk']);
                                $q_p = mysqli_query($koneksi, "SELECT * FROM produk WHERE id_produk = '$id_p_es' LIMIT 1");
                                $dp  = ($q_p && mysqli_num_rows($q_p) > 0) ? mysqli_fetch_assoc($q_p) : [];
                                $jml_p = (int) ($row['jumlah'] ?? $row['qty'] ?? 1);
                                $hrg_p = (float) ($dp[$pcol_harga] ?? ($jml_p > 0 ? $total / $jml_p : 0));
                                $items[] = [
                                    'nama'     => $dp['nama_produk'] ?? $row['id_produk'],
                                    'jumlah'   => $jml_p,
                                    'harga'    => $hrg_p,
                                    'subtotal' => $total
                                ];
                            }
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($id_trx); ?></td>
                            <td><?php echo htmlspecialchars($tgl_fmt); ?></td>
                            <td>
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $item): ?>
                                        <div class="item-detail-box">
                                            <div><?php echo htmlspecialchars($item['nama']); ?></div>
                                            <div>Jumlah: <?php echo $item['jumlah']; ?></div>
                                            <div>Harga: Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></div>
                                            <div>Subtotal: Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #64748b;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($metode); ?></td>
                            <td>Rp <?php echo number_format($dibayar, 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($kembalian, 0, ',', '.'); ?></td>
                            <td><span class="status-selesai"><?php echo htmlspecialchars($status); ?></span></td>
                        </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <tr>
                            <td colspan="9" class="empty-row">Belum ada riwayat transaksi yang tercatat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>