# ✈️ Distributed Flight Reservation System (BiletArena)
*Scroll down for the Turkish version. / Türkçe versiyon için aşağı kaydırın.*

A highly secure, distributed web application built to simulate a real-world airline reservation network. This project features a Central API communicating with multiple independent agency nodes, designed with a strong emphasis on Application Security (AppSec) and Cloud Infrastructure.

---

## 📑 Requirement Specification

### Purpose:
The aviation and travel industry requires robust, seamless, and secure platforms to handle complex daily operations. Traditional localized systems face scalability and synchronization issues. The purpose of this project is to build a distributed, browser-based reservation system that can process, retrieve, and analyze ticketing information across multiple separate nodes simultaneously. Furthermore, this system is designed to be highly resistant to modern cyber threats, maintaining infinite logs and operating securely on a distributed client-server computing technology.

### Project Scope:
The project’s focus is the development of a highly scalable Travel Management System. The main deliverables of the project are:
*   A Central API engine for database synchronization.
*   Three independent Agency Nodes (Acenta A, B, and C).
*   Integration of real-time cloud security monitoring.
*   Detailed security implementations for data integrity.

---

## 🔭 Overall Description

### Product Perspective:
The system is targeted towards Travelers seeking a secure booking experience, Travel Agencies needing synchronized flight inventories, and System Administrators monitoring network health and security.

### Project Features:
*   **Distributed Architecture:** Independent nodes communicating with a Central API via cURL and JSON.
*   **Real-Time Security Monitoring (SOC):** Critical security events are written to local logs and instantly forwarded to **AWS CloudWatch**.
*   **Complex Ticketing Logic:** Handles intricate business rules, such as preventing infant passengers from booking without an adult companion.
*   **Database Optimization:** B-Tree indexing on high-traffic columns in Microsoft SQL Server to ensure logarithmic search complexities.

### Operating Environment:
The product is hosted on a cloud environment (**AWS EC2 Ubuntu Linux**) and sits behind a **Cloudflare Reverse Proxy**. It is compatible with all modern web browsers.

---

## ⚙️ System Features

### Admin / System Architect:
The Admin is responsible for observing and maintaining the whole system and its security posture. The functionalities include:
*   Monitoring the AWS CloudWatch SOC dashboard for potential threats.
*   Managing the MS SQL Database and indexing structures.
*   Observing intrusion attempts (e.g., Brute-Force, XSS).

### Travel Agency Node:
Independent platforms that interact with the central database.
*   Fetch available flights dynamically via Central API.
*   Process ticket sales and synchronize inventory securely.

### Traveler (Passenger):
Each traveler has an individual account protected by strict security protocols. The options given to each registered Traveler are:
*   **Secure Registration:** Inputs are sanitized and monitored by Regex-based **XSS (Cross-Site Scripting) Sensors**.
*   **Login:** Protected by an **Anti-Brute-Force mechanism** that locks the account for 15 minutes after 5 failed attempts (handling UTC Time Drift).
*   **Search & Buy Ticket:** Browse dynamic routes and purchase tickets.
*   **Cancel Ticket:** State-changing requests are protected by cryptographic **CSRF Tokens** to prevent request forgery.

---

## 💻 Interfaces & Constraints

*   **Database:** MS SQL Server
*   **Development Tools:** Visual Studio Code, Cursor AI, Git/GitHub
*   **Cloud & DevOps:** AWS EC2, AWS IAM, AWS CloudWatch, Ubuntu Linux, Bash Scripting
*   **Network Security:** Cloudflare WAF, Nmap (Reconnaissance Defense)

---
---

# 🇹🇷 Dağıtık Uçuş Rezervasyon Sistemi (BiletArena)

Gerçek dünya havayolu rezervasyon ağlarını simüle etmek amacıyla geliştirilmiş, yüksek güvenlikli ve dağıtık mimariye sahip bir web uygulamasıdır. Bu proje, Uygulama Güvenliği (AppSec) ve Bulut Altyapısına güçlü bir vurgu yapılarak tasarlanmış olup, çoklu bağımsız acenta düğümleriyle iletişim kuran bir Merkez API içerir.

---

## 📑 Gereksinim Şartnamesi

