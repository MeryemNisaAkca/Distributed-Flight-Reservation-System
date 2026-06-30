<?php

//This is the Ticket Management page where users access their reservations by entering their PNR codes.
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
session_start();
include 'connecting.php';


$error = "";
// Search Ticket
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pnr = trim($_POST['pnr'] ?? '');
    $surname = trim($_POST['surname'] ?? '');

    if (empty($pnr)) {
        $error = "Please enter a PNR code.";
    } else {
        // If user is logged in, surname is optional (can search with PNR only)
        // If user is NOT logged in (guest), surname is required for security
        if (empty($surname) && !isset($_SESSION['user_id'])) {
            $error = "Please enter both PNR code and surname to search for your ticket.";
        } else {
            // Search for PNR in Reservation_Table (single PNR per reservation)
            if (!empty($surname)) {
                // Guest: validate with surname using stored procedure
                // Convert surname to uppercase for case-insensitive comparison
                $surnameUpper = strtoupper(trim($surname));
                $sqlAuth = "{CALL UP_GuestTicketInformation(?, ?)}";
                $paramsAuth = array($pnr, $surnameUpper);
                $stmtAuth = sqlsrv_query($conn, $sqlAuth, $paramsAuth);

                if ($stmtAuth !== false && sqlsrv_has_rows($stmtAuth)) {
                    // Check if flight is delayed before redirecting
                    $sqlCheckDelayed = "
                        SELECT F.Status as FlightStatus
                        FROM Reservation_Table R
                        INNER JOIN Flights_Table F ON R.FlightID = F.FlightID
                        WHERE R.PNR = ?
                    ";
                    $stmtDelayed = sqlsrv_query($conn, $sqlCheckDelayed, array($pnr));
                    $delayedData = sqlsrv_fetch_array($stmtDelayed, SQLSRV_FETCH_ASSOC);
                    $isDelayed = ($delayedData && ($delayedData['FlightStatus'] ?? 'Planned') === 'Delayed');
                    
                    if ($isDelayed) {
                        $_SESSION['delayed_alert'] = true;
                    }
                    
                    header("Location: ticket_details.php?pnr=" . urlencode($pnr) . "&surname=" . urlencode($surname));
                    exit();
                } else {
                    // If stored procedure fails, try direct query
                    // Use uppercase for case-insensitive comparison
                    $surnameUpper = strtoupper(trim($surname));
                    $sqlDirect = "
                        SELECT R.ReservationID, R.PNR
                        FROM Reservation_Table R
                        INNER JOIN Tickets_Table T ON R.ReservationID = T.ReservationID
                        WHERE R.PNR = ? AND T.PassengerSurname = ?
                    ";
                    $paramsDirect = array($pnr, $surnameUpper);
                    $stmtDirect = sqlsrv_query($conn, $sqlDirect, $paramsDirect);
                    
                    if ($stmtDirect && sqlsrv_has_rows($stmtDirect)) {
                        // Check if flight is delayed before redirecting
                        $sqlCheckDelayed = "
                            SELECT F.Status as FlightStatus
                            FROM Reservation_Table R
                            INNER JOIN Flights_Table F ON R.FlightID = F.FlightID
                            WHERE R.PNR = ?
                        ";
                        $stmtDelayed = sqlsrv_query($conn, $sqlCheckDelayed, array($pnr));
                        $delayedData = sqlsrv_fetch_array($stmtDelayed, SQLSRV_FETCH_ASSOC);
                        $isDelayed = ($delayedData && ($delayedData['FlightStatus'] ?? 'Planned') === 'Delayed');
                        
                        if ($isDelayed) {
                            $_SESSION['delayed_alert'] = true;
                        }
                        
                        header("Location: ticket_details.php?pnr=" . urlencode($pnr) . "&surname=" . urlencode($surname));
                        exit();
                    } else {
                        $error = "Reservation not found for the given PNR and surname. Please check and try again.";
                    }
                }
            } else {
                // Logged-in user: search reservation PNR
        $sql = "SELECT ReservationID FROM Reservation_Table WHERE PNR = ?";
        $params = array($pnr);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log("Ticket management query failed: " . print_r($errors, true));
            die("An error occurred. Please try again later.");
        }

        if (sqlsrv_has_rows($stmt)) {
                    // Check if flight is delayed before redirecting
                    $sqlCheckDelayed = "
                        SELECT F.Status as FlightStatus
                        FROM Reservation_Table R
                        INNER JOIN Flights_Table F ON R.FlightID = F.FlightID
                        WHERE R.PNR = ?
                    ";
                    $stmtDelayed = sqlsrv_query($conn, $sqlCheckDelayed, array($pnr));
                    $delayedData = sqlsrv_fetch_array($stmtDelayed, SQLSRV_FETCH_ASSOC);
                    $isDelayed = ($delayedData && ($delayedData['FlightStatus'] ?? 'Planned') === 'Delayed');
                    
                    if ($isDelayed) {
                        $_SESSION['delayed_alert'] = true;
                    }
                    
                    header("Location: ticket_details.php?pnr=" . urlencode($pnr));
            exit();
        } else {
            $error = "PNR not found. Please check and try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Booking - THY Project</title>
    <link rel="stylesheet" href="css/ticket_management_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="login-card">
        <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY PROJECT</a>
        <h2>Manage Booking</h2>
        
        <?php if($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="pnr">PNR Code</label>
                <input type="text" id="pnr" name="pnr" placeholder="e.g. 1A2B3C" required maxlength="6" style="text-transform:uppercase">
                <small style="color: #888; font-size: 11px; display: block; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Enter your reservation PNR code
                </small>
            </div>
            
            <div class="form-group">
                <label for="surname">Passenger Surname</label>
                <input type="text" id="surname" name="surname" placeholder="Enter your surname" <?php echo !isset($_SESSION['user_id']) ? 'required' : ''; ?> style="text-transform:uppercase">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <small style="color: #888; font-size: 12px; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Optional for logged-in users
                    </small>
                <?php else: ?>
                    <small style="color: #888; font-size: 12px; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Required for guest users
                    </small>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-submit">Find My Ticket <i class="fas fa-arrow-right"></i></button>
        </form>

        <div style="margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 4px solid #c8102e;">
            <p style="margin: 0; font-size: 13px; color: #555; line-height: 1.6;">
                <i class="fas fa-lightbulb" style="color: #c8102e;"></i> 
                <strong>Tip:</strong> Use your reservation PNR code to view or manage your booking. 
                Guest users need to provide their surname for security.
            </p>
        </div>

        <a href="javascript:history.back()" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

</body>
</html>


