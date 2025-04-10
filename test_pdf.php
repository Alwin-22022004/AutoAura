<?php
header('Content-Type: application/pdf');
$file = 'uploads/verification_documents/' . $_GET['file'];
if (file_exists($file)) {
    readfile($file);
} else {
    echo "File not found: " . $file;
}
?>