### Amacı:
Havacılık ve seyahat endüstrisi, karmaşık günlük operasyonları yönetmek için sağlam, kesintisiz ve güvenli platformlara ihtiyaç duyar. Geleneksel yerel sistemler ölçeklenebilirlik ve senkronizasyon sorunlarıyla karşılaşmaktadır. Bu projenin amacı; biletleme bilgilerini birden fazla ayrı düğüm (node) üzerinden eşzamanlı olarak işleyebilen, alabilen ve analiz edebilen dağıtık, tarayıcı tabanlı bir rezervasyon sistemi oluşturmaktır. Ayrıca bu sistem; modern siber tehditlere karşı son derece dirençli olacak, sınırsız log kaydı tutacak ve dağıtık istemci-sunucu teknolojisi üzerinde güvenle çalışacak şekilde tasarlanmıştır.

### Proje Kapsamı:
Projenin odak noktası, yüksek oranda ölçeklenebilir bir Seyahat Yönetim Sistemi geliştirmektir. Projenin ana çıktıları şunlardır:
*   Veritabanı senkronizasyonu için bir Merkez API motoru.
*   Üç bağımsız Acenta Düğümü (Acenta A, B ve C).
*   Gerçek zamanlı bulut güvenlik izleme entegrasyonu.
*   Veri bütünlüğü için detaylı güvenlik uygulamaları.

---

## 🔭 Genel Tanım

### Ürün Perspektifi:
Sistem; güvenli bir rezervasyon deneyimi arayan Yolcular, senkronize uçuş envanterine ihtiyaç duyan Seyahat Acentaları ve ağ sağlığı ile güvenliğini izleyen Sistem Yöneticileri hedeflenerek geliştirilmiştir.

### Proje Özellikleri:
*   **Dağıtık Mimari:** Merkez API ile cURL ve JSON üzerinden iletişim kuran bağımsız düğümler.
*   **Gerçek Zamanlı Güvenlik İzleme (SOC):** Kritik güvenlik olayları yerel loglara yazılır ve anında **AWS CloudWatch**'a iletilir.
*   **Karmaşık Biletleme Mantığı:** Bebek yolcuların bir yetişkin refakatçisi olmadan bilet almasını engellemek gibi karmaşık iş kurallarını yönetir.
*   **Veritabanı Optimizasyonu:** Logaritmik arama karmaşıklıkları sağlamak için Microsoft SQL Server'da yüksek trafikli sütunlarda B-Tree indeksleme kullanımı.

### Çalışma Ortamı:
Ürün bir bulut ortamında (**AWS EC2 Ubuntu Linux**) barındırılmaktadır ve bir **Cloudflare Ters Vekil Sunucusu (Reverse Proxy)** arkasında çalışır. Tüm modern web tarayıcılarıyla uyumludur.

---

## ⚙️ Sistem Özellikleri

### Yönetici (Sistem Mimarı):
Yönetici, tüm sistemi ve güvenlik duruşunu gözlemlemek ve sürdürmekten sorumludur. İşlevleri şunları içerir:
*   Potansiyel tehditler için AWS CloudWatch SOC panelini izlemek.
*   MS SQL Veritabanını ve indeksleme yapılarını yönetmek.
*   Saldırı girişimlerini (ör. Brute-Force, XSS) gözlemlemek.

### Seyahat Acentası Düğümü:
Merkezi veritabanı ile etkileşime giren bağımsız platformlardır.
*   Merkez API aracılığıyla mevcut uçuşları dinamik olarak çeker.
*   Bilet satışlarını işler ve envanteri güvenli bir şekilde senkronize eder.

### Yolcu:
Her yolcunun sıkı güvenlik protokolleriyle korunan bireysel bir hesabı vardır. Kayıtlı her Yolcuya sunulan seçenekler şunlardır:
*   **Güvenli Kayıt:** Girdiler temizlenir ve Regex tabanlı **XSS Sensörleri** tarafından izlenir.
*   **Giriş (Login):** 5 başarısız denemeden sonra hesabı 15 dakika boyunca kilitleyen bir **Brute-Force (Kaba Kuvvet) koruma mekanizması** ile korunmaktadır.
*   **Arama ve Bilet Satın Alma:** Dinamik rotalara göz atar ve bilet satın alır.
*   **Bilet İptali:** Durum değiştiren istekler, sahte istekleri (CSRF) engellemek için kriptografik **CSRF Token'ları** ile korunmaktadır.

---

## 💻 Arayüzler ve Kısıtlamalar

*   **Veritabanı:** MS SQL Server
*   **Geliştirme Araçları:** Visual Studio Code, Cursor AI, Git/GitHub
*   **Bulut ve DevOps:** AWS EC2, AWS IAM, AWS CloudWatch, Ubuntu Linux, Bash Betikleri
*   **Ağ Güvenliği:** Cloudflare WAF, Nmap (Keşif Savunması)