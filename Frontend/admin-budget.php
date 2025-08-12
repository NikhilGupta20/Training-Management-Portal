<?php
// admin-budget.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin-login.php");
    exit;
}

// Database connection - using ncrb_training database
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ncrb_training';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Table creation with foreign keys - using InnoDB to fix foreign key issues
$tables = [
    // Budget heads table
    "CREATE TABLE IF NOT EXISTS budget_heads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        head_code VARCHAR(50) NOT NULL,
        head_name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB",
    
    // Expenditure details table
    "CREATE TABLE IF NOT EXISTS expenditure_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        head_id INT NOT NULL,
        detail_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (head_id) REFERENCES budget_heads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    // Training budget table
    "CREATE TABLE IF NOT EXISTS training_budget (
        id INT AUTO_INCREMENT PRIMARY KEY,
        head_id INT NOT NULL,
        allocated_budget DECIMAL(10,2) NOT NULL,
        expenses DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (head_id) REFERENCES budget_heads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    
    // Expenses table - added course_id and bill_path
    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        budget_id INT NOT NULL,
        ncrb_course_id INT(6) UNSIGNED,
        rpctc_course_id INT(6) UNSIGNED,
        amount DECIMAL(10,2) NOT NULL,
        expenditure_detail VARCHAR(255) NOT NULL,
        department VARCHAR(255) NOT NULL,
        bill_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (budget_id) REFERENCES training_budget(id) ON DELETE CASCADE,
        FOREIGN KEY (ncrb_course_id) REFERENCES training_events(id) ON DELETE SET NULL,
        FOREIGN KEY (rpctc_course_id) REFERENCES rpctc_training_events(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    
    // Create rpctc_training_events if not exists
    "CREATE TABLE IF NOT EXISTS rpctc_training_events (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        course_name_hindi VARCHAR(255),
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        location VARCHAR(255) NOT NULL,
        duration INT(3),
        eligibility TEXT,
        color VARCHAR(7),
        status VARCHAR(20) DEFAULT 'active',
        reminders_active TINYINT(1) DEFAULT 1,
        reminders_paused TINYINT(1) DEFAULT 0,
        reminders_enabled TINYINT(1) DEFAULT 1,
        objectives TEXT
    ) ENGINE=InnoDB"
];

// Create tables if they don't exist
foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// ALTER TABLE for expenses if needed
$result = $conn->query("SHOW COLUMNS FROM expenses LIKE 'course_id'");
if ($result && $result->num_rows > 0) {
    $conn->query("ALTER TABLE expenses DROP FOREIGN KEY IF EXISTS expenses_ibfk_2");
    $conn->query("ALTER TABLE expenses DROP COLUMN course_id");
    $conn->query("ALTER TABLE expenses ADD COLUMN ncrb_course_id INT(6) UNSIGNED AFTER budget_id");
    $conn->query("ALTER TABLE expenses ADD COLUMN rpctc_course_id INT(6) UNSIGNED AFTER ncrb_course_id");
    $conn->query("ALTER TABLE expenses ADD FOREIGN KEY (ncrb_course_id) REFERENCES training_events(id) ON DELETE SET NULL");
    $conn->query("ALTER TABLE expenses ADD FOREIGN KEY (rpctc_course_id) REFERENCES rpctc_training_events(id) ON DELETE SET NULL");
}

// Initialize budget heads
$budgetHeads = [
    ['TE02.01.09', 'Training Expenses (TE)02.01.09'],
    ['PS02.01.28', 'Professional Services (PS) 02.01.28'],
    ['OE02.01.13', 'Office Expenses (OE) 02.01.13'],
    ['OFA07.01.77', 'Other Fixed Assets (OFA) (07.01.77)']
];

// Initialize expenditure details
$expenditureDetails = [
    ['TE02.01.09', 'Reimbursement of bills of Stationery Items and Tea/Snacks/Lunch in NCRB Sponsored Training Courses at 4 RPCTC.'],
    ['PS02.01.28', 'Payment of Honorarium to Guest faculties taking classes at RPCTCs in NCRB sponsored Training Courses and Guest faculties taking classes in NCRB.'],
    ['OE02.01.13', 'Payment of Conveyance Charge to Guest faculties taking classes at NCRB.'],
    ['OFA07.01.77', 'Book purchase.']
];

$departments = ['NCRB', 'RPCTC'];

// Populate budget heads if empty
if ($conn->query("SELECT COUNT(*) as count FROM budget_heads")->fetch_assoc()['count'] == 0) {
    foreach ($budgetHeads as $head) {
        $stmt = $conn->prepare("INSERT INTO budget_heads (head_code, head_name) VALUES (?, ?)");
        $stmt->bind_param("ss", $head[0], $head[1]);
        $stmt->execute();
    }
}

