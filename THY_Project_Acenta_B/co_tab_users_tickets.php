<?php
//User Analytics: Pulls only users who have made a booking through THIS company.
//Financial Reports: Calculates KPI (Key Performance Indicators) ONLY for this company's sales.
//Ticket Listing: Lists only tickets sold by THIS company grouped by PNR code.

// İzolasyon için gerekli olan şirket ID'sini Session'dan alıyoruz
$userCompanyID = $_SESSION['user_company_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_ticket') {
    $ticketID = (int)$_POST['ticket_id'];
    $pnr = $_POST['pnr'] ?? '';
    $surname = $_POST['surname'] ?? '';
    
    // Güvenlik Duvarı: Bu bilet GERÇEKTEN bu acentanın sattığı bir bilet mi?
    $checkSql = "SELECT T.TicketID FROM Tickets_Table T 
                 INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID 
                 WHERE T.TicketID = ? AND R.CompanyID = ?";
    $checkStmt = sqlsrv_query($conn, $checkSql, array($ticketID, $_SESSION['user_company_id']));
    
    if (sqlsrv_has_rows($checkStmt)) {
        
        // DOĞRUDAN SQL UPDATE YERİNE MERKEZ API'YE İSTEK ATIYORUZ (Distributed Mimari Kuralı)
        $cancelPayload = [
            "pnr" => $pnr,
            "ticket_id" => $ticketID,
            "user_id" => null, // Şirket iptal ettiği için spesifik bir müşteri oturumu aramıyoruz
            "surname" => $surname,
            "agency" => AGENCY_CODE,
            "baby_action" => "cancel_baby", // Eğer bebek varsa acenta otomatik iptal etsin
            "new_companion_id" => ""
        ];

        $postUrl = MERKEZ_URL . "/process_cancel.php"; // Merkez bağlantısı
        $ch2 = curl_init($postUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($cancelPayload));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $apiCevap = curl_exec($ch2);
        $api_ulasildi = !curl_errno($ch2);
        curl_close($ch2);

        $result = json_decode($apiCevap, true);

        // API'den Gelen Yanıta Göre Kullanıcıya Mesaj Ver
        if ($api_ulasildi && isset($result['status']) && $result['status'] === 'success') {
            $_SESSION['success_message'] = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Ticket #$ticketID has been successfully cancelled via Central API. Seat is released.</div>";
        } else {
            $hataDetay = $result['message'] ?? 'Central API rejected the action.';
            $_SESSION['error_message'] = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-ban'></i> Cancellation failed: " . htmlspecialchars($hataDetay) . "</div>";
        }

    } else {
        $_SESSION['error_message'] = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-ban'></i> Unauthorized action! You can only cancel tickets sold by your agency.</div>";
    }
    
    // İşlem bitince JavaScript ile sayfayı yenile
    echo "<script>window.location.href = 'company_owner_dashboard.php?tab=users_tickets';</script>";
    exit();
}

// 1. Fetch Users data (Sadece bu acentadan bilet almış müşteriler)
$sqlUsers = "
    SELECT DISTINCT U.UserID, U.Name, U.Surname, U.Email, U.Role, U.LoyaltyPoint 
    FROM Users_Table U
    INNER JOIN Reservation_Table R ON U.UserID = R.UserID
    WHERE R.CompanyID = ?
    ORDER BY U.UserID DESC
";
$stmtUsers = sqlsrv_query($conn, $sqlUsers, array($userCompanyID));

$usersArray = [];
if ($stmtUsers !== false) {
    while($u = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
        $usersArray[] = $u;
    }
} else {
    $errors = sqlsrv_errors();
    if ($errors) {
        error_log("Users query error: " . print_r($errors, true));
    }
}

// 2. Calculate spent loyalty points (Sadece bu acentada harcanan puanlar)
$spentPointsMap = [];
foreach($usersArray as $u) {
    $userID = $u['UserID'];
    $spentPoints = 0;
    
    try {
        $sqlSpent = "SELECT ISNULL(SUM(LoyaltyPointsUsed), 0) as TotalSpent 
                     FROM Reservation_Table 
                     WHERE UserID = ? AND CompanyID = ? AND (LoyaltyPointsUsed IS NOT NULL AND LoyaltyPointsUsed > 0)";
        $stmtSpent = sqlsrv_query($conn, $sqlSpent, array($userID, $userCompanyID));
        
        if ($stmtSpent !== false) {
            $rowSpent = sqlsrv_fetch_array($stmtSpent, SQLSRV_FETCH_ASSOC);
            if ($rowSpent) {
                $spentPoints = $rowSpent['TotalSpent'] ?? 0;
            }
        }
    } catch (Exception $e) {
        $spentPoints = 0;
        error_log("Error calculating spent points for UserID $userID: " . $e->getMessage());
    }
    
    $spentPointsMap[$userID] = $spentPoints;
}

