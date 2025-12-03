<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Handle payment verification
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pembayaran_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'verify') {
        $status = 'paid';
        $message = 'Pembayaran berhasil diverifikasi!';
        
        // Update payment status
        $stmt = $pdo->prepare("UPDATE pembayaran SET status = ?, tanggal_pembayaran = NOW() WHERE pembayaran_id = ?");
        $stmt->execute([$status, $pembayaran_id]);
        
        // Update order status to approved
        $stmt = $pdo->prepare("
            UPDATE pesanan p 
            JOIN pembayaran py ON p.pesanan_id = py.pesanan_id 
            SET p.status = 'approved' 
            WHERE py.pembayaran_id = ?
        ");
        $stmt->execute([$pembayaran_id]);
        
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $message = 'Pembayaran ditolak!';
        
        $stmt = $pdo->prepare("UPDATE pembayaran SET status = ? WHERE pembayaran_id = ?");
        $stmt->execute([$status, $pembayaran_id]);
    }
    
    showMessage($message, 'success');
    redirect('pembayaran.php');
}

// Get pending payments
$stmt = $pdo->prepare("
    SELECT py.*, p.tanggal_sesi, s.nama_studio, u.nama as customer_nama, p.total_biaya, p.pesanan_id
    FROM pembayaran py 
    JOIN pesanan p ON py.pesanan_id = p.pesanan_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    WHERE py.status = 'pending'
    ORDER BY py.created_at DESC
");
$stmt->execute();
$pending_payments = $stmt->fetchAll();

// Get all payments
$status_filter = $_GET['status'] ?? 'all';
$query_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $query_conditions[] = "py.status = ?";
    $params[] = $status_filter;
}

$where_clause = $query_conditions ? "WHERE " . implode(" AND ", $query_conditions) : "";

