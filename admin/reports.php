<?php
require_once '../config/database.php';
require_once '../functions/auth.php';

// Cek login dan role admin
if (!is_logged_in() || get_user_role() != 'admin') {
    header('Location: /clean-tech/login.php');
    exit();
}

$page_title = 'Laporan';
$user_id = $_SESSION['user_id'];

// Filter parameters
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : date('Y-m-t');
$service_id = isset($_GET['service_id']) ? clean_input($_GET['service_id']) : '';
$status     = isset($_GET['status']) ? clean_input($_GET['status']) : '';

$service_id_param = $service_id;
$status_param     = $status;

$start_datetime = $start_date . ' 00:00:00';
$end_datetime   = $end_date . ' 23:59:59';

// Ambil semua layanan untuk filter
$services_query = "SELECT * FROM services WHERE is_active = 1 ORDER BY name";
$services_result = mysqli_query($conn, $services_query);

// Build report query
$query = "SELECT 
            o.*,
            u.name as user_name,
            u.email as user_email,
            u.phone as user_phone,
            s.name as service_name,
            s.price as service_price
          FROM orders o
          JOIN users u ON o.user_id = u.id
          JOIN services s ON o.service_id = s.id
          WHERE o.created_at BETWEEN ? AND ?
          AND (? = '' OR o.service_id = ?)
          AND (? = '' OR o.status = ?)
          ORDER BY o.created_at DESC";

// Prepare and execute
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param(
    $stmt,
    "ssssss",
    $start_datetime,
    $end_datetime,
    $service_id_param,
    $service_id_param,
    $status_param,
    $status_param
);


mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);

// Calculate summary
$summary = [
    'total_orders' => 0,
    'total_revenue' => 0,
    'total_discount' => 0,
    'average_order_value' => 0
];

// Get summary data
$summary_query = "SELECT
    s.name AS service_name,
    COUNT(o.id) AS order_count,
    SUM(o.final_price) AS revenue
FROM orders o
JOIN services s ON o.service_id = s.id
WHERE o.created_at BETWEEN ? AND ?
AND (? = '' OR o.service_id = ?)
AND (? = '' OR o.status = ?)
GROUP BY o.service_id
ORDER BY order_count DESC";


$stmt = mysqli_prepare($conn, $summary_query);
mysqli_stmt_bind_param(
    $stmt,
    "ssssss",
    $start_datetime,
    $end_datetime,
    $service_id_param,
    $service_id_param,
    $status_param,
    $status_param
);

mysqli_stmt_execute($stmt);
$chart_data = mysqli_stmt_get_result($stmt);

include '../includes/header.php';
?>

<div style="margin: 2rem 0;">
    <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">Laporan</h1>
    <p style="color: #666;">Analisis data pesanan dan pendapatan</p>
</div>

