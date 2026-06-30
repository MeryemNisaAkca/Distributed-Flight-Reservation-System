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

// ==========================================
// 1. TUR: ÖNCE SADECE YETİŞKİNLERİ CHECK-IN YAP
// ==========================================
foreach ($seats as $ticketID => $seatNo) {
    $isBaby = false;
    foreach ($passengers as $p) {
        if ($p['TicketID'] == $ticketID && strtolower($p['AgeType']) === 'baby') {
            $isBaby = true; break;
        }
    }
    
    // Eğer bebekse, bu turda atla (2. tura bırak)
    if ($isBaby) continue; 

    $mealID = !empty($meals[$ticketID]) ? (int)$meals[$ticketID] : null;
    $baggageID = !empty($baggages[$ticketID]) ? (int)$baggages[$ticketID] : null;

    $paramTicketID = (int)$ticketID;
    $paramSeatNo = (!empty($seatNo) && $seatNo !== 'COMPANION') ? $seatNo : null;

    // Yetişkinler Stored Procedure (Kural Motoru) ile kaydedilir
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

// ==========================================
// 2. TUR: ŞİMDİ SADECE BEBEKLERİ İŞLE
// ==========================================
foreach ($seats as $ticketID => $seatNo) {
    $isBaby = false;
    foreach ($passengers as $p) {
        if ($p['TicketID'] == $ticketID && strtolower($p['AgeType']) === 'baby') {
            $isBaby = true; break;
        }
    }
    
    // Eğer yetişkinse atla (Zaten 1. turda hallettik)
    if (!$isBaby) continue; 

    $compID = !empty($companions[$ticketID]) ? (int)$companions[$ticketID] : null;
    $babySeat = null;

    // Ebeveynin 1. Turda kesinleşen koltuğunu bul
    if ($compID) {
        $stmt = sqlsrv_query($conn, "SELECT SeatNo FROM Tickets_Table WHERE TicketID = ?", [$compID]);
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) { 
            $babySeat = $row['SeatNo']; 
        }
    }

    // BEBEK HACK: Stored Procedure'ün "Dolu Koltuk" uyarısını atlatmak için,
    // Bebeği doğrudan SQL UPDATE ile ebeveynin kucağına kaydediyoruz!
    $sqlBaby = "UPDATE Tickets_Table SET CheckInStatus = 1, SeatNo = ? WHERE TicketID = ?";
    $stmtBaby = sqlsrv_query($conn, $sqlBaby, array($babySeat, $ticketID));

    if ($stmtBaby) {
        $successCount++;
    } else {
        $errorCount++;
        $err = sqlsrv_errors();
        $lastMessage = $err[0]['message'] ?? 'Bebek check-in hatası.';
    }
}

if ($errorCount === 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $lastMessage]);
}
?>