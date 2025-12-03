<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

$user_id = $_SESSION['user_id'];

// Get admin data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$admin = $stmt->fetch();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);

    // Validation
    if (empty($nama) || empty($email)) {
        $error = 'Semua field harus diisi!';
    } else {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            
            if ($stmt->fetch()) {
                $error = 'Email sudah digunakan oleh user lain!';
            } else {
                // Update user data
                $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$nama, $email, $user_id]);

                // Update session
                $_SESSION['nama'] = $nama;
                $_SESSION['email'] = $email;

                $success = 'Profile berhasil diperbarui!';
                
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $admin = $stmt->fetch();
            }
        } catch(PDOException $e) {
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

// Get admin statistics for dashboard
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM customers) as total_customers,
        (SELECT COUNT(*) FROM pesanan WHERE DATE(created_at) = CURDATE()) as today_orders,
        (SELECT COUNT(*) FROM pesanan WHERE status = 'pending') as pending_orders,
        (SELECT COUNT(*) FROM pembayaran WHERE status = 'pending') as pending_payments,
        (SELECT COALESCE(SUM(total_biaya), 0) FROM pesanan WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as today_revenue
");
$admin_stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Admin - StudioEase</title>
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

        /* Admin Profile Header */
        .admin-profile-header {
            background: linear-gradient(135deg, #2d3748, #4a5568);
            color: var(--white);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .admin-profile-header::before {
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

        /* Admin Stats Grid */
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .admin-stat {
            text-align: center;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .admin-stat:hover {
            background: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

        .admin-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .admin-stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
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

        /* System Info */
        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .info-item {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .info-label {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--text);
        }

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

            .admin-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .system-info {
                grid-template-columns: 1fr;
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
                <li><a href="studios.php"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="profile.php" class="active"><i class="fas fa-user-cog"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-user-shield"></i> Profile Administrator</h1>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>

            <!-- Admin Profile Header -->
            <div class="admin-profile-header">
                <div class="profile-info">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-details">
                        <h2><?php echo htmlspecialchars($admin['nama']); ?></h2>
                        <div class="profile-meta">
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin['email']); ?></span>
                            <span><i class="fas fa-user-tag"></i> Administrator</span>
                            <span><i class="fas fa-calendar"></i> Bergabung <?php echo date('d M Y', strtotime($admin['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Admin Quick Stats -->
                <div class="admin-stats-grid">
                    <div class="admin-stat">
                        <div class="admin-stat-number"><?php echo $admin_stats['total_customers']; ?></div>
                        <div class="admin-stat-label">Total Customer</div>
                    </div>
                    <div class="admin-stat">
                        <div class="admin-stat-number"><?php echo $admin_stats['today_orders']; ?></div>
                        <div class="admin-stat-label">Order Hari Ini</div>
                    </div>
                    <div class="admin-stat">
                        <div class="admin-stat-number"><?php echo $admin_stats['pending_orders']; ?></div>
                        <div class="admin-stat-label">Menunggu Validasi</div>
                    </div>
                    <div class="admin-stat">
                        <div class="admin-stat-number"><?php echo $admin_stats['pending_payments']; ?></div>
                        <div class="admin-stat-label">Pembayaran Pending</div>
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

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="openTab('profile')">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </button>
                <button class="tab" onclick="openTab('password')">
                    <i class="fas fa-lock"></i> Ubah Password
                </button>
                <button class="tab" onclick="openTab('system')">
                    <i class="fas fa-info-circle"></i> Info Sistem
                </button>
            </div>

            <!-- Edit Profile Tab -->
            <div id="profile" class="tab-content active">
                <div class="section-header">
                    <h2><i class="fas fa-user-edit"></i> Informasi Administrator</h2>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nama"><i class="fas fa-user"></i> Nama Lengkap</label>
                            <input type="text" id="nama" name="nama" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin['nama']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($admin['email']); ?>" required>
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
                    <h2><i class="fas fa-lock"></i> Keamanan Akun</h2>
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

                    <div id="password-strength" style="margin-bottom: 1rem;"></div>

                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i> Ubah Password
                    </button>
                </form>
            </div>

            <!-- System Info Tab -->
            <div id="system" class="tab-content">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> Informasi Sistem</h2>
                </div>

                <div class="system-info">
                    <div class="info-item">
                        <div class="info-label">Versi Aplikasi</div>
                        <div class="info-value">StudioEase v1.0</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?php echo phpversion(); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Server Software</div>
                        <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Database</div>
                        <div class="info-value">MySQL</div>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--text);">Statistik Sistem</h3>
                    <div class="system-info">
                        <div class="info-item">
                            <div class="info-label">Total Studio</div>
                            <div class="info-value">
                                <?php 
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM studios");
                                echo $stmt->fetch()['total'];
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Pemesanan</div>
                            <div class="info-value">
                                <?php 
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM pesanan");
                                echo $stmt->fetch()['total'];
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Pembayaran</div>
                            <div class="info-value">
                                <?php 
                                $stmt = $pdo->query("SELECT COUNT(*) as total FROM pembayaran");
                                echo $stmt->fetch()['total'];
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Pendapatan Total</div>
                            <div class="info-value">
                                <?php 
                                $stmt = $pdo->query("SELECT COALESCE(SUM(total_biaya), 0) as total FROM pesanan WHERE status = 'completed'");
                                echo formatRupiah($stmt->fetch()['total']);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Management -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-cog"></i> Pengaturan Akun</h2>
            </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #fef2f2; border-radius: 8px; border: 1px solid #feb2b2;">
                        <div>
                            <h4 style="margin-bottom: 0.5rem; color: var(--error);">Zona Berbahaya</h4>
                            <p style="color: var(--error); font-size: 0.9rem;">
                                Hati-hati dengan tindakan di bawah ini. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                        <button class="btn btn-danger" onclick="showSystemResetConfirmation()">
                            <i class="fas fa-exclamation-triangle"></i> Reset Sistem
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- System Reset Confirmation Modal -->
    <div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 1rem; color: var(--error);">
                <i class="fas fa-exclamation-triangle"></i> Konfirmasi Reset Sistem
            </h3>
            <p style="margin-bottom: 1rem; color: var(--text);">
                <strong>PERINGATAN:</strong> Tindakan ini akan menghapus semua data kecuali akun administrator.
            </p>
            <ul style="margin-bottom: 1.5rem; color: var(--text-light); padding-left: 1.5rem;">
                <li>Semua data customer akan dihapus</li>
                <li>Semua data pemesanan akan dihapus</li>
                <li>Semua data pembayaran akan dihapus</li>
                <li>Semua data studio akan direset</li>
            </ul>
            <p style="margin-bottom: 1.5rem; color: var(--error); font-weight: 600;">
                Tindakan ini TIDAK DAPAT DIBATALKAN!
            </p>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeResetModal()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button class="btn btn-danger" onclick="resetSystem()">
                    <i class="fas fa-bomb"></i> Ya, Reset Sistem
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

        // Password strength check
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
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
            
            strengthIndicator.innerHTML = `Kekuatan password: <strong style="color: ${color}">${message}</strong>`;
        });

        // System reset modal
        function showSystemResetConfirmation() {
            document.getElementById('resetModal').style.display = 'flex';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        function resetSystem() {
            if (confirm('APAKAH ANDA BENAR-BENAR YAKIN?\n\nTindakan ini akan menghapus SEMUA DATA dan TIDAK DAPAT DIBATALKAN!')) {
                alert('Fitur reset sistem akan diimplementasikan!');
                // Implementation for system reset would go here
                // window.location.href = 'reset-system.php';
            }
        }
        // Close modals when clicking outside
        document.getElementById('resetModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetModal();
            }
        });

        // Add some animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .tab-content {
                animation: fadeIn 0.3s ease;
            }
            
            .admin-stat {
                animation: fadeIn 0.6s ease both;
            }
            
            .admin-stat:nth-child(1) { animation-delay: 0.1s; }
            .admin-stat:nth-child(2) { animation-delay: 0.2s; }
            .admin-stat:nth-child(3) { animation-delay: 0.3s; }
            .admin-stat:nth-child(4) { animation-delay: 0.4s; }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>