<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Manajemen Pengguna';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Proses update user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update') {
        $id = clean_input($_POST['id']);
        $name = clean_input($_POST['name']);
        $email = clean_input($_POST['email']);
        $phone = clean_input($_POST['phone']);
        $address = clean_input($_POST['address']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Cek email unique jika berubah
        $check_query = "SELECT email FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $old_email = mysqli_fetch_assoc($result)['email'];
        
        if ($old_email != $email) {
            $check_email = "SELECT id FROM users WHERE email = ?";
            $stmt = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $message = 'Email sudah digunakan oleh user lain';
                $message_type = 'error';
            }
        }
        
        if (!$message) {
            $query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ?, is_active = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssii", $name, $email, $phone, $address, $is_active, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Data pengguna berhasil diperbarui';
                $message_type = 'success';
            } else {
                $message = 'Gagal memperbarui data pengguna';
                $message_type = 'error';
            }
        }
        
    } elseif ($action == 'reset_password') {
        $id = clean_input($_POST['id']);
        
        // Generate random password
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Password berhasil direset. Password baru: <strong>' . $new_password . '</strong>';
            $message_type = 'success';
        } else {
            $message = 'Gagal mereset password';
            $message_type = 'error';
        }
    }
}

// Ambil semua user (kecuali admin yang sedang login)
$query = "SELECT * FROM users WHERE id != ? ORDER BY role DESC, created_at DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$users_result = mysqli_stmt_get_result($stmt);

// Get user stats
$stats_query = "SELECT 
                  COUNT(*) as total_users,
                  SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
                  SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as total_customers,
                  SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
                FROM users 
                WHERE id != ?";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Get user for edit
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = clean_input($_GET['edit']);
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_user = mysqli_fetch_assoc($result);
}

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Manajemen Pengguna</h1>
    <p style="color: #666;">Kelola data pengguna sistem</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>">
        <?php echo $message; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="grid grid-4" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Total Pengguna</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
        <div class="stat-label">Admin</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
        <div class="stat-label">Pelanggan</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['active_users']; ?></div>
        <div class="stat-label">Aktif</div>
    </div>
</div>

<div class="grid" style="grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Form Edit User -->
    <?php if ($edit_user): ?>
        <div class="card">
            <div class="card-header">
                <h3 style="color: white; margin: 0;">Edit Pengguna</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="name">Nama Lengkap *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">Telepon *</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($edit_user['phone']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="address">Alamat</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($edit_user['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <div style="padding: 0.5rem; background-color: #f8f9fa; border-radius: 4px;">
                            <?php echo $edit_user['role'] == 'admin' ? 'Administrator' : 'Pelanggan'; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?php echo $edit_user['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="margin: 0;">Akun Aktif</label>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary btn-block">Update Data</button>
                        <button type="button" onclick="confirmResetPassword(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['name']); ?>')" 
                                class="btn btn-warning btn-block" style="margin-top: 0.5rem;">
                            Reset Password
                        </button>
                        <a href="/clean-tech/admin/users.php" class="btn btn-secondary btn-block" style="margin-top: 0.5rem;">
                            Batal Edit
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 style="color: white; margin: 0;">Informasi</h3>
            </div>
            <div class="card-body">
                <p>Pilih pengguna untuk mengedit data.</p>
                <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                    <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Tips:</h4>
                    <ul style="padding-left: 1rem; color: #666;">
                        <li>Klik tombol "Edit" untuk mengubah data pengguna</li>
                        <li>Gunakan "Reset Password" untuk membuat password baru</li>
                        <li>Nonaktifkan akun untuk membatasi akses pengguna</li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Users List -->
    <div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: white; margin: 0;">Daftar Pengguna</h3>
                <span style="color: white; font-size: 0.9rem;">
                    Total: <?php echo $stats['total_users']; ?> pengguna
                </span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($users_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Bergabung</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <?php if ($user['address']): ?>
                                                <small style="color: #666; font-size: 0.8rem;">
                                                    <?php echo substr($user['address'], 0, 30); ?>...
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td>
                                            <?php if ($user['role'] == 'admin'): ?>
                                                <span class="badge badge-primary">Admin</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Pelanggan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active']): ?>
                                                <span class="badge badge-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Tidak ada pengguna lain</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Confirmation Modal -->
<div id="resetPasswordModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Konfirmasi Reset Password</h3>
            <button class="close-modal" onclick="closeResetModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="resetMessage">Apakah Anda yakin ingin mereset password pengguna ini?</p>
            <p style="color: #666; font-size: 0.9rem; margin-top: 0.5rem;">
                Password baru akan digenerate secara acak dan ditampilkan setelah proses selesai.
            </p>
        </div>
        <div class="modal-footer">
            <form method="POST" action="" id="resetForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="resetId">
                <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Batal</button>
                <button type="submit" class="btn btn-warning">Reset Password</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmResetPassword(id, name) {
    document.getElementById('resetMessage').textContent = `Reset password untuk "${name}"?`;
    document.getElementById('resetId').value = id;
    document.getElementById('resetPasswordModal').classList.add('active');
}

function closeResetModal() {
    document.getElementById('resetPasswordModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeResetModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>