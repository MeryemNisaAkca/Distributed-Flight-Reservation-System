<?php
//It is the most comprehensive part of the flight management system. 
// It includes adding, updating, and deleting flights, as well as managing relational data such as meals, baggage, and pricing.
// Handle Flights-related form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Add new Flight
    if ($_POST['action'] === 'add_flight') {
        // Auto-generate Flight Number if not provided or empty
        $flightNo = trim($_POST['flight_no'] ?? '');
        if (empty($flightNo)) {
            $flightNo = generateFlightNumber($conn);
        }
        
        $planeID = (int)($_POST['plane_id'] ?? 0);
        $depAirportID = (int)($_POST['dep_airport_id'] ?? 0);
        $arrAirportID = (int)($_POST['arr_airport_id'] ?? 0);
        $depTime = $_POST['dep_time'] ?? '';
        $arrTime = $_POST['arr_time'] ?? '';
        $status = $_POST['status'] ?? 'Planned';
        
        // Convert datetime-local format (YYYY-MM-DDTHH:MM) to SQL Server format (YYYY-MM-DD HH:MM:SS)
        $depTime = str_replace('T', ' ', $depTime);
        if (strlen($depTime) == 16) {
            $depTime .= ':00';
        }
        $arrTime = str_replace('T', ' ', $arrTime);
        if (strlen($arrTime) == 16) {
            $arrTime .= ':00';
        }
        
        if (!empty($flightNo) && $planeID > 0 && $depAirportID > 0 && $arrAirportID > 0 && !empty($depTime) && !empty($arrTime)) {
            // Check if departure time is in the past, and prevent adding flight in the past
            $depDateTime = new DateTime($depTime);
            $currentDateTime = new DateTime();
            if ($depDateTime < $currentDateTime) {
                $_SESSION['alert_message'] = "Cannot add flights with departure time in the past. Please select a future date and time.";
                $activeTab = 'flights';
                // Use JavaScript redirect instead of header() since we're in an included file
                echo "<script>window.location.href = 'admin_dashboard.php?tab=flights';</script>";
                exit();
            } else {
                // Check if FlightNo already exists
                $sqlCheck = "SELECT FlightID FROM Flights_Table WHERE FlightNo = ?";
                $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightNo));
                if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Flight add failed: Flight number '$flightNo' already exists. Please use a different flight number.</div>";
                    $activeTab = 'flights';
                } else {
                // Insert flight
                $sql = "INSERT INTO Flights_Table (FlightNo, PlaneID, DepartureAirportID, ArrivalAirportID, DepartureTime, ArrivalTime, Status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $params = array($flightNo, $planeID, $depAirportID, $arrAirportID, $depTime, $arrTime, $status);
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Flight add failed: " . print_r(sqlsrv_errors(), true) . "</div>";
                    $activeTab = 'flights';
                } else {
                    // Get the new FlightID
                    $sqlIdentity = "SELECT @@IDENTITY AS LastID";
                    $stmtIdentity = sqlsrv_query($conn, $sqlIdentity);
                    $rowIdentity = sqlsrv_fetch_array($stmtIdentity, SQLSRV_FETCH_ASSOC);
                    $newFlightID = $rowIdentity['LastID'];
                    
                    // Automatically add default meals and baggage to the new flight
                    if ($newFlightID > 0) {
                        // Default baggage: 15kg (free), 20kg (150tl), 25kg (250tl), 30kg (400tl)
                        $defaultBaggage = [
                            ['WeightKG' => 15, 'Price' => 0],
                            ['WeightKG' => 20, 'Price' => 150],
                            ['WeightKG' => 25, 'Price' => 250],
                            ['WeightKG' => 30, 'Price' => 400]
                        ];
                        
                        foreach($defaultBaggage as $db) {
                            // Check if this baggage option exists
                            $sqlFindBaggage = "SELECT BaggageID FROM BaggagePackages WHERE WeightKG = ? AND Price = ?";
                            $stmtFind = sqlsrv_query($conn, $sqlFindBaggage, array($db['WeightKG'], $db['Price']));
                            $baggageID = null;
                            
                            if ($stmtFind && sqlsrv_has_rows($stmtFind)) {
                                $baggageRow = sqlsrv_fetch_array($stmtFind, SQLSRV_FETCH_ASSOC);
                                $baggageID = $baggageRow['BaggageID'];
                            } else {
                                // Create the baggage option if it doesn't exist
                                $sqlInsertBaggage = "INSERT INTO BaggagePackages (WeightKG, Price) VALUES (?, ?)";
                                $stmtInsert = sqlsrv_query($conn, $sqlInsertBaggage, array($db['WeightKG'], $db['Price']));
                                if ($stmtInsert !== false) {
                                    $sqlIdentity = "SELECT @@IDENTITY AS LastID";
                                    $stmtIdentity = sqlsrv_query($conn, $sqlIdentity);
                                    $rowIdentity = sqlsrv_fetch_array($stmtIdentity, SQLSRV_FETCH_ASSOC);
                                    $baggageID = $rowIdentity['LastID'];
                                }
                            }
                            
                            // Add to flight if FlightBaggageOptions table exists
                            if ($baggageID) {
                                $sqlCheck = "SELECT FlightBaggageOptionID FROM FlightBaggageOptions WHERE FlightID = ? AND BaggageID = ?";
                                $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($newFlightID, $baggageID));
                                if ($stmtCheck !== false && !sqlsrv_has_rows($stmtCheck)) {
                                    $sqlBaggage = "INSERT INTO FlightBaggageOptions (FlightID, BaggageID) VALUES (?, ?)";
                                    sqlsrv_query($conn, $sqlBaggage, array($newFlightID, $baggageID));
                                }
                            }
                        }
                        
                        // Fetch all meals from MealPackages table
                        $sqlMeals = "SELECT MealID, MealName, Description, Price FROM MealPackages ORDER BY MealID";
                        $stmtMeals = sqlsrv_query($conn, $sqlMeals);
                        $mealsArray = [];
                        if ($stmtMeals) {
                            while($m = sqlsrv_fetch_array($stmtMeals, SQLSRV_FETCH_ASSOC)) {
                                $mealsArray[] = $m;
                            }
                        }
                        
                        // Add all meals from MealPackages to the flight
                        if (!empty($mealsArray)) {
                            foreach($mealsArray as $m) {
                                $sqlCheck = "SELECT FlightMealOptionID FROM FlightMealOptions WHERE FlightID = ? AND MealID = ?";
                                $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($newFlightID, $m['MealID']));
                                if ($stmtCheck !== false && !sqlsrv_has_rows($stmtCheck)) {
                                    $sqlMeal = "INSERT INTO FlightMealOptions (FlightID, MealID) VALUES (?, ?)";
                                    sqlsrv_query($conn, $sqlMeal, array($newFlightID, $m['MealID']));
                                }
                            }
                        }
                    }
                    
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Flight added successfully. Default meals and baggage options (15kg free, 20kg 150tl, 25kg 250tl, 30kg 400tl) have been automatically added.</div>";
                    $activeTab = 'flights';
                }
                }
            }
        }
    }

    
    // Update Flight
    if ($_POST['action'] === 'update_flight') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $flightNo = trim($_POST['flight_no'] ?? '');
        $planeID = (int)($_POST['plane_id'] ?? 0);
        $depAirportID = (int)($_POST['dep_airport_id'] ?? 0);
        $arrAirportID = (int)($_POST['arr_airport_id'] ?? 0);
        $depTime = $_POST['dep_time'] ?? '';
        $arrTime = $_POST['arr_time'] ?? '';
        $status = $_POST['status'] ?? 'Planned';
        
        // If status is Delayed, check if departure time is provided (BEFORE format conversion)
        if ($status === 'Delayed') {
            $depTimeOriginal = trim($depTime);
            if (empty($depTimeOriginal) || $depTimeOriginal === '') {
                $_SESSION['alert_message'] = "Departure time is required when status is set to 'Delayed'. Please enter the new departure time.";
                echo "<script>window.location.href = 'admin_dashboard.php?tab=flights';</script>";
                exit();
            }
        }
        
        // Convert datetime-local format to SQL Server format
        $depTime = str_replace('T', ' ', $depTime);
        if (strlen($depTime) == 16) {
            $depTime .= ':00';
        }
        $arrTime = str_replace('T', ' ', $arrTime);
        if (strlen($arrTime) == 16) {
            $arrTime .= ':00';
        }
        
        // Double check for Delayed status after format conversion
        if ($status === 'Delayed') {
            $depTimeCheck = trim($depTime);
            if (empty($depTimeCheck) || $depTimeCheck === '' || $depTimeCheck === ':00') {
                $_SESSION['alert_message'] = "Departure time is required when status is set to 'Delayed'. Please enter the new departure time.";
                echo "<script>window.location.href = 'admin_dashboard.php?tab=flights';</script>";
                exit();
            }
        }
        
        if ($flightID > 0 && !empty($flightNo) && $planeID > 0 && $depAirportID > 0 && $arrAirportID > 0 && !empty($depTime) && !empty($arrTime)) {
            // Update flight (no past date check for updates - allows updating existing flights)
            $sql = "UPDATE Flights_Table SET FlightNo = ?, PlaneID = ?, DepartureAirportID = ?, ArrivalAirportID = ?, DepartureTime = ?, ArrivalTime = ? WHERE FlightID = ?";
            $params = array($flightNo, $planeID, $depAirportID, $arrAirportID, $depTime, $arrTime, $flightID);
            
            $stmt = sqlsrv_query($conn, $sql, $params);

            $sqlSP = "{CALL UP_UpdateFlightStatus(?, ?)}";
            $paramsSP = array($flightID, $status);
    
            $stmtSP = sqlsrv_query($conn, $sqlSP, $paramsSP);
            if ($stmt === false || $stmtSP === false) {
                $errors = sqlsrv_errors();
                error_log("Flight update failed: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Flight update failed. Please try again.</div>";
                $activeTab = 'flights';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Flight updated successfully.</div>";
                $activeTab = 'flights';
            }
        } else {
            // Validation failed
            if ($status === 'Delayed') {
                $_SESSION['alert_message'] = "Departure time is required when status is set to 'Delayed'. Please enter the new departure time.";
            } else {
                $_SESSION['alert_message'] = "Please fill in all required fields.";
            }
            echo "<script>window.location.href = 'admin_dashboard.php?tab=flights';</script>";
            exit();
        }
    }
    
    // Delete Flight
    if ($_POST['action'] === 'delete_flight') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        if ($flightID > 0) {
            $sql = "DELETE FROM Flights_Table WHERE FlightID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($flightID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Flight delete failed: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Flight delete failed. Please try again.</div>";
                $activeTab = 'flights';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Flight deleted successfully.</div>";
                $activeTab = 'flights';
            }
        }
    }
    
    // Add Meal to Flight
    if ($_POST['action'] === 'add_flight_meal') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $mealID = (int)($_POST['meal_id'] ?? 0);
        
        if ($flightID > 0 && $mealID > 0) {
            $sqlCheck = "SELECT FlightMealOptionID FROM FlightMealOptions WHERE FlightID = ? AND MealID = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightID, $mealID));
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                $message = "<div style='color:orange; margin-bottom:15px;'><i class='fas fa-info-circle'></i> This meal is already available for this flight.</div>";
            } else {
                $sql = "INSERT INTO FlightMealOptions (FlightID, MealID) VALUES (?, ?)";
                $stmt = sqlsrv_query($conn, $sql, array($flightID, $mealID));
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to add meal. Make sure FlightMealOptions table exists. Run create_flight_meal_baggage_tables.sql first.</div>";
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Meal added to flight successfully.</div>";
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Remove Meal from Flight
    if ($_POST['action'] === 'remove_flight_meal') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $mealID = (int)($_POST['meal_id'] ?? 0);
        
        if ($flightID > 0 && $mealID > 0) {
            $sql = "DELETE FROM FlightMealOptions WHERE FlightID = ? AND MealID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($flightID, $mealID));
            if ($stmt === false) {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to remove meal: " . print_r(sqlsrv_errors(), true) . "</div>";
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Meal removed from flight successfully.</div>";
            }
            $activeTab = 'flights';
        }
    }
    
    // Add Baggage to Flight
    if ($_POST['action'] === 'add_flight_baggage') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $baggageID = (int)($_POST['baggage_id'] ?? 0);
        
        if ($flightID > 0 && $baggageID > 0) {
            $sqlCheck = "SELECT FlightBaggageOptionID FROM FlightBaggageOptions WHERE FlightID = ? AND BaggageID = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightID, $baggageID));
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                $message = "<div style='color:orange; margin-bottom:15px;'><i class='fas fa-info-circle'></i> This baggage is already available for this flight.</div>";
            } else {
                $sql = "INSERT INTO FlightBaggageOptions (FlightID, BaggageID) VALUES (?, ?)";
                $stmt = sqlsrv_query($conn, $sql, array($flightID, $baggageID));
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to add baggage. Make sure FlightBaggageOptions table exists. Run create_flight_meal_baggage_tables.sql first.</div>";
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Baggage added to flight successfully.</div>";
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Remove Baggage from Flight
    if ($_POST['action'] === 'remove_flight_baggage') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $baggageID = (int)($_POST['baggage_id'] ?? 0);
        
        if ($flightID > 0 && $baggageID > 0) {
            $sql = "DELETE FROM FlightBaggageOptions WHERE FlightID = ? AND BaggageID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($flightID, $baggageID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Failed to remove baggage: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to remove baggage. Please try again.</div>";
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Baggage removed from flight successfully.</div>";
            }
            $activeTab = 'flights';
        }
    }
    
    // Add New Meal to MealPackages
    if ($_POST['action'] === 'add_new_meal') {
        $mealName = trim($_POST['meal_name'] ?? '');
        $description = trim($_POST['meal_description'] ?? '');
        $price = (float)($_POST['meal_price'] ?? 0);
        
        if (!empty($mealName) && $price >= 0) {
            $sql = "INSERT INTO MealPackages (MealName, Description, Price) VALUES (?, ?, ?)";
            $stmt = sqlsrv_query($conn, $sql, array($mealName, $description, $price));
            if ($stmt === false) {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to add meal: " . print_r(sqlsrv_errors(), true) . "</div>";
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Meal added successfully.</div>";
                // Refresh meals array
                $sqlMeals = "SELECT MealID, MealName, Description, Price FROM MealPackages ORDER BY MealID";
                $stmtMeals = sqlsrv_query($conn, $sqlMeals);
                $mealsArray = [];
                if ($stmtMeals) {
                    while($m = sqlsrv_fetch_array($stmtMeals, SQLSRV_FETCH_ASSOC)) {
                        $mealsArray[] = $m;
                    }
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Update Meal Price
    if ($_POST['action'] === 'update_meal_price') {
        $mealID = (int)($_POST['meal_id'] ?? 0);
        $price = (float)($_POST['meal_price'] ?? 0);
        
        if ($mealID > 0 && $price >= 0) {
            $sql = "UPDATE MealPackages SET Price = ? WHERE MealID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($price, $mealID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Failed to update meal price: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to update meal price. Please try again.</div>";
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Meal price updated successfully.</div>";
                // Refresh meals array
                $sqlMeals = "SELECT MealID, MealName, Description, Price FROM MealPackages ORDER BY MealID";
                $stmtMeals = sqlsrv_query($conn, $sqlMeals);
                $mealsArray = [];
                if ($stmtMeals) {
                    while($m = sqlsrv_fetch_array($stmtMeals, SQLSRV_FETCH_ASSOC)) {
                        $mealsArray[] = $m;
                    }
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Add New Baggage to BaggagePackages
    if ($_POST['action'] === 'add_new_baggage') {
        $weightKG = (int)($_POST['baggage_weight'] ?? 0);
        $price = (float)($_POST['baggage_price'] ?? 0);
        
        if ($weightKG > 0 && $price >= 0) {
            // Check if this weight already exists
            $sqlCheck = "SELECT BaggageID FROM BaggagePackages WHERE WeightKG = ?";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($weightKG));
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                $message = "<div style='color:orange; margin-bottom:15px;'><i class='fas fa-info-circle'></i> Baggage option with {$weightKG}kg already exists. You can update its price instead.</div>";
            } else {
                $sql = "INSERT INTO BaggagePackages (WeightKG, Price) VALUES (?, ?)";
                $stmt = sqlsrv_query($conn, $sql, array($weightKG, $price));
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to add baggage: " . print_r(sqlsrv_errors(), true) . "</div>";
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Baggage option added successfully.</div>";
                    // Refresh baggage array
                    $sqlBaggage = "SELECT BaggageID, WeightKG, Price FROM BaggagePackages ORDER BY WeightKG, Price";
                    $stmtBaggage = sqlsrv_query($conn, $sqlBaggage);
                    $baggageArray = [];
                    if ($stmtBaggage) {
                        while($b = sqlsrv_fetch_array($stmtBaggage, SQLSRV_FETCH_ASSOC)) {
                            $baggageArray[] = $b;
                        }
                    }
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Update Baggage Price
    if ($_POST['action'] === 'update_baggage_price') {
        $baggageID = (int)($_POST['baggage_id'] ?? 0);
        $price = (float)($_POST['baggage_price'] ?? 0);
        
        if ($baggageID > 0 && $price >= 0) {
            $sql = "UPDATE BaggagePackages SET Price = ? WHERE BaggageID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($price, $baggageID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Failed to update baggage price: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Failed to update baggage price. Please try again.</div>";
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Baggage price updated successfully.</div>";
                // Refresh baggage array
                $sqlBaggage = "SELECT BaggageID, WeightKG, Price FROM BaggagePackages ORDER BY WeightKG, Price";
                $stmtBaggage = sqlsrv_query($conn, $sqlBaggage);
                $baggageArray = [];
                if ($stmtBaggage) {
                    while($b = sqlsrv_fetch_array($stmtBaggage, SQLSRV_FETCH_ASSOC)) {
                        $baggageArray[] = $b;
                    }
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Set Flight Prices (Modal Submission)
    // Add/Update Flight Price (only Economy and Business, no AgeType)
    if ($_POST['action'] === 'add_flight_price') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $cabinType = trim($_POST['cabin_type'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        
        if ($flightID > 0 && !empty($cabinType) && in_array($cabinType, ['Economy', 'Business']) && $price > 0) {
            // Check if price already exists (for this flight and cabin type, AgeType is NULL or 'All')
            $sqlCheck = "SELECT PriceID FROM Price_Table WHERE FlightID = ? AND CabinType = ? AND (AgeType IS NULL OR AgeType = 'All')";
            $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightID, $cabinType));
            if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
                // Update existing price - Only use BasicPrice column (Price column doesn't exist)
                $row = sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC);
                $priceID = $row['PriceID'];
                $sql = "UPDATE Price_Table SET BasicPrice = ? WHERE PriceID = ?";
                $stmt = sqlsrv_query($conn, $sql, array($price, $priceID));
                if ($stmt === false) {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price update failed: " . print_r(sqlsrv_errors(), true) . "</div>";
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $cabinType price updated successfully.</div>";
                    $activeTab = 'flights';
                    echo "<script>setTimeout(function(){ window.location.href = 'admin_dashboard.php?tab=flights'; }, 500);</script>";
                }
            } else {
                // Insert new price - Use 'All' for AgeType (general pricing for Economy/Business only)
                // Only use BasicPrice column (Price column doesn't exist)
                // Try with 'All' first, if fails try NULL
                $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, BasicPrice) VALUES (?, ?, 'All', ?)";
                $stmt = sqlsrv_query($conn, $sql, array($flightID, $cabinType, $price));
                if ($stmt === false) {
                    // If 'All' fails, try NULL for AgeType
                    $errors = sqlsrv_errors();
                    error_log("Price INSERT with 'All' failed: " . print_r($errors, true));
                    $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, BasicPrice) VALUES (?, ?, NULL, ?)";
                    $stmt = sqlsrv_query($conn, $sql, array($flightID, $cabinType, $price));
                    if ($stmt === false) {
                        $errors = sqlsrv_errors();
                        error_log("Price add failed: " . print_r($errors, true));
                        $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price add failed. Please try again.</div>";
                    } else {
                        $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $cabinType price added successfully (with NULL AgeType).</div>";
                        $activeTab = 'flights';
                        echo "<script>setTimeout(function(){ window.location.href = 'admin_dashboard.php?tab=flights'; }, 500);</script>";
                    }
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $cabinType price added successfully.</div>";
                    $activeTab = 'flights';
                    echo "<script>setTimeout(function(){ window.location.href = 'admin_dashboard.php?tab=flights'; }, 500);</script>";
                }
            }
            $activeTab = 'flights';
        }
    }
    
    // Update Flight Price (inline update)
    if ($_POST['action'] === 'update_flight_price') {
        $priceID = (int)($_POST['price_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        
        if ($priceID > 0 && $price > 0) {
            // Only use BasicPrice column (Price column doesn't exist)
            $sql = "UPDATE Price_Table SET BasicPrice = ? WHERE PriceID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($price, $priceID));
            if ($stmt === false) {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Price update failed: " . print_r(sqlsrv_errors(), true) . "</div>";
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> Price updated successfully.</div>";
                $activeTab = 'flights';
                echo "<script>setTimeout(function(){ window.location.href = 'admin_dashboard.php?tab=flights'; }, 500);</script>";
            }
            $activeTab = 'flights';
        }
    }
    
    // Set Flight Prices (from modal)
    if ($_POST['action'] === 'set_flight_prices') {
        $flightID = (int)($_POST['flight_id'] ?? 0);
        $ecoPrice = trim($_POST['economy_price'] ?? '');
        $busPrice = trim($_POST['business_price'] ?? '');
        
        // Convert to float, allow empty string (0)
        $ecoPrice = $ecoPrice === '' ? 0 : (float)$ecoPrice;
        $busPrice = $busPrice === '' ? 0 : (float)$busPrice;
        
        // Debug: Log received values
        error_log("Set Prices - FlightID: $flightID, Economy: $ecoPrice, Business: $busPrice");
        
        if ($flightID > 0) {
            $success = true;
            $errorMsg = '';
            $insertedCount = 0;
            $updatedCount = 0;
            
            // Set Economy Price (only if > 0)
            if ($ecoPrice > 0) {
                // Check if price exists
                $sqlCheckEco = "SELECT PriceID FROM Price_Table WHERE FlightID = ? AND CabinType = 'Economy' AND (AgeType IS NULL OR AgeType = 'All')";
                $stmtCheckEco = sqlsrv_query($conn, $sqlCheckEco, array($flightID));
                if ($stmtCheckEco && sqlsrv_has_rows($stmtCheckEco)) {
                    $row = sqlsrv_fetch_array($stmtCheckEco, SQLSRV_FETCH_ASSOC);
                    $priceID = $row['PriceID'];
                    // Only use BasicPrice column (Price column doesn't exist)
                    $sql = "UPDATE Price_Table SET BasicPrice = ? WHERE PriceID = ?";
                    $stmt = sqlsrv_query($conn, $sql, array($ecoPrice, $priceID));
                    if ($stmt === false) {
                        $success = false;
                        $errors = sqlsrv_errors();
                        error_log("Economy price update failed: " . print_r($errors, true));
                        $errorMsg .= "Economy price update failed. ";
                        error_log("Economy UPDATE failed: " . print_r($errors, true));
                    } else {
                        $updatedCount++;
                        error_log("Economy price updated: FlightID=$flightID, Price=$ecoPrice");
                    }
                } else {
                    // Insert new price - Use 'All' for AgeType (general pricing for Economy/Business only)
                    // Only use BasicPrice column (Price column doesn't exist)
                    // Try with 'All' first, if fails try NULL
                    $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, BasicPrice) VALUES (?, 'Economy', 'All', ?)";
                    $stmt = sqlsrv_query($conn, $sql, array($flightID, $ecoPrice));
                    if ($stmt === false) {
                        // If 'All' fails, try NULL for AgeType
                        $errors = sqlsrv_errors();
                        error_log("Economy INSERT with 'All' failed: " . print_r($errors, true));
                        $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, BasicPrice) VALUES (?, 'Economy', NULL, ?)";
                        $stmt = sqlsrv_query($conn, $sql, array($flightID, $ecoPrice));
                        if ($stmt === false) {
                            $success = false;
                            $errors = sqlsrv_errors();
                            $errorMsg .= "Economy price insert failed: " . print_r($errors, true) . " ";
                            error_log("Economy INSERT with NULL also failed: " . print_r($errors, true));
                        } else {
                            $insertedCount++;
                            error_log("Economy price inserted with NULL: FlightID=$flightID, Price=$ecoPrice");
                        }
                    } else {
                        $insertedCount++;
                        error_log("Economy price inserted with 'All': FlightID=$flightID, Price=$ecoPrice");
                    }
                }
            }
            
            // Set Business Price (only if > 0)
            if ($busPrice > 0) {
                // Check if price exists
                $sqlCheckBus = "SELECT PriceID FROM Price_Table WHERE FlightID = ? AND CabinType = 'Business' AND (AgeType IS NULL OR AgeType = 'All')";
                $stmtCheckBus = sqlsrv_query($conn, $sqlCheckBus, array($flightID));
                if ($stmtCheckBus && sqlsrv_has_rows($stmtCheckBus)) {
                    $row = sqlsrv_fetch_array($stmtCheckBus, SQLSRV_FETCH_ASSOC);
                    $priceID = $row['PriceID'];
                    // Only use BasicPrice column (Price column doesn't exist)
                    $sql = "UPDATE Price_Table SET BasicPrice = ? WHERE PriceID = ?";
                    $stmt = sqlsrv_query($conn, $sql, array($busPrice, $priceID));
                    if ($stmt === false) {
                        $success = false;
                        $errors = sqlsrv_errors();
                        error_log("Business price update failed: " . print_r($errors, true));
                        $errorMsg .= "Business price update failed. ";
                        error_log("Business UPDATE failed: " . print_r($errors, true));
                    } else {
                        $updatedCount++;
                        error_log("Business price updated: FlightID=$flightID, Price=$busPrice");
                    }
                } else {
                    // Insert new price - Use 'All' for AgeType (general pricing for Economy/Business only)
                    // Try with 'All' first (most compatible)
                    // Try Price column first, if fails try BasicPrice
                    $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, Price) VALUES (?, 'Business', 'All', ?)";
                    $stmt = sqlsrv_query($conn, $sql, array($flightID, $busPrice));
                    if ($stmt === false) {
                        // Try with BasicPrice column
                        $errors = sqlsrv_errors();
                        error_log("Business INSERT with Price column failed: " . print_r($errors, true));
                        $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, BasicPrice) VALUES (?, 'Business', 'All', ?)";
                        $stmt = sqlsrv_query($conn, $sql, array($flightID, $busPrice));
                        if ($stmt === false) {
                            // If BasicPrice also fails, try NULL for AgeType
                            $errors = sqlsrv_errors();
                            error_log("Business INSERT with BasicPrice column failed: " . print_r($errors, true));
                            $sql = "INSERT INTO Price_Table (FlightID, CabinType, AgeType, BasicPrice) VALUES (?, 'Business', NULL, ?)";
                            $stmt = sqlsrv_query($conn, $sql, array($flightID, $busPrice));
                            if ($stmt === false) {
                                $success = false;
                                $errors = sqlsrv_errors();
                                $errorMsg .= "Business price insert failed: " . print_r($errors, true) . " ";
                                error_log("Business INSERT with NULL also failed: " . print_r($errors, true));
                            } else {
                                $insertedCount++;
                                error_log("Business price inserted with NULL: FlightID=$flightID, Price=$busPrice");
                            }
                        } else {
                            $insertedCount++;
                            error_log("Business price inserted with 'All' (BasicPrice): FlightID=$flightID, Price=$busPrice");
                        }
                    } else {
                        $insertedCount++;
                        error_log("Business price inserted with 'All': FlightID=$flightID, Price=$busPrice");
                    }
                }
            }
            
            if ($success) {
                $msg = "Flight prices updated successfully.";
                if ($insertedCount > 0) $msg .= " ($insertedCount price(s) inserted)";
                if ($updatedCount > 0) $msg .= " ($updatedCount price(s) updated)";
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $msg</div>";
                $activeTab = 'flights';
                // Store success flag in session for JavaScript redirect
                $_SESSION['price_update_success'] = true;
            } else {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Error: " . htmlspecialchars($errorMsg) . "</div>";
                $activeTab = 'flights';
            }
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Invalid flight ID. Received: " . htmlspecialchars($_POST['flight_id'] ?? 'empty') . "</div>";
            $activeTab = 'flights';
        }
    }
}


// Check and update flights where DepartureTime has passed
// This runs every time the flights tab is loaded
// Count how many flights need to be updated (using CAST to ensure proper comparison)
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
    
    if ($stmtUpdatePast !== false && !isset($message)) {
        // Show message if flights were updated (only if no other message exists)
        $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> $countToUpdate past flight(s) automatically updated to 'Land' status.</div>";
    } else if ($stmtUpdatePast === false) {
        // Log error
        $errors = sqlsrv_errors();
        if ($errors) {
            error_log("Auto update past flights error: " . print_r($errors, true));
        }
    }
}

// Fetch Flights data for display (refresh after POST)
$sqlFlights = "
    SELECT F.FlightID, F.FlightNo, F.DepartureTime, F.ArrivalTime, F.Status, F.PlaneID,
           D.City as DepCity, D.IATA as DepIATA, A.City as ArrCity, A.IATA as ArrIATA,
           P.Model as PlaneModel, P.SeatCapacity,dbo.FN_GetTicketCountByStatus(F.FlightID, 1) as CheckInCount
    FROM Flights_Table F
    LEFT JOIN Airports_Table D ON F.DepartureAirportID = D.AirportID
    LEFT JOIN Airports_Table A ON F.ArrivalAirportID = A.AirportID
    LEFT JOIN Planes_Table P ON F.PlaneID = P.PlaneID
    ORDER BY F.DepartureTime DESC
";
$stmtFlights = sqlsrv_query($conn, $sqlFlights);
$flightsGrouped = [];
if ($stmtFlights === false) {
    $errors = sqlsrv_errors();
    if ($errors) {
        error_log("Flights query error: " . print_r($errors, true));
        error_log("Error loading flights: " . print_r($errors, true));
        $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Error loading flights. Please refresh the page.</div>";
    }
} else {
    while($f = sqlsrv_fetch_array($stmtFlights, SQLSRV_FETCH_ASSOC)) {
        $flightID = $f['FlightID'];
        if (!isset($flightsGrouped[$flightID])) {
            $flightsGrouped[$flightID] = $f;
        }
    }
}

// Fetch Flight Meal Options
$flightMealsMap = [];
foreach($flightsGrouped as $flightID => $f) {
    $flightMealsMap[$flightID] = [];
    $sqlFlightMeals = "SELECT MealID FROM FlightMealOptions WHERE FlightID = ?";
    $stmtFlightMeals = sqlsrv_query($conn, $sqlFlightMeals, array($flightID));
    if ($stmtFlightMeals !== false) {
        while($fm = sqlsrv_fetch_array($stmtFlightMeals, SQLSRV_FETCH_ASSOC)) {
            $flightMealsMap[$flightID][] = $fm['MealID'];
        }
    }
}

// Fetch Flight Baggage Options
$flightBaggageMap = [];
foreach($flightsGrouped as $flightID => $f) {
    $flightBaggageMap[$flightID] = [];
    $sqlFlightBaggage = "SELECT BaggageID FROM FlightBaggageOptions WHERE FlightID = ?";
    $stmtFlightBaggage = sqlsrv_query($conn, $sqlFlightBaggage, array($flightID));
    if ($stmtFlightBaggage !== false) {
        while($fb = sqlsrv_fetch_array($stmtFlightBaggage, SQLSRV_FETCH_ASSOC)) {
            $flightBaggageMap[$flightID][] = $fb['BaggageID'];
        }
    }
}

// Fetch Flight Prices (only Economy and Business, AgeType is NULL or 'All')
$flightPricesMap = [];
foreach($flightsGrouped as $flightID => $f) {
    $flightPricesMap[$flightID] = [
        'Economy' => ['PriceID' => null, 'Price' => 0],
        'Business' => ['PriceID' => null, 'Price' => 0]
    ];
    // Fetch prices - prioritize AgeType = 'All' or NULL, but accept any AgeType if no general price exists
    // Try Price column first, if fails try BasicPrice
    $sqlPrices = "SELECT PriceID, CabinType, AgeType, BasicPrice FROM Price_Table WHERE FlightID = ? AND CabinType IN ('Economy', 'Business') ORDER BY CASE WHEN AgeType = 'All' OR AgeType IS NULL THEN 0 ELSE 1 END";
    $stmtPrices = sqlsrv_query($conn, $sqlPrices, array($flightID));
    if ($stmtPrices !== false) {
        while($pr = sqlsrv_fetch_array($stmtPrices, SQLSRV_FETCH_ASSOC)) {
            $cabinType = trim($pr['CabinType'] ?? '');
            if (!isset($flightPricesMap[$flightID][$cabinType])) {
                continue; // Skip if cabin type is not Economy or Business
            }
            
            // If we already have a price for this cabin type, skip (prioritize 'All' or NULL)
            if ($flightPricesMap[$flightID][$cabinType]['Price'] > 0) {
                continue;
            }
            
            $ageType = $pr['AgeType'];
            // Convert AgeType to string for comparison (handle NULL, empty, or string values)
            $ageTypeStr = ($ageType === null || $ageType === '') ? '' : trim((string)$ageType);
            
            // Get price value from BasicPrice column (Price column doesn't exist)
            $priceValue = 0;
            if (isset($pr['BasicPrice']) && $pr['BasicPrice'] !== null && $pr['BasicPrice'] > 0) {
                $priceValue = (float)$pr['BasicPrice'];
            }
            
            // Accept prices where AgeType is NULL, empty, or 'All' (general pricing)
            // Also accept any AgeType if no general price exists yet (fallback)
            if ($priceValue > 0) {
                // Prefer 'All' or NULL, but accept any if we don't have a price yet
                if ($ageTypeStr === '' || strtolower($ageTypeStr) === 'all' || $flightPricesMap[$flightID][$cabinType]['Price'] == 0) {
                    $flightPricesMap[$flightID][$cabinType] = [
                        'PriceID' => $pr['PriceID'],
                        'Price' => $priceValue
                    ];
                }
            }
        }
    }
    
}
?>

<!-- FLIGHTS TAB CONTENT -->
<div id="tab-flights" class="tab-content <?php echo $activeTab === 'flights' ? 'active' : ''; ?>">
    <h2><i class="fas fa-plane"></i> Flight Management</h2>
    
    <div class="admin-form">
        <h3>Add New Flight</h3>
        <form method="POST" action="admin_dashboard.php?tab=flights" onsubmit="return validateFlightDate(this);">
            <input type="hidden" name="action" value="add_flight">
            <div class="form-row">
                <div class="form-group">
                    <label>Flight Number <small style="color:#666;">(Auto-generated)</small></label>
                    <input type="text" name="flight_no" id="flight_no_input" readonly style="background-color:#f5f5f5; cursor:not-allowed;" placeholder="Will be auto-generated (e.g., TK1234)">
                    <small style="color:#666; display:block; margin-top:4px;">Flight number will be automatically generated (TK + 4 random digits)</small>
                </div>
                <div class="form-group">
                    <label>Plane</label>
                    <select name="plane_id" required>
                        <option value="">Select Plane</option>
                        <?php 
                        foreach($planesArray as $p) {
                            echo "<option value='{$p['PlaneID']}'>{$p['Model']} ({$p['SeatCapacity']} seats)</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Departure Airport</label>
                    <select name="dep_airport_id" required>
                        <option value="">Select Airport</option>
                        <?php 
                        foreach($airportsArray as $a) {
                            echo "<option value='{$a['AirportID']}'>{$a['City']} ({$a['IATA']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Arrival Airport</label>
                    <select name="arr_airport_id" required>
                        <option value="">Select Airport</option>
                        <?php 
                        foreach($airportsArray as $a) {
                            echo "<option value='{$a['AirportID']}'>{$a['City']} ({$a['IATA']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Departure Time</label>
                    <input type="datetime-local" name="dep_time" required>
                </div>
                <div class="form-group">
                    <label>Arrival Time</label>
                    <input type="datetime-local" name="arr_time" required>
                </div>
            </div>
            <small style="color: #666; display: block; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> New flights are automatically set to "Planned" status. You can change the status later from the flights list.</small>
            <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Add Flight</button>
        </form>
    </div>

    <h3>All Flights</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(500px, 1fr)); gap: 15px; margin-top: 20px;">
    <?php if(!empty($flightsGrouped)): ?>
        <?php foreach($flightsGrouped as $flightID => $f): 
                    // Prepare data for JSON encoding (convert DateTime to string)
                    $fForJson = $f;
                    if (isset($f['DepartureTime']) && $f['DepartureTime'] instanceof DateTime) {
                        $depTime = $f['DepartureTime']->format('Y-m-d\TH:i');
                        $fForJson['DepartureTime'] = ['date' => $f['DepartureTime']->format('Y-m-d H:i:s')];
                    } else {
                        $depTime = 'N/A';
                    }
                    if (isset($f['ArrivalTime']) && $f['ArrivalTime'] instanceof DateTime) {
                        $arrTime = $f['ArrivalTime']->format('Y-m-d\TH:i');
                        $fForJson['ArrivalTime'] = ['date' => $f['ArrivalTime']->format('Y-m-d H:i:s')];
                    } else {
                        $arrTime = 'N/A';
                    }
                    
                    // Get occupied seats based on Tickets_Table CabinType
                    // Get sold tickets count for Economy (excluding cancelled and babies - babies don't occupy seats)
                    $sqlSoldEco = "SELECT COUNT(*) as SoldCount 
                                   FROM Tickets_Table 
                                   WHERE FlightID = ? 
                                   AND CabinType = 'Economy' 
                                   AND (TicketStatus IS NULL OR TicketStatus <> 'Cancelled')
                                   AND (AgeType IS NULL OR AgeType <> 'Baby')";
                    $stmtSoldEco = sqlsrv_query($conn, $sqlSoldEco, array($flightID));
                    $occupiedEco = 0;
                    if ($stmtSoldEco) {
                        $rowSoldEco = sqlsrv_fetch_array($stmtSoldEco, SQLSRV_FETCH_ASSOC);
                        $occupiedEco = $rowSoldEco['SoldCount'] ?? 0;
                    }
                    
                    // Get sold tickets count for Business (excluding cancelled and babies)
                    $sqlSoldBus = "SELECT COUNT(*) as SoldCount 
                                   FROM Tickets_Table 
                                   WHERE FlightID = ? 
                                   AND CabinType = 'Business' 
                                   AND (TicketStatus IS NULL OR TicketStatus <> 'Cancelled')
                                   AND (AgeType IS NULL OR AgeType <> 'Baby')";
                    $stmtSoldBus = sqlsrv_query($conn, $sqlSoldBus, array($flightID));
                    $occupiedBus = 0;
                    if ($stmtSoldBus) {
                        $rowSoldBus = sqlsrv_fetch_array($stmtSoldBus, SQLSRV_FETCH_ASSOC);
                        $occupiedBus = $rowSoldBus['SoldCount'] ?? 0;
                    }
                    
                    // Get prices for this flight - Always query directly for most up-to-date prices
                    $ecoPrice = 0;
                    $busPrice = 0;
                    $ecoPriceID = null;
                    $busPriceID = null;
                    
                    // Direct query for Economy price - Get ALL prices and pick the best one
                    // Only use BasicPrice column (Price column doesn't exist)
                    $sqlEcoPrice = "SELECT PriceID, BasicPrice, AgeType FROM Price_Table WHERE FlightID = ? AND CabinType = 'Economy'";
                    $stmtEcoPrice = sqlsrv_query($conn, $sqlEcoPrice, array($flightID));
                    if ($stmtEcoPrice !== false) {
                        $bestEcoPrice = 0;
                        $bestEcoPriceID = null;
                        $bestEcoPriority = 999; // Lower is better
                        
                        while($rowEco = sqlsrv_fetch_array($stmtEcoPrice, SQLSRV_FETCH_ASSOC)) {
                            // Determine priority: 'All' or NULL = 0, others = 1
                            $ageType = $rowEco['AgeType'];
                            $ageTypeStr = ($ageType === null || $ageType === '') ? '' : trim((string)$ageType);
                            $priority = (strtolower($ageTypeStr) === 'all' || $ageTypeStr === '') ? 0 : 1;
                            
                            // Get price value from BasicPrice column
                            $priceValue = 0;
                            if (isset($rowEco['BasicPrice']) && $rowEco['BasicPrice'] !== null && $rowEco['BasicPrice'] > 0) {
                                $priceValue = (float)$rowEco['BasicPrice'];
                            }
                            
                            // Only consider positive prices
                            if ($priceValue > 0) {
                                // If this is better priority or same priority but higher price, use it
                                if ($priority < $bestEcoPriority || ($priority == $bestEcoPriority && $priceValue > $bestEcoPrice)) {
                                    $bestEcoPrice = $priceValue;
                                    $bestEcoPriceID = $rowEco['PriceID'];
                                    $bestEcoPriority = $priority;
                                }
                            }
                        }
                        
                        if ($bestEcoPrice > 0) {
                            $ecoPrice = $bestEcoPrice;
                            $ecoPriceID = $bestEcoPriceID;
                        }
                    }
                    
                    // Direct query for Business price - Get ALL prices and pick the best one
                    // Only use BasicPrice column (Price column doesn't exist)
                    $sqlBusPrice = "SELECT PriceID, BasicPrice, AgeType FROM Price_Table WHERE FlightID = ? AND CabinType = 'Business'";
                    $stmtBusPrice = sqlsrv_query($conn, $sqlBusPrice, array($flightID));
                    if ($stmtBusPrice !== false) {
                        $bestBusPrice = 0;
                        $bestBusPriceID = null;
                        $bestBusPriority = 999; // Lower is better
                        
                        while($rowBus = sqlsrv_fetch_array($stmtBusPrice, SQLSRV_FETCH_ASSOC)) {
                            // Determine priority: 'All' or NULL = 0, others = 1
                            $ageType = $rowBus['AgeType'];
                            $ageTypeStr = ($ageType === null || $ageType === '') ? '' : trim((string)$ageType);
                            $priority = (strtolower($ageTypeStr) === 'all' || $ageTypeStr === '') ? 0 : 1;
                            
                            // Get price value from BasicPrice column
                            $priceValue = 0;
                            if (isset($rowBus['BasicPrice']) && $rowBus['BasicPrice'] !== null && $rowBus['BasicPrice'] > 0) {
                                $priceValue = (float)$rowBus['BasicPrice'];
                            }
                            
                            // Only consider positive prices
                            if ($priceValue > 0) {
                                // If this is better priority or same priority but higher price, use it
                                if ($priority < $bestBusPriority || ($priority == $bestBusPriority && $priceValue > $bestBusPrice)) {
                                    $bestBusPrice = $priceValue;
                                    $bestBusPriceID = $rowBus['PriceID'];
                                    $bestBusPriority = $priority;
                                }
                            }
                        }
                        
                        if ($bestBusPrice > 0) {
                            $busPrice = $bestBusPrice;
                            $busPriceID = $bestBusPriceID;
                        }
                    }
                ?>
                    <?php
                    // Determine status colors and icons
                    $status = $f['Status'] ?? 'Planned';
                    $statusColors = [
                        'Planned' => ['bg' => '#e3f2fd', 'border' => '#2196f3', 'text' => '#1976d2', 'icon' => 'fa-calendar-check'],
                        'Delayed' => ['bg' => '#fff3e0', 'border' => '#ff9800', 'text' => '#f57c00', 'icon' => 'fa-clock'],
                        'Land' => ['bg' => '#e8f5e9', 'border' => '#4caf50', 'text' => '#388e3c', 'icon' => 'fa-check-circle'],
                        'Cancelled' => ['bg' => '#ffebee', 'border' => '#f44336', 'text' => '#d32f2f', 'icon' => 'fa-times-circle']
                    ];
                    $statusConfig = $statusColors[$status] ?? ['bg' => '#f5f5f5', 'border' => '#9e9e9e', 'text' => '#616161', 'icon' => 'fa-plane'];
                    
                    // Check if flight is in the past
                    $isPast = false;
                    if (isset($f['DepartureTime']) && $f['DepartureTime'] instanceof DateTime) {
                        $isPast = $f['DepartureTime'] < new DateTime();
                    }
                    ?>
                    <div style="background: white; border: 2px solid <?php echo $statusConfig['border']; ?>; border-left: 4px solid <?php echo $statusConfig['border']; ?>; border-radius: 8px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; position: relative;" 
                         onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                        
                        <!-- Status Badge -->
                        <div style="position: absolute; top: 10px; right: 10px;">
                            <span style="background: <?php echo $statusConfig['bg']; ?>; color: <?php echo $statusConfig['text']; ?>; padding: 4px 10px; border-radius: 15px; font-size: 10px; font-weight: bold; display: inline-flex; align-items: center; gap: 4px; border: 1px solid <?php echo $statusConfig['border']; ?>;">
                                <i class="fas <?php echo $statusConfig['icon']; ?>"></i>
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </div>
                        
                        <!-- Flight Header -->
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding-right: 100px;">
                            <div style="background: linear-gradient(135deg, <?php echo $statusConfig['border']; ?> 0%, <?php echo $statusConfig['text']; ?> 100%); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); flex-shrink: 0;">
                                <i class="fas fa-plane"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h3 style="margin: 0; color: #232b38; font-size: 16px; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                                    <span><?php echo htmlspecialchars($f['FlightNo'] ?? 'N/A'); ?></span>
                                    <?php if($isPast): ?>
                                        <span style="font-size: 10px; color: #999; font-weight: normal;">(Past)</span>
                                    <?php endif; ?>
                                </h3>
                                <div style="color: #666; font-size: 12px; margin-top: 3px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                    <i class="fas fa-map-marker-alt" style="color: <?php echo $statusConfig['text']; ?>; font-size: 10px;"></i>
                                    <strong><?php echo htmlspecialchars($f['DepIATA'] ?? 'N/A'); ?></strong>
                                    <i class="fas fa-arrow-right" style="color: #c8102e; margin: 0 4px; font-size: 10px;"></i>
                                    <strong><?php echo htmlspecialchars($f['ArrIATA'] ?? 'N/A'); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Flight Details Grid - Compact -->
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px;">
                            <!-- Departure Time -->
                            <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #007bff;">
                                <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                    <i class="fas fa-plane-departure"></i> Departure
                                </div>
                                <div style="font-size: 13px; font-weight: bold; color: #232b38;">
                                    <?php echo isset($f['DepartureTime']) && $f['DepartureTime'] instanceof DateTime ? $f['DepartureTime']->format('d M') : 'N/A'; ?>
                                </div>
                                <div style="font-size: 11px; color: #666;">
                                    <?php echo isset($f['DepartureTime']) && $f['DepartureTime'] instanceof DateTime ? $f['DepartureTime']->format('H:i') : 'N/A'; ?>
                                </div>
                            </div>
                            
                            <!-- Plane Model -->
                            <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #6c757d;">
                                <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                    <i class="fas fa-plane"></i> Aircraft
                                </div>
                                <div style="font-size: 12px; font-weight: bold; color: #232b38; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($f['PlaneModel'] ?? 'N/A'); ?>
                                </div>
                                <div style="font-size: 10px; color: #666;">
                                    <?php echo $f['SeatCapacity'] ?? 0; ?> seats
                                </div>
                            </div>
                            
                            <!-- Occupied Seats -->
                            <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #28a745;">
                                <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                    <i class="fas fa-users"></i> Occupied
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <div>
                                        <span style="font-size: 10px; color: #666;">Eco:</span>
                                        <strong style="font-size: 13px; color: #28a745; margin-left: 3px;"><?php echo $occupiedEco; ?></strong>
                                    </div>
                                    <div>
                                        <span style="font-size: 10px; color: #666;">Bus:</span>
                                        <strong style="font-size: 13px; color: #c8102e; margin-left: 3px;"><?php echo $occupiedBus; ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div style="background: #e3f2fd; padding: 8px; border-radius: 6px; border-left: 2px solid #2196f3;">
                                <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                    <i class="fas fa-clipboard-check"></i> Check-in
                                </div>
                                <div style="font-size: 13px; font-weight: bold; color: #0d47a1;">
                                    <?php echo $f['CheckInCount']; ?>
                                    <span style="font-size:10px; font-weight:normal; color:#666;">
                                        / <?php echo ($occupiedEco + $occupiedBus); ?> Passengers
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Prices -->
                            <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #ffc107;">
                                <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                    <i class="fas fa-dollar-sign"></i> Prices
                                </div>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <div>
                                        <span style="font-size: 10px; color: #666;">Eco:</span>
                                        <strong style="font-size: 13px; color: #232b38; margin-left: 3px;">
                                            <?php 
                                                if ($ecoPrice > 0) {
                                                    echo '₺' . number_format($ecoPrice, 0);
                                                } else {
                                                    echo '<span style="color:#999; font-size:10px;">-</span>';
                                                }
                                            ?>
                                        </strong>
                                    </div>
                                    <div>
                                        <span style="font-size: 10px; color: #666;">Bus:</span>
                                        <strong style="font-size: 13px; color: #232b38; margin-left: 3px;">
                                            <?php 
                                                if ($busPrice > 0) {
                                                    echo '₺' . number_format($busPrice, 0);
                                                } else {
                                                    echo '<span style="color:#999; font-size:10px;">-</span>';
                                                }
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons - Compact -->
                        <div style="display: flex; gap: 6px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid #eee;">
                            <button onclick="editFlight(<?php echo htmlspecialchars(json_encode($fForJson)); ?>)" class="btn-submit" style="padding: 5px 10px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="setFlightPrices(<?php echo $flightID; ?>, '<?php echo htmlspecialchars($f['FlightNo']); ?>', <?php echo $ecoPrice; ?>, <?php echo $busPrice; ?>)" style="padding: 5px 10px; font-size: 11px; background: #ffc107; color: #333; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-weight: bold;">
                                <i class="fas fa-dollar-sign"></i> Prices
                            </button>
                            <button onclick="manageMealBaggage(<?php echo $f['FlightID']; ?>)" style="padding: 5px 10px; font-size: 11px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-weight: bold;">
                                <i class="fas fa-utensils"></i> Meals
                            </button>
                            <form method="POST" action="admin_dashboard.php?tab=flights" style="display:inline;" onsubmit="return confirm('Delete this flight?');">
                                <input type="hidden" name="action" value="delete_flight">
                                <input type="hidden" name="flight_id" value="<?php echo $f['FlightID']; ?>">
                                <button type="submit" class="btn-delete" style="padding: 5px 10px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px; background: white; border-radius: 12px; border: 2px dashed #ddd;">
                    <i class="fas fa-plane" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                    <p style="color: #999; font-size: 18px; margin: 0;">No flights found.</p>
                </div>
            <?php endif; ?>
    </div>
</div>

<!-- Manage Meal/Baggage Modal -->
<div id="manageMealBaggageModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('manageMealBaggageModal')">&times;</span>
        <h2><i class="fas fa-utensils"></i> Manage Meals & Baggage for Flight</h2>
        <input type="hidden" id="manage_flight_id">
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
            <!-- Meals Section -->
            <div>
                <h3><i class="fas fa-utensils"></i> Available Meals (from MealPackages)</h3>
                
                <!-- Add New Meal Form -->
                <div style="background:#e8f5e9; padding:10px; border-radius:4px; margin-bottom:15px; border:1px solid #4caf50;">
                    <strong style="color:#2e7d32; font-size:13px;"><i class="fas fa-plus-circle"></i> Add New Meal</strong>
                    <form method="POST" action="admin_dashboard.php?tab=flights" style="margin-top:8px;">
                        <input type="hidden" name="action" value="add_new_meal">
                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                            <input type="text" name="meal_name" placeholder="Meal Name" required style="flex:1; min-width:120px; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                            <input type="text" name="meal_description" placeholder="Description (optional)" style="flex:1; min-width:120px; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                            <input type="number" name="meal_price" placeholder="Price (₺)" step="0.01" min="0" required style="width:80px; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                            <button type="submit" class="btn-submit" style="padding:6px 12px; font-size:11px;"><i class="fas fa-plus"></i> Add</button>
                        </div>
                    </form>
                </div>
                
                <div id="meals_list" style="max-height:400px; overflow-y:auto; border:1px solid #ddd; padding:10px; border-radius:4px;">
                    <?php if(!empty($mealsArray)): ?>
                        <?php foreach($mealsArray as $m): ?>
                            <div style="padding:10px; border-bottom:1px solid #eee; background:#f9f9f9; margin-bottom:8px; border-radius:4px;" id="meal_item_<?php echo $m['MealID']; ?>">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                    <div style="flex:1;">
                                        <strong style="color:#232b38;"><?php echo htmlspecialchars($m['MealName']); ?></strong>
                                        <?php if(!empty($m['Description'])): ?>
                                            <br><small style="color:#666; font-size:12px;"><?php echo htmlspecialchars($m['Description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" onclick="toggleFlightMeal(<?php echo $m['MealID']; ?>)" class="btn-submit" style="padding:4px 8px; font-size:11px; margin-left:5px;" id="meal_btn_<?php echo $m['MealID']; ?>">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <span style="color:#c8102e; font-weight:bold; font-size:14px;">Price:</span>
                                    <form method="POST" action="admin_dashboard.php?tab=flights" style="display:inline-flex; gap:5px; align-items:center; flex:1;">
                                        <input type="hidden" name="action" value="update_meal_price">
                                        <input type="hidden" name="meal_id" value="<?php echo $m['MealID']; ?>">
                                        <input type="number" name="meal_price" value="<?php echo number_format($m['Price'] ?? 0, 2, '.', ''); ?>" step="0.01" min="0" style="width:80px; padding:4px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                                        <span style="font-size:12px;">₺</span>
                                        <button type="submit" class="btn-submit" style="padding:4px 8px; font-size:11px;"><i class="fas fa-save"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#999; text-align:center; padding:20px;">No meals found in MealPackages table.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Baggage Section -->
            <div>
                <h3><i class="fas fa-suitcase"></i> Available Baggage (from BaggagePackages)</h3>
                
                <!-- Add New Baggage Form -->
                <div style="background:#e8f5e9; padding:10px; border-radius:4px; margin-bottom:15px; border:1px solid #4caf50;">
                    <strong style="color:#2e7d32; font-size:13px;"><i class="fas fa-plus-circle"></i> Add New Baggage</strong>
                    <form method="POST" action="admin_dashboard.php?tab=flights" style="margin-top:8px;">
                        <input type="hidden" name="action" value="add_new_baggage">
                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                            <input type="number" name="baggage_weight" placeholder="Weight (KG)" min="1" required style="width:100px; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                            <input type="number" name="baggage_price" placeholder="Price (₺)" step="0.01" min="0" required style="width:100px; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                            <button type="submit" class="btn-submit" style="padding:6px 12px; font-size:11px;"><i class="fas fa-plus"></i> Add</button>
                        </div>
                    </form>
                </div>
                
                <div id="baggage_list" style="max-height:400px; overflow-y:auto; border:1px solid #ddd; padding:10px; border-radius:4px;">
                    <?php if(!empty($baggageArray)): ?>
                        <?php foreach($baggageArray as $b): ?>
                            <div style="padding:10px; border-bottom:1px solid #eee; background:#f9f9f9; margin-bottom:8px; border-radius:4px;" id="baggage_item_<?php echo $b['BaggageID']; ?>">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                    <div style="flex:1;">
                                        <strong style="color:#232b38; font-size:16px;"><?php echo $b['WeightKG']; ?> KG</strong>
                                    </div>
                                    <button type="button" onclick="toggleFlightBaggage(<?php echo $b['BaggageID']; ?>)" class="btn-submit" style="padding:4px 8px; font-size:11px; margin-left:5px;" id="baggage_btn_<?php echo $b['BaggageID']; ?>">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                                <div style="display:flex; align-items:center; gap:5px;">
                                    <span style="color:#c8102e; font-weight:bold; font-size:14px;">Price:</span>
                                    <form method="POST" action="admin_dashboard.php?tab=flights" style="display:inline-flex; gap:5px; align-items:center; flex:1;">
                                        <input type="hidden" name="action" value="update_baggage_price">
                                        <input type="hidden" name="baggage_id" value="<?php echo $b['BaggageID']; ?>">
                                        <input type="number" name="baggage_price" value="<?php echo number_format($b['Price'] ?? 0, 2, '.', ''); ?>" step="0.01" min="0" style="width:80px; padding:4px; border:1px solid #ddd; border-radius:4px; font-size:12px;">
                                        <span style="font-size:12px;">₺</span>
                                        <button type="submit" class="btn-submit" style="padding:4px 8px; font-size:11px;"><i class="fas fa-save"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#999; text-align:center; padding:20px;">No baggage found in BaggagePackages table.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="margin-top:20px; padding:15px; background:#fff3cd; border-radius:4px; border-left:4px solid #ffc107;">
            <strong><i class="fas fa-info-circle"></i> Note:</strong>
            <p style="margin:5px 0 0 0; font-size:13px;">
                <strong>For this flight:</strong> Use the "Add" buttons to add/remove meals and baggage options for this specific flight.<br>
                <strong>Manage all meals/baggage:</strong> Use the forms above to add new meals/baggage options or update prices. These changes will apply to all flights.
            </p>
        </div>
    </div>
</div>

<!-- Edit Flight Modal -->
<div id="editFlightModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editFlightModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Flight</h2>
        <form method="POST" action="admin_dashboard.php?tab=flights" id="editFlightForm" onsubmit="return validateDelayedStatus(this);">
            <input type="hidden" name="action" value="update_flight">
            <input type="hidden" name="flight_id" id="edit_flight_id">
            <div class="form-row">
                <div class="form-group">
                    <label>Flight Number</label>
                    <input type="text" name="flight_no" id="edit_flight_no" required>
                </div>
                <div class="form-group">
                    <label>Plane</label>
                    <select name="plane_id" id="edit_plane_id" required>
                        <?php foreach($planesArray as $p): ?>
                            <option value="<?php echo $p['PlaneID']; ?>"><?php echo htmlspecialchars($p['Model']); ?> (<?php echo $p['SeatCapacity']; ?> seats)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Departure Airport</label>
                    <select name="dep_airport_id" id="edit_dep_airport_id" required>
                        <?php foreach($airportsArray as $a): ?>
                            <option value="<?php echo $a['AirportID']; ?>"><?php echo htmlspecialchars($a['City']); ?> (<?php echo htmlspecialchars($a['IATA']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Arrival Airport</label>
                    <select name="arr_airport_id" id="edit_arr_airport_id" required>
                        <?php foreach($airportsArray as $a): ?>
                            <option value="<?php echo $a['AirportID']; ?>"><?php echo htmlspecialchars($a['City']); ?> (<?php echo htmlspecialchars($a['IATA']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Departure Time <span id="dep_time_required" style="color: red; display: none;">*</span></label>
                    <input type="datetime-local" name="dep_time" id="edit_dep_time" required oninvalid="this.setCustomValidity('Departure time is required')" oninput="this.setCustomValidity('')">
                </div>
                <div class="form-group">
                    <label>Arrival Time</label>
                    <input type="datetime-local" name="arr_time" id="edit_arr_time" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" onchange="handleStatusChange()">
                        <option value="Planned">Planned</option>
                        <option value="Delayed">Delayed</option>
                        <option value="Land">Land</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <small style="color: #666; display: block; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> When status is "Delayed", update the Departure Time above with the new delayed time.</small>
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Flight</button>
        </form>
    </div>
</div>

<!-- Set Flight Prices Modal -->
<div id="setPricesModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('setPricesModal')">&times;</span>
        <h2><i class="fas fa-dollar-sign"></i> Set Flight Prices</h2>
        <p style="margin-bottom: 20px; color: #666;">
            <strong>Flight:</strong> <span id="setPrices_flight_no"></span>
        </p>
        <form method="POST" action="admin_dashboard.php?tab=flights" id="setPricesForm">
            <input type="hidden" name="action" value="set_flight_prices">
            <input type="hidden" name="flight_id" id="setPrices_flight_id">
            <div class="form-row">
                <div class="form-group">
                    <label>Economy Price (₺)</label>
                    <input type="number" name="economy_price" id="setPrices_eco_price" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Business Price (₺)</label>
                    <input type="number" name="business_price" id="setPrices_bus_price" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Prices</button>
        </form>
    </div>
</div>

<script>
    // Flights-specific JavaScript functions
    // Note: Common functions like showTab, showEdit, cancelEdit are in main admin_dashboard.php
    
    // Meals and Baggage data for JavaScript (needed for modals)
    const mealsData = <?php echo json_encode($mealsArray); ?>;
    const baggageData = <?php echo json_encode($baggageArray); ?>;
    const airportsData = <?php echo json_encode($airportsArray); ?>;
    const planesData = <?php echo json_encode($planesArray); ?>;
    const flightMealsMap = <?php echo json_encode($flightMealsMap); ?>;
    const flightBaggageMap = <?php echo json_encode($flightBaggageMap); ?>;
    
    function editFlight(flight) {
        document.getElementById('edit_flight_id').value = flight.FlightID;
        document.getElementById('edit_flight_no').value = flight.FlightNo;
        document.getElementById('edit_plane_id').value = flight.PlaneID;
        
        // Find airport IDs
        const depAirport = airportsData.find(a => a.City === flight.DepCity);
        const arrAirport = airportsData.find(a => a.City === flight.ArrCity);
        if (depAirport) document.getElementById('edit_dep_airport_id').value = depAirport.AirportID;
        if (arrAirport) document.getElementById('edit_arr_airport_id').value = arrAirport.AirportID;
        
        // Format datetime for input
        const depTime = new Date(flight.DepartureTime.date || flight.DepartureTime);
        const arrTime = new Date(flight.ArrivalTime.date || flight.ArrivalTime);
        document.getElementById('edit_dep_time').value = depTime.toISOString().slice(0, 16);
        document.getElementById('edit_arr_time').value = arrTime.toISOString().slice(0, 16);
        
        document.getElementById('edit_status').value = flight.Status || 'Planned';
        
        document.getElementById('editFlightModal').style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    function setFlightPrices(flightID, flightNo, ecoPrice, busPrice) {
        document.getElementById('setPrices_flight_id').value = flightID;
        document.getElementById('setPrices_flight_no').textContent = flightNo;
        document.getElementById('setPrices_eco_price').value = ecoPrice > 0 ? ecoPrice.toFixed(2) : '';
        document.getElementById('setPrices_bus_price').value = busPrice > 0 ? busPrice.toFixed(2) : '';
        document.getElementById('setPricesModal').style.display = 'block';
    }
    
    // Handle form submission for price setting
    document.addEventListener('DOMContentLoaded', function() {
        const setPricesForm = document.getElementById('setPricesForm');
        if (setPricesForm) {
            setPricesForm.addEventListener('submit', function(e) {
                const flightID = document.getElementById('setPrices_flight_id').value;
                const ecoPrice = document.getElementById('setPrices_eco_price').value;
                const busPrice = document.getElementById('setPrices_bus_price').value;
                
                // Validate that at least one price is entered
                if (!ecoPrice && !busPrice) {
                    e.preventDefault();
                    alert('Please enter at least one price (Economy or Business)');
                    return false;
                }
                
                // Validate that flightID exists
                if (!flightID || flightID === '0') {
                    e.preventDefault();
                    alert('Error: Flight ID is missing');
                    return false;
                }
                
                console.log('Submitting prices - FlightID:', flightID, 'Economy:', ecoPrice, 'Business:', busPrice);
            });
        }
    });
    
    function manageMealBaggage(flightID) {
        document.getElementById('manage_flight_id').value = flightID;
        
        const currentMeals = flightMealsMap[flightID] || [];
        const currentBaggage = flightBaggageMap[flightID] || [];
        
        // Update UI to show which meals from MealPackages are already added to this flight
        <?php foreach($mealsArray as $m): ?>
            const mealID_<?php echo $m['MealID']; ?> = <?php echo $m['MealID']; ?>;
            const mealBtn_<?php echo $m['MealID']; ?> = document.getElementById('meal_btn_' + mealID_<?php echo $m['MealID']; ?>);
            if (mealBtn_<?php echo $m['MealID']; ?>) {
                if (currentMeals.includes(mealID_<?php echo $m['MealID']; ?>)) {
                    mealBtn_<?php echo $m['MealID']; ?>.innerHTML = '<i class="fas fa-check"></i> Added';
                    mealBtn_<?php echo $m['MealID']; ?>.className = 'btn-delete';
                    mealBtn_<?php echo $m['MealID']; ?>.style.padding = '4px 8px';
                    mealBtn_<?php echo $m['MealID']; ?>.style.fontSize = '11px';
                } else {
                    mealBtn_<?php echo $m['MealID']; ?>.innerHTML = '<i class="fas fa-plus"></i> Add';
                    mealBtn_<?php echo $m['MealID']; ?>.className = 'btn-submit';
                    mealBtn_<?php echo $m['MealID']; ?>.style.padding = '4px 8px';
                    mealBtn_<?php echo $m['MealID']; ?>.style.fontSize = '11px';
                }
            }
        <?php endforeach; ?>
        
        // Update UI to show which baggage from BaggagePackages are already added to this flight
        <?php foreach($baggageArray as $b): ?>
            const baggageID_<?php echo $b['BaggageID']; ?> = <?php echo $b['BaggageID']; ?>;
            const baggageBtn_<?php echo $b['BaggageID']; ?> = document.getElementById('baggage_btn_' + baggageID_<?php echo $b['BaggageID']; ?>);
            if (baggageBtn_<?php echo $b['BaggageID']; ?>) {
                if (currentBaggage.includes(baggageID_<?php echo $b['BaggageID']; ?>)) {
                    baggageBtn_<?php echo $b['BaggageID']; ?>.innerHTML = '<i class="fas fa-check"></i> Added';
                    baggageBtn_<?php echo $b['BaggageID']; ?>.className = 'btn-delete';
                    baggageBtn_<?php echo $b['BaggageID']; ?>.style.padding = '4px 8px';
                    baggageBtn_<?php echo $b['BaggageID']; ?>.style.fontSize = '11px';
                } else {
                    baggageBtn_<?php echo $b['BaggageID']; ?>.innerHTML = '<i class="fas fa-plus"></i> Add';
                    baggageBtn_<?php echo $b['BaggageID']; ?>.className = 'btn-submit';
                    baggageBtn_<?php echo $b['BaggageID']; ?>.style.padding = '4px 8px';
                    baggageBtn_<?php echo $b['BaggageID']; ?>.style.fontSize = '11px';
                }
            }
        <?php endforeach; ?>
        
        document.getElementById('manageMealBaggageModal').style.display = 'block';
    }
    
    function toggleFlightMeal(mealID) {
        const flightID = document.getElementById('manage_flight_id').value;
        const btn = document.getElementById('meal_btn_' + mealID);
        const isAdded = btn.innerHTML.includes('Added');
        
        if (isAdded) {
            // Remove meal
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_dashboard.php?tab=flights';
            form.innerHTML = '<input type="hidden" name="action" value="remove_flight_meal">' +
                            '<input type="hidden" name="flight_id" value="' + flightID + '">' +
                            '<input type="hidden" name="meal_id" value="' + mealID + '">';
            document.body.appendChild(form);
            form.submit();
        } else {
            // Add meal
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_dashboard.php?tab=flights';
            form.innerHTML = '<input type="hidden" name="action" value="add_flight_meal">' +
                            '<input type="hidden" name="flight_id" value="' + flightID + '">' +
                            '<input type="hidden" name="meal_id" value="' + mealID + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function toggleFlightBaggage(baggageID) {
        const flightID = document.getElementById('manage_flight_id').value;
        const btn = document.getElementById('baggage_btn_' + baggageID);
        const isAdded = btn.innerHTML.includes('Added');
        
        if (isAdded) {
            // Remove baggage
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_dashboard.php?tab=flights';
            form.innerHTML = '<input type="hidden" name="action" value="remove_flight_baggage">' +
                            '<input type="hidden" name="flight_id" value="' + flightID + '">' +
                            '<input type="hidden" name="baggage_id" value="' + baggageID + '">';
            document.body.appendChild(form);
            form.submit();
        } else {
            // Add baggage
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_dashboard.php?tab=flights';
            form.innerHTML = '<input type="hidden" name="action" value="add_flight_baggage">' +
                            '<input type="hidden" name="flight_id" value="' + flightID + '">' +
                            '<input type="hidden" name="baggage_id" value="' + baggageID + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Update Flight Number placeholder with example (visual only, actual generation happens in PHP)
    window.addEventListener('DOMContentLoaded', function() {
        const flightNoInput = document.getElementById('flight_no_input');
        if (flightNoInput) {
            const randomNumber = Math.floor(Math.random() * 9000) + 1000; // 1000-9999
            flightNoInput.placeholder = 'Will be auto-generated (e.g., TK' + randomNumber + ')';
        }
    });
    
    // Auto-refresh page after price update
    <?php if(isset($_SESSION['price_update_success']) && $_SESSION['price_update_success']): ?>
        setTimeout(function() {
            window.location.href = 'admin_dashboard.php?tab=flights';
        }, 1000);
        <?php unset($_SESSION['price_update_success']); ?>
    <?php endif; ?>
    
    // Validate flight date before form submission (only for add flight, not update)
    function validateFlightDate(form) {
        // Only validate for add flight form, not update form
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput && actionInput.value === 'add_flight') {
            const depTimeInput = form.querySelector('input[name="dep_time"]');
            if (depTimeInput && depTimeInput.value) {
                const depDateTime = new Date(depTimeInput.value);
                const currentDateTime = new Date();
                
                if (depDateTime < currentDateTime) {
                    alert('Cannot add flights with departure time in the past. Please select a future date and time.');
                    return false; // Prevent form submission
                }
            }
        }
        return true; // Allow form submission for updates
    }
    
    // Handle status change - show/hide required indicator
    function handleStatusChange() {
        const statusSelect = document.getElementById('edit_status');
        const depTimeRequired = document.getElementById('dep_time_required');
        const depTimeInput = document.getElementById('edit_dep_time');
        
        if (statusSelect && statusSelect.value === 'Delayed') {
            if (depTimeRequired) depTimeRequired.style.display = 'inline';
            if (depTimeInput) {
                depTimeInput.required = true;
                depTimeInput.setAttribute('required', 'required');
                depTimeInput.style.borderColor = '#dc3545';
                depTimeInput.setCustomValidity('Departure time is required when status is "Delayed"');
            }
        } else {
            if (depTimeRequired) depTimeRequired.style.display = 'none';
            if (depTimeInput) {
                depTimeInput.required = true; // Still required for other statuses
                depTimeInput.setAttribute('required', 'required');
                depTimeInput.style.borderColor = '';
                depTimeInput.setCustomValidity('');
            }
        }
    }
    
    // Validate delayed status on form submission (inline function for onsubmit)
    function validateDelayedStatus(form) {
        const statusSelect = form.querySelector('select[name="status"]') || document.getElementById('edit_status');
        const depTimeInput = form.querySelector('input[name="dep_time"]') || document.getElementById('edit_dep_time');
        
        if (statusSelect && statusSelect.value === 'Delayed') {
            const depTimeValue = depTimeInput ? depTimeInput.value.trim() : '';
            if (!depTimeInput || !depTimeValue || depTimeValue === '') {
                alert('Departure time is required when status is set to "Delayed". Please enter the new departure time in the Departure Time field.');
                if (depTimeInput) {
                    depTimeInput.focus();
                    depTimeInput.style.borderColor = '#dc3545';
                    depTimeInput.style.borderWidth = '2px';
                }
                return false;
            }
        }
        return true; // Allow form submission
    }
    
    // Also add event listener as backup
    document.addEventListener('DOMContentLoaded', function() {
        const editFlightForm = document.getElementById('editFlightForm');
        if (editFlightForm) {
            editFlightForm.addEventListener('submit', function(e) {
                if (!validateDelayedStatus(this)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true); // Use capture phase to catch event early
        }
    });
</script>
