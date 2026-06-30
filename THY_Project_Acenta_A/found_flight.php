<?php
//This is the Flight Search Results page. It queries the database based on the criteria entered by the user on the main page (From, To, Date, Number of Passengers) and lists suitable flights.
//The critical function of this page is to differentiate between round-trip and one-way flights. If the user selects a round-trip, clicking the "Select" button here will redirect them to the return flight selection page, not the payment page.
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
session_start();
include 'connecting.php';

// Get all inputs safely
$fromCity   = $_GET['nereden'] ?? '';
$toCity     = $_GET['nereye'] ?? '';
$dateInput  = $_GET['tarih'] ?? ''; 
$cabinClass = $_GET['sinif'] ?? 'Economy';
$tripType   = $_GET['trip_type'] ?? 'oneway';
$returnDate = $_GET['return_tarih'] ?? '';

//Get passenger counts individually (Default to 0, except adult)
$adultCount = isset($_GET['adult_count']) ? (int)$_GET['adult_count'] : 1;
$childCount = isset($_GET['child_count']) ? (int)$_GET['child_count'] : 0;
$teenCount  = isset($_GET['teen_count']) ? (int)$_GET['teen_count'] : 0;
$oldCount   = isset($_GET['old_count']) ? (int)$_GET['old_count'] : 0;
$babyCount  = isset($_GET['baby_count']) ? (int)$_GET['baby_count'] : 0;

// Calculate Total Seats Needed (Babies usually sit on lap, so we don't count them for seats)
// If you want babies to have seats, add $babyCount here too.
$totalSeatsNeeded = $adultCount + $childCount + $teenCount + $oldCount;

// Total humans for display purpose
$totalPassengersDisplay = $totalSeatsNeeded + $babyCount;

// Get sort parameter
$sortBy = $_GET['sort'] ?? 'time_asc'; // Default: sort by time ascending

// Validate date is not in the past
$today = date('Y-m-d');
if ($dateInput < $today) {
    die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;'>
        <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Invalid Date</h3>
        <p style='color:#666;'>You cannot search for flights in the past. Please select today's date or a future date.</p>
        <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Go Back to Search</a>
    </div>");
}

// Update SQL to check seat availability and exclude past flights
$sql = "
    SELECT * FROM VW_FlightDetails 
    WHERE 
        [Departure City] = ? 
        AND [Arrival City] = ? 
        AND CAST([Departure Time] AS DATE) = ?
        AND [Cabin Type] = ?
        AND [Remaining Seats] >= ? 
        AND [Departure Time] >= GETDATE()
";

// Add $totalSeatsNeeded to the parameters
$params = array($fromCity, $toCity, $dateInput, $cabinClass, $totalSeatsNeeded);
$stmt = sqlsrv_query($conn, $sql, $params);
// Error Handling
if ($stmt === false) {
    $errors = sqlsrv_errors();
    error_log("Flight search query failed: " . print_r($errors, true));
    die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;'>
        <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Search Error</h3>
        <p style='color:#666;'>An error occurred while searching for flights. Please try again.</p>
        <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Go Back</a>
    </div>");
}

// Fetch all results into array for sorting
$flightsArray = [];
if (sqlsrv_has_rows($stmt)) {
    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $flightsArray[] = $row;
    }
}

