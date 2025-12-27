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

// Cek apakah user baru (belum pernah pesan)
$check_new_customer = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $check_new_customer);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$new_customer_result = mysqli_stmt_get_result($stmt);
$new_customer_data = mysqli_fetch_assoc($new_customer_result);
$is_new_customer = ($new_customer_data['order_count'] == 0);

// Hitung jumlah pesanan untuk diskon loyalty
$query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order_count = mysqli_fetch_assoc($result)['order_count'];

// Cek apakah memilih langganan bulanan
$is_monthly_subscription = isset($_POST['is_monthly_subscription']) && $_POST['is_monthly_subscription'] == '1';

// Ambil semua diskon aktif
$query = "SELECT * FROM discounts WHERE is_active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE()) ORDER BY discount_value DESC";
$discounts_result = mysqli_query($conn, $query);
$available_discounts = [];

while ($discount = mysqli_fetch_assoc($discounts_result)) {
    $available_discounts[] = $discount;
}

// Tentukan diskon yang berlaku
$applicable_discounts = [];
$selected_discount = null;
$discount_amount = 0;
$discount_type = '';
$discount_name = '';

// 1. Cek diskon new customer (15%)
if ($is_new_customer) {
    foreach ($available_discounts as $discount) {
        if ($discount['name'] == 'New Customer' && $discount['min_orders'] == 0) {
            $applicable_discounts[] = [
                'id' => $discount['id'],
                'name' => $discount['name'],
                'description' => $discount['description'],
                'value' => $discount['discount_value'],
                'type' => 'new_customer'
            ];
        }
    }
}

// 2. Cek diskon monthly subscription (20%)
if ($is_monthly_subscription) {
    foreach ($available_discounts as $discount) {
        if ($discount['name'] == 'Monthly Subscription') {
            $applicable_discounts[] = [
                'id' => $discount['id'],
                'name' => $discount['name'],
                'description' => $discount['description'],
                'value' => $discount['discount_value'],
                'type' => 'monthly'
            ];
        }
    }
}

// 3. Cek diskon loyalty (30% setelah 5 pesanan)
if ($order_count >= 5) {
    foreach ($available_discounts as $discount) {
        if ($discount['name'] == 'Loyalty Discount' && $order_count >= $discount['min_orders']) {
            $applicable_discounts[] = [
                'id' => $discount['id'],
                'name' => $discount['name'],
                'description' => $discount['description'],
                'value' => $discount['discount_value'],
                'type' => 'loyalty'
            ];
        }
    }
}

// Pilih diskon dengan nilai tertinggi
if (!empty($applicable_discounts)) {
    usort($applicable_discounts, function($a, $b) {
        return $b['value'] <=> $a['value']; // Urutkan dari nilai tertinggi
    });
    
    $selected_discount = $applicable_discounts[0];
    $discount_amount = $selected_discount['value'];
    $discount_type = $selected_discount['type'];
    $discount_name = $selected_discount['name'];
}

