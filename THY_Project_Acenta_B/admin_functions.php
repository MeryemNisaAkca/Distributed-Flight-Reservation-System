<?php
// Common functions for Admin Dashboard

// 1. FORM YAKALAMA VE VERİTABANI İŞLEMLERİ (POST İşlemleri)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- A. YENİ ŞİRKET EKLEME İŞLEMİ ---
    if ($action === 'add_company') {
        $companyName = trim($_POST['company_name'] ?? '');
        $agencyCode = trim(strtoupper($_POST['agency_code'] ?? '')); // Güvenlik: Hep büyük harf yapar

        if (!empty($companyName) && !empty($agencyCode)) {
            // Önce bu Acenta Kodu ile başka bir şirket var mı diye kontrol edelim (Çakışmayı önleme)
            $checkSql = "SELECT CompanyID FROM Companies_Table WHERE AgencyCode = ?";
            $checkStmt = sqlsrv_query($conn, $checkSql, array($agencyCode));
            
            if (sqlsrv_has_rows($checkStmt)) {
                $message = "<div style='color:#721c24; background:#f8d7da; border-left:4px solid #f5c6cb; padding:15px; margin-bottom:20px; border-radius:4px;'>
                                <i class='fas fa-exclamation-triangle'></i> <b>Error:</b> The Agency Code '<strong>$agencyCode</strong>' is already in use by another company!
                            </div>";
            } else {
                // Çakışma yoksa yeni şirketi veritabanına ekle
                $sql = "INSERT INTO Companies_Table (CompanyName, AgencyCode, IsActive) VALUES (?, ?, 1)";
                $params = array($companyName, $agencyCode);
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt) {
                    $message = "<div style='color:#155724; background:#d4edda; border-left:4px solid #c3e6cb; padding:15px; margin-bottom:20px; border-radius:4px;'>
                                    <i class='fas fa-check-circle'></i> Company '<strong>$companyName</strong>' has been successfully registered!
                                </div>";
                } else {
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-times-circle'></i> Error adding company. Database issue.</div>";
                    error_log(print_r(sqlsrv_errors(), true)); // Loglara hatayı yaz
                }
            }
        }
    } 
    // --- B. ŞİRKETİ AKTİF/PASİF YAPMA (SUSPEND) İŞLEMİ ---
    elseif ($action === 'toggle_company_status') {
        $companyId = $_POST['company_id'];
        $currentStatus = $_POST['current_status'];
        
        // Mevcut durum 1 (Aktif) ise 0 (Pasif) yap, 0 ise 1 yap
        $newStatus = $currentStatus ? 0 : 1; 

        $sql = "UPDATE Companies_Table SET IsActive = ? WHERE CompanyID = ?";
        $params = array($newStatus, $companyId);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $statusText = $newStatus ? 'Activated' : 'Suspended';
            $message = "<div style='color:#155724; background:#d4edda; border-left:4px solid #c3e6cb; padding:15px; margin-bottom:20px; border-radius:4px;'>
                            <i class='fas fa-info-circle'></i> Company status successfully updated to: <b>$statusText</b>
                        </div>";
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-times-circle'></i> Error updating company status.</div>";
        }
    }
}

// 2. YARDIMCI FONKSİYONLAR


// Function to generate automatic Flight Number (TK + 4 random digits)
function generateFlightNumber($conn) {
    $maxAttempts = 10;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        // Generate TK + 4 random digits (1000-9999)
        $randomNumber = rand(1000, 9999);
        $flightNo = 'TK' . $randomNumber;
        
        // Check if this flight number already exists
        $sqlCheck = "SELECT FlightID FROM Flights_Table WHERE FlightNo = ?";
        $stmtCheck = sqlsrv_query($conn, $sqlCheck, array($flightNo));
        
        if ($stmtCheck && !sqlsrv_has_rows($stmtCheck)) {
            // Flight number is available
            return $flightNo;
        }
        
        $attempt++;
    }
    
    // If all attempts failed, return a timestamp-based number
    return 'TK' . date('His'); // TK + current time (HHMMSS)
}
?>