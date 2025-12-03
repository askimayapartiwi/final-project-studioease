<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

$customer_id = $_SESSION['customer_id'];

// Get pending payments
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, s.harga_per_jam 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.customer_id = ? 
    AND p.status IN ('pending', 'approved')
    ORDER BY p.created_at DESC
");
$stmt->execute([$customer_id]);
$pending_payments = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("
    SELECT py.*, p.tanggal_sesi, s.nama_studio, p.total_biaya 
    FROM pembayaran py 
    JOIN pesanan p ON py.pesanan_id = p.pesanan_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.customer_id = ? 
    ORDER BY py.created_at DESC
");
$stmt->execute([$customer_id]);
$payment_history = $stmt->fetchAll();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $pesanan_id = $_POST['pesanan_id'];
    $metode = $_POST['metode'];
    
    if ($metode === 'transfer') {
        // Handle file upload
        if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (in_array($_FILES['bukti_transfer']['type'], $allowed_types) && 
                $_FILES['bukti_transfer']['size'] <= $max_size) {
                
                $file_extension = pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION);
                $file_name = 'bukti_' . time() . '_' . $customer_id . '.' . $file_extension;
                $upload_path = '../uploads/bukti-transfer/' . $file_name;
                
                if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $upload_path)) {
                    // Create payment record
                    $stmt = $pdo->prepare("
                        INSERT INTO pembayaran (pesanan_id, jumlah, metode, status, bukti_transfer) 
                        VALUES (?, (SELECT total_biaya FROM pesanan WHERE pesanan_id = ?), 'transfer', 'pending', ?)
                    ");
                    $stmt->execute([$pesanan_id, $pesanan_id, $file_name]);
                    
                    showMessage('Bukti transfer berhasil diupload! Menunggu verifikasi admin.', 'success');
                    redirect('pembayaran.php');
                } else {
                    showMessage('Gagal mengupload bukti transfer.', 'error');
                }
            } else {
                showMessage('File tidak valid. Maksimal 2MB dengan format JPG, PNG, atau PDF.', 'error');
            }
        } else {
            showMessage('Silakan pilih file bukti transfer.', 'error');
        }
    } else {
        // Cash payment - langsung approved
        $stmt = $pdo->prepare("
            INSERT INTO pembayaran (pesanan_id, jumlah, metode, status, tanggal_pembayaran) 
            VALUES (?, (SELECT total_biaya FROM pesanan WHERE pesanan_id = ?), 'tunai', 'paid', NOW())
        ");
        $stmt->execute([$pesanan_id, $pesanan_id]);
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE pesanan SET status = 'approved' WHERE pesanan_id = ?");
        $stmt->execute([$pesanan_id]);
        
        showMessage('Pembayaran tunai berhasil dicatat! Pemesanan Anda telah disetujui.', 'success');
        redirect('pembayaran.php');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - StudioEase</title>
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

        /* Payment Form */
        .payment-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 1rem;
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

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .payment-method {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--primary);
        }

        .payment-method.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f0f4ff, #f8faff);
        }

        .payment-method i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
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

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Transfer Instructions */
        .transfer-instructions {
            background: #f0f4ff;
            border: 1px solid #c3dafe;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .transfer-instructions h4 {
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .bank-account {
            background: var(--white);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .bank-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
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

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .tabs {
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

            .payment-methods {
                grid-template-columns: 1fr;
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
                <p>Customer Dashboard</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="pesan.php"><i class="fas fa-calendar-plus"></i> Pesan Studio</a></li>
                <li><a href="pemesanan.php"><i class="fas fa-list"></i> Data Pemesanan</a></li>
                <li><a href="pembayaran.php" class="active"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-credit-card"></i> Pembayaran</h1>
                <div class="user-info">
                    <span>Kelola pembayaran pemesanan Anda</span>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="openTab('pending')">
                    <i class="fas fa-clock"></i> Menunggu Pembayaran
                    <?php if (count($pending_payments) > 0): ?>
                    <span style="background: var(--warning); color: white; border-radius: 10px; padding: 2px 8px; font-size: 0.8rem; margin-left: 5px;">
                        <?php echo count($pending_payments); ?>
                    </span>
                    <?php endif; ?>
                </button>
                <button class="tab" onclick="openTab('history')">
                    <i class="fas fa-history"></i> Riwayat Pembayaran
                </button>
            </div>

            <!-- Pending Payments Tab -->
            <div id="pending" class="tab-content active">
                <div class="section-header">
                    <h2>Pembayaran yang Perlu Diselesaikan</h2>
                    <span class="payment-status status-pending">
                        <?php echo count($pending_payments); ?> Pemesanan
                    </span>
                </div>

                <?php if ($pending_payments): ?>
                    <div class="payment-cards">
                        <?php foreach ($pending_payments as $order): ?>
                        <div class="payment-card">
                            <div class="payment-header">
                                <div class="payment-info">
                                    <h3><?php echo htmlspecialchars($order['nama_studio']); ?></h3>
                                    <div class="payment-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo $order['jam_mulai'] . ' - ' . $order['jam_selesai']; ?></span>
                                        <span><i class="fas fa-hourglass-half"></i> <?php echo $order['durasi']; ?> Jam</span>
                                    </div>
                                </div>
                                <div class="payment-amount">
                                    <?php echo formatRupiah($order['total_biaya']); ?>
                                </div>
                            </div>

                            <div class="payment-form">
                                <form method="POST" action="" enctype="multipart/form-data" id="paymentForm<?php echo $order['pesanan_id']; ?>">
                                    <input type="hidden" name="pesanan_id" value="<?php echo $order['pesanan_id']; ?>">
                                    
                                    <div class="form-group">
                                        <label>Pilih Metode Pembayaran:</label>
                                        <div class="payment-methods">
                                            <div class="payment-method" onclick="selectPaymentMethod(<?php echo $order['pesanan_id']; ?>, 'transfer')">
                                                <i class="fas fa-university"></i>
                                                <div>Transfer Bank</div>
                                            </div>
                                            <div class="payment-method" onclick="selectPaymentMethod(<?php echo $order['pesanan_id']; ?>, 'tunai')">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <div>Bayar di Tempat</div>
                                            </div>
                                        </div>
                                        <input type="hidden" name="metode" id="metode<?php echo $order['pesanan_id']; ?>" required>
                                    </div>

                                    <!-- Transfer Instructions -->
                                    <div id="transferInstructions<?php echo $order['pesanan_id']; ?>" style="display: none;">
                                        <div class="transfer-instructions">
                                            <h4><i class="fas fa-info-circle"></i> Instruksi Transfer</h4>
                                            <div class="bank-account">
                                                <div class="bank-detail">
                                                    <span>Bank:</span>
                                                    <strong>BCA</strong>
                                                </div>
                                                <div class="bank-detail">
                                                    <span>No. Rekening:</span>
                                                    <strong>1234 5678 9012</strong>
                                                </div>
                                                <div class="bank-detail">
                                                    <span>Atas Nama:</span>
                                                    <strong>STUDIO EASE FOTO</strong>
                                                </div>
                                                <div class="bank-detail">
                                                    <span>Jumlah Transfer:</span>
                                                    <strong><?php echo formatRupiah($order['total_biaya']); ?></strong>
                                                </div>
                                            </div>
                                            <p style="color: var(--text-light); font-size: 0.9rem;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Transfer tepat sesuai jumlah di atas. Upload bukti transfer setelah melakukan pembayaran.
                                            </p>
                                        </div>

                                        <div class="form-group">
                                            <label for="bukti_transfer<?php echo $order['pesanan_id']; ?>">
                                                <i class="fas fa-upload"></i> Upload Bukti Transfer
                                            </label>
                                            <input type="file" id="bukti_transfer<?php echo $order['pesanan_id']; ?>" name="bukti_transfer" 
                                                   class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                            <small style="color: var(--text-light);">
                                                Format: JPG, PNG, PDF (Maks. 2MB)
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Cash Instructions -->
                                    <div id="cashInstructions<?php echo $order['pesanan_id']; ?>" style="display: none;">
                                        <div class="transfer-instructions" style="background: #f0fff4; border-color: #9ae6b4;">
                                            <h4><i class="fas fa-info-circle"></i> Pembayaran di Tempat</h4>
                                            <p>Anda dapat melakukan pembayaran secara tunai ketika datang ke studio.</p>
                                            <p style="color: var(--success); font-weight: 500;">
                                                <i class="fas fa-check-circle"></i>
                                                Pemesanan akan langsung aktif setelah konfirmasi ini.
                                            </p>
                                        </div>
                                    </div>

                                    <button type="submit" name="submit_payment" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Konfirmasi Pembayaran
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>Tidak ada pembayaran pending</h3>
                        <p>Semua pembayaran Anda telah diselesaikan</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment History Tab -->
            <div id="history" class="tab-content">
                <div class="section-header">
                    <h2>Riwayat Pembayaran</h2>
                    <span class="payment-status status-paid">
                        <?php echo count($payment_history); ?> Transaksi
                    </span>
                </div>

                <?php if ($payment_history): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Studio</th>
                                    <th>Metode</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_history as $payment): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d M Y', strtotime($payment['created_at'])); ?>
                                        <div style="font-size: 0.8rem; color: var(--text-light);">
                                            <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                                        </div>
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
                                        <?php if ($payment['metode'] === 'transfer' && $payment['bukti_transfer']): ?>
                                            <a href="../uploads/bukti-transfer/<?php echo $payment['bukti_transfer']; ?>" 
                                               target="_blank" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-eye"></i> Lihat Bukti
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-light);">-</span>
                                        <?php endif; ?>
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

        // Payment method selection
        function selectPaymentMethod(orderId, method) {
            const paymentMethods = document.querySelectorAll(`#paymentForm${orderId} .payment-method`);
            paymentMethods.forEach(pm => pm.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            
            document.getElementById(`metode${orderId}`).value = method;
            
            // Show/hide instructions
            document.getElementById(`transferInstructions${orderId}`).style.display = 
                method === 'transfer' ? 'block' : 'none';
            document.getElementById(`cashInstructions${orderId}`).style.display = 
                method === 'tunai' ? 'block' : 'none';
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const orderId = this.querySelector('input[name="pesanan_id"]').value;
                    const method = document.getElementById(`metode${orderId}`).value;
                    const fileInput = document.getElementById(`bukti_transfer${orderId}`);
                    
                    if (!method) {
                        e.preventDefault();
                        alert('Silakan pilih metode pembayaran!');
                        return false;
                    }
                    
                    if (method === 'transfer' && !fileInput.files[0]) {
                        e.preventDefault();
                        alert('Silakan upload bukti transfer!');
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>