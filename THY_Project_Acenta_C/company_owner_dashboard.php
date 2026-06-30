<?php
//Security: Only allows access for users with the "CompanyOwner" role.
//Automation: Automatically sets past departure times to "Land" status each time the page loads.
//Data Preparation: Retrieves and prepares airport, flight, and meal lists from the database for use in sub-tabs.
//Interface Management: Includes HTML and JavaScript for switching between tabs.

if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'connecting.php';

// 1. TEMEL ROL KONTROLÜ
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'CompanyOwner') {
    header("Location: index.php");
    exit();
}

// =========================================================================
// --- KURAL 3: YAPI İZOLASYONU VE LİSANS DUVARI (YENİ EKLENEN KISIM) ---
// =========================================================================
$currentAgencyCode = AGENCY_CODE; // agency_config.php'den gelen klasör kodu (Örn: ACENTA_A)
$userCompanyId = $_SESSION['user_company_id'] ?? null; // login.php'den atadığımız kişinin yetkili olduğu şirket ID'si

// A. Şu anki klasörün (Acentanın) Merkez Veritabanındaki ID'sini bul
$sqlAgency = "SELECT CompanyID, IsActive FROM Companies_Table WHERE AgencyCode = ?";
$stmtAgency = sqlsrv_query($conn, $sqlAgency, array($currentAgencyCode));
$agencyRow = sqlsrv_fetch_array($stmtAgency, SQLSRV_FETCH_ASSOC);

if (!$agencyRow) {
    die("Sistem Hatası: Bu acenta (" . htmlspecialchars($currentAgencyCode) . ") merkez sisteme kayıtlı değil. Lütfen Admin ile iletişime geçin.");
}

$agencyCompanyID = $agencyRow['CompanyID'];

// B. ÇAPRAZ GİRİŞ KONTROLÜ (GİRİŞ YAPAN KİŞİ BURANIN SAHİBİ Mİ?)
if ($userCompanyId != $agencyCompanyID) {
    // Kişinin atandığı şirket ID'si ile sitenin ID'si uyuşmuyorsa, kaçak giriş demektir!
    // Anında oturumu sonlandır ve kapı dışarı et.
    header("Location: logout.php");
    exit();
}
// =========================================================================

$message = '';
// Check for session messages (from redirects)
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
// Check for alert messages (from redirects)
if (isset($_SESSION['alert_message'])) {
    $alertMessage = $_SESSION['alert_message'];
    unset($_SESSION['alert_message']);
} else {
    $alertMessage = '';
}
$activeTab = $_GET['tab'] ?? 'flights'; // Default tab

// Check and update flights where DepartureTime has passed
$sqlCountPastFlights = "
    SELECT COUNT(*) as CountToUpdate
    FROM Flights_Table 
    WHERE CAST(DepartureTime AS DATETIME) < CAST(GETDATE() AS DATETIME)
    AND Status NOT IN ('Land', 'Cancelled')
";
$stmtCount = sqlsrv_query($conn, $sqlCountPastFlights);
$countToUpdate = 0;
if ($stmtCount) {
    $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $countToUpdate = $row['CountToUpdate'] ?? 0;
}

// If there are flights to update, update them
if ($countToUpdate > 0) {
    $sqlUpdatePastFlights = "
        UPDATE Flights_Table 
        SET Status = 'Land'
        WHERE CAST(DepartureTime AS DATETIME) < CAST(GETDATE() AS DATETIME)
        AND Status NOT IN ('Land', 'Cancelled')
    ";
    $stmtUpdatePast = sqlsrv_query($conn, $sqlUpdatePastFlights);
    
    if ($stmtUpdatePast !== false) {
        $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $countToUpdate past flight(s) automatically updated to 'Land' status.</div>";
    } else {
        $errors = sqlsrv_errors();
        if ($errors) {
            error_log("Auto update past flights error: " . print_r($errors, true));
        }
    }
}

// Fetch Airports - Store in array for multiple use
$sqlAirports = "SELECT * FROM Airports_Table ORDER BY City";
$stmtAirports = sqlsrv_query($conn, $sqlAirports);
$airportsArray = [];
if ($stmtAirports) {
    while($a = sqlsrv_fetch_array($stmtAirports, SQLSRV_FETCH_ASSOC)) {
        $airportsArray[] = $a;
    }
}

