[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Helpers](../README.md) / **FilterValidator**

---
# FilterValidator

`FilterValidator` sınıfı, dış dünyadan (GET/POST) gelen verileri belirli bir şema bazlı doğrulamak, tür dönüşümlerini yapmak ve güvenli hale getirmekten sorumludur.

## Temel Görevi
Uygulamadaki her bir AJAX işlemi veya sayfa isteği için beklenen parametreleri (`required`, `optional`) ve bunların türlerini (`int`, `string`, `array`) denetleyerek kodun geri kalanını hatalı veya kötü niyetli verilerden korur.

## Kullanım Örneği
```php
$validator = new FilterValidator();
try {
    $filters = $validator->validate($_POST, 'saveScheduleItems');
    // $filters artık doğrulanmış ve güvenlidir.
} catch (InvalidArgumentException $e) {
    // Eksik veya hatalı veri durumunda hata fırlatır
}
```

## Metodlar
*   [__construct()](./__construct.md): Şemaları ve operasyon kurallarını ilklendirir.
*   [validate()](./validate.md): Verilen işlemi doğrular ve temizlenmiş filtre dizisini döndürür.
*   [validateType()](./validateType.md): Değerin şemada tanımlanan tipe uygunluğunu denetler.
*   [isIntegerish()](./isIntegerish.md): Değerin tam sayı veya tam sayı benzeri olup olmadığını kontrol eder.
*   [isArrayOf()](./isArrayOf.md): Bir dizinin tüm elemanlarının belirli bir türde olup olmadığını kontrol eder.
