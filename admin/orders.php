<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Manajemen Pesanan';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Proses update status
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = clean_input($_POST['order_id']);
        $status = clean_input($_POST['status']);
        $notes = clean_input($_POST['notes']);
        
        // Update order status
        $query = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "si", $status, $order_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Add to history
            $query = "INSERT INTO order_history (order_id, status, notes) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iss", $order_id, $status, $notes);
            mysqli_stmt_execute($stmt);
            
            // Send notification to user
            $order_query = "SELECT user_id FROM orders WHERE id = ?";
            $stmt = mysqli_prepare($conn, $order_query);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $order_result = mysqli_stmt_get_result($stmt);
            $order_data = mysqli_fetch_assoc($order_result);
            
            if ($order_data) {
                $status_texts = [
                    'confirmed' => 'dikonfirmasi',
                    'processing' => 'sedang diproses',
                    'completed' => 'selesai',
                    'cancelled' => 'dibatalkan'
                ];
                
                $notification_title = "Status Pesanan Diubah";
                $notification_message = "Pesanan Anda telah " . ($status_texts[$status] ?? $status);
                
                $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "iss", $order_data['user_id'], $notification_title, $notification_message);
                mysqli_stmt_execute($stmt);
            }
            
            $message = 'Status pesanan berhasil diperbarui';
            $message_type = 'success';
        } else {
            $message = 'Gagal memperbarui status pesanan';
            $message_type = 'error';
        }
    }
}

