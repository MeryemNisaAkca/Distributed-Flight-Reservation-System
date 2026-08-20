<?php
// Acenta bağımlılığından (Session) kurtulduk, API Token (Dijital Anahtar) mimarisine geçtik.

// 1. GİZLİ KASAYI (.env) OKUMA
$env_path = "/var/www/html/merkez_api/.env";
$env_vars = parse_ini_file($env_path);
$api_gizli_anahtari = $env_vars['ADMIN_API_TOKEN']; // Şifreyi kasadan çektik!

// 2. SIFIR GÜVEN KALKANI (TOKEN KONTROLÜ)
if (!isset($_GET['token']) || $_GET['token'] !== $api_gizli_anahtari) {
    die("<div style='text-align:center; margin-top:50px;'><h2 style='color:red;'>🚨 GÜVENLİK İHLALİ (403)</h2><p>Geçersiz veya eksik API Anahtarı!</p></div>");
}

echo "<div style='font-family:sans-serif; text-align:center;'>";
echo "<h2>🛡️ API Anahtarı Doğrulandı. Python Modülü Çalıştırılıyor...</h2>";

// 2. PYTHON TETİKLEME
$output = shell_exec("python3 /var/www/html/merkez_api/secure_scripts/veri_analizi.py 2>&1");

// 3. GÜVENLİ GÖSTERİM
$imagePath = "/var/www/html/merkez_api/secure_scripts/acenta_satis_raporu.png";

if (file_exists($imagePath)) {
    // Grafik varsa şifreleyerek ekrana bas
    $imageData = base64_encode(file_get_contents($imagePath));
    echo '<img src="data:image/png;base64,'.$imageData.'" alt="Acenta Satış Raporu" style="max-width:800px; width:100%; border:2px solid #333; border-radius:10px; box-shadow: 0px 10px 20px rgba(0,0,0,0.2); margin-top:20px;">';
} else {
    // Hata varsa terminal loglarını göster
    echo "<h3 style='color:red;'>Rapor oluşturulamadı! Python Logları:</h3>";
    echo "<pre style='background:#1e1e1e; color:#00ff00; padding:15px; text-align:left; border-radius:5px; max-width:800px; margin: 0 auto; overflow-x:auto;'>$output</pre>";
}

echo "</div>";
?>