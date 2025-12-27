[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **dropTableToTable**

---
# ScheduleCard.dropTableToTable(isBulk = false)

Tablo içindeki bir dersi (veya seçili birden fazla dersi) başka bir hücreye taşıdığınızda çalışan metottur.

## Mantık (Algoritma)
1.  **Mod Belirleme**: Tekli taşıma mı yoksa toplu taşıma mı (`isBulk`) yapıldığını belirler.
2.  **Veri Toplama**: Taşınacak derslerin mevcut ID, saat ve derslik bilgilerini `getLessonItemData()` ile toplar.
3.  **Ön Kontrol (Frontend)**: Yeni konumun müsaitliğini `checkCrash()` ile denetler.
4.  **Backend Kontrolü**: Yeni konumu ve dersleri `checkCrashBackEnd()` üzerinden sunucuya doğrulatır.
5.  **Atomik İşlem (Sil-Ekle)**:
    - Önce eski konumdaki kayıtları sunucudan siler (`deleteScheduleItems`).
    - Ardından yeni konumdaki kayıtları sunucuya ekler (`saveScheduleItems`).
6.  **Görsel Güncelleme**:
    - Eski hücredeki ders kartlarını temizler.
    - Yeni hücreye güncel kartları yerleştirir (`moveLessonListToTable`).
7.  **Hata Yönetimi**: Eğer silme veya ekleme adımlarında hata oluşursa kullanıcıya bildirir.
