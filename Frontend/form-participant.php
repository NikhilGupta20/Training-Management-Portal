<?php
session_start();

// Create database tables if they don't exist
$conn = new mysqli('localhost', 'root', '', 'ncrb_training');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create nominee table
$conn->query("CREATE TABLE IF NOT EXISTS nominee (
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
    residential_phone VARCHAR(15),
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

$conn->close();

// Include PHPMailer for email functionality
require_once __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

// Twilio Configuration for SMS/WhatsApp - USE YOUR OWN CREDENTIALS
$twilio_sid = 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
$twilio_token = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
$twilio = new Client($twilio_sid, $twilio_token);

// Database connection helper
function getDBConnection() {
    global $db_host, $db_user, $db_pass, $db_name;
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Initialize form data with default values
$formData = [
    'participant_name' => '',
    'participant_name_hindi' => '',
    'gender' => '',
    'category_name' => '',
    'category_name_hindi' => '',
    'rank' => '',
    'rank_hindi' => '',
    'other_rank' => '',
    'other_rank_hindi' => '',
    'email' => '',
    'qualifications' => '',
    'residential_address' => '',
    'state_name' => '',
    'state_name_hindi' => '',
    'district_name' => '',
    'official_address' => '',
    'residential_phone' => '',
    'delhi_address' => '',
    'officer_designation' => '',
    'officer_phone' => '', 
    'officer_address' => '',
    'course_name' => '',
    'course_name_hindi' => '',
    'start_date' => '',
    'end_date' => '',
    'course_expectation' => ''
];

$success = '';
$error = '';

// CAPTCHA generation
if (!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
}

// CAPTCHA refresh handler
if (isset($_GET['refresh_captcha'])) {
    $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
    echo $_SESSION['captcha_code'];
    session_write_close();
    exit;
}

// Email OTP generation
if (isset($_GET['generate_otp']) && isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
        session_write_close();
        exit;
    }

    // Clear previous verification
    unset($_SESSION['email_verified']);
    unset($_SESSION['email_otp']);
    
    // Generate OTP
    $otp = rand(100000, 999999);
    $_SESSION['email_otp'] = $otp;
    $_SESSION['email_to_verify'] = $email;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
    
    try {
        // Create the Transport
        $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
            ->setUsername('nikhilguptaji@gmail.com')
            ->setPassword('dmmufijsqwaoenpy');
        
        // Create the Mailer using your created Transport
        $mailer = new Swift_Mailer($transport);
        
        // Create a message
        $message = (new Swift_Message('Your NCRB Participant OTP'))
            ->setFrom(['nikhilguptaji@gmail.com' => 'NCRB'])
            ->setTo([$email])
            ->setBody("Your OTP is: $otp\nValid for 5 minutes");
        
        // Send the message
        $result = $mailer->send($message);
        echo json_encode(['status' => 'success', 'message' => 'OTP sent successfully']);
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again later.']);
    }
    session_write_close();
    exit;
}

// Email OTP verification
if (isset($_POST['verify_otp'])) {
    $user_otp = trim($_POST['otp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($user_otp) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'OTP and email are required']);
        session_write_close();
        exit;
    }
    
    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['email_to_verify']) || !isset($_SESSION['otp_expiry'])) {
        echo json_encode(['status' => 'error', 'message' => 'OTP session expired. Please request a new OTP']);
        session_write_close();
        exit;
    }
    
    if ($_SESSION['email_to_verify'] !== $email) {
        echo json_encode(['status' => 'error', 'message' => 'Email mismatch. Please verify the correct email']);
        session_write_close();
        exit;
    }
    
    if (time() > $_SESSION['otp_expiry']) {
        echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new OTP']);
        session_write_close();
        exit;
    }
    
    if ($user_otp != $_SESSION['email_otp']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again']);
        session_write_close();
        exit;
    }
    
    // If all checks pass
    $_SESSION['email_verified'] = true;
    echo json_encode(['status' => 'success', 'message' => 'Email verified successfully']);
    session_write_close();
    exit;
}