// Fetch Planes - Store in array for multiple use
$sqlPlanes = "SELECT * FROM Planes_Table ORDER BY Model";
$stmtPlanes = sqlsrv_query($conn, $sqlPlanes);
$planesArray = [];
if ($stmtPlanes) {
    while($p = sqlsrv_fetch_array($stmtPlanes, SQLSRV_FETCH_ASSOC)) {
        $planesArray[] = $p;
    }
}

// Fetch Meal Packages
$sqlMeals = "SELECT MealID, MealName, Description, Price FROM MealPackages ORDER BY MealID";
$stmtMeals = sqlsrv_query($conn, $sqlMeals);
$mealsArray = [];
if ($stmtMeals) {
    while($m = sqlsrv_fetch_array($stmtMeals, SQLSRV_FETCH_ASSOC)) {
        $mealsArray[] = $m;
    }
}

// Güvenli Fonksiyon Tanımlaması (Çakışmaları önlemek için)
if (!function_exists('generateFlightNumber')) {
    function generateFlightNumber($conn) {
        do {
            $randomDigits = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $flightNo = 'TK' . $randomDigits;
            
            $sqlCheck = "SELECT FlightID FROM Flights_Table WHERE FlightNo = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightNo));
            if ($stmtCheck === false || !sqlsrv_has_rows($stmtCheck)) {
                return $flightNo;
            }
        } while (true);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars(AGENCY_CODE); ?> Dashboard</title>
    <link rel="stylesheet" href="css/company_owner_dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="co-header">
        <div class="co-header-left">
            <i class="fas fa-briefcase"></i>
            <strong><?php echo htmlspecialchars(AGENCY_CODE); ?> - Agency Dashboard</strong>
            <?php if ($agencyRow['IsActive'] == 0): ?>
                <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px; margin-left: 10px;">SUSPENDED</span>
            <?php endif; ?>
        </div>
        <div class="co-header-right">
            <span style="font-size: 13px;">
                Logged in as: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
            </span>
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="co-dashboard">
        <?php if(!empty($message)): ?>
            <div style="margin-bottom: 20px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($agencyRow['IsActive'] == 0): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Acentanızın yetkisi Merkez Yönetim tarafından askıya alınmıştır. Sistem üzerinden yeni bilet satışı yapamazsınız. Sadece geçmiş verilerinizi görüntüleyebilirsiniz.
            </div>
        <?php endif; ?>

        <div class="tab-header">
            <button class="tab-btn <?php echo $activeTab === 'flights' ? 'active' : ''; ?>" onclick="showTab('flights', this)">
                <i class="fas fa-plane"></i> Flight View
            </button>
            <button class="tab-btn <?php echo $activeTab === 'users_tickets' ? 'active' : ''; ?>" onclick="showTab('users_tickets', this)">
                <i class="fas fa-ticket-alt"></i> Sold Tickets
            </button>
            <button class="tab-btn <?php echo $activeTab === 'reports' ? 'active' : ''; ?>" onclick="showTab('reports', this)">
                <i class="fas fa-chart-bar"></i> Earnings & Reports
            </button>
        </div>

        <?php
        // Include tab files
        include 'co_tab_flights.php';
        include 'co_tab_users_tickets.php';
        include 'co_tab_reports.php';
        ?>

    </div>

    <script>
        // Tab switching function
        function showTab(tabName, buttonElement) {
            const allTabs = document.querySelectorAll('.tab-content');
            allTabs.forEach(tab => tab.classList.remove('active'));
            
            const allButtons = document.querySelectorAll('.tab-btn');
            allButtons.forEach(btn => btn.classList.remove('active'));
            
            const selectedTab = document.getElementById('tab-' + tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
            
            if (buttonElement) {
                buttonElement.classList.add('active');
            }
            
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'flights';
            const initialButton = document.querySelector(`.tab-btn[onclick*="'${activeTab}'"]`);
            if (initialButton) {
                showTab(activeTab, initialButton);
            }
        });
        
        <?php if(!empty($alertMessage)): ?>
        alert('<?php echo addslashes($alertMessage); ?>');
        <?php endif; ?>
    </script>

</body>
</html>