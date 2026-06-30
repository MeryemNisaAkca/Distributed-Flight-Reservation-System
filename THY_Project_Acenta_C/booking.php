<?php
// --- KURAL 3: Yerel Veritabanı ve Oturum İzolasyonu (Konfigürasyon Dahil) ---
require_once 'agency_config.php'; 
session_start();
include 'connecting.php';



// --- KURAL 2: Merkez API İsteklerinin Ayrıştırılması (Helper Fonksiyon) ---
// İleride merkez sunucuya bir istek atman gerekirse kullanacağın yapı:
function callCentralAPI($endpoint, $params = []) {
    $params['agency'] = AGENCY_CODE; // İstek sonuna dinamik acenta kodunu ekle
    $url = MERKEZ_URL . $endpoint . '?' . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Retrieve flight IDs and pricing details from the URL (GET request)
$flightID = $_GET['flightID'] ?? 0;
$outboundFlightID = $_GET['outbound_flightID'] ?? 0;
$returnFlightID = $_GET['return_flightID'] ?? 0;
$basePrice = $_GET['price'] ?? 0; // Price per person (for one-way)
$outboundPrice = $_GET['outbound_price'] ?? 0; // Outbound price (for round trip)
$returnPrice = $_GET['return_price'] ?? 0; // Return price (for round trip)
$cabinClass = $_GET['class'] ?? 'Economy';
$tripType = $_GET['trip_type'] ?? 'oneway';

// Determine if it's round trip
$isRoundTrip = ($tripType === 'roundtrip' && $outboundFlightID > 0 && $returnFlightID > 0);

// For backward compatibility: if flightID is set but outboundFlightID is not, use flightID
if (!$isRoundTrip && $flightID > 0) {
    $outboundFlightID = $flightID;
    $outboundPrice = $basePrice;
}

// Get Passenger Counts
// YENİ KOD (Hem eskiyi hem de index.php'den gelen count kelimelerini destekler):
$adults = isset($_GET['adult_count']) ? (int)$_GET['adult_count'] : (isset($_GET['adults']) ? (int)$_GET['adults'] : 1);
$children = isset($_GET['child_count']) ? (int)$_GET['child_count'] : (isset($_GET['children']) ? (int)$_GET['children'] : 0);
$teens = isset($_GET['teen_count']) ? (int)$_GET['teen_count'] : (isset($_GET['teens']) ? (int)$_GET['teens'] : 0);
$old = isset($_GET['old_count']) ? (int)$_GET['old_count'] : (isset($_GET['old']) ? (int)$_GET['old'] : 0);
$babies = isset($_GET['baby_count']) ? (int)$_GET['baby_count'] : (isset($_GET['babies']) ? (int)$_GET['babies'] : 0);

// Security Check: Ensure valid flight IDs are present. If not, redirect to home.
if (!$isRoundTrip && $outboundFlightID == 0) {
    header("Location: index.php");
    exit();
}
if ($isRoundTrip && ($outboundFlightID == 0 || $returnFlightID == 0)) {
    header("Location: index.php");
    exit();
}

// Calculate Total Price
$payingPassengers = $adults + $children + $teens + $old;
if ($isRoundTrip) {
    $totalPrice = ($outboundPrice + $returnPrice) * $payingPassengers;
} else {
    $totalPrice = $outboundPrice * $payingPassengers;
}

// Fetch Outbound Flight Details
$sqlOutbound = "SELECT * FROM VW_FlightDetails WHERE [Flight ID] = ? AND [Departure Time] >= GETDATE()";
$stmtOutbound = sqlsrv_query($conn, $sqlOutbound, array($outboundFlightID));
$outboundFlightInfo = sqlsrv_fetch_array($stmtOutbound, SQLSRV_FETCH_ASSOC);

if (!$outboundFlightInfo) {
    die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;'>
        <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Flight Not Available</h3>
        <p style='color:#666;'>This flight is no longer available for booking.</p>
        <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Go Back to Search</a>
    </div>");
}

// Fetch Return Flight Details
$returnFlightInfo = null;
if ($isRoundTrip) {
    $sqlReturn = "SELECT * FROM VW_FlightDetails WHERE [Flight ID] = ? AND [Departure Time] >= GETDATE()";
    $stmtReturn = sqlsrv_query($conn, $sqlReturn, array($returnFlightID));
    $returnFlightInfo = sqlsrv_fetch_array($stmtReturn, SQLSRV_FETCH_ASSOC);
    
    if (!$returnFlightInfo) {
        die("<div style='padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;'>
            <h3 style='color:#856404;'><i class='fas fa-exclamation-triangle'></i> Return Flight Not Available</h3>
            <p style='color:#666;'>The return flight is no longer available.</p>
            <a href='index.php' style='display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; margin-top:15px;'>Go Back to Search</a>
        </div>");
    }
}

$flightInfo = $outboundFlightInfo;
$userName = $_SESSION['user_name'] ?? '';
$userSurname = $_SESSION['user_surname'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';

$userLoyaltyPoints = 0;
if (isset($_SESSION['user_id'])) {
    $sqlPoints = "SELECT LoyaltyPoint FROM Users_Table WHERE UserID = ?";
    $stmtPoints = sqlsrv_query($conn, $sqlPoints, array($_SESSION['user_id']));
    if ($stmtPoints) {
        $rowPoints = sqlsrv_fetch_array($stmtPoints, SQLSRV_FETCH_ASSOC);
        $userLoyaltyPoints = $rowPoints['LoyaltyPoint'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Booking - <?php echo AGENCY_CODE; ?></title>
    <link rel="stylesheet" href="css/index_style.css">
    <link rel="stylesheet" href="css/booking_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
             <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> <?php echo AGENCY_CODE; ?></a>
        </div>
    </div>

    <div class="booking-container">
        
        <div class="booking-form">
            <form action="payment_process.php" method="POST" id="bookingForm">
                <?php if ($isRoundTrip): ?>
                    <input type="hidden" name="trip_type" value="roundtrip">
                    <input type="hidden" name="outbound_flight_id" value="<?php echo $outboundFlightID; ?>">
                    <input type="hidden" name="return_flight_id" value="<?php echo $returnFlightID; ?>">
                    <input type="hidden" name="outbound_price" value="<?php echo $outboundPrice; ?>">
                    <input type="hidden" name="return_price" value="<?php echo $returnPrice; ?>">
                <?php else: ?>
                    <input type="hidden" name="trip_type" value="oneway">
                    <input type="hidden" name="flight_id" value="<?php echo $outboundFlightID; ?>">
                <?php endif; ?>
                <input type="hidden" name="total_price" id="total_price_hidden" value="<?php echo $totalPrice; ?>">
                <input type="hidden" name="class" value="<?php echo $cabinClass; ?>">

                <?php 
                $pIndex = 1;
                $passengerTypes = [
                    'Adult' => $adults,
                    'Teen' => $teens,
                    'Child' => $children,
                    'Baby' => $babies,
                    'Old' => $old
                ];

                foreach($passengerTypes as $type => $count):
                    for($i = 0; $i < $count; $i++):
                        $isFirstPassenger = ($pIndex === 1);
                        $preName = $isFirstPassenger ? $userName : '';
                        $preSurname = $isFirstPassenger ? $userSurname : '';
                ?>
                    <div class="passenger-card">
                        <h4 class="passenger-title"><i class="fas fa-user"></i> Passenger <?php echo $pIndex; ?> (<?php echo $type; ?>)</h4>
                        
                        <input type="hidden" name="p_type[]" value="<?php echo $type; ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="p_name[]" value="<?php echo $preName; ?>" required placeholder="Name">
                            </div>
                            <div class="form-group">
                                <label>Surname</label>
                                <input type="text" name="p_surname[]" value="<?php echo $preSurname; ?>" required placeholder="Surname">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" name="p_dob[]" required>
                            </div>
                            <div class="form-group">
                                <label>TC / Passport No</label>
                                <input type="text" name="p_tc[]" required placeholder="Identity Number">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="p_gender[]">
                                <option value="M">Male</option>
                                <option value="F">Female</option>
                            </select>
                        </div>
                    </div>
                <?php 
                    $pIndex++;
                    endfor; 
                endforeach; 
                ?>

                <div class="passenger-card" style="border-left-color: #232b38;">
                    <h2><i class="fas fa-envelope"></i> Contact Information</h2>
                    <div class="form-group">
                        <label>Email Address (Your tickets will be sent here)</label>
                        <input type="email" name="contact_email" value="<?php echo $userEmail; ?>" required placeholder="john@example.com">
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $userLoyaltyPoints > 0): ?>
                <div class="passenger-card" style="border-left-color: #ffc107;">
                    <h2><i class="fas fa-star"></i> Use Loyalty Points</h2>
                    <div class="form-group">
                        <label>Available Points: <strong style="color: #c8102e;"><?php echo number_format($userLoyaltyPoints, 0); ?> Points</strong></label>
                        <input type="number" name="loyalty_points_used" id="loyalty_points_input" min="0" max="<?php echo $userLoyaltyPoints; ?>" value="0" step="1" onchange="updateFinalPrice()" style="margin-top: 8px;">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> 1 Point = 1 ₺. Maximum <?php echo number_format($userLoyaltyPoints, 0); ?> points can be used.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <div class="passenger-card" style="border-left-color: #28a745;">
                    <h2><i class="fas fa-credit-card"></i> Payment Details</h2>
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" placeholder="XXXX XXXX XXXX XXXX" maxlength="19" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="text" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="form-group">
                            <label>CVV</label>
                            <input type="text" placeholder="123" maxlength="3" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-pay">CONFIRM BOOKING <i class="fas fa-check"></i></button>
            </form>
        </div>

        <div class="flight-summary">
            <h3 style="margin-top: 0; color: #ffcc00;">Flight Summary</h3>
            
            <?php if ($isRoundTrip): ?>
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #555;">
                    <h4 style="color: #ffcc00; margin-bottom: 10px;"><i class="fas fa-plane-departure"></i> Outbound Flight</h4>
                    <div class="summary-row">
                        <span>Flight No</span>
                        <strong><?php echo $outboundFlightInfo['Flight No']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Route</span>
                        <strong><?php echo $outboundFlightInfo['Departure IATA']; ?> <i class="fas fa-arrow-right"></i> <?php echo $outboundFlightInfo['Arrival IATA']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Date</span>
                        <strong><?php echo $outboundFlightInfo['Departure Time']->format('d M Y'); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Time</span>
                        <strong><?php echo $outboundFlightInfo['Departure Time']->format('H:i'); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Price</span>
                        <strong><?php echo number_format($outboundPrice * $payingPassengers, 2); ?> ₺</strong>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #555;">
                    <h4 style="color: #ffcc00; margin-bottom: 10px;"><i class="fas fa-plane-arrival"></i> Return Flight</h4>
                    <div class="summary-row">
                        <span>Flight No</span>
                        <strong><?php echo $returnFlightInfo['Flight No']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Route</span>
                        <strong><?php echo $returnFlightInfo['Departure IATA']; ?> <i class="fas fa-arrow-right"></i> <?php echo $returnFlightInfo['Arrival IATA']; ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Date</span>
                        <strong><?php echo $returnFlightInfo['Departure Time']->format('d M Y'); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Time</span>
                        <strong><?php echo $returnFlightInfo['Departure Time']->format('H:i'); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Price</span>
                        <strong><?php echo number_format($returnPrice * $payingPassengers, 2); ?> ₺</strong>
                    </div>
                </div>
                
                <div class="summary-row">
                    <span>Class</span>
                    <strong><?php echo $cabinClass; ?></strong>
                </div>
            <?php else: ?>
            <div class="summary-row">
                <span>Flight No</span>
                <strong><?php echo $flightInfo['Flight No']; ?></strong>
            </div>
            <div class="summary-row">
                <span>Route</span>
                <strong><?php echo $flightInfo['Departure IATA']; ?> <i class="fas fa-arrow-right"></i> <?php echo $flightInfo['Arrival IATA']; ?></strong>
            </div>
            <div class="summary-row">
                <span>Date</span>
                <strong><?php echo $flightInfo['Departure Time']->format('d M Y'); ?></strong>
            </div>
            <div class="summary-row">
                <span>Time</span>
                <strong><?php echo $flightInfo['Departure Time']->format('H:i'); ?></strong>
            </div>
            <div class="summary-row">
                <span>Class</span>
                <strong><?php echo $cabinClass; ?></strong>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; border-top: 1px solid #555; padding-top: 10px;">
                <small style="color: #aaa;">Passengers:</small><br>
                <?php if($adults > 0) echo "$adults Adult(s)<br>"; ?>
                <?php if($children > 0) echo "$children Child(ren)<br>"; ?>
                <?php if($teens > 0) echo "$teens Teen(s)<br>"; ?>
                <?php if($old > 0) echo "$old Senior(s)<br>"; ?>
                <?php if($babies > 0) echo "$babies Baby(ies)<br>"; ?>
            </div>

            <?php if (isset($_SESSION['user_id']) && $userLoyaltyPoints > 0): ?>
            <div class="summary-row" id="points_discount_row" style="display: none;">
                <span>Points Discount</span>
                <strong style="color: #ffcc00;" id="points_discount">-0.00 ₺</strong>
            </div>
            <?php endif; ?>
            
            <div class="total-price" id="final_price">
                <?php echo number_format($totalPrice, 2); ?> ₺
            </div>
        </div>

    </div>

    <script>
        const totalPrice = <?php echo $totalPrice; ?>;
        const maxPoints = <?php echo $userLoyaltyPoints; ?>;
        
        function updateFinalPrice() {
            const pointsInput = document.getElementById('loyalty_points_input');
            if (!pointsInput) return;
            
            let pointsUsed = parseInt(pointsInput.value) || 0;
            
            if (pointsUsed > maxPoints) {
                pointsUsed = maxPoints;
                pointsInput.value = maxPoints;
            }
            
            if (pointsUsed > totalPrice) {
                pointsUsed = totalPrice;
                pointsInput.value = totalPrice;
            }
            
            const discount = pointsUsed;
            const finalPrice = Math.max(0, totalPrice - discount);
            
            const discountRow = document.getElementById('points_discount_row');
            const discountDisplay = document.getElementById('points_discount');
            const finalPriceDisplay = document.getElementById('final_price');
            const totalPriceHidden = document.getElementById('total_price_hidden');
            
            if (pointsUsed > 0) {
                if (discountRow) discountRow.style.display = 'flex';
                if (discountDisplay) discountDisplay.textContent = '-' + discount.toFixed(2) + ' ₺';
                if (finalPriceDisplay) finalPriceDisplay.innerHTML = finalPrice.toFixed(2) + ' ₺';
            } else {
                if (discountRow) discountRow.style.display = 'none';
                if (finalPriceDisplay) finalPriceDisplay.innerHTML = totalPrice.toFixed(2) + ' ₺';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateFinalPrice();
        });
    </script>

</body>
</html>