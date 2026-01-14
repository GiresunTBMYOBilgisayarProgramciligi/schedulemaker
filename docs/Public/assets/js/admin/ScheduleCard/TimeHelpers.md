[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **TimeHelpers**

---
# ScheduleCard Zaman Yardımcı Metotları

Ders programı hesaplamalarında kullanılan matematiksel zaman yardımcı işlevleri.

## [addMinutes(time, mins)](./addMinutes.md)
Verilen bir saat dizesine (`HH:MM`) belirtilen dakika miktarını ekler ve yeni saat dizesini döner.
- **Mantık**: Saati dakikaya çevirir, eklemeyi yapar, tekrar saat formatına döndürür.

## [timeToMinutes(time)](./timeToMinutes.md)
`HH:MM` formatındaki saat bilgisini toplam dakikaya çevirir (Örn: `02:30` -> `150`).
- **Mantık**: Saati 60 ile çarpar ve dakikayı ekler.

## [minutesToTime(minutes)](./minutesToTime.md)
Toplam dakika bilgisini `HH:MM` formatına geri döndürür.
- **Mantık**: Toplamı 60'a bölerek saati bulur (bölüm), kalanı dakika olarak alır. Tek haneli sayılara `0` ekler.

## [getDurationInHours(startTime, endTime)](./getDurationInHours.md)
İki saat arasındaki farkı "ders saati sayısı" olarak döner.
- **Mantık**: İki saati de dakikaya çevirir, farkı alır ve sistemdeki ders süresine (örn: 50 dk) bölerek toplam saat sayısını bulur.
