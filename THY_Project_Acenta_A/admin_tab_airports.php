<?php
//It provides airport management. It handles adding new airports (with IATA code verification), updating, deleting, and listing airports. It also includes an algorithm that dynamically assigns colors based on country names for a more visually appealing look.
// Handle Airports-related form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Add Airport
    if ($_POST['action'] === 'add_airport') {
        $iata = strtoupper(trim($_POST['iata'] ?? ''));
        $name = trim($_POST['airport_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        if (!empty($iata) && !empty($name) && !empty($city) && !empty($country)) {
            // Check if IATA already exists
            $sqlCheck = "SELECT AirportID FROM Airports_Table WHERE IATA = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($iata));
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Error: Airport with IATA code '$iata' already exists. Please use a different code.</div>";
                $activeTab = 'airports';
            } else {
                $sql = "INSERT INTO Airports_Table (IATA, AirportName, City, Country) VALUES (?, ?, ?, ?)";
                $stmt = sqlsrv_query($conn, $sql, array($iata, $name, $city, $country));
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $errorMsg = "Airport add failed.";
                    if ($errors && isset($errors[0]['code']) && $errors[0]['code'] == 2627) {
                        $errorMsg = "Error: Airport with IATA code '$iata' already exists. Please use a different code.";
                    } else {
                        error_log("Airport add failed: " . print_r($errors, true));
                        $errorMsg = "Airport add failed. Please try again.";
                    }
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> $errorMsg</div>";
                    $activeTab = 'airports';
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Airport added successfully.</div>";
                    $activeTab = 'airports';
                }
            }
        }
    }
    
    // Update Airport
    if ($_POST['action'] === 'update_airport') {
        $airportID = (int)($_POST['airport_id'] ?? 0);
        $iata = strtoupper(trim($_POST['iata'] ?? ''));
        $name = trim($_POST['airport_name'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
        
        if ($airportID > 0 && !empty($iata) && !empty($name) && !empty($city) && !empty($country)) {
            $sql = "UPDATE Airports_Table SET IATA = ?, AirportName = ?, City = ?, Country = ? WHERE AirportID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($iata, $name, $city, $country, $airportID));
            if ($stmt === false) {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Airport update failed: " . print_r(sqlsrv_errors(), true) . "</div>";
                $activeTab = 'airports';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Airport updated successfully.</div>";
                $activeTab = 'airports';
            }
        }
    }
    
    // Delete Airport
    if ($_POST['action'] === 'delete_airport') {
        $airportID = (int)($_POST['airport_id'] ?? 0);
        if ($airportID > 0) {
            $sql = "DELETE FROM Airports_Table WHERE AirportID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($airportID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Airport delete failed: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Airport delete failed. Please try again.</div>";
                $activeTab = 'airports';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Airport deleted successfully.</div>";
                $activeTab = 'airports';
            }
        }
    }
}

// Fetch Airports data for display (refresh after POST)
$sqlAirports = "SELECT * FROM Airports_Table ORDER BY City";
$stmtAirports = sqlsrv_query($conn, $sqlAirports);
$airportsArray = [];
if ($stmtAirports) {
    while($a = sqlsrv_fetch_array($stmtAirports, SQLSRV_FETCH_ASSOC)) {
        $airportsArray[] = $a;
    }
}
?>

<!-- AIRPORTS TAB CONTENT -->
<div id="tab-airports" class="tab-content <?php echo $activeTab === 'airports' ? 'active' : ''; ?>">
    <h2><i class="fas fa-building"></i> Airport Management</h2>
    
    <div class="admin-form">
        <h3>Add New Airport</h3>
        <form method="POST" action="admin_dashboard.php?tab=airports">
            <input type="hidden" name="action" value="add_airport">
            <div class="form-row">
                <div class="form-group">
                    <label>IATA Code</label>
                    <input type="text" name="iata" required maxlength="3" placeholder="IST" style="text-transform:uppercase;" oninput="this.value = this.value.toUpperCase()">
                </div>
                <div class="form-group">
                    <label>Airport Name</label>
                    <input type="text" name="airport_name" required placeholder="Istanbul Airport">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" required placeholder="Istanbul">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country" required placeholder="Turkey">
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Add Airport</button>
        </form>
    </div>

    <h3>All Airports</h3>
    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
        <?php if(!empty($airportsArray)): ?>
            <?php foreach($airportsArray as $a): 
                // Generate color based on country (consistent colors)
                $countryHash = crc32($a['Country']);
                $colors = [
                    ['bg' => '#e3f2fd', 'border' => '#2196f3', 'text' => '#1976d2'],
                    ['bg' => '#fff3e0', 'border' => '#ff9800', 'text' => '#f57c00'],
                    ['bg' => '#e8f5e9', 'border' => '#4caf50', 'text' => '#388e3c'],
                    ['bg' => '#fce4ec', 'border' => '#e91e63', 'text' => '#c2185b'],
                    ['bg' => '#f3e5f5', 'border' => '#9c27b0', 'text' => '#7b1fa2'],
                    ['bg' => '#e0f2f1', 'border' => '#009688', 'text' => '#00796b']
                ];
                $airportConfig = $colors[abs($countryHash) % count($colors)];
            ?>
                <div style="background: white; border: 2px solid <?php echo $airportConfig['border']; ?>; border-left: 4px solid <?php echo $airportConfig['border']; ?>; border-radius: 8px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; position: relative;" 
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    
                    <!-- Airport Header -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                        <div style="background: linear-gradient(135deg, <?php echo $airportConfig['border']; ?> 0%, <?php echo $airportConfig['text']; ?> 100%); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); flex-shrink: 0;">
                            <i class="fas fa-building"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <h3 style="margin: 0; color: #232b38; font-size: 16px; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                                <span style="background: <?php echo $airportConfig['bg']; ?>; color: <?php echo $airportConfig['text']; ?>; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                    <?php echo htmlspecialchars($a['IATA']); ?>
                                </span>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($a['AirportName']); ?></span>
                            </h3>
                            <div style="color: #666; font-size: 12px; margin-top: 3px; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-map-marker-alt" style="color: <?php echo $airportConfig['text']; ?>; font-size: 10px;"></i>
                                <span><?php echo htmlspecialchars($a['City']); ?>, <?php echo htmlspecialchars($a['Country']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Airport Details -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px;">
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #007bff;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-city"></i> City
                            </div>
                            <div style="font-size: 13px; font-weight: bold; color: #232b38;">
                                <?php echo htmlspecialchars($a['City']); ?>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #6c757d;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-globe"></i> Country
                            </div>
                            <div style="font-size: 13px; font-weight: bold; color: #232b38;">
                                <?php echo htmlspecialchars($a['Country']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 6px; padding-top: 10px; border-top: 1px solid #eee;">
                        <form method="POST" action="admin_dashboard.php?tab=airports" style="display:inline; flex: 1;" onsubmit="return confirm('Delete this airport?');">
                            <input type="hidden" name="action" value="delete_airport">
                            <input type="hidden" name="airport_id" value="<?php echo $a['AirportID']; ?>">
                            <button type="submit" class="btn-delete" style="padding: 5px 10px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; width: 100%; justify-content: center;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 12px; border: 2px dashed #ddd; grid-column: 1 / -1;">
                <i class="fas fa-building" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                <p style="color: #999; font-size: 18px; margin: 0;">No airports found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
