<?php

//Allows the user to update their profile information (Email and Password).
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}

// 2. Güvenli session başlatılır (Özel isimle çerez oluşturulur)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. En son yerel veritabanı bağlantısı kurulur
include_once 'connecting.php';


// Only logged-in users can access this page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['user_id'];
$currentEmail = $_SESSION['user_email'] ?? '';
$message = '';

// Get user's loyalty points
$sqlPoints = "SELECT LoyaltyPoint FROM Users_Table WHERE UserID = ?";
$stmtPoints = sqlsrv_query($conn, $sqlPoints, array($userID));
$userPoints = 0;
if ($stmtPoints) {
    $rowPoints = sqlsrv_fetch_array($stmtPoints, SQLSRV_FETCH_ASSOC);
    $userPoints = $rowPoints['LoyaltyPoint'] ?? 0;
}
//Update Profile
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newEmail = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');
    $newPasswordConfirm = trim($_POST['password_confirm'] ?? '');

    // Simple validations
    if (!empty($newPassword) && $newPassword !== $newPasswordConfirm) {
        $message = "<div style='color:red; margin-bottom:15px;'>Passwords do not match.</div>";
    } else {
        $paramEmail = !empty($newEmail) ? $newEmail : null;
        $paramPassHash = null;

        if (!empty($newPassword)) {
            $paramPassHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        // Call Stored Procedure UP_UpdateUserInformation
        $sql = "{CALL UP_UpdateUserInformation(?, ?, ?)}";
        $params = array($userID, $paramEmail, $paramPassHash);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log("Profile update failed: " . print_r($errors, true));
            $message = "<div style='color:red; margin-bottom:15px;'>Update failed. Please try again or contact support.</div>";
        } else {
            // Update session email if changed
            if (!empty($newEmail)) {
                $_SESSION['user_email'] = $newEmail;
                $currentEmail = $newEmail;
            }
            $message = "<div style='color:green; margin-bottom:15px;'>Profile updated successfully.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - THY Project</title>
    <link rel="stylesheet" href="css/login_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY PROJECT</a>
            <a href="index.php">Home</a>
            <a href="my_flights.php">My Flights</a>
        </div>
        <div class="navbar-right">
            <a href="profile.php" style="background: rgba(255,255,255,0.1); border-radius: 4px; padding: 5px 10px;">
                <i class="fas fa-user-cog"></i> My Profile
            </a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="login-container">
        <div class="login-box">
            <h2>My Profile</h2>
            <p>You can update your email and password.</p>
            
            <div style="background: linear-gradient(135deg, #c8102e 0%, #a00c24 100%); color:white; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center;">
                <div style="font-size:14px; opacity:0.9;">Loyalty Points</div>
                <div style="font-size:28px; font-weight:bold;">
                    <?php echo number_format($userPoints, 0); ?> <span style="font-size:16px;">Points</span>
                </div>
            </div>

            <?php echo $message; ?>

            <form action="profile.php" method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($currentEmail); ?>" placeholder="your.email@example.com">
                </div>

                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <div style="position:relative; width:100%;">
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            placeholder="******" 
                            style="width:100%; padding-right:40px; box-sizing:border-box;">
                        <span 
                            onclick="togglePassword('password', this)" 
                            style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#777; width:24px; text-align:center;">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div style="position:relative; width:100%;">
                        <input 
                            type="password" 
                            name="password_confirm" 
                            id="password_confirm" 
                            placeholder="******" 
                            style="width:100%; padding-right:40px; box-sizing:border-box;">
                        <span 
                            onclick="togglePassword('password_confirm', this)" 
                            style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#777; width:24px; text-align:center;">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <input type="submit" value="Save Changes">
            </form>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 THY Project - All Rights Reserved.</p>
    </div>

</body>
<script>
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>
</html>
