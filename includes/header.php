<?php
if (!isset($page_title)) {
    $page_title = 'Clean-Tech - Jasa Cleaning Service';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="/clean-tech/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #2e51f0;
            --primary-dark: #1a3cd8;
            --secondary-color: #ffffff;
            --text-color: #333333;
            --light-gray: #f5f5f5;
            --gray: #e0e0e0;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            background-color: var(--light-gray);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        .main-header {
            background-color: var(--secondary-color);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            min-height: 70px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo h1 {
            color: var(--primary-color);
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
        }

        .logo span {
            color: var(--text-color);
            font-weight: 300;
        }

        .main-nav {
            flex: 1;
            display: flex;
            justify-content: center;
            padding: 0 1rem;
        }

        .main-nav ul {
            display: flex;
            list-style: none;
            gap: 0.8rem;
            flex-wrap: wrap;
            justify-content: center;
            padding: 0;
            margin: 0;
        }

        .main-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            padding: 0.5rem 0.8rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .main-nav a:hover,
        .main-nav a.active {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        /* Dropdown Styles */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            cursor: pointer;
        }

        .dropdown-toggle::after {
            content: '▾';
            font-size: 0.8rem;
            margin-left: 0.2rem;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background-color: var(--secondary-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 4px;
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            margin-top: 0.5rem;
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            padding: 0.6rem 1rem;
            border-radius: 0;
            display: block;
            border-bottom: 1px solid var(--light-gray);
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        .auth-buttons {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-shrink: 0;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .user-info span {
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Admin-specific styles */
        .admin-badge {
            background-color: var(--danger-color);
            color: white;
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
            font-size: 0.65rem;
            font-weight: bold;
            margin-left: 0.3rem;
        }

        /* Alert Messages */
        .alert {
            padding: 0.8rem 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .close-alert {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            padding: 0;
            margin-left: 0.5rem;
        }

        /* Main content */
        main {
            flex: 1;
            padding: 1.5rem 0 3rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .header-content {
                flex-wrap: wrap;
                gap: 1rem;
                padding: 0.8rem 0;
            }

            .main-nav {
                order: 3;
                width: 100%;
                padding: 0;
                margin-top: 0.5rem;
            }

            .main-nav ul {
                gap: 0.5rem;
            }

            .main-nav a {
                padding: 0.4rem 0.6rem;
                font-size: 0.85rem;
            }

            .dropdown-menu {
                position: static;
                box-shadow: none;
                opacity: 1;
                visibility: visible;
                transform: none;
                display: none;
                margin-top: 0;
            }

            .dropdown:hover .dropdown-menu {
                display: block;
            }

            .logo {
                order: 1;
            }

            .auth-buttons {
                order: 2;
            }
        }

        @media (max-width: 576px) {

            .user-info span,
            .admin-badge {
                display: none;
            }

            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .logo h1 {
                font-size: 1.4rem;
            }

            .container {
                width: 95%;
                padding: 0 15px;
            }
        }

        /* Active menu highlight */
        <?php
        // Determine current page for active menu
        $current_page = basename($_SERVER['PHP_SELF']);
        $active_styles = '';

        // Admin pages
        $admin_pages = [
            'dashboard.php' => 'Dashboard',
            'orders.php' => 'Pesanan',
            'payments.php' => 'Pembayaran',
            'services.php' => 'Layanan',
            'discounts.php' => 'Diskon',
            'users.php' => 'Pengguna',
            'reports.php' => 'Laporan'
        ];

        // User pages
        $user_pages = [
            'dashboard.php' => 'Dashboard',
            'services.php' => 'Layanan',
            'order.php' => 'Pesan',
            'payments.php' => 'Pembayaran',
            'history.php' => 'Riwayat'
        ];

        foreach ($admin_pages as $page => $name) {
            if ($current_page === $page && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
                $active_styles .= ".main-nav a[href*='{$page}'] { background-color: var(--primary-color); color: var(--secondary-color); }";
            }
        }

        foreach ($user_pages as $page => $name) {
            if ($current_page === $page && isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'user') {
                $active_styles .= ".main-nav a[href*='{$page}'] { background-color: var(--primary-color); color: var(--secondary-color); }";
            }
        }

        if ($current_page === 'index.php' || $current_page === '') {
            $active_styles .= ".main-nav a[href='/clean-tech/'] { background-color: var(--primary-color); color: var(--secondary-color); }";
        }

        echo $active_styles;
        ?>
    </style>
</head>

<body>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <a href="/clean-tech/" class="logo">
                    <h1>Clean<span>Tech</span></h1>
                </a>

                <nav class="main-nav">
                    <ul>
                        <li><a href="/clean-tech/">Beranda</a></li>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['user_role'] == 'admin'): ?>
                                <!-- Menu untuk Admin dengan dropdown -->
                                <li><a href="/clean-tech/admin/dashboard.php">Dashboard</a></li>
                                <li><a href="/clean-tech/admin/orders.php">Pesanan</a></li>
                                <li class="dropdown">
                                    <a href="javascript:void(0)" class="dropdown-toggle">Lainnya</a>
                                    <div class="dropdown-menu">
                                        <a href="/clean-tech/admin/payments.php">Pembayaran</a>
                                        <a href="/clean-tech/admin/services.php">Layanan</a>
                                        <a href="/clean-tech/admin/discounts.php">Diskon</a>
                                        <a href="/clean-tech/admin/users.php">Pengguna</a>
                                        <a href="/clean-tech/admin/reports.php">Laporan</a>
                                    </div>
                                </li>
                            <?php else: ?>
                                <!-- Menu untuk User -->
                                <li><a href="/clean-tech/user/dashboard.php">Dashboard</a></li>
                                <li><a href="/clean-tech/user/services.php">Layanan</a></li>
                                <li><a href="/clean-tech/user/order.php">Pesan</a></li>
                                <li><a href="/clean-tech/user/payments.php">Pembayaran</a></li>
                                <li><a href="/clean-tech/user/history.php">Riwayat</a></li>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Menu untuk Guest -->
                            <li><a href="/clean-tech/user/services.php">Layanan</a></li>
                            <li><a href="#about">Tentang</a></li>
                            <li><a href="#contact">Kontak</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>

                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="user-menu">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                </div>
                                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                <?php if ($_SESSION['user_role'] == 'admin'): ?>
                                    <span class="admin-badge">ADMIN</span>
                                <?php endif; ?>
                            </div>
                            <a href="/clean-tech/logout.php" class="btn btn-secondary">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="/clean-tech/login.php" class="btn btn-secondary">Login</a>
                        <a href="/clean-tech/register.php" class="btn btn-primary">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="container">