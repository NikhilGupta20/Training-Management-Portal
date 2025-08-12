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

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Database connection error. Please try again later.");
}

// Create AIBE examination marks table if not exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS aibe_exam_marks (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id INT(6) UNSIGNED NOT NULL,
    theory_marks DECIMAL(5,2) NOT NULL,
    practical_marks DECIMAL(5,2) NOT NULL,
    viva_marks DECIMAL(5,2) NOT NULL,
    theory_max DECIMAL(5,2) NOT NULL DEFAULT 100,
    practical_max DECIMAL(5,2) NOT NULL DEFAULT 100,
    viva_max DECIMAL(5,2) NOT NULL DEFAULT 100,
    total_marks DECIMAL(5,2) GENERATED ALWAYS AS (theory_marks + practical_marks + viva_marks) STORED,
    total_max DECIMAL(5,2) GENERATED ALWAYS AS (theory_max + practical_max + viva_max) STORED,
    percentage DECIMAL(5,2) GENERATED ALWAYS AS ((theory_marks + practical_marks + viva_marks) * 100 / (theory_max + practical_max + viva_max)) STORED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registration_id) REFERENCES registration(id) ON DELETE CASCADE
)";

if (!$conn->query($createTableSQL)) {
    error_log("AI BE examination marks table creation failed: " . $conn->error);
}

$currentYear = date('Y');
$targetYear = $currentYear;
$maxEndDate = $targetYear . '-12-31';