// 3. BİLET VE REZERVASYONLAR (Sadece bu acentanın sattığı biletler)
$sqlTickets = "
    SELECT 
        T.TicketID, T.PassengerName, T.PassengerSurname, T.AgeType, T.CabinType, T.TicketPrice,
        ISNULL(T.TicketStatus, 'Active') as TicketStatus, T.CheckInStatus, T.SeatNo, T.MealID, T.BaggageID,
        R.ReservationID, R.PNR, R.ReservationDateTime, R.TotalCost, R.PaymentStatus, R.ContactEmail, R.ContactName, R.ContactSurname, R.UserID,
        F.FlightID, F.FlightNo, F.DepartureTime, F.ArrivalTime, F.Status as FlightStatus,
        dbo.FN_GetTicketCountByStatus(F.FlightID, 1) as TotalCheckedIn,
        dbo.FN_GetRemainingSeats(F.FlightID) as RemainingSeats,
        ISNULL(DepAirport.City, 'Unknown') as DepCity, ISNULL(DepAirport.IATA, 'N/A') as DepIATA,
        ISNULL(ArrAirport.City, 'Unknown') as ArrCity, ISNULL(ArrAirport.IATA, 'N/A') as ArrIATA,
        U.Name as UserName, U.Surname as UserSurname, U.Email as UserEmail
    FROM Tickets_Table T
    INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
    INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
    LEFT JOIN Airports_Table DepAirport ON F.DepartureAirportID = DepAirport.AirportID
    LEFT JOIN Airports_Table ArrAirport ON F.ArrivalAirportID = ArrAirport.AirportID
    LEFT JOIN Users_Table U ON R.UserID = U.UserID
    WHERE R.CompanyID = ?
    ORDER BY R.ReservationDateTime DESC, T.TicketID DESC
";

$stmtTickets = sqlsrv_query($conn, $sqlTickets, array($userCompanyID));

$allTicketsArray = [];
if ($stmtTickets !== false) {
    while($ticket = sqlsrv_fetch_array($stmtTickets, SQLSRV_FETCH_ASSOC)) {
        $allTicketsArray[] = $ticket;
    }
} else {
    $errors = sqlsrv_errors();
    if ($errors) {
        error_log("Tickets query error: " . print_r($errors, true));
    }
}

// Group tickets by PNR
$ticketsByPNR = [];
foreach($allTicketsArray as $ticket) {
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
                'ArrCity' => $ticket['ArrCity'],
                'ArrIATA' => $ticket['ArrIATA'],
                'RemainingSeats' => $ticket['RemainingSeats'],
                'TotalCheckedIn' => $ticket['TotalCheckedIn']
            ],
            'tickets' => []
        ];
    }
    $ticketsByPNR[$pnr]['tickets'][] = $ticket;
}

// 4. İSTATİSTİKLER (Sadece bu acentanın kazançları)
$sqlTotalRevenue = "
    SELECT 
        (SELECT ISNULL(SUM(TotalCost), 0) FROM Reservation_Table WHERE PaymentStatus = 'Completed' AND CompanyID = ?) 
        - 
        (SELECT ISNULL(SUM(T.TicketPrice), 0) FROM Tickets_Table T INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID WHERE R.PaymentStatus = 'Completed' AND R.CompanyID = ? AND T.TicketStatus = 'Cancelled')
    AS TotalRevenue
";
$stmtTotalRevenue = sqlsrv_query($conn, $sqlTotalRevenue, array($userCompanyID, $userCompanyID));
$totalRevenue = ($stmtTotalRevenue && $row = sqlsrv_fetch_array($stmtTotalRevenue, SQLSRV_FETCH_ASSOC)) ? ($row['TotalRevenue'] ?? 0) : 0;

// Today's Revenue 
$sqlTodayRevenue = "
    SELECT 
        (SELECT ISNULL(SUM(TotalCost), 0) FROM Reservation_Table WHERE PaymentStatus = 'Completed' AND CAST(ReservationDateTime AS DATE) = CAST(GETDATE() AS DATE) AND CompanyID = ?) 
        - 
        (SELECT ISNULL(SUM(T.TicketPrice), 0) FROM Tickets_Table T INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID WHERE R.PaymentStatus = 'Completed' AND CAST(R.ReservationDateTime AS DATE) = CAST(GETDATE() AS DATE) AND R.CompanyID = ? AND T.TicketStatus = 'Cancelled')
    AS TodayRevenue
