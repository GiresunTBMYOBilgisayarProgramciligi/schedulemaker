# ScheduleController::saveScheduleItems(array $itemsData)

Ders programı öğelerini (ScheduleItems) toplu olarak veya tekil olarak kaydetmekten sorumlu ana metodur. Çok haftalı programları (`week_index`) destekler.

## Parametreler
*   `$itemsData`: Kaydedilecek öğelerin bilgilerini içeren dizi. İçeriğinde `lesson_id`, `lecturer_id`, `classroom_id`, `day_index`, `week_index`, `start_time`, `end_time` vb. bulunur.

## Algoritma Adımları

1.  **Transaction Başlatımı**: Veritabanı tutarlılığı için bir `beginTransaction` başlatılır.
2.  **Döngü**: Gelen her bir öğe verisi için (Örn: 2 saatlik blok ders için 2 ayrı öğe):
    *   **Hafta İndeksi**: `week_index` değeri veriden okunur (varsayılan: 0).
    *   **Hedef Sıfırlama**: İlgili ders saatine ait paydaş listesi (`$targetSchedules`) sıfırlanır.
    *   İlgili `Lesson` modeli veritabanından çekilir.
    *   Hoca, Sınıf, Program ve Ders bazlı paydaşlar belirlenir.
    *   **Bağlı Ders Senkronizasyonu**: Alt dersler için de paydaşlar oluşturulur.
3.  **Çakışma Taraması**:
    *   Belirlenen tüm paydaşların takvimleri taranır.
    *   **Haftaya Duyarlı Kontrol**: Sadece aynı `week_index` içindeki öğeler taranır.
    *   Eklenmek istenen zaman dilimiyle çakışan (`checkOverlap`) öğeler aranır.
    *   İhlal durumuna göre `resolvePreferredConflict` veya `resolveConflict` işletilir.
4.  **Kayıt / Güncelleme**:
    *   Öğe `group` ise `processGroupItemSaving` (hafta bilgisiyle) çağrılır.
    *   Değilse yeni bir `ScheduleItem` olarak (doğru `week_index` ile) kaydedilir.
5.  **Bitiş**: `commit` / `rollBack` işlemleri yapılır.

## Dönüş Değeri
*   `array`: Oluşturulan yeni öğelerin ID listesi (`createdIds`).
