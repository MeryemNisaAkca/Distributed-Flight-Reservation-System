<?php
if (file_exists('agency_config.php')) {
    include 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
// ---------------------------------
session_start();
$pnr = $_GET['pnr'] ?? '';
$outboundPNR = $_GET['outbound_pnr'] ?? '';
$returnPNR = $_GET['return_pnr'] ?? '';
$surname = $_GET['surname'] ?? '';
$tripType = $_GET['trip_type'] ?? 'oneway';
$isRoundTrip = ($tripType === 'roundtrip' && !empty($outboundPNR) && !empty($returnPNR));

// 1. MERKEZ API'YE REZERVASYON DETAYLARI İSTEĞİ AT (cURL)
// --- KURAL 2: API İSTEKLERİNİN AYRIŞTIRILMASI ---
// Parametreleri API'ye uygun şekilde hazırlıyoruz ve 'agency' bilgisini ekliyoruz.
$query_params = http_build_query([
    'pnr' => $pnr,
    'outbound_pnr' => $outboundPNR,
    'return_pnr' => $returnPNR,
    'trip_type' => $tripType,
    'agency' => AGENCY_CODE // Merkezin isteği hangi acentadan aldığını bilmesi için
]);

// Hardcoded localhost yerine dinamik MERKEZ_URL kullanıyoruz
$merkez_api_url = MERKEZ_URL . "/api/get_booking_details.php?" . $query_params;

$ch = curl_init($merkez_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 saniye bekle

$merkez_cevabi = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if(curl_errno($ch)){
    $hata_mesaji = curl_error($ch);
    curl_close($ch);
    
    // MÜHENDİSLİK TESTİ: Burada "Connection refused" görmemiz bekleniyor!
    die("<div style='padding:20px; text-align:center; background:#f8d7da; border:2px solid #dc3545; border-radius:8px; margin:20px auto; max-width:600px; font-family:sans-serif;'>
            <h3 style='color:#721c24;'><i class='fas fa-network-wired'></i> Ağ İletişim Hatası</h3>
            <p style='color:#666;'><strong>" . htmlspecialchars(AGENCY_CODE) . "</strong>, Merkez Sunucuya bağlanarak rezervasyon özetini alamadı.</p>
            <p><strong>Hata Detayı:</strong> " . $hata_mesaji . "</p>
            <p style='font-size:12px; color:gray;'>Not: Bu hata normaldir. Bilet başarıyla API'ye iletilmiş olsa bile, onay sayfasını çeken Merkez kapalı olduğu için bu hatayı alıyorsunuz.</p>
            <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Ana Sayfaya Dön</a>
        </div>");
}
curl_close($ch);

// 2. MERKEZDEN GELEN JSON CEVABINI İŞLE
$api_yaniti = json_decode($merkez_cevabi, true);
$tickets = [];

if ($http_status == 200 && isset($api_yaniti['status']) && $api_yaniti['status'] == 'success') {
    $tickets = $api_yaniti['tickets']; // Merkez bize bilet dizisini gönderdi
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Confirmed - <?php echo htmlspecialchars(AGENCY_CODE); ?></title>
    <link rel="stylesheet" href="css/index_style.css">
    <link rel="stylesheet" href="css/booking_success_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <div class="navbar">
         <div class="navbar-left">
             <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> <?php echo htmlspecialchars(AGENCY_CODE); ?></a>
        </div>
    </div>

    <div class="success-container">
        <div class="icon-box"><i class="fas fa-check-circle"></i></div>
        
        <h1 style="color: #333; margin-bottom: 10px;">Booking Confirmed!</h1>
        <p style="color: #666; font-size: 16px;">Your tickets have been successfully created. Below <?php echo $isRoundTrip ? 'are your Reservation Codes (PNR)' : 'is your Reservation Code (PNR)'; ?>.</p>

        <?php if ($isRoundTrip): ?>
            <div class="pnr-box" style="margin-bottom: 15px;">
                <span style="display: block; font-size: 12px; color: #888; margin-bottom: 5px;">OUTBOUND FLIGHT PNR</span>
                <span class="pnr-code"><?php echo htmlspecialchars($outboundPNR); ?></span>
            </div>
            <div class="pnr-box">
                <span style="display: block; font-size: 12px; color: #888; margin-bottom: 5px;">RETURN FLIGHT PNR</span>
                <span class="pnr-code"><?php echo htmlspecialchars($_GET['pnr'] ?? 'HATA'); ?></span>
            </div>
        <?php else: ?>
        <div class="pnr-box">
            <span style="display: block; font-size: 12px; color: #888; margin-bottom: 5px;">YOUR PNR CODE</span>
            <span class="pnr-code"><?php echo htmlspecialchars($pnr); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($tickets)): ?>
        <div class="tickets-list">
            <h3 style="text-align: center; color: #333; margin-bottom: 20px; font-size: 18px;">
                <i class="fas fa-users"></i> Passengers
            </h3>
            <?php 
            $currentFlightPNR = '';
            foreach ($tickets as $ticket): 
                // For round trip: Group tickets by flight
                if ($isRoundTrip && isset($ticket['PNR']) && $ticket['PNR'] !== $currentFlightPNR):
                    $currentFlightPNR = $ticket['PNR'];
                    $isOutbound = ($currentFlightPNR === $outboundPNR);
            ?>
                <div style="background: #e3f2fd; padding: 10px; margin: 15px 0 10px 0; border-radius: 6px; border-left: 4px solid #c8102e;">
                    <strong style="color: #1976d2;">
                        <i class="fas fa-plane-<?php echo $isOutbound ? 'departure' : 'arrival'; ?>"></i> 
                        <?php echo $isOutbound ? 'Outbound' : 'Return'; ?> Flight
                        <?php if (isset($ticket['FlightNo'])): ?>
                            - <?php echo htmlspecialchars($ticket['FlightNo']); ?>
                            (<?php echo htmlspecialchars($ticket['DepCity'] ?? ''); ?> → <?php echo htmlspecialchars($ticket['ArrCity'] ?? ''); ?>)
                        <?php endif; ?>
                    </strong>
                </div>
            <?php endif; ?>
                <div class="ticket-item">
                    <div class="ticket-info">
                        <div class="ticket-name">
                            <?php echo htmlspecialchars($ticket['PassengerName'] . ' ' . $ticket['PassengerSurname']); ?>
                        </div>
                        <div class="ticket-age">
                            <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($ticket['AgeType']); ?>
                        </div>
                    </div>
                    <?php if ($isRoundTrip && isset($ticket['PNR'])): ?>
                        <div class="ticket-pnr" style="font-size: 11px; padding: 4px 8px;">
                            PNR: <?php echo htmlspecialchars($ticket['PNR']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p style="margin-bottom: 30px; font-size: 14px; color: #c8102e;">
            <i class="fas fa-exclamation-circle"></i> Please save <?php echo $isRoundTrip ? 'both PNR codes' : 'this PNR code'; ?> for Check-in and Flight Management.
            <?php if ($isRoundTrip): ?>
                <br><small style="color: #666;">Each flight requires a separate check-in using its respective PNR code.</small>
            <?php endif; ?>
        </p>

        <div>
            <a href="index.php" class="btn-home"><i class="fas fa-home"></i> Home</a>
            
            <?php if ($isRoundTrip): ?>
                <a href="ticket_management.php" class="btn-manage">Manage / Check-in <i class="fas fa-arrow-right"></i></a>
            <?php else: ?>
            <form action="ticket_details.php" method="POST" style="display: inline;">
                <input type="hidden" name="pnr" value="<?php echo htmlspecialchars($pnr); ?>">
                <input type="hidden" name="surname" value="<?php echo htmlspecialchars($surname); ?>">
                <button type="submit" class="btn-manage" style="border: none; cursor: pointer;">Manage / Check-in <i class="fas fa-arrow-right"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>