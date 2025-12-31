[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **saveScheduleItems**

---
# ScheduleController::saveScheduleItems(array $itemsData)

Ders programı öğelerini (ScheduleItems) toplu olarak veya tekil olarak kaydetmekten sorumlu ana metodur.

## Parametreler
*   `$itemsData`: Kaydedilecek öğelerin bilgilerini içeren dizi. İçeriğinde `lesson_id`, `lecturer_id`, `classroom_id`, `day_index`, `start_time`, `end_time` vb. bulunur.

## Algoritma Adımları

1.  **Transaction Başlatımı**: Veritabanı tutarlılığı için bir `beginTransaction` başlatılır.
2.  **Döngü**: Gelen her bir öğe verisi için (Örn: 2 saatlik blok ders için 2 ayrı öğe):
    *   **Hedef Sıfırlama**: İlgili ders saatine ait paydaş listesi (`$targetSchedules`) sıfırlanır. *Bu adım, blok derslerde mükerrer kayıt hatasını önler.*
    *   İlgili `Lesson` modeli veritabanından çekilir.
    *   Hoca, Sınıf, Program ve Ders bazlı 4 farklı paydaş belirlenir:
        *   `user` (Hoca)
        *   `classroom` (Derslik) - *İstisna: Eğer ders tipi **UZEM (3)** ise bu paydaş atlanır (derslik programına kayıt yapılmaz).*
        *   `program` (Öğrenci Grubu/Bölüm)
        *   `lesson` (Dersin kendisi)
    *   **Bağlı Ders Senkronizasyonu**: Eğer ders bir "Ana Ders" ise, ona bağlı olan tüm **Alt Dersler** (Child Lessons) için de otomatik olarak `lesson` ve `program` paydaşları oluşturulur.
    *   Her bir paydaş için mevcut bir `Schedule` (Takvim başlığı) olup olmadığı kontrol edilir, yoksa oluşturulur (`firstOrCreate`).
3.  **Çakışma Taraması**:
    *   Belirlenen tüm paydaşların takvimleri taranır.
    *   Eklenmek istenen zaman dilimiyle çakışan (`checkOverlap`) mevcut öğeler aranır.
    *   Eğer çakışan öğe `preferred` (tercih edilen) statüsündeyse `resolvePreferredConflict` ile alan boşaltılır. Bu aşamada öğenin `description` (açıklama) verisi hafızaya alınır.
    *   Değilse `resolveConflict` ile kural ihlali (hata) olup olmadığına bakılır.
4.  **Kayıt / Güncelleme**:
    *   Hafızaya alınan `description` verisi, yeni oluşturulan dersin `detail` alanına `preferred => true` bayrağı ile birlikte eklenir.
    *   Eğer öğe `group` (birleştirilebilir grup dersi) statüsündeyse `processGroupItemSaving` çağrılır.
    *   Değilse normal bir `ScheduleItem` olarak oluşturulur.
5.  **Bitiş**: Tüm öğeler başarıyla işlendiyse `commit` yapılır, hata oluşursa `rollBack`.

## Dönüş Değeri
*   `array`: Oluşturulan yeni öğelerin ID listesi (`createdIds`). 
    *   **Yapı**: Her ders saati için owner tiplerine göre gruplandırılmış bir map döner.
    *   *Örn:* `[[ 'user' => [10], 'classroom' => [11], 'program' => [12], 'lesson' => [13] ], ...]`
    *   Bu yapı, frontend'in (Hoca, Derslik veya Program ekranı) kendine uygun olan doğru ID'yi seçmesini sağlar.
