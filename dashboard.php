<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth-page.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
  </head>
  <body>
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
          <li><a href="#contact">Reviews</a></li>
          <li><a href="#spare">Spare</a></li>
          <li><a href="#workshops">Workshops</a></li>
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
              <a href="settings.php" class="dropdown-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
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
        <form action="/">
          <div class="input__group">
            <label for="location">Pick up & Return location</label>
            <input
              type="text"
              name="location"
              id="location"
              placeholder="Dallas, Texas"
            />
          </div>
          <div class="input__group">
            <label for="start">Start</label>
            <input
              type="text"
              name="start"
              id="start"
              placeholder="Aug 16, 10:00 AM"
            />
          </div>
          <div class="input__group">
            <label for="stop">Stop</label>
            <input
              type="text"
              name="stop"
              id="stop"
              placeholder="Aug 18, 10:00 PM"
            />
          </div>
          <button class="btn">
            <i class="ri-search-line"></i>
          </button>
        </form>
        <img src="assets/header.png" alt="header" />
      </div>
      <a href="#about" class="scroll__down">
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

        <section class="story__container" id="story">
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

        <section class="feedback-section" id="contact">
            <div class="feedback-container">
                <div class="feedback-header">
                    <h2>Share Your Experience</h2>
                    <p>Your feedback helps us improve and provide better service to our community.</p>
                </div>
                
                <div class="feedback-grid">
                    <div class="feedback-box">
                        <div class="rating-container">
                            <div class="stars">
                                <i class="star fas fa-star" data-rating="1"></i>
                                <i class="star fas fa-star" data-rating="2"></i>
                                <i class="star fas fa-star" data-rating="3"></i>
                                <i class="star fas fa-star" data-rating="4"></i>
                                <i class="star fas fa-star" data-rating="5"></i>
                            </div>
                        </div>
                        
                        <form class="feedback-form" id="feedbackForm">
                            <textarea 
                                id="reviewText"
                                placeholder="Tell us about your experience with our service..."
                                maxlength="500"
                            ></textarea>
                            
                            <div class="feedback-actions">
                                <span class="char-count">0/500 characters</span>
                                <button type="submit" class="post-btn" disabled>
                                    Post Review
                                    <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>
                        
                        <div class="success-message">
                            Thank you! Your feedback has been submitted successfully.
                        </div>
                    </div>

                    <!-- Reviews Display Section -->
                    <div class="reviews-container">
                        <h3>Recent Reviews</h3>
                        <div id="reviewsList" class="reviews-list">
                            <!-- Reviews will be loaded here dynamically -->
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .feedback-section {
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    padding: 4rem 0;
                    position: relative;
                    overflow: hidden;
                }

                .feedback-section::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #f5b754, #f8d094, #f5b754);
                    animation: shimmer 2s infinite linear;
                }

                @keyframes shimmer {
                    0% { background-position: -200% 0; }
                    100% { background-position: 200% 0; }
                }

                .feedback-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 0 20px;
                }

                .feedback-header {
                    text-align: center;
                    margin-bottom: 3rem;
                }

                .feedback-header h2 {
                    font-size: 2.5rem;
                    color: #2c3e50;
                    margin-bottom: 1rem;
                    font-weight: 600;
                }

                .feedback-header p {
                    color: #666;
                    font-size: 1.1rem;
                    max-width: 600px;
                    margin: 0 auto;
                }

                .feedback-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 2rem;
                    margin-bottom: 3rem;
                }

                .feedback-box {
                    background: white;
                    border-radius: 15px;
                    padding: 2rem;
                    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                    transition: transform 0.3s ease;
                }

                .feedback-box:hover {
                    transform: translateY(-5px);
                }

                .rating-container {
                    margin-bottom: 1.5rem;
                }

                .stars {
                    display: flex;
                    gap: 5px;
                    font-size: 1.8rem;
                }

                .star {
                    color: #ddd;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .star:hover {
                    transform: scale(1.2);
                }

                .star.active {
                    color: #f5b754;
                }

                .feedback-form textarea {
                    width: 100%;
                    min-height: 120px;
                    padding: 1rem;
                    border: 2px solid #eee;
                    border-radius: 10px;
                    margin-bottom: 1rem;
                    resize: vertical;
                    font-family: inherit;
                    font-size: 1rem;
                    transition: border-color 0.3s ease;
                }

                .feedback-form textarea:focus {
                    outline: none;
                    border-color: #f5b754;
                }

                .feedback-actions {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .char-count {
                    color: #666;
                    font-size: 0.9rem;
                }

                .post-btn {
                    background: #f5b754;
                    color: white;
                    border: none;
                    padding: 0.8rem 2rem;
                    border-radius: 25px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .post-btn:hover {
                    background: #e4a643;
                    transform: translateX(5px);
                }

                .post-btn:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                    transform: none;
                }

                .post-btn i {
                    transition: transform 0.3s ease;
                }

                .post-btn:hover i {
                    transform: translateX(5px);
                }

                .success-message {
                    display: none;
                    text-align: center;
                    padding: 1rem;
                    background: #4CAF50;
                    color: white;
                    border-radius: 10px;
                    margin-top: 1rem;
                }

                .reviews-container {
                    margin-top: 3rem;
                }

                .reviews-container h3 {
                    color: #2c3e50;
                    margin-bottom: 1.5rem;
                    text-align: center;
                }

                .reviews-list {
                    display: grid;
                    gap: 1.5rem;
                    max-width: 800px;
                    margin: 0 auto;
                }

                .review-item {
                    background: white;
                    border-radius: 10px;
                    padding: 1.5rem;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }

                .review-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 1rem;
                }

                .review-user {
                    font-weight: 600;
                    color: #2c3e50;
                }

                .review-date {
                    color: #666;
                    font-size: 0.9rem;
                }

                .review-rating {
                    color: #f5b754;
                    margin-bottom: 0.5rem;
                }

                .review-text {
                    color: #444;
                    line-height: 1.6;
                }

                @media (max-width: 768px) {
                    .feedback-header h2 {
                        font-size: 2rem;
                    }
                    
                    .feedback-grid {
                        grid-template-columns: 1fr;
                    }

                    .feedback-box {
                        padding: 1.5rem;
                    }
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stars = document.querySelectorAll('.star');
                    const textarea = document.querySelector('#reviewText');
                    const charCount = document.querySelector('.char-count');
                    const submitBtn = document.querySelector('.post-btn');
                    const form = document.querySelector('.feedback-form');
                    const successMessage = document.querySelector('.success-message');
                    const reviewsList = document.querySelector('#reviewsList');
                    let rating = 0;

                    // Star rating functionality
                    stars.forEach(star => {
                        star.addEventListener('mouseover', function() {
                            const rating = this.dataset.rating;
                            highlightStars(rating);
                        });

                        star.addEventListener('mouseout', function() {
                            highlightStars(rating);
                        });

                        star.addEventListener('click', function() {
                            rating = this.dataset.rating;
                            highlightStars(rating);
                            validateForm();
                        });
                    });

                    function highlightStars(rating) {
                        stars.forEach(star => {
                            const starRating = star.dataset.rating;
                            star.classList.toggle('active', starRating <= rating);
                        });
                    }

                    // Character count and form validation
                    textarea.addEventListener('input', function() {
                        const length = this.value.length;
                        charCount.textContent = `${length}/500 characters`;
                        validateForm();
                    });

                    function validateForm() {
                        submitBtn.disabled = !(rating > 0 && textarea.value.trim().length > 0);
                    }

                    // Load existing reviews
                    function loadReviews() {
                        fetch('get_reviews.php')
                            .then(response => response.json())
                            .then(reviews => {
                                reviewsList.innerHTML = reviews.map(review => `
                                    <div class="review-item">
                                        <div class="review-header">
                                            <span class="review-user">${review.fullname}</span>
                                            <span class="review-date">${new Date(review.created_at).toLocaleDateString()}</span>
                                        </div>
                                        <div class="review-rating">
                                            ${'★'.repeat(review.rating)}${'☆'.repeat(5-review.rating)}
                                        </div>
                                        <div class="review-text">${review.review_text}</div>
                                    </div>
                                `).join('');
                            })
                            .catch(error => console.error('Error loading reviews:', error));
                    }

                    // Load reviews on page load
                    loadReviews();

                    // Form submission
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

                        // Submit review to server
                        fetch('submit_review.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                rating: rating,
                                review: textarea.value.trim()
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                form.style.display = 'none';
                                successMessage.style.display = 'block';
                                
                                // Reset form and reload reviews after 3 seconds
                                setTimeout(() => {
                                    form.reset();
                                    form.style.display = 'block';
                                    successMessage.style.display = 'none';
                                    rating = 0;
                                    highlightStars(0);
                                    submitBtn.innerHTML = 'Post Review <i class="fas fa-arrow-right"></i>';
                                    charCount.textContent = '0/500 characters';
                                    validateForm();
                                    loadReviews(); // Reload the reviews
                                }, 3000);
                            } else {
                                throw new Error(data.error || 'Failed to submit review');
                            }
                        })
                        .catch(error => {
                            alert('Error submitting review: ' + error.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Post Review <i class="fas fa-arrow-right"></i>';
                        });
                    });
                });
            </script>
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
  </body>
</html>