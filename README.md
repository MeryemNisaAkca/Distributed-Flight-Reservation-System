# ✈️ BiletArena: Distributed Flight Ticket Reservation System

![Status](https://img.shields.io/badge/Status-Phase_2_Completed-success)
![Architecture](https://img.shields.io/badge/Architecture-Distributed_Agents-orange)
![Security](https://img.shields.io/badge/Security-Least_Privilege_Enforced-blue)

BiletArena is a comprehensive, distributed flight ticket sales and reservation system. It is designed to demonstrate secure database management, microservices-inspired agent-center communication, and advanced data analytics.

## 🏗️ Distributed System Architecture
The project is divided into two main components to ensure separation of concerns and security:
*   **Agent Interfaces (Acentalar):** Lightweight web frontends (Agent A, B, and C) where customer transactions occur. They do not have direct database access.
*   **Central API (Merkez):** The secure backend core that processes HTTP/POST requests from agents, manages sessions, and securely connects to the central database.

## 💻 Tech Stack
*   **Backend:** PHP (PDO/sqlsrv)
*   **Frontend:** HTML5, CSS3
*   **Database:** Microsoft SQL Server (Relational DB Design with Foreign Keys)
*   **Data Analytics:** Python (`pyodbc`, `pandas`, `matplotlib`, `seaborn`)

## 📊 Data Analytics & Reporting
Integrated a Python-based reporting module that fetches raw sales data from MS SQL and generates business intelligence (BI) reports.
*   Calculates total reservations and revenue per travel agent.
*   Automatically generates and exports high-quality statistical plots using `seaborn` and `matplotlib`.

## 🛡️ Cybersecurity & Infrastructure Protection
Security is treated as a first-class citizen in this project, ensuring the architecture is protected against common vulnerabilities and unauthorized access:

1.  **Environment Variables (`.env`):** All critical database credentials are encrypted and stored in `.env` files, strictly ignored by Git (`.gitignore`) to prevent source code leaks.
2.  **Principle of Least Privilege (PoLP):** Python analytics scripts connect to the MS SQL database using a heavily restricted `thy_analyst` role (SELECT-only permissions), actively preventing destructive commands like DELETE/UPDATE.
3.  **SQL Injection Prevention:** Parametrized queries and strict input validation protect the authentication gateways from malicious payloads (e.g., `' OR 1=1 --`).

## 🚀 Cloud Migration & Next Steps (Phase 3 & 4)
- [ ] Migrate the local MS SQL database to **AWS RDS (Relational Database Service)**.
- [ ] Deploy the web application on **AWS EC2** with an Apache/Nginx web server.
- [ ] Implement **AWS VPC** (Public/Private Subnets) for network isolation.
- [ ] Secure the architecture with **AWS WAF (Web Application Firewall)** to block cloud-based SQLi and DDoS attempts.

---
*Developed to showcase full-stack development, database administration (DBA), and cybersecurity practices.*



# ✈️ BiletArena: Dağıtık Uçuş Bilet Rezervasyon Sistemi

![Durum](https://img.shields.io/badge/Durum-Aşama_2_Tamamlandı-success)
![Mimari](https://img.shields.io/badge/Mimari-Dağıtık_Acentalar-orange)
![Güvenlik](https://img.shields.io/badge/Güvenlik-En_Az_Yetki_Uygulandı-blue)

BiletArena, kapsamlı ve dağıtık bir uçuş bileti satış ve rezervasyon sistemidir. Güvenli veritabanı yönetimi, mikroservis ilhamlı acenta-merkez iletişimi ve gelişmiş veri analitiği yeteneklerini sergilemek üzere tasarlanmıştır.

## 🏗️ Dağıtık Sistem Mimarisi
Proje, güvenlik ve sorumlulukların ayrılığı ilkesini sağlamak için iki ana bileşene ayrılmıştır:
*   **Acenta Arayüzleri (Agent Interfaces):** Müşteri işlemlerinin gerçekleştiği hafif web ön yüzleri (Acenta A, B ve C). Doğrudan veritabanı erişimleri yoktur.
*   **Merkez API (Central API):** Acentalardan gelen HTTP/POST isteklerini işleyen, oturumları yöneten ve merkezi veritabanına güvenli bir şekilde bağlanan arka uç çekirdeği.

## 💻 Teknoloji Yığını
*   **Arka Uç (Backend):** PHP (PDO/sqlsrv)
*   **Ön Yüz (Frontend):** HTML5, CSS3
*   **Veritabanı:** Microsoft SQL Server (Yabancı Anahtarlar ile İlişkisel Veritabanı Tasarımı)
*   **Veri Analitiği:** Python (`pyodbc`, `pandas`, `matplotlib`, `seaborn`)

## 📊 Veri Analitiği ve Raporlama
MS SQL'den ham satış verilerini çekerek iş zekası (BI) raporları üreten Python tabanlı bir modül entegre edilmiştir.
*   Her bir seyahat acentasının toplam rezervasyon sayısını ve cirosunu hesaplar.
*   `seaborn` ve `matplotlib` kullanarak yüksek kaliteli istatistiksel grafikleri otomatik olarak oluşturur ve dışa aktarır.

## 🛡️ Siber Güvenlik ve Altyapı Koruması
Güvenlik, bu projede birinci sınıf bir öncelik olarak ele alınmış olup, mimarinin yaygın zafiyetlere ve yetkisiz erişimlere karşı korunması sağlanmıştır:

1.  **Çevre Değişkenleri (`.env`):** Kaynak kod sızıntılarını önlemek için tüm kritik veritabanı kimlik bilgileri `.env` dosyalarında gizlenmiş ve Git tarafından kesinlikle yok sayılmıştır (`.gitignore`).
2.  **En Az Yetki İlkesi (PoLP):** Python analitik scriptleri, MS SQL veritabanına sadece okuma (SELECT) yetkisi olan kısıtlı `thy_analyst` rolü ile bağlanarak DELETE/UPDATE gibi yıkıcı komutları aktif olarak engeller.
3.  **SQL Injection Koruması:** Parametrik sorgular ve sıkı girdi doğrulamaları, kimlik doğrulama ağ geçitlerini zararlı kodlardan (örneğin `' OR 1=1 --`) korur.

## 🚀 Bulut Entegrasyonu ve Sıradaki Adımlar (Aşama 3 ve 4)
- [ ] Yerel MS SQL veritabanını **AWS RDS'e (Relational Database Service)** taşımak.
- [ ] Web uygulamasını **AWS EC2** üzerinde bir Apache/Nginx web sunucusu ile canlıya almak.
- [ ] Ağ izolasyonu için **AWS VPC** (Açık/Kapalı Alt Ağlar) kurmak.
- [ ] Bulut tabanlı SQLi ve DDoS saldırılarını engellemek için **AWS WAF (Web Application Firewall)** kalkanını devreye almak.

---
*Tam yığın (full-stack) geliştirme, veritabanı yönetimi (DBA) ve siber güvenlik uygulamalarını profesyonel bir yaklaşımla sergilemek amacıyla geliştirilmiştir.*