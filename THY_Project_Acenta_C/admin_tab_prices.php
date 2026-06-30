<?php
//This section manages flight pricing strategies (Price Table). It allows managers to set specific prices for different cabin classes (Economy, Business) and age groups (Infant, Child, Adult, etc.).
// Handle Prices-related form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Add or update Flight Price
    if ($_POST['action'] === 'add_flight_price') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $cabinType = trim($_POST['cabin_type'] ?? '');
        $ageType = trim($_POST['age_type'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        
        if ($flightID > 0 && !empty($cabinType) && !empty($ageType) && $price > 0) {
            // Check if price already exists
            $sqlCheck = "SELECT PriceID FROM Price_Table WHERE FlightID = ? AND CabinType = ? AND AgeType = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightID, $cabinType, $ageType));
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                // Update existing price
                $sql = "UPDATE Price_Table SET Price = ? WHERE FlightID = ? AND CabinType = ? AND AgeType = ?";
                $stmt = sqlsrv_query($conn, $sql, array($price, $flightID, $cabinType, $ageType));
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    error_log("Price update failed: " . print_r($errors, true));
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price update failed. Please try again.</div>";
                    $activeTab = 'prices';
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Price updated successfully.</div>";
                    $activeTab = 'prices';
                }
            } else {
                // Insert new price
                $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, Price) VALUES (?, ?, ?, ?)";
                $stmt = sqlsrv_query($conn, $sql, array($flightID, $cabinType, $ageType, $price));
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price add failed: " . print_r(sqlsrv_errors(), true) . "</div>";
                    $activeTab = 'prices';
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Price added successfully.</div>";
                    $activeTab = 'prices';
                }
            }
        }
    }
    
    // Update Flight Price
    if ($_POST['action'] === 'update_flight_price') {
        $priceID = (int)($_POST['price_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        
        if ($priceID > 0 && $price > 0) {
            $sql = "UPDATE Price_Table SET Price = ? WHERE PriceID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($price, $priceID));
            if ($stmt === false) {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price update failed: " . print_r(sqlsrv_errors(), true) . "</div>";
                $activeTab = 'prices';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Price updated successfully.</div>";
                $activeTab = 'prices';
            }
        }
    }
    
    // Delete Flight Price
    if ($_POST['action'] === 'delete_flight_price') {
        $priceID = (int)($_POST['price_id'] ?? 0);
        if ($priceID > 0) {
            $sql = "DELETE FROM Price_Table WHERE PriceID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($priceID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Price delete failed: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price delete failed. Please try again.</div>";
                $activeTab = 'prices';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Price deleted successfully.</div>";
                $activeTab = 'prices';
            }
        }
    }
}

// Fetch Flight Prices for display
$sqlPrices = "
    SELECT P.PriceID, P.FlightID, P.CabinType, P.AgeType, P.Price,
           F.FlightNo, D.City as DepCity, A.City as ArrCity
    FROM Price_Table P
    INNER JOIN Flights_Table F ON P.FlightID = F.FlightID
    INNER JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
    INNER JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
    ORDER BY P.FlightID, P.CabinType, P.AgeType
";
$stmtPrices = sqlsrv_query($conn, $sqlPrices);
$pricesArray = [];
if ($stmtPrices) {
    while($p = sqlsrv_fetch_array($stmtPrices, SQLSRV_FETCH_ASSOC)) {
        $pricesArray[] = $p;
    }
}
// Checks if $flightsGrouped (from previous tab) exists to avoid redundant queries.
// Fetch Flights for dropdown (need flightsGrouped from flights tab or fetch here)
if (!isset($flightsGrouped) || empty($flightsGrouped)) {
    $sqlFlights = "
        SELECT F.FlightID, F.FlightNo, D.City as DepCity, A.City as ArrCity
        FROM Flights_Table F
        LEFT JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
        LEFT JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
        ORDER BY F.DepartureTime DESC
    ";
    $stmtFlights = sqlsrv_query($conn, $sqlFlights);
    $flightsGrouped = [];
    if ($stmtFlights) {
        while($f = sqlsrv_fetch_array($stmtFlights, SQLSRV_FETCH_ASSOC)) {
            $flightID = $f['FlightID'];
            if (!isset($flightsGrouped[$flightID])) {
                $flightsGrouped[$flightID] = $f;
            }
        }
    }
}
?>

<!-- PRICES TAB CONTENT -->
<div id="tab-prices" class="tab-content <?php echo $activeTab === 'prices' ? 'active' : ''; ?>">
    <h2><i class="fas fa-dollar-sign"></i> Flight Price Management</h2>
    
    <div class="admin-form">
        <h3>Add/Update Flight Price</h3>
        <form method="POST" action="admin_dashboard.php?tab=prices">
            <input type="hidden" name="action" value="add_flight_price">
            <div class="form-row">
                <div class="form-group">
                    <label>Flight</label>
                    <select name="flight_id" required>
                        <option value="">Select Flight</option>
                        <?php 
                        if (!empty($flightsGrouped)) {
                            foreach($flightsGrouped as $f) {
                                $depCity = $f['DepCity'] ?? 'N/A';
                                $arrCity = $f['ArrCity'] ?? 'N/A';
                                $flightNo = $f['FlightNo'] ?? 'N/A';
                                echo "<option value='{$f['FlightID']}'>$flightNo - $depCity → $arrCity</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cabin Type</label>
                    <select name="cabin_type" required>
                        <option value="">Select Cabin</option>
                        <option value="Economy">Economy</option>
                        <option value="Business">Business</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Age Type</label>
                    <select name="age_type" required>
                        <option value="">Select Age Type</option>
                        <option value="Baby">Baby (0-1)</option>
                        <option value="Child">Child (2-11)</option>
                        <option value="Teen">Teen (12-24)</option>
                        <option value="Adult">Adult (25-64)</option>
                        <option value="Old">Old (65+)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (₺)</label>
                    <input type="number" name="price" required min="0" step="0.01" placeholder="500.00">
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Add/Update Price</button>
        </form>
    </div>

    <h3>All Flight Prices</h3>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Flight</th>
                    <th>Route</th>
                    <th>Cabin Type</th>
                    <th>Age Type</th>
                    <th>Price (₺)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($pricesArray)): ?>
                    <?php foreach($pricesArray as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['FlightNo']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['DepCity']); ?> → <?php echo htmlspecialchars($p['ArrCity']); ?></td>
                            <td><?php echo htmlspecialchars($p['CabinType']); ?></td>
                            <td><?php echo htmlspecialchars($p['AgeType']); ?></td>
                            <td>
                                <form method="POST" action="admin_dashboard.php?tab=prices" style="display:inline-flex; gap:5px; align-items:center;">
                                    <input type="hidden" name="action" value="update_flight_price">
                                    <input type="hidden" name="price_id" value="<?php echo $p['PriceID']; ?>">
                                    <input type="number" name="price" value="<?php echo number_format($p['Price'], 2, '.', ''); ?>" step="0.01" min="0" style="width:100px; padding:4px;">
                                    <button type="submit" class="btn-submit" style="padding:4px 8px; font-size:11px;"><i class="fas fa-save"></i></button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="admin_dashboard.php?tab=prices" style="display:inline;" onsubmit="return confirm('Delete this price?');">
                                    <input type="hidden" name="action" value="delete_flight_price">
                                    <input type="hidden" name="price_id" value="<?php echo $p['PriceID']; ?>">
                                    <button type="submit" class="btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No prices found. Add prices for flights.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
