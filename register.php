<?php
require_once 'config/database.php';
require_once 'functions/auth.php';

// Redirect jika sudah login
if (is_logged_in()) {
    redirect_based_on_role();
}

$page_title = 'Clean-Tech - Daftar';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $password = clean_input($_POST['password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    $address = clean_input($_POST['address']);

    // Validasi
    $errors = [];

    if (empty($name)) $errors[] = 'Nama harus diisi';
    if (empty($email)) $errors[] = 'Email harus diisi';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid';
    if (empty($phone)) $errors[] = 'Telepon harus diisi';
    if (empty($password)) $errors[] = 'Password harus diisi';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter';
    if ($password !== $confirm_password) $errors[] = 'Password tidak sama';

    // Cek email sudah terdaftar
    $check_email = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_email);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        $errors[] = 'Email sudah terdaftar';
    }

    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $query = "INSERT INTO users (name, email, phone, password, address, role) VALUES (?, ?, ?, ?, ?, 'user')";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $phone, $hashed_password, $address);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Pendaftaran berhasil! Silakan login.';
            header('Refresh: 3; URL=/clean-tech/login.php');
        } else {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

include 'includes/header.php';
?>

<div style="max-width: 500px; margin: 3rem auto; padding: 2rem; background-color: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <h2 style="text-align: center; color: var(--primary-color); margin-bottom: 1.5rem;">Daftar Akun Baru</h2>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
            <button class="close-alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
            <button class="close-alert">&times;</button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label" for="name">Nama Lengkap</label>
            <input type="text" id="name" name="name" class="form-control" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="phone">Nomor Telepon</label>
            <input type="tel" id="phone" name="phone" class="form-control" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" required minlength="6">
            <small style="color: #666;">Minimal 6 karakter</small>
        </div>

        <div class="form-group">
            <label class="form-label" for="confirm_password">Konfirmasi Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="address">Alamat</label>
            <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-group" style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary btn-block">Daftar</button>
        </div>

        <div style="text-align: center; margin-top: 1rem;">
            <p>Sudah punya akun? <a href="/clean-tech/login.php" style="color: var(--primary-color);">Login disini</a></p>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>