<?php
// Merkez Sunucu - get_checkin_data.php
include 'connecting.php';
header('Content-Type: application/json');

$pnr = $_GET['pnr'] ?? '';
if (empty($pnr)) { echo json_encode(['status' => 'error', 'message' => 'PNR eksik']); exit; }

// 1. Uçuş Saati ve Gecikme Kontrolü
$sqlFlightTime = "SELECT F.DepartureTime, F.FlightNo, F.Status as FlightStatus, DATEDIFF(HOUR, GETDATE(), F.DepartureTime) as HoursUntilDeparture, DATEDIFF(MINUTE, GETDATE(), F.DepartureTime) as MinutesUntilDeparture FROM Reservation_Table R INNER JOIN Flights_Table F ON R.FlightID = F.FlightID WHERE R.PNR = ?";
$stmtFlightTime = sqlsrv_query($conn, $sqlFlightTime, array($pnr));
$flightTimeData = sqlsrv_fetch_array($stmtFlightTime, SQLSRV_FETCH_ASSOC);

if (!$flightTimeData) { echo json_encode(['status' => 'error', 'message' => 'Rezervasyon bulunamadı']); exit; }
if ($flightTimeData['DepartureTime']) $flightTimeData['DepartureTime'] = $flightTimeData['DepartureTime']->format('Y-m-d H:i:s');

// 2. Bagaj ve Yemek Fiyatları
$baggageOptions = []; $baggagePrices = [];
$stmtBaggage = sqlsrv_query($conn, "SELECT * FROM BaggagePackages");
while($b = sqlsrv_fetch_array($stmtBaggage, SQLSRV_FETCH_ASSOC)) { $baggageOptions[] = $b; $baggagePrices[$b['BaggageID']] = $b['Price']; }

$mealOptions = []; $mealPrices = [];
$stmtMeals = sqlsrv_query($conn, "SELECT * FROM MealPackages");
while($m = sqlsrv_fetch_array($stmtMeals, SQLSRV_FETCH_ASSOC)) { $mealOptions[] = $m; $mealPrices[$m['MealID']] = $m['Price'] ?? 0; }

// 3. Uçak Kapasitesi ve Dolu Koltuklar
$sqlFlight = "SELECT TOP 1 F.FlightID, P.SeatCapacity FROM Reservation_Table R INNER JOIN Flights_Table F ON R.FlightID = F.FlightID LEFT JOIN Planes_Table P ON F.PlaneID = P.PlaneID WHERE R.PNR = ?";
$stmtFlight = sqlsrv_query($conn, $sqlFlight, array($pnr));
$flightRow = sqlsrv_fetch_array($stmtFlight, SQLSRV_FETCH_ASSOC);

$occupiedSeats = [];
if($flightRow && $flightRow['FlightID']) {
    $stmtTaken = sqlsrv_query($conn, "SELECT SeatNo FROM VW_OccupiedSeats WHERE FlightID = ?", array($flightRow['FlightID']));
    if($stmtTaken) { while($row = sqlsrv_fetch_array($stmtTaken, SQLSRV_FETCH_ASSOC)) { if(!empty($row['SeatNo'])) $occupiedSeats[] = $row['SeatNo']; } }
}

// 4. Yolcu Listesi
$sqlPassengers = "SELECT T.TicketID, T.PassengerName, T.PassengerSurname, T.AgeType FROM Tickets_Table T INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID WHERE R.PNR = ? AND T.CheckInStatus = 0 AND (T.TicketStatus IS NULL OR T.TicketStatus <> 'Cancelled')";
$stmtPassengers = sqlsrv_query($conn, $sqlPassengers, array($pnr));
$passengers = [];
while($p = sqlsrv_fetch_array($stmtPassengers, SQLSRV_FETCH_ASSOC)) { $passengers[] = $p; }

echo json_encode([
    'status' => 'success',
    'flightTimeData' => $flightTimeData,
    'baggageOptions' => $baggageOptions,
    'baggagePrices' => $baggagePrices,
    'mealOptions' => $mealOptions,
    'mealPrices' => $mealPrices,
    'flightRow' => $flightRow,
    'occupiedSeats' => $occupiedSeats,
    'passengers' => $passengers
]);
?>