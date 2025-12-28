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
            $order_query = "SELECT user_id, order_code FROM orders WHERE id = ?";
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
                $notification_message = "Pesanan #" . $order_data['order_code'] . " telah " . ($status_texts[$status] ?? $status);
                
                $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "iss", $order_data['user_id'], $notification_title, $notification_message);
                mysqli_stmt_execute($stmt);
            }
            
            $message = 'Status pesanan berhasil diperbarui';
            $message_type = 'success';
            
            // Redirect to clear GET parameters
            header('Location: /clean-tech/admin/orders.php?message=' . urlencode($message) . '&type=' . $message_type);
            exit();
        } else {
            $message = 'Gagal memperbarui status pesanan';
            $message_type = 'error';
        }
    }
}

// Check for message from redirect
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = $_GET['type'] ?? 'success';
}

// Filter parameters
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? clean_input($_GET['date']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Determine if showing modal
$show_modal = false;
$view_order = null;
$order_history = [];

if (isset($_GET['view'])) {
    $show_modal = true;
    $id = clean_input($_GET['view']);
    
    // Get order details
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
        while ($row = mysqli_fetch_assoc($history_result)) {
            $order_history[] = $row;
        }
    }
}

// Build query for orders table
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

// Get total revenue from completed orders
$revenue_query = "SELECT SUM(final_price) as total_revenue FROM orders WHERE status = 'completed' AND payment_status = 'paid'";
$revenue_result = mysqli_query($conn, $revenue_query);
$revenue_data = mysqli_fetch_assoc($revenue_result);
$total_revenue = $revenue_data['total_revenue'] ?? 0;

// Get today's orders
$today_query = "SELECT COUNT(*) as today_orders FROM orders WHERE DATE(created_at) = CURDATE()";
$today_result = mysqli_query($conn, $today_query);
$today_data = mysqli_fetch_assoc($today_result);
$today_orders = $today_data['today_orders'] ?? 0;

// Get pending payments
$pending_payments_query = "SELECT COUNT(*) as pending_payments FROM orders WHERE payment_status = 'pending' AND status != 'cancelled'";
$pending_payments_result = mysqli_query($conn, $pending_payments_query);
$pending_payments_data = mysqli_fetch_assoc($pending_payments_result);
$pending_payments = $pending_payments_data['pending_payments'] ?? 0;

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

<!-- Stats Overview - 3x2 Grid -->
<div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <!-- Row 1 -->
    <a href="?status=" class="stats-card" style="text-decoration: none;">
        <div class="stat-number"><?php echo $status_counts['all']; ?></div>
        <div class="stat-label">Total Pesanan</div>
    </a>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $today_orders; ?></div>
        <div class="stat-label">Pesanan Hari Ini</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
        <div class="stat-label">Total Pendapatan</div>
    </div>
    
    <!-- Row 2 -->
    <a href="?status=pending" class="stats-card" style="text-decoration: none;">
        <div class="stat-number" style="color: var(--warning-color);"><?php echo $status_counts['pending']; ?></div>
        <div class="stat-label">Pending</div>
    </a>
    
    <a href="?status=processing" class="stats-card" style="text-decoration: none;">
        <div class="stat-number" style="color: #17a2b8;"><?php echo $status_counts['processing']; ?></div>
        <div class="stat-label">Diproses</div>
    </a>
    
    <a href="?status=completed" class="stats-card" style="text-decoration: none;">
        <div class="stat-number" style="color: var(--success-color);"><?php echo $status_counts['completed']; ?></div>
        <div class="stat-label">Selesai</div>
    </a>
</div>

<!-- Additional Stats in Cards -->
<div class="grid" style="grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <a href="?status=confirmed" class="card" style="text-decoration: none; padding: 1.5rem; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
            <div style="width: 40px; height: 40px; background-color: var(--info-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo $status_counts['confirmed']; ?>
            </div>
            <div style="text-align: left;">
                <div style="font-weight: bold; color: var(--primary-color);">Dikonfirmasi</div>
                <div style="font-size: 0.9rem; color: #666;">Pesanan yang sudah dikonfirmasi</div>
            </div>
        </div>
    </a>
    
    <a href="?status=cancelled" class="card" style="text-decoration: none; padding: 1.5rem; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
            <div style="width: 40px; height: 40px; background-color: var(--danger-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo $status_counts['cancelled']; ?>
            </div>
            <div style="text-align: left;">
                <div style="font-weight: bold; color: var(--primary-color);">Dibatalkan</div>
                <div style="font-size: 0.9rem; color: #666;">Pesanan yang dibatalkan</div>
            </div>
        </div>
    </a>