$stmt = $pdo->prepare("
    SELECT py.*, p.tanggal_sesi, s.nama_studio, u.nama as customer_nama, p.total_biaya, p.pesanan_id
    FROM pembayaran py 
    JOIN pesanan p ON py.pesanan_id = p.pesanan_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    $where_clause
    ORDER BY py.created_at DESC
");
$stmt->execute($params);
$all_payments = $stmt->fetchAll();

// Statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_payments,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_payments,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN jumlah ELSE 0 END), 0) as total_revenue
    FROM pembayaran
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran - StudioEase</title>
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

        /* Payment Cards */
        .payment-cards {
            display: grid;
            gap: 1.5rem;
        }

        .payment-card {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .payment-card:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .payment-info h3 {
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .payment-meta {
            display: flex;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .payment-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }

        .payment-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending { background: #fefcbf; color: var(--warning); }
        .status-paid { background: #c6f6d5; color: var(--success); }
        .status-rejected { background: #fed7d7; color: var(--error); }

        .payment-details {
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
            margin-top: 1rem;
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

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Payment Image */
        .payment-image {
            max-width: 300px;
            max-height: 200px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-image:hover {
            border-color: var(--primary);
            transform: scale(1.02);
        }

        /* History Table */
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

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .image-modal img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
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

            .payment-header {
                flex-direction: column;
                gap: 1rem;
            }

            .payment-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .table {
                font-size: 0.9rem;
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
                <li><a href="validasi-pemesanan.php"><i class="fas fa-clipboard-list"></i> Validasi Pemesanan</a></li>
                <li><a href="pembayaran.php" class="active"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="penjadwalan.php"><i class="fas fa-calendar-alt"></i> Penjadwalan</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="studios.php"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-money-check"></i> Verifikasi Pembayaran</h1>
                <div class="user-info">
                    <span>Hari ini: <?php echo date('d M Y'); ?></span>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <a href="?status=all" class="stat-card primary" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon primary">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_payments']; ?></div>
                    <div class="stat-label">Total Pembayaran</div>
                </a>

                <a href="?status=pending" class="stat-card warning" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_payments']; ?></div>
                    <div class="stat-label">Menunggu Verifikasi</div>
                </a>

                <a href="?status=paid" class="stat-card success" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['paid_payments']; ?></div>
                    <div class="stat-label">Terverifikasi</div>
                </a>

                <a href="?status=all" class="stat-card info" style="text-decoration: none; color: inherit;">
                    <div class="stat-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </a>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Semua
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Menunggu
                    <?php if ($stats['pending_payments'] > 0): ?>
                    <span style="background: var(--warning); color: white; border-radius: 10px; padding: 2px 8px; font-size: 0.8rem;">
                        <?php echo $stats['pending_payments']; ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="?status=paid" class="filter-tab <?php echo $status_filter === 'paid' ? 'active' : ''; ?>">
                    <i class="fas fa-check"></i> Terverifikasi
                </a>
                <a href="?status=rejected" class="filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times"></i> Ditolak
                </a>
            </div>

            <!-- Pending Payments Section -->
            <?php if ($status_filter === 'all' || $status_filter === 'pending'): ?>
            <div class="content-section">
                <div class="section-header">
                    <h2>Pembayaran Menunggu Verifikasi</h2>
                    <span class="payment-status status-pending">
                        <?php echo count($pending_payments); ?> Pembayaran
                    </span>
                </div>

                <?php if ($pending_payments): ?>
                    <div class="payment-cards">
                        <?php foreach ($pending_payments as $payment): ?>
                        <div class="payment-card">
                            <div class="payment-header">
                                <div class="payment-info">
                                    <h3><?php echo htmlspecialchars($payment['customer_nama']); ?></h3>
                                    <div class="payment-meta">
                                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($payment['nama_studio']); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($payment['tanggal_sesi'])); ?></span>
                                        <span><i class="fas fa-money-bill"></i> <?php echo formatRupiah($payment['jumlah']); ?></span>
                                        <span><i class="fas fa-credit-card"></i> <?php echo $payment['metode'] === 'transfer' ? 'Transfer Bank' : 'Tunai'; ?></span>
                                    </div>
                                </div>
                                <div class="payment-amount">
                                    <?php echo formatRupiah($payment['jumlah']); ?>
                                </div>
                            </div>

                            <div class="payment-details">
                                <div class="detail-item">
                                    <div class="detail-label">Tanggal Pembayaran</div>
                                    <div class="detail-value"><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Metode</div>
                                    <div class="detail-value">
                                        <?php echo $payment['metode'] === 'transfer' ? 'Transfer Bank' : 'Tunai'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <span class="payment-status status-<?php echo $payment['status']; ?>">
                                            Menunggu Verifikasi
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <?php if ($payment['metode'] === 'transfer' && $payment['bukti_transfer']): ?>
                            <div class="detail-item">
                                <div class="detail-label">Bukti Transfer</div>
                                <div class="detail-value">
                                    <img src="../uploads/bukti-transfer/<?php echo $payment['bukti_transfer']; ?>" 
                                         alt="Bukti Transfer" class="payment-image"
                                         onclick="showImage('../uploads/bukti-transfer/<?php echo $payment['bukti_transfer']; ?>')">
                                    <br>
                                    <small style="color: var(--text-light);">
                                        Klik gambar untuk memperbesar
                                    </small>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <?php if ($payment['status'] === 'pending'): ?>
                                    <a href="pembayaran.php?action=verify&id=<?php echo $payment['pembayaran_id']; ?>" 
                                       class="btn btn-success"
                                       onclick="return confirm('Verifikasi pembayaran dari <?php echo htmlspecialchars($payment['customer_nama']); ?>?')">
                                        <i class="fas fa-check"></i> Verifikasi
                                    </a>
                                    <a href="pembayaran.php?action=reject&id=<?php echo $payment['pembayaran_id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Tolak pembayaran dari <?php echo htmlspecialchars($payment['customer_nama']); ?>?')">
                                        <i class="fas fa-times"></i> Tolak
                                    </a>
                                <?php endif; ?>
                                
                                <a href="validasi-pemesanan.php" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> Lihat Pemesanan
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>Tidak ada pembayaran yang menunggu verifikasi</h3>
                        <p>Semua pembayaran telah diverifikasi</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- All Payments Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <?php
                        $status_titles = [
                            'all' => 'Semua Pembayaran',
                            'pending' => 'Pembayaran Menunggu Verifikasi',
                            'paid' => 'Pembayaran Terverifikasi',
                            'rejected' => 'Pembayaran Ditolak'
                        ];
                        echo $status_titles[$status_filter];
                        ?>
                    </h2>
                    <span class="payment-status status-<?php echo $status_filter === 'all' ? 'paid' : $status_filter; ?>">
                        <?php echo count($all_payments); ?> Transaksi
                    </span>
                </div>

                <?php if ($all_payments): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Customer</th>
                                    <th>Studio</th>
                                    <th>Metode</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_payments as $payment): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d M Y', strtotime($payment['created_at'])); ?>
                                        <div style="font-size: 0.8rem; color: var(--text-light);">
                                            <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['customer_nama']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['nama_studio']); ?></td>
                                    <td>
                                        <?php echo $payment['metode'] === 'transfer' ? 'Transfer Bank' : 'Tunai'; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo formatRupiah($payment['jumlah']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="payment-status status-<?php echo $payment['status']; ?>">
                                            <?php 
                                            $status_text = [
                                                'pending' => 'Menunggu',
                                                'paid' => 'Lunas',
                                                'rejected' => 'Ditolak'
                                            ];
                                            echo $status_text[$payment['status']]; 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($payment['status'] === 'pending'): ?>
                                                <a href="pembayaran.php?action=verify&id=<?php echo $payment['pembayaran_id']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="pembayaran.php?action=reject&id=<?php echo $payment['pembayaran_id']; ?>" 
                                                   class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($payment['metode'] === 'transfer' && $payment['bukti_transfer']): ?>
                                                <button class="btn btn-secondary btn-sm" 
                                                        onclick="showImage('../uploads/bukti-transfer/<?php echo $payment['bukti_transfer']; ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>Belum ada riwayat pembayaran</h3>
                        <p>Riwayat pembayaran akan muncul di sini</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="" alt="Bukti Transfer">
    </div>

    <script>
        // Image modal functionality
        function showImage(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').style.display = 'flex';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Tab functionality for filter tabs
        document.addEventListener('DOMContentLoaded', function() {
            const currentStatus = '<?php echo $status_filter; ?>';
            if (currentStatus !== 'all') {
                // Scroll to relevant section
                setTimeout(() => {
                    document.querySelector('.content-section').scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 100);
            }
        });
    </script>
</body>
</html>