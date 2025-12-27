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
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo span {
            color: var(--text-color);
            font-weight: 300;
        }

        .main-nav ul {
            display: flex;
            list-style: none;
            gap: 1.5rem;
        }

        .main-nav a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            padding: 0.5rem 0.8rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .main-nav a:hover {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: var(--secondary-color);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        /* Admin-specific styles */
        .admin-badge {
            background-color: var(--danger-color);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: bold;
            margin-left: 0.3rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                padding: 0.8rem 0;
            }

            .main-nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.8rem;
            }

            .main-nav a {
                padding: 0.4rem 0.6rem;
                font-size: 0.9rem;
            }

            .user-info span {
                display: none;
            }
        }
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
                                <!-- Menu untuk Admin -->
                                <li><a href="/clean-tech/admin/dashboard.php">Dashboard</a></li>
                                <li><a href="/clean-tech/admin/orders.php">Pesanan</a></li>
                                <li><a href="/clean-tech/admin/payments.php">Pembayaran</a></li>
                                <li><a href="/clean-tech/admin/services.php">Layanan</a></li>
                                <li><a href="/clean-tech/admin/users.php">Pengguna</a></li>
                                <li><a href="/clean-tech/admin/reports.php">Laporan</a></li>
                            <?php else: ?>
                                <!-- Menu untuk User -->
                                <li><a href="/clean-tech/user/dashboard.php">Dashboard</a></li>
                                <li><a href="/clean-tech/user/services.php">Layanan</a></li>
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