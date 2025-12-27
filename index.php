<?php
require_once 'config/database.php';
require_once 'functions/auth.php';

$page_title = 'Clean-Tech - Beranda';
include 'includes/header.php';

// Ambil data layanan untuk ditampilkan
$services_query = "SELECT * FROM services WHERE is_active = 1 LIMIT 3";
$services_result = mysqli_query($conn, $services_query);
?>

<!-- Hero Section -->
<section style="background: linear-gradient(135deg, var(--primary-color) 0%, #1a3cd8 100%); color: white; padding: 5rem 0; border-radius: 10px; margin: 2rem 0;">
    <div style="text-align: center;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">CleanTech Cleaning Service</h1>
        <p style="font-size: 1.2rem; max-width: 800px; margin: 0 auto 2rem;">Layanan kebersihan profesional dengan teknologi modern untuk rumah dan kantor Anda. Dapatkan diskon 30% setelah 5 kali pemesanan!</p>
        <a href="/clean-tech/user/services.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2rem;">Pesan Sekarang</a>
    </div>
</section>

<!-- Promo Section -->
<section style="background-color: white; padding: 2rem; border-radius: 10px; margin: 2rem 0; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Promo Spesial</h2>
        <p style="color: #666;">Manfaatkan penawaran menarik dari CleanTech</p>
    </div>
    
    <div style="background: linear-gradient(45deg, #ff6b6b, #ff8e53); color: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Diskon 30% Setelah 5 Kali Pemesanan!</h3>
                <p>Setiap 5 kali pemesanan layanan reguler, dapatkan diskon 30% untuk pemesanan ke-6!</p>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                30% OFF
            </div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
        <div style="background-color: var(--light-gray); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--primary-color);">
            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">Paket Bulanan</h4>
            <p>Diskon 20% untuk langganan bulanan</p>
        </div>
        <div style="background-color: var(--light-gray); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--success-color);">
            <h4 style="color: var(--success-color); margin-bottom: 0.5rem;">New Customer</h4>
            <p>Diskon 15% untuk pelanggan baru</p>
        </div>
        <div style="background-color: var(--light-gray); padding: 1.5rem; border-radius: 8px; border-left: 4px solid var(--warning-color);">
            <h4 style="color: var(--warning-color); margin-bottom: 0.5rem;">Referral Program</h4>
            <p>Dapatkan 1x free cleaning untuk setiap 5 referral</p>
        </div>
    </div>
</section>

<!-- Services Preview -->
<section style="margin: 3rem 0;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Layanan Kami</h2>
        <p style="color: #666;">Berbagai layanan kebersihan profesional</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
        <?php if (mysqli_num_rows($services_result) > 0): ?>
            <?php while ($service = mysqli_fetch_assoc($services_result)): ?>
                <div style="background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s ease;">
                    <div style="height: 200px; background: linear-gradient(45deg, var(--primary-color), #1a3cd8); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                        <?php echo htmlspecialchars($service['name'][0]); ?>
                    </div>
                    <div style="padding: 1.5rem;">
                        <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p style="color: #666; margin-bottom: 1rem;"><?php echo htmlspecialchars($service['description']); ?></p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 1.2rem; font-weight: bold; color: var(--primary-color);">
                                Rp <?php echo number_format($service['price'], 0, ',', '.'); ?>
                            </span>
                            <a href="/clean-tech/user/services.php#service-<?php echo $service['id']; ?>" class="btn btn-primary">Detail</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 2rem;">
                <p style="color: #666;">Belum ada layanan tersedia.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="/clean-tech/user/services.php" class="btn btn-secondary">Lihat Semua Layanan</a>
    </div>
</section>

<!-- How It Works -->
<section style="background-color: white; padding: 3rem; border-radius: 10px; margin: 2rem 0;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Cara Kerja</h2>
        <p style="color: #666;">4 langkah mudah menggunakan layanan CleanTech</p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
        <div>
            <div style="width: 80px; height: 80px; background-color: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem;">
                1
            </div>
            <h3>Pilih Layanan</h3>
            <p>Pilih layanan yang sesuai dengan kebutuhan Anda</p>
        </div>
        
        <div>
            <div style="width: 80px; height: 80px; background-color: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem;">
                2
            </div>
            <h3>Jadwalkan</h3>
            <p>Tentukan tanggal dan waktu yang diinginkan</p>
        </div>
        
        <div>
            <div style="width: 80px; height: 80px; background-color: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem;">
                3
            </div>
            <h3>Bayar</h3>
            <p>Lakukan pembayaran dengan metode yang tersedia</p>
        </div>
        
        <div>
            <div style="width: 80px; height: 80px; background-color: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin: 0 auto 1rem;">
                4
            </div>
            <h3>Bersih!</h3>
            <p>Tim kami akan membersihkan sesuai jadwal</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>