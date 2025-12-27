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

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Riwayat Pesanan</h1>
    <p style="color: #666;">Lihat semua pesanan yang telah Anda buat</p>
</div>

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
        <form method="GET" action="" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 1rem; align-items: end;">
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
    <div class="card-header">
        <h3 style="color: white; margin: 0;">Daftar Pesanan</h3>
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
                                    <strong><?php echo htmlspecialchars($order['order_code']); ?></strong>
                                    <?php if ($order['discount'] > 0): ?>
                                        <br><small style="color: var(--success-color);">✅ Diskon diterapkan</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
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
                                    <div>Rp <?php echo number_format($order['final_price'], 0, ',', '.'); ?></div>
                                    <?php if ($order['discount'] > 0): ?>
                                        <small style="color: var(--success-color);">
                                            Diskon: Rp <?php echo number_format($order['discount'], 0, ',', '.'); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="viewOrderDetail(<?php echo $order['id']; ?>)"
                                        class="btn btn-sm btn-secondary">Detail</button>
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <button onclick="cancelOrder(<?php echo $order['id']; ?>)"
                                            class="btn btn-sm btn-danger">Batal</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <p style="color: #666; font-size: 1.1rem;">Belum ada pesanan</p>
                <a href="/clean-tech/user/order.php" class="btn btn-primary">Buat Pesanan Pertama</a>
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
                            <div>${order.order_code}</div>
                            
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
                            
                            <div><strong>Harga:</strong></div>
                            <div>Rp ${parseInt(order.total_price).toLocaleString('id-ID')}</div>
                            
                            <div><strong>Diskon:</strong></div>
                            <div>Rp ${parseInt(order.discount).toLocaleString('id-ID')}</div>
                            
                            <div><strong>Total Bayar:</strong></div>
                            <div style="font-weight: bold; color: var(--primary-color);">Rp ${parseInt(order.final_price).toLocaleString('id-ID')}</div>
                        </div>
                    </div>
                `;

                    if (order.notes) {
                        content += `
                        <div style="margin-bottom: 1.5rem;">
                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Catatan</h4>
                            <p>${order.notes}</p>
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
                                        <div style="font-weight: 500;">${item.status}</div>
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
            'cash': 'Cash'
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