// SMS/WhatsApp OTP generation
if (isset($_GET['generate_sms_otp']) && isset($_GET['phone'])) {
    $phone = trim($_GET['phone']);
    
    // Only process if phone is provided
    if (!empty($phone)) {
        if (!preg_match('/^\d{10}$/', $phone)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
            session_write_close();
            exit;
        }

        // Clear previous verification
        unset($_SESSION['phone_verified']);
        unset($_SESSION['phone_otp']);
        
        // Generate OTP
        $otp = rand(100000, 999999);
        $_SESSION['phone_otp'] = $otp;
        $_SESSION['phone_to_verify'] = $phone;
        $_SESSION['phone_otp_expiry'] = time() + 300; // 5 minutes
        
        try {
            $body = "Your NCRB OTP is: $otp";
            $successCount = 0;
            $errorMessages = [];

            // Send via SMS
            try {
                $twilio->messages->create(
                    "+91" . $phone,
                    [
                        'from' => '+19472148608',
                        'body' => $body
                    ]
                );
                $successCount++;
            } catch (Exception $smsEx) {
                error_log("SMS sending error: " . $smsEx->getMessage());
                $errorMessages[] = 'SMS: ' . $smsEx->getMessage();
            }

            // Send via WhatsApp
            try {
                $twilio->messages->create(
                    "whatsapp:+91" . $phone,
                    [
                        'from' => 'whatsapp:+14155238886',
                        'body' => $body
                    ]
                );
                $successCount++;
            } catch (Exception $waEx) {
                error_log("WhatsApp sending error: " . $waEx->getMessage());
                $errorMessages[] = 'WhatsApp: ' . $waEx->getMessage();
            }

            if ($successCount > 0) {
                $message = 'OTP sent successfully';
                if ($successCount === 1) $message .= ' via ' . (count($errorMessages) ? 'SMS' : 'WhatsApp');
                if ($successCount === 2) $message .= ' via SMS and WhatsApp';
                if (!empty($errorMessages)) {
                    $message .= '. Partial failures: ' . implode(', ', $errorMessages);
                }
                echo json_encode(['status' => 'success', 'message' => $message]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again later.']);
            }
        } catch (Exception $e) {
            error_log("OTP delivery error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again later.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Phone number is empty']);
    }
    session_write_close();
    exit;
}

// SMS/WhatsApp OTP verification
if (isset($_POST['verify_sms_otp'])) {
    $user_otp = trim($_POST['sms_otp'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($user_otp) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'OTP and phone are required']);
        session_write_close();
        exit;
    }
    
    if (!isset($_SESSION['phone_otp']) || !isset($_SESSION['phone_to_verify']) || !isset($_SESSION['phone_otp_expiry'])) {
        echo json_encode(['status' => 'error', 'message' => 'OTP session expired. Please request new OTP']);
        session_write_close();
        exit;
    }
    
    if ($_SESSION['phone_to_verify'] !== $phone) {
        echo json_encode(['status' => 'error', 'message' => 'Phone number mismatch']);
        session_write_close();
        exit;
    }
    
    if (time() > $_SESSION['phone_otp_expiry']) {
        echo json_encode(['status' => 'error', 'message' => 'OTP expired']);
        session_write_close();
        exit;
    }
    
    if ($user_otp != $_SESSION['phone_otp']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP']);
        session_write_close();
        exit;
    }
    
    // Verification successful
    $_SESSION['phone_verified'] = true;
    echo json_encode(['status' => 'success', 'message' => 'Phone verified successfully']);
    session_write_close();
    exit;
}

// Clear verification sessions
if (isset($_GET['clear_verification'])) {
    unset($_SESSION['email_verified']);
    unset($_SESSION['email_to_verify']);
    unset($_SESSION['email_otp']);
    unset($_SESSION['otp_expiry']);
    
    unset($_SESSION['phone_verified']);
    unset($_SESSION['phone_to_verify']);
    unset($_SESSION['phone_otp']);
    unset($_SESSION['phone_otp_expiry']);
    session_write_close();
    exit;
}

// AJAX request handlers
if (isset($_GET['ajax'])) {
    $conn = getDBConnection();
    
    // Rank and designation data loader
    if ($_GET['ajax'] == 'get_rank' && isset($_GET['category'])) {
        $category = $conn->real_escape_string($_GET['category']);
        
        $response = [
            'status' => 'success',
            'english' => '<option value="">Select Rank</option>',
            'hindi' => '<option value="">रैंक चुने</option>',
            'designation' => '<option value="">Select Designation</option>'
        ];

        // Handle "Other" category selection
        if ($category === 'Other' || $category === 'अन्य') {
            $response['english'] .= '<option value="Other">Other (Please Specify)</option>';
            $response['hindi'] .= '<option value="अन्य">अन्य (कृपया निर्दिष्ट करें)</option>';
            $response['designation'] .= '<option value="Other">Other (Please Specify)</option>';
            echo json_encode($response);
            session_write_close();
            exit;
        }

        // Check if we need to translate Hindi category to English
        $is_hindi = preg_match('/[\x{0900}-\x{097F}]/u', $category);
        $original_category = $category;
        
        if ($is_hindi) {
            $cat_query = "SELECT category_name FROM categories WHERE category_name_hindi = '$category' LIMIT 1";
            $cat_result = $conn->query($cat_query);
            if ($cat_result && $cat_result->num_rows > 0) {
                $category = $cat_result->fetch_assoc()['category_name'];
            }
        }

        // Get ranks for the category
        $query = "SELECT rank, rank_hindi FROM ranks WHERE category_name = '$category' ORDER BY rank";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response['english'] .= '<option value="' . htmlspecialchars($row['rank']) . '">' . 
                                      htmlspecialchars($row['rank']) . '</option>';
                $response['hindi'] .= '<option value="' . htmlspecialchars($row['rank_hindi']) . '">' . 
                                    htmlspecialchars($row['rank_hindi']) . '</option>';
                $response['designation'] .= '<option value="' . htmlspecialchars($row['rank']) . '">' . 
                                          htmlspecialchars($row['rank']) . '</option>';
            }
        }
        
        // Always add "Other" option
        $response['english'] .= '<option value="Other">Other (Please Specify)</option>';
        $response['hindi'] .= '<option value="अन्य">अन्य (कृपया निर्दिष्ट करें)</option>';
        $response['designation'] .= '<option value="Other">Other (Please Specify)</option>';

        header('Content-Type: application/json');
        echo json_encode($response);
        $conn->close();
        session_write_close();
        exit();
    }

    // District data loader
    if ($_GET['ajax'] == 'get_districts' && isset($_GET['state'])) {
        $state = $conn->real_escape_string($_GET['state']);
        $query = "SELECT district_name FROM districts WHERE state_name = '$state' ORDER BY district_name";
        $result = $conn->query($query);
        
        $options = '<option value="">Select District</option>';
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $options .= '<option value="' . htmlspecialchars($row['district_name']) . '">' . 
                           htmlspecialchars($row['district_name']) . '</option>';
            }
        } else {
            $options .= '<option value="">No districts found</option>';
        }
        
        echo $options;
        $conn->close();
        session_write_close();
        exit();
    }
    
    // Get date ranges for a course
    if ($_GET['ajax'] == 'get_dates' && isset($_GET['course'])) {
        $course = $conn->real_escape_string($_GET['course']);
        $query = "SELECT DISTINCT start_date, end_date FROM training_events WHERE course_name = '$course' ORDER BY start_date";
        $result = $conn->query($query);
        
        $options = '<option value="">Select Date Range</option>';
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $start = date('d-m-Y', strtotime($row['start_date']));
                $end = date('d-m-Y', strtotime($row['end_date']));
                $display = $start . ' to ' . $end;
                $value = $row['start_date'] . '|' . $row['end_date'];
                $options .= '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($display) . '</option>';
            }
        } else {
            $options .= '<option value="">No dates available</option>';
        }
        
        echo $options;
        $conn->close();
        session_write_close();
        exit();
    }
    
    $conn->close();
    session_write_close();
    exit();
}

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_otp'])) {
    // CAPTCHA verification
    if (empty($_POST['captcha'])) {
        $error = "Please enter the CAPTCHA code";
    } else {
        $userCaptcha = trim($_POST['captcha']);
        $sessionCaptcha = trim($_SESSION['captcha_code']);
        
        if (strcasecmp($userCaptcha, $sessionCaptcha) !== 0) {
            $error = "Invalid CAPTCHA code. Please try again.";
            $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
        } else {
            $conn = getDBConnection();
            
            // Collect and sanitize data
            foreach ($formData as $key => $value) {
                $formData[$key] = isset($_POST[$key]) ? $conn->real_escape_string(trim($_POST[$key])) : '';
            }
            
            // Convert dates to MySQL format
            $startDateMySQL = null;
            $endDateMySQL = null;
            $validDates = true;
            
            if (!empty($formData['start_date'])) {
                $dateObj = DateTime::createFromFormat('d-m-Y', $formData['start_date']);
                if ($dateObj) {
                    $startDateMySQL = $dateObj->format('Y-m-d');
                } else {
                    $error = "Invalid start date format. Please use dd-mm-yyyy.";
                    $validDates = false;
                }
            }
            
            if (!empty($formData['end_date'])) {
                $dateObj = DateTime::createFromFormat('d-m-Y', $formData['end_date']);
                if ($dateObj) {
                    $endDateMySQL = $dateObj->format('Y-m-d');
                } else {
                    $error = "Invalid end date format. Please use dd-mm-yyyy.";
                    $validDates = false;
                }
            }

            // Verify state and district exist
            $valid = true;
            if (!empty($formData['state_name'])) {
                $state_check = $conn->query("SELECT 1 FROM states WHERE state_name = '{$formData['state_name']}'");
                if (!$state_check || $state_check->num_rows === 0) {
                    $error = "Selected state is invalid.";
                    $valid = false;
                }
            }
                
            if ($valid && !empty($formData['district_name'])) {
                $district_check = $conn->query("SELECT 1 FROM districts WHERE district_name = '{$formData['district_name']}' AND state_name = '{$formData['state_name']}'");
                if (!$district_check || $district_check->num_rows === 0) {
                    $error = "Selected district is invalid for the chosen state.";
                    $valid = false;
                }
            }

            // Check if email is verified using session
            if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'] || $_SESSION['email_to_verify'] !== $formData['email']) {
                $error = "Please verify your email address first";
                $valid = false;
            }
            
            // Validate dates
            if (!$validDates) {
                $valid = false;
            }

            // Insert data if valid
            if ($valid) {
                // Insert Participant data with created_at timestamp
                $district_value = !empty($formData['district_name']) ? $formData['district_name'] : NULL;
                
                $sql = "INSERT INTO nominee (
                        participant_name, participant_name_hindi, gender, 
                        category_name, category_name_hindi, rank, rank_hindi, 
                        other_rank, other_rank_hindi, email, qualifications, 
                        residential_address, state_name, state_name_hindi, district_name, 
                        official_address, residential_phone, delhi_address, course_expectation, 
                        officer_designation, officer_phone, officer_address, course_name, 
                        course_name_hindi, start_date, end_date,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                    
                $stmt->bind_param(
                    "ssssssssssssssssssssssssss",
                    $formData['participant_name'], $formData['participant_name_hindi'],
                    $formData['gender'], $formData['category_name'], $formData['category_name_hindi'],
                    $formData['rank'], $formData['rank_hindi'], $formData['other_rank'], $formData['other_rank_hindi'],
                    $formData['email'], $formData['qualifications'], 
                    $formData['residential_address'], $formData['state_name'], $formData['state_name_hindi'], $district_value, 
                    $formData['official_address'], $formData['residential_phone'], $formData['delhi_address'], 
                    $formData['course_expectation'], $formData['officer_designation'], $formData['officer_phone'],
                    $formData['officer_address'], $formData['course_name'], $formData['course_name_hindi'], $startDateMySQL,
                    $endDateMySQL
                );

                if ($stmt->execute()) {
                    $last_id = $conn->insert_id;
                    $success = "Participation submitted successfully!";
                    
                    try {
                        // SMTP Configuration
                        $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
                            ->setUsername('nikhilguptaji@gmail.com')
                            ->setPassword('dmmufijsqwaoenpy');
                                    
                        // Create the Mailer
                        $mailer = new Swift_Mailer($transport);
                                    
                        // Create the message
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
                            </style>
                        </head>
                        <body>
                            <div class="email-container">
                                <div class="email-header">
                                    <h2>National Crime Records Bureau</h2>
                                    <h3>Participation Confirmation</h3>
                                </div>
                                
                                <div class="email-body">
                                    <p>Dear ' . htmlspecialchars($formData['participant_name']) . ',</p>
                                    <p>Your Participation for the following course has been successfully processed:</p>
                                    
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
                                            <th>Rank/Designation</th>
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
                                    
                                    <div class="section-title">Contact Information</div>
                                    <table class="details-table">
                                        <tr>
                                            <th>Residential Address</th>
                                            <td>' . htmlspecialchars($formData['residential_address']) . '</td>
                                        </tr>
                                        <tr>
                                            <th>State</th>
                                            <td>' . htmlspecialchars($formData['state_name']) . '</td>
                                        </tr>
                                        <tr>
                                            <th>District</th>
                                            <td>' . htmlspecialchars($formData['district_name']) . '</td>
                                        </tr>
                                        <tr>
                                            <th>Delhi Address</th>
                                            <td>' . htmlspecialchars($formData['delhi_address']) . '</td>
                                        </tr>
                                    </table>
                                    
                                    <div class="section-title">Nominating Office</div>
                                    <table class="details-table">
                                        <tr>
                                            <th>Designation</th>
                                            <td>' . htmlspecialchars($formData['officer_designation']) . '</td>
                                        </tr>
                                        <tr>
                                            <th>Phone</th>
                                            <td>' . htmlspecialchars($formData['officer_phone']) . '</td>
                                        </tr>
                                        <tr>
                                            <th>Address</th>
                                            <td>' . htmlspecialchars($formData['officer_address']) . '</td>
                                        </tr>
                                    </table>
                                    
                                    <p>Thank you for your Participation. We will contact you with further details about the course.</p>
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
                        </html>
                        ';
                        
                        $message = (new Swift_Message('Your Participation Details for NCRB Course'))
                            ->setFrom(['noreply@ncrb.gov.in' => 'NCRB Participation'])
                            ->setTo([$formData['email'] => $formData['participant_name']])
                            ->setBody($htmlContent, 'text/html');

                        // Send the message
                        $failedRecipients = [];
                        $numSent = $mailer->send($message, $failedRecipients);
                        
                        // SMS/WhatsApp Confirmation (After Form Submission)
                        if (!empty($formData['residential_phone']) && 
                            isset($_SESSION['phone_verified']) && 
                            $_SESSION['phone_verified'] && 
                            $_SESSION['phone_to_verify'] === $formData['residential_phone']) 
                        {
                            try {
                                $phone = $formData['residential_phone'];
                                // Enhanced message with full details
                                $messageBody = "NCRB Participation CONFIRMATION\n\n";
                                $messageBody .= "Name: {$formData['participant_name']}\n";
                                $messageBody .= "Course: {$formData['course_name']}\n";
                                $messageBody .= "Dates: {$formData['start_date']} to {$formData['end_date']}\n";
                                $messageBody .= "Email: {$formData['email']}\n\n";
                                $messageBody .= "Thank you for your Participation!";
                                
                                $smsSuccess = false;
                                $waSuccess = false;
                                
                                // Send via SMS
                                try {
                                    $twilio->messages->create(
                                        "+91" . $phone,
                                        [
                                            'from' => '+19472148608',
                                            'body' => $messageBody
                                        ]
                                    );
                                    $smsSuccess = true;
                                } catch (Exception $smsEx) {
                                    error_log("SMS sending error: " . $smsEx->getMessage());
                                }
                                
                                // Send via WhatsApp
                                try {
                                    $twilio->messages->create(
                                        "whatsapp:+91" . $phone,
                                        [
                                            'from' => 'whatsapp:+14155238886',
                                            'body' => $messageBody
                                        ]
                                    );
                                    $waSuccess = true;
                                } catch (Exception $waEx) {
                                    error_log("WhatsApp sending error: " . $waEx->getMessage());
                                }
                                
                                // Build success message
                                if ($smsSuccess && $waSuccess) {
                                    $success .= " Confirmation sent via SMS and WhatsApp.";
                                } elseif ($smsSuccess) {
                                    $success .= " Confirmation sent via SMS.";
                                } elseif ($waSuccess) {
                                    $success .= " Confirmation sent via WhatsApp.";
                                }
                            } catch (Exception $e) {
                                error_log("Phone confirmation error: " . $e->getMessage());
                            }
                        }
                    } catch (Exception $e) {
                        $error = "Participation saved but email could not be sent. Please contact support with your Participation details.";
                    }
                    
                    // Clear verification sessions after successful submission
                    unset($_SESSION['email_verified']);
                    unset($_SESSION['email_to_verify']);
                    unset($_SESSION['email_otp']);
                    unset($_SESSION['otp_expiry']);
                    
                    unset($_SESSION['phone_verified']);
                    unset($_SESSION['phone_to_verify']);
                    unset($_SESSION['phone_otp']);
                    unset($_SESSION['phone_otp_expiry']);
                    
                    $formData = array_fill_keys(array_keys($formData), '');
                    $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                } else {
                    $error = "Error saving Participation: " . $conn->error;
                }
            }
        }
    }
}

