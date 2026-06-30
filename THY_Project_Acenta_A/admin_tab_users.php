<?php
//This is the User Management panel. It allows administrators to change user roles, delete users, and view system-wide statistics (total users, active tickets, etc.).
//It also includes security controls that prevent the administrator from self-deleting or revoking their privileges.

// Handle Users-related form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Update User Role and CompanyID
    if ($_POST['action'] === 'update_user_role') {
        $userID = (int)($_POST['user_id'] ?? 0);
        $newRole = trim($_POST['new_role'] ?? '');
        $companyID = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;

        // SECURITY CHECK: Prevent Self-Demotion
        if ($userID > 0 && in_array($newRole, ['Admin', 'CompanyOwner', 'Passenger'])) {
            if ($userID == $_SESSION['user_id'] && $newRole !== 'Admin') {
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> You cannot change your own role from Admin.</div>";
                $activeTab = 'users';
            } else {  
                // YENİ MİMARİ: Rol ve CompanyID Güncellemesi
                if ($newRole === 'CompanyOwner' && $companyID > 0) {
                    $sql = "UPDATE Users_Table SET Role = ?, CompanyID = ? WHERE UserID = ?";
                    $params = array($newRole, $companyID, $userID);
                } else {
                    // CompanyOwner değilse veya şirket seçilmediyse CompanyID = NULL olur
                    $sql = "UPDATE Users_Table SET Role = ?, CompanyID = NULL WHERE UserID = ?";
                    $params = array($newRole, $userID);
                }
                
                $stmt = sqlsrv_query($conn, $sql, $params);
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    error_log("Role update failed: " . print_r($errors, true));
                    $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> Role update failed. Please try again.</div>";
                    $activeTab = 'users';
                } else {
                    $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> User role and company assignment updated successfully.</div>";
                    $activeTab = 'users';
                }
            }
        }
    }
    
    // Delete User
    if ($_POST['action'] === 'delete_user') {
        $userID = (int)($_POST['user_id'] ?? 0);
        // SECURITY CHECK: Prevent Self-Deletion
        if ($userID > 0 && $userID != $_SESSION['user_id']) {
            $sql = "DELETE FROM Users_Table WHERE UserID = ?";
            $stmt = sqlsrv_query($conn, $sql, array($userID));
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("User delete failed: " . print_r($errors, true));
                $message = "<div style='color:red; margin-bottom:15px;'><i class='fas fa-exclamation-triangle'></i> User delete failed. Please try again.</div>";
                $activeTab = 'users';
            } else {
                $message = "<div style='color:green; margin-bottom:15px;'><i class='fas fa-check-circle'></i> User deleted successfully.</div>";
                $activeTab = 'users';
            }
        }
    }
}

// 1. Şirketleri Çek (Açılır menü için)
$sqlCompanies = "SELECT CompanyID, CompanyName FROM Companies_Table WHERE IsActive = 1 ORDER BY CompanyName";
$stmtCompanies = sqlsrv_query($conn, $sqlCompanies);
$companiesList = [];
if ($stmtCompanies !== false) {
    while($c = sqlsrv_fetch_array($stmtCompanies, SQLSRV_FETCH_ASSOC)) {
        $companiesList[] = $c;
    }
}

// 2. Kullanıcıları Çek (Company Info ile birlikte - JOIN işlemi)
$sqlUsers = "
    SELECT U.UserID, U.Name, U.Surname, U.Email, U.Role, U.LoyaltyPoint, U.CompanyID, C.CompanyName 
    FROM Users_Table U
    LEFT JOIN Companies_Table C ON U.CompanyID = C.CompanyID
    ORDER BY U.UserID DESC
";
$stmtUsers = sqlsrv_query($conn, $sqlUsers);

$usersArray = [];
if ($stmtUsers !== false) {
    while($u = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
        $usersArray[] = $u;
    }
} else {
    $errors = sqlsrv_errors();
    if ($errors) {
        error_log("Users query error: " . print_r($errors, true));
    }
}

// Get total tickets count
$sqlTotalTickets = "SELECT COUNT(*) as TotalTickets FROM Tickets_Table";
$stmtTotalTickets = sqlsrv_query($conn, $sqlTotalTickets);
$totalTickets = 0;
if ($stmtTotalTickets) {
    $row = sqlsrv_fetch_array($stmtTotalTickets, SQLSRV_FETCH_ASSOC);
    $totalTickets = $row['TotalTickets'] ?? 0;
}

