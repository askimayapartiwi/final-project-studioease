<?php
// Error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'studioease';
$username = 'root';
$password = ''; // Default Laragon password kosong

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Fungsi helper - HANYA SATU DEKLARASI
function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function showMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $class = $type === 'error' ? 'alert-danger' : 'alert-success';
        echo "<div class='alert $class'>" . $_SESSION['message'] . "</div>";
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
}

// Security function to prevent SQL injection
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Di akhir config.php, tambahkan:
// Auto create uploads directory
$uploads_dir = __DIR__ . '/../uploads/bukti-transfer';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}
?>