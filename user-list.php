<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: auth-page.php');
    exit();
}

require_once 'db_connect.php';

// Handle PDF display request
if (isset($_GET['view_pdf']) && is_numeric($_GET['view_pdf'])) {
    $user_id = intval($_GET['view_pdf']);
    
    // Get PDF file path
    $stmt = $conn->prepare("SELECT verification_doc FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $file_path = $row['verification_doc'];
        
        // Check if the file path is valid and the file exists
        if (!empty($file_path) && file_exists(__DIR__ . '/' . $file_path)) {
            $full_path = __DIR__ . '/' . $file_path;
            
            // Get the file mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $full_path);
            finfo_close($finfo);
            
            // Verify it's a PDF
            if ($mime_type === 'application/pdf') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="verification_document.pdf"');
                readfile($full_path);
                exit();
            }
        }
    }
    
    // If we get here, something went wrong
    error_log("PDF not found for user $user_id. File path: $file_path");
    http_response_code(404);
    exit('Document not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - LUXE DRIVE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f4f4f4;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            background: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #c4a47c;
            margin-bottom: 0.5rem;
        }

        .search-container {
            margin-bottom: 1.5rem;
        }

        #searchInput {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        #searchInput:focus {
            border-color: #c4a47c;
            outline: none;
            box-shadow: 0 0 10px rgba(196, 164, 124, 0.3);
        }

        .table-container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .pdf-preview {
            color: #c4a47c;
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .pdf-preview:hover {
            color: #a88a60;
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        #pdfModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }

        #pdfViewer {
            width: 90%;
            height: 90%;
            border: none;
            border-radius: 8px;
            background: white;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 30px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem auto;
            }

            th, td {
                padding: 0.75rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>User Management</h1>
            <p>View and manage registered users</p>
        </div>

        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search users by name, email, or mobile number...">
        </div>

        <div class="table-container">
            <table id="userTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Document</th>
                        <th>Registered On</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <!-- Users will be dynamically populated here -->
                </tbody>
            </table>
        </div>
    </div>

    <div id="pdfModal" onclick="closePdfModal()">
        <span class="close-modal" onclick="closePdfModal()">&times;</span>
        <iframe id="pdfViewer" src=""></iframe>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userTableBody = document.getElementById('userTableBody');
            const searchInput = document.getElementById('searchInput');
            const pdfModal = document.getElementById('pdfModal');
            const pdfViewer = document.getElementById('pdfViewer');

            // Fetch users from database
            async function fetchUsers() {
                try {
                    const response = await fetch('get_users.php');
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    const users = await response.json();
                    displayUsers(users);
                } catch (error) {
                    console.error('Error fetching users:', error);
                    userTableBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="no-results">
                                Error loading users. Please try again later.
                            </td>
                        </tr>
                    `;
                }
            }

            // Display users in table
            function displayUsers(users) {
                if (users.length === 0) {
                    userTableBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="no-results">
                                No users found.
                            </td>
                        </tr>
                    `;
                    return;
                }

                userTableBody.innerHTML = '';
                users.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.id}</td>
                        <td>${escapeHtml(user.fullname)}</td>
                        <td>${escapeHtml(user.email)}</td>
                        <td>${escapeHtml(user.mobile)}</td>
                        <td>
                            ${user.verification_doc ? 
                                `<i class="fas fa-file-pdf pdf-preview" 
                                    onclick="openPdfModal(${user.id})" 
                                    title="View PDF Document"></i>`
                                : 'No document'
                            }
                        </td>
                        <td>${formatDate(user.created_at)}</td>
                    `;
                    userTableBody.appendChild(row);
                });
            }

            // Utility function to escape HTML
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Format date
            function formatDate(dateString) {
                const options = { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit', 
                    minute: '2-digit'
                };
                return new Date(dateString).toLocaleDateString('en-US', options);
            }

            // Search functionality
            searchInput.addEventListener('input', () => {
                const searchTerm = searchInput.value.toLowerCase();
                const rows = userTableBody.getElementsByTagName('tr');
                
                Array.from(rows).forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });

                // Show no results message if no matches
                const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
                if (visibleRows.length === 0 && searchTerm !== '') {
                    userTableBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="no-results">
                                No users found matching "${escapeHtml(searchInput.value)}"
                            </td>
                        </tr>
                    `;
                }
            });

            // Initial fetch
            fetchUsers();
        });

        // Modal functions
        function openPdfModal(userId) {
            event.stopPropagation();
            const pdfViewer = document.getElementById('pdfViewer');
            pdfViewer.src = `?view_pdf=${userId}`;
            document.getElementById('pdfModal').style.display = 'flex';
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = 'none';
            document.getElementById('pdfViewer').src = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closePdfModal();
            }
        });
    </script>
</body>
</html>