// Sort flights based on selected option
if (!empty($flightsArray)) {
    usort($flightsArray, function($a, $b) use ($sortBy) {
        switch($sortBy) {
            case 'price_asc':
                // Sort by price ascending (lowest first)
                $priceA = (float)($a['Basic Price'] ?? 0);
                $priceB = (float)($b['Basic Price'] ?? 0);
                return $priceA <=> $priceB;
                
            case 'price_desc':
                // Sort by price descending (highest first)
                $priceA = (float)($a['Basic Price'] ?? 0);
                $priceB = (float)($b['Basic Price'] ?? 0);
                return $priceB <=> $priceA;
                
            case 'time_asc':
                // Sort by departure time ascending (earliest first)
                $timeA = $a['Departure Time'];
                $timeB = $b['Departure Time'];
                if ($timeA instanceof DateTime && $timeB instanceof DateTime) {
                    return $timeA <=> $timeB;
                }
                return 0;
                
            case 'time_desc':
                // Sort by departure time descending (latest first)
                $timeA = $a['Departure Time'];
                $timeB = $b['Departure Time'];
                if ($timeA instanceof DateTime && $timeB instanceof DateTime) {
                    return $timeB <=> $timeA;
                }
                return 0;
                
            default:
                return 0;
        }
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flight Results - THY Project</title>
    <link rel="stylesheet" href="css/index_style.css"> 
    <link rel="stylesheet" href="css/found_flight_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY PROJECT</a>
            <a href="index.php">Home</a>
            <a href="found_flight.php">Flights</a>
        </div>
        <div class="navbar-right">
            <?php if(isset($_SESSION['user_name'])): ?>
                <a href="#" style="color: #ffcc00; font-weight: bold;"><i class="fas fa-user"></i> Hello, <?php echo $_SESSION['user_name']; ?></a>
                <a href="logout.php" style="background: #c8102e; border-radius: 4px; padding: 5px 10px;">Logout</a>
            <?php else: ?>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Log In</a>
                <a href="register.php">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="results-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0;"><i class="fas fa-plane"></i> Flight Results</h2>
                <p style="margin: 5px 0 0 0;">
                    From <strong><?php echo htmlspecialchars($fromCity); ?></strong> 
                    to <strong><?php echo htmlspecialchars($toCity); ?></strong> 
                    on <strong><?php echo htmlspecialchars($dateInput); ?></strong>
            <br>
            <small>
                Looking for <strong><?php echo $totalSeatsNeeded; ?> Seat(s)</strong> 
                        (Total Passengers: <?php echo $totalPassengersDisplay; ?>) - Class: <?php echo htmlspecialchars($cabinClass); ?>
            </small>
        </p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="sortSelect" style="font-weight: bold; color: #333;">Sort by:</label>
                <select id="sortSelect" onchange="sortFlights(this.value)" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; background: white; cursor: pointer;">
                    <option value="time_asc" <?php echo $sortBy === 'time_asc' ? 'selected' : ''; ?>>Time: Earliest First</option>
                    <option value="time_desc" <?php echo $sortBy === 'time_desc' ? 'selected' : ''; ?>>Time: Latest First</option>
                    <option value="price_asc" <?php echo $sortBy === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sortBy === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </div>
        </div>
        <hr style="margin-bottom: 30px; border: 0; border-top: 1px solid #ddd;">

        <?php if(!empty($flightsArray)): ?>
            <?php foreach($flightsArray as $row): ?>
                
                <div class="flight-card">
                    <div class="flight-info">
                        <div class="flight-time">
                            <?php echo $row['Departure Time']->format('H:i'); ?> 
                            <i class="fas fa-long-arrow-alt-right" style="font-size: 18px; color: #aaa; margin: 0 10px;"></i> 
                            <?php echo $row['Arrival Time']->format('H:i'); ?>
                        </div>
                        <div class="flight-route">
                            <?php echo $row['Departure City']; ?> (<?php echo $row['Departure IATA']; ?>) <br>
                            To: <?php echo $row['Arrival City']; ?> (<?php echo $row['Arrival IATA']; ?>) <br>
                            <small style="color: #888;">Flight No: <?php echo $row['Flight No']; ?></small>
                        </div>
                    </div>

                    <div class="flight-class" style="text-align: center; color: #555;">
                        <i class="fas fa-chair"></i> <?php echo $row['Cabin Type']; ?> <br>
                        
                        <?php if($row['Remaining Seats'] < 10): ?>
                            <span class="remaining-seats">
                                <i class="fas fa-exclamation-circle"></i> Only <?php echo $row['Remaining Seats']; ?> seats left!
                            </span>
                        <?php else: ?>
                            <span style="font-size: 12px; color: green;">Available</span>
                        <?php endif; ?>
                    </div>

                    <div class="flight-price">
                        <span class="price-tag"><?php echo number_format($row['Basic Price'], 2); ?> ₺</span>
                        
                        <?php if ($tripType === 'roundtrip'): ?>
                            <a href="found_flight_return.php?outbound_flightID=<?php echo $row['Flight ID']; ?>&outbound_price=<?php echo $row['Basic Price']; ?>&class=<?php echo $cabinClass; ?>&adults=<?php echo $adultCount; ?>&children=<?php echo $childCount; ?>&teens=<?php echo $teenCount; ?>&old=<?php echo $oldCount; ?>&babies=<?php echo $babyCount; ?>&from=<?php echo urlencode($toCity); ?>&to=<?php echo urlencode($fromCity); ?>&return_date=<?php echo urlencode($returnDate); ?>" class="btn-select"> 
                                Continue <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                        <a href="booking.php?flightID=<?php echo $row['Flight ID']; ?>&price=<?php echo $row['Basic Price']; ?>&class=<?php echo $cabinClass; ?>&adults=<?php echo $adultCount; ?>&children=<?php echo $childCount; ?>&teens=<?php echo $teenCount; ?>&old=<?php echo $oldCount; ?>&babies=<?php echo $babyCount; ?>" class="btn-select"> 
                            Select <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>

                    </div>
                </div>

            <?php endforeach; ?>
        
        <?php else: ?>
            <div class="no-flights">
                <i class="fas fa-search" style="font-size: 50px; color: #ddd; margin-bottom: 20px;"></i>
                <h3>No flights found.</h3>
                <p>We couldn't find any flights matching your criteria or there are not enough seats available.</p>
                <a href="javascript:history.back()" style="display: inline-block; padding: 10px 20px; background: #c8102e; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function sortFlights(sortValue) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            
            // Update sort parameter
            urlParams.set('sort', sortValue);
            
            // Reload page with new sort parameter
            window.location.search = urlParams.toString();
        }
    </script>

</body>
</html>