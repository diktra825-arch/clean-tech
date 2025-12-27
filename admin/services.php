<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Manajemen Layanan';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        // Tambah layanan baru
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);
        $price = clean_input($_POST['price']);
        $duration_hours = clean_input($_POST['duration_hours']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "INSERT INTO services (name, description, price, duration_hours, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssdii", $name, $description, $price, $duration_hours, $is_active);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Layanan berhasil ditambahkan';
            $message_type = 'success';
        } else {
            $message = 'Gagal menambahkan layanan';
            $message_type = 'error';
        }
        
    } elseif ($action == 'update') {
        // Update layanan
        $id = clean_input($_POST['id']);
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);
        $price = clean_input($_POST['price']);
        $duration_hours = clean_input($_POST['duration_hours']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE services SET name = ?, description = ?, price = ?, duration_hours = ?, is_active = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssdiii", $name, $description, $price, $duration_hours, $is_active, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Layanan berhasil diperbarui';
            $message_type = 'success';
        } else {
            $message = 'Gagal memperbarui layanan';
            $message_type = 'error';
        }
        
    } elseif ($action == 'delete') {
        // Hapus layanan
        $id = clean_input($_POST['id']);
        
        // Cek apakah layanan digunakan di pesanan
        $check_query = "SELECT COUNT(*) as count FROM orders WHERE service_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $check = mysqli_fetch_assoc($result);
        
        if ($check['count'] > 0) {
            $message = 'Layanan tidak dapat dihapus karena sudah digunakan dalam pesanan';
            $message_type = 'error';
        } else {
            $query = "DELETE FROM services WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Layanan berhasil dihapus';
                $message_type = 'success';
            } else {
                $message = 'Gagal menghapus layanan';
                $message_type = 'error';
            }
        }
    }
}

// Ambil semua layanan
$query = "SELECT * FROM services ORDER BY is_active DESC, name ASC";
$services_result = mysqli_query($conn, $query);

// Ambil layanan untuk edit
$edit_service = null;
if (isset($_GET['edit'])) {
    $id = clean_input($_GET['edit']);
    $query = "SELECT * FROM services WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_service = mysqli_fetch_assoc($result);
}

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Manajemen Layanan</h1>
    <p style="color: #666;">Kelola layanan cleaning service yang tersedia</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>">
        <?php echo $message; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Form Tambah/Edit -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: white; margin: 0;">
                <?php echo $edit_service ? 'Edit Layanan' : 'Tambah Layanan Baru'; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <?php if ($edit_service): ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $edit_service['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="create">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label" for="name">Nama Layanan *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo $edit_service ? htmlspecialchars($edit_service['name']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Deskripsi *</label>
                    <textarea id="description" name="description" class="form-control" rows="3" required><?php echo $edit_service ? htmlspecialchars($edit_service['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="price">Harga (Rp) *</label>
                    <input type="number" id="price" name="price" class="form-control" 
                           value="<?php echo $edit_service ? $edit_service['price'] : ''; ?>" 
                           min="0" step="1000" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="duration_hours">Durasi (jam) *</label>
                    <input type="number" id="duration_hours" name="duration_hours" class="form-control" 
                           value="<?php echo $edit_service ? $edit_service['duration_hours'] : '1'; ?>" 
                           min="1" max="24" required>
                </div>
                
                <div class="form-group">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo ($edit_service && $edit_service['is_active']) || !$edit_service ? 'checked' : ''; ?>>
                        <label for="is_active" style="margin: 0;">Aktif</label>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo $edit_service ? 'Update Layanan' : 'Tambah Layanan'; ?>
                    </button>
                    
                    <?php if ($edit_service): ?>
                        <a href="/clean-tech/admin/services.php" class="btn btn-secondary btn-block" style="margin-top: 0.5rem;">
                            Batal Edit
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Daftar Layanan -->
    <div>
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: white; margin: 0;">Daftar Layanan</h3>
                <span style="color: white; font-size: 0.9rem;">
                    Total: <?php echo mysqli_num_rows($services_result); ?> layanan
                </span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($services_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Harga</th>
                                    <th>Durasi</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($service = mysqli_fetch_assoc($services_result)): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($service['name']); ?></div>
                                            <small style="color: #666; font-size: 0.8rem;">
                                                <?php echo substr($service['description'], 0, 50); ?>...
                                            </small>
                                        </td>
                                        <td>Rp <?php echo number_format($service['price'], 0, ',', '.'); ?></td>
                                        <td><?php echo $service['duration_hours']; ?> jam</td>
                                        <td>
                                            <?php if ($service['is_active']): ?>
                                                <span class="badge badge-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.25rem;">
                                                <a href="?edit=<?php echo $service['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                                <button onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')" 
                                                        class="btn btn-sm btn-danger">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Belum ada layanan</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Konfirmasi Hapus</h3>
            <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus layanan ini?</p>
        </div>
        <div class="modal-footer">
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteMessage').textContent = `Apakah Anda yakin ingin menghapus layanan "${name}"?`;
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>