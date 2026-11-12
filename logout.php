<?php
session_start();

// Hapus semua data variabel session
$_SESSION = [];
session_unset();

// Hapus cookie session di browser jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan session sepenuhnya
session_destroy();

// Arahkan pengguna kembali ke halaman login
header("Location: login.php");
exit;
?>