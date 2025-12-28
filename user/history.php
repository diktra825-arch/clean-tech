<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login
if (!is_logged_in() || get_user_role() != 'user') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Riwayat Pesanan';
$user_id = $_SESSION['user_id'];

// Filter parameters
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_month = isset($_GET['month']) ? clean_input($_GET['month']) : date('Y-m');
$show_subscription = isset($_GET['subscription']) ? true : false;

// Build query
$query = "SELECT o.*, s.name as service_name, s.duration_hours 
          FROM orders o 
          JOIN services s ON o.service_id = s.id 
          WHERE o.user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter_status) {
    $query .= " AND o.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_month) {
    $query .= " AND DATE_FORMAT(o.created_at, '%Y-%m') = ?";
    $params[] = $filter_month;
    $types .= "s";
}

if ($show_subscription) {
    $query .= " AND o.is_monthly_subscription = 1";
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

$count_query = "SELECT status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY status";
$stmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$count_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($count_result)) {
    $status_counts[$row['status']] = $row['count'];
    $status_counts['all'] += $row['count'];
}

// Get subscription stats
$subscription_query = "SELECT 
    COUNT(*) as total_subscription,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_subscription
    FROM orders 
    WHERE user_id = ? AND is_monthly_subscription = 1";
$stmt = mysqli_prepare($conn, $subscription_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$subscription_result = mysqli_stmt_get_result($stmt);
$subscription_stats = mysqli_fetch_assoc($subscription_result);

// Get discount stats
$discount_query = "SELECT 
    COUNT(*) as total_with_discount,
    SUM(discount) as total_discount_amount
    FROM orders 
    WHERE user_id = ? AND discount > 0";
$stmt = mysqli_prepare($conn, $discount_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$discount_result = mysqli_stmt_get_result($stmt);
$discount_stats = mysqli_fetch_assoc($discount_result);

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Riwayat Pesanan</h1>
    <p style="color: #666;">Lihat semua pesanan yang telah Anda buat</p>
</div>

<!-- Stats Overview -->
<div style="margin-bottom: 2rem;">
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <!-- All Orders -->
        <a href="?status=" class="card" style="text-decoration: none; text-align: center; padding: 1.5rem; border-left: 4px solid var(--primary-color);">
            <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">
                <?php echo $status_counts['all']; ?>
            </div>
            <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">Total Pesanan</div>
        </a>

        <!-- Subscription Orders -->
        <a href="?subscription=1" class="card" style="text-decoration: none; text-align: center; padding: 1.5rem; border-left: 4px solid #ff9800;">
            <div style="font-size: 2rem; font-weight: bold; color: #ff9800;">
                <?php echo $subscription_stats['total_subscription'] ?? 0; ?>
            </div>
            <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">Langganan</div>
            <?php if ($subscription_stats['total_subscription'] > 0): ?>
                <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                    <?php echo $subscription_stats['completed_subscription']; ?> selesai
                </div>
            <?php endif; ?>
        </a>

        <!-- Discount Stats -->
        <div class="card" style="text-align: center; padding: 1.5rem; border-left: 4px solid var(--success-color);">
            <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);">
                <?php echo $discount_stats['total_with_discount'] ?? 0; ?>
            </div>
            <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">Dengan Diskon</div>
            <?php if ($discount_stats['total_discount_amount'] > 0): ?>
                <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                    Hemat: Rp <?php echo number_format($discount_stats['total_discount_amount'], 0, ',', '.'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Status Stats -->
        <div class="card" style="padding: 1.5rem; border-left: 4px solid #17a2b8;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; text-align: center;">
                <div>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--warning-color);">
                        <?php echo $status_counts['pending']; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #666;">Pending</div>
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--info-color);">
                        <?php echo $status_counts['confirmed'] + $status_counts['processing']; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #666;">Diproses</div>
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--success-color);">
                        <?php echo $status_counts['completed']; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #666;">Selesai</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Status Filter -->
