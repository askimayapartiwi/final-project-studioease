<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudioEase - Sistem Pemesanan Jasa Foto Studio & Outdoor</title>
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            color: var(--text);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header & Navigation */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
        }

        .logo i {
            font-size: 2rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding-top: 80px;
        }

        .hero-content {
            flex: 1;
            color: var(--white);
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
            opacity: 0;
            animation: fadeInUp 1s ease 0.5s forwards;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            opacity: 0;
            animation: fadeInUp 1s ease 0.8s forwards;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
            opacity: 0;
            animation: fadeInUp 1s ease 1.1s forwards;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
        }

        .btn-primary {
            background: var(--white);
            color: var(--primary);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--white);
            border-color: var(--white);
        }

        .btn-outline:hover {
            background: var(--white);
            color: var(--primary);
            transform: translateY(-3px);
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
            opacity: 0;
            animation: fadeInUp 1s ease 1.4s forwards;
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .hero-visual {
            flex: 1;
            position: relative;
            opacity: 0;
            animation: fadeInRight 1s ease 0.8s forwards;
        }

        .floating-card {
            position: absolute;
            background: var(--white);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .card-1 {
            top: 10%;
            left: 10%;
            animation: float 6s ease-in-out infinite;
        }

        .card-2 {
            top: 50%;
            right: 10%;
            animation: float 6s ease-in-out infinite 2s;
        }

        .card-3 {
            bottom: 10%;
            left: 20%;
            animation: float 6s ease-in-out infinite 4s;
        }

        /* Features Section */
        .features {
            padding: 100px 0;
            background: var(--light-bg);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--text);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--white);
            font-size: 2rem;
        }

        .feature-title {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--text);
        }

        .feature-desc {
            color: var(--text-light);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white);
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Footer */
        footer {
            background: var(--text);
            color: var(--white);
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .footer-section a {
            color: var(--text-light);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--text-light);
            color: var(--text-light);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero {
                flex-direction: column;
                text-align: center;
                padding: 100px 0 50px;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-visual {
                margin-top: 3rem;
            }

            .floating-card {
                position: relative;
                margin: 1rem;
            }
        }

        /* Particle Background */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container nav-container">
            <a href="#" class="logo">
                <i class="fas fa-camera"></i>
                <span>StudioEase</span>
            </a>
            <ul class="nav-links">
                <li><a href="customer/login.php" class="btn btn-outline" style="padding: 10px 20px;">Login</a></li>
            </ul>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container" style="display: flex; align-items: center; gap: 3rem;">
            <div class="hero-content">
                <h1 class="hero-title">Capture Your Perfect Moments</h1>
                <p class="hero-subtitle">Sistem pemesanan jasa foto studio dan outdoor terintegrasi. Pesan, bayar, dan abadikan momen berharga Anda dengan mudah.</p>
                
                <div class="hero-buttons">
                    <a href="customer/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Daftar Sekarang
                    </a>
                    <a href="#features" class="btn btn-outline">
                        <i class="fas fa-play-circle"></i>
                        Lihat Demo
                    </a>
                </div>

                <div class="hero-stats">
                    <div class="stat">
                        <span class="stat-number">500+</span>
                        <span class="stat-label">Sesi Foto</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">98%</span>
                        <span class="stat-label">Kepuasan Pelanggan</span>
                    </div>
                    <div class="stat">
                        <span class="stat-number">24/7</span>
                        <span class="stat-label">Support</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="floating-card card-1">
                    <h4><i class="fas fa-calendar-check"></i> Booking Mudah</h4>
                    <p>Pesan studio dalam 3 langkah</p>
                </div>
                <div class="floating-card card-2">
                    <h4><i class="fas fa-clock"></i> Jadwal Fleksibel</h4>
                    <p>Pilih waktu sesuai kebutuhan</p>
                </div>
                <div class="floating-card card-3">
                    <h4><i class="fas fa-camera"></i> Hasil Profesional</h4>
                    <p>Kualitas foto terjamin</p>
                </div>
            </div>
        </div>

        <!-- Particle Background -->
        <div class="particles" id="particles"></div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">Mengapa Memilih StudioEase?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="feature-title">Booking Online</h3>
                    <p class="feature-desc">Pesan jasa foto kapan saja dan di mana saja melalui platform online kami yang responsif.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="feature-title">Penjadwalan Real-time</h3>
                    <p class="feature-desc">Lihat ketersediaan jadwal secara real-time dan pilih slot yang sesuai kebutuhan Anda.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Pembayaran Aman</h3>
                    <p class="feature-desc">Sistem pembayaran yang aman dengan berbagai metode transfer dan tunai.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3 class="feature-title">Notifikasi Real-time</h3>
                    <p class="feature-desc">Dapatkan pemberitahuan instan untuk setiap update status pemesanan Anda.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="feature-title">Laporan Detail</h3>
                    <p class="feature-desc">Akses laporan lengkap dan bukti transaksi untuk keperluan dokumentasi.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="feature-title">Support 24/7</h3>
                    <p class="feature-desc">Tim support kami siap membantu Anda kapan pun dibutuhkan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="cta">
        <div class="container">
            <h2 class="cta-title">Siap Mengabadikan Momen Anda?</h2>
            <p class="cta-subtitle">Bergabunglah dengan ratusan pelanggan yang telah mempercayakan momen berharga mereka kepada kami.</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="customer/register.php" class="btn btn-primary" style="background: var(--white); color: var(--primary);">
                    <i class="fas fa-rocket"></i>
                    Mulai Sekarang
                </a>
                <a href="admin/login.php" class="btn btn-outline" style="border-color: var(--white); color: var(--white);">
                    <i class="fas fa-user-cog"></i>
                    Admin Login
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>StudioEase</h3>
                    <p>Sistem pemesanan jasa foto studio dan outdoor terintegrasi yang memudahkan Anda dalam mengabadikan setiap momen berharga.</p>
                </div>

                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul style="list-style: none;">
                        <li><a href="customer/login.php">Login Customer</a></li>
                        <li><a href="admin/login.php">Login Admin</a></li>
                        <li><a href="customer/register.php">Daftar Member</a></li>
                        <li><a href="#features">Fitur Layanan</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Kontak Kami</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Jl. Lintas Jambi-Muara Bulian KM. 16, Simpang Sungai Duren, Kabupaten Muaro Jambi 36361</p>
                    <p><i class="fas fa-phone"></i> +62 895-6227-96138</p>
                    <p><i class="fas fa-envelope"></i> uinjambi.ac.id</p>
                </div>

                <div class="footer-section">
                    <h3>Developed By</h3>
                    <p>Kelompok 4 - 5C Sistem Informasi</p>
                    <p>UIN Sulthan Thaha Saifuddin Jambi</p>
                    <p>© 2025 All rights reserved</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2025 StudioEase. All rights reserved. | Developed with <i class="fas fa-heart" style="color: #e74c3c;"></i> by Kelompok 4</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript for Particles -->
    <script>
        // Create particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 15;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random properties
                const size = Math.random() * 20 + 5;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const animationDelay = Math.random() * 15;
                const opacity = Math.random() * 0.5 + 0.1;
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${posX}%`;
                particle.style.top = `${posY}%`;
                particle.style.animationDelay = `${animationDelay}s`;
                particle.style.opacity = opacity;
                
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Add scroll effect to header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            }
        });
    </script>
</body>
</html>