$admin_username = $_SESSION['admin_username'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// AJAX handler for district loading
if (isset($_GET['action']) && $_GET['action'] === 'get_districts' && isset($_GET['state'])) {
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

    header('Content-Type: application/json');
    echo json_encode($districts);
    exit;
}

// Process marks submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_marks'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "CSRF token validation failed";
        header("Location: admin-aibe-examination.php");
        exit;
    }

    $registration_id = intval($_POST['registration_id']);
    $theory_marks = floatval($_POST['theory_marks']);
    $practical_marks = floatval($_POST['practical_marks']);
    $viva_marks = floatval($_POST['viva_marks']);
    $theory_max = floatval($_POST['theory_max']);
    $practical_max = floatval($_POST['practical_max']);
    $viva_max = floatval($_POST['viva_max']);

    $checkStmt = $conn->prepare("SELECT id FROM aibe_exam_marks WHERE registration_id = ?");
    $checkStmt->bind_param("i", $registration_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $updateStmt = $conn->prepare("UPDATE aibe_exam_marks SET 
            theory_marks = ?, practical_marks = ?, viva_marks = ?,
            theory_max = ?, practical_max = ?, viva_max = ?
            WHERE registration_id = ?");
        $updateStmt->bind_param("ddddddi", $theory_marks, $practical_marks, $viva_marks, 
                                $theory_max, $practical_max, $viva_max, $registration_id);
        if ($updateStmt->execute()) {
            $_SESSION['success_message'] = "Marks updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating marks: " . $updateStmt->error;
        }
        $updateStmt->close();
    } else {
        $insertStmt = $conn->prepare("INSERT INTO aibe_exam_marks 
            (registration_id, theory_marks, practical_marks, viva_marks, 
             theory_max, practical_max, viva_max) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("idddddd", $registration_id, $theory_marks, $practical_marks, $viva_marks,
                                $theory_max, $practical_max, $viva_max);
        if ($insertStmt->execute()) {
            $_SESSION['success_message'] = "Marks saved successfully!";
        } else {
            $_SESSION['error_message'] = "Error saving marks: " . $insertStmt->error;
        }
        $insertStmt->close();
    }
    
    $checkStmt->close();
    header("Location: admin-aibe-examination.php");
    exit;
}

// Get states for filter dropdown
$states = $conn->query("SELECT * FROM states")->fetch_all(MYSQLI_ASSOC);

// Initialize filter variables
$selectedState = $_GET['state'] ?? '';
$selectedDistricts = [];
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? $maxEndDate;

// Cap end date to current year maximum
if (strtotime($endDate) > strtotime($maxEndDate)) {
    $endDate = $maxEndDate;
}

// Load districts for selected state
if (!empty($selectedState)) {
    $stmt = $conn->prepare("SELECT district_name FROM districts WHERE state_name = ? ORDER BY district_name");
    $stmt->bind_param("s", $selectedState);
    $stmt->execute();
    $result = $stmt->get_result();
    $selectedDistricts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Build query for participants
$conditions = [];
$params = [];
$paramTypes = '';
$paramValues = [];

// Get AIBEEXAM course names - FIXED: Added proper error handling and $conn reference
$examCourses = [];
$examResult = $conn->query("SELECT course_name FROM training_events WHERE course_code = 'AIBEEXAM'");
if ($examResult) {
    $examCourses = $examResult->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Error getting AIBEEXAM courses: " . $conn->error);
}

// FIXED: Pass $conn to closure using 'use'
$courseNames = array_map(function($course) use ($conn) { 
    return "'" . $conn->real_escape_string($course['course_name']) . "'"; 
}, $examCourses);

// FIXED: Handle empty course list
if (empty($courseNames)) {
    $courseList = "''"; // Default to empty value to prevent SQL error
} else {
    $courseList = implode(',', $courseNames);
}

$baseQuery = "SELECT r.*, m.theory_marks, m.practical_marks, m.viva_marks, 
              m.theory_max, m.practical_max, m.viva_max, m.total_marks, m.total_max, m.percentage
              FROM registration r
              LEFT JOIN aibe_exam_marks m ON r.id = m.registration_id
              WHERE r.course_name IN ($courseList)
              AND r.end_date <= ?";
$paramTypes = 's';
$paramValues = [$endDate];

if (!empty($startDate)) {
    $conditions[] = "r.end_date >= ?";
    $paramTypes .= 's';
    $paramValues[] = $startDate;
}

if (!empty($_GET['state'])) {
    $conditions[] = "r.state_name = ?";
    $paramTypes .= 's';
    $paramValues[] = $_GET['state'];
}
if (!empty($_GET['district'])) {
    $conditions[] = "r.district_name = ?";
    $paramTypes .= 's';
    $paramValues[] = $_GET['district'];
}
if (!empty($_GET['search'])) {
    $conditions[] = "r.participant_name LIKE ?";
    $paramTypes .= 's';
    $paramValues[] = "%" . $_GET['search'] . "%";
}

if (!empty($conditions)) {
    $baseQuery .= " AND " . implode(" AND ", $conditions);
}

$baseQuery .= " ORDER BY m.percentage DESC, r.participant_name";

$stmt = $conn->prepare($baseQuery);
if ($stmt) {
    if (!empty($paramTypes)) {
        $stmt->bind_param($paramTypes, ...$paramValues);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $participants = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("Prepare failed: " . $conn->error);
    $participants = [];
}

// Get AIBE course details
$courseStmt = $conn->prepare("SELECT course_name, start_date, end_date 
                              FROM training_events 
                              WHERE course_code = 'AIBEEXAM' 
                              AND YEAR(end_date) = ?
                              ORDER BY end_date DESC 
                              LIMIT 1");
$courseStmt->bind_param("s", $currentYear);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
$aibeCourse = $courseResult->fetch_assoc();
$courseStmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>AIBE <?= $targetYear ?> Examination Management - NCRB Training Portal</title>
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
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
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
            padding-top: 200px;
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

        .dashboard-header p {
            font-size: 1.2rem;
            color: var(--gray);
            max-width: 800px;
            margin: 0 auto;
        }

        /* Examination Criteria */
        .criteria-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--accent);
        }

        .criteria-info h3 {
            color: var(--primary);
            margin-bottom: 15px;
            text-align: center;
            font-size: 1.4rem;
        }

        .criteria-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .criteria-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .criteria-item i {
            font-size: 1.5rem;
            color: var(--accent);
            margin-right: 15px;
            min-width: 30px;
            text-align: center;
        }

        /* Filter Form Styles */
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
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
        .marks-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .marks-table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .marks-table td {
            padding: 14px 15px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }

        .marks-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .marks-table tr:hover {
            background-color: #f0f7ff;
        }

        .marks-input {
            width: 70px;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-align: center;
        }

        .marks-input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        .total-cell {
            font-weight: 600;
            text-align: center;
        }

        .percentage-cell {
            font-weight: 700;
            text-align: center;
        }

        .passed {
            color: var(--success);
        }

        .failed {
            color: var(--danger);
        }

        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .save-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }
        
        .save-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
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
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        
        .certificate-btn:hover {
            background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
            transform: translateY(-2px);
        }

        .id-card-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
        }
        
        .id-card-btn:hover {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
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

        /* ===== ID CARD PREVIEW MODAL ===== */
        .id-card-modal {
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
        
        .id-card-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            padding: 25px;
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .id-card-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .id-card-header img {
            max-width: 80px;
            margin-bottom: 10px;
        }
        
        .id-card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .id-card-subtitle {
            color: var(--secondary);
            font-size: 1rem;
        }
        
        .id-card-details {
            margin-top: 20px;
        }
        
        .id-card-row {
            display: flex;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .id-card-label {
            font-weight: 600;
            width: 40%;
            color: #003366;
        }
        
        .id-card-value {
            width: 60%;
        }
        
        .id-card-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* ID Card Styles */
        .id-card-template {
            width: 85mm;
            height: 54mm;
            background: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            margin: 10px auto;
            border: 1px solid #ddd;
        }
        
        .id-card-template .id-card-header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .id-card-template .id-card-header img {
            max-width: 60px;
            margin-bottom: 5px;
        }
        
        .id-card-template .id-card-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 3px;
        }
        
        .id-card-template .id-card-subtitle {
            color: var(--secondary);
            font-size: 12px;
        }
        
        .id-card-template .id-card-body {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .id-card-template .id-card-details {
            text-align: left;
            border-top: 1px solid #e9ecef;
            padding-top: 10px;
        }
        
        .id-card-template .id-card-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 10px;
        }
        
        .id-card-template .id-card-label {
            font-weight: 600;
            width: 40%;
            color: #003366;
        }
        
        .id-card-template .id-card-value {
            width: 60%;
        }
        
        .id-card-template .id-card-footer {
            position: absolute;
            bottom: 5px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #666;
            text-align: center;
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

        /* ===== MODAL STYLES ===== */
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
            
            .marks-table {
                font-size: 14px;
            }
            
            .marks-input {
                width: 60px;
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
            
            .marks-table {
                display: block;
                overflow-x: auto;
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
            
            .dashboard-header h1 {
                font-size: 2rem;
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
                <div class="nav-title">National Crime Records Bureau - AIBE <?= $targetYear ?> Examination Management</div>
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
            <h1>AIBE <?= $targetYear ?> Examination Management</h1>
            <p>
                <?php if ($aibeCourse): ?>
                    <?= $aibeCourse['course_name'] ?> - 
                    <?= date('d M Y', strtotime($aibeCourse['start_date'])) ?> to 
                    <?= date('d M Y', strtotime($aibeCourse['end_date'])) ?>
                <?php else: ?>
                    AIBE (All India Bureau Examination) - Participants from <?= $targetYear ?>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Examination Criteria -->
        <div class="criteria-info">
            <h3>Passing Criteria</h3>
            <div class="criteria-list">
                <div class="criteria-item">
                    <i class="fas fa-book"></i>
                    <div>Theory: Minimum 60% marks required to pass</div>
                </div>
                <div class="criteria-item">
                    <i class="fas fa-flask"></i>
                    <div>Practical: Minimum 75% marks required to pass</div>
                </div>
                <div class="criteria-item">
                    <i class="fas fa-comments"></i>
                    <div>Viva: Minimum 60% marks required to pass</div>
                </div>
                <div class="criteria-item">
                    <i class="fas fa-star"></i>
                    <div>Candidate must pass all three sections to qualify</div>
                </div>
            </div>
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
            <!-- Date Range Filters -->
            <div class="filter-group">
                <label for="startDate">From</label>
                <input type="date" name="start_date" id="startDate" 
                       value="<?= htmlspecialchars($startDate) ?>">
            </div>
            
            <div class="filter-group">
                <label for="endDate">To</label>
                <input type="date" name="end_date" id="endDate" 
                       max="<?= $maxEndDate ?>" 
                       value="<?= htmlspecialchars($endDate) ?>">
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
                <label for="searchInput">Search Participant</label>
                <input type="text" name="search" id="searchInput" placeholder="Participant name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="filter-btn">Filter/Search</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="admin-aibe-examination.php" class="reset-btn">Reset Filters</a>
            </div>
        </form>

        <!-- Participants and Marks Table -->
        <?php if (!empty($participants)): ?>
            <form method="post" id="marksForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Registration ID</th>
                            <th>Participant Name</th>
                            <th>Theory</th>
                            <th>Practical</th>
                            <th>Viva</th>
                            <th>Total</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): 
                            // Avoid division by zero
                            $theoryPassed = ($participant['theory_max'] > 0) ? (($participant['theory_marks'] / $participant['theory_max'] * 100) >= 60) : false;
                            $practicalPassed = ($participant['practical_max'] > 0) ? (($participant['practical_marks'] / $participant['practical_max'] * 100) >= 75) : false;
                            $vivaPassed = ($participant['viva_max'] > 0) ? (($participant['viva_marks'] / $participant['viva_max'] * 100) >= 60) : false;
                            $allPassed = $theoryPassed && $practicalPassed && $vivaPassed;
                        ?>
                            <tr data-state="<?= htmlspecialchars($participant['state_name'] ?? 'N/A') ?>">
                                <td><?= htmlspecialchars($participant['registration_id']) ?></td>
                                <td>
                                    <?= htmlspecialchars($participant['participant_name']) ?>
                                    <input type="hidden" name="registration_id" value="<?= $participant['id'] ?>">
                                </td>
                                
                                <!-- Theory Marks -->
                                <td>
                                    <input type="number" step="0.01" min="0" 
                                        name="theory_marks" 
                                        class="marks-input" 
                                        value="<?= $participant['theory_marks'] ?? '' ?>" 
                                        placeholder="Marks"
                                        oninput="calculateTotal(this)">
                                    <span>/</span>
                                    <input type="number" step="0.01" min="0" 
                                        name="theory_max" 
                                        class="marks-input" 
                                        value="<?= $participant['theory_max'] ?? 100 ?>" 
                                        placeholder="Max"
                                        oninput="calculateTotal(this)">
                                </td>
                                
                                <!-- Practical Marks -->
                                <td>
                                    <input type="number" step="0.01" min="0" 
                                        name="practical_marks" 
                                        class="marks-input" 
                                        value="<?= $participant['practical_marks'] ?? '' ?>" 
                                        placeholder="Marks"
                                        oninput="calculateTotal(this)">
                                    <span>/</span>
                                    <input type="number" step="0.01" min="0" 
                                        name="practical_max" 
                                        class="marks-input" 
                                        value="<?= $participant['practical_max'] ?? 100 ?>" 
                                        placeholder="Max"
                                        oninput="calculateTotal(this)">
                                </td>
                                
                                <!-- Viva Marks -->
                                <td>
                                    <input type="number" step="0.01" min="0" 
                                        name="viva_marks" 
                                        class="marks-input" 
                                        value="<?= $participant['viva_marks'] ?? '' ?>" 
                                        placeholder="Marks"
                                        oninput="calculateTotal(this)">
                                    <span>/</span>
                                    <input type="number" step="0.01" min="0" 
                                        name="viva_max" 
                                        class="marks-input" 
                                        value="<?= $participant['viva_max'] ?? 100 ?>" 
                                        placeholder="Max"
                                        oninput="calculateTotal(this)">
                                </td>
                                
                                <!-- Total Marks -->
                                <td class="total-cell">
                                    <?php if (isset($participant['total_marks'])): ?>
                                        <?= number_format($participant['total_marks'], 2) ?> / 
                                        <?= number_format($participant['total_max'], 2) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Percentage -->
                                <td class="percentage-cell">
                                    <?php if (isset($participant['percentage'])): ?>
                                        <span class="<?= $allPassed ? 'passed' : 'failed' ?>">
                                            <?= number_format($participant['percentage'], 2) ?>%
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Status -->
                                <td class="status-cell">
                                    <?php if (isset($participant['percentage'])): ?>
                                        <span class="<?= $allPassed ? 'passed' : 'failed' ?>">
                                            <?= $allPassed ? 'Passed' : 'Failed' ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Actions -->
                                <td class="action-btns">
                                    <button type="button" class="save-btn" onclick="saveMarks(this)">Save</button>
                                    <?php if (isset($participant['percentage'])): ?>
                                        <button type="button" class="certificate-btn" 
                                            onclick="generateCertificate(
                                                '<?= $participant['gender'] ?? '' ?>',
                                                '<?= addslashes(htmlspecialchars($participant['participant_name'] ?? '')) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['participant_name_hindi'] ?? '')) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['rank'] ?? '')) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['rank_hindi'] ?? '')) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['state_name'] ?? '')) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['state_name_hindi'] ?? '')) ?>',
                                                'AIBE Examination',
                                                'एआईबीई परीक्षा',
                                                '<?= date('d-m-Y', strtotime($aibeCourse['start_date'] ?? 'now')) ?>',
                                                '<?= date('d-m-Y', strtotime($aibeCourse['end_date'] ?? 'now')) ?>',
                                                '<?= htmlspecialchars($participant['registration_id'] ?? 'NCRB-AIBECERT-'.rand(1000,9999)) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['other_rank'] ?? '')) ?>',
                                                '<?= addslashes(htmlspecialchars($participant['other_rank_hindi'] ?? '')) ?>',
                                                <?= number_format($participant['percentage'], 2) ?>,
                                                <?= $allPassed ? 'true' : 'false' ?>
                                            )">
                                            Certificate
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="id-card-btn" 
                                        onclick="showIDCard(
                                            '<?= addslashes(htmlspecialchars($participant['participant_name'] ?? '')) ?>',
                                            '<?= $participant['registration_id'] ?? '' ?>',
                                            '<?= addslashes(htmlspecialchars($aibeCourse['course_name'] ?? '')) ?>',
                                            '<?= date('d M Y', strtotime($aibeCourse['start_date'] ?? 'now')) ?>',
                                            '<?= date('d M Y', strtotime($aibeCourse['end_date'] ?? 'now')) ?>',
                                            '<?= addslashes(htmlspecialchars($participant['rank'] ?? '')) ?>',
                                            '<?= addslashes(htmlspecialchars($participant['state_name'] ?? '')) ?>',
                                            '<?= addslashes(htmlspecialchars(($participant['state_name'] ?? '') . ' ' . ($participant['category_name'] ?? ''))) ?>'
                                        )">ID Card</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <div class="btn-container">
                <button onclick="printReport('all')" class="action-btn print-btn">Print All Report</button>
                <button onclick="printReport('passed')" class="action-btn statistics-btn">Print Passed Report</button>
                <button onclick="printReport('failed')" class="action-btn statistics-btn">Print Failed Report</button>
                <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <p class="no-data">
                <?php if (!empty($_GET)): ?>
                    No participants found matching your criteria.
                <?php else: ?>
                    No eligible participants found for AIBE Examination <?= $targetYear ?>.
                <?php endif; ?>
            </p>
            <div class="btn-container">
                <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ID Card Preview Modal -->
    <div id="idCardModal" class="id-card-modal">
        <div class="id-card-content">
            <div class="id-card-header">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                <div class="id-card-title">National Crime Records Bureau <br> Central FingerPrint Bureau</div>
                <div class="id-card-subtitle">Examination Admit Card</div>
            </div>
            
            <div class="id-card-details">
                <div class="id-card-row">
                    <div class="id-card-label">Name:</div>
                    <div class="id-card-value" id="idCardName"></div>
                </div>
                <div class="id-card-row">
                    <div class="id-card-label">Registration ID:</div>
                    <div class="id-card-value" id="idCardRegId"></div>
                </div>
                <div class="id-card-row">
                    <div class="id-card-label">Designation:</div>
                    <div class="id-card-value" id="idCardDesignation"></div>
                </div>
                <div class="id-card-row">
                    <div class="id-card-label">Organization:</div>
                    <div class="id-card-value" id="idCardOrganization"></div>
                </div>
                <div class="id-card-row">
                    <div class="id-card-label">State:</div>
                    <div class="id-card-value" id="idCardState"></div>
                </div>
                <div class="id-card-row">
                    <div class="id-card-label">Course:</div>
                    <div class="id-card-value" id="idCardCourse"></div>
                </div>
                <div class="id-card-row">
                    <div class="id-card-label">Dates:</div>
                    <div class="id-card-value" id="idCardDates"></div>
                </div>
            </div>
            
            <div class="id-card-footer">
                <button class="action-btn print-btn" onclick="printAdmitCard()" style="margin-top: 10px;">Print</button>
                <button class="action-btn" onclick="closeIDCard()" style="background: var(--gray); margin-top: 10px;">Close</button>
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

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
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

        fetch('admin-aibe-examination.php?action=get_districts&state=' + encodeURIComponent(stateName))
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

    // Calculate total and percentage
    function calculateTotal(input) {
        const row = input.closest('tr');
        const inputs = row.querySelectorAll('.marks-input');
        
        let totalMarks = 0;
        let totalMax = 0;
        
        // Get all marks and max values
        const marks = [
            parseFloat(inputs[0].value) || 0,
            parseFloat(inputs[2].value) || 0,
            parseFloat(inputs[4].value) || 0
        ];
        
        const maxes = [
            parseFloat(inputs[1].value) || 0,
            parseFloat(inputs[3].value) || 0,
            parseFloat(inputs[5].value) || 0
        ];
        
        // Calculate totals
        marks.forEach(mark => totalMarks += mark);
        maxes.forEach(max => totalMax += max);
        
        // Update total cell
        const totalCell = row.querySelector('.total-cell');
        totalCell.textContent = totalMarks.toFixed(2) + ' / ' + totalMax.toFixed(2);
        
        // Update percentage cell
        const percentageCell = row.querySelector('.percentage-cell');
        let percentage = 0;
        if (totalMax > 0) {
            percentage = (totalMarks / totalMax) * 100;
        }
        
        // Check passing criteria (avoid division by zero)
        const theoryPassed = (maxes[0] > 0) ? (marks[0] / maxes[0] * 100) >= 60 : false;
        const practicalPassed = (maxes[1] > 0) ? (marks[1] / maxes[1] * 100) >= 75 : false;
        const vivaPassed = (maxes[2] > 0) ? (marks[2] / maxes[2] * 100) >= 60 : false;
        const allPassed = theoryPassed && practicalPassed && vivaPassed;
        
        // Update status
        const statusCell = row.querySelector('.status-cell');
        if (percentage > 0) {
            percentageCell.innerHTML = `<span class="${allPassed ? 'passed' : 'failed'}">${percentage.toFixed(2)}%</span>`;
            statusCell.innerHTML = `<span class="${allPassed ? 'passed' : 'failed'}">${allPassed ? 'Passed' : 'Failed'}</span>`;
        } else {
            percentageCell.innerHTML = '-';
            statusCell.innerHTML = '-';
        }
    }

    // Save marks for a single row
    function saveMarks(button) {
        const row = button.closest('tr');
        const form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
        form.appendChild(csrfInput);
        
        // Add registration ID
        const regIdInput = document.createElement('input');
        regIdInput.type = 'hidden';
        regIdInput.name = 'registration_id';
        regIdInput.value = row.querySelector('input[name="registration_id"]').value;
        form.appendChild(regIdInput);
        
        // Add marks
        const marksInputs = row.querySelectorAll('.marks-input');
        const names = [
            'theory_marks', 'theory_max',
            'practical_marks', 'practical_max',
            'viva_marks', 'viva_max'
        ];
        
        for (let i = 0; i < marksInputs.length; i++) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = names[i];
            input.value = marksInputs[i].value;
            form.appendChild(input);
        }
        
        // Add submit flag
        const submitInput = document.createElement('input');
        submitInput.type = 'hidden';
        submitInput.name = 'submit_marks';
        submitInput.value = '1';
        form.appendChild(submitInput);
        
        document.body.appendChild(form);
        form.submit();
    }

    // Generate certificate
    function generateCertificate(gender, name, nameHindi, rank, rankHindi, state, stateHindi, courseName, courseNameHindi, startDate, endDate, certificateNumber, otherRank, otherRankHindi, percentage, isPassed) {
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
            certificateNumber,
            percentage,
            isPassed
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

    // HTML template function for certificate
    function createCertificateHTML(gender, name, nameHindi, rank, rankHindi, state, stateHindi, courseName, courseNameHindi, startDate, endDate, certificateNumber, percentage, isPassed) {
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
        courseName = courseName || 'AIBE Examination';
        courseNameHindi = courseNameHindi || 'एआईबीई परीक्षा';
        certificateNumber = certificateNumber || 'NCRB-CERT-' + Math.floor(Math.random() * 10000);
        percentage = percentage || 0;
        
        // Result text
        const resultHindi = isPassed ? 
            'और परीक्षा में उत्तीर्ण हुए हैं।' : 
            'और परीक्षा में अनुत्तीर्ण हुए हैं।';
        
        const resultEnglish = isPassed ? 
            'and has passed the examination.' : 
            'and has not passed the examination.';
        
        const resultClass = isPassed ? 'passed' : 'failed';
        
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
                }

                .header img {
                    max-width: 320px;
                    margin-bottom: 10px;
                }

                .title {
                    font-size: 35px;
                    font-weight: bold;
                    text-align: center;
                    margin: 20px 0;
                    color: #003366;
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
                    margin-top: 80px; /* Increased spacing */
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
                
                .result {
                    font-size: 26px;
                    font-weight: bold;
                    margin-top: 30px; /* Increased spacing */
                    text-align: center;
                    color: ${isPassed ? '#28a745' : '#dc3545'};
                }
                
                .percentage {
                    font-size: 30px;
                    font-weight: bold;
                    margin-top: 10px;
                    text-align: center;
                    color: ${isPassed ? '#28a745' : '#dc3545'};
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
                        <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                    </div>
                    <div class="title">Certificate of Examination</div>

                    <div class="hindi">
                        <p> प्रमाणित किया जाता है कि <strong>${titleHindi} ${nameHindi}</strong>, ${rankHindi}, ${stateHindi} ने ब्यूरो मे आयोजित पाठ्यक्रम <strong>${courseNameHindi}</strong> में दिनांक ${startDate} से ${endDate} तक भाग लिया ${resultHindi}</p>
                    </div>

                    <div class="content">
                        <p>This is to certify that <strong>${titleEnglish} ${name}</strong>, ${rank}, ${state} has attended Course on <strong>${courseName}</strong> conducted by this bureau from ${startDate} to ${endDate} ${resultEnglish}</p>
                    </div>
                    
                    <div class="result ${resultClass}">
                        ${isPassed ? 'Passed' : 'Failed'}
                    </div>
                    
                    <div class="percentage ${resultClass}">
                        Score: ${percentage.toFixed(2)}%
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

    // Print report with professional formatting
    function printReport(type) {
        // Create a print container
        const printContainer = document.createElement('div');
        printContainer.id = 'print-container';
        
        // Add header with logos
        const header = document.createElement('div');
        header.style.display = 'flex';
        header.style.justifyContent = 'space-between';
        header.style.alignItems = 'center';
        header.style.marginBottom = '20px';
        header.style.paddingBottom = '15px';
        header.style.borderBottom = '2px solid #003366';
        
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
        subtitle.textContent = 'AIBE <?= $targetYear ?> Examination Report';
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
        <?php if (!empty($startDate) && !empty($endDate)): ?>
            filters.push(`Date Range: <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['state'])): ?>
            filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
        <?php endif; ?>
        <?php if (!empty($_GET['district'])): ?>
            filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
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
        
        // Create report table
        const reportTable = document.createElement('table');
        reportTable.style.width = '100%';
        reportTable.style.borderCollapse = 'collapse';
        reportTable.style.marginTop = '20px';
        
        // Create table header
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        // ADDED STATE COLUMN TO HEADER
        const headers = ['Registration ID', 'Participant Name', 'State', 'Theory', 'Practical', 'Viva', 'Total', 'Percentage', 'Status'];
        
        headers.forEach(headerText => {
            const th = document.createElement('th');
            th.textContent = headerText;
            th.style.border = '1px solid #ddd';
            th.style.padding = '8px';
            th.style.textAlign = 'left';
            th.style.backgroundColor = '#003366';
            th.style.color = 'white';
            headerRow.appendChild(th);
        });
        
        thead.appendChild(headerRow);
        reportTable.appendChild(thead);
        
        // Create table body
        const tbody = document.createElement('tbody');
        
        // Traverse the current table rows
        const rows = document.querySelectorAll('.marks-table tbody tr');
        rows.forEach(row => {
            // Skip if no percentage (no data saved)
            const percentageCell = row.querySelector('.percentage-cell');
            if (!percentageCell) return;
            
            const percentageText = percentageCell.textContent.trim();
            if (percentageText === '-') return;
            
            const percentageMatch = percentageText.match(/(\d+\.?\d*)%/);
            if (!percentageMatch) return;
            
            const percentage = parseFloat(percentageMatch[1]);
            if (isNaN(percentage)) return;
            
            // Check the type filter
            const statusCell = row.querySelector('.status-cell');
            const status = statusCell.textContent.trim().toLowerCase();
            
            if (type === 'passed' && status !== 'passed') return;
            if (type === 'failed' && status !== 'failed') return;
            
            // Extract data from the row
            const registrationId = row.querySelector('td:nth-child(1)').textContent.trim();
            const participantName = row.querySelector('td:nth-child(2)').textContent.trim();
            // GET STATE FROM DATA ATTRIBUTE
            const stateName = row.dataset.state || 'N/A';
            
            // Marks cells: theory, practical, viva
            const theoryMarks = row.querySelector('td:nth-child(3) input:nth-child(1)').value;
            const theoryMax = row.querySelector('td:nth-child(3) input:nth-child(3)').value;
            const practicalMarks = row.querySelector('td:nth-child(4) input:nth-child(1)').value;
            const practicalMax = row.querySelector('td:nth-child(4) input:nth-child(3)').value;
            const vivaMarks = row.querySelector('td:nth-child(5) input:nth-child(1)').value;
            const vivaMax = row.querySelector('td:nth-child(5) input:nth-child(3)').value;
            
            // Total cell
            const totalCell = row.querySelector('.total-cell').textContent.trim();
            const [totalMarks, totalMax] = totalCell.split('/').map(s => s.trim());
            
            // Status
            const statusClass = status === 'passed' ? 'passed' : 'failed';
            
            // Create table row
            const tr = document.createElement('tr');
            
            // Add cells - INCLUDING STATE COLUMN
            const cells = [
                registrationId,
                participantName,
                stateName, // STATE COLUMN ADDED HERE
                `${theoryMarks} / ${theoryMax}`,
                `${practicalMarks} / ${practicalMax}`,
                `${vivaMarks} / ${vivaMax}`,
                `${totalMarks} / ${totalMax}`,
                `${percentage.toFixed(2)}%`,
                status
            ];
            
            cells.forEach((cellText, index) => {
                const td = document.createElement('td');
                if (type === 'failed') {
                    if (index === 3) { // theory cell
                        let theoryPercentage = 0;
                        if (parseFloat(theoryMax) > 0) {
                            theoryPercentage = (parseFloat(theoryMarks) / parseFloat(theoryMax)) * 100;
                        }
                        if (parseFloat(theoryMax) > 0 && theoryPercentage < 60) {
                            td.innerHTML = `<span style="color: red; font-weight: bold;">${theoryMarks} / ${theoryMax}</span>`;
                        } else {
                            td.innerHTML = `${theoryMarks} / ${theoryMax}`;
                        }
                    } else if (index === 4) { // practical cell
                        let practicalPercentage = 0;
                        if (parseFloat(practicalMax) > 0) {
                            practicalPercentage = (parseFloat(practicalMarks) / parseFloat(practicalMax)) * 100;
                        }
                        if (parseFloat(practicalMax) > 0 && practicalPercentage < 75) {
                            td.innerHTML = `<span style="color: red; font-weight: bold;">${practicalMarks} / ${practicalMax}</span>`;
                        } else {
                            td.innerHTML = `${practicalMarks} / ${practicalMax}`;
                        }
                    } else if (index === 5) { // viva cell
                        let vivaPercentage = 0;
                        if (parseFloat(vivaMax) > 0) {
                            vivaPercentage = (parseFloat(vivaMarks) / parseFloat(vivaMax)) * 100;
                        }
                        if (parseFloat(vivaMax) > 0 && vivaPercentage < 60) {
                            td.innerHTML = `<span style="color: red; font-weight: bold;">${vivaMarks} / ${vivaMax}</span>`;
                        } else {
                            td.innerHTML = `${vivaMarks} / ${vivaMax}`;
                        }
                    } else {
                        td.textContent = cellText;
                    }
                } else {
                    td.textContent = cellText;
                }
                
                // Apply color to status cell
                if (index === 8) {
                    td.style.color = status === 'passed' ? 'var(--success)' : 'var(--danger)';
                    td.style.fontWeight = 'bold';
                }
                
                // Apply color to percentage cell
                if (index === 7) {
                    td.style.color = status === 'passed' ? 'var(--success)' : 'var(--danger)';
                    td.style.fontWeight = 'bold';
                }
                
                td.style.border = '1px solid #ddd';
                td.style.padding = '8px';
                tr.appendChild(td);
            });
            
            tbody.appendChild(tr);
        });
        
        reportTable.appendChild(tbody);
        printContainer.appendChild(reportTable);
        
        // Add footer
        const footer = document.createElement('div');
        footer.style.marginTop = '30px';
        footer.style.textAlign = 'center';
        footer.style.fontSize = '10pt';
        footer.style.color = '#666';
        footer.innerHTML = `
            <p>National Crime Records Bureau<br>
            Ministry of Home Affairs, Government of India</p>
            <p>Generated by NCRB Training Management System</p>
        `;
        printContainer.appendChild(footer);
    
        // Create a print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>AIBE Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@media print { body { margin: 1cm; font-family: Arial, sans-serif; } }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f2f2f2; }');
        printWindow.document.write('.header { text-align: center; margin-bottom: 20px; }');
        printWindow.document.write('.title { font-size: 24px; font-weight: bold; }');
        printWindow.document.write('.subtitle { font-size: 18px; color: #555; }');
        printWindow.document.write('.report-info { margin-bottom: 20px; }');
        printWindow.document.write('.passed { color: #28a745; }');
        printWindow.document.write('.failed { color: #dc3545; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(printContainer.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
    
        // Print after content loads
        printWindow.onload = function() {
            printWindow.print();
            printWindow.close();
        };
    }

    // ===== ID CARD FUNCTIONS =====
    function showIDCard(name, regId, courseName, startDate, endDate, designation, state, organization) {
        document.getElementById('idCardName').textContent = name;
        document.getElementById('idCardRegId').textContent = regId;
        document.getElementById('idCardCourse').textContent = courseName;
        document.getElementById('idCardDates').textContent = `${startDate} - ${endDate}`;
        document.getElementById('idCardDesignation').textContent = designation || 'N/A';
        document.getElementById('idCardState').textContent = state || 'N/A';
        document.getElementById('idCardOrganization').textContent = organization || 'N/A';
        
        document.getElementById('idCardModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeIDCard() {
        document.getElementById('idCardModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function printAdmitCard() {
        const name = document.getElementById('idCardName').textContent;
        const regId = document.getElementById('idCardRegId').textContent;
        const courseName = document.getElementById('idCardCourse').textContent;
        const dates = document.getElementById('idCardDates').textContent;
        const designation = document.getElementById('idCardDesignation').textContent;
        const state = document.getElementById('idCardState').textContent;
        const organization = document.getElementById('idCardOrganization').textContent;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Admit Card</title>
                <style>
                    @page {
                        size: A4;
                        margin: 0;
                    }
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 2px solid #003366;
                        padding-bottom: 20px;
                    }
                    .header img {
                        max-width: 120px;
                        margin-bottom: 15px;
                    }
                    .header h1 {
                        font-size: 28px;
                        color: #003366;
                        margin: 10px 0;
                    }
                    .header h1 {
                        font-size: 28px;
                        font-weight: bold;
                        margin: 10px 0;
                    }
                    .header h2 {
                        font-size: 22px;
                        color: #0066cc;
                        margin: 5px 0;
                    }
                    .admit-card-title {
                        text-align: center;
                        font-size: 24px;
                        font-weight: bold;
                        margin: 20px 0;
                        color: #003366;
                    }
                    .admit-card-body {
                        width: 80%;
                        margin: 0 auto;
                        border: 2px solid #003366;
                        border-radius: 10px;
                        padding: 30px;
                    }
                    .admit-card-row {
                        display: flex;
                        margin-bottom: 15px;
                        font-size: 18px;
                    }
                    .admit-card-label {
                        font-weight: 600;
                        width: 30%;
                        color: #003366;
                    }
                    .admit-card-value {
                        width: 70%;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 40px;
                        font-size: 16px;
                        color: #666;
                    }
                    .signature {
                        margin-top: 40px;
                        text-align: right;
                    }
                    .signature p {
                        margin: 5px 0;
                        font-size: 16px;
                    }
                    @media print {
                        body {
                            margin: 0;
                            padding: 20px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                    <h1>National Crime Records Bureau</h1>
                    <h1>Central FingerPrint Bureau</h1>
                    <h2>AIBE <?= $targetYear ?> Admit Card</h2>
                </div>
                
                <div class="admit-card-body">
                    <div class="admit-card-row">
                        <div class="admit-card-label">Name:</div>
                        <div class="admit-card-value">${name}</div>
                    </div>
                    <div class="admit-card-row">
                        <div class="admit-card-label">Registration ID:</div>
                        <div class="admit-card-value">${regId}</div>
                    </div>
                    <div class="admit-card-row">
                        <div class="admit-card-label">Designation:</div>
                        <div class="admit-card-value">${designation}</div>
                    </div>
                    <div class="admit-card-row">
                        <div class="admit-card-label">Organization:</div>
                        <div class="admit-card-value">${organization}</div>
                    </div>
                    <div class="admit-card-row">
                        <div class="admit-card-label">State:</div>
                        <div class="admit-card-value">${state}</div>
                    </div>
                    <div class="admit-card-row">
                        <div class="admit-card-label">Course:</div>
                        <div class="admit-card-value">${courseName}</div>
                    </div>
                    <div class="admit-card-row">
                        <div class="admit-card-label">Dates:</div>
                        <div class="admit-card-value">${dates}</div>
                    </div>
                    
                    <div class="signature">
                        <img src="Direct Sign.jpg" alt="Director" style="height: 60px;">
                        <p>Authorized Signature</p>
                        <p>Director, NCRB</p>
                    </div>
                </div>
                
                <div class="footer">
                    <p>National Crime Records Bureau, Ministry of Home Affairs, Government of India</p>
                    <p>This admit card is valid only with an official photo ID</p>
                </div>
                
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    function createIDCardHTML(name, regId, courseName, startDate, endDate, designation, state, organization) {
        return `
        <div class="id-card-template">
            <div class="id-card-header">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                <div class="id-card-title">National Crime Records Bureau</div>
                <div class="id-card-title">Central FingerPrint Bureau</div>
            </div>
            
            <div class="id-card-body">              
                <div class="id-card-details">
                    <div class="id-card-row">
                        <div class="id-card-label">Name:</div>
                        <div class="id-card-value">${name}</div>
                    </div>
                    <div class="id-card-row">
                        <div class="id-card-label">Registration ID:</div>
                        <div class="id-card-value">${regId}</div>
                    </div>
                    <div class="id-card-row">
                        <div class="id-card-label">Designation:</div>
                        <div class="id-card-value">${designation || 'N/A'}</div>
                    </div>
                    <div class="id-card-row">
                        <div class="id-card-label">Organization:</div>
                        <div class="id-card-value">${organization || 'N/A'}</div>
                    </div>
                    <div class="id-card-row">
                        <div class="id-card-label">State:</div>
                        <div class="id-card-value">${state || 'N/A'}</div>
                    </div>
                    <div class="id-card-row">
                        <div class="id-card-label">Course:</div>
                        <div class="id-card-value">${courseName}</div>
                    </div>
                    <div class="id-card-row">
                        <div class="id-card-label">Dates:</div>
                        <div class="id-card-value">${startDate} - ${endDate}</div>
                    </div>
                </div>
            </div>
            
            <div class="id-card-footer">
                NCRB AIBE Examination <?= $targetYear ?> | Valid for examination period only
            </div>
        </div>`;
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

    // Initialize districts if state is already selected
    $(document).ready(function() {
        const selectedState = "<?= $selectedState ?>";
        if (selectedState) {
            loadDistricts(selectedState);
        }
        
        // Set end date max to <?= $maxEndDate ?>
        document.getElementById('endDate').max = "<?= $maxEndDate ?>";
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });
    </script>
</body>
</html>