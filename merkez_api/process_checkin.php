<?php
// Merkez Sunucu - process_checkin.php
include 'connecting.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['status' => 'error', 'message' => 'Veri alınamadı.']); exit; }

$seats = $input['seats'] ?? [];
$meals = $input['meals'] ?? [];
$baggages = $input['baggages'] ?? [];
$companions = $input['companions'] ?? [];
$passengers = $input['passengers'] ?? [];

$successCount = 0; $errorCount = 0; $lastMessage = "";

foreach ($seats as $ticketID => $seatNo) {
    
    if (!empty($companions[$ticketID])) {
        $compID = (int)$companions[$ticketID];
        $stmt = sqlsrv_query($conn, "SELECT SeatNo FROM Tickets_Table WHERE TicketID = ?", [$compID]);
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { $seatNo = $row['SeatNo']; }
    }

    $mealID = !empty($meals[$ticketID]) ? (int)$meals[$ticketID] : null;
    $baggageID = !empty($baggages[$ticketID]) ? (int)$baggages[$ticketID] : null;

    
    foreach ($passengers as $p) {
        if ($p['TicketID'] == $ticketID && strtolower($p['AgeType']) === 'baby') {
            $mealID = null; $baggageID = null; break;
        }
    }

    $paramTicketID = (int)$ticketID;
    $paramSeatNo = (!empty($seatNo) && $seatNo !== 'COMPANION') ? $seatNo : null;

    
    $sqlProc = "{CALL UP_CheckIn(?, ?, ?, ?)}";
    $paramsProc = array($paramTicketID, $paramSeatNo, $mealID, $baggageID);
    $stmtProc = sqlsrv_query($conn, $sqlProc, $paramsProc);

    if ($stmtProc === false) {
        $errorCount++;
        $err = sqlsrv_errors();
        $lastMessage = $err[0]['message'] ?? 'DB Hatası';
    } else {
        $row = sqlsrv_fetch_array($stmtProc, SQLSRV_FETCH_ASSOC);
        if ($row && isset($row['ResultCode']) && $row['ResultCode'] == 1) {
            $successCount++;
        } else {
            $errorCount++;
            $lastMessage = $row['Message'] ?? 'Check-in başarısız.';
        }
    }
}

if ($errorCount === 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $lastMessage]);
}
?>