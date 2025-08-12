<?php
// form-feedback.php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

// Helper function to establish database connection
function getDBConnection() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        return false;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Create required tables if they don't exist
$conn = getDBConnection();
if ($conn) {
    // Create feedback table
    $sql = "CREATE TABLE IF NOT EXISTS feedback (
        feedback_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        userid VARCHAR(50) NOT NULL,
        participant_name VARCHAR(100) NOT NULL,
        participant_name_hindi VARCHAR(100),
        gender VARCHAR(10),
        category_name VARCHAR(50),
        category_name_hindi VARCHAR(50),
        `rank` VARCHAR(100),
        rank_hindi VARCHAR(100),
        email VARCHAR(100),
        qualifications VARCHAR(100),
        residential_address TEXT,
        state_name VARCHAR(100),
        state_name_hindi VARCHAR(100),
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(20),
        delhi_address TEXT,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255),
        start_date DATE,
        end_date DATE,
        course_experience TEXT,
        rating FLOAT,
        other_rank VARCHAR(100),
        other_rank_hindi VARCHAR(100),
        course_objectives INT(1),
        background_material INT(1),
        visual_aids INT(1),
        programme_rating INT(1),
        suggestions TEXT,
        financial_year INT(4),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($sql);

    // Create lecture_ratings table
    $sql = "CREATE TABLE IF NOT EXISTS lecture_ratings (
        rating_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        feedback_id INT(6) UNSIGNED NOT NULL,
        lecture_day_id VARCHAR(20) NOT NULL,
        lecture_number INT(2) NOT NULL,
        lecture_relevance FLOAT,
        lecture_contents FLOAT,
        lecture_presentation FLOAT,
        rating FLOAT
    )";
    $conn->query($sql);
    
    $conn->close();
}

// Initialize form data
$formData = [
    'userid' => '',
    'participant_name' => '',
    'participant_name_hindi' => '',
    'gender' => '',
    'category_name' => '',
    'category_name_hindi' => '',
    'rank' => '',
    'rank_hindi' => '',
    'email' => '',
    'qualifications' => '',
    'residential_address' => '',
    'state_name' => '',
    'state_name_hindi' => '',
    'district_name' => '',
    'official_address' => '',
    'residential_phone' => '',
    'delhi_address' => '',
    'course_name' => '',
    'course_name_hindi' => '',
    'start_date' => '',
    'end_date' => '',
    'course_experience' => '',
    'rating' => 0,
    'other_rank' => '',
    'other_rank_hindi' => '',
    'course_objectives' => 0,
    'background_material' => 0,
    'visual_aids' => 0,
    'programme_rating' => 0,
    'suggestions' => '',
    'lecture_relevance' => [],
    'lecture_contents' => [],
    'lecture_presentation' => []
];

