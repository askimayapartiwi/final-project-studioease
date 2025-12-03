<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

// Get customer data
$customer_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("
    SELECT c.*, u.nama, u.email, u.created_at 
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    WHERE c.customer_id = ?
");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

// Get recent orders
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, s.harga_per_jam 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.customer_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$stmt->execute([$customer_id]);
$recent_orders = $stmt->fetchAll();

// Get order statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders
    FROM pesanan 
    WHERE customer_id = ?
");
$stmt->execute([$customer_id]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Customer - StudioEase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --accent: #f093fb;
            --text: #2d3748;
            --text-light: #718096;
            --white: #ffffff;
            --light-bg: #f7fafc;
            --success: #38a169;
            --warning: #d69e2e;
            --error: #e53e3e;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            color: var(--text);
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            padding: 2rem 1rem;
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: var(--white);
        }

        .sidebar-nav i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .header h1 {
            color: var(--text);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: bold;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.primary {
            background: #c3dafe;
            color: var(--primary);
        }

        .stat-icon.success {
            background: #c6f6d5;
            color: var(--success);
        }

        .stat-icon.warning {
            background: #fefcbf;
            color: var(--warning);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Recent Orders */
        .section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            color: var(--text);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Order List */
        .order-list {
            list-style: none;
        }

        .order-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.3s ease;
        }

        .order-item:hover {
            background: var(--light-bg);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info h4 {
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .order-meta {
            display: flex;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fefcbf; color: var(--warning); }
        .status-approved { background: #c6f6d5; color: var(--success); }
        .status-completed { background: #bee3f8; color: var(--primary); }
        .status-rejected { background: #fed7d7; color: var(--error); }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: var(--text);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-camera"></i> StudioEase</h2>
                <p>Customer Dashboard</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="pesan.php"><i class="fas fa-calendar-plus"></i> Pesan Studio</a></li>
                <li><a href="pemesanan.php"><i class="fas fa-list"></i> Data Pemesanan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Selamat Datang, <?php echo htmlspecialchars($customer['nama']); ?>! 👋</h1>
                <div class="user-info">
    <div class="user-avatar">
        <?php echo strtoupper(substr($customer['nama'], 0, 1)); ?>
    </div>
    <div>
        <strong><?php echo htmlspecialchars($customer['nama']); ?></strong>
        <div style="font-size: 0.9rem; color: var(--text-light);">
            Status: 
            <span class="order-status status-<?php echo $customer['status_verifikasi']; ?>">
                <?php 
                $status_text = [
                    'pending' => 'Menunggu Verifikasi',
                    'verified' => 'Terverifikasi',
                    'rejected' => 'Ditolak'
                ];
                echo $status_text[$customer['status_verifikasi']]; 
                ?>
            </span>
        </div>
    </div>
</div>
 </div>

            <!-- Tambahkan alert jika status pending -->
            <?php if ($customer['status_verifikasi'] === 'pending'): ?>
            <div class="alert alert-warning" style="background: #fefcbf; color: var(--warning); padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <i class="fas fa-clock"></i> 
                <strong>Akun Anda sedang menunggu verifikasi admin.</strong> 
                Anda dapat melihat fitur aplikasi tetapi belum dapat melakukan pemesanan hingga akun diverifikasi.
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                    <div class="stat-label">Total Pemesanan</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['completed_orders'] ?? 0; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_orders'] ?? 0; ?></div>
                    <div class="stat-label">Menunggu</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section">
                <h2 style="margin-bottom: 1rem;">Aksi Cepat</h2>
                <div class="quick-actions">
                    <a href="pesan.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>Pesan Studio</div>
                    </a>
                    
                    <a href="pemesanan.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div>Lihat Pemesanan</div>
                    </a>
                    
                    <a href="pembayaran.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div>Pembayaran</div>
                    </a>
                    
                    <a href="profile.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div>Edit Profile</div>
                    </a>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="section">
                <div class="section-header">
                    <h2>Pemesanan Terbaru</h2>
                    <a href="pemesanan.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i> Lihat Semua
                    </a>
                </div>

                <?php if ($recent_orders): ?>
                    <ul class="order-list">
                        <?php foreach ($recent_orders as $order): ?>
                        <li class="order-item">
                            <div class="order-info">
                                <h4><?php echo htmlspecialchars($order['nama_studio']); ?></h4>
                                <div class="order-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo $order['jam_mulai'] . ' - ' . $order['jam_selesai']; ?></span>
                                    <span><i class="fas fa-money-bill"></i> <?php echo formatRupiah($order['total_biaya']); ?></span>
                                </div>
                            </div>
                            <div class="order-status status-<?php echo $order['status']; ?>">
                                <?php 
                                $status_text = [
                                    'pending' => 'Menunggu',
                                    'approved' => 'Disetujui', 
                                    'completed' => 'Selesai',
                                    'rejected' => 'Ditolak',
                                    'cancelled' => 'Dibatalkan'
                                ];
                                echo $status_text[$order['status']] ?? $order['status']; 
                                ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Belum ada pemesanan</p>
                        <a href="pesan.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Buat Pemesanan Pertama
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Simple animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.6s ease ${index * 0.1}s both`;
            });

            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>