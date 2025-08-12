<?php
// home-present-year.php
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ncrb_training";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create training_events table if not exists
$sql = "CREATE TABLE IF NOT EXISTS training_events (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50) NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    course_name_hindi VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    location VARCHAR(255) NOT NULL,
    eligibility TEXT,
    objectives TEXT,
    duration INT(3),
    color VARCHAR(20) DEFAULT '#ff6b6b',
    status ENUM('Completed', 'Active', 'Upcoming') DEFAULT 'Upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    error_log("Table creation error: " . $conn->error);
}

// Update event statuses automatically
$update_sql = "UPDATE training_events 
               SET status = CASE
                   WHEN CURDATE() > end_date THEN 'Completed'
                   WHEN CURDATE() BETWEEN start_date AND end_date THEN 'Active'
                   ELSE 'Upcoming'
               END";

$conn->query($update_sql);

// Get current financial year
function getFinancialYear($date) {
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    return ($month < 4) ? ($year - 1) . '-' . $year : $year . '-' . ($year + 1);
}

$financialYear = getFinancialYear(date('Y-m-d'));
[$startYear, $endYear] = array_map('intval', explode('-', $financialYear));

// Fetch training events ordered by status and start date (Active > Upcoming > Completed)
$events = [];
$sql = "SELECT * FROM training_events 
        WHERE (
            (YEAR(start_date) = ? AND MONTH(start_date) >= 4) 
            OR 
            (YEAR(start_date) = ? AND MONTH(start_date) <= 3)
        )
        ORDER BY 
            FIELD(status, 'Active', 'Upcoming', 'Completed'),
            start_date";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $startYear, $endYear);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$conn->close();

// Identify active/upcoming event for auto-scroll
$scrollTargetId = null;
foreach ($events as $event) {
    if ($event['status'] === 'Active' || $event['status'] === 'Upcoming') {
        $scrollTargetId = $event['id'];
        break;
    }
}

