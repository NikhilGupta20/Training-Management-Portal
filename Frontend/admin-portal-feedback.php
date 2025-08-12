<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.php");
    exit;
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch admin details
$admin_username = $_SESSION['admin_username'];
$stmt = $conn->prepare("SELECT * FROM admin_credentials WHERE admin_username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit;
}

$admin = $result->fetch_assoc();
$username = htmlspecialchars($admin['admin_username']);
$stmt->close();

// Initialize date filter variables
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build feedback query with date filters
$feedbackQuery = "SELECT * FROM portal_feedback";
$whereClauses = [];
$params = [];
$types = '';

if (!empty($start_date)) {
    $whereClauses[] = "created_at >= ?";
    $params[] = $start_date . ' 00:00:00';
    $types .= 's';
}

if (!empty($end_date)) {
    $whereClauses[] = "created_at <= ?";
    $params[] = $end_date . ' 23:59:59';
    $types .= 's';
}

if (!empty($whereClauses)) {
    $feedbackQuery .= " WHERE " . implode(" AND ", $whereClauses);
}

$feedbackQuery .= " ORDER BY created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($feedbackQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$feedbackData = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate overall rating
$totalRating = 0;
$ratingCount = count($feedbackData);

foreach ($feedbackData as $feedback) {
    $totalRating += (int)$feedback['rating'];
}

$averageRating = $ratingCount > 0 ? round($totalRating / $ratingCount, 2) : 0;

