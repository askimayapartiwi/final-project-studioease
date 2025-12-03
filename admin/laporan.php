<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../admin/login.php');
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    $report_type = $_GET['report_type'] ?? 'overview';
    
    if ($export_type === 'pdf') {
        exportToPDF($start_date, $end_date, $report_type, $pdo);
    } elseif ($export_type === 'excel') {
        exportToExcel($start_date, $end_date, $report_type, $pdo);
    }
}

// PDF Export Function
function exportToPDF($start_date, $end_date, $report_type, $pdo) {
    // Get report data based on type
    $report_data = getReportData($report_type, $start_date, $end_date, $pdo);
    
    $filename = "laporan_{$report_type}_{$start_date}_to_{$end_date}.pdf";
    
    // Set headers for PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate PDF content
    $pdf_content = generatePDFContent($start_date, $end_date, $report_type, $report_data, $pdo);
    echo $pdf_content;
    exit;
}

// Excel Export Function  
function exportToExcel($start_date, $end_date, $report_type, $pdo) {
    // Get report data based on type
    $report_data = getReportData($report_type, $start_date, $end_date, $pdo);
    
    $filename = "laporan_{$report_type}_{$start_date}_to_{$end_date}.csv";
    
    // Set headers for Excel/CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate Excel content
    $excel_content = generateExcelContent($start_date, $end_date, $report_type, $report_data, $pdo);
    echo $excel_content;
    exit;
}

// Helper function to get report data
function getReportData($report_type, $start_date, $end_date, $pdo) {
    switch ($report_type) {
        case 'overview':
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_orders,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN total_biaya ELSE 0 END), 0) as total_revenue,
                    COALESCE(AVG(CASE WHEN status = 'completed' THEN total_biaya ELSE NULL END), 0) as avg_revenue_per_order,
                    COUNT(DISTINCT customer_id) as unique_customers
                FROM pesanan 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            return $stmt->fetch();
            
        case 'revenue':
            $stmt = $pdo->prepare("
                SELECT 
                    s.nama_studio,
                    COUNT(p.pesanan_id) as total_orders,
                    COALESCE(SUM(p.total_biaya), 0) as total_revenue,
                    COALESCE(AVG(p.total_biaya), 0) as avg_revenue
                FROM studios s 
                LEFT JOIN pesanan p ON s.studio_id = p.studio_id 
                AND p.status = 'completed'
                AND DATE(p.created_at) BETWEEN ? AND ?
                GROUP BY s.studio_id, s.nama_studio
                ORDER BY total_revenue DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            return $stmt->fetchAll();
            
        case 'customers':
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_customers,
                    COUNT(CASE WHEN status_verifikasi = 'verified' THEN 1 END) as verified_customers,
                    COUNT(CASE WHEN status_verifikasi = 'pending' THEN 1 END) as pending_customers,
                    COUNT(CASE WHEN status_verifikasi = 'rejected' THEN 1 END) as rejected_customers,
                    COUNT(DISTINCT p.customer_id) as active_customers
                FROM customers c 
                LEFT JOIN pesanan p ON c.customer_id = p.customer_id 
                AND DATE(p.created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            return $stmt->fetch();
            
        case 'studio_utilization':
            $days_in_period = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
            $total_possible_hours = $days_in_period * 12;
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.nama_studio,
                    s.harga_per_jam,
                    COUNT(p.pesanan_id) as booking_count,
                    COALESCE(SUM(p.durasi), 0) as total_hours,
                    COALESCE(SUM(p.total_biaya), 0) as total_revenue,
                    ROUND((COUNT(p.pesanan_id) / ?) * 100, 2) as utilization_rate
                FROM studios s 
                LEFT JOIN pesanan p ON s.studio_id = p.studio_id 
                AND p.status IN ('approved', 'completed')
                AND DATE(p.tanggal_sesi) BETWEEN ? AND ?
                GROUP BY s.studio_id, s.nama_studio, s.harga_per_jam
                ORDER BY utilization_rate DESC
            ");
            $stmt->execute([$total_possible_hours, $start_date, $end_date]);
            return $stmt->fetchAll();
    }
}

