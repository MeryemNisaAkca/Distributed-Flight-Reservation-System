<?php
// 1. KURAL 3: YAPI İZOLASYONU - ÖNCE ACENTA AYARLARI
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}

// 2. GÜVENLİ SESSION: Ayarlardan hemen sonra başlatılmalı (connecting.php kaldırıldı!)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];

// 3. KURAL 2: MERKEZ API'DEN cURL İLE VERİLERİ ÇEKME (GET)
$url = MERKEZ_URL . "/api_get_my_flights.php?user_id=" . urlencode($userID) . "&agency=" . urlencode(AGENCY_CODE);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);

if(curl_errno($ch)){
    die("An error occurred while loading your flights. Central API unreachable.");
}
curl_close($ch);

$apiData = json_decode($response, true);

if (!$apiData || !isset($apiData['status']) || $apiData['status'] !== 'success') {
    die("An error occurred while loading your flights. Please try again later.");
}

// API'den gelen ham verileri senin orijinal değişkenlerine atıyoruz
$userPoints = $apiData['userPoints'];
$hasDelayedFlight = $apiData['hasDelayedFlight'];
$delayedFlightsList = $apiData['delayedFlightsList'];
$upcomingFlights = $apiData['upcomingFlights'];
$pastFlights = $apiData['pastFlights'];

