<?php
session_start();
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']);

    // Cari data akun berdasarkan username pada tabel pengguna
    $query = mysqli_query($koneksi, "SELECT * FROM pengguna WHERE username = '$username' LIMIT 1");

    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);

        // Mendukung pengecekan password baik yang di-hash (password_hash) maupun teks biasa
        if (password_verify($password, $data['password']) || $password === $data['password']) {
            
            // Simpan data user ke dalam session
            $_SESSION['login']        = true;
            $_SESSION['id_pengguna']  = $data['id_pengguna'] ?? $data['id_user'] ?? $data['id'];
            $_SESSION['nama_lengkap'] = $data['nama_lengkap'];
            $_SESSION['username']     = $data['username'];
            $_SESSION['role']         = $data['role'];
            $_SESSION['id_toko']      = $data['id_toko'] ?? null;

            // Arahkan ke halaman dashboard sesuai role
            $role = strtolower(trim($data['role']));

            if ($role === 'karyawan toko' || $role === 'karyawan') {
                header("Location: dashboard_karyawan.php");
                exit;
            } elseif ($role === 'pemilik toko' || $role === 'pemilik') {
                header("Location: pemilik/dashboard.php");
                exit;
            } elseif ($role === 'admin') {
                header("Location: admin/dashboard.php");
                exit;
            } else {
                header("Location: login.php?pesan=role_tidak_valid");
                exit;
            }
        }
    }

    // Jika username tidak ditemukan atau password salah
    header("Location: login.php?pesan=gagal");
    exit;
} else {
    header("Location: login.php");
    exit;
}
?>