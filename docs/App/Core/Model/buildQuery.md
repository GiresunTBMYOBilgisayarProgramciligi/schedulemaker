[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **buildQuery**

---
# Model::buildQuery()

Nesneye girilen tüm filtre ve ayarları kullanarak ham bir SQL cümlesi ve bu cümleye bağlanacak (bind) parametreleri üretir.

## Mantık (Algoritma)
1.  **SELECT**: `select` özelliğine bakarak veya varsayılan `SELECT * FROM table` metnini oluşturur.
2.  **WHERE**: `where()` metodundan gelen koşulları ve bunlara bağlı PHP verilerini (prepared statement uyumlu) hazırlar.
3.  **Sıralama ve Limit**: `order_by`, `limit` ve `offset` değerlerini SQL sözdizimine uygun şekilde sonuna ekler.
4.  **Dönüş**: `[sql_string, params_array]` şeklinde bir dizi döndürür.
