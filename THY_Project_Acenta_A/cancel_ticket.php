<?php
// Manages ticket cancellation and Infant Passenger rules via Central API.



require_once 'agency_config.php';
session_start();
$pnr = $_GET['pnr'] ?? '';
$ticketID = $_GET['ticket_id'] ?? null;
$surname = $_GET['surname'] ?? '';
$cancelSingleTicket = !empty($ticketID); 

if (empty($pnr)) {
    header("Location: index.php");
    exit();
}

$message = '';
$rezInfo = null;
$ticketInfo = null;
$babyCompanionInfo = [];
$otherPassengers = [];

// --- AŞAMA 1: SAYFA YÜKLENİRKEN VERİLERİ MERKEZDEN ÇEK (GET) ---
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
curl_close($ch);

$apiData = json_decode($response, true);

if (!$apiData || !isset($apiData['status']) || $apiData['status'] === 'error') {
    die("<h2>Unauthorized access or reservation not found.</h2><a href='javascript:history.back()'>Back</a>");
}

$rezInfo = $apiData['rezInfo'];
$passengers = $apiData['passengers'];

if ($cancelSingleTicket) {
    // İptal edilecek bileti API'den gelen listeden bul
    foreach ($passengers as $p) {
        if ($p['TicketID'] == $ticketID) {
            $ticketInfo = $p;
            break;
        }
    }

    if (!$ticketInfo) {
        header("Location: index.php?error=invalid_ticket");
        exit();
    }

    // Bebek ve Diğer Yolcu listelerini API verisinden ayrıştır (Eski SQL mantığının API versiyonu)
    foreach ($passengers as $p) {
        $isCancelled = (isset($p['TicketStatus']) && $p['TicketStatus'] === 'Cancelled');
        if (!$isCancelled && $p['TicketID'] != $ticketID) {
            if (strtolower($p['AgeType']) === 'baby' && $p['SeatNo'] === $ticketInfo['SeatNo']) {
                $babyCompanionInfo[] = $p;
            } elseif (strtolower($p['AgeType']) !== 'baby') {
                $otherPassengers[] = $p;
            }
        }
    }
}

// --- AŞAMA 2: FORM GÖNDERİLDİĞİNDE İPTALİ MERKEZE BİLDİR (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    
    $cancelPayload = [
        "pnr" => $pnr,
        "ticket_id" => $ticketID ? $ticketID : 'ALL',
        "user_id" => $_SESSION['user_id'] ?? null,
        "surname" => $surname,
        "agency" => AGENCY_CODE,
        "baby_action" => $_POST['baby_action'] ?? '',
        "new_companion_id" => $_POST['new_companion_id'] ?? ''
    ];

    $postUrl = MERKEZ_URL . "/process_cancel.php";
    $ch2 = curl_init($postUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($cancelPayload));
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $apiCevap = curl_exec($ch2);
    $api_ulasildi = !curl_errno($ch2);
    curl_close($ch2);

    $result = json_decode($apiCevap, true);

    if ($api_ulasildi && isset($result['status']) && $result['status'] === 'success') {
        $message = "<div style='color:green; padding:10px; background:#e8f5e9; border-radius:5px;'><i class='fas fa-check-circle'></i> Cancellation successful. Central system synchronized.</div>";
    } else {
        $hataDetay = $result['message'] ?? 'Merkez API işlemi reddetti.';
        $message = "<div style='color:red; padding:10px; background:#ffebee; border-radius:5px;'><i class='fas fa-times-circle'></i> Cancellation failed: " . htmlspecialchars($hataDetay) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cancel Reservation - <?php echo htmlspecialchars(AGENCY_CODE); ?></title>
    <link rel="stylesheet" href="css/checkin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/cancel_ticket_style.css">
    <script>
        // Show/hide companion dropdown when radio button is selected
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="baby_action"]');
            const companionSelect = document.getElementById('new_companion_select');
            
            if (radioButtons.length > 0 && companionSelect) {
                radioButtons.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        if (this.value === 'change_companion') {
                            companionSelect.style.display = 'block';
                            companionSelect.required = true;
                        } else {
                            companionSelect.style.display = 'none';
                            companionSelect.required = false;
                            companionSelect.value = '';
                        }
                    });
                });
            }
        });
        
        function validateBabyAction() {
            <?php if (!empty($babyCompanionInfo) && !empty($otherPassengers)): ?>
            const babyAction = document.querySelector('input[name="baby_action"]:checked');
            if (!babyAction) {
                alert('Please select an action for the baby passenger(s).');
                return false;
            }
            if (babyAction.value === 'change_companion') {
                const companionSelect = document.getElementById('new_companion_select');
                if (!companionSelect.value) {
                    alert('Please select a new companion for the baby passenger(s).');
                    return false;
                }
            }
            <?php endif; ?>
            return confirm('Are you sure you want to proceed with the cancellation?');
        }
    </script>
