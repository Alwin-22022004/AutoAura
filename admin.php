<!DOCTYPE html>
<?php
session_start();
require_once 'db_connect.php';

// Enhanced admin authentication with role check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache"); 
    header("Location: index.php");
    exit();
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// Display status message if exists
if (isset($_SESSION['status_message'])) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                showAlert('" . addslashes($_SESSION['status_message']) . "', 'success');
            });
          </script>";
    unset($_SESSION['status_message']);
}

// Get statistics for the dashboard
$stats_sql = "SELECT 
    COUNT(*) as total_cars,
    SUM(CASE WHEN is_active = 1 AND status = 'approved' THEN 1 ELSE 0 END) as active_cars,
    SUM(CASE WHEN rc_document IS NULL OR rc_document = '' THEN 1 ELSE 0 END) as pending_rc_docs,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_approvals
FROM cars";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get total users count
$sql = "SELECT COUNT(id) as total_users FROM users";
$result = $conn->query($sql);
$userCount = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userCount = $row["total_users"];
}

// Get car statistics
$car_stats_sql = "SELECT 
    COUNT(*) as total_cars,
    SUM(CASE WHEN is_active = 1 AND status = 'approved' THEN 1 ELSE 0 END) as active_cars,
    SUM(CASE WHEN rc_document IS NULL OR rc_document = '' THEN 1 ELSE 0 END) as pending_rc_docs,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_approvals
FROM cars";
$car_stats_result = $conn->query($car_stats_sql);
$car_stats = $car_stats_result->fetch_assoc();

