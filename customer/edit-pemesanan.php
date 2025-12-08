<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

$customer_id = $_SESSION['customer_id'];
$error = '';
$success = '';

// Check if order ID is provided
if (!isset($_GET['id'])) {
    showMessage('ID pemesanan tidak valid!', 'error');
    redirect('pemesanan.php');
}

$pesanan_id = $_GET['id'];

// Get order details
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, s.harga_per_jam, s.studio_id
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.pesanan_id = ? AND p.customer_id = ? AND p.status = 'pending'
");
$stmt->execute([$pesanan_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) {
    showMessage('Pemesanan tidak ditemukan atau tidak dapat diedit!', 'error');
    redirect('pemesanan.php');
}

// Get available studios
$stmt = $pdo->query("SELECT * FROM studios WHERE status = 'active'");
$studios = $stmt->fetchAll();

// Get available dates (next 30 days)
$available_dates = [];
for ($i = 1; $i <= 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $available_dates[] = $date;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studio_id = $_POST['studio_id'];
    $tanggal_sesi = $_POST['tanggal_sesi'];
    $jam_mulai = $_POST['jam_mulai'];
    $durasi = $_POST['durasi'];
    $catatan_khusus = trim($_POST['catatan_khusus']);
    
    // Validation
    if (empty($studio_id) || empty($tanggal_sesi) || empty($jam_mulai) || empty($durasi)) {
        $error = 'Semua field wajib diisi!';
    } elseif (strtotime($tanggal_sesi) < strtotime('tomorrow')) {
        $error = 'Tanggal tidak valid! Pilih tanggal mulai besok.';
    } else {
        try {
            // Calculate end time
            $jam_selesai = date('H:i:s', strtotime("$jam_mulai +$durasi hours"));
            
            // Check if editing to same studio and time (no change)
            $is_same_booking = (
                $order['studio_id'] == $studio_id &&
                $order['tanggal_sesi'] == $tanggal_sesi &&
                $order['jam_mulai'] == $jam_mulai &&
                $order['durasi'] == $durasi
            );
            
            if (!$is_same_booking) {
                // Check studio availability (exclude current booking)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM jadwal_studio 
                    WHERE studio_id = ? 
                    AND tanggal = ? 
                    AND jam_tersedia BETWEEN ? AND ?
                    AND status = 'available'
                    AND NOT EXISTS (
                        SELECT 1 FROM pesanan 
                        WHERE pesanan_id = ? 
                        AND studio_id = ? 
                        AND tanggal_sesi = ? 
                        AND jam_mulai <= ? 
                        AND jam_selesai >= ?
                    )
                ");
                $stmt->execute([
                    $studio_id, 
                    $tanggal_sesi, 
                    $jam_mulai, 
                    $jam_selesai,
                    $pesanan_id,
                    $studio_id,
                    $tanggal_sesi,
                    $jam_selesai,
                    $jam_mulai
                ]);
                $available_count = $stmt->fetch()['count'];
                
                if ($available_count < $durasi) {
                    $error = 'Studio tidak tersedia pada jam yang dipilih!';
                } else {
                    // Get studio price
                    $stmt = $pdo->prepare("SELECT harga_per_jam FROM studios WHERE studio_id = ?");
                    $stmt->execute([$studio_id]);
                    $studio = $stmt->fetch();
                    $total_biaya = $studio['harga_per_jam'] * $durasi;
                    
                    $pdo->beginTransaction();
                    
                    // Free up old schedule
                    $stmt = $pdo->prepare("
                        UPDATE jadwal_studio 
                        SET status = 'available' 
                        WHERE studio_id = ? 
                        AND tanggal = ? 
                        AND jam_tersedia BETWEEN ? AND ?
                    ");
                    $stmt->execute([
                        $order['studio_id'],
                        $order['tanggal_sesi'],
                        $order['jam_mulai'],
                        $order['jam_selesai']
                    ]);
                    
                    // Update order
                    $stmt = $pdo->prepare("
                        UPDATE pesanan 
                        SET studio_id = ?, 
                            tanggal_sesi = ?, 
                            jam_mulai = ?, 
                            jam_selesai = ?, 
                            durasi = ?, 
                            total_biaya = ?, 
                            catatan_khusus = ?,
                            status = 'pending'  // Reset status karena ada perubahan
                        WHERE pesanan_id = ?
                    ");
                    $stmt->execute([
                        $studio_id,
                        $tanggal_sesi,
                        $jam_mulai,
                        $jam_selesai,
                        $durasi,
                        $total_biaya,
                        $catatan_khusus,
                        $pesanan_id
                    ]);
                    
                    // Book new schedule
                    $stmt = $pdo->prepare("
                        UPDATE jadwal_studio 
                        SET status = 'booked' 
                        WHERE studio_id = ? 
                        AND tanggal = ? 
                        AND jam_tersedia BETWEEN ? AND ?
                    ");
                    $stmt->execute([$studio_id, $tanggal_sesi, $jam_mulai, $jam_selesai]);
                    
                    $pdo->commit();
                    
                    $success = 'Pemesanan berhasil diupdate! Menunggu validasi ulang admin.';
                }
            } else {
                // Only update catatan_khusus if no other changes
                $stmt = $pdo->prepare("
                    UPDATE pesanan 
                    SET catatan_khusus = ?
                    WHERE pesanan_id = ?
                ");
                $stmt->execute([$catatan_khusus, $pesanan_id]);
                
                $success = 'Catatan berhasil diupdate!';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get updated order data after possible update
$stmt = $pdo->prepare("
    SELECT p.*, s.nama_studio, s.harga_per_jam
    FROM pesanan p 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE p.pesanan_id = ? AND p.customer_id = ?
");
$stmt->execute([$pesanan_id, $customer_id]);
$order = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pemesanan - StudioEase</title>
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

        /* Edit Form Container */
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-section {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .section-title {
            color: var(--primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--light-bg);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-info {
            background: var(--light-bg);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            margin-bottom: 0.5rem;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .info-value {
            color: var(--text);
            font-weight: 500;
        }

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
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: var(--light-bg);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Studio Cards */
        .studio-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .studio-card {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .studio-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .studio-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f0f4ff, #f8faff);
        }

        .studio-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text);
        }

        .studio-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .studio-features {
            list-style: none;
            color: var(--text-light);
        }

        .studio-features li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .studio-features i {
            color: var(--success);
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 2rem;
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

        /* Price Summary */
        .price-summary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .price-total {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid rgba(255,255,255,0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .studio-cards {
                grid-template-columns: 1fr;
            }

            .info-grid {
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
                <h1><i class="fas fa-edit"></i> Edit Pemesanan</h1>
                <a href="pemesanan.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>

            <div class="edit-container">
                <?php displayMessage(); ?>
                
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

                <!-- Order Information -->
                <div class="order-info">
                    <h3 style="margin-bottom: 1rem; color: var(--primary);">
                        <i class="fas fa-info-circle"></i> Informasi Pemesanan Saat Ini
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">ID Pemesanan</div>
                            <div class="info-value">#<?php echo $pesanan_id; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tanggal Pesan</div>
                            <div class="info-value"><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span style="color: var(--warning); font-weight: 500;">
                                    Menunggu Validasi
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Total Biaya Saat Ini</div>
                            <div class="info-value"><?php echo formatRupiah($order['total_biaya']); ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="">
                    <!-- Pilih Studio -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-building"></i> Pilih Studio Baru (Opsional)
                        </h2>
                        
                        <div class="studio-cards">
                            <?php foreach ($studios as $studio): ?>
                            <div class="studio-card <?php echo $studio['studio_id'] == $order['studio_id'] ? 'selected' : ''; ?>" 
                                 onclick="selectStudio(<?php echo $studio['studio_id']; ?>, <?php echo $studio['harga_per_jam']; ?>)">
                                <div class="studio-name"><?php echo htmlspecialchars($studio['nama_studio']); ?></div>
                                <div class="studio-price"><?php echo formatRupiah($studio['harga_per_jam']); ?>/jam</div>
                                <ul class="studio-features">
                                    <?php 
                                    if ($studio['fasilitas']) {
                                        $features = explode(',', $studio['fasilitas']);
                                        foreach ($features as $feature): 
                                    ?>
                                    <li><i class="fas fa-check"></i> <?php echo trim($feature); ?></li>
                                    <?php endforeach; } ?>
                                </ul>
                                <?php if ($studio['studio_id'] == $order['studio_id']): ?>
                                <div style="margin-top: 1rem; color: var(--primary); font-weight: 500;">
                                    <i class="fas fa-check-circle"></i> Saat ini dipilih
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <input type="hidden" id="studio_id" name="studio_id" value="<?php echo $order['studio_id']; ?>" required>
                    </div>

                    <!-- Detail Pemesanan -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-calendar-alt"></i> Detail Pemesanan Baru
                        </h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tanggal_sesi"><i class="fas fa-calendar-day"></i> Tanggal Sesi</label>
                                <input type="date" id="tanggal_sesi" name="tanggal_sesi" class="form-control" 
                                       value="<?php echo $order['tanggal_sesi']; ?>"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                       required onchange="checkAvailability()">
                                <small style="color: var(--text-light); display: block; margin-top: 5px;">
                                    Tanggal saat ini: <?php echo date('d M Y', strtotime($order['tanggal_sesi'])); ?>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="jam_mulai"><i class="fas fa-clock"></i> Jam Mulai</label>
                                <select id="jam_mulai" name="jam_mulai" class="form-control" required onchange="checkAvailability()">
                                    <option value="">Pilih Jam</option>
                                    <?php for ($i = 8; $i <= 20; $i++): 
                                        $jam = sprintf('%02d:00:00', $i);
                                        $selected = $jam == $order['jam_mulai'] ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $jam; ?>" <?php echo $selected; ?>>
                                            <?php echo sprintf('%02d:00', $i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <small style="color: var(--text-light); display: block; margin-top: 5px;">
                                    Jam saat ini: <?php echo $order['jam_mulai']; ?>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="durasi"><i class="fas fa-hourglass-half"></i> Durasi (jam)</label>
                                <select id="durasi" name="durasi" class="form-control" required onchange="calculatePrice()">
                                    <option value="">Pilih Durasi</option>
                                    <?php for ($i = 1; $i <= 4; $i++): 
                                        $selected = $i == $order['durasi'] ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo $selected; ?>>
                                            <?php echo $i; ?> Jam
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <small style="color: var(--text-light); display: block; margin-top: 5px;">
                                    Durasi saat ini: <?php echo $order['durasi']; ?> Jam
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="current_price"><i class="fas fa-money-bill"></i> Harga per Jam</label>
                                <input type="text" id="current_price" class="form-control" 
                                       value="<?php echo formatRupiah($order['harga_per_jam']); ?>/jam" 
                                       readonly style="background: #f7fafc;">
                            </div>

                            <div class="form-group full-width">
                                <label for="catatan_khusus"><i class="fas fa-sticky-note"></i> Catatan Khusus</label>
                                <textarea id="catatan_khusus" name="catatan_khusus" class="form-control" rows="4"><?php echo htmlspecialchars($order['catatan_khusus'] ?? ''); ?></textarea>
                                <small style="color: var(--text-light); display: block; margin-top: 5px;">
                                    Biarkan kosong jika tidak ada perubahan
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Harga -->
                    <div class="price-summary">
                        <h3 style="margin-bottom: 1.5rem; text-align: center;">
                            <i class="fas fa-calculator"></i> Ringkasan Perubahan Biaya
                        </h3>
                        
                        <div class="price-item">
                            <span>Biaya Lama:</span>
                            <span id="old-price"><?php echo formatRupiah($order['total_biaya']); ?></span>
                        </div>
                        
                        <div class="price-item">
                            <span>Harga per Jam Baru:</span>
                            <span id="price-per-hour"><?php echo formatRupiah($order['harga_per_jam']); ?></span>
                        </div>
                        
                        <div class="price-item">
                            <span>Durasi Baru:</span>
                            <span id="duration-display"><?php echo $order['durasi']; ?> Jam</span>
                        </div>
                        
                        <div class="price-total">
                            <span>Total Biaya Baru:</span>
                            <span id="total-price"><?php echo formatRupiah($order['total_biaya']); ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="pemesanan.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <a href="batalkan-pemesanan.php?id=<?php echo $pesanan_id; ?>" class="btn" style="background: var(--error); color: white;">
                            <i class="fas fa-trash"></i> Batalkan Pemesanan
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let selectedStudioPrice = <?php echo $order['harga_per_jam']; ?>;
        let selectedStudioId = <?php echo $order['studio_id']; ?>;
        let oldPrice = <?php echo $order['total_biaya']; ?>;

        function selectStudio(studioId, price) {
            selectedStudioId = studioId;
            selectedStudioPrice = price;
            
            // Update UI
            document.querySelectorAll('.studio-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            document.getElementById('studio_id').value = studioId;
            document.getElementById('price-per-hour').textContent = 'Rp ' + price.toLocaleString();
            
            calculatePrice();
        }

        function calculatePrice() {
            const durasi = parseInt(document.getElementById('durasi').value) || <?php echo $order['durasi']; ?>;
            const totalPrice = selectedStudioPrice * durasi;
            
            document.getElementById('duration-display').textContent = durasi + ' Jam';
            document.getElementById('total-price').textContent = 'Rp ' + totalPrice.toLocaleString();
            
            // Highlight price change
            const totalPriceElement = document.getElementById('total-price');
            const oldPriceElement = document.getElementById('old-price');
            
            if (totalPrice !== oldPrice) {
                totalPriceElement.style.color = '#f6ad55';
                totalPriceElement.style.fontWeight = 'bold';
                totalPriceElement.innerHTML = 'Rp ' + totalPrice.toLocaleString() + ' <i class="fas fa-arrow-up" style="font-size: 0.8em;"></i>';
                
                oldPriceElement.style.color = '#e53e3e';
                oldPriceElement.style.textDecoration = 'line-through';
            } else {
                totalPriceElement.style.color = 'white';
                oldPriceElement.style.color = 'rgba(255,255,255,0.8)';
                oldPriceElement.style.textDecoration = 'none';
            }
        }

        function checkAvailability() {
            // This would typically make an AJAX call to check availability
            const tanggal = document.getElementById('tanggal_sesi').value;
            const jam = document.getElementById('jam_mulai').value;
            
            if (tanggal && jam) {
                console.log('Checking availability for:', tanggal, jam);
                // Implement AJAX call here to check real availability
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const tanggal = document.getElementById('tanggal_sesi').value;
                const jam = document.getElementById('jam_mulai').value;
                const durasi = document.getElementById('durasi').value;
                
                if (!tanggal || !jam || !durasi) {
                    e.preventDefault();
                    alert('Silakan lengkapi semua field yang wajib diisi!');
                    return false;
                }
                
                // Confirm if there are price changes
                const newTotal = selectedStudioPrice * parseInt(durasi);
                if (newTotal !== oldPrice) {
                    if (!confirm('Perubahan akan mengubah total biaya. Lanjutkan?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
            
            // Initial price calculation
            calculatePrice();
        });
    </script>
</body>
</html>