<!DOCTYPE html>
<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "car_rental";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get total users count
$sql = "SELECT COUNT(id) as total_users FROM users";
$result = $conn->query($sql);
$userCount = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userCount = $row["total_users"];
}

$conn->close();
?>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Premium Car Rental</title>
    <link
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
      rel="stylesheet"
    />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      }

      :root {
        --primary: #1a237e;
        --secondary: #283593;
        --accent: #3949ab;
        --light: #f5f6fa;
        --danger: #d32f2f;
        --success: #2e7d32;
        --text-primary: #2c3e50;
        --text-secondary: #546e7a;
        --border-color: #e0e0e0;
      }

      body {
        display: flex;
        background: var(--light);
        color: var(--text-primary);
      }

      /* Sidebar */
      .sidebar {
        width: 250px;
        height: 100vh;
        background: linear-gradient(180deg,  #f5b754 0%,  #f5b754 100%);
        padding: 1rem;
        position: fixed;
        color: white;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
      }

      .menu-items {
        flex: 1;
      }

      .brand {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .brand img {
        width: 150px;
        height: auto;
        object-fit: contain;
      }

      @media (max-width: 768px) {
        .brand img {
          width: 50px;
        }
      }

      .menu-item {
        padding: 0.8rem 1rem;
        margin: 0.5rem 0;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s;
      }

      .menu-item:hover,
      .menu-item.active {
        background: var(--secondary);
      }

      .menu-item i {
        margin-right: 0.8rem;
      }

      .logout-btn {
        padding: 0.8rem 1rem;
        margin-top: auto;
        background: var(--danger);
        color: white;
        text-decoration: none;
        border-radius: 5px;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        transition: all 0.3s ease;
      }

      .logout-btn:hover {
        background: #b71c1c;
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      }

      /* Main Content */
      .main-content {
        margin-left: 250px;
        flex: 1;
        padding: 2rem;
      }

      .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        background: white;
        padding: 1rem 2rem;
        margin: -2rem -2rem 2rem -2rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      }

      .breadcrumb {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
      }

      .search-bar {
        display: flex;
        gap: 1rem;
      }

      .search-bar input {
        padding: 0.5rem 1rem;
        border: 1px solid #ddd;
        border-radius: 5px;
        width: 300px;
      }

      .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .user-info img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
      }

      /* Dashboard Cards */
      .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
      }

      .card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
      }

      .card.clickable {
        cursor: pointer;
        position: relative;
        overflow: hidden;
      }

      .card.clickable::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(196, 164, 124, 0.1);
        opacity: 0;
        transition: opacity 0.3s ease;
      }

      .card.clickable:hover::after {
        opacity: 1;
      }

      .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      }

      .card-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
      }

      .card-content {
        text-align: right;
        position: relative;
        padding: 1rem;
        border-radius: 8px;
        transition: all 0.3s ease;
      }

      .card:hover .card-content {
        background: linear-gradient(145deg, rgba(245, 183, 84, 0.1) 0%, rgba(57, 73, 171, 0.1) 100%);
        transform: translateY(-5px);
      }

      .card:hover .card-number {
        color: var(--accent);
        transform: scale(1.1);
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
      }

      .card-number {
        font-size: 2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
        transition: all 0.3s ease;
      }

      .card:hover .card-label {
        letter-spacing: 2px;
        color: var(--accent);
      }

      .card-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
      }

      @keyframes pulse {
        0% {
          transform: scale(1);
        }
        50% {
          transform: scale(1.05);
        }
        100% {
          transform: scale(1);
        }
      }

      .card:hover .card-icon {
        animation: pulse 1.5s infinite;
        box-shadow: 0 0 15px rgba(57, 73, 171, 0.3);
      }

      /* Tables */
      .table-container {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        border: 1px solid var(--border-color);
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th,
      td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #ddd;
      }

      th {
        background: var(--light);
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
      }

      .status {
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.9rem;
      }

      .status.pending {
        background: #fff3cd;
        color: #856404;
      }

      .status.approved {
        background: #d4edda;
        color: #155724;
      }

      .status.rejected {
        background: #f8d7da;
        color: #721c24;
      }

      .action-buttons {
        display: flex;
        gap: 0.5rem;
      }

      .btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        color: white;
        transition: all 0.3s ease;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
      }

      .btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      }

      .btn-approve {
        background: var(--success);
      }

      .btn-reject {
        background: var(--danger);
      }

      /* Loading State */
      .btn.loading {
        opacity: 0.7;
        cursor: not-allowed;
      }

      .btn.loading::after {
        content: "...";
      }

      tbody tr:hover {
        background-color: rgba(57, 73, 171, 0.1);
        transition: background-color 0.3s ease;
      }

      /* Sections */
      .section {
        display: none;
      }

      .section.active {
        display: block;
      }

      @media (max-width: 768px) {
        .sidebar {
          width: 70px;
          padding: 1rem 0.5rem;
        }

        .brand,
        .menu-item span,
        .logout-btn span {
          display: none;
        }

        .main-content {
          margin-left: 70px;
        }
      }
    </style>
  </head>
  <body>
    <!-- Sidebar -->
    <div class="sidebar">
      <div class="brand">
        <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="Car Rental Logo">
      </div>
      <div class="menu-items">
        <div class="menu-item active" data-section="dashboard">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </div>
        <div class="menu-item" data-section="users">
          <i class="fas fa-users"></i>
          <span>Users & Owners</span>
        </div>
        <div class="menu-item" data-section="inventory">
          <i class="fas fa-car"></i>
          <span>Inventory</span>
        </div>
        <div class="menu-item" data-section="workshops">
          <i class="fas fa-wrench"></i>
          <span>Workshops</span>
        </div>
        <div class="menu-item" data-section="promotions">
          <i class="fas fa-tag"></i>
          <span>Promotions</span>
        </div>
        <div class="menu-item" data-section="complaints">
          <i class="fas fa-comment-alt"></i>
          <span>Complaints</span>
        </div>
      </div>
      <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
      <!-- Header -->
      <div class="header">
        <div class="breadcrumb">
          Dashboard / <span class="current-page">Overview</span>
        </div>
        <h1
          class="page-title"
          style="margin-bottom: 2rem; color: var(--text-primary)"
        >
          Dashboard Overview
        </h1>
        <div class="search-bar">
          <input type="text" placeholder="Search..." />
        </div>
        <div class="user-info">
          <span>Admin User</span>
          <img src="/api/placeholder/40/40" alt="Admin" />
        </div>
      </div>

      <!-- Dashboard Section -->
      <div class="section active" id="dashboard">
        <div class="dashboard-cards">
          <div class="card clickable" onclick="window.location.href='user-list.php'">
            <div class="card-header">
              <div class="card-icon" style="background: var(--accent)">
                <i class="fas fa-users"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number"><?php echo $userCount; ?></div>
              <div class="card-label">Total Users</div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon" style="background: var(--success)">
                <i class="fas fa-car"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number">456</div>
              <div class="card-label">Available Cars</div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon" style="background: var(--danger)">
                <i class="fas fa-wrench"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number">89</div>
              <div class="card-label">Active Workshops</div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <div class="card-icon" style="background: var(--secondary)">
                <i class="fas fa-comment-alt"></i>
              </div>
            </div>
            <div class="card-content">
              <div class="card-number">23</div>
              <div class="card-label">Pending Complaints</div>
            </div>
          </div>
        </div>

        <div class="table-container">
          <h2>Recent Activities</h2>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>User</th>
                <th>Activity</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>#1234</td>
                <td>John Doe</td>
                <td>Car Rental Request</td>
                <td><span class="status pending">Pending</span></td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-approve">Approve</button>
                    <button class="btn btn-reject">Reject</button>
                  </div>
                </td>
              </tr>
              <tr>
                <td>#1235</td>
                <td>Jane Smith</td>
                <td>Workshop Registration</td>
                <td><span class="status approved">Approved</span></td>
                <td>
                  <div class="action-buttons">
                    <button class="btn btn-approve">Approve</button>
                    <button class="btn btn-reject">Reject</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Other Sections -->
      <div class="section" id="users">
        <h2>User & Owner Management</h2>
        <!-- User management content -->
      </div>

      <div class="section" id="inventory">
        <h2>Inventory Management</h2>
        <!-- Inventory management content -->
      </div>

      <div class="section" id="workshops">
        <h2>Workshop Management</h2>
        <!-- Workshop management content -->
      </div>

      <div class="section" id="promotions">
        <h2>Promotions</h2>
        <!-- Promotions content -->
      </div>

      <div class="section" id="complaints">
        <h2>Complaint Handling</h2>
        <!-- Complaints content -->
      </div>
    </div>

    <script>
      // Navigation functionality
      const menuItems = document.querySelectorAll(".menu-item");
      const sections = document.querySelectorAll(".section");

      menuItems.forEach((item) => {
        item.addEventListener("click", () => {
          // Remove active class from all menu items
          menuItems.forEach((i) => i.classList.remove("active"));
          // Add active class to clicked item
          item.classList.add("active");

          // Hide all sections
          sections.forEach((section) => section.classList.remove("active"));
          // Show selected section
          const sectionId = item.getAttribute("data-section");
          document.getElementById(sectionId).classList.add("active");
        });
      });

      // Action buttons functionality
      const actionButtons = document.querySelectorAll(".action-buttons button");
      actionButtons.forEach((button) => {
        button.addEventListener("click", async (e) => {
          const button = e.target;
          button.classList.add("loading");
          button.disabled = true;

          // Simulate API call
          await new Promise((resolve) => setTimeout(resolve, 800));

          const action = button.classList.contains("btn-approve")
            ? "approved"
            : "rejected";
          const row = button.closest("tr");
          const statusCell = row.querySelector(".status");

          statusCell.className = `status ${action}`;
          statusCell.textContent =
            action.charAt(0).toUpperCase() + action.slice(1);

          button.classList.remove("loading");
          button.disabled = false;
        });
      });

      // Search functionality
      const searchInput = document.querySelector(".search-bar input");
      searchInput.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const tableRows = document.querySelectorAll("tbody tr");

        tableRows.forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? "" : "none";
        });
      });
    </script>
  </body>
</html>
