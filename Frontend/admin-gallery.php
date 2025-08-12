<?php
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

// Create required tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS program_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT(11) DEFAULT NULL,
        course_code VARCHAR(50) DEFAULT NULL,
        image_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255) NOT NULL DEFAULT '',
        use_for ENUM('home','course') NOT NULL DEFAULT 'course',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS program_videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT(11) DEFAULT NULL,
        course_code VARCHAR(50) DEFAULT NULL,
        video_path VARCHAR(255) NOT NULL,
        caption VARCHAR(255) NOT NULL DEFAULT '',
        use_for ENUM('home','course') NOT NULL DEFAULT 'course',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS training_events (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
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
        reminders_enabled BOOLEAN DEFAULT 1
    )",
    
    "CREATE TABLE IF NOT EXISTS admin_credentials (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_username VARCHAR(255) NOT NULL,
        admin_password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($createTables as $sql) {
    if (!$conn->query($sql)) {
        // Continue execution even if table exists
    }
}

// Add missing columns if needed
$alterColumns = [
    "ALTER TABLE program_images ADD COLUMN IF NOT EXISTS event_id INT(11) DEFAULT NULL",
    "ALTER TABLE program_images ADD COLUMN IF NOT EXISTS use_for ENUM('home','course') NOT NULL DEFAULT 'course'",
    "ALTER TABLE program_images ADD COLUMN IF NOT EXISTS course_code VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE program_videos ADD COLUMN IF NOT EXISTS event_id INT(11) DEFAULT NULL",
    "ALTER TABLE program_videos ADD COLUMN IF NOT EXISTS use_for ENUM('home','course') NOT NULL DEFAULT 'course'",
    "ALTER TABLE program_videos ADD COLUMN IF NOT EXISTS course_code VARCHAR(50) DEFAULT NULL"
];

foreach ($alterColumns as $sql) {
    if (!$conn->query($sql)) {
        // Continue even if column exists
    }
}

// Process deletion
if (isset($_GET['delete_id']) && isset($_GET['type'])) {
    $delete_id = intval($_GET['delete_id']);
    $type = $_GET['type'];
    
    if ($type === 'image') {
        $table = 'program_images';
        $path_col = 'image_path';
    } elseif ($type === 'video') {
        $table = 'program_videos';
        $path_col = 'video_path';
    } else {
        $_SESSION['delete_error'] = "Invalid media type";
        header("Location: admin-gallery.php");
        exit;
    }
    
    // Get media path
    $stmt = $conn->prepare("SELECT $path_col FROM $table WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $media_path = $row[$path_col];
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            // Delete file
            if (file_exists($media_path)) {
                unlink($media_path);
            }
            $_SESSION['delete_success'] = true;
        } else {
            $_SESSION['delete_error'] = "Failed to delete from database.";
        }
    } else {
        $_SESSION['delete_error'] = "Media not found.";
    }
    header("Location: admin-gallery.php");
    exit;
}

