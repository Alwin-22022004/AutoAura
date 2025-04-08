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

      /* Truncated content styles */
      .truncated {
        max-height: 60px;
        overflow: hidden;
        position: relative;
      }

      .show-more {
        color: #007bff;
        cursor: pointer;
        font-size: 0.9em;
        margin-top: 5px;
        display: inline-block;
      }

      .show-more:hover {
        text-decoration: underline;
      }

      .car-images.truncated {
        max-height: 100px;
      }

      .car-images img {
        margin: 2px;
        width: 100px;
        height: auto;
      }

      .feature-list {
        margin: 0;
        padding: 0;
        list-style: none;
      }

      .feature-list li {
        margin-bottom: 4px;
      }

      /* Truncated content styles */
      .truncate-content {
        position: relative;
        transition: max-height 0.3s ease;
      }

      .truncated {
        max-height: 60px;
        overflow: hidden;
      }

      .truncated::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 20px;
        background: linear-gradient(transparent, #fff);
        pointer-events: none;
      }

      .show-more {
        color: #007bff;
        cursor: pointer;
        font-size: 0.9em;
        padding: 4px 8px;
        margin-top: 5px;
        display: inline-block;
        border-radius: 4px;
        transition: all 0.2s ease;
        background: rgba(0, 123, 255, 0.1);
      }

      .show-more:hover {
        background: rgba(0, 123, 255, 0.2);
        text-decoration: none;
      }

      /* Car images styles */
      .car-images {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 8px;
        padding: 8px;
        transition: max-height 0.3s ease;
      }

      .car-images.truncated {
        max-height: 120px;
        overflow: hidden;
      }

      .car-images img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 4px;
        transition: transform 0.2s ease;
      }

      .car-images img:hover {
        transform: scale(1.05);
      }

      /* Features list styles */
      .feature-list {
        margin: 0;
        padding: 0;
        list-style: none;
      }

      .feature-list li {
        margin-bottom: 6px;
        padding: 4px 0;
        border-bottom: 1px solid #eee;
      }

      .feature-list li:last-child {
        border-bottom: none;
      }

      .feature-list strong {
        color: #555;
        margin-right: 8px;
      }

      /* Description styles */
      .car-description {
        line-height: 1.5;
        color: #444;
      }

      /* Table Container Styles */
      .table-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-height: 600px;
        overflow-y: auto;
      }

      .table-container table {
        width: 100%;
        border-collapse: collapse;
      }

      .table-container thead {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }

      .table-container tbody {
        border-top: 2px solid #eee;
      }

      /* Custom scrollbar styles */
      .table-container::-webkit-scrollbar {
        width: 8px;
      }

      .table-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
      }

      .table-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
      }

      .table-container::-webkit-scrollbar-thumb:hover {
        background: #555;
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
            <a href="pending_approvals.php" class="card-link" style="text-decoration: none; color: inherit;">
              <div class="card-content">
                <div class="card-number"><?php echo $stats['pending_approvals']; ?></div>
                <div class="card-label">Pending Approvals</div>
              </div>
            </a>
          </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        $(document).ready(function() {
            initializeTruncatedContent();
        });

        function initializeTruncatedContent() {
            // Initialize truncated content
            $('.truncate-content').each(function() {
                const content = $(this);
                if (content.height() > 60) {
                    content.addClass('truncated');
                    content.after('<span class="show-more">Show More</span>');
                }
            });

            // Initialize truncated images
            $('.car-images').each(function() {
                const images = $(this);
                if (images.height() > 100) {
                    images.addClass('truncated');
                    images.append('<div class="show-more">Show More Images</div>');
                }
            });

            // Handle show more clicks
            $(document).on('click', '.show-more', function() {
                const button = $(this);
                const content = button.prev();
                
                if (content.hasClass('truncated')) {
                    content.removeClass('truncated');
                    button.text('Show Less');
                } else {
                    content.addClass('truncated');
                    button.text(content.hasClass('car-images') ? 'Show More Images' : 'Show More');
                }
            });
        }

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

      </div>

      <!-- Other Sections -->
      <div class="section" id="users">
        <h2><i class="fas fa-users"></i> User Management</h2>
        
        <div class="search-box" style="margin-bottom: 20px;">
            <input type="text" id="userSearch" placeholder="Search users..." 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <?php
        $user_sql = "SELECT id, fullname, email, mobile, active FROM users ORDER BY id DESC";
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
                        <th>Status</th>
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
                                <span class="status-badge <?php echo $user['active'] === 'active' ? 'active' : 'blocked'; ?>">
                                    <?php echo ucfirst($user['active']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['active'] === 'active'): ?>
                                    <button onclick="updateUserStatus(<?php echo $user['id']; ?>, 'block')" class="btn btn-warning btn-sm">
                                        <i class="fas fa-ban"></i>Block
                                    </button>
                                <?php else: ?>
                                    <button onclick="updateUserStatus(<?php echo $user['id']; ?>, 'activate')" class="btn btn-success btn-sm">
                                        <i class="fas fa-check-circle"></i>Activate
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
            .status-badge {
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.9em;
                font-weight: 500;
            }
            .status-badge.active {
                background: #d4edda;
                color: #155724;
            }
            .status-badge.blocked {
                background: #f8d7da;
                color: #721c24;
            }
            .btn-warning {
                background: #ffc107;
                color: #000;
            }
            .btn-success {
                background: #28a745;
                color: white;
            }
            .btn-sm {
                padding: 5px 10px;
                font-size: 0.875rem;
            }
        </style>

        <script>
        function updateUserStatus(userId, action) {
            if (!confirm('Are you sure you want to ' + action + ' this user?')) {
                return;
            }

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', action);

            fetch('update_user_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the UI
                    const row = document.querySelector(`.user-row[data-user-id="${userId}"]`);
                    const statusBadge = row.querySelector('.status-badge');
                    const actionButton = row.querySelector('button');

                    if (action === 'block') {
                        statusBadge.classList.remove('active');
                        statusBadge.classList.add('blocked');
                        statusBadge.textContent = 'Blocked';
                        actionButton.classList.remove('btn-warning');
                        actionButton.classList.add('btn-success');
                        actionButton.innerHTML = '<i class="fas fa-check-circle"></i> Activate';
                        actionButton.onclick = () => updateUserStatus(userId, 'activate');
                    } else {
                        statusBadge.classList.remove('blocked');
                        statusBadge.classList.add('active');
                        statusBadge.textContent = 'Active';
                        actionButton.classList.remove('btn-success');
                        actionButton.classList.add('btn-warning');
                        actionButton.innerHTML = '<i class="fas fa-ban"></i> Block';
                        actionButton.onclick = () => updateUserStatus(userId, 'block');
                    }

                    // Show success message
                    alert(data.message);
                } else {
                    alert(data.message || 'Error updating user status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating user status');
            });
        }
        </script>
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
        
        <div class="car-grid">
            <?php
            // Modified query to only show approved cars
            $sql = "SELECT * FROM cars WHERE status = 'approved' ORDER BY id DESC";
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0):
                while ($car = $result->fetch_assoc()):
                    $images = json_decode($car['images'], true);
                    $main_image = !empty($images['main_image']) ? $images['main_image'] : 'assets/img/no-image.jpg';
            ?>
                <div class="car-card" id="car-<?php echo $car['id']; ?>">
                    <div class="car-image">
                        <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>">
                        <div class="car-status <?php echo $car['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $car['is_active'] ? 'Active' : 'Inactive'; ?>
                        </div>
                    </div>
                    <div class="car-details">
                        <h3 class="car-name"><?php echo htmlspecialchars($car['car_name']); ?></h3>
                        <div class="car-price">₹<?php echo number_format($car['price'], 2); ?></div>
                        
                        <div class="car-actions">
                            <?php if (!empty($images['rc_document'])): ?>
                                <a href="<?php echo htmlspecialchars($images['rc_document']); ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-file-pdf"></i> RC Book
                                </a>
                            <?php endif; ?>
                            
                            <button 
                                class="btn btn-edit"
                                onclick="openEditModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars(addslashes($car['car_name'])); ?>', <?php echo $car['price']; ?>)"
                            >
                                <i class="fas fa-edit"></i> Edit
                            </button>

                            <button 
                                class="btn <?php echo $car['is_active'] ? 'btn-disable' : 'btn-enable'; ?>"
                                onclick="return toggleCarStatus(<?php echo $car['id']; ?>)"
                                data-status="<?php echo $car['is_active']; ?>"
                            >
                                <i class="fas <?php echo $car['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                <?php echo $car['is_active'] ? 'Disable' : 'Enable'; ?>
                            </button>
                            
                            <button class="btn btn-delete" onclick="deleteCar(<?php echo $car['id']; ?>)" style="width: 100%; display: flex; justify-content: center; align-items: center;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile;
            else: 
            ?>
                <div class="no-cars">
                    <p>No approved car listings available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Car Details</h2>
                <form id="editCarForm">
                    <input type="hidden" id="editCarId" name="car_id">
                    <div class="form-group">
                        <label for="editCarName">Car Name:</label>
                        <input type="text" id="editCarName" name="car_name" required>
                    </div>
                    <div class="form-group">
                        <label for="editCarPrice">Price (₹):</label>
                        <input type="number" id="editCarPrice" name="price" min="0" step="0.01" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Save Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <style>
            .car-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                padding: 20px;
            }

            .car-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
                transition: transform 0.2s;
            }

            .car-card:hover {
                transform: translateY(-5px);
            }

            .car-image {
                position: relative;
                height: 200px;
                overflow: hidden;
            }

            .car-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .car-status {
                position: absolute;
                top: 10px;
                right: 10px;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.8em;
                font-weight: 500;
            }

            .car-status.active {
                background: #d4edda;
                color: #155724;
            }

            .car-status.inactive {
                background: #f8d7da;
                color: #721c24;
            }

            .car-details {
                padding: 15px;
            }

            .car-name {
                margin: 0 0 10px;
                font-size: 1.2em;
                font-weight: 600;
                color: #333;
            }

            .car-price {
                font-size: 1.3em;
                font-weight: 700;
                color: #28a745;
                margin-bottom: 15px;
            }

            .car-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .car-actions .btn {
                flex: 1;
                min-width: 100px;
                text-align: center;
                padding: 8px;
                border-radius: 4px;
                font-size: 0.9em;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .btn-info {
                background: #17a2b8;
                color: white;
                text-decoration: none;
            }

            .btn-edit {
                background: #6c757d;
                color: white;
            }

            .btn-enable {
                background: #28a745;
                color: white;
            }

            .btn-disable {
                background: #ffc107;
                color: #000;
            }

            .btn-delete {
                background: #dc3545;
                color: white;
            }

            .btn-secondary {
                background: #6c757d;
                color: white;
            }

            .no-cars {
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px;
                background: #f8f9fa;
                border-radius: 8px;
            }

            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }

            .modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border-radius: 8px;
                width: 80%;
                max-width: 500px;
                position: relative;
            }

            .close {
                position: absolute;
                right: 20px;
                top: 10px;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
            }

            .form-group input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 1em;
            }

            .form-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 20px;
            }
        </style>

        <script>
        // Modal functions
        const modal = document.getElementById('editModal');
        const span = document.getElementsByClassName('close')[0];

        function openEditModal(carId, carName, carPrice) {
            document.getElementById('editCarId').value = carId;
            document.getElementById('editCarName').value = carName;
            document.getElementById('editCarPrice').value = carPrice;
            modal.style.display = 'block';
        }

        function closeEditModal() {
            modal.style.display = 'none';
        }

        span.onclick = closeEditModal;
        window.onclick = function(event) {
            if (event.target == modal) {
                closeEditModal();
            }
        }

        // Form submission
        document.getElementById('editCarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('update_car.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the card without refreshing
                    const card = document.getElementById('car-' + formData.get('car_id'));
                    card.querySelector('.car-name').textContent = formData.get('car_name');
                    card.querySelector('.car-price').textContent = '₹' + Number(formData.get('price')).toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    closeEditModal();
                    showAlert('success', data.message);
                } else {
                    showAlert('error', data.message || 'Error updating car details');
                }
            })
            .catch(error => {
                showAlert('error', 'Error communicating with server');
            });
        });
        </script>
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
        <h2><i class="fas fa-comment-alt"></i> Complaints Management</h2><br>
        
        <!-- Search and Filter Bar -->
        <div class="filter-bar">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="complaintSearch" placeholder="Search complaints..." onkeyup="filterComplaints()">
          </div>
          <div class="filter-buttons">
            <button class="filter-btn active" data-status="all">All</button>
            <button class="filter-btn" data-status="pending">Pending</button>
            <button class="filter-btn" data-status="in_progress">In Progress</button>
            <button class="filter-btn" data-status="resolved">Resolved</button>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
          <?php
          $stats_sql = "SELECT 
                          COUNT(*) as total,
                          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                          SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                          SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
                        FROM complaints";
          $stats_result = $conn->query($stats_sql);
          $stats = $stats_result->fetch_assoc();
          ?>
          <div class="stat-card total">
            <i class="fas fa-comments"></i>
            <div class="stat-info">
              <span class="stat-value"><?php echo $stats['total']; ?></span>
              <span class="stat-label">Total</span>
            </div>
          </div>
          <div class="stat-card pending">
            <i class="fas fa-clock"></i>
            <div class="stat-info">
              <span class="stat-value"><?php echo $stats['pending']; ?></span>
              <span class="stat-label">Pending</span>
            </div>
          </div>
          <div class="stat-card progress">
            <i class="fas fa-tasks"></i>
            <div class="stat-info">
              <span class="stat-value"><?php echo $stats['in_progress']; ?></span>
              <span class="stat-label">In Progress</span>
            </div>
          </div>
          <div class="stat-card resolved">
            <i class="fas fa-check-circle"></i>
            <div class="stat-info">
              <span class="stat-value"><?php echo $stats['resolved']; ?></span>
              <span class="stat-label">Resolved</span>
            </div>
          </div>
        </div>

        <div class="content-grid">
          <?php
          $complaints_sql = "SELECT c.*, u.fullname as user_name 
                            FROM complaints c 
                            JOIN users u ON c.user_id = u.id 
                            ORDER BY c.created_at DESC";
          $complaints_result = $conn->query($complaints_sql);

          if ($complaints_result && $complaints_result->num_rows > 0) {
            while($row = $complaints_result->fetch_assoc()) {
              $status_class = '';
              switch($row['status']) {
                case 'pending':
                  $status_class = 'bg-warning text-dark';
                  break;
                case 'resolved':
                  $status_class = 'bg-success text-white';
                  break;
                case 'in_progress':
                  $status_class = 'bg-info text-white';
                  break;
              }
              ?>
              <div class="content-card" data-status="<?php echo $row['status']; ?>" data-search="<?php echo strtolower($row['subject'] . ' ' . $row['description'] . ' ' . $row['user_name']); ?>">
                <style>
                    .content-card {
                        background: #fff;
                        border-radius: 12px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        margin-bottom: 20px;
                        transition: transform 0.2s, box-shadow 0.2s;
                        overflow: hidden;
                        border: 1px solid #eee;
                    }
                    .content-card:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
                    }
                    .card-header {
                        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                        padding: 15px 20px;
                        border-bottom: 1px solid #eee;
                    }
                    .card-header h5 {
                        color: #2c3e50;
                        font-weight: 600;
                        margin: 0;
                    }
                    .badge {
                        padding: 8px 12px;
                        border-radius: 20px;
                        font-weight: 500;
                        text-transform: capitalize;
                        letter-spacing: 0.5px;
                    }
                    .badge.pending { background: #fff3cd; color: #856404; }
                    .badge.in_progress { background: #cce5ff; color: #004085; }
                    .badge.resolved { background: #d4edda; color: #155724; }
                    .card-body {
                        padding: 20px;
                    }
                    .card-subtitle {
                        color: #3498db;
                        font-weight: 600;
                        margin-bottom: 12px;
                    }
                    .card-text {
                        color: #505050;
                        line-height: 1.6;
                        margin-bottom: 15px;
                    }
                    .meta-info {
                        display: flex;
                        gap: 20px;
                        color: #6c757d;
                        font-size: 0.9rem;
                        margin-top: 15px;
                    }
                    .meta-info i {
                        margin-right: 5px;
                        color: #3498db;
                    }
                    .card-footer {
                        background: #f8f9fa;
                        padding: 15px 20px;
                        border-top: 1px solid #eee;
                    }
                    .btn {
                        padding: 8px 16px;
                        border-radius: 20px;
                        font-weight: 500;
                        transition: all 0.2s;
                        border: none;
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                    }
                    .btn i {
                        font-size: 0.9rem;
                    }
                    .btn-info {
                        background: #3498db;
                        color: white;
                    }
                    .btn-success {
                        background: #2ecc71;
                        color: white;
                    }
                    .btn:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    }
                </style>
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">
                    <i class="fas fa-ticket-alt" style="margin-right: 8px; color: #3498db;"></i>
                    Complaint #<?php echo $row['id']; ?>
                  </h5>
                  <span class="badge <?php echo $status_class; ?>">
                    <i class="fas fa-circle" style="font-size: 8px; margin-right: 5px;"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                  </span>
                </div>
                <div class="card-body">
                  <h6 class="card-subtitle mb-2"><?php echo htmlspecialchars($row['subject']); ?></h6>
                  <p class="card-text expandable"><?php echo htmlspecialchars($row['description']); ?></p>
                  <div class="meta-info">
                    <small class="text-muted">
                      <i class="fas fa-user"></i> <?php echo htmlspecialchars($row['user_name']); ?>
                    </small>
                    <small class="text-muted">
                      <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                    </small>
                  </div>
                </div>
                <?php if ($row['status'] !== 'resolved') { ?>
                  <div class="card-footer">
                    <div class="d-flex justify-content-end gap-3">
                      <button class="btn btn-info" onclick="updateStatus(<?php echo $row['id']; ?>, 'in_progress')">
                        <i class="fas fa-tasks"></i> Mark In Progress
                      </button>
                      <button class="btn btn-success" onclick="resolveComplaint(<?php echo $row['id']; ?>)">
                        <i class="fas fa-check"></i> Mark as Resolved
                      </button>
                    </div>
                  </div>
                <?php } ?>
              </div>
              <?php
            }
          } else {
            echo "<div class='no-data'>No complaints found</div>";
          }
          ?>
        </div>
      </div>

      <style>
        /* Enhanced Grid Styles */
        .filter-bar {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
          flex-wrap: wrap;
          gap: 15px;
        }

        .search-box {
          position: relative;
          flex: 1;
          max-width: 300px;
        }

        .search-box input {
          width: 100%;
          padding: 10px 15px 10px 35px;
          border: 1px solid #ddd;
          border-radius: 20px;
          font-size: 0.9rem;
        }

        .search-box i {
          position: absolute;
          left: 12px;
          top: 50%;
          transform: translateY(-50%);
          color: #666;
        }

        .filter-buttons {
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
        }

        .filter-btn {
          padding: 8px 15px;
          border: none;
          border-radius: 15px;
          background: #f0f0f0;
          color: #666;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .filter-btn:hover {
          background: #e0e0e0;
        }

        .filter-btn.active {
          background: #007bff;
          color: white;
        }

        /* Stats Cards */
        .stats-container {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 20px;
          margin-bottom: 20px;
        }

        .stat-card {
          background: white;
          border-radius: 10px;
          padding: 20px;
          display: flex;
          align-items: center;
          gap: 15px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card i {
          font-size: 2rem;
          padding: 15px;
          border-radius: 50%;
        }

        .stat-card.total i {
          background: #e3f2fd;
          color: #1976d2;
        }

        .stat-card.pending i {
          background: #fff3e0;
          color: #f57c00;
        }

        .stat-card.progress i {
          background: #e8f5e9;
          color: #388e3c;
        }

        .stat-card.resolved i {
          background: #e8eaf6;
          color: #3f51b5;
        }

        .stat-info {
          display: flex;
          flex-direction: column;
        }

        .stat-value {
          font-size: 1.5rem;
          font-weight: bold;
          line-height: 1;
        }

        .stat-label {
          color: #666;
          font-size: 0.9rem;
        }

        /* Card Enhancements */
        .content-card {
          transform-origin: center;
          animation: cardAppear 0.3s ease-out;
        }

        .content-card.hidden {
          display: none;
        }

        .card-text.expandable {
          cursor: pointer;
          position: relative;
        }

        .card-text.expanded {
          max-height: none;
          -webkit-line-clamp: unset;
        }

        .card-footer {
          display: flex;
          justify-content: flex-end;
          gap: 10px;
        }

        @keyframes cardAppear {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
          .filter-bar {
            flex-direction: column;
            align-items: stretch;
          }

          .search-box {
            max-width: none;
          }

          .stats-container {
            grid-template-columns: repeat(2, 1fr);
          }
        }
      </style>

      <script>
        // Enhanced complaint handling
        function updateStatus(complaintId, status) {
          if (!confirm(`Are you sure you want to mark this complaint as ${status.replace('_', ' ')}?`)) return;

          try {
            const formData = new FormData();
            formData.append('complaint_id', complaintId);
            formData.append('status', status);
            formData.append('csrf_token', '<?php echo $_SESSION["csrf_token"]; ?>');

            fetch('handle_complaint.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                showAlert('Status updated successfully', 'success');
                location.reload();
              } else {
                throw new Error(data.message);
              }
            })
            .catch(error => {
              showAlert(error.message, 'error');
            });
          } catch (error) {
            showAlert(error.message, 'error');
          }
        }

        // Filter complaints
        function filterComplaints() {
          const searchTerm = document.getElementById('complaintSearch').value.toLowerCase();
          const activeFilter = document.querySelector('.filter-btn.active').dataset.status;
          const cards = document.querySelectorAll('.content-card');

          cards.forEach(card => {
            const status = card.dataset.status;
            const searchText = card.dataset.search;
            const matchesSearch = searchText.includes(searchTerm);
            const matchesFilter = activeFilter === 'all' || status === activeFilter;

            if (matchesSearch && matchesFilter) {
              card.classList.remove('hidden');
            } else {
              card.classList.add('hidden');
            }
          });
        }

        // Filter button handling
        document.querySelectorAll('.filter-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterComplaints();
          });
        });

        // Expandable description
        document.querySelectorAll('.card-text.expandable').forEach(text => {
          text.addEventListener('click', () => {
            text.classList.toggle('expanded');
          });
        });

        // Show alerts with animation
        function showAlert(message, type) {
          const alertDiv = document.createElement('div');
          alertDiv.className = `alert alert-${type}`;
          alertDiv.textContent = message;
          alertDiv.style.position = 'fixed';
          alertDiv.style.top = '20px';
          alertDiv.style.right = '20px';
          alertDiv.style.zIndex = '9999';
          alertDiv.style.padding = '15px 20px';
          alertDiv.style.borderRadius = '4px';
          alertDiv.style.animation = 'fadeIn 0.3s ease';
          alertDiv.style.backgroundColor = type === 'success' ? '#4caf50' : '#f44336';
          alertDiv.style.color = 'white';

          document.body.appendChild(alertDiv);

          setTimeout(() => {
            alertDiv.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => alertDiv.remove(), 300);
          }, 3000);
        }
      </script>
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
      // Toggle car status
      function toggleCarStatus(carId) {
          if (!confirm('Are you sure you want to change this car\'s status?')) return false;

          fetch('update_car_status.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `car_id=${carId}&action=toggle_status&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
          })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  const button = document.querySelector(`button[onclick="return toggleCarStatus(${carId})"]`);
                  const statusDiv = button.closest('.car-card').querySelector('.car-status');
                  
                  // Update button text and class
                  if (data.new_status === 1) {
                      button.className = 'btn btn-disable';
                      button.innerHTML = '<i class="fas fa-eye-slash"></i> Disable';
                      statusDiv.className = 'car-status active';
                      statusDiv.textContent = 'Active';
                  } else {
                      button.className = 'btn btn-enable';
                      button.innerHTML = '<i class="fas fa-eye"></i> Enable';
                      statusDiv.className = 'car-status inactive';
                      statusDiv.textContent = 'Inactive';
                  }
                  showAlert('Status updated successfully', 'success');
              } else {
                  showAlert(data.message || 'Error updating status', 'error');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              showAlert('Error communicating with server', 'error');
          });
          
          return false; // Prevent form submission
      }
      </script>

      <div class="section" id="inventory">{{ ... }}</div>

    <style>
        /* Table styles */
        .table-container {
            overflow-x: auto;
            margin: 20px 0;
        }

        table td {
            vertical-align: top;
            padding: 12px;
        }

        /* Truncated content styles */
        .truncate-content {
            position: relative;
            transition: max-height 0.3s ease;
            min-height: 40px;
        }

        .truncated {
            max-height: 60px;
            overflow: hidden;
        }

        .truncated::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 25px;
            background: linear-gradient(transparent, #fff);
            pointer-events: none;
        }

        .show-more {
            color: #007bff;
            cursor: pointer;
            font-size: 0.9em;
            padding: 4px 8px;
            margin-top: 5px;
            display: inline-block;
            border-radius: 4px;
            transition: all 0.2s ease;
            background: rgba(0, 123, 255, 0.1);
        }

        .show-more:hover {
            background: rgba(0, 123, 255, 0.2);
        }

        /* Car images styles */
        .car-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 8px;
            padding: 8px;
            transition: max-height 0.3s ease;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .car-images.truncated {
            max-height: 120px;
            overflow: hidden;
        }

        .car-images img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            transition: transform 0.2s ease;
        }

        .car-images img:hover {
            transform: scale(1.05);
        }

        /* Features list styles */
        .feature-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .feature-list li {
            margin-bottom: 6px;
            padding: 4px 0;
            border-bottom: 1px solid #eee;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list strong {
            color: #555;
            margin-right: 8px;
        }

        /* Description styles */
        .car-description {
            line-height: 1.5;
            color: #444;
        }
    </style>
  </body>
</html>
