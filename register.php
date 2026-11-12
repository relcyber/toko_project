<?php
session_start();
if (isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}
$pesan = "";
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'username_ada') {
        $pesan = "Username sudah digunakan!";
    } elseif ($_GET['pesan'] == 'berhasil') {
        $pesan = "Pendaftaran berhasil! Silahkan login.";
    } elseif ($_GET['pesan'] == 'gagal') {
        $pesan = "Pendaftaran gagal! Silahkan coba lagi.";
    } elseif ($_GET['pesan'] == 'input_tidak_valid') {
        $pesan = "Nama, username, dan password wajib diisi dengan benar.";
    } elseif ($_GET['pesan'] == 'role_tidak_valid') {
        $pesan = "Role yang dipilih tidak valid.";
    } elseif ($_GET['pesan'] == 'toko_wajib') {
        $pesan = "ID Toko wajib diisi untuk Pemilik dan Karyawan.";
    } elseif ($_GET['pesan'] == 'toko_tidak_ditemukan') {
        $pesan = "ID Toko tidak ditemukan.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TokoKu</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Register TokoKu</h2>
            <p>Silahkan buat akun baru Anda</p>
            <?php if ($pesan != "") { ?>
                <div class="arlet">
                    <?= htmlspecialchars($pesan); ?>
                </div>
            <?php } ?>
            <form action="proses_register.php" method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama"
                        placeholder="Masukkan nama lengkap" required>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username"
                        placeholder="Buat Username" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password"
                        placeholder="Buat Password" required>
                </div>
                
                <div class="form-group">
                    <label>Daftrai Sebagai</label>
                    <select name="role" id="role" required>
                        <option value="">--Pilih Role--</option>
                        <option value="admin">Admin</option>
                        <option value="pemilik">Pemilik Toko</option>
                        <option value="karyawan">Karyawan Toko</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>ID Toko</label>
                    <input type="text" name="id_toko"
                        placeholder="Contoh: TK001">
                    <small style="color: #64748b;">Kosongkan jika mendaftar sebagai Admin</small>
                </div>
                <button type="submit" name="register" class="btn-login">Daftar Sekarang</button>
            </form>
            <div class="register-link">
                Sudah Punya Akun?
                <a href="login.php">Login di sini</a>
            </div>
                <a href="index.php" class="back-home">
                    Kembali ke Home
                </a>
        </div>
    </div>
</body>
</html>