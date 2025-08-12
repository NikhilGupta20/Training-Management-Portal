<?php
// admin-feedback.php
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
    die("Database connection failed: " . $conn->connect_error);
}

// Create required tables if they don't exist
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

// Updated lecture_ratings table structure
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

// Get admin username from session
$admin_username = $_SESSION['admin_username'];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch filter data
$courses = [];
$states = [];

$courses_result = $conn->query("SELECT DISTINCT course_name FROM training_events");
if ($courses_result) {
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
}

$states_result = $conn->query("SELECT * FROM states");
if ($states_result) {
    $states = $states_result->fetch_all(MYSQLI_ASSOC);
}

// Initialize filter variables
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
$param_types = "";

// Handle filters
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $conditions[] = "start_date BETWEEN ? AND ?";
    $params[] = $_GET['start_date'];
    $params[] = $_GET['end_date'];
    $param_types .= "ss";
}

if (!empty($_GET['course'])) {
    $conditions[] = "course_name = ?";
    $params[] = $_GET['course'];
    $param_types .= "s";
}

if (!empty($_GET['state'])) {
    $conditions[] = "state_name = ?";
    $params[] = $_GET['state'];
    $param_types .= "s";
}

if (!empty($_GET['district'])) {
    $conditions[] = "district_name = ?";
    $params[] = $_GET['district'];
    $param_types .= "s";
}

if (!empty($_GET['gender'])) {
    $conditions[] = "gender = ?";
    $params[] = $_GET['gender'];
    $param_types .= "s";
}

if (!empty($_GET['search'])) {
    $conditions[] = "(participant_name LIKE ? OR userid LIKE ?)";
    $searchTerm = "%" . $_GET['search'] . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $param_types .= "ss";
}

// Fetch data for reports
$results = [];
$lectureRatings = [];
$lectureDailyRatings = [];
$courseRatings = [];
$ratingDistribution = [];
$overallRating = 0;
$feedbackCount = 0;

// Main query for records table
$sql = "SELECT * FROM feedback";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
} else {
    // Show no records if no filters are applied
    $sql .= " WHERE 1=0";
}

