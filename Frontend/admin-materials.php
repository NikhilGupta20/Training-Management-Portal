<?php
// admin-materials.php
session_start();

// Redirect if not logged in
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

// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS training_events (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255) DEFAULT '',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        duration INT(11) DEFAULT 0,
        location VARCHAR(100) DEFAULT 'NCRB Training Center',
        eligibility TEXT DEFAULT '',
        objectives TEXT DEFAULT '',
        color VARCHAR(7) DEFAULT '#ff6b6b',
        status ENUM('active','cancelled') DEFAULT 'active',
        reminders_enabled BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS course_materials (
        material_id INT(11) AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        event_id INT(6) UNSIGNED NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        file_size INT(11) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_general TINYINT(1) DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES training_events(id) ON DELETE CASCADE
    )",
    
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        // Handle table creation errors
        error_log("Table creation failed: " . $conn->error);
    }
}

// Create uploads directory if not exists
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die("Failed to create upload directory");
    }
}

// Get admin username from session
$admin_username = $_SESSION['admin_username'];

// Fetch admin details
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

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// File upload handling
$uploadStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['material_file'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

    $course_code = $_POST['course_code'];
    $event_id = isset($_POST['event_id']) ? $_POST['event_id'] : null;
    $isGeneral = isset($_POST['is_general']) ? 1 : 0;
    $fileName = $_FILES['material_file']['name'];
    $fileTmp = $_FILES['material_file']['tmp_name'];
    $fileSize = $_FILES['material_file']['size'];
    $fileError = $_FILES['material_file']['error'];
    $fileType = $_FILES['material_file']['type'];
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Allowed file types
    $allowed = [
        'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odp', 'ods', 'md',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'ico', 'avif', 'psd', 'ai', 'eps',
        'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'dmg', 'iso', 'img', 'bin', 'cue',
        'mp3', 'wav', 'aac', 'm4a', 'ogg', 'flac', 'amr', 'mid', 'midi', 'wma',
        'mp4', 'mkv', 'mov', 'avi', 'webm', 'm4v', '3gp', 'flv', 'wmv', 'ogv', '3g2',
        'html', 'htm', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h', 'cs', 'rb', 'go', 'swift', 'kt',
        'ttf', 'otf', 'woff', 'woff2', 'eot'
    ];
    
    if (in_array($fileExt, $allowed)) {
        if ($fileError === 0) {
            if ($fileSize < 100000000) { // 100MB limit
                $fileNewName = uniqid('', true) . '.' . $fileExt;
                $fileDestination = $uploadDir . $fileNewName;
                
                if (move_uploaded_file($fileTmp, $fileDestination)) {
                    // If it's general material, set event_id to NULL
                    if ($isGeneral) {
                        $event_id = null;
                    }
                    
                    // Save to database
                    $stmt = $conn->prepare("INSERT INTO course_materials (course_code, event_id, file_name, file_path, file_type, file_size, is_general) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sisssii", $course_code, $event_id, $fileName, $fileDestination, $fileType, $fileSize, $isGeneral);
                    
                    if ($stmt->execute()) {
                        $uploadStatus = '<div class="alert alert-success">File uploaded successfully!</div>';
                    } else {
                        $uploadStatus = '<div class="alert alert-error">Database error: ' . $stmt->error . '</div>';
                        // Clean up file if DB insert fails
                        if (file_exists($fileDestination)) {
                            unlink($fileDestination);
                        }
                    }
                    $stmt->close();
                } else {
                    $uploadStatus = '<div class="alert alert-error">Error uploading file! Please check directory permissions.</div>';
                }
            } else {
                $uploadStatus = '<div class="alert alert-error">File is too large! Max size is 100MB.</div>';
            }
        } else {
            $uploadStatus = '<div class="alert alert-error">Error uploading file! Code: ' . $fileError . '</div>';
        }
    } else {
        $uploadStatus = '<div class="alert alert-error">Invalid file type! Allowed formats include documents, images, archives, audio, video, and more.</div>';
    }
}

