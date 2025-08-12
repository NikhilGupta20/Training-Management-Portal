<?php
session_start();

// Include PHPMailer
require_once __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

// Twilio credentials
$twilio_sid = 'AC5329f9c47dfa4be553ce996325a4a1b3';
$twilio_token = '72d6f66055f29e031d04accd8ec1fd94';

// Validate Twilio credentials
$twilio = null;
try {
    $twilio = new Client($twilio_sid, $twilio_token);
    // Test credentials are valid
    $twilio->balance->fetch();
} catch (Exception $e) {
    error_log("Twilio initialization error: " . $e->getMessage());
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

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

$success = '';
$error = '';

// Initialize formData with default values
$formData = [
    'course_name' => '',
    'course_name_hindi' => '',
    'start_date' => '',
    'end_date' => '',
    'office_category' => '',
    'office_category_hindi' => '',
    'office_designation' => '',
    'office_phone' => '',
    'office_state' => '',
    'office_state_hindi' => '',
    'office_district' => '',
    'office_address' => '',
    'office_email' => '',
    'other_category' => '',        
    'other_category_hindi' => '',  
    'other_rank' => '',            
    'nominees' => []
];

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

// Handle email OTP generation
if (isset($_GET['generate_otp']) && isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address']);
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
        $message = (new Swift_Message('Your NCRB Nominee OTP'))
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
    exit;
}

// Handle email OTP verification
if (isset($_POST['verify_otp'])) {
    $user_otp = trim($_POST['otp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($user_otp) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'OTP and email are required']);
        exit;
    }
    
    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['email_to_verify']) || !isset($_SESSION['otp_expiry'])) {
        echo json_encode(['status' => 'error', 'message' => 'OTP session expired. Please request a new OTP']);
        exit;
    }
    
    if ($_SESSION['email_to_verify'] !== $email) {
        echo json_encode(['status' => 'error', 'message' => 'Email mismatch. Please verify the correct email']);
        exit;
    }
    
    if (time() > $_SESSION['otp_expiry']) {
        echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new OTP']);
        exit;
    }
    
    if ($user_otp != $_SESSION['email_otp']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again']);
        exit;
    }
    
    // If all checks pass
    $_SESSION['email_verified'] = true;
    echo json_encode(['status' => 'success', 'message' => 'Email verified successfully']);
    exit;
}

// Handle SMS/WhatsApp OTP generation
if (isset($_GET['generate_sms_otp']) && isset($_GET['phone'])) {
    header('Content-Type: application/json');
    
    $phone = trim($_GET['phone']);
    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
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
    
    $body = "Your NCRB Nominee OTP is: $otp\nValid for 5 minutes";
    $successCount = 0;
    $errorMessages = [];

    // Only proceed if Twilio is properly initialized
    if ($twilio === null) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Twilio service not configured properly. Contact administrator.'
        ]);
        exit;
    }

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
        if ($successCount === 2) {
            $message .= ' via SMS and WhatsApp';
        } elseif ($successCount === 1) {
            $message .= count($errorMessages) === 1 ? ' via WhatsApp' : ' via SMS';
        }
        
        if (!empty($errorMessages)) {
            $message .= ' (partial failures: ' . implode(', ', $errorMessages) . ')';
        }
        echo json_encode(['status' => 'success', 'message' => $message]);
    } else {
        $errorMsg = 'Failed to send OTP via any channel. ';
        if (!empty($errorMessages)) {
            $errorMsg .= 'Errors: ' . implode('; ', $errorMessages);
        } else {
            $errorMsg .= 'Twilio service unavailable.';
        }
        echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    }
    exit;
}

// Handle SMS OTP verification
if (isset($_POST['verify_sms_otp'])) {
    $user_otp = trim($_POST['sms_otp'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($user_otp) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'OTP and phone are required']);
        exit;
    }
    
    if (!isset($_SESSION['phone_otp']) || !isset($_SESSION['phone_to_verify']) || !isset($_SESSION['phone_otp_expiry'])) {
        echo json_encode(['status' => 'error', 'message' => 'OTP session expired. Please request a new OTP']);
        exit;
    }
    
    if ($_SESSION['phone_to_verify'] !== $phone) {
        echo json_encode(['status' => 'error', 'message' => 'Phone number mismatch. Please verify the correct phone']);
        exit;
    }
    
    if (time() > $_SESSION['phone_otp_expiry']) {
        echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new OTP']);
        exit;
    }
    
    if ($user_otp != $_SESSION['phone_otp']) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please try again']);
        exit;
    }
    
    // If all checks pass
    $_SESSION['phone_verified'] = true;
    echo json_encode(['status' => 'success', 'message' => 'Phone verified successfully']);
    exit;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    $conn = getDBConnection();
    
    if ($_GET['ajax'] == 'get_rank' && isset($_GET['category'])) {
        header('Content-Type: application/json');
        $category = $conn->real_escape_string($_GET['category']);
        
        // Initialize response
        $response = [
            'status' => 'success',
            'english' => '<option value="">Select Rank</option>',
            'hindi' => '<option value="">रैंक चुने</option>',
            'designation' => '<option value="">Select Designation</option>'
        ];

        // Check if this is "Other" category
        if ($category === 'Other' || $category === 'अन्य') {
            $response['english'] .= '<option value="Other">Other (Please Specify)</option>';
            $response['hindi'] .= '<option value="अन्य">अन्य (कृपया निर्दिष्ट करें)</option>';
            $response['designation'] .= '<option value="Other">Other (Please Specify)</option>';
            echo json_encode($response);
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

        if ($result) {
            if ($result->num_rows > 0) {
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
        } else {
            // Handle query error
            $response = ['status' => 'error', 'message' => 'Database query failed: ' . $conn->error];
        }
        
        echo json_encode($response);
        $conn->close();
        exit();
    }

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
        exit();
    }
    
    // Get date ranges for a specific course
    if ($_GET['ajax'] == 'get_date_ranges' && isset($_GET['course_name'])) {
        $course_name = $conn->real_escape_string($_GET['course_name']);
        $query = "SELECT id, start_date, end_date 
                  FROM training_events 
                  WHERE course_name = '$course_name' 
                  ORDER BY start_date";
        $result = $conn->query($query);
        
        $options = '<option value="">Select Date Range</option>';
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $start_date = date('d M Y', strtotime($row['start_date']));
                $end_date = date('d M Y', strtotime($row['end_date']));
                $display = $start_date . ' to ' . $end_date;
                $value = $row['id'];
                $options .= '<option value="' . $value . '" data-start="' . $row['start_date'] . '" data-end="' . $row['end_date'] . '">' . $display . '</option>';
            }
        } else {
            $options .= '<option value="">No date ranges available</option>';
        }
        
        echo $options;
        $conn->close();
        exit();
    }
    
    $conn->close();
    exit();
}

