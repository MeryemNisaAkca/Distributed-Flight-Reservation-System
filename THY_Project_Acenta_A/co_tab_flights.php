<?php
// This is the Flight View panel for the Company Owner / Agency Admin.
// Agency owners can view all THY flights to sell tickets, but cannot add or modify flights.

if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'connecting.php';

$userCompanyID = $_SESSION['user_company_id'] ?? 0;

// Fetch Flights data for display (Tüm THY uçuşları + Acentanın Satış Durumu)
$sqlFlights = "
    SELECT F.FlightID, F.FlightNo, F.DepartureTime, F.ArrivalTime, F.Status, F.PlaneID,
           D.City as DepCity, D.IATA as DepIATA, A.City as ArrCity, A.IATA as ArrIATA,
           P.Model as PlaneModel, P.SeatCapacity,
           dbo.FN_GetRemainingSeats(F.FlightID) as RemainingSeats,
           (SELECT COUNT(T.TicketID) 
            FROM Tickets_Table T 
            INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID 
            WHERE T.FlightID = F.FlightID AND R.CompanyID = ?) as AgencySalesCount
    FROM Flights_Table F
    LEFT JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
    LEFT JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
    LEFT JOIN Planes_Table P ON F.PlaneID = P.PlaneID
    ORDER BY F.DepartureTime DESC
";

$stmtFlights = sqlsrv_query($conn, $sqlFlights, array($userCompanyID));
$flightsGrouped = [];
if ($stmtFlights === false) {
    $errors = sqlsrv_errors();
    if ($errors) { error_log("Flights query error: " . print_r($errors, true)); }
} else {
    while($f = sqlsrv_fetch_array($stmtFlights, SQLSRV_FETCH_ASSOC)) {
        $flightID = $f['FlightID'];
        if (!isset($flightsGrouped[$flightID])) { $flightsGrouped[$flightID] = $f; }
    }
}

$activeTab = $activeTab ?? 'flights';
?>

