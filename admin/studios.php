<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Handle studio actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $studio_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'delete') {
        // Check if studio has active orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as active_orders FROM pesanan WHERE studio_id = ? AND status IN ('pending', 'approved')");
        $stmt->execute([$studio_id]);
        $result = $stmt->fetch();
        
        if ($result['active_orders'] > 0) {
            showMessage('Tidak dapat menghapus studio yang memiliki pemesanan aktif!', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE studios SET status = 'inactive' WHERE studio_id = ?");
            $stmt->execute([$studio_id]);
            showMessage('Studio berhasil dinonaktifkan!', 'success');
        }
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE studios SET status = 'active' WHERE studio_id = ?");
        $stmt->execute([$studio_id]);
        showMessage('Studio berhasil diaktifkan!', 'success');
    }
    
    redirect('studios.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_studio'])) {
        $nama_studio = trim($_POST['nama_studio']);
        $harga_per_jam = $_POST['harga_per_jam'];
        $fasilitas = trim($_POST['fasilitas']);
        
        if (empty($nama_studio) || empty($harga_per_jam) || empty($fasilitas)) {
            showMessage('Semua field harus diisi!', 'error');
        } else {
            $stmt = $pdo->prepare("INSERT INTO studios (nama_studio, harga_per_jam, fasilitas) VALUES (?, ?, ?)");
            $stmt->execute([$nama_studio, $harga_per_jam, $fasilitas]);
            showMessage('Studio berhasil ditambahkan!', 'success');
            redirect('studios.php');
        }
    } elseif (isset($_POST['edit_studio'])) {
        $studio_id = $_POST['studio_id'];
        $nama_studio = trim($_POST['nama_studio']);
        $harga_per_jam = $_POST['harga_per_jam'];
        $fasilitas = trim($_POST['fasilitas']);
        $status = $_POST['status'];
        
        if (empty($nama_studio) || empty($harga_per_jam) || empty($fasilitas)) {
            showMessage('Semua field harus diisi!', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE studios SET nama_studio = ?, harga_per_jam = ?, fasilitas = ?, status = ? WHERE studio_id = ?");
            $stmt->execute([$nama_studio, $harga_per_jam, $fasilitas, $status, $studio_id]);
            showMessage('Studio berhasil diperbarui!', 'success');
            redirect('studios.php');
        }
    }
}

// Get all studios
$stmt = $pdo->query("SELECT * FROM studios ORDER BY status DESC, nama_studio ASC");
$studios = $stmt->fetchAll();

// Get studio statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_studios,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_studios,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_studios,
        COALESCE(AVG(harga_per_jam), 0) as avg_price
    FROM studios
");
$stats = $stmt->fetch();

