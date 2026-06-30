<?php
// 1. ALTIN KURAL: Acenta ayarlarını yükle (Hangi kimliği sileceğini bilmesi için şart!)
if (file_exists('agency_config.php')) {
    include_once 'agency_config.php';
} else {
    die("Error: Configuration file missing.");
}

// 2. OTURUMU BAŞLAT (Silmek için önce o odaya girmemiz lazım)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. TÜM OTURUM DEĞİŞKENLERİNİ BOŞALT
$_SESSION = array();

// 4. TARAYICIDAKİ ACENTAYA ÖZEL ÇEREZİ (Örn: ACENTA_A_SESSION) SİL
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. SUNUCUDAKİ OTURUM DOSYASINI TAMAMEN YOK ET
session_destroy();

// 6. ANA SAYFAYA YÖNLENDİR
header("Location: index.php");
exit();
?>