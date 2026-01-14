[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **count**

---
# Model::count()

Belirlenen koşullara uyan toplam kayıt sayısını döndürür.

## Mantık (Algoritma)
1.  **Sorgu Dönüştürme**: `buildQuery()` ile oluşturulan standart `SELECT *` sorgusunu alır.
2.  **SQL Manipülasyonu**: `preg_replace` kullanarak `SELECT ... FROM` kısmını `SELECT COUNT(*) as count FROM` ile değiştirir.
3.  **Kısıtlama Temizliği**: Sayım işlemini etkilememesi için `LIMIT` ve `OFFSET` ifadelerini sorgudan temizler.
4.  **Execute**: Hazırlanan sayım sorgusunu PDO ile çalıştırır.
5.  **Dönüş**: Veritabanından dönen `count` değerini integer olarak döndürür.
