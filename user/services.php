<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login
if (!is_logged_in()) {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Layanan Clean-Tech';
$user_id = $_SESSION['user_id'];

// Ambil semua layanan aktif
$query = "SELECT * FROM services WHERE is_active = 1 ORDER BY price ASC";
$services_result = mysqli_query($conn, $query);

// Ambil diskon yang berlaku
$query = "SELECT * FROM discounts WHERE is_active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())";
$discounts_result = mysqli_query($conn, $query);
$discounts = [];
while ($discount = mysqli_fetch_assoc($discounts_result)) {
    $discounts[] = $discount;
}

// Hitung jumlah pesanan user untuk diskon loyalty
$query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ? AND status = 'completed'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order_count = mysqli_fetch_assoc($result)['order_count'];

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Layanan Kami</h1>
    <p style="color: #666;">Pilih layanan kebersihan yang sesuai dengan kebutuhan Anda</p>
</div>

<!-- Discount Banner -->
<?php if ($order_count >= 5): ?>
<div class="alert alert-success" style="margin-bottom: 2rem;">
    🎉 Anda berhak mendapatkan diskon 30% untuk pemesanan berikutnya!
    <button class="close-alert">&times;</button>
</div>
<?php endif; ?>

<!-- Services Grid -->
<div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
    <?php if (mysqli_num_rows($services_result) > 0): ?>
        <?php while ($service = mysqli_fetch_assoc($services_result)): 
            // Hitung harga setelah diskon
            $final_price = $service['price'];
            $discount_applied = false;
            
            // Cek diskon loyalty
            if ($order_count >= 5) {
                $final_price = $service['price'] * 0.7; // 30% discount
                $discount_applied = true;
            }
        ?>
            <div class="service-card" id="service-<?php echo $service['id']; ?>">
                <div class="service-image">
                    <div style="font-size: 3rem;">🏠</div>
                </div>
                <div class="service-content">
                    <?php if ($discount_applied): ?>
                        <div class="pricing-badge">DISKON 30%</div>
                    <?php endif; ?>
                    
                    <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p style="color: #666; margin-bottom: 1rem; min-height: 60px;"><?php echo htmlspecialchars($service['description']); ?></p>
                    
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: #666;">⏱️</span>
                            <span><?php echo $service['duration_hours']; ?> jam</span>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <?php if ($discount_applied): ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="text-decoration: line-through; color: #999; font-size: 0.9rem;">
                                    Rp <?php echo number_format($service['price'], 0, ',', '.'); ?>
                                </span>
                                <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                    Rp <?php echo number_format($final_price, 0, ',', '.'); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <span style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                Rp <?php echo number_format($service['price'], 0, ',', '.'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="/clean-tech/user/order.php?service_id=<?php echo $service['id']; ?>" class="btn btn-primary" style="flex: 1;">Pesan Sekarang</a>
                        <button onclick="showServiceDetail(<?php echo $service['id']; ?>)" class="btn btn-secondary">Detail</button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
            <p style="color: #666; font-size: 1.1rem;">Belum ada layanan tersedia.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Discount Section -->
<div class="card" style="margin-bottom: 3rem;">
    <div class="card-header">
        <h3 style="color: white; margin: 0;">Promo & Diskon</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
            <?php foreach ($discounts as $discount): ?>
                <div style="background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                    <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($discount['name']); ?>
                    </h4>
                    <p style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">
                        <?php echo htmlspecialchars($discount['description']); ?>
                    </p>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-weight: bold; color: var(--primary-color);">
                            <?php 
                            if ($discount['discount_type'] == 'percentage') {
                                echo $discount['discount_value'] . '%';
                            } else {
                                echo 'Rp ' . number_format($discount['discount_value'], 0, ',', '.');
                            }
                            ?>
                        </span>
                        <?php if ($discount['min_orders'] > 0): ?>
                            <span style="font-size: 0.8rem; color: #666;">
                                Minimal <?php echo $discount['min_orders']; ?> pesanan
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Service Detail Modal -->
<div id="serviceDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalServiceName">Detail Layanan</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalServiceContent">
                <!-- Content will be loaded by JavaScript -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Tutup</button>
            <a id="modalBookButton" href="#" class="btn btn-primary">Pesan Layanan Ini</a>
        </div>
    </div>
</div>

<script>
function showServiceDetail(serviceId) {
    // Fetch service details via AJAX
    fetch(`/clean-tech/api/get_service.php?id=${serviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const service = data.service;
                document.getElementById('modalServiceName').textContent = service.name;
                
                let content = `
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Deskripsi:</strong> ${service.description}</p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Durasi:</strong> ${service.duration_hours} jam</p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Harga:</strong> Rp ${parseInt(service.price).toLocaleString('id-ID')}</p>
                    </div>
                `;
                
                // Add discount info if eligible
                const orderCount = <?php echo $order_count; ?>;
                if (orderCount >= 5) {
                    const discountedPrice = service.price * 0.7;
                    content += `
                        <div style="background-color: #d4edda; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                            <p><strong>💰 Anda berhak mendapatkan diskon 30%!</strong></p>
                            <p>Harga setelah diskon: <strong>Rp ${parseInt(discountedPrice).toLocaleString('id-ID')}</strong></p>
                        </div>
                    `;
                }
                
                document.getElementById('modalServiceContent').innerHTML = content;
                document.getElementById('modalBookButton').href = `/clean-tech/user/order.php?service_id=${serviceId}`;
                
                // Show modal
                document.getElementById('serviceDetailModal').classList.add('active');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Gagal memuat detail layanan');
        });
}

function closeModal() {
    document.getElementById('serviceDetailModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('serviceDetailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>