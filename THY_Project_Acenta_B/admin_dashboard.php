<?php

//This is the main page of the Admin Dashboard.
//Accessible only to authorized administrators (Admin), it is the central control point where all components of the system (Flights, Airports, Aircraft, Users, Tickets) are managed from a single location.
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}

// 1. GÜVENLİ OTURUM BAŞLATMA
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'connecting.php';

// 2. YETKİ KONTROLÜ
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$message = '';
$activeTab = $_GET['tab'] ?? 'flights';
include 'admin_functions.php';
$currentDateTime = date('Y-m-d H:i:s');

// Count how many flights need to be updated
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
} else {
    $errors = sqlsrv_errors();
    if ($errors) {
        error_log("Count past flights error: " . print_r($errors, true));
    }
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
            $errorMsg = print_r($errors, true);
            error_log("Auto update past flights error: " . $errorMsg);
            $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Error updating past flights: " . htmlspecialchars($errorMsg) . "</div>";
        }
    }
}

// Update TicketStatus to 'Used'
$sqlUpdateUsedTickets = "
    UPDATE Tickets_Table 
    SET TicketStatus = 'Used'
    WHERE TicketID IN (
        SELECT T.TicketID
        FROM Tickets_Table T
        INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
        WHERE F.ArrivalTime < GETDATE()
        AND (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled', 'Used'))
    )
";
sqlsrv_query($conn, $sqlUpdateUsedTickets);


// Fetch Airports
$sqlAirports = "SELECT * FROM Airports_Table ORDER BY City";
$stmtAirports = sqlsrv_query($conn, $sqlAirports);
$airportsArray = [];
if ($stmtAirports) {
    while($a = sqlsrv_fetch_array($stmtAirports, SQLSRV_FETCH_ASSOC)) {
        $airportsArray[] = $a;
    }
}

// Fetch Planes
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

// Fetch Baggage Packages
$sqlBaggage = "SELECT BaggageID, WeightKG, Price FROM BaggagePackages ORDER BY WeightKG, Price";
$stmtBaggage = sqlsrv_query($conn, $sqlBaggage);
$baggageArray = [];
if ($stmtBaggage) {
    while($b = sqlsrv_fetch_array($stmtBaggage, SQLSRV_FETCH_ASSOC)) {
        $baggageArray[] = $b;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - THY Project</title>
    <link rel="stylesheet" href="css/checkin_style.css">
    <link rel="stylesheet" href="css/admin_dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div style="background:#232b38; padding:15px; color:white; display:flex; align-items:center; gap:10px;">
        <i class="fas fa-user-shield"></i> 
        <strong>Admin Dashboard</strong>
        <span style="margin-left:auto; font-size:13px;">
            Logged in as: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?> (Admin)
        </span>
        <a href="admin_flights.php" style="color:#aaa; margin-left:15px; text-decoration:none;">Flight Management</a>
        <a href="index.php" style="color:#aaa; margin-left:15px; text-decoration:none;">Home</a>
        <a href="logout.php" style="color:#ffcc00; margin-left:15px; text-decoration:none;">Logout</a>
    </div>

    <div class="admin-dashboard">
        <?php if(!empty($message)): ?>
            <div style="margin-bottom: 20px; padding: 15px; border-radius: 8px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="tab-header">
            <button class="tab-btn <?php echo $activeTab === 'flights' ? 'active' : ''; ?>" onclick="showTab('flights', this)">
                <i class="fas fa-plane"></i> Flights
            </button>
            <button class="tab-btn <?php echo $activeTab === 'airports' ? 'active' : ''; ?>" onclick="showTab('airports', this)">
                <i class="fas fa-building"></i> Airports
            </button>
            <button class="tab-btn <?php echo $activeTab === 'planes' ? 'active' : ''; ?>" onclick="showTab('planes', this)">
                <i class="fas fa-plane-departure"></i> Planes
            </button>
            <button class="tab-btn <?php echo $activeTab === 'users' ? 'active' : ''; ?>" onclick="showTab('users', this)">
                <i class="fas fa-users"></i> Users
            </button>
            <button class="tab-btn <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>" onclick="showTab('tickets', this)">
                <i class="fas fa-ticket-alt"></i> Tickets
            </button>
            <button class="tab-btn <?php echo $activeTab === 'companies' ? 'active' : ''; ?>" onclick="showTab('companies', this)">
                <i class="fas fa-briefcase"></i> Companies
            </button>
        </div>

        <?php
        include 'admin_tab_flights.php';
        include 'admin_tab_airports.php';
        include 'admin_tab_planes.php';
        include 'admin_tab_users.php';
        include 'admin_tab_tickets.php';
        // YENİ EKLENEN ŞİRKETLER SEKME DOSYASI
        include 'admin_tab_companies.php';
        ?>
    </div>

    <script>
        
        function showTab(tabName, buttonElement) {
            window.history.pushState({}, '', 'admin_dashboard.php?tab=' + tabName);
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const tabElement = document.getElementById('tab-' + tabName);
            if (tabElement) {
                tabElement.classList.add('active');
            }
            if (buttonElement) {
                buttonElement.classList.add('active');
            } else {
                const btn = document.querySelector(`.tab-btn[onclick*="${tabName}"]`);
                if (btn) {
                    btn.classList.add('active');
                }
            }
        }
        
        function showEdit(type, id) {
            document.getElementById('edit_' + type + '_' + id).style.display = 'block';
            document.getElementById('view_' + type + '_' + id).style.display = 'none';
        }
        
        function cancelEdit(type, id) {
            document.getElementById('edit_' + type + '_' + id).style.display = 'none';
            document.getElementById('view_' + type + '_' + id).style.display = 'block';
            location.reload();
        }
        
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'flights';
            const btn = document.querySelector(`.tab-btn[onclick*="${tab}"]`);
            if (btn) {
                btn.click();
            } else {
                showTab(tab, null);
            }
        });
        
        <?php if(!empty($alertMessage)): ?>
        alert('<?php echo addslashes($alertMessage); ?>');
        <?php endif; ?>
    </script>

</body>
</html>