[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **deleteScheduleItems**

---
# ScheduleController::deleteScheduleItems(array $items)

Seçilen ders veya zaman aralıklarını tüm ilgili takvimlerden siler.

## Algoritma Adımları

1.  **Hedef Ders Belirleme (Smart Discovery)**:
    *   İstek içerisinde spesifik bir `lesson_id` varsa, sadece bu ders silinecekler listesine (`targetLessonIds`) eklenir.
    *   Eğer spesifik bir ID yoksa (örn: tüm zaman bloğunun silinmesi talep edildiğinde), o zaman slottaki **tüm** dersler otomatik olarak listeye eklenir.
    *   *Bu mantık, gruplu derslerde seçilmeyen derslerin yanlışlıkla silinmesini engeller.*
2.  **Paydaş Tespiti**: Belirlenmiş olan `targetLessonIds` listesine göre `findSiblingItems` çağrılarak, bu derslerin diğer takvimlerdeki (Hoca, Sınıf, Program) tüm kopyaları bulunur.
3.  **Aralık Birleştirme**: Aynı ID'ye sahip öğeler için gelen farklı silme talepleri zaman bazlı olarak birleştirilir.
4.  **Flatten Timeline Uygulaması**:
    *   Her bir paydaş öğe için `processItemDeletion` çağrılır.
    *   Metod, bloğu zaman çizelgesi üzerinde "düzleştirir" ve istenen aralığı çıkarıp geriye kalanları yeni bloklar olarak kaydeder.
4.  **ID Senkronizasyonu**: Silinen ID'ler ve bölünme (split) sonucu yeni oluşan ID'ler bir listede toplanır.

## Dönüş Değeri
*   `array`: `deletedIds` ve `createdItems` (yeni oluşan parçalar) bilgisini içeren dizi.
