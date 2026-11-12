<?php
require_once "koneksi.php";

function kembaliKeRegister($pesan)
{
    header("Location: register.php?pesan=" . urlencode($pesan));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$nama = trim($_POST['nama'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$id_toko = trim($_POST['id_toko'] ?? '');

if ($nama === '' || $username === '' || $password === '') {
    kembaliKeRegister('input_tidak_valid');
}

if (strlen($nama) > 100 || strlen($username) > 50) {
    kembaliKeRegister('input_tidak_valid');
}

if (!in_array($role, ['admin', 'pemilik', 'karyawan'], true)) {
    kembaliKeRegister('role_tidak_valid');
}

if ($role === 'admin') {
    $id_toko = null;
} else {
    if ($id_toko === '') {
        kembaliKeRegister('toko_wajib');
    }

    $stmt_toko = mysqli_prepare($koneksi, "SELECT id_toko FROM toko WHERE id_toko = ?");
    if (!$stmt_toko) {
        error_log("Gagal menyiapkan pemeriksaan toko: " . mysqli_error($koneksi));
        kembaliKeRegister('gagal');
    }

    mysqli_stmt_bind_param($stmt_toko, "s", $id_toko);
    if (!mysqli_stmt_execute($stmt_toko)) {
        error_log("Gagal memeriksa toko: " . mysqli_stmt_error($stmt_toko));
        mysqli_stmt_close($stmt_toko);
        kembaliKeRegister('gagal');
    }

    mysqli_stmt_store_result($stmt_toko);
    $toko_ditemukan = mysqli_stmt_num_rows($stmt_toko) > 0;
    mysqli_stmt_close($stmt_toko);

    if (!$toko_ditemukan) {
        kembaliKeRegister('toko_tidak_ditemukan');
    }
}

$stmt_username = mysqli_prepare($koneksi, "SELECT id_pengguna FROM pengguna WHERE username = ?");
if (!$stmt_username) {
    error_log("Gagal menyiapkan pemeriksaan username: " . mysqli_error($koneksi));
    kembaliKeRegister('gagal');
}

mysqli_stmt_bind_param($stmt_username, "s", $username);
if (!mysqli_stmt_execute($stmt_username)) {
    error_log("Gagal memeriksa username: " . mysqli_stmt_error($stmt_username));
    mysqli_stmt_close($stmt_username);
    kembaliKeRegister('gagal');
}

mysqli_stmt_store_result($stmt_username);
$username_tersedia = mysqli_stmt_num_rows($stmt_username) === 0;
mysqli_stmt_close($stmt_username);

if (!$username_tersedia) {
    kembaliKeRegister('username_ada');
}

$id_pengguna = "USR" . strtoupper(bin2hex(random_bytes(4)));
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare(
    $koneksi,
    "INSERT INTO pengguna (id_pengguna, nama, username, password, role, id_toko)
     VALUES (?, ?, ?, ?, ?, ?)"
);
if (!$stmt) {
    error_log("Gagal menyiapkan pendaftaran pengguna: " . mysqli_error($koneksi));
    kembaliKeRegister('gagal');
}

mysqli_stmt_bind_param(
    $stmt,
    "ssssss",
    $id_pengguna,
    $nama,
    $username,
    $password_hash,
    $role,
    $id_toko
);

if (!mysqli_stmt_execute($stmt)) {
    $errno = mysqli_stmt_errno($stmt);
    if ($errno === 1062) {
        mysqli_stmt_close($stmt);
        kembaliKeRegister('username_ada');
    }

    error_log("Gagal menyimpan pengguna: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    kembaliKeRegister('gagal');
}

mysqli_stmt_close($stmt);
mysqli_close($koneksi);
kembaliKeRegister('berhasil');
?>
