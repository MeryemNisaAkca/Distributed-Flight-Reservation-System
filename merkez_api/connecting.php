<?php
// .env dosyasının olduğu yeri buluyoruz
$envPath = __DIR__ . '/.env';

// Dosya var mı diye kontrol ediyoruz (Güvenlik önlemi)
if (file_exists($envPath)) {
    // Şifreleri gizli kasadan çekiyoruz
    $envVariables = parse_ini_file($envPath);
    
    $serverName = $envVariables["DB_SERVER"];
    $database = $envVariables["DB_NAME"];
    $uid = $envVariables["DB_USER_PHP"];
    $pwd = $envVariables["DB_PASS_PHP"];
    
    $connectionOptions = array(
        "Database" => $database,
        "Uid" => $uid,
        "PWD" => $pwd,
        "CharacterSet" => "UTF-8"
    );
    
    // Bağlantıyı başlatıyoruz
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    
    if($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }
} else {
    // Dosya yoksa sistemi direkt durdur!
    die("Kritik Hata: .env dosyası bulunamadı! Güvenlik sebebiyle sistem durduruldu.");
}
?>