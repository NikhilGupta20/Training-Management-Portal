<?php
// admin-faculty-management.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.php");
    exit;
}

// Initialize active tab from session or default to master-pool
$active_tab = $_SESSION['active_tab'] ?? 'master-pool';

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist with corrected schema
$create_tables = "
    CREATE TABLE IF NOT EXISTS faculty_master (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        mobile VARCHAR(15) NOT NULL,
        email VARCHAR(255) NOT NULL,
        expertise VARCHAR(255) NOT NULL,
        institute VARCHAR(255) NOT NULL,
        eminent BOOLEAN DEFAULT 0,
        professional_degree VARCHAR(255) NOT NULL,
        pan VARCHAR(20) NOT NULL,
        account_name VARCHAR(255) NOT NULL,
        account_no VARCHAR(30) NOT NULL,
        ifsc VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB;
    
    CREATE TABLE IF NOT EXISTS faculty_ncrb (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        rating DECIMAL(3,2) DEFAULT 0.00,
        FOREIGN KEY (faculty_id) REFERENCES faculty_master(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
    
    CREATE TABLE IF NOT EXISTS faculty_other (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id INT NOT NULL,
        rating DECIMAL(3,2) DEFAULT 0.00,
        FOREIGN KEY (faculty_id) REFERENCES faculty_master(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";

if ($conn->multi_query($create_tables)) {
    while ($conn->next_result()) {;} // Flush multi_queries
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
$stmt->close();

// Extract username
$username = htmlspecialchars($admin['admin_username']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store active tab from form submission
    if (isset($_POST['active_tab'])) {
        $_SESSION['active_tab'] = $_POST['active_tab'];
        $active_tab = $_SESSION['active_tab'];
    }
    
    // Add new faculty to master pool
    if (isset($_POST['add_faculty'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $email = $conn->real_escape_string($_POST['email']);
        $expertise = $conn->real_escape_string($_POST['expertise']);
        $institute = $conn->real_escape_string($_POST['institute']);
        $eminent = isset($_POST['eminent']) ? 1 : 0;
        $degree = $conn->real_escape_string($_POST['degree']);
        $pan = $conn->real_escape_string($_POST['pan']);
        $account_name = $conn->real_escape_string($_POST['account_name']);
        $account_no = $conn->real_escape_string($_POST['account_no']);
        $ifsc = $conn->real_escape_string($_POST['ifsc']);
        
        $stmt = $conn->prepare("INSERT INTO faculty_master (name, mobile, email, expertise, institute, eminent, professional_degree, pan, account_name, account_no, ifsc) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssisssss", $name, $mobile, $email, $expertise, $institute, $eminent, $degree, $pan, $account_name, $account_no, $ifsc);
        $stmt->execute();
        $stmt->close();
    }
    
    // Assign faculty to NCRB
    if (isset($_POST['assign_to_ncrb']) && isset($_POST['faculty_ids'])) {
        $stmt_check = $conn->prepare("SELECT id FROM faculty_master WHERE id = ?");
        $stmt_insert = $conn->prepare("INSERT INTO faculty_ncrb (faculty_id) VALUES (?)");
        
        foreach ($_POST['faculty_ids'] as $faculty_id) {
            $faculty_id = intval($faculty_id);
            // Validate faculty exists
            $stmt_check->bind_param("i", $faculty_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            
            if ($result->num_rows > 0) {
                // Check if already assigned
                $check = $conn->query("SELECT * FROM faculty_ncrb WHERE faculty_id = $faculty_id");
                if ($check->num_rows == 0) {
                    $stmt_insert->bind_param("i", $faculty_id);
                    $stmt_insert->execute();
                }
            }
        }
        $stmt_check->close();
        $stmt_insert->close();
    }
    
    // Assign faculty to Other
    if (isset($_POST['assign_to_other']) && isset($_POST['faculty_ids'])) {
        $stmt_check = $conn->prepare("SELECT id FROM faculty_master WHERE id = ?");
        $stmt_insert = $conn->prepare("INSERT INTO faculty_other (faculty_id) VALUES (?)");
        
        foreach ($_POST['faculty_ids'] as $faculty_id) {
            $faculty_id = intval($faculty_id);
            // Validate faculty exists
            $stmt_check->bind_param("i", $faculty_id);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            
            if ($result->num_rows > 0) {
                // Check if already assigned
                $check = $conn->query("SELECT * FROM faculty_other WHERE faculty_id = $faculty_id");
                if ($check->num_rows == 0) {
                    $stmt_insert->bind_param("i", $faculty_id);
                    $stmt_insert->execute();
                }
            }
        }
        $stmt_check->close();
        $stmt_insert->close();
    }
    
    // Update ratings
    if (isset($_POST['update_ratings'])) {
        foreach ($_POST['ratings'] as $id => $rating) {
            $id = intval($id);
            $rating = floatval($rating);
            
            if (isset($_POST['category']) && $_POST['category'] === 'ncrb') {
                $stmt = $conn->prepare("UPDATE faculty_ncrb SET rating = ? WHERE id = ?");
                $stmt->bind_param("di", $rating, $id);
                $stmt->execute();
                $stmt->close();
            } elseif (isset($_POST['category']) && $_POST['category'] === 'other') {
                $stmt = $conn->prepare("UPDATE faculty_other SET rating = ? WHERE id = ?");
                $stmt->bind_param("di", $rating, $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    // Remove from NCRB
    if (isset($_POST['remove_from_ncrb'])) {
        $id = intval($_POST['remove_id']);
        $stmt = $conn->prepare("DELETE FROM faculty_ncrb WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Remove from Other
    if (isset($_POST['remove_from_other'])) {
        $id = intval($_POST['remove_id']);
        $stmt = $conn->prepare("DELETE FROM faculty_other WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete from master pool
    if (isset($_POST['delete_from_master'])) {
        $id = intval($_POST['delete_id']);
        $stmt = $conn->prepare("DELETE FROM faculty_master WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch data for display
$master_faculties = $conn->query("SELECT * FROM faculty_master");
$ncrb_faculties = $conn->query("SELECT fn.id as ncrb_id, fm.*, fn.rating 
                               FROM faculty_ncrb fn 
                               JOIN faculty_master fm ON fn.faculty_id = fm.id");
$other_faculties = $conn->query("SELECT fo.id as other_id, fm.*, fo.rating 
                               FROM faculty_other fo 
                               JOIN faculty_master fm ON fo.faculty_id = fm.id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Faculty Management - NCRB Training Portal</title>
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

        /* Header Styles */
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
            transition: all 0.3s ease;
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

        /* Dashboard Styles */
        .dashboard {
            max-width: 1400px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
            padding-top: 20px;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Faculty Management Styles */
        .faculty-management {
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 50px;
        }

        .faculty-tabs {
            background: var(--primary);
            border-bottom: 3px solid var(--accent);
            padding: 0 20px;
        }

        .tabs-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .tabs-group {
            display: flex;
        }

        .tab-btn {
            padding: 15px 25px;
            background: transparent;
            border: none;
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .tab-btn.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .tab-btn:after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--accent);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .tab-btn.active:after {
            transform: scaleX(1);
        }

        .tab-content {
            padding: 30px;
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-back {
            background: var(--accent);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            margin: 5px 0;
            border: none;
            cursor: pointer;
        }

        .btn-back:hover {
            background: #e68a00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 153, 0, 0.25);
        }

        .faculty-form {
            background: var(--light);
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }

        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--secondary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.25);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background: rgba(0, 102, 204, 0.1);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .accounts-section {
            background: rgba(0, 102, 204, 0.05);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid var(--secondary);
        }

        .section-title {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
        }

        .faculty-list {
            margin-top: 30px;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid var(--light-gray);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        thead {
            background: var(--primary);
            color: var(--white);
        }

        th {
            font-weight: 600;
        }

        tbody tr:hover {
            background-color: rgba(0, 102, 204, 0.03);
        }

        .rating-input {
            width: 80px;
            padding: 8px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            text-align: center;
        }

        .assignment-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .assignment-box {
            flex: 1;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            background: var(--light);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .assignment-header {
            font-size: 1.2rem;
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .assignment-content {
            min-height: 300px;
            max-height: 400px;
            overflow-y: auto;
        }

        .faculty-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
            transition: var(--transition);
        }

        .faculty-item:hover {
            background: rgba(0, 102, 204, 0.05);
        }

        .faculty-item:last-child {
            border-bottom: none;
        }

        .faculty-name {
            font-weight: 600;
        }

        .faculty-expertise {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .drag-area {
            border: 2px dashed var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: rgba(0, 102, 204, 0.02);
            color: var(--gray);
        }

        .drag-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--light-gray);
        }

        .drag-text {
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        .drag-hint {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .action-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .rating-table {
            width: 100%;
            margin-top: 20px;
        }

        .rating-table th {
            background: var(--primary);
            color: var(--white);
        }

        .rating-table .rating-cell {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Account details styling */
        .account-details {
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .account-detail {
            display: flex;
            margin-bottom: 3px;
        }
        
        .account-label {
            font-weight: 600;
            min-width: 120px;
        }

        /* Footer Styles */
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
            cursor: pointer;
        }

        .copyright a#developer-contact-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            body {
                padding-top: 190px;
            }
            
            .dashboard-header h1 {
                font-size: 2.2rem;
            }
            
            .logo-container img {
                height: 70px;
            }
            
            .ncrb-logo {
                height: 80px;
            }

            .assignment-container {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 180px;
            }
            
            .header-logos {
                padding: 10px 20px;
            }
            
            .logo-container img {
                height: 60px;
            }
            
            .ncrb-logo {
                height: 70px;
            }
            
            .nav-container {
                padding: 0 20px 5px;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs-container {
                flex-direction: column;
            }
            
            .tabs-group {
                width: 100%;
                flex-direction: column;
            }
            
            .btn-back {
                width: 100%;
                margin-top: 10px;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 250px;
            }
            
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
            
            .dashboard-header h1 {
                font-size: 1.8rem;
            }
            
            .admin-info-container {
                justify-content: center;
                width: 100%;
            }

            .account-details {
                font-size: 0.75rem;
            }
            
            .account-label {
                min-width: 90px;
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
                <div class="nav-title">National Crime Records Bureau - Faculty Management</div>
                <div class="admin-info-container">
                    <div class="user-avatar">
                        <?php 
                            // Get first letter of username
                            $initial = strtoupper(substr($username, 0, 1));
                            echo $initial;
                        ?>
                    </div>
                    <div class="user-name">
                        <?php echo $username; ?>
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
            <h1>Faculty Management</h1>
            <p>Manage all faculty members categorized by Master Pool, NCRB Faculties, and Other Faculties</p>
        </div>
        
        <!-- Faculty Management Section -->
        <div class="faculty-management">
            <div class="faculty-tabs">
                <div class="tabs-container">
                    <div class="tabs-group">
                        <button class="tab-btn <?php echo $active_tab === 'master-pool' ? 'active' : ''; ?>" 
                                data-tab="master-pool">Master Pool</button>
                        <button class="tab-btn <?php echo $active_tab === 'ncrb-faculties' ? 'active' : ''; ?>" 
                                data-tab="ncrb-faculties">NCRB Faculties</button>
                        <button class="tab-btn <?php echo $active_tab === 'other-faculties' ? 'active' : ''; ?>" 
                                data-tab="other-faculties">Other Faculties</button>
                    </div>
                    <a href="admin-dashboard.php" class="tab-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Master Pool Tab -->
            <div id="master-pool" class="tab-content <?php echo $active_tab === 'master-pool' ? 'active' : ''; ?>">
                <div class="faculty-form">
                    <h2 class="section-title">Add New Faculty</h2>
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="master-pool">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="mobile">Mobile Number</label>
                                <input type="tel" id="mobile" name="mobile" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="expertise">Area of Expertise</label>
                                <input type="text" id="expertise" name="expertise" required>
                            </div>
                            <div class="form-group">
                                <label for="institute">Institute/Organization</label>
                                <input type="text" id="institute" name="institute" required>
                            </div>
                            <div class="form-group">
                                <label for="degree">Professional Degree</label>
                                <input type="text" id="degree" name="degree" required>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="eminent" name="eminent">
                            <label for="eminent">Mark as Eminent Faculty</label>
                        </div>
                        
                        <div class="accounts-section">
                            <h3>Account Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="pan">PAN Number</label>
                                    <input type="text" id="pan" name="pan" required>
                                </div>
                                <div class="form-group">
                                    <label for="account_name">Account Holder Name</label>
                                    <input type="text" id="account_name" name="account_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="account_no">Account Number</label>
                                    <input type="text" id="account_no" name="account_no" required>
                                </div>
                                <div class="form-group">
                                    <label for="ifsc">IFSC Code</label>
                                    <input type="text" id="ifsc" name="ifsc" required>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="add_faculty" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Add Faculty
                        </button>
                    </form>
                </div>
                
                <div class="faculty-list">
                    <h2 class="section-title">Master Faculty Pool</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th>Expertise</th>
                                    <th>Institute</th>
                                    <th>Eminent</th>
                                    <th>Degree</th>
                                    <th>Account Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($master_faculties && $master_faculties->num_rows > 0): ?>
                                    <?php while($faculty = $master_faculties->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $faculty['id']; ?></td>
                                        <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['mobile']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['expertise']); ?></td>
                                        <td><?php echo htmlspecialchars($faculty['institute']); ?></td>
                                        <td><?php echo $faculty['eminent'] ? 'Yes' : 'No'; ?></td>
                                        <td><?php echo htmlspecialchars($faculty['professional_degree']); ?></td>
                                        <td>
                                            <div class="account-details">
                                                <div class="account-detail">
                                                    <span class="account-label">PAN:</span>
                                                    <span><?php echo htmlspecialchars($faculty['pan']); ?></span>
                                                </div>
                                                <div class="account-detail">
                                                    <span class="account-label">Holder:</span>
                                                    <span><?php echo htmlspecialchars($faculty['account_name']); ?></span>
                                                </div>
                                                <div class="account-detail">
                                                    <span class="account-label">Account No:</span>
                                                    <span><?php echo htmlspecialchars($faculty['account_no']); ?></span>
                                                </div>
                                                <div class="account-detail">
                                                    <span class="account-label">IFSC:</span>
                                                    <span><?php echo htmlspecialchars($faculty['ifsc']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="active_tab" value="master-pool">
                                                <input type="hidden" name="delete_id" value="<?php echo $faculty['id']; ?>">
                                                <button type="submit" name="delete_from_master" class="btn btn-danger" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 30px;">
                                            No faculties found in master pool. Please add new faculty members.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- NCRB Faculties Tab -->
            <div id="ncrb-faculties" class="tab-content <?php echo $active_tab === 'ncrb-faculties' ? 'active' : ''; ?>">
                <div class="assignment-container">
                    <div class="assignment-box">
                        <div class="assignment-header">
                            <span>Master Faculty Pool</span>
                            <span class="badge"><?php echo $master_faculties->num_rows; ?> Faculty</span>
                        </div>
                        <div class="assignment-content">
                            <form id="assign-ncrb-form" method="POST">
                                <input type="hidden" name="active_tab" value="ncrb-faculties">
                                <?php 
                                // Reset pointer and re-fetch master faculties
                                $master_faculties->data_seek(0);
                                if ($master_faculties->num_rows > 0): ?>
                                    <?php while($faculty = $master_faculties->fetch_assoc()): ?>
                                    <div class="faculty-item">
                                        <div>
                                            <div class="faculty-name"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                            <div class="faculty-expertise"><?php echo htmlspecialchars($faculty['expertise']); ?></div>
                                        </div>
                                        <input type="checkbox" name="faculty_ids[]" value="<?php echo $faculty['id']; ?>">
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state" style="padding: 20px; text-align: center;">
                                        <p>No faculties available in master pool</p>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <div class="assignment-box">
                        <div class="assignment-header">
                            <span>NCRB Faculties</span>
                            <span class="badge"><?php echo $ncrb_faculties->num_rows; ?> Faculty</span>
                        </div>
                        <div class="assignment-content">
                            <?php if ($ncrb_faculties->num_rows > 0): ?>
                                <?php while($faculty = $ncrb_faculties->fetch_assoc()): ?>
                                <div class="faculty-item">
                                    <div>
                                        <div class="faculty-name"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                        <div class="faculty-expertise"><?php echo htmlspecialchars($faculty['expertise']); ?></div>
                                    </div>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="active_tab" value="ncrb-faculties">
                                        <input type="hidden" name="remove_id" value="<?php echo $faculty['ncrb_id']; ?>">
                                        <button type="submit" name="remove_from_ncrb" class="btn btn-danger" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="drag-area">
                                    <div class="drag-icon">
                                        <i class="fas fa-arrow-left"></i>
                                    </div>
                                    <div class="drag-text">Assign Faculty from Master Pool</div>
                                    <div class="drag-hint">Select faculty and click "Assign to NCRB"</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" form="assign-ncrb-form" name="assign_to_ncrb" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Assign to NCRB
                    </button>
                </div>
                
                <div class="faculty-list">
                    <h2 class="section-title">NCRB Faculty Ratings</h2>
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="ncrb-faculties">
                        <input type="hidden" name="category" value="ncrb">
                        <div class="table-container">
                            <table class="rating-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Expertise</th>
                                        <th>Rating (1-5)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset pointer for NCRB faculties
                                    $ncrb_faculties->data_seek(0);
                                    if ($ncrb_faculties->num_rows > 0): ?>
                                        <?php while($faculty = $ncrb_faculties->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $faculty['id']; ?></td>
                                            <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                            <td><?php echo htmlspecialchars($faculty['expertise']); ?></td>
                                            <td class="rating-cell">
                                                <input type="number" name="ratings[<?php echo $faculty['ncrb_id']; ?>]" 
                                                       class="rating-input" min="0" max="5" step="0.1" 
                                                       value="<?php echo $faculty['rating']; ?>">
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="active_tab" value="ncrb-faculties">
                                                    <input type="hidden" name="remove_id" value="<?php echo $faculty['ncrb_id']; ?>">
                                                    <button type="submit" name="remove_from_ncrb" class="btn btn-outline">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 30px;">
                                                No faculties assigned to NCRB yet. Please assign from Master Pool.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" name="update_ratings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Ratings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Other Faculties Tab -->
            <div id="other-faculties" class="tab-content <?php echo $active_tab === 'other-faculties' ? 'active' : ''; ?>">
                <div class="assignment-container">
                    <div class="assignment-box">
                        <div class="assignment-header">
                            <span>Master Faculty Pool</span>
                            <span class="badge"><?php echo $master_faculties->num_rows; ?> Faculty</span>
                        </div>
                        <div class="assignment-content">
                            <form id="assign-other-form" method="POST">
                                <input type="hidden" name="active_tab" value="other-faculties">
                                <?php 
                                // Reset pointer and re-fetch master faculties
                                $master_faculties->data_seek(0);
                                if ($master_faculties->num_rows > 0): ?>
                                    <?php while($faculty = $master_faculties->fetch_assoc()): ?>
                                    <div class="faculty-item">
                                        <div>
                                            <div class="faculty-name"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                            <div class="faculty-expertise"><?php echo htmlspecialchars($faculty['expertise']); ?></div>
                                        </div>
                                        <input type="checkbox" name="faculty_ids[]" value="<?php echo $faculty['id']; ?>">
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state" style="padding: 20px; text-align: center;">
                                        <p>No faculties available in master pool</p>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <div class="assignment-box">
                        <div class="assignment-header">
                            <span>Other Faculties</span>
                            <span class="badge"><?php echo $other_faculties->num_rows; ?> Faculty</span>
                        </div>
                        <div class="assignment-content">
                            <?php if ($other_faculties->num_rows > 0): ?>
                                <?php while($faculty = $other_faculties->fetch_assoc()): ?>
                                <div class="faculty-item">
                                    <div>
                                        <div class="faculty-name"><?php echo htmlspecialchars($faculty['name']); ?></div>
                                        <div class="faculty-expertise"><?php echo htmlspecialchars($faculty['expertise']); ?></div>
                                    </div>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="active_tab" value="other-faculties">
                                        <input type="hidden" name="remove_id" value="<?php echo $faculty['other_id']; ?>">
                                        <button type="submit" name="remove_from_other" class="btn btn-danger" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="drag-area">
                                    <div class="drag-icon">
                                        <i class="fas fa-arrow-left"></i>
                                    </div>
                                    <div class="drag-text">Assign Faculty from Master Pool</div>
                                    <div class="drag-hint">Select faculty and click "Assign to Other"</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" form="assign-other-form" name="assign_to_other" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Assign to Other
                    </button>
                </div>
                
                <div class="faculty-list">
                    <h2 class="section-title">Other Faculty Ratings</h2>
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="other-faculties">
                        <input type="hidden" name="category" value="other">
                        <div class="table-container">
                            <table class="rating-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Expertise</th>
                                        <th>Rating (1-5)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Reset pointer for other faculties
                                    $other_faculties->data_seek(0);
                                    if ($other_faculties->num_rows > 0): ?>
                                        <?php while($faculty = $other_faculties->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $faculty['id']; ?></td>
                                            <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                            <td><?php echo htmlspecialchars($faculty['expertise']); ?></td>
                                            <td class="rating-cell">
                                                <input type="number" name="ratings[<?php echo $faculty['other_id']; ?>]" 
                                                       class="rating-input" min="0" max="5" step="0.1" 
                                                       value="<?php echo $faculty['rating']; ?>">
                                            </td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="active_tab" value="other-faculties">
                                                    <input type="hidden" name="remove_id" value="<?php echo $faculty['other_id']; ?>">
                                                    <button type="submit" name="remove_from_other" class="btn btn-outline">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 30px;">
                                                No faculties assigned to Other yet. Please assign from Master Pool.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" name="update_ratings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Ratings
                            </button>
                        </div>
                    </form>
                </div>
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
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all buttons and contents
                    tabBtns.forEach(b => b.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked button
                    btn.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = btn.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                    
                    // Store active tab in sessionStorage for client-side persistence
                    sessionStorage.setItem('activeTab', tabId);
                });
            });
            
            // Restore active tab from sessionStorage
            const activeTab = sessionStorage.getItem('activeTab');
            if (activeTab) {
                const tabBtn = document.querySelector(`.tab-btn[data-tab="${activeTab}"]`);
                if (tabBtn) {
                    tabBtn.click();
                }
            }
            
            // Date and copyright
            const dateOptions = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            document.getElementById('current-date').textContent = 
                new Date().toLocaleDateString('en-US', dateOptions);
                
            document.getElementById('copyright-year').textContent = 
                new Date().getFullYear();
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