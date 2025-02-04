<?php
session_start();
session_destroy();
session_unset();

// Clear all session cookies
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-3600, '/');
    }
}

// Redirect to login page
echo "<script>
    alert('You have been successfully logged out!');
    window.location.href = 'index.php';
</script>";
exit();
?>
