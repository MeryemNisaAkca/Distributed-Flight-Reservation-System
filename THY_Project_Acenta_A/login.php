<?php
//These are login page codes that allow users to log in to the system securely.
// 1. Önce acenta ayarları yüklenir (session_name belirlenir)
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}

// 2. Güvenli session başlatılır (Özel isimle çerez oluşturulur)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
include 'connecting.php';

$message = "";
// Check if the form was submitted via POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM Users_Table WHERE Email = ?";
    $params = array($email);
    $stmt = sqlsrv_query($conn, $sql, $params);
    // Error handling for database query failure
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("Login query failed: " . print_r($errors, true));
        die("An error occurred. Please try again later.");
    }

    if (sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        
        if (password_verify($password, $row['PassHash'])) {
            
            $_SESSION['user_id'] = $row['UserID'];
            $_SESSION['user_name'] = $row['Name'];
            $_SESSION['user_surname'] = $row['Surname'];
            $_SESSION['user_email'] = $row['Email'];
            $_SESSION['user_role'] = $row['Role'] ?? 'Passenger';
            
            
            $_SESSION['user_company_id'] = $row['CompanyID'] ?? null;
            // ----------------------------------------------

            if ($_SESSION['user_role'] === 'Admin') {
                header("Location: admin_dashboard.php");
            } elseif ($_SESSION['user_role'] === 'CompanyOwner') {
                header("Location: company_owner_dashboard.php");
            } else {
                header("Location: index.php"); // Normal yolcular için
            }
            exit();
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'>❌ Incorrect Password!</div>";
        }
    } else {
        $message = "<div style='color:red; margin-bottom:15px;'>❌ User not found with this email.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In - THY Project</title>
    <link rel="stylesheet" href="css/login_style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="navbar">
        <div class="navbar-left">
            <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY PROJECT</a>
            <a href="index.php">Home</a>
            <a href="found_flight.php">Flights</a>
        </div>
        <div class="navbar-right">
            <a href="login.php" style="background: rgba(255,255,255,0.1); border-radius: 4px; padding: 5px 10px;">Log In</a>
            <a href="register.php">Sign Up</a>
        </div>
    </div>

    <div class="login-container">
        <div class="login-box">
            <h2>Welcome Back</h2>
            <p>Please log in to your account</p>
            
            <?php echo $message; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your.email@example.com">
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="******">
                </div>

                <input type="submit" value="Log In">
            </form>
            
            <p style="margin-top: 20px; font-size: 13px;">
                Don't have an account? <a href="register.php" style="color: #c8102e; text-decoration: none; font-weight: bold;">Sign Up</a>
            </p>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 THY Project - All Rights Reserved.</p>
    </div>

</body>
</html>