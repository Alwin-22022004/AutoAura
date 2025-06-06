<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
    />
    <link rel="stylesheet" href="styles.css" />
    <title>Web Design Mastery | RENTAL</title>
  </head>
  <body>
    <header>
      <nav>
        <div class="nav__header">
          <div class="nav__logo">
            <img
              src="assets/Grey_and_Black_Car_Rental_Service_Logo-removebg-preview.png"
              style="width: 150px; margin-left: -100px"
            />
            <a href="#"></a>
          </div>
          <div class="nav__menu__btn" id="menu-btn">
            <i class="ri-menu-line"></i>
          </div>
        </div>
        <ul
          class="nav__links"
          id="nav-links"
          style="justify-content: flex-start"
        >
          <li><a href="#home">Home</a></li>
          <li><a href="#rent">Rent</a></li>
          <li><a href="#ride">Ride</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="nav__btn">
          <a href="auth-page.php">
            <button class="btn">Log in</button>
          </a>
        </div>
      </nav>
      <div class="header__container" id="home">
        <h1>PREMIUM CAR RENTAL</h1>
        
        <img src="assets/header.png" alt="header" />
      </div>
      <a href="#about" class="scroll__down">
        <i class="ri-arrow-down-line"></i>
      </a>
    </header>

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
            <a href="location.php" style="color: white">Find a Location</a>
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
      </div>
      <form action="/" class="select__form">
        <div class="select__price">
          <span><i class="ri-price-tag-3-line"></i></span>
          <div><span id="select-price">10000</span> /day</div>
        </div>
        <div class="select__btns">
          <a href="auth-page.php" class="btn btn-outline">
            <i class="ri-file-list-3-line"></i> View Details
          </a>
          <a href="auth-page.php" class="btn btn-primary">
            <i class="ri-car-line"></i> Rent Now
          </a>
        </div>
      </form>
    </section>

    <section class="section__container story__container">
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

    <section class="news" id="contact">
      <div class="section__container news__container">
        <h2 class="section__header">In-Depth Reviews: Stay Informed on the Latest Trends.</h2>
        
        <div class="reviews-container">
            <div class="reviews-grid" id="reviews-grid">
                <!-- Reviews will be loaded here -->
            </div>
        </div>

        <style>
            .reviews-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 2rem;
            }

            .reviews-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
                margin-top: 2rem;
            }

            .review-card {
                background: white;
                border-radius: 15px;
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

            .review-rating {
                color: #f5b754;
            }

            .review-text {
                color: #666;
                line-height: 1.6;
                margin-bottom: 1rem;
            }

            .review-date {
                font-size: 0.85rem;
                color: #999;
                text-align: right;
            }
        </style>

        <script>
            function loadReviews() {
                fetch('get_reviews.php')
                    .then(response => response.json())
                    .then(reviews => {
                        const reviewsGrid = document.getElementById('reviews-grid');
                        reviewsGrid.innerHTML = reviews.map(review => `
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-user">${review.fullname}</div>
                                    <div class="review-rating">
                                        ${'★'.repeat(review.rating)}${'☆'.repeat(5-review.rating)}
                                    </div>
                                </div>
                                <div class="review-text">${review.review_text}</div>
                                <div class="review-date">${new Date(review.created_at).toLocaleDateString()}</div>
                            </div>
                        `).join('');
                    })
                    .catch(error => console.error('Error loading reviews:', error));
            }

            // Load reviews when the page loads
            document.addEventListener('DOMContentLoaded', loadReviews);
        </script>
      </div>
    </section>

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
        Copyright  autoaura premium cars. All rights reserved.
      </div>
    </footer>

    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="main.js"></script>
  </body>
</html>
