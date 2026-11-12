<?php
session_start();
if (isset($_SESSION['role'])){
    header("Location: index.php");
    exit;
}
$pesan ="";
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'gagal'){
        $pesan = "Username atau password salah!";
    } elseif ($_GET['pesan'] == 'logout') {
        $pesan = "Anda berhasil logout!";
    } elseif ($_GET['pesan'] == 'login') {
        $pesan = "Silakhkan login terlebuh dahulu.!";
    }   
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Login - TokoKu</title>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Login TokoKu</h2>
            <p>Silakhkan masukkan akun Anda</p>
        <?php if ($pesan != "") { ?>
            <div class="arlet">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php } ?>
        <form action="proses_login.php" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required
                    placeholder="Masukkan username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required
                 placeholder="Masukkan password" required>
            </div>
            <button type="submit" name="login" class="btn-login">Login</button>
        </form> 
        <div class="register-link">
            Belum punya akun?
            <a href="register.php">Register di sini</a>
        </div>
        <a href="index.php" class="back-home">Kembali ke Home</a>
    </div>
</body>
</html>