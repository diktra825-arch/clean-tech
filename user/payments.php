<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login
if (!is_logged_in() || get_user_role() != 'user') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Pembayaran';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Proses upload bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_proof'])) {
    $order_id = clean_input($_POST['order_id']);
    $payment_method = clean_input($_POST['payment_method']);
    $payment_notes = clean_input($_POST['payment_notes']);
    
    // Validasi
    $errors = [];
    
    if (empty($order_id)) $errors[] = 'Order ID tidak valid';
    if (empty($payment_method)) $errors[] = 'Pilih metode pembayaran';
    
    // Cek apakah order milik user
    $check_query = "SELECT id, order_code, final_price FROM orders WHERE id = ? AND user_id = ? AND payment_status = 'pending'";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    
    if (!$order) {
        $errors[] = 'Pesanan tidak ditemukan atau sudah diproses';
    }
    
    // Cek upload file
    if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] == UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Bukti pembayaran harus diupload';
    } else {
        $file = $_FILES['payment_proof'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau PDF';
        }
        
        if ($file['size'] > $max_size) {
            $errors[] = 'Ukuran file maksimal 5MB';
        }
    }
    
    if (empty($errors)) {
        // Upload dan kompres gambar
        $upload_dir = '../assets/payments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = 'proof_' . $order['order_code'] . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Kompres gambar jika format image
            if (in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
                compressImage($file_path, $file_path, 75);
            }
            
            // Update database
            $update_query = "UPDATE orders SET 
                            payment_proof = ?, 
                            payment_method = ?, 
                            payment_notes = ?, 
                            payment_date = NOW(),
                            payment_status = 'pending' 
                            WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sssi", $file_name, $payment_method, $payment_notes, $order_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Add to order history
                $history_query = "INSERT INTO order_history (order_id, status, notes) VALUES (?, 'payment_uploaded', 'Bukti pembayaran diupload')";
                $stmt = mysqli_prepare($conn, $history_query);
                mysqli_stmt_bind_param($stmt, "i", $order_id);
                mysqli_stmt_execute($stmt);
                
                // Send notification to admin
                $notification_title = "Bukti Pembayaran Baru";
                $notification_message = "Bukti pembayaran untuk pesanan #" . $order['order_code'] . " telah diupload";
                
                $notif_query = "INSERT INTO notifications (user_id, title, message) VALUES (NULL, ?, ?)";
                $stmt = mysqli_prepare($conn, $notif_query);
                mysqli_stmt_bind_param($stmt, "ss", $notification_title, $notification_message);
                mysqli_stmt_execute($stmt);
                
                $success = "Bukti pembayaran berhasil diupload. Status akan diperiksa oleh admin dalam 1x24 jam.";
            } else {
                $error = 'Gagal menyimpan data pembayaran';
            }
        } else {
            $error = 'Gagal mengupload file';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Ambil semua pesanan user dengan detail pembayaran
$query = "SELECT o.*, s.name as service_name 
          FROM orders o 
          JOIN services s ON o.service_id = s.id 
          WHERE o.user_id = ? 
          ORDER BY 
            CASE WHEN o.payment_status = 'pending' AND o.payment_proof IS NOT NULL THEN 1
                 WHEN o.payment_status = 'pending' THEN 2
                 ELSE 3
            END,
            o.created_at DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);

// Hitung statistik pembayaran
$payment_stats = [
    'pending' => 0,
    'paid' => 0,
    'failed' => 0,
    'total' => 0
];

$stats_query = "SELECT 
                  payment_status,
                  COUNT(*) as count
                FROM orders 
                WHERE user_id = ?
                GROUP BY payment_status";
$stmt = mysqli_prepare($conn, $stats_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($stats_result)) {
    $payment_stats[$row['payment_status']] = $row['count'];
    $payment_stats['total'] += $row['count'];
}

// Get unpaid orders
$unpaid_query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND payment_status = 'pending' AND status != 'cancelled'";
$stmt = mysqli_prepare($conn, $unpaid_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$unpaid_result = mysqli_stmt_get_result($stmt);
$unpaid_count = mysqli_fetch_assoc($unpaid_result)['count'];

// Fungsi kompresi gambar
function compressImage($source, $destination, $quality) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg' || $info['mime'] == 'image/jpg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        
        // Preserve transparency untuk PNG
        imagealphablending($image, false);
        imagesavealpha($image, true);
        
        // Quality untuk PNG (0-9, 0 = no compression)
        $png_quality = 9 - ($quality / 100 * 9);
        imagepng($image, $destination, $png_quality);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
        imagegif($image, $destination);
    }
    
    if (isset($image)) {
        imagedestroy($image);
    }
}

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Pembayaran</h1>
    <p style="color: #666;">Upload bukti pembayaran dan pantau statusnya</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <?php echo $error; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo $success; ?>
        <button class="close-alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Payment Alert -->
