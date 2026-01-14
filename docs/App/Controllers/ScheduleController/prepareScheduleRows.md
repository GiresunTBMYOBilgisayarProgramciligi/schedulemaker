# ScheduleController::prepareScheduleRows(Schedule $schedule, $type, $maxDayIndex)

Bir `Schedule` nesnesine bağlı tüm `ScheduleItem` kayıtlarını tablo formatına sokar. Çok haftalı programları (Final sınavları gibi) destekler.

## Parametreler
*   `$schedule`: Verilerin çekileceği ana program başlığı.
*   `$type`: 'html' veya 'excel'.
*   `$maxDayIndex`: Gün sınırı.

## Algoritma
1.  **Hafta Sayısı Belirleme**: Program `final-exam` türündeyse 2 hafta, değilse 1 hafta olarak belirlenir.
2.  **Boş Şablon Oluşturma**: Her hafta için `generateEmptyWeek` ile boş şablon oluşturulur.
3.  İlgili programın tüm `Items` kayıtları veritabanından çekilir.
4.  Her bir item için:
    *   Hangi hafta (`week_index`), hangi gün (`day_index`) ve hangi saatte (`start_time`) olduğu belirlenir.
    *   Öğe, boş şablondaki ilgili hafta ve hücreye yerleştirilir.
5.  **Ardışık Blok Yönetimi**: Eğer bir ders birden fazla saat sürüyorsa, tablo görünümünde "span" veya "merging" işlemleri için işaretlenir.

## Dönüş Değeri
*   `array`: Haftalara göre gruplandırılmış (`$rows[week_index][row_index]` şeklinde), her bir satırı ve içindeki hücreleri temsil eden yapılı dizi.
