<?php
// rpctc_hyderabad_dashboard.php
session_start();

// Check if RPCTC admin is logged in
if (!isset($_SESSION['rpctc_logged_in']) || $_SESSION['rpctc_center'] !== 'Hyderabad') {
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

// Fetch admin details
$rpctc_username = $_SESSION['rpctc_username'];
$stmt = $conn->prepare("SELECT * FROM rpctc_admin_credentials WHERE center = 'Hyderabad' AND rpctc_username = ?");
$stmt->bind_param("s", $rpctc_username);
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
$username = htmlspecialchars($admin['rpctc_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>RPCTC Hyderabad Dashboard - NCRB Training Portal</title>
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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 50px 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 50px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out forwards, float 6s ease-in-out infinite;
        }

        .welcome-banner::before {
            content: "";
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-banner::after {
            content: "";
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
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

        /* Action Button Grid */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .action-button {
            background: #ffffff;
            border-radius: 14px;
            padding: 30px 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-align: center;
            border: 1px solid #e2e8f0;
            position: relative;
            text-decoration: none;
            display: block;
        }
        
        .action-button:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            border-color: #c2d6e6;
        }
        
        .action-button:hover .button-icon {
            transform: scale(1.15);
            color: #0066cc;
        }
        
        .button-icon {
            font-size: 42px;
            margin-bottom: 20px;
            color: #003366;
            transition: all 0.3s ease;
        }
        
        .button-title {
            font-size: 22px;
            font-weight: 700;
            color: #003366;
            margin-bottom: 12px;
        }
        
        .button-description {
            font-size: 16px;
            color: #718096;
            line-height: 1.6;
        }
        
        .action-button::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #003366, #0066cc);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .action-button:hover::after {
            transform: scaleX(1);
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
            
            .welcome-banner h2 {
                font-size: 2.4rem;
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
            
            .contact-methods {
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
            
            .welcome-banner h2 {
                font-size: 1.8rem;
            }
            
            .admin-info-container {
                justify-content: center;
                width: 100%;
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
                <div class="nav-title">RPCTC Hyderabad - Admin Dashboard</div>
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
        </div>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>RPCTC Hyderabad - Training Management</h2>
            <p>Welcome, <strong><?php echo $username; ?></strong>. Manage all aspects of the Hyderabad RPCTC training portal.</p>
        </div>
        
        <!-- Action Buttons - Reorganized as per requirements -->
        <div class="action-grid">
            <!-- 1. Training Programs -->
            <a href="admin-calendar.php" class="action-button">
                <div class="button-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="button-title">Training Programs</div>
                <div class="button-description">Create and manage training schedules</div>
            </a>
            
            <!-- 2. Nomination Management -->
            <a href="admin-nomination.php" class="action-button">
                <div class="button-icon"><i class="fas fa-clipboard"></i></div>
                <div class="button-title">Nomination Management</div>
                <div class="button-description">Manage training nominations and approvals</div>
            </a>
            
            <!-- 3. Hostel Management -->
            <a href="admin-hostel-management.php" class="action-button">
                <div class="button-icon"><i class="fas fa-hotel"></i></div>
                <div class="button-title">Hostel Management</div>
                <div class="button-description">View and manage hostel applications</div>
            </a>
            
            <!-- 4. Trainee Management -->
            <a href="admin-registration.php" class="action-button">
                <div class="button-icon"><i class="fas fa-users"></i></div>
                <div class="button-title">Trainee Management</div>
                <div class="button-description">View and manage training participants</div>
            </a>
            
            <!-- 5. Faculty Management -->
            <a href="admin-faculty-management.php" class="action-button">
                <div class="button-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="button-title">Faculty Management</div>
                <div class="button-description">Manage faculty data categorized by courses</div>
            </a>
            
            <!-- 6. Feedback Reports -->
            <a href="admin-feedback.php" class="action-button">
                <div class="button-icon"><i class="fas fa-chart-line"></i></div>
                <div class="button-title">Feedback Reports</div>
                <div class="button-description">Generate reports and view analytics</div>
            </a>
            
            <!-- 7. Administrative Report -->
            <a href="admin-report.php" class="action-button">
                <div class="button-icon"><i class="fas fa-file-alt"></i></div>
                <div class="button-title">Administrative Report</div>
                <div class="button-description">Generate consolidated reports for the course</div>
            </a>
            
            <!-- 8. Training Gallery -->
            <a href="admin-gallery.php" class="action-button">
                <div class="button-icon"><i class="fas fa-images"></i></div>
                <div class="button-title">Training Gallery</div>
                <div class="button-description">Manage photos and videos for courses</div>
            </a>
            
            <!-- 9. Training Materials -->
            <a href="admin-materials.php" class="action-button">
                <div class="button-icon"><i class="fas fa-book"></i></div>
                <div class="button-title">Training Materials</div>
                <div class="button-description">Upload and manage resources</div>
            </a>
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
        // ===== SCRIPT INITIALIZATION =====
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>