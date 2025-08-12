<?php
session_start();

// Include PHPMailer
require_once __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

// Initialize Twilio client
$twilio_sid = 'AC5329f9c47dfa4be553ce996325a4a1b3';
$twilio_token = '72d6f66055f29e031d04accd8ec1fd94';
$twilio = new Client($twilio_sid, $twilio_token);

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'nikhilguptaji@gmail.com');
define('SMTP_PASSWORD', 'dmmufijsqwaoenpy');
define('SMTP_FROM', 'noreply@ncrb.gov.in');
define('SMTP_FROM_NAME', 'NCRB Registration');

// Helper function to establish database connection
function getDBConnection() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Create required tables if they don't exist
function createRequiredTables($conn) {
    // Create accepted_participants table
    $conn->query("CREATE TABLE IF NOT EXISTS accepted_participants (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        registration_id VARCHAR(50) NOT NULL UNIQUE,
        participant_name VARCHAR(255) NOT NULL,
        participant_name_hindi VARCHAR(255),
        gender ENUM('Male','Female','Other') NOT NULL,
        category_name VARCHAR(50) NOT NULL,
        category_name_hindi VARCHAR(50),
        rank VARCHAR(100) NOT NULL,
        rank_hindi VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        qualifications VARCHAR(255),
        residential_address TEXT,
        state_name VARCHAR(100) NOT NULL,
        state_name_hindi VARCHAR(100),
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(20) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255),
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        course_expectation TEXT
    )");
    
    // Create registration table
    $conn->query("CREATE TABLE IF NOT EXISTS registration (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        userid VARCHAR(50) NOT NULL,
        registration_id VARCHAR(50) NOT NULL,
        participant_name VARCHAR(255) NOT NULL,
        participant_name_hindi VARCHAR(255),
        gender ENUM('Male','Female','Other') NOT NULL,
        category_name VARCHAR(50) NOT NULL,
        category_name_hindi VARCHAR(50),
        rank VARCHAR(100) NOT NULL,
        rank_hindi VARCHAR(100),
        email VARCHAR(255) NOT NULL,
        qualifications VARCHAR(255),
        residential_address TEXT,
        state_name VARCHAR(100) NOT NULL,
        state_name_hindi VARCHAR(100),
        district_name VARCHAR(100),
        official_address TEXT,
        residential_phone VARCHAR(20) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255),
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        course_expectation TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create participant_users table
    $conn->query("CREATE TABLE IF NOT EXISTS participant_users (
        userid VARCHAR(50) PRIMARY KEY,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Initialize form data
$formData = [
    'userid' => '',
    'registration_id' => '',
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
    'course_name' => '',
    'course_name_hindi' => '',
    'start_date' => '',
    'end_date' => '',
    'course_expectation' => '',
    'other_category' => ''
];

$success = '';
$error = '';

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
    header('Content-Type: application/json');
    
    $response = ['status' => 'error', 'message' => 'Unknown error'];
    
    try {
        $conn = getDBConnection();
        createRequiredTables($conn);
        
        // Get user details based on registration ID
        if ($_GET['ajax'] == 'get_user_details') {
            if (!isset($_GET['registration_id'])) {
                throw new Exception('Registration ID is required');
            }
            
            $registrationId = $conn->real_escape_string($_GET['registration_id']);
            $stmt = $conn->prepare("SELECT * FROM accepted_participants WHERE registration_id = ?");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $registrationId);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user['start_date'] = $user['start_date'] ? date('d-m-Y', strtotime($user['start_date'])) : '';
                $user['end_date'] = $user['end_date'] ? date('d-m-Y', strtotime($user['end_date'])) : '';
                $response = ['status' => 'success', 'user' => $user];
            } else {
                $response = ['status' => 'error', 'message' => 'Registration not found'];
            }
            $stmt->close();
        } 
        // Get ranks for a category
        elseif ($_GET['ajax'] == 'get_ranks') {
            if (!isset($_GET['category'])) {
                throw new Exception('Category is required');
            }
            
            $category = rawurldecode($_GET['category']);
            $ranks = [];
            
            $sql = "SELECT rank, rank_hindi FROM ranks WHERE category_name = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $category);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $ranks[] = [
                    'rank' => $row['rank'],
                    'rank_hindi' => $row['rank_hindi']
                ];
            }
            
            $stmt->close();
            $response = ['status' => 'success', 'ranks' => $ranks];
        } 
        else {
            $response = ['status' => 'error', 'message' => 'Invalid AJAX request'];
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    } finally {
        if (isset($conn)) $conn->close();
    }
    
    echo json_encode($response);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_id'])) {
    // CAPTCHA validation
    $userCaptcha = trim($_POST['captcha'] ?? '');
    $sessionCaptcha = trim($_SESSION['captcha_code'] ?? '');
    
    if (strcasecmp($userCaptcha, $sessionCaptcha) !== 0) {
        $error = "Invalid CAPTCHA code. Please try again.";
        $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    } else {
        $conn = getDBConnection();
        createRequiredTables($conn);
        
        // Collect form data
        foreach ($formData as $key => $value) {
            if (isset($_POST[$key])) {
                $formData[$key] = $conn->real_escape_string(trim($_POST[$key]));
            }
        }
        
        // Handle "Other" category
        if ($formData['category_name'] === 'Other' && !empty($formData['other_category'])) {
            $formData['category_name'] = $formData['other_category'];
            $formData['category_name_hindi'] = $formData['other_category'];
        }
        
        // Date conversion
        $dateErrors = false;
        $startDateMySQL = null;
        $endDateMySQL = null;
        
        if (!empty($formData['start_date'])) {
            $startDate = DateTime::createFromFormat('d-m-Y', $formData['start_date']);
            if ($startDate) {
                $startDateMySQL = $startDate->format('Y-m-d');
            } else {
                $dateErrors = true;
                $error = "Invalid start date format. Use DD-MM-YYYY.";
            }
        }
        
        if (!empty($formData['end_date'])) {
            $endDate = DateTime::createFromFormat('d-m-Y', $formData['end_date']);
            if ($endDate) {
                $endDateMySQL = $endDate->format('Y-m-d');
            } else {
                $dateErrors = true;
                $error = "Invalid end date format. Use DD-MM-YYYY.";
            }
        }
                    
        // Generate password
        $password = substr(md5(uniqid(rand(), true)), 0, 8);
        $formData['userid'] = $formData['registration_id'];
        
        if (empty($error) && !$dateErrors) {
            $conn->begin_transaction();
            
            try {
                // Insert registration data
                $sql = "INSERT INTO registration (
                    userid, registration_id, participant_name, participant_name_hindi, gender, 
                    category_name, category_name_hindi, rank, rank_hindi, email, qualifications, 
                    residential_address, state_name, state_name_hindi, district_name, official_address,
                    residential_phone, course_name, course_name_hindi, start_date, 
                    end_date, course_expectation
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                
                // Create type string and parameters array
                $typeString = str_repeat('s', 22); // 22 parameters
                $params = [
                    $formData['userid'], 
                    $formData['registration_id'],
                    $formData['participant_name'], 
                    $formData['participant_name_hindi'],
                    $formData['gender'], 
                    $formData['category_name'], 
                    $formData['category_name_hindi'],
                    $formData['rank'], 
                    $formData['rank_hindi'], 
                    $formData['email'], 
                    $formData['qualifications'], 
                    $formData['residential_address'], 
                    $formData['state_name'], 
                    $formData['state_name_hindi'], 
                    $formData['district_name'], 
                    $formData['official_address'], 
                    $formData['residential_phone'],
                    $formData['course_name'], 
                    $formData['course_name_hindi'], 
                    $startDateMySQL,
                    $endDateMySQL, 
                    $formData['course_expectation']
                ];
                
                // Validate parameter count
                if (count($params) !== 22) {
                    throw new Exception("Parameter count mismatch: expected 22, got " . count($params));
                }
                
                // Bind parameters using variable expansion
                $stmt->bind_param($typeString, ...$params);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // Create user account
                    $user_sql = "INSERT INTO participant_users (userid, password, created_at) VALUES (?, ?, NOW())";
                    $user_stmt = $conn->prepare($user_sql);
                    if (!$user_stmt) throw new Exception("Prepare failed: " . $conn->error);
                    
                    $user_stmt->bind_param("ss", $formData['userid'], $password);
                    
                    if ($user_stmt->execute()) {
                        $user_stmt->close();
                        
                        try {
                            // Send registration details via Email
                            $transport = (new Swift_SmtpTransport(SMTP_HOST, SMTP_PORT, 'ssl'))
                                ->setUsername(SMTP_USERNAME)
                                ->setPassword(SMTP_PASSWORD);
                            
                            $mailer = new Swift_Mailer($transport);
                            
                            // Professional email template
                            $htmlContent = '
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <style>
                                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                                    .email-container { max-width: 700px; margin: 0 auto; border: 1px solid #ddd; border-radius: 5px; }
                                    .email-header { background: #003366; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                                    .email-body { padding: 30px; background: #f9f9f9; }
                                    .email-footer { background: #e9ecef; padding: 15px; text-align: center; font-size: 0.9em; color: #6c757d; border-radius: 0 0 5px 5px; }
                                    .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                                    .details-table th, .details-table td { border: 1px solid #dee2e6; padding: 10px; text-align: left; }
                                    .details-table th { background-color: #e9ecef; width: 30%; }
                                    .section-title { font-size: 1.2em; color: #003366; margin-top: 25px; padding-bottom: 5px; border-bottom: 2px solid #003366; }
                                    .credentials { background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; }
                                </style>
                            </head>
                            <body>
                                <div class="email-container">
                                    <div class="email-header">
                                        <h2>National Crime Records Bureau</h2>
                                        <h3>Registration Confirmation</h3>
                                    </div>
                                    
                                    <div class="email-body">
                                        <p>Dear ' . htmlspecialchars($formData['participant_name']) . ',</p>
                                        <p>Your registration for the following course has been successfully processed:</p>
                                        
                                        <div class="section-title">Course Details</div>
                                        <table class="details-table">
                                            <tr>
                                                <th>Course Name</th>
                                                <td>' . htmlspecialchars($formData['course_name']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Course Dates</th>
                                                <td>' . htmlspecialchars($formData['start_date']) . ' to ' . htmlspecialchars($formData['end_date']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Course Expectations</th>
                                                <td>' . htmlspecialchars($formData['course_expectation']) . '</td>
                                            </tr>
                                        </table>
                                        
                                        <div class="section-title">Personal Information</div>
                                        <table class="details-table">
                                            <tr>
                                                <th>Full Name</th>
                                                <td>' . htmlspecialchars($formData['participant_name']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Gender</th>
                                                <td>' . htmlspecialchars($formData['gender']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Category</th>
                                                <td>' . htmlspecialchars($formData['category_name']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Rank</th>
                                                <td>' . htmlspecialchars($formData['rank']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Qualifications</th>
                                                <td>' . htmlspecialchars($formData['qualifications']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td>' . htmlspecialchars($formData['email']) . '</td>
                                            </tr>
                                            <tr>
                                                <th>Phone</th>
                                                <td>' . htmlspecialchars($formData['residential_phone']) . '</td>
                                            </tr>
                                        </table>
                                        
                                        <div class="section-title">Login Credentials</div>
                                        <div class="credentials">
                                            <p><strong>Registration ID / User ID:</strong> ' . $formData['registration_id'] . '</p>
                                            <p><strong>Password:</strong> ' . $password . '</p>
                                            <p>Please keep this information safe for accessing your account.</p>
                                        </div>
                                        
                                        <p>Thank you for registering with NCRB.</p>
                                        <p>Regards,<br>Training Division<br>National Crime Records Bureau</p>
                                    </div>
                                    
                                    <div class="email-footer">
                                        <p>National Crime Records Bureau<br>
                                        Ministry of Home Affairs, Government of India<br>
                                        New Delhi, India</p>
                                        <p>This is an automated message. Please do not reply to this email.</p>
                                    </div>
                                </div>
                            </body>
                            </html>';
                            
                            $message = (new Swift_Message('Your Registration Details for NCRB Course'))
                                ->setFrom([SMTP_FROM => SMTP_FROM_NAME])
                                ->setTo([$formData['email'] => $formData['participant_name']])
                                ->setBody($htmlContent, 'text/html');

                            $mailer->send($message);
                            
                            // Send registration details via SMS and WhatsApp
                            $phone = $formData['residential_phone'] ?? '';
                            $smsBody = "NCRB REGISTRATION CONFIRMATION\n\n";
                            $smsBody .= "Name: " . $formData['participant_name'] . "\n";
                            $smsBody .= "Course: " . $formData['course_name'] . "\n";
                            $smsBody .= "Dates: " . $formData['start_date'] . " to " . $formData['end_date'] . "\n";
                            $smsBody .= "User ID: " . $formData['registration_id'] . "\n";
                            $smsBody .= "Password: " . $password . "\n";
                            
                            $phoneMessage = '';
                            if ($phone && preg_match('/^\d{10}$/', $phone)) {
                                $smsSuccess = false;
                                $waSuccess = false;
                                
                                // Send SMS
                                try {
                                    $twilio->messages->create(
                                        "+91" . $phone,
                                        [
                                            'from' => '+19472148608',
                                            'body' => $smsBody
                                        ]
                                    );
                                    $smsSuccess = true;
                                } catch (Exception $smsEx) {
                                    error_log("SMS sending error: " . $smsEx->getMessage());
                                }
                                
                                // Send WhatsApp
                                try {
                                    $twilio->messages->create(
                                        "whatsapp:+91" . $phone,
                                        [
                                            'from' => 'whatsapp:+14155238886',
                                            'body' => $smsBody
                                        ]
                                    );
                                    $waSuccess = true;
                                } catch (Exception $waEx) {
                                    error_log("WhatsApp sending error: " . $waEx->getMessage());
                                }
                                
                                // Build success message
                                if ($smsSuccess && $waSuccess) {
                                    $phoneMessage = " Confirmation sent via SMS and WhatsApp.";
                                } elseif ($smsSuccess) {
                                    $phoneMessage = " Confirmation sent via SMS.";
                                } elseif ($waSuccess) {
                                    $phoneMessage = " Confirmation sent via WhatsApp.";
                                }
                            }
                            
                            $conn->commit();
                            $success = "Registration submitted successfully! Your login credentials have been sent to your email." . $phoneMessage;
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = "Registration saved but notifications could not be sent. Error: " . $e->getMessage();
                        }
                    } else {
                        $conn->rollback();
                        $error = "Error creating user account: " . $user_stmt->error;
                    }
                } else {
                    $conn->rollback();
                    $error = "Error saving registration data: " . $stmt->error;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Database error: " . $e->getMessage();
            }
        }
        
        $conn->close();
    }
}

// Setup database cleanup event
try {
    $conn = getDBConnection();
    createRequiredTables($conn);
    
    // Add created_at columns if not exists
    $conn->query("ALTER TABLE participant_users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    // Create cleanup event (runs every 6 months)
    $conn->query("
        CREATE EVENT IF NOT EXISTS cleanup_old_data
        ON SCHEDULE EVERY 6 MONTH
        STARTS CURRENT_TIMESTAMP
        DO
        BEGIN
            DELETE FROM participant_users WHERE created_at < NOW() - INTERVAL 6 MONTH;
        END
    ");
    
    $conn->close();
} catch (Exception $e) {
    error_log("Database cleanup setup error: " . $e->getMessage());
}

// Fetch data for form
$conn = getDBConnection();
createRequiredTables($conn);
$states = $conn->query("SELECT state_name, state_name_hindi FROM states");

// Get categories
$categories = [];
$result = $conn->query("SELECT DISTINCT category_name, category_name_hindi FROM categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'category_name' => $row['category_name'],
        'category_name_hindi' => $row['category_name_hindi']
    ];
}
$categories[] = ['category_name' => 'Other', 'category_name_hindi' => 'अन्य'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Registration Form | National Crime Records Bureau</title>
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            color: var(--white);
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

        /* Added for better visibility of other fields */
        .other-category-container {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        /* Form note styling */
        .form-note {
            background-color: #e6f0fa;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
            font-size: 0.95rem;
        }
        
        /* Loading spinner */
        .autofill-loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
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
            background: #2c3e50; /* Dark blue-gray background */
            color: #ecf0f1; /* Light text color */
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            position: relative;
            border: 1px solid #1a252f; /* Darker border */
        }
        
        .modal-header {
            background: #1a252f; /* Darker header */
            color: #fff;
            padding: 20px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            border-bottom: 2px solid #f39c12; /* Accent border */
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
            color: #f39c12; /* Golden accent color */
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Developer Contact Modal */
        #developerContactModal .developer-contact h3 {
            color: #f39c12; /* Golden accent color */
            font-size: 1.4rem;
            margin-bottom: 20px;
            text-align: center;
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
            text-align: center;
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            text-align: center;
        }
        
        #developerContactModal .contact-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        
        #developerContactModal .contact-btn i {
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

        .mailto-link {
            color: var(--white);
            text-decoration: none;
            transition: var(--transition);
        }
        .mailto-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        @keyframes spin { 
            to { transform: rotate(360deg); }
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
                    <i class="fas fa-user-graduate me-2"></i> Registration Form
                </div>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-user-graduate me-2"></i> Registration Form</h2>
            </div>
            
            <div class="form-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" id="registrationForm">
                    <!-- Registration Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-id-card me-2"></i> Registration Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <label for="registration_id" class="form-label required">Nominee ID</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="registration_id" name="registration_id" required 
                                        placeholder="Enter your nominee ID">
                                    <button type="button" class="btn btn-action" id="fetch-details-btn">Fetch Details</button>
                                </div>
                                <div class="autofill-loader" id="userid-loader"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Course Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-book me-2"></i> Course Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="course_name" class="form-label required">Course Name</label>
                                <input class="form-control" id="course_name" name="course_name" required readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_name_hindi" class="form-label required">कोर्स का नाम</label>
                                <input class="form-control" id="course_name_hindi" name="course_name_hindi" required readonly>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label for="start_date" class="form-label required">Start Date</label>
                                <input type="text" class="form-control datepicker" id="start_date" name="start_date" required readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="end_date" class="form-label required">End Date</label>
                                <input type="text" class="form-control datepicker" id="end_date" name="end_date" required readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_expectation" class="form-label required">Course Expectations</label>
                                <textarea class="form-control" id="course_expectation" name="course_expectation" rows="1" required readonly></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user me-2"></i> Personal Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-5 mb-3">
                                <label for="participant_name" class="form-label required">Name (English)</label>
                                <input type="text" class="form-control" id="participant_name" name="participant_name" required readonly>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="participant_name_hindi" class="form-label required">नाम (हिन्दी में)</label>
                                <input type="text" class="form-control hindi-input" id="participant_name_hindi" name="participant_name_hindi" required readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="gender" class="form-label required">Gender</label>
                                <input class="form-control" id="gender" name="gender" required readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-2 mb-3">
                                <label for="category_name" class="form-label required">Category</label>
                                <input class="form-control" id="category_name" name="category_name" required readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="category_name_hindi" class="form-label required">श्रेणी</label>
                                <input class="form-control" id="category_name_hindi" name="category_name_hindi" required readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="rank" class="form-label required">Rank</label>
                                <input class="form-control" id="rank" name="rank" required readonly>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="rank_hindi" class="form-label required">रैंक</label>
                                <input class="form-control" id="rank_hindi" name="rank_hindi" required readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="qualifications" class="form-label">Qualifications</label>
                                <input type="text" class="form-control" id="qualifications" name="qualifications" readonly>
                            </div>
                        </div>
                        
                        <!-- Other Category Fields -->
                        <div class="other-category-container" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="other_category" class="form-label">Specify Category (English)</label>
                                    <input type="text" class="form-control" id="other_category" name="other_category">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="other_category_hindi" class="form-label">श्रेणी निर्दिष्ट करें (हिन्दी)</label>
                                    <input type="text" class="form-control hindi-input" id="other_category_hindi" name="other_category_hindi">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label required">Email Address</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="email" class="form-control" id="email" name="email" required readonly>
                                    <span class="verification-status verified">✓ Verified</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="residential_phone" class="form-label">Mobile Phone</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="tel" class="form-control" id="residential_phone" name="residential_phone" maxlength="10" readonly>
                                    <span class="verification-status verified">✓ Verified</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-home me-2"></i> Address Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="residential_address" class="form-label">Residential Address</label>
                                <textarea class="form-control" id="residential_address" name="residential_address" rows="3" readonly></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="official_address" class="form-label">Official Address</label>
                                <textarea class="form-control" id="official_address" name="official_address" rows="3" readonly></textarea>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label for="state_name" class="form-label required">State</label>
                                <input class="form-control" id="state_name" name="state_name" required readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="state_name_hindi" class="form-label required">राज्य</label>
                                <input class="form-control" id="state_name_hindi" name="state_name_hindi" required readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="district_name" class="form-label">District</label>
                                <input class="form-control" id="district_name" name="district_name" readonly>
                            </div>
                        </div>
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
                                        <i class="fas fa-sync-alt me-1"></i>Refresh
                                    </span>
                                </div>
                                <div class="captcha-input">
                                    <input type="text" class="form-control text-center" id="captcha" name="captcha" required placeholder="Enter CAPTCHA" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <button type="submit" class="btn btn-submit me-3">Submit Registration</button>
                        <button type="reset" class="btn btn-reset">Reset Form</button>
                    </div>
                </form>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
    <script>
    $(document).ready(function() {
        // Initialize date pickers
        flatpickr(".datepicker", {
            dateFormat: "d-m-Y",
            allowInput: true,
            defaultDate: null
        });

        // Fetch user details
        $('#fetch-details-btn').click(function() {
            const registrationId = $('#registration_id').val().trim();
            if (!registrationId) {
                showStatus('Please enter a Nominee ID', 'alert-danger');
                return;
            }
            
            $('#userid-loader').show();
            $(this).prop('disabled', true);
            
            $.ajax({
                url: 'form-auto-register.php?ajax=get_user_details&registration_id=' + encodeURIComponent(registrationId),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const user = response.user;
                        
                        // Fill form fields
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
                        $('#state_name').val(user.state_name || '');
                        $('#state_name_hindi').val(user.state_name_hindi || '');
                        $('#district_name').val(user.district_name || '');
                        $('#official_address').val(user.official_address || '');
                        $('#residential_phone').val(user.residential_phone || '');
                        $('#course_name').val(user.course_name || '');
                        $('#course_name_hindi').val(user.course_name_hindi || '');
                        $('#start_date').val(user.start_date || '');
                        $('#end_date').val(user.end_date || '');
                        $('#course_expectation').val(user.course_expectation || '');
                        
                        // Check for 'Other' category
                        if (user.category_name === 'Other') {
                            $('.other-category-container').show();
                            $('#other_category').prop('required', true);
                        } else {
                            $('.other-category-container').hide();
                            $('#other_category').prop('required', false);
                        }
                        
                        showStatus('Details loaded successfully!', 'alert-success');
                    } else {
                        showStatus('Error: ' + (response.message || 'Registration not found'), 'alert-danger');
                    }
                },
                error: function() {
                    showStatus('Error fetching details', 'alert-danger');
                },
                complete: function() {
                    $('#userid-loader').hide();
                    $('#fetch-details-btn').prop('disabled', false);
                }
            });
        });

        // CAPTCHA refresh
        $('#refresh-captcha').click(function() {
            $.get('form-auto-register.php?refresh_captcha=1', function(data) {
                $('#captcha-code').text(data);
                $('#captcha').val('').focus();
            });
        });
        
        // Form submission
        $('#registrationForm').submit(function(e) {
            e.preventDefault();
            
            let valid = true;
            $('.is-invalid').removeClass('is-invalid');
            
            // Validate required fields
            $('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    valid = false;
                }
            });
            
            // Validate CAPTCHA
            const userCaptcha = $('#captcha').val().trim();
            const sessionCaptcha = $('#captcha-code').text().trim();
            
            if (userCaptcha.toLowerCase() !== sessionCaptcha.toLowerCase()) {
                $('#captcha').addClass('is-invalid');
                showStatus('Invalid CAPTCHA', 'alert-danger');
                $('#refresh-captcha').click();
                valid = false;
            }
                        
            if (!valid) {
                showStatus('Please fill all required fields', 'alert-danger');
                return;
            }
            
            // Disable submit button
            const submitBtn = $('.btn-submit');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');
            
            // Submit form
            this.submit();
        });

        // Reset form
        $('button[type="reset"]').click(function() {
            $('.is-invalid').removeClass('is-invalid');
            $('.other-category-container').hide();
            $('#refresh-captcha').click();
            showStatus('Form reset', 'alert-success');
        });

        // Show status message
        function showStatus(message, type) {
            const alert = $('<div class="alert ' + type + '">' + message + '</div>');
            $('.form-body').prepend(alert);
            setTimeout(() => alert.fadeOut(), 5000);
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
    });

    // Modal functionality
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