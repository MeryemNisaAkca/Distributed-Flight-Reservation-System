<?php

include 'connecting.php';
header('Content-Type: application/json');

$userID = $_GET['user_id'] ?? '';
$agency = $_GET['agency'] ?? '';

if (empty($userID)) {
    echo json_encode(['status' => 'error', 'message' => 'Kullanıcı kimliği bulunamadı.']);
    exit;
}

// 1. Sadakat Puanını Çek
$sqlPoints = "SELECT LoyaltyPoint FROM Users_Table WHERE UserID = ?";
$stmtPoints = sqlsrv_query($conn, $sqlPoints, array($userID));
$userPoints = ($stmtPoints && $rowPoints = sqlsrv_fetch_array($stmtPoints, SQLSRV_FETCH_ASSOC)) ? ($rowPoints['LoyaltyPoint'] ?? 0) : 0;

// 2. Rezervasyonları Çek
$sql = "
    SELECT DISTINCT
        R.PNR, R.ReservationID, R.ReservationDateTime,
        F.FlightID, F.FlightNo, F.DepartureTime, F.ArrivalTime, F.Status as FlightStatus,
        DepAirport.City as DepCity, DepAirport.IATA as DepIATA,
        ArrAirport.City as ArrCity, ArrAirport.IATA as ArrIATA
    FROM Reservation_Table R
    INNER JOIN Flights_Table F ON R.FlightID = F.FlightID
    LEFT JOIN Airports_Table DepAirport ON F.DepartureAirportID = DepAirport.AirportID
    LEFT JOIN Airports_Table ArrAirport ON F.ArrivalAirportID = ArrAirport.AirportID
    WHERE R.UserID = ?
    ORDER BY F.DepartureTime DESC
";
$stmt = sqlsrv_query($conn, $sql, array($userID));

$upcomingFlights = [];
$pastFlights = [];
$delayedFlightsList = [];
$hasDelayedFlight = false;
$now = new DateTime();

if ($stmt) {
    while ($res = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        
        $flightDate = $res['DepartureTime'];
        
        // Gecikme Kontrolü
        if (($res['FlightStatus'] ?? 'Planned') === 'Delayed' && $flightDate > $now) {
            $hasDelayedFlight = true;
            $delayedFlightsList[] = [
                'FlightNo' => $res['FlightNo'],
                'Route' => ($res['DepCity'] ?? 'N/A') . ' (' . ($res['DepIATA'] ?? 'N/A') . ') → ' . ($res['ArrCity'] ?? 'N/A') . ' (' . ($res['ArrIATA'] ?? 'N/A') . ')',
                'DepartureTimeStr' => $flightDate->format('Y-m-d H:i:s')
            ];
        }

        // 3. Bu Rezervasyonun Biletlerini Çek
        $sqlTickets = "
            SELECT 
                T.TicketID, T.PassengerName, T.PassengerSurname, T.AgeType, T.CabinType,
                T.SeatNo, T.MealID, T.BaggageID, T.TicketStatus,
                M.MealName, M.Price as MealPrice, B.Weight as BaggageWeight, B.Price as BaggagePrice
            FROM Tickets_Table T
            LEFT JOIN MealPackages M ON T.MealID = M.MealID
            LEFT JOIN BaggagePackages B ON T.BaggageID = B.BaggageID
            WHERE T.ReservationID = ?
            ORDER BY T.TicketID
        ";
        $stmtTickets = sqlsrv_query($conn, $sqlTickets, array($res['ReservationID']));
        
        $tickets = [];
        if ($stmtTickets) {
            while ($ticket = sqlsrv_fetch_array($stmtTickets, SQLSRV_FETCH_ASSOC)) {
                $companionName = null;
                if (strtolower($ticket['AgeType']) === 'baby' && !empty($ticket['SeatNo'])) {
                    $sqlComp = "SELECT PassengerName, PassengerSurname FROM Tickets_Table WHERE ReservationID = ? AND SeatNo = ? AND TicketID <> ? AND LOWER(AgeType) <> 'baby' AND CheckInStatus = 1";
                    $stmtComp = sqlsrv_query($conn, $sqlComp, array($res['ReservationID'], $ticket['SeatNo'], $ticket['TicketID']));
                    if ($stmtComp && $comp = sqlsrv_fetch_array($stmtComp, SQLSRV_FETCH_ASSOC)) {
                        $companionName = $comp['PassengerName'] . ' ' . $comp['PassengerSurname'];
                    }
                }
                $ticket['CompanionName'] = $companionName;
                $tickets[] = $ticket;
            }
        }

        // İPTAL EDİLEN BİLETLERİ EN ALTA SIRALAMA MANTIĞI
        usort($tickets, function($a, $b) {
            $aCan = (isset($a['TicketStatus']) && $a['TicketStatus'] === 'Cancelled') ? 1 : 0;
            $bCan = (isset($b['TicketStatus']) && $b['TicketStatus'] === 'Cancelled') ? 1 : 0;
            return $aCan - $bCan;
        });

        $res['Tickets'] = $tickets;
        
        // Tarihleri JSON'a uygun String yap
        $res['DepartureTimeStr'] = $flightDate->format('Y-m-d H:i:s');
        $res['ArrivalTimeStr'] = $res['ArrivalTime']->format('Y-m-d H:i:s');
        unset($res['DepartureTime']);
        unset($res['ArrivalTime']);
        unset($res['ReservationDateTime']); // Gönderilmese de olur

        if ($flightDate > $now) {
            $upcomingFlights[] = $res;
        } else {
            $pastFlights[] = $res;
        }
    }
}

echo json_encode([
    'status' => 'success',
    'userPoints' => $userPoints,
    'hasDelayedFlight' => $hasDelayedFlight,
    'delayedFlightsList' => $delayedFlightsList,
    'upcomingFlights' => $upcomingFlights,
    'pastFlights' => $pastFlights
]);
?>