$success = '';
$error = '';
$alreadySubmitted = false; // Flag to track if feedback was already submitted

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CAPTCHA
    $userCaptcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
    $sessionCaptcha = isset($_SESSION['captcha_code']) ? $_SESSION['captcha_code'] : '';

    if (strcasecmp($userCaptcha, $sessionCaptcha) !== 0) {
        $error = "Invalid CAPTCHA code. Please try again.";
        $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    } else {
        // Extract and sanitize form data
        $userid = trim($_POST['userid'] ?? '');
        $participant_name = trim($_POST['participant_name'] ?? '');
        $participant_name_hindi = trim($_POST['participant_name_hindi'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $category_name = trim($_POST['category_name'] ?? '');
        $category_name_hindi = trim($_POST['category_name_hindi'] ?? '');
        $rank = trim($_POST['rank'] ?? '');
        $rank_hindi = trim($_POST['rank_hindi'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $qualifications = trim($_POST['qualifications'] ?? '');
        $residential_address = trim($_POST['residential_address'] ?? '');
        $state_name = trim($_POST['state_name'] ?? '');
        $state_name_hindi = trim($_POST['state_name_hindi'] ?? '');
        $district_name = trim($_POST['district_name'] ?? '');
        $official_address = trim($_POST['official_address'] ?? '');
        $residential_phone = trim($_POST['residential_phone'] ?? '');
        $delhi_address = trim($_POST['delhi_address'] ?? '');
        $course_name = trim($_POST['course_name'] ?? '');
        $course_name_hindi = trim($_POST['course_name_hindi'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $course_experience = trim($_POST['course_experience'] ?? '');
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $other_rank = trim($_POST['other_rank'] ?? '');
        $other_rank_hindi = trim($_POST['other_rank_hindi'] ?? '');
        $course_objectives = isset($_POST['course_objectives']) ? (int)$_POST['course_objectives'] : 0;
        $background_material = isset($_POST['background_material']) ? (int)$_POST['background_material'] : 0;
        $visual_aids = isset($_POST['visual_aids']) ? (int)$_POST['visual_aids'] : 0;
        $programme_rating = isset($_POST['programme_rating']) ? (int)$_POST['programme_rating'] : 0;
        $suggestions = trim($_POST['suggestions'] ?? '');
        
        // Capture lecture ratings
        $lectureRelevance = $_POST['lecture_relevance'] ?? [];
        $lectureContents = $_POST['lecture_contents'] ?? [];
        $lecturePresentation = $_POST['lecture_presentation'] ?? [];
        
        // Calculate financial year
        $financialYear = NULL;
        if ($end_date) {
            $dateParts = explode('-', $end_date);
            if (count($dateParts) === 3) {
                $day = (int)$dateParts[0];
                $month = (int)$dateParts[1];
                $year = (int)$dateParts[2];
                
                if (checkdate($month, $day, $year)) {
                    $financialYear = ($month < 4) ? $year : $year + 1;
                }
            }
        }
        if (!$financialYear) {
            $monthNum = date('n');
            $yearNum = date('Y');
            $financialYear = ($monthNum < 4) ? $yearNum : $yearNum + 1;
        }
        
        // Database insertion
        $conn = getDBConnection();
        if (!$conn) {
            $error = "Database connection failed.";
        } else {
            $conn->begin_transaction();
            
            try {
                // Check if feedback already exists for this user and course
                $checkSql = "SELECT feedback_id FROM feedback WHERE userid = ? AND course_name = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ss", $userid, $course_name);
                $checkStmt->execute();
                $checkStmt->store_result();
                
                if ($checkStmt->num_rows > 0) {
                    throw new Exception("You have already submitted feedback for this course.");
                }
                $checkStmt->close();
                
                // Handle empty district_name
                if ($district_name === '') {
                    $district_name = NULL;
                }
                
                // Prepare SQL query for feedback table
                $sql = "INSERT INTO feedback (
                    userid, participant_name, participant_name_hindi, gender, 
                    category_name, category_name_hindi, `rank`, rank_hindi, 
                    other_rank, other_rank_hindi, email, qualifications, 
                    residential_address, state_name, state_name_hindi, district_name, 
                    official_address, residential_phone, delhi_address, 
                    course_name, course_name_hindi, start_date, end_date, 
                    course_experience, rating, financial_year,
                    course_objectives, background_material, visual_aids, programme_rating, suggestions
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    // Format dates for MySQL
                    $courseStartDateMysql = NULL;
                    $courseEndDateMysql = NULL;
                    
                    if (!empty($start_date)) {
                        $parsedDate = date_parse_from_format('d-m-Y', $start_date);
                        if ($parsedDate['error_count'] === 0 && checkdate($parsedDate['month'], $parsedDate['day'], $parsedDate['year'])) {
                            $courseStartDateMysql = date('Y-m-d', strtotime($parsedDate['day'].'-'.$parsedDate['month'].'-'.$parsedDate['year']));
                        }
                    }
                    
                    if (!empty($end_date)) {
                        $parsedDate = date_parse_from_format('d-m-Y', $end_date);
                        if ($parsedDate['error_count'] === 0 && checkdate($parsedDate['month'], $parsedDate['day'], $parsedDate['year'])) {
                            $courseEndDateMysql = date('Y-m-d', strtotime($parsedDate['day'].'-'.$parsedDate['month'].'-'.$parsedDate['year']));
                        }
                    }
                    
                    // Bind parameters
                    $stmt->bind_param(
                        "ssssssssssssssssssssssssiiiiiis",
                        $userid,
                        $participant_name,
                        $participant_name_hindi,
                        $gender,
                        $category_name,
                        $category_name_hindi,
                        $rank,
                        $rank_hindi,
                        $other_rank,
                        $other_rank_hindi,
                        $email,
                        $qualifications,
                        $residential_address,
                        $state_name,
                        $state_name_hindi,
                        $district_name,
                        $official_address,
                        $residential_phone,
                        $delhi_address,
                        $course_name,
                        $course_name_hindi,
                        $courseStartDateMysql,
                        $courseEndDateMysql,
                        $course_experience,
                        $rating,
                        $financialYear,
                        $course_objectives,
                        $background_material,
                        $visual_aids,
                        $programme_rating,
                        $suggestions
                    );
                    
                    if ($stmt->execute()) {
                        $feedback_id = $conn->insert_id;
                        
                        // Check if we have a valid feedback ID
                        if ($feedback_id == 0) {
                            throw new Exception("Failed to generate valid feedback ID");
                        }
                        
                        // Insert lecture ratings
                        if (!empty($lectureRelevance)) {
                            foreach ($lectureRelevance as $lectureId => $relevanceValue) {
                                if (!isset($lectureContents[$lectureId]) || !isset($lecturePresentation[$lectureId])) continue;
                                
                                $relevance = (float)$relevanceValue;
                                $contents = (float)$lectureContents[$lectureId];
                                $presentation = (float)$lecturePresentation[$lectureId];
                                $avg = ($relevance + $contents + $presentation) / 3;
                                
                                // Parse lecture ID (format: YYYYMMDD_lectureNumber)
                                $parts = explode('_', $lectureId);
                                if (count($parts) < 2) continue;
                                
                                $lecture_day_id = $parts[0];
                                $lecture_number = (int)$parts[1];
                                
                                $stmt_lecture = $conn->prepare("INSERT INTO lecture_ratings (
                                    feedback_id, lecture_day_id, lecture_number, 
                                    lecture_relevance, lecture_contents, lecture_presentation, rating
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                
                                if ($stmt_lecture) {
                                    $stmt_lecture->bind_param(
                                        "isidddd",
                                        $feedback_id,
                                        $lecture_day_id,
                                        $lecture_number,
                                        $relevance,
                                        $contents,
                                        $presentation,
                                        $avg
                                    );
                                    if (!$stmt_lecture->execute()) {
                                        throw new Exception("Lecture rating insertion failed: " . $stmt_lecture->error);
                                    }
                                    $stmt_lecture->close();
                                } else {
                                    throw new Exception("Failed to prepare lecture statement: " . $conn->error);
                                }
                            }
                        }
                        
                        $conn->commit();
                        $success = "Feedback submitted successfully!";
                        $alreadySubmitted = true;
                        $formData = array_fill_keys(array_keys($formData), '');
                        $formData['course_objectives'] = 0;
                        $formData['background_material'] = 0;
                        $formData['visual_aids'] = 0;
                        $formData['programme_rating'] = 0;
                        $formData['rating'] = 0;
                        $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                        
                        // Set session flag to prevent resubmission
                        $_SESSION['feedback_submitted_'.$userid.'_'.$course_name] = true;
                    } else {
                        throw new Exception($stmt->error);
                    }
                    $stmt->close();
                } else {
                    throw new Exception($conn->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error: " . $e->getMessage();
            }
            $conn->close();
        }
    }
}

// Generate CAPTCHA if not exists
if (!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
}

// Handle CAPTCHA refresh request
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    echo $_SESSION['captcha_code'];
    exit;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    $conn = getDBConnection();
    
    if (!$conn) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        exit;
    }
    
    // Get user details based on registration ID
    if ($_GET['ajax'] == 'get_user_details' && isset($_GET['userid'])) {
        $userid = $conn->real_escape_string($_GET['userid']);
        $query = "SELECT * FROM registration WHERE userid = '$userid' LIMIT 1";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            $start_date = $user['start_date'] ? date('d-m-Y', strtotime($user['start_date'])) : '';
            $end_date = $user['end_date'] ? date('d-m-Y', strtotime($user['end_date'])) : '';
            
            // Check if feedback already exists for this user and course
            $feedback_exists = false;
            $checkSql = "SELECT feedback_id FROM feedback WHERE userid = ? AND course_name = ?";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param("ss", $userid, $user['course_name']);
                $checkStmt->execute();
                $checkStmt->store_result();
                $feedback_exists = ($checkStmt->num_rows > 0);
                $checkStmt->close();
            }
            
            echo json_encode([
                'status' => 'success',
                'user' => [
                    'userid' => $user['userid'] ?? '',
                    'participant_name' => $user['participant_name'] ?? '',
                    'participant_name_hindi' => $user['participant_name_hindi'] ?? '',
                    'gender' => $user['gender'] ?? '',
                    'category_name' => $user['category_name'] ?? '',
                    'category_name_hindi' => $user['category_name_hindi'] ?? '',
                    'rank' => $user['rank'] ?? '',
                    'rank_hindi' => $user['rank_hindi'] ?? '',
                    'email' => $user['email'] ?? '',
                    'qualifications' => $user['qualifications'] ?? '',
                    'residential_address' => $user['residential_address'] ?? '',
                    'state_name' => $user['state_name'] ?? '',
                    'state_name_hindi' => $user['state_name_hindi'] ?? '',
                    'district_name' => $user['district_name'] ?? '',
                    'official_address' => $user['official_address'] ?? '',
                    'residential_phone' => $user['residential_phone'] ?? '',
                    'delhi_address' => $user['delhi_address'] ?? '',
                    'course_name' => $user['course_name'] ?? '',
                    'course_name_hindi' => $user['course_name_hindi'] ?? '',
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'other_rank' => $user['other_rank'] ?? '',
                    'other_rank_hindi' => $user['other_rank_hindi'] ?? ''
                ],
                'feedback_exists' => $feedback_exists
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration not found']);
        }
        $conn->close();
        exit();
    }
    
    // Get course dates
    if ($_GET['ajax'] == 'get_course_dates' && isset($_GET['course_name'])) {
        $course_name = $conn->real_escape_string($_GET['course_name']);
        $query = "SELECT DISTINCT start_date, end_date FROM training_events WHERE course_name = '$course_name' ORDER BY start_date DESC";
        $result = $conn->query($query);
        
        $dates = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $start_date = date('d-m-Y', strtotime($row['start_date']));
                $end_date = date('d-m-Y', strtotime($row['end_date']));
                $dates[] = [
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'display' => $start_date . ' to ' . $end_date
                ];
            }
        }
        
        echo json_encode(['status' => 'success', 'dates' => $dates]);
        $conn->close();
        exit();
    }
    
    $conn->close();
    exit;
}

// Check if coming from dashboard with submitted flag
if (isset($_GET['submitted']) && $_GET['submitted'] == 'true') {
    $alreadySubmitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Feedback Form | National Crime Records Bureau</title>
    
    <!-- Favicons -->
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
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Variables for consistent theming */
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
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark);
            min-height: 100vh;
            padding-top: 180px;
        }

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

        .admin-login {
            background: var(--accent);
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 600;
            transition: var(--transition);
        }

        .admin-login:hover {
            background: #e68a00;
            transform: translateY(-2px);
        }

        .admin-login a {
            color: var(--white);
            text-decoration: none;
        }

        /* Form styling */
        .form-container {
            max-width: 1200px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }

        .form-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 20px 30px;
            text-align: center;
        }

        .form-header h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .form-body {
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .section-title {
            color: var(--primary);
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }

        .form-label {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid #ced4da;
            border-radius: 5px;
            padding: 10px 15px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.25rem rgba(0, 102, 204, 0.25);
        }

        .required:after {
            content: " *";
            color: #dc3545;
        }

        .hindi-input {
            font-family: 'Arial Unicode MS', 'Mangal', 'Kokila', sans-serif;
        }

        .other-input-container {
            margin-top: 10px;
            display: none;
        }

        .captcha-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            justify-content: center;
        }

        .captcha-code {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 3px;
            background: var(--light-gray);
            padding: 10px 15px;
            border-radius: 5px;
        }

        .refresh-captcha {
            cursor: pointer;
            color: var(--secondary);
            font-weight: 600;
        }

        .refresh-captcha:hover {
            text-decoration: underline;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .loading-districts {
            color: var(--gray);
            font-style: italic;
        }

        .captcha-input {
            width: 200px;
            margin: 0 auto;
        }

        .verification-status {
            margin-left: 10px;
            font-weight: bold;
        }

        .verification-status.verified {
            color: #28a745;
        }

        .verification-status.not-verified {
            color: #dc3545;
        }

        .btn-action {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-action:hover {
            background: linear-gradient(135deg, #00264d 0%, #0052a3 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-outline-action {
            background: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-outline-action:hover {
            background: var(--secondary);
            color: var(--white);
        }

        .otp-section {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .otp-input {
            max-width: 150px;
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #00264d 0%, #0052a3 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-reset {
            background: var(--light-gray);
            color: var(--dark);
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
        }

        .btn-reset:hover {
            background: #d1d1d1;
        }

        .form-footer {
            text-align: center;
            margin-top: 30px;
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
        
        /* Developer Contact */
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

        /* Added for better visibility of other fields */
        .other-category-container, .other-rank-container {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        /* Lecture Table Styling */
        .lecture-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .lecture-table th {
            background-color: var(--primary);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        
        .lecture-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .lecture-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .lecture-table tr:hover {
            background-color: rgba(10, 66, 117, 0.05);
        }
        
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        
        .rating input {
            display: none;
        }
        
        .rating label {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffc107;
        }
        
        .rating input:checked ~ label {
            color: #ffc107;
        }
        
        .evaluation-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
        }
        
        .evaluation-question {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #0a4275;
        }
        
        .evaluation-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .evaluation-option {
            flex: 1;
            min-width: 200px;
        }
        
        .evaluation-option label {
            display: block;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .evaluation-option input:checked + label {
            background-color: #e6f0fa;
            border-color: #0a4275;
            color: #0a4275;
            font-weight: 500;
        }
        
        .evaluation-option label:hover {
            background-color: #e9ecef;
        }
        
        .readonly-field {
            background-color: #f8f9fa;
            color: #495057;
            cursor: not-allowed;
        }
        
        .autofill-loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: #0d6efd;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Overall Rating Styles */
        .overall-rating-container {
            background: linear-gradient(135deg, #003366 0%, #0066cc 100%);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            color: white;
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }
        
        .overall-rating-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .overall-rating-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .overall-rating-stars {
            font-size: 2rem;
            color: #FFD700;
            letter-spacing: 5px;
            margin: 10px 0;
        }
        
        .rating-note {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 10px;
        }
        
        .rating-description {
            font-size: 1.1rem;
            margin-top: 5px;
            font-weight: 500;
        }
        
        /* Course Evaluation Table Styles */
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .evaluation-table th {
            background-color: var(--primary);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        
        .evaluation-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .evaluation-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .rating-column {
            width: 40%;
            padding-right: 20px;
            border-right: 1px solid #eaeaea;
        }
        
        .question-column {
            padding-left: 20px;
        }
        
        /* Success Modal */
        .modal-success .modal-header {
            background-color: #28a745;
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            body {
                padding-top: 170px;
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
                padding-top: 160px;
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
            
            .form-body {
                padding: 20px;
            }
            
            .otp-section {
                flex-direction: column;
            }
            
            .lecture-table {
                display: block;
                overflow-x: auto;
            }
            
            .rating-column, .question-column {
                width: 100%;
                padding: 10px;
                border: none;
            }
            
            .evaluation-table, .evaluation-table tbody, .evaluation-table tr, .evaluation-table td {
                display: block;
                width: 100%;
            }
            
            .evaluation-table tr {
                margin-bottom: 20px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .evaluation-table td {
                border-bottom: none;
            }
        }

        @media (max-width: 576px) {
            body {
                padding-top: 220px;
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
        }

        /* New styles for disabled form */
        .form-disabled {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .submitted-banner {
            background: #28a745;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        /* Course selection dropdown */
        .course-selection {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .course-selection .form-group {
            flex: 1;
        }
        
        .course-selection label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
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
                <div class="nav-title">National Crime Records Bureau</div>
                <div class="admin-login">
                    <i class="fas fa-comment-alt me-2"></i> Feedback Form
                </div>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-comment-alt me-2"></i> Feedback Form</h2>
            </div>
            
            <div class="form-body">
                <?php if ($alreadySubmitted): ?>
                    <div class="submitted-banner">
                        <i class="fas fa-check-circle me-2"></i> You have already submitted feedback for this course
                    </div>
                    <div class="text-center mt-4">
                        <a href="participant-dashboard.php" class="btn btn-action">
                            <i class="fas fa-arrow-left me-2"></i> Return to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <p class="text-center mb-4">Share your valuable feedback to help us improve our training programs</p>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Please enter your Registration ID to pre-fill your details. All fields marked with <span class="text-danger">*</span> are required.
                    </div>

                    <form method="post" id="feedbackForm">
                        <!-- Registration Details Section -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-id-card me-2"></i> Registration Details</h4>
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label for="userid" class="form-label required">User ID / Registration ID</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="userid" name="userid" required 
                                            placeholder="Enter your registration ID" value="<?php echo htmlspecialchars($formData['userid']); ?>">
                                        <button type="button" class="btn btn-action" id="fetch-details-btn">Fetch Details</button>
                                    </div>
                                    <div class="autofill-loader" id="userid-loader"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Details Section -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-user me-2"></i> Personal Details</h4>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="participant_name" class="form-label required">Name (English)</label>
                                    <input type="text" class="form-control readonly-field" id="participant_name" name="participant_name" required readonly value="<?php echo htmlspecialchars($formData['participant_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="participant_name_hindi" class="form-label">नाम (हिन्दी में)</label>
                                    <input type="text" class="form-control hindi-input readonly-field" id="participant_name_hindi" name="participant_name_hindi" readonly value="<?php echo htmlspecialchars($formData['participant_name_hindi']); ?>">
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <label for="gender" class="form-label required">Gender</label>
                                    <input class="form-control readonly-field" id="gender" name="gender" required readonly value="<?php echo htmlspecialchars($formData['gender']); ?>"></input>
                                </div>
                                <div class="col-md-2">
                                    <label for="category_name" class="form-label required">Category</label>
                                    <input class="form-control readonly-field" id="category_name" name="category_name" required readonly value="<?php echo htmlspecialchars($formData['category_name']); ?>"></input>
                                </div>
                                <div class="col-md-2">
                                    <label for="category_name_hindi" class="form-label required">श्रेणी</label>
                                    <input class="form-control readonly-field" id="category_name_hindi" name="category_name_hindi" required readonly value="<?php echo htmlspecialchars($formData['category_name_hindi']); ?>"></input>
                                </div>
                                <div class="col-md-2">
                                    <label for="rank" class="form-label required">Rank</label>
                                    <input class="form-control readonly-field" id="rank" name="rank" required readonly value="<?php echo htmlspecialchars($formData['rank']); ?>"></input>
                                </div>
                                <div class="col-md-2">
                                    <label for="rank_hindi" class="form-label required">रैंक</label>
                                    <input class="form-control readonly-field" id="rank_hindi" name="rank_hindi" required readonly value="<?php echo htmlspecialchars($formData['rank_hindi']); ?>"></input>
                                </div>
                                <div class="col-md-2">
                                    <label for="residential_phone" class="form-label">Mobile Phone</label>
                                    <input type="tel" class="form-control readonly-field" id="residential_phone" name="residential_phone" maxlength="10" readonly value="<?php echo htmlspecialchars($formData['residential_phone']); ?>">
                                </div>
                            </div>

                            <!-- Other Rank Fields -->
                            <div class="other-rank-container" style="display: <?php echo ($formData['other_rank'] || $formData['other_rank_hindi']) ? 'block' : 'none'; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="other_rank" class="form-label">Specify Rank (English)</label>
                                        <input type="text" class="form-control" id="other_rank" name="other_rank" value="<?php echo htmlspecialchars($formData['other_rank']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="other_rank_hindi" class="form-label">रैंक निर्दिष्ट करें (हिन्दी)</label>
                                        <input type="text" class="form-control hindi-input" id="other_rank_hindi" name="other_rank_hindi" value="<?php echo htmlspecialchars($formData['other_rank_hindi']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="email" class="form-label required">Email Address</label>
                                    <input type="email" class="form-control readonly-field" id="email" name="email" required readonly placeholder="Enter your email" value="<?php echo htmlspecialchars($formData['email']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="qualifications" class="form-label">Qualifications</label>
                                    <input type="text" class="form-control readonly-field" id="qualifications" name="qualifications" readonly value="<?php echo htmlspecialchars($formData['qualifications']); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="residential_address" class="form-label">Residential Address</label>
                                    <textarea class="form-control readonly-field" id="residential_address" name="residential_address" rows="3" readonly><?php echo htmlspecialchars($formData['residential_address']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="official_address" class="form-label">Official Address</label>
                                    <textarea class="form-control readonly-field" id="official_address" name="official_address" rows="3" readonly><?php echo htmlspecialchars($formData['official_address']); ?></textarea>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="state_name" class="form-label required">State</label>
                                    <input class="form-control readonly-field" id="state_name" name="state_name" required readonly value="<?php echo htmlspecialchars($formData['state_name']); ?>"></input>
                                </div>
                                <div class="col-md-3">
                                    <label for="state_name_hindi" class="form-label required">राज्य</label>
                                    <input class="form-control readonly-field" id="state_name_hindi" name="state_name_hindi" required readonly value="<?php echo htmlspecialchars($formData['state_name_hindi']); ?>"></input>
                                </div>
                                <div class="col-md-3">
                                    <label for="district_name" class="form-label">District</label>
                                    <input class="form-control readonly-field" id="district_name" name="district_name" disabled value="<?php echo htmlspecialchars($formData['district_name']); ?>"></input>
                                </div>
                                <div class="col-md-3">
                                    <label for="delhi_address" class="form-label">Delhi Address (if any)</label>
                                    <textarea class="form-control readonly-field" id="delhi_address" name="delhi_address" rows="1" readonly><?php echo htmlspecialchars($formData['delhi_address']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Course Details Section -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-book me-2"></i> Course Details</h4>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="course_name" class="form-label required">Course Name</label>
                                    <input class="form-control readonly-field" id="course_name" name="course_name" required readonly value="<?php echo htmlspecialchars($formData['course_name']); ?>"></input>
                                </div>
                                <div class="col-md-6">
                                    <label for="course_name_hindi" class="form-label required">कोर्स का नाम</label>
                                    <input class="form-control readonly-field" id="course_name_hindi" name="course_name_hindi" required readonly value="<?php echo htmlspecialchars($formData['course_name_hindi']); ?>"></input>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label required">Start Date</label>
                                    <input type="text" class="form-control datepicker readonly-field" id="start_date" name="start_date" required readonly value="<?php echo htmlspecialchars($formData['start_date']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label required">End Date</label>
                                    <input type="text" class="form-control datepicker readonly-field" id="end_date" name="end_date" required readonly value="<?php echo htmlspecialchars($formData['end_date']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="course_experience" class="form-label required">Your Suggestions</label>
                                    <textarea class="form-control" id="course_experience" name="course_experience" rows="1" required><?php echo htmlspecialchars($formData['course_experience']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Lecture Evaluation Section -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-chalkboard-teacher me-2"></i> Lecture Evaluation</h4>
                            <div class="table-responsive">
                                <table class="table lecture-table">
                                    <thead>
                                        <tr>
                                            <th>Date (Day)</th>
                                            <th>Lecture</th>
                                            <th>Relevance</th>
                                            <th>Contents</th>
                                            <th>Presentation</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lecture-days">
                                        <tr>
                                            <td colspan="5" class="text-center py-5">Course dates will appear after registration details are loaded</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Course Evaluation Section -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-clipboard-check me-2"></i> Course Evaluation</h4>
                            <table class="table evaluation-table">
                                <thead>
                                    <tr>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Question 1 -->
                                    <tr>
                                        <td class="question-column">
                                            <div class="evaluation-question">1. How well the objectives of this course been achieved?<br>1. इस पाठ्यक्रम के उद्देश्य कितने अच्छे से प्राप्त किए गए?</div>
                                        </td>
                                        <td class="rating-column">
                                            <div class="rating d-flex justify-content-start">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" id="objectives_<?php echo $i; ?>" name="course_objectives" value="<?php echo $i; ?>" required <?php echo ($formData['course_objectives'] == $i) ? 'checked' : ''; ?>>
                                                    <label for="objectives_<?php echo $i; ?>" class="mx-1">★</label>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Question 2 -->
                                    <tr>
                                        <td class="question-column">
                                            <div class="evaluation-question">2. How useful did you find the background material?<br>2. आपको पृष्ठभूमि सामग्री कितनी उपयोगी लगी?</div>
                                        </td>
                                        <td class="rating-column">
                                            <div class="rating d-flex justify-content-start">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" id="material_<?php echo $i; ?>" name="background_material" value="<?php echo $i; ?>" required <?php echo ($formData['background_material'] == $i) ? 'checked' : ''; ?>>
                                                    <label for="material_<?php echo $i; ?>" class="mx-1">★</label>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Question 3 -->
                                    <tr>
                                        <td class="question-column">
                                            <div class="evaluation-question">3. Your rating of the visual aids and training techniques used in the programme.<br>3. कार्यक्रम में उपयोग की जाने वाली दृश्य सामग्री और प्रशिक्षण तकनीकों पर आपकी रेटिंग।</div>
                                        </td>
                                        <td class="rating-column">
                                            <div class="rating d-flex justify-content-start">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" id="visual_aids_<?php echo $i; ?>" name="visual_aids" value="<?php echo $i; ?>" required <?php echo ($formData['visual_aids'] == $i) ? 'checked' : ''; ?>>
                                                    <label for="visual_aids_<?php echo $i; ?>" class="mx-1">★</label>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Question 4 -->
                                    <tr>
                                        <td class="question-column">
                                            <div class="evaluation-question">4. How would you rate the programme overall?<br>4. आप समग्र रूप से कार्यक्रम को कैसे रेट करेंगे?</div>
                                        </td>
                                        <td class="rating-column">
                                            <div class="rating d-flex justify-content-start">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" id="programme_<?php echo $i; ?>" name="programme_rating" value="<?php echo $i; ?>" required <?php echo ($formData['programme_rating'] == $i) ? 'checked' : ''; ?>>
                                                    <label for="programme_<?php echo $i; ?>" class="mx-1">★</label>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Overall Rating Row -->
                                    <tr>
                                        <td colspan="2" class="text-center">
                                            <div class="overall-rating-container">
                                                <div class="overall-rating-title">Overall Course Rating</div>
                                                <div class="overall-rating-stars" id="overallStars">
                                                    ★★★★★
                                                </div>
                                                <div class="overall-rating-value" id="overallRating">
                                                    0.0
                                                </div>
                                                <div class="rating-description" id="ratingDescription">
                                                    (Calculated from your evaluations)
                                                </div>
                                                <div class="rating-note">
                                                    Based on your ratings for objectives, material, visual aids, and program quality
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Suggestions -->
                                    <tr>
                                        <td colspan="2">
                                            <div class="evaluation-question">Any Suggestion?<br>कोई सुझाव?</div>
                                            <textarea class="form-control" id="suggestions" name="suggestions" rows="3" 
                                                placeholder="Please share your valuable suggestions / कृपया अपने कीमती सुझाव साझा करें"><?php echo htmlspecialchars($formData['suggestions']); ?></textarea>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <!-- Hidden field to store the calculated rating -->
                            <input type="hidden" name="rating" id="calculatedRating" value="0">
                        </div>

                        <!-- CAPTCHA Section -->
                        <div class="form-section">
                            <h4 class="section-title"><i class="fas fa-shield-alt me-2"></i> Security Verification</h4>
                            <div class="row">
                                <div class="col-md-8 offset-md-2 text-center">
                                    <label for="captcha" class="form-label required">CAPTCHA Verification</label>
                                    <div class="captcha-container">
                                        <div class="captcha-code" id="captcha-code"><?php echo htmlspecialchars($_SESSION['captcha_code']); ?></div>
                                        <span class="refresh-captcha" id="refresh-captcha">
                                            <i class="fas fa-sync-alt me-1"></i> Refresh
                                        </span>
                                    </div>
                                    <div class="captcha-input">
                                        <input type="text" class="form-control text-center" id="captcha" name="captcha" required placeholder="Enter CAPTCHA" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-footer">
                            <button type="submit" class="btn btn-submit me-3">Submit Feedback</button>
                            <button type="reset" class="btn btn-reset">Reset Form</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success!</h5>
                </div>
                <div class="modal-body">
                    <p>Feedback submitted successfully!</p>
                    <p>You will be redirected to the dashboard in <span id="countdown">5</span> seconds.</p>
                </div>
            </div>
        </div>
    </div>

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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    $(document).ready(function() {
        // Initialize date pickers
        flatpickr(".datepicker", {
            dateFormat: "d-m-Y",
            allowInput: true
        });

        // Initialize with server CAPTCHA value
        let captchaCode = '<?php echo $_SESSION['captcha_code']; ?>';
        
        // Function to refresh CAPTCHA via server
        function refreshCaptcha() {
            $.get('form-feedback.php?refresh_captcha', function(newCaptcha) {
                $('#captcha-code').text(newCaptcha);
                captchaCode = newCaptcha;
                $('#captcha').val('').focus();
            });
        }

        // Set up refresh button
        $('#refresh-captcha').click(refreshCaptcha);

        // Function to update lecture days table
        function updateLectureDays() {
            const startDate = $('#start_date').val();
            const endDate = $('#end_date').val();
            
            if (startDate && endDate) {
                try {
                    // Parse dd-mm-yyyy formatted dates
                    const startParts = startDate.split('-');
                    const endParts = endDate.split('-');
                    
                    const start = new Date(
                        parseInt(startParts[2]), 
                        parseInt(startParts[1]) - 1, 
                        parseInt(startParts[0])
                    );
                    
                    const end = new Date(
                        parseInt(endParts[2]), 
                        parseInt(endParts[1]) - 1, 
                        parseInt(endParts[0])
                    );
                    
                    if (!isNaN(start.getTime()) && !isNaN(end.getTime())) {
                        // Include end date by adding one day
                        const endDatePlusOne = new Date(end);
                        endDatePlusOne.setDate(endDatePlusOne.getDate() + 1);
                        
                        const days = [];
                        const current = new Date(start);
                        
                        // Calculate total days (inclusive)
                        const totalDays = Math.ceil((endDatePlusOne - start) / (1000 * 60 * 60 * 24));
                        
                        for (let i = 0; i < totalDays; i++) {
                            const dayDate = new Date(start);
                            dayDate.setDate(start.getDate() + i);
                            
                            const day = dayDate.toLocaleDateString('en-GB', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric',
                                weekday: 'long'
                            });
                            
                            const dayId = dayDate.getFullYear() + 
                                         String(dayDate.getMonth() + 1).padStart(2, '0') + 
                                         String(dayDate.getDate()).padStart(2, '0');
                            
                            days.push({day, dayId});
                        }
                        
                        let rows = '';
                        days.forEach(day => {
                            // Main date row
                            rows += `
                                <tr>
                                    <td rowspan="6" style="vertical-align: middle;">${day.day}</td>
                                    <td colspan="4" style="background-color: #e9ecef; font-weight: bold;">Lectures</td>
                                </tr>
                            `;
                            
                            // Add 5 lectures per day
                            for (let lectureNum = 1; lectureNum <= 5; lectureNum++) {
                                const lectureId = `${day.dayId}_${lectureNum}`;
                                rows += `
                                    <tr class="lecture-subrow" data-id="${lectureId}">
                                        <td class="lecture-number">Lecture ${lectureNum}</td>
                                        <td>
                                            <div class="rating">
                                                <input type="radio" id="relevance_${lectureId}_5" name="lecture_relevance[${lectureId}]" value="5">
                                                <label for="relevance_${lectureId}_5">★</label>
                                                <input type="radio" id="relevance_${lectureId}_4" name="lecture_relevance[${lectureId}]" value="4">
                                                <label for="relevance_${lectureId}_4">★</label>
                                                <input type="radio" id="relevance_${lectureId}_3" name="lecture_relevance[${lectureId}]" value="3">
                                                <label for="relevance_${lectureId}_3">★</label>
                                                <input type="radio" id="relevance_${lectureId}_2" name="lecture_relevance[${lectureId}]" value="2">
                                                <label for="relevance_${lectureId}_2">★</label>
                                                <input type="radio" id="relevance_${lectureId}_1" name="lecture_relevance[${lectureId}]" value="1">
                                                <label for="relevance_${lectureId}_1">★</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="rating">
                                                <input type="radio" id="contents_${lectureId}_5" name="lecture_contents[${lectureId}]" value="5">
                                                <label for="contents_${lectureId}_5">★</label>
                                                <input type="radio" id="contents_${lectureId}_4" name="lecture_contents[${lectureId}]" value="4">
                                                <label for="contents_${lectureId}_4">★</label>
                                                <input type="radio" id="contents_${lectureId}_3" name="lecture_contents[${lectureId}]" value="3">
                                                <label for="contents_${lectureId}_3">★</label>
                                                <input type="radio" id="contents_${lectureId}_2" name="lecture_contents[${lectureId}]" value="2">
                                                <label for="contents_${lectureId}_2">★</label>
                                                <input type="radio" id="contents_${lectureId}_1" name="lecture_contents[${lectureId}]" value="1">
                                                <label for="contents_${lectureId}_1">★</label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="rating">
                                                <input type="radio" id="presentation_${lectureId}_5" name="lecture_presentation[${lectureId}]" value="5">
                                                <label for="presentation_${lectureId}_5">★</label>
                                                <input type="radio" id="presentation_${lectureId}_4" name="lecture_presentation[${lectureId}]" value="4">
                                                <label for="presentation_${lectureId}_4">★</label>
                                                <input type="radio" id="presentation_${lectureId}_3" name="lecture_presentation[${lectureId}]" value="3">
                                                <label for="presentation_${lectureId}_3">★</label>
                                                <input type="radio" id="presentation_${lectureId}_2" name="lecture_presentation[${lectureId}]" value="2">
                                                <label for="presentation_${lectureId}_2">★</label>
                                                <input type="radio" id="presentation_${lectureId}_1" name="lecture_presentation[${lectureId}]" value="1">
                                                <label for="presentation_${lectureId}_1">★</label>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }
                        });
                        
                        $('#lecture-days').html(rows);
                        return;
                    }
                } catch (e) {
                    console.error("Date parsing error", e);
                }
            }
            
            $('#lecture-days').html('<tr><td colspan="5" class="text-center py-4">No course dates available or invalid date format</td></tr>');
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

        // Function to calculate and update overall rating
        function updateOverallRating() {
            let total = 0;
            let count = 0;
            
            // Get values from all course evaluation ratings
            const objectives = parseInt($('input[name="course_objectives"]:checked').val()) || 0;
            const material = parseInt($('input[name="background_material"]:checked').val()) || 0;
            const visual = parseInt($('input[name="visual_aids"]:checked').val()) || 0;
            const program = parseInt($('input[name="programme_rating"]:checked').val()) || 0;
            
            // Only include ratings that have been set
            if (objectives > 0) { total += objectives; count++; }
            if (material > 0) { total += material; count++; }
            if (visual > 0) { total += visual; count++; }
            if (program > 0) { total += program; count++; }
            
            let average = 0;
            if (count > 0) {
                average = total / count;
            }
            
            // Update display
            const roundedAverage = average.toFixed(1);
            $('#overallRating').text(roundedAverage);
            $('#calculatedRating').val(Math.round(average));
            
            // Update star display
            const fullStars = Math.round(average);
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                starsHtml += (i <= fullStars) ? '★' : '☆';
            }
            $('#overallStars').html(starsHtml);
            
            // Update description
            let description = "";
            if (average >= 4.5) description = "Excellent!";
            else if (average >= 3.5) description = "Very Good";
            else if (average >= 2.5) description = "Good";
            else if (average >= 1.5) description = "Fair";
            else if (average > 0) description = "Needs Improvement";
            else description = "(Waiting for your ratings)";
            
            $('#ratingDescription').text(description);
        }
        
        // Add event listeners to all rating inputs
        $('input[name="course_objectives"], input[name="background_material"], input[name="visual_aids"], input[name="programme_rating"]').change(updateOverallRating);
        
        // Initialize overall rating
        updateOverallRating();

        // Fetch user details when button is clicked
        $('#fetch-details-btn').click(function() {
            const userid = $('#userid').val().trim();
            if (!userid) {
                alert('Please enter a User/ Registration ID');
                return;
            }
            
            $('#userid-loader').show();
            
            $.ajax({
                url: 'form-feedback.php?ajax=get_user_details&userid=' + encodeURIComponent(userid),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const user = response.user;
                        
                        // Fill all user fields
                        $('#participant_name').val(user.participant_name || '');
                        $('#participant_name_hindi').val(user.participant_name_hindi || '');
                        $('#gender').val(user.gender || '');
                        $('#category_name').val(user.category_name || '');
                        $('#category_name_hindi').val(user.category_name_hindi || '');
                        $('#rank').val(user.rank || '');
                        $('#rank_hindi').val(user.rank_hindi || '');
                        $('#email').val(user.email || '');
                        $('#qualifications').val(user.qualifications || '');
                        $('#residential_address').val(user.residential_address || '');
                        $('#official_address').val(user.official_address || '');
                        $('#residential_phone').val(user.residential_phone || '');
                        $('#state_name').val(user.state_name || '');
                        $('#state_name_hindi').val(user.state_name_hindi || '');
                        $('#district_name').val(user.district_name || '');
                        $('#delhi_address').val(user.delhi_address || '');
                        $('#course_name').val(user.course_name || '');
                        $('#course_name_hindi').val(user.course_name_hindi || '');
                        $('#start_date').val(user.start_date || '');
                        $('#end_date').val(user.end_date || '');
                        
                        // Handle other rank
                        if (user.other_rank || user.other_rank_hindi) {
                            $('.other-rank-container').show();
                            $('#other_rank').val(user.other_rank || '');
                            $('#other_rank_hindi').val(user.other_rank_hindi || '');
                        } else {
                            $('.other-rank-container').hide();
                        }
                        
                        // Update lecture days
                        updateLectureDays();
                        
                        $('.alert-success').remove();
                        $('#feedbackForm').prepend(
                            '<div class="alert alert-success">User details loaded successfully!</div>'
                        );
                        
                        // Check if feedback already exists
                        if (response.feedback_exists) {
                            $('#feedbackForm :input').prop('disabled', true);
                            $('#feedbackForm').prepend(
                                '<div class="alert alert-info">You have already submitted feedback for this course.</div>'
                            );
                        }
                    } else {
                        $('.alert-danger').remove();
                        $('#feedbackForm').prepend(
                            '<div class="alert alert-danger">Error: ' + (response.message || 'Registration not found') + '</div>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $('.alert-danger').remove();
                    $('#feedbackForm').prepend(
                        '<div class="alert alert-danger">Error fetching details: ' + error + '</div>'
                    );
                },
                complete: function() {
                    $('#userid-loader').hide();
                }
            });
        });

        // Form validation
        $('#feedbackForm').submit(function(e) {
            let valid = true;
            $('.is-invalid').removeClass('is-invalid');
            
            // Validate required fields
            $('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    valid = false;
                }
            });
            
            // Validate CAPTCHA with case-insensitive comparison
            const userCaptcha = $('#captcha').val().trim();
            if (userCaptcha.toLowerCase() !== captchaCode.toLowerCase()) {
                $('#captcha').addClass('is-invalid');
                $('.alert-danger').remove();
                $('#feedbackForm').prepend(
                    '<div class="alert alert-danger">Invalid CAPTCHA code. Please try again.</div>'
                );
                refreshCaptcha();
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.alert-danger').offset().top - 100
                }, 500);
            }
        });

        // Reset form
        $('button[type="reset"]').click(function() {
            $('.is-invalid').removeClass('is-invalid');
            refreshCaptcha();
            $('.alert').remove();
            updateOverallRating(); // Reset rating display
        });

        // Initialize form
        updateLectureDays();
        
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
        
        // Auto-fill user details if userid is in URL
        const urlParams = new URLSearchParams(window.location.search);
        const useridParam = urlParams.get('userid');
        
        if (useridParam) {
            $('#userid').val(useridParam);
            // Trigger fetch after a short delay to allow DOM to update
            setTimeout(() => {
                $('#fetch-details-btn').click();
            }, 300);
        }
        
        // Handle successful submission
        <?php if ($success): ?>
            // Show success modal
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            
            // Start countdown for redirection
            let seconds = 5;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'participant-dashboard.php';
                }
            }, 1000);
        <?php endif; ?>
    });
    </script>
</body>
</html>