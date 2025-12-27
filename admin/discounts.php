<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Manajemen Diskon';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Proses CRUD diskon
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        // Tambah diskon baru
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);
        $discount_type = clean_input($_POST['discount_type']);
        $discount_value = clean_input($_POST['discount_value']);
        $min_orders = clean_input($_POST['min_orders']);
        $valid_from = !empty($_POST['valid_from']) ? clean_input($_POST['valid_from']) : NULL;
        $valid_until = !empty($_POST['valid_until']) ? clean_input($_POST['valid_until']) : NULL;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "INSERT INTO discounts (name, description, discount_type, discount_value, min_orders, valid_from, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssdissi", $name, $description, $discount_type, $discount_value, $min_orders, $valid_from, $valid_until, $is_active);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Diskon berhasil ditambahkan';
            $message_type = 'success';
        } else {
            $message = 'Gagal menambahkan diskon';
            $message_type = 'error';
        }
        
    } elseif ($action == 'update') {
        // Update diskon
        $id = clean_input($_POST['id']);
        $name = clean_input($_POST['name']);
        $description = clean_input($_POST['description']);
        $discount_type = clean_input($_POST['discount_type']);
        $discount_value = clean_input($_POST['discount_value']);
        $min_orders = clean_input($_POST['min_orders']);
        $valid_from = !empty($_POST['valid_from']) ? clean_input($_POST['valid_from']) : NULL;
        $valid_until = !empty($_POST['valid_until']) ? clean_input($_POST['valid_until']) : NULL;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $query = "UPDATE discounts SET name = ?, description = ?, discount_type = ?, discount_value = ?, min_orders = ?, valid_from = ?, valid_until = ?, is_active = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssdissii", $name, $description, $discount_type, $discount_value, $min_orders, $valid_from, $valid_until, $is_active, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Diskon berhasil diperbarui';
            $message_type = 'success';
        } else {
            $message = 'Gagal memperbarui diskon';
            $message_type = 'error';
        }
        
    } elseif ($action == 'delete') {
        // Hapus diskon
        $id = clean_input($_POST['id']);
        
        $query = "DELETE FROM discounts WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Diskon berhasil dihapus';
            $message_type = 'success';
        } else {
            $message = 'Gagal menghapus diskon';
            $message_type = 'error';
        }
    }
}

// Ambil semua diskon
$query = "SELECT * FROM discounts ORDER BY is_active DESC, discount_value DESC";
$discounts_result = mysqli_query($conn, $query);

// Ambil diskon untuk edit
$edit_discount = null;
if (isset($_GET['edit'])) {
    $id = clean_input($_GET['edit']);
    $query = "SELECT * FROM discounts WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_discount = mysqli_fetch_assoc($result);
}

// Statistik penggunaan diskon
$stats_query = "SELECT 
                  d.name,
                  COUNT(o.id) as usage_count,
                  SUM(o.discount) as total_discount_given
                FROM discounts d
                LEFT JOIN orders o ON d.id = o.discount_id
                GROUP BY d.id
                ORDER BY usage_count DESC";
