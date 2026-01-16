[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **getLabel**

---
# Model::getLabel()

Modelin insan tarafından okunabilir Türkçe adını döndürür. Bu değer log mesajlarında (örn: "Yeni ders eklendi") kullanılır.

## Varsayılan Değer
Varsayılan olarak veritabanı tablo adını (`table_name`) döndürür.

## Örnek Kullanım (Override)

Her model sınıfı bu metodu ezerek (override) kendine özgü bir etiket tanımlamalıdır:

```php
public function getLabel(): string
{
    return "derslik";
}
```
