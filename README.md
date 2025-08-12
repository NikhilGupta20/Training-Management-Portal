# NCRB Training Management Portal

A comprehensive web-based training management system developed for the National Crime Records Bureau (NCRB) and Regional Police Computer Training Centres (RPCTCs) to streamline law enforcement training operations across India.

## 🏛️ About NCRB

The National Crime Records Bureau (NCRB) is a central agency under the Ministry of Home Affairs, Government of India, responsible for collecting, analyzing, and disseminating crime data across India. This portal modernizes their training management infrastructure to enhance efficiency and accessibility.

## 🎯 Project Overview

This portal addresses critical needs in law enforcement training management by providing a centralized, secure, and scalable platform for:
- **Training Course Management**: Comprehensive scheduling and coordination
- **Multi-Location Operations**: Support for NCRB headquarters and 4 RPCTC centers
- **End-to-End Workflow**: From nomination to certification
- **Real-time Analytics**: Data-driven insights for decision making

## ✨ Key Features

### 🔐 Multi-Role Access Control
- **NCRB Admin**: Full system management and oversight
- **RPCTC Admins**: Regional center-specific management
- **Participants**: Personal dashboard and training access

### 📅 Training Management
- Interactive training calendar with course scheduling
- Automated email notifications for course announcements
- Multi-batch course management
- Faculty assignment and management system

### 👥 Participant Management
- Online nomination and approval workflow
- Bulk participant registration capabilities
- Hostel accommodation management
- Digital certificate generation with unique IDs

### 📊 Analytics & Reporting
- Real-time dashboard with training metrics
- Comprehensive feedback collection and analysis
- Budget tracking and expense management
- Exportable reports in multiple formats (PDF)

### 🏨 Accommodation Management
- Hostel room allocation system
- Duration-based booking management
- Special requirements handling

### 📚 Learning Resources
- Course-specific material uploads
- Training gallery (photos/videos)
- Mobile-responsive resource access
- Download tracking and management

### 💰 Financial Management
- Budget allocation and tracking
- Expense recording with bill uploads
- Department-wise expenditure reports
- Cost analysis per training program

### 🎓 Assessment & Certification
- AIBE examination management
- Automated grading and result processing
- Reappear examination handling
- Digital certificate generation with blockchain-ready architecture

## 🛠️ Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| **Frontend** | HTML5, CSS3, JavaScript | Latest |
| **Backend** | PHP | 8.x |
| **Database** | MySQL | 5.7+ |
| **Server** | Apache (via XAMPP) | 2.4+ |
| **Development** | phpMyAdmin | 5.x |

## 🗄️ Database Architecture

The system uses a normalized relational database with 25+ tables including:

- **User Management**: Admin credentials, participant users, role-based access
- **Training Operations**: Course schedules, nominations, registrations
- **Resource Management**: Materials, gallery, faculty data
- **Assessment**: Feedback, ratings, examination results
- **Administration**: Budget tracking, reports, notifications

## 📋 Prerequisites

- **Operating System**: Windows 10/11, macOS, or Linux
- **PHP Runtime**: PHP 8.x with mysqli/PDO_MySQL extensions
- **Database**: MySQL 5.7+ or MariaDB equivalent
- **Web Server**: Apache 2.4+ with URL rewriting
- **Memory**: Minimum 2GB RAM (4GB+ recommended for production)

## 🚀 Installation & Setup

1. **Install XAMPP**
   ```bash
   # Download XAMPP from https://www.apachefriends.org/
   # Install and start Apache and MySQL services
   ```

2. **Clone Repository**
   ```bash
   git clone https://github.com/NikhilGupta20/Training-Management-Portal.git
   cd Training-Management-Portal
   ```

3. **Database Setup**
   ```bash
   # Open phpMyAdmin (http://localhost/phpmyadmin)
   # Create database: ncrb_training
   # Import provided SQL file or create tables as per schema
   ```

4. **Configuration**
   ```php
   // Update database connection settings
   $servername = "localhost";
   $username = "root";
   $password = "";
   $database = "ncrb_training";
   ```

5. **Deploy to XAMPP**
   ```bash
   # Copy project files to xampp/htdocs/NCRB/
   # Access via http://localhost/NCRB/home-page.php
   ```