// Populate expenditure details if empty
if ($conn->query("SELECT COUNT(*) as count FROM expenditure_details")->fetch_assoc()['count'] == 0) {
    foreach ($expenditureDetails as $detail) {
        $head_id = $conn->query("SELECT id FROM budget_heads WHERE head_code = '{$detail[0]}'")->fetch_assoc()['id'];
        $stmt = $conn->prepare("INSERT INTO expenditure_details (head_id, detail_name) VALUES (?, ?)");
        $stmt->bind_param("is", $head_id, $detail[1]);
        $stmt->execute();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new budget allocation
    if (isset($_POST['add_budget'])) {
        if ($_POST['head_id'] === 'other') {
            $head_code = htmlspecialchars($_POST['custom_head_code'], ENT_QUOTES, 'UTF-8');
            $head_name = htmlspecialchars($_POST['custom_head_name'], ENT_QUOTES, 'UTF-8');
            
            $stmt = $conn->prepare("INSERT INTO budget_heads (head_code, head_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $head_code, $head_name);
            $stmt->execute();
            $head_id = $stmt->insert_id;
        } else {
            $head_id = intval($_POST['head_id']);
        }
        
        $allocated_budget = floatval($_POST['allocated_budget']);

        $stmt = $conn->prepare("INSERT INTO training_budget (head_id, allocated_budget) VALUES (?, ?)");
        $stmt->bind_param("id", $head_id, $allocated_budget);
        $stmt->execute();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
    // Add new expense
    elseif (isset($_POST['add_expense'])) {
        $budget_id = intval($_POST['budget_id']);
        $expense_amount = floatval($_POST['expense_amount']);
        $department = htmlspecialchars($_POST['department'], ENT_QUOTES, 'UTF-8');
        
        $ncrb_course_id = null;
        $rpctc_course_id = null;
        
        if (isset($_POST['course_id'])) {
            if ($department === 'NCRB') {
                $ncrb_course_id = intval($_POST['course_id']);
            } elseif ($department === 'RPCTC') {
                $rpctc_course_id = intval($_POST['course_id']);
            }
        }
        
        $expenditure_detail = '';
        
        // Handle custom expenditure detail
        if ($_POST['expenditure_detail'] === 'other') {
            $expenditure_detail = htmlspecialchars($_POST['custom_expenditure_detail'], ENT_QUOTES, 'UTF-8');
        } else {
            $expenditure_detail = htmlspecialchars($_POST['expenditure_detail'], ENT_QUOTES, 'UTF-8');
        }
        
        // Handle custom department
        if ($_POST['department'] === 'other') {
            $department = htmlspecialchars($_POST['custom_department'], ENT_QUOTES, 'UTF-8');
        }
        
        // Handle file upload
        if (isset($_FILES['bill']) && $_FILES['bill']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/bills/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = uniqid() . '_' . basename($_FILES['bill']['name']);
            $targetPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['bill']['tmp_name'], $targetPath)) {
                $bill_path = $targetPath;
            }
        } else {
            $bill_path = null;
        }
        
        // Insert expense record
        $stmt = $conn->prepare("INSERT INTO expenses (budget_id, ncrb_course_id, rpctc_course_id, amount, expenditure_detail, department, bill_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiidsss", $budget_id, $ncrb_course_id, $rpctc_course_id, $expense_amount, $expenditure_detail, $department, $bill_path);
        $stmt->execute();
        $stmt->close();
        
        // Update budget spent amount
        $stmt = $conn->prepare("UPDATE training_budget SET expenses = expenses + ? WHERE id = ?");
        $stmt->bind_param("di", $expense_amount, $budget_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Date filtering
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Fetch courses based on department
function getCourses($conn, $department) {
    if ($department === 'NCRB') {
        $query = "SELECT course_name, MIN(id) as id 
                  FROM training_events 
                  GROUP BY course_name
                  ORDER BY course_name";
    } elseif ($department === 'RPCTC') {
        $query = "SELECT course_name, MIN(id) as id 
                  FROM rpctc_training_events 
                  GROUP BY course_name
                  ORDER BY course_name";
    } else {
        return [];
    }
    $result = $conn->query($query);
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    return $courses;
}

// Function to get budget data with optional department filter
function getBudgetData($conn, $start_date = null, $end_date = null, $department = null) {
    $budgets_query = "SELECT tb.*, bh.head_name 
                      FROM training_budget tb
                      JOIN budget_heads bh ON tb.head_id = bh.id";
    $budgets_result = $conn->query($budgets_query);

    $budgets = [];
    $total_allocated = 0;
    $total_expenses = 0;
    $period_expenses = 0;
    $period_allocated = 0;
    $period_remaining = 0;

    if ($budgets_result) {
        while ($row = $budgets_result->fetch_assoc()) {
            
            $expenses_query = "SELECT e.*, 
                               COALESCE(te.course_name, rte.course_name) as course_name 
                               FROM expenses e
                               LEFT JOIN training_events te ON e.ncrb_course_id = te.id
                               LEFT JOIN rpctc_training_events rte ON e.rpctc_course_id = rte.id
                               WHERE budget_id = ?";
            $params = [$row['id']];
            $types = "i";
            
            // Apply department filter if set
            if ($department) {
                $expenses_query .= " AND e.department = ?";
                $params[] = $department;
                $types .= "s";
            }
            
            // Apply date filters if set
            if ($start_date && $end_date) {
                $expenses_query .= " AND e.created_at BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= "ss";
            }
            
            $expenses_query .= " ORDER BY e.created_at DESC";
            
            $stmt = $conn->prepare($expenses_query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $expenses_result = $stmt->get_result();
            $row['expenses_list'] = [];
            
            $budget_period_expenses = 0;
            while ($expense = $expenses_result->fetch_assoc()) {
                $row['expenses_list'][] = $expense;
                $budget_period_expenses += $expense['amount'];
            }
            $row['period_expenses'] = $budget_period_expenses;
            $period_expenses += $budget_period_expenses;
            
            $row['period_allocated'] = $row['allocated_budget'];
            $row['period_remaining'] = $row['allocated_budget'] - $budget_period_expenses;
            $period_allocated += $row['period_allocated'];
            $period_remaining += $row['period_remaining'];
            
            $budgets[] = $row;
            
            $total_allocated += $row['allocated_budget'];
            $total_expenses += $row['expenses'];
        }
    }
    
    return [
        'budgets' => $budgets,
        'total_allocated' => $total_allocated,
        'total_expenses' => $total_expenses,
        'period_expenses' => $period_expenses,
        'period_allocated' => $period_allocated,
        'period_remaining' => $period_remaining
    ];
}

// Get budget data for screen display (no department filter)
$screenData = getBudgetData($conn, $start_date, $end_date);
$budgets = $screenData['budgets'];
$total_allocated = $screenData['total_allocated'];
$total_expenses = $screenData['total_expenses'];
$period_expenses = $screenData['period_expenses'];
$period_allocated = $screenData['period_allocated'];
$period_remaining = $screenData['period_remaining'];

// Calculate budget metrics
$remaining_budget = $total_allocated - $total_expenses;
$utilization_percentage = $total_allocated > 0 ? ($total_expenses / $total_allocated) * 100 : 0;
$username = htmlspecialchars($_SESSION['admin_username']);

// Function to build printable report
function buildPrintableReport($conn, $start_date, $end_date, $department = null) {
    $data = getBudgetData($conn, $start_date, $end_date, $department);
    $budgets = $data['budgets'];
    $totalAllocated = $data['total_allocated'];
    $totalExpenses = $data['period_expenses'];
    $totalRemaining = $data['period_remaining'];
    
    ob_start();
    ?>
    <div class="printable-report" data-department="<?= $department ?>">
        <div class="print-header">
            <img src="https://www.ncrb.gov.in/static/dist/images/icons/Ministry_of_Home_Affairs_India.svg.png" 
                 alt="MHA Logo" class="print-logo">
            <div class="print-title-container">
                <h1 class="print-title">National Crime Records Bureau</h1>
                <h2 class="print-subtitle">Training Budget Report</h2>
                <?php if ($department): ?>
                    <h3 class="department-title">Department: <?= $department ?></h3>
                <?php endif; ?>
            </div>
            <img src="https://www.ncrb.gov.in/static/dist/images/icons/National-Crime-Records-Bureau-Logo.png" 
                 alt="NCRB Logo" class="print-logo">
        </div>
        
        <div class="print-meta">
            <div>
                <strong>Generated:</strong> <?= date('d M Y H:i:s') ?>
            </div>
            <div>
                <strong>Admin:</strong> <?= htmlspecialchars($_SESSION['admin_username']) ?>
            </div>
            <?php if ($start_date || $end_date): ?>
            <div>
                <strong>Date Range:</strong> 
                <?= $start_date ? date('d M Y', strtotime($start_date)) : 'Beginning' ?> 
                to 
                <?= $end_date ? date('d M Y', strtotime($end_date)) : 'Today' ?>
            </div>
            <?php endif; ?>
        </div>
        
        <table class="printable-report-table">
            <thead>
                <tr>
                    <th>S.No.</th>
                    <th>Budget Head</th>
                    <th>Allocated (₹)</th>
                    <th>Expenses (₹)</th>
                    <th>Remaining (₹)</th>
                    <th>Expense Details</th>
                    <th>Utilization (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sno = 1;
                $totalAllocated = 0;
                $totalExpenses = 0;
                $totalRemaining = 0;
                
                foreach ($budgets as $budget): 
                    if ($start_date || $end_date) {
                        $expenses = $budget['period_expenses'];
                        $remaining = $budget['period_remaining'];
                    } else {
                        $expenses = $budget['expenses'];
                        $remaining = $budget['allocated_budget'] - $expenses;
                    }
                    
                    $allocated = $budget['allocated_budget'];
                    $utilization = $allocated > 0 ? ($expenses / $allocated) * 100 : 0;
                    
                    $totalAllocated += $allocated;
                    $totalExpenses += $expenses;
                    $totalRemaining += $remaining;
                ?>
                <tr>
                    <td><?= $sno++ ?></td>
                    <td><?= htmlspecialchars($budget['head_name']) ?></td>
                    <td class="text-right">₹<?= number_format($allocated, 2) ?></td>
                    <td class="text-right">₹<?= number_format($expenses, 2) ?></td>
                    <td class="text-right">₹<?= number_format($remaining, 2) ?></td>
                    <td>
                        <?php if (!empty($budget['expenses_list'])): ?>
                        <table style="width:100%;">
                            <tr class="expense-details-header">
                                <td>Course</td>
                                <td>Detail</td>
                                <td>Amount</td>
                                <td>Department</td>
                                <td>Date</td>
                            </tr>
                            <?php foreach ($budget['expenses_list'] as $expense): ?>
                            <tr class="expense-detail-row">
                                <td><?= htmlspecialchars($expense['course_name']) ?></td>
                                <td><?= htmlspecialchars($expense['expenditure_detail']) ?></td>
                                <td class="text-right">₹<?= number_format($expense['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($expense['department']) ?></td>
                                <td><?= date('d M Y', strtotime($expense['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php else: ?>
                        No expenses recorded
                        <?php endif; ?>
                    </td>
                    <td class="utilization-cell"><?= number_format($utilization, 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="summary-row">
                    <td colspan="2" class="text-bold">TOTAL</td>
                    <td class="text-right text-bold">₹<?= number_format($totalAllocated, 2) ?></td>
                    <td class="text-right text-bold">₹<?= number_format($totalExpenses, 2) ?></td>
                    <td class="text-right text-bold">₹<?= number_format($totalRemaining, 2) ?></td>
                    <td></td>
                    <td class="utilization-cell text-bold">
                        <?= $totalAllocated > 0 ? number_format(($totalExpenses / $totalAllocated) * 100, 1) : '0.0' ?>%
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="print-footer">
            <p>National Crime Records Bureau<br>
            Ministry of Home Affairs, Government of India</p>
            <p>Generated by NCRB Budget Management System</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Generate printable reports
$printable_report_all = buildPrintableReport($conn, $start_date, $end_date);
$printable_report_ncrb = buildPrintableReport($conn, $start_date, $end_date, 'NCRB');
$printable_report_rpctc = buildPrintableReport($conn, $start_date, $end_date, 'RPCTC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-param" content="_csrf">
    <meta name="csrf-token" content="b1YiF5Sawp-d_QvbNTSAEWtDrLlMvhek8L6ch0ziPCkiEXFt0PCdx-27O4xDWfVfGi7i_wLbXt2b-f3AK9Z6UA==">
    <title>Budget Management | NCRB</title>
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
            --success: #276749;
            --warning: #b45309;
            --danger: #c53030;
            --light: #f8fafc;
            --dark: #2d3748;
            --accent: #ff9900;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            background-color: #f5f7fa;
            color: #333;
            padding-top: 200px;
        }
        
        .fixed-header {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: white;
            border-bottom: 1px solid #e2e8f0;
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
        
        .nav-container {
            background: var(--primary);
            color: white;
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
            color: #718096;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 50px 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 50px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px 25px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            font-size: 16px;
            transition: all 0.3s;
            background-color: white;
            background-image: linear-gradient(45deg, transparent 50%, #555 50%),
                              linear-gradient(135deg, #555 50%, transparent 50%);
            background-position: calc(100% - 20px) center, calc(100% - 15px) center;
            background-size: 5px 5px, 5px 5px;
            background-repeat: no-repeat;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .budget-head-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .budget-head-container .form-group {
            flex: 1 1 300px;
        }
        
        .custom-input-group {
            margin-top: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--secondary);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0, 102, 204, 0.25);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 102, 204, 0.4);
        }
        
        .btn-print {
            background: #4a5568;
        }
        
        .btn-back {
            background: #4a5568;
            margin-right: 15px;
        }
        
        .budget-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .budget-table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .budget-table td {
            padding: 15px;
            border-bottom: 1px solid #edf2f7;
        }
        
        .budget-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        .budget-table tr:hover {
            background-color: #f1f7ff;
        }
        
        .expense-item {
            margin-bottom: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--secondary);
        }
        
        .expense-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .expense-amount {
            font-weight: 700;
            color: var(--primary);
            font-size: 18px;
            min-width: 100px;
        }
        
        .expense-reason {
            font-weight: 600;
            color: var(--dark);
            flex-grow: 1;
            padding: 0 15px;
        }
        
        .expense-description {
            color: #4a5568;
            padding: 8px 0;
            margin-top: 8px;
        }
        
        .expense-department {
            font-weight: 500;
            color: #2d3748;
            margin-top: 5px;
            font-size: 15px;
        }
        
        .expense-date {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .summary-card .value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .text-center { text-align: center; }
        .mt-4 { margin-top: 1.5rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .d-flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .align-center { align-items: center; }
        .no-print { display: block; }
        
        .date-filter {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .date-filter .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 200px;
        }
        
        .date-filter-btn {
            background: #4a5568;
        }
        
        .period-summary {
            background: #e6f7ff;
            border-left: 4px solid #1890ff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .combined-budget-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #edf2f7;
        }
        
        .combined-budget-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .combined-budget-header i {
            font-size: 22px;
        }
        
        .budget-head-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .budget-head-group .form-group {
            flex: 1;
        }
        
        /* Professional Footer Styles (Integrated from home-page.php) */
        .professional-footer {
            background: #003366;
            color: #ffffff;
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
            color: #ff9900;
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
            color: #ff9900;
        }
        
        .footer-info a {
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-info a:hover {
            color: #ff9900;
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
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .copyright a:hover {
            color: #ff9900;
            text-decoration: underline;
        }
        
        .current-date {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 10px;
            color: #ff9900;
        }
        
        .mailto-link {
            color: #ffffff;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .mailto-link:hover {
            color: #ff9900;
            text-decoration: underline;
        }
        
        /* OFFICIAL REPORT STYLES */
        .official-report {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .report-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .report-title {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0 0 5px 0;
        }
        
        .report-subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin: 0;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            background: #f0f5ff;
            padding: 15px 20px;
            border-bottom: 1px solid #dde4f0;
            font-size: 14px;
        }
        
        .report-meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-weight: 600;
            color: var(--primary);
            font-size: 13px;
        }
        
        .meta-value {
            color: #333;
        }
        
        .budget-report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 20px;
        }
        
        .budget-report-table th {
            background: #e6f0ff;
            color: var(--primary);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #b3c6e5;
        }
        
        .budget-report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .budget-report-table tr:nth-child(even) {
            background-color: #f9fbfe;
        }
        
        .budget-head-row {
            background-color: #f0f7ff !important;
            font-weight: 600;
        }
        
        .expense-details {
            padding: 0;
        }
        
        .expense-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .expense-details-table td {
            padding: 8px 10px;
            border: none;
            font-size: 13px;
        }
        
        .expense-details-table tr:not(:last-child) td {
            border-bottom: 1px dashed #e0e0e0;
        }
        
        .expense-amount-cell {
            font-weight: 600;
            color: #006600;
            white-space: nowrap;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-bold {
            font-weight: 700;
        }
        
        .summary-row {
            background-color: #f0f0f0;
        }
        
        .utilization-cell {
            text-align: center;
        }
        
        .signature-section {
            padding: 30px;
            margin-top: 30px;
            border-top: 1px solid #ccc;
        }
        
        .signature-line {
            width: 300px;
            border-bottom: 1px solid #000;
            margin: 40px auto 5px;
            text-align: center;
        }
        
        .official-stamp {
            float: right;
            border: 2px solid #c00;
            padding: 10px 20px;
            text-align: center;
            font-weight: bold;
            transform: rotate(5deg);
            margin-top: -40px;
            margin-right: 50px;
            opacity: 0.8;
        }
        
        /* PRINT STYLES */
        @media print {
            body * {
                visibility: hidden;
            }
            .official-report, .official-report * {
                visibility: visible;
            }
            .official-report {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 10px;
            }
            .no-print, .btn {
                display: none !important;
            }
        }
        
        /* NEW REPORT TABLE STYLES */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .report-table th {
            background: #003366;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .budget-head-row {
            background-color: #f0f7ff;
            font-weight: 600;
        }
        
        .expense-row td {
            padding: 8px 15px 8px 30px;
            font-size: 13px;
        }
        
        .expense-row:not(:last-child) td {
            border-bottom: 1px dashed #e0e0e0;
        }
        
        .expense-details {
            padding-left: 20px;
        }
        
        .expense-amount {
            font-weight: 600;
            color: #006600;
            min-width: 100px;
        }
        
        .expense-reason {
            flex-grow: 1;
        }
        
        .expense-department {
            min-width: 100px;
        }
        
        .expense-date {
            min-width: 100px;
        }
        
        .report-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #003366;
            text-align: center;
            font-weight: 600;
        }
        
        /* New styles for printable report */
        .printable-report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10pt;
        }
        
        .printable-report-table th {
            background: #003366;
            color: white;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .printable-report-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        .expense-detail-row td {
            padding: 4px 8px;
            font-size: 9pt;
        }
        
        .expense-detail-row:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .expense-details-header {
            background-color: #e6f0ff;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-bold {
            font-weight: bold;
        }
        
        .summary-row {
            background-color: #f0f0f0;
        }
        
        .utilization-cell {
            text-align: center;
        }
        
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #003366;
            padding-bottom: 15px;
        }
        
        .print-title-container {
            text-align: center;
        }
        
        .print-title {
            margin: 0;
            font-size: 18pt;
            color: #003366;
        }
        
        .print-subtitle {
            margin: 5px 0 0 0;
            font-size: 14pt;
            font-weight: normal;
            color: #0066cc;
        }
        
        .print-logo {
            height: 70px;
        }
        
        .print-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 10pt;
        }
        
        .print-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        
        @media print {
            @page {
                margin: 0.5cm;
            }
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
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            color: var(--accent);
        }
        
        .modal-body {
            padding: 25px;
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
            transition: all 0.3s ease;
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
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        #developerContactModal .contact-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
        }
        
        #developerContactModal .contact-btn i {
            font-size: 1.3rem;
        }
        
        .call-btn { background: #28a745; }
        .sms-btn { background: #17a2b8; }
        .whatsapp-btn { background: #25D366; }
        .email-btn { background: #dc3545; }
        
        @media (max-width: 992px) {
            body {
                padding-top: 190px;
            }
            
            .dashboard-header h1 {
                font-size: 2.2rem;
            }
            
            .welcome-banner h2 {
                font-size: 2.4rem;
            }
            
            .report-meta {
                flex-direction: column;
                gap: 10px;
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
                height: 70px;
            }
            
            .nav-container {
                padding: 0 20px 5px;
            }
            
            .dashboard-header h1 {
                font-size: 2rem;
            }
            
            .budget-head-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .date-filter {
                flex-direction: column;
                align-items: stretch;
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
            
            .welcome-banner h2 {
                font-size: 1.8rem;
            }
            
            .admin-info-container {
                justify-content: center;
                width: 100%;
            }
            
            .report-title {
                font-size: 18px;
            }
            
            .report-subtitle {
                font-size: 16px;
            }
        }
        
        /* Hidden printable report */
        #printable-report {
            display: none;
        }
        
        /* Add new style for bill link */
        .expense-bill a {
            display: inline-block;
            padding: 5px 10px;
            background: #003366;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 8px;
        }
        
        .expense-bill a:hover {
            background: #0066cc;
        }
        
        /* New styles for printable report */
        .printable-report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10pt;
        }
        
        .printable-report-table th {
            background: #003366;
            color: white;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .printable-report-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        .expense-detail-row td {
            padding: 4px 8px;
            font-size: 9pt;
        }
        
        .expense-detail-row:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .expense-details-header {
            background-color: #e6f0ff;
            font-weight: bold;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-bold {
            font-weight: bold;
        }
        
        .summary-row {
            background-color: #f0f0f0;
        }
        
        .utilization-cell {
            text-align: center;
        }
        
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #003366;
            padding-bottom: 15px;
        }
        
        .print-title-container {
            text-align: center;
        }
        
        .print-title {
            margin: 0;
            font-size: 18pt;
            color: #003366;
        }
        
        .print-subtitle {
            margin: 5px 0 0 0;
            font-size: 14pt;
            font-weight: normal;
            color: #0066cc;
        }
        
        .department-title {
            font-size: 12pt;
            color: #555;
            margin-top: 5px;
        }
        
        .print-logo {
            height: 70px;
        }
        
        .print-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-size: 10pt;
        }
        
        .print-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        
        @media print {
            @page {
                margin: 0.5cm;
            }
        }
        
        /* Print options dropdown */
        .print-options {
            position: relative;
            display: inline-block;
        }
        
        .print-dropdown {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .print-dropdown a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
        }
        
        .print-dropdown a:hover {
            background-color: #003366;
            color: white;
        }
        
        .print-options:hover .print-dropdown {
            display: block;
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
                <div class="nav-title">National Crime Records Bureau - Budget Management</div>
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
        <div class="welcome-banner no-print">
            <h2>Training Budget Management</h2>
            <p>Welcome, <strong><?= $username ?></strong>. Track and manage all training budgets from this dashboard.</p>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Allocated</h3>
                <div class="value">₹<?= number_format($total_allocated, 2) ?></div>
                <div class="sub-value">For <?= count($budgets) ?> programs</div>
            </div>
            
            <div class="summary-card">
                <h3>Total Expenses</h3>
                <div class="value">₹<?= number_format($total_expenses, 2) ?></div>
                <div class="sub-value">
                    <?= number_format($utilization_percentage, 1) ?>% utilized
                </div>
            </div>
            
            <div class="summary-card">
                <h3>Remaining Budget</h3>
                <div class="value">₹<?= number_format($remaining_budget, 2) ?></div>
                <div class="sub-value">Available for use</div>
            </div>
        </div>

        <!-- Create Budget Form -->
        <div class="card no-print">
            <div class="card-header">Create New Budget</div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="head_id">Budget Head</label>
                        <select id="head_id" name="head_id" class="form-control" required onchange="toggleBudgetHeadCustom(this.value)">
                            <option value="">-- Select Budget Head --</option>
                            <?php 
                            $budget_heads_result = $conn->query("SELECT * FROM budget_heads");
                            while ($head = $budget_heads_result->fetch_assoc()): ?>
                                <option value="<?= $head['id'] ?>">
                                    <?= htmlspecialchars($head['head_name']) ?>
                                </option>
                            <?php endwhile; ?>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div id="custom_head_group" class="custom-input-group" style="display:none;">
                        <div class="form-group">
                            <label for="custom_head_code">Budget Head Code</label>
                            <input type="text" id="custom_head_code" name="custom_head_code" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="custom_head_name">Budget Head Name</label>
                            <input type="text" id="custom_head_name" name="custom_head_name" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="allocated_budget">Budget Amount (₹)</label>
                        <input type="number" id="allocated_budget" name="allocated_budget" 
                               step="0.01" min="0" class="form-control" required>
                    </div>
                    
                    <button type="submit" name="add_budget" class="btn">
                        <i class="fas fa-plus-circle"></i> Create Budget
                    </button>
                </form>
            </div>
        </div>

        <!-- Record Expense Form -->
        <div class="card no-print">
            <div class="card-header">Record Expense</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="budget_id">Select Allocated Budget</label>
                        <select id="budget_id" name="budget_id" class="form-control" required>
                            <option value="">-- Select Allocated Budget --</option>
                            <?php foreach ($budgets as $budget): 
                                $remaining = $budget['allocated_budget'] - $budget['expenses'];
                            ?>
                                <option value="<?= $budget['id'] ?>" <?= ($remaining <= 0) ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($budget['head_name']) ?> 
                                    (₹<?= number_format($remaining, 2) ?> remaining)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department</label>
                        <select id="department" name="department" class="form-control" required onchange="updateCoursesDropdown(this.value)">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>">
                                    <?= htmlspecialchars($dept) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div id="custom_department_group" class="custom-input-group" style="display:none;">
                        <div class="form-group">
                            <label for="custom_department">Custom Department</label>
                            <input type="text" id="custom_department" name="custom_department" class="form-control">
                        </div>
                    </div>
                    
                    <div class="combined-budget-section">
                        <div class="combined-budget-header">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Training Course & Expenditure Details
                        </div>
                        
                        <div class="budget-head-group">
                            <div class="form-group">
                                <label for="course_id">Training Course</label>
                                <select id="course_id" name="course_id" class="form-control" required>
                                    <option value="">-- Select Training Course --</option>
                                    <?php 
                                    // Get unique courses by name
                                    $courses = $conn->query("
                                        SELECT course_name, MIN(id) as id 
                                        FROM training_events 
                                        GROUP BY course_name
                                        ORDER BY course_name
                                    ");
                                    while ($course = $courses->fetch_assoc()): ?>
                                        <option value="<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['course_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                
                            <div class="form-group">
                                <label for="expenditure_detail">Detail of Expenditure</label>
                                <select id="expenditure_detail" name="expenditure_detail" class="form-control" required onchange="toggleExpenditureDetailCustom(this.value)">
                                    <option value="">-- Select Detail --</option>
                                    <?php 
                                    $details = $conn->query("SELECT * FROM expenditure_details");
                                    while ($detail = $details->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($detail['detail_name']) ?>">
                                            <?= htmlspecialchars($detail['detail_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="custom_expenditure_detail_group" class="custom-input-group" style="display:none;">
                            <div class="form-group">
                                <label for="custom_expenditure_detail">Custom Expenditure Detail</label>
                                <input type="text" id="custom_expenditure_detail" name="custom_expenditure_detail" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="expense_amount">Amount (₹)</label>
                        <input type="number" id="expense_amount" name="expense_amount" 
                               step="0.01" min="0.01" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bill">Bill (if available)</label>
                        <input type="file" id="bill" name="bill" class="form-control">
                    </div>
                    
                    <button type="submit" name="add_expense" class="btn">
                        <i class="fas fa-receipt"></i> Record Expense
                    </button>
                </form>
            </div>
        </div>

        <!-- Budget Records Section -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-between align-center">
                    <span>Budget Records</span>
                    <div class="print-options">
                        <button class="btn btn-print">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                        <div class="print-dropdown">
                            <a href="#" onclick="printBudgetReport('all')">Print All</a>
                            <a href="#" onclick="printBudgetReport('ncrb')">Print NCRB</a>
                            <a href="#" onclick="printBudgetReport('rpctc')">Print RPCTC</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Date Filter Form -->
                <form method="GET" class="date-filter no-print">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?= htmlspecialchars($start_date) ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?= htmlspecialchars($end_date) ?>" class="form-control">
                    </div>
                    
                    <button type="submit" class="btn date-filter-btn">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    
                    <?php if ($start_date || $end_date): ?>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn">
                        <i class="fas fa-times"></i> Clear Filter
                    </a>
                    <?php endif; ?>
                </form>
                
                <?php if ($start_date || $end_date): ?>
                <div class="period-summary">
                    Showing expenses from 
                    <strong><?= $start_date ? date('d M Y', strtotime($start_date)) : 'beginning' ?></strong> 
                    to 
                    <strong><?= $end_date ? date('d M Y', strtotime($end_date)) : 'now' ?></strong>
                    <div class="mt-2">
                        Total expenses in period: 
                        <strong>₹<?= number_format($period_expenses, 2) ?></strong>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="table-container">
                    <table class="budget-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Budget Head</th>
                                <th>Allocated (₹)</th>
                                <th>Spent (₹)</th>
                                <th>Remaining (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): 
                                $remaining = $budget['allocated_budget'] - $budget['expenses'];
                                $utilization = $budget['allocated_budget'] > 0 ? 
                                    ($budget['expenses'] / $budget['allocated_budget']) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= $budget['id'] ?></td>
                                <td><strong><?= htmlspecialchars($budget['head_name']) ?></strong></td>
                                <td>₹<?= number_format($budget['allocated_budget'], 2) ?></td>
                                <td>
                                    <?php if ($start_date || $end_date): ?>
                                        ₹<?= number_format($budget['period_expenses'], 2) ?>
                                    <?php else: ?>
                                        ₹<?= number_format($budget['expenses'], 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($start_date || $end_date): ?>
                                        ₹<?= number_format($budget['period_remaining'], 2) ?>
                                    <?php else: ?>
                                        ₹<?= number_format($remaining, 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5">
                                    <h4>Expense Details:</h4>
                                    <?php if (!empty($budget['expenses_list'])): ?>
                                        <div class="expense-details-screen">
                                            <?php foreach ($budget['expenses_list'] as $expense): ?>
                                            <div class="expense-item">
                                                <div class="expense-header">
                                                    <div class="expense-amount">₹<?= number_format($expense['amount'], 2) ?></div>
                                                    <div class="expense-reason">
                                                        <?= htmlspecialchars($expense['course_name']) ?>
                                                    </div>
                                                </div>
                                                <div class="expense-description">
                                                    <?= htmlspecialchars($expense['expenditure_detail']) ?>
                                                </div>
                                                <div class="expense-department">
                                                    Department: <?= htmlspecialchars($expense['department']) ?>
                                                </div>
                                                <?php if (!empty($expense['bill_path'])): ?>
                                                    <div class="expense-bill">
                                                        <a href="<?= htmlspecialchars($expense['bill_path']) ?>" target="_blank">View Bill</a>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="expense-date">
                                                    <?= date('d M Y', strtotime($expense['created_at'])) ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p>No expenses recorded</p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="summary-row">
                                <td colspan="2">TOTALS</td>
                                <td>₹<?= number_format($total_allocated, 2) ?></td>
                                <td>
                                    <?php if ($start_date || $end_date): ?>
                                        ₹<?= number_format($period_expenses, 2) ?>
                                    <?php else: ?>
                                        ₹<?= number_format($total_expenses, 2) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($start_date || $end_date): ?>
                                        ₹<?= number_format($period_remaining, 2) ?>
                                    <?php else: ?>
                                        ₹<?= number_format($remaining_budget, 2) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <?php if ($start_date || $end_date): ?>
                            <tr class="period-summary-row">
                                <td colspan="2">PERIOD SUMMARY</td>
                                <td>₹<?= number_format($period_allocated, 2) ?></td>
                                <td>₹<?= number_format($period_expenses, 2) ?></td>
                                <td>₹<?= number_format($period_remaining, 2) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Back to Dashboard Button -->
        <div class="text-center mt-4 no-print" style="padding: 20px;">
            <a href="admin-dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Professional Footer -->
    <footer class="professional-footer no-print">
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

    <!-- Hidden printable reports -->
    <div style="display:none;">
        <div id="printable-report-all"><?= $printable_report_all ?></div>
        <div id="printable-report-ncrb"><?= $printable_report_ncrb ?></div>
        <div id="printable-report-rpctc"><?= $printable_report_rpctc ?></div>
    </div>

    <script>
        // DOM elements
        const expenditureDetailDropdown = document.getElementById('expenditure_detail');
        const customExpenditureDetailGroup = document.getElementById('custom_expenditure_detail_group');
        const customExpenditureDetailInput = document.getElementById('custom_expenditure_detail');
        const departmentDropdown = document.getElementById('department');
        const customDepartmentGroup = document.getElementById('custom_department_group');
        const customDepartmentInput = document.getElementById('custom_department');
        const headIdDropdown = document.getElementById('head_id');
        const customHeadGroup = document.getElementById('custom_head_group');
        const customHeadCodeInput = document.getElementById('custom_head_code');
        const customHeadNameInput = document.getElementById('custom_head_name');
        const courseDropdown = document.getElementById('course_id');

        // Preload courses for both departments
        const ncrbCourses = <?= json_encode(getCourses($conn, 'NCRB')) ?>;
        const rpctcCourses = <?= json_encode(getCourses($conn, 'RPCTC')) ?>;

        // Expenditure detail change handler
        expenditureDetailDropdown.addEventListener('change', function() {
            if (this.value === 'other') {
                customExpenditureDetailGroup.style.display = 'block';
                customExpenditureDetailInput.setAttribute('required', 'required');
            } else {
                customExpenditureDetailGroup.style.display = 'none';
                customExpenditureDetailInput.removeAttribute('required');
            }
        });
        
        // Department change handler
        departmentDropdown.addEventListener('change', function() {
            if (this.value === 'other') {
                customDepartmentGroup.style.display = 'block';
                customDepartmentInput.setAttribute('required', 'required');
            } else {
                customDepartmentGroup.style.display = 'none';
                customDepartmentInput.removeAttribute('required');
            }
        });
        
        // Budget head change handler
        function toggleBudgetHeadCustom(value) {
            if (value === 'other') {
                customHeadGroup.style.display = 'block';
                customHeadCodeInput.setAttribute('required', 'required');
                customHeadNameInput.setAttribute('required', 'required');
            } else {
                customHeadGroup.style.display = 'none';
                customHeadCodeInput.removeAttribute('required');
                customHeadNameInput.removeAttribute('required');
            }
        }
        
        // Update courses dropdown based on department
        function updateCoursesDropdown(department) {
            // Clear existing options
            courseDropdown.innerHTML = '<option value="">-- Select Training Course --</option>';
            
            let courses = [];
            if (department === 'NCRB') {
                courses = ncrbCourses;
            } else if (department === 'RPCTC') {
                courses = rpctcCourses;
            }
            
            // Add courses to dropdown
            courses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.course_name;
                courseDropdown.appendChild(option);
            });
        }

        // DOM ready handler
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdowns
            if (expenditureDetailDropdown.value === 'other') {
                expenditureDetailDropdown.dispatchEvent(new Event('change'));
            }
            
            if (departmentDropdown.value === 'other') {
                departmentDropdown.dispatchEvent(new Event('change'));
            }
            
            if (headIdDropdown.value === 'other') {
                toggleBudgetHeadCustom('other');
            }
            
            // Set default date values
            const today = new Date().toISOString().split('T')[0];
            
            if (!document.getElementById('end_date').value) {
                document.getElementById('end_date').value = today;
            }
            
            if (!document.getElementById('start_date').value) {
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                document.getElementById('start_date').value = thirtyDaysAgo.toISOString().split('T')[0];
            }
            
            // Set current date and copyright year
            const dateOptions = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            document.getElementById('current-date').textContent = 
                new Date().toLocaleDateString('en-US', dateOptions);
                
            document.getElementById('copyright-year').textContent = new Date().getFullYear();
        });

        // Print Budget Report Function
        function printBudgetReport(type) {
            let reportContent = '';
            switch(type) {
                case 'ncrb':
                    reportContent = document.getElementById('printable-report-ncrb').innerHTML;
                    break;
                case 'rpctc':
                    reportContent = document.getElementById('printable-report-rpctc').innerHTML;
                    break;
                default:
                    reportContent = document.getElementById('printable-report-all').innerHTML;
            }
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Training Budget Report</title>
                        <style>
                            body { 
                                margin: 1cm; 
                                font-family: "Times New Roman", serif;
                                color: #000;
                            }
                            .print-header {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                margin-bottom: 20px;
                                border-bottom: 2px solid #003366;
                                padding-bottom: 15px;
                            }
                            .print-title-container {
                                text-align: center;
                            }
                            .print-title {
                                margin: 0;
                                font-size: 18pt;
                                color: #003366;
                            }
                            .print-subtitle {
                                margin: 5px 0 0 0;
                                font-size: 14pt;
                                font-weight: normal;
                                color: #0066cc;
                            }
                            .department-title {
                                font-size: 12pt;
                                color: #555;
                                margin-top: 5px;
                            }
                            .print-logo {
                                height: 70px;
                            }
                            .print-meta {
                                display: flex;
                                justify-content: space-between;
                                margin-bottom: 20px;
                                font-size: 10pt;
                            }
                            .printable-report-table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-top: 20px;
                                font-size: 10pt;
                            }
                            .printable-report-table th {
                                background: #003366;
                                color: white;
                                padding: 8px;
                                text-align: left;
                                border: 1px solid #ddd;
                            }
                            .printable-report-table td {
                                padding: 8px;
                                border: 1px solid #ddd;
                                vertical-align: top;
                            }
                            .expense-detail-row td {
                                padding: 4px 8px;
                                font-size: 9pt;
                            }
                            .expense-detail-row:nth-child(even) {
                                background-color: #f9f9f9;
                            }
                            .expense-details-header {
                                background-color: #e6f0ff;
                                font-weight: bold;
                            }
                            .text-right {
                                text-align: right;
                            }
                            .text-bold {
                                font-weight: bold;
                            }
                            .summary-row {
                                background-color: #f0f0f0;
                            }
                            .utilization-cell {
                                text-align: center;
                            }
                            .print-footer {
                                margin-top: 30px;
                                text-align: center;
                                font-size: 9pt;
                                color: #666;
                            }
                            @media print {
                                @page {
                                    margin: 0.5cm;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        ${reportContent}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
    </script>
</body>
</html>