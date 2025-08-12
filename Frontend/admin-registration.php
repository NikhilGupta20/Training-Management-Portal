<?php
// admin-registration.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.php");
    exit;
}

// Handle AJAX request for districts
if (isset($_GET['action']) && $_GET['action'] === 'get_districts' && isset($_GET['state'])) {
    // Database configuration
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'ncrb_training';
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
    }

    $stateName = $conn->real_escape_string($_GET['state']);
    $stmt = $conn->prepare("SELECT district_name FROM districts WHERE state_name = ? ORDER BY district_name");
    $stmt->bind_param("s", $stateName);
    $stmt->execute();
    $result = $stmt->get_result();
    $districts = [];
    while ($row = $result->fetch_assoc()) {
        $districts[] = $row;
    }
    $stmt->close();
    $conn->close();

    header('Content-Type: application/json');
    echo json_encode($districts);
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

// Create required tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS registration (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        userid VARCHAR(50) NOT NULL,
        registration_id VARCHAR(50) NOT NULL,
        participant_name VARCHAR(255) NOT NULL,
        participant_name_hindi VARCHAR(255) NOT NULL,
        gender VARCHAR(10) NOT NULL,
        category_name VARCHAR(50) NOT NULL,
        category_name_hindi VARCHAR(50) NOT NULL,
        rank VARCHAR(100) NOT NULL,
        rank_hindi VARCHAR(100) NOT NULL,
        other_rank VARCHAR(100),
        other_rank_hindi VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        qualifications TEXT NOT NULL,
        residential_address TEXT NOT NULL,
        state_name VARCHAR(100) NOT NULL,
        state_name_hindi VARCHAR(100) NOT NULL,
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(20) NOT NULL,
        delhi_address TEXT,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        course_expectation TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        error_log("Table creation failed: " . $conn->error);
    }
}

// Get admin username from session
$admin_username = $_SESSION['admin_username'];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch filter data
$courses = $conn->query("SELECT DISTINCT course_name FROM training_events")->fetch_all(MYSQLI_ASSOC);
$states = $conn->query("SELECT * FROM states")->fetch_all(MYSQLI_ASSOC);

// Retain selected filters
$selectedState = $_GET['state'] ?? '';
$selectedDistricts = [];
if (!empty($selectedState)) {
    $stmt = $conn->prepare("SELECT district_name FROM districts WHERE state_name = ? ORDER BY district_name");
    $stmt->bind_param("s", $selectedState);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedDistricts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Initialize filter variables
$conditions = [];
$params = [];
$paramTypes = '';
$paramValues = [];

// Handle filters
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $conditions[] = "start_date BETWEEN ? AND ?";
    $paramTypes .= 'ss';
    $paramValues[] = $_GET['start_date'];
    $paramValues[] = $_GET['end_date'];
}
if (!empty($_GET['course'])) {
    $conditions[] = "course_name = ?";
    $paramTypes .= 's';
    $paramValues[] = $_GET['course'];
}
if (!empty($_GET['state'])) {
    $conditions[] = "state_name = ?";
    $paramTypes .= 's';
    $paramValues[] = $_GET['state'];
}
if (!empty($_GET['district'])) {
    $conditions[] = "district_name = ?";
    $paramTypes .= 's';
    $paramValues[] = $_GET['district'];
}
if (!empty($_GET['gender'])) {
    $conditions[] = "gender = ?";
    $paramTypes .= 's';
    $paramValues[] = $_GET['gender'];
}
if (!empty($_GET['search'])) {
    $conditions[] = "participant_name LIKE ?";
    $paramTypes .= 's';
    $paramValues[] = "%" . $_GET['search'] . "%";
}

// Build query with prepared statements
$whereClause = '';
if (!empty($conditions)) {
    $whereClause = " WHERE " . implode(" AND ", $conditions);
} else {
    // Show no records if no filters are applied
    $whereClause = " WHERE 1=0";
}

// Fetch data for reports
$stateWiseData = [];
$stateCourseWiseData = [];

// State-wise counts
$stateQuery = "SELECT state_name, COUNT(*) as count FROM registration $whereClause GROUP BY state_name ORDER BY state_name";
$stateStmt = $conn->prepare($stateQuery);
if (!empty($paramTypes)) {
    $stateStmt->bind_param($paramTypes, ...$paramValues);
}
$stateStmt->execute();
$result = $stateStmt->get_result();
$stateWiseData = $result->fetch_all(MYSQLI_ASSOC);
$stateStmt->close();

// State and Course-wise counts
$stateCourseQuery = "SELECT state_name, course_name, COUNT(*) as count FROM registration $whereClause GROUP BY state_name, course_name ORDER BY state_name, course_name";
$stateCourseStmt = $conn->prepare($stateCourseQuery);
if (!empty($paramTypes)) {
    $stateCourseStmt->bind_param($paramTypes, ...$paramValues);
}
$stateCourseStmt->execute();
$result = $stateCourseStmt->get_result();
$stateCourseWiseData = $result->fetch_all(MYSQLI_ASSOC);
$stateCourseStmt->close();

