<?php
//This is the Flight Status page, which allows users to enter their flight number and view the real-time status of that flight (Delayed, Canceled, Scheduled, Arrived, etc.).

if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}session_start();
include 'connecting.php';

// Retrieve Flight Number from URL parameter (GET request)
$flightNo = trim($_GET['flight_no'] ?? '');
$flightDate = $_GET['flight_date'] ?? date('Y-m-d'); // Currently not used, reserved for future extension

// Use Stored Procedure to fetch flight status details
$stmt = null;
$errorMessage = '';
if (!empty($flightNo)) {
    // Convert to uppercase for consistency (flight numbers are usually uppercase)
    $flightNoUpper = strtoupper(trim($flightNo));
    
    // Call stored procedure
    $sql = "{CALL UP_GetFlightStatusByCode(?)}";
    $params = array($flightNoUpper);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    // Check for SQL errors
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        if ($errors) {
            error_log("Flight status query failed: " . print_r($errors, true));
            $errorMessage = "An error occurred while fetching flight status. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flight Status - <?php echo htmlspecialchars($flightNo); ?></title>
    <link rel="stylesheet" href="css/index_style.css"> 
    <link rel="stylesheet" href="css/flight_status_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-left">
             <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY PROJECT</a>
             <a href="index.php">Home</a>
        </div>
        <div class="navbar-right">
            <a href="javascript:history.back()" style="color: #fff; text-decoration: none; padding: 8px 15px; background: rgba(255,255,255,0.2); border-radius: 5px;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="status-container">
        <h2>Flight Status: <?php echo htmlspecialchars($flightNo); ?></h2>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="status-card" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: #856404;"></i>
                <h3>Error</h3>
                <p style="color: #856404;"><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($stmt && sqlsrv_has_rows($stmt)): ?>
            <?php while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                <?php 
                    // Get data from stored procedure (column names: Status, Model, DepCity, ArrCity, etc.)
                    $statusText = $row['Status'] ?? 'On Time';
                    $depCity = $row['DepCity'] ?? 'N/A';
                    $arrCity = $row['ArrCity'] ?? 'N/A';
                    $depAirport = $row['DepAirportName'] ?? 'N/A';
                    $arrAirport = $row['ArrAirportName'] ?? 'N/A';
                    $planeModel = $row['Model'] ?? 'Boeing 737';
                    
                    // Handle datetime objects
                    $depTime = isset($row['DepartureTime']) && $row['DepartureTime'] instanceof DateTime ? $row['DepartureTime'] : null;
                    $arrTime = isset($row['ArrivalTime']) && $row['ArrivalTime'] instanceof DateTime ? $row['ArrivalTime'] : null;
                    
                    // Determine status class based on status text
                    $statusClass = 'status-active';
                    if(stripos($statusText, 'Delay') !== false) $statusClass = 'status-delayed';
                    if(stripos($statusText, 'Cancel') !== false) $statusClass = 'status-cancelled';
                    if(stripos($statusText, 'Land') !== false) $statusClass = 'status-active';
                ?>
                
                <div class="status-card">
                    <div style="font-size: 24px; font-weight: bold; margin-bottom: 20px;">
                        <?php echo htmlspecialchars($depCity); ?> <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($arrCity); ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-around; margin-bottom: 20px;">
                        <div>
                            <strong>Departure</strong><br>
                            <?php if ($depTime): ?>
                                <?php echo $depTime->format('H:i'); ?><br>
                            <?php else: ?>
                                N/A<br>
                            <?php endif; ?>
                            <small><?php echo htmlspecialchars($depAirport); ?></small>
                        </div>
                        <div>
                            <strong>Arrival</strong><br>
                            <?php if ($arrTime): ?>
                                <?php echo $arrTime->format('H:i'); ?><br>
                            <?php else: ?>
                                N/A<br>
                            <?php endif; ?>
                            <small><?php echo htmlspecialchars($arrAirport); ?></small>
                        </div>
                    </div>

                    <div>
                        <strong>Plane Model:</strong> <?php echo htmlspecialchars($planeModel); ?>
                    </div>

                    <div class="status-badge <?php echo $statusClass; ?>">
                        <?php echo strtoupper($statusText); ?>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <a href="javascript:history.back()" style="display: inline-block; padding: 10px 20px; background: #c8102e; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="status-card">
                <i class="fas fa-search-minus" style="font-size: 40px; color: #ccc;"></i>
                <h3>Flight Not Found</h3>
                <p>Could not find any flight with number <strong><?php echo htmlspecialchars($flightNo); ?></strong>.</p>
                <a href="javascript:history.back()" style="display: inline-block; padding: 10px 20px; background: #c8102e; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>