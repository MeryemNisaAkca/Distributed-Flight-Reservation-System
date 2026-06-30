<?php

include 'connecting.php';
header('Content-Type: application/json');

// --- HATA FONKSİYONU ---
function getSqlErrors() {
    $errors = sqlsrv_errors();
    return $errors ? $errors[0]['message'] : "Bilinmeyen SQL hatası";
}

$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz veri formatı.']);
    exit;
}

if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode(['status' => 'error', 'message' => 'Transaction başlatılamadı.']);
    exit;
}

try {
    // --- 1. ACENTA LİSANS VE YETKİ KONTROLÜ  ---
    $agencyCode = $data['acente_id'] ?? '';
    $companyID = null;

    if (empty($agencyCode)) {
        throw new Exception("Güvenlik İhlali: Acenta kodu (API Key) bulunamadı. İşlem reddedildi.");
    }

    $sqlCheckCompany = "SELECT CompanyID, IsActive FROM Companies_Table WHERE AgencyCode = ?";
    $stmtCheck = sqlsrv_query($conn, $sqlCheckCompany, array($agencyCode));

    if ($stmtCheck === false) {
        throw new Exception("Acenta doğrulama hatası: " . getSqlErrors());
    }

    $companyRow = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);

    if (!$companyRow) {
        throw new Exception("Kayıtdışı Acenta: Sistemde böyle bir acenta bulunamadı.");
    }

    if ($companyRow['IsActive'] == 0) {
        throw new Exception("Yetki İptali: Acenta yetkisi merkez tarafından askıya alınmıştır (Suspended). Bilet satışı yapılamaz.");
    }

    // Yetki onaylandı, CompanyID'yi hafızaya al
    $companyID = $companyRow['CompanyID'];
   


    $contactInfo = $data['contact_info'] ?? [];
    $flightDetails = $data['flight_details'] ?? [];
    $isRoundTrip = ($data['trip_type'] === 'roundtrip');
    $userID = !empty($contactInfo['user_id']) ? $contactInfo['user_id'] : null;

    // --- PNR ÜRETİMİ ---
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $outboundPNR = substr(str_shuffle($chars), 0, 6);
    $returnPNR = $isRoundTrip ? substr(str_shuffle($chars), 0, 6) : $outboundPNR;

    if (empty($outboundPNR)) throw new Exception("PNR kodu oluşturulamadı.");

    $flightIDs = $isRoundTrip ? [$flightDetails['outbound_flight_id'], $flightDetails['return_flight_id']] : [$flightDetails['flight_id']];
    $flightPrices = $isRoundTrip ? [$flightDetails['outbound_price'], $flightDetails['return_price']] : [$flightDetails['total_price']];
    
    $reservationIDs = [];

    // --- 2. REZERVASYON KAYITLARI  ---
    foreach ($flightIDs as $index => $fid) {
        $currentPNR = ($isRoundTrip && $index == 1) ? $returnPNR : $outboundPNR;
        
        // Sütunlara CompanyID eklendi
        $sqlRez = "INSERT INTO Reservation_Table (UserID, FlightID, ReservationDateTime, TotalCost, PaymentStatus, PNR, ContactEmail, ContactName, ContactSurname, CompanyID) 
                   OUTPUT INSERTED.ReservationID
                   VALUES (?, ?, GETDATE(), ?, 'Completed', ?, ?, ?, ?, ?)";
        
        $paramsRez = array($userID, $fid, $flightPrices[$index], $currentPNR, $contactInfo['email'], $contactInfo['name'], $contactInfo['surname'], $companyID);
        
        $stmtRez = sqlsrv_query($conn, $sqlRez, $paramsRez);
        if ($stmtRez === false) throw new Exception("Rezervasyon INSERT Hatası: " . getSqlErrors());

        $rowIdentity = sqlsrv_fetch_array($stmtRez, SQLSRV_FETCH_ASSOC);
        
        if (!$rowIdentity || empty($rowIdentity['ReservationID'])) {
            throw new Exception("IDENTITY değeri alınamadı. OUTPUT INSERTED başarısız.");
        }
        $reservationIDs[] = $rowIdentity['ReservationID'];
    }

    // --- 3. BİLET KAYITLARI ---
    $passengerCount = count($data['passengers']);
    if ($passengerCount == 0) throw new Exception("Yolcu bilgisi bulunamadı.");

    foreach ($data['passengers'] as $p) {
        foreach ($reservationIDs as $idx => $rID) {
            
            $calculatedPrice = $flightPrices[$idx] / $passengerCount;
            $ticketPrice = ($calculatedPrice > 0) ? $calculatedPrice : 1.00;

            $sqlTicket = "INSERT INTO Tickets_Table (ReservationID, FlightID, CabinType, AgeType, TicketPrice, CheckInStatus, PassengerName, PassengerSurname) 
                          VALUES (?, ?, ?, ?, ?, 0, ?, ?)";
            
            $paramsTicket = array($rID, $flightIDs[$idx], $flightDetails['cabin_class'], 'Adult', $ticketPrice, strtoupper($p['name']), strtoupper($p['surname']));
            
            $stmtTicket = sqlsrv_query($conn, $sqlTicket, $paramsTicket);
            if ($stmtTicket === false) throw new Exception("Bilet INSERT Hatası: " . getSqlErrors());
        }
    }

    // --- 4. PUAN GÜNCELLEME (Loyalty Points) ---
    $loyaltyPointsUsed = (int)($contactInfo['loyalty_points_used'] ?? 0);
    if ($userID && $loyaltyPointsUsed > 0) {
        $sqlUpdatePoints = "UPDATE Users_Table SET LoyaltyPoint = LoyaltyPoint - ? WHERE UserID = ?";
        $stmtPoints = sqlsrv_query($conn, $sqlUpdatePoints, array($loyaltyPointsUsed, $userID));
        if ($stmtPoints === false) throw new Exception("Puan Güncelleme Hatası: " . getSqlErrors());
    }

    $ticketCount = count($data['passengers'] ?? []); // Satın alınan bilet sayısı
    if (!empty($userID) && $ticketCount > 0) {
        $kazanilanPuan = $ticketCount * 200;
        
        $sqlEarnPoints = "UPDATE Users_Table SET LoyaltyPoint = LoyaltyPoint + ? WHERE UserID = ?";
        $stmtEarn = sqlsrv_query($conn, $sqlEarnPoints, array($kazanilanPuan, $userID));
        
        if ($stmtEarn === false) {
            throw new Exception("Puan Kazanma Hatası: " . getSqlErrors());
        }
    }

    sqlsrv_commit($conn);
    echo json_encode(['status' => 'success', 'pnr' => $outboundPNR]);

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>