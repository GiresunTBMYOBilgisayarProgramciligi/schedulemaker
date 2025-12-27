[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **dropTableToList**

---
# ScheduleCard.dropTableToList(skipDelete = false)

Tabloda kayıtlı olan bir dersi tutup tekrar yan taraftaki listeye bıraktığınızda dersi silmek için kullanılır.

## Mantık (Algoritma)
1.  **Veritabanı Silme**: `skipDelete` parametresi `false` ise `deleteScheduleItems()` metodunu çağırarak dersi sunucudan siler.
2.  **Liste Kontrolü**: Silinen dersin yan taraftaki "Müsait Dersler" listesinde hali hazırda olup olmadığını kontrol eder.
3.  **Görsel İade**:
    - Eğer ders listede zaten varsa: Kalan saat miktarını veya kişi sayısını arttırarak günceller.
    - Eğer ders listede yoksa: Yeni bir ders kartı oluşturarak listeye ekler.
4.  **Tablo Temizliği**: Dersi tablodaki hücresinden görsel olarak kaldırır.
5.  **Senkronizasyon**: `updateStickyList()` ile yapışkan listeyi güncel durumla eşleştirir.
