# 🚀 Final Project RPL — StudioEase

## 👥 Identitas Kelompok

**Nama Kelompok:** Kelompok 4 - 5C Sistem Informasi  
**Anggota & Jobdesk:**

| Nama Anggota | NIM | Tugas / Jobdesk |
|--------------|-----|-----------------|
| Yuni Amelia | 701230010 | Requirement Analysis, Documentation |
| Aski Maya Partiwi | 701230027 | UI/UX Design, Frontend Development |
| Putra Dwi Pratama Lubis | 701230084 | Backend Development, Database Design |

---

## 📱 Deskripsi Singkat Aplikasi

**StudioEase** adalah sistem pemesanan jasa foto studio dan outdoor berbasis web yang memudahkan customer dalam memesan jasa foto dan membantu admin dalam mengelola proses bisnis studio foto secara digital. Aplikasi ini menghubungkan dua jenis pengguna utama: customer (pengguna jasa foto) dan admin (pengelola studio).

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
- **PHP Native** - Bahasa pemrograman utama
- **MySQL** - Database management system
- **PDO** - Database connection dengan prepared statements
- **Sessions** - Manajemen autentikasi dan authorization

### **Frontend:**
- **HTML5** - Struktur halaman web
- **CSS3** - Styling dengan custom design
- **JavaScript (Vanilla)** - Interaktivitas client-side
- **Font Awesome 6** - Icon library
- **Responsive Design** - Mobile-friendly interface

### **Security Features:**
- **SQL Injection Prevention** - Menggunakan prepared statements PDO
- **XSS Protection** - Input sanitization dengan htmlspecialchars()
- **Password Hashing** - Bcrypt password encryption
- **Session Management** - Secure session handling
- **File Upload Security** - Validasi type, size, dan content
- **CSRF Protection** - Form submission validation

---

## 🚀 Cara Menjalankan Aplikasi

### **Cara Instalasi:**

1. **Download dan Extract Project:**
   ```bash
   # Clone atau download project ke folder htdocs (XAMPP) atau www (Laragon)
   # Contoh: C:\xampp\htdocs\studioease\
   ```

2. **Setup Database:**
   - Buka phpMyAdmin (`http://localhost/phpmyadmin`)
   - Buat database baru dengan nama `studioease`
   - Database akan otomatis terbuat saat pertama kali diakses

3. **Konfigurasi Database:**
   - Buka file `includes/config.php`
   - Sesuaikan konfigurasi database jika diperlukan:
   ```php
   $host = 'localhost';
   $dbname = 'studioease';
   $username = 'root';
   $password = ''; // Kosong untuk Laragon default
   ```

### **Cara Menjalankan Project:**

1. **Start Web Server:**
   - Buka XAMPP/Laragon
   - Start Apache dan MySQL

2. **Akses Aplikasi:**
   - Buka browser
   - Kunjungi: `http://localhost/studioease/`

3. **Setup Awal:**
   - Sistem akan otomatis membuat tabel database
   - Data sample studio akan terbuat otomatis

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

## 🔗 Link Deployment

* **Website WeddingLink:** [http://studioease.wuaze.com/](http://studioease.wuaze.com/)
* **Repository GitHub:** [https://github.com/nadhifpandyas/weddinglink.git](https://github.com/nadhifpandyas/weddinglink.git)
* **Demo Video:** [Link YouTube Demo](https://youtu.be/IoTgQHpKeKY?si=oki_KbvHfjIHZJzp)
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

*Project ini dikembangkan untuk tujuan edukasi dan memenuhi tugas akhir mata kuliah Rekayasa Perangkat Lunak.*

---

**Dibuat dengan ❤ oleh Kelompok 4 — StudioEase Team**
