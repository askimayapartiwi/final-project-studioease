<?php
class Security {
    
    // Membersihkan (sanitize) data input
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    // Validasi email
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Validasi nomor telepon (format Indonesia)
    public static function validatePhone($phone) {
        return preg_match('/^08[1-9][0-9]{7,10}$/', $phone);
    }
    
    // Validasi tanggal
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    // Generate token CSRF
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Verifikasi token CSRF
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Pembatasan percobaan (Rate limiting)
    public static function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 900) { // 15 menit
        $rateLimitKey = "rate_limit_{$key}";
        
        if (!isset($_SESSION[$rateLimitKey])) {
            $_SESSION[$rateLimitKey] = [
                'attempts' => 0,
                'last_attempt' => time()
            ];
        }
        
        $rateLimit = $_SESSION[$rateLimitKey];
        
        // Reset jika waktu batas telah lewat
        if (time() - $rateLimit['last_attempt'] > $timeWindow) {
            $rateLimit['attempts'] = 0;
            $rateLimit['last_attempt'] = time();
        }
        
        // Cek apakah percobaan sudah melewati batas
        if ($rateLimit['attempts'] >= $maxAttempts) {
            return false;
        }
        
        // Tambahkan jumlah percobaan
        $rateLimit['attempts']++;
        $rateLimit['last_attempt'] = time();
        $_SESSION[$rateLimitKey] = $rateLimit;
        
        return true;
    }
    
    // Validasi upload file
    public static function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'], $maxSize = 2097152) {
        $errors = [];
        
        // Cek apakah file berhasil di-upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Terjadi kesalahan saat mengupload file.';
            return $errors;
        }
        
        // Cek ukuran file
        if ($file['size'] > $maxSize) {
            $errors[] = 'Ukuran file melebihi batas maksimum (2MB).';
        }
        
        // Cek tipe file
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'Tipe file tidak valid. Yang diperbolehkan: JPG, PNG, PDF.';
        }
        
        // Cek ekstensi file
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = 'Ekstensi file tidak valid.';
        }
        
        return $errors;
    }
    
    // Validasi kekuatan password
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 6) {
            $errors[] = 'Password harus memiliki minimal 6 karakter.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal satu huruf kapital.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal satu huruf kecil.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung minimal satu angka.';
        }
        
        return $errors;
    }
}
?>
