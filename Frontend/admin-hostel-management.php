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

// Add floor and room_number columns if they don't exist
$checkFloor = $conn->query("SHOW COLUMNS FROM hostel_allotment LIKE 'floor'");
if ($checkFloor->num_rows == 0) {
    $conn->query("ALTER TABLE hostel_allotment ADD COLUMN floor VARCHAR(20) NULL AFTER special_requirements");
}

$checkRoom = $conn->query("SHOW COLUMNS FROM hostel_allotment LIKE 'room_number'");
if ($checkRoom->num_rows == 0) {
    $conn->query("ALTER TABLE hostel_allotment ADD COLUMN room_number VARCHAR(20) NULL AFTER floor");
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

// Handle form messages from session
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle room allotment/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allot_room'])) {
    $hostel_id = $_POST['hostel_id'];
    $floor = $_POST['floor'];
    $room_number = $_POST['room_number'];
    
    // Validate inputs
    $errors = [];
    
    if (!preg_match('/^[A-Z0-9\-]{1,5}$/', $floor)) {
        $errors[] = "Invalid floor format. Use 1-5 letters/numbers/hyphens.";
    }
    
    if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $room_number)) {
        $errors[] = "Invalid room number format. Use letters, numbers, and hyphens only.";
    }
    
    if (empty($errors)) {
        $update_stmt = $conn->prepare("UPDATE hostel_allotment SET floor = ?, room_number = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $floor, $room_number, $hostel_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Room allocation updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating room: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
    
    // Redirect to prevent form resubmission
    header("Location: admin-hostel-management.php");
    exit;
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$state_filter = $_GET['state'] ?? '';
$floor_filter = $_GET['floor'] ?? '';

// Build query with filters - FIXED SQL SYNTAX
$query = "SELECT 
            h.id, 
            h.registration_id, 
            h.participant_name, 
            a.state_name, 
            h.residential_phone, 
            a.email, 
            h.hostel_from, 
            h.hostel_to, 
            h.duration, 
            h.special_requirements,
            h.floor,
            h.room_number
          FROM hostel_allotment h
          JOIN accepted_participants a ON h.registration_id = a.registration_id
          WHERE h.need_hostel = 'yes'"; // Only show approved hostel applications
          
$where = [];
$params = [];
$types = '';

// Add filters to query
if (!empty($start_date)) {
    $where[] = "h.hostel_from >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if (!empty($end_date)) {
    $where[] = "h.hostel_to <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if (!empty($state_filter)) {
    $where[] = "a.state_name = ?";
    $params[] = $state_filter;
    $types .= 's';
}

if (!empty($floor_filter)) {
    $where[] = "h.floor = ?";
    $params[] = $floor_filter;
    $types .= 's';
}

if (count($where) > 0) {
    $query .= " AND " . implode(" AND ", $where);
}

$query .= " ORDER BY h.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$hostel_applications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distinct states for filter dropdown
$state_query = "SELECT DISTINCT a.state_name 
                FROM accepted_participants a 
                JOIN hostel_allotment h ON a.registration_id = h.registration_id 
                WHERE h.need_hostel = 'yes' 
                ORDER BY state_name";
$state_result = $conn->query($state_query);
$states = $state_result->fetch_all(MYSQLI_ASSOC);

// Get distinct floors for filter dropdown
$floor_query = "SELECT DISTINCT floor 
                FROM hostel_allotment 
                WHERE need_hostel = 'yes' AND floor IS NOT NULL AND floor != '' 
                ORDER BY floor";
$floor_result = $conn->query($floor_query);
$floors = $floor_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_applications = count($hostel_applications);
$rooms_allotted = 0;

foreach ($hostel_applications as $app) {
    if (!empty($app['room_number'])) {
        $rooms_allotted++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Management | NCRB</title>
    
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
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
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
            background-color: #f5f7fa;
        }

        /* Fixed Header Styles */
        .fixed-header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--white);
            box-shadow: var(--shadow);
            transition: var(--transition);
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
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
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

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            border-top: 5px solid var(--info);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .summary-card.approved {
            border-color: var(--success);
        }
        
        .summary-card.allotted {
            border-color: var(--accent);
        }
        
        .summary-card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .summary-card-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .summary-card-label {
            font-size: 1.1rem;
            color: var(--gray);
        }

        /* Filter Container */
        .filter-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 25px;
            margin: 0 auto 30px;
            max-width: 1400px;
        }

        .filter-title {
            font-size: 1.5rem;
            color: #002147;
            margin-bottom: 20px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #003366;
        }

        .filter-input, .filter-select {
            padding: 12px 15px;
            border: 1px solid #c2d6e6;
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .filter-input:focus, .filter-select:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
        }

        .filter-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: flex-end;
        }

        .filter-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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

        /* Hostel Table Container */
        .hostel-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
            max-width: 1400px;
            overflow-x: auto;
        }

        .hostel-table-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .hostel-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            min-width: 1100px;
        }

        .hostel-table th, .hostel-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e9ff;
        }

        .hostel-table th {
            background-color: #f0f7ff;
            color: #003366;
            font-weight: 600;
        }

        .hostel-table tr:hover {
            background-color: #f8fbff;
        }

        .floor-input, .room-input {
            padding: 8px 12px;
            border: 1px solid #c2d6e6;
            border-radius: 6px;
            font-size: 0.95rem;
            width: 100px;
            transition: var(--transition);
            margin-bottom: 5px;
        }
        
        .floor-input:focus, .room-input:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
        }

        .save-btn {
            background: var(--success);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 15px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            width: 100%;
            margin-top: 5px;
        }
        
        .save-btn:hover {
            background: #218838;
            transform: translateY(-2px);
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
    
        .contact-btn i {
            font-size: 1.3rem;
            margin-right: 8px;
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

        /* Responsive adjustments */
        @media (max-width: 992px) {
            body {
                padding-top: 190px;
            }
            
            .welcome-banner h2 {
                font-size: 2.4rem;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 250px;
            }
            
            .hostel-table th, .hostel-table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            
            .hostel-table-container, .filter-container {
                padding: 20px;
            }
            
            .floor-input, .room-input {
                width: 80px;
            }
            
            .nav-title {
                font-size: 1.1rem;
            }
            
            .admin-info-container {
                padding: 8px 15px;
            }
            
            .user-name {
                max-width: 100px;
            }
        }

        @media (max-width: 576px) {
            .header-logos {
                flex-direction: column;
                gap: 10px;
                height: auto;
            }
            
            .logo-container {
                justify-content: center;
                height: auto;
            }
            
            .nav-content {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.8rem;
            }
            
            .admin-info-container {
                justify-content: center;
                width: 100%;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                grid-column: 1 / -1;
                margin-top: 10px;
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
                <div class="nav-title">Hostel Management System | NCRB</div>
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
            <h2>Hostel Accommodation Management</h2>
            <p>Welcome, <strong><?= $username ?></strong>. Manage hostel accommodations for training participants.</p>
        </div>
        
        <!-- Alert Messages -->
        <?php if(!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if(!empty($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="summary-card-value"><?= $total_applications ?></div>
                <div class="summary-card-label">Total Applications</div>
            </div>
            
            <div class="summary-card allotted">
                <div class="summary-card-icon">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="summary-card-value"><?= $rooms_allotted ?></div>
                <div class="summary-card-label">Rooms Allotted</div>
            </div>
        </div>
        
        <!-- Filter Container -->
        <div class="filter-container">
            <h2 class="filter-title">Filter Hostel Applications</h2>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">State</label>
                    <select name="state" class="filter-select">
                        <option value="">All States</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?= htmlspecialchars($state['state_name']) ?>" <?= $state_filter === $state['state_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($state['state_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Floor</label>
                    <select name="floor" class="filter-select">
                        <option value="">All Floors</option>
                        <?php foreach ($floors as $floor): ?>
                            <option value="<?= htmlspecialchars($floor['floor']) ?>" <?= $floor_filter === $floor['floor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($floor['floor']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="filter-btn apply-filter">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="admin-hostel-management.php" class="filter-btn reset-filter">
                        <i class="fas fa-sync-alt"></i> Reset Filters
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Hostel Table Container -->
        <div class="hostel-table-container">
            <h2 class="hostel-table-title">Hostel Applications</h2>
            
            <table class="hostel-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Participant Name</th>
                        <th>State</th>
                        <th>Registration ID</th>
                        <th>Contact Details</th>
                        <th>Stay Period</th>
                        <th>Duration</th>
                        <th>Special Requirements</th>
                        <th>Floor</th>
                        <th>Room Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($hostel_applications) > 0): ?>
                        <?php foreach($hostel_applications as $app): ?>
                            <tr>
                                <td><?= $app['id'] ?></td>
                                <td><?= htmlspecialchars($app['participant_name']) ?></td>
                                <td><?= htmlspecialchars($app['state_name']) ?></td>
                                <td><?= htmlspecialchars($app['registration_id']) ?></td>
                                <td>
                                    <div><i class="fas fa-phone"></i> <?= htmlspecialchars($app['residential_phone']) ?></div>
                                    <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($app['email'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <?= date('d M Y', strtotime($app['hostel_from'])) ?> - 
                                    <?= date('d M Y', strtotime($app['hostel_to'])) ?>
                                </td>
                                <td><?= $app['duration'] ?> days</td>
                                <td>
                                    <?php if (!empty($app['special_requirements'])): ?>
                                        <i class="fas fa-info-circle" title="<?= htmlspecialchars($app['special_requirements']) ?>"></i>
                                        <?= strlen($app['special_requirements']) > 30 ? 
                                            substr(htmlspecialchars($app['special_requirements']), 0, 30).'...' : 
                                            htmlspecialchars($app['special_requirements']) 
                                        ?>
                                    <?php else: ?>
                                        None
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" class="room-allot-form">
                                        <input type="hidden" name="hostel_id" value="<?= $app['id'] ?>">
                                        <input type="text" 
                                               name="floor" 
                                               class="floor-input" 
                                               value="<?= htmlspecialchars($app['floor'] ?? '') ?>" 
                                               placeholder="Floor" 
                                               pattern="[A-Z0-9\-]{1,5}"
                                               title="1-5 letters/numbers/hyphens">
                                </td>
                                <td>
                                        <input type="text" 
                                               name="room_number" 
                                               class="room-input" 
                                               value="<?= htmlspecialchars($app['room_number'] ?? '') ?>" 
                                               placeholder="Room No." 
                                               pattern="[A-Z0-9\-]{2,20}"
                                               title="Use letters, numbers, and hyphens only">
                                </td>
                                <td>
                                        <button type="submit" name="allot_room" class="save-btn">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align: center;">No hostel applications found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="text-align: center; margin-top: 30px;">
                <button class="print-btn" onclick="printHostelReport()">
                    <i class="fas fa-print"></i> Print Hostel Report
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
        document.querySelectorAll('.filter-input[type="date"]').forEach(input => {
            input.max = today;
        });
        
        // Print hostel report function
        function printHostelReport() {
            const printWindow = window.open('', '', 'width=1000,height=700');
            let printContent = `
            <html>
            <head>
                <title>NCRB Hostel Report</title>
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
                        flex-wrap: wrap;
                        margin-bottom: 20px;
                        font-size: 11pt;
                        gap: 15px;
                    }
                    .metadata div {
                        min-width: 200px;
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
                </style>
            </head>
            <body>
                <div class="print-header">
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" alt="MHA Logo">
                    <div class="print-title">
                        <h1>National Crime Records Bureau</h1>
                        <h2>Hostel Management Report</h2>
                    </div>
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                </div>
                <div class="metadata">
                    <div><strong>Generated:</strong> ${new Date().toLocaleString()}</div>
                    <div><strong>Admin:</strong> <?= $username ?></div>
                    <div><strong>Total Applications:</strong> <?= $total_applications ?></div>
                    <div><strong>Rooms Allotted:</strong> <?= $rooms_allotted ?></div>
                    <div><strong>Date Range:</strong> ${document.querySelector('[name="start_date"]').value || 'All dates'} to ${document.querySelector('[name="end_date"]').value || 'All dates'}</div>
                    <div><strong>State:</strong> ${document.querySelector('[name="state"]').value || 'All states'}</div>
                    <div><strong>Floor:</strong> ${document.querySelector('[name="floor"]').value || 'All floors'}</div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Participant Name</th>
                            <th>State</th>
                            <th>Registration ID</th>
                            <th>Contact Details</th>
                            <th>Stay Period</th>
                            <th>Duration</th>
                            <th>Special Requirements</th>
                            <th>Floor</th>
                            <th>Room Number</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            // Add table rows from hostel_applications
            <?php foreach($hostel_applications as $app): ?>
                printContent += `
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= htmlspecialchars($app['participant_name']) ?></td>
                        <td><?= htmlspecialchars($app['state_name']) ?></td>
                        <td><?= htmlspecialchars($app['registration_id']) ?></td>
                        <td>
                            Phone: <?= htmlspecialchars($app['residential_phone']) ?><br>
                            Email: <?= htmlspecialchars($app['email'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <?= date('d M Y', strtotime($app['hostel_from'])) ?> - 
                            <?= date('d M Y', strtotime($app['hostel_to'])) ?>
                        </td>
                        <td><?= $app['duration'] ?> days</td>
                        <td><?= htmlspecialchars($app['special_requirements'] ?? 'None') ?></td>
                        <td><?= htmlspecialchars($app['floor'] ?? '') ?></td>
                        <td><?= htmlspecialchars($app['room_number'] ?? '') ?></td>
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
    </script>
</body>
</html>