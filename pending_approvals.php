<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: index.php");
    exit();
}

require_once 'db_connect.php';

// Handle AJAX requests for approving/rejecting cars
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['car_id'])) {
    $response = ['success' => false, 'message' => ''];
    
    $car_id = intval($_POST['car_id']);
    $action = $_POST['action'];

    if ($action === 'approve' || $action === 'reject') {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        $update_sql = "UPDATE cars SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $car_id);
        
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => "Car listing #$car_id has been " . ucfirst($action) . "d successfully!",
                'new_status' => ucfirst($new_status)
            ];
        } else {
            $response['message'] = "Error updating status: " . $conn->error;
        }
        $stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get all car listings
$sql = "SELECT c.*, u.fullname as owner_name 
        FROM cars c 
        LEFT JOIN users u ON c.owner_id = u.id 
        ORDER BY c.id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Error fetching cars: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Listing Approvals - Admin Dashboard</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --accent: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f1c40f;
            --info: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f8f9fa;
            color: #333;
        }

        .main-content {
            padding: 20px;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 1px solid var(--light);
        }

        .header h1 {
            margin: 0;
            color: var(--primary);
            font-size: 24px;
            font-weight: 600;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .car-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
            border: 1px solid var(--light);
        }

        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .car-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .car-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .car-card:hover .car-image img {
            transform: scale(1.05);
        }

        .car-details {
            padding: 20px;
        }

        .car-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .car-price {
            font-size: 20px;
            font-weight: 600;
            color: var(--success);
            margin-bottom: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn i {
            font-size: 16px;
        }

        .btn-approve {
            background: var(--success);
            color: white;
        }

        .btn-approve:hover {
            background: #219a52;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: var(--danger);
            color: white;
        }

        .btn-reject:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-info {
            background: var(--info);
            color: white;
            margin-bottom: 15px;
            width: 100%;
        }

        .btn-info:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .alert-success {
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            border-left: 4px solid var(--danger);
        }

        .status-label {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .status-label.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-label.approved {
            background: #d4edda;
            color: #155724;
        }

        .status-label.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .card-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header">
            <h1>Car Listing Approvals</h1>
            <a href="admin.php" class="btn" style="background: var(--primary); color: white;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div id="alertContainer"></div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="card-grid">
                <?php while ($car = $result->fetch_assoc()): ?>
                    <div class="car-card" id="car-<?php echo $car['id']; ?>">
                        <div class="car-image">
                            <?php 
                            $images = json_decode($car['images'], true);
                            $main_image = $images && isset($images['main_image']) ? $images['main_image'] : 'placeholder.jpg';
                            ?>
                            <img src="<?php echo htmlspecialchars($main_image); ?>" alt="<?php echo htmlspecialchars($car['car_name']); ?>">
                        </div>
                        <div class="car-details">
                            <div class="car-name"><?php echo htmlspecialchars($car['car_name']); ?></div>
                            <div class="car-price">₹<?php echo number_format($car['price'], 2); ?></div>
                            
                            <span class="status-label <?php echo htmlspecialchars($car['status']); ?>" id="status-<?php echo $car['id']; ?>">
                                <?php echo ucfirst(htmlspecialchars($car['status'])); ?>
                            </span>
                            
                            <?php if (!empty($images['rc_document'])): ?>
                                <a href="<?php echo htmlspecialchars($images['rc_document']); ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-file-pdf"></i> View RC Document
                                </a>
                            <?php else: ?>
                                <button class="btn btn-info" disabled>
                                    <i class="fas fa-exclamation-circle"></i> No RC Document
                                </button>
                            <?php endif; ?>

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
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-car"></i>
                <h2>No Car Listings</h2>
                <p>There are no car listings available at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function updateCarStatus(carId, action) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                car_id: carId,
                action: action
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        // Update status label
                        const statusLabel = document.getElementById(`status-${carId}`);
                        if (statusLabel) {
                            statusLabel.className = `status-label ${action === 'approve' ? 'approved' : 'rejected'}`;
                            statusLabel.textContent = result.new_status;
                        }
                        
                        // Remove action buttons
                        const actionButtons = document.querySelector(`#car-${carId} .action-buttons`);
                        if (actionButtons) {
                            actionButtons.remove();
                        }
                        
                        // Show success message
                        showAlert(result.message, 'success');
                    } else {
                        showAlert(result.message || 'Error updating status', 'error');
                    }
                } catch (e) {
                    showAlert('Error processing response', 'error');
                }
            },
            error: function() {
                showAlert('Error communicating with server', 'error');
            }
        });
    }

    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alert = `
            <div class="alert ${alertClass}">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                ${message}
            </div>
        `;
        alertContainer.innerHTML = alert;
        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);
    }
    </script>
</body>
</html>
