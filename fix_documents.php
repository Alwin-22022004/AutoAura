<?php
require_once 'db_connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create verification documents directory if it doesn't exist
$target_dir = 'uploads/verification_documents';
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Get all users with verification documents
$stmt = $conn->prepare("SELECT id, verification_doc FROM users WHERE verification_doc IS NOT NULL");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $user_id = $row['id'];
    $current_doc = $row['verification_doc'];
    
    // Skip if it's already in the correct format and location
    if (strpos($current_doc, 'uploads/verification_documents/') === 0) {
        continue;
    }
    
    // Handle case where PDF content was stored in database
    if (strpos($current_doc, '%PDF') === 0) {
        $new_filename = 'doc_' . uniqid() . '_' . time() . '.pdf';
        $new_path = $target_dir . '/' . $new_filename;
        
        // Write PDF content to file
        file_put_contents($new_path, $current_doc);
    } else {
        // Handle case where file is in wrong directory
        $filename = basename($current_doc);
        $new_path = $target_dir . '/' . $filename;
        
        // Move file if it exists
        if (file_exists($current_doc)) {
            rename($current_doc, $new_path);
        } else {
            // If file doesn't exist, check other possible directories
            $possible_dirs = [
                'uploads/verification/',
                'uploads/verification_docs/',
                'uploads/documents/'
            ];
            
            foreach ($possible_dirs as $dir) {
                $old_path = $dir . $filename;
                if (file_exists($old_path)) {
                    rename($old_path, $new_path);
                    break;
                }
            }
        }
    }
    
    // Update database with new path
    if (file_exists($new_path)) {
        $update = $conn->prepare("UPDATE users SET verification_doc = ? WHERE id = ?");
        $update->bind_param("si", $new_path, $user_id);
        $update->execute();
        echo "Updated document for user $user_id: $new_path\n";
    } else {
        echo "Failed to fix document for user $user_id\n";
    }
}

echo "Document fix process completed.\n";
?>
