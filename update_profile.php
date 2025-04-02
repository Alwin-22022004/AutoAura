<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $fullname = trim($_POST['fullname']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address']);
    $errors = [];

    // Validate inputs
    if (empty($fullname)) {
        $errors[] = "Full name is required";
    }

    if (empty($mobile)) {
        $errors[] = "Mobile number is required";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = "Invalid mobile number format";
    }

    if (empty($address)) {
        $errors[] = "Address is required";
    }

    // Handle PDF upload
    $verification_doc = null;
    $has_file = false;
    
    if (isset($_FILES['verification_doc']) && $_FILES['verification_doc']['size'] > 0) {
        $file = $_FILES['verification_doc'];
        
        // Validate file type
        $allowed_types = ['application/pdf'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only PDF files are allowed";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = "File size must be less than 5MB";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/verification_documents';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('doc_') . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . '/' . $unique_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $verification_doc = $unique_filename;
                $has_file = true;
            } else {
                $errors[] = "Failed to upload file. Please try again.";
            }
        }
    }

    // Handle profile picture upload
    $profile_picture = null;
    $has_profile_picture = false;
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
        $file = $_FILES['profile_picture'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, JPEG and PNG images are allowed";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $errors[] = "File size must be less than 5MB";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/profile_pictures';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('profile_') . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . '/' . $unique_filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $profile_picture = $target_path;
                $has_profile_picture = true;
            } else {
                $errors[] = "Failed to upload profile picture. Please try again.";
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($has_file) {
                // Delete old file if exists
                $stmt = $conn->prepare("SELECT verification_doc FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($old_doc = $result->fetch_assoc()) {
                    if (!empty($old_doc['verification_doc'])) {
                        $old_file = 'uploads/verification_documents/' . $old_doc['verification_doc'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                }
                $stmt->close();
            }

            if ($has_profile_picture) {
                // Delete old profile picture if exists
                $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($old_pic = $result->fetch_assoc()) {
                    if (!empty($old_pic['profile_picture'])) {
                        if (file_exists($old_pic['profile_picture'])) {
                            unlink($old_pic['profile_picture']);
                        }
                    }
                }
                $stmt->close();
            }

            // Update query with all fields including profile picture
            if ($has_file && $has_profile_picture) {
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, mobile = ?, address = ?, verification_doc = ?, profile_picture = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $fullname, $mobile, $address, $verification_doc, $profile_picture, $user_id);
            } elseif ($has_file) {
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, mobile = ?, address = ?, verification_doc = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $fullname, $mobile, $address, $verification_doc, $user_id);
            } elseif ($has_profile_picture) {
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, mobile = ?, address = ?, profile_picture = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $fullname, $mobile, $address, $profile_picture, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, mobile = ?, address = ? WHERE id = ?");
                $stmt->bind_param("sssi", $fullname, $mobile, $address, $user_id);
            }

            if ($stmt->execute()) {
                $_SESSION['success'] = "Profile updated successfully!";
                header("Location: dashboard.php");
                exit();
            } else {
                $_SESSION['errors'] = ["Failed to update profile. Please try again."];
            }
        } catch (Exception $e) {
            $_SESSION['errors'] = ["An error occurred. Please try again later."];
        }
    } else {
        $_SESSION['errors'] = $errors;
    }
    
    header("Location: user-profile.php" . (isset($_GET['complete_profile']) ? "?complete_profile=1" : ""));
    exit();
}

header("Location: user-profile.php");
exit();
?>