// Execute main query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $results = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $result = $conn->query($sql);
    if ($result) {
        $results = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Build query string without report parameter
$queryParams = $_GET;
unset($queryParams['report']);
$queryString = http_build_query($queryParams);

// Function to get lecture ratings
function getLectureRatings($conn, $filters) {
    $sql = "SELECT 
                AVG(lecture_relevance) AS avg_relevance,
                AVG(lecture_contents) AS avg_contents,
                AVG(lecture_presentation) AS avg_presentation
            FROM lecture_ratings lr
            JOIN feedback fb ON lr.feedback_id = fb.feedback_id";
    
    $conditions = [];
    $params = [];
    $param_types = "";
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $conditions[] = "fb.start_date BETWEEN ? AND ?";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
        $param_types .= "ss";
    }
    if (!empty($filters['course'])) {
        $conditions[] = "fb.course_name = ?";
        $params[] = $filters['course'];
        $param_types .= "s";
    }
    if (!empty($filters['state'])) {
        $conditions[] = "fb.state_name = ?";
        $params[] = $filters['state'];
        $param_types .= "s";
    }
    if (!empty($filters['district'])) {
        $conditions[] = "fb.district_name = ?";
        $params[] = $filters['district'];
        $param_types .= "s";
    }
    if (!empty($filters['gender'])) {
        $conditions[] = "fb.gender = ?";
        $params[] = $filters['gender'];
        $param_types .= "s";
    }
    if (!empty($filters['search'])) {
        $conditions[] = "(fb.participant_name LIKE ? OR fb.userid LIKE ?)";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $param_types .= "ss";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    return [];
}

// Function to get lecture-wise daily ratings
function getLectureDailyRatings($conn, $filters) {
    $sql = "SELECT 
                DATE_FORMAT(STR_TO_DATE(lr.lecture_day_id, '%Y%m%d'), '%W, %d/%m/%Y') AS formatted_date,
                lr.lecture_day_id,
                lr.lecture_number,
                COALESCE(AVG(lr.lecture_relevance), 0) AS avg_relevance,
                COALESCE(AVG(lr.lecture_contents), 0) AS avg_contents,
                COALESCE(AVG(lr.lecture_presentation), 0) AS avg_presentation,
                (COALESCE(AVG(lr.lecture_relevance), 0) + 
                 COALESCE(AVG(lr.lecture_contents), 0) + 
                 COALESCE(AVG(lr.lecture_presentation), 0)) / 3 AS avg_rating
            FROM lecture_ratings lr
            JOIN feedback fb ON lr.feedback_id = fb.feedback_id";
    
    $conditions = [];
    $params = [];
    $param_types = "";
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $conditions[] = "fb.start_date BETWEEN ? AND ?";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
        $param_types .= "ss";
    }
    if (!empty($filters['course'])) {
        $conditions[] = "fb.course_name = ?";
        $params[] = $filters['course'];
        $param_types .= "s";
    }
    if (!empty($filters['state'])) {
        $conditions[] = "fb.state_name = ?";
        $params[] = $filters['state'];
        $param_types .= "s";
    }
    if (!empty($filters['district'])) {
        $conditions[] = "fb.district_name = ?";
        $params[] = $filters['district'];
        $param_types .= "s";
    }
    if (!empty($filters['gender'])) {
        $conditions[] = "fb.gender = ?";
        $params[] = $filters['gender'];
        $param_types .= "s";
    }
    if (!empty($filters['search'])) {
        $conditions[] = "(fb.participant_name LIKE ? OR fb.userid LIKE ?)";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $param_types .= "ss";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY lr.lecture_day_id, lr.lecture_number
              ORDER BY STR_TO_DATE(lr.lecture_day_id, '%Y%m%d'), lr.lecture_number";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }
    return [];
}

// Function to get overall course ratings
function getCourseRatings($conn, $filters) {
    $sql = "SELECT 
                AVG(rating) AS avg_rating,
                COUNT(*) AS feedback_count
            FROM feedback";
    
    $conditions = [];
    $params = [];
    $param_types = "";
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $conditions[] = "start_date BETWEEN ? AND ?";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
        $param_types .= "ss";
    }
    if (!empty($filters['course'])) {
        $conditions[] = "course_name = ?";
        $params[] = $filters['course'];
        $param_types .= "s";
    }
    if (!empty($filters['state'])) {
        $conditions[] = "state_name = ?";
        $params[] = $filters['state'];
        $param_types .= "s";
    }
    if (!empty($filters['district'])) {
        $conditions[] = "district_name = ?";
        $params[] = $filters['district'];
        $param_types .= "s";
    }
    if (!empty($filters['gender'])) {
        $conditions[] = "gender = ?";
        $params[] = $filters['gender'];
        $param_types .= "s";
    }
    if (!empty($filters['search'])) {
        $conditions[] = "(participant_name LIKE ? OR userid LIKE ?)";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $param_types .= "ss";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    return ['avg_rating' => 0, 'feedback_count' => 0];
}

// Function to get rating distribution
function getRatingDistribution($conn, $filters) {
    $sql = "SELECT 
                ROUND(rating) as rating_value,
                COUNT(*) AS count
            FROM feedback";
    
    $conditions = [];
    $params = [];
    $param_types = "";
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $conditions[] = "start_date BETWEEN ? AND ?";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];
        $param_types .= "ss";
    }
    if (!empty($filters['course'])) {
        $conditions[] = "course_name = ?";
        $params[] = $filters['course'];
        $param_types .= "s";
    }
    if (!empty($filters['state'])) {
        $conditions[] = "state_name = ?";
        $params[] = $filters['state'];
        $param_types .= "s";
    }
    if (!empty($filters['district'])) {
        $conditions[] = "district_name = ?";
        $params[] = $filters['district'];
        $param_types .= "s";
    }
    if (!empty($filters['gender'])) {
        $conditions[] = "gender = ?";
        $params[] = $filters['gender'];
        $param_types .= "s";
    }
    if (!empty($filters['search'])) {
        $conditions[] = "(participant_name LIKE ? OR userid LIKE ?)";
        $searchTerm = "%" . $filters['search'] . "%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $param_types .= "ss";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY rating_value";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $distribution = [];
        $total = 0;
        
        foreach ($data as $row) {
            $rating = intval($row['rating_value']);
            $count = intval($row['count']);
            $distribution[$rating] = $count;
            $total += $count;
        }
        
        // Prepare an array for ratings 1 to 5 with count and percentage
        $result = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $distribution[$i] ?? 0;
            $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
            $result[] = [
                'rating' => $i,
                'count' => $count,
                'percentage' => $percentage
            ];
        }
        
        return $result;
    }
    return [];
}