// Filter parameters
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? clean_input($_GET['date']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Build query
$query = "SELECT o.*, u.name as user_name, u.phone as user_phone, s.name as service_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          JOIN services s ON o.service_id = s.id 
          WHERE 1=1";
$params = [];
$types = "";

if ($filter_status) {
    $query .= " AND o.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_date) {
    $query .= " AND DATE(o.order_date) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($search) {
    $query .= " AND (o.order_code LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY o.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);

// Get order counts for stats
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$count_query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$count_result = mysqli_query($conn, $count_query);
while ($row = mysqli_fetch_assoc($count_result)) {
    $status_counts[$row['status']] = $row['count'];
    $status_counts['all'] += $row['count'];
}

// Get order for view/edit
$view_order = null;
if (isset($_GET['view'])) {
    $id = clean_input($_GET['view']);
    $query = "SELECT o.*, u.name as user_name, u.email as user_email, u.phone as user_phone, 
                     s.name as service_name, s.price as service_price, s.duration_hours
              FROM orders o 
              JOIN users u ON o.user_id = u.id 
              JOIN services s ON o.service_id = s.id 
              WHERE o.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $view_order = mysqli_fetch_assoc($result);
    
    // Get order history
    if ($view_order) {
        $history_query = "SELECT * FROM order_history WHERE order_id = ? ORDER BY created_at ASC";
        $stmt = mysqli_prepare($conn, $history_query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $history_result = mysqli_stmt_get_result($stmt);
        $order_history = [];
        while ($row = mysqli_fetch_assoc($history_result)) {
            $order_history[] = $row;
        }
    }
}

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Manajemen Pesanan</h1>
    <p style="color: #666;">Kelola semua pesanan dari pelanggan</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>">
        <?php echo $message; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="grid grid-6" style="gap: 0.5rem; margin-bottom: 2rem;">
    <a href="?status=" class="card" style="text-decoration: none; text-align: center; padding: 1rem;">
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
            <?php echo $status_counts['all']; ?>
        </div>
        <div style="font-size: 0.9rem; color: #666;">Semua</div>
    </a>
    
    <a href="?status=pending" class="card" style="text-decoration: none; text-align: center; padding: 1rem;">
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--warning-color);">
            <?php echo $status_counts['pending']; ?>
        </div>
        <div style="font-size: 0.9rem; color: #666;">Pending</div>
    </a>
    
    <a href="?status=confirmed" class="card" style="text-decoration: none; text-align: center; padding: 1rem;">
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--info-color);">
            <?php echo $status_counts['confirmed']; ?>
        </div>
        <div style="font-size: 0.9rem; color: #666;">Dikonfirmasi</div>
    </a>
    
    <a href="?status=processing" class="card" style="text-decoration: none; text-align: center; padding: 1rem;">
        <div style="font-size: 1.5rem; font-weight: bold; color: #17a2b8;">
            <?php echo $status_counts['processing']; ?>
        </div>
        <div style="font-size: 0.9rem; color: #666;">Diproses</div>
    </a>
    
    <a href="?status=completed" class="card" style="text-decoration: none; text-align: center; padding: 1rem;">
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--success-color);">
            <?php echo $status_counts['completed']; ?>
        </div>
        <div style="font-size: 0.9rem; color: #666;">Selesai</div>
    </a>
    
    <a href="?status=cancelled" class="card" style="text-decoration: none; text-align: center; padding: 1rem;">
        <div style="font-size: 1.5rem; font-weight: bold; color: var(--danger-color);">
            <?php echo $status_counts['cancelled']; ?>
        </div>
        <div style="font-size: 0.9rem; color: #666;">Dibatalkan</div>
    </a>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group">
                <label class="form-label" for="status">Filter Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $filter_status == 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                    <option value="processing" <?php echo $filter_status == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                    <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="date">Filter Tanggal</label>
                <input type="date" id="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="search">Cari (Kode/Nama/Telepon)</label>
                <input type="text" id="search" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="orders.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="color: white; margin: 0;">Daftar Pesanan</h3>
        <span style="color: white; font-size: 0.9rem;">
            Menampilkan: <?php echo mysqli_num_rows($orders_result); ?> pesanan
        </span>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($orders_result) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Pelanggan</th>
                            <th>Layanan</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Pembayaran</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_code']); ?></strong>
                                    <?php if ($order['discount'] > 0): ?>
                                        <br><small style="color: var(--success-color);">✅ Diskon</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($order['user_name']); ?></div>
                                    <small style="color: #666;"><?php echo $order['user_phone']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <?php 
                                    $status_colors = [
                                        'pending' => 'badge-warning',
                                        'confirmed' => 'badge-info',
                                        'processing' => 'badge-primary',
                                        'completed' => 'badge-success',
                                        'cancelled' => 'badge-danger'
                                    ];
                                    $status_text = [
                                        'pending' => 'Pending',
                                        'confirmed' => 'Dikonfirmasi',
                                        'processing' => 'Diproses',
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $status_colors[$order['status']]; ?>">
                                        <?php echo $status_text[$order['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $payment_status_colors = [
                                        'pending' => 'badge-warning',
                                        'paid' => 'badge-success',
                                        'failed' => 'badge-danger'
                                    ];
                                    $payment_status_text = [
                                        'pending' => 'Belum Bayar',
                                        'paid' => 'Lunas',
                                        'failed' => 'Gagal'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $payment_status_colors[$order['payment_status']]; ?>">
                                        <?php echo $payment_status_text[$order['payment_status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div>Rp <?php echo number_format($order['final_price'], 0, ',', '.'); ?></div>
                                    <?php if ($order['discount'] > 0): ?>
                                        <small style="color: var(--success-color);">
                                            -Rp <?php echo number_format($order['discount'], 0, ',', '.'); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <a href="?view=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary">Detail</a>
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <a href="?edit=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Konfirmasi</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 2rem;">Tidak ada pesanan yang ditemukan</p>
        <?php endif; ?>
    </div>
</div>

<!-- Order Detail Modal -->
<?php if ($view_order): ?>
<div id="orderDetailModal" class="modal active">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header">
            <h3>Detail Pesanan #<?php echo htmlspecialchars($view_order['order_code']); ?></h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Order Information -->
                <div>
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Informasi Pesanan</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="font-weight: 500; color: #666;">Kode Pesanan</label>
                            <div><?php echo htmlspecialchars($view_order['order_code']); ?></div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Tanggal Pesan</label>
                            <div><?php echo date('d/m/Y H:i', strtotime($view_order['created_at'])); ?></div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Tanggal Layanan</label>
                            <div><?php echo date('d/m/Y', strtotime($view_order['order_date'])); ?> <?php echo substr($view_order['order_time'], 0, 5); ?></div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Durasi</label>
                            <div><?php echo $view_order['duration_hours']; ?> jam</div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Status</label>
                            <div>
                                <span class="badge <?php echo $status_colors[$view_order['status']]; ?>">
                                    <?php echo $status_text[$view_order['status']]; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Status Pembayaran</label>
                            <div>
                                <span class="badge <?php echo $payment_status_colors[$view_order['payment_status']]; ?>">
                                    <?php echo $payment_status_text[$view_order['payment_status']]; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Informasi Pelanggan</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="font-weight: 500; color: #666;">Nama</label>
                            <div><?php echo htmlspecialchars($view_order['user_name']); ?></div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Email</label>
                            <div><?php echo htmlspecialchars($view_order['user_email']); ?></div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Telepon</label>
                            <div><?php echo htmlspecialchars($view_order['user_phone']); ?></div>
                        </div>
                        
                        <div>
                            <label style="font-weight: 500; color: #666;">Alamat</label>
                            <div><?php echo nl2br(htmlspecialchars($view_order['address'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($view_order['notes']): ?>
                        <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Catatan</h4>
                        <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px;">
                            <?php echo nl2br(htmlspecialchars($view_order['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Price and Actions -->
                <div>
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Ringkasan Harga</h4>
                    
                    <div style="background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Layanan:</span>
                            <span>Rp <?php echo number_format($view_order['service_price'], 0, ',', '.'); ?></span>
                        </div>
                        
                        <?php if ($view_order['discount'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--success-color);">
                                <span>Diskon:</span>
                                <span>-Rp <?php echo number_format($view_order['discount'], 0, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="border-top: 1px solid #ddd; padding-top: 0.5rem; margin-top: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; font-weight: bold;">
                                <span>Total:</span>
                                <span style="color: var(--primary-color);">
                                    Rp <?php echo number_format($view_order['final_price'], 0, ',', '.'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Update Status Form -->
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Update Status</h4>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="order_id" value="<?php echo $view_order['id']; ?>">
                        <input type="hidden" name="update_status" value="1">
                        
                        <div class="form-group">
                            <select name="status" class="form-control" required>
                                <option value="pending" <?php echo $view_order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $view_order['status'] == 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                <option value="processing" <?php echo $view_order['status'] == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="completed" <?php echo $view_order['status'] == 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="cancelled" <?php echo $view_order['status'] == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <textarea name="notes" class="form-control" rows="3" placeholder="Catatan untuk perubahan status..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Update Status</button>
                    </form>
                    
                    <!-- Order History -->
                    <?php if (!empty($order_history)): ?>
                        <h4 style="color: var(--primary-color); margin-top: 1.5rem; margin-bottom: 1rem;">Riwayat Status</h4>
                        
                        <div style="background-color: white; border: 1px solid #eee; border-radius: 8px; padding: 1rem; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($order_history as $history): ?>
                                <div style="margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f0f0f0;">
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($history['status']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($history['notes']); ?></div>
                                    <div style="font-size: 0.7rem; color: #999;">
                                        <?php echo date('d/m/Y H:i', strtotime($history['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closeModal() {
    window.location.href = '/clean-tech/admin/orders.php';
}

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Auto-close modal after status update if success message
<?php if ($message && $message_type == 'success' && $view_order): ?>
    setTimeout(() => {
        closeModal();
    }, 3000);
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>