[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **select**

---
# Model::select(array|string $fields)

Sorguda hangi sütunların döndürüleceğini belirler.

## Mantık (Algoritma)
1.  **Parametre Kontrolü**: Eğer `$fields` bir dizi ise, elemanları virgül ile birleştirir. Eğer yıldız (`*`) ise tüm sütunları temsil eder.
2.  **Depolama**: Oluşturulan sütun listesini nesnenin dahili `select` özelliğine kaydeder.
3.  **Zincirleme**: Diğer metodların çağrılabilmesi için `$this` nesnesini döner.