// Proses form pemesanan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $service_id = clean_input($_POST['service_id']);
    $order_date = clean_input($_POST['order_date']);
    $order_time = clean_input($_POST['order_time']);
    $address = clean_input($_POST['address']);
    $notes = clean_input($_POST['notes']);
    $payment_method = clean_input($_POST['payment_method']);
    $is_monthly_subscription = isset($_POST['is_monthly_subscription']) ? 1 : 0;
    $subscription_months = $is_monthly_subscription ? clean_input($_POST['subscription_months']) : 1;
    
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
    
    // Validasi subscription months
    if ($is_monthly_subscription && ($subscription_months < 1 || $subscription_months > 12)) {
        $errors[] = 'Pilih jumlah bulan antara 1-12 bulan';
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
        
        // Hitung total harga jika subscription
        if ($is_monthly_subscription) {
            $base_price = $base_price * $subscription_months;
        }
        
        // Hitung diskon
        $discount = 0;
        $discount_id = null;
        
        if ($selected_discount) {
            $discount = $base_price * ($selected_discount['value'] / 100);
            $discount_id = $selected_discount['id'];
        }
        
        $total_price = $base_price;
        $final_price = $total_price - $discount;
        
        // Generate order code
        $order_code = 'CT-' . date('ymd') . '-' . strtoupper(uniqid());
        
        // Insert order
        $query = "INSERT INTO orders (order_code, user_id, service_id, order_date, order_time, address, total_price, discount, final_price, payment_method, discount_type, discount_id, notes, is_monthly_subscription, subscription_months) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "siisssddsssisii", $order_code, $user_id, $service_id, $order_date, $order_time, $address, $total_price, $discount, $final_price, $payment_method, $discount_type, $discount_id, $notes, $is_monthly_subscription, $subscription_months);
        
        if (mysqli_stmt_execute($stmt)) {
            $order_id = mysqli_insert_id($conn);
            
            // Add to order history
            $query = "INSERT INTO order_history (order_id, status, notes) VALUES (?, 'pending', 'Pesanan dibuat')";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            
            // Jika subscription, buat jadwal untuk bulan-bulan berikutnya
            if ($is_monthly_subscription && $subscription_months > 1) {
                for ($i = 2; $i <= $subscription_months; $i++) {
                    $next_month_date = date('Y-m-d', strtotime("+".($i-1)." months", strtotime($order_date)));
                    $subscription_order_code = $order_code . '-M' . $i;
                    
                    $query = "INSERT INTO orders (order_code, user_id, service_id, order_date, order_time, address, total_price, discount, final_price, payment_method, discount_type, discount_id, notes, is_monthly_subscription, subscription_months, status, payment_status, parent_order_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "siisssddsssisiii", $subscription_order_code, $user_id, $service_id, $next_month_date, $order_time, $address, $total_price, $discount, $final_price, $payment_method, $discount_type, $discount_id, $notes, $is_monthly_subscription, $subscription_months, $order_id);
                    mysqli_stmt_execute($stmt);
                    
                    $sub_order_id = mysqli_insert_id($conn);
                    
                    $query = "INSERT INTO order_history (order_id, status, notes) VALUES (?, 'pending', 'Pesanan langganan bulan ke-$i')";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $sub_order_id);
                    mysqli_stmt_execute($stmt);
                }
            }
            
            // Send notification to admin
            $query = "INSERT INTO notifications (user_id, title, message) VALUES (NULL, 'Pesanan Baru', 'Pesanan baru dengan kode $order_code telah dibuat')";
            mysqli_query($conn, $query);
            
            // Send notification to user about payment
            $notification_title = "Pesanan Berhasil Dibuat";
            $notification_message = "Pesanan #$order_code berhasil dibuat. Silakan lakukan pembayaran sesuai instruksi di halaman Pembayaran.";
            
            if ($selected_discount) {
                $notification_message .= " Anda mendapatkan diskon " . $selected_discount['value'] . "% (" . $selected_discount['name'] . ").";
            }
            
            $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $notification_title, $notification_message);
            mysqli_stmt_execute($stmt);
            
            $success_message = "Pesanan berhasil dibuat! Kode pesanan: <strong>$order_code</strong>";
            
            if ($selected_discount) {
                $success_message .= "<br>✅ Anda mendapatkan diskon <strong>" . $selected_discount['value'] . "%</strong> (" . $selected_discount['name'] . ")";
            }
            
            if ($is_monthly_subscription && $subscription_months > 1) {
                $success_message .= "<br>📅 Paket langganan <strong>$subscription_months bulan</strong> berhasil dibuat.";
            }
            
            $success_message .= "<br><small style='color: #666;'>Silakan cek halaman <a href='/clean-tech/user/payments.php' style='color: var(--primary-color);'>Pembayaran</a> untuk instruksi pembayaran.</small>";
            
            $success = $success_message;
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
    
    <!-- Available Discounts Info -->
    <?php if (!empty($applicable_discounts)): ?>
    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
        <strong>🎉 Diskon Tersedia!</strong> 
        <?php foreach ($applicable_discounts as $discount): ?>
            <div style="margin-top: 0.5rem;">
                ✅ <strong><?php echo $discount['name']; ?>:</strong> 
                <?php echo $discount['description']; ?> (<?php echo $discount['value']; ?>%)
            </div>
        <?php endforeach; ?>
        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
            <em>Diskon dengan nilai tertinggi akan otomatis diterapkan.</em>
        </div>
        <button class="close-alert">&times;</button>
    </div>
    <?php endif; ?>
    
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
                        
                        <!-- Monthly Subscription Option -->
                        <div class="form-group">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; padding: 1rem; background-color: #f0f7ff; border-radius: 8px;">
                                <input type="checkbox" id="is_monthly_subscription" name="is_monthly_subscription" value="1"
                                    <?php echo (isset($_POST['is_monthly_subscription']) && $_POST['is_monthly_subscription'] == '1') ? 'checked' : ''; ?>
                                    onchange="toggleSubscription()">
                                <div>
                                    <label for="is_monthly_subscription" style="font-weight: 500; margin: 0; cursor: pointer;">
                                        📅 Langganan Bulanan
                                    </label>
                                    <p style="color: #666; font-size: 0.9rem; margin-top: 0.25rem; margin-bottom: 0.5rem;">
                                        Dapatkan diskon 20% untuk pemesanan berlangganan
                                    </p>
                                    <div id="subscriptionOptions" style="<?php echo (isset($_POST['is_monthly_subscription']) && $_POST['is_monthly_subscription'] == '1') ? '' : 'display: none;'; ?>">
                                        <label style="font-size: 0.9rem; color: #666;">Pilih jumlah bulan:</label>
                                        <select name="subscription_months" class="form-control" style="margin-top: 0.5rem;">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo (isset($_POST['subscription_months']) && $_POST['subscription_months'] == $i) ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?> bulan
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
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
                        
                        <div id="subscriptionRow" style="display: none;">
                            <div>Jumlah Bulan:</div>
                            <div id="monthsCount" style="text-align: right;">1 bulan</div>
                        </div>
                        
                        <div id="discountRow" style="display: none;">
                            <div>Diskon (<span id="discountPercentage">0</span>%):</div>
                            <div id="discountAmount" style="text-align: right; color: var(--success-color);">
                                -Rp 0
                            </div>
                        </div>
                        
                        <div style="border-top: 1px solid #ddd; padding-top: 0.5rem; font-weight: bold;">Total:</div>
                        <div id="finalPrice" style="border-top: 1px solid #ddd; padding-top: 0.5rem; text-align: right; font-weight: bold; color: var(--primary-color);">
                            Rp 0
                        </div>
                    </div>
                    
                    <!-- Discount Information -->
                    <div id="discountInfo" style="margin-top: 1rem;">
                        <?php if ($is_new_customer): ?>
                            <div style="padding: 0.5rem; background-color: #d4edda; border-radius: 4px; margin-bottom: 0.5rem;">
                                ✅ <strong>Pelanggan Baru:</strong> Berhak mendapatkan diskon 15% untuk pesanan pertama
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($order_count >= 5): ?>
                            <div style="padding: 0.5rem; background-color: #d4edda; border-radius: 4px; margin-bottom: 0.5rem;">
                                🎉 <strong>Loyalty Reward:</strong> Anda telah menyelesaikan <?php echo $order_count; ?> pesanan. Berhak mendapatkan diskon 30%!
                            </div>
                        <?php else: ?>
                            <div style="padding: 0.5rem; background-color: #fff3cd; border-radius: 4px; margin-bottom: 0.5rem;">
                                ℹ️ <strong>Loyalty Program:</strong> Butuh <?php echo 5 - $order_count; ?> pesanan lagi untuk diskon 30%
                            </div>
                        <?php endif; ?>
                        
                        <div style="padding: 0.5rem; background-color: #e3f2fd; border-radius: 4px; font-size: 0.9rem;">
                            💡 <strong>Tips:</strong> Pilih "Langganan Bulanan" untuk mendapatkan diskon 20%
                        </div>
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
    const discountRow = document.getElementById('discountRow');
    const discountPercentageElem = document.getElementById('discountPercentage');
    const discountAmountElem = document.getElementById('discountAmount');
    const finalPriceElem = document.getElementById('finalPrice');
    const subscriptionRow = document.getElementById('subscriptionRow');
    const monthsCountElem = document.getElementById('monthsCount');
    const isMonthlySubscription = document.getElementById('is_monthly_subscription').checked;
    const subscriptionMonths = isMonthlySubscription ? parseInt(document.querySelector('select[name="subscription_months"]').value) : 1;
    
    if (serviceSelect.value) {
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        let basePrice = parseFloat(selectedOption.getAttribute('data-price'));
        
        // Apply subscription multiplier
        if (isMonthlySubscription) {
            basePrice = basePrice * subscriptionMonths;
            subscriptionRow.style.display = 'grid';
            monthsCountElem.textContent = subscriptionMonths + ' bulan';
        } else {
            subscriptionRow.style.display = 'none';
        }
        
        // Calculate applicable discount (highest value)
        let discountPercentage = 0;
        
        // Check new customer discount
        <?php if ($is_new_customer): ?>
            discountPercentage = Math.max(discountPercentage, 15);
        <?php endif; ?>
        
        // Check monthly subscription discount
        if (isMonthlySubscription) {
            discountPercentage = Math.max(discountPercentage, 20);
        }
        
        // Check loyalty discount
        <?php if ($order_count >= 5): ?>
            discountPercentage = Math.max(discountPercentage, 30);
        <?php endif; ?>
        
        const discountAmount = basePrice * (discountPercentage / 100);
        const finalPrice = basePrice - discountAmount;
        
        // Format to Indonesian Rupiah
        const formatter = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        });
        
        basePriceElem.textContent = formatter.format(basePrice);
        
        if (discountPercentage > 0) {
            discountRow.style.display = 'grid';
            discountPercentageElem.textContent = discountPercentage;
            discountAmountElem.textContent = '- ' + formatter.format(discountAmount);
        } else {
            discountRow.style.display = 'none';
        }
        
        finalPriceElem.textContent = formatter.format(finalPrice);
        priceSummary.style.display = 'block';
    } else {
        priceSummary.style.display = 'none';
    }
}

function toggleSubscription() {
    const subscriptionOptions = document.getElementById('subscriptionOptions');
    const isMonthlySubscription = document.getElementById('is_monthly_subscription').checked;
    
    if (isMonthlySubscription) {
        subscriptionOptions.style.display = 'block';
    } else {
        subscriptionOptions.style.display = 'none';
    }
    
    updatePrice();
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
    
    // Set maximum date to 1 year from now
    const maxDate = new Date();
    maxDate.setFullYear(maxDate.getFullYear() + 1);
    document.getElementById('order_date').max = maxDate.toISOString().split('T')[0];
    
    // Add change listener for subscription months
    document.querySelector('select[name="subscription_months"]').addEventListener('change', updatePrice);
    
    // Add change listener for monthly subscription checkbox
    document.getElementById('is_monthly_subscription').addEventListener('change', toggleSubscription);
};
</script>

<?php include '../includes/footer.php'; ?>