$conn->close();

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Feedback | NCRB</title>
    
    <!-- Favicon links -->
    <link rel="apple-touch-icon" sizes="57x57" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="https://www.ncrb.gov.in/static/dist/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="https://www.ncrb.gov.in/static/dist/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="https://www.ncrb.gov.in/static/dist/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://www.ncrb.gov.in/static/dist/favicon/favicon-16x16.png">
    <link rel="manifest" href="https://www.ncrb.gov.in/static/dist/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="https://www.ncrb.gov.in/static/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ===== GLOBAL STYLES ===== */
        :root {
            --primary: #003366;
            --secondary: #0066cc;
            --accent: #ff9900;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            color: var(--dark);
            min-height: 100vh;
            padding-top: 200px;
        }

        /* Fixed Header Styles */
        .fixed-header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: var(--white);
            border-bottom: 1px solid var(--light-gray);
        }

        .logo-container {
            display: flex;
            align-items: center;
            height: 100px;
        }

        .logo-container img {
            height: 80px;
            max-width: 100%;
            object-fit: contain;
        }

        .ncrb-logo {
            height: 90px;
        }

        /* Navigation Bar */
        .nav-container {
            background: var(--primary);
            color: var(--white);
            padding: 5px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            width: 100%;
        }

        .nav-title {
            font-weight: bold;
            font-size: 1.3rem;
            letter-spacing: 0.5px;
        }

        .admin-info-container {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px 10px 15px;
            border-radius: 50px;
            transition: var(--transition);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .admin-info-container:hover {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            flex-shrink: 0;
        }

        .user-name {
            font-weight: 600;
            font-size: 16px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        /* Dashboard Section */
        .dashboard {
            max-width: 1400px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #002147;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
            padding-bottom: 15px;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background: linear-gradient(to right, #0066cc, #003366);
            border-radius: 2px;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 50px 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner h2 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .welcome-banner p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.7;
            position: relative;
            z-index: 2;
            opacity: 0.9;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Feedback Table Container */
        .feedback-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
        }

        .feedback-table-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .feedback-table th, .feedback-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e9ff;
        }

        .feedback-table th {
            background-color: #f0f7ff;
            color: #003366;
            font-weight: 600;
        }

        .feedback-table tr:hover {
            background-color: #f8fbff;
        }

        .star-rating {
            color: #FFD700; /* Gold color for stars */
        }

        .feedback-response {
            margin-top: 10px;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #17a2b8;
        }

        /* Date Filter Styles */
        .date-filter-container {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #d1e0ff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filter-title {
            font-size: 1.2rem;
            color: #003366;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            align-items: center;
        }

        .date-input-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 200px;
        }

        .date-input-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #003366;
        }

        .date-input {
            padding: 10px 15px;
            border: 1px solid #c2d9ff;
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .date-input:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
            outline: none;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
            width: 100%;
        }

        .filter-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .apply-filter {
            background: linear-gradient(to right, #17a2b8, #138496);
            color: white;
        }

        .reset-filter {
            background: linear-gradient(to right, #6c757d, #5a6268);
            color: white;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Rating Summary */
        .rating-summary {
            background: #e8f4ff;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
            border-left: 4px solid #0066cc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 15px;
            min-width: 180px;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #003366;
        }

        .summary-label {
            font-size: 1rem;
            color: #0066cc;
            font-weight: 500;
        }

        .average-rating-display {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .average-stars {
            color: #FFD700;
            font-size: 1.5rem;
        }

        /* Professional Footer */
        .professional-footer {
            background: var(--primary);
            color: var(--white);
            padding: 50px 5% 30px;
            margin-top: 50px;
        }

        .footer-columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
            align-items: center;
        }

        .footer-column {
            text-align: center;
            padding: 0 15px;
        }

        .footer-logo {
            max-width: 100px;
            height: auto;
            margin: 0 auto 15px;
        }

        .footer-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 600;
            color: var(--accent);
        }

        .footer-info {
            line-height: 1.8;
            font-size: 1rem;
        }

        .footer-info p {
            margin: 8px 0;
        }

        .footer-info i {
            width: 20px;
            margin-right: 10px;
            color: var(--accent);
        }
        
        .footer-info a {
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-info a:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .copyright a {
            color: inherit;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .copyright a:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .current-date {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 10px;
            color: var(--accent);
        }

        /* Print Button */
        .print-btn {
            background: linear-gradient(to right, #17a2b8, #138496);
            padding: 12px 30px;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            margin: 20px auto;
            text-align: center;
        }
        
        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Back to Dashboard Button */
        .back-to-dashboard {
            background: linear-gradient(to right, #ff9900, #e68a00);
            padding: 12px 30px;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            text-align: center;
        }
        
        .back-to-dashboard:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Developer Contact Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: #2c3e50;
            color: #ecf0f1;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            position: relative;
            border: 1px solid #1a252f;
        }
        
        .modal-header {
            background: #1a252f;
            color: #fff;
            padding: 20px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #f39c12;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            color: #f39c12;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Developer Contact Modal */
        .developer-contact h3 {
            color: #f39c12;
            font-size: 1.4rem;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .contact-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .contact-info p {
            margin: 12px 0;
            font-size: 1.05rem;
            text-align: center;
        }
        
        .contact-info i {
            color: #f39c12;
            width: 22px;
        }
        
        .contact-info a {
            color: #3498db;
            transition: var(--transition);
        }
        
        .contact-info a:hover {
            color: #f39c12;
        }
        
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        .contact-btn {
            padding: 14px 10px;
            font-size: 1rem;
            border-radius: 6px;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            text-align: center;
        }
        
        .contact-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        
        .call-btn { background: #28a745; }
        .sms-btn { background: #17a2b8; }
        .whatsapp-btn { background: #25D366; }
        .email-btn { background: #dc3545; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding-top: 250px;
            }
            
            .feedback-table th, .feedback-table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            
            .feedback-table-container {
                padding: 20px;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .date-input-group {
                width: 100%;
            }
            
            .rating-summary {
                flex-direction: column;
                gap: 15px;
            }
            
            .summary-item {
                width: 100%;
            }

            .contact-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Header -->
    <div class="fixed-header">
        <div class="header-logos">
            <div class="logo-container">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" alt="MHA Logo">
            </div>
            <div class="logo-container">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo" style="height: 90px;">
            </div>
        </div>
        
        <div class="nav-container">
            <div class="nav-content">
                <div class="nav-title">Portal Feedback Management | NCRB</div>
                <div class="admin-info-container">
                    <div class="user-avatar">
                        <?= strtoupper(substr($username, 0, 1)) ?>
                    </div>
                    <div class="user-name">
                        <?= $username ?>
                    </div>
                    <a href="admin-logout.php" class="logout-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-header">
        </div>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>User Feedback Analysis</h2>
            <p>Welcome, <strong><?= $username ?></strong>. Review and analyze user feedback collected through the portal.</p>
        </div>
        
        <!-- Alert Messages -->
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Feedback Table -->
        <div class="feedback-table-container">
            <h2 class="feedback-table-title">User Feedback Records</h2>
            
            <!-- Date Filter Form -->
            <div class="date-filter-container">
                <h3 class="filter-title">Filter Feedback by Date Range</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="date-input-group">
                        <label for="start_date">From Date</label>
                        <input type="date" id="start_date" name="start_date" class="date-input" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    
                    <div class="date-input-group">
                        <label for="end_date">To Date</label>
                        <input type="date" id="end_date" name="end_date" class="date-input" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="filter-btn apply-filter">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <a href="admin-portal-feedback.php" class="filter-btn reset-filter">
                            <i class="fas fa-sync-alt"></i> Reset Filter
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Rating Summary -->
            <div class="rating-summary">
                <div class="summary-item">
                    <span class="summary-value"><?= $ratingCount ?></span>
                    <span class="summary-label">Total Feedback Records</span>
                </div>
                
                <div class="summary-item">
                    <div class="average-rating-display">
                        <span class="summary-value"><?= $averageRating ?></span>
                        <span class="average-stars">
                            <?php
                            $fullStars = floor($averageRating);
                            $halfStar = ($averageRating - $fullStars) >= 0.5;
                            
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= $fullStars): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i == $fullStars + 1 && $halfStar): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif;
                            endfor; ?>
                        </span>
                    </div>
                    <span class="summary-label">Average Rating</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-value"><?= $totalRating ?></span>
                    <span class="summary-label">Total Rating Points</span>
                </div>
            </div>
            
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Topic</th>
                        <th>Rating</th>
                        <th>Feedback</th>
                        <th>Contact</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($feedbackData) > 0): ?>
                        <?php foreach($feedbackData as $feedback): ?>
                            <tr>
                                <td><?= $feedback['id'] ?></td>
                                <td><?= htmlspecialchars($feedback['visitor_name']) ?></td>
                                <td><?= htmlspecialchars($feedback['email']) ?></td>
                                <td><?= htmlspecialchars($feedback['topic']) ?></td>
                                <td>
                                    <div class="star-rating">
                                        <?php 
                                        $rating = (int)$feedback['rating'];
                                        for ($i = 1; $i <= 5; $i++): 
                                        ?>
                                            <i class="fas fa-star<?= $i <= $rating ? '' : '-empty' ?>"></i>
                                        <?php endfor; ?>
                                        (<?= $rating ?>/5)
                                    </div>
                                </td>
                                <td>
                                    <div class="feedback-content">
                                        <?= htmlspecialchars($feedback['feedback']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($feedback['contact_number']) ?></td>
                                <td><?= date('d M Y', strtotime($feedback['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No feedback records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="text-align: center; margin-top: 30px;">
                <button class="print-btn" onclick="printFeedback()">
                    <i class="fas fa-print"></i> Print Feedback Report
                </button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Professional Footer -->
    <footer class="professional-footer">
        <div class="footer-columns">
            <div class="footer-column">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/ncrb-logo.png" alt="NCRB Logo" class="footer-logo">
                <div class="footer-title">National Crime Records Bureau</div>
                <div class="footer-info">
                    Ministry of Home Affairs, Government of India
                </div>
            </div>
            
            <div class="footer-column">
                <div class="footer-title">Contact Information</div>
                <div class="footer-info">
                    <p><a href="https://www.google.com/maps/search/?api=1&query=NCRB,Mahipalpur,New+Delhi" target="_blank"><i class="fas fa-map-marker-alt"></i> NCRB, Mahipalpur, New Delhi</a></p>
                    <p><a href="tel:(011) 26735450"><i class="fas fa-phone"></i>Phone: (011) 26735450</a></p>
                    <p><a href="mailto:dct@ncrb.gov.in" class="mailto-link"><i class="fas fa-envelope"></i>Email: dct@ncrb.gov.in</a></p>
                    <p><i class="fas fa-clock"></i> Working Hours: 9:30 hours to 18:00 hours <br>(Saturday and Sunday Holidays)</p>
                </div>
            </div>
            
            <div class="footer-column">
                <div class="footer-title">About the Portal</div>
                <div class="footer-info">
                    <span>Updated On: <span id="current-date"></span></span>
                    <p>Content Management National Crime Records Bureau</p>
                    <p>&copy; <span id="copyright-year"></span> National Crime Records Bureau. <br>All Rights Reserved.</p>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            This portal is developed and maintained by 
            <a href="#" id="developer-contact-link">Nikhil Gupta</a> 
            and the Training Division of NCRB.
        </div>
    </footer>
    
    <!-- Developer Contact Modal -->
    <div id="developerContactModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Contact Developer
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="developer-contact">
                    <h3>Nikhil Gupta</h3>
                    <div class="contact-info">
                        <p><i class="fas fa-phone"></i> <a href="tel:+919818018487">+91-9818018487</a></p>
                        <p><i class="fas fa-envelope"></i> <a href="mailto:nikhillguptaa.2004@gmail.com">nikhillguptaa.2004@gmail.com</a></p>
                    </div>
                    <div class="contact-methods">
                        <a href="tel:+919818018487" class="contact-btn call-btn">
                            <i class="fas fa-phone"></i> Call
                        </a>
                        <a href="sms:+919818018487" class="contact-btn sms-btn">
                            <i class="fas fa-comment"></i> SMS
                        </a>
                        <a href="https://wa.me/919818018487" target="_blank" class="contact-btn whatsapp-btn">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="mailto:nikhillguptaa.2004@gmail.com" class="contact-btn email-btn">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set current date and year
        const dateOptions = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        document.getElementById('current-date').textContent = 
            new Date().toLocaleDateString('en-US', dateOptions);
        document.getElementById('copyright-year').textContent = new Date().getFullYear();
        
        // Set max date for date inputs to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').max = today;
        document.getElementById('end_date').max = today;
        
        // Validate date range
        document.querySelector('form').addEventListener('submit', function(e) {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            
            if (startDate && endDate && startDate > endDate) {
                alert('End date must be after start date');
                e.preventDefault();
            }
        });
        
        // Modal functionality
        const modals = document.querySelectorAll('.modal');
        const developerLink = document.getElementById('developer-contact-link');
        const closeModalButtons = document.querySelectorAll('.close-modal');
        
        // Open modal function
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Close modal function
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Developer contact link handler
        if (developerLink) {
            developerLink.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('developerContactModal');
            });
        }
        
        // Close modal buttons
        closeModalButtons.forEach(button => {
            button.addEventListener('click', () => {
                const modal = button.closest('.modal');
                closeModal(modal.id);
            });
        });
        
        // Close modal when clicking outside
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
        
        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                modals.forEach(modal => {
                    if (modal.style.display === 'flex') {
                        closeModal(modal.id);
                    }
                });
            }
        });
        
        // Print feedback function
        function printFeedback() {
            // Create a print window
            const printWindow = window.open('', '', 'width=1000,height=700');
            
            // Start building HTML content
            let printContent = `
            <html>
            <head>
                <title>NCRB Portal Feedback Report</title>
                <style>
                    body {
                        font-family: "Times New Roman", Times, serif;
                        margin: 1cm;
                        color: #000;
                        background: #fff;
                    }
                    .print-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        padding-bottom: 20px;
                        border-bottom: 2px solid #003366;
                    }
                    .print-header img {
                        height: 70px;
                    }
                    .print-title {
                        text-align: center;
                    }
                    .print-title h1 {
                        margin: 0;
                        font-size: 22pt;
                        color: #003366;
                    }
                    .print-title h2 {
                        margin: 5px 0 0 0;
                        font-size: 18pt;
                        font-weight: normal;
                        color: #0066cc;
                    }
                    .metadata {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 20px;
                        font-size: 11pt;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 10pt;
                        margin-top: 20px;
                    }
                    th {
                        background-color: #003366;
                        color: white;
                        padding: 10px;
                        border-bottom: 2px solid #003366;
                    }
                    td {
                        padding: 8px;
                        border-bottom: 1px solid #e0e9ff;
                    }
                    tbody tr:nth-child(even) {
                        background-color: #f8fbff;
                    }
                    .print-footer {
                        margin-top: 30px;
                        text-align: center;
                        font-style: italic;
                        color: #666;
                    }
                    .star-rating {
                        color: #FFD700; /* Gold color for stars */
                    }
                    .rating-summary {
                        background: #e8f4ff;
                        border-radius: 8px;
                        padding: 15px 20px;
                        margin: 20px 0;
                        border-left: 4px solid #0066cc;
                        display: flex;
                        justify-content: space-between;
                        flex-wrap: wrap;
                    }
                    .summary-item {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        padding: 10px 15px;
                        min-width: 180px;
                    }
                    .summary-value {
                        font-size: 1.8rem;
                        font-weight: 700;
                        color: #003366;
                    }
                    .summary-label {
                        font-size: 1rem;
                        color: #0066cc;
                        font-weight: 500;
                    }
                    .average-stars {
                        color: #FFD700;
                        font-size: 1.5rem;
                    }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" alt="MHA Logo">
                    <div class="print-title">
                        <h1>National Crime Records Bureau</h1>
                        <h2>Portal Feedback Report</h2>
                    </div>
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                </div>
                <div class="metadata">
                    <div><strong>Generated:</strong> ${new Date().toLocaleString()}</div>
                    <div><strong>Admin:</strong> <?= $username ?></div>
                    <div><strong>Total Feedback:</strong> <?= $ratingCount ?> records</div>
                    <div><strong>Date Range:</strong> 
                        <?= !empty($start_date) ? date('d M Y', strtotime($start_date)) : 'All dates' ?> 
                        to 
                        <?= !empty($end_date) ? date('d M Y', strtotime($end_date)) : 'All dates' ?>
                    </div>
                </div>
                
                <!-- Rating Summary -->
                <div class="rating-summary">
                    <div class="summary-item">
                        <span class="summary-value"><?= $ratingCount ?></span>
                        <span class="summary-label">Total Feedback Records</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-value"><?= $averageRating ?></span>
                        <span class="summary-label">Average Rating</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-value"><?= $totalRating ?></span>
                        <span class="summary-label">Total Rating Points</span>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Topic</th>
                            <th>Rating</th>
                            <th>Feedback</th>
                            <th>Contact</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            // Add table rows from feedbackData
            <?php foreach($feedbackData as $feedback): ?>
                printContent += `
                    <tr>
                        <td><?= $feedback['id'] ?></td>
                        <td><?= htmlspecialchars($feedback['visitor_name']) ?></td>
                        <td><?= htmlspecialchars($feedback['email']) ?></td>
                        <td><?= htmlspecialchars($feedback['topic']) ?></td>
                        <td>
                            <div class="star-rating">
                                <?php 
                                $rating = (int)$feedback['rating'];
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <i class="fas fa-star<?= $i <= $rating ? '' : '-empty' ?>"></i>
                                <?php endfor; ?>
                                (<?= $rating ?>/5)
                            </div>
                        </td>
                        <td><?= htmlspecialchars($feedback['feedback']) ?></td>
                        <td><?= htmlspecialchars($feedback['contact_number']) ?></td>
                        <td><?= date('d M Y', strtotime($feedback['created_at'])) ?></td>
                    </tr>`;
            <?php endforeach; ?>
            
            // Add footer
            printContent += `
                    </tbody>
                </table>
                <div class="print-footer">
                    <p>National Crime Records Bureau<br>
                    Ministry of Home Affairs, Government of India</p>
                    <p>Generated by NCRB Training Calendar Management System</p>
                </div>
            </body>
            </html>`;
            
            // Write content to print window
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Trigger print after content loads
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }, 500);
        }

        // Header shadow on scroll
        const header = document.querySelector('.fixed-header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.2)';
            } else {
                header.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            }
        });
    </script>
</body>
</html>