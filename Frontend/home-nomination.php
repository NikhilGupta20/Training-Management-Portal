<?php
//home-nomination.php
// Database connection setup
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

// Initialize filter variables
$whereClauses = [];
$params = [];
$types = "";

// Process filters if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_filters'])) {
    // Date Range Filter
    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
        $whereClauses[] = "start_date BETWEEN ? AND ?";
        $params[] = $_POST['start_date'];
        $params[] = $_POST['end_date'];
        $types .= "ss";
    }
    
    // Course Filter
    if (!empty($_POST['course'])) {
        $whereClauses[] = "course_name = ?";
        $params[] = $_POST['course'];
        $types .= "s";
    }
    
    // State Filter
    if (!empty($_POST['state'])) {
        $whereClauses[] = "state_name = ?";
        $params[] = $_POST['state'];
        $types .= "s";
    }
    
    // District Filter
    if (!empty($_POST['district'])) {
        $whereClauses[] = "district_name = ?";
        $params[] = $_POST['district'];
        $types .= "s";
    }
    
    // Gender Filter
    if (!empty($_POST['gender'])) {
        $whereClauses[] = "gender = ?";
        $params[] = $_POST['gender'];
        $types .= "s";
    }
    
    // Name Search
    if (!empty($_POST['search_name'])) {
        $whereClauses[] = "participant_name LIKE ?";
        $params[] = '%' . $_POST['search_name'] . '%';
        $types .= "s";
    }
}

// Build the base query
$query = "SELECT 
            participant_name, 
            course_name, 
            start_date, 
            end_date, 
            state_name, 
            district_name, 
            email, 
            residential_phone AS phone 
          FROM accepted_participants";

