<?php
//Users (whether logged in or not, including guest users logged in with a PNR code) can view their reservation details here.

require_once 'agency_config.php';
session_start();
// Get PNR and (optional) surname from request
$pnr = $_REQUEST['pnr'] ?? '';
$surname = $_REQUEST['surname'] ?? '';

if (empty($pnr)) {
    header("Location: index.php");
    exit();
}

// --- KURAL 1: MERKEZ API'DEN VERİ OKUMA (GET İSTEĞİ) ---
$query_params = http_build_query([
    'pnr' => $pnr,
    'surname' => $surname,
    'user_id' => $_SESSION['user_id'] ?? '',
    'agency' => AGENCY_CODE 
]);

$url = MERKEZ_URL . "/get_booking_details.php?" . $query_params;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);

if(curl_errno($ch)){
    die("<h2>Merkez Sunucuya Ulaşılamadı</h2><p>Ağ hatası: " . curl_error($ch) . "</p>");
}
curl_close($ch);

$apiData = json_decode($response, true);

// Merkez sunucu hata dönerse (Debug modu kapatıldı, kullanıcı arayüzü eklendi):
if (!$apiData || !isset($apiData['status']) || $apiData['status'] === 'error') {
    $hataMesaji = $apiData['message'] ?? 'Merkez sunucuya ulaşılamadı veya kayıt bulunamadı.';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Erişim Reddedildi - <?php echo AGENCY_CODE; ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body style="background-color: #f4f7f6; padding: 50px; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <div style="max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
            <i class="fas fa-shield-alt" style="font-size: 64px; color: #dc3545; margin-bottom: 20px;"></i>
            <h2 style="color: #333; margin-top: 0; margin-bottom: 15px;">Güvenlik Engeli</h2>
            <p style="color: #666; font-size: 16px; margin-bottom: 30px; line-height: 1.5;">
                <?php echo htmlspecialchars($hataMesaji); ?>
            </p>
            <a href="javascript:history.back()" style="display: inline-block; padding: 12px 25px; background: #c8102e; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; transition: background 0.3s;">
                <i class="fas fa-undo"></i> Geri Dön ve Tekrar Dene
            </a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// --- API'DEN GELEN VERİLERİ HTML'İN BEKLEDİĞİ DEĞİŞKENLERE ATAMA ---
$rezInfo = $apiData['rezInfo'];
$rezInfo['DepartureTime'] = new DateTime($rezInfo['DepartureTimeStr']);
$rezInfo['ArrivalTime'] = new DateTime($rezInfo['ArrivalTimeStr']);

$isDelayed = $apiData['isDelayed'];
$passengers = $apiData['passengers'];
$allCheckedIn = $apiData['allCheckedIn'];
$allCancelled = $apiData['allCancelled'];
$hasActiveTickets = $apiData['hasActiveTickets'];
$canCancel = $apiData['canCancel'];

// --- BUG FİX: UÇUŞ SÜRESİ HESAPLAYICISI (Eksik Olan Kısım) ---
$now = new DateTime();
$departure = $rezInfo['DepartureTime'];

if ($departure > $now) {
    $interval = $now->diff($departure);
    $minutesUntilDeparture = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
    $hoursUntilDeparture = $minutesUntilDeparture / 60;
} else {
    // Uçuş geçmişte kalmışsa
    $minutesUntilDeparture = 0;
    $hoursUntilDeparture = 0;
}

// Check-in butonu sadece 24 saat ile 25 dakika arasında görünür
$canCheckIn = ($hoursUntilDeparture <= 24 && $minutesUntilDeparture >= 25);

// Date Formatting (HTML'in dokunulmamış kodları için)
$depDate = $rezInfo['DepartureTime']->format('d M Y, l'); 
$depTime = $rezInfo['DepartureTime']->format('H:i');
$arrTime = $rezInfo['ArrivalTime']->format('H:i');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Booking - <?php echo $pnr; ?></title>
    <link rel="stylesheet" href="css/checkin_style.css"> 
    <link rel="stylesheet" href="css/ticket_details_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div style="background:#232b38; padding:15px; color:white; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-plane"></i> <strong><?php echo AGENCY_CODE; ?> Booking Management</strong>
        <div style="margin-left:auto; display:flex; gap:15px; align-items:center;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="javascript:history.back()" style="color:#fff; text-decoration:none; padding:8px 15px; background:rgba(255,255,255,0.2); border-radius:5px; transition:0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            <?php endif; ?>
            <a href="index.php" style="color:#aaa; text-decoration:none;">
                <i class="fas fa-home"></i> Home
            </a>
        </div>
    </div>

    <div class="manage-container">
        
        <div style="margin-bottom: 20px;">
            <a href="javascript:history.back()" style="display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <div class="flight-card">
            <div class="card-header">
                <span>PNR: <strong><?php echo $pnr; ?></strong></span>
                <span><?php echo $depDate; ?></span>
            </div>
            <div class="card-body">
                
                <div class="route-large">
                    <b><?php echo $rezInfo['DepCode']; ?></b> 
                    <i class="fas fa-plane" style="font-size:20px; color:#ccc;"></i> 
                    <b><?php echo $rezInfo['ArrCode']; ?></b>
                </div>

                <div class="flight-grid">
                    <div class="f-item">
                        <label>Flight No</label> <span><?php echo $rezInfo['FlightNo']; ?></span>
                    </div>
                    <div class="f-item">
                        <label>Departure</label> <span><?php echo $depTime; ?></span>
                    </div>
                    <div class="f-item">
                        <label>Arrival</label> <span><?php echo $arrTime; ?></span>
                    </div>
                    <div class="f-item">
                        <label>Total Price</label> <span><?php echo number_format($rezInfo['TotalCost'], 2); ?> ₺</span>
                    </div>
                </div>

                <div class="passenger-list">
                    <h4 style="margin-bottom:15px;">Passengers</h4>
                    <?php foreach($passengers as $p): 
                        $isCancelled = (isset($p['TicketStatus']) && $p['TicketStatus'] === 'Cancelled');
                    ?>
                        <div class="p-item" style="<?php echo $isCancelled ? 'opacity:0.6; background:#f0f0f0;' : ''; ?>">
                            <div class="p-item-content">
                                <div class="p-item-left">
                                    <i class="fas fa-user" style="color:#ccc; margin-right:10px;"></i>
                                    <strong><?php echo htmlspecialchars($p['PassengerName'] . " " . strtoupper($p['PassengerSurname'])); ?></strong>
                                    <span style="font-size:12px; color:#888;">(<?php echo htmlspecialchars($p['AgeType']); ?>)</span>
                                    <?php if ($isCancelled): ?>
                                        <span class="status-badge status-cancelled" style="margin-left:10px;"><i class="fas fa-times"></i> Cancelled</span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-item-right">
                                    <?php if($isCancelled): ?>
                                        <span class="status-badge status-cancelled">Cancelled</span>
                                    <?php elseif($p['CheckInStatus'] == 1): ?>
                                        <span class="status-badge status-ok"><i class="fas fa-check"></i> Checked-in</span>
                                        <span style="font-size:12px; font-weight:bold; color:#333;">Seat: <?php echo htmlspecialchars($p['SeatNo'] ?? 'N/A'); ?></span>
                                    <?php else: ?>
                                        <span class="status-badge status-wait">Pending Check-in</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($canCancel && !$isCancelled): ?>
                                        <?php 
                                        $cancelUrl = "cancel_ticket.php?pnr=" . urlencode($pnr) . "&ticket_id=" . $p['TicketID'];
                                        if (!isset($_SESSION['user_id']) && !empty($surname)) {
                                            $cancelUrl .= "&surname=" . urlencode($surname);
                                        }
                                        ?>
                                        <a href="<?php echo $cancelUrl; ?>" 
                                           class="btn-cancel-passenger" 
                                           onclick="return confirm('Are you sure you want to cancel <?php echo htmlspecialchars($p['PassengerName']); ?>\'s ticket? This action cannot be undone.');">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <?php if($allCancelled || !$hasActiveTickets): ?>
                        <div class="btn-action" style="flex:1; background:#dc3545; color:white; cursor:not-allowed; opacity:0.7;">
                            <i class="fas fa-ban"></i> All tickets in this reservation have been cancelled
                        </div>
                    <?php elseif($allCheckedIn): ?>
                        <a href="boarding_pass.php?pnr=<?php echo $pnr; ?>" class="btn-action btn-boarding" style="flex:1;">
                            <i class="fas fa-ticket-alt"></i> View Boarding Passes
                        </a>
                    <?php elseif ($canCheckIn && $hasActiveTickets): ?>
                        <a href="checkin.php?pnr=<?php echo $pnr; ?>" class="btn-action btn-checkin" style="flex:1;" onclick="return validateCheckInTime(<?php echo $hoursUntilDeparture; ?>, <?php echo $minutesUntilDeparture; ?>);">
                            <i class="fas fa-suitcase-rolling"></i> Start Check-in Process
                        </a>
                    <?php else: ?>
                        <div class="btn-action" style="flex:1; background:#6c757d; color:white; cursor:not-allowed; opacity:0.7;">
                            <i class="fas fa-clock"></i> 
                            <?php if ($hoursUntilDeparture > 24): ?>
                                Check-in opens 24 hours before departure (<?php echo max(0, floor($hoursUntilDeparture)); ?> hours remaining)
                            <?php else: ?>
                                Check-in closed (less than 25 minutes remaining)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($canCancel && $hasActiveTickets): ?>
                        <a href="cancel_ticket.php?pnr=<?php echo htmlspecialchars($pnr); ?>" class="btn-action" style="background:#dc3545; flex:1;" onclick="return confirm('Are you sure you want to cancel ALL tickets in this reservation? This action cannot be undone.');">
                            <i class="fas fa-times-circle"></i> Cancel All Tickets
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

    <script>
        function validateCheckInTime(hoursUntilDeparture, minutesUntilDeparture) {
            if (hoursUntilDeparture > 24) {
                var hoursRemaining = Math.max(0, Math.floor(hoursUntilDeparture));
                var minutesRemaining = Math.max(0, Math.floor(minutesUntilDeparture % 60));
                var message = "Check-in is not yet available. Check-in opens 24 hours before departure.\n\n";
                message += "Your flight departs in " + hoursRemaining + " hours and " + minutesRemaining + " minutes.\n";
                message += "Please try again later.";
                alert(message);
                return false; 
            }
            
            if (minutesUntilDeparture < 25) {
                alert("Check-in has closed. Check-in closes 25 minutes before departure.");
                return false; 
            }
            
            return true; 
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($isDelayed || (isset($_SESSION['delayed_alert']) && $_SESSION['delayed_alert'])): ?>
            alert('⚠️ Flight Delay Notice\n\nYour flight has been delayed. Please check the updated departure time.');
            <?php 
            if (isset($_SESSION['delayed_alert'])) {
                unset($_SESSION['delayed_alert']);
            }
            ?>
            <?php endif; ?>
        });
    </script>

</body>
</html>