// Get open tickets count
$sqlOpenTickets = "
    SELECT COUNT(*) as OpenTickets 
    FROM Tickets_Table T
    INNER JOIN Flights_Table F ON T.FlightID = F.FlightID
    WHERE (T.TicketStatus IS NULL OR T.TicketStatus NOT IN ('Cancelled', 'Used'))
    AND F.ArrivalTime >= GETDATE()
";
$stmtOpenTickets = sqlsrv_query($conn, $sqlOpenTickets);
$openTickets = 0;
if ($stmtOpenTickets) {
    $row = sqlsrv_fetch_array($stmtOpenTickets, SQLSRV_FETCH_ASSOC);
    $openTickets = $row['OpenTickets'] ?? 0;
}
?>

<!-- USERS TAB CONTENT -->
<div id="tab-users" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
    <h2><i class="fas fa-users"></i> User Management</h2>
    
    <?php if(isset($message)) echo $message; ?>
    
    <!-- Statistics Cards -->
    <div style="background: #f0f0f0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #007bff;">
                <div style="font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px;">Total Users</div>
                <div style="font-size: 24px; font-weight: bold; color: #007bff;">
                    <?php echo count($usersArray); ?>
                </div>
            </div>
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #28a745;">
                <div style="font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px;">Open Tickets</div>
                <div style="font-size: 24px; font-weight: bold; color: #28a745;">
                    <?php echo $openTickets; ?>
                </div>
                <small style="color: #999; font-size: 10px;">Active flights</small>
            </div>
            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #232b38;">
                <div style="font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px;">Total Tickets</div>
                <div style="font-size: 24px; font-weight: bold; color: #232b38;">
                    <?php echo $totalTickets; ?>
                </div>
            </div>
        </div>
    </div>
    
    <h3>All Users (<?php echo count($usersArray); ?>)</h3>
    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 20px;">
        <?php if(!empty($usersArray)): ?>
            <?php foreach($usersArray as $u): 
                $role = $u['Role'] ?? 'Passenger';
                $roleColors = [
                    'Admin' => ['bg' => '#e3f2fd', 'border' => '#2196f3', 'text' => '#1976d2', 'icon' => 'fa-user-shield'],
                    'CompanyOwner' => ['bg' => '#fff3e0', 'border' => '#ff9800', 'text' => '#f57c00', 'icon' => 'fa-user-tie'],
                    'Passenger' => ['bg' => '#e8f5e9', 'border' => '#4caf50', 'text' => '#388e3c', 'icon' => 'fa-user']
                ];
                $roleConfig = $roleColors[$role] ?? ['bg' => '#f5f5f5', 'border' => '#9e9e9e', 'text' => '#616161', 'icon' => 'fa-user'];
                $isCurrentUser = ($u['UserID'] == $_SESSION['user_id']);
            ?>
                <div style="background: white; border: 2px solid <?php echo $roleConfig['border']; ?>; border-left: 4px solid <?php echo $roleConfig['border']; ?>; border-radius: 8px; padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; position: relative;" 
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.12)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)';">
                    
                    <!-- Role & Company Badges -->
                    <div style="position: absolute; top: 10px; right: 10px; display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                        <span style="background: <?php echo $roleConfig['bg']; ?>; color: <?php echo $roleConfig['text']; ?>; padding: 4px 10px; border-radius: 15px; font-size: 10px; font-weight: bold; display: inline-flex; align-items: center; gap: 4px; border: 1px solid <?php echo $roleConfig['border']; ?>;">
                            <i class="fas <?php echo $roleConfig['icon']; ?>"></i>
                            <?php echo htmlspecialchars($role); ?>
                        </span>
                        
                        <?php if($role === 'CompanyOwner' && !empty($u['CompanyName'])): ?>
                            <span style="background: #232b38; color: white; padding: 3px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; display: inline-flex; align-items: center; gap: 3px;">
                                <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($u['CompanyName']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Header -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding-right: 120px;">
                        <div style="background: linear-gradient(135deg, <?php echo $roleConfig['border']; ?> 0%, <?php echo $roleConfig['text']; ?> 100%); width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); flex-shrink: 0;">
                            <i class="fas <?php echo $roleConfig['icon']; ?>"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <h3 style="margin: 0; color: #232b38; font-size: 16px; font-weight: bold;">
                                <?php echo htmlspecialchars($u['Name'] . ' ' . $u['Surname']); ?>
                                <?php if($isCurrentUser): ?>
                                    <span style="font-size: 10px; color: #999; font-weight: normal;">(You)</span>
                                <?php endif; ?>
                            </h3>
                            <div style="color: #666; font-size: 12px; margin-top: 3px; display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-envelope" style="color: <?php echo $roleConfig['text']; ?>; font-size: 10px;"></i>
                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($u['Email']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Details -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 12px;">
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #007bff;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-id-card"></i> User ID
                            </div>
                            <div style="font-size: 13px; font-weight: bold; color: #232b38;">
                                #<?php echo $u['UserID']; ?>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 8px; border-radius: 6px; border-left: 2px solid #ffc107;">
                            <div style="font-size: 9px; color: #666; text-transform: uppercase; margin-bottom: 3px;">
                                <i class="fas fa-star"></i> Loyalty Points
                            </div>
                            <div style="font-size: 13px; font-weight: bold; color: #232b38;">
                                <?php echo number_format($u['LoyaltyPoint'] ?? 0, 0); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons & Role Form -->
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; padding-top: 10px; border-top: 1px solid #eee; align-items: center;">
                        <?php if(!$isCurrentUser): ?>
                            <form method="POST" action="admin_dashboard.php?tab=users" style="display:inline-flex; gap: 5px; flex: 1; align-items: center; flex-wrap: wrap;">
                                <input type="hidden" name="action" value="update_user_role">
                                <input type="hidden" name="user_id" value="<?php echo $u['UserID']; ?>">
                                
                                <!-- Role Dropdown -->
                                <select name="new_role" id="role_select_<?php echo $u['UserID']; ?>" onchange="toggleCompanySelect(<?php echo $u['UserID']; ?>)" style="padding: 6px 10px; font-size: 11px; border-radius: 4px; border: 1px solid #ddd; min-width: 120px;">
                                    <option value="Admin" <?php echo $role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="CompanyOwner" <?php echo $role === 'CompanyOwner' ? 'selected' : ''; ?>>CompanyOwner</option>
                                    <option value="Passenger" <?php echo $role === 'Passenger' ? 'selected' : ''; ?>>Passenger</option>
                                </select>

                                <!-- Company Dropdown (Sadece CompanyOwner seçilirse görünür) -->
                                <select name="company_id" id="company_select_<?php echo $u['UserID']; ?>" style="padding: 6px 10px; font-size: 11px; border-radius: 4px; border: 1px solid #ddd; min-width: 150px; display: <?php echo $role === 'CompanyOwner' ? 'block' : 'none'; ?>;">
                                    <option value="">-- Select Company --</option>
                                    <?php foreach($companiesList as $comp): ?>
                                        <option value="<?php echo $comp['CompanyID']; ?>" <?php echo ($u['CompanyID'] == $comp['CompanyID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($comp['CompanyName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Kaydet Butonu -->
                                <button type="submit" style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold;">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </form>

                            <form method="POST" action="admin_dashboard.php?tab=users" style="display:inline;" onsubmit="return confirm('Delete this user? This cannot be undone!');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $u['UserID']; ?>">
                                <button type="submit" class="btn-delete" style="background: #dc3545; color: white; border: none; padding: 6px 12px; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color: #999; font-size: 11px; padding: 5px 10px; font-weight: bold;">Current Logged In User</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; background: white; border-radius: 12px; border: 2px dashed #ddd; grid-column: 1 / -1;">
                <i class="fas fa-users" style="font-size: 64px; color: #ddd; margin-bottom: 20px;"></i>
                <p style="color: #999; font-size: 18px; margin: 0;">No users found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Rol değiştiğinde şirket seçimi kutusunu aç/kapat
function toggleCompanySelect(userId) {
    var roleSelect = document.getElementById('role_select_' + userId);
    var companySelect = document.getElementById('company_select_' + userId);
    
    if (roleSelect.value === 'CompanyOwner') {
        companySelect.style.display = 'block';
        companySelect.required = true; // Şirket seçilmesini zorunlu kıl
    } else {
        companySelect.style.display = 'none';
        companySelect.required = false;
        companySelect.value = ''; // İçini temizle
    }
}
</script>