## 📁 Project Structure

```
NCRB/
├── home-page.php                    # Main landing page
├── admin-login.php                  # Admin authentication
├── admin-dashboard.php              # Admin control panel
├── participant-login.php            # Participant authentication
├── participant-dashboard.php        # Participant interface
├── rpctc-*-home-page.php           # RPCTC-specific pages
├── form-*.php                       # Various forms (nomination, registration, etc.)
├── admin-*.php                      # Admin management modules
├── uploads/                         # File storage directory
├── vendor/                          # External libraries
└── assets/                          # CSS, JS, images
```

## 🔧 Key Modules

### Admin Modules
- **Training Calendar Management**: Create and manage training schedules
- **Nomination Processing**: Accept/reject participant nominations
- **Registration Management**: Handle participant registrations
- **Faculty Management**: Manage instructor profiles and assignments
- **Hostel Management**: Room allocation and accommodation
- **Budget Management**: Financial tracking and reporting
- **Gallery Management**: Upload and organize training media
- **Materials Management**: Course resource uploads
- **Feedback Analytics**: Analyze participant feedback
- **Report Generation**: Comprehensive training reports

### Participant Features
- **Dashboard**: Personal training information
- **Materials Access**: Download course resources
- **Gallery Viewing**: Access training photos/videos
- **Feedback Submission**: Course evaluation forms
- **Certificate Generation**: Digital training certificates

## 🔒 Security Features

- **Input Validation**: Server-side validation for all forms
- **SQL Injection Protection**: Prepared statements implementation
- **Session Management**: Secure user session handling
- **Access Control**: Role-based permission system
- **CAPTCHA Integration**: Bot protection for forms
- **OTP Verification**: Two-factor authentication for critical actions

## 📊 Reporting Capabilities

- **Training Statistics**: Participant numbers, completion rates
- **Financial Reports**: Budget utilization and expense tracking
- **Feedback Analysis**: Course evaluation and improvement insights
- **State-wise Analysis**: Regional training distribution
- **Certificate Tracking**: Digital certificate verification

## 🌐 Multi-Location Support

The system supports 5 organizational units:
- **NCRB Headquarters** (New Delhi)
- **RPCTC Lucknow**
- **RPCTC Kolkata** 
- **RPCTC Hyderabad**
- **RPCTC Gujarat**

Each location has dedicated admin access and customized interfaces while maintaining centralized oversight.

## 📱 Responsive Design

- Mobile-first responsive design
- Cross-browser compatibility (Chrome, Firefox, Safari, Edge)
- Accessibility compliance (WCAG 2.1 guidelines)
- Government website standards (GIGW) adherence

## 🔮 Future Enhancements

- **AI Integration**: Intelligent course recommendations and assessment
- **Mobile App**: Native mobile applications for iOS and Android
- **Blockchain Certification**: Immutable certificate verification
- **VR/AR Training**: Immersive learning experiences
- **API Development**: RESTful APIs for system integrations
- **Cloud Migration**: Scalable cloud infrastructure
- **Advanced Analytics**: Predictive analytics and ML insights

## 🤝 Contributing

This project was developed as part of an internship at NCRB. For contributions or modifications:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/enhancement`)
3. Commit changes (`git commit -am 'Add new feature'`)
4. Push to branch (`git push origin feature/enhancement`)
5. Create a Pull Request

## 📄 License

This project is developed for the National Crime Records Bureau, Government of India. All rights reserved.

## 👨‍💻 Developer

**Nikhil Gupta**
- Enrollment: 215/ICS/023
- Institution: Gautam Buddha University
- Program: Dual Degree Integrated B.Tech & M.Tech (CSE)
- Specialization: Software Engineering
- Email: [Contact via GitHub]

## 🏛️ Organization

**National Crime Records Bureau (NCRB)**
- Ministry of Home Affairs, Government of India
- Address: National Highway - 8, Mahipalpur, New Delhi - 110037
- Phone: (011) 26735450
- Email: dct@ncrb.gov.in

## 📞 Support

For technical support or queries regarding the training portal, contact the NCRB Training Division.

---

**"Empowering Indian Police with Information Technology"** 

*This portal contributes to India's Digital India initiative and enhances law enforcement training infrastructure nationwide.*
