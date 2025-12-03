<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Get dashboard statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_customers,
        COUNT(CASE WHEN status_verifikasi = 'pending' THEN 1 END) AS pending_verifications,
        (SELECT COUNT(*) FROM pesanan) AS total_orders,
        (SELECT COUNT(*) FROM pesanan WHERE status = 'pending') AS pending_orders,
        (SELECT COUNT(*) FROM pesanan WHERE status = 'approved') AS approved_orders,
        (SELECT COUNT(*) FROM pesanan WHERE status = 'completed') AS completed_orders,
        (SELECT COALESCE(SUM(total_biaya), 0) FROM pesanan WHERE status = 'completed') AS total_revenue
    FROM customers
");
$stats = $stmt->fetch();

// Get recent orders
$stmt = $pdo->query("
    SELECT p.*, c.no_telepon, u.nama as customer_nama, s.nama_studio 
    FROM pesanan p 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$recent_orders = $stmt->fetchAll();

// Get pending verifications
$stmt = $pdo->query("
    SELECT c.*, u.nama, u.email, u.created_at 
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    WHERE c.status_verifikasi = 'pending' 
    ORDER BY u.created_at DESC 
    LIMIT 5
");
$pending_verifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - StudioEase</title>
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
            --info: #3182ce;
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
            background: linear-gradient(135deg, #2d3748, #4a5568);
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
            justify-content: space-between;
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
            background: var(--error);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: bold;
        }

        /* Stats Grid */
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
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.info::before { background: var(--info); }
        .stat-card.error::before { background: var(--error); }

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

        .stat-icon.primary { background: #c3dafe; color: var(--primary); }
        .stat-icon.success { background: #c6f6d5; color: var(--success); }
        .stat-icon.warning { background: #fefcbf; color: var(--warning); }
        .stat-icon.info { background: #bee3f8; color: var(--info); }
        .stat-icon.error { background: #fed7d7; color: var(--error); }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Sections */
        .section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
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

        .btn-danger {
            background: var(--error);
            color: var(--white);
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            background: var(--light-bg);
            font-weight: 600;
            color: var(--text);
        }

        .table tr:hover {
            background: var(--light-bg);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fefcbf; color: var(--warning); }
        .status-approved { background: #c6f6d5; color: var(--success); }
        .status-completed { background: #bee3f8; color: var(--info); }
        .status-rejected { background: #fed7d7; color: var(--error); }
        .status-verified { background: #c6f6d5; color: var(--success); }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-color: var(--primary);
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

            .table {
                font-size: 0.9rem;
            }

            .action-buttons {
                flex-direction: column;
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
                <p>Admin Dashboard</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="validasi-member.php"><i class="fas fa-users"></i> Validasi Member</a></li>
                <li><a href="validasi-pemesanan.php"><i class="fas fa-clipboard-list"></i> Validasi Pemesanan</a></li>
                <li><a href="penjadwalan.php"><i class="fas fa-calendar-alt"></i> Penjadwalan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="studios.php"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user-cog"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Dashboard Admin 🎯</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['nama'], 0, 1)); ?>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['nama']); ?></strong>
                        <div style="font-size: 0.9rem; color: var(--text-light);">Administrator</div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total Customer</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_verifications']; ?></div>
                    <div class="stat-label">Verifikasi Pending</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Pemesanan</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section">
                <h2 style="margin-bottom: 1rem;">Aksi Cepat</h2>
                <div class="quick-actions">
                    <a href="validasi-member.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>Validasi Member</div>
                        <small style="color: var(--text-light);"><?php echo $stats['pending_verifications']; ?> pending</small>
                    </a>
                    
                    <a href="validasi-pemesanan.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div>Validasi Pemesanan</div>
                        <small style="color: var(--text-light);"><?php echo $stats['pending_orders']; ?> pending</small>
                    </a>
                    
                    <a href="penjadwalan.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>Penjadwalan</div>
                        <small style="color: var(--text-light);"><?php echo $stats['approved_orders']; ?> disetujui</small>
                    </a>
                    
                    <a href="laporan.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>Lihat Laporan</div>
                        <small style="color: var(--text-light);">Analytics</small>
                    </a>
                </div>
            </div>

            <div class="grid-2-col" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Recent Orders -->
                <div class="section">
                    <div class="section-header">
                        <h2>Pemesanan Terbaru</h2>
                        <a href="validasi-pemesanan.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Lihat Semua
                        </a>
                    </div>

                    <?php if ($recent_orders): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Studio</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['customer_nama']); ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--text-light);">
                                            <?php echo $order['no_telepon']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['nama_studio']); ?></td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?><br>
                                        <small style="color: var(--text-light);">
                                            <?php echo $order['jam_mulai'] . ' - ' . $order['jam_selesai']; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'pending' => 'Menunggu',
                                                'approved' => 'Disetujui', 
                                                'completed' => 'Selesai',
                                                'rejected' => 'Ditolak'
                                            ];
                                            echo $status_text[$order['status']] ?? $order['status']; 
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Belum ada pemesanan</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Verifications -->
                <div class="section">
                    <div class="section-header">
                        <h2>Verifikasi Member Pending</h2>
                        <a href="validasi-member.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> Lihat Semua
                        </a>
                    </div>

                    <?php if ($pending_verifications): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_verifications as $member): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($member['nama']); ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--text-light);">
                                            <?php echo $member['no_telepon']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($member['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="validasi-member.php?action=approve&id=<?php echo $member['customer_id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="validasi-member.php?action=reject&id=<?php echo $member['customer_id']; ?>" 
                                               class="btn btn-danger btn-sm">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-light);">
                            <i class="fas fa-user-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Tidak ada verifikasi pending</p>
                        </div>
                    <?php endif; ?>
                </div>
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