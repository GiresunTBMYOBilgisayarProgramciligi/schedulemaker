[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **prepareScheduleRows**

---
# ScheduleController::prepareScheduleRows(Schedule $schedule, $type, $maxDayIndex)

Bir `Schedule` nesnesine bağlı tüm `ScheduleItem` kayıtlarını tablo formatına sokar.

## Parametreler
*   `$schedule`: Verilerin çekileceği ana program başlığı.
*   `$type`: 'html' veya 'excel'.
*   `$maxDayIndex`: Gün sınırı.

## Algoritma
1.  `generateEmptyWeek` ile boş şablon oluşturulur.
2.  İlgili programın tüm `Items` kayıtları veritabanından çekilir.
3.  Her bir item için:
    *   Hangi gün (`day_index`) ve hangi saatte (`start_time`) olduğu belirlenir.
    *   Öğe, boş şablondaki ilgili hücreye yerleştirilir.
4.  **Ardışık Blok Yönetimi**: Eğer bir ders birden fazla saat sürüyorsa, tablo görünümünde "span" veya "merging" işlemleri için işaretlenir.

## Dönüş Değeri
*   `array`: Tablonun her bir satırını ve içindeki hücreleri temsil eden yapılı dizi.
