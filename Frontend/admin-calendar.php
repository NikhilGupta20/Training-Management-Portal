<?php
session_start();
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
    die("Connection failed: " . $conn->connect_error);
}

// Fetch admin details
$admin_username = $_SESSION['admin_username'];
$stmt = $conn->prepare("SELECT * FROM admin_credentials WHERE admin_username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit;
}

$admin = $result->fetch_assoc();
$username = htmlspecialchars($admin['admin_username']);
$stmt->close();

// Create tables if not exists
$tables = [
    "training_events" => "CREATE TABLE IF NOT EXISTS training_events (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255) DEFAULT '',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        duration INT(11) DEFAULT 0,
        location VARCHAR(100) DEFAULT 'NCRB Training Center',
        eligibility TEXT DEFAULT '',
        objectives TEXT DEFAULT '',
        color VARCHAR(7) DEFAULT '#ff6b6b',
        status ENUM('active','cancelled') DEFAULT 'active',
        reminders_enabled BOOLEAN DEFAULT 1
    )",
    
    "recipients" => "CREATE TABLE IF NOT EXISTS recipients (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        state_name VARCHAR(100) NOT NULL DEFAULT ''
    )",
    
    "email_log" => "CREATE TABLE IF NOT EXISTS email_log (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        event_id INT(11) NOT NULL,
        email_type ENUM('creation', 'cancellation', 'reminder', 'update') NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('sent', 'failed') NOT NULL,
        recipient_count INT(11) DEFAULT 0
    )",
    
    "event_reminders" => "CREATE TABLE IF NOT EXISTS event_reminders (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        event_id INT(11) NOT NULL,
        reminder_date DATE NOT NULL,
        days_before INT(11) NOT NULL,
        status ENUM('pending','sent','failed') DEFAULT 'pending',
        sent_at TIMESTAMP NULL DEFAULT NULL
    )"
];

foreach ($tables as $table => $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table $table: " . $conn->error);
    }
}

// Add new columns if missing
$conn->query("ALTER TABLE training_events ADD COLUMN IF NOT EXISTS duration INT(11) DEFAULT 0");
$conn->query("ALTER TABLE training_events ADD COLUMN IF NOT EXISTS status ENUM('active','cancelled') DEFAULT 'active'");
$conn->query("ALTER TABLE training_events ADD COLUMN IF NOT EXISTS reminders_enabled BOOLEAN DEFAULT 1");
$conn->query("ALTER TABLE training_events ADD COLUMN IF NOT EXISTS objectives TEXT DEFAULT ''");

// Ensure email_log has the updated enum type
$conn->query("ALTER TABLE email_log MODIFY email_type ENUM('creation', 'cancellation', 'reminder', 'update') NOT NULL");

// Function to send email
function sendEmail($to, $name, $subject, $body) {
    // SMTP Configuration
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_USER', 'nikhilguptaji@gmail.com');
    define('SMTP_PASS', 'dmmufijsqwaoenpy');
    define('SMTP_PORT', 465);
    define('EMAIL_FROM', 'noreply@ncrb.gov.in');
    define('EMAIL_FROM_NAME', 'NCRB Training Calendar');

    require_once __DIR__ . '/vendor/autoload.php';
    
    try {
        $transport = (new Swift_SmtpTransport(SMTP_HOST, SMTP_PORT, 'ssl'))
            ->setUsername(SMTP_USER)
            ->setPassword(SMTP_PASS);

        $mailer = new Swift_Mailer($transport);

        $message = (new Swift_Message($subject))
            ->setFrom([EMAIL_FROM => EMAIL_FROM_NAME])
            ->setTo([$to => $name])
            ->setBody($body, 'text/html');

        return $mailer->send($message) > 0;
    } catch (Exception $e) {
        error_log("Email sending failed to $to: " . $e->getMessage());
        return false;
    }
}

