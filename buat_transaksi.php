<?php
session_start();
include 'koneksi.php';

// 1. Validasi login khusus Karyawan Toko
if (!isset($_SESSION['login']) or !in_array(strtolower(trim($_SESSION['role'])), ['karyawan toko', 'karyawan'])) {
    header("Location: login.php");
    exit;
}

$id_pengguna =$_SESSION['id_pengguna'] ?? '';
$id_toko     =$_SESSION['id_toko'] ?? '';
$id_user_esc = mysqli_real_escape_string($koneksi, $id_pengguna);$id_toko_esc = mysqli_real_escape_string($koneksi,$id_toko);

// Fungsi bantu cek tabel dan struktur kolom database
function cekTabel($koneksi,$tabel) {
    $res = mysqli_query($koneksi, "SHOW TABLES LIKE '$tabel'");
    return ($res && mysqli_num_rows($res) > 0);
}

function ambilInfoKolom($koneksi, $tabel) {$info = [];
    if (cekTabel($koneksi,$tabel)) {
        $q = mysqli_query($koneksi, "SHOW COLUMNS FROM `$tabel`");
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $info[] =$r;
            }
        }
    }
    return $info;
}

function cariKolomMetode($info_kolom) {$kandidat = [
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
    foreach ($kandidat as$k) {
        if (in_array($k,$semua_field)) {
            return $k;
        }
    }
    foreach ($info_kolom as $col) {$f = strtolower($col['Field']);$t = strtolower($col['Type']);$is_text = (strpos($t, 'varchar') !== false or strpos($t, 'enum') !== false or strpos($t, 'text') !== false or strpos($t, 'char') !== false);
        if ($is_text && !in_array($f, ['id_transaksi', 'id_toko', 'id_pengguna', 'id_user', 'id_karyawan', 'status'])) {
            if (strpos($f, 'metode') !== false or strpos($f, 'bayar') !== false or strpos($f, 'pembayaran') !== false or strpos($f, 'jenis') !== false or strpos($f, 'cara') !== false) {
                return $col['Field'];
            }
        }
    }
    return null;
}

$info_produk    = ambilInfoKolom($koneksi, 'produk');
$info_transaksi = ambilInfoKolom($koneksi, 'transaksi');
$info_detail    = ambilInfoKolom($koneksi, 'detail_transaksi');

$kolom_produk    = array_column($info_produk, 'Field');
$kolom_transaksi = array_column($info_transaksi, 'Field');

$col_harga  = in_array('harga', $kolom_produk) ? 'harga' : (in_array('harga_jual',$kolom_produk) ? 'harga_jual' : 'harga_produk');
$col_stok   = in_array('stok',$kolom_produk) ? 'stok' : 'jumlah_stok';
$col_metode = cariKolomMetode($info_transaksi);

// Jika tabel transaksi belum punya kolom metode pembayaran sama sekali, tambahkan otomatis
if ($col_metode === null && !empty($kolom_transaksi)) {
    try {
        mysqli_query($koneksi, "ALTER TABLE transaksi ADD COLUMN metode_pembayaran VARCHAR(50) NULL");
        $col_metode = 'metode_pembayaran';$kolom_transaksi[] = 'metode_pembayaran';
    } catch (Exception $e) {
        // Abaikan jika tidak diizinkan mengubah struktur tabel
    }
}

$pesan = "";

// 2. Proses Simpan Transaksi
if (isset($_POST['simpan_transaksi'])) {
    $id_produk         = mysqli_real_escape_string($koneksi, trim($_POST['id_produk'] ?? ''));$jumlah            = (int) ($_POST['jumlah'] ?? 0);$metode_pembayaran = mysqli_real_escape_string($koneksi, trim($_POST['metode_pembayaran'] ?? ''));
    $uang_dibayar      = (float) ($_POST['uang_dibayar'] ?? 0);

    // Ambil data produk terpilih dari database
    $q_prod = mysqli_query($koneksi, "SELECT * FROM produk WHERE id_produk = '$id_produk' LIMIT 1");
    $prod   = ($q_prod && mysqli_num_rows($q_prod) > 0) ? mysqli_fetch_assoc($q_prod) : null;

    if (!$prod) {$pesan = "<div class='alert error'>Silakan pilih produk terlebih dahulu.</div>";
    } elseif ($jumlah <= 0) {$pesan = "<div class='alert error'>Jumlah pembelian minimal 1.</div>";
    } elseif ($jumlah > (int)$prod[$col_stok]) {$pesan = "<div class='alert error'>Stok tidak mencukupi. Stok tersedia hanya " . (int) $prod[$col_stok] . ".</div>";
    } elseif ($metode_pembayaran === '') {$pesan = "<div class='alert error'>Silakan pilih metode pembayaran.</div>";
    } else {
        $harga     = (float)$prod[$col_harga];$total     = $harga * $jumlah;
        $kembalian = $uang_dibayar -$total;

        if ($uang_dibayar < $total) {$pesan = "<div class='alert error'>Jumlah uang dibayarkan kurang dari total pembayaran (Rp " . number_format($total, 0, ',', '.') . ").</div>";
        } else {
            // Buat ID Transaksi unik otomatis (TRX001, TRX002, dst.)
            $urut_trx = 1;
            do {
                $next_id_trx = "TRX" . sprintf("\%03d", $urut_trx);
                $cek_trx     = mysqli_query($koneksi, "SELECT 1 FROM transaksi WHERE id_transaksi = '$next_id_trx' LIMIT 1");
                $ada_trx     = ($cek_trx && mysqli_num_rows($cek_trx) > 0);$urut_trx++;
            } while ($ada_trx);

            $tanggal_sekarang = date('Y-m-d H:i:s');

            // Siapkan data insert ke tabel transaksi sesuai kolom yang ada di database
            $data_trx = [
                'id_transaksi'      => $next_id_trx,
                'id_toko'           => $id_toko,
                'id_pengguna'       => $id_pengguna,
                'id_user'           => $id_pengguna,
                'id_karyawan'       => $id_pengguna,
                'tanggal'           => $tanggal_sekarang,
                'tanggal_transaksi' => $tanggal_sekarang,
                'tgl_transaksi'     => $tanggal_sekarang,
                'total'             => $total,
                'total_harga'       => $total,
                'total_pembayaran'  => $total,
                'total_bayar'       => $total,
                'uang_dibayar'      => $uang_dibayar,
                'jumlah_bayar'      => $uang_dibayar,
                'bayar'             => $uang_dibayar,
                'kembalian'         => $kembalian,
                'status'            => 'Selesai'
            ];

            if ($col_metode !== null) {$data_trx[$col_metode] =$metode_pembayaran;
            }

            $cols_ins = [];$vals_ins = [];
            foreach ($kolom_transaksi as$col) {
                if (array_key_exists($col,$data_trx)) {
                    if ($data_trx[$col] === '' && in_array($col, ['id_toko', 'id_pengguna', 'id_user', 'id_karyawan'])) { continue; }$cols_ins[] = "`$col`";
                    $vals_ins[] = "'" . mysqli_real_escape_string($koneksi, (string) $data_trx[$col]) . "'";
                }
            }

            try {
                $sql_trx = "INSERT INTO transaksi (" . implode(", ", $cols_ins) . ") VALUES (" . implode(", ", $vals_ins) . ")";
                if (mysqli_query($koneksi,$sql_trx)) {
                    // Simpan ke tabel detail_transaksi jika tabel tersedia
                    if (!empty($info_detail)) {$data_det = [
                            'id_transaksi' => $next_id_trx,
                            'id_produk'    => $id_produk,
                            'jumlah'       => $jumlah,
                            'qty'          => $jumlah,
                            'jumlah_beli'  => $jumlah,
                            'harga'        => $harga,
                            'harga_satuan' => $harga,
                            'harga_produk' => $harga,
                            'subtotal'     => $total,
                            'sub_total'    => $total,
                            'total_harga'  => $total,
                            'total'        => $total
                        ];

                        $det_cols = [];$det_vals = [];

                        foreach ($info_detail as$dinfo) {
                            $dcol =$dinfo['Field'];

                            if ($dinfo['Extra'] === 'auto_increment') {
                                continue;
                            }

                            if ($dinfo['Key'] === 'PRI' && !in_array($dcol, ['id_transaksi', 'id_produk'])) {
                                if (stripos($dinfo['Type'], 'int') !== false) {
                                    $q_max_d = mysqli_query($koneksi, "SELECT COALESCE(MAX(`$dcol`), 0) + 1 AS next_num FROM detail_transaksi");
                                    $r_max_d = mysqli_fetch_assoc($q_max_d);$id_det_baru = (string) ($r_max_d['next_num'] ?? 1); } else {$urut_det = 1;
                                    do {
                                        $id_det_baru = "DTL" . sprintf("\%03d", $urut_det);
                                        $cek_det     = mysqli_query($koneksi, "SELECT 1 FROM detail_transaksi WHERE `$dcol` = '$id_det_baru' LIMIT 1");
                                        $ada_det     = ($cek_det && mysqli_num_rows($cek_det) > 0);$urut_det++;
                                    } while ($ada_det);
                                }
                                $det_cols[] = "`$dcol`";
                                $det_vals[] = "'" . mysqli_real_escape_string($koneksi,$id_det_baru) . "'";
                                continue;
                            }

                            if (array_key_exists($dcol, $data_det)) {$det_cols[] = "`$dcol`";
                                $det_vals[] = "'" . mysqli_real_escape_string($koneksi, (string) $data_det[$dcol]) . "'";
                            }
                        }

                        if (!empty($det_cols)) {
                            mysqli_query($koneksi, "INSERT INTO detail_transaksi (" . implode(", ", $det_cols) . ") VALUES (" . implode(", ", $det_vals) . ")");
                        }
                    }

                    // Kurangi stok produk
                    $stok_baru = (int)$prod[$col_stok] -$jumlah;
                    mysqli_query($koneksi, "UPDATE produk SET `$col_stok` = '$stok_baru' WHERE id_produk = '$id_produk'");

                    $pesan = "<div class='alert success'>
                                Transaksi <strong>$next_id_trx</strong> ($metode_pembayaran) berhasil disimpan! 
                                Total: <strong>Rp " . number_format($total, 0, ',', '.') . "</strong> - 
                                Kembalian: <strong>Rp " . number_format($kembalian, 0, ',', '.') . "</strong>.
                              </div>";
                }
            } catch (Exception $e) {$pesan = "<div class='alert error'>Gagal menyimpan transaksi: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// 3. Ambil daftar produk yang tersedia untuk pilihan dropdown
$where_produk = ($id_toko !== '' && in_array('id_toko',$kolom_produk))
    ? "WHERE id_toko = '$id_toko_esc' AND `$col_stok` > 0"
    : "WHERE `$col_stok` > 0";
$daftar_produk = mysqli_query($koneksi, "SELECT * FROM produk $where_produk ORDER BY nama_produk ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Transaksi Penjualan - TokoKu</title>
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
            max-width: 780px;
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
        .form-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }
        .page-header h2 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .page-header p {
            color: #475569;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .info-banner {
            background-color: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1e40af;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 22px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert.success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 7px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            color: #0f172a;
            background-color: #ffffff;
        }
        .form-group input[readonly] {
            background-color: #f8fafc;
            color: #475569;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .btn-submit {
            width: 100%;
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            padding: 13px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
            transition: background 0.2s ease;
        }
        .btn-submit:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <a href="dashboard_karyawan.php" class="back-link">&larr; Kembali ke Dashboard</a>

        <div class="form-container">
            <div class="page-header">
                <h2>Form Transaksi Penjualan</h2>
                <p>Masukkan produk yang dibeli pelanggan.</p>
            </div>

            <div class="info-banner">
                Transaksi akan dicatat menggunakan akun karyawan yang sedang login.
            </div>

            <?php echo $pesan; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="id_produk">Pilih Produk</label>
                    <select name="id_produk" id="id_produk" onchange="updateProduk()" required>
                        <option value="" data-harga="0" data-stok="">-- Pilih Produk --</option>
                        <?php if ($daftar_produk && mysqli_num_rows($daftar_produk) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($daftar_produk)): ?>
                                <option value="<?php echo htmlspecialchars($p['id_produk']); ?>"
                                        data-harga="<?php echo (float) $p[$col_harga]; ?>"
                                        data-stok="<?php echo (int) $p[$col_stok]; ?>">
                                    <?php echo htmlspecialchars($p['nama_produk'] . ' (Rp ' . number_format((float) $p[$col_harga], 0, ',', '.') . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="harga_produk">Harga Produk</label>
                    <input type="text" id="harga_produk" placeholder="Harga produk" readonly>
                </div>

                <div class="form-group">
                    <label for="stok_tersedia">Stok Tersedia</label>
                    <input type="text" id="stok_tersedia" placeholder="Stok produk" readonly>
                </div>

                <div class="form-group">
                    <label for="jumlah">Jumlah Pembelian</label>
                    <input type="number" name="jumlah" id="jumlah" value="1" min="1" oninput="hitungTotal()" required>
                </div>

                <div class="form-group">
                    <label for="total_pembayaran">Total Pembayaran</label>
                    <input type="text" id="total_pembayaran" placeholder="Total" readonly>
                </div>

                <div class="form-group">
                    <label for="metode_pembayaran">Metode Pembayaran</label>
                    <select name="metode_pembayaran" id="metode_pembayaran" onchange="cekMetodeBayar()" required>
                        <option value="">-- Pilih Pembayaran --</option>
                        <option value="Tunai">Tunai</option>
                        <option value="QRIS">QRIS</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="uang_dibayar">Jumlah Uang Dibayarkan</label>
                    <input type="number" name="uang_dibayar" id="uang_dibayar" min="0" placeholder="Masukkan nominal uang dibayarkan" required>
                </div>

                <button type="submit" name="simpan_transaksi" class="btn-submit">Simpan Transaksi</button>
            </form>
        </div>
    </div>

    <script>
        let hargaAktif = 0;
        let totalAktif = 0;

        function formatRupiah(angka) {
            return 'Rp ' + angka.toLocaleString('id-ID');
        }

        function updateProduk() {
            const select   = document.getElementById('id_produk');
            const option   = select.options[select.selectedIndex];
            const rawHarga = option.getAttribute('data-harga');
            const harga    = rawHarga ? parseFloat(rawHarga) : 0;
            const stok     = option.getAttribute('data-stok');

            hargaAktif = harga;

            const inputHarga  = document.getElementById('harga_produk');
            const inputStok   = document.getElementById('stok_tersedia');
            const inputJumlah = document.getElementById('jumlah');

            if (select.value === '') {
                inputHarga.value  = '';
                inputStok.value   = '';
                inputJumlah.removeAttribute('max');
            } else {
                inputHarga.value = formatRupiah(harga);
                inputStok.value  = stok;
                inputJumlah.setAttribute('max', stok);
                if (parseInt(inputJumlah.value) > parseInt(stok)) {
                    inputJumlah.value = stok;
                }
            }

            hitungTotal();
        }

        function hitungTotal() {
            const rawJumlah  = document.getElementById('jumlah').value;
            const jumlah     = rawJumlah ? parseInt(rawJumlah) : 0;
            const inputTotal = document.getElementById('total_pembayaran');

            if (hargaAktif > 0 && jumlah > 0) {
                totalAktif = hargaAktif * jumlah;
                inputTotal.value = formatRupiah(totalAktif);
            } else {
                totalAktif = 0;
                inputTotal.value = '';
            }

            cekMetodeBayar();
        }

        function cekMetodeBayar() {
            const metode = document.getElementById('metode_pembayaran').value;
            const inputBayar = document.getElementById('uang_dibayar');

            if (metode === 'QRIS' && totalAktif > 0) {
                inputBayar.value = totalAktif;
            }
        }
    </script>

</body>
</html>