<div id="tab-flights" class="tab-content <?php echo $activeTab === 'flights' ? 'active' : ''; ?>">
    <h2><i class="fas fa-plane"></i> Global Flight Schedule</h2>
    <p style="color: #666; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; border-radius: 4px;">
        <i class="fas fa-info-circle"></i> As an agency (<strong><?php echo htmlspecialchars(AGENCY_CODE); ?></strong>), you can view all available flights below. Flight scheduling and status updates are managed strictly by the Central Administration (THY).
    </p>
    
    <?php if(isset($_SESSION['success_message'])) { echo $_SESSION['success_message']; unset($_SESSION['success_message']); } ?>
    <?php if(isset($_SESSION['alert_message'])) { echo "<div style='color:red; margin-bottom:15px;'>".$_SESSION['alert_message']."</div>"; unset($_SESSION['alert_message']); } ?>
    
    <!-- UX GELİŞTİRMESİ: FİLTRE BUTONLARI -->
    <div style="display: flex; gap: 10px; margin-bottom: 20px; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <button id="btn-filter-all" onclick="filterFlights('all')" style="flex: 1; padding: 10px; border: none; background: #007bff; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 14px;">
            <i class="fas fa-globe"></i> Global THY Flights
        </button>
        <button id="btn-filter-agency" onclick="filterFlights('agency')" style="flex: 1; padding: 10px; border: 1px solid #ddd; background: white; color: #555; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 14px;">
            <i class="fas fa-bullseye"></i> My Agency's Flights
        </button>
    </div>
    <!-- ------------------------------- -->

    <h3 style="margin-bottom: 15px;"><i class="fas fa-list"></i> <span id="flight-list-title">Available Flights for Booking</span></h3>
    
    <?php if(!empty($flightsGrouped)): ?>
        <div style="display: grid; gap: 15px;" id="flights-container">
            <?php foreach($flightsGrouped as $f): 
                $flightID = $f['FlightID'];
                $isAgencyFlight = ($f['AgencySalesCount'] > 0) ? 'true' : 'false';
                
                $status = $f['Status'] ?? 'Planned';
                $statusColors = ['Planned' => '#3b3f9f', 'Delayed' => '#856404', 'Cancelled' => '#721c24', 'Land' => '#155724'];
                $statusBgColors = ['Planned' => '#e2e3ff', 'Delayed' => '#fff3cd', 'Cancelled' => '#f8d7da', 'Land' => '#d4edda'];
                
                // Kapasite durumuna göre renk
                $remaining = $f['RemainingSeats'] ?? 0;
                $seatColor = $remaining > 10 ? '#28a745' : ($remaining > 0 ? '#ffc107' : '#dc3545');
            ?>
                <!-- Uçuş Kartı (data-agency-flight parametresi eklendi) -->
                <div class="flight-card" data-agency-flight="<?php echo $isAgencyFlight; ?>" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 5px solid <?php echo $statusColors[$status]; ?>; position: relative;">
                    
                    <?php if($isAgencyFlight === 'true'): ?>
                        <div style="position: absolute; top: -10px; right: 20px; background: #ffc107; color: #000; font-size: 10px; font-weight: bold; padding: 4px 10px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            <i class="fas fa-star"></i> Your Sales: <?php echo $f['AgencySalesCount']; ?> Tickets
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <strong style="font-size: 18px; color: #232b38;"><?php echo htmlspecialchars($f['FlightNo'] ?? 'N/A'); ?></strong>
                            <span style="margin-left: 15px; color: #555; font-weight: bold;">
                                <?php echo htmlspecialchars($f['DepCity'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($f['DepIATA'] ?? 'N/A'); ?>) 
                                <i class="fas fa-plane" style="margin: 0 10px; color: #ccc;"></i>
                                <?php echo htmlspecialchars($f['ArrCity'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($f['ArrIATA'] ?? 'N/A'); ?>)
                            </span>
                        </div>
                        <span style="padding: 6px 15px; border-radius: 20px; font-size: 12px; font-weight: bold; background: <?php echo $statusBgColors[$status] ?? '#f0f0f0'; ?>; color: <?php echo $statusColors[$status] ?? '#333'; ?>;">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; background: #f8f9fa; padding: 15px; border-radius: 6px;">
                        <div>
                            <label style="font-size: 11px; color: #888; text-transform: uppercase;">Departure</label>
                            <div style="font-weight: bold; color: #333;">
                                <?php echo isset($f['DepartureTime']) && $f['DepartureTime'] instanceof DateTime ? $f['DepartureTime']->format('d M Y H:i') : 'N/A'; ?>
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: #888; text-transform: uppercase;">Arrival</label>
                            <div style="font-weight: bold; color: #333;">
                                <?php echo isset($f['ArrivalTime']) && $f['ArrivalTime'] instanceof DateTime ? $f['ArrivalTime']->format('d M Y H:i') : 'N/A'; ?>
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: #888; text-transform: uppercase;">Plane Model</label>
                            <div style="font-weight: bold; color: #333;"><?php echo htmlspecialchars($f['PlaneModel'] ?? 'N/A'); ?></div>
                        </div>
                        <div>
                            <label style="font-size: 11px; color: #888; text-transform: uppercase;">Availability</label>
                            <div style="font-weight: bold; color: <?php echo $seatColor; ?>; font-size: 15px;">
                                <i class="fas fa-chair"></i> <?php echo $remaining; ?> seats left
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Eğer acentanın uçuşu yoksa gösterilecek uyarı mesajı -->
        <div id="no-agency-flights-msg" style="display: none; text-align: center; padding: 40px; background: white; border-radius: 8px; border: 1px dashed #ccc;">
            <i class="fas fa-search" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
            <p style="color: #666; font-size: 16px;">You don't have any active ticket sales for the current flights yet.</p>
        </div>

    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; border: 1px dashed #ccc;">
            <i class="fas fa-plane-slash" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
            <p style="color: #666; font-size: 16px;">No flights available at the moment.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Anlık Filtreleme Scripti
function filterFlights(type) {
    const allCards = document.querySelectorAll('.flight-card');
    const btnAll = document.getElementById('btn-filter-all');
    const btnAgency = document.getElementById('btn-filter-agency');
    const title = document.getElementById('flight-list-title');
    const noMsg = document.getElementById('no-agency-flights-msg');
    
    let visibleCount = 0;

    if (type === 'all') {
        // Global butonuna tıklandığında
        btnAll.style.background = '#007bff';
        btnAll.style.color = 'white';
        btnAll.style.border = 'none';
        
        btnAgency.style.background = 'white';
        btnAgency.style.color = '#555';
        btnAgency.style.border = '1px solid #ddd';
        
        title.innerText = "Available Flights for Booking";
        
        allCards.forEach(card => {
            card.style.display = 'block';
            visibleCount++;
        });
    } else {
        // My Agency butonuna tıklandığında
        btnAgency.style.background = '#28a745';
        btnAgency.style.color = 'white';
        btnAgency.style.border = 'none';
        
        btnAll.style.background = 'white';
        btnAll.style.color = '#555';
        btnAll.style.border = '1px solid #ddd';
        
        title.innerText = "Flights with Your Agency's Sales";
        
        allCards.forEach(card => {
            if (card.getAttribute('data-agency-flight') === 'true') {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Hiç sonuç yoksa bilgi mesajı göster
    if (visibleCount === 0 && type === 'agency') {
        if(noMsg) noMsg.style.display = 'block';
    } else {
        if(noMsg) noMsg.style.display = 'none';
    }
}
</script>