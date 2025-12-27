<?php
session_start();

// Fungsi untuk mengecek apakah user sudah login
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk mengecek role user
function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

// Fungsi untuk redirect berdasarkan role
function redirect_based_on_role() {
    if (is_logged_in()) {
        $role = get_user_role();
        if ($role == 'admin') {
            header('Location: /clean-tech/admin/dashboard.php');
        } else {
            header('Location: /clean-tech/user/dashboard.php');
        }
        exit();
    }
}

// Fungsi logout
function logout() {
    session_destroy();
    header('Location: /clean-tech/');
    exit();
}
?>