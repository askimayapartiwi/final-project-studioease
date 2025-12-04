# 🚀 Final Project RPL — StudioEase

<p align="center">
  <img alt="StudioEase" src="https://img.shields.io/badge/StudioEase-Sistem%20Pemesanan%20Jasa Foto-blue" />
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1+-777BB4" />
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-8.0+-4479A1" />
  <img alt="Frontend" src="https://img.shields.io/badge/HTML5%2FCSS3-Frontend-E34F26" />
  <img alt="License" src="https://img.shields.io/badge/License-MIT-green" />
</p>

---

## 👥 Identitas Kelompok

**Nama Kelompok:** Kelompok 4 - 5C Sistem Informasi  
**Anggota & Jobdesk:**

| Nama Anggota | NIM | Tugas / Jobdesk |
|--------------|-----|-----------------|
| Yuni Amelia | 701230010 | Requirement Analysis, Documentation |
| Aski Maya Partiwi | 701230027 | Mengembangkan website menggunakan PHP Native, Melakukan deployment ke Infinityfree, Membuat desain tabel MySQL dan menyediakan file database.sql, Membuat video demo aplikasi untuk fitur admin, Melakukan push ke Github dan membuat repository  |
| Putra Dwi Pratama Lubis | 701230084 | Backend Development, Database Design |

---

## 📱 Deskripsi Singkat Aplikasi

**StudioEase** adalah sistem pemesanan jasa foto studio dan outdoor berbasis web yang memudahkan customer dalam memesan jasa foto dan membantu admin dalam mengelola proses bisnis studio foto secara digital.

---

## 🎯 Tujuan Sistem / Permasalahan yang Diselesaikan

### **Permasalahan:**
1. Proses pemesanan jasa foto masih manual dan konvensional
2. Kesulitan dalam mengelola jadwal dan ketersediaan studio
3. Proses validasi member dan pemesanan memakan waktu
4. Pembukuan dan laporan keuangan yang tidak terstruktur
5. Tidak adanya sistem pembayaran yang terintegrasi

### **Solusi yang Ditawarkan:**
1. Digitalisasi proses pemesanan jasa foto
2. Sistem penjadwalan otomatis dengan kalender real-time
3. Validasi member dan pemesanan secara online
4. Sistem pembayaran terintegrasi (transfer & tunai)
5. Laporan keuangan yang terstruktur
6. Dashboard monitoring untuk admin

---

## 🛠 Teknologi yang Digunakan

### **Backend:**
-  **PHP 8.1.10 (Native)** – Bahasa pemrograman utama untuk logika bisnis, pemesanan, dan autentikasi
-  **MySQL 8.0.30** – Database utama untuk menyimpan data pengguna, pemesanan, dan jadwal
-  **PDO (PHP Data Objects)** – Koneksi database aman dengan prepared statements
-  **PHP Sessions** – Manajemen autentikasi dan pembatasan hak akses (admin & customer)

### **Frontend:**
-  **HTML5** – Struktur dasar halaman website
-  **CSS3 (Custom)** – Styling murni tanpa framework
-  **JavaScript (Vanilla)** – Interaksi client-side
-  **Font Awesome 6** – Koleksi ikon untuk antarmuka
-  **Responsive Design** – Tampilan optimal di semua perangkat

###  **Security:**
-  **SQL Injection Prevention** – Prepared statements via PDO
-  **XSS Protection** – Input sanitization dengan `htmlspecialchars()`
-  **Password Hashing** – Enkripsi password menggunakan `password_hash()` & `password_verify()`
-  **Session Validation** – Validasi sesi untuk akses halaman terproteksi
-  **File Upload Validation** – Validasi tipe, ukuran, dan konten file bukti pembayaran
-  **Role-Based Access Control** – Pembatasan akses berdasarkan peran (admin/customer)

---

## 🚀 Cara Menjalankan Aplikasi

### **Cara Instalasi**

1. **Download atau Clone Project**  
   Unduh project StudioEase atau clone melalui GitHub:
   ```
   C:\xampp\htdocs\studioease\
   ```
   atau
   ```
   C:\laragon\www\studioease\
   ```

2. **Persiapan Environment**  
   Pastikan perangkat berikut sudah terpasang:
   - XAMPP atau Laragon
   - PHP 8.1+ 
   - MySQL 8.0+
   - Web browser (Chrome, Firefox, Edge)

3. **Setup Database**  
   Buka phpMyAdmin: `http://localhost/phpmyadmin`
   - Buat database baru: `studioease`
   - Import file SQL: `studioease.sql`

---

### **Cara Konfigurasi**

1. Buka file konfigurasi:
   ```
   includes/config.php
   ```

2. Sesuaikan konfigurasi:
   ```php
   $host = 'localhost';
   $dbname = 'studioease';
   $username = 'root';
   $password = ''; // Default untuk XAMPP & Laragon
   ```

---

### **Cara Menjalankan Project:**

1. **Start Web Server:**
   - Buka XAMPP/Laragon
   - Start Apache dan MySQL

2. **Akses Aplikasi:**
   - Buka browser
   - Kunjungi: `http://localhost/studioease/`

---

## 🔑 Akun Demo

### **Admin Account:**
- **Email:** admin@studioease.com
- **Password:** password

### **Customer Account:**
- **Registrasi:** Daftar melalui halaman registrasi customer

---

## 📱 Fitur Aplikasi

### **👤 Fitur Customer:**
- ✅ Registrasi dan Login Customer
- ✅ Pemesanan Jasa Foto Studio/Outdoor
- ✅ Pilih Jadwal dan Studio
- ✅ Sistem Pembayaran (Transfer & Tunai)
- ✅ Upload Bukti Transfer
- ✅ Lihat Status Pemesanan
- ✅ Riwayat Pembayaran
- ✅ Dashboard Customer
- ✅ Batalkan Pemesanan (pending only)

