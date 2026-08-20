import pyodbc
import pandas as pd
import os
from dotenv import load_dotenv
import matplotlib.pyplot as plt
import seaborn as sns

# 1. MUTLAK YOL İLE GİZLİ KASAYI AÇ (Nereden tetiklenirse tetiklensin yolu şaşırmaz)
load_dotenv('/var/www/html/merkez_api/.env')

# 2. AWS RDS TEMİZLİĞİ (Gereksiz tırnakları ve tcp: eklerini temizliyoruz)
server = os.getenv('DB_SERVER').replace('tcp:', '').replace('"', '').strip()
database = os.getenv('DB_NAME').replace('"', '').strip()
username = os.getenv('DB_USER_PYTHON').replace('"', '').strip()
password = os.getenv('DB_PASS_PYTHON').replace('"', '').strip()

try:
    # 3. MODERN ODBC DRIVER 18 BAĞLANTISI
    connection_string = f'DRIVER={{ODBC Driver 18 for SQL Server}};SERVER={server};DATABASE={database};UID={username};PWD={password};Encrypt=yes;TrustServerCertificate=yes;Timeout=30;'
    conn = pyodbc.connect(connection_string)
    
    # 4. KISITLI YETKİ (thy_analyst) İLE VERİ ÇEKME
    query = """
        SELECT C.CompanyName, COUNT(R.ReservationID) as TotalReservations
        FROM Companies_Table C
        LEFT JOIN Reservation_Table R ON C.CompanyID = R.CompanyID
        GROUP BY C.CompanyName
    """
    df = pd.read_sql(query, conn)

    # 5. GRAFİK ÇİZİMİ VE GÜVENLİ KAYIT
    plt.figure(figsize=(10, 6))
    ax = sns.barplot(x="CompanyName", y="TotalReservations", data=df, hue="CompanyName", palette="Blues_d", legend=False)
    plt.title('Acenta Bazlı Toplam Rezervasyon Sayıları')
    plt.xlabel('Acenta Adı')
    plt.ylabel('Rezervasyon Sayısı')
    plt.xticks(rotation=45)
    plt.tight_layout()

    # ÇOK KRİTİK: Resmi doğrudan kilitli kasanın içine 'Mutlak Yol' ile kaydediyoruz!
    plt.savefig('/var/www/html/merkez_api/secure_scripts/acenta_satis_raporu.png')
    print("Grafik başarıyla oluşturuldu ve güvenli alana kaydedildi!")

except Exception as e:
    print(f"BİR HATA OLUŞTU: {e}")