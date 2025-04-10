<?php
require_once 'db_connect.php';

$stmt = $conn->prepare("SELECT id, verification_doc FROM users WHERE verification_doc IS NOT NULL");
$stmt->execute();
$result = $stmt->get_result();

echo "Users with verification documents:\n\n";
while ($row = $result->fetch_assoc()) {
    echo "User ID: " . $row['id'] . "\n";
    echo "Document: " . $row['verification_doc'] . "\n\n";
}
?>
