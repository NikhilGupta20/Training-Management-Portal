<?php
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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get admin username from session
$admin_username = $_SESSION['admin_username'];

// Fetch courses for filter dropdown
$courses = [];
$courses_result = $conn->query("SELECT DISTINCT course_name FROM training_events");
if ($courses_result) {
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
}

// Initialize filter variables
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$course_name = $_GET['course'] ?? '';

// Prepare report data array
$report_data = [];

// Build query based on filters
if (!empty($course_name)) {
    $query = "
        SELECT 
            te.id AS course_id,
            te.course_name,
            te.start_date,
            te.end_date,
            COALESCE((SELECT SUM(amount) FROM expenses WHERE course_id = te.id), 0) AS total_expense,
            COALESCE((SELECT AVG(rating) FROM feedback WHERE course_name = te.course_name), 0) AS course_rating,
            COALESCE((SELECT AVG(lr.rating) 
                     FROM feedback fb
                     JOIN lecture_ratings lr ON fb.feedback_id = lr.feedback_id
                     WHERE fb.course_name = te.course_name), 0) AS lecture_rating
        FROM training_events te
        WHERE te.course_name = ?
    ";
    
    $params = [];
    $types = "s";
    $params[] = &$course_name;
    
    // Add date range if provided
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND te.start_date BETWEEN ? AND ?";
        $types .= "ss";
        $params[] = &$start_date;
        $params[] = &$end_date;
    }
    
    $stmt = $conn->prepare($query);
    
    // Dynamic binding based on parameters
    if (!empty($params)) {
        $bind_params = array_merge([$types], $params);
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    $stmt->close();
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Training Reports - NCRB Training Portal</title>
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

    .logout-btn {
        color: white;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.2rem;
        margin-left: 10px;
        transition: var(--transition);
    }

    .logout-btn:hover {
        color: var(--accent);
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

    @media (max-width: 768px) {
        .filters {
            grid-template-columns: 1fr;
        }
        .filter-group.date-range-group {
            grid-column: span 1;
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

    /* ===== RESULTS TABLE ===== */
    .results-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 25px;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .results-table th {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 16px 15px;
        text-align: left;
        font-weight: 600;
    }

    .results-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #e5e7eb;
        color: #4b5563;
    }

    .results-table tr:nth-child(even) {
        background-color: #f9fafb;
    }

    .results-table tr:hover {
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

    /* ===== PROFESSIONAL FOOTER ===== */
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

    @media (max-width: 768px) {
        body {
            padding-top: 180px;
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
                <div class="nav-title">National Crime Records Bureau - Training Reports</div>
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
            <h1>Training Reports</h1>
            <p>Consolidated expense and evaluation reports</p>
        </div>
        
        <!-- Filter Form -->
        <form method="get" class="filters">
            <!-- Date Range Filter -->
            <div class="filter-group date-range-group">
                <label>Date Range</label>
                <div class="date-range-container">
                    <input type="date" name="start_date" id="startDate" value="<?= htmlspecialchars($start_date) ?>" style="flex:1">
                    <span class="date-separator">to</span>
                    <input type="date" name="end_date" id="endDate" value="<?= htmlspecialchars($end_date) ?>" style="flex:1">
                </div>
            </div>
            
            <div class="filter-group">
                <label for="courseSelect">Course</label>
                <select name="course" id="courseSelect" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= htmlspecialchars($course['course_name']) ?>" <?= $course_name === $course['course_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="filter-btn">Generate Report</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="admin-report.php" class="reset-btn">Reset Filters</a>
            </div>
        </form>

        <!-- Results Table -->
        <?php if (!empty($report_data)): ?>
            <div id="reportSection">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Course Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Expense (₹)</th>
                            <th>Course Rating</th>
                            <th>Lecture Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; ?>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?= $serial++ ?></td>
                                <td><?= htmlspecialchars($row['course_name']) ?></td>
                                <td><?= date("d-m-Y", strtotime($row['start_date'])) ?></td>
                                <td><?= date("d-m-Y", strtotime($row['end_date'])) ?></td>
                                <td>₹<?= number_format($row['total_expense'], 2) ?></td>
                                <td>
                                    <div class="rating-stars">
                                        <?php 
                                            $course_rating = round($row['course_rating']);
                                            echo str_repeat('★', $course_rating) . str_repeat('☆', 5 - $course_rating);
                                            echo " ($course_rating/5)";
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="rating-stars">
                                        <?php 
                                            $lecture_rating = round($row['lecture_rating']);
                                            echo str_repeat('★', $lecture_rating) . str_repeat('☆', 5 - $lecture_rating);
                                            echo " ($lecture_rating/5)";
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!empty($course_name) || !empty($start_date) || !empty($end_date)): ?>
            <p class="no-data">
                No report data found for the selected filters.
            </p>
        <?php else: ?>
            <p class="no-data">
                Please select a course to generate the report.
            </p>
        <?php endif; ?>

        <div class="btn-container">
            <?php if (!empty($report_data)): ?>
                <button onclick="printReport()" class="action-btn print-btn">Print Report</button>
            <?php endif; ?>
            <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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

    function printReport() {
        // Create a print container
        const printContainer = document.createElement('div');
        printContainer.id = 'print-container';
        
        // Add title with logos
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
        subtitle.textContent = 'Training Report';
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
        <?php if (!empty($start_date) && !empty($end_date)): ?>
            filters.push(`Date Range: <?= htmlspecialchars($start_date) ?> to <?= htmlspecialchars($end_date) ?>`);
        <?php endif; ?>
        <?php if (!empty($course_name)): ?>
            filters.push(`Course: <?= htmlspecialchars($course_name) ?>`);
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
        
        // Add the report table
        const reportTable = document.getElementById('reportSection').cloneNode(true);
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
        const printWindow = window.open('', '', 'width=1000,height=700');
        printWindow.document.write('<html><head><title>NCRB Training Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
        printWindow.document.write('th { background-color: #003366; color: white; padding: 10px; border: 1px solid #003366; }');
        printWindow.document.write('td { padding: 8px; border: 1px solid #ddd; }');
        printWindow.document.write('.rating-stars { color: #FFD700; font-size: 18px; }');
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

    // Auto-hide messages after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    </script>
</body>
</html>