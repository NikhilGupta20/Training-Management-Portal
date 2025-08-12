<?php
// admin-rpctc-management.php
session_start();

// Check if admin is logged in
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

// Create table if not exists
$createTable = "CREATE TABLE IF NOT EXISTS rpctc_admin_credentials (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center ENUM('Lucknow', 'Kolkata', 'Hyderabad', 'Gujarat') NOT NULL,
    rpctc_username VARCHAR(255) NOT NULL,
    rpctc_password VARCHAR(255) NOT NULL,
    status ENUM('active', 'deleted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_updated_by VARCHAR(255) NOT NULL
)";

if (!$conn->query($createTable)) {
    die("Error creating table: " . $conn->error);
}

// Check and add missing columns if needed
$columns = [
    'status' => "ALTER TABLE rpctc_admin_credentials ADD COLUMN status ENUM('active', 'deleted') NOT NULL DEFAULT 'active' AFTER rpctc_password",
    'created_at' => "ALTER TABLE rpctc_admin_credentials ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "ALTER TABLE rpctc_admin_credentials ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'last_updated_by' => "ALTER TABLE rpctc_admin_credentials ADD COLUMN last_updated_by VARCHAR(255) NOT NULL AFTER status"
];

foreach ($columns as $column => $alterSql) {
    $check = $conn->query("SHOW COLUMNS FROM rpctc_admin_credentials LIKE '$column'");
    if ($check->num_rows == 0) {
        if (!$conn->query($alterSql)) {
            die("Error adding column $column: " . $conn->error);
        }
    }
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $response = ['success' => false, 'message' => '', 'credentials' => []];
    $admin_username = $_SESSION['admin_username'];
    
    // Create new credentials
    if (isset($_POST['create'])) {
        $center = $conn->real_escape_string($_POST['center']);
        $username = $conn->real_escape_string($_POST['username']);
        $password = $conn->real_escape_string($_POST['password']);

        $insert_stmt = $conn->prepare("INSERT INTO rpctc_admin_credentials (center, rpctc_username, rpctc_password, last_updated_by) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ssss", $center, $username, $password, $admin_username);
        
        if ($insert_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Credentials created successfully!";
        } else {
            $response['message'] = "Error creating credentials: " . $conn->error;
        }
        $insert_stmt->close();
    }
    
    // Update existing credentials
    if (isset($_POST['update'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $username = $conn->real_escape_string($_POST['username']);
        $password = $conn->real_escape_string($_POST['password']);
        
        if (!empty($password)) {
            $update_stmt = $conn->prepare("UPDATE rpctc_admin_credentials SET rpctc_username = ?, rpctc_password = ?, last_updated_by = ? WHERE id = ?");
            $update_stmt->bind_param("sssi", $username, $password, $admin_username, $id);
        } else {
            $update_stmt = $conn->prepare("UPDATE rpctc_admin_credentials SET rpctc_username = ?, last_updated_by = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $username, $admin_username, $id);
        }
        
        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Credentials updated successfully!";
        } else {
            $response['message'] = "Error updating credentials: " . $conn->error;
        }
        $update_stmt->close();
    }
    
    // Delete credentials (soft delete)
    if (isset($_POST['delete'])) {
        $id = $conn->real_escape_string($_POST['id']);
        
        $delete_stmt = $conn->prepare("UPDATE rpctc_admin_credentials SET status = 'deleted', last_updated_by = ? WHERE id = ?");
        $delete_stmt->bind_param("si", $admin_username, $id);
        
        if ($delete_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Credentials deleted successfully!";
        } else {
            $response['message'] = "Error deleting credentials: " . $conn->error;
        }
        $delete_stmt->close();
    }
    
    // Restore credentials
    if (isset($_POST['restore'])) {
        $id = $conn->real_escape_string($_POST['id']);
        
        $restore_stmt = $conn->prepare("UPDATE rpctc_admin_credentials SET status = 'active', last_updated_by = ? WHERE id = ?");
        $restore_stmt->bind_param("si", $admin_username, $id);
        
        if ($restore_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Credentials restored successfully!";
        } else {
            $response['message'] = "Error restoring credentials: " . $conn->error;
        }
        $restore_stmt->close();
    }
    
    // Refetch credentials after operation
    if ($response['success']) {
        $center = $conn->real_escape_string($_POST['center']);
        $fetch_stmt = $conn->prepare("SELECT id, center, rpctc_username, rpctc_password, status, created_at, updated_at, last_updated_by 
                                    FROM rpctc_admin_credentials 
                                    WHERE center = ?
                                    ORDER BY created_at DESC");
        $fetch_stmt->bind_param("s", $center);
        $fetch_stmt->execute();
        $result = $fetch_stmt->get_result();
        $response['credentials'] = [];
        
        while ($row = $result->fetch_assoc()) {
            $response['credentials'][] = $row;
        }
        $fetch_stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch admin details for header
$admin_username = $_SESSION['admin_username'];
$stmt = $conn->prepare("SELECT * FROM admin_credentials WHERE admin_username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();
$username = htmlspecialchars($admin['admin_username']);

// Fetch existing credentials (including deleted)
$credentials = [
    'Lucknow' => [],
    'Kolkata' => [],
    'Hyderabad' => [],
    'Gujarat' => []
];

// Fetch all credentials (active + deleted) including passwords
$fetch_stmt = $conn->prepare("SELECT id, center, rpctc_username, rpctc_password, status, created_at, updated_at, last_updated_by 
                             FROM rpctc_admin_credentials 
                             ORDER BY created_at DESC");
$fetch_stmt->execute();
$fetch_result = $fetch_stmt->get_result();

while ($row = $fetch_result->fetch_assoc()) {
    $credentials[$row['center']][] = $row;
}

$fetch_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>RPCTC Admin Management - NCRB Training Portal</title>
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
            transition: all 0.3s ease;
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

        /* ===== DASHBOARD SECTION ===== */
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
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header p {
            font-size: 1.1rem;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* RPCTC Management Styles */
        .rpctc-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 40px;
        }

        .tab-container {
            margin-bottom: 30px;
        }

        .tab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--light-gray);
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tabs {
            display: flex;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 25px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .tab-btn.active {
            color: var(--primary);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }

        .tab-btn:hover:not(.active) {
            color: var(--secondary);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        .form-title {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .form-container {
            background: var(--light);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .form-group {
            flex: 1 0 300px;
            padding: 0 15px;
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 16px;
            transition: var(--transition);
        }

        .form-input:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .password-info {
            font-size: 14px;
            color: var(--gray);
            margin-top: 5px;
        }

        .password-toggle {
            position: absolute;
            right: 25px;
            top: 40px;
            cursor: pointer;
            color: var(--gray);
        }

        /* Credentials Table */
        .credentials-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .credentials-table th {
            background: var(--primary);
            color: white;
            text-align: left;
            padding: 15px;
            font-weight: 600;
        }

        .credentials-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .credentials-table tr:nth-child(even) {
            background: var(--light);
        }

        .credentials-table tr:hover {
            background: rgba(0, 102, 204, 0.05);
        }

        .no-credentials {
            text-align: center;
            padding: 30px;
            color: var(--gray);
            font-style: italic;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-edit {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-edit:hover {
            background: #218838;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-restore {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-restore:hover {
            background: #138496;
        }

        .status-active {
            color: #28a745;
            font-weight: 600;
        }

        .status-deleted {
            color: #dc3545;
            font-weight: 600;
        }

        /* Status Filter */
        .status-filter {
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-filter label {
            font-weight: 600;
        }
        
        .status-filter select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--light-gray);
        }

        /* Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-weight: 500;
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

        /* Edit Modal */
        .edit-modal {
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
        
        .edit-modal-content {
            background: var(--white);
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .edit-modal-header {
            background: var(--primary);
            color: var(--white);
            padding: 20px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            border-radius: 10px 10px 0 0;
        }
        
        .close-edit-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close-edit-modal:hover {
            color: var(--accent);
        }
        
        .edit-modal-body {
            padding: 25px;
        }

        /* Animation Keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .contact-btn i {
            font-size: 1.3rem;
            margin-right: 8px;
        }

        .call-btn { background: #28a745; }
        .sms-btn { background: #17a2b8; }
        .whatsapp-btn { background: #25D366; }
        .email-btn { background: #dc3545; }

        /* ===== RESPONSIVE DESIGN ===== */
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
            
            .tab-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .tabs {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .tab-btn {
                flex: 1 0 25%;
                text-align: center;
                padding: 10px;
                font-size: 14px;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
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
            
            .form-group {
                flex: 1 0 100%;
            }
            
            .tab-btn {
                flex: 1 0 50%;
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
                <div class="nav-title">RPCTC Admin Management</div>
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
            <h1>RPCTC Admin Management</h1>
            <p>Create and manage credentials for Regional Police Computer Training Centers</p>
        </div>
        
        <div class="rpctc-container">
            <div class="tab-container">
                <div class="tab-header">
                    <div class="tabs">
                        <button class="tab-btn active" data-tab="lucknow">RPCTC Lucknow</button>
                        <button class="tab-btn" data-tab="kolkata">RPCTC Kolkata</button>
                        <button class="tab-btn" data-tab="hyderabad">RPCTC Hyderabad</button>
                        <button class="tab-btn" data-tab="gujarat">RPCTC Gujarat</button>
                    </div>
                    <a href="admin-dashboard.php" class="btn-submit">Back to Dashboard</a>
                </div>
                
                <!-- Lucknow Tab -->
                <div id="lucknow" class="tab-content active">
                    <h2 class="form-title">RPCTC Lucknow Admin Credentials</h2>
                    
                    <div id="notification-lucknow"></div>
                    
                    <div class="form-container">
                        <form id="create-form-lucknow" method="POST">
                            <input type="hidden" name="center" value="Lucknow">
                            <input type="hidden" name="create" value="1">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username-lucknow" class="form-label">Username</label>
                                    <input type="text" id="username-lucknow" name="username" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-lucknow" class="form-label">Password</label>
                                    <input type="password" id="password-lucknow" name="password" class="form-input" required>
                                    <span class="password-toggle" data-target="password-lucknow">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                    <p class="password-info">Password will be stored as plain text</p>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-submit">Create Credentials</button>
                        </form>
                    </div>
                    
                    <div class="status-filter">
                        <label>Show:</label>
                        <select id="status-filter-lucknow" class="status-filter-select">
                            <option value="all">All Credentials</option>
                            <option value="active">Active Only</option>
                            <option value="deleted">Deleted Only</option>
                        </select>
                    </div>
                    
                    <h3>Existing Credentials</h3>
                    <div id="credentials-container-lucknow">
                        <?php if(!empty($credentials['Lucknow'])): ?>
                            <table class="credentials-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Last Updated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($credentials['Lucknow'] as $cred): ?>
                                        <tr class="cred-row" data-status="<?php echo $cred['status']; ?>">
                                            <td><?php echo htmlspecialchars($cred['rpctc_username']); ?></td>
                                            <td><?php echo htmlspecialchars($cred['rpctc_password']); ?></td>
                                            <td class="status-<?php echo $cred['status']; ?>"><?php echo ucfirst($cred['status']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['created_at'])); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($cred['last_updated_by']); ?></td>
                                            <td class="action-buttons">
                                                <?php if($cred['status'] == 'active'): ?>
                                                    <button class="btn-edit" 
                                                        data-id="<?php echo $cred['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($cred['rpctc_username']); ?>"
                                                        data-password="<?php echo htmlspecialchars($cred['rpctc_password']); ?>"
                                                        data-center="Lucknow">
                                                        Edit
                                                    </button>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Lucknow">
                                                        <input type="hidden" name="delete" value="1">
                                                        <button type="submit" class="btn-delete">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Lucknow">
                                                        <input type="hidden" name="restore" value="1">
                                                        <button type="submit" class="btn-restore">Restore</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-credentials">
                                <p>No credentials created yet for RPCTC Lucknow</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Kolkata Tab -->
                <div id="kolkata" class="tab-content">
                    <h2 class="form-title">RPCTC Kolkata Admin Credentials</h2>
                    
                    <div id="notification-kolkata"></div>
                    
                    <div class="form-container">
                        <form id="create-form-kolkata" method="POST">
                            <input type="hidden" name="center" value="Kolkata">
                            <input type="hidden" name="create" value="1">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username-kolkata" class="form-label">Username</label>
                                    <input type="text" id="username-kolkata" name="username" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-kolkata" class="form-label">Password</label>
                                    <input type="password" id="password-kolkata" name="password" class="form-input" required>
                                    <span class="password-toggle" data-target="password-kolkata">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                    <p class="password-info">Password will be stored as plain text</p>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-submit">Create Credentials</button>
                        </form>
                    </div>
                    
                    <div class="status-filter">
                        <label>Show:</label>
                        <select id="status-filter-kolkata" class="status-filter-select">
                            <option value="all">All Credentials</option>
                            <option value="active">Active Only</option>
                            <option value="deleted">Deleted Only</option>
                        </select>
                    </div>
                    
                    <h3>Existing Credentials</h3>
                    <div id="credentials-container-kolkata">
                        <?php if(!empty($credentials['Kolkata'])): ?>
                            <table class="credentials-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Last Updated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($credentials['Kolkata'] as $cred): ?>
                                        <tr class="cred-row" data-status="<?php echo $cred['status']; ?>">
                                            <td><?php echo htmlspecialchars($cred['rpctc_username']); ?></td>
                                            <td><?php echo htmlspecialchars($cred['rpctc_password']); ?></td>
                                            <td class="status-<?php echo $cred['status']; ?>"><?php echo ucfirst($cred['status']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['created_at'])); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($cred['last_updated_by']); ?></td>
                                            <td class="action-buttons">
                                                <?php if($cred['status'] == 'active'): ?>
                                                    <button class="btn-edit" 
                                                        data-id="<?php echo $cred['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($cred['rpctc_username']); ?>"
                                                        data-password="<?php echo htmlspecialchars($cred['rpctc_password']); ?>"
                                                        data-center="Kolkata">
                                                        Edit
                                                    </button>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Kolkata">
                                                        <input type="hidden" name="delete" value="1">
                                                        <button type="submit" class="btn-delete">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Kolkata">
                                                        <input type="hidden" name="restore" value="1">
                                                        <button type="submit" class="btn-restore">Restore</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-credentials">
                                <p>No credentials created yet for RPCTC Kolkata</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Hyderabad Tab -->
                <div id="hyderabad" class="tab-content">
                    <h2 class="form-title">RPCTC Hyderabad Admin Credentials</h2>
                    
                    <div id="notification-hyderabad"></div>
                    
                    <div class="form-container">
                        <form id="create-form-hyderabad" method="POST">
                            <input type="hidden" name="center" value="Hyderabad">
                            <input type="hidden" name="create" value="1">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username-hyderabad" class="form-label">Username</label>
                                    <input type="text" id="username-hyderabad" name="username" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-hyderabad" class="form-label">Password</label>
                                    <input type="password" id="password-hyderabad" name="password" class="form-input" required>
                                    <span class="password-toggle" data-target="password-hyderabad">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                    <p class="password-info">Password will be stored as plain text</p>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-submit">Create Credentials</button>
                        </form>
                    </div>
                    
                    <div class="status-filter">
                        <label>Show:</label>
                        <select id="status-filter-hyderabad" class="status-filter-select">
                            <option value="all">All Credentials</option>
                            <option value="active">Active Only</option>
                            <option value="deleted">Deleted Only</option>
                        </select>
                    </div>
                    
                    <h3>Existing Credentials</h3>
                    <div id="credentials-container-hyderabad">
                        <?php if(!empty($credentials['Hyderabad'])): ?>
                            <table class="credentials-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Last Updated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($credentials['Hyderabad'] as $cred): ?>
                                        <tr class="cred-row" data-status="<?php echo $cred['status']; ?>">
                                            <td><?php echo htmlspecialchars($cred['rpctc_username']); ?></td>
                                            <td><?php echo htmlspecialchars($cred['rpctc_password']); ?></td>
                                            <td class="status-<?php echo $cred['status']; ?>"><?php echo ucfirst($cred['status']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['created_at'])); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($cred['last_updated_by']); ?></td>
                                            <td class="action-buttons">
                                                <?php if($cred['status'] == 'active'): ?>
                                                    <button class="btn-edit" 
                                                        data-id="<?php echo $cred['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($cred['rpctc_username']); ?>"
                                                        data-password="<?php echo htmlspecialchars($cred['rpctc_password']); ?>"
                                                        data-center="Hyderabad">
                                                        Edit
                                                    </button>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Hyderabad">
                                                        <input type="hidden" name="delete" value="1">
                                                        <button type="submit" class="btn-delete">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Hyderabad">
                                                        <input type="hidden" name="restore" value="1">
                                                        <button type="submit" class="btn-restore">Restore</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-credentials">
                                <p>No credentials created yet for RPCTC Hyderabad</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Gujarat Tab -->
                <div id="gujarat" class="tab-content">
                    <h2 class="form-title">RPCTC Gujarat Admin Credentials</h2>
                    
                    <div id="notification-gujarat"></div>
                    
                    <div class="form-container">
                        <form id="create-form-gujarat" method="POST">
                            <input type="hidden" name="center" value="Gujarat">
                            <input type="hidden" name="create" value="1">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username-gujarat" class="form-label">Username</label>
                                    <input type="text" id="username-gujarat" name="username" class="form-input" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password-gujarat" class="form-label">Password</label>
                                    <input type="password" id="password-gujarat" name="password" class="form-input" required>
                                    <span class="password-toggle" data-target="password-gujarat">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                    <p class="password-info">Password will be stored as plain text</p>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-submit">Create Credentials</button>
                        </form>
                    </div>
                    
                    <div class="status-filter">
                        <label>Show:</label>
                        <select id="status-filter-gujarat" class="status-filter-select">
                            <option value="all">All Credentials</option>
                            <option value="active">Active Only</option>
                            <option value="deleted">Deleted Only</option>
                        </select>
                    </div>
                    
                    <h3>Existing Credentials</h3>
                    <div id="credentials-container-gujarat">
                        <?php if(!empty($credentials['Gujarat'])): ?>
                            <table class="credentials-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Updated At</th>
                                        <th>Last Updated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($credentials['Gujarat'] as $cred): ?>
                                        <tr class="cred-row" data-status="<?php echo $cred['status']; ?>">
                                            <td><?php echo htmlspecialchars($cred['rpctc_username']); ?></td>
                                            <td><?php echo htmlspecialchars($cred['rpctc_password']); ?></td>
                                            <td class="status-<?php echo $cred['status']; ?>"><?php echo ucfirst($cred['status']); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['created_at'])); ?></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($cred['updated_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($cred['last_updated_by']); ?></td>
                                            <td class="action-buttons">
                                                <?php if($cred['status'] == 'active'): ?>
                                                    <button class="btn-edit" 
                                                        data-id="<?php echo $cred['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($cred['rpctc_username']); ?>"
                                                        data-password="<?php echo htmlspecialchars($cred['rpctc_password']); ?>"
                                                        data-center="Gujarat">
                                                        Edit
                                                    </button>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Gujarat">
                                                        <input type="hidden" name="delete" value="1">
                                                        <button type="submit" class="btn-delete">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form class="action-form" method="POST">
                                                        <input type="hidden" name="id" value="<?php echo $cred['id']; ?>">
                                                        <input type="hidden" name="center" value="Gujarat">
                                                        <input type="hidden" name="restore" value="1">
                                                        <button type="submit" class="btn-restore">Restore</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-credentials">
                                <p>No credentials created yet for RPCTC Gujarat</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Credentials Modal -->
    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                Edit Credentials
                <span class="close-edit-modal">&times;</span>
            </div>
            <div class="edit-modal-body">
                <form method="POST" id="edit-form">
                    <input type="hidden" name="id" id="edit-id">
                    <input type="hidden" name="center" id="edit-center">
                    <input type="hidden" name="update" value="1">
                    
                    <div class="form-group">
                        <label for="edit-username" class="form-label">Username</label>
                        <input type="text" id="edit-username" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-password" class="form-label">Password</label>
                        <input type="password" id="edit-password" name="password" class="form-input" placeholder="Enter new password">
                        <span class="password-toggle" data-target="edit-password">
                            <i class="fas fa-eye"></i>
                        </span>
                        <p class="password-info">Leave blank to keep current password</p>
                    </div>
                    
                    <button type="submit" class="btn-submit">Update Credentials</button>
                </form>
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
        // ===== TAB FUNCTIONALITY =====
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active class from all buttons and content
                    tabBtns.forEach(b => b.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked button
                    btn.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = btn.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // ===== PASSWORD TOGGLE FUNCTIONALITY =====
            const passwordToggles = document.querySelectorAll('.password-toggle');
            
            passwordToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // ===== STATUS FILTER FUNCTIONALITY =====
            const statusFilters = document.querySelectorAll('.status-filter-select');
            
            statusFilters.forEach(filter => {
                filter.addEventListener('change', function() {
                    const tabContent = this.closest('.tab-content');
                    const rows = tabContent.querySelectorAll('.cred-row');
                    const status = this.value;
                    
                    rows.forEach(row => {
                        if (status === 'all') {
                            row.style.display = '';
                        } else {
                            if (row.getAttribute('data-status') === status) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        }
                    });
                });
            });
            
            // ===== EDIT MODAL FUNCTIONALITY =====
            const editModal = document.getElementById('editModal');
            const closeEditModal = document.querySelector('.close-edit-modal');
            const editButtons = document.querySelectorAll('.btn-edit');
            const editForm = document.getElementById('edit-form');
            const editIdInput = document.getElementById('edit-id');
            const editUsernameInput = document.getElementById('edit-username');
            const editPasswordInput = document.getElementById('edit-password');
            const editCenterInput = document.getElementById('edit-center');
            
            // Open edit modal
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    const password = this.getAttribute('data-password');
                    const center = this.getAttribute('data-center');
                    
                    editIdInput.value = id;
                    editUsernameInput.value = username;
                    editPasswordInput.value = password;
                    editCenterInput.value = center;
                    
                    editModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                });
            });
            
            // Close edit modal
            closeEditModal.addEventListener('click', function() {
                editModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === editModal) {
                    editModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
            
            // ===== DATE AND COPYRIGHT =====
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
            
            // ===== AJAX FORM HANDLING =====
            function handleFormSubmit(e) {
                e.preventDefault();
                const form = e.target;
                const formData = new FormData(form);
                const center = formData.get('center') || document.getElementById('edit-center').value;
                const notificationId = `notification-${center.toLowerCase()}`;
                
                fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Show notification
                    const notification = document.getElementById(notificationId);
                    notification.innerHTML = `<div class="alert alert-${data.success ? 'success' : 'error'}">${data.message}</div>`;
                    
                    // Remove notification after 5 seconds
                    setTimeout(() => {
                        notification.innerHTML = '';
                    }, 5000);
                    
                    if (data.success) {
                        // Reset create form
                        if (form.id.startsWith('create-form')) {
                            form.reset();
                        }
                        
                        // Close edit modal
                        if (form.id === 'edit-form') {
                            editModal.style.display = 'none';
                            document.body.style.overflow = 'auto';
                        }
                        
                        // Update credentials table
                        updateCredentialsTable(center, data.credentials);
                    }
                })
                .catch(error => {
                    const notification = document.getElementById(notificationId);
                    notification.innerHTML = `<div class="alert alert-error">An error occurred. Please try again.</div>`;
                    console.error('Error:', error);
                });
            }
            
            function updateCredentialsTable(center, credentials) {
                const containerId = `credentials-container-${center.toLowerCase()}`;
                const container = document.getElementById(containerId);
                
                if (credentials.length === 0) {
                    container.innerHTML = `
                        <div class="no-credentials">
                            <p>No credentials created yet for RPCTC ${center}</p>
                        </div>
                    `;
                    return;
                }
                
                let tableHTML = `
                    <table class="credentials-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Last Updated By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                credentials.forEach(cred => {
                    const createdDate = new Date(cred.created_at).toLocaleString('en-US', { 
                        day: '2-digit', 
                        month: 'short', 
                        year: 'numeric', 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                    
                    const updatedDate = new Date(cred.updated_at).toLocaleString('en-US', { 
                        day: '2-digit', 
                        month: 'short', 
                        year: 'numeric', 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    });
                    
                    tableHTML += `
                        <tr class="cred-row" data-status="${cred.status}">
                            <td>${escapeHtml(cred.rpctc_username)}</td>
                            <td>${escapeHtml(cred.rpctc_password)}</td>
                            <td class="status-${cred.status}">${cred.status.charAt(0).toUpperCase() + cred.status.slice(1)}</td>
                            <td>${createdDate}</td>
                            <td>${updatedDate}</td>
                            <td>${escapeHtml(cred.last_updated_by)}</td>
                            <td class="action-buttons">
                    `;
                    
                    if (cred.status === 'active') {
                        tableHTML += `
                            <button class="btn-edit" 
                                data-id="${cred.id}"
                                data-username="${escapeHtml(cred.rpctc_username)}"
                                data-password="${escapeHtml(cred.rpctc_password)}"
                                data-center="${center}">
                                Edit
                            </button>
                            <form class="action-form" method="POST">
                                <input type="hidden" name="id" value="${cred.id}">
                                <input type="hidden" name="center" value="${center}">
                                <input type="hidden" name="delete" value="1">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        `;
                    } else {
                        tableHTML += `
                            <form class="action-form" method="POST">
                                <input type="hidden" name="id" value="${cred.id}">
                                <input type="hidden" name="center" value="${center}">
                                <input type="hidden" name="restore" value="1">
                                <button type="submit" class="btn-restore">Restore</button>
                            </form>
                        `;
                    }
                    
                    tableHTML += `
                            </td>
                        </tr>
                    `;
                });
                
                tableHTML += `
                        </tbody>
                    </table>
                `;
                
                container.innerHTML = tableHTML;
                
                // Reattach event handlers
                document.querySelectorAll('.btn-edit').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const username = this.getAttribute('data-username');
                        const password = this.getAttribute('data-password');
                        const center = this.getAttribute('data-center');
                        
                        editIdInput.value = id;
                        editUsernameInput.value = username;
                        editPasswordInput.value = password;
                        editCenterInput.value = center;
                        
                        editModal.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    });
                });
                
                document.querySelectorAll('.action-form').forEach(form => {
                    form.addEventListener('submit', handleFormSubmit);
                });
            }
            
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
            
            // Attach form submit handlers
            document.querySelectorAll('#create-form-lucknow, #create-form-kolkata, #create-form-hyderabad, #create-form-gujarat, #edit-form, .action-form').forEach(form => {
                form.addEventListener('submit', handleFormSubmit);
            });
        });
    </script>
</body>
</html>