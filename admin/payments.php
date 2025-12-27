<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Manajemen Pembayaran';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Proses verifikasi pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verify_payment'])) {
        $order_id = clean_input($_POST['order_id']);
        $payment_status = clean_input($_POST['payment_status']);
        $verification_notes = clean_input($_POST['verification_notes']);

        // Update payment status
        $query = "UPDATE orders SET payment_status = ?, payment_notes = CONCAT(COALESCE(payment_notes, ''), '\n[VERIFIKASI] ', ?) WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $payment_status, $verification_notes, $order_id);

        if (mysqli_stmt_execute($stmt)) {
            // Add to order history
            $status_text = $payment_status == 'paid' ? 'payment_verified' : 'payment_rejected';
            $history_notes = $payment_status == 'paid' ? 'Pembayaran diverifikasi' : 'Pembayaran ditolak: ' . $verification_notes;

            $query = "INSERT INTO order_history (order_id, status, notes) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iss", $order_id, $status_text, $history_notes);
            mysqli_stmt_execute($stmt);

            // Get order info for notification
            $order_query = "SELECT user_id, order_code FROM orders WHERE id = ?";
            $stmt = mysqli_prepare($conn, $order_query);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $order_result = mysqli_stmt_get_result($stmt);
            $order_data = mysqli_fetch_assoc($order_result);

            if ($order_data) {
                $notification_title = $payment_status == 'paid' ? "Pembayaran Diverifikasi" : "Pembayaran Ditolak";
                $notification_message = $payment_status == 'paid'
                    ? "Pembayaran untuk pesanan #" . $order_data['order_code'] . " telah diverifikasi. Pesanan akan segera diproses."
                    : "Pembayaran untuk pesanan #" . $order_data['order_code'] . " ditolak: " . $verification_notes;

                $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "iss", $order_data['user_id'], $notification_title, $notification_message);
                mysqli_stmt_execute($stmt);
            }

            $message = 'Status pembayaran berhasil diperbarui';
            $message_type = 'success';
            
            // Redirect to clear GET parameters
            header('Location: /clean-tech/admin/payments.php?message=' . urlencode($message) . '&type=' . $message_type);
            exit();
        } else {
            $message = 'Gagal memperbarui status pembayaran';
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
$filter_payment_status = isset($_GET['payment_status']) ? clean_input($_GET['payment_status']) : '';
$filter_date = isset($_GET['date']) ? clean_input($_GET['date']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Determine which modal to show
$modal_type = '';
$view_order = null;

if (isset($_GET['view'])) {
    $modal_type = 'detail';
    $id = clean_input($_GET['view']);
} elseif (isset($_GET['verify'])) {
    $modal_type = 'verification';
    $id = clean_input($_GET['verify']);
} else {
    $modal_type = '';
}

// Get order for view/verification if needed
if ($modal_type) {
    $query = "SELECT o.*, u.name as user_name, u.email as user_email, u.phone as user_phone, 
                     s.name as service_name, s.price as service_price
              FROM orders o 
              JOIN users u ON o.user_id = u.id 
              JOIN services s ON o.service_id = s.id 
              WHERE o.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $view_order = mysqli_fetch_assoc($result);
}

// Build query for main table - TAMPILKAN SEMUA ORDER, TIDAK HANYA YANG ADA BUKTI
$query = "SELECT o.*, u.name as user_name, u.phone as user_phone, s.name as service_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          JOIN services s ON o.service_id = s.id 
          WHERE 1=1";
$params = [];
$types = "";

if ($filter_payment_status) {
    $query .= " AND o.payment_status = ?";
    $params[] = $filter_payment_status;
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

$query .= " ORDER BY 
            CASE 
                WHEN o.payment_status = 'pending' AND o.payment_proof IS NOT NULL THEN 1
                WHEN o.payment_status = 'pending' THEN 2
                ELSE 3
            END,
            o.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);

// Get payment stats
$stats_query = "SELECT 
                  payment_status,
                  COUNT(*) as count,
                  SUM(final_price) as total_amount
                FROM orders 
                GROUP BY payment_status";
$stats_result = mysqli_query($conn, $stats_query);
$payment_stats = [
    'pending' => ['count' => 0, 'amount' => 0],
    'paid' => ['count' => 0, 'amount' => 0],
    'failed' => ['count' => 0, 'amount' => 0]
];

while ($row = mysqli_fetch_assoc($stats_result)) {
    if (isset($payment_stats[$row['payment_status']])) {
        $payment_stats[$row['payment_status']] = [
            'count' => $row['count'],
            'amount' => $row['total_amount'] ?? 0
        ];
    }
}

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Manajemen Pembayaran</h1>
    <p style="color: #666;">Kelola status pembayaran pesanan</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'error' ? 'error' : 'success'; ?>">
        <?php echo $message; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<div class="grid grid-3" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['pending']['count']; ?></div>
        <div class="stat-label">Menunggu Pembayaran</div>
        <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
            Rp <?php echo number_format($payment_stats['pending']['amount'], 0, ',', '.'); ?>
        </div>
    </div>

    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['paid']['count']; ?></div>
        <div class="stat-label">Sudah Dibayar</div>
        <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
            Rp <?php echo number_format($payment_stats['paid']['amount'], 0, ',', '.'); ?>
        </div>
    </div>

    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['failed']['count']; ?></div>
        <div class="stat-label">Gagal Bayar</div>
        <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
            Rp <?php echo number_format($payment_stats['failed']['amount'], 0, ',', '.'); ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group">
                <label class="form-label" for="payment_status">Status Pembayaran</label>
                <select id="payment_status" name="payment_status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="pending" <?php echo $filter_payment_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $filter_payment_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $filter_payment_status == 'failed' ? 'selected' : ''; ?>>Failed</option>
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
                <a href="payments.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="color: white; margin: 0;">Daftar Pembayaran</h3>
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
                            <th>Total</th>
                            <th>Status Pesanan</th>
                            <th>Status Pembayaran</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['order_code']); ?></strong>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($order['user_name']); ?></div>
                                    <small style="color: #666;"><?php echo $order['user_phone']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <strong>Rp <?php echo number_format($order['final_price'], 0, ',', '.'); ?></strong>
                                    <?php if ($order['discount'] > 0): ?>
                                        <br><small style="color: var(--success-color);">
                                            Diskon: Rp <?php echo number_format($order['discount'], 0, ',', '.'); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
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
                                        'pending' => $order['payment_proof'] ? 'badge-info' : 'badge-warning',
                                        'paid' => 'badge-success',
                                        'failed' => 'badge-danger'
                                    ];
                                    $payment_status_text = [
                                        'pending' => $order['payment_proof'] ? 'Menunggu Verifikasi' : 'Belum Bayar',
                                        'paid' => 'Lunas',
                                        'failed' => 'Gagal'
                                    ];
                                    ?>
                                    <span class="badge <?php echo $payment_status_colors[$order['payment_status']]; ?>">
                                        <?php echo $payment_status_text[$order['payment_status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['payment_proof']): ?>
                                        <a href="/clean-tech/assets/payments/<?php echo $order['payment_proof']; ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-secondary">
                                            Lihat Bukti
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.9rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <a href="?view=<?php echo $order['id']; ?>" class="btn btn-sm btn-secondary">Detail</a>
                                        <?php if ($order['payment_status'] == 'pending' && $order['payment_proof']): ?>
                                            <a href="?verify=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Verifikasi</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 2rem;">Tidak ada data pembayaran yang ditemukan</p>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Detail Modal -->
<?php if ($modal_type == 'detail' && $view_order): ?>
<div id="paymentDetailModal" class="modal active">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Detail Pembayaran #<?php echo htmlspecialchars($view_order['order_code']); ?></h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 2rem;">
                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Informasi Pesanan</h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="font-weight: 500; color: #666;">Pelanggan</label>
                        <div><?php echo htmlspecialchars($view_order['user_name']); ?></div>
                        <div style="font-size: 0.9rem; color: #666;"><?php echo $view_order['user_phone']; ?></div>
                    </div>

                    <div>
                        <label style="font-weight: 500; color: #666;">Layanan</label>
                        <div><?php echo htmlspecialchars($view_order['service_name']); ?></div>
                        <div style="font-size: 0.9rem; color: #666;">
                            Rp <?php echo number_format($view_order['service_price'], 0, ',', '.'); ?>
                        </div>
                    </div>

                    <div>
                        <label style="font-weight: 500; color: #666;">Tanggal Pesanan</label>
                        <div><?php echo date('d/m/Y', strtotime($view_order['order_date'])); ?> <?php echo substr($view_order['order_time'], 0, 5); ?></div>
                    </div>

                    <div>
                        <label style="font-weight: 500; color: #666;">Tanggal Upload</label>
                        <div>
                            <?php if ($view_order['payment_date']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($view_order['payment_date'])); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-weight: 500; color: #666;">Status Pesanan</label>
                        <div>
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
                            <span class="badge <?php echo $status_colors[$view_order['status']]; ?>">
                                <?php echo $status_text[$view_order['status']]; ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <label style="font-weight: 500; color: #666;">Status Pembayaran</label>
                        <div>
                            <?php
                            $payment_status_colors = [
                                'pending' => $view_order['payment_proof'] ? 'badge-info' : 'badge-warning',
                                'paid' => 'badge-success',
                                'failed' => 'badge-danger'
                            ];
                            $payment_status_text = [
                                'pending' => $view_order['payment_proof'] ? 'Menunggu Verifikasi' : 'Belum Bayar',
                                'paid' => 'Lunas',
                                'failed' => 'Gagal'
                            ];
                            ?>
                            <span class="badge <?php echo $payment_status_colors[$view_order['payment_status']]; ?>">
                                <?php echo $payment_status_text[$view_order['payment_status']]; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($view_order['payment_proof']): ?>
                <div style="margin-bottom: 1rem;">
                    <h5 style="color: var(--primary-color); margin-bottom: 0.5rem;">Bukti Pembayaran</h5>
                    <?php
                    $file_extension = pathinfo($view_order['payment_proof'], PATHINFO_EXTENSION);
                    $file_path = '/clean-tech/assets/payments/' . $view_order['payment_proof'];
                    ?>
                    <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?php echo $file_path; ?>"
                            style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;"
                            alt="Bukti Pembayaran">
                    <?php else: ?>
                        <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px; text-align: center;">
                            <div style="font-size: 2rem; color: #666;">📄</div>
                            <p>File: <?php echo $view_order['payment_proof']; ?></p>
                            <a href="<?php echo $file_path; ?>" target="_blank" class="btn btn-sm btn-primary">
                                Download File
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                    <h5 style="color: var(--primary-color); margin-bottom: 1rem;">Ringkasan Pembayaran</h5>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Harga Layanan:</span>
                        <span>Rp <?php echo number_format($view_order['service_price'], 0, ',', '.'); ?></span>
                    </div>

                    <?php if ($view_order['discount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--success-color);">
                            <span>Diskon:</span>
                            <span>- Rp <?php echo number_format($view_order['discount'], 0, ',', '.'); ?></span>
                        </div>
                    <?php endif; ?>

                    <div style="border-top: 1px solid #ddd; padding-top: 0.5rem; margin-top: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem;">
                            <span>Total Bayar:</span>
                            <span style="color: var(--primary-color);">
                                Rp <?php echo number_format($view_order['final_price'], 0, ',', '.'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if ($view_order['payment_notes']): ?>
                <div style="margin-top: 1rem; padding: 1rem; background-color: #e3f2fd; border-radius: 8px;">
                    <h5 style="color: var(--primary-color); margin-bottom: 0.5rem;">Catatan</h5>
                    <p style="color: #666;"><?php echo nl2br(htmlspecialchars($view_order['payment_notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div style="text-align: center;">
                <?php if ($view_order['payment_status'] == 'pending' && $view_order['payment_proof']): ?>
                    <a href="?verify=<?php echo $view_order['id']; ?>" class="btn btn-primary">Verifikasi Pembayaran</a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" onclick="closeModal()" style="margin-left: 0.5rem;">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Verification Modal -->
<?php if ($modal_type == 'verification' && $view_order): ?>
<div id="verificationModal" class="modal active">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Verifikasi Pembayaran #<?php echo htmlspecialchars($view_order['order_code']); ?></h3>
            <button class="close-modal" onclick="closeVerificationModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 2rem;">
                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Bukti Pembayaran</h4>

                <div style="text-align: center; margin-bottom: 1rem;">
                    <?php if ($view_order['payment_proof']):
                        $file_extension = pathinfo($view_order['payment_proof'], PATHINFO_EXTENSION);
                        $file_path = '/clean-tech/assets/payments/' . $view_order['payment_proof'];
                    ?>
                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <img src="<?php echo $file_path; ?>"
                                style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid #ddd;"
                                alt="Bukti Pembayaran">
                        <?php else: ?>
                            <div style="background-color: #f8f9fa; padding: 3rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 4rem; color: #666;">📄</div>
                                <p>File: <?php echo $view_order['payment_proof']; ?></p>
                                <a href="<?php echo $file_path; ?>" target="_blank" class="btn btn-primary">
                                    Download File
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #666; text-align: center;">Tidak ada bukti pembayaran</p>
                    <?php endif; ?>
                </div>

                <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <div><strong>Pelanggan:</strong></div>
                        <div><?php echo htmlspecialchars($view_order['user_name']); ?></div>

                        <div><strong>Telepon:</strong></div>
                        <div><?php echo htmlspecialchars($view_order['user_phone']); ?></div>

                        <div><strong>Layanan:</strong></div>
                        <div><?php echo htmlspecialchars($view_order['service_name']); ?></div>

                        <div><strong>Total:</strong></div>
                        <div>Rp <?php echo number_format($view_order['final_price'], 0, ',', '.'); ?></div>

                        <div><strong>Metode:</strong></div>
                        <div>
                            <?php
                            $method_text = [
                                'bca' => 'BCA',
                                'mandiri' => 'Mandiri',
                                'bni' => 'BNI',
                                'gopay' => 'GoPay',
                                'ovo' => 'OVO',
                                'dana' => 'DANA',
                                'cash' => 'Cash',
                                'transfer_bank' => 'Transfer Bank',
                                'e_wallet' => 'E-Wallet'
                            ];
                            echo $method_text[$view_order['payment_method']] ?? $view_order['payment_method'];
                            ?>
                        </div>

                        <div><strong>Tanggal Upload:</strong></div>
                        <div>
                            <?php if ($view_order['payment_date']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($view_order['payment_date'])); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($view_order['payment_notes']): ?>
                        <div style="margin-top: 1rem;">
                            <strong>Catatan User:</strong>
                            <p style="color: #666; margin-top: 0.25rem;"><?php echo nl2br(htmlspecialchars($view_order['payment_notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Verification Form -->
            <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Verifikasi Pembayaran</h4>

            <form method="POST" action="">
                <input type="hidden" name="order_id" value="<?php echo $view_order['id']; ?>">
                <input type="hidden" name="verify_payment" value="1">

                <div class="form-group">
                    <label class="form-label" for="payment_status">Status Verifikasi *</label>
                    <select name="payment_status" class="form-control" required>
                        <option value="paid">✅ Terima (Pembayaran Valid)</option>
                        <option value="failed">❌ Tolak (Pembayaran Tidak Valid)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="verification_notes">Catatan Verifikasi *</label>
                    <textarea name="verification_notes" class="form-control" rows="3" required
                        placeholder="Contoh: Nominal sesuai, bukti transfer valid, dll..."></textarea>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">Simpan Verifikasi</button>
                    <button type="button" class="btn btn-secondary" onclick="closeVerificationModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function closeModal() {
    window.location.href = '/clean-tech/admin/payments.php';
}

function closeVerificationModal() {
    window.location.href = '/clean-tech/admin/payments.php';
}

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (document.getElementById('paymentDetailModal') && document.getElementById('paymentDetailModal').classList.contains('active')) {
            closeModal();
        }
        if (document.getElementById('verificationModal') && document.getElementById('verificationModal').classList.contains('active')) {
            closeVerificationModal();
        }
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        if (e.target.id === 'paymentDetailModal') {
            closeModal();
        }
        if (e.target.id === 'verificationModal') {
            closeVerificationModal();
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>