// Handle form submission
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
            // Collect form data
            $formData = [
                'course_name' => $_POST['course_name'] ?? '',
                'course_name_hindi' => $_POST['course_name_hindi'] ?? '',
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? '',
                'office_category' => $_POST['office_category'] ?? '',
                'office_category_hindi' => $_POST['office_category_hindi'] ?? '',
                'office_designation' => $_POST['office_designation'] ?? '',
                'office_phone' => $_POST['office_phone'] ?? '',
                'office_state' => $_POST['office_state'] ?? '',
                'office_state_hindi' => $_POST['office_state_hindi'] ?? '',
                'office_district' => $_POST['office_district'] ?? '',
                'office_address' => $_POST['office_address'] ?? '',
                'office_email' => $_POST['office_email'] ?? '',
                'other_category' => $_POST['other_category'] ?? '',        
                'other_category_hindi' => $_POST['other_category_hindi'] ?? '',  
                'other_rank' => $_POST['other_rank'] ?? '',                
                'nominees' => []
            ];

            // Collect nominee data
            if (isset($_POST['nominee_name']) && is_array($_POST['nominee_name'])) {
                foreach ($_POST['nominee_name'] as $index => $name) {
                    $formData['nominees'][] = [
                        'name' => $name,
                        'name_hindi' => $_POST['nominee_name_hindi'][$index] ?? '',
                        'gender' => $_POST['nominee_gender'][$index] ?? '',
                        'rank' => $_POST['nominee_rank'][$index] ?? '',
                        'rank_hindi' => $_POST['nominee_rank_hindi'][$index] ?? '',
                        'phone' => $_POST['nominee_phone'][$index] ?? '',
                        'email' => $_POST['nominee_email'][$index] ?? ''
                    ];
                }
            }

            // Verify office email is verified
            $valid = true;
            if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified'] || $_SESSION['email_to_verify'] !== $formData['office_email']) {
                $error = "Please verify your office email address first";
                $valid = false;
            }

            if ($valid) {
                try {
                    // Professional email template for nominee
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
                                <h3>Nominee Form Submission</h3>
                            </div>
                            
                            <div class="email-body">
                                <p>Dear Training Coordinator,</p>
                                <p>A new nominee form has been submitted. Below are the details:</p>
                                
                                <div class="section-title">Course Details</div>
                                <table class="details-table">
                                    <tr>
                                        <th>Course Name (English)</th>
                                        <td>' . htmlspecialchars($formData['course_name']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Course Name (Hindi)</th>
                                        <td>' . htmlspecialchars($formData['course_name_hindi']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Start Date</th>
                                        <td>' . htmlspecialchars($formData['start_date']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>End Date</th>
                                        <td>' . htmlspecialchars($formData['end_date']) . '</td>
                                    </tr>
                                </table>
                                
                                <div class="section-title">Nominating Office Details</div>
                                <table class="details-table">
                                    <tr>
                                        <th>Category (English)</th>
                                        <td>' . ($formData['office_category'] === 'Other' && !empty($formData['other_category']) ? htmlspecialchars($formData['other_category']) . ' (Other)' : htmlspecialchars($formData['office_category'])) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Category (Hindi)</th>
                                        <td>' . ($formData['office_category_hindi'] === 'अन्य' && !empty($formData['other_category_hindi']) ? htmlspecialchars($formData['other_category_hindi']) . ' (अन्य)' : htmlspecialchars($formData['office_category_hindi'])) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Designation</th>
                                        <td>' . ($formData['office_designation'] === 'Other' && !empty($formData['other_rank']) ? htmlspecialchars($formData['other_rank']) . ' (Other)' : htmlspecialchars($formData['office_designation'])) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Phone</th>
                                        <td>' . htmlspecialchars($formData['office_phone']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>State (English)</th>
                                        <td>' . htmlspecialchars($formData['office_state']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>State (Hindi)</th>
                                        <td>' . htmlspecialchars($formData['office_state_hindi']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>District</th>
                                        <td>' . htmlspecialchars($formData['office_district']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Office Address</th>
                                        <td>' . htmlspecialchars($formData['office_address']) . '</td>
                                    </tr>
                                    <tr>
                                        <th>Office Email</th>
                                        <td>' . htmlspecialchars($formData['office_email']) . '</td>
                                    </tr>
                                </table>';
                    
                    // Nominee Details
                    $htmlContent .= '<div class="section-title">Nominee Details</div>';
                    foreach ($formData['nominees'] as $index => $nominee) {
                        $htmlContent .= '<h4>Nominee #' . ($index + 1) . '</h4>';
                        $htmlContent .= '<table class="details-table">';
                        $htmlContent .= '<tr><th>Name (English)</th><td>' . htmlspecialchars($nominee['name']) . '</td></tr>';
                        $htmlContent .= '<tr><th>Name (Hindi)</th><td>' . htmlspecialchars($nominee['name_hindi']) . '</td></tr>';
                        $htmlContent .= '<tr><th>Gender</th><td>' . htmlspecialchars($nominee['gender']) . '</td></tr>';
                        $htmlContent .= '<tr><th>Rank</th><td>' . htmlspecialchars($nominee['rank']) . '</td></tr>';
                        $htmlContent .= '<tr><th>Rank (Hindi)</th><td>' . htmlspecialchars($nominee['rank_hindi']) . '</td></tr>';
                        $htmlContent .= '<tr><th>Phone</th><td>' . htmlspecialchars($nominee['phone']) . '</td></tr>';
                        $htmlContent .= '<tr><th>Email</th><td>' . htmlspecialchars($nominee['email']) . '</td></tr>';
                        $htmlContent .= '</table>';
                    }
                    
                    $htmlContent .= '
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
                    
                    // Create the Transport
                    $transport = (new Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))
                        ->setUsername('nikhilguptaji@gmail.com')
                        ->setPassword('dmmufijsqwaoenpy');
                    
                    // Create the Mailer
                    $mailer = new Swift_Mailer($transport);
                    
                    // Create the message - SEND TO ADMIN EMAIL
                    $message = (new Swift_Message('NCRB Nominee Form Submission'))
                        ->setFrom(['nikhilguptaji@gmail.com' => 'NCRB System'])
                        ->setReplyTo([$formData['office_email']])
                        ->setTo(['nikhillguptaa.2004@gmail.com'])
                        ->setBody($htmlContent, 'text/html');
                    
                    // Send the message
                    $result = $mailer->send($message);
                    
                    if ($result) {
                        $phoneMessage = '';
                        
                        // Send copy to office email
                        try {
                            $officeMessage = (new Swift_Message('Copy of Your NCRB Nominee Form Submission'))
                                ->setFrom(['nikhilguptaji@gmail.com' => 'NCRB System'])
                                ->setTo([$formData['office_email']])
                                ->setBody($htmlContent, 'text/html');
                            
                            $copyResult = $mailer->send($officeMessage);
                            $phoneMessage = "Details have been sent to NCRB and a copy to your email.";
                        } catch (Exception $e) {
                            error_log("Copy email sending error: " . $e->getMessage());
                            $phoneMessage = "Details sent to NCRB, but failed to send copy to your email.";
                        }
                        
                        // Send confirmation to phone if verified
                        if (!empty($formData['office_phone']) && 
                            isset($_SESSION['phone_verified']) && 
                            $_SESSION['phone_verified'] && 
                            $_SESSION['phone_to_verify'] === $formData['office_phone']) 
                        {
                            try {
                                $phone = $formData['office_phone'];
                                
                                // Format SMS/WhatsApp message with all details
                                $smsBody = "NCRB Nominee Submission:\n";
                                $smsBody .= "Course: {$formData['course_name']} ({$formData['course_name_hindi']})\n";
                                $smsBody .= "Dates: {$formData['start_date']} to {$formData['end_date']}\n";
                                $smsBody .= "Office: {$formData['office_category']} - {$formData['office_designation']}\n";
                                $smsBody .= "State: {$formData['office_state']} ({$formData['office_state_hindi']})\n";
                                $smsBody .= "District: {$formData['office_district']}\n";
                                $smsBody .= "Nominees:\n";
                                
                                foreach ($formData['nominees'] as $index => $nominee) {
                                    $smsBody .= ($index + 1) . ". {$nominee['name']} ({$nominee['name_hindi']}) - {$nominee['rank']}\n";
                                }
                                
                                $successCount = 0;
                                $deliveryErrors = [];
                                
                                // Only send if Twilio is available
                                if ($twilio !== null) {
                                    // Send via SMS
                                    try {
                                        $twilio->messages->create(
                                            "+91" . $phone,
                                            [
                                                'from' => '+19472148608',
                                                'body' => $smsBody
                                            ]
                                        );
                                        $successCount++;
                                    } catch (Exception $smsEx) {
                                        error_log("SMS sending error: " . $smsEx->getMessage());
                                        $deliveryErrors[] = "SMS: " . $smsEx->getMessage();
                                    }
                                    
                                    // Send via WhatsApp
                                    try {
                                        $twilio->messages->create(
                                            "whatsapp:+91" . $phone,
                                            [
                                                'from' => 'whatsapp:+14155238886',
                                                'body' => $smsBody
                                            ]
                                        );
                                        $successCount++;
                                    } catch (Exception $waEx) {
                                        error_log("WhatsApp sending error: " . $waEx->getMessage());
                                        $deliveryErrors[] = "WhatsApp: " . $waEx->getMessage();
                                    }
                                } else {
                                    $deliveryErrors[] = "Twilio service unavailable";
                                }
                                
                                // Build success message with error details
                                if ($successCount > 0) {
                                    $phoneMessage .= " Full details sent via " . 
                                                    ($successCount === 2 ? "SMS and WhatsApp" : 
                                                    ($successCount === 1 ? "SMS" : "WhatsApp"));
                                    
                                    if (!empty($deliveryErrors)) {
                                        $phoneMessage .= " (partial failures: " . implode(", ", $deliveryErrors) . ")";
                                    }
                                } else {
                                    $phoneMessage .= " Could not send details via SMS/WhatsApp: " . implode("; ", $deliveryErrors);
                                }
                            } catch (Exception $e) {
                                error_log("Phone confirmation error: " . $e->getMessage());
                                $phoneMessage .= " Error sending phone confirmation: " . $e->getMessage();
                            }
                        }
                        
                        $success = $phoneMessage;
                        
                        // Reset form data to initial state
                        $formData = [
                            'course_name' => '',
                            'course_name_hindi' => '',
                            'start_date' => '',
                            'end_date' => '',
                            'office_category' => '',
                            'office_category_hindi' => '',
                            'office_designation' => '',
                            'office_phone' => '',
                            'office_state' => '',
                            'office_state_hindi' => '',
                            'office_district' => '',
                            'office_address' => '',
                            'office_email' => '',
                            'other_category' => '',
                            'other_category_hindi' => '',
                            'other_rank' => '',
                            'nominees' => []
                        ];
                        $_SESSION['captcha_code'] = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                    } else {
                        $error = "Nominee saved but email could not be sent. Please contact support with your Nominee details.";
                    }
                } catch (Exception $e) {
                    $error = "Nominee saved but email could not be sent. Please contact support with your Nominee details.";
                }
            }
        }
    }
}

