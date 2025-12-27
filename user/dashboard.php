<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role
if (!is_logged_in() || get_user_role() != 'user') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Dashboard User';
$user_id = $_SESSION['user_id'];

// Hitung statistik
$stats = [];

// Total orders
$query = "SELECT COUNT(*) as total FROM orders WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['total_orders'] = mysqli_fetch_assoc($result)['total'];

// Pending orders
$query = "SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['pending_orders'] = mysqli_fetch_assoc($result)['total'];

// Completed orders
$query = "SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats['completed_orders'] = mysqli_fetch_assoc($result)['total'];

// Total spent
$query = "SELECT SUM(final_price) as total FROM orders WHERE user_id = ? AND payment_status = 'paid'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$total_spent = mysqli_fetch_assoc($result)['total'];
$stats['total_spent'] = $total_spent ? number_format($total_spent, 0, ',', '.') : 0;

// Cek apakah eligible untuk diskon 30%
$query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order_count = mysqli_fetch_assoc($result)['order_count'];
$stats['eligible_for_discount'] = ($order_count >= 5);
$stats['orders_to_discount'] = max(0, 5 - $order_count);

// Recent orders
$query = "SELECT o.*, s.name as service_name FROM orders o 
          JOIN services s ON o.service_id = s.id 
          WHERE o.user_id = ? 
          ORDER BY o.created_at DESC 
          LIMIT 5";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_orders = mysqli_stmt_get_result($stmt);

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 1rem;">Dashboard</h1>
    <p style="color: #666;">Selamat datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-4" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
        <div class="stat-label">Total Pesanan</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
        <div class="stat-label">Pesanan Pending</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['completed_orders']; ?></div>
        <div class="stat-label">Pesanan Selesai</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number">Rp <?php echo $stats['total_spent']; ?></div>
        <div class="stat-label">Total Pengeluaran</div>
    </div>
</div>

<!-- Discount Alert -->
<?php if ($stats['eligible_for_discount']): ?>
<div class="alert alert-success" style="margin-bottom: 2rem;">
    🎉 Selamat! Anda berhak mendapatkan diskon 30% untuk pemesanan berikutnya!
    <button class="close-alert">&times;</button>
</div>
<?php else: ?>
<div class="alert alert-info" style="margin-bottom: 2rem;">
    💡 Anda membutuhkan <?php echo $stats['orders_to_discount']; ?> pesanan lagi untuk mendapatkan diskon 30%
    <button class="close-alert">&times;</button>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: white; margin: 0;">Pesanan Terbaru</h3>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($recent_orders) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Pesanan</th>
                                <th>Layanan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = mysqli_fetch_assoc($recent_orders)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_code']); ?></td>
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
                                    <td>Rp <?php echo number_format($order['final_price'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="/clean-tech/user/history.php" class="btn btn-secondary">Lihat Semua Pesanan</a>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">Belum ada pesanan</p>
                <div style="text-align: center;">
                    <a href="/clean-tech/user/services.php" class="btn btn-primary">Pesan Layanan</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div>
        <div class="card" style="margin-bottom: 1rem;">
            <div class="card-header">
                <h3 style="color: white; margin: 0;">Aksi Cepat</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="/clean-tech/user/order.php" class="btn btn-primary">Pesan Layanan Baru</a>
                    <a href="/clean-tech/user/history.php" class="btn btn-secondary">Riwayat Pesanan</a>
                    <a href="/clean-tech/user/services.php" class="btn btn-secondary">Lihat Layanan</a>
                    <a href="/clean-tech/logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
        
        <!-- Promo Info -->
        <div class="card">
            <div class="card-header">
                <h3 style="color: white; margin: 0;">Promo Aktif</h3>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 1rem; padding: 1rem; background-color: #f0f7ff; border-radius: 8px;">
                    <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Diskon 30%</h4>
                    <p style="font-size: 0.9rem; color: #666;">Setelah 5 kali pemesanan, dapatkan diskon 30% untuk pemesanan ke-6!</p>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                        <div style="flex: 1; height: 8px; background-color: #e0e0e0; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo min(100, ($order_count / 5) * 100); ?>%; height: 100%; background-color: var(--primary-color);"></div>
                        </div>
                        <span style="font-size: 0.8rem; color: #666;"><?php echo $order_count; ?>/5</span>
                    </div>
                </div>
                
                <div style="padding: 1rem; background-color: #fff8e1; border-radius: 8px;">
                    <h4 style="color: #ff9800; margin-bottom: 0.5rem;">Diskon 15%</h4>
                    <p style="font-size: 0.9rem; color: #666;">Untuk pelanggan baru pada pemesanan pertama</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>