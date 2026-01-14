[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **create**

---
# Model::create()

Model nesnesindeki verileri veritabanına yeni bir kayıt olarak ekler.

## Mantık (Algoritma)
1.  **Veri Toplama**: Nesnenin public özelliklerini `getArray()` metoduyla bir diziye toplar (`id` ve sistem tarihleri hariç tutulur).
2.  **Serileştirme**: Veri dizisindeki "array" türündeki değerleri otomatik olarak `serialize()` ederek veritabanına uygun string formatına getirir.
3.  **Sorgu Hazırlığı**: Sütun isimlerinden `INSERT INTO ...` SQL taslağını ve PDO yer tutucularını (`:property`) oluşturur.
4.  **Güvenlik**: Sütun isimlerini backtick (`` ` ``) işaretleri içine alır.
5.  **Execute**: PDO üzerinde sorguyu çalıştırır (bindValue).
6.  **ID Güncelleme**: İşlem başarılıysa veritabanının atadığı `lastInsertId` değerini nesnenin `id` özelliğine yazar.
7.  **Log**: Yapılan işlemi sistem loglarına kaydeder.