// Add WHERE clauses if any filters are applied
if (!empty($whereClauses)) {
    $query .= " WHERE " . implode(" AND ", $whereClauses);
}

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch distinct values for filter dropdowns
$courses = $conn->query("SELECT DISTINCT course_name FROM accepted_participants ORDER BY course_name");
$states = $conn->query("SELECT DISTINCT state_name FROM accepted_participants ORDER BY state_name");
$districts = $conn->query("SELECT DISTINCT district_name FROM accepted_participants ORDER BY district_name");

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Accepted Participants | NCRB Training Portal</title>
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

        /* Dashboard Section */
        .dashboard {
            max-width: 1400px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 30px;
            padding-top: 20px;
        }

        .dashboard-header h1 {
            font-size: 2.2rem;
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

        /* Filter Section */
        .filter-container {
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            padding: 25px;
            animation: fadeIn 0.6s ease-out forwards;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .filter-title {
            font-size: 1.4rem;
            color: var(--primary);
            font-weight: 600;
        }

        .reset-btn {
            background: transparent;
            color: var(--secondary);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .reset-btn:hover {
            color: var(--accent);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
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

        .form-control {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.2);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .search-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .search-container .form-control {
            flex: 1;
        }

        .search-btn {
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            padding: 0 20px;
            cursor: pointer;
            transition: var(--transition);
        }

        .search-btn:hover {
            background: var(--secondary);
        }

        .submit-btn {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-top: 10px;
        }

        .submit-btn:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Results Section */
        .results-container {
            background: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 25px;
            animation: fadeIn 0.6s ease-out forwards;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .results-title {
            font-size: 1.4rem;
            color: var(--primary);
            font-weight: 600;
        }

        .results-count {
            font-weight: 600;
            color: var(--secondary);
        }

        /* Table Styles */
        .participants-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .participants-table th {
            background: var(--primary);
            color: var(--white);
            text-align: left;
            padding: 15px;
            font-weight: 600;
            position: sticky;
            top: 0;
        }

        .participants-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .participants-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .participants-table tr:hover {
            background-color: #f1f7ff;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--gray);
            font-style: italic;
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

        /* Responsive Design */
        @media (max-width: 992px) {
            body {
                padding-top: 170px;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
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
            
            .dashboard-header h1 {
                font-size: 1.8rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .participants-table {
                display: block;
                overflow-x: auto;
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
            
            .nav-content {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .login-links {
                justify-content: center;
            }
            
            .dashboard-header h1 {
                font-size: 1.6rem;
            }
            
            .filter-title, .results-title {
                font-size: 1.2rem;
            }
            
            .results-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
                <div class="nav-title">Accepted Participants List</div>
                <div class="login-links">
                    <div class="admin-login">
                        <a href="home-page.php"><i class="fas fa-home"></i> Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>Accepted Participants</h1>
            <p>View and filter the list of participants accepted for NCRB training programs</p>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-container">
            <div class="filter-header">
                <div class="filter-title">Filter Participants</div>
                <button type="button" class="reset-btn" id="resetFilters">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
            </div>
            
            <form method="POST" action="">
                <div class="filter-form">
                    <!-- Date Range -->
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?= isset($_POST['start_date']) ? $_POST['start_date'] : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?= isset($_POST['end_date']) ? $_POST['end_date'] : '' ?>">
                    </div>
                    
                    <!-- Course -->
                    <div class="form-group">
                        <label for="course">Course</label>
                        <select id="course" name="course" class="form-control">
                            <option value="">All Courses</option>
                            <?php while($course = $courses->fetch_assoc()): ?>
                                <option value="<?= $course['course_name'] ?>" 
                                    <?= isset($_POST['course']) && $_POST['course'] == $course['course_name'] ? 'selected' : '' ?>>
                                    <?= $course['course_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- State -->
                    <div class="form-group">
                        <label for="state">State</label>
                        <select id="state" name="state" class="form-control">
                            <option value="">All States</option>
                            <?php while($state = $states->fetch_assoc()): ?>
                                <option value="<?= $state['state_name'] ?>" 
                                    <?= isset($_POST['state']) && $_POST['state'] == $state['state_name'] ? 'selected' : '' ?>>
                                    <?= $state['state_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- District -->
                    <div class="form-group">
                        <label for="district">District</label>
                        <select id="district" name="district" class="form-control">
                            <option value="">All Districts</option>
                            <?php while($district = $districts->fetch_assoc()): ?>
                                <option value="<?= $district['district_name'] ?>" 
                                    <?= isset($_POST['district']) && $_POST['district'] == $district['district_name'] ? 'selected' : '' ?>>
                                    <?= $district['district_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Gender -->
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control">
                            <option value="">All Genders</option>
                            <option value="Male" <?= isset($_POST['gender']) && $_POST['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= isset($_POST['gender']) && $_POST['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= isset($_POST['gender']) && $_POST['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <!-- Name Search -->
                    <div class="form-group">
                        <label for="search_name">Search by Name</label>
                        <div class="search-container">
                            <input type="text" id="search_name" name="search_name" class="form-control" 
                                   placeholder="Enter participant name" 
                                   value="<?= isset($_POST['search_name']) ? $_POST['search_name'] : '' ?>">
                            <button type="submit" name="apply_filters" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="apply_filters" class="submit-btn">Apply Filters</button>
            </form>
        </div>
        
        <!-- Results Section -->
        <div class="results-container">
            <div class="results-header">
                <div class="results-title">Accepted Participants List</div>
                <div class="results-count"><?= $result->num_rows ?> Participants Found</div>
            </div>
            
            <div class="table-responsive">
                <table class="participants-table">
                    <thead>
                        <tr>
                            <th>Participant Name</th>
                            <th>Course Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>State</th>
                            <th>District</th>
                            <th>Email</th>
                            <th>Phone</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['participant_name']) ?></td>
                                    <td><?= htmlspecialchars($row['course_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($row['end_date'])) ?></td>
                                    <td><?= htmlspecialchars($row['state_name']) ?></td>
                                    <td><?= htmlspecialchars($row['district_name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="no-results">
                                    No participants found matching your criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
        // Update current date
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Reset filters functionality
            document.getElementById('resetFilters').addEventListener('click', function() {
                // Reset all form fields
                document.getElementById('start_date').value = '';
                document.getElementById('end_date').value = '';
                document.getElementById('course').selectedIndex = 0;
                document.getElementById('state').selectedIndex = 0;
                document.getElementById('district').selectedIndex = 0;
                document.getElementById('gender').selectedIndex = 0;
                document.getElementById('search_name').value = '';
                
                // Submit the form to reset results
                document.querySelector('form').submit();
            });
            
            // Initialize date fields with today's date if not set
            if (!document.getElementById('start_date').value) {
                const today = new Date();
                document.getElementById('start_date').value = today.toISOString().split('T')[0];
            }
            
            if (!document.getElementById('end_date').value) {
                const nextMonth = new Date();
                nextMonth.setMonth(nextMonth.getMonth() + 1);
                document.getElementById('end_date').value = nextMonth.toISOString().split('T')[0];
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