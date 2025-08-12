<?php
session_start();

// Check if participant is logged in
if (!isset($_SESSION['participant_logged_in'])) {
    header("Location: participant-login.php");
    exit;
}

// Database connection
$host = 'localhost';
$db   = 'ncrb_training';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// Fetch participant details
$userid = $_SESSION['participant_userid'];
$stmt = $pdo->prepare("SELECT * FROM registration WHERE userid = ?");
$stmt->execute([$userid]);
$participant = $stmt->fetch();

if (!$participant) {
    session_unset();
    session_destroy();
    header("Location: participant-login.php");
    exit;
}

// Extract user details
$username = htmlspecialchars($participant['userid'] ?? '');
$participant_name = htmlspecialchars($participant['participant_name'] ?? $username);
$course_name = htmlspecialchars($participant['course_name'] ?? 'Not specified');

// Get course code from training_events table
$course_code = '';
$stmt = $pdo->prepare("SELECT course_code FROM training_events WHERE course_name = ? LIMIT 1");
$stmt->execute([$course_name]);
$course = $stmt->fetch();
if ($course) {
    $course_code = $course['course_code'];
}

// Fetch images for this participant's course
$images = [];
if ($course_code) {
    $imagesQuery = "SELECT pi.*, te.course_name, te.start_date, te.end_date 
                   FROM program_images pi 
                   LEFT JOIN training_events te ON pi.event_id = te.id 
                   WHERE pi.use_for = 'course'
                     AND pi.course_code = :course_code
                   ORDER BY pi.uploaded_at DESC";
                   
    $imagesStmt = $pdo->prepare($imagesQuery);
    $imagesStmt->execute([':course_code' => $course_code]);
    $images = $imagesStmt->fetchAll();
}

