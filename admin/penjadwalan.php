<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Handle schedule actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pesanan_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE pesanan SET status = 'completed' WHERE pesanan_id = ?");
        $stmt->execute([$pesanan_id]);
        showMessage('Sesi pemotretan telah diselesaikan!', 'success');
    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE pesanan SET status = 'cancelled' WHERE pesanan_id = ?");
        $stmt->execute([$pesanan_id]);
        showMessage('Sesi pemotretan telah dibatalkan!', 'success');
    }
    
    redirect('penjadwalan.php');
}

// Get date filter
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_studio = $_GET['studio'] ?? 'all';

// Get approved orders for scheduling
$query_conditions = ["p.status = 'approved'"];
$params = [];

if ($selected_date !== 'all') {
    $query_conditions[] = "p.tanggal_sesi = ?";
    $params[] = $selected_date;
}

if ($selected_studio !== 'all') {
    $query_conditions[] = "p.studio_id = ?";
    $params[] = $selected_studio;
}

$where_clause = $query_conditions ? "WHERE " . implode(" AND ", $query_conditions) : "";

$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, u.nama as customer_nama, c.no_telepon 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    $where_clause
    ORDER BY p.tanggal_sesi, p.jam_mulai
");
$stmt->execute($params);
$scheduled_orders = $stmt->fetchAll();

// Get all studios for filter
$stmt = $pdo->query("SELECT * FROM studios WHERE status = 'active'");
$studios = $stmt->fetchAll();

// Get today's schedule for quick overview
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, u.nama as customer_nama 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    WHERE p.tanggal_sesi = CURDATE() 
    AND p.status IN ('approved', 'completed')
    ORDER BY p.jam_mulai ASC
");
$stmt->execute();
$today_schedule = $stmt->fetchAll();

// Get upcoming schedule (next 7 days)
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, u.nama as customer_nama 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    WHERE p.tanggal_sesi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND p.status = 'approved'
    ORDER BY p.tanggal_sesi, p.jam_mulai
    LIMIT 10
");
$stmt->execute();
$upcoming_schedule = $stmt->fetchAll();

// Statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_scheduled,
        COUNT(CASE WHEN tanggal_sesi = CURDATE() THEN 1 END) as today_sessions,
        COUNT(CASE WHEN tanggal_sesi = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 END) as tomorrow_sessions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_sessions
    FROM pesanan 
    WHERE status IN ('approved', 'completed')
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjadwalan - StudioEase</title>
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

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Filter Section */
        .filter-section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

        /* Content Sections */
        .content-section {
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

        /* Schedule Grid */
        .schedule-grid {
            display: grid;
            gap: 1.5rem;
        }

        .schedule-card {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .schedule-card:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .schedule-card.completed {
            border-color: var(--success);
            background: linear-gradient(135deg, #f0fff4, #ffffff);
        }

        .schedule-card.ongoing {
            border-color: var(--info);
            background: linear-gradient(135deg, #f0f4ff, #ffffff);
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .schedule-info h3 {
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .schedule-meta {
            display: flex;
            gap: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .schedule-time {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
            text-align: right;
        }

        .schedule-details {
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

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.8rem;
        }

        /* Timeline */
        .timeline {
            position: relative;
            margin: 2rem 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2rem;
            position: relative;
        }

        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            margin-right: 1rem;
            position: relative;
            z-index: 2;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-left: 1rem;
        }

        .timeline-time {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
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

        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-scheduled { background: #bee3f8; color: var(--info); }
        .status-completed { background: #c6f6d5; color: var(--success); }
        .status-ongoing { background: #fefcbf; color: var(--warning); }

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

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .schedule-header {
                flex-direction: column;
                gap: 1rem;
            }

            .schedule-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .timeline::before {
                left: 15px;
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
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="penjadwalan.php" class="active"><i class="fas fa-calendar-alt"></i> Penjadwalan</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="studios.php"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-calendar-alt"></i> Penjadwalan Studio</h1>
                <div class="user-info">
                    <span><?php echo date('d F Y'); ?></span>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_scheduled']; ?></div>
                    <div class="stat-label">Total Terjadwal</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['today_sessions']; ?></div>
                    <div class="stat-label">Sesi Hari Ini</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['tomorrow_sessions']; ?></div>
                    <div class="stat-label">Sesi Besok</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['completed_sessions']; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
            </div>

            <!-- Quick Overview -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Jadwal Hari Ini</h2>
                    <span class="status-badge status-scheduled">
                        <?php echo count($today_schedule); ?> Sesi
                    </span>
                </div>

                <?php if ($today_schedule): ?>
                    <div class="timeline">
                        <?php foreach ($today_schedule as $session): 
                            $is_ongoing = false;
                            $current_time = time();
                            $start_time = strtotime($session['jam_mulai']);
                            $end_time = strtotime($session['jam_selesai']);
                            
                            if ($current_time >= $start_time && $current_time <= $end_time) {
                                $is_ongoing = true;
                            }
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-dot" style="background: <?php echo $is_ongoing ? 'var(--warning)' : ($session['status'] === 'completed' ? 'var(--success)' : 'var(--info)'); ?>"></div>
                            <div class="timeline-content <?php echo $is_ongoing ? 'ongoing' : ''; ?>">
                                <div class="timeline-time">
                                    <?php echo date('H:i', strtotime($session['jam_mulai'])); ?> - 
                                    <?php echo date('H:i', strtotime($session['jam_selesai'])); ?>
                                </div>
                                <div style="font-weight: 600; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($session['customer_nama']); ?>
                                </div>
                                <div style="color: var(--text-light); font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($session['nama_studio']); ?> • 
                                    <?php echo $session['durasi']; ?> Jam
                                </div>
                                <?php if ($is_ongoing): ?>
                                <div class="status-badge status-ongoing" style="margin-top: 0.5rem;">
                                    <i class="fas fa-play-circle"></i> Sedang Berlangsung
                                </div>
                                <?php elseif ($session['status'] === 'completed'): ?>
                                <div class="status-badge status-completed" style="margin-top: 0.5rem;">
                                    <i class="fas fa-check-circle"></i> Selesai
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Tidak ada jadwal hari ini</h3>
                        <p>Tidak ada sesi pemotretan yang dijadwalkan untuk hari ini</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="date"><i class="fas fa-calendar-day"></i> Tanggal</label>
                            <input type="date" id="date" name="date" class="form-control" 
                                   value="<?php echo $selected_date; ?>">
                        </div>

                        <div class="form-group">
                            <label for="studio"><i class="fas fa-building"></i> Studio</label>
                            <select id="studio" name="studio" class="form-control">
                                <option value="all">Semua Studio</option>
                                <?php foreach ($studios as $studio): ?>
                                <option value="<?php echo $studio['studio_id']; ?>" 
                                        <?php echo $selected_studio == $studio['studio_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($studio['nama_studio']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="penjadwalan.php" class="btn btn-secondary">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Scheduled Sessions -->
            <div class="content-section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-list"></i> 
                        <?php 
                        if ($selected_date === 'all') {
                            echo 'Semua Jadwal';
                        } else {
                            echo 'Jadwal ' . date('d F Y', strtotime($selected_date));
                        }
                        ?>
                    </h2>
                    <span class="status-badge status-scheduled">
                        <?php echo count($scheduled_orders); ?> Sesi
                    </span>
                </div>

                <?php if ($scheduled_orders): ?>
                    <div class="schedule-grid">
                        <?php foreach ($scheduled_orders as $order): 
                            $is_today = $order['tanggal_sesi'] == date('Y-m-d');
                            $is_ongoing = false;
                            
                            if ($is_today) {
                                $current_time = time();
                                $start_time = strtotime($order['jam_mulai']);
                                $end_time = strtotime($order['jam_selesai']);
                                
                                if ($current_time >= $start_time && $current_time <= $end_time) {
                                    $is_ongoing = true;
                                }
                            }
                        ?>
                        <div class="schedule-card <?php echo $is_ongoing ? 'ongoing' : ''; ?>">
                            <div class="schedule-header">
                                <div class="schedule-info">
                                    <h3><?php echo htmlspecialchars($order['customer_nama']); ?></h3>
                                    <div class="schedule-meta">
                                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($order['nama_studio']); ?></span>
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['no_telepon']); ?></span>
                                        <span><i class="fas fa-hourglass-half"></i> <?php echo $order['durasi']; ?> Jam</span>
                                    </div>
                                </div>
                                <div class="schedule-time">
                                    <?php echo date('H:i', strtotime($order['jam_mulai'])); ?> - 
                                    <?php echo date('H:i', strtotime($order['jam_selesai'])); ?>
                                </div>
                            </div>

                            <div class="schedule-details">
                                <div class="detail-item">
                                    <div class="detail-label">Tanggal Sesi</div>
                                    <div class="detail-value">
                                        <?php echo date('d F Y', strtotime($order['tanggal_sesi'])); ?>
                                        <?php if ($is_today): ?>
                                        <span class="status-badge status-ongoing" style="margin-left: 0.5rem;">
                                            Hari Ini
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">
                                        <?php if ($is_ongoing): ?>
                                        <span class="status-badge status-ongoing">
                                            <i class="fas fa-play-circle"></i> Sedang Berlangsung
                                        </span>
                                        <?php else: ?>
                                        <span class="status-badge status-scheduled">
                                            <i class="fas fa-clock"></i> Terjadwal
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Biaya</div>
                                    <div class="detail-value">
                                        <strong><?php echo formatRupiah($order['total_biaya']); ?></strong>
                                    </div>
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
                                <?php if (!$is_ongoing && $order['status'] === 'approved'): ?>
                                    <a href="penjadwalan.php?action=complete&id=<?php echo $order['pesanan_id']; ?>" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Tandai sesi <?php echo htmlspecialchars($order['nama_studio']); ?> sebagai selesai?')">
                                        <i class="fas fa-check-double"></i> Selesai
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($order['status'] === 'approved'): ?>
                                    <a href="penjadwalan.php?action=cancel&id=<?php echo $order['pesanan_id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Batalkan sesi <?php echo htmlspecialchars($order['nama_studio']); ?>?')">
                                        <i class="fas fa-times"></i> Batalkan
                                    </a>
                                <?php endif; ?>
                                
                                <a href="validasi-pemesanan.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> Detail Pemesanan
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Tidak ada jadwal</h3>
                        <p>
                            <?php 
                            if ($selected_date === 'all') {
                                echo 'Tidak ada sesi yang terjadwal';
                            } else {
                                echo 'Tidak ada sesi yang terjadwal pada ' . date('d F Y', strtotime($selected_date));
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Schedule -->
            <?php if ($upcoming_schedule && $selected_date !== 'all'): ?>
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-week"></i> Jadwal 7 Hari ke Depan</h2>
                    <span class="status-badge status-scheduled">
                        <?php echo count($upcoming_schedule); ?> Sesi Mendatang
                    </span>
                </div>

                <div class="schedule-grid">
                    <?php foreach ($upcoming_schedule as $order): ?>
                    <div class="schedule-card">
                        <div class="schedule-header">
                            <div class="schedule-info">
                                <h3><?php echo htmlspecialchars($order['customer_nama']); ?></h3>
                                <div class="schedule-meta">
                                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($order['nama_studio']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?></span>
                                    <span><i class="fas fa-hourglass-half"></i> <?php echo $order['durasi']; ?> Jam</span>
                                </div>
                            </div>
                            <div class="schedule-time">
                                <?php echo date('H:i', strtotime($order['jam_mulai'])); ?> - 
                                <?php echo date('H:i', strtotime($order['jam_selesai'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Set default date to today if not set
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('date');
            if (!dateInput.value) {
                dateInput.value = '<?php echo date('Y-m-d'); ?>';
            }

            // Auto-refresh for ongoing sessions
            setInterval(() => {
                const ongoingSessions = document.querySelectorAll('.schedule-card.ongoing');
                if (ongoingSessions.length > 0) {
                    window.location.reload();
                }
            }, 60000); // Refresh every minute if there are ongoing sessions
        });

        // Add some animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .schedule-card.ongoing {
                animation: pulse 2s infinite;
            }
            
            .timeline-item {
                animation: fadeInUp 0.6s ease both;
            }
            
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
    </script>
</body>
</html>