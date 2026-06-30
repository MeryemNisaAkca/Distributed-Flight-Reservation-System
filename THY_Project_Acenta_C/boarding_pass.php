<?php
// This code runs on the Agency side. It does not connect to the local database.
// It queries the Central API for the PNR code and generates a boarding pass based on the response.



// 1. ACENTAYA ÖZEL AYARLARI ÇEK
// Bu dosya her acentada farklı (Acenta A, B, C) olduğu için kod evrensel çalışır.
if (file_exists('agency_config.php')) {
    include 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
session_start();
// PNR kontrolü
$pnr = $_REQUEST['pnr'] ?? '';
if (empty($pnr)) {
    echo "Error: PNR code not found.";
    exit();
}

// 2. MERKEZ API'YE BİNİŞ KARTI İSTEĞİ AT (cURL)
// PNR kodunun yanına AGENCY_CODE parametresini de ekliyoruz. Böylece merkez kimin istediğini biliyor.
$merkez_api_url = MERKEZ_URL . "/get_boarding_pass.php?pnr=" . urlencode($pnr) . "&agency=" . urlencode(AGENCY_CODE);

$ch = curl_init($merkez_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 saniye bekle, Merkez yoksa hata ver

$merkez_cevabi = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if(curl_errno($ch)){
    $hata_mesaji = curl_error($ch);
    curl_close($ch);
    
    // MÜHENDİSLİK TESTİ: Burada "Connection refused" görmemiz bekleniyor!
    die("<div style='padding:20px; text-align:center; background:#f8d7da; border:2px solid #dc3545; border-radius:8px; margin:20px auto; max-width:600px; font-family:sans-serif;'>
            <h3 style='color:#721c24;'><i class='fas fa-network-wired'></i> Ağ İletişim Hatası</h3>
            <p style='color:#666;'><strong>" . htmlspecialchars(AGENCY_CODE) . "</strong>, Merkez Sunucuya bağlanarak biniş kartı verilerini alamadı.</p>
            <p><strong>Hata Detayı:</strong> " . $hata_mesaji . "</p>
            <p style='font-size:12px; color:gray;'>Not: Bu hata normaldir. Merkez Sunucu henüz aktif değilse bu uyarı görünür.</p>
            <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Geri Dön</a>
        </div>");
}
curl_close($ch);

// 3. MERKEZDEN GELEN JSON CEVABINI İŞLE
$api_yaniti = json_decode($merkez_cevabi, true);
$passes = [];

if ($http_status == 200 && isset($api_yaniti['status']) && $api_yaniti['status'] == 'success') {
    $passes = $api_yaniti['passes']; 
} else {
    $api_hatasi = $api_yaniti['message'] ?? 'An error occurred while loading boarding pass.';
    die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px; font-family:sans-serif;'>
            <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Boarding Pass Error</h3>
            <p style='color:#666;'>" . htmlspecialchars($api_hatasi) . "</p>
            <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Return Home</a>
        </div>");
}

// Güvenlik kontrolü
if (count($passes) == 0) {
    echo "<h3 style='text-align:center; font-family:sans-serif;'>No passengers found with check-in completed for this PNR, or PNR is invalid.</h3>";
    echo "<div style='text-align:center;'><a href='index.php'>Return Home</a></div>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Boarding Pass - <?php echo htmlspecialchars($pnr); ?></title>
    <link rel="stylesheet" href="css/boarding_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="container">
        
        <div class="page-title no-print">
            <h2><i class="fas fa-ticket-alt"></i> Your Boarding Passes</h2>
            <p>Please save or print your boarding passes before your flight.</p>
        </div>

        <?php foreach ($passes as $pass): 
            // JSON'dan gelen tarih artık bir obje değil, String ("Y-m-d H:i:s") formatındadır.
            // Bu yüzden önce PHP'nin DateTime objesine dönüştürüyoruz.
            $dateObj = new DateTime($pass['DepartureTime']); 
            $dateFormatted = $dateObj->format('d M Y'); // 16 Dec 2025
            $timeFormatted = $dateObj->format('H:i');   // 14:30
            
            // Simulation: Random Gate Number 
            $gate = chr(rand(65, 68)) . rand(1, 20); 
            
            // QR Code Generator 
            $qrData = "PNR:$pnr|Seat:{$pass['SeatNo']}|Name:{$pass['PassengerName']}";
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);
        ?>
        
        <div class="boarding-pass">
            
            <div class="pass-left">
                <div class="pass-header">
                    <div class="airline-name"><i class="fas fa-plane"></i> THY PROJECT</div>
                    <div class="class-type"><?php echo htmlspecialchars($pass['CabinType']); ?> Class</div>
                </div>

                <div class="route">
                    <b><?php echo htmlspecialchars($pass['DepCode']); ?></b> <i class="fas fa-long-arrow-alt-right" style="font-size:20px; color:#ccc;"></i> <b><?php echo htmlspecialchars($pass['ArrCode']); ?></b>
                </div>

                <div class="flight-info">
                    <div class="info-group">
                        <label>Passenger</label>
                        <span><?php echo htmlspecialchars(strtoupper($pass['PassengerName'] . " " . $pass['PassengerSurname'])); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Flight</label>
                        <span><?php echo htmlspecialchars($pass['FlightNo']); ?></span>
                    </div>
                    <div class="info-group">
                        <label>Date</label>
                        <span><?php echo $dateFormatted; ?></span>
                    </div>
                    <div class="info-group">
                        <label>Gate</label>
                        <span style="color:#c8102e;"><?php echo $gate; ?></span>
                    </div>
                    <div class="info-group">
                        <label>Boarding Time</label>
                        <span><?php echo $timeFormatted; ?></span>
                    </div>
                    <div class="info-group">
                        <label>Seat</label>
                        <span style="font-size:24px; color:#232b38;"><?php echo htmlspecialchars($pass['SeatNo']); ?></span>
                    </div>
                </div>
            </div>

            <div class="pass-right">
                <div style="text-align:left; width:100%; margin-bottom:10px;">
                    <label>Flight</label>
                    <span><?php echo htmlspecialchars($pass['FlightNo']); ?></span>
                </div>
                
                <label>SEAT</label>
                <div class="seat-big"><?php echo htmlspecialchars($pass['SeatNo']); ?></div>
                
                <div style="margin-bottom:15px;">
                    <label>Gate</label><br>
                    <span style="font-size:18px; color:#ffcc00;"><?php echo $gate; ?></span>
                </div>

                <img src="<?php echo $qrUrl; ?>" alt="QR Code" class="qr-code">
            </div>

        </div>
        <?php endforeach; ?>

        <div class="actions no-print">
            <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print Boarding Passes</button>
            <a href="index.php" class="btn-home">Return Home</a>
        </div>

    </div>

</body>
</html>