// Color mapping
$colorMap = [
    '#ff6b6b' => 'Red',
    '#4da6ff' => 'Blue',
    '#6bff91' => 'Green',
    '#ffd96b' => 'Yellow',
    '#b96bff' => 'Purple',
    '#ff9e6b' => 'Orange',
    '#ff6bc9' => 'Pink',
    '#6bf0ff' => 'Cyan',
    '#6b8cff' => 'Royal Blue',
    '#6bffd0' => 'Aqua',
    '#d0ff6b' => 'Lime',
    '#ffb96b' => 'Light Orange',
    '#c56bff' => 'Lavender',
    '#ff6b8b' => 'Rose',
    '#6bff6f' => 'Mint'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Present Year Training Calendar | NCRB</title>
    
    <!-- Favicon links -->
    <link rel="apple-touch-icon" sizes="57x57" href="https://www.ncrb.gov.in/static/dist/favicon/apple-icon-57x57.png">
    <link rel="icon" type="image/png" sizes="192x192" href="https://www.ncrb.gov.in/static/dist/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="https://www.ncrb.gov.in/static/dist/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="https://www.ncrb.gov.in/static/dist/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://www.ncrb.gov.in/static/dist/favicon/favicon-16x16.png">
    <link rel="manifest" href="https://www.ncrb.gov.in/static/dist/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="https://www.ncrb.gov.in/static/dist/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    
    <!-- Font Awesome -->
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

        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            color: var(--dark);
            min-height: 100vh;
            padding-top: 180px;
        }

        /* Animation Keyframes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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

        /* Login links container */
        .login-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .separator {
            color: var(--white);
            font-weight: bold;
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
            padding-top: 20px;
        }

        .dashboard-header img {
            max-height: 120px;
            margin-bottom: 20px;
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

        /* Calendar Specific Styles */
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

        /* PDF Viewer Container */
        .pdf-viewer-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
            max-width: 1400px;
            text-align: center;
        }

        .pdf-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .pdf-viewer {
            width: 100%;
            height: 700px;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .pdf-note {
            margin-top: 15px;
            font-size: 1rem;
            color: #666;
            font-style: italic;
        }

        .course-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
            max-width: 1400px;
        }

        .course-table-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }
        
        .participant-box {
            background: #f0f7ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            border-left: 4px solid var(--accent);
        }
        
        .participant-box p {
            margin-bottom: 15px;
            font-size: 1.1rem;
            color: #002147;
        }
        
        .participant-link {
            display: inline-block;
            background: var(--accent);
            color: white;
            padding: 12px 25px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .participant-link:hover {
            background: #e68a00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .course-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            table-layout: fixed;
        }

        .course-table th, .course-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e9ff;
            word-wrap: break-word;
        }

        .course-table th {
            background-color: #f0f7ff;
            color: #003366;
            font-weight: 600;
        }

        .course-table tr:hover {
            background-color: #f8fbff;
        }

        .course-table tr.highlight-row {
            background-color: #fff9db;
            animation: highlightPulse 2s ease-in-out infinite;
        }

        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            margin-right: 8px;
            vertical-align: middle;
            border: 1px solid #ddd;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-completed {
            background-color: #e9ecef;
            color: #6c757d;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-upcoming {
            background-color: #fff3cd;
            color: #856404;
        }

        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 40px auto;
            max-width: 100%;
            overflow: hidden;
        }
        
        .year-selector {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .current-year {
            font-size: 1.8rem;
            font-weight: 600;
            color: #002147;
            min-width: 150px;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }
        
        .month-container {
            background: #f8fbff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e0e9ff;
        }
        
        .month-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        
        .month-header {
            background: linear-gradient(to right, #0a3d6d, #002147);
            color: white;
            padding: 15px;
            font-size: 1.3rem;
            font-weight: 600;
            text-align: center;
        }
        
        .month-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            padding: 15px;
            gap: 8px;
        }
        
        .day-header {
            text-align: center;
            font-weight: 600;
            color: #0066cc;
            padding: 8px 0;
            font-size: 0.9rem;
        }
        
        .day-cell {
            text-align: center;
            padding: 10px 0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            cursor: default;
            position: relative;
            border: 1px solid #e6f0ff;
        }
        
        .day-cell:hover {
            background: #e6f0ff;
        }
        
        .empty-cell {
            visibility: hidden;
        }
        
        .day-cell.event-start {
            border-top-left-radius: 15px;
            border-bottom-left-radius: 15px;
            background-color: var(--event-color) !important;
            color: white;
        }
        
        .day-cell.event-middle {
            background-color: var(--event-color) !important;
            color: white;
        }
        
        .day-cell.event-end {
            border-top-right-radius: 15px;
            border-bottom-right-radius: 15px;
            background-color: var(--event-color) !important;
            color: white;
        }
        
        .tooltip {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-width: 300px;
            font-size: 14px;
            line-height: 1.5;
            display: none;
        }
        
        .tooltip strong {
            color: #002147;
        }
        
        .tooltip-event {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .tooltip-event:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            padding: 8px 15px;
            background: #f0f7ff;
            border-radius: 30px;
        }
        
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ff6b6b;
        }
        
        /* Highlight animation */
        @keyframes highlightPulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 204, 0, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 204, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 204, 0, 0); }
        }
        
        /* Footer */
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
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
            font-weight: normal;
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
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: #2c3e50;
            color: #ecf0f1;
            border: 1px solid #1a252f;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
            position: relative;
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
        .developer-contact {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .developer-contact h3 {
            color: #f39c12;
            margin-bottom: 20px;
            font-size: 1.4rem;
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
        }
        
        .contact-info i {
            color: #f39c12;
            width: 22px;
        }
        
        .contact-info a {
            color: #3498db;
            transition: var(--transition);
            text-decoration: none;
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px 10px;
            font-size: 1rem;
            border-radius: 6px;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
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

        /* Developer link styling */
        .copyright a#developer-contact-link {
            font-weight: normal; /* Normal weight to match surrounding text */
            color: inherit; /* Inherit parent color */
            text-decoration: none; /* Remove underline */
            transition: var(--transition); /* Smooth transition */
        }
        
        .copyright a#developer-contact-link:hover {
            color: var(--accent); /* Accent color on hover */
            text-decoration: underline; /* Underline on hover */
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .calendar-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .pdf-viewer {
                height: 600px;
            }
        }
        
        @media (max-width: 900px) {
            .calendar-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .pdf-viewer {
                height: 500px;
            }
            
            .course-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .current-year {
                font-size: 1.5rem;
            }
            
            body {
                padding-top: 240px;
            }
            
            .pdf-viewer {
                height: 400px;
            }
        }
        
        @media (max-width: 600px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }
            
            body {
                padding-top: 280px;
            }
            
            .participant-box {
                padding: 15px;
            }
            
            .participant-link {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            
            .pdf-viewer {
                height: 350px;
            }
        }
        
        @media (max-width: 480px) {
            .page-title {
                font-size: 1.7rem;
            }
            
            .year-selector {
                gap: 10px;
            }
            
            .current-year {
                min-width: 120px;
                font-size: 1.3rem;
            }
            
            body {
                padding-top: 320px;
            }
            
            .pdf-viewer {
                height: 300px;
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
                <div class="nav-title">National Crime Records Bureau</div>
                <div class="login-links">
                    <div class="admin-login">
                        <a href="home-page.php"><i class="fas fa-home"></i> Back to Home Page</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-header">
            <h1 class="page-title">Training Calendar for Current Academic Year</h1>
        </div>
        
        <!-- PDF Viewer -->
        <div class="pdf-viewer-container">
            <h2 class="pdf-title">Training Calendar PDF - <?= htmlspecialchars($financialYear) ?></h2>
            <iframe src="Training Calendar 2025-26.pdf" class="pdf-viewer"></iframe>
            <p class="pdf-note">This PDF contains the complete training schedule for the current academic year</p>
        </div>
        
        <!-- Training Programs Table -->
        <div class="course-table-container" id="course-table-container">
            <h2 class="course-table-title">Training Programs for <?= htmlspecialchars($financialYear) ?></h2>
            
            <div class="participant-box">
                <p>Submit nominations for training programs through the Participants Form:</p>
                <a href="http://localhost/NCRB/form-participant.php" class="participant-link">
                    <i class="fas fa-external-link-alt"></i> Access Participant Form
                </a>
            </div>
            
            <table class="course-table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Duration</th>
                        <th>Objectives</th>
                        <th>Eligibility</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Color</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($events) > 0): ?>
                        <?php foreach($events as $event): 
                            $start = new DateTime($event['start_date']);
                            $end = new DateTime($event['end_date']);
                            $duration = $start->diff($end)->days + 1;
                            
                            // Format dates as "dd Month yyyy"
                            $startFormatted = $start->format('d F Y');
                            $endFormatted = $end->format('d F Y');
                            
                            // Status badge
                            $statusClass = 'status-' . strtolower($event['status']);
                            
                            // Highlight row for active/upcoming events
                            $highlightClass = ($event['status'] === 'Active' || $event['status'] === 'Upcoming') ? 'highlight-row' : '';
                        ?>
                            <tr class="<?= $highlightClass ?>" 
                                id="<?= ($event['id'] == $scrollTargetId) ? 'scroll-target' : '' ?>">
                                <td><?= htmlspecialchars($event['course_code']) ?></td>
                                <td><?= htmlspecialchars($event['course_name']) ?></td>
                                <td><?= $startFormatted ?></td>
                                <td><?= $endFormatted ?></td>
                                <td><?= $duration ?> days</td>
                                <td><?= htmlspecialchars($event['objectives'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($event['eligibility'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($event['location']) ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= $event['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="color-preview" style="background: <?= htmlspecialchars($event['color']) ?>"></span>
                                    <?= $colorMap[$event['color']] ?? htmlspecialchars($event['color']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">No training programs scheduled</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Calendar View -->
        <div class="calendar-container">
            <div class="year-selector">
                <div class="current-year" id="current-financial-year"><?= htmlspecialchars($financialYear) ?></div>
            </div>
            
            <div class="calendar-grid" id="calendar-grid">
                <!-- JavaScript will populate this -->
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-dot"></div>
                    <span>Training Program Day</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-info-circle"></i>
                    <span>Hover over dates for program details</span>
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
            This portal is developed and maintained by <a href="#" id="developer-contact-link">Nikhil Gupta</a> and the Training Division of NCRB.
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
                        <p><i class="fas fa-envelope"></i> <a href="mailto:nikhillguptaa.2004@gmail.com" class="mailto-link">nikhillguptaa.2004@gmail.com</a></p>
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
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Developer contact modal functionality
            const developerModal = document.getElementById('developerContactModal');
            const developerLink = document.getElementById('developer-contact-link');
            const closeModal = document.querySelector('.close-modal');
            
            developerLink.addEventListener('click', (e) => {
                e.preventDefault();
                developerModal.style.display = 'flex';
            });
            
            closeModal.addEventListener('click', () => {
                developerModal.style.display = 'none';
            });
            
            window.addEventListener('click', (e) => {
                if (e.target === developerModal) {
                    developerModal.style.display = 'none';
                }
            });
            
            // Get training events from PHP
            const trainingEvents = <?= json_encode($events) ?>;
            
            // Format date as "dd Month yyyy"
            function formatEventDate(dateStr) {
                const date = new Date(dateStr);
                return date.toLocaleDateString('en-US', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
            }
            
            // Precompute event dates for quick lookup
            const eventDates = {};
            trainingEvents.forEach(event => {
                const start = new Date(event.start_date);
                const end = new Date(event.end_date);
                const current = new Date(start);
                
                while (current <= end) {
                    const dateStr = current.toISOString().split('T')[0];
                    if (!eventDates[dateStr]) {
                        eventDates[dateStr] = [];
                    }
                    eventDates[dateStr].push(event);
                    current.setDate(current.getDate() + 1);
                }
            });

            // Financial year from PHP
            const financialYear = "<?= $financialYear ?>";
            const [startYear, endYear] = financialYear.split('-').map(Number);
            
            // Months array for financial year (April to March)
            const months = [
                { name: "April", days: 30, startYear: startYear, monthIndex: 3 },
                { name: "May", days: 31, startYear: startYear, monthIndex: 4 },
                { name: "June", days: 30, startYear: startYear, monthIndex: 5 },
                { name: "July", days: 31, startYear: startYear, monthIndex: 6 },
                { name: "August", days: 31, startYear: startYear, monthIndex: 7 },
                { name: "September", days: 30, startYear: startYear, monthIndex: 8 },
                { name: "October", days: 31, startYear: startYear, monthIndex: 9 },
                { name: "November", days: 30, startYear: startYear, monthIndex: 10 },
                { name: "December", days: 31, startYear: startYear, monthIndex: 11 },
                { name: "January", days: 31, startYear: endYear, monthIndex: 0 },
                { name: "February", days: 28, startYear: endYear, monthIndex: 1 },
                { name: "March", days: 31, startYear: endYear, monthIndex: 2 }
            ];
            
            // Adjust February for leap years
            const isLeapYear = (year) => (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
            if (isLeapYear(endYear)) {
                months.find(m => m.name === "February").days = 29;
            }
            
            // Day names
            const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
            
            // Generate calendar
            function generateCalendar() {
                const calendarGrid = document.getElementById('calendar-grid');
                calendarGrid.innerHTML = '';
                
                // Create tooltip element
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.setAttribute('role', 'tooltip');
                tooltip.setAttribute('aria-hidden', 'true');
                document.body.appendChild(tooltip);
                
                months.forEach(month => {
                    // Create month container
                    const monthContainer = document.createElement('div');
                    monthContainer.className = 'month-container';
                    monthContainer.setAttribute('aria-label', `${month.name} ${month.startYear}`);
                    
                    // Month header
                    const monthHeader = document.createElement('div');
                    monthHeader.className = 'month-header';
                    monthHeader.textContent = `${month.name} ${month.startYear}`;
                    
                    // Month grid
                    const monthGrid = document.createElement('div');
                    monthGrid.className = 'month-grid';
                    monthGrid.setAttribute('role', 'grid');
                    
                    // Add day headers
                    dayNames.forEach(day => {
                        const dayHeader = document.createElement('div');
                        dayHeader.className = 'day-header';
                        dayHeader.textContent = day;
                        dayHeader.setAttribute('aria-label', day);
                        monthGrid.appendChild(dayHeader);
                    });
                    
                    // Get first day of the month
                    const firstDay = new Date(month.startYear, month.monthIndex, 1).getDay();
                    
                    // Add empty cells for days before the first day
                    for (let i = 0; i < firstDay; i++) {
                        const emptyCell = document.createElement('div');
                        emptyCell.className = 'day-cell empty-cell';
                        emptyCell.setAttribute('aria-hidden', 'true');
                        monthGrid.appendChild(emptyCell);
                    }
                    
                    // Add days
                    for (let day = 1; day <= month.days; day++) {
                        const dayCell = document.createElement('div');
                        dayCell.className = 'day-cell';
                        dayCell.textContent = day;
                        dayCell.setAttribute('aria-label', `${month.name} ${day}, ${month.startYear}`);
                        
                        // Format date for comparison (YYYY-MM-DD)
                        const dateStr = `${month.startYear}-${String(month.monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        
                        // Check if date has event
                        if (eventDates[dateStr]) {
                            const events = eventDates[dateStr];
                            // Use the first event's color for the indicator
                            const eventColor = events[0].color || '#ff6b6b';
                            dayCell.style.setProperty('--event-color', eventColor);
                            
                            // Determine event position in month
                            const event = events[0];
                            const eventStart = new Date(event.start_date);
                            const eventEnd = new Date(event.end_date);
                            const currentDate = new Date(dateStr);
                            
                            // Check position in event
                            if (currentDate.getTime() === eventStart.getTime()) {
                                dayCell.classList.add('event-start');
                            } else if (currentDate.getTime() === eventEnd.getTime()) {
                                dayCell.classList.add('event-end');
                            } else {
                                dayCell.classList.add('event-middle');
                            }
                            
                            // Create tooltip content
                            let tooltipContent = '';
                            events.forEach(ev => {
                                const startDate = formatEventDate(ev.start_date);
                                const endDate = formatEventDate(ev.end_date);
                                tooltipContent += `
                                    <div class="tooltip-event">
                                        <div><strong>${ev.course_code || 'Training Program'}</strong></div>
                                        <div>${ev.course_name || ''}</div>
                                        <div>${startDate} - ${endDate}</div>
                                        <div>${ev.location || 'NCRB Training Center'}</div>
                                        <div><span class="status-badge status-${ev.status.toLowerCase()}">${ev.status}</span></div>
                                    </div>
                                `;
                            });
                            
                            // Add tooltip functionality
                            dayCell.addEventListener('mouseenter', (e) => {
                                const rect = dayCell.getBoundingClientRect();
                                const scrollTop = window.scrollY || document.documentElement.scrollTop;
                                const scrollLeft = window.scrollX || document.documentElement.scrollLeft;
                                
                                tooltip.innerHTML = tooltipContent;
                                tooltip.style.display = 'block';
                                tooltip.style.left = `${rect.left + scrollLeft}px`;
                                tooltip.style.top = `${rect.bottom + scrollTop + 5}px`;
                                
                                // Adjust if tooltip goes off screen
                                const tooltipRect = tooltip.getBoundingClientRect();
                                if (tooltipRect.right > window.innerWidth) {
                                    tooltip.style.left = `${window.innerWidth - tooltipRect.width - 10}px`;
                                }
                                if (tooltipRect.bottom > window.innerHeight) {
                                    tooltip.style.top = `${rect.top + scrollTop - tooltipRect.height - 5}px`;
                                }
                            });
                            
                            dayCell.addEventListener('mouseleave', () => {
                                tooltip.style.display = 'none';
                            });
                            
                            // Keyboard accessibility
                            dayCell.addEventListener('focus', (e) => {
                                const rect = dayCell.getBoundingClientRect();
                                const scrollTop = window.scrollY || document.documentElement.scrollTop;
                                const scrollLeft = window.scrollX || document.documentElement.scrollLeft;
                                
                                tooltip.innerHTML = tooltipContent;
                                tooltip.style.display = 'block';
                                tooltip.style.left = `${rect.left + scrollLeft}px`;
                                tooltip.style.top = `${rect.bottom + scrollTop + 5}px`;
                            });
                            
                            dayCell.addEventListener('blur', () => {
                                tooltip.style.display = 'none';
                            });
                            
                            // Make day cell focusable
                            dayCell.setAttribute('tabindex', '0');
                        }
                        
                        monthGrid.appendChild(dayCell);
                    }
                    
                    monthContainer.appendChild(monthHeader);
                    monthContainer.appendChild(monthGrid);
                    calendarGrid.appendChild(monthContainer);
                });
            }
            
            // Initial calendar generation
            generateCalendar();
            
            // Header shadow on scroll
            const header = document.querySelector('.fixed-header');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.2)';
                } else {
                    header.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
                }
            });
            
            // Resize handling for tooltips
            window.addEventListener('resize', () => {
                document.querySelectorAll('.tooltip').forEach(t => t.style.display = 'none');
            });
            
            // Auto-scroll to active/upcoming event
            setTimeout(() => {
                const targetRow = document.getElementById('scroll-target');
                if (targetRow) {
                    targetRow.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center'
                    });
                }
            }, 1000);
        });
    </script>
</body>
</html>