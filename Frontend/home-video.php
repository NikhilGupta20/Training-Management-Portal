<?php
// home-video.php
// Database connection
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

// Create program_videos table if it doesn't exist (with updated structure)
$sql = "CREATE TABLE IF NOT EXISTS program_videos (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) NOT NULL,
    use_for ENUM('home','course') NOT NULL DEFAULT 'home',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    // Error creating table - handle appropriately
}

// Fetch home page videos only
$videos = [];
$result = $conn->query("SELECT * FROM program_videos WHERE use_for = 'home' ORDER BY uploaded_at DESC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $videos[] = $row;
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
    <title>Training Videos - National Crime Records Bureau</title>
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
        /* Consistent Theming Variables */
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

        /* Back to Dashboard button styling */
        .back-dashboard-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--accent);
            padding: 8px 20px;
            border-radius: 4px;
            font-weight: 600;
            transition: var(--transition);
            color: var(--white);
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .back-dashboard-btn:hover {
            background: #e68a00;
            transform: translateY(-2px);
            text-decoration: none;
        }

        /* Dashboard Section */
        .dashboard {
            max-width: 1400px;
            margin: 0 auto 50px;
            padding: 0 20px;
            margin-top: 30px;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeIn 0.8s ease-out;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            position: relative;
            padding-bottom: 15px;
        }

        .dashboard-header h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 25%;
            right: 25%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            border-radius: 2px;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Gallery Styles */
        .gallery-container {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            padding: 30px;
            margin-top: 20px;
            min-height: 300px;
        }

        .gallery-scroll-container {
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            padding: 20px 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--primary) var(--light-gray);
        }

        .gallery-scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        .gallery-scroll-container::-webkit-scrollbar-track {
            background: var(--light-gray);
            border-radius: 10px;
        }

        .gallery-scroll-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        .gallery-item {
            display: inline-block;
            width: 300px;
            height: 350px;
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            margin: 0 15px;
            vertical-align: top;
            white-space: normal;
            cursor: pointer;
            transition: var(--transition);
        }

        .gallery-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .gallery-video-container {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: #000;
        }

        .gallery-video-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: var(--transition);
        }

        .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
            z-index: 2;
        }

        .gallery-item:hover .play-icon {
            color: var(--accent);
            transform: translate(-50%, -50%) scale(1.1);
        }

        .gallery-overlay {
            transition: var(--transition);
            opacity: 0;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 51, 102, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            text-align: center;
            z-index: 1;
        }

        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }

        .overlay-text {
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .overlay-btn {
            background: transparent;
            border: 2px solid var(--white);
            color: var(--white);
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .overlay-btn:hover {
            background: var(--white);
            color: var(--primary);
        }

        .gallery-info {
            padding: 20px;
            background: var(--white);
        }

        .gallery-caption {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 500;
            height: 45px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .gallery-date {
            font-size: 0.9rem;
            color: var(--gray);
            font-style: italic;
        }

        /* Video Modal */
        .video-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.5s ease;
        }

        .video-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .video-modal-content {
            position: relative;
            width: 90%;
            max-width: 800px;
        }

        .video-player {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 8px;
        }

        .video-close {
            position: absolute;
            top: 40px;
            right: 40px;
            color: var(--white);
            font-size: 2.5rem;
            cursor: pointer;
            background: none;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            transition: var(--transition);
            z-index: 10;
        }

        .video-close:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }

        /* Professional Footer (Consistent with home-page.php) */
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
        
        /* Developer Contact Link Styling */
        .copyright a#developer-contact-link {
            font-weight: normal;
            color: inherit;
            text-decoration: none;
        }

        .copyright a#developer-contact-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }
        
        .current-date {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 10px;
            color: var(--accent);
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
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: #2c3e50;
            color: #ecf0f1;
            border: 1px solid #1a252f;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
            position: relative;
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
        
        /* Developer Contact */
        .developer-contact {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .developer-contact h3 {
            color: #f39c12;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .contact-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
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

        /* Responsive Design */
        @media (max-width: 992px) {
            body {
                padding-top: 170px;
            }
            
            .dashboard-header h1 {
                font-size: 2.2rem;
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
            
            .gallery-item {
                width: 280px;
                height: 330px;
            }
            
            .gallery-video-container {
                height: 200px;
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
            
            .dashboard-header h1 {
                font-size: 1.8rem;
            }
            
            .gallery-item {
                width: 260px;
                height: 310px;
                margin: 0 10px;
            }
            
            .gallery-video-container {
                height: 180px;
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
                <!-- Back to Dashboard button -->
                <a href="home-page.php" class="back-dashboard-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>

    <!-- Dashboard Section -->
    <div class="dashboard">
        <div class="dashboard-header">
            <h1>Training Video Gallery</h1>
            <p>Explore videos from our training programs and workshops</p>
        </div>
        
        <div class="gallery-container">
            <?php if (empty($videos)): ?>
                <div class="no-videos" style="text-align: center; padding: 60px; background: var(--white); border-radius: 15px; box-shadow: var(--shadow);">
                    <i class="fas fa-video" style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--primary); margin-bottom: 10px;">No videos available</h3>
                    <p style="color: var(--gray);">Check back later for training program videos</p>
                </div>
            <?php else: ?>
                <div class="gallery-scroll-container" id="scrollContainer">
                    <?php foreach ($videos as $index => $video): ?>
                        <div class="gallery-item" data-index="<?php echo $index; ?>" 
                             data-src="<?php echo $video['video_path']; ?>"
                             data-caption="<?php echo htmlspecialchars($video['caption']); ?>">
                            <div class="gallery-video-container">
                                <video class="gallery-video-preview" muted loop>
                                    <source src="<?php echo $video['video_path']; ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                                <i class="fas fa-play-circle play-icon"></i>
                                <div class="gallery-overlay">
                                    <div class="overlay-text">Training Session</div>
                                    <button class="overlay-btn">Play Video</button>
                                </div>
                            </div>
                            <div class="gallery-info">
                                <div class="gallery-caption">
                                    <?php 
                                        $caption = $video['caption'];
                                        echo !empty($caption) ? htmlspecialchars($caption) : 'Professional Training Program';
                                    ?>
                                </div>
                                <div class="gallery-date">
                                    <?php echo date('M d, Y', strtotime($video['uploaded_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Modal -->
    <div class="video-modal" id="videoModal">
        <button class="video-close"><i class="fas fa-times"></i></button>
        <div class="video-modal-content">
            <video class="video-player" controls id="videoPlayer">
                <source src="" type="video/mp4">
                Your browser does not support the video tag.
            </video>
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
        
        <!-- Developer Information -->
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Video modal functionality
            const videoModal = document.getElementById('videoModal');
            const videoPlayer = document.getElementById('videoPlayer');
            const videoClose = document.querySelector('.video-close');
            
            const galleryItems = document.querySelectorAll('.gallery-item');
            
            // Play preview videos on hover
            galleryItems.forEach(item => {
                const previewVideo = item.querySelector('.gallery-video-preview');
                previewVideo.play();
                
                item.addEventListener('mouseenter', () => {
                    previewVideo.play();
                });
                
                item.addEventListener('mouseleave', () => {
                    previewVideo.pause();
                    previewVideo.currentTime = 0;
                });
            });
            
            // Open video modal
            function openVideoModal(src) {
                videoPlayer.src = src;
                videoModal.classList.add('active');
                document.body.style.overflow = 'hidden';
                videoPlayer.play();
            }
            
            // Close video modal
            function closeVideoModal() {
                videoModal.classList.remove('active');
                document.body.style.overflow = 'auto';
                videoPlayer.pause();
                videoPlayer.currentTime = 0;
            }
            
            // Event listeners
            galleryItems.forEach(item => {
                item.addEventListener('click', () => {
                    const videoSrc = item.getAttribute('data-src');
                    openVideoModal(videoSrc);
                });
            });
            
            videoClose.addEventListener('click', closeVideoModal);
            
            // Close modal when clicking outside
            videoModal.addEventListener('click', (e) => {
                if (e.target === videoModal) {
                    closeVideoModal();
                }
            });
            
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (videoModal.classList.contains('active') && e.key === 'Escape') {
                    closeVideoModal();
                }
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
            const developerModal = document.getElementById('developerContactModal');
            const developerLink = document.getElementById('developer-contact-link');
            const closeModalButtons = document.querySelectorAll('.close-modal');
            
            // Open developer modal
            developerLink.addEventListener('click', (e) => {
                e.preventDefault();
                developerModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
            
            // Close modal
            function closeModal() {
                developerModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            
            // Close modal buttons
            closeModalButtons.forEach(button => {
                button.addEventListener('click', closeModal);
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target === developerModal) {
                    closeModal();
                }
            });
            
            // Close with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && developerModal.style.display === 'flex') {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>