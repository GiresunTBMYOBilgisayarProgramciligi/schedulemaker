[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **where**

---
# Model::where(?array $filters, string $logicalOperator = "AND")

SQL sorguları için dinamik `WHERE` koşulları oluşturur.

## Mantık (Algoritma)
1.  **Ön Hazırlık**: Eğer daha önce bir `whereClause` yazılmışsa, mevcut koşulu paranteze alarak kapsüller.
2.  **Diziyi İşleme**: Gelen `$filters` dizisindeki her anahtar-değer ikilisini döngüye alır:
    - **Operatör Ayıklama**: Anahtar `!` ile başlıyorsa `NOT` durumunu set eder.
    - **Dizi Değerleri**: Eğer değer dizi ise; `in` (IN), `between` (BETWEEN) veya (`>`, `<`, `>=` vb.) gibi özel operatörleri belirler.
3.  **Placeholder (Yer Tutucu) Oluşturma**: SQL injection saldırılarını önlemek için sütun isimlerine göre `:sutun_adi_0` şeklinde benzersiz parametreler üretir.
4.  **Dizi Birleştirme**: Oluşturulan tüm koşul parçalarını `$logicalOperator` (varsayılan AND) ile birbirine bağlar.
5.  **Saklama**: Sonuç stringini sınıfın `whereClause` değişkenine kaydeder ve zincirleme kullanım için `$this` (kendini) döndürür.
