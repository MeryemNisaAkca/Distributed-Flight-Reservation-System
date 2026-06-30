<?php
//This page is activated if the user selects extra baggage or special meals beyond their standard allowance during check-in.



// --- KURAL 1: YAPI İZOLASYONU ---
if (file_exists('agency_config.php')) {
    include 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
// ---------------------------------
session_start();
include 'connecting.php';

// --- KURAL 2: API İSTEKLERİNİN AYRIŞTIRILMASI VE JSON YAYINI ---
// Bu fonksiyonu dosyanın üst kısımlarına ekle
function send_to_center_log($pnr, $ticketID, $seatNo, $status) {
    $data = [
        "pnr" => $pnr,
        "ticket_id" => $ticketID,
        "seat" => $seatNo,
        "status" => $status,
        "agency" => AGENCY_CODE, // Acente kimliğini ekliyoruz
        "timestamp" => date("Y-m-d H:i:s")
    ];
    
    $ch = curl_init(MERKEZ_URL . '/api/checkin_log');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Kullanıcıyı bekletmemek için kısa timeout
    curl_exec($ch);
    curl_close($ch);
}

if (!isset($_SESSION['pending_checkin'])) {
    // Eğer bekleyen bir işlem yoksa ve sadece test için girildiyse güvenli çıkış yap
    $fallbackPnr = $_REQUEST['pnr'] ?? '';
    echo "<script>alert('No pending payments found.'); window.location.href='index.php';</script>";
    exit();
}

$pendingData = $_SESSION['pending_checkin'];
$totalAmount = $pendingData['total_amount'];
$pnr = $pendingData['pnr'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Simulate Payment Success
    $seats = $pendingData['seats'];
    $meals = $pendingData['meals'] ?? [];
    $baggages = $pendingData['baggages'] ?? [];
    $companions = $pendingData['companions'] ?? [];
    
    // Fetch passengers to check for babies
    $pnr = $pendingData['pnr'];
    $sqlPassengers = "
        SELECT T.TicketID, T.PassengerName, T.PassengerSurname, T.AgeType 
        FROM Tickets_Table T
        INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
        WHERE R.PNR = ? AND T.CheckInStatus = 0
    ";
    $stmtPassengers = sqlsrv_query($conn, $sqlPassengers, array($pnr));
    $passengers = [];
    while($p = sqlsrv_fetch_array($stmtPassengers, SQLSRV_FETCH_ASSOC)) { 
        $passengers[$p['TicketID']] = $p; 
    }
    
    $successCount = 0;
    // execute check-in for each passenger
    foreach ($seats as $ticketID => $seatNo) {
        // For babies: use companion's seat number
        if (isset($companions[$ticketID]) && !empty($companions[$ticketID])) {
            $companionTicketID = (int)$companions[$ticketID];
            // Get companion's seat number
            $sqlCompanionSeat = "SELECT SeatNo FROM Tickets_Table WHERE TicketID = ?";
            $stmtCompanionSeat = sqlsrv_query($conn, $sqlCompanionSeat, array($companionTicketID));
            if ($stmtCompanionSeat) {
                $companionRow = sqlsrv_fetch_array($stmtCompanionSeat, SQLSRV_FETCH_ASSOC);
                if ($companionRow && !empty($companionRow['SeatNo'])) {
                    $seatNo = $companionRow['SeatNo']; // Use companion's seat
                }
            }
        }
        
        $mealID = isset($meals[$ticketID]) ? $meals[$ticketID] : null;
        $baggageID = isset($baggages[$ticketID]) ? $baggages[$ticketID] : null;
        
        // For babies: no meal and no baggage
        if (isset($passengers[$ticketID]) && strtolower($passengers[$ticketID]['AgeType']) === 'baby') {
            $mealID = null;
            $baggageID = null;
        }

        $sqlProc = "{CALL UP_CheckIn(?, ?, ?, ?)}";
        $paramsProc = array($ticketID, $seatNo, $mealID, $baggageID);
        
        $stmtProc = sqlsrv_query($conn, $sqlProc, $paramsProc);
        if ($stmtProc) {
            // Prosedür sonuçlarını oku (ResultCode vs var mı kontrolü)
            $row = sqlsrv_fetch_array($stmtProc, SQLSRV_FETCH_ASSOC);
            if ($row) {
                $successCount++;
                // İşlem başarılı, merkeze JSON log fırlat!
                send_to_center_log($pnr, $ticketID, $seatNo, "SUCCESS");
            }
        }
    }
    // CLEANUP: Remove temporary session data
    unset($_SESSION['pending_checkin']);
    
    echo "<script>alert('Payment Successful! Check-in Completed for $successCount passengers.'); window.location.href='index.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Extra Services Payment - <?php echo htmlspecialchars(AGENCY_CODE); ?></title>
    <link rel="stylesheet" href="css/index_style.css">
    <link rel="stylesheet" href="css/payment_extra_style.css">
</head>
<body>
    <div class="payment-box">
        <h2>Extra Services Payment</h2>
        <p style="color: #c8102e; font-weight: bold; margin-top: -10px; margin-bottom: 15px;">
            <i class="fas fa-shield-alt"></i> Secure Payment by <?php echo htmlspecialchars(AGENCY_CODE); ?>
        </p>
        <p>Payment for selected Meals and Extra Baggage.</p>
        
        <div class="amount"><?php echo number_format($totalAmount, 2); ?> ₺</div>

        <form method="POST">
            <div class="input-group">
                <label>Card Number</label>
                <input type="text" placeholder="XXXX XXXX XXXX XXXX" required maxlength="19">
            </div>
            <div class="input-group">
                <label>Expiry Date</label>
                <input type="text" placeholder="MM/YY" required maxlength="5">
            </div>
            <div class="input-group">
                <label>CVV</label>
                <input type="text" placeholder="123" required maxlength="3">
            </div>
            
            <button type="submit" class="btn-pay">PAY & COMPLETE CHECK-IN</button>
        </form>
        <br>
        <a href="checkin.php?pnr=<?php echo htmlspecialchars($pnr); ?>" style="color: #666;">Cancel</a>
    </div>
</body>
</html>