// Handle car status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['car_id'])) {
    $car_id = filter_var($_POST['car_id'], FILTER_VALIDATE_INT);
    $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
    
    if ($car_id === false || $action !== 'toggle_status') {
        $_SESSION['error_message'] = "Invalid request parameters";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get current status
        $stmt = $conn->prepare("SELECT is_active FROM cars WHERE id = ?");
        $stmt->bind_param("i", $car_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $car = $result->fetch_assoc();
        
        if (!$car) {
            throw new Exception("Car not found");
        }
        
        // Toggle status
        $new_status = $car['is_active'] ? 0 : 1;
        $stmt = $conn->prepare("UPDATE cars SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $car_id);
        
        if ($stmt->execute()) {
            // Log action
            $log_entry = sprintf("[%s] Admin %d toggled car #%d visibility to %s\n", 
                date('Y-m-d H:i:s'), 
                $_SESSION['user_id'], 
                $car_id,
                $new_status ? 'active' : 'inactive'
            );
            error_log($log_entry, 3, 'logs/admin_actions.log');
            
            $conn->commit();
            $_SESSION['success_message'] = "Car visibility updated successfully";
        } else {
            throw new Exception("Database error");
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Admin Error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while updating car status";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle car listing approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['car_id'])) {
    include 'db_connection.php';
    $car_id = intval($_POST['car_id']);
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'reject') {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        $update_sql = "UPDATE cars SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $car_id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Car listing #$car_id has been $new_status successfully!", "new_status" => ucfirst($new_status)]);
        } else {
            echo json_encode(["success" => false, "message" => "Error updating status: " . $conn->error]);
        }
        $stmt->close();
        exit;
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    try {
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User deleted successfully";
        } else {
            throw new Exception("Database error");
        }
    } catch (Exception $e) {
        error_log("Admin Error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while deleting the user";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Premium Car Rental</title>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Syncopate:wght@400;700&display=swap"
      rel="stylesheet">
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      }

      :root {
        --primary: #1a237e;
        --secondary: #283593;
        --accent: #3949ab;
        --light: #f5f6fa;
        --danger: #d32f2f;
        --success: #2e7d32;
        --text-primary: #2c3e50;
        --text-secondary: #546e7a;
        --border-color: #e0e0e0;
      }

      body {
        display: flex;
        background: var(--light);
        color: var(--text-primary);
      }

      /* Sidebar */
      .sidebar {
        width: 250px;
        height: 100vh;
        background: linear-gradient(180deg,  #f5b754 0%,  #f5b754 100%);
        padding: 1rem;
        position: fixed;
        color: white;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
      }

      .menu-items {
        flex: 1;
      }

      .brand {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .brand img {
        width: 150px;
        height: auto;
        object-fit: contain;
      }

      @media (max-width: 768px) {
        .brand img {
          width: 50px;
        }
      }

      .menu-item {
        padding: 0.8rem 1rem;
        margin: 0.5rem 0;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s;
      }

      .menu-item:hover,
      .menu-item.active {
        background: var(--secondary);
      }

      .menu-item i {
        margin-right: 0.8rem;
      }

      /* Profile Dropdown Styles */
      .user-info {
        position: relative;
        display: flex;
        align-items: center;
        gap: 1rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
        transition: background 0.3s;
      }

      .user-info:hover {
        background: rgba(0, 0, 0, 0.05);
      }

      .user-info img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #f5b754;
        transition: transform 0.3s;
      }

      .user-info:hover img {
        transform: scale(1.1);
      }

      .dropdown-menu {
        position: absolute;
        top: calc(100% + 5px);
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        padding: 0.5rem 0;
        min-width: 200px;
        display: none;
        z-index: 1000;
        border: 1px solid rgba(0, 0, 0, 0.1);
      }

      .dropdown-menu.show {
        display: block;
        animation: slideDown 0.3s ease;
      }

      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .dropdown-menu a {
        display: flex;
        align-items: center;
        padding: 0.8rem 1.2rem;
        text-decoration: none;
        color: var(--text-primary);
        transition: all 0.3s;
        font-size: 0.95rem;
      }

      .dropdown-menu a:hover {
        background: rgba(245, 183, 84, 0.1);
        color: #f5b754;
      }

      .dropdown-menu a:hover i {
        transform: translateX(3px);
      }

      .dropdown-menu a:last-child {
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        margin-top: 0.5rem;
        color: var(--danger);
      }

      .dropdown-menu a:last-child i {
        color: var(--danger);
      }

      .dropdown-menu a:last-child:hover {
        background: rgba(211, 47, 47, 0.1);
        color: var(--danger);
      }

      @media (max-width: 768px) {
        .user-info span {
          display: none;
        }

        .dropdown-menu {
          position: fixed;
          top: unset;
          bottom: 0;
          left: 0;
          right: 0;
          width: 100%;
          border-radius: 15px 15px 0 0;
          padding: 1rem 0;
          animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
          from {
            opacity: 0;
            transform: translateY(100%);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .dropdown-menu a {
          padding: 1rem 2rem;
          font-size: 1.1rem;
        }

        .dropdown-menu i {
          font-size: 1.2rem;
          width: 24px;
        }
      }

      /* Main Content */
      .main-content {
        margin-left: 250px;
        flex: 1;
        padding: 2rem;
      }

      .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        background: white;
        padding: 1rem 2rem;
        margin: -2rem -2rem 2rem -2rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      }

      .breadcrumb {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
      }

      .search-bar {
        display: flex;
        gap: 1rem;
      }

      .search-bar input {
        padding: 0.5rem 1rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        width: 300px;
      }

      /* Dashboard Cards */
      .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
      }

      .card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
      }

      .card.clickable {
        cursor: pointer;
        position: relative;
        overflow: hidden;
      }

      .card.clickable::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(196, 164, 124, 0.1);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .card.clickable:hover::after {
        opacity: 1;
      }

      .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      }

      .card-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
      }

      .card-content {
        text-align: right;
        position: relative;
        padding: 1rem;
        border-radius: 8px;
        transition: all 0.3s ease;
      }

      .card:hover .card-content {
        background: linear-gradient(145deg, rgba(245, 183, 84, 0.1) 0%, rgba(57, 73, 171, 0.1) 100%);
        transform: translateY(-5px);
      }

      .card:hover .card-number {
        color: var(--accent);
        transform: scale(1.1);
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
      }

      .card-number {
        font-size: 2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        transition: all 0.3s ease;
      }

      .card:hover .card-label {
        letter-spacing: 2px;
        color: var(--accent);
      }

      .card-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
      }

      @keyframes pulse {
        0% {
          transform: scale(1);
        }
        50% {
          transform: scale(1.05);
        }
        100% {
          transform: scale(1);
        }
      }

      .card:hover .card-icon {
        animation: pulse 1.5s infinite;
        box-shadow: 0 0 15px rgba(57, 73, 171, 0.3);
      }

      /* Alert Messages */
      .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 5px;
        font-weight: 500;
      }

      .alert.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
      }

      .alert.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
      }

      /* Tables */
      .table-container {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th,
      td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #ddd;
      }

      th {
        background: var(--light);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
      }

      .status {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.9rem;
      }

      .status.pending {
        background: #fff3cd;
        color: #856404;
      }

      .status.approved {
        background: #d4edda;
        color: #155724;
      }

      .status.rejected {
        background: #f8d7da;
        color: #721c24;
      }

      .action-buttons {
        display: flex;
        gap: 0.5rem;
      }

      .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
      }

      .btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      }

      .btn-approve {
        background-color: #28a745;
        color: white;
      }

      .btn-approve:hover {
        background-color: #218838;
        transform: translateY(-2px);
      }

      .btn-reject {
        background-color: #dc3545;
        color: white;
        margin-left: 8px;
      }

      .btn-reject:hover {
        background-color: #c82333;
        transform: translateY(-2px);
      }

      /* Loading State */
      .btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
      }

      .btn.loading::after {
        content: "...";
      }

      tbody tr:hover {
        background-color: rgba(57, 73, 171, 0.1);
        transition: background-color 0.3s ease;
      }

      /* Sections */
      .section {
        display: none;
      }

      .section.active {
        display: block;
      }

      @media (max-width: 768px) {
        .sidebar {
          width: 70px;
          padding: 1rem 0.5rem;
        }

        .brand,
        .menu-item span,
        .logout-btn span {
          display: none;
        }

        .main-content {
          margin-left: 70px;
        }
      }

      .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
        font-size: 0.9em;
      }

      .feature-list li {
        margin-bottom: 4px;
        padding: 2px 0;
        border-bottom: 1px solid #eee;
      }

      .feature-list li:last-child {
        border-bottom: none;
      }

      .feature-text {
        white-space: pre-line;
        font-size: 0.9em;
      }

      /* User Management Styles */
      #users {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
      }

      #users h2 {
        color: #333;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
      }

      #users h2 i {
        color: #c4a47c;
      }

      #users .search-box {
        position: relative;
        margin-bottom: 20px;
      }

      #users .search-box input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s;
      }

      #users .search-box input:focus {
        border-color: #c4a47c;
        outline: none;
        box-shadow: 0 0 5px rgba(196, 164, 124, 0.2);
      }

      #users .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
      }

      #users .table th {
        background: #f8f9fa;
        color: #495057;
        font-weight: 600;
        padding: 12px 15px;
        text-align: left;
        border-bottom: 2px solid #dee2e6;
      }

      #users .table td {
        padding: 12px 15px;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
      }

      #users .table tr:hover {
        background-color: #f8f9fa;
      }

      #users .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
      }

      #users .btn-danger:hover {
        background: #c82333;
      }

      #users .btn-danger i {
        font-size: 14px;
      }

      #users .text-center {
        text-align: center;
      }

      @media (max-width: 768px) {
        #users .table {
          display: block;
          overflow-x: auto;
          white-space: nowrap;
        }
        
        #users .table th,
        #users .table td {
          padding: 10px;
        }
      }

      /* Add these styles for smooth transitions */
      .btn-enable, .btn-disable {
        transition: all 0.3s ease;
      }

      .btn-enable {
        background-color: #28a745;
        color: white;
      }

      .btn-disable {
        background-color: #dc3545;
        color: white;
      }

      .btn-enable:hover, .btn-disable:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      }

      tr {
        transition: background-color 0.3s ease;
      }

      /* Alert styles */
      #alertContainer {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
      }

      .alert {
        padding: 15px 25px;
        margin-bottom: 10px;
        border: 1px solid transparent;
        border-radius: 4px;
        animation: slideIn 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      }

      @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }

      @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(-20px); }
      }

      .alert.success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
      }

      .alert.error {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
      }
    </style>
  </head>
  <body>
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="brand">
        <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="Car Rental Logo">
      </div>
      <div class="menu-items">
        <div class="menu-item active" data-section="dashboard">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </div>
        <div class="menu-item" data-section="users">
          <i class="fas fa-users"></i>
          <span>Users</span>
        </div>
        <div class="menu-item" data-section="inventory">
          <i class="fas fa-car"></i>
          <span>Inventory</span>
        </div>
        <div class="menu-item" data-section="workshops">
          <i class="fas fa-wrench"></i>
          <span>Workshops</span>
        </div>
        <div class="menu-item" data-section="promotions">
          <i class="fas fa-tag"></i>
          <span>Promotions</span>
        </div>
        <div class="menu-item" data-section="complaints">
          <i class="fas fa-comment-alt"></i>
          <span>Complaints</span>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Header -->
      <div class="header">
        <div class="breadcrumb">
          Dashboard / <span class="current-page">Overview</span>
        </div>
        <h1 class="page-title" style="margin-bottom: 2rem; color: var(--text-primary)">
          Dashboard Overview
        </h1>
        <div class="search-bar">
          <input type="text" placeholder="Search..." />
        </div>
        <div class="user-info" id="profileDropdown">
          <span>Admin User</span>
          <img src="assets/profile.jpg" alt="Admin" />
          <div class="dropdown-menu">
            <a href="#"><i class="fas fa-user"></i>My Profile</a>
            <a href="#"><i class="fas fa-cog"></i>Account Settings</a>
            <a href="#"><i class="fas fa-bell"></i>Notifications</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
          </div>
        </div>
      </div>

      <!-- Dashboard Section -->
      <div class="section active" id="dashboard">
        <div class="dashboard-cards">
          <div class="card clickable" onclick="window.location.href='user-list.php'">
            <div class="card-header">
              <div class="card-icon" style="background: var(--accent)">
                <i class="fas fa-users"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number"><?php echo $userCount; ?></div>
              <div class="card-label">Total Users</div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon" style="background: var(--success)">
                <i class="fas fa-car"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number"><?php echo $stats['active_cars']; ?></div>
              <div class="card-label">Active Cars</div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon" style="background: var(--danger)">
                <i class="fas fa-file-pdf"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number"><?php echo $stats['pending_rc_docs']; ?></div>
              <div class="card-label">Pending RC Docs</div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon" style="background: var(--accent)">
                <i class="fas fa-clock"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number"><?php echo $stats['pending_approvals']; ?></div>
              <div class="card-label">Pending Approvals</div>
            </div>
          </div>
        </div>

        <div class="table-container">
            <h2>Car Listing Approvals</h2><br>
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['car_id'])) {
                include 'db_connection.php';
                $car_id = intval($_POST['car_id']);
                $action = $_POST['action'];

                if ($action === 'approve' || $action === 'reject') {
                    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
                    $update_sql = "UPDATE cars SET status = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("si", $new_status, $car_id);

                    if ($stmt->execute()) {
                        echo json_encode(["success" => true, "message" => "Car listing #$car_id has been $new_status successfully!", "new_status" => ucfirst($new_status)]);
                    } else {
                        echo json_encode(["success" => false, "message" => "Error updating status: " . $conn->error]);
                    }
                    $stmt->close();
                    exit;
                }
            }

            $sql = "SELECT * FROM cars ORDER BY id DESC";
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0): ?>
                <div id="alertContainer"></div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Car Name</th>
                            <th>Description</th>
                            <th>Features</th>
                            <th>Price</th>
                            <th>Images</th>
                            <th>RC Document</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($car = $result->fetch_assoc()): ?>
                            <tr id="row-<?php echo $car['id']; ?>">
                                <td><?php echo $car['id']; ?></td>
                                <td><?php echo htmlspecialchars($car['car_name']); ?></td>
                                <td><?php echo htmlspecialchars($car['car_description']); ?></td>
                                <td>
                                    <?php 
                                    $features = json_decode($car['car_features'], true);
                                    if ($features && is_array($features)) {
                                        echo "<ul class='feature-list'>";
                                        foreach ($features as $key => $value) {
                                            echo "<li><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> " . htmlspecialchars($value) . "</li>";
                                        }
                                        echo "</ul>";
                                    } else {
                                        echo "<div class='feature-text'>" . nl2br(htmlspecialchars($car['car_features'])) . "</div>";
                                    }
                                    ?>
                                </td>
                                <td>₹<?php echo number_format($car['price'], 2); ?></td>
                                <td class="car-images">
                                    <?php 
                                    $images = json_decode($car['images'], true);
                                    if ($images && is_array($images)) {
                                        // Display main image
                                        if (!empty($images['main_image'])) {
                                            echo '<img src="' . htmlspecialchars($images['main_image']) . '" width="100" alt="Main Car Image" style="margin: 2px;">';
                                        }
                                        
                                        // Display thumbnails
                                        if (!empty($images['thumbnails']) && is_array($images['thumbnails'])) {
                                            foreach ($images['thumbnails'] as $thumbnail) {
                                                if (!empty($thumbnail)) {
                                                    echo '<img src="' . htmlspecialchars($thumbnail) . '" width="100" alt="Car Thumbnail" style="margin: 2px;">';
                                                }
                                            }
                                        }
                                    } else {
                                        echo "<p>No Images Available</p>";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($car['rc_document'])): ?>
                                        <a href="<?php echo htmlspecialchars($car['rc_document']); ?>" target="_blank" class="btn btn-info">
                                            <i class="fas fa-file-pdf"></i> View RC Book
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No RC Document</span>
                                    <?php endif; ?>
                                </td>
                                <td id="status-<?php echo $car['id']; ?>">
                                    <span class="status-label <?php echo htmlspecialchars($car['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($car['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($car['status'] === 'pending'): ?>
                                        <div class="action-buttons">
                                            <button class="btn btn-approve" onclick="updateCarStatus(<?php echo $car['id']; ?>, 'approve')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-reject" onclick="updateCarStatus(<?php echo $car['id']; ?>, 'reject')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="content-section">
                    <p>No car listings available.</p>
                </div>
            <?php endif; ?>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        function updateCarStatus(carId, action) {
            const row = $(`#row-${carId}`);
            const actionButtons = row.find('.action-buttons');
            const statusCell = $(`#status-${carId}`);

            if (!confirm(`Are you sure you want to ${action} this car listing? This action cannot be undone.`)) return;

            // Add loading state
            actionButtons.css('opacity', '0.5');
            actionButtons.find('button').prop('disabled', true);

            $.ajax({
                url: 'car_status_update.php',
                method: 'POST',
                data: {
                    car_id: carId,
                    action: action,
                    csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update status cell with animation
                        statusCell.fadeOut(300, function() {
                            const statusHtml = `<span class="status ${response.new_status.toLowerCase()}">${response.new_status}</span>`;
                            $(this).html(statusHtml).fadeIn(300);
                        });

                        // Remove action buttons after successful update
                        actionButtons.fadeOut(300);

                        // Show success message
                        showAlert('success', response.message);

                        // Update dashboard counters
                        updateDashboardCounters(action);
                    } else {
                        showAlert('error', response.message || 'Error updating car status');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    showAlert('error', 'An error occurred while updating the car status');
                },
                complete: function() {
                    // Reset loading state
                    actionButtons.css('opacity', '1');
                    actionButtons.find('button').prop('disabled', false);
                }
            });
        }

        // Function to update dashboard counters
        function updateDashboardCounters(action) {
            const pendingApprovals = document.querySelector('.card-number');
            const currentPending = parseInt(pendingApprovals.textContent);
            pendingApprovals.textContent = Math.max(0, currentPending - 1);
            
            if (action === 'approve') {
                const activeCards = document.querySelectorAll('.card-number')[1];
                const currentActive = parseInt(activeCards.textContent);
                activeCards.textContent = currentActive + 1;
            }
        }

        // Enhanced alert system with queue
        const alertQueue = [];
        let alertProcessing = false;

        function showAlert(type, message) {
            alertQueue.push({ type, message });
            if (!alertProcessing) processAlertQueue();
        }

        function processAlertQueue() {
            if (alertQueue.length === 0) {
                alertProcessing = false;
                return;
            }
            
            alertProcessing = true;
            const { type, message } = alertQueue.shift();
            
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert ${type}`;
            alert.textContent = message;
            
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => {
                    alert.remove();
                    processAlertQueue();
                }, 300);
            }, 3000);
        }
        </script>

        <style>
        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 500;
            text-transform: capitalize;
            animation: fadeIn 0.3s ease;
        }

        .status.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status.approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        </style>

        <style>
            @keyframes fadeOut {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(-20px); }
            }

            #alertContainer {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
            }

            .alert {
                padding: 15px 25px;
                margin-bottom: 10px;
                border: 1px solid transparent;
                border-radius: 4px;
                animation: slideIn 0.3s ease;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            }

            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }

            .alert.success {
                color: #155724;
                background-color: #d4edda;
                border-color: #c3e6cb;
            }

            .alert.error {
                color: #721c24;
                background-color: #f8d7da;
                border-color: #f5c6cb;
            }

            .action-buttons {
                display: flex;
                gap: 8px;
                justify-content: center;
            }

            .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 500;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                transition: all 0.3s ease;
            }

            .btn-approve {
                background-color: #28a745;
                color: white;
            }

            .btn-approve:hover {
                background-color: #218838;
                transform: translateY(-2px);
            }

            .btn-reject {
                background-color: #dc3545;
                color: white;
            }

            .btn-reject:hover {
                background-color: #c82333;
                transform: translateY(-2px);
            }
        </style>

      </div>

      <!-- Other Sections -->
      <div class="section" id="users">
        <h2><i class="fas fa-users"></i> User Management</h2>
        
        <div class="search-box" style="margin-bottom: 20px;">
            <input type="text" id="userSearch" placeholder="Search users..." 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <?php
        $user_sql = "SELECT * FROM users ORDER BY id DESC";
        $user_result = $conn->query($user_sql);
        ?>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user_result && $user_result->num_rows > 0): ?>
                        <?php while($user = $user_result->fetch_assoc()): ?>
                        <tr class="user-row" data-user-id="<?php echo $user['id']; ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                            <td>
                                <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
      </div>

      <div class="section" id="inventory">
        <h2>Car Inventory Management</h2><br>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
          <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['car_id'])) {
                include 'db_connection.php';
                $car_id = intval($_POST['car_id']);
                $action = $_POST['action'];

                if ($action === 'delete') {
                    $delete_sql = "DELETE FROM cars WHERE id = ?";
                    $stmt = $conn->prepare($delete_sql);
                    $stmt->bind_param("i", $car_id);

                    if ($stmt->execute()) {
                        echo json_encode(["success" => true, "message" => "Car listing $car_id has been deleted successfully!"]);
                    } else {
                        echo json_encode(["success" => false, "message" => "Error deleting car: " . $conn->error]);
                    }
                    $stmt->close();
                    exit;
                } elseif ($action === 'toggle_status') {
                    $toggle_sql = "UPDATE cars SET is_active = NOT is_active WHERE id = ?";
                    $stmt = $conn->prepare($toggle_sql);
                    $stmt->bind_param("i", $car_id);

                    if ($stmt->execute()) {
                        // Get the new status
                        $status_sql = "SELECT is_active FROM cars WHERE id = ?";
                        $status_stmt = $conn->prepare($status_sql);
                        $status_stmt->bind_param("i", $car_id);
                        $status_stmt->execute();
                        $result = $status_stmt->get_result();
                        $new_status = $result->fetch_assoc()['is_active'];
                        
                        // Store message in session
                        $_SESSION['status_message'] = "Car listing $car_id has been " . ($new_status ? "enabled" : "disabled") . " successfully!";
                        
                        // Redirect to the same page
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        echo json_encode(["success" => false, "message" => "Error updating status: " . $conn->error]);
                    }
                    $stmt->close();
                    exit;
                }
            }

            // Modified query to only show approved cars
            $sql = "SELECT * FROM cars WHERE status = 'approved' ORDER BY id DESC";
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0): ?>
                <div id="alertContainer"></div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Car Name</th>
                            <th>Description</th>
                            <th>Features</th>
                            <th>Price</th>
                            <th>Images</th>
                            <th>RC Document</th>
                            <th>Status</th>
                            <th>Visibility</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($car = $result->fetch_assoc()): ?>
                            <tr id="row-<?php echo $car['id']; ?>">
                                <td><?php echo $car['id']; ?></td>
                                <td><?php echo htmlspecialchars($car['car_name']); ?></td>
                                <td><?php echo htmlspecialchars($car['car_description']); ?></td>
                                <td>
                                    <?php 
                                    $features = json_decode($car['car_features'], true);
                                    if ($features && is_array($features)) {
                                        echo "<ul class='feature-list'>";
                                        foreach ($features as $key => $value) {
                                            echo "<li><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> " . htmlspecialchars($value) . "</li>";
                                        }
                                        echo "</ul>";
                                    } else {
                                        echo "<div class='feature-text'>" . nl2br(htmlspecialchars($car['car_features'])) . "</div>";
                                    }
                                    ?>
                                </td>
                                <td>₹<?php echo number_format($car['price'], 2); ?></td>
                                <td class="car-images">
                                    <?php 
                                    $images = json_decode($car['images'], true);
                                    if ($images && is_array($images)) {
                                        // Display main image
                                        if (!empty($images['main_image'])) {
                                            echo '<img src="' . htmlspecialchars($images['main_image']) . '" width="100" alt="Main Car Image" style="margin: 2px;">';
                                        }
                                        
                                        // Display thumbnails
                                        if (!empty($images['thumbnails']) && is_array($images['thumbnails'])) {
                                            foreach ($images['thumbnails'] as $thumbnail) {
                                                if (!empty($thumbnail)) {
                                                    echo '<img src="' . htmlspecialchars($thumbnail) . '" width="100" alt="Car Thumbnail" style="margin: 2px;">';
                                                }
                                            }
                                        }
                                    } else {
                                        echo "<p>No Images Available</p>";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($car['rc_document'])): ?>
                                        <a href="<?php echo htmlspecialchars($car['rc_document']); ?>" target="_blank" class="btn btn-info">
                                            <i class="fas fa-file-pdf"></i> View RC Book
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No RC Document</span>
                                    <?php endif; ?>
                                </td>
                                <td id="status-<?php echo $car['id']; ?>">
                                    <span class="status-label <?php echo htmlspecialchars($car['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($car['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button 
                                        class="btn <?php echo $car['is_active'] ? 'btn-disable' : 'btn-enable'; ?>"
                                        onclick="return toggleCarStatus(<?php echo $car['id']; ?>)"
                                        data-status="<?php echo $car['is_active']; ?>"
                                    >
                                        <i class="fas <?php echo $car['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                        <?php echo $car['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-delete" onclick="deleteCar(<?php echo $car['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="content-section">
                    <p>No approved car listings available.</p>
                </div>
            <?php endif; ?>
        </div>
      </div>

      <div class="section" id="workshops">
        <h2>Workshop Management</h2>
        <!-- Workshop management content -->
      </div>

      <div class="section" id="promotions">
        <h2>Promotions</h2>
        <!-- Promotions content -->
      </div>

      <div class="section" id="complaints">
        <h2>Complaint Handling</h2>
        <!-- Complaints content -->
      </div>
    </div>

    <?php
    // Close the database connection at the end of the file
    $conn->close();
    ?>

    <script>
      // Navigation functionality
      const menuItems = document.querySelectorAll(".menu-item");
      const sections = document.querySelectorAll(".section");

      menuItems.forEach((item) => {
        item.addEventListener("click", () => {
          // Remove active class from all menu items
          menuItems.forEach((i) => i.classList.remove("active"));
          // Add active class to clicked item
          item.classList.add("active");

          // Hide all sections
          sections.forEach((section) => section.classList.remove("active"));
          // Show selected section
          const sectionId = item.getAttribute("data-section");
          document.getElementById(sectionId).classList.add("active");
        });
      });

      // Profile Dropdown functionality with backdrop
      const profileDropdown = document.getElementById('profileDropdown');
      const dropdownMenu = profileDropdown.querySelector('.dropdown-menu');
      let backdrop;

      function createBackdrop() {
        backdrop = document.createElement('div');
        backdrop.style.position = 'fixed';
        backdrop.style.top = '0';
        backdrop.style.left = '0';
        backdrop.style.right = '0';
        backdrop.style.bottom = '0';
        backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        backdrop.style.opacity = '0';
        backdrop.style.transition = 'opacity 0.3s';
        backdrop.style.zIndex = '999';
        document.body.appendChild(backdrop);
        
        // Animate backdrop
        setTimeout(() => {
          backdrop.style.opacity = '1';
        }, 10);
      }

      function removeBackdrop() {
        if (backdrop) {
          backdrop.style.opacity = '0';
          setTimeout(() => {
            backdrop.remove();
          }, 300);
        }
      }

      profileDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
        const isShowing = dropdownMenu.classList.contains('show');
        
        if (isShowing) {
          dropdownMenu.classList.remove('show');
          removeBackdrop();
        } else {
          dropdownMenu.classList.add('show');
          if (window.innerWidth <= 768) {
            createBackdrop();
          }
        }
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!profileDropdown.contains(e.target)) {
          dropdownMenu.classList.remove('show');
          removeBackdrop();
        }
      });

      // Action buttons functionality
      const actionButtons = document.querySelectorAll(".action-buttons button");
      actionButtons.forEach((button) => {
        button.addEventListener("click", async (e) => {
          const button = e.target;
          button.classList.add("loading");
          button.disabled = true;

          // Simulate API call
          await new Promise((resolve) => setTimeout(resolve, 800));

          const action = button.classList.contains("btn-approve")
            ? "approved"
            : "rejected";
          const row = button.closest("tr");
          const statusCell = row.querySelector(".status");

          statusCell.className = `status ${action}`;
          statusCell.textContent =
            action.charAt(0).toUpperCase() + action.slice(1);

          button.classList.remove("loading");
          button.disabled = false;
        });
      });

      // Search functionality
      const searchInput = document.querySelector(".search-bar input");
      searchInput.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const tableRows = document.querySelectorAll("tbody tr");

        tableRows.forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? "" : "none";
        });
      });
    </script>

    <script>
      async function deleteUser(userId) {
          if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
              try {
                  const response = await fetch('delete_user.php', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                      body: `user_id=${userId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
                  });
                  const data = await response.json();
                  if (data.success) {
                      location.reload();
                  } else {
                      alert(data.message || 'Error deleting user');
                  }
              } catch (error) {
                  alert('Error deleting user');
              }
          }
      }
      </script>

      <script>
      function toggleCarStatus(carId) {
          // Prevent default form submission behavior
          event.preventDefault();
          
          if (!confirm('Are you sure you want to change the visibility status of this car?')) return;

          // Create form data
          const formData = new FormData();
          formData.append('car_id', carId);
          formData.append('action', 'toggle_status');
          formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

          // Send request to the same page
          fetch(window.location.href, { 
              method: 'POST',
              body: formData
          })
          .then(response => {
              // Reload the page after successful response
              window.location.reload();
          })
          .catch(error => {
              console.error('Error:', error);
              showAlert('error', 'An error occurred while updating the car status');
          });
      }

      // Alert function for showing notifications
      function showAlert(message, type) {
          const alertContainer = document.getElementById('alertContainer');
          const alert = document.createElement('div');
          alert.className = `alert ${type}`;
          alert.textContent = message;
          
          alertContainer.appendChild(alert);
          
          // Remove the alert after 3 seconds
          setTimeout(() => {
              alert.style.animation = 'fadeOut 0.3s ease forwards';
              setTimeout(() => alert.remove(), 300);
          }, 3000);
      }
      </script>

      <style>
      /* Button styles */
      .btn-enable, .btn-disable {
          transition: all 0.3s ease;
      }

      .btn-enable {
          background-color: #28a745;
          color: white;
      }

      .btn-disable {
          background-color: #dc3545;
          color: white;
      }

      .btn-enable:hover, .btn-disable:hover {
          transform: translateY(-2px);
          box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      }

      /* Row transition */
      tr {
          transition: background-color 0.3s ease;
      }

      /* Alert styles */
      #alertContainer {
          position: fixed;
          top: 20px;
          right: 20px;
          z-index: 1000;
      }

      .alert {
          padding: 15px 25px;
          margin-bottom: 10px;
          border: 1px solid transparent;
          border-radius: 4px;
          animation: slideIn 0.3s ease;
          box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      }

      @keyframes slideIn {
          from { transform: translateX(100%); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
      }

      @keyframes fadeOut {
          from { opacity: 1; transform: translateX(0); }
          to { opacity: 0; transform: translateX(-20px); }
      }

      .alert.success {
          color: #155724;
          background-color: #d4edda;
          border-color: #c3e6cb;
      }

      .alert.error {
          color: #721c24;
          background-color: #f8d7da;
          border-color: #f5c6cb;
      }
      </style>

      <!-- Add CSRF token to forms -->
      <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
      </form>

      <script>
      // Add CSRF token to AJAX requests
      $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': '<?php echo $_SESSION['csrf_token']; ?>'
          }
      });

      // Improved error handling
      $(document).ajaxError(function(event, jqXHR, settings, error) {
          showAlert('error', 'A network error occurred. Please try again.');
      });
      </script>

      <script>
      // Add search functionality
      document.getElementById('userSearch').addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          const rows = document.getElementsByClassName('user-row');
          
          Array.from(rows).forEach(row => {
              const text = row.textContent.toLowerCase();
              row.style.display = text.includes(searchTerm) ? '' : 'none';
          });
      });

      function deleteUser(userId) {
          const userRow = document.querySelector(`tr[data-user-id="${userId}"]`);
          const userName = userRow.querySelector('td:nth-child(2)').textContent;
          const userEmail = userRow.querySelector('td:nth-child(3)').textContent;
          
          if (confirm(`Are you sure you want to delete user:\n\nName: ${userName}\nEmail: ${userEmail}\n\nThis action cannot be undone.`)) {
              fetch('delete_user.php', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: `user_id=${userId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
              })
              .then(response => response.json())
              .then(data => {
                  if (data.success) {
                      userRow.style.animation = 'fadeOut 0.3s ease forwards';
                      setTimeout(() => {
                          userRow.remove();
                          // Update user count in dashboard
                          const userCountElement = document.querySelector('.card-number');
                          const currentCount = parseInt(userCountElement.textContent);
                          userCountElement.textContent = currentCount - 1;
                      }, 300);
                      showAlert('success', data.message);
                  } else {
                      showAlert('error', data.message || 'Error deleting user');
                  }
              })
              .catch(error => {
                  console.error('Error:', error);
                  showAlert('error', 'An error occurred while deleting the user');
              });
          }
      }

      function toggleCarStatus(carId) {
          // Prevent default form submission behavior
          event.preventDefault();
          
          if (!confirm('Are you sure you want to change the visibility status of this car?')) return;

          // Create form data
          const formData = new FormData();
          formData.append('car_id', carId);
          formData.append('action', 'toggle_status');
          formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

          // Send request to the same page
          fetch(window.location.href, { 
              method: 'POST',
              body: formData
          })
          .then(response => {
              // Reload the page after successful response
              window.location.reload();
          })
          .catch(error => {
              console.error('Error:', error);
              showAlert('error', 'An error occurred while updating the car status');
          });
      }

      // Alert function for showing notifications
      function showAlert(type, message) {
          const alertContainer = document.getElementById('alertContainer');
          const alert = document.createElement('div');
          alert.className = `alert ${type}`;
          alert.textContent = message;
          
          alertContainer.appendChild(alert);
          
          // Remove the alert after 3 seconds
          setTimeout(() => {
              alert.style.animation = 'fadeOut 0.3s ease forwards';
              setTimeout(() => alert.remove(), 300);
          }, 3000);
      }
      </script>

      <div class="section" id="inventory">{{ ... }}
