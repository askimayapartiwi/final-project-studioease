<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

$customer_id = $_SESSION['customer_id'];

// Get all orders for this customer
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, s.harga_per_jam 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.customer_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll();

// Get order statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_orders
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
    <title>Data Pemesanan - StudioEase</title>
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

        /* Orders Section */
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

        /* Order Cards */
        .orders-list {
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
        .status-completed { background: #bee3f8; color: var(--primary); }
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

        .order-timeline {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .timeline-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e2e8f0;
        }

        .timeline-dot.active {
            background: var(--success);
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.8rem;
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

            .order-header {
                flex-direction: column;
                gap: 1rem;
            }

            .order-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .order-actions {
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
                <p>Customer Dashboard</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="pesan.php"><i class="fas fa-calendar-plus"></i> Pesan Studio</a></li>
                <li><a href="pemesanan.php" class="active"><i class="fas fa-list"></i> Data Pemesanan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-list"></i> Data Pemesanan Saya</h1>
                <div class="user-info">
                    <a href="pesan.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Pesan Baru
                    </a>
                </div>
            </div>

            <?php displayMessage(); ?>

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

                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['approved_orders'] ?? 0; ?></div>
                    <div class="stat-label">Disetujui</div>
                </div>
            </div>

            <!-- Orders List -->
            <div class="section">
                <div class="section-header">
                    <h2>Semua Pemesanan</h2>
                    <span class="order-status status-approved">
                        <?php echo count($orders); ?> Pesanan
                    </span>
                </div>

                <?php if ($orders): ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3><?php echo htmlspecialchars($order['nama_studio']); ?></h3>
                                    <div class="order-meta">
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
                                        'rejected' => 'Ditolak',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    echo $status_text[$order['status']] ?? $order['status']; 
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

                            <div class="order-timeline">
                                <div class="timeline-item">
                                    <div class="timeline-dot <?php echo $order['status'] !== 'pending' ? 'active' : ''; ?>"></div>
                                    <span>Pemesanan dibuat - <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></span>
                                </div>
                                
                                <?php if ($order['status'] === 'approved' || $order['status'] === 'completed'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot active"></div>
                                    <span>Disetujui oleh admin</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'completed'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot active"></div>
                                    <span>Sesi foto selesai</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'rejected'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot" style="background: var(--error);"></div>
                                    <span>Pemesanan ditolak</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="order-actions">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <a href="edit-pemesanan.php?id=<?php echo $order['pesanan_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="batalkan-pemesanan.php?id=<?php echo $order['pesanan_id']; ?>" class="btn btn-danger btn-sm"
                                       onclick="return confirm('Batalkan pemesanan ini?')">
                                        <i class="fas fa-times"></i> Batalkan
                                    </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-secondary btn-sm" 
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
                        <h3>Belum ada pemesanan</h3>
                        <p>Mulai dengan membuat pemesanan studio pertama Anda</p>
                        <a href="pesan.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Pesan Studio Sekarang
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3>Detail Pemesanan</h3>
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
            
            modalContent.innerHTML = `
                <div style="display: grid; gap: 1rem;">
                    <div>
                        <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Studio</div>
                        <div style="color: #2d3748; font-size: 1.1rem;"><strong>${order.nama_studio}</strong></div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Tanggal</div>
                            <div style="color: #2d3748;">${new Date(order.tanggal_sesi).toLocaleDateString('id-ID')}</div>
                        </div>
                        <div>
                            <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Waktu</div>
                            <div style="color: #2d3748;">${order.jam_mulai} - ${order.jam_selesai}</div>
                        </div>
                        <div>
                            <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Durasi</div>
                            <div style="color: #2d3748;">${order.durasi} Jam</div>
                        </div>
                        <div>
                            <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Status</div>
                            <div>
                                <span class="order-status status-${order.status}" style="display: inline-block;">
                                    ${statusText[order.status]}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Total Biaya</div>
                        <div style="color: #2d3748; font-size: 1.2rem; font-weight: bold;">
                            Rp ${parseInt(order.total_biaya).toLocaleString()}
                        </div>
                    </div>
                    ${order.catatan_khusus ? `
                    <div>
                        <div style="font-weight: 500; color: #718096; font-size: 0.9rem;">Catatan Khusus</div>
                        <div style="background: #f7fafc; padding: 1rem; border-radius: 5px; margin-top: 0.5rem;">
                            ${order.catatan_khusus}
                        </div>
                    </div>
                    ` : ''}
                </div>
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