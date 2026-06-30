<?php


require_once 'agency_config.php';
session_start();
$message = "";
$messageType = ""; 
$pnr = $_REQUEST['pnr'] ?? '';
if (empty($pnr)) { header("Location: index.php"); exit(); }

// --- KURAL 1: API İSTEKLERİNİN AYRIŞTIRILMASI (Merkezden Veri Okuma) ---
$url = MERKEZ_URL . "/get_checkin_data.php?pnr=" . urlencode($pnr);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
curl_close($ch);

$apiData = json_decode($response, true);

if (!$apiData || $apiData['status'] !== 'success') {
    header("Location: index.php?error=reservation_not_found");
    exit();
}

// API'den Gelen Verileri HTML'in Beklediği Formata Aktar
$flightTimeData = $apiData['flightTimeData'];
$isDelayed = ($flightTimeData['FlightStatus'] === 'Delayed');
$hoursUntilDeparture = $flightTimeData['HoursUntilDeparture'] ?? 0;
$minutesUntilDeparture = $flightTimeData['MinutesUntilDeparture'] ?? 0;

// 24 Saat ve 25 Dakika Kuralı (HTML Hata Ekranları)
if ($hoursUntilDeparture > 24) {
    $h = max(0, floor($hoursUntilDeparture)); $m = max(0, floor($minutesUntilDeparture % 60));
    $msg = "Check-in is not yet available. Check-in opens 24 hours before departure. Your flight departs in $h hours and $m minutes.";
    die("<!DOCTYPE html><html lang='en'><head><title>Not Available</title><link rel='stylesheet' href='css/checkin_error_style.css'><link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'></head><body><div class='error-container'><div class='error-icon'><i class='fas fa-clock'></i></div><h1>Not Available</h1><p>$msg</p><a href='javascript:history.back()' class='btn-back'><i class='fas fa-arrow-left'></i> Back</a></div></body></html>");
}
if ($minutesUntilDeparture < 25) {
    die("<!DOCTYPE html><html lang='en'><head><title>Closed</title><link rel='stylesheet' href='css/checkin_error_style.css'><link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'></head><body><div class='error-container'><div class='error-icon'><i class='fas fa-times-circle'></i></div><h1>Closed</h1><p>Check-in has closed 25 minutes before departure.</p><a href='javascript:history.back()' class='btn-back'><i class='fas fa-arrow-left'></i> Back</a></div></body></html>");
}

// Verileri Değişkenlere Ata
$baggageOptions = $apiData['baggageOptions'];
$baggagePrices = $apiData['baggagePrices'];
$mealOptions = $apiData['mealOptions'];
$mealPrices = $apiData['mealPrices'];
$planeCapacity = $apiData['flightRow']['SeatCapacity'] ?? 180;
$occupiedSeats = $apiData['occupiedSeats'];
$passengers = $apiData['passengers'];
$hasPassengers = (count($passengers) > 0);

$numRows = 6;
$totalCols = max(10, min(50, (int)ceil($planeCapacity / $numRows)));

