<?php
// Merkez Sunucu - process_cancel.php
include 'connecting.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek formatı.']);
    exit;
}

$pnr = $input['pnr'] ?? '';
$ticketID = $input['ticket_id'] ?? '';
$userID = $input['user_id'] ?? null;
$surname = $input['surname'] ?? '';
$babyAction = $input['baby_action'] ?? '';
$newCompanionID = $input['new_companion_id'] ?? '';

if (empty($pnr) || empty($ticketID)) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik parametre (PNR veya TicketID).']);
    exit;
}

// 1. GÜVENLİK (Yetkilendirme) KONTROLÜ
$isAuthorized = false;
$reservationID = null;

if (!empty($surname)) {
    $surnameUpper = strtoupper(trim($surname));
    $sqlAuth = "{CALL UP_GuestTicketInformation(?, ?)}";
    $stmtAuth = sqlsrv_query($conn, $sqlAuth, array($pnr, $surnameUpper));
    if ($stmtAuth !== false && sqlsrv_fetch_array($stmtAuth, SQLSRV_FETCH_ASSOC)) {
        $isAuthorized = true;
    }
} else if (!empty($userID)) {
    $sqlCheck = "SELECT ReservationID FROM Reservation_Table WHERE PNR = ? AND UserID = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($pnr, $userID));
    if ($stmtCheck !== false && sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    echo json_encode(['status' => 'error', 'message' => 'Bu iptal işlemini yapmak için yetkiniz yok.']);
    exit;
}

// Reservation ID'yi bul
$stmtRez = sqlsrv_query($conn, "SELECT ReservationID FROM Reservation_Table WHERE PNR = ?", array($pnr));
$rezRow = sqlsrv_fetch_array($stmtRez, SQLSRV_FETCH_ASSOC);
$reservationID = $rezRow['ReservationID'];

// 2. İPTAL İŞLEMİ (TRANSACTION BAŞLAT)
sqlsrv_begin_transaction($conn);

try {
    if ($ticketID === 'ALL') {
        
        sqlsrv_query($conn, "UPDATE Tickets_Table SET TicketStatus = 'Cancelled', CheckInStatus = 0, SeatNo = NULL WHERE ReservationID = ?", array($reservationID));
    } else {
        
        sqlsrv_query($conn, "UPDATE Tickets_Table SET TicketStatus = 'Cancelled', CheckInStatus = 0, SeatNo = NULL WHERE TicketID = ? AND ReservationID = ?", array($ticketID, $reservationID));
        
        
        if ($babyAction === 'cancel_baby') {
            // İlgili koltuktaki bebekleri de iptal et
            $sqlCancelBaby = "UPDATE Tickets_Table SET TicketStatus = 'Cancelled' WHERE ReservationID = ? AND LOWER(AgeType) = 'baby' AND SeatNo = (SELECT SeatNo FROM Tickets_Table WHERE TicketID = ?)";
            sqlsrv_query($conn, $sqlCancelBaby, array($reservationID, $ticketID));
            
        } elseif ($babyAction === 'change_companion' && !empty($newCompanionID)) {
            
            $newComp = sqlsrv_fetch_array(sqlsrv_query($conn, "SELECT SeatNo FROM Tickets_Table WHERE TicketID = ?", array($newCompanionID)));
            if ($newComp && !empty($newComp['SeatNo'])) {
                $sqlMoveBaby = "UPDATE Tickets_Table SET SeatNo = ? WHERE ReservationID = ? AND LOWER(AgeType) = 'baby' AND SeatNo = (SELECT SeatNo FROM Tickets_Table WHERE TicketID = ?)";
                sqlsrv_query($conn, $sqlMoveBaby, array($newComp['SeatNo'], $reservationID, $ticketID));
            }
        }
    }
    
    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => 'Veritabanı güncellenirken hata oluştu.']);
}
?>