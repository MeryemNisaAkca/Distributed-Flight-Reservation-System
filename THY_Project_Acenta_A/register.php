<?php
//It allows new users to register with the system.

include 'connecting.php';

$message = ""; // Message variable for success/error

//Check if the form was submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 🚨 XSS TESPİT SENSÖRÜ (CLOUDWATCH İÇİN) BAŞLANGICI ---
    // Kullanıcının girdiği "ham" veriyi alıp içinde virüs var mı diye tarıyoruz
    $raw_name = $_POST['Name'] ?? '';
    $raw_surname = $_POST['Surname'] ?? '';

    // Siber güvenlik dünyasındaki en yaygın XSS saldırı kelimeleri (Regex)
    $xss_pattern = '/<script\b[^>]*>|javascript:|onerror=|onload=/i';

    if (preg_match($xss_pattern, $raw_name) || preg_match($xss_pattern, $raw_surname)) {
        // 📝 CLOUDWATCH LOGU: ZARARLI KOD TESPİT EDİLDİ!
        $log_mesaj = "[" . date('Y-m-d H:i:s') . "] GÜVENLİK İHLALİ: XSS Script Enjeksiyonu Engellendi! | Hedef Form: Kayıt Ol | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
        error_log($log_mesaj, 3, "/var/www/html/logs/security_audit.log");

        // Opsiyonel: Saldırganı direkt engelleyebilirsin ama biz şu anlık sadece logluyor
        // ve aşağıdaki orijinal kodunla temizleyerek kaydetmesine izin veriyoruz.
    }
    // --- 🚨 XSS TESPİT SENSÖRÜ BİTİŞİ ---


    // --- 🛡️ XSS KORUMALI VERİ ALIMI BAŞLANGICI ---
    // filter_input() ile formdan gelen verilerdeki zararlı HTML/JS kodlarını etkisiz hale getiriyoruz
    // trim() ile de başındaki ve sonundaki boşlukları siliyoruz (boşlukla filtre atlatmayı engeller)
    $TCno = trim(filter_input(INPUT_POST, 'TCno', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $Name = trim(filter_input(INPUT_POST, 'Name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $Surname = trim(filter_input(INPUT_POST, 'Surname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    // E-posta adresi için özel e-posta filtresi kullanıyoruz
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');

    // Şifreyi filtrelemiyoruz çünkü kullanıcı şifresinde özel karakterler kullanmış olabilir.
    // password_hash fonksiyonu onu zaten güvenli bir şekilde şifreleyeceği için XSS riski oluşturmaz.
    $password = $_POST['password'];

    $BirthDate = filter_input(INPUT_POST, 'BirthDate', FILTER_SANITIZE_SPECIAL_CHARS);
    $Role = 'Passenger';
    // --- 🛡️ XSS KORUMALI VERİ ALIMI BİTİŞİ ---

    // Hashing the password for security (NEVER store plain passwords!)
    // If your table has a specific password column length, ensure the hash fits (it's long).
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // SQL Query to insert a new user (The ID column is omitted, as it's auto-incrementing)
    $sql = "INSERT INTO Users_Table (TCno, Name, Surname, Email, PassHash, BirthDate, Role)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    // Parameters array for secure execution (Prevents SQL Injection)
    $params = array($TCno, $Name, $Surname, $email, $hashedPassword, $BirthDate, $Role);

    // Execute the query
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("Registration failed: " . print_r($errors, true));
        // If query fails, display error (often due to duplicate email/primary key violation)
        $message = "<div style='color:red;'>Registration failed. Please check your information and try again. If the problem persists, contact support.</div>";
    } else {
        // Success
        $message = "<div style='color:green; font-weight: bold;'>✅ Registration Successful! You can now Log In.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - THY Project</title>
    <link rel="stylesheet" href="css/register_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="register-page">

    <div class="navbar">
        <a href="index.php" class="logo"><i class="fas fa-plane-departure"></i> THY PROJECT</a>
        <a href="index.php">Home</a>
        <a href="found_flight.php">Flights</a>
        <div style="margin-left: auto;">
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Log In</a>
            <a href="register.php" style="background: rgba(255,255,255,0.1); border-radius: 4px; margin-left: 10px;">Sign Up</a>
        </div>
    </div>

    <div class="container">
        <div class="signup-box">
            <h2 style="text-align: center; color: #232b38;">Create Your Account</h2>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">Join our Miles&Smiles program today!</p>

            <?php echo $message; // Display success or error message ?>

            <form action="" method="POST">
                <label>TC Number:</label>
                <input type="text" name="TCno" required placeholder="12345678901" maxlength="11">

                <label>First Name:</label>
                <input type="text" name="Name" required placeholder="Name">

                <label>Last Name:</label>
                <input type="text" name="Surname" required placeholder="Surname">

                <label>Email Address:</label>
                <input type="email" name="email" required placeholder="your.email@example.com">

                <label>Password:</label>
                <input type="password" name="password" required placeholder="******">

                <label>BirthDate:</label>
                <input type="date" name="BirthDate">

                <p style="font-size: 12px; color: #888; margin-top: 10px;">
                    <i class="fas fa-lock"></i> Your password will be securely encrypted.
                </p>

                <input type="submit" value="Sign Up Now">
            </form>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 THY Project - All Rights Reserved.</p>
    </div>

</body>
</html>