// --- FORM GÖNDERİLDİĞİNDE (POST - Merkeze Yazma) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $seats = $_POST['seats'] ?? [];     
    $meals = $_POST['meals'] ?? [];     
    $baggages = $_POST['baggages'] ?? []; 
    $companions = $_POST['companions'] ?? [];
    
    // Ekstra ücretleri Acenta tarafında (Local) hesapla
    $totalExtraCost = 0;
    foreach ($baggages as $bid) { if(isset($baggagePrices[$bid])) $totalExtraCost += $baggagePrices[$bid]; }
    foreach ($meals as $mid) { if(isset($mealPrices[$mid])) $totalExtraCost += $mealPrices[$mid]; }
    
    if ($totalExtraCost > 0) {
        $_SESSION['pending_checkin'] = [ 'pnr' => $pnr, 'seats' => $seats, 'meals' => $meals, 'baggages' => $baggages, 'companions' => $companions, 'total_amount' => $totalExtraCost ];
        header("Location: payment_extra.php"); 
        exit();
    } else {
        // Ekstra ücret yoksa Merkeze Check-in işlemini onayla (API POST)
        $checkinPayload = [
            "pnr" => $pnr,
            "agency" => AGENCY_CODE,
            "seats" => $seats,
            "meals" => $meals,
            "baggages" => $baggages,
            "companions" => $companions,
            "passengers" => $passengers
        ];

        $postUrl = MERKEZ_URL . "/process_checkin.php";
        $ch2 = curl_init($postUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($checkinPayload));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $apiCevap = curl_exec($ch2);
        curl_close($ch2);

        $result = json_decode($apiCevap, true);
        
        if ($result && isset($result['status']) && $result['status'] === 'success') {
            header("Location: boarding_pass.php?pnr=" . $pnr);
            exit();
        } else {
            $message = "Error: " . ($result['message'] ?? "API Hatası");
            $messageType = "warning";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visual Check-in - <?php echo htmlspecialchars(AGENCY_CODE); ?></title>
    <link rel="stylesheet" href="css/checkin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div style="background:#232b38; padding:15px; color:white; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-plane-departure"></i> <strong><?php echo htmlspecialchars(AGENCY_CODE); ?> Check-in</strong>
        <a href="javascript:history.back()" style="color:#fff; margin-left:auto; text-decoration:none; padding:8px 15px; background:rgba(255,255,255,0.2); border-radius:5px;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if($message != ""): ?>
        <div style="padding:20px; text-align:center; background:#fff3cd; border:2px solid #ffc107; border-radius:8px; margin:20px auto; max-width:600px;">
            <h3 style="color:#856404; margin-bottom:10px;"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($message); ?></h3>
            <p style="color:#666; font-size:14px; margin-bottom:15px;">Please check your selections and try again.</p>
            <a href="checkin.php?pnr=<?php echo htmlspecialchars($pnr); ?>" style="display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; font-weight:bold; margin-right:10px;">
                <i class="fas fa-redo"></i> Try Again
            </a>
            <a href="index.php" style="display:inline-block; padding:10px 20px; background:#6c757d; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">
                <i class="fas fa-home"></i> Go Home
            </a>
        </div>
    <?php endif; ?>

    <?php if($messageType != 'success' && $hasPassengers): 
        $allBabies = true;
        foreach($passengers as $p) {
            if (strtolower($p['AgeType']) !== 'baby') {
                $allBabies = false;
                break;
            }
        }
    ?>
    <form action="checkin.php" method="POST" id="visualCheckinForm">
        <input type="hidden" name="pnr" value="<?php echo htmlspecialchars($pnr); ?>">
        
        <div class="checkin-wrapper">
            
            <?php if (!$allBabies): ?>
            <div class="plane-container">
                <div class="cockpit"></div>
                
                <div class="fuselage">
                    <?php 
                    $rows = ['A', 'B', 'C', 'aisle', 'D', 'E', 'F'];

                    foreach($rows as $letter):
                        if($letter == 'aisle') {
                            echo "<div class='aisle-gap'>---------------- AISLE ----------------</div>";
                            continue;
                        }

                        echo "<div class='plane-row'>";
                        echo "<div class='row-letter'>$letter</div>";

                        for($i=1; $i<=$totalCols; $i++):
                            $seatCode = $i . $letter;
                            
                            $isOccupied = in_array($seatCode, $occupiedSeats);
                            $class = $isOccupied ? "seat occupied" : "seat";
                            $onclick = $isOccupied ? "" : "onclick=\"selectSeat(this, '$seatCode')\"";
                            
                            echo "<div class='$class' id='seat_$seatCode' $onclick>";
                            if($isOccupied) echo "<i class='fas fa-times'></i>";
                            else echo $i; 
                            echo "</div>";
                        endfor;

                        echo "</div>";
                    endforeach; 
                    ?>
                </div>
            </div>

            <div class="legend">
                <div><span class="box free"></span> Empty</div>
                <div><span class="box occ"></span> Full</div>
                <div><span class="box sel"></span> Selected</div>
            </div>
            <?php else: ?>
            <div style="text-align:center; padding:40px; background:#f9f9f9; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-baby" style="font-size:48px; color:#c8102e; margin-bottom:15px;"></i>
                <h3 style="color:#333;">Baby Passengers</h3>
                <p style="color:#666;">Babies travel on lap and share the companion's seat. Please select companions for each baby below.</p>
            </div>
            <?php endif; ?>

            <div class="passenger-panel">
                <h3 style="margin-top:0;">Select Passenger & Services</h3>
                
                <div class="passenger-container">
                    <?php 
                    $index = 0;
                    $isBaby = false;
                    foreach($passengers as $pass): 
                        $tid = $pass['TicketID'];
                        $activeClass = ($index === 0) ? 'active' : '';
                        $isBaby = (strtolower($pass['AgeType']) === 'baby');
                    ?>
                        <?php if ($isBaby): ?>
                            <input type="hidden" name="seats[<?php echo $tid; ?>]" id="input_seat_<?php echo $index; ?>" value="COMPANION" required>
                        <?php else: ?>
                        <input type="hidden" name="seats[<?php echo $tid; ?>]" id="input_seat_<?php echo $index; ?>" required>
                        <?php endif; ?>

                        <div class="passenger-tab <?php echo $activeClass; ?>" onclick="activatePassenger(<?php echo $index; ?>)" id="tab_<?php echo $index; ?>">
                            <span class="selected-seat-badge" id="badge_<?php echo $index; ?>"></span>

                            <div class="passenger-header">
                                <div class="p-icon"><i class="fas fa-user"></i></div>
                                <div class="p-info">
                                    <span class="p-name">
                                        <?php echo htmlspecialchars($pass['PassengerName'] . " " . strtoupper($pass['PassengerSurname'])); ?>
                                    </span>
                                    <span class="p-type"><?php echo htmlspecialchars($pass['AgeType']); ?> Passenger</span>
                                </div>
                            </div>
                            <div style="font-size:13px;">
                                <?php if ($isBaby): ?>
                                    <label style="font-weight:600; color:#555; margin-bottom:4px; display:block;">Traveling with (Companion):</label>
                                    <select name="companions[<?php echo $tid; ?>]" id="companion_<?php echo $index; ?>" required style="width:100%; margin-bottom:10px;" onchange="updateBabyCompanion(<?php echo $index; ?>)">
                                        <option value="">Select Companion</option>
                                        <?php 
                                        foreach($passengers as $otherPass): 
                                            $otherAgeType = strtolower($otherPass['AgeType']);
                                            $allowedAgeTypes = ['teen', 'adult', 'old'];
                                            if ($otherPass['TicketID'] != $tid && in_array($otherAgeType, $allowedAgeTypes)):
                                                echo "<option value='{$otherPass['TicketID']}'>".htmlspecialchars($otherPass['PassengerName'])." ".htmlspecialchars($otherPass['PassengerSurname'])." ({$otherPass['AgeType']})</option>";
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                    <small style="color:#666; font-size:11px; display:block; margin-top:5px;">
                                        <i class="fas fa-info-circle"></i> Babies can only travel with Teen, Adult, or Old passengers. Babies travel on lap and share the companion's seat.
                                    </small>
                                <?php else: ?>
                                <label style="font-weight:600; color:#555; margin-bottom:4px; display:block;">Meal Preference:</label>
                                <select name="meals[<?php echo $tid; ?>]" class="calc-price" onchange="calculateTotal()" style="width:100%; margin-bottom:10px;">
                                    <option value="" data-price="0">No Preference</option>
                                    <?php foreach($mealOptions as $m) echo "<option value='{$m['MealID']}' data-price='".($m['Price']??0)."'>".htmlspecialchars($m['MealName'])."</option>"; ?>
                                </select>
                                
                                <label style="font-weight:600; color:#555; margin-bottom:4px; display:block;">Extra Baggage:</label>
                                <select name="baggages[<?php echo $tid; ?>]" class="calc-price" onchange="calculateTotal()" style="width:100%;">
                                    <option value="" data-price="0">Standard Allowance</option>
                                    <?php foreach($baggageOptions as $b) echo "<option value='{$b['BaggageID']}' data-price='{$b['Price']}'>{$b['WeightKG']} KG</option>"; ?>
                                </select>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php $index++; endforeach; ?>
                </div>

                <div class="action-bar">
                    <div class="total-price">Total Extra: <span id="displayTotal">0.00</span> ₺</div>
                    <button type="submit" class="btn-finish" id="submitBtn" disabled>Complete Check-in</button>
                </div>
            </div>

        </div>
    </form>
    <?php elseif (!$hasPassengers): ?>
        <?php
        $sqlCheckCancelled = "
            SELECT COUNT(*) as TotalTickets,
                   SUM(CASE WHEN (TicketStatus IS NULL OR TicketStatus <> 'Cancelled') THEN 1 ELSE 0 END) as ActiveTickets
            FROM Tickets_Table T
            INNER JOIN Reservation_Table R ON T.ReservationID = R.ReservationID
            WHERE R.PNR = ?
        ";
        $stmtCheckCancelled = sqlsrv_query($conn, $sqlCheckCancelled, array($pnr));
        $cancelledInfo = sqlsrv_fetch_array($stmtCheckCancelled, SQLSRV_FETCH_ASSOC);
        $allTicketsCancelled = ($cancelledInfo && $cancelledInfo['ActiveTickets'] == 0 && $cancelledInfo['TotalTickets'] > 0);
        ?>
        <div style="max-width:800px; margin:40px auto; padding:30px; background:white; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); text-align:center;">
            <div style="font-size:64px; color:<?php echo $allTicketsCancelled ? '#dc3545' : '#6c757d'; ?>; margin-bottom:20px;">
                <i class="fas fa-<?php echo $allTicketsCancelled ? 'ban' : 'info-circle'; ?>"></i>
            </div>
            <h2 style="color:#333; margin-bottom:15px;">
                <?php echo $allTicketsCancelled ? 'All Tickets Cancelled' : 'No Passengers to Check In'; ?>
            </h2>
            <p style="color:#666; font-size:16px; margin-bottom:30px;">
                <?php if ($allTicketsCancelled): ?>
                    All tickets in this reservation have been cancelled. Check-in is not available for cancelled tickets.
                <?php else: ?>
                    All passengers appear to have already checked in.
                <?php endif; ?>
            </p>
            <a href="javascript:history.back()" style="display:inline-block; padding:10px 20px; background:#c8102e; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    <?php endif; ?>

    <script>
        let currentPassengerIndex = 0;
        const totalPassengers = <?php echo count($passengers); ?>;
        let selections = {}; 
        const passengerTypes = <?php echo json_encode(array_column($passengers, 'AgeType')); ?>;
        const passengerTicketIDs = <?php echo json_encode(array_column($passengers, 'TicketID')); ?>;

        function activatePassenger(index) {
            currentPassengerIndex = index;
            document.querySelectorAll('.passenger-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab_' + index).classList.add('active');
        }

        function selectSeat(element, seatCode) {
            if (passengerTypes[currentPassengerIndex] && passengerTypes[currentPassengerIndex].toLowerCase() === 'baby') {
                alert("Babies don't need a seat. Please select a companion from the passenger panel instead.");
                return;
            }
            
            for (let pIndex in selections) {
                if (passengerTypes[pIndex] && passengerTypes[pIndex].toLowerCase() === 'baby') continue;
                if (selections[pIndex] === seatCode && pIndex != currentPassengerIndex) {
                    alert("Seat already selected by another passenger in your group!"); return;
                }
            }
            let prev = selections[currentPassengerIndex];
            if (prev) {
                let old = document.getElementById('seat_' + prev);
                if(old) old.classList.remove('selected');
            }
            selections[currentPassengerIndex] = seatCode;
            element.classList.add('selected');
            
            document.getElementById('input_seat_' + currentPassengerIndex).value = seatCode;
            let badge = document.getElementById('badge_' + currentPassengerIndex);
            badge.style.display = 'block'; badge.innerText = seatCode;
            
            validateForm();
        }

        function updateBabyCompanion(index) {
            const companionSelect = document.getElementById('companion_' + index);
            const badge = document.getElementById('badge_' + index);
            if (companionSelect && badge) {
                if (companionSelect.value !== "") {
                    const selectedOption = companionSelect.options[companionSelect.selectedIndex];
                    badge.style.display = 'block';
                    badge.innerText = 'With: ' + selectedOption.text.split(' (')[0];
                } else {
                    badge.style.display = 'none';
                }
            }
            validateForm();
        }

        function validateForm() {
            let count = 0;
            for(let i=0; i<totalPassengers; i++) {
                const isBaby = passengerTypes[i] && passengerTypes[i].toLowerCase() === 'baby';
                if (isBaby) {
                    const companionSelect = document.getElementById('companion_' + i);
                    if (companionSelect && companionSelect.value !== "") {
                        count++;
                    }
                } else {
                    const seatInput = document.getElementById('input_seat_' + i);
                    if(seatInput && seatInput.value !== "" && seatInput.value !== "COMPANION") count++;
                }
            }
            const btn = document.getElementById('submitBtn');
            if(count === totalPassengers) btn.disabled = false; else btn.disabled = true;
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.calc-price').forEach(sel => {
                total += parseFloat(sel.options[sel.selectedIndex].getAttribute('data-price')) || 0;
            });
            document.getElementById('displayTotal').innerText = total.toFixed(2);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            validateForm();
            <?php if ($isDelayed): ?>
            alert('⚠️ Flight Delay Notice\n\nYour flight has been delayed. Please check the updated departure time.');
            <?php endif; ?>
        });
    </script>
</body>
</html>