<?php
//Occupancy Rate Calculation: Since infants do not occupy seats (travel on laps), the actual "Seat Occupancy" is calculated by subtracting the number of infants from the total number of tickets sold.
//General Statistics: The total capacity of all flights, the total number of passengers, and the overall occupancy percentage are calculated.
//Visual Reporting: Data is presented using color-coded progress bars and dashboard cards.

$userCompanyID = $_SESSION['user_company_id'] ?? 0;

// Fetch all flights WHERE this specific company has sold at least one ticket
$sqlFlights = "
    SELECT 
        F.FlightID, F.FlightNo, F.DepartureTime, F.ArrivalTime, F.Status,
        D.City as DepCity, D.IATA as DepIATA,
        A.City as ArrCity, A.IATA as ArrIATA,
        P.Model as PlaneModel, P.SeatCapacity
    FROM Flights_Table F
    LEFT JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
    LEFT JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
    LEFT JOIN Planes_Table P ON F.PlaneID = P.PlaneID
    -- İZOLASYON: Sadece bu acentanın rezervasyon yaptığı uçuşları getir
    WHERE F.FlightID IN (SELECT DISTINCT FlightID FROM Reservation_Table WHERE CompanyID = ?)
    ORDER BY F.DepartureTime DESC
";

$stmtFlights = sqlsrv_query($conn, $sqlFlights, array($userCompanyID));
$flightsData = [];
if ($stmtFlights) {
    while($f = sqlsrv_fetch_array($stmtFlights, SQLSRV_FETCH_ASSOC)) {
        $flightID = $f['FlightID'];
        
        // Get total capacity from plane
        $totalCapacity = $f['SeatCapacity'] ?? 0;
        
        // Get total ticket count FOR THIS COMPANY ONLY (excluding cancelled)
        $sqlTotalTickets = "
            SELECT COUNT(*) as TicketCount 
            FROM Tickets_Table T
            INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
            WHERE T.FlightID = ? AND R.CompanyID = ? AND (T.TicketStatus IS NULL OR T.TicketStatus <> 'Cancelled')";
        $stmtTotalTickets = sqlsrv_query($conn, $sqlTotalTickets, array($flightID, $userCompanyID));
        $totalTickets = 0;
        if ($stmtTotalTickets) {
            $rowTotal = sqlsrv_fetch_array($stmtTotalTickets, SQLSRV_FETCH_ASSOC);
            $totalTickets = $rowTotal['TicketCount'] ?? 0;
        }
        
        // Get baby ticket count FOR THIS COMPANY ONLY (babies don't occupy seats)
        $sqlBabyTickets = "
            SELECT COUNT(*) as BabyCount 
            FROM Tickets_Table T
            INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
            WHERE T.FlightID = ? AND R.CompanyID = ? AND T.AgeType = 'Baby' AND (T.TicketStatus IS NULL OR T.TicketStatus <> 'Cancelled')";
        $stmtBabyTickets = sqlsrv_query($conn, $sqlBabyTickets, array($flightID, $userCompanyID));
        $babyTickets = 0;
        if ($stmtBabyTickets) {
            $rowBaby = sqlsrv_fetch_array($stmtBabyTickets, SQLSRV_FETCH_ASSOC);
            $babyTickets = $rowBaby['BabyCount'] ?? 0;
        }
        
        // Calculate occupied seats: Total tickets - Baby tickets (babies don't occupy seats)
        $occupied = $totalTickets - $babyTickets;
        
        // Uçağın genel boş koltuk sayısı (Diğer acentaların ve THY'nin satışları da dahil genel uçak kapasitesi)
        $sqlGeneralOccupied = "SELECT dbo.FN_GetRemainingSeats(?) as RemainingSeats";
        $stmtGenOcc = sqlsrv_query($conn, $sqlGeneralOccupied, array($flightID));
        $remaining = 0;
        if ($stmtGenOcc) {
            $rowGenOcc = sqlsrv_fetch_array($stmtGenOcc, SQLSRV_FETCH_ASSOC);
            $remaining = $rowGenOcc['RemainingSeats'] ?? 0;
        }
        if ($remaining < 0) $remaining = 0;
        
        // Acentanın o uçaktaki Payı (Yüzdesi)
        $occupancyPercent = $totalCapacity > 0 ? round((100 * $occupied) / $totalCapacity, 2) : 0;
        
        // Add processed data to array for display
        $flightsData[] = [
            'FlightID' => $flightID,
            'FlightNo' => $f['FlightNo'],
            'DepartureTime' => $f['DepartureTime'],
            'ArrivalTime' => $f['ArrivalTime'],
            'Status' => $f['Status'],
            'DepCity' => $f['DepCity'],
            'DepIATA' => $f['DepIATA'],
            'ArrCity' => $f['ArrCity'],
            'ArrIATA' => $f['ArrIATA'],
            'PlaneModel' => $f['PlaneModel'],
            'TotalCapacity' => $totalCapacity,
            'Occupied' => $occupied, // Bu acentanın sattığı koltuk sayısı
            'Remaining' => $remaining, // Uçaktaki genel boş koltuk
            'OccupancyPercent' => $occupancyPercent, // Acentanın uçağı doldurma payı
            'TicketCount' => $totalTickets // Acentanın kestiği bilet
        ];
    }
}