// Generate PDF Content
function generatePDFContent($start_date, $end_date, $report_type, $report_data, $pdo) {
    $title = "LAPORAN STUDIO EASE";
    $period = "Periode: " . date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date));
    $report_title = "Jenis Laporan: " . strtoupper(str_replace('_', ' ', $report_type));
    
    $content = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { font-size: 16px; color: #666; margin: 10px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .number { text-align: right; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='title'>$title</div>
                <div class='subtitle'>$period</div>
                <div class='subtitle'>$report_title</div>
            </div>
    ";
    
    // Add content based on report type
    switch ($report_type) {
        case 'overview':
            $content .= "
                <h3>Statistik Overview</h3>
                <table>
                    <tr><th>Metric</th><th>Value</th></tr>
                    <tr><td>Total Pemesanan</td><td class='number'>{$report_data['total_orders']}</td></tr>
                    <tr><td>Pemesanan Selesai</td><td class='number'>{$report_data['completed_orders']}</td></tr>
                    <tr><td>Pemesanan Pending</td><td class='number'>{$report_data['pending_orders']}</td></tr>
                    <tr><td>Pemesanan Disetujui</td><td class='number'>{$report_data['approved_orders']}</td></tr>
                    <tr><td>Total Pendapatan</td><td class='number'>Rp " . number_format($report_data['total_revenue'], 0, ',', '.') . "</td></tr>
                    <tr><td>Rata-rata per Order</td><td class='number'>Rp " . number_format($report_data['avg_revenue_per_order'], 0, ',', '.') . "</td></tr>
                    <tr><td>Customer Aktif</td><td class='number'>{$report_data['unique_customers']}</td></tr>
                </table>
            ";
            break;
            
        case 'revenue':
            $content .= "
                <h3>Analisis Revenue per Studio</h3>
                <table>
                    <tr><th>Studio</th><th>Total Order</th><th>Total Revenue</th><th>Rata-rata</th></tr>
            ";
            foreach ($report_data as $studio) {
                $content .= "
                    <tr>
                        <td>{$studio['nama_studio']}</td>
                        <td class='number'>{$studio['total_orders']}</td>
                        <td class='number'>Rp " . number_format($studio['total_revenue'], 0, ',', '.') . "</td>
                        <td class='number'>Rp " . number_format($studio['avg_revenue'], 0, ',', '.') . "</td>
                    </tr>
                ";
            }
            $content .= "</table>";
            break;
            
        case 'customers':
            // Get top customers for PDF
            $stmt = $pdo->prepare("
                SELECT u.nama, COUNT(p.pesanan_id) as total_orders, 
                       COALESCE(SUM(p.total_biaya), 0) as total_spent
                FROM customers c 
                JOIN users u ON c.user_id = u.user_id 
                LEFT JOIN pesanan p ON c.customer_id = p.customer_id 
                AND p.status = 'completed'
                AND DATE(p.created_at) BETWEEN ? AND ?
                GROUP BY c.customer_id, u.nama
                ORDER BY total_spent DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $top_customers = $stmt->fetchAll();
            
            $content .= "
                <h3>Statistik Customer</h3>
                <table>
                    <tr><th>Metric</th><th>Value</th></tr>
                    <tr><td>Total Customer</td><td class='number'>{$report_data['total_customers']}</td></tr>
                    <tr><td>Customer Terverifikasi</td><td class='number'>{$report_data['verified_customers']}</td></tr>
                    <tr><td>Customer Pending</td><td class='number'>{$report_data['pending_customers']}</td></tr>
                    <tr><td>Customer Ditolak</td><td class='number'>{$report_data['rejected_customers']}</td></tr>
                    <tr><td>Customer Aktif</td><td class='number'>{$report_data['active_customers']}</td></tr>
                </table>
                
                <h3>Top 10 Customers</h3>
                <table>
                    <tr><th>Nama</th><th>Total Order</th><th>Total Belanja</th></tr>
            ";
            foreach ($top_customers as $customer) {
                $content .= "
                    <tr>
                        <td>{$customer['nama']}</td>
                        <td class='number'>{$customer['total_orders']}</td>
                        <td class='number'>Rp " . number_format($customer['total_spent'], 0, ',', '.') . "</td>
                    </tr>
                ";
            }
            $content .= "</table>";
            break;
            
        case 'studio_utilization':
            $content .= "
                <h3>Tingkat Pemanfaatan Studio</h3>
                <table>
                    <tr><th>Studio</th><th>Total Booking</th><th>Total Jam</th><th>Total Revenue</th><th>Utilisasi</th></tr>
            ";
            foreach ($report_data as $studio) {
                $content .= "
                    <tr>
                        <td>{$studio['nama_studio']}</td>
                        <td class='number'>{$studio['booking_count']}</td>
                        <td class='number'>{$studio['total_hours']} Jam</td>
                        <td class='number'>Rp " . number_format($studio['total_revenue'], 0, ',', '.') . "</td>
                        <td class='number'>{$studio['utilization_rate']}%</td>
                    </tr>
                ";
            }
            $content .= "</table>";
            break;
    }
    
    $content .= "
            <div class='footer'>
                Generated on " . date('d F Y H:i:s') . " | StudioEase Reporting System
            </div>
        </body>
        </html>
    ";
    
    return $content;
}

// Generate Excel Content
function generateExcelContent($start_date, $end_date, $report_type, $report_data, $pdo) {
    $output = "";
    
    // Add headers
    $output .= "LAPORAN STUDIO EASE\n";
    $output .= "Periode: " . date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date)) . "\n";
    $output .= "Jenis Laporan: " . strtoupper(str_replace('_', ' ', $report_type)) . "\n\n";
    
    switch ($report_type) {
        case 'overview':
            $output .= "STATISTIK OVERVIEW\n";
            $output .= "Metric,Value\n";
            $output .= "Total Pemesanan,{$report_data['total_orders']}\n";
            $output .= "Pemesanan Selesai,{$report_data['completed_orders']}\n";
            $output .= "Pemesanan Pending,{$report_data['pending_orders']}\n";
            $output .= "Pemesanan Disetujui,{$report_data['approved_orders']}\n";
            $output .= "Total Pendapatan,Rp " . number_format($report_data['total_revenue'], 0, ',', '.') . "\n";
            $output .= "Rata-rata per Order,Rp " . number_format($report_data['avg_revenue_per_order'], 0, ',', '.') . "\n";
            $output .= "Customer Aktif,{$report_data['unique_customers']}\n";
            break;
            
        case 'revenue':
            $output .= "ANALISIS REVENUE PER STUDIO\n";
            $output .= "Studio,Total Order,Total Revenue,Rata-rata Revenue\n";
            foreach ($report_data as $studio) {
                $output .= "{$studio['nama_studio']},{$studio['total_orders']},Rp " . number_format($studio['total_revenue'], 0, ',', '.') . ",Rp " . number_format($studio['avg_revenue'], 0, ',', '.') . "\n";
            }
            break;
            
        case 'customers':
            // Get top customers for Excel
            $stmt = $pdo->prepare("
                SELECT u.nama, COUNT(p.pesanan_id) as total_orders, 
                       COALESCE(SUM(p.total_biaya), 0) as total_spent
                FROM customers c 
                JOIN users u ON c.user_id = u.user_id 
                LEFT JOIN pesanan p ON c.customer_id = p.customer_id 
                AND p.status = 'completed'
                AND DATE(p.created_at) BETWEEN ? AND ?
                GROUP BY c.customer_id, u.nama
                ORDER BY total_spent DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $top_customers = $stmt->fetchAll();
            
            $output .= "STATISTIK CUSTOMER\n";
            $output .= "Total Customer,{$report_data['total_customers']}\n";
            $output .= "Customer Terverifikasi,{$report_data['verified_customers']}\n";
            $output .= "Customer Pending,{$report_data['pending_customers']}\n";
            $output .= "Customer Ditolak,{$report_data['rejected_customers']}\n";
            $output .= "Customer Aktif,{$report_data['active_customers']}\n\n";
            
            $output .= "TOP 10 CUSTOMERS\n";
            $output .= "Nama,Total Order,Total Belanja\n";
            foreach ($top_customers as $customer) {
                $output .= "{$customer['nama']},{$customer['total_orders']},Rp " . number_format($customer['total_spent'], 0, ',', '.') . "\n";
            }
            break;
            
        case 'studio_utilization':
            $output .= "TINGKAT PEMANFAATAN STUDIO\n";
            $output .= "Studio,Total Booking,Total Jam,Total Revenue,Tingkat Utilisasi\n";
            foreach ($report_data as $studio) {
                $output .= "{$studio['nama_studio']},{$studio['booking_count']},{$studio['total_hours']} Jam,Rp " . number_format($studio['total_revenue'], 0, ',', '.') . ",{$studio['utilization_rate']}%\n";
            }
            break;
    }
    
    $output .= "\nGenerated on " . date('d F Y H:i:s') . " | StudioEase Reporting System";
    
    return $output;
}

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'overview';

