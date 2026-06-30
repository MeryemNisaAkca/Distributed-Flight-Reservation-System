<?php
// Merkez Sunucu - api_cancel_ticket.php
include 'connecting.php';
header('Content-Type: application/json');


$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek.']);
    exit;
}

$pnr = $input['pnr'] ?? '';
$ticketID = $input['ticket_id'] ?? '';
$agency = $input['agency'] ?? 'BİLİNMİYOR';

if (empty($pnr) || empty($ticketID)) {
    echo json_encode(['status' => 'error', 'message' => 'PNR veya Bilet ID eksik.']);
    exit;
}


$sqlCheck = "
    SELECT T.TicketID 
    FROM Tickets_Table T
    INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
    WHERE R.PNR = ? AND T.TicketID = ?
";
$stmtCheck = sqlsrv_query($conn, $sqlCheck, array($pnr, $ticketID));

if ($stmtCheck === false || !sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
    echo json_encode(['status' => 'error', 'message' => 'Bilet bu rezervasyonla eşleşmedi veya bulunamadı.']);
    exit;
}


$sqlCancel = "UPDATE Tickets_Table SET TicketStatus = 'Cancelled', CheckInStatus = 0, SeatNo = NULL WHERE TicketID = ?";
$stmtCancel = sqlsrv_query($conn, $sqlCancel, array($ticketID));

if ($stmtCancel === false) {
    $errors = sqlsrv_errors();
    $errorMsg = $errors[0]['message'] ?? 'Bilinmeyen SQL Hatası';
    echo json_encode(['status' => 'error', 'message' => 'İptal işlemi başarısız: ' . $errorMsg]);
} else {
    echo json_encode(['status' => 'success', 'message' => 'Bilet başarıyla iptal edildi.']);
}
?>