<?php
session_start();

// Check if user is logged in (you may want to add admin check here)
if (!isset($_SESSION['user_id'])) {
    header('Location: auth-page.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - LUXE DRIVE</title>
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
            position: relative;
        }

        .header h1 {
            color: #c4a47c;
            margin-bottom: 0.5rem;
        }

        .back-button {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: #c4a47c;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: #b3936b;
            transform: translateY(-2px);
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

        .document-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .document-preview:hover {
            transform: scale(1.1);
        }

        #documentModal {
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

        #modalImage {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 8px;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 30px;
            cursor: pointer;
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            color: #666;
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
            <a href="admin.php" class="back-button">← Back to Admin</a>
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

    <div id="documentModal" onclick="closeDocumentModal()">
        <span class="close-modal" onclick="closeDocumentModal()">&times;</span>
        <img id="modalImage" src="" alt="Document Preview">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const userTableBody = document.getElementById('userTableBody');
            const searchInput = document.getElementById('searchInput');
            const documentModal = document.getElementById('documentModal');
            const modalImage = document.getElementById('modalImage');

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
                                `<img src="${escapeHtml(user.verification_doc)}" 
                                     alt="Document" 
                                     class="document-preview" 
                                     onclick="openDocumentModal('${escapeHtml(user.verification_doc)}')">`
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
        function openDocumentModal(imageSrc) {
            event.stopPropagation();
            modalImage.src = imageSrc;
            documentModal.style.display = 'flex';
        }

        function closeDocumentModal() {
            documentModal.style.display = 'none';
        }

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDocumentModal();
            }
        });
    </script>
</body>
</html>
