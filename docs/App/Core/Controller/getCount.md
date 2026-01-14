[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Controller](./README.md) / **getCount**

---
# Controller::getCount(?array $filters)

Belirli kriterlere uyan toplam kayıt sayısını hızlıca almak için kullanılır.

## Mantık (Algoritma)
1.  **Model Tespiti**: Alt sınıfta (örn: `LessonController`) tanımlı olan `$modelName` özelliğini okur.
2.  **Nesne Oluşturma**: İlgili modelden (örn: `Lesson`) yeni bir boş nesne türetir.
3.  **Sorgulama**: Modelin `get()` (Query Builder) metodunu başlatır, `$filters` dizisini `where()` koşulu olarak ekler.
4.  **Dönüş**: Modelin `count()` metodunu çağırarak veritabanından dönen toplam sayı değerini (integer) döndürür.
