<?php
/**
 * File ini digunakan untuk membuat hash password
 * Gunakan hasil hash untuk disimpan ke database
 */

// Password yang ingin di-hash
$password = 'admin123'; // GANTI sesuai kebutuhan

// Generate hash menggunakan algoritma default (bcrypt)
$hash = password_hash($password, PASSWORD_DEFAULT);

// Tampilkan hasil
echo "<h3>Generator Hash Password</h3>";
echo "<p><strong>Password Asli:</strong> {$password}</p>";
echo "<p><strong>Hash Password:</strong></p>";
echo "<textarea cols='100' rows='3'>{$hash}</textarea>";
