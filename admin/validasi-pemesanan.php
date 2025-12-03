<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Handle order validation actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pesanan_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $status = 'approved';
        $message = 'Pemesanan berhasil disetujui!';
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $message = 'Pemesanan berhasil ditolak!';
    } elseif ($action === 'complete') {
        $status = 'completed';
        $message = 'Pemesanan telah diselesaikan!';
    } else {
        $message = 'Aksi tidak valid!';
    }
    
    if (isset($status)) {
        $stmt = $pdo->prepare("UPDATE pesanan SET status = ? WHERE pesanan_id = ?");
        $stmt->execute([$status, $pesanan_id]);
        showMessage($message, 'success');
        redirect('validasi-pemesanan.php');
    }
}

// Get orders with different statuses
$status_filter = $_GET['status'] ?? 'all';

$query_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $query_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

$where_clause = $query_conditions ? "WHERE " . implode(" AND ", $query_conditions) : "";

$stmt = $pdo->prepare("
    SELECT p.*, c.no_telepon, u.nama as customer_nama, s.nama_studio, s.harga_per_jam 
    FROM pesanan p 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    $where_clause
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_orders,
        COALESCE(SUM(total_biaya), 0) as total_revenue
    FROM pesanan
");
$stats = $stmt->fetch();

// Get today's orders for quick overview
$stmt = $pdo->prepare("
    SELECT p.*, u.nama as customer_nama, s.nama_studio 
    FROM pesanan p 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE DATE(p.tanggal_sesi) = CURDATE() 
    AND p.status IN ('approved', 'completed')
    ORDER BY p.jam_mulai ASC
");
$stmt->execute();
$today_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Pemesanan - StudioEase</title>
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
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            background: var(--white);
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }

        .filter-tab {
            flex: 1;
            padding: 1rem 1.5rem;
            text-align: center;
            background: var(--white);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .filter-tab.active {
            background: var(--primary);
            color: var(--white);
        }

        .filter-tab:hover:not(.active) {
            background: var(--light-bg);
        }

        /* Content Sections */
        .content-section {
            background: var(--white);
            border-radius: 0 0 10px 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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

        /* Orders Grid */
        .orders-grid {
            display: grid;
            gap: 1.5rem;
        }

        .order-card {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .order-info h3 {
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .order-meta {
            display: flex;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fefcbf; color: var(--warning); }
        .status-approved { background: #c6f6d5; color: var(--success); }
        .status-completed { background: #bee3f8; color: var(--info); }
        .status-rejected { background: #fed7d7; color: var(--error); }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            margin-bottom: 0.5rem;
        }

        .detail-label {
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--text);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #2f855a;
        }

        .btn-danger {
            background: var(--error);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-info {
            background: var(--info);
            color: var(--white);
        }

        .btn-info:hover {
            background: #2c5aa0;
        }

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Today's Schedule */
        .schedule-list {
            display: grid;
            gap: 1rem;
        }

        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: 8px;
            border-left: 4px solid var(--success);
        }

        .schedule-time {
            font-weight: 600;
            color: var(--text);
            min-width: 100px;
        }

        .schedule-details {
            flex: 1;
            margin: 0 1rem;
        }

        .schedule-details strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .schedule-details span {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

            .filter-tabs {
                flex-direction: column;
            }

            .order-header {
                flex-direction: column;
                gap: 1rem;
            }

            .order-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .schedule-item {
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
                <p>Admin Dashboard</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="validasi-member.php"><i class="fas fa-users"></i> Validasi Member</a></li>
                <li><a href="validasi-pemesanan.php" class="active"><i class="fas fa-clipboard-list"></i> Validasi Pemesanan</a></li>
                <li><a href="penjadwalan.php"><i class="fas fa-calendar-alt"></i> Penjadwalan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="studios.php"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-clipboard-check"></i> Validasi Pemesanan</h1>
                <div class="user-info">
                    <span>Hari ini: <?php echo date('d M Y'); ?></span>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <a href="?status=all" class="stat-card primary" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Pemesanan</div>
                </a>

                <a href="?status=pending" class="stat-card warning" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stat-label">Menunggu Validasi</div>
                </a>

                <a href="?status=approved" class="stat-card success" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['approved_orders']; ?></div>
                    <div class="stat-label">Disetujui</div>
                </a>

                <a href="?status=completed" class="stat-card info" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </a>
            </div>

            <!-- Today's Schedule -->
            <?php if ($today_orders): ?>
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-day"></i> Jadwal Hari Ini</h2>
                    <span class="order-status status-approved">
                        <?php echo count($today_orders); ?> Sesi
                    </span>
                </div>

                <div class="schedule-list">
                    <?php foreach ($today_orders as $order): ?>
                    <div class="schedule-item">
                        <div class="schedule-time">
                            <?php echo date('H:i', strtotime($order['jam_mulai'])); ?> - 
                            <?php echo date('H:i', strtotime($order['jam_selesai'])); ?>
                        </div>
                        <div class="schedule-details">
                            <strong><?php echo htmlspecialchars($order['customer_nama']); ?></strong>
                            <span><?php echo htmlspecialchars($order['nama_studio']); ?></span>
                        </div>
                        <div class="order-status status-<?php echo $order['status']; ?>">
                            <?php echo $order['status'] === 'completed' ? 'Selesai' : 'Berlangsung'; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Menunggu
                    <?php if ($stats['pending_orders'] > 0): ?>
                    <span style="background: var(--warning); color: white; border-radius: 10px; padding: 2px 8px; font-size: 0.8rem;">
                        <?php echo $stats['pending_orders']; ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="?status=approved" class="filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check"></i> Disetujui
                </a>
                <a href="?status=completed" class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    <i class="fas fa-check-double"></i> Selesai
                </a>
                <a href="?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times"></i> Ditolak
                </a>
            </div>

            <!-- Orders List -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <?php
                        $status_titles = [
                            'all' => 'Semua Pemesanan',
                            'pending' => 'Pemesanan Menunggu Validasi',
                            'approved' => 'Pemesanan Disetujui',
                            'completed' => 'Pemesanan Selesai',
                            'rejected' => 'Pemesanan Ditolak'
                        ];
                        echo $status_titles[$status_filter];
                        ?>
                    </h2>
                    <span class="order-status status-<?php echo $status_filter === 'all' ? 'approved' : $status_filter; ?>">
                        <?php echo count($orders); ?> Pesanan
                    </span>
                </div>

                <?php if ($orders): ?>
                    <div class="orders-grid">
                        <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3><?php echo htmlspecialchars($order['nama_studio']); ?></h3>
                                    <div class="order-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($order['customer_nama']); ?></span>
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['no_telepon']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo $order['jam_mulai'] . ' - ' . $order['jam_selesai']; ?></span>
                                        <span><i class="fas fa-money-bill"></i> <?php echo formatRupiah($order['total_biaya']); ?></span>
                                    </div>
                                </div>
                                <div class="order-status status-<?php echo $order['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'Menunggu Validasi',
                                        'approved' => 'Disetujui', 
                                        'completed' => 'Selesai',
                                        'rejected' => 'Ditolak'
                                    ];
                                    echo $status_text[$order['status']]; 
                                    ?>
                                </div>
                            </div>

                            <div class="order-details">
                                <div class="detail-item">
                                    <div class="detail-label">Durasi</div>
                                    <div class="detail-value"><?php echo $order['durasi']; ?> Jam</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Metode Pembayaran</div>
                                    <div class="detail-value"><?php echo $order['metode_pembayaran'] === 'transfer' ? 'Transfer Bank' : 'Tunai'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Tanggal Pesan</div>
                                    <div class="detail-value"><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></div>
                                </div>
                            </div>

                            <?php if ($order['catatan_khusus']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Catatan Khusus</div>
                                <div class="detail-value" style="background: var(--light-bg); padding: 0.75rem; border-radius: 5px; margin-top: 0.5rem;">
                                    <?php echo htmlspecialchars($order['catatan_khusus']); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <a href="validasi-pemesanan.php?action=approve&id=<?php echo $order['pesanan_id']; ?>" 
                                       class="btn btn-success"
                                       onclick="return confirm('Setujui pemesanan dari <?php echo htmlspecialchars($order['customer_nama']); ?>?')">
                                        <i class="fas fa-check"></i> Setujui
                                    </a>
                                    <a href="validasi-pemesanan.php?action=reject&id=<?php echo $order['pesanan_id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Tolak pemesanan dari <?php echo htmlspecialchars($order['customer_nama']); ?>?')">
                                        <i class="fas fa-times"></i> Tolak
                                    </a>
                                <?php elseif ($order['status'] === 'approved'): ?>
                                    <a href="validasi-pemesanan.php?action=complete&id=<?php echo $order['pesanan_id']; ?>" 
                                       class="btn btn-info"
                                       onclick="return confirm('Tandai pemesanan dari <?php echo htmlspecialchars($order['customer_nama']); ?> sebagai selesai?')">
                                        <i class="fas fa-check-double"></i> Selesai
                                    </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-secondary" 
                                        onclick="showOrderDetails(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                    <i class="fas fa-eye"></i> Detail
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Tidak ada pemesanan</h3>
                        <p>
                            <?php 
                            $empty_messages = [
                                'all' => 'Belum ada pemesanan yang dibuat',
                                'pending' => 'Tidak ada pemesanan yang menunggu validasi',
                                'approved' => 'Tidak ada pemesanan yang disetujui',
                                'completed' => 'Tidak ada pemesanan yang selesai',
                                'rejected' => 'Tidak ada pemesanan yang ditolak'
                            ];
                            echo $empty_messages[$status_filter];
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 id="modalTitle">Detail Pemesanan</h3>
                <button onclick="closeOrderModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="modalContent">
                <!-- Content will be filled by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Order details modal
        function showOrderDetails(order) {
            const modal = document.getElementById('orderModal');
            const modalContent = document.getElementById('modalContent');
            
            const statusText = {
                'pending': 'Menunggu Validasi',
                'approved': 'Disetujui', 
                'completed': 'Selesai',
                'rejected': 'Ditolak'
            };
            
            const paymentText = {
                'transfer': 'Transfer Bank',
                'tunai': 'Tunai'
            };
            
            modalContent.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <div class="detail-label">Customer</div>
                        <div class="detail-value"><strong>${order.customer_nama}</strong></div>
                    </div>
                    <div>
                        <div class="detail-label">Telepon</div>
                        <div class="detail-value">${order.no_telepon}</div>
                    </div>
                    <div>
                        <div class="detail-label">Studio</div>
                        <div class="detail-value">${order.nama_studio}</div>
                    </div>
                    <div>
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="order-status status-${order.status}">
                                ${statusText[order.status]}
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="detail-label">Tanggal Sesi</div>
                        <div class="detail-value">${new Date(order.tanggal_sesi).toLocaleDateString('id-ID')}</div>
                    </div>
                    <div>
                        <div class="detail-label">Waktu</div>
                        <div class="detail-value">${order.jam_mulai} - ${order.jam_selesai}</div>
                    </div>
                    <div>
                        <div class="detail-label">Durasi</div>
                        <div class="detail-value">${order.durasi} Jam</div>
                    </div>
                    <div>
                        <div class="detail-label">Total Biaya</div>
                        <div class="detail-value"><strong>Rp ${parseInt(order.total_biaya).toLocaleString()}</strong></div>
                    </div>
                    <div>
                        <div class="detail-label">Metode Pembayaran</div>
                        <div class="detail-value">${paymentText[order.metode_pembayaran]}</div>
                    </div>
                    <div>
                        <div class="detail-label">Tanggal Pesan</div>
                        <div class="detail-value">${new Date(order.created_at).toLocaleString('id-ID')}</div>
                    </div>
                </div>
                
                ${order.catatan_khusus ? `
                <div style="margin-top: 1rem;">
                    <div class="detail-label">Catatan Khusus</div>
                    <div style="background: #f7fafc; padding: 1rem; border-radius: 5px; margin-top: 0.5rem;">
                        ${order.catatan_khusus}
                    </div>
                </div>
                ` : ''}
            `;
            
            modal.style.display = 'flex';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });
    </script>
</body>
</html>