// Main query for records table
$sql = "SELECT * FROM registration $whereClause";
$stmt = $conn->prepare($sql);
if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$paramValues);
}
$stmt->execute();
$result = $stmt->get_result();
$results = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build query string without report parameter
$queryParams = $_GET;
unset($queryParams['report']);
$queryString = http_build_query($queryParams);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Registration Management - NCRB Training Portal</title>
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
    <meta name="msapplication-TileImage" content="https://www.ncrb.gov.in/static//ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <!-- External Stylesheets -->
    <link type="text/css" rel="stylesheet" href="https://www.ncrb.gov.in/static/admin/dist/deploy/app.min.css?rel=20231028004" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        /* ===== CSS VARIABLES ===== */
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
        }

        /* ===== GLOBAL STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            color: var(--dark);
            min-height: 100vh;
            padding-top: 200px; /* Increased padding to prevent collision */
            background-color: #f5f7fa;
        }

        /* ===== HEADER STYLES ===== */
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
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
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

        /* ===== MAIN CONTENT STYLES ===== */
        .dashboard-container {
            background-color: #fff;
            border-radius: 18px;
            padding: 40px;
            box-shadow: var(--shadow);
            max-width: 1400px;
            margin: 0 auto 50px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
            position: relative;
            display: inline-block;
            padding-bottom: 15px;
        }

        .dashboard-header h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 25%;
            right: 25%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--secondary), transparent);
            border-radius: 2px;
        }

        /* Filter Form Styles */
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .date-range-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-separator {
            color: #666;
            padding: 0 5px;
        }

        .filter-group.date-range-group {
            grid-column: span 2;
        }

        @media (max-width: 992px) {
            .filter-group.date-range-group {
                grid-column: span 1;
            }
            .date-range-container {
                flex-direction: column;
                align-items: stretch;
            }
            .date-separator {
                text-align: center;
                padding: 5px 0;
            }
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary);
            font-size: 14px;
        }

        select, input[type="text"], input[type="date"] {
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background-color: #fff;
            font-size: 14px;
            transition: var(--transition);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            width: 100%;
        }

        select:focus, input[type="text"]:focus, input[type="date"]:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .filter-btn, .reset-btn {
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 102, 204, 0.25);
        }

        .reset-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            box-shadow: 0 4px 6px rgba(107, 114, 128, 0.2);
        }

        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(107, 114, 128, 0.25);
            color: white;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 14px 15px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        tr:hover {
            background-color: #f0f7ff;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .certificate-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .certificate-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
        }

        .action-btn {
            padding: 12px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin: 5px;
            color: white;
        }

        .print-btn {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .dashboard-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .statistics-btn {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
        }

        .btn-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--gray);
            font-size: 18px;
        }

        /* ===== FOOTER STYLES ===== */
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

        /* Developer Link Styling */
        .copyright a#developer-contact-link {
            font-weight: normal;
            color: inherit;
            text-decoration: none;
        }

        .copyright a#developer-contact-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        /* ===== RESPONSIVE ADJUSTMENTS ===== */
        @media (max-width: 1200px) {
            body {
                padding-top: 190px;
            }
            
            .dashboard-container {
                padding: 30px;
            }
        }

        @media (max-width: 992px) {
            .filters {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 180px;
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            .action-btns {
                flex-direction: column;
                gap: 5px;
            }

            .date-range-container {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 250px;
            }
            
            .dashboard-container {
                padding: 20px 15px;
            }
            
            .btn-container {
                flex-direction: column;
                align-items: center;
            }
            
            .action-btn {
                width: 100%;
                max-width: 300px;
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
                <img class="ncrb-logo" src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
            </div>
        </div>
        
        <div class="nav-container">
            <div class="nav-content">
                <div class="nav-title">National Crime Records Bureau - Registration Management</div>
                <div class="admin-info-container">
                    <div class="user-avatar">
                        <?php 
                            $initial = strtoupper(substr($admin_username, 0, 1));
                            echo $initial;
                        ?>
                    </div>
                    <div class="user-name">
                        <?php echo $admin_username; ?>
                    </div>
                    <a href="admin-logout.php" class="logout-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Registration Management</h1>
        </div>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Filter Form -->
        <form method="get" class="filters">
            <!-- Date Range Filter -->
            <div class="filter-group date-range-group">
                <label>Date Range</label>
                <div class="date-range-container">
                    <input type="date" name="start_date" id="startDate" value="<?= $_GET['start_date'] ?? '' ?>" style="flex:1">
                    <span class="date-separator">to</span>
                    <input type="date" name="end_date" id="endDate" value="<?= $_GET['end_date'] ?? '' ?>" style="flex:1">
                </div>
            </div>
            
            <div class="filter-group">
                <label for="courseSelect">Course</label>
                <select name="course" id="courseSelect">
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['course_name'] ?>" <?= ($_GET['course'] ?? '') === $course['course_name'] ? 'selected' : '' ?>>
                            <?= $course['course_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="stateSelect">State</label>
                <select name="state" id="stateSelect" onchange="loadDistricts(this.value)">
                    <option value="">-- Select State --</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?= $state['state_name'] ?>" <?= ($_GET['state'] ?? '') === $state['state_name'] ? 'selected' : '' ?>>
                            <?= $state['state_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="districtSelect">District</label>
                <select name="district" id="districtSelect">
                    <option value="">-- Select District --</option>
                    <?php foreach ($selectedDistricts as $district): ?>
                        <option value="<?= $district['district_name'] ?>" <?= ($_GET['district'] ?? '') === $district['district_name'] ? 'selected' : '' ?>>
                            <?= $district['district_name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="genderSelect">Gender</label>
                <select name="gender" id="genderSelect">
                    <option value="">-- Gender --</option>
                    <option value="Male" <?= ($_GET['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($_GET['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($_GET['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="searchInput">Search</label>
                <input type="text" name="search" id="searchInput" placeholder="Participant name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="filter-btn">Filter/Search</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="admin-registration.php" class="reset-btn">Reset Filters</a>
            </div>
        </form>

        <!-- Results Table -->
        <?php if (!empty($results)): ?>
            <div id="reportSection">
                <table border="1" cellspacing="0" cellpadding="5">
                    <thead>
                        <tr>
                            <th>Serial No.</th>
                            <th>Participant Name</th>
                            <th>Course Name</th>
                            <th>Course Start</th>
                            <th>Course End</th>
                            <th>State</th>
                            <th>District</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; ?>
                        <?php foreach ($results as $row): ?>
                            <tr data-gender="<?= htmlspecialchars($row['gender'] ?? '') ?>">
                                <td><?= $serial++ ?></td>
                                <td><?= htmlspecialchars($row['participant_name']) ?></td>
                                <td><?= htmlspecialchars($row['course_name']) ?></td>
                                <td><?= date("d-m-Y", strtotime($row['start_date'])) ?></td>
                                <td><?= date("d-m-Y", strtotime($row['end_date'])) ?></td>
                                <td><?= htmlspecialchars($row['state_name']) ?></td>
                                <td><?= htmlspecialchars($row['district_name']) ?></td>
                                <td>
                                    <button onclick="generateCertificate(
                                        '<?= htmlspecialchars($row['gender'] ?? '') ?>',
                                        '<?= addslashes(htmlspecialchars($row['participant_name'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['participant_name_hindi'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['rank'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['rank_hindi'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['state_name'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['state_name_hindi'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['course_name'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['course_name_hindi'] ?? '')) ?>',
                                        '<?= date("d-m-Y", strtotime($row['start_date'])) ?>',
                                        '<?= date("d-m-Y", strtotime($row['end_date'])) ?>',
                                        '<?= htmlspecialchars($row['registration_id'] ?? 'NCRB-CERT-'.rand(1000,9999)) ?>',
                                        '<?= addslashes(htmlspecialchars($row['other_rank'] ?? '')) ?>',
                                        '<?= addslashes(htmlspecialchars($row['other_rank_hindi'] ?? '')) ?>'
                                    )" class="certificate-btn">Generate Certificate</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="btn-container">
                <button onclick="printReport()" class="action-btn print-btn">Print Report</button>
                <button onclick="printStateWiseReport()" class="action-btn statistics-btn">State-wise Report</button>
                <button onclick="printStateCourseWiseReport()" class="action-btn statistics-btn">State & Course Report</button>
                <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <p class="no-data">
                <?php if (!empty($conditions)): ?>
                    No data found matching your criteria.
                <?php else: ?>
                    Please apply filters to view participant records.
                <?php endif; ?>
            </p>
            <div class="btn-container">
                <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
            </div>
        <?php endif; ?>
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

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
    // Update current date
    const dateOptions = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    document.getElementById('current-date').textContent = 
        new Date().toLocaleDateString('en-US', dateOptions);
        
    // Set dynamic copyright year
    document.getElementById('copyright-year').textContent = new Date().getFullYear();

    // Function to load districts based on selected state
    function loadDistricts(stateName) {
        if (!stateName) {
            document.getElementById("districtSelect").innerHTML = '<option value="">-- Select District --</option>';
            return;
        }

        const districtSelect = document.getElementById("districtSelect");
        districtSelect.innerHTML = '<option value="">Loading...</option>';

        fetch('admin-registration.php?action=get_districts&state=' + encodeURIComponent(stateName))
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (!Array.isArray(data)) {
                    throw new Error('Invalid response format');
                }
                
                let options = '<option value="">-- Select District --</option>';
                data.forEach(d => {
                    if (d.district_name) {
                        options += `<option value="${d.district_name}">${d.district_name}</option>`;
                    }
                });
                districtSelect.innerHTML = options;
                
                // Restore selected district if any
                const selectedDistrict = "<?= $_GET['district'] ?? '' ?>";
                if (selectedDistrict) {
                    districtSelect.value = selectedDistrict;
                }
            })
            .catch(error => {
                console.error('Error loading districts:', error);
                districtSelect.innerHTML = '<option value="">Error loading districts</option>';
            });
    }

    // ===== MODAL FUNCTIONALITY =====
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

    // Function to print the main report
    function printReport() {
        // Clone the report section
        const reportClone = document.getElementById('reportSection').cloneNode(true);
    
        // Create a print container
        const printContainer = document.createElement('div');
        printContainer.id = 'print-container';
    
        // Add title with logos
        const header = document.createElement('div');
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.marginBottom = '20px';
        header.style.borderBottom = '2px solid #003366';
        header.style.paddingBottom = '15px';
        
        const logo1 = document.createElement('img');
        logo1.src = 'https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png';
        logo1.style.height = '70px';
        
        const logo2 = document.createElement('img');
        logo2.src = 'https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png';
        logo2.style.height = '70px';
        
        const titleContainer = document.createElement('div');
        titleContainer.style.textAlign = 'center';
        
        const title = document.createElement('h1');
        title.textContent = 'National Crime Records Bureau';
        title.style.margin = '0';
        title.style.fontSize = '22pt';
        title.style.color = '#003366';
        
        const subtitle = document.createElement('h2');
        subtitle.textContent = 'Registration Management Report';
        subtitle.style.margin = '5px 0 0 0';
        subtitle.style.fontSize = '18pt';
        subtitle.style.fontWeight = 'normal';
        subtitle.style.color = '#0066cc';
        
        titleContainer.appendChild(title);
        titleContainer.appendChild(subtitle);
        
        header.appendChild(logo1);
        header.appendChild(titleContainer);
        header.appendChild(logo2);
        printContainer.appendChild(header);
        
        // Add report metadata
        const metaContainer = document.createElement('div');
        metaContainer.style.display = 'flex';
        metaContainer.style.justifyContent = 'space-between';
        metaContainer.style.marginBottom = '20px';
        metaContainer.style.fontSize = '11pt';
        
        const generatedDate = document.createElement('div');
        generatedDate.innerHTML = `<strong>Generated:</strong> ${new Date().toLocaleString()}`;
        
        const adminInfo = document.createElement('div');
        adminInfo.innerHTML = `<strong>Admin:</strong> <?= $admin_username ?>`;
        
        metaContainer.appendChild(generatedDate);
        metaContainer.appendChild(adminInfo);
        printContainer.appendChild(metaContainer);
        
        // Add filter information
        const filters = [];
        <?php if (!empty($_GET['start_date']) && !empty($_GET['end_date'])): ?>
            filters.push(`Date Range: <?= htmlspecialchars($_GET['start_date']) ?> to <?= htmlspecialchars($_GET['end_date']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['course'])): ?>
            filters.push(`Course: <?= htmlspecialchars($_GET['course']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['state'])): ?>
            filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['district'])): ?>
            filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['gender'])): ?>
            filters.push(`Gender: <?= htmlspecialchars($_GET['gender']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['search'])): ?>
            filters.push(`Search: <?= htmlspecialchars($_GET['search']) ?>`);
        <?php endif; ?>
    
        if (filters.length > 0) {
            const filterInfo = document.createElement('div');
            filterInfo.style.marginBottom = '15px';
            filterInfo.style.padding = '10px';
            filterInfo.style.backgroundColor = '#f0f7ff';
            filterInfo.style.borderLeft = '4px solid #0066cc';
            filterInfo.innerHTML = `<strong>Filters Applied:</strong> ${filters.join(', ')}`;
            printContainer.appendChild(filterInfo);
        }
        
        // Modify participant names based on gender
        const rows = reportClone.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.cells;
            const nameCell = cells[1];
            const gender = row.getAttribute('data-gender') || '';
        
            if (gender.toLowerCase() === 'female') {
                nameCell.textContent = 'Smt. ' + nameCell.textContent;
            } else if (gender) {
                nameCell.textContent = 'Shri ' + nameCell.textContent;
            }
            
            // Remove the certificate button
            const certificateCell = cells[7];
            certificateCell.innerHTML = '';
        });
    
        // Add the modified cloned report
        printContainer.appendChild(reportClone);
        
        // Add footer
        const footer = document.createElement('div');
        footer.style.marginTop = '30px';
        footer.style.textAlign = 'center';
        footer.style.fontSize = '10pt';
        footer.style.color = '#666';
        footer.innerHTML = `
            <p>National Crime Records Bureau<br>
            Ministry of Home Affairs, Government of India</p>
            <p>Generated by NCRB Participant Registration System</p>
        `;
        printContainer.appendChild(footer);
    
        // Create a print window
        const printWindow = window.open('', '', 'width=1000,height=700');
        printWindow.document.write('<html><head><title>NCRB Registration Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContainer.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
    
        // Trigger print after content loads
        setTimeout(function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    // Function to print state-wise report
    function printStateWiseReport() {
        const stateWiseData = <?= json_encode($stateWiseData) ?>;
    
        if (stateWiseData.length === 0) {
            alert('No data available for state-wise report');
            return;
        }
    
        // Create report container
        const printContainer = document.createElement('div');
        printContainer.id = 'print-container';
    
        // Add title with logos
        const header = document.createElement('div');
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.marginBottom = '20px';
        header.style.borderBottom = '2px solid #003366';
        header.style.paddingBottom = '15px';
        
        const logo1 = document.createElement('img');
        logo1.src = 'https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png';
        logo1.style.height = '70px';
        
        const logo2 = document.createElement('img');
        logo2.src = 'https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png';
        logo2.style.height = '70px';
        
        const titleContainer = document.createElement('div');
        titleContainer.style.textAlign = 'center';
        
        const title = document.createElement('h1');
        title.textContent = 'National Crime Records Bureau';
        title.style.margin = '0';
        title.style.fontSize = '22pt';
        title.style.color = '#003366';
        
        const subtitle = document.createElement('h2');
        subtitle.textContent = 'State-wise Registration Report';
        subtitle.style.margin = '5px 0 0 0';
        subtitle.style.fontSize = '18pt';
        subtitle.style.fontWeight = 'normal';
        subtitle.style.color = '#0066cc';
        
        titleContainer.appendChild(title);
        titleContainer.appendChild(subtitle);
        
        header.appendChild(logo1);
        header.appendChild(titleContainer);
        header.appendChild(logo2);
        printContainer.appendChild(header);
        
        // Add report metadata
        const metaContainer = document.createElement('div');
        metaContainer.style.display = 'flex';
        metaContainer.style.justifyContent = 'space-between';
        metaContainer.style.marginBottom = '20px';
        metaContainer.style.fontSize = '11pt';
        
        const generatedDate = document.createElement('div');
        generatedDate.innerHTML = `<strong>Generated:</strong> ${new Date().toLocaleString()}`;
        
        const adminInfo = document.createElement('div');
        adminInfo.innerHTML = `<strong>Admin:</strong> <?= $admin_username ?>`;
        
        metaContainer.appendChild(generatedDate);
        metaContainer.appendChild(adminInfo);
        printContainer.appendChild(metaContainer);
        
        // Add filter information
        const filters = [];
        <?php if (!empty($_GET['start_date']) && !empty($_GET['end_date'])): ?>
            filters.push(`Date Range: <?= htmlspecialchars($_GET['start_date']) ?> to <?= htmlspecialchars($_GET['end_date']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['course'])): ?>
            filters.push(`Course: <?= htmlspecialchars($_GET['course']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['state'])): ?>
            filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['district'])): ?>
            filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['gender'])): ?>
            filters.push(`Gender: <?= htmlspecialchars($_GET['gender']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['search'])): ?>
            filters.push(`Search: <?= htmlspecialchars($_GET['search']) ?>`);
        <?php endif; ?>
    
        if (filters.length > 0) {
            const filterInfo = document.createElement('div');
            filterInfo.style.marginBottom = '15px';
            filterInfo.style.padding = '10px';
            filterInfo.style.backgroundColor = '#f0f7ff';
            filterInfo.style.borderLeft = '4px solid #0066cc';
            filterInfo.innerHTML = `<strong>Filters Applied:</strong> ${filters.join(', ')}`;
            printContainer.appendChild(filterInfo);
        }
        
        // Create table
        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.fontSize = '10pt';
        table.style.marginTop = '20px';
        
        // Create table header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        
        ['S.No.', 'State', 'Number of Registrations'].forEach(text => {
            const th = document.createElement('th');
            th.textContent = text;
            th.style.backgroundColor = '#003366';
            th.style.color = 'white';
            th.style.padding = '10px';
            th.style.border = '1px solid #ddd';
            headerRow.appendChild(th);
        });
        
        thead.appendChild(headerRow);
        table.appendChild(thead);
        
        // Create table body
        const tbody = document.createElement('tbody');
        let total = 0;
        
        stateWiseData.forEach((row, index) => {
            const tr = document.createElement('tr');
            
            const td1 = document.createElement('td');
            td1.textContent = index + 1;
            td1.style.padding = '8px';
            td1.style.border = '1px solid #ddd';
            tr.appendChild(td1);
            
            const td2 = document.createElement('td');
            td2.textContent = row.state_name;
            td2.style.padding = '8px';
            td2.style.border = '1px solid #ddd';
            tr.appendChild(td2);
            
            const td3 = document.createElement('td');
            td3.textContent = row.count;
            td3.style.padding = '8px';
            td3.style.border = '1px solid #ddd';
            td3.style.textAlign = 'center';
            tr.appendChild(td3);
            
            tbody.appendChild(tr);
            total += parseInt(row.count);
        });
        
        // Add total row
        const totalRow = document.createElement('tr');
        totalRow.style.fontWeight = 'bold';
        
        const totalTd1 = document.createElement('td');
        totalTd1.textContent = '';
        totalTd1.style.padding = '8px';
        totalTd1.style.border = '1px solid #ddd';
        totalRow.appendChild(totalTd1);
        
        const totalTd2 = document.createElement('td');
        totalTd2.textContent = 'Total';
        totalTd2.style.padding = '8px';
        totalTd2.style.border = '1px solid #ddd';
        totalRow.appendChild(totalTd2);
        
        const totalTd3 = document.createElement('td');
        totalTd3.textContent = total;
        totalTd3.style.padding = '8px';
        totalTd3.style.border = '1px solid #ddd';
        totalTd3.style.textAlign = 'center';
        totalRow.appendChild(totalTd3);
        
        tbody.appendChild(totalRow);
        table.appendChild(tbody);
        printContainer.appendChild(table);
        
        // Add footer
        const footer = document.createElement('div');
        footer.style.marginTop = '30px';
        footer.style.textAlign = 'center';
        footer.style.fontSize = '10pt';
        footer.style.color = '#666';
        footer.innerHTML = `
            <p>National Crime Records Bureau<br>
            Ministry of Home Affairs, Government of India</p>
            <p>Generated by NCRB Participant Registration System</p>
        `;
        printContainer.appendChild(footer);
    
        // Create a print window
        const printWindow = window.open('', '', 'width=1000,height=700');
        printWindow.document.write('<html><head><title>NCRB State-wise Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContainer.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
    
        // Trigger print after content loads
        setTimeout(function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    // Function to print state and course-wise report
    function printStateCourseWiseReport() {
        const stateCourseWiseData = <?= json_encode($stateCourseWiseData) ?>;
    
        if (stateCourseWiseData.length === 0) {
            alert('No data available for state & course-wise report');
            return;
        }
    
        // Create report container
        const printContainer = document.createElement('div');
        printContainer.id = 'print-container';
    
        // Add title with logos
        const header = document.createElement('div');
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.marginBottom = '20px';
        header.style.borderBottom = '2px solid #003366';
        header.style.paddingBottom = '15px';
        
        const logo1 = document.createElement('img');
        logo1.src = 'https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png';
        logo1.style.height = '70px';
        
        const logo2 = document.createElement('img');
        logo2.src = 'https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png';
        logo2.style.height = '70px';
        
        const titleContainer = document.createElement('div');
        titleContainer.style.textAlign = 'center';
        
        const title = document.createElement('h1');
        title.textContent = 'National Crime Records Bureau';
        title.style.margin = '0';
        title.style.fontSize = '22pt';
        title.style.color = '#003366';
        
        const subtitle = document.createElement('h2');
        subtitle.textContent = 'State & Course Registration Report';
        subtitle.style.margin = '5px 0 0 0';
        subtitle.style.fontSize = '18pt';
        subtitle.style.fontWeight = 'normal';
        subtitle.style.color = '#0066cc';
        
        titleContainer.appendChild(title);
        titleContainer.appendChild(subtitle);
        
        header.appendChild(logo1);
        header.appendChild(titleContainer);
        header.appendChild(logo2);
        printContainer.appendChild(header);
        
        // Add report metadata
        const metaContainer = document.createElement('div');
        metaContainer.style.display = 'flex';
        metaContainer.style.justifyContent = 'space-between';
        metaContainer.style.marginBottom = '20px';
        metaContainer.style.fontSize = '11pt';
        
        const generatedDate = document.createElement('div');
        generatedDate.innerHTML = `<strong>Generated:</strong> ${new Date().toLocaleString()}`;
        
        const adminInfo = document.createElement('div');
        adminInfo.innerHTML = `<strong>Admin:</strong> <?= $admin_username ?>`;
        
        metaContainer.appendChild(generatedDate);
        metaContainer.appendChild(adminInfo);
        printContainer.appendChild(metaContainer);
        
        // Add filter information
        const filters = [];
        <?php if (!empty($_GET['start_date']) && !empty($_GET['end_date'])): ?>
            filters.push(`Date Range: <?= htmlspecialchars($_GET['start_date']) ?> to <?= htmlspecialchars($_GET['end_date']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['course'])): ?>
            filters.push(`Course: <?= htmlspecialchars($_GET['course']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['state'])): ?>
            filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['district'])): ?>
            filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['gender'])): ?>
            filters.push(`Gender: <?= htmlspecialchars($_GET['gender']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['search'])): ?>
            filters.push(`Search: <?= htmlspecialchars($_GET['search']) ?>`);
        <?php endif; ?>
    
        if (filters.length > 0) {
            const filterInfo = document.createElement('div');
            filterInfo.style.marginBottom = '15px';
            filterInfo.style.padding = '10px';
            filterInfo.style.backgroundColor = '#f0f7ff';
            filterInfo.style.borderLeft = '4px solid #0066cc';
            filterInfo.innerHTML = `<strong>Filters Applied:</strong> ${filters.join(', ')}`;
            printContainer.appendChild(filterInfo);
        }
        
        // Create table
        const table = document.createElement('table');
        table.style.width = '100%';
        table.style.borderCollapse = 'collapse';
        table.style.fontSize = '10pt';
        table.style.marginTop = '20px';
        
        // Create table header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        
        ['S.No.', 'State', 'Course', 'Number of Registrations'].forEach(text => {
            const th = document.createElement('th');
            th.textContent = text;
            th.style.backgroundColor = '#003366';
            th.style.color = 'white';
            th.style.padding = '10px';
            th.style.border = '1px solid #ddd';
            headerRow.appendChild(th);
        });
        
        thead.appendChild(headerRow);
        table.appendChild(thead);
        
        // Create table body
        const tbody = document.createElement('tbody');
        let total = 0;
        
        stateCourseWiseData.forEach((row, index) => {
            const tr = document.createElement('tr');
            
            const td1 = document.createElement('td');
            td1.textContent = index + 1;
            td1.style.padding = '8px';
            td1.style.border = '1px solid #ddd';
            tr.appendChild(td1);
            
            const td2 = document.createElement('td');
            td2.textContent = row.state_name;
            td2.style.padding = '8px';
            td2.style.border = '1px solid #ddd';
            tr.appendChild(td2);
            
            const td3 = document.createElement('td');
            td3.textContent = row.course_name;
            td3.style.padding = '8px';
            td3.style.border = '1px solid #ddd';
            tr.appendChild(td3);
            
            const td4 = document.createElement('td');
            td4.textContent = row.count;
            td4.style.padding = '8px';
            td4.style.border = '1px solid #ddd';
            td4.style.textAlign = 'center';
            tr.appendChild(td4);
            
            tbody.appendChild(tr);
            total += parseInt(row.count);
        });
        
        // Add total row
        const totalRow = document.createElement('tr');
        totalRow.style.fontWeight = 'bold';
        
        const totalTd1 = document.createElement('td');
        totalTd1.textContent = '';
        totalTd1.style.padding = '8px';
        totalTd1.style.border = '1px solid #ddd';
        totalRow.appendChild(totalTd1);
        
        const totalTd2 = document.createElement('td');
        totalTd2.textContent = 'Total';
        totalTd2.style.padding = '8px';
        totalTd2.style.border = '1px solid #ddd';
        totalRow.appendChild(totalTd2);
        
        const totalTd3 = document.createElement('td');
        totalTd3.textContent = '';
        totalTd3.style.padding = '8px';
        totalTd3.style.border = '1px solid #ddd';
        totalRow.appendChild(totalTd3);
        
        const totalTd4 = document.createElement('td');
        totalTd4.textContent = total;
        totalTd4.style.padding = '8px';
        totalTd4.style.border = '1px solid #ddd';
        totalTd4.style.textAlign = 'center';
        totalRow.appendChild(totalTd4);
        
        tbody.appendChild(totalRow);
        table.appendChild(tbody);
        printContainer.appendChild(table);
        
        // Add footer
        const footer = document.createElement('div');
        footer.style.marginTop = '30px';
        footer.style.textAlign = 'center';
        footer.style.fontSize = '10pt';
        footer.style.color = '#666';
        footer.innerHTML = `
            <p>National Crime Records Bureau<br>
            Ministry of Home Affairs, Government of India</p>
            <p>Generated by NCRB Participant Registration System</p>
        `;
        printContainer.appendChild(footer);
    
        // Create a print window
        const printWindow = window.open('', '', 'width=1000,height=700');
        printWindow.document.write('<html><head><title>NCRB State & Course Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContainer.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
    
        // Trigger print after content loads
        setTimeout(function() {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    // Function to generate certificate
    function generateCertificate(gender, name, nameHindi, rank, rankHindi, state, stateHindi, courseName, courseNameHindi, startDate, endDate, certificateNumber, otherRank, otherRankHindi) {
        // If rank is empty, use otherRank
        if (!rank || rank.trim() === '') {
            rank = otherRank;
            rankHindi = otherRankHindi;
        }
        // Generate certificate HTML
        const certificateHTML = createCertificateHTML(
            gender,
            name, nameHindi, 
            rank, rankHindi,
            state, stateHindi,
            courseName, courseNameHindi,
            startDate, endDate,
            certificateNumber
        );

        // Open in new window
        const certificateWindow = window.open('', '_blank');
        if (!certificateWindow) {
            alert('Popup blocked! Please allow popups for this site.');
            return;
        }

        certificateWindow.document.write(certificateHTML);
        certificateWindow.document.close();
    }

    // HTML template function
    function createCertificateHTML(gender, name, nameHindi, rank, rankHindi, state, stateHindi, courseName, courseNameHindi, startDate, endDate, certificateNumber) {
        // Determine titles based on gender
        const titleHindi = (gender === 'Female') ? 'श्रीमती' : 'श्री';
        const titleEnglish = (gender === 'Female') ? 'Smt.' : 'Shri';
        
        // Default values for missing data
        name = name || 'Participant Name';
        nameHindi = nameHindi || 'प्रतिभागी का नाम';
        rank = rank || 'Rank';
        rankHindi = rankHindi || 'पद';
        state = state || 'State';
        stateHindi = stateHindi || 'राज्य';
        courseName = courseName || 'Course Name';
        courseNameHindi = courseNameHindi || 'पाठ्यक्रम का नाम';
        certificateNumber = certificateNumber || 'NCRB-CERT-' + Math.floor(Math.random() * 10000);
        
        return `<!DOCTYPE html>
        <html>
        <head>
            <title>Certificate</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Playpen+Sans+Deva:wght@400..700&display=swap');
                @import url('https://fonts.googleapis.com/css2?family=Hind:wght@400;700&display=swap');

                body, html {
                    margin: 0;
                    padding: 0;
                    height: 100%;
                }

                .certificate-view {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    background-color: #f5f5f5;
                }

                .certificate {
                    border: 15px solid #1a5276;
                    padding: 20px;
                    width: 95%;
                    max-width: 1200px;
                    background-color: white;
                    box-shadow: 0 0 20px rgba(0,0,0,0.2);
                    position: relative;
                    height: 95vh;
                }

                .certificate-number {
                    position: absolute;
                    top: 15px;
                    right: 20px;
                    font-size: 16px;
                    font-weight: bold;
                    color: #333;
                }

                .header {
                    text-align: center;
                    margin-top: 42px;
                    margin-bottom: 15px;
                    font-family: Monotype Corsiva, Times, Serif;
                }

                .header img {
                    max-width: 320px;
                    margin-bottom: 10px;
                }

                .title {
                    font-size: 35px;
                    font-weight: bold;
                    text-align: center;
                    margin-bottom: 15px;
                    color: #1a5276;
                    font-family: Monotype Corsiva, Times, Serif;
                }

                .content {
                    font-size: 28px;
                    line-height: 1.4;
                    margin: 15px 0;
                    text-align: center;
                    padding: 0 10px;
                    font-family: Monotype Corsiva, Times, Serif;
                }

                .hindi {
                    direction: ltr;
                    text-align: center;
                    unicode-bidi: bidi-override;
                    font-family: Monotype Corsiva, Times, Serif;
                    font-size: 25px;
                    margin: 15px 0;
                    line-height: 1.4;
                }

                .signature {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 20px;
                    position: absolute;
                    bottom: 40px;
                    left: 0;
                    right: 0;
                    padding: 0 30px;
                }

                .signature div {
                    text-align: center;
                    width: 45%;
                    font-family: Monotype Corsiva, Times, Serif;
                    font-size: 24px;
                }
    
                .print-btn {
                    display: block;
                    margin: 20px auto 0;
                    padding: 10px 20px;
                    background: #4CAF50;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
    
                @media print {
                    @page {
                        size: landscape;
                        margin: 0;
                    }
    
                    body {
                        margin: 0;
                        padding: 0;
                        background: white;
                        height: 100%;
                    }
        
                    .certificate-view {
                        display: flex;
                        height: 100%;
                        background: white;
                    }
        
                    .certificate {
                        width: 100%;
                        height: 100%;
                        margin: 0;
                        padding: 15px;
                        box-shadow: none;
                        border: 15px solid #1a5276;
                        box-sizing: border-box;
                        page-break-after: avoid;
                        page-break-inside: avoid;
                    }
        
                    .no-print {
                        display: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="certificate-view">
                <div class="certificate">
                    <div class="certificate-number">Certificate No: ${certificateNumber}</div>
                    <div class="header">
                        <div class="title">NATIONAL CRIME RECORDS BUREAU, New Delhi</div>
                        <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                    </div>
                    <div class="title">Certificate of Participation</div>

                    <div class="hindi">
                        <p> प्रमाणित किया जाता है कि <strong>${titleHindi} ${nameHindi}</strong>, ${rankHindi}, ${stateHindi} ने ब्यूरो मे आयोजित पाठ्यक्रम <strong>${courseNameHindi}</strong> में दिनांक ${startDate} से ${endDate} तक भाग लिया ।</p>
                    </div>

                    <div class="content">
                        <p>This is to certify that <strong>${titleEnglish} ${name}</strong>, ${rank}, ${state} has attended Course on <strong>${courseName}</strong> conducted by this bureau from ${startDate} to ${endDate}.</p>
                    </div>
        
                    <div class="signature">
                        <div>
                            <img src="Coord Sign.jpg" alt="Coordinator" style="height: 60px;">
                            <p>(डॉ. पवन भारद्वाज)/(Dr. Pawan Bhardwaj)</p>
                            <p>(समन्वयक )/(Coordinator)</p>
                        </div>
                        <div>
                            <img src="Direct Sign.jpg" alt="Director" style="height: 60px;">
                            <p>(आलोक रंजन, भा.पु.से.)/(Alok Ranjan, IPS)</p>
                            <p>(निदेशक)/(Director, NCRB)</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()" class="print-btn">Print Certificate</button>
            </div>
        </body>
        </html>`;
    }

    // Initialize districts if state is already selected
    $(document).ready(function() {
        const selectedState = "<?= $selectedState ?>";
        if (selectedState) {
            loadDistricts(selectedState);
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });
    </script>
</body>
</html>