<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Handle member validation actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $customer_id = $_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $status = 'verified';
        $message = 'Member berhasil diverifikasi!';
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $message = 'Member berhasil ditolak!';
    } else {
        $message = 'Aksi tidak valid!';
    }
    
    if (isset($status)) {
        $stmt = $pdo->prepare("UPDATE customers SET status_verifikasi = ? WHERE customer_id = ?");
        $stmt->execute([$status, $customer_id]);
        showMessage($message, 'success');
        redirect('validasi-member.php');
    }
}

// Get pending members for validation
$stmt = $pdo->prepare("
    SELECT c.*, u.nama, u.email, u.created_at 
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    WHERE c.status_verifikasi = 'pending'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$pending_members = $stmt->fetchAll();

// Get all members for management
$stmt = $pdo->prepare("
    SELECT c.*, u.nama, u.email, u.created_at 
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$all_members = $stmt->fetchAll();

// Statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN status_verifikasi = 'verified' THEN 1 END) as verified_members,
        COUNT(CASE WHEN status_verifikasi = 'pending' THEN 1 END) as pending_members,
        COUNT(CASE WHEN status_verifikasi = 'rejected' THEN 1 END) as rejected_members
    FROM customers
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Member - StudioEase</title>
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

        /* Tabs */
        .tabs {
            display: flex;
            background: var(--white);
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* Content Sections */
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

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            color: var(--text);
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
        .status-verified { background: #c6f6d5; color: var(--success); }
        .status-rejected { background: #fed7d7; color: var(--error); }

        .action-buttons {
            display: flex;
            gap: 5px;
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

        /* Member Details Modal */
        .member-details {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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

            .tabs {
                flex-direction: column;
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
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="validasi-member.php" class="active"><i class="fas fa-users"></i> Validasi Member</a></li>
                <li><a href="validasi-pemesanan.php"><i class="fas fa-clipboard-list"></i> Validasi Pemesanan</a></li>
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
                <h1><i class="fas fa-user-check"></i> Validasi Data Member</h1>
                <div class="user-info">
                    <span>Selamat datang, <?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_members']; ?></div>
                    <div class="stat-label">Total Member</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['verified_members']; ?></div>
                    <div class="stat-label">Terverifikasi</div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_members']; ?></div>
                    <div class="stat-label">Menunggu Validasi</div>
                </div>

                <div class="stat-card error">
                    <div class="stat-icon error">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['rejected_members']; ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="openTab('pending')">
                    <i class="fas fa-clock"></i> Menunggu Validasi
                    <span class="badge"><?php echo count($pending_members); ?></span>
                </button>
                <button class="tab" onclick="openTab('all')">
                    <i class="fas fa-list"></i> Semua Member
                </button>
            </div>

            <!-- Pending Validation Tab -->
            <div id="pending" class="tab-content active">
                <div class="section-header">
                    <h2>Member Menunggu Validasi</h2>
                    <div>
                        <span class="status-badge status-pending">
                            <?php echo count($pending_members); ?> Member
                        </span>
                    </div>
                </div>

                <?php if ($pending_members): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama Member</th>
                                    <th>Kontak</th>
                                    <th>Alamat</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_members as $member): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($member['nama']); ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--text-light);">
                                            <?php echo htmlspecialchars($member['email']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="detail-item">
                                            <div class="detail-value"><?php echo htmlspecialchars($member['no_telepon']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="detail-item">
                                            <div class="detail-value"><?php echo htmlspecialchars($member['alamat']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($member['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="validasi-member.php?action=approve&id=<?php echo $member['customer_id']; ?>" 
                                               class="btn btn-success"
                                               onclick="return confirm('Setujui member <?php echo htmlspecialchars($member['nama']); ?>?')">
                                                <i class="fas fa-check"></i> Setujui
                                            </a>
                                            <a href="validasi-member.php?action=reject&id=<?php echo $member['customer_id']; ?>" 
                                               class="btn btn-danger"
                                               onclick="return confirm('Tolak member <?php echo htmlspecialchars($member['nama']); ?>?')">
                                                <i class="fas fa-times"></i> Tolak
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-check"></i>
                        <h3>Tidak ada member yang menunggu validasi</h3>
                        <p>Semua member sudah terverifikasi</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Members Tab -->
            <div id="all" class="tab-content">
                <div class="section-header">
                    <h2>Semua Member Terdaftar</h2>
                    <div>
                        <span class="status-badge status-verified">
                            <?php echo count($all_members); ?> Total
                        </span>
                    </div>
                </div>

                <?php if ($all_members): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama Member</th>
                                    <th>Email</th>
                                    <th>Telepon</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_members as $member): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($member['nama']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['no_telepon']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $member['status_verifikasi']; ?>">
                                            <?php 
                                            $status_text = [
                                                'pending' => 'Menunggu',
                                                'verified' => 'Terverifikasi',
                                                'rejected' => 'Ditolak'
                                            ];
                                            echo $status_text[$member['status_verifikasi']]; 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($member['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($member['status_verifikasi'] === 'pending'): ?>
                                                <a href="validasi-member.php?action=approve&id=<?php echo $member['customer_id']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="validasi-member.php?action=reject&id=<?php echo $member['customer_id']; ?>" 
                                                   class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php elseif ($member['status_verifikasi'] === 'verified'): ?>
                                                <a href="validasi-member.php?action=reject&id=<?php echo $member['customer_id']; ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   title="Tolak Member">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="validasi-member.php?action=approve&id=<?php echo $member['customer_id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   title="Setujui Member">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-secondary btn-sm" 
                                                    onclick="showMemberDetails(<?php echo htmlspecialchars(json_encode($member)); ?>)"
                                                    title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>Belum ada member terdaftar</h3>
                        <p>Member akan muncul di sini setelah mendaftar</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Member Details Modal -->
    <div id="memberModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 id="modalTitle">Detail Member</h3>
                <button onclick="closeMemberModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div id="modalContent">
                <!-- Content will be filled by JavaScript -->
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

        // Member details modal
        function showMemberDetails(member) {
            const modal = document.getElementById('memberModal');
            const modalContent = document.getElementById('modalContent');
            
            const statusText = {
                'pending': 'Menunggu Validasi',
                'verified': 'Terverifikasi', 
                'rejected': 'Ditolak'
            };
            
            modalContent.innerHTML = `
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Nama Lengkap</div>
                        <div class="detail-value">${member.nama}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">${member.email}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Telepon</div>
                        <div class="detail-value">${member.no_telepon}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-${member.status_verifikasi}">
                                ${statusText[member.status_verifikasi]}
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tanggal Daftar</div>
                        <div class="detail-value">${new Date(member.created_at).toLocaleDateString('id-ID')}</div>
                    </div>
                </div>
                <div class="detail-item" style="margin-top: 1rem;">
                    <div class="detail-label">Alamat Lengkap</div>
                    <div class="detail-value" style="background: #f7fafc; padding: 1rem; border-radius: 5px; margin-top: 0.5rem;">
                        ${member.alamat}
                    </div>
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        function closeMemberModal() {
            document.getElementById('memberModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('memberModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMemberModal();
            }
        });

        // Add badge style
        const style = document.createElement('style');
        style.textContent = `
            .badge {
                background: var(--error);
                color: white;
                border-radius: 10px;
                padding: 2px 8px;
                font-size: 0.8rem;
                margin-left: 5px;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>