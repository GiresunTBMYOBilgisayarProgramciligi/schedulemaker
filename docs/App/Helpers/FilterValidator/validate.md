[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Helpers](../README.md) / [FilterValidator](./README.md) / **validate**

---
# FilterValidator::validate(array $data, string $for)

Ana doğrulama motorudur. Ham veriyi alır ve belirli bir şemaya göre temizleyip güvenli bir dizi döndürür.

## Mantık (Algoritma)
1.  **Kural Kontrolü**: `$for` parametresi ile istenen kural setinin `operationRules` içinde olup olmadığına bakar. Yoksa hata fırlatır.
2.  **Zorunlu Alanlar**: Tanımlanan zorunlu anahtarların ham veri içinde varlığını ve boş olmadığını (null veya boş string) denetler. Eksik varsa `InvalidArgumentException` fırlatır.
3.  **Opsiyonel Alanlar**: Tanımlı opsiyonel anahtarlar veride varsa, değerlerini doğrular; yoksa atlar.
4.  **Varsayılan Değerler**: Tanımlı varsayılan alanlar kullanıcıdan gelmemişse, `getSettingValue` gibi yardımcı fonksiyonlar kullanarak otomatik olarak (örn: `semester`, `academic_year`) doldurur.
5.  **Tip Doğrulama**: Eklenen her bir değer için `validateType()` metodunu çağırarak veri türünün master şemaya uygunluğunu denetler.
6.  **Filtreleme**: Belirtilen kurallar dışında gelen (fazlalık) anahtarları eler ve sadece "temiz" diziyi döndürür.