<?php if ($unpaid_count > 0): ?>
<div class="alert alert-warning" style="margin-bottom: 2rem;">
    ⚠️ Anda memiliki <?php echo $unpaid_count; ?> pesanan yang belum dibayar. 
    Segera upload bukti pembayaran agar pesanan dapat diproses.
    <button class="close-alert">&times;</button>
</div>
<?php endif; ?>

<!-- Payment Stats -->
<div class="grid grid-4" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['total']; ?></div>
        <div class="stat-label">Total Pesanan</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['pending']; ?></div>
        <div class="stat-label">Menunggu</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['paid']; ?></div>
        <div class="stat-label">Lunas</div>
    </div>
    
    <div class="stats-card">
        <div class="stat-number"><?php echo $payment_stats['failed']; ?></div>
        <div class="stat-label">Gagal</div>
    </div>
</div>

<!-- Payment Instructions -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 style="color: white; margin: 0;">Instruksi Pembayaran</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="padding: 1rem; background-color: #e8f5e8; border-radius: 8px;">
                <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Transfer Bank</h4>
                <div style="font-size: 0.9rem; color: #666;">
                    <p><strong>BCA:</strong><br>
                    <span style="font-family: monospace;">123-456-7890</span><br>
                    <strong>a.n:</strong> CleanTech Indonesia</p>
                    
                    <p><strong>Mandiri:</strong><br>
                    <span style="font-family: monospace;">098-765-4321</span><br>
                    <strong>a.n:</strong> CleanTech Indonesia</p>
                    
                    <p><strong>BNI:</strong><br>
                    <span style="font-family: monospace;">567-890-1234</span><br>
                    <strong>a.n:</strong> CleanTech Indonesia</p>
                </div>
            </div>
            
            <div style="padding: 1rem; background-color: #fff3cd; border-radius: 8px;">
                <h4 style="color: #ff9800; margin-bottom: 0.5rem;">E-Wallet</h4>
                <div style="font-size: 0.9rem; color: #666;">
                    <p><strong>GoPay:</strong><br>
                    <span style="font-family: monospace;">0812-3456-7890</span><br>
                    <strong>a.n:</strong> CleanTech</p>
                    
                    <p><strong>OVO:</strong><br>
                    <span style="font-family: monospace;">0812-3456-7890</span><br>
                    <strong>a.n:</strong> CleanTech</p>
                    
                    <p><strong>DANA:</strong><br>
                    <span style="font-family: monospace;">0812-3456-7890</span><br>
                    <strong>a.n:</strong> CleanTech</p>
                </div>
            </div>
            
            <div style="padding: 1rem; background-color: #e3f2fd; border-radius: 8px;">
                <h4 style="color: #2196f3; margin-bottom: 0.5rem;">Cash</h4>
                <div style="font-size: 0.9rem; color: #666;">
                    <p>Pembayaran langsung saat tim kami datang.</p>
                    <p><strong>Catatan:</strong> Untuk metode cash, tidak perlu upload bukti pembayaran.</p>
                </div>
            </div>
        </div>
        
        <div style="background-color: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary-color);">
            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">📝 Cara Upload Bukti Pembayaran:</h4>
            <ol style="margin-left: 1.5rem; color: #666;">
                <li>Lakukan transfer sesuai nominal ke salah satu rekening/ewallet di atas</li>
                <li>Masukkan <strong>kode pesanan</strong> di keterangan transfer</li>
                <li>Screenshot/photo bukti transfer yang jelas</li>
                <li>Klik tombol "Upload Bukti" pada pesanan yang sesuai</li>
                <li>Pilih file gambar (max 5MB, format: JPG, PNG, GIF, PDF)</li>
                <li>Status akan berubah setelah admin memverifikasi (1x24 jam)</li>
            </ol>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header">
        <h3 style="color: white; margin: 0;">Riwayat Pembayaran</h3>
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
                                    <?php if ($order['discount'] > 0): ?>
                                        <br><small style="color: var(--success-color);">✅ Diskon diterapkan</small>
                                    <?php endif; ?>
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
                                    <?php if ($order['payment_date']): ?>
                                        <br><small style="font-size: 0.8rem; color: #666;">
                                            <?php echo date('d/m/Y H:i', strtotime($order['payment_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['payment_proof']): ?>
                                        <a href="/clean-tech/assets/payments/<?php echo $order['payment_proof']; ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-secondary">
                                            Lihat Bukti
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['payment_status'] == 'pending' && $order['status'] != 'cancelled' && !$order['payment_proof']): ?>
                                        <button onclick="showUploadForm(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_code']); ?>', <?php echo $order['final_price']; ?>)" 
                                                class="btn btn-sm btn-primary">
                                            Upload Bukti
                                        </button>
                                    <?php elseif ($order['payment_status'] == 'pending' && $order['payment_proof']): ?>
                                        <span style="color: #666; font-size: 0.9rem;">Menunggu verifikasi admin</span>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 0.9rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem;">
                <p style="color: #666; font-size: 1.1rem;">Belum ada riwayat pembayaran</p>
                <a href="/clean-tech/user/order.php" class="btn btn-primary">Buat Pesanan</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Proof Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Upload Bukti Pembayaran</h3>
            <button class="close-modal" onclick="closeUploadModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="upload_proof" value="1">
                <input type="hidden" name="order_id" id="uploadOrderId">
                
                <div style="margin-bottom: 1.5rem;">
                    <h4 id="orderCodeTitle" style="color: var(--primary-color); margin-bottom: 0.5rem;"></h4>
                    <div id="orderAmount" style="font-size: 1.2rem; font-weight: bold; color: var(--primary-color);"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="payment_method">Metode Pembayaran *</label>
                    <select name="payment_method" id="payment_method" class="form-control" required>
                        <option value="">-- Pilih Metode --</option>
                        <option value="bca">BCA</option>
                        <option value="mandiri">Mandiri</option>
                        <option value="bni">BNI</option>
                        <option value="gopay">GoPay</option>
                        <option value="ovo">OVO</option>
                        <option value="dana">DANA</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="payment_proof">Bukti Pembayaran *</label>
                    <div style="border: 2px dashed #ddd; padding: 2rem; text-align: center; border-radius: 8px; margin-bottom: 0.5rem; cursor: pointer;" 
                         onclick="document.getElementById('fileInput').click()"
                         id="fileDropArea">
                        <div id="filePreview" style="display: none; margin-bottom: 1rem;"></div>
                        <div id="filePlaceholder">
                            <div style="font-size: 3rem; color: #ccc;">📎</div>
                            <p style="color: #666; margin: 0.5rem 0;">Klik untuk memilih file atau drag & drop</p>
                            <p style="color: #999; font-size: 0.9rem;">Format: JPG, PNG, GIF, PDF (max 5MB)</p>
                        </div>
                        <input type="file" id="fileInput" name="payment_proof" style="display: none;" 
                               accept="image/jpeg,image/jpg,image/png,image/gif,application/pdf" 
                               onchange="previewFile(this)">
                    </div>
                    <small style="color: #666;">Pastikan gambar jelas menunjukkan nominal dan nomor rekening</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="payment_notes">Catatan Tambahan</label>
                    <textarea name="payment_notes" class="form-control" rows="3" placeholder="Contoh: Transfer via mobile banking, jam transfer, dll..."></textarea>
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary btn-block">Upload Bukti Pembayaran</button>
                    <button type="button" class="btn btn-secondary btn-block" onclick="closeUploadModal()" style="margin-top: 0.5rem;">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showUploadForm(orderId, orderCode, amount) {
    document.getElementById('uploadOrderId').value = orderId;
    document.getElementById('orderCodeTitle').textContent = 'Pesanan #' + orderCode;
    document.getElementById('orderAmount').textContent = 'Total: Rp ' + amount.toLocaleString('id-ID');
    document.getElementById('uploadModal').classList.add('active');
    
    // Reset form
    document.getElementById('uploadForm').reset();
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('filePlaceholder').style.display = 'block';
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
}

function previewFile(input) {
    const fileDropArea = document.getElementById('fileDropArea');
    const filePreview = document.getElementById('filePreview');
    const filePlaceholder = document.getElementById('filePlaceholder');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                filePreview.innerHTML = `
                    <img src="${e.target.result}" 
                         style="max-width: 100%; max-height: 200px; border-radius: 4px;"
                         alt="Preview">
                    <p style="margin-top: 0.5rem; color: #666;">${file.name} (${formatFileSize(file.size)})</p>
                `;
            } else {
                filePreview.innerHTML = `
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; color: #666;">📄</div>
                        <p style="color: #666;">${file.name} (${formatFileSize(file.size)})</p>
                    </div>
                `;
            }
            
            filePreview.style.display = 'block';
            filePlaceholder.style.display = 'none';
            fileDropArea.style.borderColor = 'var(--primary-color)';
        }
        
        reader.readAsDataURL(file);
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Drag and drop functionality
const fileDropArea = document.getElementById('fileDropArea');
const fileInput = document.getElementById('fileInput');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    fileDropArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    fileDropArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    fileDropArea.addEventListener(eventName, unhighlight, false);
});

function highlight() {
    fileDropArea.style.borderColor = 'var(--primary-color)';
    fileDropArea.style.backgroundColor = 'rgba(46, 81, 240, 0.1)';
}

function unhighlight() {
    fileDropArea.style.borderColor = '#ddd';
    fileDropArea.style.backgroundColor = '';
}

fileDropArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        fileInput.files = files;
        previewFile(fileInput);
    }
}

// File size validation before upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('fileInput');
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    if (fileInput.files.length > 0) {
        const fileSize = fileInput.files[0].size;
        if (fileSize > maxSize) {
            e.preventDefault();
            alert('Ukuran file terlalu besar. Maksimal 5MB');
            return false;
        }
    }
});

// Close modal when clicking outside
document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeUploadModal();
    }
});

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUploadModal();
    }
});
</script>

<style>
#fileDropArea:hover {
    border-color: var(--primary-color);
    background-color: rgba(46, 81, 240, 0.05);
}
</style>

<?php include '../includes/footer.php'; ?>