// Fetch videos for this participant's course
$videos = [];
if ($course_code) {
    $videosQuery = "SELECT pv.*, te.course_name, te.start_date, te.end_date 
                   FROM program_videos pv 
                   LEFT JOIN training_events te ON pv.event_id = te.id 
                   WHERE pv.use_for = 'course'
                     AND pv.course_code = :course_code
                   ORDER BY pv.uploaded_at DESC";
                   
    $videosStmt = $pdo->prepare($videosQuery);
    $videosStmt->execute([':course_code' => $course_code]);
    $videos = $videosStmt->fetchAll();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Training Gallery - NCRB Training Portal</title>
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
            background-color: #f5f8fa;
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

        /* Gallery Container */
        .gallery-container {
            background: var(--white);
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
            margin: 20px auto;
            position: relative;
            overflow: hidden;
        }
        
        .gallery-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 20px;
            text-decoration: none;
            transition: all 0.2s;
            padding: 8px 15px;
            border-radius: 8px;
            background: rgba(0, 102, 204, 0.1);
        }
        
        .back-btn:hover {
            color: var(--primary);
            background: rgba(0, 102, 204, 0.2);
            transform: translateX(-3px);
        }
        
        /* Gallery Section */
        .gallery-section {
            margin-top: 40px;
        }
        
        .section-title {
            font-size: 26px;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 120px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .gallery-item {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            background: #fff;
            animation: fadeIn 0.5s ease-out;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }
        
        .gallery-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid #ccefff;
        }
        
        .gallery-video {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid #ccefff;
        }
        
        .gallery-caption {
            padding: 18px 15px;
            font-size: 16px;
            color: #334155;
            line-height: 1.5;
            min-height: 70px;
        }
        
        .gallery-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            font-size: 14px;
            color: #64748b;
        }
        
        .date-info {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .download-btn {
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .download-btn:hover {
            background: var(--secondary);
        }
        
        /* Tabs for switching between images and videos */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            background: transparent;
            border: none;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab.active {
            color: var(--primary);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }
        
        .tab:hover:not(.active) {
            color: var(--secondary);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .no-media-message {
            text-align: center;
            padding: 40px;
            background: #f0f9ff;
            color: #0c4a6e;
            border-radius: 10px;
            font-size: 18px;
        }
        
        .no-media-message i {
            font-size: 48px;
            margin-bottom: 20px;
            display: block;
            color: #3b82f6;
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
            color: var(--white);
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
        
        /* Responsive Design */
        @media (max-width: 992px) {
            body {
                padding-top: 190px;
            }
            
            .dashboard-header h1 {
                font-size: 2.2rem;
            }
            
            .gallery-container {
                padding: 30px;
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
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .gallery-actions {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
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
            
            .gallery-container {
                padding: 25px 15px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                width: 100%;
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
                <div class="nav-title">National Crime Records Bureau - Training Gallery</div>
                <div class="admin-info-container">
                    <div class="user-avatar">
                        <?php 
                            $initial = strtoupper(substr($participant_name, 0, 1));
                            echo $initial;
                        ?>
                    </div>
                    <div class="user-name">
                        <?php echo $participant_name; ?>
                    </div>
                    <a href="participant-logout.php" class="logout-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>Training Gallery</h1>
            <p>View photos and videos from your training program: <strong><?php echo $course_name; ?></strong></p>
        </div>
        
        <div class="gallery-container">
            <a href="participant-dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <!-- Tabs for Images and Videos -->
            <div class="tabs">
                <button class="tab active" data-tab="images-tab">Images</button>
                <button class="tab" data-tab="videos-tab">Videos</button>
            </div>
            
            <!-- Images Tab -->
            <div id="images-tab" class="tab-content active">
                <!-- Gallery Section for Images -->
                <div class="gallery-section">
                    <h2 class="section-title">Training Images</h2>
                    
                    <?php if (empty($images)): ?>
                        <div class="no-media-message">
                            <i class="fas fa-images"></i>
                            No images found for this course. Check back later for updates.
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid" id="galleryGrid">
                            <?php foreach ($images as $image): ?>
                                <div class="gallery-item">
                                    <img src="<?php echo $image['image_path']; ?>" alt="Program image" class="gallery-image">
                                    
                                    <div class="gallery-caption">
                                        <?php 
                                            $caption = $image['caption'];
                                            echo !empty($caption) ? htmlspecialchars($caption) : 'Training Program Image';
                                        ?>
                                    </div>
                                    <div class="gallery-actions">
                                        <div class="date-info">
                                            <i class="far fa-calendar"></i>
                                            Uploaded: <?php echo date('M d, Y', strtotime($image['uploaded_at'])); ?>
                                        </div>
                                        <a href="<?php echo $image['image_path']; ?>" download class="download-btn">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Videos Tab -->
            <div id="videos-tab" class="tab-content">
                <!-- Gallery Section for Videos -->
                <div class="gallery-section">
                    <h2 class="section-title">Training Videos</h2>
                    
                    <?php if (empty($videos)): ?>
                        <div class="no-media-message">
                            <i class="fas fa-video"></i>
                            No videos found for this course. Check back later for updates.
                        </div>
                    <?php else: ?>
                        <div class="gallery-grid" id="videoGalleryGrid">
                            <?php foreach ($videos as $video): ?>
                                <div class="gallery-item">
                                    <video class="gallery-video" controls>
                                        <source src="<?php echo $video['video_path']; ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                    
                                    <div class="gallery-caption">
                                        <?php 
                                            $caption = $video['caption'];
                                            echo !empty($caption) ? htmlspecialchars($caption) : 'Training Program Video';
                                        ?>
                                    </div>
                                    <div class="gallery-actions">
                                        <div class="date-info">
                                            <i class="far fa-calendar"></i>
                                            Uploaded: <?php echo date('M d, Y', strtotime($video['uploaded_at'])); ?>
                                        </div>
                                        <a href="<?php echo $video['video_path']; ?>" download class="download-btn">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
        // Tab switching
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
        
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

        // Modal functionality
        const developerContactLink = document.getElementById('developer-contact-link');
        const developerContactModal = document.getElementById('developerContactModal');
        const closeModal = document.querySelector('.close-modal');
        
        developerContactLink.addEventListener('click', (e) => {
            e.preventDefault();
            developerContactModal.style.display = 'flex';
        });
        
        closeModal.addEventListener('click', () => {
            developerContactModal.style.display = 'none';
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === developerContactModal) {
                developerContactModal.style.display = 'none';
            }
        });
    </script>
</body>
</html>