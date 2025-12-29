[🏠 Ana Sayfa](../../../../README.md) / [App](../../../README.md) / [Models](../../README.md) / [Schedule](./README.md) / **getScheduleScreenName**

---
# getScheduleScreenName

Bu metod, `Schedule` modelinin `owner_type` ve `owner_id` özelliklerini kullanarak, programın ekranda gösterilecek başlığını dinamik olarak oluşturur.

## İmza

```php
public function getScheduleScreenName(): string
```

## Dönüş Değeri

*   **`string`**: Programın ekran adı (Örn: "Ahmet Yılmaz Ders Programı", "Biyoloji 1. Sınıf Ders Programı").

## Mantık

Metod, `owner_type` değerine göre ilgili modelden (`User`, `Lesson`, `Program`, `Classroom`) veriyi çeker ve uygun bir başlık formatı döndürür:

*   **user**: `[Ad Soyad] Ders Programı`
*   **lesson**: `[Ders Adı] Ders Programı`
*   **program**: `[Program Adı] [Sınıf] Ders Programı`
*   **classroom**: `[Derslik Adı] Ders Programı`
*   **default**: "Ders Programı"

## Kullanım Örneği

```php
$schedule = new Schedule();
$schedule->owner_type = 'user';
$schedule->owner_id = 1; // Ahmet Yılmaz user id

echo $schedule->getScheduleScreenName();
// Çıktı: Ahmet Yılmaz Ders Programı
```