<!-- Summary Cards -->
<div class="grid grid-4" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="stats-card">
        <div class="stat-number"><?php echo $summary['total_orders'] ?? 0; ?></div>
        <div class="stat-label">Total Pesanan</div>
    </div>

    <div class="stats-card">
        <div class="stat-number">Rp <?php echo number_format($summary['total_revenue'] ?? 0, 0, ',', '.'); ?></div>
        <div class="stat-label">Total Pendapatan</div>
    </div>

    <div class="stats-card">
        <div class="stat-number">Rp <?php echo number_format($summary['total_discount'] ?? 0, 0, ',', '.'); ?></div>
        <div class="stat-label">Total Diskon</div>
    </div>

    <div class="stats-card">
        <div class="stat-number">Rp <?php echo number_format($summary['avg_order_value'] ?? 0, 0, ',', '.'); ?></div>
        <div class="stat-label">Rata-rata Pesanan</div>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 style="color: white; margin: 0;">Filter Laporan</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="start_date">Tanggal Mulai</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="end_date">Tanggal Akhir</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="service_id">Layanan</label>
                    <select id="service_id" name="service_id" class="form-control">
                        <option value="">Semua Layanan</option>
                        <?php while ($service = mysqli_fetch_assoc($services_result)): ?>
                            <option value="<?php echo $service['id']; ?>"
                                <?php echo $service_id == $service['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                        <option value="processing" <?php echo $status == 'processing' ? 'selected' : ''; ?>>Diproses</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                <button type="button" onclick="printReport()" class="btn btn-secondary">Cetak Laporan</button>
                <button type="button" onclick="exportToExcel()" class="btn btn-success">Export Excel</button>
                <a href="reports.php" class="btn btn-danger">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Chart Section -->
<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
    <!-- Orders by Service -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: white; margin: 0;">Pesanan per Layanan</h3>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($chart_data) > 0):
                $chart_data_array = [];
                while ($row = mysqli_fetch_assoc($chart_data)) {
                    $chart_data_array[] = $row;
                }
                $order_counts = array_column($chart_data_array, 'order_count');
                $max_count = !empty($order_counts) ? max($order_counts) : 0;

            ?>
                <div style="height: 300px; display: flex; flex-direction: column; justify-content: space-around; padding: 1rem;">
                    <?php foreach ($chart_data_array as $data):
                        $percentage = $max_count > 0 ? ($data['order_count'] / $max_count * 100) : 0;
                    ?>
                        <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                            <div style="width: 30%; font-size: 0.9rem;"><?php echo htmlspecialchars($data['service_name']); ?></div>
                            <div style="flex: 1; display: flex; align-items: center;">
                                <div style="width: <?php echo $percentage; ?>%; height: 30px; background-color: var(--primary-color); border-radius: 4px; margin-right: 1rem;"></div>
                                <div style="min-width: 60px; text-align: right;">
                                    <div style="font-weight: bold;"><?php echo $data['order_count']; ?></div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        Rp <?php echo number_format($data['revenue'] ?? 0, 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">Tidak ada data untuk ditampilkan</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Distribution -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: white; margin: 0;">Distribusi Status</h3>
        </div>
        <div class="card-body">
            <?php
            // Get status distribution
            $status_query = "SELECT 
                                status,
                                COUNT(*) as count
                            FROM orders
                            WHERE created_at BETWEEN ? AND ?
                            AND (? = '' OR service_id = ?)
                            GROUP BY status";

            $stmt = mysqli_prepare($conn, $status_query);
            mysqli_stmt_bind_param(
                $stmt,
                "ssss",
                $start_datetime,
                $end_datetime,
                $service_id_param,
                $service_id_param
            );


            mysqli_stmt_execute($stmt);
            $status_data = mysqli_stmt_get_result($stmt);
            $status_distribution = [];
            $total_status = 0;

            while ($row = mysqli_fetch_assoc($status_data)) {
                $status_distribution[$row['status']] = $row['count'];
                $total_status += $row['count'];
            }

            if ($total_status > 0):
            ?>
                <div style="height: 300px; display: flex; flex-direction: column; justify-content: center; padding: 1rem;">
                    <?php
                    $status_config = [
                        'pending' => ['color' => '#ffc107', 'label' => 'Pending'],
                        'confirmed' => ['color' => '#17a2b8', 'label' => 'Dikonfirmasi'],
                        'processing' => ['color' => '#007bff', 'label' => 'Diproses'],
                        'completed' => ['color' => '#28a745', 'label' => 'Selesai'],
                        'cancelled' => ['color' => '#dc3545', 'label' => 'Dibatalkan']
                    ];

                    foreach ($status_config as $status_key => $config):
                        $count = $status_distribution[$status_key] ?? 0;
                        $percentage = $total_status > 0 ? ($count / $total_status * 100) : 0;
                    ?>
                        <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                            <div style="width: 20px; height: 20px; background-color: <?php echo $config['color']; ?>; border-radius: 4px; margin-right: 0.5rem;"></div>
                            <div style="flex: 1;">
                                <div style="font-weight: 500;"><?php echo $config['label']; ?></div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                                    <span><?php echo $count; ?> pesanan</span>
                                    <span><?php echo number_format($percentage, 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">Tidak ada data status</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="color: white; margin: 0;">Detail Pesanan</h3>
        <div style="font-size: 0.9rem; color: white;">
            <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>
        </div>
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
                            <th>Status</th>
                            <th>Harga</th>
                            <th>Diskon</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_code']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($order['user_name']); ?></div>
                                    <small style="color: #666;"><?php echo $order['user_phone']; ?></small>
                                </td>
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
                                <td>Rp <?php echo number_format($order['service_price'], 0, ',', '.'); ?></td>
                                <td>Rp <?php echo number_format($order['discount'], 0, ',', '.'); ?></td>
                                <td>
                                    <strong>Rp <?php echo number_format($order['final_price'], 0, ',', '.'); ?></strong>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="5" style="text-align: right;">Total:</td>
                            <td>Rp <?php echo number_format(array_sum(array_column(mysqli_fetch_all($orders_result, MYSQLI_ASSOC), 'service_price')), 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($summary['total_discount'], 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($summary['total_revenue'], 0, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 2rem;">Tidak ada data pesanan dalam periode ini</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function printReport() {
        window.print();
    }

    function exportToExcel() {
        // Create table data
        let table = document.querySelector('.table');
        let rows = table.querySelectorAll('tr');
        let csv = [];

        for (let i = 0; i < rows.length; i++) {
            let row = [],
                cols = rows[i].querySelectorAll('td, th');

            for (let j = 0; j < cols.length; j++) {
                // Remove badge HTML
                let text = cols[j].innerText;
                row.push(text);
            }

            csv.push(row.join(','));
        }

        // Download CSV file
        let csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
        let encodedUri = encodeURI(csvContent);
        let link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `laporan_cleantech_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Add print styles
    let style = document.createElement('style');
    style.textContent = `
    @media print {
        .no-print, .main-header, .main-nav, .auth-buttons, footer {
            display: none !important;
        }
        body {
            font-size: 12pt;
        }
        .card {
            box-shadow: none;
            border: 1px solid #000;
        }
        .table {
            border: 1px solid #000;
        }
        .badge {
            border: 1px solid #000;
            background: none;
            color: #000;
        }
    }
`;
    document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>