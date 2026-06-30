<?php

include 'connecting.php';
header('Content-Type: application/json');

$pnr = $_GET['pnr'] ?? '';
$surname = $_GET['surname'] ?? '';
$userID = $_GET['user_id'] ?? '';

if (empty($pnr)) {
    echo json_encode(['status' => 'error', 'message' => 'PNR bulunamadı.']);
    exit;
}

$isAuthorized = false;

// 1. Yetki Kontrolü
if (!empty($surname)) {
    $surnameUpper = strtoupper(trim($surname));
    $sqlAuth = "{CALL UP_GuestTicketInformation(?, ?)}";
    $stmtAuth = sqlsrv_query($conn, $sqlAuth, array($pnr, $surnameUpper));
    if ($stmtAuth !== false && sqlsrv_has_rows($stmtAuth)) {
        $isAuthorized = true;
    }
} else if (!empty($userID)) {
    $sqlCheck = "SELECT ReservationID FROM Reservation_Table WHERE PNR = ? AND UserID = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($pnr, $userID));
    if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    $checkExist = sqlsrv_query($conn, "SELECT UserID, ContactSurname FROM Reservation_Table WHERE PNR = ?", array($pnr));
    $existRow = sqlsrv_fetch_array($checkExist, SQLSRV_FETCH_ASSOC);
    
    if ($existRow) {
        $dbSurname = strtoupper(trim($existRow['ContactSurname']));
        $reqSurname = strtoupper(trim($surname));
        
        if (!empty($reqSurname) && $reqSurname !== $dbSurname) {
            echo json_encode(['status' => 'error', 'message' => 'Güvenlik İhlali: PNR kodu sistemde mevcut ancak girilen soyadı bilet sahibiyle eşleşmiyor!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Bu bileti görüntüleme yetkiniz yok (Farklı bir hesaba ait).']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Bu PNR koduna ait hiçbir kayıt bulunamadı.']);
    }
    exit;
}

// 2. Rezervasyon Detaylarını Çek
$sqlRez = "
    SELECT TOP 1 
        R.ReservationID, R.ReservationDateTime, R.TotalCost, R.PNR,
        F.FlightNo, F.DepartureTime, F.ArrivalTime, F.Status as FlightStatus,
        D.IATA as DepCode, D.City as DepCity, D.AirportName as DepName,
        A.IATA as ArrCode, A.City as ArrCity, A.AirportName as ArrName
    FROM Reservation_Table R
    INNER JOIN Flights_Table F ON R.FlightID = F.FlightID
    INNER JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
    INNER JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
    WHERE R.PNR = ?
";
$stmtRez = sqlsrv_query($conn, $sqlRez, array($pnr));
$rezInfo = sqlsrv_fetch_array($stmtRez, SQLSRV_FETCH_ASSOC);

if (!$rezInfo) {
    echo json_encode(['status' => 'error', 'message' => 'Uçuş bilgileri bulunamadı.']);
    exit;
}

// JSON formatında tarihlerin bozulmaması için string'e çeviriyoruz
$rezInfo['DepartureTimeStr'] = $rezInfo['DepartureTime']->format('Y-m-d H:i:s');
$rezInfo['ArrivalTimeStr'] = $rezInfo['ArrivalTime']->format('Y-m-d H:i:s');

// 3. Bilet ve Yolcu Bilgilerini Çek
$sqlPass = "SELECT * FROM Tickets_Table WHERE ReservationID = ?";
$stmtPass = sqlsrv_query($conn, $sqlPass, array($rezInfo['ReservationID']));

$passengers = [];
$allCheckedIn = true;
$allCancelled = true;
$hasActiveTickets = false;

while($row = sqlsrv_fetch_array($stmtPass, SQLSRV_FETCH_ASSOC)){
    // DateTime nesnelerini string'e çevir (JSON Decode hatasını önlemek için)
    if (isset($row['CheckInTime']) && $row['CheckInTime'] instanceof DateTime) {
        $row['CheckInTime'] = $row['CheckInTime']->format('Y-m-d H:i:s');
    }
    $passengers[] = $row;
    
    $isCancelled = (isset($row['TicketStatus']) && $row['TicketStatus'] === 'Cancelled');
    if (!$isCancelled) {
        $allCancelled = false;
        $hasActiveTickets = true;
        if($row['CheckInStatus'] == 0) {
            $allCheckedIn = false;
        }
    }
}

// İptal Mantığı ve Gecikme Durumu
$now = new DateTime();
$canCancel = ($rezInfo['DepartureTime'] > $now);
$isDelayed = (($rezInfo['FlightStatus'] ?? 'Planned') === 'Delayed');

// 4. Veriyi JSON Olarak Gönder (Acentanın beklediği paket)
echo json_encode([
    'status' => 'success',
    'rezInfo' => $rezInfo,
    'passengers' => $passengers,
    'allCheckedIn' => $allCheckedIn,
    'allCancelled' => $allCancelled,
    'hasActiveTickets' => $hasActiveTickets,
    'canCancel' => $canCancel,
    'isDelayed' => $isDelayed
]);
?>