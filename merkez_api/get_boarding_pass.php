<?php

// Bu API, PNR'a göre Check-in işlemi tamamlanmış biletleri bulup Acentaya gönderir.
include 'connecting.php';
header('Content-Type: application/json');

$pnr = $_GET['pnr'] ?? '';
$agency = $_GET['agency'] ?? 'BİLİNMİYOR';

if (empty($pnr)) {
    echo json_encode(['status' => 'error', 'message' => 'PNR eksik gönderildi.']);
    exit;
}

// 1. PNR Sistemde Var mı ve Check-in Yapılmış Bilet Var mı Kontrol Et
$sql = "
    SELECT 
        T.TicketID, T.PassengerName, T.PassengerSurname, T.SeatNo, T.CabinType,
        F.FlightNo, F.DepartureTime,
        D.IATA as DepCode, 
        A.IATA as ArrCode
    FROM Tickets_Table T
    INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
    INNER JOIN Flights_Table F ON R.FlightID = F.FlightID
    INNER JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
    INNER JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
    WHERE R.PNR = ? AND T.CheckInStatus = 1 AND (T.TicketStatus IS NULL OR T.TicketStatus <> 'Cancelled')
";

$stmt = sqlsrv_query($conn, $sql, array($pnr));

if ($stmt === false) {
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı hatası oluştu.']);
    exit;
}

$passes = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // DateTime nesnesini Acentanın beklediği String formatına çevir
    if ($row['DepartureTime']) {
        $row['DepartureTime'] = $row['DepartureTime']->format('Y-m-d H:i:s');
    }
    $passes[] = $row;
}

// 2. Cevabı Gönder
if (count($passes) > 0) {
    echo json_encode(['status' => 'success', 'passes' => $passes]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Bu PNR için Check-in yapılmış bilet bulunamadı. Lütfen önce Check-in yapınız.']);
}
?>