### **👨‍💼 Fitur Admin:**
- ✅ Login Admin
- ✅ Validasi Data Member
- ✅ Validasi Pemesanan
- ✅ Verifikasi Pembayaran
- ✅ Kelola Jadwal Studio
- ✅ Today's Schedule Overview
- ✅ Dashboard dengan Statistics
- ✅ Filter Data Berdasarkan Status

---

## 🔗 Link Deployment

* **Website StudioEase:** [http://studioease.wuaze.com/](http://studioease.wuaze.com/)
* **Repository GitHub:** [https://github.com/askimayapartiwi/final-project-studioease](https://github.com/askimayapartiwi/final-project-studioease)
* **Demo Video:** [Link YouTube Demo](https://youtu.be/IoTgQHpKeKY?si=oki_KbvHfjIHZJzp)

---

## 🖼️ Screenshot Halaman Utama

### **Halaman Homepage:**
![Home Page](https://github.com/askimayapartiwi/final-project-studioease/blob/main/assets/homepage.png)

### **Dashboard Admin:**
![Dashboard Admin](https://github.com/askimayapartiwi/final-project-studioease/blob/main/assets/dashboard-admin.png)

### **Login Customer:**
![Login Customer](https://github.com/askimayapartiwi/final-project-studioease/blob/main/assets/login-customers.png)

### **Pesan Studio:**
![Pesan Studio](https://github.com/askimayapartiwi/final-project-studioease/blob/main/assets/pesan-studio.png)

### **Pembayaran:**
![Pembayaran](https://github.com/askimayapartiwi/final-project-studioease/blob/main/assets/pembayaran.png)

---

## 📝 Catatan Tambahan

### **Keterbatasan Sistem:**
1. Belum mendukung payment gateway otomatis
2. Belum ada notifikasi real-time (email/SMS)
3. Upload file maksimal 2MB
4. Belum support multiple photographer
5. Belum ada mobile app version

### **Fitur yang Akan Dikembangkan:**
- [ ] Mobile App Version
- [ ] Payment Gateway Integration
- [ ] Real-time Notifications
- [ ] Multiple Photographer Support
- [ ] Photo Gallery Management
- [ ] Review and Rating System
- [ ] Invoice Generation
- [ ] Email Notifications

---

### **Petunjuk Penggunaan Khusus:**

#### **Untuk Customer:**
1. Daftar akun terlebih dahulu di halaman registrasi
2. Tunggu verifikasi admin (maksimal 24 jam)
3. Setelah terverifikasi, bisa melakukan pemesanan
4. Pilih studio, tanggal, dan jam yang tersedia
5. Lakukan pembayaran sesuai metode yang dipilih
6. Upload bukti transfer jika memilih metode transfer
7. Pantau status pemesanan di dashboard

#### **Untuk Admin:**
1. Login dengan akun admin (`admin@studioease.com` / `password`)
2. Verifikasi member yang pending di menu "Validasi Member"
3. Validasi pemesanan yang masuk di menu "Validasi Pemesanan"
4. Verifikasi bukti transfer di menu "Pembayaran"
5. Pantau jadwal melalui dashboard
6. Monitor statistics di dashboard utama

---

### **Troubleshooting:**

#### **Common Issues:**

1. **Database Connection Error:**
   - Pastikan MySQL sedang running
   - Cek konfigurasi di `includes/config.php`
   - Pastikan database `studioease` sudah dibuat

2. **File Upload Error:**
   - Pastikan folder `uploads/bukti-transfer/` ada dan writable
   - Cek ukuran file (maksimal 2MB)
   - Format file harus JPG, PNG, atau PDF

3. **Session Error:**
   - Pastikan tidak ada output sebelum session_start()
   - Cek browser cookies settings

4. **Page Not Found:**
   - Pastikan menggunakan local server (XAMPP/Laragon)
   - Cek file structure dan naming

#### **Debug Mode:**
Tambahkan kode berikut di `config.php` untuk debugging:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

## 📚 Keterangan Tugas

Project ini dibuat untuk memenuhi Tugas Final Project Mata Kuliah **Rekayasa Perangkat Lunak**

**Dosen Pengampu:**
- **Nama:** Dila Nurlaila, M.Kom.
- **Mata Kuliah:** Rekayasa Perangkat Lunak
- **Program Studi:** Sistem Informasi
- **Universitas:** UIN STS Jambi

---

### **Scope Project yang Dikembangkan:**
1. ✅ Analisis kebutuhan berdasarkan SRS dokumen
2. ✅ Perancangan sistem (ERD, Use Case Diagram)
3. ✅ Implementasi database dengan MySQL
4. ✅ Pengembangan backend dengan PHP native
5. ✅ Implementasi frontend dengan HTML/CSS/JS
6. ✅ Testing dan debugging
7. ✅ Dokumentasi sistem lengkap

### **Fitur Wajib yang Telah Diterapkan:**
1. ✅ Sistem login/register multi-role (admin/customer)
2. ✅ CRUD untuk semua entitas utama
3. ✅ Sistem pembayaran dengan upload bukti transfer
4. ✅ Manajemen order dengan berbagai status
5. ✅ Dashboard dengan statistik per role
6. ✅ Responsive design untuk mobile devices
7. ✅ Security features (SQL injection prevention, XSS protection)
8. ✅ File upload dengan validation

---

## 📄 License

**© 2025 StudioEase - Developed by Kelompok 4, 5C Sistem Informasi UIN Sulthan Thaha Saifuddin Jambi**

---

**Dibuat dengan ❤ oleh Kelompok 4 — StudioEase Team**