// Calculate overall statistics
$totalFlights = count($flightsData);
$totalCapacityAll = array_sum(array_column($flightsData, 'TotalCapacity'));
$totalOccupiedAll = array_sum(array_column($flightsData, 'Occupied'));
// Acentanın genel olarak uçakları doldurma payı
$overallOccupancy = $totalCapacityAll > 0 ? round((100 * $totalOccupiedAll) / $totalCapacityAll, 2) : 0;

// Get flights by status
$flightsByStatus = [
    'Planned' => 0,
    'Delayed' => 0,
    'Cancelled' => 0,
    'Land' => 0
];
foreach($flightsData as $f) {
    $status = $f['Status'] ?? 'Planned';
    if (isset($flightsByStatus[$status])) {
        $flightsByStatus[$status]++;
    }
}
?>

<!-- REPORTS TAB CONTENT -->
<div id="tab-reports" class="tab-content <?php echo $activeTab === 'reports' ? 'active' : ''; ?>">
    <h2><i class="fas fa-chart-bar"></i> Agency Sales Analytics</h2>
    
    <!-- Overall Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Flights with Your Sales</div>
            <div style="font-size: 32px; font-weight: bold;"><?php echo $totalFlights; ?></div>
        </div>
        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Total Plane Capacity</div>
            <div style="font-size: 32px; font-weight: bold;"><?php echo number_format($totalCapacityAll); ?></div>
        </div>
        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Seats Sold by You</div>
            <div style="font-size: 32px; font-weight: bold;"><?php echo number_format($totalOccupiedAll); ?></div>
        </div>
        <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 8px;">Your Market Share</div>
            <div style="font-size: 32px; font-weight: bold;"><?php echo $overallOccupancy; ?>%</div>
        </div>
    </div>
    
    <!-- Flights by Status -->
    <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-chart-pie"></i> Your Flights by Status</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="text-align: center; padding: 15px; background: #e2e3ff; border-radius: 6px;">
                <div style="font-size: 24px; font-weight: bold; color: #3b3f9f;"><?php echo $flightsByStatus['Planned']; ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">Planned</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 6px;">
                <div style="font-size: 24px; font-weight: bold; color: #856404;"><?php echo $flightsByStatus['Delayed']; ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">Delayed</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 6px;">
                <div style="font-size: 24px; font-weight: bold; color: #721c24;"><?php echo $flightsByStatus['Cancelled']; ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">Cancelled</div>
            </div>
            <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 6px;">
                <div style="font-size: 24px; font-weight: bold; color: #155724;"><?php echo $flightsByStatus['Land']; ?></div>
                <div style="font-size: 12px; color: #666; margin-top: 5px;">Land</div>
            </div>
        </div>
    </div>
    
    <!-- Flight Occupancy Details -->
    <div>
        <h3 style="margin-bottom: 15px;"><i class="fas fa-plane"></i> Your Sales Performance per Flight</h3>
        
        <?php if(!empty($flightsData)): ?>
            <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Flight No</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Route</th>
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Departure</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Capacity</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Your Sales</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Flight Avail.</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Your Share %</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($flightsData as $f): 
                            $occupancyColor = $f['OccupancyPercent'] >= 50 ? '#28a745' : ($f['OccupancyPercent'] >= 20 ? '#ffc107' : '#dc3545');
                        ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 12px;"><strong><?php echo htmlspecialchars($f['FlightNo']); ?></strong></td>
                                <td style="padding: 12px;">
                                    <?php echo htmlspecialchars($f['DepCity']); ?> (<?php echo htmlspecialchars($f['DepIATA']); ?>) 
                                    <i class="fas fa-arrow-right" style="margin: 0 5px; color: #999;"></i>
                                    <?php echo htmlspecialchars($f['ArrCity']); ?> (<?php echo htmlspecialchars($f['ArrIATA']); ?>)
                                </td>
                                <td style="padding: 12px;">
                                    <?php echo isset($f['DepartureTime']) && $f['DepartureTime'] instanceof DateTime ? $f['DepartureTime']->format('d M Y H:i') : 'N/A'; ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <strong style="font-size: 14px; color: #666;"><?php echo $f['TotalCapacity']; ?></strong>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <strong style="font-size: 16px; color: #0056b3;"><?php echo $f['Occupied']; ?></strong>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <strong style="font-size: 14px; color: #28a745;"><?php echo $f['Remaining']; ?></strong>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <div style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 12px; background: <?php echo $occupancyColor; ?>; color: white;">
                                        <?php echo $f['OccupancyPercent']; ?>%
                                    </div>
                                    <div style="width: 100px; height: 6px; background: #e9ecef; border-radius: 3px; margin-top: 5px; overflow: hidden; display: inline-block;">
                                        <div style="width: <?php echo $f['OccupancyPercent']; ?>%; height: 100%; background: <?php echo $occupancyColor; ?>;"></div>
                                    </div>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php 
                                    $status = $f['Status'] ?? 'Planned';
                                    $statusColors = [
                                        'Planned' => ['bg' => '#e2e3ff', 'text' => '#3b3f9f'],
                                        'Delayed' => ['bg' => '#fff3cd', 'text' => '#856404'],
                                        'Cancelled' => ['bg' => '#f8d7da', 'text' => '#721c24'],
                                        'Land' => ['bg' => '#d4edda', 'text' => '#155724']
                                    ];
                                    $sc = $statusColors[$status] ?? ['bg' => '#f0f0f0', 'text' => '#333'];
                                    ?>
                                    <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; 
                                        background: <?php echo $sc['bg']; ?>; 
                                        color: <?php echo $sc['text']; ?>;">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 40px;">No flight sales data found for your agency.</p>
        <?php endif; ?>
    </div>
</div>