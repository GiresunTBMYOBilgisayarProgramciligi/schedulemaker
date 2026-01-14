[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **loadRelations**

---
# Model::loadRelations(array $results)

Ana sorgudan dönen sonuç kümesi üzerinde, `with()` ile istenen ilişkileri yükler.

## Mantık (Algoritma)
1.  **Sonuç Kontrolü**: Eğer ana sorgu boşsa işlemi durdurur.
2.  **İlişki Döngüsü**: `with()` ile belirtilen her bir ilişki için:
    - Model sınıfında bu ilişkiyi tanımlayan metodun (örn: `lessons()`) varlığını kontrol eder.
    - Metodu çağırarak ilişki tanımını (Relationship nesnesi) alır.
    - İlişkili tablodan verileri tek bir toplu sorgu (örn: `IN (...)`) ile çeker.
3.  **Eşleştirme**: Çekilen ilişkili verileri, yabancı anahtarlarına (foreign keys) göre ana sonuç kümesindeki ilgili nesnelere özellik olarak atar.
4.  **Dönüş**: İlişkileri doldurulmuş nesne dizisini döndürür.
