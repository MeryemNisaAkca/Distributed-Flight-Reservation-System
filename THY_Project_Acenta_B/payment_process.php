<?php
// En başa session_start() ekleyerek oturum verilerine erişimi açtık


// --- KURAL 3: İzolasyon ---
require_once 'agency_config.php';
session_start();
// Form gönderilmediyse ana sayfaya dön
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

// 1. FORMDAN GELEN VERİLERİ TOPLA
$tripType = $_POST['trip_type'] ?? 'oneway';
$isRoundTrip = ($tripType === 'roundtrip');

// Uçuş ID ve Fiyatları Güvenli Bir Şekilde Al
$outboundFlightID = $isRoundTrip ? ($_POST['outbound_flight_id'] ?? 0) : ($_POST['flight_id'] ?? 0);
$returnFlightID = $isRoundTrip ? ($_POST['return_flight_id'] ?? 0) : 0;
$totalPrice = (float)($_POST['total_price'] ?? 0);
$cabinClass = $_POST['class'] ?? 'Economy';
$contactEmail = $_POST['contact_email'] ?? '';

// İletişim soyadını al (başarı sayfasına taşımak için)
$contactSurname = $_POST['p_surname'][0] ?? 'Yolcu';

// 2. MERKEZE GÖNDERİLECEK VERİ PAKETİNİ HAZIRLA
// --- KURAL 1: DİNAMİK KİMLİK ---
$api_verisi = array(
    "acente_id" => AGENCY_CODE, 
    "trip_type" => $tripType,
    "flight_details" => array(
        "outbound_flight_id" => $outboundFlightID,
        "return_flight_id" => $returnFlightID,
        "flight_id" => $outboundFlightID, // Tek yön için flight_id değerini outbound ile eşitledik
        "total_price" => $totalPrice,
        "cabin_class" => $cabinClass,
        "outbound_price" => (float)($_POST['outbound_price'] ?? 0),
        "return_price" => (float)($_POST['return_price'] ?? 0)
    ),
    "contact_info" => array(
        "email" => $contactEmail,
        "name" => $_POST['p_name'][0] ?? '',
        "surname" => $contactSurname,
        "user_id" => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null, // Artık oturum açık olduğu için ID okunabilecek
        "loyalty_points_used" => (int)($_POST['loyalty_points_used'] ?? 0)
    ),
    "passengers" => array() 
);

// Yolcuları diziye ekle
$pNames = $_POST['p_name'] ?? [];
for ($i = 0; $i < count($pNames); $i++) {
    $api_verisi["passengers"][] = array(
        "AgeType" => $_POST['p_type'][$i] ?? 'Adult',
        "name" => $_POST['p_name'][$i] ?? '',
        "surname" => $_POST['p_surname'][$i] ?? '',
        "dob" => $_POST['p_dob'][$i] ?? '',
        "tc" => $_POST['p_tc'][$i] ?? '',
        "gender" => $_POST['p_gender'][$i] ?? ''
    );
}

$json_veri = json_encode($api_verisi);

// 3. cURL İLE MERKEZ API'YE GÖNDERİM
// --- KURAL 2: API İSTEKLERİNİN AYRIŞTIRILMASI ---
$url = MERKEZ_URL . "/confirm_booking.php?agency=" . urlencode(AGENCY_CODE);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_veri);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_veri)
));
curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

$merkez_cevabi = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if(curl_errno($ch)){
    $hata = curl_error($ch);
    curl_close($ch);
    die("<div style='padding:20px; text-align:center; background:#f8d7da; border:2px solid #dc3545; border-radius:8px;'>
            <h3>Ağ İletişim Hatası</h3><p>Merkez Sunucuya ulaşılamadı: " . htmlspecialchars($hata) . "</p></div>");
}
curl_close($ch);

// 4. MERKEZDEN GELEN CEVABI İŞLE
$cevap_dizisi = json_decode($merkez_cevabi, true);

if ($http_status == 200 && isset($cevap_dizisi['status']) && $cevap_dizisi['status'] == 'success') {
    $pnr = $cevap_dizisi['pnr'] ?? 'PNR_ALINAMADI';
    header("Location: booking_success.php?status=success&pnr=" . urlencode($pnr) . "&surname=" . urlencode($contactSurname));
    exit();
} else {
    $errorMessage = "Merkez Sunucu işlemi reddetti. Bilinmeyen bir hata oluştu.";
    if (isset($cevap_dizisi['message'])) {
        $errorMessage = $cevap_dizisi['message'];
    }

    // Yolcuya şık ve anlaşılır bir hata ekranı göster
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Booking Failed</title></head>";
    echo "<body style='font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;'>";
    echo "<div style='background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 500px;'>";
    echo "<h2 style='color: #dc3545;'><i class='fas fa-times-circle'></i> Booking Failed</h2>";
    echo "<p style='color: #555; font-size: 16px; margin-bottom: 20px;'><b>Hata Detayı:</b> " . htmlspecialchars($errorMessage) . "</p>";
    echo "<p style='color: #888; font-size: 13px; margin-bottom: 30px;'>Lütfen acenta yetkilisiyle iletişime geçin veya daha sonra tekrar deneyin.</p>";
    echo "<a href='index.php' style='background: #232b38; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Return to Home</a>";
    echo "</div></body></html>";
    exit();
}
?>