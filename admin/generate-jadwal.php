<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Clear existing schedule to avoid duplicates
$pdo->exec("DELETE FROM jadwal_studio WHERE tanggal > CURDATE()");

// Generate jadwal untuk 30 hari ke depan
$studios = $pdo->query("SELECT studio_id FROM studios WHERE status = 'active'")->fetchAll();
$generated_count = 0;

foreach ($studios as $studio) {
    for ($i = 0; $i <= 30; $i++) { // Hari ini + 30 hari ke depan
        $tanggal = date('Y-m-d', strtotime("+$i days"));
        
        // Skip weekends if needed (optional)
        // if (date('N', strtotime($tanggal)) >= 6) continue;
        
        // Generate time slots from 8:00 to 20:00
        for ($hour = 8; $hour <= 20; $hour++) {
            $jam = sprintf('%02d:00:00', $hour);
            
            // Insert time slot
            $stmt = $pdo->prepare("
                INSERT INTO jadwal_studio (studio_id, tanggal, jam_tersedia, status) 
                VALUES (?, ?, ?, 'available')
            ");
            $stmt->execute([$studio['studio_id'], $tanggal, $jam]);
            $generated_count++;
        }
    }
}

showMessage("Jadwal berhasil digenerate! $generated_count slot waktu dibuat.", 'success');
redirect('dashboard.php');
?>