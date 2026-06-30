<?php
//This section lists tickets, groups bookings (PNRs), and automatically updates ticket statuses (Used/Cancelled/Open).
// Handle Tickets-related form submissions (if needed in future)
// Logic to handle future form submissions (e.g., manual ticket cancellation by admin).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Future: Add ticket status update, cancellation, etc.
    if (!isset($activeTab)) {
        $activeTab = 'tickets';
    }
    if (!isset($message)) {
        $message = '';
    }
}

// First, update TicketStatus for flights that have landed (ArrivalTime < GETDATE())
// Set to 'Used' if not already 'Cancelled'
$sqlUpdateUsedTickets = "
    UPDATE Tickets_Table 
    SET TicketStatus = 'Used'
    WHERE TicketID IN (
        SELECT T.TicketID
        FROM Tickets_Table T
        INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
        WHERE F.ArrivalTime < GETDATE()
        AND (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled', 'Used'))
    )
";
sqlsrv_query($conn, $sqlUpdateUsedTickets);

// Fetch all tickets with detailed information
// Using LEFT JOIN for airports in case of missing data
// Calculate TicketStatus dynamically: Cancelled > Used (if flight landed) > Open
// A comprehensive query joining Tickets, Reservations, Flights, Airports, and Users.
// Includes dynamic status calculation based on flight time.
$sqlTickets = "
    SELECT     
        T.TicketID,
        T.PassengerName,
        T.PassengerSurname,
        T.AgeType,
        T.CabinType,
        T.TicketPrice,
        CASE 
            WHEN T.TicketStatus = 'Cancelled' THEN 'Cancelled'
            WHEN F.ArrivalTime < GETDATE() THEN 'Used'
            ELSE 'Open'
        END as TicketStatus,
        T.CheckInStatus,
        T.SeatNo,
        T.MealID,
        T.BaggageID,
        R.ReservationID,
        R.PNR,
        R.ReservationDateTime,
        R.TotalCost,
        R.PaymentStatus,
        R.ContactEmail,
        R.ContactName,
        R.ContactSurname,
        R.UserID,
        F.FlightID,
        F.FlightNo,
        F.DepartureTime,
        F.ArrivalTime,
        F.Status as FlightStatus,
        ISNULL(DepAirport.City, 'Unknown') as DepCity,
        ISNULL(DepAirport.IATA, 'N/A') as DepIATA,
        ISNULL(DepAirport.AirportName, 'Unknown') as DepAirportName,
        ISNULL(ArrAirport.City, 'Unknown') as ArrCity,
        ISNULL(ArrAirport.IATA, 'N/A') as ArrIATA,
        ISNULL(ArrAirport.AirportName, 'Unknown') as ArrAirportName,
        U.Name as UserName,
        U.Surname as UserSurname,
        U.Email as UserEmail
    FROM Tickets_Table T
    INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
    INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
    LEFT JOIN Airports_Table DepAirport ON F.DepartureAirportID = DepAirport.AirportID
    LEFT JOIN Airports_Table ArrAirport ON F.ArrivalAirportID = ArrAirport.AirportID
    LEFT JOIN Users_Table U ON R.UserID = U.UserID
    ORDER BY R.ReservationDateTime DESC, T.TicketID DESC
";

$stmtTickets = sqlsrv_query($conn, $sqlTickets);

// Check for SQL errors
$sqlError = '';
if ($stmtTickets === false) {
    $errors = sqlsrv_errors();
    $sqlError = 'SQL Error: ' . print_r($errors, true);
}

// Group tickets by PNR for better display
// The database returns a flat list of tickets. 
// We group them by PNR (Reservation Code) to display them as a single booking card containing multiple passengers.
$ticketsByPNR = [];
if ($stmtTickets) {
    while($ticket = sqlsrv_fetch_array($stmtTickets, SQLSRV_FETCH_ASSOC)) {
        $pnr = $ticket['PNR'];
        if (!isset($ticketsByPNR[$pnr])) {
            $ticketsByPNR[$pnr] = [
                'reservation_info' => [
                    'PNR' => $ticket['PNR'],
                    'ReservationID' => $ticket['ReservationID'],
                    'ReservationDateTime' => $ticket['ReservationDateTime'],
                    'TotalCost' => $ticket['TotalCost'],
                    'PaymentStatus' => $ticket['PaymentStatus'],
                    'ContactEmail' => $ticket['ContactEmail'],
                    'ContactName' => $ticket['ContactName'],
                    'ContactSurname' => $ticket['ContactSurname'],
                    'UserID' => $ticket['UserID'],
                    'UserName' => $ticket['UserName'],
                    'UserSurname' => $ticket['UserSurname'],
                    'UserEmail' => $ticket['UserEmail'],
                ],
                'flight_info' => [
                    'FlightID' => $ticket['FlightID'],
                    'FlightNo' => $ticket['FlightNo'],
                    'DepartureTime' => $ticket['DepartureTime'],
                    'ArrivalTime' => $ticket['ArrivalTime'],
                    'FlightStatus' => $ticket['FlightStatus'] ?? 'Planned',
                    'DepCity' => $ticket['DepCity'],
                    'DepIATA' => $ticket['DepIATA'],
                    'DepAirportName' => $ticket['DepAirportName'],
                    'ArrCity' => $ticket['ArrCity'],
                    'ArrIATA' => $ticket['ArrIATA'],
                    'ArrAirportName' => $ticket['ArrAirportName'],
                ],
                'tickets' => []
            ];
        }
        $ticketsByPNR[$pnr]['tickets'][] = $ticket;
    }
}
?>

<!-- TICKETS TAB CONTENT -->
<div id="tab-tickets" class="tab-content <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>">
    <h2><i class="fas fa-ticket-alt"></i> Ticket Management</h2>
    
    <?php if(!empty($sqlError)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <strong><i class="fas fa-exclamation-triangle"></i> Database Error:</strong>
            <pre style="margin: 10px 0 0 0; font-size: 12px; overflow-x: auto;"><?php echo htmlspecialchars($sqlError); ?></pre>
        </div>
    <?php endif; ?>
    
    <?php 
    $sqlCount = "SELECT COUNT(*) as TotalTickets FROM Tickets_Table";
    $stmtCount = sqlsrv_query($conn, $sqlCount);
    $ticketCount = 0;
    if ($stmtCount) {
        $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
        $ticketCount = $row['TotalTickets'] ?? 0;
    }
    if ($ticketCount == 0 && empty($sqlError)): ?>
        <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ffc107;">
            <strong><i class="fas fa-info-circle"></i> Info:</strong> There are no tickets in the database. Please create a booking first.
        </div>
    <?php endif; ?>
    
    <?php
    // Calculate ticket statistics based on actual TicketStatus and flight arrival time
    // Open: Not cancelled and flight hasn't landed yet
    // Used: Flight has landed (ArrivalTime < GETDATE()) and not cancelled
    // Cancelled: TicketStatus = 'Cancelled'
    
    $sqlOpenCount = "
        SELECT COUNT(*) as TicketCount 
        FROM Tickets_Table T
        INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
        WHERE (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled', 'Used'))
        AND F.ArrivalTime >= GETDATE()
    ";
    $stmtOpenCount = sqlsrv_query($conn, $sqlOpenCount);
    $openCount = 0;
    if ($stmtOpenCount) {
        $row = sqlsrv_fetch_array($stmtOpenCount, SQLSRV_FETCH_ASSOC);
        $openCount = $row['TicketCount'] ?? 0;
    }
    
    $sqlCancelledCount = "
        SELECT COUNT(*) as TicketCount 
        FROM Tickets_Table 
        WHERE TicketStatus = 'Cancelled'
    ";
    $stmtCancelledCount = sqlsrv_query($conn, $sqlCancelledCount);
    $cancelledCount = 0;
    if ($stmtCancelledCount) {
        $row = sqlsrv_fetch_array($stmtCancelledCount, SQLSRV_FETCH_ASSOC);
        $cancelledCount = $row['TicketCount'] ?? 0;
    }
    
    $sqlUsedCount = "
        SELECT COUNT(*) as TicketCount 
        FROM Tickets_Table T
        INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
        WHERE F.ArrivalTime < GETDATE()
        AND (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled'))
    ";
    $stmtUsedCount = sqlsrv_query($conn, $sqlUsedCount);
    $usedCount = 0;
    if ($stmtUsedCount) {
        $row = sqlsrv_fetch_array($stmtUsedCount, SQLSRV_FETCH_ASSOC);
        $usedCount = $row['TicketCount'] ?? 0;
    }
    
    $totalTickets = 0;
    foreach($ticketsByPNR as $pnr => $data) {
        $totalTickets += count($data['tickets']);
    }
    ?>
    
    <div style="background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 10px 0; color: #232b38;">
            <i class="fas fa-info-circle"></i> All Tickets (Grouped by PNR)
        </h3>
        <p style="margin: 0; color: #666; font-size: 13px; margin-bottom: 10px;">
            Total Reservations: <strong><?php echo count($ticketsByPNR); ?></strong> | 
            Total Tickets: <strong><?php echo $totalTickets; ?></strong>
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 10px;">
            <div style="background: white; padding: 10px; border-radius: 6px; border-left: 4px solid #28a745;">
                <div style="font-size: 11px; color: #666; text-transform: uppercase;">Open Tickets</div>
                <div style="font-size: 20px; font-weight: bold; color: #28a745;">
                    <?php echo $openCount; ?>
                </div>
                <small style="color: #999; font-size: 10px;">Active flights</small>
            </div>
            <div style="background: white; padding: 10px; border-radius: 6px; border-left: 4px solid #dc3545;">
                <div style="font-size: 11px; color: #666; text-transform: uppercase;">Cancelled Tickets</div>
                <div style="font-size: 20px; font-weight: bold; color: #dc3545;">
                    <?php echo $cancelledCount; ?>
                </div>
                <small style="color: #999; font-size: 10px;">Cancelled by user</small>
            </div>
            <div style="background: white; padding: 10px; border-radius: 6px; border-left: 4px solid #6c757d;">
                <div style="font-size: 11px; color: #666; text-transform: uppercase;">Used Tickets</div>
                <div style="font-size: 20px; font-weight: bold; color: #6c757d;">
                    <?php echo $usedCount; ?>
                </div>
                <small style="color: #999; font-size: 10px;">Flight completed</small>
            </div>
        </div>
    </div>

    <?php if(!empty($ticketsByPNR)): ?>
        <?php foreach($ticketsByPNR as $pnr => $data): 
            $reservation = $data['reservation_info'];
            $flight = $data['flight_info'];
            $tickets = $data['tickets'];
            
            // Format dates
            $resDate = $reservation['ReservationDateTime']->format('d M Y, H:i');
            $depDate = $flight['DepartureTime']->format('d M Y');
            $depTime = $flight['DepartureTime']->format('H:i');
            $arrTime = $flight['ArrivalTime']->format('H:i');
            
            // Check if all tickets are checked in
            $allCheckedIn = true;
            $allCancelled = true;
            foreach($tickets as $t) {
                if($t['CheckInStatus'] == 0) $allCheckedIn = false;
                if($t['TicketStatus'] != 'Cancelled') $allCancelled = false;
            }
        ?>
            <div style="background: white; border: 2px solid #232b38; border-left: 4px solid #232b38; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;" 
                 onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" 
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                <!-- Reservation Header -->
                <div style="background: linear-gradient(135deg, #232b38 0%, #3a4a5c 100%); color: white; padding: 12px; border-radius: 6px 6px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="flex: 1; min-width: 0;">
                        <strong style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-ticket-alt"></i>
                            PNR: <?php echo htmlspecialchars($pnr); ?>
                        </strong>
                        <div style="font-size: 11px; opacity: 0.9; margin-top: 4px; display: flex; flex-wrap: wrap; gap: 8px;">
                            <span><i class="fas fa-calendar"></i> <?php echo $resDate; ?></span>
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($reservation['ContactName'] . ' ' . $reservation['ContactSurname']); ?></span>
                            <?php if($reservation['UserID']): ?>
                                <span><i class="fas fa-user-check"></i> Registered</span>
                            <?php else: ?>
                                <span><i class="fas fa-user-times"></i> Guest</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 12px; opacity: 0.9;">Total Cost</div>
                        <div style="font-size: 18px; font-weight: bold;">₺<?php echo number_format($reservation['TotalCost'], 2); ?></div>
                        <div style="font-size: 10px; margin-top: 4px;">
                            <span style="background: <?php echo $reservation['PaymentStatus'] === 'Completed' ? '#28a745' : '#ffc107'; ?>; padding: 2px 6px; border-radius: 3px; font-size: 10px;">
                                <?php echo htmlspecialchars($reservation['PaymentStatus']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Flight Information -->
                <div style="padding: 12px; border-bottom: 1px solid #eee; background: #f9f9f9;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #007bff;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-plane"></i> Flight No
                            </div>
                            <div style="font-size: 13px; font-weight: bold; color: #232b38;"><?php echo htmlspecialchars($flight['FlightNo']); ?></div>
                        </div>
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #c8102e;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-route"></i> Route
                            </div>
                            <div style="font-size: 12px; font-weight: bold; color: #232b38;">
                                <?php echo htmlspecialchars($flight['DepIATA']); ?> <i class="fas fa-arrow-right" style="color: #c8102e; margin: 0 3px; font-size: 10px;"></i> <?php echo htmlspecialchars($flight['ArrIATA']); ?>
                            </div>
                        </div>
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #28a745;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-plane-departure"></i> Departure
                            </div>
                            <div style="font-size: 12px; font-weight: bold; color: #232b38;">
                                <?php echo $depDate; ?> <?php echo $depTime; ?>
                            </div>
                        </div>
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #6c757d;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-plane-arrival"></i> Arrival
                            </div>
                            <div style="font-size: 12px; font-weight: bold; color: #232b38;">
                                <?php echo $flight['ArrivalTime']->format('d M Y'); ?> <?php echo $arrTime; ?>
                            </div>
                        </div>
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #ffc107;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-info-circle"></i> Status
                            </div>
                            <div>
                                <span style="background: 
                                    <?php 
                                        switch($flight['FlightStatus']) {
                                            case 'Planned': echo '#007bff'; break;
                                            case 'Delayed': echo '#ffc107'; break;
                                            case 'Cancelled': echo '#dc3545'; break;
                                            case 'Land': echo '#28a745'; break;
                                            default: echo '#6c757d';
                                        }
                                    ?>; 
                                    color: white; padding: 3px 8px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                    <?php echo htmlspecialchars($flight['FlightStatus']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tickets List -->
                <div style="padding: 12px;">
                    <h4 style="margin: 0 0 10px 0; color: #232b38; font-size: 14px;">
                        <i class="fas fa-users"></i> Passengers (<?php echo count($tickets); ?>)
                    </h4>
                    <div class="table-wrapper">
                    <table class="data-table" style="margin: 0; width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="min-width: 60px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">ID</th>
                                <th style="min-width: 120px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Passenger</th>
                                <th style="min-width: 80px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Age</th>
                                <th style="min-width: 80px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Cabin</th>
                                <th style="min-width: 80px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Status</th>
                                <th style="min-width: 100px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Check-in</th>
                                <th style="min-width: 60px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Seat</th>
                                <th style="min-width: 80px; padding: 8px; text-align: left; background: #232b38; color: white; white-space: nowrap; font-size: 11px;">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tickets as $t): ?>
                                <tr style="<?php echo $t['TicketStatus'] === 'Cancelled' ? 'opacity: 0.6; background: #f8f8f8;' : ''; ?>">
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap; font-size: 11px;"><?php echo $t['TicketID']; ?></td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap; font-size: 11px;">
                                        <strong><?php echo htmlspecialchars($t['PassengerName'] . ' ' . $t['PassengerSurname']); ?></strong>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap;">
                                        <span style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 10px;">
                                            <?php echo htmlspecialchars($t['AgeType']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap;">
                                        <span style="background: <?php echo $t['CabinType'] === 'Business' ? '#c8102e' : '#6c757d'; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                            <?php echo htmlspecialchars($t['CabinType']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap;">
                                        <span style="background: 
                                            <?php 
                                                switch($t['TicketStatus']) {
                                                    case 'Open': echo '#28a745'; break;
                                                    case 'Cancelled': echo '#dc3545'; break;
                                                    case 'Used': echo '#6c757d'; break;
                                                    default: echo '#ffc107';
                                                }
                                            ?>; 
                                            color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                            <?php echo htmlspecialchars($t['TicketStatus'] ?? 'Open'); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap;">
                                        <?php if($t['CheckInStatus'] == 1): ?>
                                            <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">
                                                <i class="fas fa-check"></i> ✓
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #ffc107; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 10px;">
                                                <i class="fas fa-clock"></i> -
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap;">
                                        <?php if(!empty($t['SeatNo'])): ?>
                                            <span style="background: #232b38; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                                <?php echo htmlspecialchars($t['SeatNo']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 10px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 8px; border-bottom: 1px solid #eee; white-space: nowrap; font-size: 11px;">
                                        <strong style="color: #c8102e;">₺<?php echo number_format($t['TicketPrice'], 0); ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <!-- Additional Info -->
                <div style="padding: 8px 12px; background: #f9f9f9; border-top: 1px solid #eee; border-radius: 0 0 6px 6px; font-size: 11px; color: #666;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($reservation['ContactEmail']); ?></span>
                        </div>
                        <div>
                            <a href="ticket_details.php?pnr=<?php echo urlencode($pnr); ?>" target="_blank" style="color: #007bff; text-decoration: none; font-size: 11px;">
                                <i class="fas fa-external-link-alt"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; border: 1px solid #ddd;">
            <i class="fas fa-ticket-alt" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
            <p style="color: #999; font-size: 16px;">No tickets found in the system.</p>
        </div>
    <?php endif; ?>
</div>
