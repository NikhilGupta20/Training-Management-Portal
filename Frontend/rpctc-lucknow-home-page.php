<?php
//home-page.php
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

// Create portal_feedback table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS portal_feedback (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    feedback TEXT NOT NULL,
    visitor_name VARCHAR(255) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    rating INT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    // Error creating table - handle appropriately
}

// Handle form submission
$formSubmitted = false;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $topic = $conn->real_escape_string($_POST['feedback_topic']);
    $email = $conn->real_escape_string($_POST['email']);
    $feedback = $conn->real_escape_string($_POST['feedback']);
    $name = $conn->real_escape_string($_POST['visitor_name']);
    $contact = $conn->real_escape_string($_POST['contact_number']);
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    
    $sql = "INSERT INTO portal_feedback (topic, email, feedback, visitor_name, contact_number, rating)
            VALUES ('$topic', '$email', '$feedback', '$name', '$contact', $rating)";
    
    if ($conn->query($sql) === TRUE) {
        $formSubmitted = true;
    }
}

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
    <title>Training Portal | RPCTC Lucknow</title>
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

        /* WELCOME BANNER STYLES */
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
            animation: fadeIn 0.8s ease-out forwards;
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

        /* Portal Sections */
        .portal-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .portal-section {
            background: var(--white);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            opacity: 0;
            animation: fadeIn 0.6s ease-out forwards;
        }

        .portal-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 20px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .section-buttons {
            padding: 25px;
        }

        /* Uniform button height and layout */
        .portal-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white); /* Base text color */
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 15px;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            border: none;
            width: 100%;
            cursor: pointer;
            text-align: left;
            opacity: 0;
            animation: fadeIn 0.4s ease-out forwards;
        }

        .portal-btn:last-child {
            margin-bottom: 0;
        }

        /* MODIFICATION: Improve text contrast on hover */
        .portal-btn:hover {
            background: linear-gradient(135deg, #00264d 0%, #0052a3 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
            color: #ffffff; /* Ensure text remains white on hover */
        }

        .btn-icon {
            font-size: 1.4rem;
            margin-right: 12px;
            flex-shrink: 0; /* Prevent icon from shrinking */
            color: inherit; /* Inherit parent's color */
        }

        .btn-text {
            flex: 1;
            font-size: 1.1rem;
            overflow-wrap: break-word;
            padding-right: 10px;
            display: flex;
            align-items: center; /* Vertically center text */
            color: inherit; /* Inherit parent's color */
        }

        .btn-arrow {
            font-size: 1.2rem;
            transition: var(--transition);
            flex-shrink: 0; /* Prevent arrow from shrinking */
            color: inherit; /* Inherit parent's color */
        }

        .portal-btn:hover .btn-arrow {
            transform: translateX(5px);
        }

        /* Disabled button style */
        .portal-btn.disabled {
            background: rgba(0, 0, 0, 0.05);
            color: #6c757d;
            box-shadow: none;
            pointer-events: none;
            cursor: not-allowed;
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
            background: var(--white);
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
            position: relative;
        }
        
        /* Increase width specifically for feedback modal */
        #feedbackModal .modal-content {
            max-width: 800px; /* Increased width for better form layout */
        }
        
        .modal-header {
            background: var(--primary);
            color: var(--white);
            padding: 20px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
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
            color: var(--accent);
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
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .contact-info {
            background: var(--light-gray);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .contact-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border-radius: 8px;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            text-align: center;
        }
        
        .contact-btn i {
            margin-right: 8px;
            font-size: 1.2rem;
        }
        
        .call-btn { background: #28a745; }
        .sms-btn { background: #17a2b8; }
        .whatsapp-btn { background: #25D366; }
        .email-btn { background: #dc3545; }
        
        .contact-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Feedback Form */
        .feedback-form {
            display: grid;
            gap: 15px;
        }
        
        /* NEW: Grid layout for feedback form */
        .feedback-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-full-width {
            grid-column: span 2;
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
            padding: 12px 15px;
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
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Enhanced Professional Rating System - All in one row */
        .rating-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 15px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .rating-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .rating-subtitle {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .rating-options {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: nowrap;
            width: 100%;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .rating-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: var(--transition);
            min-width: 100px;
            white-space: nowrap;
        }
        
        .rating-option:hover {
            background-color: var(--light);
            border-color: var(--secondary);
        }
        
        .rating-option.active {
            background-color: rgba(0, 102, 204, 0.1);
            border-color: var(--secondary);
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .rating-text {
            font-size: 0.9rem;
            font-weight: 500;
            text-align: center;
        }
        
        .rating-input {
            display: none;
        }
        
        /* Captcha */
        .captcha-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .captcha-code {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: bold;
            background: var(--light-gray);
            padding: 8px 15px;
            border-radius: 6px;
            letter-spacing: 3px;
            user-select: none;
            background-image: linear-gradient(45deg, #f3f3f3 25%, #e7e7e7 25%, #e7e7e7 50%, #f3f3f3 50%, #f3f3f3 75%, #e7e7e7 75%, #e7e7e7 100%);
            background-size: 20px 20px;
        }
        
        .captcha-refresh {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .captcha-refresh:hover {
            background: var(--secondary);
        }
        
        /* Submit Button */
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
        
        /* Success Message */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        /* FAQs */
        .faq-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .faq-item {
            margin-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 20px;
        }
        
        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .faq-question {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .faq-answer {
            color: var(--gray);
            line-height: 1.6;
        }

        /* Training Gallery & Support section modifications */
        .portal-section:nth-child(3) .section-buttons {
            display: grid;
            grid-template-columns: 1fr;
            row-gap: 15px;
        }

        .half-width-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .portal-section:nth-child(3) .portal-btn {
            margin-bottom: 0; /* Remove bottom margin */
            width: 100%; /* Ensure full width in their container */
        }

        /* MODIFICATION: Normalize developer name */
        .copyright a#developer-contact-link {
            font-weight: normal; /* Remove bold */
            color: inherit; /* Use same color as surrounding text */
            text-decoration: none; /* Remove underline */
        }

        .copyright a#developer-contact-link:hover {
            color: var(--accent); /* Maintain hover effect */
            text-decoration: underline; /* Add underline on hover */
        }
        
        /* Mailto link styling */
        .mailto-link {
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
        }
        .mailto-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }
        
        #developerContactModal .modal-content {
            background: #2c3e50; /* Dark blue-gray background */
            color: #ecf0f1; /* Light text color */
            border: 1px solid #1a252f; /* Darker border */
        }
        
        #developerContactModal .modal-header {
            background: #1a252f; /* Darker header */
            color: #fff;
            border-bottom: 2px solid #f39c12; /* Accent border */
        }
        
        #developerContactModal .close-modal:hover {
            color: #f39c12; /* Golden accent color */
        }
        
        #developerContactModal .developer-contact h3 {
            color: #f39c12; /* Golden accent color */
            font-size: 1.4rem;
            margin-bottom: 20px;
        }
        
        #developerContactModal .contact-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        #developerContactModal .contact-info p {
            margin: 12px 0;
            font-size: 1.05rem;
        }
        
        #developerContactModal .contact-info i {
            color: #f39c12; /* Golden accent color */
            width: 22px;
        }
        
        #developerContactModal .contact-info a {
            color: #3498db; /* Light blue for links */
            transition: var(--transition);
        }
        
        #developerContactModal .contact-info a:hover {
            color: #f39c12; /* Golden accent on hover */
        }
        
        #developerContactModal .contact-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        #developerContactModal .contact-btn {
            padding: 14px 10px;
            font-size: 1rem;
            border-radius: 6px;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        #developerContactModal .contact-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        
        #developerContactModal .contact-btn i {
            font-size: 1.3rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            body {
                padding-top: 170px;
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
                font-size: 2rem;
            }
            
            .portal-sections {
                grid-template-columns: 1fr;
            }
            
            .welcome-banner {
                padding: 40px 20px;
            }
            
            .half-width-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .welcome-banner h2 {
                font-size: 2rem;
            }
            
            .welcome-banner p {
                font-size: 1.1rem;
            }

            .footer-columns {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
            }
            
            #feedbackModal .modal-content {
                width: 95%;
            }
            
            .feedback-form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-full-width {
                grid-column: span 1;
            }
            
            /* Responsive adjustments for contact modal */
            #developerContactModal .contact-methods {
                grid-template-columns: 1fr;
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
                font-size: 1.8rem;
            }
            
            .welcome-banner h2 {
                font-size: 1.8rem;
            }
            
            /* Adjust rating options for mobile */
            .rating-option {
                min-width: 85px;
                padding: 8px 5px;
            }
            
            .rating-stars {
                font-size: 1.3rem;
            }
            
            .rating-text {
                font-size: 0.8rem;
            }
            
            /* Adjust captcha for mobile */
            .captcha-container {
                flex-direction: column;
                align-items: stretch;
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
                <div class="nav-title">RPCTC Lucknow</div>
                <div class="login-links">
                    <div class="admin-login">
                        <a href="rpctc-lucknow-admin-login.php"><i class="fas fa-lock"></i> Admin Login</a>
                    </div>
                    <div class="separator">|</div>
                    <div class="admin-login">
                        <a href="participant-login.php"><i class="fas fa-lock"></i> Participant Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-header">
            <!-- Header content can be added here if needed -->
        </div>
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <a href="javascript:;" class="logo">
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/ncrb-logo.png" alt="National Crime Records Bureau" class="img-fluid">
            </a>
            <h2>Welcome to RPCTC Lucknow Training Management System</h2>
        </div>
        
        <div class="portal-sections">
            <!-- Training Calendar Section -->
            <div class="portal-section" style="animation-delay: 0.2s">
                <div class="section-header">
                    <i class="fas fa-calendar"></i> Training Calendar
                </div>
                <div class="section-buttons">
                    <a href="home-present-year.php" class="portal-btn" style="animation-delay: 0.3s">
                        <i class="btn-icon fas fa-file-pdf"></i>
                        <span class="btn-text">Training Calendar for Present Academic Year</span>
                        <i class="btn-arrow fas fa-chevron-right"></i>
                    </a>
                    <a href="home-next-year.php" class="portal-btn" style="animation-delay: 0.4s">
                        <i class="btn-icon fas fa-file-pdf"></i>
                        <span class="btn-text">Training Calendar for Next Academic Year</span>
                        <i class="btn-arrow fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Participant Portal Section -->
            <div class="portal-section" style="animation-delay: 0.3s">
                <div class="section-header">
                    <i class="fas fa-user-graduate"></i> Participant Portal
                </div>
                <div class="section-buttons">
                    <a href="home-nomination.php" class="portal-btn" style="animation-delay: 0.5s">
                        <i class="btn-icon fas fa-list"></i>
                        <span class="btn-text">Nomination List For Accepted Participants</span>
                        <i class="btn-arrow fas fa-chevron-right"></i>
                    </a>
                    <a href="participant-login.php" class="portal-btn" style="animation-delay: 0.6s">
                        <i class="btn-icon fas fa-sign-in-alt"></i>
                        <span class="btn-text">Participant Login after Registration</span>
                        <i class="btn-arrow fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Training Gallery & Support Section -->
            <div class="portal-section" style="animation-delay: 0.4s">
                <div class="section-header">
                    <i class="fas fa-images"></i> Training Gallery & Support
                </div>
                <div class="section-buttons">
                    <!-- Row 1: Image Gallery -->
                    <a href="home-image.php" class="portal-btn" style="animation-delay: 0.7s">
                        <i class="btn-icon fas fa-camera"></i>
                        <span class="btn-text">Image Gallery</span>
                        <i class="btn-arrow fas fa-chevron-right"></i>
                    </a>
                    
                    <!-- Row 2: Video Gallery -->
                    <a href="home-video.php" class="portal-btn" style="animation-delay: 0.8s">
                        <i class="btn-icon fas fa-video"></i>
                        <span class="btn-text">Video Gallery</span>
                        <i class="btn-arrow fas fa-chevron-right"></i>
                    </a>
                    
                    <!-- Row 3: FAQs and Feedback -->
                    <div class="half-width-row">
                        <a href="#faqsModal" class="portal-btn" data-modal="faqsModal" style="animation-delay: 0.9s">
                            <i class="btn-icon fas fa-question-circle"></i>
                            <span class="btn-text">FAQs</span>
                            <i class="btn-arrow fas fa-chevron-right"></i>
                        </a>
                        <a href="#feedbackModal" class="portal-btn" data-modal="feedbackModal" style="animation-delay: 1.0s">
                            <i class="btn-icon fas fa-comment-dots"></i>
                            <span class="btn-text">Feedback</span>
                            <i class="btn-arrow fas fa-chevron-right"></i>
                        </a>
                    </div>
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
                    <!-- Fixed mailto links -->
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
    
    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Feedback
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <?php if ($formSubmitted): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i> Thank you for your feedback! We appreciate your input.
                    </div>
                <?php endif; ?>
                
                <form id="feedbackForm" class="feedback-form" method="POST">
                    <!-- NEW: Grid layout for professional form organization -->
                    <div class="feedback-form-grid">
                        <div class="form-group">
                            <label for="feedback_topic">Feedback Topic</label>
                            <input type="text" id="feedback_topic" name="feedback_topic" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="visitor_name">Visitor Name</label>
                            <input type="text" id="visitor_name" name="visitor_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group form-full-width">
                            <label for="feedback">Feedback</label>
                            <textarea id="feedback" name="feedback" class="form-control" required></textarea>
                        </div>
                        
                        <!-- Enhanced Professional Rating System - All in one row -->
                        <div class="form-group form-full-width">
                            <div class="rating-container">
                                <div class="rating-label">Portal Rating</div>
                                <div class="rating-subtitle">(Optional - Select to rate your experience)</div>
                                <div class="rating-options">
                                    <!-- Rating Option 5 -->
                                    <label class="rating-option">
                                        <input type="radio" name="rating" value="5" class="rating-input">
                                        <div class="rating-stars">★★★★★</div>
                                        <div class="rating-text">Excellent</div>
                                    </label>
                                    
                                    <!-- Rating Option 4 -->
                                    <label class="rating-option">
                                        <input type="radio" name="rating" value="4" class="rating-input">
                                        <div class="rating-stars">★★★★☆</div>
                                        <div class="rating-text">Good</div>
                                    </label>
                                    
                                    <!-- Rating Option 3 -->
                                    <label class="rating-option">
                                        <input type="radio" name="rating" value="3" class="rating-input">
                                        <div class="rating-stars">★★★☆☆</div>
                                        <div class="rating-text">Average</div>
                                    </label>
                                    
                                    <!-- Rating Option 2 -->
                                    <label class="rating-option">
                                        <input type="radio" name="rating" value="2" class="rating-input">
                                        <div class="rating-stars">★★☆☆☆</div>
                                        <div class="rating-text">Fair</div>
                                    </label>
                                    
                                    <!-- Rating Option 1 -->
                                    <label class="rating-option">
                                        <input type="radio" name="rating" value="1" class="rating-input">
                                        <div class="rating-stars">★☆☆☆☆</div>
                                        <div class="rating-text">Poor</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group form-full-width">
                            <div class="captcha-container">
                                <div class="captcha-code" id="captchaCode"></div>
                                <button type="button" class="captcha-refresh" id="refreshCaptcha">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <input type="text" id="captcha_input" name="captcha_input" class="form-control" placeholder="Enter Captcha" required>
                            </div>
                        </div>
                        
                        <div class="form-group form-full-width">
                            <button type="submit" name="submit_feedback" class="submit-btn">Submit Feedback</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- FAQs Modal -->
    <div id="faqsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Frequently Asked Questions
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="faq-container">
                    <div class="faq-item">
                        <div class="faq-question">Q: How do I send nomination for a training program?</div>
                        <div class="faq-answer">A: You can nominate yourself for training programs through the Access Participant Form, available in the "Training Calendar" section of the portal.</div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Q: Where can I find the training calendar?</div>
                        <div class="faq-answer">A: The training calendar for the current and next academic year is available in the "Training Calendar" section of the portal.</div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Q: How can I access training materials?</div>
                        <div class="faq-answer">A: Once registered and logged in, training materials are available in your participant dashboard under the "My Courses" section.</div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Q: What are the requirements for training?</div>
                        <div class="faq-answer">A: You need to wait for your nomination to get Accepted and follow all the Instructions given by NCRB.</div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question">Q: How do I get a certificate after completing training?</div>
                        <div class="faq-answer">A: Certificates are automatically generated upon successful completion of training and can be downloaded from your participant dashboard.</div>
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
            
            // Modal functionality
            const modals = document.querySelectorAll('.modal');
            const openModalButtons = document.querySelectorAll('.portal-btn[data-modal]');
            const closeModalButtons = document.querySelectorAll('.close-modal');
            const developerLink = document.getElementById('developer-contact-link');
            
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
            
            // Open modal on button click
            openModalButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevent default anchor behavior
                    const modalId = button.getAttribute('data-modal');
                    openModal(modalId);
                });
            });
            
            // Open developer modal
            developerLink.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('developerContactModal');
            });
            
            // Close modal on close button click
            closeModalButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const modal = button.closest('.modal');
                    closeModal(modal.id);
                });
            });
            
            // Close modal when clicking outside content
            modals.forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal(modal.id);
                    }
                });
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    modals.forEach(modal => {
                        if (modal.style.display === 'flex') {
                            closeModal(modal.id);
                        }
                    });
                }
            });
            
            // Alphanumeric captcha generation
            function generateCaptcha() {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                let result = '';
                const length = 6;
                for (let i = 0; i < length; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                document.getElementById('captchaCode').textContent = result;
                return result;
            }
            
            let captchaAnswer = generateCaptcha();
            
            // Refresh captcha
            document.getElementById('refreshCaptcha').addEventListener('click', () => {
                captchaAnswer = generateCaptcha();
            });
            
            // Form validation
            const feedbackForm = document.getElementById('feedbackForm');
            if (feedbackForm) {
                feedbackForm.addEventListener('submit', (e) => {
                    const captchaInput = document.getElementById('captcha_input').value;
                    
                    if (captchaInput !== captchaAnswer) {
                        e.preventDefault();
                        alert('Please enter the correct captcha.');
                        captchaAnswer = generateCaptcha();
                        document.getElementById('captcha_input').value = '';
                    }
                });
            }
            
            // Equalize button heights within each section
            document.querySelectorAll('.portal-section').forEach(section => {
                const buttons = section.querySelectorAll('.portal-btn');
                let maxHeight = 0;
                
                buttons.forEach(btn => {
                    // Reset height to auto to get natural height
                    btn.style.height = 'auto';
                });
                
                // Calculate max height
                buttons.forEach(btn => {
                    if (btn.offsetHeight > maxHeight) maxHeight = btn.offsetHeight;
                });
                
                // Apply max height to all buttons in the section
                buttons.forEach(btn => {
                    btn.style.height = `${maxHeight}px`;
                });
            });
            
            // Rating option selection
            document.querySelectorAll('.rating-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remove active class from all options
                    document.querySelectorAll('.rating-option').forEach(opt => {
                        opt.classList.remove('active');
                    });
                    
                    // Add active class to selected option
                    this.classList.add('active');
                    
                    // Check the radio input
                    const radio = this.querySelector('.rating-input');
                    radio.checked = true;
                });
            });
        });
    </script>
</body>
</html>