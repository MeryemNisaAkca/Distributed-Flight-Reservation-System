# ✈️ Distributed Flight Reservation System (BiletArena)
*Scroll down for the Turkish version. / Türkçe versiyon için aşağı kaydırın.*

A highly secure, distributed web application built to simulate a real-world airline reservation network. This project features a Central API communicating with multiple independent agency nodes (Agency A, B, and C) to manage flights, passenger ticketing, and complex infant-companion cancellation policies.

Beyond core software engineering, this project is architected with a strong emphasis on **Application Security (AppSec)** and **Cloud Infrastructure (DevSecOps)**, utilizing AWS and Cloudflare to ensure enterprise-grade resilience.

---

## 🏗️ System Architecture
The platform operates on a distributed network model:
*   **Central API (`merkez_api`):** The core engine that processes cross-agency synchronizations, inventory management, and database transactions via MS SQL Server.
*   **Agency Nodes (`Acenta_A`, `Acenta_B`, `Acenta_C`):** Independent front-end and backend nodes that securely communicate with the Central API using cURL and JSON payloads.
*   **Cloud Infrastructure:** Hosted on **AWS EC2 (Ubuntu Linux)**, securely sitting behind a **Cloudflare Reverse Proxy** for SSL/TLS encryption and DDoS mitigation.

---

## 🛡️ Security & Incident Response (AppSec)
Security is treated as a first-class citizen in this repository. The system includes custom-built defense mechanisms and real-time monitoring sensors:

*   **AWS CloudWatch SOC Integration:** Critical security events are instantly written to a local `security_audit.log` and forwarded to AWS CloudWatch via an internal agent, creating a real-time Security Operations Center (SOC) dashboard.
*   **Anti-Brute-Force & Account Lockout:** Implemented a secure login mechanism that locks user accounts for 15 minutes after 5 consecutive failed attempts. Handled Time Drift (UTC vs UTC+3) between the application layer and the database seamlessly.
*   **Cross-Site Scripting (XSS) Sensors:** Beyond standard `filter_input` sanitization, active Regex-based sensors detect malicious HTML/JavaScript payloads (e.g., `<script>`) in user inputs and alert the AWS CloudWatch dashboard.
*   **CSRF Token Tampering Prevention:** State-changing requests (like ticket cancellations) are protected by cryptographic CSRF tokens (`random_bytes`). Logic flaws (GET-based state changes) were eliminated and restricted to secure POST methods.
*   **Database Security:** 100% prevention of SQL Injection through parameterized queries (`sqlsrv_query`).

---

## 💻 Technologies Used
*   **Backend:** PHP 8.x, cURL
*   **Frontend:** HTML5, CSS3, JavaScript
*   **Database:** MS SQL Server (with B-Tree Indexing)
*   **Cloud & DevOps:** AWS EC2, AWS IAM, AWS CloudWatch, Ubuntu Linux
*   **Network Security:** Cloudflare WAF, Nmap (Reconnaissance Defense)

*Dedicated to delivering industry-standard solutions by bridging modern cloud infrastructures with advanced AppSec practices.
---
---

# 🇹🇷 Dağıtık Uçuş Rezervasyon Sistemi (BiletArena)

Gerçek dünya havayolu rezervasyon ağlarını simüle etmek amacıyla geliştirilmiş, yüksek güvenlikli ve dağıtık mimariye sahip bir web uygulamasıdır. Bu proje, uçuşları, biletlemeyi ve karmaşık bebek-yetişkin yolcu iptal politikalarını yönetmek için çoklu bağımsız acenta düğümleriyle (Acenta A, B ve C) iletişim kuran bir Merkez API içerir.

Temel yazılım mühendisliğinin ötesinde bu proje; kurumsal düzeyde dayanıklılık sağlamak amacıyla AWS ve Cloudflare kullanılarak **Uygulama Güvenliği (AppSec)** ve **Bulut Altyapısı (DevSecOps)** odağında tasarlanmıştır.

---

## 🏗️ Sistem Mimarisi
Platform dağıtık bir ağ modeli üzerinde çalışır:
*   **Merkez API (`merkez_api`):** Acentalar arası senkronizasyonları, envanter yönetimini ve MS SQL Server üzerinden veritabanı işlemlerini yürüten çekirdek motordur.
*   **Acenta Düğümleri (`Acenta_A`, `Acenta_B`, `Acenta_C`):** cURL ve JSON yapıları kullanarak Merkez API ile güvenli bir şekilde iletişim kuran bağımsız önyüz ve arkayüz düğümleridir.
*   **Bulut Altyapısı:** SSL/TLS şifrelemesi ve DDoS koruması için **Cloudflare Ters Vekil Sunucusu (Reverse Proxy)** arkasında konumlandırılmış **AWS EC2 (Ubuntu Linux)** üzerinde barındırılmaktadır.

---

## 🛡️ Güvenlik ve Olay Müdahalesi (AppSec)
Bu projede güvenlik birinci önceliktir. Sistem, özel olarak geliştirilmiş savunma mekanizmaları ve gerçek zamanlı izleme sensörleri içerir:

*   **AWS CloudWatch SOC Entegrasyonu:** Kritik güvenlik olayları anında yerel `security_audit.log` dosyasına yazılır ve dahili bir ajan aracılığıyla AWS CloudWatch'a iletilerek gerçek zamanlı bir Siber Güvenlik Operasyon Merkezi (SOC) panosu oluşturulur.
*   **Brute-Force Koruması ve Hesap Kilitleme:** Ardından 5 kez hatalı giriş yapıldığında kullanıcı hesabını 15 dakika kilitleyen güvenli bir giriş mekanizması kurulmuştur. Uygulama ve veritabanı arasındaki zaman kayması (Time Drift) sorunu çözülmüştür.
*   **XSS (Cross-Site Scripting) Sensörleri:** Standart `filter_input` temizliğinin ötesinde, aktif Regex tabanlı sensörler kullanıcı girdilerindeki zararlı HTML/JavaScript kodlarını (ör. `<script>`) tespit eder ve AWS CloudWatch'a alarm gönderir.
*   **CSRF Token Manipülasyonu Engelleme:** Durum değiştiren istekler (bilet iptalleri gibi) kriptografik CSRF token'ları ile korunmaktadır. Zafiyet yaratan GET tabanlı durum değişiklikleri tamamen engellenmiş ve güvenli POST metotlarına kısıtlanmıştır.
*   **Veritabanı Güvenliği:** Parametrik sorgular (`sqlsrv_query`) kullanılarak SQL Enjeksiyonu saldırıları %100 oranında engellenmiştir.

---

## 💻 Kullanılan Teknolojiler
*   **Arkayüz:** PHP 8.x, cURL
*   **Önyüz:** HTML5, CSS3, JavaScript
*   **Veritabanı:** MS SQL Server (B-Tree İndeksleme ile)
*   **Bulut ve DevOps:** AWS EC2, AWS IAM, AWS CloudWatch, Ubuntu Linux
*   **Ağ Güvenliği:** Cloudflare WAF, Nmap (Keşif Savunması)

---
*Modern bulut altyapılarını ileri seviye uygulama güvenliği (AppSec) pratikleriyle harmanlayarak sektörel standartlarda çözümler üretmeyi hedeflemektedir.
