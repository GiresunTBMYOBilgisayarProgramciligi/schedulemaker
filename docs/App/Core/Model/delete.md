[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **delete**

---
# Model::delete()

Kayıtları veritabanından fiziksel olarak siler.

## Mantık (Algoritma)
1.  **Güvenlik Kontrolü**: `users` tablosundaki 1 numaralı (Süper Karşılama) yöneticinin silinmesini engeller.
2.  **Silme Yöntemi Belirleme**:
    - **ID ile Silme**: Eğer nesnenin `id` özelliği doluysa, sadece o ID'ye sahip satırı silen bir SQL hazırlar.
    - **Koşul (Where) ile Silme**: ID yoksa ancak `where()` metoduyla bir koşul belirtilmişse, o koşula uyan tüm satırları siler.
3.  **Hata Yönetimi**: Eğer hem ID hem de koşul boşsa, güvenliğin korunması adına ("tüm tabloyu silme" riskine karşı) hata fırlatır.
4.  **Execute & Log**: Sorguyu çalıştırır, başarılıysa sistem günlüğüne (Log) bilgi yazar.