// Handle file deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $material_id = $_GET['id'];
    
    // Get file path
    $stmt = $conn->prepare("SELECT file_path FROM course_materials WHERE material_id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileData = $result->fetch_assoc();
    $stmt->close();
    
    if ($fileData) {
        $filePath = $fileData['file_path'];
        
        // Delete file from server
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                $uploadStatus = '<div class="alert alert-error">Error deleting file from server!</div>';
            }
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $stmt->bind_param("i", $material_id);
        if ($stmt->execute()) {
            $uploadStatus = '<div class="alert alert-success">File deleted successfully!</div>';
        } else {
            $uploadStatus = '<div class="alert alert-error">Error deleting file from database!</div>';
        }
        $stmt->close();
    } else {
        $uploadStatus = '<div class="alert alert-error">File not found!</div>';
    }
}

// Fetch distinct courses
$courses = [];
$courses_query = "SELECT DISTINCT course_code, course_name 
                 FROM training_events 
                 ORDER BY course_name";
if ($result = $conn->query($courses_query)) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    $result->free();
}

// Initialize variables
$selected_course = null;
$course_events = [];
$materials = [];
$hasMaterials = false;
$show_general = false;

if (isset($_GET['course_code'])) {
    $course_code = $_GET['course_code'];
    
    // Get course details
    $stmt = $conn->prepare("SELECT course_code, course_name 
                           FROM training_events 
                           WHERE course_code = ? 
                           GROUP BY course_code");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_course = $result->fetch_assoc();
    $stmt->close();
    
    if ($selected_course) {
        // Fetch events for this course
        $stmt = $conn->prepare("SELECT * 
                               FROM training_events 
                               WHERE course_code = ? 
                               ORDER BY start_date DESC");
        $stmt->bind_param("s", $course_code);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $course_events[] = $row;
        }
        $stmt->close();
        
        // Check if we're filtering by event
        if (isset($_GET['event_id'])) {
            $event_id = $_GET['event_id'];
            
            // Fetch materials for this event
            $stmt = $conn->prepare("SELECT * 
                                   FROM course_materials 
                                   WHERE event_id = ? 
                                   ORDER BY uploaded_at DESC");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $materials[] = $row;
            }
            $hasMaterials = count($materials) > 0;
            $stmt->close();
        }
        // Check if we're showing general materials
        elseif (isset($_GET['show_general'])) {
            $show_general = true;
            
            // Fetch general materials for this course
            $stmt = $conn->prepare("SELECT * 
                                   FROM course_materials 
                                   WHERE course_code = ? AND is_general = 1
                                   ORDER BY uploaded_at DESC");
            $stmt->bind_param("s", $course_code);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $materials[] = $row;
            }
            $hasMaterials = count($materials) > 0;
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Training Materials | NCRB</title>
    
    <!-- Favicon Links -->
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .dashboard-container {
            background-color: #fff;
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .dashboard-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #003366 0%, #0066cc 100%);
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
        
        /* Materials Grid */
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .material-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: var(--transition);
            display: flex;
            padding: 20px;
        }

        .material-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .material-icon {
            width: 60px;
            height: 60px;
            background: #e0f2fe;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .material-icon i {
            font-size: 28px;
            color: #0369a1;
        }

        .material-details {
            flex: 1;
        }

        .material-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
            word-break: break-word;
        }

        .material-meta {
            display: flex;
            gap: 10px;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .material-type {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 15px;
        }

        .material-actions {
            display: flex;
            gap: 10px;
        }

        .download-btn, .delete-btn {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .download-btn {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .download-btn:hover {
            background: #bae6fd;
        }

        .delete-btn {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .delete-btn:hover {
            background: #fecaca;
        }

        .no-materials {
            text-align: center;
            padding: 40px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
            margin-top: 20px;
        }

        .no-materials i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .no-materials h3 {
            color: #475569;
            margin-bottom: 10px;
        }

        .no-materials p {
            color: #64748b;
            max-width: 400px;
            margin: 0 auto;
        }

        .upload-form {
            margin-top: 40px;
            padding: 30px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .file-upload {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: var(--transition);
            margin-bottom: 20px;
            background: white;
        }

        .file-upload:hover, .file-upload.dragover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }

        .file-upload-label {
            cursor: pointer;
        }

        .file-upload-label i {
            font-size: 48px;
            color: #94a3b8;
            margin-bottom: 15px;
        }

        .file-upload-label span {
            font-weight: 600;
            color: #334155;
            display: block;
            margin-bottom: 5px;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(5, 150, 105, 0.25);
        }

        .material-count {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .course-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #003366;
            transition: var(--transition);
            position: relative;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Events List */
        .events-container {
            margin-top: 30px;
        }

        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .event-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0066cc;
            transition: var(--transition);
            position: relative;
        }
        
        .event-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .event-dates {
            font-size: 14px;
            color: #64748b;
            margin: 8px 0;
        }
        
        .event-location {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 15px;
        }
        
        .general-materials-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #ff9900;
            transition: var(--transition);
            position: relative;
            margin-bottom: 30px;
        }
        
        .general-materials-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 12px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        
        .action-btn i {
            font-size: 18px;
        }
        
        .dashboard-btn {
            background: linear-gradient(135deg, #003366 0%, #0066cc 100%);
            color: white;
        }
        
        .dashboard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 102, 204, 0.25);
        }
        
        .upload-btn-container {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }
        
        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border-radius: 8px;
            background: #f1f5f9;
            margin-bottom: 10px;
        }
        
        .filter-option label {
            font-weight: 500;
            color: #334155;
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

        /* Developer Link Styling - Normalized */
        .copyright a#developer-contact-link {
            font-weight: normal;
            color: inherit;
            text-decoration: none;
            transition: var(--transition);
        }

        .copyright a#developer-contact-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }
        
        /* Modal Styles */
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
            
            .admin-info-container {
                margin-top: 10px;
                margin-left: 0;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
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
            
            .events-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .material-actions {
                flex-direction: column;
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
                <div class="nav-title">National Crime Records Bureau - Training Materials</div>
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

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1><?= $selected_course ? 'Course Materials' : 'Training Materials' ?></h1>
                <p><?= $selected_course ? 'Study materials for selected course' : 'Select a course to manage materials' ?></p>
            </div>
            
            <?php if (!empty($uploadStatus)) echo $uploadStatus; ?>
            
            <?php if ($selected_course): ?>
                <!-- Selected Course Details -->
                <div class="course-details text-center mb-4">
                    <h2><?= htmlspecialchars($selected_course['course_name']) ?></h2>
                    <?php if (!empty($selected_course['course_code'])): ?>
                        <p class="course-meta">
                            <strong>Code:</strong> <?= htmlspecialchars($selected_course['course_code']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Events and General Materials Section -->
                <div class="events-container">
                    <div class="events-header">
                        <h3 style="color: #1e293b;">Available Events & Materials</h3>
                    </div>
                    
                    <!-- General Materials Card -->
                    <div class="general-materials-card">
                        <h4 style="margin-top: 0; color: #ff9900;">
                            <i class="fas fa-book"></i> General Course Materials
                        </h4>
                        <p>Materials applicable to all instances of this course</p>
                        <a href="admin-materials.php?course_code=<?= $selected_course['course_code'] ?>&show_general=1" class="dashboard-btn" style="display: inline-block; text-decoration: none; margin-top: 10px; padding: 10px 20px;">
                            <i class="fas fa-folder-open"></i> Manage General Materials
                        </a>
                    </div>
                    
                    <!-- Events Grid -->
                    <?php if (count($course_events) > 0): ?>
                        <h4 style="margin-top: 20px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                            Course Events
                        </h4>
                        
                        <div class="events-grid">
                            <?php foreach ($course_events as $event): 
                                // Count materials for this event
                                $material_count = 0;
                                $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM course_materials WHERE event_id = ?");
                                if ($stmt) {
                                    $stmt->bind_param("i", $event['id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result) {
                                        $row = $result->fetch_assoc();
                                        $material_count = $row ? $row['count'] : 0;
                                    }
                                    $stmt->close();
                                }
                            ?>
                                <div class="event-card">
                                    <h5 style="margin-top: 0; color: #0066cc;"><?= htmlspecialchars($event['course_name']) ?></h5>
                                    <div class="event-dates">
                                        <i class="fas fa-calendar"></i> 
                                        <?= date('M j, Y', strtotime($event['start_date'])) ?> - 
                                        <?= date('M j, Y', strtotime($event['end_date'])) ?>
                                    </div>
                                    <?php if (!empty($event['location'])): ?>
                                        <div class="event-location">
                                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="position: absolute; top: 15px; right: 15px; background: #e0f2fe; color: #0369a1; border-radius: 20px; padding: 4px 12px; font-size: 14px; font-weight: 500;">
                                        <?= $material_count ?> file<?= $material_count != 1 ? 's' : '' ?>
                                    </div>
                                    <a href="admin-materials.php?course_code=<?= $selected_course['course_code'] ?>&event_id=<?= $event['id'] ?>" class="dashboard-btn" style="display: inline-block; text-decoration: none; margin-top: 10px; padding: 10px 20px;">
                                        <i class="fas fa-book"></i> Manage Materials
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-materials">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Events Scheduled</h3>
                            <p>This course doesn't have any scheduled events yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Materials Section (if event or general is selected) -->
                <?php if (isset($_GET['event_id']) || isset($_GET['show_general'])): ?>
                    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <h3 style="color: #1e293b; margin-bottom: 25px;">
                            <?php if (isset($_GET['event_id'])): ?>
                                <i class="fas fa-calendar"></i> Materials for Selected Event
                            <?php else: ?>
                                <i class="fas fa-book"></i> General Course Materials
                            <?php endif; ?>
                        </h3>
                        
                        <?php if ($hasMaterials): ?>
                            <div class="materials-grid">
                                <?php foreach ($materials as $material): 
                                    $fileExt = pathinfo($material['file_name'], PATHINFO_EXTENSION);
                                    $icon = 'fa-file';
                                    
                                    // Determine icon based on file type
                                    if (in_array($fileExt, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odp', 'ods', 'md', 'dot', 'dotx', 'xlsm', 'ppsx', 'tex'])) {
                                        $icon = 'fa-file-alt';
                                    } elseif (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'svg', 'ico', 'avif', 'psd', 'ai', 'eps', 'cr2', 'nef', 'raw', 'arw', 'dng'])) {
                                        $icon = 'fa-file-image';
                                    } elseif (in_array($fileExt, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'dmg', 'iso', 'img', 'bin', 'cue'])) {
                                        $icon = 'fa-file-archive';
                                    } elseif (in_array($fileExt, ['mp3', 'wav', 'aac', 'm4a', 'ogg', 'flac', 'amr', 'mid', 'midi', 'wma'])) {
                                        $icon = 'fa-file-audio';
                                    } elseif (in_array($fileExt, ['mp4', 'mkv', 'mov', 'avi', 'webm', 'm4v', '3gp', 'flv', 'wmv', 'ogv', '3g2'])) {
                                        $icon = 'fa-file-video';
                                    } elseif (in_array($fileExt, ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'h', 'cs', 'rb', 'go', 'swift', 'kt', 'ts', 'sh', 'bat', 'json', 'xml', 'yml', 'yaml', 'sql', 'vb', 'asm', 'ini', 'conf', 'env', 'toml', 'ipynb', 'class', 'jar', 'csproj', 'kts', 'tsx', 'bash', 'zsh', 'csh', 'cmd', 'frm', 'ibd', 'mdb', 'accdb', 'ora', 'dmp', 'dbf', 'log', 'nfo', 'ps1', 'wsf', 'vhd', 'vmdk', 'dockerfile', 'db', 'sqlite', 'db3', 'adoc', 'rst', 'org', 'make', 'mk', 'cfg', 'config', 'patch', 'diff'])) {
                                        $icon = 'fa-file-code';
                                    } elseif (in_array($fileExt, ['ttf', 'otf', 'woff', 'woff2', 'eot'])) {
                                        $icon = 'fa-font';
                                    }
                                ?>
                                    <div class="material-card">
                                        <div class="material-icon">
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div class="material-details">
                                            <div class="material-title"><?= htmlspecialchars($material['file_name']) ?></div>
                                            <div class="material-meta">
                                                <span><?= htmlspecialchars(strtoupper($fileExt)) ?> file</span>
                                                <span><?= round($material['file_size'] / 1024) ?> KB</span>
                                            </div>
                                            <div class="material-type"><?= htmlspecialchars($material['file_type']) ?></div>
                                            <div class="material-actions">
                                                <a href="<?= $material['file_path'] ?>" download class="download-btn">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                                <button class="delete-btn" onclick="deleteMaterial(<?= $material['material_id'] ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-materials">
                                <i class="fas fa-folder-open"></i>
                                <h3>No Materials Available</h3>
                                <p>Upload study materials using the form below.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upload Form -->
                        <div class="upload-form">
                            <h3 style="margin-top: 0; margin-bottom: 25px; color: #1e293b;">Upload New Material</h3>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="course_code" value="<?= $selected_course['course_code'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <?php if (isset($_GET['show_general'])): ?>
                                    <input type="hidden" name="is_general" value="1">
                                <?php else: ?>
                                    <input type="hidden" name="event_id" value="<?= isset($_GET['event_id']) ? $_GET['event_id'] : '' ?>">
                                    <div class="filter-option">
                                        <i class="fas fa-book" style="color: #ff9900;"></i>
                                        <label>
                                            <input type="checkbox" name="is_general"> 
                                            Mark as General Course Material (applies to all events)
                                        </label>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (count($course_events) > 0): ?>
                                    <div class="form-group">
                                        <label for="materialFile">Select File</label>
                                        <div class="file-upload">
                                            <label class="file-upload-label" for="materialFile">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <span>Drag & drop files here or click to browse</span>
                                                <p style="margin-top: 10px; color: #64748b; font-size: 13px;">
                                                    Max file size: 100MB • Allowed formats: Documents, Images, Archives, Audio, Video, and more
                                                </p>
                                            </label>
                                            <input type="file" name="material_file" id="materialFile" class="file-input" required>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-btn-container">
                                        <button type="submit" class="action-btn upload-btn">
                                            <i class="fas fa-upload"></i> Upload Material
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-error">No events found for this course. You cannot upload materials until an event is created.</div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="admin-materials.php" class="action-btn dashboard-btn">
                        <i class="fas fa-list"></i> All Courses
                    </a>
                    <a href="admin-dashboard.php" class="action-btn dashboard-btn">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Courses Listing -->
                <?php if (count($courses) > 0): ?>
                    <div class="courses-grid">
                        <?php foreach ($courses as $course): 
                            // Count total materials for this course
                            $material_count = 0;
                            $stmt = $conn->prepare("SELECT COUNT(*) AS count 
                                                  FROM course_materials 
                                                  WHERE course_code = ?");
                            if ($stmt) {
                                $stmt->bind_param("s", $course['course_code']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result) {
                                    $row = $result->fetch_assoc();
                                    $material_count = $row ? $row['count'] : 0;
                                }
                                $stmt->close();
                            }
                        ?>
                            <div class="course-card">
                                <div style="position: absolute; top: 15px; right: 15px; background: #e0f2fe; color: #0369a1; border-radius: 20px; padding: 4px 12px; font-size: 14px; font-weight: 500;">
                                    <?= $material_count ?> file<?= $material_count != 1 ? 's' : '' ?>
                                </div>
                                <h3 style="margin-top: 0; color: #003366; padding-right: 70px;"><?= htmlspecialchars($course['course_name']) ?></h3>
                                <?php if (!empty($course['course_code'])): ?>
                                    <p style="color: #666; font-size: 14px; margin: 10px 0;">
                                        <strong>Code:</strong> <?= htmlspecialchars($course['course_code']) ?>
                                    </p>
                                <?php endif; ?>
                                <a href="admin-materials.php?course_code=<?= $course['course_code'] ?>" class="dashboard-btn" style="display: inline-block; text-decoration: none; margin-top: 15px; padding: 10px 20px;">
                                    <i class="fas fa-book"></i> Manage Materials
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-materials">
                        <i class="fas fa-book"></i>
                        <h3>No Training Courses Available</h3>
                        <p>No training courses are currently available for materials management.</p>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="admin-dashboard.php" class="action-btn dashboard-btn">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
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
        function deleteMaterial(materialId) {
            if (confirm('Are you sure you want to delete this material? This action cannot be undone.')) {
                let url = `admin-materials.php?action=delete&id=${materialId}`;
                
                <?php if (isset($_GET['course_code'])): ?>
                    url += `&course_code=<?= $_GET['course_code'] ?>`;
                <?php endif; ?>
                
                <?php if (isset($_GET['event_id'])): ?>
                    url += `&event_id=<?= $_GET['event_id'] ?>`;
                <?php elseif (isset($_GET['show_general'])): ?>
                    url += `&show_general=1`;
                <?php endif; ?>
                
                window.location.href = url;
            }
        }
        
        // File upload label update
        document.getElementById('materialFile')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file selected';
            const label = document.querySelector('.file-upload-label span');
            if (label) label.textContent = fileName;
        });
        
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

        // Modal functionality
        const developerLink = document.getElementById('developer-contact-link');
        const modal = document.getElementById('developerContactModal');
        const closeModal = document.querySelector('.close-modal');

        if (developerLink) {
            developerLink.addEventListener('click', function(e) {
                e.preventDefault();
                modal.style.display = 'flex';
            });
        }

        if (closeModal) {
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>