// Validate dates
if ($start_date > $end_date) {
    $start_date = $end_date;
}

// Get report data based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'overview':
        // Overview statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_orders,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_biaya ELSE 0 END), 0) as total_revenue,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN total_biaya ELSE NULL END), 0) as avg_revenue_per_order,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM pesanan 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetch();

        // Revenue by day for chart
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COALESCE(SUM(total_biaya), 0) as revenue,
                COUNT(*) as orders
            FROM pesanan 
            WHERE status = 'completed' 
            AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$start_date, $end_date]);
        $chart_data = $stmt->fetchAll();
        break;

    case 'revenue':
        // Revenue by studio
        $stmt = $pdo->prepare("
            SELECT 
                s.nama_studio,
                COUNT(p.pesanan_id) as total_orders,
                COALESCE(SUM(p.total_biaya), 0) as total_revenue,
                COALESCE(AVG(p.total_biaya), 0) as avg_revenue
            FROM studios s 
            LEFT JOIN pesanan p ON s.studio_id = p.studio_id 
            AND p.status = 'completed'
            AND DATE(p.created_at) BETWEEN ? AND ?
            GROUP BY s.studio_id, s.nama_studio
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll();

        // Revenue by month for chart
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COALESCE(SUM(total_biaya), 0) as revenue,
                COUNT(*) as orders
            FROM pesanan 
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $chart_data = $stmt->fetchAll();
        break;

    case 'customers':
        // Customer statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN status_verifikasi = 'verified' THEN 1 END) as verified_customers,
                COUNT(CASE WHEN status_verifikasi = 'pending' THEN 1 END) as pending_customers,
                COUNT(CASE WHEN status_verifikasi = 'rejected' THEN 1 END) as rejected_customers,
                COUNT(DISTINCT p.customer_id) as active_customers
            FROM customers c 
            LEFT JOIN pesanan p ON c.customer_id = p.customer_id 
            AND DATE(p.created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetch();

        // New customers by month
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(u.created_at, '%Y-%m') as month,
                COUNT(*) as new_customers
            FROM users u 
            JOIN customers c ON u.user_id = c.user_id 
            WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(u.created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $chart_data = $stmt->fetchAll();
        break;

    case 'studio_utilization':
        // Studio utilization
        $stmt = $pdo->prepare("
            SELECT 
                s.nama_studio,
                s.harga_per_jam,
                COUNT(p.pesanan_id) as booking_count,
                COALESCE(SUM(p.durasi), 0) as total_hours,
                COALESCE(SUM(p.total_biaya), 0) as total_revenue,
                ROUND((COUNT(p.pesanan_id) / ?) * 100, 2) as utilization_rate
            FROM studios s 
            LEFT JOIN pesanan p ON s.studio_id = p.studio_id 
            AND p.status IN ('approved', 'completed')
            AND DATE(p.tanggal_sesi) BETWEEN ? AND ?
            GROUP BY s.studio_id, s.nama_studio, s.harga_per_jam
            ORDER BY utilization_rate DESC
        ");
        
        // Calculate total possible hours in period
        $days_in_period = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
        $total_possible_hours = $days_in_period * 12; // 12 hours per day (8 AM - 8 PM)
        
        $stmt->execute([$total_possible_hours, $start_date, $end_date]);
        $report_data = $stmt->fetchAll();
        break;
}

// Get top customers
$stmt = $pdo->prepare("
    SELECT 
        u.nama,
        u.email,
        c.no_telepon,
        COUNT(p.pesanan_id) as total_orders,
        COALESCE(SUM(p.total_biaya), 0) as total_spent
    FROM customers c 
    JOIN users u ON c.user_id = u.user_id 
    LEFT JOIN pesanan p ON c.customer_id = p.customer_id 
    AND p.status = 'completed'
    AND DATE(p.created_at) BETWEEN ? AND ?
    GROUP BY c.customer_id, u.nama, u.email, c.no_telepon
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date]);
$top_customers = $stmt->fetchAll();

// Get recent activities
$stmt = $pdo->prepare("
    SELECT 
        'Pemesanan' as activity_type,
        u.nama as customer_name,
        s.nama_studio,
        p.total_biaya,
        p.status,
        p.created_at
    FROM pesanan p 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    UNION ALL
    SELECT 
        'Pembayaran' as activity_type,
        u.nama as customer_name,
        s.nama_studio,
        py.jumlah as total_biaya,
        py.status,
        py.created_at
    FROM pembayaran py 
    JOIN pesanan p ON py.pesanan_id = p.pesanan_id 
    JOIN customers c ON p.customer_id = c.customer_id 
    JOIN users u ON c.user_id = u.user_id 
    JOIN studios s ON p.studio_id = s.studio_id 
    WHERE DATE(py.created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$recent_activities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Analytics - StudioEase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --info: #3182ce;
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
            background: linear-gradient(135deg, #2d3748, #4a5568);
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

        /* Filter Section */
        .filter-section {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #2f855a;
        }

        /* Report Tabs */
        .report-tabs {
            display: flex;
            background: var(--white);
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 0;
        }

        .report-tab {
            flex: 1;
            padding: 1rem 1.5rem;
            text-align: center;
            background: var(--white);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: var(--text-light);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .report-tab.active {
            background: var(--primary);
            color: var(--white);
        }

        .report-tab:hover:not(.active) {
            background: var(--light-bg);
        }

        /* Content Sections */
        .content-section {
            background: var(--white);
            border-radius: 0 0 10px 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            color: var(--text);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.info::before { background: var(--info); }
        .stat-card.error::before { background: var(--error); }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: #c3dafe; color: var(--primary); }
        .stat-icon.success { background: #c6f6d5; color: var(--success); }
        .stat-icon.warning { background: #fefcbf; color: var(--warning); }
        .stat-icon.info { background: #bee3f8; color: var(--info); }
        .stat-icon.error { background: #fed7d7; color: var(--error); }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Charts */
        .chart-container {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 400px;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            background: var(--light-bg);
            font-weight: 600;
            color: var(--text);
        }

        .table tr:hover {
            background: var(--light-bg);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Activity Timeline */
        .activity-timeline {
            position: relative;
            margin: 2rem 0;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .activity-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            margin-right: 1rem;
            position: relative;
            z-index: 2;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
            background: var(--white);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-left: 1rem;
        }

        .activity-type {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .activity-details {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }

        /* Export Section */
        .export-section {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            text-align: center;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .report-tabs {
                flex-direction: column;
            }

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 300px;
            }

            .table {
                font-size: 0.9rem;
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
                <p>Admin Dashboard</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="validasi-member.php"><i class="fas fa-users"></i> Validasi Member</a></li>
                <li><a href="validasi-pemesanan.php"><i class="fas fa-clipboard-list"></i> Validasi Pemesanan</a></li>
                <li><a href="pembayaran.php"><i class="fas fa-credit-card"></i> Pembayaran</a></li>
                <li><a href="penjadwalan.php"><i class="fas fa-calendar-alt"></i> Penjadwalan</a></li>
                <li><a href="laporan.php" class="active"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                <li><a href="studios.php"><i class="fas fa-building"></i> Kelola Studio</a></li>
                <li><a href="../includes/auth.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-chart-line"></i> Laporan & Analytics</h1>
                <div class="user-info">
                    <span>Periode: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></span>
                </div>
            </div>

            <!-- Filter Section -->
<div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="start_date"><i class="fas fa-calendar-start"></i> Tanggal Mulai</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" 
                                   value="<?php echo $start_date; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="end_date"><i class="fas fa-calendar-end"></i> Tanggal Akhir</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" 
                                   value="<?php echo $end_date; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="report_type"><i class="fas fa-chart-pie"></i> Jenis Laporan</label>
                            <select id="report_type" name="report_type" class="form-control">
                                <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                                <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                                <option value="customers" <?php echo $report_type === 'customers' ? 'selected' : ''; ?>>Customers</option>
                                <option value="studio_utilization" <?php echo $report_type === 'studio_utilization' ? 'selected' : ''; ?>>Utilization</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Generate Laporan
                            </button>
                            <!-- TAMBAH LINK EXPORT YANG NYATA -->
                            <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'report_type' => $report_type, 'export' => 'pdf']); ?>" 
                               class="btn btn-success">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                            <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'report_type' => $report_type, 'export' => 'excel']); ?>" 
                               class="btn btn-success" style="background: #217346;">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <!-- Report Tabs -->
            <div class="report-tabs">
                <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'report_type' => 'overview']); ?>" 
                   class="report-tab <?php echo $report_type === 'overview' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Overview
                </a>
                <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'report_type' => 'revenue']); ?>" 
                   class="report-tab <?php echo $report_type === 'revenue' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i> Revenue
                </a>
                <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'report_type' => 'customers']); ?>" 
                   class="report-tab <?php echo $report_type === 'customers' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Customers
                </a>
                <a href="?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'report_type' => 'studio_utilization']); ?>" 
                   class="report-tab <?php echo $report_type === 'studio_utilization' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Utilization
                </a>
            </div>

            <!-- Report Content -->
            <div class="content-section">
                <?php if ($report_type === 'overview'): ?>
                    <!-- Overview Report -->
                    <div class="section-header">
                        <h2>Business Overview</h2>
                        <span style="color: var(--text-light);">
                            Ringkasan Performa Bisnis
                        </span>
                    </div>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon primary">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['total_orders']; ?></div>
                            <div class="stat-label">Total Pemesanan</div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['completed_orders']; ?></div>
                            <div class="stat-label">Pemesanan Selesai</div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon info">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-number"><?php echo formatRupiah($report_data['total_revenue']); ?></div>
                            <div class="stat-label">Total Pendapatan</div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon warning">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['unique_customers']; ?></div>
                            <div class="stat-label">Customer Aktif</div>
                        </div>
                    </div>

                    <!-- Revenue Chart -->
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>

                    <!-- Recent Activities -->
                    <div class="section-header">
                        <h2>Aktivitas Terbaru</h2>
                    </div>

                    <?php if ($recent_activities): ?>
                        <div class="activity-timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-dot" style="background: <?php echo $activity['activity_type'] === 'Pemesanan' ? 'var(--primary)' : 'var(--success)'; ?>"></div>
                                <div class="activity-content">
                                    <div class="activity-type">
                                        <?php echo $activity['activity_type']; ?> - <?php echo htmlspecialchars($activity['customer_name']); ?>
                                    </div>
                                    <div class="activity-details">
                                        <?php echo htmlspecialchars($activity['nama_studio']); ?> • 
                                        <?php echo formatRupiah($activity['total_biaya']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?> • 
                                        Status: <span style="color: var(--<?php echo $activity['status'] === 'completed' ? 'success' : ($activity['status'] === 'pending' ? 'warning' : 'info'); ?>);">
                                            <?php echo $activity['status']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>Tidak ada aktivitas</h3>
                            <p>Tidak ada aktivitas dalam periode yang dipilih</p>
                        </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'revenue'): ?>
                    <!-- Revenue Report -->
                    <div class="section-header">
                        <h2>Revenue Analysis</h2>
                        <span style="color: var(--text-light);">
                            Analisis Pendapatan per Studio
                        </span>
                    </div>

                    <?php if ($report_data): ?>
                        <div class="chart-grid">
                            <div class="chart-container">
                                <canvas id="revenueStudioChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <canvas id="monthlyRevenueChart"></canvas>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Studio</th>
                                        <th>Total Pemesanan</th>
                                        <th>Total Pendapatan</th>
                                        <th>Rata-rata per Order</th>
                                        <th>Persentase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_revenue = array_sum(array_column($report_data, 'total_revenue'));
                                    foreach ($report_data as $studio): 
                                        $percentage = $total_revenue > 0 ? ($studio['total_revenue'] / $total_revenue) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($studio['nama_studio']); ?></strong></td>
                                        <td><?php echo $studio['total_orders']; ?></td>
                                        <td><strong><?php echo formatRupiah($studio['total_revenue']); ?></strong></td>
                                        <td><?php echo formatRupiah($studio['avg_revenue']); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <span><?php echo round($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <h3>Tidak ada data revenue</h3>
                            <p>Tidak ada data revenue dalam periode yang dipilih</p>
                        </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'customers'): ?>
                    <!-- Customers Report -->
                    <div class="section-header">
                        <h2>Customer Analytics</h2>
                        <span style="color: var(--text-light);">
                            Analisis Perilaku Customer
                        </span>
                    </div>

                    <!-- Customer Stats -->
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['total_customers']; ?></div>
                            <div class="stat-label">Total Customer</div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['verified_customers']; ?></div>
                            <div class="stat-label">Terverifikasi</div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon warning">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['pending_customers']; ?></div>
                            <div class="stat-label">Menunggu</div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon info">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-number"><?php echo $report_data['active_customers']; ?></div>
                            <div class="stat-label">Customer Aktif</div>
                        </div>
                    </div>

                    <!-- Customer Growth Chart -->
                    <div class="chart-container">
                        <canvas id="customerGrowthChart"></canvas>
                    </div>

                    <!-- Top Customers -->
                    <div class="section-header">
                        <h2>Top 10 Customers</h2>
                    </div>

                    <?php if ($top_customers): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Telepon</th>
                                        <th>Total Order</th>
                                        <th>Total Belanja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $customer): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($customer['nama']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['no_telepon']); ?></td>
                                        <td><?php echo $customer['total_orders']; ?></td>
                                        <td><strong><?php echo formatRupiah($customer['total_spent']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>Tidak ada data customer</h3>
                            <p>Tidak ada data customer dalam periode yang dipilih</p>
                        </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'studio_utilization'): ?>
                    <!-- Studio Utilization Report -->
                    <div class="section-header">
                        <h2>Studio Utilization</h2>
                        <span style="color: var(--text-light);">
                            Tingkat Pemanfaatan Studio
                        </span>
                    </div>

                    <?php if ($report_data): ?>
                        <div class="chart-container">
                            <canvas id="utilizationChart"></canvas>
                        </div>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Studio</th>
                                        <th>Harga per Jam</th>
                                        <th>Total Booking</th>
                                        <th>Total Jam</th>
                                        <th>Total Revenue</th>
                                        <th>Tingkat Utilisasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $studio): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($studio['nama_studio']); ?></strong></td>
                                        <td><?php echo formatRupiah($studio['harga_per_jam']); ?></td>
                                        <td><?php echo $studio['booking_count']; ?></td>
                                        <td><?php echo $studio['total_hours']; ?> Jam</td>
                                        <td><strong><?php echo formatRupiah($studio['total_revenue']); ?></strong></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $studio['utilization_rate']; ?>%; background: <?php echo $studio['utilization_rate'] > 70 ? 'var(--success)' : ($studio['utilization_rate'] > 40 ? 'var(--warning)' : 'var(--error)'); ?>"></div>
                                                </div>
                                                <span><?php echo $studio['utilization_rate']; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <h3>Tidak ada data utilization</h3>
                            <p>Tidak ada data utilization dalam periode yang dipilih</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Export Section -->
                <div class="export-section">
                    <p style="margin-bottom: 1rem; color: var(--text-light);">
                        <i class="fas fa-info-circle"></i>
                        Export laporan ini untuk analisis lebih lanjut
                    </p>
                    <div class="action-buttons" style="justify-content: center;">
                        <button class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <button class="btn btn-primary" onclick="exportExcel()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                        <button class="btn btn-secondary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Chart configurations
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type === 'overview' && $chart_data): ?>
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('d M', strtotime($item['date'])) . "'"; }, $chart_data)); ?>],
                    datasets: [{
                        label: 'Pendapatan',
                        data: [<?php echo implode(',', array_column($chart_data, 'revenue')); ?>],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Order',
                        data: [<?php echo implode(',', array_column($chart_data, 'orders')); ?>],
                        borderColor: '#e53e3e',
                        backgroundColor: 'rgba(229, 62, 62, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Trend Pendapatan & Order Harian'
                        }
                    }
                }
            });
            <?php endif; ?>

            <?php if ($report_type === 'revenue' && $report_data): ?>
            // Revenue by Studio Chart
            const revenueStudioCtx = document.getElementById('revenueStudioChart').getContext('2d');
            new Chart(revenueStudioCtx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['nama_studio'] . "'"; }, $report_data)); ?>],
                    datasets: [{
                        data: [<?php echo implode(',', array_column($report_data, 'total_revenue')); ?>],
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribusi Pendapatan per Studio'
                        }
                    }
                }
            });

            // Monthly Revenue Chart
            const monthlyRevenueCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
            new Chart(monthlyRevenueCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M Y', strtotime($item['month'] . '-01')) . "'"; }, $chart_data)); ?>],
                    datasets: [{
                        label: 'Pendapatan',
                        data: [<?php echo implode(',', array_column($chart_data, 'revenue')); ?>],
                        backgroundColor: '#667eea'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Trend Pendapatan 6 Bulan Terakhir'
                        }
                    }
                }
            });
            <?php endif; ?>

            <?php if ($report_type === 'customers' && $chart_data): ?>
            // Customer Growth Chart
            const customerGrowthCtx = document.getElementById('customerGrowthChart').getContext('2d');
            new Chart(customerGrowthCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M Y', strtotime($item['month'] . '-01')) . "'"; }, $chart_data)); ?>],
                    datasets: [{
                        label: 'Customer Baru',
                        data: [<?php echo implode(',', array_column($chart_data, 'new_customers')); ?>],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Pertumbuhan Customer 6 Bulan Terakhir'
                        }
                    }
                }
            });
            <?php endif; ?>

            <?php if ($report_type === 'studio_utilization' && $report_data): ?>
            // Utilization Chart
            const utilizationCtx = document.getElementById('utilizationChart').getContext('2d');
            new Chart(utilizationCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['nama_studio'] . "'"; }, $report_data)); ?>],
                    datasets: [{
                        label: 'Tingkat Utilisasi (%)',
                        data: [<?php echo implode(',', array_column($report_data, 'utilization_rate')); ?>],
                        backgroundColor: '#667eea'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Tingkat Pemanfaatan Studio'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Persentase Utilisasi (%)'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });

        // Export functions
        function printReport() {
            window.print();
        }

        // Auto-update end date when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            const endDate = document.getElementById('end_date');
            if (this.value > endDate.value) {
                endDate.value = this.value;
            }
        });
    </script>
</body>
</html>