// Get dropdown data for form
$conn = getDBConnection();

// Get distinct courses with their date ranges
$courses = [];
$sql = "SELECT DISTINCT course_name, course_name_hindi FROM training_events";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courseName = $row['course_name'];
        $courseHindi = $row['course_name_hindi'];
        
        $courses[] = [
            'course_name' => $courseName,
            'course_name_hindi' => $courseHindi
        ];
    }
}

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
    <title>Nominee Form | National Crime Records Bureau</title>
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

        .nominee-section {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .add-nominee-btn {
            margin-top: 10px;
        }

        .nominee-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .nominee-header h5 {
            color: var(--primary);
            font-weight: 600;
            margin: 0;
        }

        .remove-nominee {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 0.9rem;
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
            
            .otp-section {
                flex-direction: column;
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
                    <i class="fas fa-user-graduate me-2"></i> Nominee Form
                </div>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-user-graduate me-2"></i> Nominee Form</h2>
            </div>
            
            <div class="form-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" id="nomineeForm">
                    <!-- Course Details Section -->
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-book me-2"></i> Course Details</h4>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="course_name" class="form-label required">Course Name</label>
                                <select class="form-select" id="course_name" name="course_name" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_name']); ?>" <?php echo ($course['course_name'] == ($formData['course_name'] ?? '')) ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo htmlspecialchars($course['course_name_hindi']); ?>" <?php echo ($course['course_name_hindi'] == ($formData['course_name_hindi'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_name_hindi']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

        <!-- Date Range Selection -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <label for="date_range" class="form-label required">Date Range</label>
                <select class="form-select" id="date_range" name="date_range" required>
                    <option value="">Select Date Range</option>
                    <!-- Will be populated by JavaScript -->
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="start_date" class="form-label required">Start Date</label>
                <input type="text" class="form-control" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($formData['start_date']); ?>" readonly>
            </div>
            <div class="col-md-3 mb-3">
                <label for="end_date" class="form-label required">End Date</label>
                <input type="text" class="form-control" id="end_date" name="end_date" required value="<?php echo htmlspecialchars($formData['end_date']); ?>" readonly>
            </div>
        </div>
    </div>

    <!-- Nominating Office Details Section -->
    <div class="form-section">
        <h4 class="section-title"><i class="fas fa-building me-2"></i> Nominating Office Details</h4>
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <label for="office_category" class="form-label required">Category</label>
                <select class="form-select" id="office_category" name="office_category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_name']); ?>" <?php echo ($category['category_name'] == ($formData['office_category'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Other" <?php echo (($formData['office_category'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <div class="other-input-container" id="other-category-en">
                    <input type="text" class="form-control mt-2" id="other_category" name="other_category" 
                           placeholder="Specify other category" value="<?php echo htmlspecialchars($formData['other_category'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label for="office_category_hindi" class="form-label required">श्रेणी</label>
                <select class="form-select" id="office_category_hindi" name="office_category_hindi" required>
                    <option value="">श्रेणी चुने</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_name_hindi']); ?>" <?php echo ($category['category_name_hindi'] == ($formData['office_category_hindi'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name_hindi']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="अन्य" <?php echo (($formData['office_category_hindi'] ?? '') === 'अन्य') ? 'selected' : ''; ?>>अन्य</option>
                </select>
                <div class="other-input-container" id="other-category-hi">
                    <input type="text" class="form-control mt-2 hindi-input" id="other_category_hindi" name="other_category_hindi" 
                           placeholder="अन्य श्रेणी निर्दिष्ट करें" value="<?php echo htmlspecialchars($formData['other_category_hindi'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <label for="office_designation" class="form-label required">Designation</label>
                <select class="form-select" id="office_designation" name="office_designation" required>
                    <option value="">Select Designation</option>
                    <?php if (!empty($formData['office_category'])): ?>
                        <?php
                            $conn = getDBConnection();
                            $rank_result = $conn->query("SELECT rank FROM ranks WHERE category_name = '{$formData['office_category']}' ORDER BY rank");
                            if ($rank_result) {
                                while ($rank = $rank_result->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($rank['rank']); ?>" <?php echo ($rank['rank'] == ($formData['office_designation'] ?? '')) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rank['rank']); ?>
                                </option>
                        <?php endwhile;
                            }
                            $conn->close();
                        ?>
                    <?php endif; ?>
                    <option value="Other" <?php echo (($formData['office_designation'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <div class="other-input-container" id="other-rank-en">
                    <input type="text" class="form-control mt-2" id="other_rank" name="other_rank" 
                           placeholder="Specify other rank" value="<?php echo htmlspecialchars($formData['other_rank'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <label for="office_state" class="form-label required">State</label>
                <select class="form-select" id="office_state" name="office_state" required>
                    <option value="">Select State</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo htmlspecialchars($state['state_name']); ?>" <?php echo ($state['state_name'] == ($formData['office_state'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($state['state_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="office_state_hindi" class="form-label required">राज्य</label>
                <select class="form-select" id="office_state_hindi" name="office_state_hindi" required>
                    <option value="">राज्य चुने</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo htmlspecialchars($state['state_name_hindi']); ?>" <?php echo ($state['state_name_hindi'] == ($formData['office_state_hindi'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($state['state_name_hindi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label for="office_district" class="form-label">District</label>
                <select class="form-select" id="office_district" name="office_district" <?php echo empty($formData['office_state']) ? 'disabled' : ''; ?>>
                    <option value=""><?php echo empty($formData['office_state']) ? 'Select State first' : 'Select District'; ?></option>
                    <?php if (!empty($formData['office_state'])): ?>
                        <?php
                        $conn = getDBConnection();
                        $districts = $conn->query("SELECT district_name FROM districts WHERE state_name = '{$formData['office_state']}' ORDER BY district_name");
                        if ($districts) {
                            while ($district = $districts->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($district['district_name']); ?>" <?php echo ($district['district_name'] == ($formData['office_district'] ?? '')) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($district['district_name']); ?>
                                </option>
                            <?php endwhile; 
                        }
                            $conn->close();
                        ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12 mb-3">
                <label for="office_address" class="form-label">Office Address</label>
                <textarea class="form-control" id="office_address" name="office_address" rows="2"><?php echo htmlspecialchars($formData['office_address']); ?></textarea>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="office_email" class="form-label required">Email Address</label>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <input type="email" class="form-control" id="office_email" name="office_email" required value="<?php echo htmlspecialchars($formData['office_email']); ?>" placeholder="Enter office email">
                    <button type="button" class="btn btn-outline-action" id="send-otp-btn">Send OTP</button>
                    <span id="verification-status" class="verification-status <?php echo (isset($_SESSION['email_verified'])) && $_SESSION['email_verified'] && ($_SESSION['email_to_verify'] ?? '') === ($formData['office_email'] ?? '') ? 'verified' : 'not-verified'; ?>">
                        <?php echo (isset($_SESSION['email_verified'])) && $_SESSION['email_verified'] && ($_SESSION['email_to_verify'] ?? '') === ($formData['office_email'] ?? '') ? '✓ Verified' : 'Not Verified'; ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2" id="otp-section">
                    <input type="text" class="form-control otp-input" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6">
                    <button type="button" class="btn btn-action" id="verify-otp-btn">Verify</button>
                </div>
                <div id="otp-message" class="mt-2"></div>
            </div>
            <div class="col-md-5 mb-3">                                                    
                <label for="office_phone" class="form-label">Mobile Phone</label>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <input type="tel" class="form-control" id="office_phone" name="office_phone" maxlength="10" value="<?php echo htmlspecialchars($formData['office_phone']); ?>" placeholder="Enter your Phone">
                    <button type="button" class="btn btn-outline-action" id="send-sms-otp-btn">Send OTP</button>
                    <?php
                    $phoneVerified = false;
                    if (isset($_SESSION['phone_verified'], $_SESSION['phone_to_verify']) && 
                        $_SESSION['phone_verified'] && 
                        $_SESSION['phone_to_verify'] === $formData['office_phone'])
                    {
                        $phoneVerified = true;
                    }
                    ?>
                    <span id="phone-verification-status" class="verification-status <?php echo $phoneVerified ? 'verified' : 'not-verified'; ?>">
                        <?php echo $phoneVerified ? '✓ Verified' : 'Not Verified'; ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2" id="sms-otp-section">
                    <input type="text" class="form-control otp-input" id="sms-otp" name="sms_otp" placeholder="Enter 6-digit OTP" maxlength="6">
                    <button type="button" class="btn btn-action" id="verify-sms-otp-btn">Verify</button>
                </div>
                <div id="sms-otp-message" class="mt-2"></div>
            </div>                                                    
        </div>
    </div>

    <!-- Nominee Details Section -->
    <div class="form-section">
        <h4 class="section-title"><i class="fas fa-users me-2"></i> Nominee Details</h4>
        <div id="nominee-container">
            <div class="nominee-section" data-index="0">
                <div class="nominee-header">
                    <h5>Nominee #1</h5>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Name (English)</label>
                        <input type="text" class="form-control" name="nominee_name[]" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">नाम (हिन्दी में)</label>
                        <input type="text" class="form-control hindi-input" name="nominee_name_hindi[]">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-3 mb-3">
                        <label class="form-label required">Gender</label>
                        <select class="form-select" name="nominee_gender[]" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label required">Rank</label>
                        <select class="form-select nominee-rank" name="nominee_rank[]" required>
                            <option value="">Select Rank</option>
                            <?php if (!empty($formData['office_category'])): ?>
                                <?php
                                $conn = getDBConnection();
                                $rank_result = $conn->query("SELECT rank FROM ranks WHERE category_name = '{$formData['office_category']}' ORDER BY rank");
                                if ($rank_result) {
                                    while ($rank = $rank_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($rank['rank']); ?>">
                                        <?php echo htmlspecialchars($rank['rank']); ?>
                                    </option>
                                    <?php endwhile; 
                                }
                                    $conn->close();
                                ?>
                            <?php endif; ?>
                                <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label required">रैंक</label>
                        <select class="form-select nominee-rank-hindi" name="nominee_rank_hindi[]" required>
                            <option value="">रैंक चुने</option>
                            <?php if (!empty($formData['office_category'])): ?>
                                <?php
                                $conn = getDBConnection();
                                $rank_result = $conn->query("SELECT rank_hindi FROM ranks WHERE category_name = '{$formData['office_category']}' ORDER BY rank");
                                if ($rank_result) {
                                    while ($rank = $rank_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($rank['rank_hindi']); ?>">
                                        <?php echo htmlspecialchars($rank['rank_hindi']); ?>
                                    </option>
                                    <?php endwhile; 
                                }
                                    $conn->close();
                                ?>
                            <?php endif; ?>
                                <option value="अन्य">अन्य</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Mobile Phone</label>
                        <input type="tel" class="form-control" name="nominee_phone[]" maxlength="10">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="nominee_email[]" placeholder="Enter nominee email">
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-action add-nominee-btn" id="add-nominee">
            <i class="fas fa-plus me-2"></i>Add 1 more Nominee
        </button>
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
        <button type="submit" class="btn btn-submit me-3">Submit Nominee</button>
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
    <script>
    $(document).ready(function() {
        // Function to toggle other fields visibility
        function toggleOtherFields() {
            // Toggle category fields
            const isOtherCategoryEN = $('#office_category').val() === 'Other';
            const isOtherCategoryHI = $('#office_category_hindi').val() === 'अन्य';
            
            $('#other-category-en').toggle(isOtherCategoryEN);
            $('#other-category-hi').toggle(isOtherCategoryHI);
            $('#other_category').prop('required', isOtherCategoryEN);
            $('#other_category_hindi').prop('required', isOtherCategoryHI);
            
            // Toggle rank field
            const isOtherRank = $('#office_designation').val() === 'Other';
            $('#other-rank-en').toggle(isOtherRank);
            $('#other_rank').prop('required', isOtherRank);
        }

        // Initialize other fields visibility
        toggleOtherFields();

        // Category change handler
        $('#office_category, #office_category_hindi').change(function() {
            const isHindi = $(this).attr('id') === 'office_category_hindi';
            const categorySelect = isHindi ? $('#office_category_hindi') : $('#office_category');
            const otherCategorySelect = isHindi ? $('#office_category') : $('#office_category_hindi');
            const category = categorySelect.val();

            // Sync the other category dropdown
            otherCategorySelect[0].selectedIndex = this.selectedIndex;

            // Update other fields visibility
            toggleOtherFields();

            // Skip AJAX if "Other" selected
            if (category === 'Other' || category === 'अन्य') return;

            // Load designations for the selected category
            loadDesignationsForCategory(category, isHindi);
            
            // Update ranks for all nominees
            updateNomineeRanks(category);
        });

        // Function to load designations for a category
        function loadDesignationsForCategory(category, isHindi) {
            const designationSelect = $('#office_designation');
            designationSelect.prop('disabled', false).html('<option value="">Loading designations...</option>');

            $.ajax({
                url: 'form-nominee.php?ajax=get_rank&category=' + encodeURIComponent(category),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    try {
                        if (response && response.status === 'success') {
                            designationSelect.html(response.designation);
                            toggleOtherFields();
                        } else {
                            const errorMsg = response && response.message ? response.message : 'Error loading designations';
                            designationSelect.html(`<option value="">${errorMsg}</option>`);
                        }
                    } catch (e) {
                        designationSelect.html('<option value="">Error parsing response</option>');
                    }
                },
                error: function(xhr, status, error) {
                    designationSelect.html('<option value="">Request failed. Try again</option>');
                    console.error("AJAX Error:", status, error);
                }
            });
        }

        // Function to update ranks for all nominees
        function updateNomineeRanks(category) {
            $.ajax({
                url: 'form-nominee.php?ajax=get_rank&category=' + encodeURIComponent(category),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    try {
                        if (response && response.status === 'success') {
                            $('.nominee-rank').each(function() {
                                $(this).html(response.english);
                            });
                            $('.nominee-rank-hindi').each(function() {
                                $(this).html(response.hindi);
                            });
                        } else {
                            console.error("Failed to update nominee ranks:", response ? response.message : "Unknown error");
                        }
                    } catch (e) {
                        console.error("Error updating nominee ranks:", e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error updating nominee ranks:", status, error);
                }
            });
        }

        // Designation change handler
        $('#office_designation').change(function() {
            toggleOtherFields();
        });

        // STATE AND DISTRICT HANDLING
        $('#office_state, #office_state_hindi').change(function() {
            const isHindi = $(this).attr('id') === 'office_state_hindi';
            const stateSelect = isHindi ? $('#office_state_hindi') : $('#office_state');
            const otherStateSelect = isHindi ? $('#office_state') : $('#office_state_hindi');
            const state = stateSelect.val();
            const districtSelect = $('#office_district');
            
            // Sync the other state dropdown
            otherStateSelect[0].selectedIndex = this.selectedIndex;
            
            if (state) {
                districtSelect.prop('disabled', false).html('<option value="" class="loading-districts">Loading districts...</option>');
                
                $.ajax({
                    url: '?ajax=get_districts&state=' + encodeURIComponent(state),
                    type: 'GET',
                    success: function(data) {
                        districtSelect.html(data || '<option value="">No districts found</option>');
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
            if (isHindi) {
                $('#course_name')[0].selectedIndex = this.selectedIndex;
            } else {
                $('#course_name_hindi')[0].selectedIndex = this.selectedIndex;
            }
            
            // Load date ranges for selected course
            const courseName = $('#course_name').val();
            if (courseName) {
                $('#date_range').html('<option value="">Loading date ranges...</option>');
                
                $.ajax({
                    url: '?ajax=get_date_ranges&course_name=' + encodeURIComponent(courseName),
                    type: 'GET',
                    success: function(data) {
                        $('#date_range').html(data);
                    },
                    error: function() {
                        $('#date_range').html('<option value="">Error loading date ranges</option>');
                    }
                });
            } else {
                $('#date_range').html('<option value="">Select a course first</option>');
                $('#start_date').val('');
                $('#end_date').val('');
            }
        });

        // Date range selection handler
        $('#date_range').change(function() {
            const selectedOption = $(this).find('option:selected');
            if (selectedOption.val()) {
                const startDate = selectedOption.data('start');
                const endDate = selectedOption.data('end');
                
                // Format dates to d-m-Y format
                const startDateObj = new Date(startDate);
                const endDateObj = new Date(endDate);
                
                const formattedStart = `${String(startDateObj.getDate()).padStart(2, '0')}-${String(startDateObj.getMonth() + 1).padStart(2, '0')}-${startDateObj.getFullYear()}`;
                const formattedEnd = `${String(endDateObj.getDate()).padStart(2, '0')}-${String(endDateObj.getMonth() + 1).padStart(2, '0')}-${endDateObj.getFullYear()}`;
                
                $('#start_date').val(formattedStart);
                $('#end_date').val(formattedEnd);
            } else {
                $('#start_date').val('');
                $('#end_date').val('');
            }
        });

        // RANK DROPDOWN SYNC FOR NOMINEES
        $(document).on('change', '.nominee-rank', function() {
            const $section = $(this).closest('.nominee-section');
            const $hindiDropdown = $section.find('.nominee-rank-hindi');
            $hindiDropdown.prop('selectedIndex', this.selectedIndex);
        });

        $(document).on('change', '.nominee-rank-hindi', function() {
            const $section = $(this).closest('.nominee-section');
            const $englishDropdown = $section.find('.nominee-rank');
            $englishDropdown.prop('selectedIndex', this.selectedIndex);
        });

        // email OTP HANDLING - FIXED RESPONSE PARSING
        $('#send-otp-btn').click(function() {
            const email = $('#office_email').val();
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                $('#otp-message').html('<div class="text-danger">Please enter a valid email address</div>');
                return;
            }
            
            $(this).prop('disabled', true).text('Sending...');
            
            $.get('form-nominee.php?generate_otp=1&email=' + encodeURIComponent(email))
                .done(function(response) {
                    try {
                        // Try to parse as JSON
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (data.status === 'success') {
                            $('#otp-message').html('<div class="text-success">OTP sent successfully. Check your email.</div>');
                            $('#otp-section').show();
                            $('#verification-status').removeClass('verified').addClass('not-verified').text('Not Verified');
                        } else {
                            $('#otp-message').html(`<div class="text-danger">${data.message || 'Failed to send OTP'}</div>`);
                        }
                    } catch (e) {
                        // Handle non-JSON response
                        console.error("Response parsing error:", e, response);
                        $('#otp-message').html('<div class="text-danger">Invalid server response</div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    $('#otp-message').html(`<div class="text-danger">Request failed: ${error}</div>`);
                })
                .always(function() {
                    $('#send-otp-btn').prop('disabled', false).text('Send OTP');
                });
        });

        // FIXED OTP VERIFICATION
        $('#verify-otp-btn').click(function() {
            const otp = $('#otp').val();
            const email = $('#office_email').val();
            
            if (!otp || !email || !/^\d{6}$/.test(otp)) {
                $('#otp-message').html('<div class="text-danger">Please enter a valid 6-digit OTP</div>');
                return;
            }
            
            $(this).prop('disabled', true).text('Verifying...');
            
            $.post('form-nominee.php', {
                verify_otp: 1,
                otp: otp,
                email: email
            })
            .done(function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.status === 'success') {
                        $('#otp-message').html('<div class="text-success">Email verified successfully!</div>');
                        $('#verification-status').removeClass('not-verified').addClass('verified').text('✓ Verified');
                        $('#otp-section').hide();
                    } else {
                        $('#otp-message').html(`<div class="text-danger">${data.message || 'Verification failed'}</div>`);
                    }
                } catch (e) {
                    console.error("Response parsing error:", e, response);
                    $('#otp-message').html('<div class="text-danger">Invalid server response</div>');
                }
            })
            .fail(function(xhr, status, error) {
                $('#otp-message').html(`<div class="text-danger">Request failed: ${error}</div>`);
            })
            .always(function() {
                $('#verify-otp-btn').prop('disabled', false).text('Verify');
            });
        });

        // SMS/WhatsApp OTP handling - FIXED
        $('#send-sms-otp-btn').click(function() {
            const phone = $('#office_phone').val();
            if (!phone || !/^\d{10}$/.test(phone)) {
                $('#sms-otp-message').html('<div class="text-danger">Please enter a valid 10-digit phone number</div>');
                return;
            }
    
            $(this).prop('disabled', true).text('Sending...');
    
            $.get('form-nominee.php?generate_sms_otp=1&phone=' + encodeURIComponent(phone))
                .done(function(response) {
                    try {
                        // Handle both JSON and plain text responses
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (data.status === 'success') {
                            $('#sms-otp-message').html(`<div class="text-success">${data.message}</div>`);
                            $('#sms-otp-section').show();
                            $('#phone-verification-status').removeClass('verified').addClass('not-verified').text('Not Verified');
                        } else {
                            $('#sms-otp-message').html(`<div class="text-danger">${data.message || 'Failed to send OTP'}</div>`);
                        }
                    } catch (e) {
                        console.error("Response parsing error:", e, response);
                        $('#sms-otp-message').html('<div class="text-danger">Invalid server response</div>');
                    }
                })
                .fail(function(xhr, status, error) {
                    $('#sms-otp-message').html(`<div class="text-danger">Request failed: ${error}</div>`);
                })
                .always(function() {
                    $('#send-sms-otp-btn').prop('disabled', false).text('Send OTP');
                });
        });

        // FIXED SMS OTP VERIFICATION
        $('#verify-sms-otp-btn').click(function() {
            const otp = $('#sms-otp').val();
            const phone = $('#office_phone').val();
    
            if (!otp || !phone || !/^\d{6}$/.test(otp)) {
                $('#sms-otp-message').html('<div class="text-danger">Please enter a valid 6-digit OTP</div>');
                return;
            }
    
            $(this).prop('disabled', true).text('Verifying...');
    
            $.post('form-nominee.php', {
                verify_sms_otp: 1,
                sms_otp: otp,
                phone: phone
            })
            .done(function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.status === 'success') {
                        $('#sms-otp-message').html('<div class="text-success">Phone verified successfully!</div>');
                        $('#phone-verification-status').removeClass('not-verified').addClass('verified').text('✓ Verified');
                        $('#sms-otp-section').hide();
                    } else {
                        $('#sms-otp-message').html(`<div class="text-danger">${data.message || 'Verification failed'}</div>`);
                    }
                } catch (e) {
                    console.error("Response parsing error:", e, response);
                    $('#sms-otp-message').html('<div class="text-danger">Invalid server response</div>');
                }
            })
            .fail(function(xhr, status, error) {
                $('#sms-otp-message').html(`<div class="text-danger">Request failed: ${error}</div>`);
            })
            .always(function() {
                $('#verify-sms-otp-btn').prop('disabled', false).text('Verify');
            });
        });

        // Add new nominee
        let nomineeCount = 1;
        $('#add-nominee').click(function() {
            nomineeCount++;
            const newIndex = nomineeCount - 1;
            
            $.ajax({
                url: 'form-nominee.php?ajax=get_rank&category=' + encodeURIComponent($('#office_category').val() || ''),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    try {
                        let englishRanks = '<option value="">Select Rank</option>';
                        let hindiRanks = '<option value="">रैंक चुने</option>';
                        
                        if (response && response.status === 'success') {
                            englishRanks = response.english;
                            hindiRanks = response.hindi;
                        } else {
                            console.error("Failed to load ranks:", response ? response.message : "Unknown error");
                        }
                        
                        const newNominee = `
                            <div class="nominee-section" data-index="${newIndex}">
                                <div class="nominee-header">
                                    <h5>Nominee #${nomineeCount}</h5>
                                    <button type="button" class="btn btn-sm btn-danger remove-nominee">Remove</button>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Name (English)</label>
                                        <input type="text" class="form-control" name="nominee_name[]" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">नाम (हिन्दी में)</label>
                                        <input type="text" class="form-control hindi-input" name="nominee_name_hindi[]">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label required">Gender</label>
                                        <select class="form-select" name="nominee_gender[]" required>
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label required">Rank</label>
                                        <select class="form-select nominee-rank" name="nominee_rank[]" required>
                                            ${englishRanks}
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label required">रैंक</label>
                                        <select class="form-select nominee-rank-hindi" name="nominee_rank_hindi[]" required>
                                            ${hindiRanks}
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Mobile Phone</label>
                                        <input type="tel" class="form-control" name="nominee_phone[]" maxlength="10">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="nominee_email[]" placeholder="Enter nominee email">
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        $('#nominee-container').append(newNominee);
                    } catch (e) {
                        console.error("Error adding nominee:", e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error adding nominee:", status, error);
                }
            });
        });

        // Remove nominee
        $(document).on('click', '.remove-nominee', function() {
            if ($('.nominee-section').length > 1) {
                $(this).closest('.nominee-section').remove();
                // Renumber remaining nominees
                $('.nominee-section').each(function(index) {
                    $(this).find('h5').text(`Nominee #${index + 1}`);
                });
                nomineeCount--;
            } else {
                alert('At least one nominee is required');
            }
        });

        // FORM VALIDATION
        $('#nomineeForm').submit(function(e) {
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
            
            // Validate office email verification
            if (!$('#verification-status').hasClass('verified') || $('#office_email').val() !== '<?php echo isset($_SESSION["email_to_verify"]) ? $_SESSION["email_to_verify"] : ""; ?>') {
                $('#office_email').addClass('is-invalid');
                $('#otp-message').html('<div class="text-danger">Please verify your office email first</div>');
                valid = false;
            }
            
            // Validate phone numbers
            $('input[name="nominee_phone[]"], #office_phone').each(function() {
                const phone = $(this).val();
                if (phone && !/^\d{10}$/.test(phone)) {
                    $(this).addClass('is-invalid');
                    valid = false;
                }
            });

            // Validate other category fields if visible
            if ($('#other-category-en').is(':visible') && !$('#other_category').val()) {
                $('#other_category').addClass('is-invalid');
                valid = false;
            }
            if ($('#other-category-hi').is(':visible') && !$('#other_category_hindi').val()) {
                $('#other_category_hindi').addClass('is-invalid');
                valid = false;
            }
            if ($('#other-rank-en').is(':visible') && !$('#other_rank').val()) {
                $('#other_rank').addClass('is-invalid');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
                $('.alert-danger').remove();
                $('#nomineeForm').prepend(
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
            // Reset nominee count to 1
            $('.nominee-section:gt(0)').remove();
            nomineeCount = 1;
            $('.nominee-section:first h5').text('Nominee #1');
            // Hide all other input fields
            $('.other-input-container').hide();
        });

        // CAPTCHA refresh
        $('#refresh-captcha').click(function() {
            $.get('form-nominee.php?refresh_captcha=1', function(data) {
                $('#captcha-code').text(data);
                $('#captcha').val('').focus();
            });
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

        // INITIALIZATION
        if ($('#office_state').val()) $('#office_state').trigger('change');
        $('#otp-section').toggle(!$('#verification-status').hasClass('verified'));
        $('#sms-otp-section').toggle(!$('#phone-verification-status').hasClass('verified'));
        
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