// Function to generate professional email body
function getEmailBody($content, $type = 'creation') {
    $headerColor = '';
    $headerText = '';
    
    switch ($type) {
        case 'creation':
            $headerColor = '#4CAF50'; // Green
            $headerText = 'New Training Program';
            break;
        case 'cancellation':
            $headerColor = '#F44336'; // Red
            $headerText = 'Program Cancellation';
            break;
        case 'reminder':
            $headerColor = '#2196F3'; // Blue
            $headerText = 'Training Reminder';
            break;
        case 'update':
            $headerColor = '#9C27B0'; // Purple
            $headerText = 'Program Update';
            break;
        default:
            $headerColor = '#003366';
            $headerText = 'NCRB Training';
    }
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .email-header {
            background-color: $headerColor;
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .ncrb-logo {
            height: 60px;
            margin-bottom: 15px;
        }
        .email-content {
            padding: 30px;
        }
        .email-content p {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .email-content strong {
            color: #002147;
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
        .instructions {
            background-color: #f8f9fa;
            border-left: 4px solid #003366;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .email-footer {
            background-color: #f0f7ff;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #e0e9ff;
        }
        .footer-logo {
            height: 40px;
            margin-bottom: 10px;
        }
        .signature {
            margin-top: 25px;
            border-top: 1px solid #eee;
            padding-top: 15px;
            font-style: italic;
        }
        .days-remaining {
            font-weight: bold;
            color: #d35400;
            background: #fdebd0;
            padding: 8px 15px;
            border-radius: 4px;
            display: inline-block;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo" class="ncrb-logo">
            <h1>$headerText</h1>
        </div>
        <div class="email-content">
            $content
            <div class="signature">
                <p>Regards,<br>Training Division<br>National Crime Records Bureau</p>
            </div>
        </div>
        <div class="email-footer">
            <p>Ministry of Home Affairs, Government of India</p>
            <p>Mahipalpur, New Delhi - 110037 | training@ncrb.gov.in</p>
            <p>This is an auto-generated email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

// Function to send pending reminders
function sendPendingReminders($conn) {
    $today = date('Y-m-d');
    $sql = "SELECT er.*, te.course_code, te.course_name, te.start_date, te.end_date, te.location, te.eligibility, te.objectives 
            FROM event_reminders er
            JOIN training_events te ON er.event_id = te.id
            WHERE er.status = 'pending' AND er.reminder_date = '$today'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) return;
    
    while ($row = $result->fetch_assoc()) {
        $event_id = $row['event_id'];
        $reminder_id = $row['id'];
        $course_code = $row['course_code'];
        $course_name = $row['course_name'];
        $start_date = date('d M Y', strtotime($row['start_date']));
        $end_date = date('d M Y', strtotime($row['end_date']));
        $location = $row['location'];
        $eligibility = $row['eligibility'];
        $objectives = $row['objectives'];
        // Calculate days until start
        $days_until_start = floor((strtotime($start_date) - time()) / (60 * 60 * 24));
        if ($days_until_start < 0) $days_until_start = 0;
        
        $subject = "Reminder: $course_code - $course_name starts in $days_until_start days";
        
        // Enhanced email content with days remaining
        $content = "Respected Sir/Madam,<br><br>
                    The Course on <strong>$course_name</strong> (<strong>$course_code</strong>) is scheduled to be held from <strong>$start_date</strong> to <strong>$end_date</strong> at National Crime Records Bureau (NCRB), New Delhi.<br><br>
                    <strong>Objectives:</strong> $objectives<br>
                    <strong>Participant Level:</strong> $eligibility<br>
                    <strong>Location:</strong> $location<br><br>
                        <div class='days-remaining'>The course will start in <strong>$days_until_start days</strong></div><br>
                    Please expedite to send nominations of two eligible willing Officers for the above mentioned training programme.<br>
                    Kindly fill the nomination form and submit the link as given below:<br>
                    <a href='http://localhost/NCRB/form-nominee.php' class='button'>Nomination Form</a><br><br>
                    and Kindly forward the below link to the participants you nominated:<br>
                    <a href='http://localhost/NCRB/form-participant.php' class='button'>Participant Form</a><br><br>
                    <div class='instructions'>
                    <strong>Important Instructions:</strong><br>
                    Please ensure that the nominated participants are relieved only after receiving acceptance from NCRB, New Delhi. [Kindly note that the mobile number and email-id of nominated officers are mandatory.]
                    </div>";
        
        $body = getEmailBody($content, 'reminder');
        
        // Get states that have submitted nominees
        $submittedStates = [];
        $stateQuery = $conn->prepare("SELECT DISTINCT state_name FROM nominee WHERE course_name = ?");
        $stateQuery->bind_param("s", $course_name);
        $stateQuery->execute();
        $stateResult = $stateQuery->get_result();
        while ($stateRow = $stateResult->fetch_assoc()) {
            $submittedStates[] = $stateRow['state_name'];
        }
        
        // Get recipients from states that haven't submitted
        $recipients = [];
        if (!empty($submittedStates)) {
            $placeholders = implode(',', array_fill(0, count($submittedStates), '?'));
            $types = str_repeat('s', count($submittedStates));
            $recipientQuery = $conn->prepare("SELECT email FROM recipients WHERE state_name NOT IN ($placeholders)");
            $recipientQuery->bind_param($types, ...$submittedStates);
        } else {
            $recipientQuery = $conn->prepare("SELECT email FROM recipients");
        }
        
        $recipientQuery->execute();
        $recipientResult = $recipientQuery->get_result();
        while ($recipient = $recipientResult->fetch_assoc()) {
            $recipients[] = $recipient['email'];
        }
        $recipient_count = count($recipients);
        
        $success = true;
        $sentCount = 0;
        foreach ($recipients as $recipient) {
            if (sendEmail($recipient, "Participant", $subject, $body)) {
                $sentCount++;
            }
        }
        
        $status = $sentCount > 0 ? 'sent' : 'failed';
        $conn->query("UPDATE event_reminders SET status = '$status', sent_at = NOW() WHERE id = $reminder_id");
        
        $insertLog = "INSERT INTO email_log (event_id, email_type, sent_at, status, recipient_count) 
                       VALUES ($event_id, 'reminder', NOW(), '$status', $sentCount)";
        $conn->query($insertLog);
    }
}

// Send pending reminders
sendPendingReminders($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle reminders
    if (isset($_POST['toggle_reminders'])) {
        $event_id = $conn->real_escape_string($_POST['event_id']);
        $conn->query("UPDATE training_events SET reminders_enabled = NOT reminders_enabled WHERE id = $event_id");
        $_SESSION['success'] = "Reminders setting updated!";
        header("Location: admin-calendar.php");
        exit;
    }
    
    // Add new event
    if (isset($_POST['add_event'])) {
        $course_code = $conn->real_escape_string($_POST['course_code']);
        $course_name = $conn->real_escape_string($_POST['course_name']);
        $course_name_hindi = $conn->real_escape_string($_POST['course_name_hindi']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $duration = $conn->real_escape_string($_POST['duration']);
        $objectives = $conn->real_escape_string($_POST['objectives']);
        $location = $conn->real_escape_string($_POST['location']);
        $eligibility = $conn->real_escape_string($_POST['eligibility']);
        $color = $conn->real_escape_string($_POST['color']);
        
        $insertQuery = "INSERT INTO training_events (course_code, course_name, course_name_hindi, start_date, end_date, duration, objectives, location, eligibility, color) 
                        VALUES ('$course_code', '$course_name', '$course_name_hindi', '$start_date', '$end_date', '$duration', '$objectives', '$location', '$eligibility', '$color')";
        
        if ($conn->query($insertQuery)) {
            $event_id = $conn->insert_id;
            $recipients = $conn->query("SELECT * FROM recipients");
            $recipient_count = $recipients->num_rows;
            
            // Schedule FUTURE reminders only
            $reminderDays = [90, 70, 50, 30, 15, 7, 1];
            $today = date('Y-m-d');
            
            foreach ($reminderDays as $days) {
                $reminderDate = date('Y-m-d', strtotime("-$days days", strtotime($start_date)));
                
                // Only schedule if reminder date is in the future
                if ($reminderDate >= $today) {
                    $insertReminder = "INSERT INTO event_reminders (event_id, reminder_date, days_before) 
                                       VALUES ($event_id, '$reminderDate', $days)";
                    $conn->query($insertReminder);
                }
            }
            
            // Send creation emails
            $subject = "New Training Program: $course_code - $course_name";
            
            // Calculate days until start
            $days_until_start = floor((strtotime($start_date) - time()) / (60 * 60 * 24));
            if ($days_until_start < 0) $days_until_start = 0;
            
            // Enhanced email content with days remaining
            $content = "Respected Sir/Madam,<br><br>
                        The Course on <strong>$course_name</strong> (<strong>$course_code</strong>) is scheduled to be held from <strong>$start_date</strong> to <strong>$end_date</strong> at National Crime Records Bureau (NCRB), New Delhi.<br><br>
                        <strong>Objectives:</strong> $objectives<br>
                        <strong>Participant Level:</strong> $eligibility<br>
                        <strong>Location:</strong> $location<br><br>
                        <div class='days-remaining'>The course will start in <strong>$days_until_start days</strong></div><br>
                        <strong>Please expedite to send nominations of two eligible willing Officers for the above mentioned training programme.</strong><br>
                        <strong>Kindly fill the nomination form and submit the link as given below:</strong><br>
                        <a href='http://localhost/NCRB/form-nominee.php' class='button'>Nomination Form</a><br><br>
                        <strong>and Kindly forward the below link to the participants you nominated:</strong><br>
                        <a href='http://localhost/NCRB/form-participant.php' class='button'>Participant Form</a><br><br>
                        <div class='instructions'>
                        <strong>Important Instructions:</strong><br>
                        <strong>Please ensure that the nominated participants are relieved only after receiving the acceptance from NCRB, New Delhi. [Kindly note that the mobile number and email-id of nominated officers are mandatory.]</strong>
                        </div>";
            
            $body = getEmailBody($content, 'creation');
            
            $successCount = 0;
            while ($recipient = $recipients->fetch_assoc()) {
                if (sendEmail($recipient['email'], "Participant", $subject, $body)) {
                    $successCount++;
                }
            }
            
            $logStatus = $successCount > 0 ? 'sent' : 'failed';
            $conn->query("INSERT INTO email_log (event_id, email_type, sent_at, status, recipient_count) 
                          VALUES ($event_id, 'creation', NOW(), '$logStatus', $successCount)");
            
            $_SESSION['success'] = "Event added successfully! Emails sent to $successCount recipients.";
        } else {
            $_SESSION['error'] = "Error adding event: " . $conn->error;
        }
        header("Location: admin-calendar.php");
        exit;
    }
    
    // Update event
    if (isset($_POST['update_event'])) {
        $event_id = $conn->real_escape_string($_POST['event_id']);
        $course_code = $conn->real_escape_string($_POST['course_code']);
        $course_name = $conn->real_escape_string($_POST['course_name']);
        $course_name_hindi = $conn->real_escape_string($_POST['course_name_hindi']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $duration = $conn->real_escape_string($_POST['duration']);
        $objectives = $conn->real_escape_string($_POST['objectives']);
        $location = $conn->real_escape_string($_POST['location']);
        $eligibility = $conn->real_escape_string($_POST['eligibility']);
        $color = $conn->real_escape_string($_POST['color']);
        
        $updateQuery = "UPDATE training_events SET 
                        course_code = '$course_code',
                        course_name = '$course_name',
                        course_name_hindi = '$course_name_hindi',
                        start_date = '$start_date',
                        end_date = '$end_date',
                        duration = '$duration',
                        objectives = '$objectives',
                        location = '$location',
                        eligibility = '$eligibility',
                        color = '$color'
                        WHERE id = $event_id";
        
        if ($conn->query($updateQuery)) {
            // Fetch updated event details
            $eventQuery = $conn->query("SELECT * FROM training_events WHERE id = $event_id");
            $event = $eventQuery->fetch_assoc();
            
            // Format dates for email
            $formatted_start = date('d M Y', strtotime($event['start_date']));
            $formatted_end = date('d M Y', strtotime($event['end_date']));
            
            // Calculate days until start
            $days_until_start = floor((strtotime($event['start_date']) - time()) / (60 * 60 * 24));
            if ($days_until_start < 0) $days_until_start = 0;
            
            // Send update notification emails
            $recipients = $conn->query("SELECT * FROM recipients");
            $subject = "Update: {$event['course_code']} - {$event['course_name']}";
            
            // Enhanced email content for update with days remaining
            $content = "Respected Sir/Madam,<br><br>
                        The details for the Course on <strong>{$event['course_name']}</strong> (<strong>{$event['course_code']}</strong>) have been updated.<br><br>
                        <strong>Updated Schedule:</strong><br>
                        - Start Date: $formatted_start<br>
                        - End Date: $formatted_end<br>
                        - Objectives: {$event['objectives']}<br>
                        - Eligibility: {$event['eligibility']}<br>
                        - Location: {$event['location']}<br><br>
                        <div class='days-remaining'>The course will start in <strong>$days_until_start days</strong></div><br>
                        Please review the updated details and make necessary arrangements.<br><br>
                        <div class='instructions'>
                        <strong>Important:</strong> If you have already submitted nominations, they remain valid unless you choose to update them.
                        </div>";
            
            $body = getEmailBody($content, 'update');
            
            $successCount = 0;
            while ($recipient = $recipients->fetch_assoc()) {
                if (sendEmail($recipient['email'], "Participant", $subject, $body)) {
                    $successCount++;
                }
            }
            
            $logStatus = $successCount > 0 ? 'sent' : 'failed';
            $conn->query("INSERT INTO email_log (event_id, email_type, sent_at, status, recipient_count) 
                          VALUES ($event_id, 'update', NOW(), '$logStatus', $successCount)");
            
            $_SESSION['success'] = "Event updated successfully! Update notification sent to $successCount recipients.";
        } else {
            $_SESSION['error'] = "Error updating event: " . $conn->error;
        }
        header("Location: admin-calendar.php");
        exit;
    }
    
    // Cancel event
    if (isset($_POST['cancel_event'])) {
        $event_id = $conn->real_escape_string($_POST['event_id']);
        $reason = $conn->real_escape_string($_POST['reason']);
        $custom_reason = isset($_POST['custom_reason']) ? $conn->real_escape_string($_POST['custom_reason']) : '';
        $final_reason = $reason === 'other' ? $custom_reason : $reason;
        
        $eventQuery = $conn->query("SELECT * FROM training_events WHERE id = $event_id");
        $event = $eventQuery->fetch_assoc();
        
        // Update status to 'cancelled' instead of deleting
        if ($conn->query("UPDATE training_events SET status = 'cancelled' WHERE id = $event_id")) {
            $recipients = $conn->query("SELECT * FROM recipients");
            
            $subject = "Cancellation: {$event['course_code']} - {$event['course_name']}";
            
            // Enhanced email content
            $content = "Respected Sir/Madam,<br><br>
                        The Course on <strong>{$event['course_name']}</strong> (<strong>{$event['course_code']}</strong>) scheduled from <strong>{$event['start_date']}</strong> to <strong>{$event['end_date']}</strong> at National Crime Records Bureau (NCRB), New Delhi, has been cancelled due to the following reason: <strong>$final_reason</strong>.<br><br>
                        We apologize for any inconvenience caused.";
            
            $body = getEmailBody($content, 'cancellation');
            
            $successCount = 0;
            while ($recipient = $recipients->fetch_assoc()) {
                if (sendEmail($recipient['email'], "Participant", $subject, $body)) {
                    $successCount++;
                }
            }
            
            $logStatus = $successCount > 0 ? 'sent' : 'failed';
            $conn->query("INSERT INTO email_log (event_id, email_type, sent_at, status, recipient_count) 
                          VALUES ($event_id, 'cancellation', NOW(), '$logStatus', $successCount)");
            
            $_SESSION['success'] = "Event cancelled! Cancellation notice sent to $successCount recipients.";
        } else {
            $_SESSION['error'] = "Error cancelling event: " . $conn->error;
        }
        header("Location: admin-calendar.php");
        exit;
    }
    
    // Send reminder
    if (isset($_POST['send_reminder'])) {
        $event_id = $conn->real_escape_string($_POST['event_id']);
        $eventQuery = $conn->query("SELECT * FROM training_events WHERE id = $event_id");
        $event = $eventQuery->fetch_assoc();
        
        // Get states that have submitted nominees
        $submittedStates = [];
        $stateQuery = $conn->prepare("SELECT DISTINCT state_name FROM nominee WHERE course_name = ?");
        $stateQuery->bind_param("s", $event['course_name']);
        $stateQuery->execute();
        $stateResult = $stateQuery->get_result();
        while ($stateRow = $stateResult->fetch_assoc()) {
            $submittedStates[] = $stateRow['state_name'];
        }
        
        // Get recipients from states that haven't submitted
        $recipients = [];
        if (!empty($submittedStates)) {
            $placeholders = implode(',', array_fill(0, count($submittedStates), '?'));
            $types = str_repeat('s', count($submittedStates));
            $recipientQuery = $conn->prepare("SELECT email FROM recipients WHERE state_name NOT IN ($placeholders)");
            $recipientQuery->bind_param($types, ...$submittedStates);
        } else {
            $recipientQuery = $conn->prepare("SELECT email FROM recipients");
        }
        
        $recipientQuery->execute();
        $recipientResult = $recipientQuery->get_result();
        while ($recipient = $recipientResult->fetch_assoc()) {
            $recipients[] = $recipient['email'];
        }
        
        $start_date = date('d M Y', strtotime($event['start_date']));
        $end_date = date('d M Y', strtotime($event['end_date']));
        // Calculate days until start
        $days_until_start = floor((strtotime($start_date) - time()) / (60 * 60 * 24));
        if ($days_until_start < 0) $days_until_start = 0;

        
        $subject = "Reminder: Upcoming Training - {$event['course_code']} - {$event['course_name']}";
        
        // Enhanced email content
        $content = "Respected Sir/Madam,<br><br>
                    The Course on <strong>{$event['course_name']}</strong> (<strong>{$event['course_code']}</strong>) is scheduled to be held from <strong>$start_date</strong> to <strong>$end_date</strong> at National Crime Records Bureau (NCRB), New Delhi.<br><br>
                    <strong>Objectives:</strong> {$event['objectives']}<br>
                    <strong>Participant Level:</strong> {$event['eligibility']}<br>
                    <strong>Location:</strong> {$event['location']}<br><br>
                    <div class='days-remaining'>The course will start in <strong>$days_until_start days</strong></div><br>                    
                    <strong>Please expedite to send nominations of two eligible willing Officers for the above mentioned training programme.</strong><br>
                    <strong>Kindly fill the nomination form using the link given below:</strong><br>
                    <a href='http://localhost/NCRB/form-nominee.php' class='button'>Nomination Form</a><br><br>
                    <strong>and Kindly forward the below link to the participants you nominated:</strong><br>
                    <a href='http://localhost/NCRB/form-participant.php' class='button'>Participant Form</a><br><br>
                    <div class='instructions'>
                    <strong>Important Instructions:</strong><br>
                    <strong>Please ensure that the nominated participants are relieved only after receiving acceptance from NCRB, New Delhi. Kindly note that the mobile number and email-id of nominated officers are mandatory.</strong>
                    </div>";
        
        $body = getEmailBody($content, 'reminder');
        
        $successCount = 0;
        foreach ($recipients as $recipient) {
            if (sendEmail($recipient, "Participant", $subject, $body)) {
                $successCount++;
            }
        }
        
        $logStatus = $successCount > 0 ? 'sent' : 'failed';
        $conn->query("INSERT INTO email_log (event_id, email_type, status, recipient_count) 
                      VALUES ($event_id, 'reminder', '$logStatus', $successCount)");
        
        $_SESSION['success'] = "Reminder sent to $successCount recipients!";
        header("Location: admin-calendar.php");
        exit;
    }
}

// Financial year calculations
function getFinancialYear($date) {
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    return ($month < 4) ? ($year - 1) . '-' . $year : $year . '-' . ($year + 1);
}

// Get date ranges for financial years
$currentFinancialYear = getFinancialYear(date('Y-m-d'));
$nextFinancialYear = getFinancialYear(date('Y-m-d', strtotime('+1 year')));
$currentYearDates = [
    'start' => explode('-', $currentFinancialYear)[0] . '-04-01',
    'end' => explode('-', $currentFinancialYear)[1] . '-03-31'
];
$nextYearDates = [
    'start' => explode('-', $nextFinancialYear)[0] . '-04-01',
    'end' => explode('-', $nextFinancialYear)[1] . '-03-31'
];

// Fetch events with duration and display_status
$currentYearEvents = [];
$result = $conn->query("SELECT *, 
    DATEDIFF(end_date, start_date) + 1 AS duration,
    CASE 
        WHEN status = 'cancelled' THEN 'Cancelled'
        WHEN end_date < CURDATE() THEN 'Completed'
        WHEN start_date <= CURDATE() AND end_date >= CURDATE() THEN 'Active'
        ELSE 'Upcoming'
    END AS display_status
    FROM training_events 
    WHERE start_date >= '{$currentYearDates['start']}' 
    AND start_date <= '{$currentYearDates['end']}' 
    ORDER BY 
        (end_date < CURDATE()) ASC, 
        start_date ASC");
while ($row = $result->fetch_assoc()) {
    $currentYearEvents[] = $row;
}

$nextYearEvents = [];
$result = $conn->query("SELECT *, 
    DATEDIFF(end_date, start_date) + 1 AS duration,
    CASE 
        WHEN status = 'cancelled' THEN 'Cancelled'
        WHEN end_date < CURDATE() THEN 'Completed'
        WHEN start_date <= CURDATE() AND end_date >= CURDATE() THEN 'Active'
        ELSE 'Upcoming'
    END AS display_status
    FROM training_events 
    WHERE start_date >= '{$nextYearDates['start']}' 
    AND start_date <= '{$nextYearDates['end']}' 
    ORDER BY 
        (end_date < CURDATE()) ASC, 
        start_date ASC");
while ($row = $result->fetch_assoc()) {
    $nextYearEvents[] = $row;
}

// All events for edit tab
$allEvents = $conn->query("SELECT *, 
    DATEDIFF(end_date, start_date) + 1 AS duration,
    CASE 
        WHEN status = 'cancelled' THEN 'Cancelled'
        WHEN end_date < CURDATE() THEN 'Completed'
        WHEN start_date <= CURDATE() AND end_date >= CURDATE() THEN 'Active'
        ELSE 'Upcoming'
    END AS display_status
    FROM training_events 
    ORDER BY 
        start_date DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch email logs for logs tab
$emailLogs = [];
$logQuery = $conn->query("
    SELECT el.*, te.course_code, te.course_name 
    FROM email_log el
    LEFT JOIN training_events te ON el.event_id = te.id
    ORDER BY el.sent_at DESC
");
if ($logQuery) {
    $emailLogs = $logQuery->fetch_all(MYSQLI_ASSOC);
}

// Get all courses for print filter
$allCourses = [];
$courseResult = $conn->query("SELECT DISTINCT course_name FROM training_events ORDER BY course_name");
while ($row = $courseResult->fetch_assoc()) {
    $allCourses[] = $row['course_name'];
}

// Initialize printEvents with allEvents
$printEvents = $allEvents;

// Check if we're on the print tab and if filters are applied
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tab']) && $_GET['tab'] === 'print') {
    $printStart = $_GET['print_start_date'] ?? '';
    $printEnd = $_GET['print_end_date'] ?? '';
    $printCourse = $_GET['print_course'] ?? '';
    
    // Apply filters only if any filter is set
    if (!empty($printStart) || !empty($printEnd) || !empty($printCourse)) {
        $printEvents = array_filter($printEvents, function($event) use ($printStart, $printEnd, $printCourse) {
            $matches = true;
            
            // Date range filtering (events that occur within the specified range)
            if (!empty($printStart) && $event['end_date'] < $printStart) {
                // Event ends before the filter start date
                $matches = false;
            }
            
            if (!empty($printEnd) && $event['start_date'] > $printEnd) {
                // Event starts after the filter end date
                $matches = false;
            }
            
            // Course filtering
            if (!empty($printCourse) && $event['course_name'] !== $printCourse) {
                $matches = false;
            }
            
            return $matches;
        });
    }
    
    // Sort printEvents by start_date (chronological order)
    usort($printEvents, function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });
}

// Color name mapping for display
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

$conn->close();

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Calendar Management | NCRB</title>
    
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
    <meta name="msapplication-TileImage" content="https://www.ncrb.gov.in/static/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ===== GLOBAL STYLES ===== */
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
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            width: 100%;
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

        /* Dashboard Section */
        .dashboard {
            max-width: 1400px;
            margin: 0 auto 50px;
            padding: 0 20px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
        }

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

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
            padding: 50px 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
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

        /* Tabs */
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e9ff;
            flex-wrap: wrap;
            gap: 10px;
        }

        .tab-btn {
            padding: 12px 30px;
            background: #f0f7ff;
            border: none;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: #d9e7ff;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
        
        .back-to-dashboard {
            background: linear-gradient(to right, #ff9900, #e68a00);
            padding: 12px 30px;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            text-align: center;
        }
        
        .back-to-dashboard:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Calendar Container */
        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 20px auto;
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
            transition: var(--transition);
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
        
        /* Continuous event blocks */
        .day-cell.event-start {
            border-top-left-radius: 15px;
            border-bottom-left-radius: 15px;
            background-color: var(--event-color) !important;
        }
        
        .day-cell.event-middle {
            background-color: var(--event-color) !important;
        }
        
        .day-cell.event-end {
            border-top-right-radius: 15px;
            border-bottom-right-radius: 15px;
            background-color: var(--event-color) !important;
        }
        
        /* Improved tooltip styling */
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
        
        /* Added styling for course code */
        .course-code {
            font-weight: bold;
            color: #003366;
        }
        
        /* Added styling for event tables */
        .course-table th:nth-child(1),
        .course-table td:nth-child(1) {
            width: 120px;
        }

        /* Event Form */
        .form-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
        }

        .form-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #003366;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1dce9;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #0066cc;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .btn-danger {
            background: linear-gradient(to right, #ff4d4d, #cc0000);
        }

        .btn-warning {
            background: linear-gradient(to right, #ff9900, #e68a00);
        }
        
        .btn-info {
            background: linear-gradient(to right, #17a2b8, #138496);
        }
        
        .btn-secondary {
            background: linear-gradient(to right, #6c757d, #5a6268);
        }
        
        .print-btn {
            background: linear-gradient(to right, #17a2b8, #138496);
            padding: 12px 30px;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            margin: 0 auto;
            text-align: center;
        }
        
        .print-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Email Logs */
        .email-logs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
        }

        .logs-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .log-table th, .log-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e9ff;
        }

        .log-table th {
            background-color: #f0f7ff;
            color: #003366;
            font-weight: 600;
        }

        .log-table tr:hover {
            background-color: #f8fbff;
        }

        .status-sent {
            color: #28a745;
            font-weight: 600;
        }

        .status-failed {
            color: #dc3545;
            font-weight: 600;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }

        /* LIGHT THEME MODALS */
        .modal-content.light-theme {
            background: white;
            color: #333;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            padding: 0;
            margin: 30px auto;
            max-width: 800px;
            width: 90%;
        }

        .modal-content.light-theme .modal-header-light {
            background: #f0f7ff; /* light blue */
            color: #003366;
            padding: 20px;
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
            border-bottom: 1px solid #d1dce9;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .modal-content.light-theme .modal-body {
            padding: 25px;
            background: white;
            color: #333;
        }

        .modal-content.light-theme .modal-footer {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: right;
            border-top: 1px solid #e0e9ff;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .modal-content.light-theme .form-control {
            background: white;
            color: #333;
            border: 1px solid #d1dce9;
        }

        .modal-content.light-theme .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .modal-content.light-theme label {
            color: #003366;
        }

        .modal-content.light-theme input[type="radio"] + label {
            color: #333;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Course Details Table */
        .course-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
        }

        .course-table-title {
            font-size: 1.8rem;
            color: #002147;
            margin-bottom: 25px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e9ff;
        }

        .course-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .course-table th, .course-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e9ff;
        }

        .course-table th {
            background-color: #f0f7ff;
            color: #003366;
            font-weight: 600;
        }

        .course-table tr:hover {
            background-color: #f8fbff;
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

        /* Updated status classes */
        .status-completed { color: #6c757d; }
        .status-active { color: #28a745; font-weight: 600; }
        .status-upcoming { color: #17a2b8; }
        .status-cancelled { color: #dc3545; font-weight: 600; } /* Added for cancelled status */

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .action-buttons .btn {
            padding: 6px 10px;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            margin: 0;
                padding: 0;
            }
            .printable-section, .printable-section * {
                visibility: visible;
            }
            .printable-section {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                padding: 20px;
                background: white;
            }
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #003366;
            }
            .print-title {
                font-size: 24px;
                color: #002147;
                margin-bottom: 10px;
            }
            .print-subtitle {
                font-size: 18px;
                color: #0066cc;
                margin-bottom: 20px;
            }
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .print-table th {
                background-color: #003366;
                color: white;
                padding: 12px;
                text-align: left;
                border-bottom: 2px solid #003366;
            }
            .print-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #e0e9ff;
            }
            .print-table tr:nth-child(even) {
                background-color: #f8fbff;
            }
            .print-footer {
                margin-top: 30px;
                text-align: center;
                font-style: italic;
                color: #666;
            }
        }

        /* Print Section Styling */
        .printable-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
            max-width: 1000px;
        }

        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #003366;
        }

        .print-title {
            font-size: 24px;
            color: #002147;
            margin-bottom: 10px;
        }

        .print-subtitle {
            font-size: 18px;
            color: #0066cc;
            margin-bottom: 20px;
        }

        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .print-table th {
            background-color: #003366;
            color: white;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #003366;
        }

        .print-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e9ff;
        }

        .print-table tr:nth-child(even) {
            background-color: #f8fbff;
        }

        .print-footer {
            margin-top: 30px;
            text-align: center;
            font-style: italic;
            color: #666;
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

        /* Developer Contact Modal */
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
        
        .call-btn { background: #28a745; }
        .sms-btn { background: #17a2b8; }
        .whatsapp-btn { background: #25D366; }
        .email-btn { background: #dc3545; }

        /* PRINT PREVIEW STYLES */
        .print-preview-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            padding: 30px;
            margin: 30px auto;
            max-width: 1000px;
        }

        .print-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #003366;
        }

        .print-preview-header img {
            height: 70px;
        }

        .print-preview-title {
            text-align: center;
        }

        .print-preview-title h1 {
            margin: 0;
            font-size: 22pt;
            color: #003366;
        }

        .print-preview-title h2 {
            margin: 5px 0 0 0;
            font-size: 18pt;
            font-weight: normal;
            color: #0066cc;
        }

        .print-preview-metadata {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 11pt;
        }

        .print-preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 20px;
        }

        .print-preview-table th {
            background-color: #003366;
            color: white;
            padding: 10px;
            border: 1px solid #ddd;
        }

        .print-preview-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }

        .print-preview-table tbody tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .print-preview-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10pt;
            color: #666;
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
                <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo" style="height: 90px;">
            </div>
        </div>
        
        <div class="nav-container">
            <div class="nav-content">
                <div class="nav-title">Training Calendar Management | NCRB</div>
                <div class="admin-info-container">
                    <div class="user-avatar">
                        <?= strtoupper(substr($username, 0, 1)) ?>
                    </div>
                    <div class="user-name">
                        <?= $username ?>
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
            <h2>Training Program Management</h2>
            <p>Welcome, <strong><?= $username ?></strong>. Create, manage, and monitor all training events from this centralized calendar.</p>
        </div>
        
        <!-- Alert Messages -->
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="current">Current Year Calendar</button>
            <button class="tab-btn" data-tab="next">Next Year Calendar</button>
            <button class="tab-btn" data-tab="add">Add New Course</button>
            <button class="tab-btn" data-tab="edit">Edit Courses</button>
            <button class="tab-btn" data-tab="logs">Email Logs</button>
            <button class="tab-btn" data-tab="print">Print Courses</button>
        </div>
        
        <!-- Current Year Calendar -->
        <div class="tab-content active" id="current-tab">
            
            <!-- Course Details Table -->
            <div class="course-table-container">
                <h2 class="course-table-title">Training Programs for <?= $currentFinancialYear ?></h2>
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
                        <?php if(count($currentYearEvents) > 0): ?>
                            <?php foreach($currentYearEvents as $event): ?>
                                <tr>
                                    <td class="course-code"><?= htmlspecialchars($event['course_code']) ?></td>
                                    <td><?= htmlspecialchars($event['course_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($event['start_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($event['end_date'])) ?></td>
                                    <td><?= $event['duration'] ?> days</td>
                                    <td><?= htmlspecialchars($event['objectives']) ?></td>
                                    <td><?= htmlspecialchars($event['eligibility']) ?></td>
                                    <td><?= htmlspecialchars($event['location']) ?></td>
                                    <td class="status-<?= strtolower($event['display_status']) ?>"><?= $event['display_status'] ?></td>
                                    <td>
                                        <span class="color-preview" style="background: <?= $event['color'] ?>"></span>
                                        <?= $colorMap[$event['color']] ?? $event['color'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center;">No training programs scheduled for this financial year</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="calendar-container">
                <div class="year-selector">
                    <div class="current-year" id="current-financial-year"><?= $currentFinancialYear ?></div>
                </div>
                
                <div class="calendar-grid" id="calendar-grid-current">
                    <!-- Calendar will be generated by JavaScript -->
                </div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Next Year Calendar -->
        <div class="tab-content" id="next-tab">
            
            <!-- Course Details Table for Next Year -->
            <div class="course-table-container">
                <h2 class="course-table-title">Training Programs for <?= $nextFinancialYear ?></h2>
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
                        <?php if(count($nextYearEvents) > 0): ?>
                            <?php foreach($nextYearEvents as $event): ?>
                                <tr>
                                    <td class="course-code"><?= htmlspecialchars($event['course_code']) ?></td>
                                    <td><?= htmlspecialchars($event['course_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($event['start_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($event['end_date'])) ?></td>
                                    <td><?= $event['duration'] ?> days</td>
                                    <td><?= htmlspecialchars($event['objectives']) ?></td>
                                    <td><?= htmlspecialchars($event['eligibility']) ?></td>
                                    <td><?= htmlspecialchars($event['location']) ?></td>
                                    <td class="status-<?= strtolower($event['display_status']) ?>"><?= $event['display_status'] ?></td>
                                    <td>
                                        <span class="color-preview" style="background: <?= $event['color'] ?>"></span>
                                        <?= $colorMap[$event['color']] ?? $event['color'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center;">No training programs scheduled for next financial year</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="calendar-container">
                <div class="year-selector">
                    <div class="current-year" id="next-financial-year"><?= $nextFinancialYear ?></div>
                </div>
                
                <div class="calendar-grid" id="calendar-grid-next">
                    <!-- Calendar will be generated by JavaScript -->
                </div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Add Event Form -->
        <div class="tab-content" id="add-tab">
            <div class="form-container">
                <h2 class="form-title">Create New Course</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="course_code">Course Code</label>
                                <input type="text" id="course_code" name="course_code" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="course_name">Course Name</label>
                                <input type="text" id="course_name" name="course_name" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_name_hindi">Course Name (Hindi)</label>
                        <input type="text" id="course_name_hindi" name="course_name_hindi" class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duration (days)</label>
                        <input type="number" id="duration" name="duration" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="objectives">Objectives</label>
                        <input type="text" id="objectives" name="objectives" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="eligibility">Eligibility</label>
                        <textarea id="eligibility" name="eligibility" class="form-control" placeholder="Specify eligibility requirements" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" value="NCRB Training Center">
                    </div>

                    <div class="form-group">
                        <label for="color">Event Color</label>
                        <select id="color" name="color" class="form-control">
                            <option value="#ff6b6b" style="background-color:#ff6b6b;color:white;">Red</option>
                            <option value="#4da6ff" style="background-color:#4da6ff;color:white;">Blue</option>
                            <option value="#6bff91" style="background-color:#6bff91;color:black;">Green</option>
                            <option value="#ffd96b" style="background-color:#ffd96b;color:black;">Yellow</option>
                            <option value="#b96bff" style="background-color:#b96bff;color:white;">Purple</option>
                            <option value="#ff9e6b" style="background-color:#ff9e6b;color:black;">Orange</option>
                            <option value="#ff6bc9" style="background-color:#ff6bc9;color:white;">Pink</option>
                            <option value="#6bf0ff" style="background-color:#6bf0ff;color:black;">Cyan</option>
                            <option value="#6b8cff" style="background-color:#6b8cff;color:white;">Royal Blue</option>
                            <option value="#6bffd0" style="background-color:#6bffd0;color:black;">Aqua</option>
                            <option value="#d0ff6b" style="background-color:#d0ff6b;color:black;">Lime</option>
                            <option value="#ffb96b" style="background-color:#ffb96b;color:black;">Light Orange</option>
                            <option value="#c56bff" style="background-color:#c56bff;color:white;">Lavender</option>
                            <option value="#ff6b8b" style="background-color:#ff6b8b;color:white;">Rose</option>
                            <option value="#6bff6f" style="background-color:#6bff6f;color:black;">Mint</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_event" class="btn btn-block">Create Training Event</button>
                </form>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Edit Courses Tab -->
        <div class="tab-content" id="edit-tab">
            <div class="course-table-container">
                <h2 class="course-table-title">All Training Programs</h2>
                <table class="course-table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Course Name (Hindi)</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                            <th>Color</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($allEvents) > 0): ?>
                            <?php foreach($allEvents as $event): 
                                $is_completed_or_cancelled = ($event['display_status'] === 'Completed' || $event['display_status'] === 'Cancelled');
                            ?>
                                <tr>
                                    <td class="course-code"><?= htmlspecialchars($event['course_code']) ?></td>
                                    <td><?= htmlspecialchars($event['course_name']) ?></td>
                                    <td><?= htmlspecialchars($event['course_name_hindi']) ?></td>
                                    <td><?= date('d M Y', strtotime($event['start_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($event['end_date'])) ?></td>
                                    <td><?= $event['duration'] ?> days</td>
                                    <td>
                                        <span class="color-preview" style="background: <?= $event['color'] ?>"></span>
                                        <?= $colorMap[$event['color']] ?? $event['color'] ?>
                                    </td>
                                    <td class="status-<?= strtolower($event['display_status']) ?>"><?= $event['display_status'] ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info edit-btn" data-event-id="<?= $event['id'] ?>" 
                                                <?= $is_completed_or_cancelled ? 'disabled' : '' ?>
                                                title="<?= $is_completed_or_cancelled ? 'Event completed/cancelled - cannot edit' : 'Edit' ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger cancel-btn" data-event-id="<?= $event['id'] ?>" 
                                                <?= $is_completed_or_cancelled ? 'disabled' : '' ?>
                                                title="<?= $is_completed_or_cancelled ? 'Event completed/cancelled - cannot cancel' : 'Cancel' ?>">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                <button type="submit" name="send_reminder" class="btn btn-warning" 
                                                    <?= $is_completed_or_cancelled ? 'disabled' : '' ?>
                                                    title="<?= $is_completed_or_cancelled ? 'Event completed/cancelled - cannot send reminders' : 'Send Immediate Reminder' ?>">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                <button type="submit" name="toggle_reminders" class="btn btn-<?= $event['reminders_enabled'] ? 'warning' : 'secondary' ?>" 
                                                    <?= $is_completed_or_cancelled ? 'disabled' : '' ?>
                                                    title="<?= $is_completed_or_cancelled ? 'Event completed/cancelled - reminders disabled' : ($event['reminders_enabled'] ? 'Stop Automated Reminders' : 'Enable Automated Reminders') ?>">
                                                    <i class="fas fa-<?= $event['reminders_enabled'] ? 'pause' : 'ban' ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center;">No training programs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Email Logs -->
        <div class="tab-content" id="logs-tab">
            <div class="course-table-container">
                <h2 class="logs-title">Email Notification History</h2>
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Email Type</th>
                            <th>Sent At</th>
                            <th>Recipients</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($emailLogs) > 0): ?>
                            <?php foreach($emailLogs as $log): ?>
                                <tr>
                                    <td><?= $log['course_code'] ?></td>
                                    <td><?= $log['course_name'] ?></td>
                                    <td><?= ucfirst($log['email_type']) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($log['sent_at'])) ?></td>
                                    <td><?= $log['recipient_count'] ?> recipients</td>
                                    <td class="status-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No email logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Print Courses Section -->
        <div class="tab-content" id="print-tab">
            <div class="course-table-container">
                <h2 class="form-title">Filter Courses for Printing</h2>
                <form method="GET">
                    <input type="hidden" name="tab" value="print">
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="print_start_date">Start Date</label>
                                <input type="date" id="print_start_date" name="print_start_date" class="form-control" 
                                       value="<?= $_GET['print_start_date'] ?? '' ?>" 
                                       placeholder="Start Date"
                                       class="date-input">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="print_end_date">End Date</label>
                                <input type="date" id="print_end_date" name="print_end_date" class="form-control" 
                                       value="<?= $_GET['print_end_date'] ?? '' ?>" 
                                       placeholder="End Date"
                                       class="date-input">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="print_course">Course</label>
                        <select id="print_course" name="print_course" class="form-control">
                            <option value="">-- All Courses --</option>
                            <?php foreach ($allCourses as $course): ?>
                                <option value="<?= $course ?>" <?= ($_GET['print_course'] ?? '') === $course ? 'selected' : '' ?>>
                                    <?= $course ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <button type="submit" class="btn btn-block">Apply Filters</button>
                        </div>
                        <div class="form-col">
                            <a href="admin-calendar.php?tab=print" class="btn btn-secondary btn-block">Reset Filters</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Printable Course List -->
            <div class="print-preview-container">
                <div class="print-preview-header">
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" alt="MHA Logo">
                    <div class="print-preview-title">
                        <h1>National Crime Records Bureau</h1>
                        <h2>Training Programs Report</h2>
                    </div>
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                </div>
                
                <?php 
                $filters = [];
                if (!empty($_GET['print_start_date'])) $filters[] = 'Start Date: ' . date('d M Y', strtotime($_GET['print_start_date']));
                if (!empty($_GET['print_end_date'])) $filters[] = 'End Date: ' . date('d M Y', strtotime($_GET['print_end_date']));
                if (!empty($_GET['print_course'])) $filters[] = 'Course: ' . htmlspecialchars($_GET['print_course']);
                ?>
                
                <?php if (!empty($filters)): ?>
                    <div class="print-preview-metadata">
                        <strong>Filters Applied:</strong> <?= implode(' | ', $filters) ?>
                    </div>
                <?php endif; ?>
                
                <table class="print-preview-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration (days)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($printEvents) > 0): ?>
                            <?php $sno = 1; ?>
                            <?php foreach($printEvents as $event): ?>
                                <tr>
                                    <td><?= $sno++ ?></td>
                                    <td><?= htmlspecialchars($event['course_code']) ?></td>
                                    <td><?= htmlspecialchars($event['course_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($event['start_date'])) ?></td>
                                    <td><?= date('d M Y', strtotime($event['end_date'])) ?></td>
                                    <td><?= $event['duration'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No training programs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="print-preview-footer">
                    <p>National Crime Records Bureau<br>
                    Ministry of Home Affairs, Government of India</p>
                    <p>Generated by NCRB Training Calendar Management System</p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button class="print-btn" onclick="printCourseList()">
                    <i class="fas fa-print"></i> Print Course List
                </button>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin-dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Cancel Event Modal (Light Theme) -->
    <div class="modal" id="cancelModal">
        <div class="modal-content light-theme">
            <div class="modal-header-light">Confirm Event Cancellation</div>
            <form id="cancelForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="cancelEventId">
                    <div class="form-group">
                        <label>Reason for cancellation</label>
                        <div>
                            <label><input type="radio" name="reason" value="Insufficient registrations" required> Insufficient registrations</label>
                        </div>
                        <div>
                            <label><input type="radio" name="reason" value="Resource unavailability"> Resource unavailability</label>
                        </div>
                        <div>
                            <label><input type="radio" name="reason" value="other"> Other (specify below)</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <textarea name="custom_reason" id="customReason" class="form-control" placeholder="Please specify reason..." style="display: none; margin-top: 10px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" id="cancelCancel">Cancel</button>
                    <button type="submit" name="cancel_event" class="btn btn-danger">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Event Modal (Light Theme) -->
    <div class="modal" id="editModal">
        <div class="modal-content light-theme">
            <div class="modal-header-light">Edit Training Course</div>
            <form id="editForm" method="POST">
                <input type="hidden" name="event_id" id="editEventId">
                <input type="hidden" name="update_event" value="1">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="edit_course_code">Course Code</label>
                                <input type="text" id="edit_course_code" name="course_code" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="edit_course_name">Course Name</label>
                                <input type="text" id="edit_course_name" name="course_name" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_course_name_hindi">Course Name (Hindi)</label>
                        <input type="text" id="edit_course_name_hindi" name="course_name_hindi" class="form-control">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="edit_start_date">Start Date</label>
                                <input type="date" id="edit_start_date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="edit_end_date">End Date</label>
                                <input type="date" id="edit_end_date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_duration">Duration (days)</label>
                        <input type="number" id="edit_duration" name="duration" class="form-control" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_objectives">Objectives</label>
                        <input type="text" id="edit_objectives" name="objectives" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_eligibility">Eligibility</label>
                        <textarea id="edit_eligibility" name="eligibility" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <input type="text" id="edit_location" name="location" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_color">Event Color</label>
                        <select id="edit_color" name="color" class="form-control">
                            <option value="#ff6b6b" style="background-color:#ff6b6b;color:white;">Red</option>
                            <option value="#4da6ff" style="background-color:#4da6ff;color:white;">Blue</option>
                            <option value="#6bff91" style="background-color:#6bff91;color:black;">Green</option>
                            <option value="#ffd96b" style="background-color:#ffd96b;color:black;">Yellow</option>
                            <option value="#b96bff" style="background-color:#b96bff;color:white;">Purple</option>
                            <option value="#ff9e6b" style="background-color:#ff9e6b;color:black;">Orange</option>
                            <option value="#ff6bc9" style="background-color:#ff6bc9;color:white;">Pink</option>
                            <option value="#6bf0ff" style="background-color:#6bf0ff;color:black;">Cyan</option>
                            <option value="#6b8cff" style="background-color:#6b8cff;color:white;">Royal Blue</option>
                            <option value="#6bffd0" style="background-color:#6bffd0;color:black;">Aqua</option>
                            <option value="#d0ff6b" style="background-color:#d0ff6b;color:black;">Lime</option>
                            <option value="#ffb96b" style="background-color:#ffb96b;color:black;">Light Orange</option>
                            <option value="#c56bff" style="background-color:#c56bff;color:white;">Lavender</option>
                            <option value="#ff6b8b" style="background-color:#ff6b8b;color:white;">Rose</option>
                            <option value="#6bff6f" style="background-color:#6bff6f;color:black;">Mint</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-block">Update Training Event</button>
                </div>
            </form>
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
        
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                document.getElementById(`${btn.dataset.tab}-tab`).classList.add('active');
            });
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
        
        // Cancel event modal
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const eventId = btn.dataset.eventId;
                document.getElementById('cancelEventId').value = eventId;
                openModal('cancelModal');
            });
        });
        
        // Edit event modal
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const eventId = btn.dataset.eventId;
                
                // Find the event data
                const event = <?= json_encode($allEvents) ?>.find(e => e.id == eventId);
                
                if (event) {
                    // Populate form fields
                    document.getElementById('editEventId').value = event.id;
                    document.getElementById('edit_course_code').value = event.course_code;
                    document.getElementById('edit_course_name').value = event.course_name;
                    document.getElementById('edit_course_name_hindi').value = event.course_name_hindi;
                    document.getElementById('edit_start_date').value = event.start_date;
                    document.getElementById('edit_end_date').value = event.end_date;
                    document.getElementById('edit_duration').value = event.duration;
                    document.getElementById('edit_objectives').value = event.objectives;
                    document.getElementById('edit_eligibility').value = event.eligibility;
                    document.getElementById('edit_location').value = event.location;
                    document.getElementById('edit_color').value = event.color;
                    
                    openModal('editModal');
                }
            });
        });
        
        // Show custom reason textarea
        document.querySelectorAll('input[name="reason"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const customReason = document.getElementById('customReason');
                customReason.style.display = radio.value === 'other' ? 'block' : 'none';
            });
        });
        
        // Cancel delete button
        document.getElementById('cancelCancel').addEventListener('click', () => {
            closeModal('cancelModal');
        });
        
        // Calendar data from PHP
        const trainingEvents = <?= json_encode($allEvents) ?>;
        
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

        // Financial year data
        const financialYears = {
            current: "<?= $currentFinancialYear ?>",
            next: "<?= $nextFinancialYear ?>"
        };
        
        // Calendar functionality
        function generateCalendar(financialYear, targetId) {
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
            
            const calendarGrid = document.getElementById(targetId);
            calendarGrid.innerHTML = '';
            
            months.forEach(month => {
                // Create month container
                const monthContainer = document.createElement('div');
                monthContainer.className = 'month-container';
                
                // Month header
                const monthHeader = document.createElement('div');
                monthHeader.className = 'month-header';
                monthHeader.textContent = `${month.name} ${month.startYear}`;
                
                // Month grid
                const monthGrid = document.createElement('div');
                monthGrid.className = 'month-grid';
                
                // Add day headers
                dayNames.forEach(day => {
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'day-header';
                    dayHeader.textContent = day;
                    monthGrid.appendChild(dayHeader);
                });
                
                // Get first day of the month
                const firstDay = new Date(month.startYear, month.monthIndex, 1).getDay();
                
                // Add empty cells for days before the first day
                for (let i = 0; i < firstDay; i++) {
                    const emptyCell = document.createElement('div');
                    emptyCell.className = 'day-cell empty-cell';
                    monthGrid.appendChild(emptyCell);
                }
                
                // Add days
                for (let day = 1; day <= month.days; day++) {
                    const dayCell = document.createElement('div');
                    dayCell.className = 'day-cell';
                    dayCell.textContent = day;
                    
                    // Format date for comparison (YYYY-MM-DD)
                    const dateStr = `${month.startYear}-${String(month.monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    
                    // Check if date has event
                    if (eventDates[dateStr]) {
                        const events = eventDates[dateStr];
                        // Use the first event's color for the indicator
                        const event = events[0];
                        
                        // Set color based on event status
                        if (event.status === 'cancelled') {
                            dayCell.style.setProperty('--event-color', '#cccccc'); // Gray for cancelled
                        } else {
                            dayCell.style.setProperty('--event-color', event.color);
                        }
                        
                        // Determine event position in month
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
                            tooltipContent += `
                                <div class="tooltip-event">
                                    <div><strong>${ev.course_code}</strong> (${ev.display_status})</div>
                                    <div>${ev.course_name}</div>
                                    <div>${ev.location}</div>
                                </div>
                            `;
                        });
                        
                        // Set tooltip attribute
                        dayCell.setAttribute('data-tooltip', tooltipContent);
                    }
                    
                    monthGrid.appendChild(dayCell);
                }
                
                monthContainer.appendChild(monthHeader);
                monthContainer.appendChild(monthGrid);
                calendarGrid.appendChild(monthContainer);
            });
            
            // Add CSS tooltip functionality
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            document.body.appendChild(tooltip);
            
            document.querySelectorAll('.day-cell[data-tooltip]').forEach(cell => {
                cell.addEventListener('mouseenter', (e) => {
                    const rect = cell.getBoundingClientRect();
                    tooltip.innerHTML = cell.getAttribute('data-tooltip');
                    tooltip.style.display = 'block';
                    tooltip.style.left = `${rect.left + window.scrollX}px`;
                    tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
                });
                
                cell.addEventListener('mouseleave', () => {
                    tooltip.style.display = 'none';
                });
            });
        }
        
        // PROFESSIONAL PRINT FUNCTION
        function printCourseList() {
            // Create a print window
            const printWindow = window.open('', '', 'width=1000,height=700');
            
            // Start building HTML content
            let printContent = `
            <html>
            <head>
                <title>NCRB Training Programs Report</title>
                <style>
                    body {
                        font-family: "Times New Roman", Times, serif;
                        margin: 1cm;
                        color: #000;
                        background: #fff;
                    }
                    .print-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        padding-bottom: 20px;
                        border-bottom: 2px solid #003366;
                    }
                    .print-header img {
                        height: 70px;
                    }
                    .print-title {
                        text-align: center;
                    }
                    .print-title h1 {
                        margin: 0;
                        font-size: 22pt;
                        color: #003366;
                    }
                    .print-title h2 {
                        margin: 5px 0 0 0;
                        font-size: 18pt;
                        font-weight: normal;
                        color: #0066cc;
                    }
                    .metadata {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 20px;
                        font-size: 11pt;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 10pt;
                        margin-top: 20px;
                    }
                    th {
                        background-color: #003366;
                        color: white;
                        padding: 10px;
                        border-bottom: 2px solid #003366;
                    }
                    td {
                        padding: 10px 12px;
                        border-bottom: 1px solid #e0e9ff;
                    }
                    tbody tr:nth-child(even) {
                        background-color: #f8fbff;
                    }
                    .print-footer {
                        margin-top: 30px;
                        text-align: center;
                        font-style: italic;
                        color: #666;
                    }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" alt="MHA Logo">
                    <div class="print-title">
                        <h1>National Crime Records Bureau</h1>
                        <h2>Training Programs Report</h2>
                    </div>
                    <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" alt="NCRB Logo">
                </div>
                <div class="metadata">
                    <div><strong>Generated:</strong> ${new Date().toLocaleString()}</div>
                    <div><strong>Admin:</strong> <?= $username ?></div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            // Add table rows from printEventsData
            const printEventsData = <?= json_encode($printEvents) ?>;
            printEventsData.forEach((event, index) => {
                printContent += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${event.course_code}</td>
                        <td>${event.course_name}</td>
                        <td>${new Date(event.start_date).toLocaleDateString('en-GB')}</td>
                        <td>${new Date(event.end_date).toLocaleDateString('en-GB')}</td>
                        <td>${event.duration} days</td>
                    </tr>`;
            });
            
            // Add footer
            printContent += `
                    </tbody>
                </table>
                <div class="print-footer">
                    <p>National Crime Records Bureau<br>
                    Ministry of Home Affairs, Government of India</p>
                    <p>Generated by NCRB Training Calendar Management System</p>
                </div>
            </body>
            </html>`;
            
            // Write content to print window
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Trigger print after content loads
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }, 500);
        }

        // Generate calendars on page load
        document.addEventListener('DOMContentLoaded', function() {
            generateCalendar(financialYears.current, 'calendar-grid-current');
            generateCalendar(financialYears.next, 'calendar-grid-next');
            
            // Header shadow on scroll
            const header = document.querySelector('.fixed-header');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 10) {
                    header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.2)';
                } else {
                    header.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
                }
            });
        });
    </script>
</body>
</html>