";
$stmtTodayRevenue = sqlsrv_query($conn, $sqlTodayRevenue, array($userCompanyID, $userCompanyID));
$todayRevenue = ($stmtTodayRevenue && $row = sqlsrv_fetch_array($stmtTodayRevenue, SQLSRV_FETCH_ASSOC)) ? ($row['TodayRevenue'] ?? 0) : 0;
$sqlUpcomingFlights = "SELECT COUNT(DISTINCT F.FlightID) as UpcomingCount FROM Flights_Table F INNER JOIN Reservation_Table R ON F.FlightID = R.FlightID WHERE F.DepartureTime >= GETDATE() AND F.Status NOT IN ('Cancelled') AND R.CompanyID = ?";
$stmtUpcomingFlights = sqlsrv_query($conn, $sqlUpcomingFlights, array($userCompanyID));
$upcomingFlights = ($stmtUpcomingFlights && $row = sqlsrv_fetch_array($stmtUpcomingFlights, SQLSRV_FETCH_ASSOC)) ? ($row['UpcomingCount'] ?? 0) : 0;

$sqlOccupancy = "SELECT AVG(CASE WHEN P.SeatCapacity > 0 THEN CAST((SELECT COUNT(DISTINCT T2.TicketID) FROM Tickets_Table T2 WHERE T2.FlightID = F.FlightID AND (T2.TicketStatus IS NULL OR T2.TicketStatus NOT IN ('Cancelled')) AND (T2.AgeType IS NULL OR T2.AgeType <> 'Baby')) AS FLOAT) / CAST(P.SeatCapacity AS FLOAT) * 100 ELSE 0 END) as AvgOccupancy FROM Flights_Table F INNER JOIN Planes_Table P ON F.PlaneID = P.PlaneID WHERE F.DepartureTime >= GETDATE() AND F.Status NOT IN ('Cancelled') AND F.FlightID IN (SELECT DISTINCT FlightID FROM Reservation_Table WHERE CompanyID = ?)";
$stmtOccupancy = sqlsrv_query($conn, $sqlOccupancy, array($userCompanyID));
$avgOccupancy = ($stmtOccupancy && $row = sqlsrv_fetch_array($stmtOccupancy, SQLSRV_FETCH_ASSOC)) ? ($row['AvgOccupancy'] ?? 0) : 0;

$sqlCheckInRate = "SELECT COUNT(CASE WHEN T.CheckInStatus = 1 THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled')) THEN 1 END), 0) as CheckInRate FROM Tickets_Table T INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID INNER JOIN Flights_Table F ON T.FlightID = F.FlightID WHERE F.DepartureTime >= GETDATE() AND F.Status NOT IN ('Cancelled') AND (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled')) AND R.CompanyID = ?";
$stmtCheckInRate = sqlsrv_query($conn, $sqlCheckInRate, array($userCompanyID));
$checkInRate = ($stmtCheckInRate && $row = sqlsrv_fetch_array($stmtCheckInRate, SQLSRV_FETCH_ASSOC)) ? ($row['CheckInRate'] ?? 0) : 0;
?>

