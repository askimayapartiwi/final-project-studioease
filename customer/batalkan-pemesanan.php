<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

$customer_id = $_SESSION['customer_id'];

// Check if order ID is provided
if (!isset($_GET['id'])) {
    showMessage('ID pemesanan tidak valid!', 'error');
    redirect('pemesanan.php');
}

$pesanan_id = $_GET['id'];

// Verify that the order belongs to the logged-in customer
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio 
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.pesanan_id = ? AND p.customer_id = ?
");
$stmt->execute([$pesanan_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) {
    showMessage('Pemesanan tidak ditemukan atau tidak memiliki akses!', 'error');
    redirect('pemesanan.php');
}

// Check if order can be cancelled (only pending orders can be cancelled)
if ($order['status'] !== 'pending') {
    showMessage('Pemesanan tidak dapat dibatalkan. Status: ' . $order['status'], 'error');
    redirect('pemesanan.php');
}

// Handle cancellation confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_cancel'])) {
        try {
            $pdo->beginTransaction();
            
            // Debug: Check what we're updating
            error_log("Cancelling order ID: " . $pesanan_id);
            
            // Update order status to cancelled
            $stmt = $pdo->prepare("UPDATE pesanan SET status = 'cancelled' WHERE pesanan_id = ?");
            $result = $stmt->execute([$pesanan_id]);
            
            if (!$result) {
                throw new Exception("Gagal update status pemesanan");
            }
            
            error_log("Order status updated to cancelled");
            
            // Free up the booked schedule
            $stmt = $pdo->prepare("
                UPDATE jadwal_studio 
                SET status = 'available' 
                WHERE studio_id = ? 
                AND tanggal = ? 
                AND jam_tersedia BETWEEN ? AND ?
            ");
            $result = $stmt->execute([
                $order['studio_id'],
                $order['tanggal_sesi'],
                $order['jam_mulai'],
                $order['jam_selesai']
            ]);
            
            if (!$result) {
                throw new Exception("Gagal update jadwal studio");
            }
            
            error_log("Studio schedule freed up");
            
            $pdo->commit();
            
            showMessage('Pemesanan berhasil dibatalkan!', 'success');
            redirect('pemesanan.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error cancelling order: " . $e->getMessage());
            showMessage('Terjadi kesalahan saat membatalkan pemesanan: ' . $e->getMessage(), 'error');
            redirect('pemesanan.php');
        }
    } else {
        // User cancelled the cancellation
        redirect('pemesanan.php');
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batalkan Pemesanan - StudioEase</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .cancel-container {
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }

        .cancel-header {
            background: linear-gradient(135deg, var(--error), #c53030);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .cancel-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .cancel-header p {
            opacity: 0.9;
        }

        .cancel-content {
            padding: 2rem;
        }

        .order-details {
            background: var(--light-bg);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .order-info h3 {
            margin-bottom: 1rem;
            color: var(--text);
            text-align: center;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            font-weight: 500;
        }

        .warning-box {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .warning-box i {
            color: var(--error);
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .warning-box h4 {
            color: var(--error);
            margin-bottom: 0.5rem;
        }

        .warning-box p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error), #c53030);
            color: var(--white);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(229, 62, 62, 0.3);
        }

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .cancel-container {
                border-radius: 10px;
            }
            
            .cancel-header,
            .cancel-content {
                padding: 1.5rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="cancel-container">
        <div class="cancel-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Batalkan Pemesanan</h1>
            <p>Konfirmasi pembatalan pemesanan studio</p>
        </div>

        <div class="cancel-content">
            <?php 
            // Enable error reporting for debugging
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            displayMessage(); 
            ?>

            <!-- Order Details -->
            <div class="order-details">
                <div class="order-info">
                    <h3>Detail Pemesanan</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">ID Pemesanan</div>
                            <div class="detail-value">#<?php echo $pesanan_id; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Studio</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['nama_studio']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tanggal</div>
                            <div class="detail-value"><?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Waktu</div>
                            <div class="detail-value"><?php echo $order['jam_mulai'] . ' - ' . $order['jam_selesai']; ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Durasi</div>
                            <div class="detail-value"><?php echo $order['durasi']; ?> Jam</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Biaya</div>
                            <div class="detail-value"><?php echo formatRupiah($order['total_biaya']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status Saat Ini</div>
                            <div class="detail-value">
                                <span style="color: var(--warning); font-weight: 500;">
                                    <?php 
                                    $status_text = [
                                        'pending' => 'Menunggu Validasi',
                                        'approved' => 'Disetujui', 
                                        'completed' => 'Selesai',
                                        'cancelled' => 'Dibatalkan'
                                    ];
                                    echo $status_text[$order['status']] ?? $order['status']; 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Message -->
            <div class="warning-box">
                <i class="fas fa-exclamation-circle"></i>
                <h4>Apakah Anda yakin ingin membatalkan pemesanan?</h4>
                <p>Tindakan ini tidak dapat dibatalkan. Jadwal studio akan dibebaskan untuk pemesanan lain.</p>
            </div>

            <!-- Action Buttons -->
            <form method="POST" action="">
                <div class="action-buttons">
                    <button type="submit" name="confirm_cancel" class="btn btn-danger">
                        <i class="fas fa-times"></i> Ya, Batalkan Pemesanan
                    </button>
                    <a href="pemesanan.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>

            <div class="back-link">
                <a href="pemesanan.php">
                    <i class="fas fa-arrow-left"></i> Kembali ke Data Pemesanan
                </a>
            </div>
        </div>
    </div>

    <script>
        // Additional confirmation for cancellation
        document.addEventListener('DOMContentLoaded', function() {
            const cancelForm = document.querySelector('form');
            const cancelButton = cancelForm.querySelector('button[type="submit"]');
            
            cancelButton.addEventListener('click', function(e) {
                if (!confirm('Anda yakin ingin membatalkan pemesanan ini? Tindakan ini tidak dapat dibatalkan.')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>