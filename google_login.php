<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'db_connect.php';

// Google OAuth configuration
$clientId = '928928437281-76vrmg7m9li61jia9vbjj36stu3gqbg8.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/Rental/google_login.php';

// Create Google client
$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");
$client->setAccessType('offline');
$client->setPrompt('select_account');

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['access_token'])) {
        $client->setAccessToken($_SESSION['access_token']);
        $client->revokeToken();
    }
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    header('Location: auth-page.php');
    exit();
}

try {
    if (isset($_GET['code'])) {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (!isset($token['error'])) {
            $client->setAccessToken($token);
            $_SESSION['access_token'] = $token;

            $service = new Google\Service\Oauth2($client);
            $google_account = $service->userinfo->get();
            
            // Get user info from Google
            $email = $google_account->email;
            $name = $google_account->name;
            $picture = $google_account->picture;
            
            // Debug Google account info
            error_log("Google Account Info - Email: $email, Name: $name, Picture: $picture");

            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Update user with Google profile picture
                $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ?, auth_type = 'google' WHERE id = ?");
                $update_stmt->bind_param("si", $picture, $user['id']);
                $update_stmt->execute();
                
                // Store in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['fullname'];
                $_SESSION['user_email'] = $email;
                $_SESSION['profile_picture'] = $picture;
                $_SESSION['google_login'] = true;
                $_SESSION['auth_type'] = 'google';
                
                error_log("Updated existing user - Profile picture: " . $picture);
                
                // Check if required details are missing
                $check_details = $conn->prepare("SELECT mobile, verification_doc FROM users WHERE id = ?");
                $check_details->bind_param("i", $user['id']);
                $check_details->execute();
                $details_result = $check_details->get_result();
                $user_details = $details_result->fetch_assoc();
                
                if (empty($user_details['mobile']) || empty($user_details['verification_doc'])) {
                    header('Location: user-profile.php?complete_profile=1');
                    exit();
                }
                
                header('Location: dashboard.php');
                exit();
            } else {
                // Create new user with Google info
                $stmt = $conn->prepare("INSERT INTO users (fullname, email, mobile, password, profile_picture, auth_type) VALUES (?, ?, '', '', ?, 'google')");
                $stmt->bind_param("sss", $name, $email, $picture);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Store in session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['profile_picture'] = $picture;
                    $_SESSION['google_login'] = true;
                    $_SESSION['auth_type'] = 'google';
                    
                    error_log("Created new user - Profile picture: " . $picture);
                    
                    // Redirect new users to complete their profile
                    header('Location: user-profile.php?complete_profile=1');
                    exit();
                } else {
                    $_SESSION['error'] = "Error creating user account";
                    error_log("Error creating user: " . $conn->error);
                    header('Location: auth-page.php');
                    exit();
                }
            }
        } else {
            throw new Exception("Google OAuth Error: " . ($token['error_description'] ?? $token['error']));
        }
    }

    // Create auth URL
    $auth_url = $client->createAuthUrl();
    header("Location: $auth_url");
    exit();

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: auth-page.php");
    exit();
}
?>