// Load dropdown data
$conn = getDBConnection();
$courses = $conn->query("SELECT DISTINCT course_name, course_name_hindi FROM training_events");
$states = $conn->query("SELECT state_name, state_name_hindi FROM states");
$categories = [];
$result = $conn->query("SELECT DISTINCT category_name, category_name_hindi FROM categories");
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'category_name' => $row['category_name'],
        'category_name_hindi' => $row['category_name_hindi']
    ];
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Participant Form | National Crime Records Bureau</title>
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
            color: inherit;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .copyright a:hover {
            color: var(--accent);
            text-decoration: underline;
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

        /* Added for better visibility of other fields */
        .other-category-container, .other-rank-container {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
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
                    <i class="fas fa-user-graduate me-2"></i> Participation Form
                </div>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-user-graduate me-2"></i> Participation Form</h2>
            </div>
            
            <div class="form-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" id="nominationForm">
                    <!-- Course Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-book me-2"></i> Course Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="course_name" class="form-label required">Course Name</label>
                                <select class="form-select" id="course_name" name="course_name" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_name']); ?>" <?php echo ($course['course_name'] == $formData['course_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="course_name_hindi" class="form-label required">कोर्स का नाम</label>
                                <select class="form-select" id="course_name_hindi" name="course_name_hindi" required>
                                    <option value="">कोर्स चुने</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_name_hindi']); ?>" <?php echo ($course['course_name_hindi'] == $formData['course_name_hindi']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_name_hindi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="date_range" class="form-label required">Select Date Range</label>
                                <select class="form-select" id="date_range" required>
                                    <option value="">Select Date Range</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="start_date" class="form-label required">Start Date</label>
                                <input type="text" class="form-control datepicker" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($formData['start_date']); ?>" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="end_date" class="form-label required">End Date</label>
                                <input type="text" class="form-control datepicker" id="end_date" name="end_date" required value="<?php echo htmlspecialchars($formData['end_date']); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12 mb-3">
                                <label for="course_expectation" class="form-label required">Course Expectations</label>
                                <textarea class="form-control" id="course_expectation" name="course_expectation" rows="1" required><?php echo htmlspecialchars($formData['course_expectation']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-user me-2"></i> Personal Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-5 mb-3">
                                <label for="participant_name" class="form-label required">Name (English)</label>
                                <input type="text" class="form-control" id="participant_name" name="participant_name" required value="<?php echo htmlspecialchars($formData['participant_name']); ?>">
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="participant_name_hindi" class="form-label required">नाम (हिन्दी में)</label>
                                <input type="text" class="form-control hindi-input" id="participant_name_hindi" name="participant_name_hindi" required value="<?php echo htmlspecialchars($formData['participant_name_hindi']); ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="gender" class="form-label required">Gender</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select</option>
                                    <option value="Male" <?php echo ($formData['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($formData['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($formData['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-2 mb-3">
                                <label for="category_name" class="form-label required">Category</label>
                                <select class="form-select" id="category_name" name="category_name" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category_name']); ?>" <?php echo ($category['category_name'] == $formData['category_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="Other" <?php echo ($formData['category_name'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="category_name_hindi" class="form-label required">श्रेणी</label>
                                <select class="form-select" id="category_name_hindi" name="category_name_hindi" required>
                                    <option value="">श्रेणी चुने</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category_name_hindi']); ?>" <?php echo ($category['category_name_hindi'] == $formData['category_name_hindi']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name_hindi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="अन्य" <?php echo ($formData['category_name_hindi'] === 'अन्य') ? 'selected' : ''; ?>>अन्य</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 mb-3">
                                <label for="rank" class="form-label required">Rank</label>
                                <select class="form-select" id="rank" name="rank" required>
                                    <option value="">Select Rank</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="rank_hindi" class="form-label required">रैंक</label>
                                <select class="form-select" id="rank_hindi" name="rank_hindi" required>
                                    <option value="">रैंक चुने</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="qualifications" class="form-label required">Qualifications</label>
                                <input type="text" class="form-control" id="qualifications" name="qualifications" required value="<?php echo htmlspecialchars($formData['qualifications']); ?>">
                            </div>
                        </div>
                        
                        <!-- Other Category Fields -->
                        <div class="other-category-container" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="other_category" class="form-label">Specify Category (English)</label>
                                    <input type="text" class="form-control" id="other_category" name="other_category" value="<?php echo htmlspecialchars($formData['other_category'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="other_category_hindi" class="form-label">श्रेणी निर्दिष्ट करें (हिन्दी)</label>
                                    <input type="text" class="form-control hindi-input" id="other_category_hindi" name="other_category_hindi" value="<?php echo htmlspecialchars($formData['other_category_hindi'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Other Rank Fields -->
                        <div class="other-rank-container" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="other_rank" class="form-label">Specify Rank (English)</label>
                                    <input type="text" class="form-control" id="other_rank" name="other_rank" value="<?php echo htmlspecialchars($formData['other_rank'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="other_rank_hindi" class="form-label">रैंक निर्दिष्ट करें (हिन्दी)</label>
                                    <input type="text" class="form-control hindi-input" id="other_rank_hindi" name="other_rank_hindi" value="<?php echo htmlspecialchars($formData['other_rank_hindi'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label required">Email Address</label>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($formData['email']); ?>" placeholder="Enter your email">
                                    <button type="button" class="btn btn-outline-action" id="send-otp-btn">Send OTP</button>
                                    <span id="verification-status" class="verification-status <?php echo (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] && $_SESSION['email_to_verify'] === $formData['email']) ? 'verified' : 'not-verified'; ?>">
                                        <?php echo (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] && $_SESSION['email_to_verify'] === $formData['email']) ? '✓ Verified' : 'Not Verified'; ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" id="otp-section">
                                    <input type="text" class="form-control otp-input" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6">
                                    <button type="button" class="btn btn-action" id="verify-otp-btn">Verify</button>
                                </div>
                                <div id="otp-message" class="mt-2"></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="residential_phone" class="form-label">Mobile Phone</label>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <input type="tel" class="form-control" id="residential_phone" name="residential_phone" maxlength="10" value="<?php echo htmlspecialchars($formData['residential_phone']); ?>" placeholder="Enter your Phone">
                                    <button type="button" class="btn btn-outline-action" id="send-sms-otp-btn">Send OTP</button>
                                    <span id="phone-verification-status" class="verification-status <?php echo (isset($_SESSION['phone_verified'])) && $_SESSION['phone_verified'] && $_SESSION['phone_to_verify'] === ($formData['residential_phone'] ?? '') ? 'verified' : 'not-verified'; ?>">
                                        <?php echo (isset($_SESSION['phone_verified'])) && $_SESSION['phone_verified'] && $_SESSION['phone_to_verify'] === ($formData['residential_phone'] ?? '') ? '✓ Verified' : 'Not Verified'; ?>
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2" id="sms-otp-section">
                                    <input type="text" class="form-control otp-input" id="sms-otp" 
                                           placeholder="Enter 6-digit OTP" maxlength="6">
                                    <button type="button" class="btn btn-action" id="verify-sms-otp-btn">Verify</button>
                                </div>
                                <div id="sms-otp-message" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-home me-2"></i> Address Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="residential_address" class="form-label">Residential Address</label>
                                <textarea class="form-control" id="residential_address" name="residential_address" rows="3"><?php echo htmlspecialchars($formData['residential_address']); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="official_address" class="form-label">Official Address</label>
                                <textarea class="form-control" id="official_address" name="official_address" rows="3"><?php echo htmlspecialchars($formData['official_address']); ?></textarea>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label for="state_name" class="form-label required">State</label>
                                <select class="form-select" id="state_name" name="state_name" required>
                                    <option value="">Select State</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?php echo htmlspecialchars($state['state_name']); ?>" <?php echo ($state['state_name'] == $formData['state_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($state['state_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="state_name_hindi" class="form-label required">राज्य</label>
                                <select class="form-select" id="state_name_hindi" name="state_name_hindi" required>
                                    <option value="">राज्य चुने</option>
                                    <?php foreach ($states as $state): ?>
                                        <option value="<?php echo htmlspecialchars($state['state_name_hindi']); ?>" <?php echo ($state['state_name_hindi'] == $formData['state_name_hindi']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($state['state_name_hindi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="district_name" class="form-label">District</label>
                                <select class="form-select" id="district_name" name="district_name" <?php echo empty($formData['state_name']) ? 'disabled' : ''; ?>>
                                    <option value=""><?php echo empty($formData['state_name']) ? 'Select State first' : 'Select District'; ?></option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="delhi_address" class="form-label">Delhi Address (if any)</label>
                                <textarea class="form-control" id="delhi_address" name="delhi_address" rows="1"><?php echo htmlspecialchars($formData['delhi_address']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Nominating Office Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-building me-2"></i> Nominating Office Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label for="officer_designation" class="form-label required">Designation</label>
                                <select class="form-select" id="officer_designation" name="officer_designation" required>
                                    <option value="">Select Designation</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="officer_phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="officer_phone" name="officer_phone" maxlength="10" value="<?php echo htmlspecialchars($formData['officer_phone']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="officer_address" class="form-label">Address</label>
                                <textarea class="form-control" id="officer_address" name="officer_address" rows="1"><?php echo htmlspecialchars($formData['officer_address']); ?></textarea>
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
                        <button type="submit" class="btn btn-submit me-3">Submit Participation</button>
                        <button type="reset" class="btn btn-reset">Reset Form</button>
                    </div>
                </form>
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
                    <p><a href="mailto:dct@ncrb.gov.in"><i class="fas fa-envelope"></i>Email: dct@ncrb.gov.in</a></p>
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
            allowInput: true,
            defaultDate: null
        });

        // Toggle visibility of "Other" input fields
        function toggleOtherFields() {
            // Category fields
            const isOtherCategory = (
                $('#category_name').val() === 'Other' || 
                $('#category_name_hindi').val() === 'अन्य'
            );
            $('.other-category-container').toggle(isOtherCategory);
            $('#other_category, #other_category_hindi').prop('required', isOtherCategory);
            
            // Rank fields
            const isOtherRank = (
                $('#rank').val() === 'Other' || 
                $('#rank_hindi').val() === 'अन्य'
            );
            $('.other-rank-container').toggle(isOtherRank);
            $('#other_rank, #other_rank_hindi').prop('required', isOtherRank);
        }

        // Category change handler - FIXED: Use index-based synchronization
        $('#category_name, #category_name_hindi').change(function() {
            const isHindi = $(this).attr('id') === 'category_name_hindi';
            const category = $(this).val();
            
            // Sync by index instead of value
            const thisIndex = this.selectedIndex;
            if (isHindi) {
                $('#category_name')[0].selectedIndex = thisIndex;
            } else {
                $('#category_name_hindi')[0].selectedIndex = thisIndex;
            }
            
            // Handle "Other" category selection
            if (category === 'Other' || category === 'अन्य') {
                $('#rank').html('<option value="Other">Other (Please Specify)</option>');
                $('#rank_hindi').html('<option value="अन्य">अन्य (कृपया निर्दिष्ट करें)</option>');
                $('#officer_designation').html('<option value="Other">Other (Please Specify)</option>');
                toggleOtherFields();
                return;
            }
            
            // Load ranks for regular categories
            loadRanksForCategory(category, isHindi);
        });

        // Rank change handler - FIXED: Use index-based synchronization
        $('#rank, #rank_hindi').change(function() {
            const isHindi = $(this).attr('id') === 'rank_hindi';
            const rankValue = $(this).val();
            
            // Sync by index instead of value
            const thisIndex = this.selectedIndex;
            if (isHindi) {
                $('#rank')[0].selectedIndex = thisIndex;
            } else {
                $('#rank_hindi')[0].selectedIndex = thisIndex;
            }
            
            toggleOtherFields();
        });

        // Load ranks and designations for category
        function loadRanksForCategory(category, isHindi) {
            $('#rank, #rank_hindi, #officer_designation').prop('disabled', true);
            
            $.ajax({
                url: 'form-participant.php?ajax=get_rank&category=' + encodeURIComponent(category),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data?.status === 'success') {
                        $('#rank').html(data.english).prop('disabled', false);
                        $('#rank_hindi').html(data.hindi).prop('disabled', false);
                        $('#officer_designation').html(data.designation).prop('disabled', false);
                        
                        // Set saved values if they exist
                        const savedRank = <?php echo json_encode($formData['rank']); ?>;
                        const savedRankHindi = <?php echo json_encode($formData['rank_hindi']); ?>;
                        const savedDesignation = <?php echo json_encode($formData['officer_designation']); ?>;
                        
                        // Set the rank in English
                        if (savedRank) {
                            $('#rank').val(savedRank);
                            if ($('#rank').val() !== savedRank) {
                                $('#rank').val('');
                            }
                        }
                        
                        // Set the rank in Hindi
                        if (savedRankHindi) {
                            $('#rank_hindi').val(savedRankHindi);
                            if ($('#rank_hindi').val() !== savedRankHindi) {
                                $('#rank_hindi').val('');
                            }
                        }
                        
                        // Set the designation
                        if (savedDesignation) {
                            $('#officer_designation').val(savedDesignation);
                            if ($('#officer_designation').val() !== savedDesignation) {
                                $('#officer_designation').val('');
                            }
                        }
                    } else {
                        console.error('Rank loading error:', data);
                        $('#rank').html('<option value="">Error loading ranks</option>');
                        $('#rank_hindi').html('<option value="">त्रुटि</option>');
                        $('#officer_designation').html('<option value="">Error loading designations</option>');
                    }
                    toggleOtherFields();
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    $('#rank').html('<option value="">Error loading ranks</option>');
                    $('#rank_hindi').html('<option value="">त्रुटि</option>');
                    $('#officer_designation').html('<option value="">Error loading designations</option>');
                    toggleOtherFields();
                }
            });
        }

        // State change handler (for districts)
        $('#state_name, #state_name_hindi').change(function() {
            const isHindi = $(this).attr('id') === 'state_name_hindi';
            const stateSelect = isHindi ? $('#state_name_hindi') : $('#state_name');
            const otherStateSelect = isHindi ? $('#state_name') : $('#state_name_hindi');
            const state = stateSelect.val();
            const districtSelect = $('#district_name');
            
            // Sync the other state dropdown
            otherStateSelect[0].selectedIndex = this.selectedIndex;
            
            if (state) {
                districtSelect.prop('disabled', false).html('<option value="" class="loading-districts">Loading districts...</option>');
                
                $.ajax({
                    url: '?ajax=get_districts&state=' + encodeURIComponent(state),
                    type: 'GET',
                    success: function(data) {
                        districtSelect.html(data || '<option value="">No districts found</option>');
                        const prevDistrict = $('#district_name').data('prev');
                        if (prevDistrict) districtSelect.val(prevDistrict);
                    },
                    error: function() {
                        districtSelect.html('<option value="">Error loading districts</option>');
                    }
                });
            } else {
                districtSelect.prop('disabled', true).html('<option value="">Select State first</option>');
            }
        });

        // COURSE DROPDOWN SYNC
        $('#course_name, #course_name_hindi').change(function() {
            const isHindi = $(this).attr('id') === 'course_name_hindi';
            const course = $(this).val();
            
            if (isHindi) {
                $('#course_name')[0].selectedIndex = this.selectedIndex;
            } else {
                $('#course_name_hindi')[0].selectedIndex = this.selectedIndex;
            }
            
            // Load date ranges for the selected course
            if (course) {
                $.ajax({
                    url: 'form-participant.php?ajax=get_dates&course=' + encodeURIComponent(course),
                    type: 'GET',
                    success: function(data) {
                        $('#date_range').html(data);
                        $('#start_date').val('');
                        $('#end_date').val('');
                    },
                    error: function() {
                        $('#date_range').html('<option value="">Error loading dates</option>');
                    }
                });
            } else {
                $('#date_range').html('<option value="">Select course first</option>');
                $('#start_date').val('');
                $('#end_date').val('');
            }
        });

        // Date range selection handler
        $('#date_range').change(function() {
            const dates = $(this).val().split('|');
            if (dates.length === 2) {
                const startDate = new Date(dates[0]);
                const endDate = new Date(dates[1]);
                
                const formattedStart = formatDate(startDate);
                const formattedEnd = formatDate(endDate);
                
                $('#start_date').val(formattedStart);
                $('#end_date').val(formattedEnd);
            }
        });
        
        function formatDate(date) {
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}-${month}-${year}`;
        }

        // EMAIL OTP HANDLING
        $('#send-otp-btn').click(function() {
            const email = $('#email').val();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                $('#otp-message').html('<div class="text-danger">Please enter a valid email address</div>');
                return;
            }
            
            $(this).prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: 'form-participant.php?generate_otp=1&email=' + encodeURIComponent(email),
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        $('#otp-message').html('<div class="text-success">OTP sent successfully. Check your email.</div>');
                        $('#otp-section').show();
                        $('#verification-status').removeClass('verified').addClass('not-verified').text('Not Verified');
                    } else {
                        $('#otp-message').html('<div class="text-danger">' + data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#otp-message').html('<div class="text-danger">Request failed: ' + error + '</div>');
                },
                complete: function() {
                    $('#send-otp-btn').prop('disabled', false).text('Send OTP');
                }
            });
        });

        $('#verify-otp-btn').click(function() {
            const otp = $('#otp').val();
            const email = $('#email').val();
            
            if (!otp || !email || !/^\d{6}$/.test(otp)) {
                $('#otp-message').html('<div class="text-danger">Please enter a valid 6-digit OTP</div>');
                return;
            }
            
            $(this).prop('disabled', true).text('Verifying...');
            
            $.ajax({
                url: 'form-participant.php',
                type: 'POST',
                data: {
                    verify_otp: 1,
                    otp: otp,
                    email: email
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        $('#otp-message').html('<div class="text-success">Email verified successfully!</div>');
                        $('#verification-status').removeClass('not-verified').addClass('verified').text('✓ Verified');
                        $('#otp-section').hide();
                    } else {
                        $('#otp-message').html('<div class="text-danger">' + data.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#otp-message').html('<div class="text-danger">Verification failed: ' + error + '</div>');
                },
                complete: function() {
                    $('#verify-otp-btn').prop('disabled', false).text('Verify');
                }
            });
        });
        
        // SMS/WhatsApp OTP HANDLING
        $('#send-sms-otp-btn').click(function() {
          const phone = $('#residential_phone').val();
          
          // Only send OTP if phone is provided
          if (phone) {
            if (!/^\d{10}$/.test(phone)) {
              $('#sms-otp-message').html('<div class="text-danger">Invalid phone number</div>');
              return;
            }

            $(this).prop('disabled', true).text('Sending...');
            
            $.ajax({
              url: 'form-participant.php?generate_sms_otp=1&phone=' + phone,
              type: 'GET',
              dataType: 'json',
              success: function(data) {
                if (data.status === 'success') {
                  $('#sms-otp-message').html(`<div class="text-success">${data.message}</div>`);
                  $('#sms-otp-section').show();
                  $('#phone-verification-status').removeClass('verified').addClass('not-verified').text('Not Verified');
                } else {
                  $('#sms-otp-message').html(`<div class="text-danger">${data.message}</div>`);
                }
              },
              error: function(xhr, status, error) {
                $('#sms-otp-message').html(`<div class="text-danger">Request failed: ${error}</div>`);
              },
              complete: function() {
                $('#send-sms-otp-btn').prop('disabled', false).text('Send OTP');
              }
            });
          } else {
            $('#sms-otp-message').html('<div class="text-warning">Please enter a phone number first</div>');
          }
        });

        $('#verify-sms-otp-btn').click(function() {
          const otp = $('#sms-otp').val();
          const phone = $('#residential_phone').val();
          
          if (!/^\d{6}$/.test(otp) || !phone) {
            $('#sms-otp-message').html('<div class="text-danger">Invalid OTP or phone number</div>');
            return;
          }
          
          $(this).prop('disabled', true).text('Verifying...');
          
          $.ajax({
            url: 'form-participant.php',
            type: 'POST',
            data: {
              verify_sms_otp: 1,
              sms_otp: otp,
              phone: phone
            },
            dataType: 'json',
            success: function(data) {
              if (data.status === 'success') {
                $('#sms-otp-message').html('<div class="text-success">Phone verified!</div>');
                $('#phone-verification-status').removeClass('not-verified').addClass('verified').text('✓ Verified');
                $('#sms-otp-section').hide();
              } else {
                $('#sms-otp-message').html(`<div class="text-danger">${data.message}</div>`);
              }
            },
            error: function(xhr, status, error) {
              $('#sms-otp-message').html('<div class="text-danger">Verification failed</div>');
            },
            complete: function() {
              $('#verify-sms-otp-btn').prop('disabled', false).text('Verify');
            }
          });
        });

        // FORM VALIDATION
        $('#nominationForm').submit(function(e) {
            let valid = true;
            $('.is-invalid').removeClass('is-invalid');
            
            // Validate required fields
            $('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    valid = false;
                }
            });
            
            // Validate dates
            const startDate = $('#start_date').val();
            const endDate = $('#end_date').val();
            if (startDate && endDate) {
                // Convert to Date objects for comparison
                const startParts = startDate.split('-');
                const endParts = endDate.split('-');
                
                const start = new Date(startParts[2], startParts[1] - 1, startParts[0]);
                const end = new Date(endParts[2], endParts[1] - 1, endParts[0]);
                
                if (start > end) {
                    $('#start_date, #end_date').addClass('is-invalid');
                    alert('End date must be after start date');
                    valid = false;
                }
            }
            
            // Validate email verification
            const emailVerified = $('#verification-status').hasClass('verified');
            if (!emailVerified) {
                $('#email').addClass('is-invalid');
                $('#otp-message').html('<div class="text-danger">Please verify your email first</div>');
                valid = false;
            }
            
            // Validate phone number if provided (but not required)
            const phone = $('#residential_phone').val();
            if (phone && !/^\d{10}$/.test(phone)) {
                $('#residential_phone').addClass('is-invalid');
                valid = false;
            }
            
            // Validate "Other" rank
            if (($('#rank').val() === 'Other' || $('#rank_hindi').val() === 'अन्य') && 
                (!$('#other_rank').val() || !$('#other_rank_hindi').val())) {
                $('.other-rank-container input').addClass('is-invalid');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
                $('.alert-danger').remove();
                $('#nominationForm').prepend(
                    '<div class="alert alert-danger">Please fill all required fields correctly.</div>'
                );
                $('html, body').animate({
                    scrollTop: $('.alert-danger').offset().top - 100
                }, 500);
            }
        });

        // Reset form handler
        $('button[type="reset"]').click(function() {
            $('.is-invalid').removeClass('is-invalid');
            $('.alert').remove();
            $('#refresh-captcha').click();
            
            // Clear verification status
            $('#verification-status').removeClass('verified').addClass('not-verified').text('Not Verified');
            $('#phone-verification-status').removeClass('verified').addClass('not-verified').text('Not Verified');
            
            // Clear session via AJAX
            $.get('form-participant.php?clear_verification=1');
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
    
        // Close modal on close button click
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

        // CAPTCHA refresh
        $('#refresh-captcha').click(function() {
            $.get('form-participant.php?refresh_captcha=1', function(data) {
                $('#captcha-code').text(data);
                $('#captcha').val('').focus();
            });
        });

        // INITIALIZATION
        toggleOtherFields();
        if ($('#category_name').val()) {
            // Trigger change to load ranks and set saved values
            $('#category_name').trigger('change');
        }
        if ($('#state_name').val()) {
            $('#state_name').trigger('change');
        }
        
        // Store previous values for rank and district
        $('#rank, #rank_hindi, #officer_designation').each(function() {
            $(this).data('prev', $(this).val());
        });
        $('#district_name').data('prev', $('#district_name').val());
        
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
    </script>
</body>
</html>