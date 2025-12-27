<?php
require_once 'config/database.php';
require_once 'functions/auth.php';

// Redirect jika sudah login
if (is_logged_in()) {
    redirect_based_on_role();
}

$page_title = 'Clean-Tech - Login';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean_input($_POST['email']);
    $password = clean_input($_POST['password']);
    
    // Validasi input
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } else {
        // Cek user di database
        $query = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Redirect berdasarkan role
            if ($user['role'] == 'admin') {
                header('Location: /clean-tech/admin/dashboard.php');
            } else {
                header('Location: /clean-tech/user/dashboard.php');
            }
            exit();
        } else {
            $error = 'Email atau password salah';
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 400px; margin: 3rem auto; padding: 2rem; background-color: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <h2 style="text-align: center; color: var(--primary-color); margin-bottom: 1.5rem;">Login</h2>
    
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
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <div class="form-group" style="margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <p>Belum punya akun? <a href="/clean-tech/register.php" style="color: var(--primary-color);">Daftar disini</a></p>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>