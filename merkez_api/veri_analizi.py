import pyodbc
import pandas as pd
import os
from dotenv import load_dotenv
import matplotlib.pyplot as plt
import seaborn as sns

# Gizli kasayı açıyoruz
load_dotenv()

server = os.getenv('DB_SERVER')
database = os.getenv('DB_NAME')
username = os.getenv('DB_USER_PYTHON')
password = os.getenv('DB_PASS_PYTHON')

try:
    # 1. BAĞLANTI VE VERİ ÇEKME
    connection_string = f'DRIVER={{ODBC Driver 17 for SQL Server}};SERVER={server};DATABASE={database};UID={username};PWD={password}'
    conn = pyodbc.connect(connection_string)
    print("✅ BAĞLANTI BAŞARILI! (Şifre gizli kasadan okundu)\n")

    query = """
        SELECT C.CompanyName, COUNT(R.ReservationID) as TotalReservations
        FROM Companies_Table C
        LEFT JOIN Reservation_Table R ON C.CompanyID = R.CompanyID
        GROUP BY C.CompanyName
        ORDER BY TotalReservations DESC
    """
    
    df = pd.read_sql(query, conn)
    print("📊 GÜNCEL ŞİRKET PERFORMANS RAPORU:")
    print(df.to_string(index=False))
    print("-" * 40)

    # 2. VERİ GÖRSELLEŞTİRME VE GRAFİK ÇİZİMİ (YENİ EKLENEN KISIM)
    if not df.empty:
        # Grafik tasarım ayarları (Şık ve kurumsal bir tema)
        sns.set_theme(style="whitegrid")
        plt.figure(figsize=(10, 6))
        
        
        ax = sns.barplot(x="CompanyName", y="TotalReservations", data=df, palette="Blues_d")
        
        plt.title("BiletArena - Acentalara Göre Toplam Rezervasyon Sayıları", fontsize=15, fontweight="bold")
        plt.xlabel("Acenta Adı", fontsize=12)
        plt.ylabel("Toplam Rezervasyon", fontsize=12)

        for p in ax.patches:
            ax.annotate(format(p.get_height(), '.0f'), 
                        (p.get_x() + p.get_width() / 2., p.get_height()), 
                        ha = 'center', va = 'center', 
                        xytext = (0, 9), 
                        textcoords = 'offset points',
                        fontweight='bold')

        dosya_adi = "acenta_satis_raporu.png"
        plt.savefig(dosya_adi, dpi=300, bbox_inches='tight')
        print(f"📈 HARİKA HABER: Grafik başarıyla çizildi ve '{dosya_adi}' adıyla kaydedildi!")
        
        plt.show()
    else:
        print("Grafik çizilecek veri bulunamadı.")

except Exception as e:
    print("❌ BİR HATA OLUŞTU:", e)
finally:
    if 'conn' in locals():
        conn.close()