<?php
require_once 'config.php';
require_once 'security.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Mendaftarkan user baru dengan peningkatan keamanan
    public function register($nama, $email, $password, $role, $telepon = null, $alamat = null) {
        // Pembatasan rate (mencegah pendaftaran berulang)
        if (!Security::checkRateLimit('register_' . $email, 3, 3600)) {
            return ['success' => false, 'message' => 'Terlalu banyak percobaan pendaftaran. Silakan coba lagi nanti.'];
        }
        
        // Membersihkan input data
        $nama = Security::sanitize($nama);
        $email = Security::sanitize($email);
        $telepon = Security::sanitize($telepon);
        $alamat = Security::sanitize($alamat);
        
        // Validasi input
        if (empty($nama) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Semua field wajib diisi!'];
        }
        
        if (!Security::validateEmail($email)) {
            return ['success' => false, 'message' => 'Format email tidak valid!'];
        }
        
        if ($telepon && !Security::validatePhone($telepon)) {
            return ['success' => false, 'message' => 'Format nomor telepon tidak valid!'];
        }
        
        $passwordErrors = Security::validatePassword($password);
        if (!empty($passwordErrors)) {
            return ['success' => false, 'message' => implode(' ', $passwordErrors)];
        }
        
        // Cek apakah email sudah terdaftar
        $stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email sudah terdaftar'];
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->pdo->beginTransaction();
            
            // Insert data user
            $stmt = $this->pdo->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $email, $hashedPassword, $role]);
            $user_id = $this->pdo->lastInsertId();
            
            // Insert detail customer jika role adalah customer
            if ($role === 'customer') {
                $stmt = $this->pdo->prepare("INSERT INTO customers (user_id, no_telepon, alamat) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $telepon, $alamat]);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Registrasi berhasil! Silakan login.'];
            
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    // Login user dengan peningkatan keamanan
    public function login($email, $password) {
        // Pembatasan rate (mencegah brute force)
        if (!Security::checkRateLimit('login_' . $_SERVER['REMOTE_ADDR'], 5, 900)) {
            return ['success' => false, 'message' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.'];
        }
        
        // Membersihkan input
        $email = Security::sanitize($email);
        
        if (!Security::validateEmail($email)) {
            return ['success' => false, 'message' => 'Format email tidak valid!'];
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set variabel session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Regenerasi session ID untuk mencegah session fixation
            session_regenerate_id(true);
            
            // Ambil detail customer jika role adalah customer
            if ($user['role'] === 'customer') {
                $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $customer = $stmt->fetch();
                
                if ($customer) {
                    $_SESSION['customer_id'] = $customer['customer_id'];
                    $_SESSION['status_verifikasi'] = $customer['status_verifikasi'];
                    $_SESSION['no_telepon'] = $customer['no_telepon'];
                    $_SESSION['alamat'] = $customer['alamat'];
                }
            }
            
            // Hapus pembatasan rate pada login yang berhasil
            unset($_SESSION["rate_limit_login_{$_SERVER['REMOTE_ADDR']}"]);
            
            return ['success' => true, 'message' => 'Login berhasil'];
        }
        
        return ['success' => false, 'message' => 'Email atau password salah'];
    }
    
    // Cek timeout session (30 menit)
    public function checkSessionTimeout() {
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
            $this->logout();
            return false;
        }
        
        // Update waktu aktivitas terakhir
        $_SESSION['login_time'] = time();
        return true;
    }
    
    // Logout user
    public function logout() {
        // Hapus semua variabel session
        $_SESSION = array();
        
        // Hapus cookie session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Hancurkan session
        session_destroy();
        
        // Redirect ke homepage
        header("Location: ../index.php");
        exit();
    }
}

// Inisialisasi class Auth
$auth = new Auth($pdo);

// Handle aksi logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
}

// Cek timeout session pada setiap request
if (isLoggedIn()) {
    $auth->checkSessionTimeout();
}
?>