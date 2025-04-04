<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Premium Auto Parts</title>
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
        --primary: #2c3e50;
        --secondary: #34495e;
        --accent: #f5b754;
        --light: #ecf0f1;
      }

      /* Navigation */
      nav {
        background: white;
        padding: 1rem 5%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        position: fixed;
        width: 100%;
        z-index: 1000;
      }

      .nav-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
      }

      .logo {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--primary);
      }

      .nav-links {
        display: flex;
        gap: 2rem;
        align-items: center;
      }

      .nav-links a {
        text-decoration: none;
        color: var(--primary);
      }

      .cart-icon {
        position: relative;
      }

      .cart-count {
        position: absolute;
        top: -8px;
        right: -8px;
        background: var(--accent);
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      /* Hero Section */
      .hero {
        padding: 8rem 5% 4rem;
        background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
          url("/api/placeholder/1920/600");
        background-size: cover;
        background-position: center;
        color: white;
        text-align: center;
      }

      .hero h1 {
        font-size: 2.5rem;
        margin-bottom: 1rem;
      }

      .search-bar {
        max-width: 600px;
        margin: 2rem auto;
        display: flex;
        gap: 1rem;
      }

      .search-bar input {
        flex: 1;
        padding: 1rem;
        border: none;
        border-radius: 5px;
      }

      .search-btn {
        padding: 1rem 2rem;
        background: var(--accent);
        border: none;
        border-radius: 5px;
        color: white;
        cursor: pointer;
      }

      /* Categories */
      .categories {
        padding: 4rem 5%;
        background: var(--light);
      }

      .section-title {
        text-align: center;
        margin-bottom: 2rem;
        color: var(--primary);
      }

      .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
      }

      .category-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: transform 0.3s;
      }

      .category-card:hover {
        transform: translateY(-5px);
      }

      .category-card i {
        font-size: 2rem;
        color: var(--accent);
        margin-bottom: 1rem;
      }

      /* Products */
      .products {
        padding: 4rem 5%;
      }

      .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
      }

      .product-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      }

      .product-image {
        height: 200px;
        background: url("/api/placeholder/400/200");
        background-size: cover;
        background-position: center;
      }

      .product-info {
        padding: 1.5rem;
      }

      .product-title {
        font-weight: bold;
        margin-bottom: 0.5rem;
      }

      .product-price {
        color: var(--accent);
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 1rem;
      }

      .add-to-cart {
        width: 100%;
        padding: 0.8rem;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s;
      }

      .add-to-cart:hover {
        background: #2980b9;
      }

      /* Footer */
      footer {
        background: var(--primary);
        color: white;
        padding: 3rem 5%;
        margin-top: 4rem;
      }

      .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
      }

      .footer-section h3 {
        margin-bottom: 1rem;
      }

      .footer-section ul {
        list-style: none;
      }

      .footer-section ul li {
        margin-bottom: 0.5rem;
      }

      .footer-section ul li a {
        color: white;
        text-decoration: none;
      }

      @media (max-width: 768px) {
        .nav-links {
          display: none;
        }
      }
      .luxury-text {
        font-family: 'Montserrat', sans-serif;
        font-size: 1.5rem;
        font-weight: 600;
        text-align: center;
        color: white;
        margin: 2rem 0;
      }
    </style>
  </head>
  <body>
    <!-- Navigation -->
    <nav>
      <div class="nav-content">
        <div class="logo">
        <img src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png" alt="Logo" style="max-width: 160px; height: auto;"></div>
        <div class="nav-links">
          <a href="dashboard.php">Home</a>
          <a href="#categories">Categories</a>
          <a href="#products">Products</a>
          <a href="#contact">Contact</a>
          <div class="cart-icon">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-count">0</span>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
     <br><br><br>
    <section class="hero" style="background-image: url('assets/back spare.jpg'); background-size: cover; background-position: center; color: white; padding: 4rem 0;">
      <p class="luxury-text">Genuine parts for luxury and performance vehicles</p>
      <div class="search-bar">
        <input
          type="text"
          placeholder="Search parts by vehicle model or part number..."
        />
        <button class="search-btn">Search</button>
      </div>
    </section>
    
    <!-- Categories -->
    <section class="categories" id="categories">
      <h2 class="section-title">Shop by Category</h2>
      <div class="category-grid">
        <div class="category-card">
          <i class="fas fa-cog"></i>
          <h3>Engine Parts</h3>
        </div>
        <div class="category-card">
          <i class="fas fa-car-battery"></i>
          <h3>Electrical</h3>
        </div>
        <div class="category-card">
          <i class="fas fa-oil-can"></i>
          <h3>Fluids</h3>
        </div>
        <div class="category-card">
          <i class="fas fa-brake-system"></i>
          <h3>Brake System</h3>
        </div>
      </div>
    </section>

    <!-- Featured Products -->
    <section class="products" id="products">
      <h2 class="section-title">Featured Products</h2>
      <div class="product-grid">
        <div class="product-card">
          <div class="product-image"></div>
          <div class="product-info">
            <div class="product-title">Premium Brake Pads</div>
            <div class="product-price">$199.99</div>
            <button class="add-to-cart">Add to Cart</button>
          </div>
        </div>
        <div class="product-card">
          <div class="product-image"></div>
          <div class="product-info">
            <div class="product-title">High-Performance Oil Filter</div>
            <div class="product-price">$49.99</div>
            <button class="add-to-cart">Add to Cart</button>
          </div>
        </div>
        <div class="product-card">
          <div class="product-image"></div>
          <div class="product-info">
            <div class="product-title">Air Suspension Kit</div>
            <div class="product-price">$899.99</div>
            <button class="add-to-cart">Add to Cart</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer>
      <div class="footer-content">
        <div class="footer-section">
          <h3>About Us</h3>
          <p>Premium auto parts for luxury and performance vehicles.</p>
        </div>
        <div class="footer-section">
          <h3>Quick Links</h3>
          <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#categories">Categories</a></li>
            <li><a href="#products">Products</a></li>
            <li><a href="#contact">Contact</a></li>
          </ul>
        </div>
        <div class="footer-section">
          <h3>Contact</h3>
          <ul>
            <li>Email: info@autoparts.com</li>
            <li>Phone: (123) 456-7890</li>
            <li>Address: 123 Auto Street</li>
          </ul>
        </div>
      </div>
    </footer>

    <script>
      // Cart functionality
      let cartCount = 0;
      const cartCountElement = document.querySelector(".cart-count");
      const addToCartButtons = document.querySelectorAll(".add-to-cart");

      addToCartButtons.forEach((button) => {
        button.addEventListener("click", () => {
          cartCount++;
          cartCountElement.textContent = cartCount;

          // Animation for button
          button.textContent = "Added!";
          button.style.background = "#27ae60";

          setTimeout(() => {
            button.textContent = "Add to Cart";
            button.style.background = "";
          }, 1000);
        });
      });

      // Search functionality
      const searchBtn = document.querySelector(".search-btn");
      const searchInput = document.querySelector(".search-bar input");

      searchBtn.addEventListener("click", () => {
        const searchTerm = searchInput.value.toLowerCase();
        // In a real application, this would trigger a search
        console.log("Searching for:", searchTerm);
      });

      // Smooth scrolling for navigation links
      document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener("click", function (e) {
          e.preventDefault();
          document.querySelector(this.getAttribute("href")).scrollIntoView({
            behavior: "smooth",
          });
        });
      });
    </script>
  </body>
</html>