// Process uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine media type (image or video)
    $media_type = $_POST['media_type'];
    $use_for = isset($_POST['use_for']) ? $_POST['use_for'] : '';
    $event_id = null;
    $course_code = null;
    
    // Validate image usage selection
    if ($use_for === 'course') {
        // Handle course-specific media
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
        $course_code = isset($_POST['course_code']) ? $_POST['course_code'] : null;
        
        // If "General" is selected, event_id remains null
        if ($event_id === -1) {
            $event_id = null;
        }
        // If a specific event is selected, validate it
        elseif ($event_id > 0) {
            // Verify event exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM training_events WHERE id = ?");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            
            if ($count == 0) {
                $_SESSION['upload_error'] = "Invalid event selected.";
                header("Location: admin-gallery.php");
                exit;
            }
        }
    } elseif ($use_for === 'home') {
        // Home page media don't need event ID or course code
    } else {
        $_SESSION['upload_error'] = "Please select where to use the media.";
        header("Location: admin-gallery.php");
        exit;
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if ($media_type === 'image') {
        $fileField = 'images';
        $subDir = 'images/';
        $validExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $maxSize = 25 * 1024 * 1024; // 25MB
    } else { // video
        $fileField = 'videos';
        $subDir = 'videos/';
        $validExtensions = ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $maxSize = 100 * 1024 * 1024; // 100MB
    }
    
    $fullUploadDir = $uploadDir . $subDir;
    if (!is_dir($fullUploadDir)) {
        mkdir($fullUploadDir, 0777, true);
    }
    
    $uploadedFiles = [];
    $errors = [];
    $captions = $_POST['captions'] ?? [];
    
    foreach ($_FILES[$fileField]['tmp_name'] as $key => $tmp_name) {
        $fileName = $_FILES[$fileField]['name'][$key];
        $fileSize = $_FILES[$fileField]['size'][$key];
        $fileTmp = $_FILES[$fileField]['tmp_name'][$key];
        $fileType = $_FILES[$fileField]['type'][$key];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $caption = $captions[$key] ?? '';
        
        // Validate file
        if (!in_array($fileExt, $validExtensions)) {
            $errors[] = "Invalid file extension for $fileName. Only " . 
                        implode(', ', $validExtensions) . " allowed.";
            continue;
        }
        
        if ($fileSize > $maxSize) {
            $sizeMB = round($maxSize / (1024 * 1024));
            $errors[] = "File $fileName exceeds maximum size ($sizeMB MB)";
            continue;
        }
        
        // Generate unique filename
        $newFileName = uniqid('media_', true) . '.' . $fileExt;
        $destination = $fullUploadDir . $newFileName;
        
        if (move_uploaded_file($fileTmp, $destination)) {
            // Insert into database with caption and event_id
            $table = ($media_type === 'image') ? 'program_images' : 'program_videos';
            $column = ($media_type === 'image') ? 'image_path' : 'video_path';
            
            $stmt = $conn->prepare("INSERT INTO $table (event_id, course_code, $column, caption, use_for) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $event_id, $course_code, $destination, $caption, $use_for);
            if ($stmt->execute()) {
                $uploadedFiles[] = $fileName;
            } else {
                $errors[] = "Database error for $fileName: " . $stmt->error;
                unlink($destination); // Remove file if DB insert failed
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to upload $fileName";
        }
    }
    
    if (!empty($uploadedFiles)) {
        $_SESSION['upload_success'] = count($uploadedFiles) . " files uploaded successfully!";
    }
    
    if (!empty($errors)) {
        $_SESSION['upload_error'] = implode("<br>", $errors);
    }
    
    header("Location: admin-gallery.php");
    exit;
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

// Fetch distinct courses with events
$courses = [];
$coursesResult = $conn->query("SELECT DISTINCT course_code, course_name FROM training_events ORDER BY course_name ASC");

if ($coursesResult && $coursesResult->num_rows > 0) {
    while ($course = $coursesResult->fetch_assoc()) {
        // Fetch events for each course
        $courseCode = $course['course_code'];
        $eventsStmt = $conn->prepare("SELECT id, start_date, end_date FROM training_events WHERE course_code = ? ORDER BY start_date DESC ");
        $eventsStmt->bind_param("s", $courseCode);
        $eventsStmt->execute();
        $eventsResult = $eventsStmt->get_result();
        $events = [];
        if ($eventsResult) {
            while ($event = $eventsResult->fetch_assoc()) {
                $events[] = $event;
            }
        }
        $course['events'] = $events;
        $courses[] = $course;
        $eventsStmt->close();
    }
}

// Fetch existing images with event details
$images = [];
$imagesQuery = "SELECT pi.*, te.course_code, te.course_name, te.start_date, te.end_date 
               FROM program_images pi 
               LEFT JOIN training_events te ON pi.event_id = te.id 
               ORDER BY pi.uploaded_at DESC";
$imagesResult = $conn->query($imagesQuery);
if ($imagesResult && $imagesResult->num_rows > 0) {
    while ($row = $imagesResult->fetch_assoc()) {
        $images[] = $row;
    }
}

// Fetch existing videos with event details
$videos = [];
$videosQuery = "SELECT pv.*, te.course_code, te.course_name, te.start_date, te.end_date 
               FROM program_videos pv 
               LEFT JOIN training_events te ON pv.event_id = te.id 
               ORDER BY pv.uploaded_at DESC";
$videosResult = $conn->query($videosQuery);
if ($videosResult && $videosResult->num_rows > 0) {
    while ($row = $videosResult->fetch_assoc()) {
        $videos[] = $row;
    }
}

// Check for session messages
$uploadSuccess = isset($_SESSION['upload_success']);
$uploadError = isset($_SESSION['upload_error']);
$deleteSuccess = isset($_SESSION['delete_success']);
$deleteError = isset($_SESSION['delete_error']);

// Clear session messages
if ($uploadSuccess) {
    $successMessage = $_SESSION['upload_success'];
    unset($_SESSION['upload_success']);
}
if ($uploadError) {
    $errorMessage = $_SESSION['upload_error'];
    unset($_SESSION['upload_error']);
}
if ($deleteSuccess) {
    unset($_SESSION['delete_success']);
}
if ($deleteError) {
    $deleteErrorMessage = $_SESSION['delete_error'];
    unset($_SESSION['delete_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Training Gallery - Admin Dashboard</title>
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
            padding-top: 200px;
            background-color: #f5f9fc;
        }

        /* Animation Keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
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

        /* Gallery Container */
        .gallery-container {
            background: var(--white);
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            margin: 20px auto;
            position: relative;
            overflow: hidden;
        }
        
        .gallery-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 20px;
            text-decoration: none;
            transition: all 0.2s;
            padding: 8px 15px;
            border-radius: 8px;
            background: rgba(0, 102, 204, 0.1);
        }
        
        .back-btn:hover {
            color: var(--primary);
            background: rgba(0, 102, 204, 0.2);
            transform: translateX(-3px);
        }
        
        /* Upload Section */
        .upload-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 40px;
            border: 1px dashed #cbd5e1;
            transition: all 0.3s ease;
        }
        
        .upload-section:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }
        
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            background: #fff;
        }
        
        .upload-area:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #94a3b8;
            margin-bottom: 15px;
        }
        
        .upload-text {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .file-input {
            display: none;
        }
        
        .upload-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.2);
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 102, 204, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }
        
        .form-control {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #94a3b8;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            border: none;
            padding: 14px 35px;
            font-size: 17px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            display: block;
            margin: 25px auto 10px;
            box-shadow: 0 6px 15px rgba(2, 132, 199, 0.25);
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(2, 132, 199, 0.35);
        }
        
        /* Gallery Section */
        .gallery-section {
            margin-top: 40px;
        }
        
        .section-title {
            font-size: 26px;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 120px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .gallery-item {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            background: #fff;
            animation: fadeIn 0.5s ease-out;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .gallery-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid #ccefff;
        }
        
        .gallery-video {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid #ccefff;
        }
        
        .gallery-caption {
            padding: 18px 15px;
            font-size: 16px;
            color: #334155;
            line-height: 1.5;
            min-height: 70px;
        }
        
        .gallery-actions {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        
        .delete-btn {
            background: #fee2e2;
            color: #b91c1c;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .delete-btn:hover {
            background: #fecaca;
        }
        
        .date-info {
            color: #64748b;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }
        
        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .error-message {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .caption-input {
            margin-top: 10px;
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .caption-input:focus {
            outline: none;
            border-color: #94a3b8;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.2);
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
        
        /* ===== RESPONSIVE DESIGN ===== */
        @media (max-width: 992px) {
            body {
                padding-top: 190px;
            }
            
            .dashboard-header h1 {
                font-size: 2.2rem;
            }
            
            .gallery-container {
                padding: 30px;
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
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .upload-area {
                padding: 20px;
            }
            
            .upload-text {
                font-size: 16px;
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
            
            .gallery-container {
                padding: 25px 15px;
            }
            
            .upload-section {
                padding: 20px 15px;
            }
            
            .gallery-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* New styles for event info */
        .gallery-event {
            padding: 10px 15px;
            background: #e6f7ff;
            border-bottom: 1px solid #ccefff;
            font-size: 14px;
            color: #0066cc;
        }
        
        .gallery-event .event-name {
            font-weight: 600;
            display: block;
        }
        
        .gallery-event .event-date {
            font-size: 13px;
            color: #4d94ff;
            display: block;
        }
        
        .no-event {
            color: #666;
            font-style: italic;
        }
        
        .gallery-caption {
            min-height: 60px;
        }
        
        .event-select-container {
            background: #f0f9ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #cce5ff;
            display: none;
        }
        
        .event-select-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #0056b3;
        }
        
        .event-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #99c2ff;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            transition: all 0.3s;
        }
        
        .event-select:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
        }
        
        /* New styles for image usage selection */
        .use-for-container {
            background: #f0f9ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #cce5ff;
        }
        
        .use-for-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #0056b3;
        }
        
        .use-for-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #99c2ff;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            transition: all 0.3s;
        }
        
        .use-for-select:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
        }
        
        .course-select-container {
            display: none;
            background: #f0f9ff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            border: 1px solid #cce5ff;
        }
        
        .image-usage-info {
            font-size: 14px;
            color: #4b5563;
            margin-top: 8px;
            font-style: italic;
        }
        
        /* Gallery item type indicators */
        .image-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 10;
        }
        
        .type-home {
            background: #10b981;
            color: white;
        }
        
        .type-course {
            background: #3b82f6;
            color: white;
        }
        
        /* Two-level dropdown styling */
        .course-event-container {
            display: flex;
            gap: 15px;
        }
        
        .course-select, .event-select-container {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .course-event-container {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        /* Tabs for switching between images and videos */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            background: transparent;
            border: none;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab.active {
            color: var(--primary);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }
        
        .tab:hover:not(.active) {
            color: var(--secondary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
                <div class="nav-title">National Crime Records Bureau - Admin Dashboard</div>
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
            <h1>Training Gallery Management</h1>
            <p>Upload and manage images and videos for specific training programs and events</p>
        </div>
        
        <div class="gallery-container">
            <a href="admin-dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <!-- Upload Messages -->
            <?php if ($uploadSuccess): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($uploadError): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($deleteSuccess): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i> Media deleted successfully!
                </div>
            <?php endif; ?>
            
            <?php if ($deleteError): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $deleteErrorMessage; ?>
                </div>
            <?php endif; ?>
            
            <!-- Tabs for Images and Videos -->
            <div class="tabs">
                <button class="tab active" data-tab="images-tab">Images</button>
                <button class="tab" data-tab="videos-tab">Videos</button>
            </div>
            
            <!-- Images Tab -->
            <div id="images-tab" class="tab-content active">
                <!-- Upload Section for Images -->
                <div class="upload-section">
                    <h2 class="section-title">Upload New Images</h2>
                    <form action="admin-gallery.php" method="post" enctype="multipart/form-data" id="uploadImageForm">
                        <input type="hidden" name="media_type" value="image">
                        <input type="hidden" name="course_code" id="imageCourseCode" value="">
                        
                        <!-- Image Usage Selection -->
                        <div class="use-for-container">
                            <label class="use-for-label">
                                <i class="fas fa-tag"></i> Image Usage
                            </label>
                            <select name="use_for" id="useForSelect" class="use-for-select" required>
                                <option value="">-- Select Image Usage --</option>
                                <option value="home">Home Page</option>
                                <option value="course">Course Gallery</option>
                            </select>
                            <div class="image-usage-info">
                                <span id="usageInfoText">Select where these images will be displayed</span>
                            </div>
                            
                            <!-- Course Selection (only shown when course is selected) -->
                            <div id="courseSelectContainer" class="course-select-container">
                                <label class="form-label">
                                    <i class="fas fa-book"></i> Select Course
                                </label>
                                <select id="courseSelect" class="form-control">
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Event Selection (only shown when course is selected) -->
                            <div id="eventSelectContainer" class="event-select-container">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i> Select Training Period
                                </label>
                                <select name="event_id" id="eventSelect" class="form-control">
                                    <option value="-1">General (No specific event)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="upload-area" id="imageDropArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Drag & drop images here or click to browse
                            </div>
                            <button type="button" class="upload-btn" id="browseImagesBtn">
                                <i class="fas fa-folder-open"></i> Select Images
                            </button>
                            <input type="file" name="images[]" id="imageFileInput" class="file-input" multiple accept="image/*">
                        </div>
                        
                        <div id="imageFilePreview"></div>
                        
                        <div class="text-center" style="margin-top: 30px;">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-upload"></i> Upload Images
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Gallery Section for Images -->
                <div class="gallery-section">
                    <h2 class="section-title">Existing Training Images</h2>
                    
                    <?php if (empty($images)): ?>
                        <div class="message" style="background: #f0f9ff; color: #0c4a6e; padding: 25px;">
                            <i class="fas fa-images" style="font-size: 24px; display: block; margin-bottom: 15px;"></i>
                            No images found in the gallery. Upload some images to showcase your training programs.
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid" id="galleryGrid">
                            <?php foreach ($images as $image): ?>
                                <div class="gallery-item">
                                    <!-- Image type badge -->
                                    <div class="image-type-badge <?php echo $image['use_for'] === 'home' ? 'type-home' : 'type-course'; ?>">
                                        <?php echo $image['use_for'] === 'home' ? 'Home Page' : 'Course'; ?>
                                    </div>
                                    
                                    <img src="<?php echo $image['image_path']; ?>" alt="Program image" class="gallery-image">
                                    
                                    <!-- Event Information -->
                                    <div class="gallery-event">
                                        <?php if ($image['use_for'] === 'course'): ?>
                                            <?php if (!empty($image['course_name'])): ?>
                                                <span class="event-name">
                                                    <?php echo htmlspecialchars($image['course_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($image['start_date']) && !empty($image['end_date'])): ?>
                                                <span class="event-date">
                                                    <?php echo date('d M Y', strtotime($image['start_date'])) . ' to ' . date('d M Y', strtotime($image['end_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="no-event">General Gallery</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="event-name">Home Page Gallery</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="gallery-caption">
                                        <?php 
                                            $caption = $image['caption'];
                                            echo !empty($caption) ? htmlspecialchars($caption) : 'Training Program Image';
                                        ?>
                                    </div>
                                    <div class="gallery-actions">
                                        <div class="date-info">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($image['uploaded_at'])); ?>
                                        </div>
                                        <button class="delete-btn" onclick="deleteMedia(<?php echo $image['id']; ?>, 'image')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Videos Tab -->
            <div id="videos-tab" class="tab-content">
                <!-- Upload Section for Videos -->
                <div class="upload-section">
                    <h2 class="section-title">Upload New Videos</h2>
                    <form action="admin-gallery.php" method="post" enctype="multipart/form-data" id="uploadVideoForm">
                        <input type="hidden" name="media_type" value="video">
                        <input type="hidden" name="course_code" id="videoCourseCode" value="">
                        
                        <!-- Video Usage Selection -->
                        <div class="use-for-container">
                            <label class="use-for-label">
                                <i class="fas fa-tag"></i> Video Usage
                            </label>
                            <select name="use_for" id="useForVideoSelect" class="use-for-select" required>
                                <option value="">-- Select Video Usage --</option>
                                <option value="home">Home Page</option>
                                <option value="course">Course Gallery</option>
                            </select>
                            <div class="image-usage-info">
                                <span id="videoUsageInfoText">Select where these videos will be displayed</span>
                            </div>
                            
                            <!-- Course Selection for Videos -->
                            <div id="courseVideoSelectContainer" class="course-select-container">
                                <label class="form-label">
                                    <i class="fas fa-book"></i> Select Course
                                </label>
                                <select id="courseVideoSelect" class="form-control">
                                    <option value="">-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Event Selection for Videos -->
                            <div id="eventVideoSelectContainer" class="event-select-container">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i> Select Training Period
                                </label>
                                <select name="event_id" id="eventVideoSelect" class="form-control">
                                    <option value="-1">General (No specific event)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="upload-area" id="videoDropArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                Drag & drop videos here or click to browse
                            </div>
                            <button type="button" class="upload-btn" id="browseVideosBtn">
                                <i class="fas fa-folder-open"></i> Select Videos
                            </button>
                            <input type="file" name="videos[]" id="videoFileInput" class="file-input" multiple accept="video/*">
                        </div>
                        
                        <div id="videoFilePreview"></div>
                        
                        <div class="text-center" style="margin-top: 30px;">
                            <button type="submit" class="submit-btn">
                                <i class="fas fa-upload"></i> Upload Videos
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Gallery Section for Videos -->
                <div class="gallery-section">
                    <h2 class="section-title">Existing Training Videos</h2>
                    
                    <?php if (empty($videos)): ?>
                        <div class="message" style="background: #f0f9ff; color: #0c4a6e; padding: 25px;">
                            <i class="fas fa-video" style="font-size: 24px; display: block; margin-bottom: 15px;"></i>
                            No videos found in the gallery. Upload some videos to showcase your training programs.
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid" id="videoGalleryGrid">
                            <?php foreach ($videos as $video): ?>
                                <div class="gallery-item">
                                    <!-- Video type badge -->
                                    <div class="image-type-badge <?php echo $video['use_for'] === 'home' ? 'type-home' : 'type-course'; ?>">
                                        <?php echo $video['use_for'] === 'home' ? 'Home Page' : 'Course'; ?>
                                    </div>
                                    
                                    <video class="gallery-video" controls>
                                        <source src="<?php echo $video['video_path']; ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    
                                    <!-- Event Information -->
                                    <div class="gallery-event">
                                        <?php if ($video['use_for'] === 'course'): ?>
                                            <?php if (!empty($video['course_name'])): ?>
                                                <span class="event-name">
                                                    <?php echo htmlspecialchars($video['course_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($video['start_date']) && !empty($video['end_date'])): ?>
                                                <span class="event-date">
                                                    <?php echo date('d M Y', strtotime($video['start_date'])) . ' to ' . date('d M Y', strtotime($video['end_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="no-event">General Gallery</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="event-name">Home Page Gallery</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="gallery-caption">
                                        <?php 
                                            $caption = $video['caption'];
                                            echo !empty($caption) ? htmlspecialchars($caption) : 'Training Program Video';
                                        ?>
                                    </div>
                                    <div class="gallery-actions">
                                        <div class="date-info">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($video['uploaded_at'])); ?>
                                        </div>
                                        <button class="delete-btn" onclick="deleteMedia(<?php echo $video['id']; ?>, 'video')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
        // Preload courses data
        const coursesData = <?php echo json_encode($courses); ?>;
        
        // DOM elements
        const imageDropArea = document.getElementById('imageDropArea');
        const imageFileInput = document.getElementById('imageFileInput');
        const browseImagesBtn = document.getElementById('browseImagesBtn');
        const imageFilePreview = document.getElementById('imageFilePreview');
        const useForSelect = document.getElementById('useForSelect');
        const courseSelectContainer = document.getElementById('courseSelectContainer');
        const eventSelectContainer = document.getElementById('eventSelectContainer');
        const courseSelect = document.getElementById('courseSelect');
        const eventSelect = document.getElementById('eventSelect');
        const usageInfoText = document.getElementById('usageInfoText');
        const imageCourseCode = document.getElementById('imageCourseCode');
        
        // Video DOM elements
        const videoDropArea = document.getElementById('videoDropArea');
        const videoFileInput = document.getElementById('videoFileInput');
        const browseVideosBtn = document.getElementById('browseVideosBtn');
        const videoFilePreview = document.getElementById('videoFilePreview');
        const useForVideoSelect = document.getElementById('useForVideoSelect');
        const courseVideoSelectContainer = document.getElementById('courseVideoSelectContainer');
        const eventVideoSelectContainer = document.getElementById('eventVideoSelectContainer');
        const courseVideoSelect = document.getElementById('courseVideoSelect');
        const eventVideoSelect = document.getElementById('eventVideoSelect');
        const videoUsageInfoText = document.getElementById('videoUsageInfoText');
        const videoCourseCode = document.getElementById('videoCourseCode');
        
        // Tabs
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        // Image usage selection handler
        useForSelect.addEventListener('change', function() {
            if (this.value === 'course') {
                courseSelectContainer.style.display = 'block';
                eventSelectContainer.style.display = 'block';
                usageInfoText.textContent = 'Images will be displayed in the gallery for the selected course';
                eventSelect.required = false;
                // Reset selections
                courseSelect.value = '';
                eventSelect.innerHTML = '<option value="-1">General (No specific event)</option>';
                imageCourseCode.value = '';
            } else if (this.value === 'home') {
                courseSelectContainer.style.display = 'none';
                eventSelectContainer.style.display = 'none';
                usageInfoText.textContent = 'Images will be displayed on the home page';
                eventSelect.required = false;
                imageCourseCode.value = '';
            } else {
                courseSelectContainer.style.display = 'none';
                eventSelectContainer.style.display = 'none';
                usageInfoText.textContent = 'Select where these images will be displayed';
                eventSelect.required = false;
                imageCourseCode.value = '';
            }
        });
        
        // Video usage selection handler
        useForVideoSelect.addEventListener('change', function() {
            if (this.value === 'course') {
                courseVideoSelectContainer.style.display = 'block';
                eventVideoSelectContainer.style.display = 'block';
                videoUsageInfoText.textContent = 'Videos will be displayed in the gallery for the selected course';
                eventVideoSelect.required = false;
                // Reset selections
                courseVideoSelect.value = '';
                eventVideoSelect.innerHTML = '<option value="-1">General (No specific event)</option>';
                videoCourseCode.value = '';
            } else if (this.value === 'home') {
                courseVideoSelectContainer.style.display = 'none';
                eventVideoSelectContainer.style.display = 'none';
                videoUsageInfoText.textContent = 'Videos will be displayed on the home page';
                eventVideoSelect.required = false;
                videoCourseCode.value = '';
            } else {
                courseVideoSelectContainer.style.display = 'none';
                eventVideoSelectContainer.style.display = 'none';
                videoUsageInfoText.textContent = 'Select where these videos will be displayed';
                eventVideoSelect.required = false;
                videoCourseCode.value = '';
            }
        });
        
        // Course selection handler for images
        courseSelect.addEventListener('change', function() {
            const courseCode = this.value;
            imageCourseCode.value = courseCode;
            eventSelect.innerHTML = '<option value="-1">General (No specific event)</option>';
            
            if (courseCode) {
                // Find the selected course
                const course = coursesData.find(c => c.course_code === courseCode);
                
                if (course && course.events.length > 0) {
                    // Populate events dropdown
                    course.events.forEach(event => {
                        const option = document.createElement('option');
                        option.value = event.id;
                        const startDate = new Date(event.start_date).toLocaleDateString('en-GB', {
                            day: '2-digit', month: 'short', year: 'numeric'
                        });
                        const endDate = new Date(event.end_date).toLocaleDateString('en-GB', {
                            day: '2-digit', month: 'short', year: 'numeric'
                        });
                        option.textContent = `${startDate} to ${endDate}`;
                        eventSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Course selection handler for videos
        courseVideoSelect.addEventListener('change', function() {
            const courseCode = this.value;
            videoCourseCode.value = courseCode;
            eventVideoSelect.innerHTML = '<option value="-1">General (No specific event)</option>';
            
            if (courseCode) {
                // Find the selected course
                const course = coursesData.find(c => c.course_code === courseCode);
                
                if (course && course.events.length > 0) {
                    // Populate events dropdown
                    course.events.forEach(event => {
                        const option = document.createElement('option');
                        option.value = event.id;
                        const startDate = new Date(event.start_date).toLocaleDateString('en-GB', {
                            day: '2-digit', month: 'short', year: 'numeric'
                        });
                        const endDate = new Date(event.end_date).toLocaleDateString('en-GB', {
                            day: '2-digit', month: 'short', year: 'numeric'
                        });
                        option.textContent = `${startDate} to ${endDate}`;
                        eventVideoSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Tab switching
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
        
        // File handling for images
        browseImagesBtn.addEventListener('click', () => {
            imageFileInput.click();
        });
        
        imageDropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageDropArea.style.borderColor = '#94a3b8';
            imageDropArea.style.backgroundColor = '#f8fafc';
        });
        
        imageDropArea.addEventListener('dragleave', () => {
            imageDropArea.style.borderColor = '#cbd5e1';
            imageDropArea.style.backgroundColor = '#fff';
        });
        
        imageDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            imageDropArea.style.borderColor = '#cbd5e1';
            imageDropArea.style.backgroundColor = '#fff';
            
            if (e.dataTransfer.files.length) {
                imageFileInput.files = e.dataTransfer.files;
                handleFiles(imageFileInput.files, 'image');
            }
        });
        
        imageFileInput.addEventListener('change', () => {
            if (imageFileInput.files.length) {
                handleFiles(imageFileInput.files, 'image');
            }
        });
        
        // File handling for videos
        browseVideosBtn.addEventListener('click', () => {
            videoFileInput.click();
        });
        
        videoDropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            videoDropArea.style.borderColor = '#94a3b8';
            videoDropArea.style.backgroundColor = '#f8fafc';
        });
        
        videoDropArea.addEventListener('dragleave', () => {
            videoDropArea.style.borderColor = '#cbd5e1';
            videoDropArea.style.backgroundColor = '#fff';
        });
        
        videoDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            videoDropArea.style.borderColor = '#cbd5e1';
            videoDropArea.style.backgroundColor = '#fff';
            
            if (e.dataTransfer.files.length) {
                videoFileInput.files = e.dataTransfer.files;
                handleFiles(videoFileInput.files, 'video');
            }
        });
        
        videoFileInput.addEventListener('change', () => {
            if (videoFileInput.files.length) {
                handleFiles(videoFileInput.files, 'video');
            }
        });
        
        function handleFiles(files, type) {
            const previewContainer = (type === 'image') ? imageFilePreview : videoFilePreview;
            previewContainer.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'form-group';
                    
                    if (type === 'image') {
                        previewItem.innerHTML = `
                            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                                <div>
                                    <img src="${e.target.result}" alt="Preview" style="width: 80px; height: 60px; object-fit: cover; border-radius: 6px;">
                                </div>
                                <div style="flex-grow: 1;">
                                    <div style="margin-bottom: 8px;">${file.name}</div>
                                    <input type="text" name="captions[]" class="caption-input" placeholder="Enter caption for this image">
                                </div>
                            </div>
                        `;
                    } else {
                        previewItem.innerHTML = `
                            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                                <div>
                                    <video src="${e.target.result}" style="width: 80px; height: 60px; object-fit: cover; border-radius: 6px;"></video>
                                </div>
                                <div style="flex-grow: 1;">
                                    <div style="margin-bottom: 8px;">${file.name}</div>
                                    <input type="text" name="captions[]" class="caption-input" placeholder="Enter caption for this video">
                                </div>
                            </div>
                        `;
                    }
                    
                    previewContainer.appendChild(previewItem);
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        function deleteMedia(mediaId, mediaType) {
            if (confirm('Are you sure you want to delete this media? This action cannot be undone.')) {
                window.location.href = `admin-gallery.php?delete_id=${mediaId}&type=${mediaType}`;
            }
        }
        
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
        const developerContactLink = document.getElementById('developer-contact-link');
        const developerContactModal = document.getElementById('developerContactModal');
        const closeModal = document.querySelector('.close-modal');
        
        developerContactLink.addEventListener('click', (e) => {
            e.preventDefault();
            developerContactModal.style.display = 'flex';
        });
        
        closeModal.addEventListener('click', () => {
            developerContactModal.style.display = 'none';
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === developerContactModal) {
                developerContactModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>