// Get studio utilization
$stmt = $pdo->query("
    SELECT 
        s.studio_id,
        s.nama_studio,
        COUNT(p.pesanan_id) as total_bookings,
        COALESCE(SUM(p.durasi), 0) as total_hours,
        COALESCE(SUM(p.total_biaya), 0) as total_revenue
    FROM studios s 
    LEFT JOIN pesanan p ON s.studio_id = p.studio_id 
    AND p.status IN ('approved', 'completed')
    AND p.tanggal_sesi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.studio_id, s.nama_studio
    ORDER BY total_bookings DESC
");
$studio_utilization = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Studio - StudioEase</title>
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
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header h2 {
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Buttons */
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

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-warning:hover {
            background: #b7791f;
        }

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Studio Grid */
        .studio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .studio-card {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .studio-card:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .studio-card.inactive {
            border-color: var(--text-light);
            opacity: 0.7;
            background: #f7fafc;
        }

        .studio-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .studio-info h3 {
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .studio-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            text-align: right;
        }

        .studio-features {
            list-style: none;
            margin-bottom: 1rem;
        }

        .studio-features li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .studio-features i {
            color: var(--success);
        }

        .studio-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active { background: #c6f6d5; color: var(--success); }
        .status-inactive { background: #fed7d7; color: var(--error); }

        .studio-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
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

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Utilization Table */
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

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            color: var(--text);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
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

            .studio-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .studio-header {
                flex-direction: column;
                gap: 1rem;
            }

            .studio-actions {
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
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="penjadwalan.php"><i class="fas fa-calendar-alt"></i> Penjadwalan</a></li>
                <li><a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="studios.php" class="active"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-building"></i> Kelola Studio</h1>
                <button class="btn btn-primary" onclick="showAddStudioModal()">
                    <i class="fas fa-plus"></i> Tambah Studio
                </button>
            </div>

            <?php displayMessage(); ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_studios']; ?></div>
                    <div class="stat-label">Total Studio</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['active_studios']; ?></div>
                    <div class="stat-label">Aktif</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['inactive_studios']; ?></div>
                    <div class="stat-label">Nonaktif</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($stats['avg_price']); ?></div>
                    <div class="stat-label">Rata-rata Harga</div>
                </div>
            </div>

            <!-- Studio Utilization -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-line"></i> Utilisasi Studio (30 Hari Terakhir)</h2>
                </div>

                <?php if ($studio_utilization): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Studio</th>
                                    <th>Total Booking</th>
                                    <th>Total Jam</th>
                                    <th>Total Revenue</th>
                                    <th>Tingkat Aktivitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $max_bookings = max(array_column($studio_utilization, 'total_bookings'));
                                foreach ($studio_utilization as $util): 
                                    $activity_level = $max_bookings > 0 ? ($util['total_bookings'] / $max_bookings) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($util['nama_studio']); ?></strong></td>
                                    <td><?php echo $util['total_bookings']; ?></td>
                                    <td><?php echo $util['total_hours']; ?> Jam</td>
                                    <td><strong><?php echo formatRupiah($util['total_revenue']); ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $activity_level; ?>%; background: <?php echo $activity_level > 70 ? 'var(--success)' : ($activity_level > 40 ? 'var(--warning)' : 'var(--error)'); ?>"></div>
                                            </div>
                                            <span><?php echo round($activity_level); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h3>Belum ada data utilisasi</h3>
                        <p>Data utilisasi akan muncul setelah ada pemesanan</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Studios List -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> Daftar Studio</h2>
                    <span style="color: var(--text-light);">
                        <?php echo count($studios); ?> Studio
                    </span>
                </div>

                <?php if ($studios): ?>
                    <div class="studio-grid">
                        <?php foreach ($studios as $studio): ?>
                        <div class="studio-card <?php echo $studio['status'] === 'inactive' ? 'inactive' : ''; ?>">
                            <div class="studio-header">
                                <div class="studio-info">
                                    <h3><?php echo htmlspecialchars($studio['nama_studio']); ?></h3>
                                    <span class="studio-status status-<?php echo $studio['status']; ?>">
                                        <?php echo $studio['status'] === 'active' ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </div>
                                <div class="studio-price">
                                    <?php echo formatRupiah($studio['harga_per_jam']); ?>/jam
                                </div>
                            </div>

                            <ul class="studio-features">
                                <?php 
                                $features = explode(',', $studio['fasilitas']);
                                foreach ($features as $feature): 
                                ?>
                                <li><i class="fas fa-check"></i> <?php echo trim($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="studio-actions">
                                <button class="btn btn-secondary btn-sm" onclick="editStudio(<?php echo htmlspecialchars(json_encode($studio)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <?php if ($studio['status'] === 'active'): ?>
                                    <a href="studios.php?action=delete&id=<?php echo $studio['studio_id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Nonaktifkan studio <?php echo htmlspecialchars($studio['nama_studio']); ?>?')">
                                        <i class="fas fa-pause"></i> Nonaktifkan
                                    </a>
                                <?php else: ?>
                                    <a href="studios.php?action=activate&id=<?php echo $studio['studio_id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-play"></i> Aktifkan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h3>Belum ada studio</h3>
                        <p>Mulai dengan menambahkan studio pertama Anda</p>
                        <button class="btn btn-primary" onclick="showAddStudioModal()" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Tambah Studio
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Studio Modal -->
    <div id="addStudioModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Tambah Studio Baru</h3>
                <button class="close-modal" onclick="closeAddStudioModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama_studio"><i class="fas fa-building"></i> Nama Studio</label>
                        <input type="text" id="nama_studio" name="nama_studio" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="harga_per_jam"><i class="fas fa-money-bill-wave"></i> Harga per Jam</label>
                        <input type="number" id="harga_per_jam" name="harga_per_jam" class="form-control" min="0" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="fasilitas"><i class="fas fa-list"></i> Fasilitas</label>
                        <textarea id="fasilitas" name="fasilitas" class="form-control" required placeholder="Pisahkan setiap fasilitas dengan koma. Contoh: Background polos, Lighting dasar, 1 set properti"></textarea>
                        <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                            Pisahkan setiap fasilitas dengan koma
                        </small>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddStudioModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="add_studio" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Studio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Studio Modal -->
    <div id="editStudioModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Studio</h3>
                <button class="close-modal" onclick="closeEditStudioModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_studio_id" name="studio_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_nama_studio"><i class="fas fa-building"></i> Nama Studio</label>
                        <input type="text" id="edit_nama_studio" name="nama_studio" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_harga_per_jam"><i class="fas fa-money-bill-wave"></i> Harga per Jam</label>
                        <input type="number" id="edit_harga_per_jam" name="harga_per_jam" class="form-control" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="edit_fasilitas"><i class="fas fa-list"></i> Fasilitas</label>
                        <textarea id="edit_fasilitas" name="fasilitas" class="form-control" required placeholder="Pisahkan setiap fasilitas dengan koma"></textarea>
                        <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                            Pisahkan setiap fasilitas dengan koma
                        </small>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditStudioModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" name="edit_studio" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Studio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddStudioModal() {
            document.getElementById('addStudioModal').style.display = 'flex';
        }

        function closeAddStudioModal() {
            document.getElementById('addStudioModal').style.display = 'none';
        }

        function showEditStudioModal() {
            document.getElementById('editStudioModal').style.display = 'flex';
        }

        function closeEditStudioModal() {
            document.getElementById('editStudioModal').style.display = 'none';
        }

        function editStudio(studio) {
            document.getElementById('edit_studio_id').value = studio.studio_id;
            document.getElementById('edit_nama_studio').value = studio.nama_studio;
            document.getElementById('edit_harga_per_jam').value = studio.harga_per_jam;
            document.getElementById('edit_fasilitas').value = studio.fasilitas;
            document.getElementById('edit_status').value = studio.status;
            showEditStudioModal();
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Format price input
        document.getElementById('harga_per_jam')?.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        document.getElementById('edit_harga_per_jam')?.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Auto-focus on first input when modal opens
        document.getElementById('addStudioModal')?.addEventListener('shown', function() {
            document.getElementById('nama_studio').focus();
        });

        document.getElementById('editStudioModal')?.addEventListener('shown', function() {
            document.getElementById('edit_nama_studio').focus();
        });
    </script>
</body>
</html>