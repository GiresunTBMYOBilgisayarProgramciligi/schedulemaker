[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **update**

---
# Model::update()

Model nesnesindeki değişiklikleri veritabanındaki mevcut kayıt üzerinde günceller.

## Mantık (Algoritma)
1.  **Doğrulama**: İşleme başlamadan önce `id` ve `table_name` değerlerinin dolu olduğu kontrol edilir.
2.  **Veri Hazırlığı**: `getArray()` metodu ile nesne özellikleri bir diziye alınır (ancak `id` güncellenecek alanlar listesine dahil edilmez).
3.  **Serileştirme**: Dizi içindeki array tipindeki veriler `serialize()` edilir.
4.  **Sorgu İnşası**: `UPDATE table SET col1 = :col1, ... WHERE id = :id` formatında bir SQL cümlesi hazırlanır.
5.  **Güvenli Bağlama**: Tüm yeni değerler ve `id` parametresi PDO `bindValue` ile sorguya güvenli şekilde bağlanır.
6.  **Execute & Log**: Sorgu çalıştırılır ve işlem başarılıysa "Veri Güncellendi" mesajı log sistemine gönderilir.