$stats_result = mysqli_query($conn, $stats_query);

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Manajemen Diskon</h1>
    <p style="color: #666;">Kelola promo dan diskon untuk pelanggan</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>">
        <?php echo $message; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Form Tambah/Edit Diskon -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: white; margin: 0;">
                <?php echo $edit_discount ? 'Edit Diskon' : 'Tambah Diskon Baru'; ?>
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <?php if ($edit_discount): ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $edit_discount['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="create">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label" for="name">Nama Diskon *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo $edit_discount ? htmlspecialchars($edit_discount['name']) : ''; ?>" 
                           required placeholder="Contoh: New Customer, Monthly Discount, etc.">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="description">Deskripsi *</label>
                    <textarea id="description" name="description" class="form-control" rows="2" required><?php echo $edit_discount ? htmlspecialchars($edit_discount['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="discount_type">Tipe Diskon *</label>
                    <select id="discount_type" name="discount_type" class="form-control" required>
                        <option value="percentage" <?php echo ($edit_discount && $edit_discount['discount_type'] == 'percentage') ? 'selected' : ''; ?>>Persentase (%)</option>
                        <option value="fixed" <?php echo ($edit_discount && $edit_discount['discount_type'] == 'fixed') ? 'selected' : ''; ?>>Nominal Tetap (Rp)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="discount_value">
                        Nilai Diskon * 
                        <span id="valueLabel"><?php echo ($edit_discount && $edit_discount['discount_type'] == 'percentage') ? '(%)' : '(Rp)'; ?></span>
                    </label>
                    <input type="number" id="discount_value" name="discount_value" class="form-control" 
                           value="<?php echo $edit_discount ? $edit_discount['discount_value'] : ''; ?>" 
                           min="0" step="<?php echo ($edit_discount && $edit_discount['discount_type'] == 'percentage') ? '1' : '1000'; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="min_orders">Minimal Pesanan</label>
                    <input type="number" id="min_orders" name="min_orders" class="form-control" 
                           value="<?php echo $edit_discount ? $edit_discount['min_orders'] : '0'; ?>" 
                           min="0" step="1">
                    <small style="color: #666;">0 = tanpa minimum pesanan</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="valid_from">Berlaku Dari</label>
                    <input type="date" id="valid_from" name="valid_from" class="form-control" 
                           value="<?php echo $edit_discount ? $edit_discount['valid_from'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="valid_until">Berlaku Sampai</label>
                    <input type="date" id="valid_until" name="valid_until" class="form-control" 
                           value="<?php echo $edit_discount ? $edit_discount['valid_until'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo ($edit_discount && $edit_discount['is_active']) || !$edit_discount ? 'checked' : ''; ?>>
                        <label for="is_active" style="margin: 0;">Aktif</label>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo $edit_discount ? 'Update Diskon' : 'Tambah Diskon'; ?>
                    </button>
                    
                    <?php if ($edit_discount): ?>
                        <a href="/clean-tech/admin/discounts.php" class="btn btn-secondary btn-block" style="margin-top: 0.5rem;">
                            Batal Edit
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Daftar Diskon dan Statistik -->
    <div>
        <!-- Statistik -->
        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-header">
                <h3 style="color: white; margin: 0;">Statistik Penggunaan</h3>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($stats_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama Diskon</th>
                                    <th>Digunakan</th>
                                    <th>Total Diskon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($stat = mysqli_fetch_assoc($stats_result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $stat['usage_count'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                                <?php echo $stat['usage_count']; ?> kali
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($stat['total_discount_given']): ?>
                                                Rp <?php echo number_format($stat['total_discount_given'], 0, ',', '.'); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 1rem;">Belum ada data statistik</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Daftar Diskon -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: white; margin: 0;">Daftar Diskon</h3>
                <span style="color: white; font-size: 0.9rem;">
                    Total: <?php echo mysqli_num_rows($discounts_result); ?> diskon
                </span>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($discounts_result) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Deskripsi</th>
                                    <th>Nilai</th>
                                    <th>Min. Pesanan</th>
                                    <th>Berlaku</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($discount = mysqli_fetch_assoc($discounts_result)): ?>
                                    <tr>
                                        <td style="font-weight: 500;"><?php echo htmlspecialchars($discount['name']); ?></td>
                                        <td style="font-size: 0.9rem; color: #666;">
                                            <?php echo htmlspecialchars($discount['description']); ?>
                                        </td>
                                        <td>
                                            <?php if ($discount['discount_type'] == 'percentage'): ?>
                                                <span class="badge badge-primary"><?php echo $discount['discount_value']; ?>%</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Rp <?php echo number_format($discount['discount_value'], 0, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($discount['min_orders'] > 0): ?>
                                                <span class="badge badge-warning"><?php echo $discount['min_orders']; ?> pesanan</span>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($discount['valid_from'] && $discount['valid_until']): ?>
                                                <div style="font-size: 0.8rem;">
                                                    <div><?php echo date('d/m/Y', strtotime($discount['valid_from'])); ?></div>
                                                    <div>s/d</div>
                                                    <div><?php echo date('d/m/Y', strtotime($discount['valid_until'])); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #999; font-size: 0.9rem;">Selamanya</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($discount['is_active']): ?>
                                                <span class="badge badge-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.25rem;">
                                                <a href="?edit=<?php echo $discount['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                                <button onclick="confirmDelete(<?php echo $discount['id']; ?>, '<?php echo htmlspecialchars($discount['name']); ?>')" 
                                                        class="btn btn-sm btn-danger">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Belum ada diskon</p>
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
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus diskon ini?</p>
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
    document.getElementById('deleteMessage').textContent = `Apakah Anda yakin ingin menghapus diskon "${name}"?`;
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

// Update label berdasarkan tipe diskon
document.getElementById('discount_type').addEventListener('change', function() {
    const valueLabel = document.getElementById('valueLabel');
    const discountValueInput = document.getElementById('discount_value');
    
    if (this.value === 'percentage') {
        valueLabel.textContent = '(%)';
        discountValueInput.step = '1';
        discountValueInput.placeholder = 'Contoh: 15, 20, 30';
    } else {
        valueLabel.textContent = '(Rp)';
        discountValueInput.step = '1000';
        discountValueInput.placeholder = 'Contoh: 50000, 100000';
    }
});

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>