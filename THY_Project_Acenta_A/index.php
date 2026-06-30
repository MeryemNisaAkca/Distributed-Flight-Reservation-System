<?php
//It is the central point where users are greeted, can search for flights, begin check-in, and log in.
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file missing.");
}

// 2. OTURUMU BAŞLAT
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'connecting.php';

// Fetch all airports from database for flight search dropdowns
$sqlAirports = "SELECT * FROM Airports_Table ORDER BY City";
$stmtAirports = sqlsrv_query($conn, $sqlAirports);
$airportsArray = [];
if ($stmtAirports) {
    while($a = sqlsrv_fetch_array($stmtAirports, SQLSRV_FETCH_ASSOC)) {
        $airportsArray[] = $a;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Turkish Airlines Acenta A - Widen Your World</title>
    <link rel="stylesheet" href="css/index_style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY Acenta A</a>
            <a href="index.php">Home</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="my_flights.php">My Flights</a>
                <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                    <a href="admin_dashboard.php">Admin Panel</a>
                <?php elseif(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'CompanyOwner'): ?>
                    <a href="admin_flights.php">Flight Management</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="ticket_management.php">Manage Booking</a>
            <?php endif; ?>
        </div>
        
        <div class="navbar-right">
            <?php if(isset($_SESSION['user_name'])): ?>
                <a href="profile.php" style="color: #c8102e; font-weight: bold;">
                    <i class="fas fa-user"></i> Hello, <?php echo $_SESSION['user_name']; ?>
                </a>
                <a href="logout.php" style="background: #c8102e; border-radius: 4px; padding: 5px 10px;">Logout</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Log In</a>
                <a href="register.php" style="background: rgba(255,255,255,0.1); border-radius: 4px; padding: 5px 10px;">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="hero-section">
        <div class="hero-content">
            <h1>Hello, Where next?</h1>
            <p>Discover new places and experiences.</p>
            <?php if(!$conn): ?>
                 <br><small style="color: #ff6b6b; background: rgba(0,0,0,0.8); padding: 5px;">Database Connection Failed!</small>
            <?php endif; ?>
        </div>
    </div>

 <div class="search-container">
        
        <div class="tab-header">
            <button class="tab-btn active" onclick="openTab(event, 'FlightSearch')">
                <i class="fas fa-plane"></i> Book a Flight
            </button>
            <button class="tab-btn" onclick="openTab(event, 'CheckIn')">
                <i class="fas fa-check-circle"></i> Check-in
            </button>
            <button class="tab-btn" onclick="openTab(event, 'FlightManage')">
                <i class="fas fa-suitcase"></i> Flight Manage
            </button>
            <button class="tab-btn" onclick="openTab(event, 'FlightStatus')">
                <i class="fas fa-clock"></i> Flight Status
            </button>
        </div>

        <div id="FlightSearch" class="tab-content active">
            <form action="found_flight.php" method="GET">
                <div class="search-form">
                    <div class="form-group">
                        <label><i class="fas fa-plane-departure"></i> From</label>
                        <select name="nereden" required>
                            <option value="">Select Airport</option>
                            <?php 
                            if (!empty($airportsArray)) {
                                foreach($airportsArray as $a) {
                                    $city = htmlspecialchars($a['City'] ?? '');
                                    $iata = htmlspecialchars($a['IATA'] ?? '');
                                    echo "<option value='$city'>$city ($iata)</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> To</label>
                        <select name="nereye" required>
                            <option value="">Select Airport</option>
                            <?php 
                            if (!empty($airportsArray)) {
                                foreach($airportsArray as $a) {
                                    $city = htmlspecialchars($a['City'] ?? '');
                                    $iata = htmlspecialchars($a['IATA'] ?? '');
                                    echo "<option value='$city'>$city ($iata)</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-exchange-alt"></i> Trip Type</label>
                        <select name="trip_type" id="tripType" onchange="toggleReturnDate()" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="oneway">One Way</option>
                            <option value="roundtrip">Round Trip</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="far fa-calendar-alt"></i> Departure Date</label>
                        <input type="date" name="tarih" id="departureDate" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group" id="returnDateGroup" style="display: none;">
                        <label><i class="far fa-calendar-alt"></i> Return Date</label>
                        <input type="date" name="return_tarih" id="returnDate" min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Passengers</label>
                        <input type="text" id="passengerDisplay" value="1 Passenger" readonly onclick="togglePassengerMenu()" style="cursor: pointer; background: #fff;">
                        
                        <div class="passenger-dropdown" id="passengerMenu">
                            
                            <div class="passenger-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="margin:0;">Baby (0-1 Age)</label>
                                <input type="number" name="baby_count" value="0" min="0" max="5" style="width: 60px; padding: 5px;" onchange="updatePassengerText()">
                            </div>

                            <div class="passenger-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="margin:0;">Child (2-11 Age)</label>
                                <input type="number" name="child_count" value="0" min="0" max="9" style="width: 60px; padding: 5px;" onchange="updatePassengerText()">
                            </div>

                            <div class="passenger-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="margin:0;">Teen (12-24 Age)</label>
                                <input type="number" name="teen_count" value="0" min="0" max="9" style="width: 60px; padding: 5px;" onchange="updatePassengerText()">
                            </div>

                            <div class="passenger-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="margin:0;">Adult (25-64 Age)</label>
                                <input type="number" name="adult_count" value="1" min="1" max="9" style="width: 60px; padding: 5px;" onchange="updatePassengerText()">
                            </div>

                            <div class="passenger-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label style="margin:0;">Old (65+ Age)</label>
                                <input type="number" name="old_count" value="0" min="0" max="9" style="width: 60px; padding: 5px;" onchange="updatePassengerText()">
                            </div>

                             <div style="text-align: right; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                                <button type="button" onclick="togglePassengerMenu()" style="background: #c8102e; color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Done</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="min-width: 180px;">
                        <label><i class="fas fa-chair"></i> Class</label>
                        <div class="class-selection-group">
                            <label class="class-option">
                                <input type="radio" name="sinif" value="Economy" checked>
                                <span>Eco</span>
                            </label>
                            <label class="class-option">
                                <input type="radio" name="sinif" value="Business">
                                <span>Bus</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" style="flex: 0 0 auto;">
                        <label>&nbsp;</label> 
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                    </div>
                </div>
            </form>
        </div>

        <div id="CheckIn" class="tab-content">
            <h3 style="color: #232b38; margin-top: 0;"><i class="fas fa-check-circle"></i> Online Check-in</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Select your seat and complete your check-in process to get your boarding pass.</p>
            
            <form action="ticket_details.php" method="GET" class="simple-form">
                <div class="form-group">
                    <label>Ticket Number or PNR</label>
                    <input type="text" name="pnr" placeholder="e.g. W2K9L1" required>
                </div>
                <div class="form-group">
                    <label>Surname</label>
                    <input type="text" name="surname" placeholder="Passenger Surname" required style="text-transform:uppercase">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-search" style="width: 100%;">Proceed <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        </div>

        <div id="FlightManage" class="tab-content">
            <h3 style="color: #232b38; margin-top: 0;"><i class="fas fa-tasks"></i> Manage Booking</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">View your ticket details, flight times, and passenger information.</p>
            
            <form action="ticket_details.php" method="POST" class="simple-form">
                <div class="form-group">
                    <label>Ticket Number or PNR</label>
                    <input type="text" name="pnr" placeholder="e.g. W2K9L1" required>
                </div>
                <div class="form-group">
                    <label>Surname</label>
                    <input type="text" name="surname" placeholder="Passenger Surname" required style="text-transform:uppercase">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-search" style="width: 100%;">View Ticket <i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>

        <div id="FlightStatus" class="tab-content">
            <h3 style="color: #232b38; margin-top: 0;"><i class="fas fa-plane-arrival"></i> Flight Status</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Check the real-time status of any flight.</p>
            
            <form action="flight_status.php" method="GET" class="simple-form">
                <div class="form-group">
                    <label>Flight Number</label>
                    <input type="text" name="flight_no" placeholder="e.g. TK1920" required>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="flight_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-search" style="width: 100%;">Check Status <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        </div>

    </div>

    <!-- Features Section -->
    <div class="features-section">
        <div class="features-container">
            <h2>Why Choose Us?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Safe & Secure</h3>
                    <p>Your safety is our top priority. We ensure the highest standards of security and comfort.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Round-the-clock customer service to assist you with all your travel needs.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <h3>Loyalty Points</h3>
                    <p>Earn points with every flight and redeem them for discounts on future bookings.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-plane"></i>
                    </div>
                    <h3>Wide Network</h3>
                    <p>Connect to hundreds of destinations worldwide with our extensive flight network.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Premium Meals</h3>
                    <p>Enjoy delicious meals prepared by top chefs during your journey.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-suitcase"></i>
                    </div>
                    <h3>Flexible Baggage</h3>
                    <p>Choose from various baggage options that suit your travel needs.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-section">
                <h3><i class="fas fa-plane-departure"></i> THY Project</h3>
                <p>Your trusted partner for comfortable and reliable air travel. Discover the world with us.</p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#FlightSearch">Book a Flight</a></li>
                    <li><a href="index.php#CheckIn">Check-in</a></li>
                    <li><a href="index.php#FlightManage">Manage Booking</a></li>
                    <li><a href="index.php#FlightStatus">Flight Status</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Services</h4>
                <ul>
                    <li><a href="#">Online Check-in</a></li>
                    <li><a href="#">Baggage Information</a></li>
                    <li><a href="#">Special Assistance</a></li>
                    <li><a href="#">Loyalty Program</a></li>
                    <li><a href="#">Travel Insurance</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <ul>
                    <li><i class="fas fa-phone"></i> +90 (212) 123 45 67</li>
                    <li><i class="fas fa-envelope"></i> info@thyproject.com</li>
                    <li><i class="fas fa-map-marker-alt"></i> Istanbul, Turkey</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> THY Project. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function togglePassengerMenu() {
            var menu = document.getElementById('passengerMenu');
            var input = document.getElementById('passengerDisplay');
            
            if (menu.style.display === "block") {
                menu.style.display = "none";
            } else {
                // Calculate position relative to input field
                var rect = input.getBoundingClientRect();
                menu.style.display = "block";
                // Position dropdown above the input field
                menu.style.bottom = (window.innerHeight - rect.top + 5) + "px";
                menu.style.left = rect.left + "px";
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            var menu = document.getElementById('passengerMenu');
            var input = document.getElementById('passengerDisplay');
            if (menu && input && !menu.contains(event.target) && event.target !== input) {
                menu.style.display = "none";
            }
        });

        function updatePassengerText() {
            // Select all number inputs inside the passenger menu
            var inputs = document.querySelectorAll('#passengerMenu input[type="number"]');
            var total = 0;
            
            // Loop through inputs to calculate total passengers
            inputs.forEach(function(input) {
                total += parseInt(input.value) || 0; // || 0 protects against NaN
            });
            
            // Update the display text
            document.getElementById('passengerDisplay').value = total + " Passenger(s)";
        }

        function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }

        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }

        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.className += " active";
        }

        // Set minimum date to today on page load
        document.addEventListener('DOMContentLoaded', function() {
            var today = new Date().toISOString().split('T')[0];
            var departureDate = document.getElementById('departureDate');
            var returnDate = document.getElementById('returnDate');
            
            if (departureDate) {
                departureDate.min = today;
            }
            if (returnDate) {
                returnDate.min = today;
            }
        });
        
        function toggleReturnDate() {
            var tripType = document.getElementById('tripType').value;
            var returnDateGroup = document.getElementById('returnDateGroup');
            var returnDate = document.getElementById('returnDate');
            var departureDate = document.getElementById('departureDate');
            var today = new Date().toISOString().split('T')[0];
            
            if (tripType === 'roundtrip') {
                returnDateGroup.style.display = 'block';
                returnDate.required = true;
                // Set minimum date to today or departure date (whichever is later)
                if (departureDate.value) {
                    returnDate.min = departureDate.value >= today ? departureDate.value : today;
                } else {
                    returnDate.min = today;
                }
                departureDate.addEventListener('change', function() {
                    if (this.value) {
                        returnDate.min = this.value >= today ? this.value : today;
                    }
                });
            } else {
                returnDateGroup.style.display = 'none';
                returnDate.required = false;
                returnDate.value = '';
            }
        }
    </script>

</body>
</html>