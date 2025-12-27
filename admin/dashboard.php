<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Dashboard Admin';
$user_id = $_SESSION['user_id'];

// Hitung statistik
$stats = [];

// Total orders
$query = "SELECT COUNT(*) as total FROM orders";
$result = mysqli_query($conn, $query);
$stats['total_orders'] = mysqli_fetch_assoc($result)['total'];

// Total users
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
$result = mysqli_query($conn, $query);
$stats['total_users'] = mysqli_fetch_assoc($result)['total'];

// Total services
$query = "SELECT COUNT(*) as total FROM services";
$result = mysqli_query($conn, $query);
$stats['total_services'] = mysqli_fetch_assoc($result)['total'];

// Total revenue (paid orders only)
$query = "SELECT SUM(final_price) as total FROM orders WHERE payment_status = 'paid'";
$result = mysqli_query($conn, $query);
$total_revenue = mysqli_fetch_assoc($result)['total'];
$stats['total_revenue'] = $total_revenue ? number_format($total_revenue, 0, ',', '.') : 0;

// Orders by status
$query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$result = mysqli_query($conn, $query);
$orders_by_status = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders_by_status[$row['status']] = $row['count'];
}

// Recent orders
$query = "SELECT o.*, u.name as user_name, s.name as service_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          JOIN services s ON o.service_id = s.id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$recent_orders = mysqli_query($conn, $query);

// Monthly revenue
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(final_price) as revenue,
            COUNT(*) as orders
          FROM orders 
          WHERE payment_status = 'paid'
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month DESC 
          LIMIT 6";
$monthly_revenue = mysqli_query($conn, $query);
$revenue_data = [];
while ($row = mysqli_fetch_assoc($monthly_revenue)) {
    $revenue_data[] = $row;
}

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Dashboard Admin</h1>
    <p style="color: #666;">Selamat datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
</div>

<!-- Stats Cards -->
<div class="grid grid-4" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
        <div class="stat-label">Total Pesanan</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Total Pengguna</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $stats['total_services']; ?></div>
        <div class="stat-label">Total Layanan</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number">Rp <?php echo $stats['total_revenue']; ?></div>
        <div class="stat-label">Total Pendapatan</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Orders by Status -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: white; margin: 0;">Pesanan berdasarkan Status</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <?php
                $status_config = [
                    'pending' => ['color' => '#ffc107', 'label' => 'Pending'],
                    'confirmed' => ['color' => '#17a2b8', 'label' => 'Dikonfirmasi'],
                    'processing' => ['color' => '#007bff', 'label' => 'Diproses'],
                    'completed' => ['color' => '#28a745', 'label' => 'Selesai'],
                    'cancelled' => ['color' => '#dc3545', 'label' => 'Dibatalkan']
                ];
                
                foreach ($status_config as $status => $config):
                    $count = $orders_by_status[$status] ?? 0;
                    $percentage = $stats['total_orders'] > 0 ? ($count / $stats['total_orders'] * 100) : 0;
                ?>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: <?php echo $config['color']; ?>;">
                            <?php echo $count; ?>
                        </div>
                        <div style="font-size: 0.9rem; color: #666;"><?php echo $config['label']; ?></div>
                        <div style="height: 8px; background-color: #e0e0e0; border-radius: 4px; margin-top: 0.5rem; overflow: hidden;">
                            <div style="width: <?php echo $percentage; ?>%; height: 100%; background-color: <?php echo $config['color']; ?>;"></div>
                        </div>
                        <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
                    <a href="/clean-tech/admin/orders.php" class="btn btn-primary">Kelola Pesanan</a>
                    <a href="/clean-tech/admin/services.php" class="btn btn-secondary">Kelola Layanan</a>
                    <a href="/clean-tech/admin/users.php" class="btn btn-secondary">Kelola Pengguna</a>
                    <a href="/clean-tech/admin/reports.php" class="btn btn-success">Lihat Laporan</a>
                    <a href="/clean-tech/logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="color: white; margin: 0;">Pesanan Terbaru</h3>
        <a href="/clean-tech/admin/orders.php" class="btn btn-sm btn-secondary">Lihat Semua</a>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($recent_orders) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Pelanggan</th>
                            <th>Layanan</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($recent_orders)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_code']); ?></td>
                                <td><?php echo htmlspecialchars($order['user_name']); ?></td>
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
                                <td>
                                    <a href="/clean-tech/admin/orders.php?action=view&id=<?php echo $order['id']; ?>" 
                                       class="btn btn-sm btn-secondary">Kelola</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 2rem;">Belum ada pesanan</p>
        <?php endif; ?>
    </div>
</div>

<!-- Revenue Chart -->
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 style="color: white; margin: 0;">Pendapatan 6 Bulan Terakhir</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($revenue_data)): ?>
            <div style="height: 300px; display: flex; align-items: flex-end; gap: 20px; padding: 1rem; border-bottom: 1px solid #eee;">
                <?php 
                $max_revenue = max(array_column($revenue_data, 'revenue'));
                foreach ($revenue_data as $data):
                    $height = $max_revenue > 0 ? ($data['revenue'] / $max_revenue * 100) : 0;
                    $month_name = date('M Y', strtotime($data['month'] . '-01'));
                ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                        <div style="width: 80%; display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 40px; height: <?php echo $height; ?>%; background-color: var(--primary-color); border-radius: 4px 4px 0 0;"></div>
                            <div style="margin-top: 0.5rem; text-align: center;">
                                <div style="font-weight: bold; color: var(--primary-color);">
                                    Rp <?php echo number_format($data['revenue'], 0, ',', '.'); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #666;"><?php echo $month_name; ?></div>
                                <div style="font-size: 0.7rem; color: #999;"><?php echo $data['orders']; ?> pesanan</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 2rem;">Belum ada data pendapatan</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>