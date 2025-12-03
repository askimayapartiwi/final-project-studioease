<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isCustomer()) {
    redirect('../customer/login.php');
}

// Cek verifikasi customer
if ($_SESSION['status_verifikasi'] !== 'verified') {
    showMessage('Akun Anda harus diverifikasi terlebih dahulu sebelum melakukan pemesanan.', 'error');
    redirect('dashboard.php');
}

// Ambil studio aktif
$stmt = $pdo->query("SELECT * FROM studios WHERE status = 'active'");
$studios = $stmt->fetchAll();

$error = '';
$success = '';

// Jika submit form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $studio_id = $_POST['studio_id'] ?? '';
    $tanggal_sesi = $_POST['tanggal_sesi'] ?? '';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $durasi = intval($_POST['durasi'] ?? 0);
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    $catatan_khusus = trim($_POST['catatan_khusus'] ?? '');

    // Validasi dasar
    if (!$studio_id || !$tanggal_sesi || !$jam_mulai || !$durasi) {
        $error = 'Semua field wajib diisi!';
    } else {

        try {
            // Hitung jam selesai yang benar
            $jam_selesai = date('H:i:s', strtotime("$jam_mulai +$durasi hour"));

            // Cek jadwal (HARUS: tidak boleh ada slot yang statusnya != available)
            $stmt = $pdo->prepare("
                SELECT * FROM jadwal_studio
                WHERE studio_id = ?
                  AND tanggal = ?
                  AND jam_tersedia >= ?
                  AND jam_tersedia < ?
                  AND status != 'available'
            ");

            $stmt->execute([$studio_id, $tanggal_sesi, $jam_mulai, $jam_selesai]);
            $conflict = $stmt->fetchAll();

            if ($conflict) {
                $error = 'Studio tidak tersedia pada jam yang dipilih!';
            } else {

                // Ambil harga studio
                $stmt = $pdo->prepare("SELECT harga_per_jam FROM studios WHERE studio_id = ?");
                $stmt->execute([$studio_id]);
                $studio = $stmt->fetch();

                if (!$studio) {
                    $error = 'Studio tidak ditemukan!';
                } else {

                    $total_biaya = $studio['harga_per_jam'] * $durasi;

                    // Simpan pemesanan
                    $stmt = $pdo->prepare("
                        INSERT INTO pesanan
                        (customer_id, studio_id, tanggal_pesan, tanggal_sesi, jam_mulai, jam_selesai, durasi, total_biaya, metode_pembayaran, catatan_khusus, status)
                        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $_SESSION['customer_id'],
                        $studio_id,
                        $tanggal_sesi,
                        $jam_mulai,
                        $jam_selesai,
                        $durasi,
                        $total_biaya,
                        $metode_pembayaran,
                        $catatan_khusus
                    ]);

                    // Update semua jam yang terpakai
                    $stmt = $pdo->prepare("
                        UPDATE jadwal_studio 
                        SET status = 'booked'
                        WHERE studio_id = ?
                          AND tanggal = ?
                          AND jam_tersedia >= ?
                          AND jam_tersedia < ?
                    ");

                    $stmt->execute([$studio_id, $tanggal_sesi, $jam_mulai, $jam_selesai]);

                    $success = 'Pemesanan berhasil! Menunggu validasi admin.';
                }
            }

        } catch (PDOException $e) {
            $error = 'Kesalahan: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Studio - StudioEase</title>
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

        /* Sidebar (sama seperti dashboard) */
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

        /* Booking Form */
        .booking-container {
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
                <li><a href="pesan.php" class="active"><i class="fas fa-calendar-plus"></i> Pesan Studio</a></li>
                <li><a href="pemesanan.php"><i class="fas fa-list"></i> Data Pemesanan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-calendar-plus"></i> Pesan Studio Foto</h1>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>

            <div class="booking-container">
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

                <form method="POST" action="">
                    <!-- Pilih Studio -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-building"></i> Pilih Studio
                        </h2>
                        
                        <div class="studio-cards">
                            <?php foreach ($studios as $studio): ?>
                            <div class="studio-card" onclick="selectStudio(<?php echo $studio['studio_id']; ?>, <?php echo $studio['harga_per_jam']; ?>)">
                                <div class="studio-name"><?php echo htmlspecialchars($studio['nama_studio']); ?></div>
                                <div class="studio-price"><?php echo formatRupiah($studio['harga_per_jam']); ?>/jam</div>
                                <ul class="studio-features">
                                    <?php 
                                    $features = explode(',', $studio['fasilitas']);
                                    foreach ($features as $feature): 
                                    ?>
                                    <li><i class="fas fa-check"></i> <?php echo trim($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <input type="hidden" id="studio_id" name="studio_id" required>
                    </div>

                    <!-- Detail Pemesanan -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-calendar-alt"></i> Detail Pemesanan
                        </h2>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tanggal_sesi"><i class="fas fa-calendar-day"></i> Tanggal Sesi</label>
                                <input type="date" id="tanggal_sesi" name="tanggal_sesi" class="form-control" 
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                       required onchange="checkAvailability()">
                            </div>

                            <div class="form-group">
                                <label for="jam_mulai"><i class="fas fa-clock"></i> Jam Mulai</label>
                                <select id="jam_mulai" name="jam_mulai" class="form-control" required onchange="checkAvailability()">
                                    <option value="">Pilih Jam</option>
                                    <?php for ($i = 8; $i <= 20; $i++): ?>
                                        <option value="<?php echo sprintf('%02d:00:00', $i); ?>">
                                            <?php echo sprintf('%02d:00', $i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="durasi"><i class="fas fa-hourglass-half"></i> Durasi (jam)</label>
                                <select id="durasi" name="durasi" class="form-control" required onchange="calculatePrice()">
                                    <option value="">Pilih Durasi</option>
                                    <option value="1">1 Jam</option>
                                    <option value="2">2 Jam</option>
                                    <option value="3">3 Jam</option>
                                    <option value="4">4 Jam</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="metode_pembayaran"><i class="fas fa-credit-card"></i> Metode Pembayaran</label>
                                <select id="metode_pembayaran" name="metode_pembayaran" class="form-control" required>
                                    <option value="">Pilih Metode</option>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="tunai">Tunai</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="catatan_khusus"><i class="fas fa-sticky-note"></i> Catatan Khusus</label>
                                <textarea id="catatan_khusus" name="catatan_khusus" class="form-control" rows="4" 
                                          placeholder="Contoh: Membawa properti sendiri, tema foto keluarga, dll."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Harga -->
                    <div class="price-summary">
                        <h3 style="margin-bottom: 1.5rem; text-align: center;">
                            <i class="fas fa-receipt"></i> Ringkasan Biaya
                        </h3>
                        
                        <div class="price-item">
                            <span>Harga per Jam:</span>
                            <span id="price-per-hour">Rp 0</span>
                        </div>
                        
                        <div class="price-item">
                            <span>Durasi:</span>
                            <span id="duration-display">0 Jam</span>
                        </div>
                        
                        <div class="price-total">
                            <span>Total Biaya:</span>
                            <span id="total-price">Rp 0</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-calendar-check"></i> Pesan Sekarang
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let selectedStudioPrice = 0;
        let selectedStudioId = null;

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
            const durasi = parseInt(document.getElementById('durasi').value) || 0;
            const totalPrice = selectedStudioPrice * durasi;
            
            document.getElementById('duration-display').textContent = durasi + ' Jam';
            document.getElementById('total-price').textContent = 'Rp ' + totalPrice.toLocaleString();
        }

        function checkAvailability() {
            // This would typically make an AJAX call to check availability
            // For now, we'll just show a simple message
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
                if (!selectedStudioId) {
                    e.preventDefault();
                    alert('Silakan pilih studio terlebih dahulu!');
                    return false;
                }
            });
        });
    </script>
</body>
</html>