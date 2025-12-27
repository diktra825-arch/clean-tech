<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login
if (!is_logged_in() || get_user_role() != 'user') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Pemesanan Layanan';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Ambil data user
$query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Ambil layanan jika ada service_id di URL
$service_id = isset($_GET['service_id']) ? clean_input($_GET['service_id']) : 0;
$service = null;
if ($service_id) {
    $query = "SELECT * FROM services WHERE id = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $service = mysqli_fetch_assoc($result);
}

// Ambil semua layanan aktif untuk dropdown
$query = "SELECT * FROM services WHERE is_active = 1 ORDER BY name";
$services_result = mysqli_query($conn, $query);

// Hitung jumlah pesanan untuk diskon
$query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order_count = mysqli_fetch_assoc($result)['order_count'];
$eligible_for_discount = ($order_count >= 5);
$discount_percentage = $eligible_for_discount ? 30 : 0;

// Proses form pemesanan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $service_id = clean_input($_POST['service_id']);
    $order_date = clean_input($_POST['order_date']);
    $order_time = clean_input($_POST['order_time']);
    $address = clean_input($_POST['address']);
    $notes = clean_input($_POST['notes']);
    $payment_method = clean_input($_POST['payment_method']);
    
    // Validasi
    $errors = [];
    
    if (empty($service_id)) $errors[] = 'Pilih layanan';
    if (empty($order_date)) $errors[] = 'Tanggal harus diisi';
    if (empty($order_time)) $errors[] = 'Waktu harus diisi';
    if (empty($address)) $errors[] = 'Alamat harus diisi';
    if (empty($payment_method)) $errors[] = 'Pilih metode pembayaran';
    
    // Validasi tanggal tidak boleh hari ini atau sebelumnya
    $today = date('Y-m-d');
    if ($order_date <= $today) {
        $errors[] = 'Tanggal harus besok atau setelahnya';
    }
    
    // Validasi waktu (09:00 - 17:00)
    $order_hour = (int) substr($order_time, 0, 2);
    if ($order_hour < 9 || $order_hour > 17) {
        $errors[] = 'Waktu harus antara 09:00 - 17:00';
    }
    
    if (empty($errors)) {
        // Ambil harga layanan
        $query = "SELECT price FROM services WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $service_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $service_data = mysqli_fetch_assoc($result);
        $base_price = $service_data['price'];
        
        // Hitung diskon
        $discount = 0;
        if ($eligible_for_discount) {
            $discount = $base_price * ($discount_percentage / 100);
        }
        
        $total_price = $base_price;
        $final_price = $total_price - $discount;
        
        // Generate order code
        $order_code = 'CT-' . date('ymd') . '-' . strtoupper(uniqid());
        
        // Insert order
        $query = "INSERT INTO orders (order_code, user_id, service_id, order_date, order_time, address, total_price, discount, final_price, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "siisssddsss", $order_code, $user_id, $service_id, $order_date, $order_time, $address, $total_price, $discount, $final_price, $payment_method, $notes);
        
        if (mysqli_stmt_execute($stmt)) {
            $order_id = mysqli_insert_id($conn);
            
            // Add to order history
            $query = "INSERT INTO order_history (order_id, status, notes) VALUES (?, 'pending', 'Pesanan dibuat')";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            
            // Send notification to admin
            $query = "INSERT INTO notifications (user_id, title, message) VALUES (NULL, 'Pesanan Baru', 'Pesanan baru dengan kode $order_code telah dibuat')";
            mysqli_query($conn, $query);
            
            // Send notification to user about payment
            $notification_title = "Pesanan Berhasil Dibuat";
            $notification_message = "Pesanan #$order_code berhasil dibuat. Silakan lakukan pembayaran sesuai instruksi di halaman Pembayaran.";
            
            $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $notification_title, $notification_message);
            mysqli_stmt_execute($stmt);
            
            $success = "Pesanan berhasil dibuat! Kode pesanan: <strong>$order_code</strong><br>
                       <small style='color: #666;'>Silakan cek halaman <a href='/clean-tech/user/payments.php' style='color: var(--primary-color);'>Pembayaran</a> untuk instruksi pembayaran.</small>";
            $_POST = []; // Clear form
            
            // Redirect after 5 seconds
            header('Refresh: 5; URL=/clean-tech/user/payments.php');
        } else {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

include '../includes/header.php';
?>

<div style="max-width: 800px; margin: 2rem auto;">
    <h1 style="color: var(--primary-color); margin-bottom: 1rem;">Pemesanan Layanan</h1>
    
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
    
    <!-- Payment Info Box -->
    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
        <strong>Informasi Pembayaran:</strong> Setelah membuat pesanan, Anda akan diarahkan ke halaman pembayaran 
        untuk melihat instruksi lengkap. Pembayaran dapat dilakukan via Transfer Bank, E-Wallet, atau Cash.
        <button class="close-alert">&times;</button>
    </div>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Left Column -->
                    <div>
                        <div class="form-group">
                            <label class="form-label" for="service_id">Pilih Layanan *</label>
                            <select id="service_id" name="service_id" class="form-control" required onchange="updatePrice()">
                                <option value="">-- Pilih Layanan --</option>
                                <?php while ($s = mysqli_fetch_assoc($services_result)): ?>
                                    <option value="<?php echo $s['id']; ?>" 
                                        <?php echo ($service && $service['id'] == $s['id']) ? 'selected' : ''; ?>
                                        data-price="<?php echo $s['price']; ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> - Rp <?php echo number_format($s['price'], 0, ',', '.'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="order_date">Tanggal Pemesanan *</label>
                            <input type="date" id="order_date" name="order_date" class="form-control" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                   value="<?php echo isset($_POST['order_date']) ? $_POST['order_date'] : ''; ?>" required>
                            <small style="color: #666;">Minimal besok hari</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="order_time">Waktu *</label>
                            <select id="order_time" name="order_time" class="form-control" required>
                                <option value="">-- Pilih Waktu --</option>
                                <?php for ($hour = 9; $hour <= 17; $hour++): ?>
                                    <option value="<?php echo sprintf('%02d:00', $hour); ?>"
                                        <?php echo (isset($_POST['order_time']) && $_POST['order_time'] == sprintf('%02d:00', $hour)) ? 'selected' : ''; ?>>
                                        <?php echo sprintf('%02d:00', $hour); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <small style="color: #666;">09:00 - 17:00</small>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <div class="form-group">
                            <label class="form-label" for="payment_method">Metode Pembayaran *</label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="">-- Pilih Pembayaran --</option>
                                <option value="transfer_bank" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'transfer_bank') ? 'selected' : ''; ?>>Transfer Bank</option>
                                <option value="e_wallet" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'e_wallet') ? 'selected' : ''; ?>>E-Wallet</option>
                                <option value="cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                            </select>
                            <small style="color: #666; display: block; margin-top: 0.25rem;">
                                Pilih metode yang paling nyaman untuk Anda
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="address">Alamat Lengkap *</label>
                            <textarea id="address" name="address" class="form-control" rows="3" required><?php echo isset($_POST['address']) ? $_POST['address'] : ($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="notes">Catatan Tambahan</label>
                            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Contoh: Khusus bersihkan kamar mandi, Ada hewan peliharaan, dll..."><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Price Summary -->
                <div id="priceSummary" style="background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; display: none;">
                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">Ringkasan Harga</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div>Harga Layanan:</div>
                        <div id="basePrice" style="text-align: right;">Rp 0</div>
                        
                        <div>Diskon (<?php echo $discount_percentage; ?>%):</div>
                        <div id="discountAmount" style="text-align: right; color: var(--success-color);">
                            -Rp 0
                        </div>
                        
                        <div style="border-top: 1px solid #ddd; padding-top: 0.5rem; font-weight: bold;">Total:</div>
                        <div id="finalPrice" style="border-top: 1px solid #ddd; padding-top: 0.5rem; text-align: right; font-weight: bold; color: var(--primary-color);">
                            Rp 0
                        </div>
                    </div>
                    
                    <?php if ($eligible_for_discount): ?>
                        <div style="margin-top: 1rem; padding: 0.5rem; background-color: #d4edda; border-radius: 4px;">
                            ✅ Anda berhak mendapatkan diskon <?php echo $discount_percentage; ?>%
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 1rem; padding: 0.5rem; background-color: #fff3cd; border-radius: 4px;">
                            ℹ️ Butuh <?php echo 5 - $order_count; ?> pesanan lagi untuk diskon 30%
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 1rem; padding: 0.5rem; background-color: #e3f2fd; border-radius: 4px; font-size: 0.9rem;">
                        <strong>Catatan:</strong> Setelah pesanan dibuat, silakan lakukan pembayaran sesuai instruksi di halaman Pembayaran.
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <a href="/clean-tech/user/services.php" class="btn btn-secondary">Kembali</a>
                        <a href="/clean-tech/user/payments.php" class="btn btn-secondary" style="margin-left: 0.5rem;">
                            Lihat Instruksi Pembayaran
                        </a>
                    </div>
                    <button type="submit" class="btn btn-primary">Buat Pesanan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updatePrice() {
    const serviceSelect = document.getElementById('service_id');
    const priceSummary = document.getElementById('priceSummary');
    const basePriceElem = document.getElementById('basePrice');
    const discountAmountElem = document.getElementById('discountAmount');
    const finalPriceElem = document.getElementById('finalPrice');
    
    if (serviceSelect.value) {
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        const basePrice = parseFloat(selectedOption.getAttribute('data-price'));
        const discountPercentage = <?php echo $discount_percentage; ?>;
        const discountAmount = basePrice * (discountPercentage / 100);
        const finalPrice = basePrice - discountAmount;
        
        // Format to Indonesian Rupiah
        const formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        });
        
        basePriceElem.textContent = formatter.format(basePrice);
        discountAmountElem.textContent = '- ' + formatter.format(discountAmount);
        finalPriceElem.textContent = formatter.format(finalPrice);
        
        priceSummary.style.display = 'block';
    } else {
        priceSummary.style.display = 'none';
    }
}

// Initialize price if service is pre-selected
window.onload = function() {
    <?php if ($service): ?>
        updatePrice();
    <?php endif; ?>
    
    // Set minimum date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const minDate = tomorrow.toISOString().split('T')[0];
    document.getElementById('order_date').min = minDate;
    
    // Set maximum date to 30 days from now
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 30);
    document.getElementById('order_date').max = maxDate.toISOString().split('T')[0];
};
</script>

<?php include '../includes/footer.php'; ?>