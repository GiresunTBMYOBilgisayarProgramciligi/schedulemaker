[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **getLogDetail**

---
# Model::getLogDetail()

Log mesajlarında nesneyi tanımlamak için kullanılacak detay bilgisini döndürür.

## Varsayılan Değer
Varsayılan olarak nesnenin ID numarasını döndürür.

## Örnek Kullanım (Override)

Model sınıfları bu metodu ezerek daha anlamlı bir tanımlayıcı (isim, kod vb.) döndürebilir:

```php
public function getLogDetail(): string
{
    return $this->name ?? "ID: " . $this->id;
}
```
