<?php

//It is a central control panel accessible only to authorized administrators (Admin) that lists and manages all flights in the system in detail. 
//It ensures data consistency by automatically updating past flights each time the page loads and allows administrators to manually change flight statuses (Delayed, Canceled, Arrived).
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file missing.");
}

// 2. OTURUM: Ayarlardan sonra başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'connecting.php';

//It includes critical operations for flight management (automatic updates and manual status changes).
// Redirect CompanyOwner to their dashboard
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'CompanyOwner') {
    header("Location: company_owner_dashboard.php");
    exit();
}

// Only Admin can access this page now (CompanyOwner uses company_owner_dashboard.php)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$message = '';


// Count how many flights need to be updated (using CAST to ensure proper comparison)
$sqlCountPastFlights = "
    SELECT COUNT(*) as CountToUpdate
    FROM Flights_Table 
    WHERE CAST(DepartureTime AS DATETIME) < CAST(GETDATE() AS DATETIME)
    AND Status NOT IN ('Land', 'Cancelled')
";
$stmtCount = sqlsrv_query($conn, $sqlCountPastFlights);
$countToUpdate = 0;
if ($stmtCount) {
    $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $countToUpdate = $row['CountToUpdate'] ?? 0;  // Fetch the count result
}

// If there are flights to update, update them
if ($countToUpdate > 0) {
    $sqlUpdatePastFlights = "
        UPDATE Flights_Table 
        SET Status = 'Land'
        WHERE CAST(DepartureTime AS DATETIME) < CAST(GETDATE() AS DATETIME)
        AND Status NOT IN ('Land', 'Cancelled')
    ";
    $stmtUpdatePast = sqlsrv_query($conn, $sqlUpdatePastFlights);
    
    if ($stmtUpdatePast !== false) {
        // Show message if flights were updated
        $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $countToUpdate past flight(s) automatically updated to 'Land' status.</div>";
    } else {
        // Log error
        $errors = sqlsrv_errors();
        if ($errors) {
            error_log("Auto update past flights error: " . print_r($errors, true));
        }
    }
}

// Handle status update via Stored Procedure
// This block handles the POST request when an admin manually changes a flight status via the dropdown.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flight_id'], $_POST['new_status'])) {
    $flightID = (int)$_POST['flight_id'];
    $newStatus = $_POST['new_status'];

    if ($flightID > 0 && !empty($newStatus)) {
        $sql = "{CALL UP_UpdateFlightStatus(?, ?)}";
        $params = array($flightID, $newStatus);
        $stmtUpdate = sqlsrv_query($conn, $sql, $params);

        if ($stmtUpdate === false) {
            $errors = sqlsrv_errors();
            error_log("Status update failed: " . print_r($errors, true));
            $message = "<div style='color:red; margin-bottom:15px;'>Status update failed. Please try again.</div>";
        } else {
            $message = "<div style='color:green; margin-bottom:15px;'>Flight status updated successfully.</div>";
        }
    }
}

// Fetch all flights for management (Admin view)
$sqlFlights = "SELECT * FROM VW_AdminFlightDetails ORDER BY [Departure Time] DESC";
$stmtFlights = sqlsrv_query($conn, $sqlFlights);

// Group flights by FlightID to avoid duplicates (same flight with different CabinType)
// The view might return multiple rows for a single flight (e.g., due to joins with cabin types).
$flightsGrouped = [];
if ($stmtFlights && sqlsrv_has_rows($stmtFlights)) {
    while($f = sqlsrv_fetch_array($stmtFlights, SQLSRV_FETCH_ASSOC)) {
        $flightID = $f['Flight ID'];
        // Only keep the first occurrence of each FlightID
        if (!isset($flightsGrouped[$flightID])) {
            $flightsGrouped[$flightID] = $f;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Flight Management - THY Project</title>
    <link rel="stylesheet" href="css/checkin_style.css">
    <link rel="stylesheet" href="css/admin_flights_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div style="background:#232b38; padding:15px; color:white; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-plane"></i> 
        <strong>Flight Management Panel</strong>
        <span style="margin-left:auto; font-size:13px;">
            Logged in as: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?> (<?php echo htmlspecialchars($_SESSION['user_role'] ?? ''); ?>)
        </span>
        <a href="index.php" style="color:#aaa; margin-left:15px; text-decoration:none;">Home</a>
        <a href="logout.php" style="color:#ffcc00; margin-left:15px; text-decoration:none;">Logout</a>
    </div>

    <div class="admin-container">

        <?php echo $message; ?>

        <h2 style="margin-bottom:20px;"><i class="fas fa-plane-departure"></i> All Flights</h2>

        <?php if(!empty($flightsGrouped)): ?>
            <?php foreach($flightsGrouped as $f): ?>
                <?php 
                    $status = $f['Flight Status'] ?? 'Planned';
                    $statusClass = 'status-pill status-' . preg_replace('/\s+/', '', $status);
                ?>
                <div class="admin-card">
                    <div class="admin-header">
                        <div>
                            <strong><?php echo $f['Flight No']; ?></strong> 
                            - <?php echo $f['Departure City']; ?> (<?php echo $f['Departure IATA']; ?>) 
                            <i class="fas fa-arrow-right"></i> 
                            <?php echo $f['Arrival City']; ?> (<?php echo $f['Arrival IATA']; ?>)
                        </div>
                        <div class="<?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars($status); ?>
                        </div>
                    </div>
                    <div class="admin-grid">
                        <div>
                            <label>Flight ID</label>
                            <span><?php echo $f['Flight ID']; ?></span>
                        </div>
                        <div>
                            <label>Departure</label>
                            <span><?php echo $f['Departure Time']->format('d M Y H:i'); ?></span>
                        </div>
                        <div>
                            <label>Arrival</label>
                            <span><?php echo $f['Arrival Time']->format('d M Y H:i'); ?></span>
                        </div>
                        <div>
                            <label>Plane</label>
                            <span><?php echo $f['Plane Model']; ?> (<?php echo $f['Total Capacity']; ?> seats)</span>
                        </div>
                        <div>
                            <label>Remaining Seats</label>
                            <span><?php echo $f['Remaining Seats']; ?></span>
                        </div>
                    </div>

                    <form method="POST" class="status-form">
                        <input type="hidden" name="flight_id" value="<?php echo $f['Flight ID']; ?>">
                        <label for="status_<?php echo $f['Flight ID']; ?>" style="font-size:13px;">Change Status:</label>
                        <select name="new_status" id="status_<?php echo $f['Flight ID']; ?>">
                            <option value="Planned" <?php if($status==='Planned') echo 'selected'; ?>>Planned</option>
                            <option value="Delayed" <?php if($status==='Delayed') echo 'selected'; ?>>Delayed</option>
                            <option value="Land" <?php if($status==='Land') echo 'selected'; ?>>Land</option>
                            <option value="Cancelled" <?php if($status==='Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                        <button type="submit" class="btn-update"><i class="fas fa-save"></i> Update</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No flights found.</p>
        <?php endif; ?>

    </div>

</body>
</html>

