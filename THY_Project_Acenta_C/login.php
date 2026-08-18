<?php
//These are login page codes that allow users to log in to the system securely.
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file (agency_config.php) missing.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'connecting.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM Users_Table WHERE Email = ?";
    $params = array($email);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("Login query failed: " . print_r($errors, true));
        die("An error occurred. Please try again later.");
    }

    if (sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        // --- 🛡️ BRUTE FORCE KORUMASI BAŞLANGICI ---
        $max_attempts = 5;
        $lockout_time = 900; // 15 dakika
        $is_locked = false;

        if ($row['FailedLoginAttempts'] >= $max_attempts) {
            if ($row['LastFailedLoginTime'] !== null) {
                $last_failed_time = $row['LastFailedLoginTime'];
                if (is_object($last_failed_time)) {
                    $last_failed = $last_failed_time->getTimestamp();
                } else {
                    $last_failed = strtotime($last_failed_time);
                }

                $time_passed = time() - $last_failed;

                if ($time_passed < $lockout_time) {
                    $kalan_dakika = ceil(($lockout_time - $time_passed) / 60);
                    $message = "<div style='color:red; margin-bottom:15px; font-weight:bold;'>❌ Güvenlik Uyarısı: Çok fazla hatalı giriş yaptınız. Hesabınız kilitlendi. Lütfen $kalan_dakika dakika sonra tekrar deneyin.</div>";
                    $is_locked = true;

                    // 📝 CLOUDWATCH LOGU 1: HESAP KİLİTLENDİ
                    $log_mesaj = "[" . date('Y-m-d H:i:s') . "] GÜVENLİK İHLALİ: Hesap Kilitlendi (Brute-Force Koruması) | Email: " . $email . " | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
                    error_log($log_mesaj, 3, "/var/www/html/logs/security_audit.log");
                }
            }
        }
        // --- 🛡️ BRUTE FORCE KORUMASI BİTİŞİ ---

        if (!$is_locked) {
            if (password_verify($password, $row['PassHash'])) {
                if(isset($stmt)) { sqlsrv_free_stmt($stmt); }

                $reset_sql = "UPDATE Users_Table SET FailedLoginAttempts = 0, LastFailedLoginTime = NULL WHERE Email = ?";
                sqlsrv_query($conn, $reset_sql, array($email));

                $_SESSION['user_id'] = $row['UserID'];
                $_SESSION['user_name'] = $row['Name'];
                $_SESSION['user_surname'] = $row['Surname'];
                $_SESSION['user_email'] = $row['Email'];
                $_SESSION['user_role'] = $row['Role'] ?? 'Passenger';
                $_SESSION['user_company_id'] = $row['CompanyID'] ?? null;

                if ($_SESSION['user_role'] === 'Admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($_SESSION['user_role'] === 'CompanyOwner') {
                    header("Location: company_owner_dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                if(isset($stmt)) { sqlsrv_free_stmt($stmt); }

                // 🛠️ MİMARİ DÜZELTME: MS SQL GETDATE() yerine PHP'nin kendi saatini kullanıyoruz!
                $su_an = date('Y-m-d H:i:s');
                $fail_sql = "UPDATE Users_Table SET FailedLoginAttempts = FailedLoginAttempts + 1, LastFailedLoginTime = ? WHERE Email = ?";
                $fail_stmt = sqlsrv_query($conn, $fail_sql, array($su_an, $email));

                if ($fail_stmt === false) {
                    die("<div style='background:black; color:lime; padding:20px;'>SİSTEM HATASI: " . print_r(sqlsrv_errors(), true) . "</div>");
                }

                $message = "<div style='color:red; margin-bottom:15px;'>❌ INCORRECT PASSWORD! </div>";

                // 📝 CLOUDWATCH LOGU 2: HATALI ŞİFRE
                $log_mesaj = "[" . date('Y-m-d H:i:s') . "] GÜVENLİK İHLALİ: Hatalı Şifre Denemesi | Email: " . $email . " | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
                error_log($log_mesaj, 3, "/var/www/html/logs/security_audit.log");
            }
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
    <link rel="stylesheet" href="css/login_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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