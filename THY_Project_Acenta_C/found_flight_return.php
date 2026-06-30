<?php
//This is the second stage in purchasing round-trip tickets. After selecting their outbound flight, the user is redirected to this page where they are asked to select their return flight.

if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
session_start();
include 'connecting.php';

// Retrieve details of the ALREADY SELECTED outbound flight (passed from previous page)
$outboundFlightID = $_GET['outbound_flightID'] ?? 0;
$outboundPrice = $_GET['outbound_price'] ?? 0;
$fromCity   = $_GET['from'] ?? '';
$toCity     = $_GET['to'] ?? '';
$returnDate = $_GET['return_date'] ?? ''; 
$cabinClass = $_GET['class'] ?? 'Economy';

//Get passenger counts individually
$adultCount = isset($_GET['adults']) ? (int)$_GET['adults'] : 1;
$childCount = isset($_GET['children']) ? (int)$_GET['children'] : 0;
$teenCount  = isset($_GET['teens']) ? (int)$_GET['teens'] : 0;
$oldCount   = isset($_GET['old']) ? (int)$_GET['old'] : 0;
$babyCount  = isset($_GET['babies']) ? (int)$_GET['babies'] : 0;

// Calculate Total Seats Needed
$totalSeatsNeeded = $adultCount + $childCount + $teenCount + $oldCount;
$totalPassengersDisplay = $totalSeatsNeeded + $babyCount;

// Get Sorting Preference (default: Time Ascending)
$sortBy = $_GET['sort'] ?? 'time_asc';

// Security check
if ($outboundFlightID == 0 || empty($returnDate)) {
    header("Location: index.php");
    exit();
}

// Validate return date is not in the past
$today = date('Y-m-d');
if ($returnDate < $today) {
    die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;'>
        <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Invalid Return Date</h3>
        <p style='color:#666;'>You cannot search for return flights in the past. Please select today's date or a future date.</p>
        <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Go Back to Search</a>
    </div>");
}

// Query return flights (reverse route) - exclude past flights
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

$params = array($fromCity, $toCity, $returnDate, $cabinClass, $totalSeatsNeeded);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    error_log("Return flight search query failed: " . print_r($errors, true));
    die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;'>
        <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Search Error</h3>
        <p style='color:#666;'>An error occurred while searching for return flights. Please try again.</p>
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
                $priceA = (float)($a['Basic Price'] ?? 0);
                $priceB = (float)($b['Basic Price'] ?? 0);
                return $priceA <=> $priceB;
                
            case 'price_desc':
                $priceA = (float)($a['Basic Price'] ?? 0);
                $priceB = (float)($b['Basic Price'] ?? 0);
                return $priceB <=> $priceA;
                
            case 'time_asc':
                $timeA = $a['Departure Time'];
                $timeB = $b['Departure Time'];
                if ($timeA instanceof DateTime && $timeB instanceof DateTime) {
                    return $timeA <=> $timeB;
                }
                return 0;
                
            case 'time_desc':
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

// Get outbound flight details for display
$sqlOutbound = "SELECT * FROM VW_FlightDetails WHERE [Flight ID] = ?";
$stmtOutbound = sqlsrv_query($conn, $sqlOutbound, array($outboundFlightID));
$outboundFlight = sqlsrv_fetch_array($stmtOutbound, SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Return Flight - THY Project</title>
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
        <div style="background: #f0f7ff; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c8102e;">
            <h3 style="margin: 0 0 10px 0;"><i class="fas fa-plane-departure"></i> Outbound Flight Selected</h3>
            <?php if ($outboundFlight): ?>
                <p style="margin: 5px 0;">
                    <strong><?php echo htmlspecialchars($outboundFlight['Departure City']); ?> (<?php echo htmlspecialchars($outboundFlight['Departure IATA']); ?>)</strong> 
                    <i class="fas fa-arrow-right" style="margin: 0 10px;"></i>
                    <strong><?php echo htmlspecialchars($outboundFlight['Arrival City']); ?> (<?php echo htmlspecialchars($outboundFlight['Arrival IATA']); ?>)</strong>
                    <br>
                    <small><?php echo $outboundFlight['Departure Time']->format('d M Y, H:i'); ?> - Flight No: <?php echo htmlspecialchars($outboundFlight['Flight No']); ?></small>
                </p>
            <?php endif; ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0;"><i class="fas fa-plane"></i> Select Return Flight</h2>
                <p style="margin: 5px 0 0 0;">
                    From <strong><?php echo htmlspecialchars($fromCity); ?></strong> 
                    to <strong><?php echo htmlspecialchars($toCity); ?></strong> 
                    on <strong><?php echo htmlspecialchars($returnDate); ?></strong>
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
                        
                        <a href="booking.php?outbound_flightID=<?php echo $outboundFlightID; ?>&outbound_price=<?php echo $outboundPrice; ?>&return_flightID=<?php echo $row['Flight ID']; ?>&return_price=<?php echo $row['Basic Price']; ?>&class=<?php echo $cabinClass; ?>&adults=<?php echo $adultCount; ?>&children=<?php echo $childCount; ?>&teens=<?php echo $teenCount; ?>&old=<?php echo $oldCount; ?>&babies=<?php echo $babyCount; ?>&trip_type=roundtrip" class="btn-select"> 
                            Select <i class="fas fa-chevron-right"></i>
                        </a>

                    </div>
                </div>

            <?php endforeach; ?>
        
        <?php else: ?>
            <div class="no-flights">
                <i class="fas fa-search" style="font-size: 50px; color: #ddd; margin-bottom: 20px;"></i>
                <h3>No return flights found.</h3>
                <p>We couldn't find any return flights matching your criteria or there are not enough seats available.</p>
                <a href="javascript:history.back()" style="display: inline-block; padding: 10px 20px; background: #c8102e; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function sortFlights(sortValue) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sort', sortValue);
            window.location.search = urlParams.toString();
        }
    </script>

</body>
</html>