</div>

<!-- Payment Status Stats -->
<div style="margin-bottom: 2rem;">
    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Status Pembayaran</h3>
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <?php
        // Get payment stats
        $payment_stats_query = "SELECT payment_status, COUNT(*) as count FROM orders GROUP BY payment_status";
        $payment_stats_result = mysqli_query($conn, $payment_stats_query);
        $payment_stats = [];
        while ($row = mysqli_fetch_assoc($payment_stats_result)) {
            $payment_stats[$row['payment_status']] = $row['count'];
        }
        
        $payment_status_config = [
            'pending' => ['color' => '#ffc107', 'label' => 'Belum Bayar', 'count' => $payment_stats['pending'] ?? 0],
            'paid' => ['color' => '#28a745', 'label' => 'Lunas', 'count' => $payment_stats['paid'] ?? 0],
            'failed' => ['color' => '#dc3545', 'label' => 'Gagal', 'count' => $payment_stats['failed'] ?? 0]
        ];
        
        foreach ($payment_status_config as $status => $config):
        ?>
            <div class="card" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2rem; color: <?php echo $config['color']; ?>; margin-bottom: 0.5rem;">
                    <?php echo $config['count']; ?>
                </div>
                <div style="font-weight: 500; color: var(--text-color); margin-bottom: 0.25rem;">
                    <?php echo $config['label']; ?>
                </div>
                <div style="height: 8px; background-color: #e0e0e0; border-radius: 4px; margin-top: 0.5rem; overflow: hidden;">
                    <?php if ($status_counts['all'] > 0): ?>
                        <div style="width: <?php echo ($config['count'] / $status_counts['all'] * 100); ?>%; height: 100%; background-color: <?php echo $config['color']; ?>;"></div>
                    <?php endif; ?>
                </div>
                <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                    <?php echo $status_counts['all'] > 0 ? number_format(($config['count'] / $status_counts['all'] * 100), 1) : 0; ?>%
                </div>
            </div>
        <?php endforeach; ?>
    </div>
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
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="color: white; font-size: 0.9rem;">
                Menampilkan: <?php echo mysqli_num_rows($orders_result); ?> pesanan
            </span>
            <a href="/clean-tech/admin/reports.php" class="btn btn-sm btn-success">Laporan</a>
        </div>
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
                                    <?php if (!empty($order['is_monthly_subscription'])): ?>
                                        <br><small style="color: var(--info-color);">📅 Langganan</small>
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
                                            <a href="?view=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Konfirmasi</a>
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
<?php if ($show_modal && $view_order): ?>
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
                    
                    <?php if ($view_order['discount_type']): ?>
                        <h4 style="color: var(--primary-color); margin-top: 1.5rem; margin-bottom: 1rem;">Informasi Diskon</h4>
                        <div style="background-color: #e8f5e8; padding: 1rem; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 500;"><?php echo $view_order['discount_type']; ?></div>
                                    <div style="font-size: 0.9rem; color: #666;">Diskon yang diterapkan</div>
                                </div>
                                <div style="font-size: 1.2rem; font-weight: bold; color: var(--success-color);">
                                    -Rp <?php echo number_format($view_order['discount'], 0, ',', '.'); ?>
                                </div>
                            </div>
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
                        
                        <?php if (!empty($view_order['is_monthly_subscription'])): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--info-color);">
                                <span>Langganan:</span>
                                <span><?php echo $view_order['subscription_months'] ?? 1; ?> bulan</span>
                            </div>
                        <?php endif; ?>
                        
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
                    
                    <!-- Quick Actions -->
                    <div style="margin-top: 1.5rem;">
                        <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Aksi Cepat</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php if ($view_order['payment_status'] == 'pending' && $view_order['payment_proof']): ?>
                                <a href="/clean-tech/admin/payments.php?verify=<?php echo $view_order['id']; ?>" class="btn btn-success">Verifikasi Pembayaran</a>
                            <?php endif; ?>
                            <a href="/clean-tech/admin/payments.php?view=<?php echo $view_order['id']; ?>" class="btn btn-secondary">Detail Pembayaran</a>
                        </div>
                    </div>
                    
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

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal();
    }
});

// Auto-close modal after status update if success message
<?php if ($message && $message_type == 'success' && $show_modal): ?>
    setTimeout(() => {
        closeModal();
    }, 3000);
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>