<div style="margin-bottom: 2rem;">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <a href="?status="
            class="btn <?php echo $filter_status == '' ? 'btn-primary' : 'btn-secondary'; ?>">
            Semua (<?php echo $status_counts['all']; ?>)
        </a>
        <a href="?status=pending"
            class="btn <?php echo $filter_status == 'pending' ? 'btn-primary' : 'btn-secondary'; ?>"
            style="background-color: <?php echo $filter_status == 'pending' ? '#ffc107' : 'transparent'; ?>; color: <?php echo $filter_status == 'pending' ? '#000' : 'var(--primary-color)'; ?>;">
            Pending (<?php echo $status_counts['pending']; ?>)
        </a>
        <a href="?status=confirmed"
            class="btn <?php echo $filter_status == 'confirmed' ? 'btn-primary' : 'btn-secondary'; ?>"
            style="background-color: <?php echo $filter_status == 'confirmed' ? '#17a2b8' : 'transparent'; ?>; color: <?php echo $filter_status == 'confirmed' ? '#fff' : 'var(--primary-color)'; ?>;">
            Dikonfirmasi (<?php echo $status_counts['confirmed']; ?>)
        </a>
        <a href="?status=processing"
            class="btn <?php echo $filter_status == 'processing' ? 'btn-primary' : 'btn-secondary'; ?>"
            style="background-color: <?php echo $filter_status == 'processing' ? '#007bff' : 'transparent'; ?>; color: <?php echo $filter_status == 'processing' ? '#fff' : 'var(--primary-color)'; ?>;">
            Diproses (<?php echo $status_counts['processing']; ?>)
        </a>
        <a href="?status=completed"
            class="btn <?php echo $filter_status == 'completed' ? 'btn-primary' : 'btn-secondary'; ?>"
            style="background-color: <?php echo $filter_status == 'completed' ? '#28a745' : 'transparent'; ?>; color: <?php echo $filter_status == 'completed' ? '#fff' : 'var(--primary-color)'; ?>;">
            Selesai (<?php echo $status_counts['completed']; ?>)
        </a>
        <a href="?subscription=1"
            class="btn <?php echo $show_subscription ? 'btn-primary' : 'btn-secondary'; ?>"
            style="background-color: <?php echo $show_subscription ? '#ff9800' : 'transparent'; ?>; color: <?php echo $show_subscription ? '#fff' : 'var(--primary-color)'; ?>;">
            Langganan (<?php echo $subscription_stats['total_subscription'] ?? 0; ?>)
        </a>
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
                <label class="form-label" for="month">Filter Bulan</label>
                <input type="month" id="month" name="month" class="form-control" value="<?php echo $filter_month; ?>">
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="history.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="color: white; margin: 0;">Daftar Pesanan</h3>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <?php if ($show_subscription): ?>
                <span style="color: white; font-size: 0.9rem; background-color: #ff9800; padding: 0.25rem 0.5rem; border-radius: 3px;">
                    📅 Hanya Menampilkan Langganan
                </span>
            <?php endif; ?>
            <span style="color: white; font-size: 0.9rem;">
                Total: <?php echo mysqli_num_rows($orders_result); ?> pesanan
            </span>
        </div>
    </div>
    <div class="card-body">
        <?php if (mysqli_num_rows($orders_result) > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Layanan</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
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
                                    <div style="font-weight: bold; color: var(--primary-color);">
                                        <?php echo htmlspecialchars($order['order_code']); ?>
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem;">
                                        <?php if ($order['discount'] > 0): ?>
                                            <span style="background-color: var(--success-color); color: white; padding: 0.1rem 0.3rem; border-radius: 3px; font-size: 0.7rem;">
                                                ✅ Diskon
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($order['is_monthly_subscription']): ?>
                                            <span style="background-color: #ff9800; color: white; padding: 0.1rem 0.3rem; border-radius: 3px; font-size: 0.7rem;">
                                                📅 Langganan
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($order['parent_order_id']): ?>
                                            <span style="background-color: #6f42c1; color: white; padding: 0.1rem 0.3rem; border-radius: 3px; font-size: 0.7rem;">
                                                🔄 Jadwal
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                                <td>
                                    <div><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo date('d/m', strtotime($order['created_at'])); ?>
                                    </div>
                                </td>
                                <td><?php echo substr($order['order_time'], 0, 5); ?></td>
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
                                    <div style="font-weight: bold; color: var(--primary-color);">
                                        Rp <?php echo number_format($order['final_price'], 0, ',', '.'); ?>
                                    </div>
                                    <?php if ($order['discount'] > 0): ?>
                                        <div style="font-size: 0.8rem; color: var(--success-color);">
                                            Hemat: Rp <?php echo number_format($order['discount'], 0, ',', '.'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['is_monthly_subscription'] && $order['subscription_months'] > 1): ?>
                                        <div style="font-size: 0.8rem; color: #ff9800;">
                                            <?php echo $order['subscription_months']; ?> bulan
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <button onclick="viewOrderDetail(<?php echo $order['id']; ?>)"
                                            class="btn btn-sm btn-secondary" style="width: 100%;">
                                            Detail
                                        </button>
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <button onclick="cancelOrder(<?php echo $order['id']; ?>)"
                                                class="btn btn-sm btn-danger" style="width: 100%;">
                                                Batal
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <p style="color: #666; font-size: 1.1rem;">
                    <?php if ($show_subscription): ?>
                        Belum ada pesanan langganan
                    <?php elseif ($filter_status): ?>
                        Tidak ada pesanan dengan status "<?php echo $filter_status; ?>"
                    <?php else: ?>
                        Belum ada pesanan
                    <?php endif; ?>
                </p>
                <a href="/clean-tech/user/order.php" class="btn btn-primary">
                    <?php if ($show_subscription): ?>
                        Pesan Langganan
                    <?php else: ?>
                        Buat Pesanan Pertama
                    <?php endif; ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Detail Modal -->
<div id="orderDetailModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Detail Pesanan</h3>
            <button class="close-modal" onclick="closeOrderModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="orderDetailContent">
                <!-- Content will be loaded by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
    function viewOrderDetail(orderId) {
        fetch(`/clean-tech/api/get_order.php?id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const order = data.order;
                    const history = data.history;

                    let content = `
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Informasi Pesanan</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <div><strong>Kode Pesanan:</strong></div>
                            <div style="font-weight: bold; color: var(--primary-color);">${order.order_code}</div>
                            
                            <div><strong>Layanan:</strong></div>
                            <div>${order.service_name}</div>
                            
                            <div><strong>Tanggal:</strong></div>
                            <div>${formatDate(order.order_date)} ${order.order_time.substring(0,5)}</div>
                            
                            <div><strong>Alamat:</strong></div>
                            <div>${order.address}</div>
                            
                            <div><strong>Status:</strong></div>
                            <div><span class="badge ${getStatusColor(order.status)}">${getStatusText(order.status)}</span></div>
                            
                            <div><strong>Pembayaran:</strong></div>
                            <div><span class="badge ${getPaymentStatusColor(order.payment_status)}">${getPaymentStatusText(order.payment_status)}</span></div>
                            
                            <div><strong>Metode:</strong></div>
                            <div>${getPaymentMethodText(order.payment_method)}</div>
                    `;

                    if (order.is_monthly_subscription) {
                        content += `
                            <div><strong>Tipe:</strong></div>
                            <div><span class="badge" style="background-color: #ff9800; color: white;">Langganan</span></div>
                            
                            <div><strong>Jumlah Bulan:</strong></div>
                            <div>${order.subscription_months || 1} bulan</div>
                        `;
                    }

                    if (order.discount_type) {
                        content += `
                            <div><strong>Diskon:</strong></div>
                            <div style="color: var(--success-color); font-weight: bold;">
                                ${order.discount_type == 'new_customer' ? 'Pelanggan Baru (15%)' : 
                                  order.discount_type == 'monthly' ? 'Langganan (20%)' :
                                  order.discount_type == 'loyalty' ? 'Loyalty (30%)' : order.discount_type}
                            </div>
                        `;
                    }

                    content += `
                            <div><strong>Harga:</strong></div>
                            <div>Rp ${parseInt(order.total_price).toLocaleString('id-ID')}</div>
                            
                            <div><strong>Diskon:</strong></div>
                            <div style="color: var(--success-color);">- Rp ${parseInt(order.discount).toLocaleString('id-ID')}</div>
                            
                            <div><strong>Total Bayar:</strong></div>
                            <div style="font-weight: bold; color: var(--primary-color);">Rp ${parseInt(order.final_price).toLocaleString('id-ID')}</div>
                        </div>
                    </div>
                `;

                    if (order.notes) {
                        content += `
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Catatan</h4>
                            <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px;">
                                <p style="margin: 0;">${order.notes}</p>
                            </div>
                        </div>
                    `;
                    }

                    if (history.length > 0) {
                        content += `
                        <div>
                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Riwayat Status</h4>
                            <div style="position: relative; padding-left: 1.5rem;">
                                ${history.map(item => `
                                    <div style="position: relative; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
                                        <div style="position: absolute; left: -1.5rem; top: 0; width: 12px; height: 12px; background-color: var(--primary-color); border-radius: 50%;"></div>
                                        <div style="font-weight: 500;">${formatStatus(item.status)}</div>
                                        <div style="font-size: 0.9rem; color: #666;">${item.notes}</div>
                                        <div style="font-size: 0.8rem; color: #999;">${formatDateTime(item.created_at)}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                    }

                    document.getElementById('orderDetailContent').innerHTML = content;
                    document.getElementById('orderDetailModal').classList.add('active');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Gagal memuat detail pesanan');
            });
    }

    function cancelOrder(orderId) {
        if (confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
            fetch(`/clean-tech/api/cancel_order.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pesanan berhasil dibatalkan');
                        location.reload();
                    } else {
                        alert(data.message || 'Gagal membatalkan pesanan');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan');
                });
        }
    }

    function closeOrderModal() {
        document.getElementById('orderDetailModal').classList.remove('active');
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatStatus(status) {
        const statusMap = {
            'pending': 'Pending',
            'confirmed': 'Dikonfirmasi',
            'processing': 'Diproses',
            'completed': 'Selesai',
            'cancelled': 'Dibatalkan',
            'payment_uploaded': 'Bukti Pembayaran Diupload',
            'payment_verified': 'Pembayaran Diverifikasi',
            'payment_rejected': 'Pembayaran Ditolak'
        };
        return statusMap[status] || status.replace(/_/g, ' ');
    }

    function getStatusColor(status) {
        const colors = {
            'pending': 'badge-warning',
            'confirmed': 'badge-info',
            'processing': 'badge-primary',
            'completed': 'badge-success',
            'cancelled': 'badge-danger'
        };
        return colors[status] || 'badge-secondary';
    }

    function getStatusText(status) {
        const texts = {
            'pending': 'Pending',
            'confirmed': 'Dikonfirmasi',
            'processing': 'Diproses',
            'completed': 'Selesai',
            'cancelled': 'Dibatalkan'
        };
        return texts[status] || status;
    }

    function getPaymentStatusColor(status) {
        const colors = {
            'pending': 'badge-warning',
            'paid': 'badge-success',
            'failed': 'badge-danger'
        };
        return colors[status] || 'badge-secondary';
    }

    function getPaymentStatusText(status) {
        const texts = {
            'pending': 'Belum Bayar',
            'paid': 'Lunas',
            'failed': 'Gagal'
        };
        return texts[status] || status;
    }

    function getPaymentMethodText(method) {
        const texts = {
            'transfer_bank': 'Transfer Bank',
            'e_wallet': 'E-Wallet',
            'cash': 'Cash',
            'bca': 'BCA',
            'mandiri': 'Mandiri',
            'bni': 'BNI',
            'gopay': 'GoPay',
            'ovo': 'OVO',
            'dana': 'DANA'
        };
        return texts[method] || method;
    }

    // Close modal when clicking outside
    document.getElementById('orderDetailModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOrderModal();
        }
    });

    // Close modal with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeOrderModal();
        }
    });
</script>

<?php include '../includes/footer.php'; ?>