<?php
include 'db_connect.php'; // Include your database connection file

// Approve or Reject Car Listing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $car_id = intval($_POST['car_id']);
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $status = 'approved';
    } elseif ($action === 'reject') {
        $status = 'rejected';
    } else {
        die("Invalid action.");
    }
    
    $sql = "UPDATE cars SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $car_id);
    
    if ($stmt->execute()) {
        echo "Car listing has been updated to: " . $status;
    } else {
        echo "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

// Fetch pending car listings
$sql = "SELECT * FROM cars WHERE status = 'pending'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Approve Cars</title>
</head>
<body>
    <h2>Admin Panel - Approve or Reject Cars</h2>
    
    <?php if ($result->num_rows > 0): ?>
        <table border="1">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Features</th>
                <th>Price</th>
                <th>Images</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['car_name']; ?></td>
                    <td><?php echo $row['car_description']; ?></td>
                    <td><?php echo nl2br($row['car_features']); ?></td>
                    <td><?php echo $row['price']; ?></td>
                    <td>
                        <?php 
                        $images = json_decode($row['images'], true);
                        foreach ($images as $image): ?>
                            <img src="<?php echo $image; ?>" width="100" alt="Car Image">
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="car_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="action" value="approve">Approve</button>
                            <button type="submit" name="action" value="reject">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No pending car listings.</p>
    <?php endif; ?>
</body>
</html>
