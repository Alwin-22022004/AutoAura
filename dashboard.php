<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}

// Check if user is blocked
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== 'admin' && $_SESSION['user_id'] !== 'owner') {
    $stmt = $conn->prepare("SELECT active FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && $user['active'] === 'blocked') {
        session_destroy();
        header("Location: auth-page.php?error=blocked");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="dashstyle.css" />
    <title>Car Rental Dashboard</title>
    <style>
        /* User dropdown styles */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        /* Help and Support Button */
        .help-support-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #d6a04a;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            font-size: 16px;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        /* Support Modal Styles */
        @keyframes modalFadeIn {
            from {
                background-color: rgba(0, 0, 0, 0);
            }
            to {
                background-color: rgba(0, 0, 0, 0.5);
            }
        }

        @keyframes modalContentShow {
            from {
                transform: scale(0.7) translateY(100px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        @keyframes modalContentHide {
            from {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
            to {
                transform: scale(0.7) translateY(100px);
                opacity: 0;
            }
        }

        .support-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0);
            z-index: 1001;
            justify-content: flex-end;
            align-items: flex-end;
            padding: 30px;
        }

        .support-modal.active {
            display: flex;
            animation: modalFadeIn 0.3s ease forwards;
        }

        .support-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transform-origin: bottom right;
            opacity: 0;
            margin-bottom: 60px; /* Space for the help button */
            margin-right: 30px;
        }

        .support-modal.active .support-modal-content {
            animation: modalContentShow 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .support-modal.closing .support-modal-content {
            animation: modalContentHide 0.3s ease forwards;
        }

        .support-modal.closing {
            animation: modalFadeIn 0.3s ease reverse forwards;
        }

        /* Success Notification Styles */
        @keyframes notificationSlideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes notificationSlideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .success-notification {
            position: fixed;
            top: 30px;
            right: 30px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: flex-start;
            gap: 15px;
            z-index: 1100;
            max-width: 400px;
            animation: notificationSlideIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        .success-notification.hiding {
            animation: notificationSlideOut 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        .success-notification .icon {
            font-size: 24px;
            color: white;
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 5px 0;
        }

        .notification-message {
            font-size: 14px;
            margin: 0;
            opacity: 0.9;
        }

        .ticket-number {
            font-family: monospace;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 5px;
        }

        .close-notification {
            background: none;
            border: none;
            color: white;
            opacity: 0.8;
            cursor: pointer;
            font-size: 20px;
            padding: 0;
            transition: opacity 0.2s;
        }

        .close-notification:hover {
            opacity: 1;
        }

        .support-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .support-modal-header h2 {
            color: #333;
            margin: 0;
            font-size: 24px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            padding: 5px;
        }

        .support-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .submit-support {
            background-color: #d6a04a;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-support:hover {
            background-color: #c49343;
        }

        .help-support-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            background-color: #c49343;
        }

        .help-support-btn i {
            font-size: 20px;
        }

        .user-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            color: #333;
        }

        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: #fff;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1000;
            padding: 8px 0;
        }

        .user-dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            text-decoration: none;
            color: #333;
            transition: background-color 0.2s;
        }

        .dropdown-item:hover {
            background-color: #f5f5f5;
        }

        .user-dropdown-divider {
            height: 1px;
            background-color: #eee;
            margin: 8px 0;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            text-decoration: none;
            color: #dc3545;
            transition: background-color 0.2s;
        }

        .logout-link:hover {
            background-color: #fff5f5;
        }

        /* Add/modify these styles in the existing <style> tag */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Modify header styles */
        header {
            width: 100%;
            background-color: #fff;
            position: relative;
            z-index: 100;
        }

        /* Main content wrapper */
        .main-content {
            flex: 1;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        /* Modify section containers */
        .section__container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        /* Modify footer styles */
        footer {
            width: 100%;
            background-color: #333;
            color: #fff;
            margin-top: auto; /* Push footer to bottom */
        }

        .footer__container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            padding: 4rem 1rem;
        }

        .story__container {
            scroll-margin-top: 80px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .section__container {
                padding: 1rem;
            }

            .footer__container {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
    <style>
            .modal {
                display: none;
                position: fixed;
                z-index: 9999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                overflow: auto;
            }
            .modal-content {
                background-color: #fff;
                margin: 15% auto;
                padding: 30px;
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                position: relative;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .rating {
                text-align: center;
                margin: 20px 0;
                direction: rtl;
            }
            .rating .fa-star {
                color: #ddd;
                font-size: 30px;
                cursor: pointer;
                margin: 0 5px;
                transition: color 0.2s ease;
            }
            .rating .fa-star:hover,
            .rating .fa-star:hover ~ .fa-star,
            .rating .fa-star.active {
                color: #ffd700;
            }
            #reviewText {
                width: 100%;
                padding: 12px;
                margin: 15px 0;
                border: 1px solid #ddd;
                border-radius: 4px;
                resize: vertical;
                min-height: 100px;
                font-size: 14px;
            }
            .modal-buttons {
                text-align: right;
                margin-top: 20px;
            }
            .modal-buttons button {
                padding: 8px 20px;
                margin-left: 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-secondary {
                background-color: #6c757d;
                color: white;
                border: none;
            }
            .btn-primary {
                background-color: #007bff;
                color: white;
                border: none;
            }
            .modal h2 {
                text-align: center;
                color: #333;
                margin-bottom: 20px;
            }
        </style>
  </head>
  <body>
    <!-- Add feedback modal -->
    <div id="feedbackModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Share Your Experience</h2>
            <div class="rating">
                <i class="fas fa-star" data-rating="1"></i>
                <i class="fas fa-star" data-rating="2"></i>
                <i class="fas fa-star" data-rating="3"></i>
                <i class="fas fa-star" data-rating="4"></i>
                <i class="fas fa-star" data-rating="5"></i>
            </div>
            <textarea id="reviewText" placeholder="Write your review here..." rows="4"></textarea>
            <input type="hidden" id="bookingId">
            <input type="hidden" id="carId">
            <div class="modal-buttons">
                <button id="skipFeedback" class="btn btn-secondary">Skip</button>
                <button id="submitFeedback" class="btn btn-primary">Submit</button>
            </div>
        </div>
    </div>
    <header>
      <nav>
        <div class="nav__header">
          <div class="nav__logo">
            <img
              src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png"
              alt="Car Rental Logo"
            />
          </div>
          <div class="nav__menu__btn" id="menu-btn">
            
          </div>
        </div>

        <ul class="nav__links" id="nav-links">
          <li><a href="#home">Home</a></li>
          <li><a href="#rent">Rent</a></li>
          <li><a href="#ride">Ride</a></li>
          <li><a href="#about-us">About us</a></li>
        </ul>

        <div class="nav__user">
          <div class="user-dropdown">
            <button type="button" class="user-icon-btn" id="user-btn">
                <span class="user-icon">
                  <i class="fas fa-user-circle"></i>
                </span>
              <span class="user-name">
                <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?>
              </span>
              <i class="fas fa-chevron-down"></i>
            </button>
            <div class="user-dropdown-content" id="user-dropdown">
              <a href="user-profile.php" class="dropdown-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
              </a>
              <a href="enquiry.php" class="dropdown-item">
                <i class="fas fa-question-circle"></i>
                <span>Enquiry</span>
              </a>
              <div class="user-dropdown-divider"></div>
              <a href="logout.php" class="logout-link" onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>
        </div>
      </nav>
      <br>
      <div class="header__container" id="home">
        <h1>PREMIUM CAR RENTAL</h1>
        <br><br>
        <img src="assets/header.png" alt="header" />
      </div>
      <a href="#about-us" class="scroll__down">
        <i class="ri-arrow-down-line"></i>
      </a>
    </header>

    <div class="main-content">
        <section class="section__container range__container" id="about">
            <h2 class="section__header">WIDE RANGE OF VEHICLES</h2>
            <div class="range__grid">
                <div class="range__card">
                    <img src="assets/range-1.jpg" alt="range" />
                    <div class="range__details">
                        <h4>CARS</h4>
                        <a href="#"><i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
                <div class="range__card">
                    <img src="assets/range-2.jpg" alt="range" />
                    <div class="range__details">
                        <h4>SUVS</h4>
                        <a href="#"><i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
                <div class="range__card">
                    <img src="assets/range-3.jpg" alt="range" />
                    <div class="range__details">
                        <h4>VANS</h4>
                        <a href="#"><i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
                <div class="range__card">
                    <img src="assets/range-4.jpg" alt="range" />
                    <div class="range__details">
                        <h4>ELECTRIC</h4>
                        <a href="#"><i class="ri-arrow-right-line"></i></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="section__container location__container" id="rent">
            <div class="location__image">
                <img src="assets/location.png" alt="location" />
            </div>
            <div class="location__content">
                <h2 class="section__header">FIND CAR IN YOUR LOCATIONS</h2>
                <p>
                    Discover the perfect vehicle tailored to your needs, wherever you are.
                    Our 'Find Car in Your Locations' feature allows you to effortlessly
                    search and select from our premium fleet available near you. Whether
                    you're looking for a luxury sedan, a spacious SUV, or a sporty
                    convertible, our easy-to-use tool ensures you find the ideal car for
                    your journey. Simply enter your location, and let us connect you with
                    top-tier vehicles ready for rental.
                </p>
                <div class="location__btn">
                    <button class="btn">
                        <a href="location.html" style="color: white">Find a Location</a>
                    </button>
                </div>
            </div>
        </section>

        <section class="select__container" id="ride">
            <h2 class="section__header">TOP PICKED YOUR DREAM CARS TODAY</h2>
            <!-- Slider main container -->
            <div class="swiper">
                <!-- Additional required wrapper -->
                <div class="swiper-wrapper">
                    <!-- Slides -->
                    <div class="swiper-slide">
                        <div class="select__card">
                            <img src="assets/select-1.png" alt="select" />
                            <div class="select__info">
                                <div class="select__info__card">
                                    <span><i class="ri-speed-up-line"></i></span>
                                    <h4>200 <span>km/h</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-settings-5-line"></i></span>
                                    <h4>6 <span>speed</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-roadster-line"></i></span>
                                    <h4>5 <span>seats</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-signpost-line"></i></span>
                                    <h4>15 <span>milage</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="select__card">
                            <img src="assets/select-2.png" alt="select" />
                            <div class="select__info">
                                <div class="select__info__card">
                                    <span><i class="ri-speed-up-line"></i></span>
                                    <h4>215 <span>km/h</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-settings-5-line"></i></span>
                                    <h4>6 <span>speed</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-roadster-line"></i></span>
                                    <h4>5 <span>seats</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-signpost-line"></i></span>
                                    <h4>16 <span>milage</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="select__card">
                            <img src="assets/select-3.png" alt="select" />
                            <div class="select__info">
                                <div class="select__info__card">
                                    <span><i class="ri-speed-up-line"></i></span>
                                    <h4>306 <span>km/h</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-settings-5-line"></i></span>
                                    <h4>6 <span>speed</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-roadster-line"></i></span>
                                    <h4>5 <span>seats</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-signpost-line"></i></span>
                                    <h4>12 <span>milage</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="select__card">
                            <img src="assets/select-4.png" alt="select" />
                            <div class="select__info">
                                <div class="select__info__card">
                                    <span><i class="ri-speed-up-line"></i></span>
                                    <h4>350 <span>km/h</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-settings-5-line"></i></span>
                                    <h4>6 <span>speed</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-roadster-line"></i></span>
                                    <h4>2 <span>seats</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-signpost-line"></i></span>
                                    <h4>08 <span>milage</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="select__card">
                            <img src="assets/select-5.png" alt="select" />
                            <div class="select__info">
                                <div class="select__info__card">
                                    <span><i class="ri-speed-up-line"></i></span>
                                    <h4>254 <span>km/h</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-settings-5-line"></i></span>
                                    <h4>6 <span>speed</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-roadster-line"></i></span>
                                    <h4>5 <span>seats</span></h4>
                                </div>
                                <div class="select__info__card">
                                    <span><i class="ri-signpost-line"></i></span>
                                    <h4>10 <span>milage</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Navigation buttons -->
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
                <!-- Pagination dots -->
                <div class="swiper-pagination"></div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
                <a href="rental-cars-listing.php" class="view-more-btn" style="
                    background-color: #f5b754;
                    color: #fff;
                    padding: 12px 24px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                ">
                    View More Cars <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </section>

        <section class="story__container" id="about-us">
            <h2 class="section__header">STORIES BEHIND THE WHEEL</h2>
            <div class="story__grid">
                <div class="story__card">
                    <div class="story__date">
                        <span>12</span>
                        <div>
                            <p>January</p>
                            <p>2024</p>
                        </div>
                    </div>
                    <h4>Adventures on the Open Road</h4>
                    <p>
                        Join us as we dive into the exhilarating stories of travelers who
                        embarked on unforgettable journeys with PREMIUM CAR RENTAL.
                    </p>
                    <img src="assets/story-1.jpg" alt="story" />
                </div>
                <div class="story__card">
                    <div class="story__date">
                        <span>04</span>
                        <div>
                            <p>March</p>
                            <p>2024</p>
                        </div>
                    </div>
                    <h4>Luxury and Comfort: Experiences</h4>
                    <p>
                        In this series, we highlight the luxurious touches, unparalleled
                        comfort, and exceptional service that make every ride.
                    </p>
                    <img src="assets/story-2.jpg" alt="story" />
                </div>
                <div class="story__card">
                    <div class="story__date">
                        <span>18</span>
                        <div>
                            <p>June</p>
                            <p>2024</p>
                        </div>
                    </div>
                    <h4>Cars that Adapt to Your Lifestyle</h4>
                    <p>
                        Read about how our versatile vehicles have seamlessly integrated
                        into the lives of professionals and families alike.
                    </p>
                    <br>
                    <img src="assets/story-3.jpg" alt="story" />
                </div>
            </div>
        </section>

        <section class="banner__container">
            <div class="banner__wrapper">
                <img src="assets/banner-1.png" alt="banner" />
                <img src="assets/banner-2.png" alt="banner" />
                <img src="assets/banner-3.png" alt="banner" />
                <img src="assets/banner-4.png" alt="banner" />
                <img src="assets/banner-5.png" alt="banner" />
                <img src="assets/banner-6.png" alt="banner" />
                <img src="assets/banner-7.png" alt="banner" />
                <img src="assets/banner-8.png" alt="banner" />
                <img src="assets/banner-9.png" alt="banner" />
                <img src="assets/banner-10.png" alt="banner" />
            </div>
        </section>

        

        <script>
            // User dropdown functionality
            document.addEventListener('DOMContentLoaded', function() {
                const userBtn = document.getElementById('user-btn');
                const userDropdown = document.getElementById('user-dropdown');
                
                userBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!userBtn.contains(e.target)) {
                        userDropdown.classList.remove('show');
                    }
                });
            });
        </script>
    </div>

    <footer>
        <div class="section__container footer__container">
            <div class="footer__col">
                <h4>Resources</h4>
                <ul class="footer__links">
                    <li><a href="#">Installation Manual</a></li>
                    <li><a href="#">Release Note</a></li>
                    <li><a href="#">Community Help</a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h4>Company</h4>
                <ul class="footer__links">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Career</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Support</a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h4>Product</h4>
                <ul class="footer__links">
                    <li><a href="#">Demo</a></li>
                    <li><a href="#">Security</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Features</a></li>
                </ul>
            </div>
            <div class="footer__col">
                <h4>Follow Us</h4>
                <ul class="footer__socials">
                    <li>
                        <a href="#"><i class="ri-facebook-fill"></i></a>
                    </li>
                    <li>
                        <a href="#"><i class="ri-twitter-fill"></i></a>
                    </li>
                    <li>
                        <a href="#"><i class="ri-linkedin-fill"></i></a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="footer__bar">
            Copyright 2024 Web Design Mastery. All rights reserved.
        </div>
    </footer>

    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const swiper = new Swiper('.swiper', {
            loop: true,
            centeredSlides: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            slidesPerView: 1.5,
            spaceBetween: 30,
            breakpoints: {
                768: {
                    slidesPerView: 2.5,
                },
                1024: {
                    slidesPerView: 2.5,
                },
            },
            effect: 'coverflow',
            coverflowEffect: {
                rotate: 0,
                stretch: 0,
                depth: 100,
                modifier: 1.5,
                slideShadows: false,
            },
        });
    </script>
    <script src="dash.js"></script>
    <script>
    $(document).ready(function() {
        // Check for pending feedback
        function checkPendingFeedback() {
            console.log('Checking for pending feedback...');
            $.ajax({
                url: 'check_feedback.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Feedback check response:', response);
                    if (response.showFeedback) {
                        $('#bookingId').val(response.bookingId);
                        $('#carId').val(response.carId);
                        $('#feedbackModal').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error checking feedback:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        }

        // Initialize rating system
        let selectedRating = 0;
        $('.rating .fa-star').on('click', function() {
            console.log('Star clicked:', $(this).data('rating'));
            selectedRating = $(this).data('rating');
            $('.rating .fa-star').removeClass('active');
            $(this).prevAll('.fa-star').addBack().addClass('active');
        });

        // Handle skip button
        $('#skipFeedback').click(function() {
            const bookingId = $('#bookingId').val();
            console.log('Skipping feedback for booking:', bookingId);
            $.ajax({
                url: 'update_feedback.php',
                method: 'POST',
                data: {
                    bookingId: bookingId,
                    action: 'skip'
                },
                success: function(response) {
                    console.log('Skip feedback response:', response);
                    $('#feedbackModal').hide();
                },
                error: function(xhr, status, error) {
                    console.error('Error skipping feedback:', error);
                    alert('Failed to skip feedback. Please try again.');
                }
            });
        });

        // Handle submit button
        $('#submitFeedback').click(function() {
            if (selectedRating === 0) {
                alert('Please select a rating');
                return;
            }

            const bookingId = $('#bookingId').val();
            const carId = $('#carId').val();
            const reviewText = $('#reviewText').val().trim();
            
            if (!reviewText) {
                alert('Please write your review');
                return;
            }

            console.log('Submitting feedback:', {
                bookingId,
                carId,
                rating: selectedRating,
                reviewText
            });

            $.ajax({
                url: 'update_feedback.php',
                method: 'POST',
                data: {
                    bookingId: bookingId,
                    carId: carId,
                    rating: selectedRating,
                    reviewText: reviewText,
                    action: 'submit'
                },
                success: function(response) {
                    console.log('Submit feedback response:', response);
                    $('#feedbackModal').hide();
                    // Reset form
                    selectedRating = 0;
                    $('.rating .fa-star').removeClass('active');
                    $('#reviewText').val('');
                },
                error: function(xhr, status, error) {
                    console.error('Error submitting feedback:', error);
                    alert('Failed to submit feedback. Please try again.');
                }
            });
        });

        // Check for pending feedback on page load
        console.log('Document ready, checking for feedback...');
        checkPendingFeedback();
    });
    </script>
  </main>

    <!-- Help and Support Button -->
    <button class="help-support-btn" id="openSupportModal">
        <i class="fas fa-headset"></i>
        Help & Support
    </button>

    <!-- Support Modal -->
    <div class="support-modal" id="supportModal">
        <div class="support-modal-content">
            <div class="support-modal-header">
                <h2><i class="fas fa-headset"></i> Help & Support</h2>
                <button class="close-modal" id="closeSupportModal">&times;</button>
            </div>
            <form class="support-form" id="supportForm">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required placeholder="Brief description of your issue">
                </div>
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required placeholder="Please describe your problem in detail"></textarea>
                </div>
                <button type="submit" class="submit-support">Submit</button>
            </form>
        </div>
    </div>

    <script>
        // Support Modal Functionality
        const supportModal = document.getElementById('supportModal');
        const openSupportModal = document.getElementById('openSupportModal');
        const closeSupportModal = document.getElementById('closeSupportModal');
        const supportForm = document.getElementById('supportForm');

        function closeModal() {
            supportModal.classList.add('closing');
            setTimeout(() => {
                supportModal.classList.remove('active', 'closing');
            }, 300); // Match the animation duration
        }

        openSupportModal.addEventListener('click', () => {
            supportModal.classList.remove('closing');
            supportModal.classList.add('active');
        });

        closeSupportModal.addEventListener('click', closeModal);

        // Close modal when clicking outside
        supportModal.addEventListener('click', (e) => {
            if (e.target === supportModal) {
                closeModal();
            }
        });

        // Handle form submission
        supportForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(supportForm);
            
            try {
                const response = await fetch('submit_support.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const ticketNumber = `#${String(result.complaint_id).padStart(6, '0')}`;
                    showSuccessNotification(ticketNumber);
                    supportForm.reset();
                    closeModal();
                } else {
                    alert(result.message);
                }
            } catch (error) {
                alert('Error submitting support request. Please try again.');
            }
        });
        // Success Notification Function
        function showSuccessNotification(ticketNumber) {
            // Remove any existing notifications
            const existingNotification = document.querySelector('.success-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'success-notification';
            notification.innerHTML = `
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="notification-content">
                    <h3 class="notification-title">Support Request Submitted</h3>
                    <p class="notification-message">
                        Your ticket<span class="ticket-number">${ticketNumber}</span> has been created.
                        Our team will respond shortly.
                    </p>
                </div>
                <button class="close-notification" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            // Add to document
            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('hiding');
                setTimeout(() => notification.remove(), 500);
            }, 5000);

            // Close on click
            notification.querySelector('.close-notification').addEventListener('click', () => {
                notification.classList.add('hiding');
                setTimeout(() => notification.remove(), 500);
            });
        }
    </script>
  </body>
</html>