<!-- USERS & TICKETS TAB CONTENT -->
<div id="tab-users_tickets" class="tab-content <?php echo $activeTab === 'users_tickets' ? 'active' : ''; ?>">
    <h2><i class="fas fa-users"></i> Users & Tickets</h2>
    
    <!-- Business Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 1;">
                <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-money-bill-wave"></i> Total Revenue</div>
                <div style="font-size: 28px; font-weight: bold; margin-bottom: 5px;">₺<?php echo number_format($totalRevenue, 0); ?></div>
                <small style="opacity: 0.8; font-size: 11px;">All completed bookings</small>
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 1;">
                <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-calendar-day"></i> Today's Revenue</div>
                <div style="font-size: 28px; font-weight: bold; margin-bottom: 5px;">₺<?php echo number_format($todayRevenue, 0); ?></div>
                <small style="opacity: 0.8; font-size: 11px;">Bookings made today</small>
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 1;">
                <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-plane-departure"></i> Upcoming Flights</div>
                <div style="font-size: 28px; font-weight: bold; margin-bottom: 5px;"><?php echo $upcomingFlights; ?></div>
                <small style="opacity: 0.8; font-size: 11px;">Scheduled flights</small>
            </div>
        </div>
        
        <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
            <div style="position: relative; z-index: 1;">
                <div style="font-size: 11px; opacity: 0.9; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-chart-line"></i> Avg Occupancy</div>
                <div style="font-size: 28px; font-weight: bold; margin-bottom: 5px;"><?php echo number_format($avgOccupancy, 1); ?>%</div>
                <small style="opacity: 0.8; font-size: 11px;">Upcoming flights</small>
            </div>
        </div>
    </div>
    
    <!-- Users Section -->
    <div style="margin-bottom: 40px;">
        <h3 style="margin-bottom: 15px;"><i class="fas fa-user-friends"></i> Agency Customers (<?php echo count($usersArray); ?>)</h3>
        <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">User ID</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Name</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Email</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Role</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Loyalty Points</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Spent Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($usersArray)): ?>
                        <?php foreach($usersArray as $u): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <td style="padding: 12px;"><?php echo $u['UserID']; ?></td>
                                <td style="padding: 12px;"><strong><?php echo htmlspecialchars($u['Name'] . ' ' . $u['Surname']); ?></strong></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($u['Email']); ?></td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; background: <?php echo ($u['Role']??'Passenger')==='Admin'?'#dc3545':(($u['Role']??'Passenger')==='CompanyOwner'?'#ffc107':'#28a745'); ?>; color: white;">
                                        <?php echo htmlspecialchars($u['Role'] ?? 'Passenger'); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;"><?php echo number_format($u['LoyaltyPoint'] ?? 0, 0); ?></td>
                                <td style="padding: 12px;"><span style="color: #dc3545; font-weight: bold;"><?php echo number_format($spentPointsMap[$u['UserID']] ?? 0, 0); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tickets Section -->
    <div>
        <h3 style="margin-bottom: 15px;">
            <i class="fas fa-ticket-alt"></i> Agency Tickets 
            (<?php echo count($ticketsByPNR); ?> Reservations, <?php echo count($allTicketsArray); ?> Total Tickets)
        </h3>
        
        <!-- YENİ EKLENEN FİLTRE BUTONLARI -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <button id="btn-t-all" onclick="filterTickets('All')" style="flex: 1; padding: 10px; border: none; background: #007bff; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 14px;">
                <i class="fas fa-list"></i> All Tickets
            </button>
            <button id="btn-t-active" onclick="filterTickets('Active')" style="flex: 1; padding: 10px; border: 1px solid #ddd; background: white; color: #555; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 14px;">
                <i class="fas fa-check-circle"></i> Active Tickets
            </button>
            <button id="btn-t-cancelled" onclick="filterTickets('Cancelled')" style="flex: 1; padding: 10px; border: 1px solid #ddd; background: white; color: #555; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 14px;">
                <i class="fas fa-ban"></i> Cancelled Tickets
            </button>
        </div>
        <!-- ------------------------------ -->

        <?php if(!empty($ticketsByPNR)): ?>
            <div style="display: grid; gap: 20px;" id="all-pnr-cards">
                <?php foreach($ticketsByPNR as $pnr => $reservation): 
                    $resInfo = $reservation['reservation_info'];
                    $flightInfo = $reservation['flight_info'];
                    $tickets = $reservation['tickets'];
                ?>
                    <div class="pnr-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <!-- PNR VE UÇUŞ BİLGİLERİ -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #eee;">
                            <div>
                                <strong style="font-size: 18px;">PNR: <?php echo htmlspecialchars($pnr); ?></strong>
                            </div>
                        </div>
                        
                        <!-- YOLCU DÖNGÜSÜ BAŞLANGICI -->
                        <label style="font-size: 12px; font-weight: bold; margin-bottom: 10px; display: block;">Passengers (<?php echo count($tickets); ?>)</label>
                        <div style="display: grid; gap: 8px;">
                            <?php foreach($tickets as $t): ?>
                                <?php 
                                // ÇÖZÜM: Veritabanındaki 'Used' veya 'Active' durumlarının ikisini de UI için "Active" kabul ediyoruz
                                $isTicketActive = ($t['TicketStatus'] === 'Active' || $t['TicketStatus'] === 'Used'); 
                                $displayStatus = $isTicketActive ? 'Active' : htmlspecialchars($t['TicketStatus']);
                                ?>
                                <!-- Yolcu Satırı: data-status değerini JavaScript'in aradığı 'Active' kelimesine sabitliyoruz -->
                                <div class="passenger-row" data-status="<?php echo $displayStatus; ?>" style="padding: 10px; background: #f8f9fa; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($t['PassengerName'] . ' ' . $t['PassengerSurname']); ?></strong>
                                        <span style="color: #666; font-size: 12px; margin-left: 8px;">(<?php echo htmlspecialchars($t['AgeType']); ?>)</span>
                                    </div>
                                    
                                    <!-- FİYAT, DURUM VE İPTAL BUTONU -->
                                    <div style="text-align: right; display: flex; align-items: center; gap: 10px;">
                                        <div style="font-size: 13px; font-weight: bold;"><?php echo number_format($t['TicketPrice'], 2); ?> ₺</div>
                                        
                                        <!-- Durum Rozeti (Artık 'Used' biletler de Yeşil renkte 'Active' yazacak) -->
                                        <span style="padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: bold;
                                            background: <?php echo $isTicketActive ? '#d4edda' : '#f8d7da'; ?>;
                                            color: <?php echo $isTicketActive ? '#155724' : '#721c24'; ?>;">
                                            <?php echo $displayStatus; ?>
                                        </span>

                                        <!-- İPTAL BUTONU -->
                                        <?php if($t['TicketStatus'] !== 'Cancelled'): ?>
                                            <form method="POST" action="company_owner_dashboard.php?tab=users_tickets" style="margin:0;" onsubmit="return confirm('Cancel this ticket?');">
                                                <input type="hidden" name="action" value="cancel_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo $t['TicketID']; ?>">
                                                
                                                <!-- API İÇİN YENİ EKLENEN GİZLİ ALANLAR -->
                                                <input type="hidden" name="pnr" value="<?php echo htmlspecialchars($pnr); ?>">
                                                <input type="hidden" name="surname" value="<?php echo htmlspecialchars($t['PassengerSurname']); ?>">
                                                <!-- --------------------------------- -->

                                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 3px 8px; border-radius: 12px; font-size: 10px; cursor: pointer;">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; // Yolcu döngüsü sonu ?>
                        </div>
                    </div>
                <?php endforeach; // PNR döngüsü sonu ?>
            </div>
            
            <!-- Hiç sonuç yoksa gösterilecek mesaj -->
            <div id="no-filtered-tickets-msg" style="display: none; text-align: center; padding: 40px; background: white; border-radius: 8px; border: 1px dashed #ccc; margin-top: 20px;">
                <i class="fas fa-filter" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                <p style="color: #666; font-size: 16px;">No tickets found matching the selected filter.</p>
            </div>
            
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 40px;">No tickets found.</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Anlık Bilet Filtreleme Scripti
function filterTickets(status) {
    const btns = {
        'All': document.getElementById('btn-t-all'),
        'Active': document.getElementById('btn-t-active'),
        'Cancelled': document.getElementById('btn-t-cancelled')
    };

    // Butonların renklerini ayarla
    for (const key in btns) {
        if (key === status) {
            btns[key].style.background = (status === 'Active') ? '#28a745' : ((status === 'Cancelled') ? '#dc3545' : '#007bff');
            btns[key].style.color = 'white';
            btns[key].style.border = 'none';
        } else {
            btns[key].style.background = 'white';
            btns[key].style.color = '#555';
            btns[key].style.border = '1px solid #ddd';
        }
    }

    const pnrCards = document.querySelectorAll('.pnr-card');
    let totalVisiblePnrs = 0;

    pnrCards.forEach(card => {
        const passengers = card.querySelectorAll('.passenger-row');
        let visiblePassengersInCard = 0;

        passengers.forEach(p => {
            const pStatus = p.getAttribute('data-status');
            if (status === 'All' || pStatus === status) {
                p.style.display = 'flex'; // passenger-row aslında display: flex kullanıyor
                visiblePassengersInCard++;
            } else {
                p.style.display = 'none';
            }
        });

        // Eğer bu PNR kartında filtremize uygun hiç yolcu yoksa, PNR kartını komple gizle
        if (visiblePassengersInCard > 0) {
            card.style.display = 'block';
            totalVisiblePnrs++;
        } else {
            card.style.display = 'none';
        }
    });

    // Filtreleme sonucu hiçbir şey kalmadıysa uyarı mesajı göster
    const noMsg = document.getElementById('no-filtered-tickets-msg');
    if (totalVisiblePnrs === 0) {
        if(noMsg) noMsg.style.display = 'block';
    } else {
        if(noMsg) noMsg.style.display = 'none';
    }
}
</script>