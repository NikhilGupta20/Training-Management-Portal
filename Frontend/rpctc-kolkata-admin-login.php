<?php
// admin-login.php
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
    die("DB Connection failed: " . $conn->connect_error);
}

// Create tables if not exists - UPDATED to include center column
$sql = "CREATE TABLE IF NOT EXISTS rpctc_admin_credentials (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center VARCHAR(255) NOT NULL,
    rpctc_username VARCHAR(255) NOT NULL,
    rpctc_password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    // Error creating table
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // RPCTC Authentication
    if (isset($_POST['rpctc_login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM rpctc_admin_credentials WHERE rpctc_username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin) {
            // Compare passwords directly (plain text comparison)
            if ($password === $admin['rpctc_password']) {
                $_SESSION['rpctc_logged_in'] = true;
                $_SESSION['rpctc_username'] = $admin['rpctc_username'];
                $_SESSION['rpctc_center'] = $admin['center']; // ADDED center to session
                header("Location: rpctc-kolkata-dashboard.php");
                exit;
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "Username not found!";
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
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Admin Login | RPCTC Kolkata</title>
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
            padding-top: 80px;
            background-color: #f5f7fa;
            display: flex;
            flex-direction: column;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== LOGIN FORM CONTAINER ===== */
        .login-main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 20px;
        }

        .login-container {
            max-width: 500px;
            width: 100%;
            margin: 80px auto 50px;
        }

        /* ===== LOGIN FORM STYLES ===== */
        .login-card {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            animation: fadeIn 0.8s ease-out forwards;
            position: relative;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 25px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 600;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            position: relative;
            z-index: 2;
        }

        .login-header .dropdown-icon {
            margin-left: 10px;
            transition: var(--transition);
        }

        .login-header.active .dropdown-icon {
            transform: rotate(180deg);
        }

        .login-body {
            padding: 35px;
        }

        .login-error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }

        .login-form-group {
            margin-bottom: 25px;
        }

        .login-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }

        .login-input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--light);
        }
        
        .login-select {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
            background: var(--light);
        }

        .login-input:focus, .login-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.15);
            outline: none;
        }

        .login-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #00264d 0%, #0052a3 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--secondary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
        
        /* Card Logo Styles */
        .card-logo {
            text-align: center;
            margin: 25px 0;
        }
        
        .card-logo img {
            height: 100px;
            max-width: 100%;
            object-fit: contain;
        }

        /* Center Pill */
        .center-pill {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* ===== FOOTER STYLES ===== */
        .professional-footer {
            background: var(--primary);
            color: var(--white);
            padding: 50px 5% 30px;
            margin-top: auto;
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

        /* DEVELOPER LINK STYLING */
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
            .login-header {
                font-size: 1.4rem;
            }
        }

        @media (max-width: 768px) {
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
            
            .login-body {
                padding: 25px;
            }
            
            .footer-columns {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .contact-methods {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
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
            
            .login-header {
                padding: 20px;
                font-size: 1.4rem;
            }
            
            .login-body {
                padding: 20px;
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
                <div class="nav-title">RPCTC Kolkata</div>
                <!-- Back to Dashboard Button -->
                <div class="admin-login">
                    <a href="rpctc-kolkata-home-page.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Centered Login Form Section -->
    <div class="login-main-container">
        <div class="login-container">
            <!-- NCRB Authentication -->
            <div class="login-card">
                <div class="card-logo">
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                </div>
                <div class="login-header">
                    <i class="fas fa-lock"></i> RPCTC Kolkata Authentication
                </div>
                <div class="login-body">
                    <?php if (!empty($error)): ?>
                        <div class="login-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="rpctc_login" value="1">
                        <div class="login-form-group">
                            <label class="login-label" for="username">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="login-input" 
                                placeholder="Enter admin username" 
                                required
                            >
                        </div>
                        
                        <div class="login-form-group">
                            <label class="login-label" for="password">
                                <i class="fas fa-key"></i> Password
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="login-input" 
                                placeholder="Enter your password" 
                                required
                            >
                        </div>
                        
                        <button type="submit" class="login-btn">
                            <i class="fas fa-sign-in-alt"></i> Login to RPCTC Kolkata Dashboard
                        </button>
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