</head>
<body>

    <div style="background:#232b38; padding:15px; color:white; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-plane"></i> <strong><?php echo htmlspecialchars(AGENCY_CODE); ?> - Cancel Reservation</strong>
        <a href="<?php echo isset($_SESSION['user_id']) ? 'my_flights.php' : 'index.php'; ?>" style="color:#aaa; margin-left:auto; text-decoration:none;">Exit</a>
    </div>

    <div class="cancel-container">
        <div class="cancel-card">
            <h2 style="color:#dc3545;"><i class="fas fa-exclamation-triangle"></i> Cancel Reservation</h2>
            
            <?php echo $message; ?>
            
            <?php if (empty($message) || strpos($message, 'failed') !== false): ?>
                <p><strong>PNR:</strong> <?php echo htmlspecialchars($pnr); ?></p>
                
                <?php if ($cancelSingleTicket && $ticketInfo): ?>
                    <div class="warning-box">
                        <h3 style="color:#856404; margin-top:0;"><i class="fas fa-exclamation-circle"></i> Warning</h3>
                        <p>Are you sure you want to cancel this passenger's ticket?</p>
                        <p style="font-weight:bold; margin-top:10px;">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($ticketInfo['PassengerName'] . ' ' . $ticketInfo['PassengerSurname']); ?>
                        </p>
                        <p style="font-weight:bold;">This action cannot be undone.</p>
                        
                        <?php if (!empty($babyCompanionInfo)): ?>
                            <div style="background:#ffebee; border:2px solid #f44336; border-radius:6px; padding:15px; margin-top:15px; text-align:left;">
                                <h4 style="color:#c62828; margin-top:0;"><i class="fas fa-baby"></i> Baby Passenger Alert</h4>
                                <p style="color:#666; margin-bottom:10px;">
                                    This passenger is traveling with the following baby passenger(s) on the same seat:
                                </p>
                                <ul style="color:#666; margin-bottom:15px;">
                                    <?php foreach($babyCompanionInfo as $baby): ?>
                                        <li><strong><?php echo htmlspecialchars($baby['PassengerName'] . ' ' . $baby['PassengerSurname']); ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p style="color:#c62828; font-weight:bold; margin-bottom:10px;">Please choose an action for the baby passenger(s):</p>
                                
                                <div style="margin-top:15px;">
                                    <label style="display:block; margin-bottom:10px; cursor:pointer;">
                                        <input type="radio" name="baby_action" value="cancel_baby" required style="margin-right:8px;">
                                        <strong>Cancel baby's ticket(s) as well</strong>
                                        <span style="display:block; font-size:12px; color:#666; margin-left:24px;">The baby passenger(s) will also be cancelled.</span>
                                    </label>
                                    
                                    <?php if (!empty($otherPassengers)): ?>
                                        <label style="display:block; margin-bottom:10px; cursor:pointer;">
                                            <input type="radio" name="baby_action" value="change_companion" required style="margin-right:8px;">
                                            <strong>Change baby's companion</strong>
                                            <span style="display:block; font-size:12px; color:#666; margin-left:24px;">Select a new companion for the baby:</span>
                                            <select name="new_companion_id" id="new_companion_select" style="margin-top:5px; margin-left:24px; padding:5px; width:80%; display:none;" onchange="document.querySelector('input[value=\"change_companion\"]').checked = true;">
                                                <option value="">-- Select New Companion --</option>
                                                <?php foreach($otherPassengers as $other): ?>
                                                    <option value="<?php echo $other['TicketID']; ?>">
                                                        <?php echo htmlspecialchars($other['PassengerName'] . ' ' . $other['PassengerSurname'] . ' (' . $other['AgeType'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    <?php else: ?>
                                        <p style="color:#f44336; font-size:12px; margin-left:24px; margin-top:5px;">
                                            <i class="fas fa-exclamation-triangle"></i> No other passengers available. Baby ticket will be cancelled.
                                        </p>
                                        <input type="hidden" name="baby_action" value="cancel_baby">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="font-size:14px; color:#666;">Only this passenger's ticket will be cancelled. Other passengers in this reservation will not be affected.</p>
                        <?php endif; ?>
                    </div>

                    <form method="POST" id="cancelForm">
                        <input type="hidden" name="confirm_cancel" value="1">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticketID; ?>">
                        <button type="submit" class="btn-cancel" onclick="return validateBabyAction();">
                            <i class="fas fa-times-circle"></i> Yes, Cancel This Passenger's Ticket
                        </button>
                        <a href="javascript:history.back()" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </form>
                <?php else: ?>
                    <div class="warning-box">
                        <h3 style="color:#856404; margin-top:0;"><i class="fas fa-exclamation-circle"></i> Warning</h3>
                        <p>Are you sure you want to cancel this reservation?</p>
                        <p style="font-weight:bold;">This action cannot be undone.</p>
                        <p style="font-size:14px; color:#666;">All tickets in this reservation will be cancelled.</p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="confirm_cancel" value="1">
                        <button type="submit" class="btn-cancel">
                            <i class="fas fa-times-circle"></i> Yes, Cancel Reservation
                        </button>
                        <a href="javascript:history.back()" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>