// BUG FIX: API'den gelen string tarihleri, aşağıdaki orijinal HTML kodlarının 
// ->format() fonksiyonlarında patlamaması için DateTime objelerine geri çeviriyoruz.
foreach ($delayedFlightsList as &$delayed) {
    if (isset($delayed['DepartureTimeStr'])) {
        $delayed['DepartureTime'] = new DateTime($delayed['DepartureTimeStr']);
    }
}
foreach ($upcomingFlights as &$f) {
    if (isset($f['DepartureTimeStr'])) {
        $f['DepartureTime'] = new DateTime($f['DepartureTimeStr']);
    }
}
foreach ($pastFlights as &$f) {
    if (isset($f['DepartureTimeStr'])) {
        $f['DepartureTime'] = new DateTime($f['DepartureTimeStr']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Flights - THY Project</title>
    <link rel="stylesheet" href="css/checkin_style.css">
    <link rel="stylesheet" href="css/my_flights_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div style="background:#232b38; padding:15px; color:white; display:flex; align-items:center; justify-content:space-between; gap:20px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <i class="fas fa-plane"></i> <strong>THY Project</strong>
            <a href="index.php" style="color:#ddd; text-decoration:none;">Home</a>
            <a href="my_flights.php" style="color:white; font-weight:bold; text-decoration:none;">My Flights</a>
        </div>
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="javascript:history.back()" style="color:#fff; text-decoration:none; padding:8px 15px; background:rgba(255,255,255,0.2); border-radius:5px;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="logout.php" style="color:#aaa; text-decoration:none;">Logout</a>
        </div>
    </div>

    <div class="container">
        
        <?php if ($hasDelayedFlight): ?>
        <div style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: #856404; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 6px solid #ff9800; box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3); animation: slideDown 0.5s ease-out;">
            <div style="display: flex; align-items: flex-start; gap: 15px;">
                <div style="font-size: 36px; flex-shrink: 0;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 10px 0; font-size: 18px; font-weight: bold; color: #856404; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-clock"></i> Flight Delay Notice
                    </h3>
                    <p style="margin: 0 0 12px 0; font-size: 14px; line-height: 1.6; color: #856404;">
                        One or more of your upcoming flights have been delayed. Please check your flight details below for updated departure times.
                    </p>
                    <?php if (!empty($delayedFlightsList)): ?>
                        <div style="background: rgba(255, 255, 255, 0.3); padding: 12px; border-radius: 6px; margin-top: 10px;">
                            <strong style="font-size: 12px; text-transform: uppercase; color: #856404; display: block; margin-bottom: 8px;">Delayed Flights:</strong>
                            <ul style="margin: 0; padding-left: 20px; list-style: none;">
                                <?php foreach ($delayedFlightsList as $delayed): ?>
                                    <li style="margin-bottom: 6px; font-size: 13px; color: #856404;">
                                        <i class="fas fa-plane" style="margin-right: 6px;"></i>
                                        <strong><?php echo htmlspecialchars($delayed['FlightNo']); ?></strong> - 
                                        <?php echo htmlspecialchars($delayed['Route']); ?>
                                        <span style="margin-left: 8px; opacity: 0.8;">
                                            (<?php echo $delayed['DepartureTime'] instanceof DateTime ? $delayed['DepartureTime']->format('d M Y, H:i') : 'N/A'; ?>)
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <button onclick="this.parentElement.parentElement.style.display='none';" style="background: rgba(133, 100, 4, 0.2); border: 2px solid #856404; color: #856404; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; flex-shrink: 0; transition: all 0.3s; height: fit-content;" 
                        onmouseover="this.style.background='rgba(133, 100, 4, 0.3)';" 
                        onmouseout="this.style.background='rgba(133, 100, 4, 0.2)';">
                    <i class="fas fa-times"></i> Dismiss
                </button>
            </div>
        </div>
        <style>
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
        <?php endif; ?>
        
        <div style="background: linear-gradient(135deg, #c8102e 0%, #a00c24 100%); color:white; padding:20px; border-radius:10px; margin-bottom:30px; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0; font-size:18px;"><i class="fas fa-star"></i> My Loyalty Points</h3>
                <p style="margin:5px 0 0 0; font-size:14px; opacity:0.9;">Total accumulated points</p>
            </div>
            <div style="font-size:32px; font-weight:bold;">
                <?php echo number_format($userPoints, 0); ?> <span style="font-size:16px;">Points</span>
            </div>
        </div>
        
        <h2 class="section-title"><i class="fas fa-plane-departure"></i> Upcoming Flights</h2>
        
        <?php if (count($upcomingFlights) > 0): ?>
            <?php foreach ($upcomingFlights as $f): 
                $dateStr = $f['DepartureTime']->format('d M Y, H:i');
                $tickets = $f['Tickets'] ?? [];
            ?>
            <div class="flight-history-card">
                <div class="fh-header">
                <div class="fh-info">
                        <div class="fh-date"><i class="far fa-calendar-alt"></i> <?php echo $dateStr; ?></div>
                        <div class="fh-route">
                            <?php echo htmlspecialchars($f['DepCity'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($f['DepIATA'] ?? 'N/A'); ?>) 
                            <i class="fas fa-arrow-right" style="font-size:14px; color:#aaa;"></i> 
                            <?php echo htmlspecialchars($f['ArrCity'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($f['ArrIATA'] ?? 'N/A'); ?>)
                        </div>
                        <div style="margin-top:5px;">
                            <span style="font-size:13px; color:#555;"><?php echo htmlspecialchars($f['FlightNo'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <a href="ticket_details.php?pnr=<?php echo htmlspecialchars($f['PNR'] ?? ''); ?>" class="btn-manage">Manage Booking</a>
                </div>
                
                <?php if (!empty($tickets)): ?>
                <div class="fh-passengers">
                    <strong style="font-size: 14px; color: #555;">Passengers (<?php echo count($tickets); ?>):</strong>
                    <?php foreach ($tickets as $t): 
                        $isCancelled = (isset($t['TicketStatus']) && $t['TicketStatus'] === 'Cancelled');
                    ?>
                        <div class="passenger-item" style="<?php echo $isCancelled ? 'opacity:0.6; background:#f0f0f0;' : ''; ?>">
                            <div style="display:flex; justify-content:space-between; align-items:start;">
                                <div style="flex:1;">
                                    <div class="passenger-name">
                                        <?php echo htmlspecialchars($t['PassengerName'] . ' ' . $t['PassengerSurname']); ?>
                                        <?php if ($isCancelled): ?>
                                            <span class="badge" style="background:#dc3545; color:white; margin-left:5px;">Cancelled</span>
                                        <?php endif; ?>
                                        <span class="badge badge-baby" style="<?php echo strtolower($t['AgeType']) === 'baby' ? '' : 'display:none;'; ?>">Baby</span>
                                    </div>
                                    <div class="passenger-details">
                                        <span><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($t['AgeType']); ?> - <?php echo htmlspecialchars($t['CabinType']); ?></span>
                                        <?php if (!empty($t['SeatNo']) && strtolower($t['AgeType']) !== 'baby'): ?>
                                            <span class="badge badge-seat"><i class="fas fa-chair"></i> Seat: <?php echo htmlspecialchars($t['SeatNo']); ?></span>
                                        <?php elseif (strtolower($t['AgeType']) === 'baby' && !empty($t['CompanionName'])): ?>
                                            <span class="badge badge-baby"><i class="fas fa-baby"></i> With: <?php echo htmlspecialchars($t['CompanionName']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($t['MealName'])): ?>
                                            <span class="badge badge-meal"><i class="fas fa-utensils"></i> <?php echo htmlspecialchars($t['MealName']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($t['BaggageWeight'])): ?>
                                            <span class="badge badge-baggage"><i class="fas fa-suitcase"></i> <?php echo htmlspecialchars($t['BaggageWeight']); ?>kg</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!$isCancelled && $f['DepartureTime'] > $now): ?>
                                    <a href="cancel_ticket.php?pnr=<?php echo htmlspecialchars($f['PNR']); ?>&ticket_id=<?php echo $t['TicketID']; ?>" 
                                       class="btn-cancel-passenger" 
                                       onclick="return confirm('Are you sure you want to cancel this passenger\'s ticket? This action cannot be undone.');"
                                       style="background:#dc3545; color:white; padding:5px 12px; border-radius:4px; text-decoration:none; font-size:12px; font-weight:bold; margin-left:10px; white-space:nowrap;">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#777; font-style:italic;">You have no upcoming flights.</p>
        <?php endif; ?>


        <h2 class="section-title" style="margin-top:50px;"><i class="fas fa-history"></i> Past Flights</h2>
        
        <?php if (count($pastFlights) > 0): ?>
            <?php foreach ($pastFlights as $f): 
                $dateStr = $f['DepartureTime']->format('d M Y, H:i');
                $tickets = $f['Tickets'] ?? [];
            ?>
            <div class="flight-history-card past">
                <div class="fh-header">
                <div class="fh-info">
                        <div class="fh-date"><?php echo $dateStr; ?></div>
                        <div class="fh-route">
                            <?php echo htmlspecialchars($f['DepCity'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($f['DepIATA'] ?? 'N/A'); ?>) 
                            <i class="fas fa-arrow-right" style="font-size:14px; color:#aaa;"></i> 
                            <?php echo htmlspecialchars($f['ArrCity'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($f['ArrIATA'] ?? 'N/A'); ?>)
                        </div>
                        <div style="margin-top:5px;">
                            <span style="font-size:13px; color:#555;"><?php echo htmlspecialchars($f['FlightNo'] ?? 'N/A'); ?></span>
                        </div>
                    </div>
                    <a href="ticket_details.php?pnr=<?php echo htmlspecialchars($f['PNR'] ?? ''); ?>" class="btn-manage btn-view">View Details</a>
                </div>
                
                <?php if (!empty($tickets)): ?>
                <div class="fh-passengers">
                    <strong style="font-size: 14px; color: #555;">Passengers (<?php echo count($tickets); ?>):</strong>
                    <?php foreach ($tickets as $t): ?>
                        <div class="passenger-item">
                            <div class="passenger-name">
                                <?php echo htmlspecialchars($t['PassengerName'] . ' ' . $t['PassengerSurname']); ?>
                                <span class="badge badge-baby" style="<?php echo strtolower($t['AgeType']) === 'baby' ? '' : 'display:none;'; ?>">Baby</span>
                            </div>
                            <div class="passenger-details">
                                <span><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($t['AgeType']); ?> - <?php echo htmlspecialchars($t['CabinType']); ?></span>
                                <?php if (!empty($t['SeatNo']) && strtolower($t['AgeType']) !== 'baby'): ?>
                                    <span class="badge badge-seat"><i class="fas fa-chair"></i> Seat: <?php echo htmlspecialchars($t['SeatNo']); ?></span>
                                <?php elseif (strtolower($t['AgeType']) === 'baby' && !empty($t['CompanionName'])): ?>
                                    <span class="badge badge-baby"><i class="fas fa-baby"></i> With: <?php echo htmlspecialchars($t['CompanionName']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($t['MealName'])): ?>
                                    <span class="badge badge-meal"><i class="fas fa-utensils"></i> <?php echo htmlspecialchars($t['MealName']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($t['BaggageWeight'])): ?>
                                    <span class="badge badge-baggage"><i class="fas fa-suitcase"></i> <?php echo htmlspecialchars($t['BaggageWeight']); ?>kg</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#777; font-style:italic;">No flight history found.</p>
        <?php endif; ?>

    </div>

</body>
</html>