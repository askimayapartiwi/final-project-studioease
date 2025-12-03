<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

$customer_id = $_SESSION['customer_id'];
$user_id = $_SESSION['user_id'];

// Get customer data
$stmt = $pdo->prepare("
    SELECT c.*, u.nama, u.email, u.created_at 
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    WHERE c.customer_id = ?
");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_telepon = trim($_POST['no_telepon']);
    $alamat = trim($_POST['alamat']);

    // Validation
    if (empty($nama) || empty($email) || empty($no_telepon) || empty($alamat)) {
        $error = 'Semua field harus diisi!';
    } else {
        try {
            $pdo->beginTransaction();

            // Update user data
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ? WHERE user_id = ?");
            $stmt->execute([$nama, $email, $user_id]);

            // Update customer data
            $stmt = $pdo->prepare("UPDATE customers SET no_telepon = ?, alamat = ? WHERE customer_id = ?");
            $stmt->execute([$no_telepon, $alamat, $customer_id]);

            $pdo->commit();

            // Update session
            $_SESSION['nama'] = $nama;
            $_SESSION['email'] = $email;

            $success = 'Profile berhasil diperbarui!';
            
            // Refresh customer data
            $stmt = $pdo->prepare("
                SELECT c.*, u.nama, u.email, u.created_at 
                FROM customers c 
                JOIN users u ON c.user_id = u.user_id 
                WHERE c.customer_id = ?
            ");
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch();

        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Gagal memperbarui profile: ' . $e->getMessage();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Semua field password harus diisi!';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Password baru dan konfirmasi password tidak sama!';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password baru minimal 6 karakter!';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $success = 'Password berhasil diubah!';
        } else {
            $error = 'Password saat ini salah!';
        }
    }
}

// Get order statistics for profile
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COALESCE(SUM(total_biaya), 0) as total_spent
    FROM pesanan 
    WHERE customer_id = ?
");
$stmt->execute([$customer_id]);
$order_stats = $stmt->fetch();

// Get recent orders
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.customer_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$stmt->execute([$customer_id]);
$recent_orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - StudioEase</title>
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

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }

        .profile-info {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255,255,255,0.3);
        }

        .profile-details h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .profile-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
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
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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

        .stat-icon.primary { background: #c3dafe; color: var(--primary); }
        .stat-icon.success { background: #c6f6d5; color: var(--success); }
        .stat-icon.warning { background: #fefcbf; color: var(--warning); }
        .stat-icon.info { background: #bee3f8; color: var(--primary); }

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

        .btn {
            padding: 12px 20px;
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

        /* Tabs */
        .tabs {
            display: flex;
            background: var(--white);
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }

        .tab {
            flex: 1;
            padding: 1rem 1.5rem;
            text-align: center;
            background: var(--white);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--text-light);
        }

        .tab.active {
            background: var(--primary);
            color: var(--white);
        }

        .tab:hover:not(.active) {
            background: var(--light-bg);
        }

        .tab-content {
            display: none;
            background: var(--white);
            border-radius: 0 0 10px 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        /* Recent Orders */
        .orders-list {
            display: grid;
            gap: 1rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .order-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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

        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fed7d7;
            color: var(--error);
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: var(--success);
            border: 1px solid #9ae6b4;
        }

        /* Verification Status */
        .verification-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-verified { background: #c6f6d5; color: var(--success); }
        .status-pending { background: #fefcbf; color: var(--warning); }
        .status-rejected { background: #fed7d7; color: var(--error); }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .profile-info {
                flex-direction: column;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .order-meta {
                flex-direction: column;
                gap: 0.5rem;
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
                <li><a href="pemesanan.php"><i class="fas fa-list"></i> Data Pemesanan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-user-cog"></i> Kelola Profile</h1>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($customer['nama'], 0, 1)); ?>
                    </div>
                    <div class="profile-details">
                        <h2><?php echo htmlspecialchars($customer['nama']); ?></h2>
                        <div class="profile-meta">
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?></span>
                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['no_telepon']); ?></span>
                            <span><i class="fas fa-calendar"></i> Member sejak <?php echo date('M Y', strtotime($customer['created_at'])); ?></span>
                        </div>
                        <div style="margin-top: 1rem;">
                            <span class="verification-status status-<?php echo $customer['status_verifikasi']; ?>">
                                <i class="fas fa-<?php echo $customer['status_verifikasi'] === 'verified' ? 'check-circle' : ($customer['status_verifikasi'] === 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                                <?php 
                                $status_text = [
                                    'verified' => 'Terverifikasi',
                                    'pending' => 'Menunggu Verifikasi',
                                    'rejected' => 'Ditolak'
                                ];
                                echo $status_text[$customer['status_verifikasi']];
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $order_stats['total_orders']; ?></div>
                    <div class="stat-label">Total Pemesanan</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $order_stats['completed_orders']; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $order_stats['pending_orders']; ?></div>
                    <div class="stat-label">Menunggu</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number"><?php echo formatRupiah($order_stats['total_spent']); ?></div>
                    <div class="stat-label">Total Pengeluaran</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="openTab('profile')">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </button>
                <button class="tab" onclick="openTab('password')">
                    <i class="fas fa-lock"></i> Ubah Password
                </button>
                <button class="tab" onclick="openTab('activity')">
                    <i class="fas fa-history"></i> Aktivitas Terbaru
                </button>
            </div>

            <!-- Edit Profile Tab -->
            <div id="profile" class="tab-content active">
                <div class="section-header">
                    <h2><i class="fas fa-user-edit"></i> Informasi Profile</h2>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nama"><i class="fas fa-user"></i> Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" class="form-control" 
                                   value="<?php echo htmlspecialchars($customer['nama']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="no_telepon"><i class="fas fa-phone"></i> Nomor Telepon</label>
                            <input type="tel" id="no_telepon" name="no_telepon" class="form-control" 
                                   value="<?php echo htmlspecialchars($customer['no_telepon']); ?>" required>
                        </div>

                        <div class="form-group full-width">
                            <label for="alamat"><i class="fas fa-map-marker-alt"></i> Alamat Lengkap</label>
                            <textarea id="alamat" name="alamat" class="form-control" rows="4" required><?php echo htmlspecialchars($customer['alamat']); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password" class="tab-content">
                <div class="section-header">
                    <h2><i class="fas fa-lock"></i> Ubah Password</h2>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-key"></i> Password Saat Ini</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-lock"></i> Password Baru</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <small style="color: var(--text-light); margin-top: 0.5rem; display: block;">
                                Password minimal 6 karakter
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Ubah Password
                    </button>
                </form>
            </div>

            <!-- Activity Tab -->
            <div id="activity" class="tab-content">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Pemesanan Terbaru</h2>
                    <a href="pemesanan.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Lihat Semua
                    </a>
                </div>

                <?php if ($recent_orders): ?>
                    <div class="orders-list">
                        <?php foreach ($recent_orders as $order): ?>
                        <div class="order-item">
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
                                    'rejected' => 'Ditolak'
                                ];
                                echo $status_text[$order['status']] ?? $order['status']; 
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-light);">
                        <i class="fas fa-inbox" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3>Belum ada pemesanan</h3>
                        <p>Mulai dengan membuat pemesanan studio pertama Anda</p>
                        <a href="pesan.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Pesan Studio Sekarang
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Account Management -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-cog"></i> Pengaturan Akun</h2>
                </div>

                <div style="display: grid; gap: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-bg); border-radius: 8px;">
                        <div>
                            <h4 style="margin-bottom: 0.5rem;">Hapus Akun</h4>
                            <p style="color: var(--text-light); font-size: 0.9rem;">
                                Menghapus akun Anda secara permanen akan menghapus semua data yang terkait.
                            </p>
                        </div>
                        <button class="btn btn-danger" onclick="showDeleteConfirmation()">
                            <i class="fas fa-trash"></i> Hapus Akun
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 400px; width: 90%;">
            <h3 style="margin-bottom: 1rem; color: var(--error);">
                <i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus Akun
            </h3>
            <p style="margin-bottom: 1.5rem; color: var(--text);">
                Apakah Anda yakin ingin menghapus akun Anda? Tindakan ini tidak dapat dibatalkan dan semua data akan dihapus secara permanen.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button class="btn btn-danger" onclick="deleteAccount()">
                    <i class="fas fa-trash"></i> Ya, Hapus Akun
                </button>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function openTab(tabName) {
            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show the specific tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to the clicked tab
            event.currentTarget.classList.add('active');
        }

        // Delete account modal
        function showDeleteConfirmation() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function deleteAccount() {
            if (confirm('Apakah Anda benar-benar yakin? Tindakan ini TIDAK DAPAT DIBATALKAN!')) {
                // Redirect to delete account script
                window.location.href = 'delete-account.php';
            }
        }

        // Password strength check
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (!strengthIndicator) {
                const strengthDiv = document.createElement('div');
                strengthDiv.id = 'password-strength';
                strengthDiv.style.marginTop = '0.5rem';
                strengthDiv.style.fontSize = '0.8rem';
                this.parentNode.appendChild(strengthDiv);
            }
            
            const indicator = document.getElementById('password-strength');
            let strength = 0;
            let message = '';
            let color = 'var(--error)';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Lemah';
                    color = 'var(--error)';
                    break;
                case 2:
                    message = 'Cukup';
                    color = 'var(--warning)';
                    break;
                case 3:
                    message = 'Baik';
                    color = 'var(--info)';
                    break;
                case 4:
                    message = 'Sangat Baik';
                    color = 'var(--success)';
                    break;
            }
            
            indicator.innerHTML = `Kekuatan password: <strong style="color: ${color}">${message}</strong>`;
        });

        // Close modal when clicking outside
        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>