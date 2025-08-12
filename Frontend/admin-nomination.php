<?php
// admin-nomination.php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ncrb_training');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'nikhilguptaji@gmail.com');
define('SMTP_PASS', 'dmmufijsqwaoenpy');
define('SMTP_PORT', 587);
define('EMAIL_FROM', 'noreply@ncrb.gov.in');
define('EMAIL_FROM_NAME', 'NCRB Participant System');

// Twilio credentials
$twilio_sid = 'AC5329f9c47dfa4be553ce996325a4a1b3';
$twilio_token = '72d6f66055f29e031d04accd8ec1fd94';

// Initialize Twilio client
require_once __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;
$twilio = new Client($twilio_sid, $twilio_token);

// Session Management
session_start();

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.php");
    exit;
}

// Get admin username from session
$admin_username = $_SESSION['admin_username'];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables if not exists
try {
    // Create nominee table
    $pdo->exec("CREATE TABLE IF NOT EXISTS nominee (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        registration_id VARCHAR(50),        
        participant_name VARCHAR(255) NOT NULL,
        participant_name_hindi VARCHAR(255) NOT NULL,
        gender VARCHAR(10) NOT NULL,
        category_name VARCHAR(50) NOT NULL,
        category_name_hindi VARCHAR(50) NOT NULL,
        rank VARCHAR(100) NOT NULL,
        rank_hindi VARCHAR(100) NOT NULL,
        other_rank VARCHAR(100),
        other_rank_hindi VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        qualifications TEXT NOT NULL,
        residential_address TEXT NOT NULL,
        state_name VARCHAR(100) NOT NULL,
        state_name_hindi VARCHAR(100) NOT NULL,
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(15) NOT NULL,
        delhi_address TEXT,
        course_expectation TEXT NOT NULL,
        officer_designation VARCHAR(100) NOT NULL,
        officer_phone VARCHAR(15),
        officer_address TEXT,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create accepted_participants table
    $pdo->exec("CREATE TABLE IF NOT EXISTS accepted_participants (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(255) NOT NULL,
        participant_name_hindi VARCHAR(255) NOT NULL,
        gender VARCHAR(10) NOT NULL,
        category_name VARCHAR(50) NOT NULL,
        category_name_hindi VARCHAR(50) NOT NULL,
        rank VARCHAR(100) NOT NULL,
        rank_hindi VARCHAR(100) NOT NULL,
        other_rank VARCHAR(100),
        other_rank_hindi VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        qualifications TEXT NOT NULL,
        residential_address TEXT NOT NULL,
        state_name VARCHAR(100) NOT NULL,
        state_name_hindi VARCHAR(100) NOT NULL,
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(15) NOT NULL,
        delhi_address TEXT,
        course_expectation TEXT NOT NULL,
        officer_designation VARCHAR(100) NOT NULL,
        officer_phone VARCHAR(15),
        officer_address TEXT,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create rejected_participants table
    $pdo->exec("CREATE TABLE IF NOT EXISTS rejected_participants (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(255) NOT NULL,
        participant_name_hindi VARCHAR(255) NOT NULL,
        gender VARCHAR(10) NOT NULL,
        category_name VARCHAR(50) NOT NULL,
        category_name_hindi VARCHAR(50) NOT NULL,
        rank VARCHAR(100) NOT NULL,
        rank_hindi VARCHAR(100) NOT NULL,
        other_rank VARCHAR(100),
        other_rank_hindi VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        qualifications TEXT NOT NULL,
        residential_address TEXT NOT NULL,
        state_name VARCHAR(100) NOT NULL,
        state_name_hindi VARCHAR(100) NOT NULL,
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(15) NOT NULL,
        delhi_address TEXT,
        course_expectation TEXT NOT NULL,
        officer_designation VARCHAR(100) NOT NULL,
        officer_phone VARCHAR(15),
        officer_address TEXT,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        rejection_reason TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
        
} catch (PDOException $e) {
    error_log("Database setup error: " . $e->getMessage());
}

// Function to get state recipient email
function getStateRecipientEmail($pdo, $stateName) {
    try {
        $stmt = $pdo->prepare("SELECT email FROM recipients WHERE state_name = ?");
        $stmt->execute([$stateName]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching state recipient: " . $e->getMessage());
        return null;
    }
}

// Generate registration ID
function generateRegistrationId($pdo, $data) {
    $year = date('Y', strtotime($data['start_date']));
    $month = date('m', strtotime($data['start_date'])); // Two-digit month
    
    // Try to get course code from courses table
    $courseCode = 'GEN'; // Default value
    if (!empty($data['course_name'])) {
        $stmt = $pdo->prepare("SELECT course_code FROM training_events WHERE course_name = :course_name");
        $stmt->execute([':course_name' => $data['course_name']]);
        $course = $stmt->fetch();
        
        if ($course && !empty($course['course_code'])) {
            $courseCode = $course['course_code'];
        } else {
            // Fallback to generating code from course name
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $data['course_name']);
            if (strlen($cleanName) >= 3) {
                $courseCode = substr(strtoupper($cleanName), 0, 3);
            } else {
                // Pad short names with 'X'
                $courseCode = str_pad(strtoupper($cleanName), 3, 'X');
            }
        }
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM accepted_participants 
        WHERE course_name = :course_name
    ");
    $stmt->execute([':course_name' => $data['course_name']]);
    $count = $stmt->fetch()['count'] + 1;
    
    return sprintf("NCRB-%s-%s-%s-%04d", $year, $month, $courseCode, $count);
}

// Handle Actions (Accept/Reject/Revert)
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['token'])) {
    // Verify CSRF token
    if ($_GET['token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: admin-nomination.php");
        exit;
    }

    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $from = $_GET['from'] ?? '';
    $view = $_GET['view'] ?? '';

    switch ($action) {
        case 'accept':
            if (moveRecord($pdo, 'nominee', 'accepted_participants', $id)) {
                $_SESSION['success_message'] = "Participant accepted successfully";
            }
            break;
            
        case 'reject':
            $reason = $_GET['reason'] ?? '';
            if (moveRecord($pdo, 'nominee', 'rejected_participants', $id, $reason)) {
                $_SESSION['success_message'] = "Participant rejected successfully";
            }
            break;
            
        case 'revert':
            $sourceTable = $from === 'accepted' ? 'accepted_participants' : 'rejected_participants';
            if (moveRecord($pdo, $sourceTable, 'nominee', $id)) {
                $_SESSION['success_message'] = "Participant reverted to pending successfully";
            }
            break;
    }

    // Preserve filters when redirecting back
    $queryParams = [];
    if (!empty($view)) $queryParams['view'] = $view;
    if (!empty($_GET['start_date'])) $queryParams['start_date'] = $_GET['start_date'];
    if (!empty($_GET['end_date'])) $queryParams['end_date'] = $_GET['end_date'];
    if (!empty($_GET['course'])) $queryParams['course'] = $_GET['course'];
    if (!empty($_GET['state'])) $queryParams['state'] = $_GET['state'];
    if (!empty($_GET['district'])) $queryParams['district'] = $_GET['district'];
    if (!empty($_GET['gender'])) $queryParams['gender'] = $_GET['gender'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];

    $redirectUrl = "admin-nomination.php";
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }
    
    header("Location: $redirectUrl");
    exit;
}

// Function to send email
function sendEmail($to, $name, $subject, $body, $cc = []) {
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Create the Transport
    $transport = (new Swift_SmtpTransport(SMTP_HOST, SMTP_PORT, 'tls'))
        ->setUsername(SMTP_USER)
        ->setPassword(SMTP_PASS);

    // Create the Mailer using your created Transport
    $mailer = new Swift_Mailer($transport);

    // Create a message
    $message = (new Swift_Message($subject))
        ->setFrom([EMAIL_FROM => EMAIL_FROM_NAME])
        ->setTo([$to => $name])
        ->setBody($body, 'text/html');

    // Add CC recipients if provided
    if (!empty($cc)) {
        $message->setCc($cc);
    }

    try {
        // Send the message
        $result = $mailer->send($message);
        return $result > 0;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Function to send SMS/WhatsApp - SMS first, fallback to WhatsApp
function sendSMS($phone, $body) {
    global $twilio;
    
    // Clean the phone number: remove non-numeric characters
    $cleanedPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Validate the phone number format
    if (empty($cleanedPhone)) {
        error_log("Empty phone number provided");
        return false;
    }
    
    // Handle different phone number formats
    if (strlen($cleanedPhone) === 10) {
        // Standard 10-digit Indian mobile number
        $formattedPhone = "+91" . $cleanedPhone;
    } elseif (strlen($cleanedPhone) === 12 && strpos($cleanedPhone, '91') === 0) {
        // 12-digit number starting with 91
        $formattedPhone = "+" . $cleanedPhone;
    } elseif (strlen($cleanedPhone) === 11 && $cleanedPhone[0] === '0') {
        // 11-digit number starting with 0
        $formattedPhone = "+91" . substr($cleanedPhone, 1);
    } else {
        error_log("Invalid phone number format: $phone (cleaned: $cleanedPhone)");
        return false;
    }

    // Log the sending attempt
    error_log("Attempting to send to: $formattedPhone, body: $body");

    // SMS sending with detailed error handling
    $smsSent = false;
    $whatsappSent = false;
    $errorDetails = [];

    // 1. First try sending via SMS
    try {
        $message = $twilio->messages->create(
            $formattedPhone,
            [
                'from' => '+19472148608',
                'body' => $body
            ]
        );
        error_log("SMS sent successfully! SID: " . $message->sid);
        $smsSent = true;
    } catch (Exception $smsEx) {
        $errorDetails['sms'] = [
            'code' => $smsEx->getCode(),
            'message' => $smsEx->getMessage(),
            'phone' => $formattedPhone,
            'body' => $body
        ];
        error_log("SMS FAILED: " . json_encode($errorDetails['sms']));
    }

    // 2. If SMS failed, try WhatsApp
    if (!$smsSent) {
        try {
            $whatsappPhone = "whatsapp:" . $formattedPhone;
            $message = $twilio->messages->create(
                $whatsappPhone,
                [
                    'from' => 'whatsapp:+14155238886',
                    'body' => $body
                ]
            );
            error_log("WhatsApp sent successfully! SID: " . $message->sid);
            $whatsappSent = true;
        } catch (Exception $waEx) {
            $errorDetails['whatsapp'] = [
                'code' => $waEx->getCode(),
                'message' => $waEx->getMessage(),
                'phone' => $whatsappPhone,
                'body' => $body
            ];
            error_log("WHATSAPP FAILED: " . json_encode($errorDetails['whatsapp']));
            
            // Special case for WhatsApp sandbox requirement
            if (strpos($waEx->getMessage(), 'not in your address book') !== false) {
                error_log("WHATSAPP SETUP REQUIRED: Recipient must initiate chat with Sandbox first");
            }
        }
    }

    // Return true if either method succeeded
    return $smsSent || $whatsappSent;
}

// Function to get gender prefix
function getGenderPrefix($gender) {
    $gender = strtolower(trim($gender));
    if ($gender === 'male') return 'Shri';
    if ($gender === 'female') return 'Smt.';
    return ''; // For 'other' or unspecified
}

// Function to send acceptance notifications
function sendAcceptanceNotifications($pdo, $data, $registrationId) {
    $name = $data['participant_name'];
    $email = $data['email'];
    $phone = $data['residential_phone'] ?? ''; // Using residential_phone
    $course = $data['course_name'];
    $state = $data['state_name'];
    $startDate = date("d-m-Y", strtotime($data['start_date']));
    $endDate = date("d-m-Y", strtotime($data['end_date']));
    
    // Get gender prefix
    $gender = $data['gender'] ?? '';
    $prefix = getGenderPrefix($gender);
    $fullName = $prefix ? $prefix . ' ' . $name : $name;
    
    // Get state recipient email
    $stateEmail = getStateRecipientEmail($pdo, $state);
    
    if (!$stateEmail) {
        error_log("No state recipient found for state: $state");
        return ['email' => false, 'sms' => false];
    }
    
    // Email content
    $subject = "Acceptance of Nomination for $course";
    
    $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NCRB Nomination Acceptance</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 700px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #003366 0%, #0066cc 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .header img {
            height: 70px;
            margin-bottom: 15px;
        }
        .header h1 {
            color: white;
            font-size: 24px;
            margin: 10px 0 5px;
            font-weight: 600;
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin: 0 0 20px 0;
            font-size: 16px;
            line-height: 1.7;
        }
        .registration-id {
            font-size: 18px;
            font-weight: bold;
            color: #003366;
            padding: 10px;
            background: #fff;
            border: 2px solid #003366;
            border-radius: 6px;
            text-align: center;
            margin: 15px 0;
        }        
        .highlight {
            background-color: #e8f4ff;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #0066cc;
        }
        .button {
            display: inline-block;
            background-color: #003366;
            color: white !important;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 4px;
            font-weight: 600;
            margin: 20px 0;
        }
        .footer {
            background-color: #f0f7ff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #e1e8ed;
        }
        .footer p {
            margin: 5px 0;
        }
        .signature {
            margin-top: 30px;
            font-weight: 600;
            color: #003366;
        }
        .contact-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin: 25px 0;
            font-size: 14px;
        }
        .contact-info p {
            margin: 8px 0;
        }
        .contact-info i {
            color: #f39c12;
            width: 22px;
        }
        .contact-info a {
            color: #3498db;
            transition: all 0.3s ease;
        }
        .contact-info a:hover {
            color: #f39c12;
        }
        .note {
            font-style: italic;
            color: #666;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
            <h1>National Crime Records Bureau</h1>
            <p>Ministry of Home Affairs, Government of India</p>
        </div>
        
        <div class="content">
            <div class="highlight">
                <p>The nominations of {$fullName}, {$state} are accepted for the course on <strong>{$course}</strong> scheduled from <strong>{$startDate}</strong> to <strong>{$endDate}</strong> at National Crime Records Bureau (NCRB), New Delhi.</p>
            </div>
            <div class="registration-id">Registration ID: {$registrationId}</div>            
            <p>{$name} may be directed to report in civil dress at NCRB(MHA), Mahipalpur, NH-8, New Delhi – 110037 on <strong>{$startDate}</strong> at 0930 hrs. The training will come to an end at 1700 hrs. on <strong>{$endDate}</strong>. The participants are requested to plan their return journey accordingly.</p>
            
            <div class="contact-info">
                <p><strong>NCRB Hostel Information:</strong></p>
                <p>NCRB has hostel facility in NCRB Complex. {$name} may avail hostel facility subject to availability.</p>
                <p><strong>Hostel contact numbers:</strong> 011-26735474, 8493087029</p>
                <p>Kindly confirm your stay in hostel before leaving for NCRB, New Delhi.</p>
                <p><a href='http://localhost/NCRB/form-hostel.php' class='button'>Hostel Allotment Form</a></p>
            </div>
            
            <p class="signature">Yours faithfully,<br>
            Director<br>
            National Crime Records Bureau<br>
            Ministry of Home Affairs</p>
            
            <div class="note">
                <p>This is an auto-generated email. Please do not reply.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>National Crime Records Bureau</p>
            <p>Mahipalpur, NH-8, New Delhi-110037</p>
            <p>Website: www.ncrb.gov.in | Email: training@ncrb.gov.in</p>
        </div>
    </div>
</body>
</html>
HTML;
    
    // SMS content
    $smsBody = "NCRB: Nomination of $name accepted for $course ($startDate to $endDate) with Registration ID: $registrationId. Report at NCRB, New Delhi on $startDate at 0930 hrs. Hostel contact: 011-26735474, 8493087029";
    
    // Send notifications
    $emailSent = sendEmail($stateEmail, $state, $subject, $emailBody, [$email]);
    $smsSent = !empty($phone) ? sendSMS($phone, $smsBody) : false;
    
    return ['email' => $emailSent, 'sms' => $smsSent];
}

// Function to send rejection notifications
function sendRejectionNotifications($pdo, $data, $reason = '') {
    $name = $data['participant_name'];
    $email = $data['email'];
    $phone = $data['residential_phone'] ?? ''; // Using residential_phone
    $course = $data['course_name'];
    $state = $data['state_name'];
    $startDate = date("d-m-Y", strtotime($data['start_date']));
    $endDate = date("d-m-Y", strtotime($data['end_date']));
    
    // Get gender prefix
    $gender = $data['gender'] ?? '';
    $prefix = getGenderPrefix($gender);
    $fullName = $prefix ? $prefix . ' ' . $name : $name;
    
    // Get state recipient email
    $stateEmail = getStateRecipientEmail($pdo, $state);
    
    if (!$stateEmail) {
        error_log("No state recipient found for state: $state");
        return ['email' => false, 'sms' => false];
    }
    
    // Email content
    $subject = "Rejection of Nomination for $course";
    
    $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NCRB Nomination Rejection</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
        }
        .email-container {
            max-width: 700px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #003366 0%, #0066cc 100%);
            padding: 30px 20px;
            text-align: center;
        }
        .header img {
            height: 70px;
            margin-bottom: 15px;
        }
        .header h1 {
            color: white;
            font-size: 24px;
            margin: 10px 0 5px;
            font-weight: 600;
        }
        .header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin: 0 0 20px 0;
            font-size: 16px;
            line-height: 1.7;
        }
        .highlight {
            background-color: #fff3f3;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #cc0000;
        }
        .footer {
            background-color: #f9f0f0;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #e1e8ed;
        }
        .footer p {
            margin: 5px 0;
        }
        .signature {
            margin-top: 30px;
            font-weight: 600;
            color: #003366;
        }
        .note {
            font-style: italic;
            color: #666;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
            <h1>National Crime Records Bureau</h1>
            <p>Ministry of Home Affairs, Government of India</p>
        </div>
        
        <div class="content">
            <div class="highlight">
                <p>The nominations of {$fullName}, {$state} for the course on <strong>{$course}</strong> scheduled from <strong>{$startDate}</strong> to <strong>{$endDate}</strong> at National Crime Records Bureau (NCRB), New Delhi has been rejected.</p>
                
                <p><strong>Reason:</strong> {$reason}</p>
            </div>
            
            <p>We appreciate your interest in our training programs and encourage you to apply for future courses.</p>
            
            <p class="signature">Yours faithfully,<br>
            Director<br>
            National Crime Records Bureau<br>
            Ministry of Home Affairs</p>
            
            <div class="note">
                <p>This is an auto-generated email. Please do not reply.</p>
            </div>
        </div>
        
        <div class="footer">
            <p>National Crime Records Bureau</p>
            <p>Mahipalpur, NH-8, New Delhi-110037</p>
            <p>Website: www.ncrb.gov.in | Email: training@ncrb.gov.in</p>
        </div>
    </div>
</body>
</html>
HTML;
    
    // SMS content
    $smsBody = "NCRB: Nomination of $name for $course has been rejected. Reason: $reason";
    
    // Send notifications
    $emailSent = sendEmail($stateEmail, $state, $subject, $emailBody, [$email]);
    $smsSent = !empty($phone) ? sendSMS($phone, $smsBody) : false;
    
    return ['email' => $emailSent, 'sms' => $smsSent];
}

// Move record from one table to another with notifications
function moveRecord($pdo, $sourceTable, $targetTable, $id, $rejectionReason = '') {
    try {
        $pdo->beginTransaction();
        
        // Get nominee data with course start date
        $stmt = $pdo->prepare("
            SELECT * 
            FROM $sourceTable 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if (!$data) {
            throw new Exception("No record found with ID: $id");
        }

        // Ensure we have start_date
        if (empty($data['start_date'])) {
            throw new Exception("Missing course start date for course: " . $data['course_name']);
        }

        // Remove id if exists to prevent duplication
        unset($data['id']);
        
        // Remove special columns when moving to nominee
        if ($targetTable === 'nominee') {
            unset($data['registration_id']);
            unset($data['rejection_reason']);
        }

        // Add registration ID for accepted participants
        if ($sourceTable === 'nominee' && $targetTable === 'accepted_participants') {
            $registrationId = generateRegistrationId($pdo, $data);
            $data['registration_id'] = $registrationId;
        }
        
        // Add rejection reason for rejected participants
        if ($sourceTable === 'nominee' && $targetTable === 'rejected_participants') {
            $data['rejection_reason'] = $rejectionReason;
        }

        // Prepare columns and values
        $columns = array_keys($data);
        $values = array_values($data);
        
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $insertQuery = "INSERT INTO $targetTable (" . implode(',', $columns) . ") VALUES ($placeholders)";
        
        // Prepare the statement
        $insertStmt = $pdo->prepare($insertQuery);
        
        // Execute with values
        if (!$insertStmt->execute($values)) {
            throw new Exception("Failed to insert into $targetTable");
        }

        // Delete from source table
        $deleteStmt = $pdo->prepare("DELETE FROM $sourceTable WHERE id = ?");
        if (!$deleteStmt->execute([$id])) {
            throw new Exception("Failed to delete from $sourceTable");
        }

        // Send appropriate notifications
        if ($sourceTable === 'nominee' && $targetTable === 'accepted_participants') {
            $result = sendAcceptanceNotifications($pdo, $data, $registrationId);
            if (!$result['email']) {
                error_log("Failed to send acceptance email to: " . $data['email']);
            }
            if (!$result['sms'] && !empty($data['residential_phone'])) {
                error_log("Failed to send acceptance SMS to: " . $data['residential_phone']);
            }
        } elseif ($sourceTable === 'nominee' && $targetTable === 'rejected_participants') {
            $result = sendRejectionNotifications($pdo, $data, $rejectionReason);
            if (!$result['email']) {
                error_log("Failed to send rejection email to: " . $data['email']);
            }
            if (!$result['sms'] && !empty($data['residential_phone'])) {
                error_log("Failed to send rejection SMS to: " . $data['residential_phone']);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in moveRecord: " . $e->getMessage());
        $_SESSION['error_message'] = "Operation failed: " . $e->getMessage();
        return false;
    }
}

// Fetch filter data
$courses = $pdo->query("SELECT DISTINCT course_name FROM training_events")->fetchAll(PDO::FETCH_ASSOC);
$states = $pdo->query("SELECT * FROM states")->fetchAll(PDO::FETCH_ASSOC);

// Retain selected filters
$selectedState = $_GET['state'] ?? '';
$selectedDistricts = [];
if (!empty($selectedState)) {
    $stmt = $pdo->prepare("SELECT district_name FROM districts WHERE state_name = :state_name ORDER BY district_name");
    $stmt->execute([':state_name' => $selectedState]);
    $selectedDistricts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Determine which table to query based on view
$table = 'nominee';
$view = $_GET['view'] ?? '';
if ($view === 'accepted') $table = 'accepted_participants';
elseif ($view === 'rejected') $table = 'rejected_participants';

// Handle filters
$conditions = [];
$params = [];

// Date range filter
if (!empty($_GET['start_date'])) {
    $conditions[] = "start_date >= :start_date";
    $params[':start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $conditions[] = "end_date <= :end_date";
    $params[':end_date'] = $_GET['end_date'];
}

if (!empty($_GET['course'])) {
    $conditions[] = "course_name = :course_name";
    $params[':course_name'] = $_GET['course'];
}
if (!empty($_GET['state'])) {
    $conditions[] = "state_name = :state";
    $params[':state'] = $_GET['state'];
}
if (!empty($_GET['district'])) {
    $conditions[] = "district_name = :district";
    $params[':district'] = $_GET['district'];
}
if (!empty($_GET['gender'])) {
    $conditions[] = "gender = :gender";
    $params[':gender'] = $_GET['gender'];
}
if (!empty($_GET['search'])) {
    $conditions[] = "participant_name LIKE :search";
    $params[':search'] = "%" . $_GET['search'] . "%";
}

$sql = "SELECT * FROM $table";
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
} else {
    // Show no records if no filters are applied
    $sql .= " WHERE 1=0";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for reports
$stateWiseData = [];
$stateCourseWiseData = [];

// State-wise counts
$stateQuery = "SELECT state_name, COUNT(*) as count FROM $table";
if (!empty($conditions)) {
    $stateQuery .= " WHERE " . implode(" AND ", $conditions);
}
$stateQuery .= " GROUP BY state_name ORDER BY state_name";

$stateStmt = $pdo->prepare($stateQuery);
$stateStmt->execute($params);
$stateWiseData = $stateStmt->fetchAll(PDO::FETCH_ASSOC);

// State and Course-wise counts
$stateCourseQuery = "SELECT state_name, course_name, COUNT(*) as count FROM $table";
if (!empty($conditions)) {
    $stateCourseQuery .= " WHERE " . implode(" AND ", $conditions);
}
$stateCourseQuery .= " GROUP BY state_name, course_name ORDER BY state_name, course_name";

$stateCourseStmt = $pdo->prepare($stateCourseQuery);
$stateCourseStmt->execute($params);
$stateCourseWiseData = $stateCourseStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Nomination Management - NCRB Training Portal</title>
    
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <style>
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
            color: var(--dark);
            min-height: 100vh;
            padding-top: 200px;
            background-color: #f5f7fa;
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

        .view-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 15px;
        }

        .view-tab {
            padding: 12px 30px;
            background: var(--light-gray);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            color: var(--dark);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .view-tab:hover {
            background: #d1d5db;
        }

        .view-tab.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            box-shadow: 0 4px 8px rgba(0, 102, 204, 0.2);
        }

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

        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .accept-btn, .reject-btn, .revert-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .accept-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .reject-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .revert-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: none;
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
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

        /* Responsive adjustments */
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
            
            .view-tabs {
                flex-direction: column;
                gap: 10px;
            }
            
            .view-tab {
                width: 100%;
                text-align: center;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
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
                <div class="nav-title">National Crime Records Bureau - Nomination Management</div>
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
            <h1>Nomination Management</h1>
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

        <!-- View Tabs -->
        <div class="view-tabs">
            <button class="view-tab <?= empty($view) ? 'active' : '' ?>" onclick="changeView('')">Received Nominations</button>
            <button class="view-tab <?= $view === 'accepted' ? 'active' : '' ?>" onclick="changeView('accepted')">Accepted Nominations</button>
            <button class="view-tab <?= $view === 'rejected' ? 'active' : '' ?>" onclick="changeView('rejected')">Rejected Nominations</button>
        </div>

        <!-- Filter Form -->
        <form method="get" class="filters">
            <?php if ($view): ?>
                <input type="hidden" name="view" value="<?= $view ?>">
            <?php endif; ?>
            
            <!-- Date Range Filter -->
            <div class="filter-group date-range-group">
                <label>Date Range</label>
                <div class="date-range-container">
                    <input type="date" name="start_date" id="startDate" 
                           value="<?= $_GET['start_date'] ?? '' ?>" 
                           placeholder="Start Date"
                           class="date-input">
                    <span class="date-separator">-</span>
                    <input type="date" name="end_date" id="endDate" 
                           value="<?= $_GET['end_date'] ?? '' ?>" 
                           placeholder="End Date"
                           class="date-input">
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
                <select name="state" id="stateSelect" onchange="loadDistricts(this.value)">
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
                <input type="text" name="search" id="searchInput" 
                       placeholder="Participant name..." 
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="filter-btn">Filter/Search</button>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <a href="admin-nomination.php" class="reset-btn">Reset Filters</a>
            </div>
        </form>

        <!-- Results Table -->
        <?php if (!empty($results)): ?>
            <div id="reportSection">
                <table border="1" cellspacing="0" cellpadding="5">
                    <thead>
                        <tr>
                            <th>Serial No.</th>
                            <th>Participant Name</th>
                            <th>Course Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>State</th>
                            <th>District</th>
                            <th>Phone</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $serial = 1; ?>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?= $serial++ ?></td>
                                <td><?= htmlspecialchars($row['participant_name']) ?></td>
                                <td><?= htmlspecialchars($row['course_name']) ?></td>
                                <td><?= date("d-m-Y", strtotime($row['start_date'])) ?></td>
                                <td><?= date("d-m-Y", strtotime($row['end_date'])) ?></td>
                                <td><?= htmlspecialchars($row['state_name']) ?></td>
                                <td><?= htmlspecialchars($row['district_name']) ?></td>
                                <td><?= htmlspecialchars($row['residential_phone'] ?? 'N/A') ?></td>
                                <td class="action-btns">
                                    <?php if ($table === 'nominee'): ?>
                                        <a href="admin-nomination.php?action=accept&id=<?= $row['id'] ?>&token=<?= $_SESSION['csrf_token'] ?>&start_date=<?= urlencode($_GET['start_date'] ?? '') ?>&end_date=<?= urlencode($_GET['end_date'] ?? '') ?>&course=<?= urlencode($_GET['course'] ?? '') ?>&state=<?= urlencode($_GET['state'] ?? '') ?>&district=<?= urlencode($_GET['district'] ?? '') ?>&gender=<?= urlencode($_GET['gender'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" class="accept-btn">Accept</a>
                                        <button onclick="showRejectionModal(<?= $row['id'] ?>)" class="reject-btn">Reject</button>
                                    <?php else: ?>
                                        <a href="admin-nomination.php?action=revert&id=<?= $row['id'] ?>&from=<?= $view ?>&view=<?= $view ?>&token=<?= $_SESSION['csrf_token'] ?>" class="revert-btn">Revert</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="btn-container">
                <button onclick="printReport()" class="action-btn print-btn">Print Report</button>
                <button onclick="printStateWiseReport()" class="action-btn print-btn">State-wise Report</button>
                <button onclick="printStateCourseWiseReport()" class="action-btn print-btn">State & Course Report</button>
                <a href="admin-dashboard.php" class="action-btn dashboard-btn">Back to Dashboard</a>
            </div>
        <?php else: ?>
            <p class="no-data">
                No data found matching your criteria.
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

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <h2>Reject Nomination</h2>
            <p>Please select a reason for rejection:</p>
            <form id="rejectionForm">
                <input type="hidden" id="nomineeId" name="id">
                <div>
                    <input type="radio" id="reason1" name="rejectionReason" value="Incomplete application">
                    <label for="reason1">Incomplete application</label>
                </div>
                <div>
                    <input type="radio" id="reason2" name="rejectionReason" value="Does not meet eligibility criteria">
                    <label for="reason2">Does not meet eligibility criteria</label>
                </div>
                <div>
                    <input type="radio" id="reason3" name="rejectionReason" value="Quota exceeded">
                    <label for="reason3">Quota exceeded</label>
                </div>
                <div>
                    <input type="radio" id="reasonOther" name="rejectionReason" value="Other">
                    <label for="reasonOther">Other</label>
                </div>
                <div id="otherReasonContainer" style="display: none; margin-top: 10px;">
                    <textarea id="otherReasonText" placeholder="Please specify reason" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;"></textarea>
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="button" class="reject-btn" style="padding: 10px 20px;" onclick="submitRejection()">Confirm Rejection</button>
                    <button type="button" class="reset-btn" style="padding: 10px 20px;" onclick="closeRejectionModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

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

        function changeView(view) {
            const url = new URL(window.location.href);
            if (view) {
                url.searchParams.set('view', view);
            } else {
                url.searchParams.delete('view');
            }
            window.location.href = url.toString();
        }

        function loadDistricts(stateName) {
            const districtSelect = document.getElementById("districtSelect");
            districtSelect.innerHTML = '<option value="">Loading...</option>';

            fetch('get-districts.php?state=' + encodeURIComponent(stateName))
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">-- Select District --</option>';
                    data.forEach(d => {
                        options += `<option value="${d.district_name}">${d.district_name}</option>`;
                    });
                    districtSelect.innerHTML = options;
                
                    // Restore selected district if any
                    const selectedDistrict = "<?= $_GET['district'] ?? '' ?>";
                    if (selectedDistrict) {
                        districtSelect.value = selectedDistrict;
                    }
                });
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

        function printReport() {
            // Clone the report section
            const reportClone = document.getElementById('reportSection').cloneNode(true);
    
            // Create a print container
            const printContainer = document.createElement('div');
            printContainer.id = 'print-container';
    
            // Add title with logos
            const header = document.createElement('div');
            header.style.display = 'flex';
            header.style.justifyContent = 'space-between';
            header.style.alignItems = 'center';
            header.style.marginBottom = '20px';
            header.style.borderBottom = '2px solid #003366';
            header.style.paddingBottom = '15px';
            
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
            subtitle.textContent = 'Nomination Management Report';
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
            <?php if (!empty($_GET['start_date'])): ?>
                filters.push(`Start Date: <?= htmlspecialchars($_GET['start_date']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['end_date'])): ?>
                filters.push(`End Date: <?= htmlspecialchars($_GET['end_date']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['course'])): ?>
                filters.push(`Course: <?= htmlspecialchars($_GET['course']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['state'])): ?>
                filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['district'])): ?>
                filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['gender'])): ?>
                filters.push(`Gender: <?= htmlspecialchars($_GET['gender']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['search'])): ?>
                filters.push(`Search: <?= htmlspecialchars($_GET['search']) ?>`);
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
            
            // Add report view info
            const viewInfo = document.createElement('div');
            viewInfo.style.marginBottom = '15px';
            viewInfo.style.fontWeight = 'bold';
            viewInfo.innerHTML = `Report View: <?= $view ? ucfirst($view) : 'Received' ?> Nominations`;
            printContainer.appendChild(viewInfo);
            
            // Add the cloned report (without action buttons)
            const table = reportClone.querySelector('table');
            // Remove action column
            const headerRow = table.tHead.rows[0];
            headerRow.deleteCell(headerRow.cells.length - 1);
            
            const rows = table.tBodies[0].rows;
            for (let i = 0; i < rows.length; i++) {
                rows[i].deleteCell(rows[i].cells.length - 1);
            }
            
            // Apply professional styling to table
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            table.style.fontSize = '10pt';
            table.style.marginTop = '20px';
            
            const thCells = table.querySelectorAll('th');
            thCells.forEach(th => {
                th.style.backgroundColor = '#003366';
                th.style.color = 'white';
                th.style.padding = '10px';
                th.style.border = '1px solid #ddd';
            });
            
            const tdCells = table.querySelectorAll('td');
            tdCells.forEach(td => {
                td.style.padding = '8px';
                td.style.border = '1px solid #ddd';
            });
            
            printContainer.appendChild(table);
            
            // Add footer
            const footer = document.createElement('div');
            footer.style.marginTop = '30px';
            footer.style.textAlign = 'center';
            footer.style.fontSize = '10pt';
            footer.style.color = '#666';
            footer.innerHTML = `
                <p>National Crime Records Bureau<br>
                Ministry of Home Affairs, Government of India</p>
                <p>Generated by NCRB Participant Nomination System</p>
            `;
            printContainer.appendChild(footer);
    
            // Create a print window
            const printWindow = window.open('', '', 'width=1000,height=700');
            printWindow.document.write('<html><head><title>NCRB Nomination Report</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
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

        function printStateWiseReport() {
            const stateWiseData = <?= json_encode($stateWiseData) ?>;
    
            if (stateWiseData.length === 0) {
                alert('No data available for state-wise report');
                return;
            }
    
            // Create report container
            const printContainer = document.createElement('div');
            printContainer.id = 'print-container';
    
            // Add title with logos
            const header = document.createElement('div');
            header.style.display = 'flex';
            header.style.justifyContent = 'space-between';
            header.style.alignItems = 'center';
            header.style.marginBottom = '20px';
            header.style.borderBottom = '2px solid #003366';
            header.style.paddingBottom = '15px';
            
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
            subtitle.textContent = 'State-wise Nomination Report';
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
            <?php if (!empty($_GET['start_date'])): ?>
                filters.push(`Start Date: <?= htmlspecialchars($_GET['start_date']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['end_date'])): ?>
                filters.push(`End Date: <?= htmlspecialchars($_GET['end_date']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['course'])): ?>
                filters.push(`Course: <?= htmlspecialchars($_GET['course']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['state'])): ?>
                filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['district'])): ?>
                filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['gender'])): ?>
                filters.push(`Gender: <?= htmlspecialchars($_Get['gender']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['search'])): ?>
                filters.push(`Search: <?= htmlspecialchars($_GET['search']) ?>`);
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
            
            // Create table
            const table = document.createElement('table');
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            table.style.fontSize = '10pt';
            table.style.marginTop = '20px';
            
            // Create table header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            
            ['S.No.', 'State', 'Number of Nominations'].forEach(text => {
                const th = document.createElement('th');
                th.textContent = text;
                th.style.backgroundColor = '#003366';
                th.style.color = 'white';
                th.style.padding = '10px';
                th.style.border = '1px solid #ddd';
                headerRow.appendChild(th);
            });
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create table body
            const tbody = document.createElement('tbody');
            let total = 0;
            
            stateWiseData.forEach((row, index) => {
                const tr = document.createElement('tr');
                
                const td1 = document.createElement('td');
                td1.textContent = index + 1;
                td1.style.padding = '8px';
                td1.style.border = '1px solid #ddd';
                tr.appendChild(td1);
                
                const td2 = document.createElement('td');
                td2.textContent = row.state_name;
                td2.style.padding = '8px';
                td2.style.border = '1px solid #ddd';
                tr.appendChild(td2);
                
                const td3 = document.createElement('td');
                td3.textContent = row.count;
                td3.style.padding = '8px';
                td3.style.border = '1px solid #ddd';
                td3.style.textAlign = 'center';
                tr.appendChild(td3);
                
                tbody.appendChild(tr);
                total += parseInt(row.count);
            });
            
            // Add total row
            const totalRow = document.createElement('tr');
            totalRow.style.fontWeight = 'bold';
            
            const totalTd1 = document.createElement('td');
            totalTd1.textContent = '';
            totalTd1.style.padding = '8px';
            totalTd1.style.border = '1px solid #ddd';
            totalRow.appendChild(totalTd1);
            
            const totalTd2 = document.createElement('td');
            totalTd2.textContent = 'Total';
            totalTd2.style.padding = '8px';
            totalTd2.style.border = '1px solid #ddd';
            totalRow.appendChild(totalTd2);
            
            const totalTd3 = document.createElement('td');
            totalTd3.textContent = total;
            totalTd3.style.padding = '8px';
            totalTd3.style.border = '1px solid #ddd';
            totalTd3.style.textAlign = 'center';
            totalRow.appendChild(totalTd3);
            
            tbody.appendChild(totalRow);
            table.appendChild(tbody);
            printContainer.appendChild(table);
            
            // Add footer
            const footer = document.createElement('div');
            footer.style.marginTop = '30px';
            footer.style.textAlign = 'center';
            footer.style.fontSize = '10pt';
            footer.style.color = '#666';
            footer.innerHTML = `
                <p>National Crime Records Bureau<br>
                Ministry of Home Affairs, Government of India</p>
                <p>Generated by NCRB Participant Nomination System</p>
            `;
            printContainer.appendChild(footer);
    
            // Create a print window
            const printWindow = window.open('', '', 'width=1000,height=700');
            printWindow.document.write('<html><head><title>NCRB State-wise Report</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
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

        function printStateCourseWiseReport() {
            const stateCourseWiseData = <?= json_encode($stateCourseWiseData) ?>;
    
            if (stateCourseWiseData.length === 0) {
                alert('No data available for state & course-wise report');
                return;
            }
    
            // Create report container
            const printContainer = document.createElement('div');
            printContainer.id = 'print-container';
    
            // Add title with logos
            const header = document.createElement('div');
            header.style.display = 'flex';
            header.style.justifyContent = 'space-between';
            header.style.alignItems = 'center';
            header.style.marginBottom = '20px';
            header.style.borderBottom = '2px solid #003366';
            header.style.paddingBottom = '15px';
            
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
            subtitle.textContent = 'State & Course-wise Nomination Report';
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
            <?php if (!empty($_GET['start_date'])): ?>
                filters.push(`Start Date: <?= htmlspecialchars($_GET['start_date']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['end_date'])): ?>
                filters.push(`End Date: <?= htmlspecialchars($_GET['end_date']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['course'])): ?>
                filters.push(`Course: <?= htmlspecialchars($_GET['course']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['state'])): ?>
                filters.push(`State: <?= htmlspecialchars($_GET['state']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['district'])): ?>
                filters.push(`District: <?= htmlspecialchars($_GET['district']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['gender'])): ?>
                filters.push(`Gender: <?= htmlspecialchars($_GET['gender']) ?>`);
            <?php endif; ?>
            <?php if (!empty($_GET['search'])): ?>
                filters.push(`Search: <?= htmlspecialchars($_GET['search']) ?>`);
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
            
            // Create table
            const table = document.createElement('table');
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            table.style.fontSize = '10pt';
            table.style.marginTop = '20px';
            
            // Create table header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            
            ['S.No.', 'State', 'Course', 'Number of Nominations'].forEach(text => {
                const th = document.createElement('th');
                th.textContent = text;
                th.style.backgroundColor = '#003366';
                th.style.color = 'white';
                th.style.padding = '10px';
                th.style.border = '1px solid #ddd';
                headerRow.appendChild(th);
            });
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create table body
            const tbody = document.createElement('tbody');
            let total = 0;
            
            stateCourseWiseData.forEach((row, index) => {
                const tr = document.createElement('tr');
                
                const td1 = document.createElement('td');
                td1.textContent = index + 1;
                td1.style.padding = '8px';
                td1.style.border = '1px solid #ddd';
                tr.appendChild(td1);
                
                const td2 = document.createElement('td');
                td2.textContent = row.state_name;
                td2.style.padding = '8px';
                td2.style.border = '1px solid #ddd';
                tr.appendChild(td2);
                
                const td3 = document.createElement('td');
                td3.textContent = row.course_name;
                td3.style.padding = '8px';
                td3.style.border = '1px solid #ddd';
                tr.appendChild(td3);
                
                const td4 = document.createElement('td');
                td4.textContent = row.count;
                td4.style.padding = '8px';
                td4.style.border = '1px solid #ddd';
                td4.style.textAlign = 'center';
                tr.appendChild(td4);
                
                tbody.appendChild(tr);
                total += parseInt(row.count);
            });
            
            // Add total row
            const totalRow = document.createElement('tr');
            totalRow.style.fontWeight = 'bold';
            
            const totalTd1 = document.createElement('td');
            totalTd1.textContent = '';
            totalTd1.style.padding = '8px';
            totalTd1.style.border = '1px solid #ddd';
            totalRow.appendChild(totalTd1);
            
            const totalTd2 = document.createElement('td');
            totalTd2.textContent = 'Total';
            totalTd2.style.padding = '8px';
            totalTd2.style.border = '1px solid #ddd';
            totalRow.appendChild(totalTd2);
            
            const totalTd3 = document.createElement('td');
            totalTd3.textContent = '';
            totalTd3.style.padding = '8px';
            totalTd3.style.border = '1px solid #ddd';
            totalRow.appendChild(totalTd3);
            
            const totalTd4 = document.createElement('td');
            totalTd4.textContent = total;
            totalTd4.style.padding = '8px';
            totalTd4.style.border = '1px solid #ddd';
            totalTd4.style.textAlign = 'center';
            totalRow.appendChild(totalTd4);
            
            tbody.appendChild(totalRow);
            table.appendChild(tbody);
            printContainer.appendChild(table);
            
            // Add footer
            const footer = document.createElement('div');
            footer.style.marginTop = '30px';
            footer.style.textAlign = 'center';
            footer.style.fontSize = '10pt';
            footer.style.color = '#666';
            footer.innerHTML = `
                <p>National Crime Records Bureau<br>
                Ministry of Home Affairs, Government of India</p>
                <p>Generated by NCRB Participant Nomination System</p>
            `;
            printContainer.appendChild(footer);
    
            // Create a print window
            const printWindow = window.open('', '', 'width=1000,height=700');
            printWindow.document.write('<html><head><title>NCRB State & Course Report</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('@media print { body { margin: 1cm; font-family: "Times New Roman", serif; } }');
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

        // Rejection modal functions
        function showRejectionModal(nomineeId) {
            document.getElementById('rejectionModal').style.display = 'block';
            document.getElementById('nomineeId').value = nomineeId;
            // Reset form state
            document.querySelectorAll('input[name="rejectionReason"]').forEach(radio => {
                radio.checked = false;
            });
            document.getElementById('otherReasonContainer').style.display = 'none';
            document.getElementById('otherReasonText').value = '';
        }

        function closeRejectionModal() {
            document.getElementById('rejectionModal').style.display = 'none';
        }

        function toggleOtherReason() {
            const otherRadio = document.querySelector('input[name="rejectionReason"][value="Other"]');
            const otherContainer = document.getElementById('otherReasonContainer');
            otherContainer.style.display = otherRadio.checked ? 'block' : 'none';
        }

        function submitRejection() {
            const selectedReason = document.querySelector('input[name="rejectionReason"]:checked');
            let reason = '';
        
            if (!selectedReason) {
                alert('Please select a rejection reason');
                return;
            }
        
            if (selectedReason.value === 'Other') {
                reason = document.getElementById('otherReasonText').value.trim();
                if (reason === '') {
                    alert('Please specify the reason for rejection');
                    return;
                }
            } else {
                reason = selectedReason.value;
            }
        
            const nomineeId = document.getElementById('nomineeId').value;
            const token = "<?= $_SESSION['csrf_token'] ?>";
        
            // Get all current query parameters
            const queryParams = new URLSearchParams(window.location.search);
            queryParams.delete('action');
            queryParams.delete('id');
            queryParams.delete('token');
            queryParams.delete('from');
        
            // Redirect with rejection action
            window.location.href = `admin-nomination.php?action=reject&id=${nomineeId}&token=${token}&${queryParams.toString()}&reason=${encodeURIComponent(reason)}`;
        }

        // Initialize districts if state is already selected
        $(document).ready(function() {
            const selectedState = "<?= $selectedState ?>";
            if (selectedState) {
                loadDistricts(selectedState);
            }
        
            // Setup event listeners for radio buttons
            document.querySelectorAll('input[name="rejectionReason"]').forEach(radio => {
                radio.addEventListener('change', toggleOtherReason);
            });
            
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>