// Get actual data for reports
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'course' => $_GET['course'] ?? '',
    'state' => $_GET['state'] ?? '',
    'district' => $_GET['district'] ?? '',
    'gender' => $_GET['gender'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$lectureRatings = getLectureRatings($conn, $filters);
$lectureDailyRatings = getLectureDailyRatings($conn, $filters);
$courseRatings = getCourseRatings($conn, $filters);
$ratingDistribution = getRatingDistribution($conn, $filters);

// Calculate overall average
$overallRating = $courseRatings['avg_rating'] ?? 0;
$feedbackCount = $courseRatings['feedback_count'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Feedback Management - NCRB Training Portal</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    
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
        padding-top: 200px; /* Match dashboard padding */
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

    /* ===== FILTER SECTION ===== */
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

    /* ===== ALERT MESSAGES ===== */
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

    /* ===== RESULTS TABLE ===== */
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

    .rating-stars {
        color: #FFD700;
        font-size: 18px;
    }
    
    /* ===== ACTION BUTTONS ===== */
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
        border: none;
    }

    .print-btn {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }

    .dashboard-btn {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
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

    /* ===== LECTURE ANALYSIS ===== */
    .lecture-analysis-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .lecture-analysis-table th {
        background-color: #003366;
        color: white;
        padding: 12px 10px;
        text-align: center;
        font-weight: 600;
        border: 1px solid #003366;
    }
    
    .lecture-analysis-table td {
        padding: 10px 8px;
        border: 1px solid #ddd;
        text-align: center;
    }
    
    .lecture-overall-rating {
        text-align: center;
        margin: 25px 0;
        padding: 15px;
        background-color: #f0f7ff;
        border-left: 4px solid #0066cc;
    }
    
    .lecture-rating-value {
        font-size: 28px;
        font-weight: bold;
        color: #003366;
        margin: 10px 0;
    }
    
    .lecture-rating-stars {
        font-size: 24px;
        color: #FFD700;
        letter-spacing: 2px;
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
                <div class="nav-title">National Crime Records Bureau - Feedback Management</div>
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
            <h1>Feedback Management</h1>
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
                <select name="state" id="stateSelect">
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
                <input type="text" name="search" id="searchInput" placeholder="Participant name or ID..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="filter-btn">Filter/Search</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="admin-feedback.php" class="reset-btn">Reset Filters</a>
            </div>
        </form>

        <!-- Results Table -->
        <?php if (!empty($results)): ?>
            <div id="reportSection">
                <table border="1" cellspacing="0" cellpadding="5">
                    <thead>
                        <tr>
                            <th>Serial No.</th>
                            <th>User ID</th>
                            <th>Participant Name</th>
                            <th>Course Name</th>
                            <th>Course Dates</th>
                            <th>State</th>
                            <th>District</th>
                            <th>Rating</th>
                            <th>Suggestions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= $serial++ ?></td>
                                <td><?= htmlspecialchars($row['userid']) ?></td>
                                <td><?= htmlspecialchars($row['participant_name']) ?></td>
                                <td><?= htmlspecialchars($row['course_name']) ?></td>
                                <td>
                                    <?= date("d-m-Y", strtotime($row['start_date'])) ?> to 
                                    <?= date("d-m-Y", strtotime($row['end_date'])) ?>
                                </td>
                                <td><?= htmlspecialchars($row['state_name']) ?></td>
                                <td><?= htmlspecialchars($row['district_name']) ?></td>
                                <td>
                                    <div class="rating-stars">
                                        <?= str_repeat('★', $row['rating']) . str_repeat('☆', 5 - $row['rating']) ?>
                                        (<?= $row['rating'] ?>/5)
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['suggestions']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="btn-container">
                <button onclick="printFeedbackReport()" class="action-btn print-btn">Print Feedback Report</button>
                <button onclick="printSuggestionsReport()" class="action-btn print-btn">Print Suggestions Report</button>
                <button onclick="printSuggestionsOnly()" class="action-btn print-btn">Print Suggestions Only</button>
                <button onclick="printCourseAnalysis()" class="action-btn print-btn">Print Course Analysis</button>                
                <button onclick="printLectureAnalysis()" class="action-btn print-btn">Lecture-wise Analysis</button>
                <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <p class="no-data">
                <?php if (!empty($conditions)): ?>
                    No feedback records found matching your criteria.
                <?php else: ?>
                    Please apply filters to view feedback records.
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

    <!-- Pass PHP data to JavaScript -->
    <script>
        const reportData = {
            overallRating: <?= $overallRating ?>,
            feedbackCount: <?= $feedbackCount ?>,
            ratingDistribution: <?= json_encode($ratingDistribution) ?>,
            lectureDailyRatings: <?= json_encode($lectureDailyRatings) ?>,
            lectureRatings: <?= json_encode($lectureRatings) ?>,
            results: <?= json_encode($results) ?>
        };
    </script>

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

    // ===== HELPER FUNCTIONS =====
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatDate(dateString) {
        const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-GB', options);
    }

    function generateRatingStars(rating) {
        let stars = '';
        const fullStars = Math.floor(rating);
        const emptyStars = 5 - fullStars;
        for (let i=0; i<fullStars; i++) stars += '★';
        for (let i=0; i<emptyStars; i++) stars += '☆';
        return stars;
    }

    // ===== PRINT FUNCTIONS =====
    function createPrintHeader(title) {
        return `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #003366;">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" alt="MHA Logo" style="height: 70px;">
                <div style="text-align: center;">
                    <h1 style="margin: 0; font-size: 22pt; color: #003366;">National Crime Records Bureau</h1>
                    <h2 style="margin: 5px 0 0 0; font-size: 18pt; font-weight: normal; color: #0066cc;">${title}</h2>
                </div>
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo" style="height: 70px;">
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 11pt;">
                <div><strong>Generated:</strong> ${new Date().toLocaleString()}</div>
                <div><strong>Admin:</strong> <?= $admin_username ?></div>
            </div>
        `;
    }

    function createPrintFooter() {
        return `
            <div style="margin-top: 30px; text-align: center; font-size: 10pt; color: #666; border-top: 1px solid #ccc; padding-top: 15px;">
                <p>National Crime Records Bureau<br>Ministry of Home Affairs, Government of India</p>
                <p>Generated by NCRB Training Management System</p>
            </div>
        `;
    }

    function openPrintWindow(title, content) {
        const printWindow = window.open('', '', 'width=1000,height=700');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${title}</title>
                <style>
                    @media print { 
                        body { 
                            margin: 1cm; 
                            font-family: "Times New Roman", serif; 
                        } 
                    }
                    body { 
                        font-family: Arial, sans-serif; 
                        color: #333;
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        margin: 20px 0;
                    }
                    th { 
                        background-color: #003366; 
                        color: white; 
                        padding: 10px; 
                        border: 1px solid #003366; 
                        text-align: left;
                    }
                    td { 
                        padding: 8px; 
                        border: 1px solid #ddd; 
                    }
                    .rating-stars { 
                        color: #FFD700; 
                        font-size: 18px; 
                    }
                    .print-header { 
                        text-align: center; 
                        margin-bottom: 20px;
                        border-bottom: 2px solid #003366;
                        padding-bottom: 20px;
                    }
                    .logo-container {
                        display: flex;
                        justify-content: center;
                        gap: 30px;
                        margin-bottom: 15px;
                    }
                    .logo-container img {
                        height: 80px;
                    }
                    .print-title { 
                        font-size: 22px; 
                        color: #003366; 
                        font-weight: bold; 
                        margin: 10px 0;
                    }
                    .print-subtitle { 
                        font-size: 18px; 
                        color: #0066cc; 
                        margin-bottom: 10px;
                    }
                    .print-meta {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 20px;
                        font-size: 14px;
                    }
                    .print-footer { 
                        text-align: center; 
                        margin-top: 30px; 
                        font-size: 10pt; 
                        color: #666;
                        border-top: 1px solid #ccc;
                        padding-top: 15px;
                    }
                    .section-title {
                        font-size: 18px;
                        color: #003366;
                        margin: 20px 0 10px;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 5px;
                    }
                </style>
            </head>
            <body>
                ${createPrintHeader(title)}
                ${content}
                ${createPrintFooter()}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }

    function printFeedbackReport() {
        let tableHTML = `<table>
            <thead>
                <tr>
                    <th>Serial No.</th>
                    <th>User ID</th>
                    <th>Participant Name</th>
                    <th>Course Name</th>
                    <th>Course Dates</th>
                    <th>State</th>
                    <th>District</th>
                    <th>Rating</th>
                    <th>Suggestions</th>
                </tr>
            </thead>
            <tbody>`;
        
        reportData.results.forEach((row, index) => {
            tableHTML += `<tr>
                <td>${index+1}</td>
                <td>${escapeHtml(row.userid)}</td>
                <td>${escapeHtml(row.participant_name)}</td>
                <td>${escapeHtml(row.course_name)}</td>
                <td>${formatDate(row.start_date)} to ${formatDate(row.end_date)}</td>
                <td>${escapeHtml(row.state_name)}</td>
                <td>${escapeHtml(row.district_name)}</td>
                <td>
                    <div class="rating-stars">
                        ${generateRatingStars(row.rating)}
                        (${row.rating}/5)
                    </div>
                </td>
                <td>${escapeHtml(row.suggestions)}</td>
            </tr>`;
        });
        
        tableHTML += `</tbody></table>`;
        openPrintWindow("Feedback Report", tableHTML);
    }

    function printSuggestionsReport() {
        let tableHTML = `<table>
            <thead>
                <tr>
                    <th>Serial No.</th>
                    <th>Participant Name</th>
                    <th>Course Name</th>
                    <th>Suggestions</th>
                </tr>
            </thead>
            <tbody>`;
        
        reportData.results.forEach((row, index) => {
            tableHTML += `<tr>
                <td>${index+1}</td>
                <td>${escapeHtml(row.participant_name)}</td>
                <td>${escapeHtml(row.course_name)}</td>
                <td>${escapeHtml(row.suggestions)}</td>
            </tr>`;
        });
        
        tableHTML += `</tbody></table>`;
        openPrintWindow("Suggestions Report", tableHTML);
    }

    function printSuggestionsOnly() {
        let tableHTML = `<table>
            <thead>
                <tr>
                    <th>Serial No.</th>
                    <th>Course Name</th>
                    <th>Suggestions</th>
                </tr>
            </thead>
            <tbody>`;
        
        reportData.results.forEach((row, index) => {
            tableHTML += `<tr>
                <td>${index+1}</td>
                <td>${escapeHtml(row.course_name)}</td>
                <td>${escapeHtml(row.suggestions)}</td>
            </tr>`;
        });
        
        tableHTML += `</tbody></table>`;
        openPrintWindow("Suggestions Only", tableHTML);
    }

    function printCourseAnalysis() {
        let content = `
            <div style="text-align: center; margin-bottom: 30px; background: #f0f7ff; padding: 20px; border-radius: 10px;">
                <div style="font-size: 24px; font-weight: bold; color: #003366;">
                    Overall Rating: ${reportData.overallRating.toFixed(2)}/5
                </div>
                <div style="font-size: 20px; color: #FFD700; margin: 10px 0;">
                    ${generateRatingStars(Math.round(reportData.overallRating))}
                </div>
                <div>Based on ${reportData.feedbackCount} feedback entries</div>
            </div>
            
            <div style="font-size: 18px; color: #003366; margin: 20px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Rating Distribution</div>
            <table>
                <thead>
                    <tr>
                        <th>Rating</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>`;
        
        reportData.ratingDistribution.forEach(data => {
            content += `<tr>
                <td>
                    ${data.rating} 
                    <span class="rating-stars">${generateRatingStars(data.rating)}</span>
                </td>
                <td>${data.count}</td>
                <td>${data.percentage}%</td>
            </tr>`;
        });
        
        content += `</tbody></table>`;
        openPrintWindow("Course Analysis Report", content);
    }

    function printLectureAnalysis() {
        let tableHTML = `<div style="font-size: 18px; color: #003366; margin: 20px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Lecture-wise Ratings</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Lecture #</th>
                    <th>Relevance</th>
                    <th>Contents</th>
                    <th>Presentation</th>
                    <th>Average</th>
                </tr>
            </thead>
            <tbody>`;
        
        reportData.lectureDailyRatings.forEach(lecture => {
            const avgRating = (lecture.avg_relevance + lecture.avg_contents + lecture.avg_presentation) / 3;
            tableHTML += `<tr>
                <td>${lecture.formatted_date}</td>
                <td>${lecture.lecture_number}</td>
                <td>${lecture.avg_relevance.toFixed(2)}</td>
                <td>${lecture.avg_contents.toFixed(2)}</td>
                <td>${lecture.avg_presentation.toFixed(2)}</td>
                <td>${avgRating.toFixed(2)}</td>
            </tr>`;
        });
        
        tableHTML += `</tbody></table>`;

        // Add overall lecture ratings
        const lectureOverall = reportData.lectureRatings;
        const overallAvgRelevance = lectureOverall.avg_relevance || 0;
        const overallAvgContents = lectureOverall.avg_contents || 0;
        const overallAvgPresentation = lectureOverall.avg_presentation || 0;
        const overallAvg = (overallAvgRelevance + overallAvgContents + overallAvgPresentation) / 3;

        let overallSection = `
            <div style="font-size: 18px; color: #003366; margin: 20px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Overall Lecture Ratings</div>
            <div style="background: #f0f7ff; padding: 20px; border-radius: 10px; margin-top: 20px;">
                <table>
                    <tr>
                        <th>Category</th>
                        <th>Rating</th>
                    </tr>
                    <tr>
                        <td>Relevance</td>
                        <td>${overallAvgRelevance.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td>Contents</td>
                        <td>${overallAvgContents.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td>Presentation</td>
                        <td>${overallAvgPresentation.toFixed(2)}</td>
                    </tr>
                    <tr style="font-weight: bold;">
                        <td>Overall Average</td>
                        <td>${overallAvg.toFixed(2)}</td>
                    </tr>
                </table>
            </div>
        `;

        openPrintWindow("Lecture-wise Analysis Report", tableHTML + overallSection);
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

    function loadDistricts(stateName) {
        if (!stateName) {
            document.getElementById("districtSelect").innerHTML = '<option value="">-- Select District --</option>';
            return;
        }

        const districtSelect = document.getElementById("districtSelect");
        districtSelect.innerHTML = '<option value="">Loading...</option>';

        fetch('get-districts.php?state=' + encodeURIComponent(stateName))
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

    // Initialize districts if state is already selected
    $(document).ready(function() {
        const selectedState = "<?= $selectedState ?>";
        if (selectedState) {
            loadDistricts(selectedState);
        }
        
        // State select change handler
        $('#stateSelect').on('change', function() {
            loadDistricts(this.value);
        });
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });
    </script>
</body>
</html>