[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **deleteScheduleItems**

---
# ScheduleController::deleteScheduleItems(array $items)

Seçilen ders veya zaman aralıklarını tüm ilgili takvimlerden siler.

## Algoritma Adımları

1.  **Hedef Ders Belirleme (Smart Discovery)**:
    *   İstek içerisinde spesifik bir `lesson_id` varsa, sadece bu ders silinecekler listesine (`targetLessonIds`) eklenir.
    *   Eğer spesifik bir ID yoksa (örn: tüm zaman bloğunun silinmesi talep edildiğinde), o zaman slottaki **tüm** dersler otomatik olarak listeye eklenir.
    *   *Bu mantık, gruplu derslerde seçilmeyen derslerin yanlışlıkla silinmesini engeller.*
2.  **Paydaş Tespiti**: 
    *   Belirlenmiş olan `targetLessonIds` listesine göre `findSiblingItems` çağrılarak, bu derslerin diğer takvimlerdeki (Hoca, Sınıf, Program) kopyaları bulunur.
    *   **Zaman Kısıtı**: Sibling tespiti, sadece silinmek istenen öğe ile **zaman çakışması (overlap)** olan kayıtları kapsayacak şekilde daraltılmıştır. Bu, farklı saatlerdeki blokların birbirini "işlendi" diyerek engellemesini önler.
3.  **Aralık Birleştirme**: Aynı ID'ye sahip öğeler için gelen farklı silme talepleri zaman bazlı olarak birleştirilir.
4.  **Atomik Silme (Delete-All-Before-Insert)**:
    *   `Duplicate Entry` hatalarını önlemek için, yeni parçalar oluşturulmadan önce tüm paydaş öğeler veritabanından topluca silinir.
5.  **Flatten Timeline & Boundary Check**:
    *   Her bir paydaş öğe için `processItemDeletion` çağrılır.
    *   **Sınır Kontrolü**: Yeni oluşan parçaların (segments) orijinal öğenin zaman sınırları (`start_time` - `end_time`) içinde kalması kesin olarak sağlanır.
    *   Metod, bloğu zaman çizelgesi üzerinde "düzleştirir" ve istenen aralığı çıkarıp geriye kalanları yeni bloklar olarak kaydeder.
6.  **ID Senkronizasyonu**: Silinen ID'ler ve bölünme (split) sonucu yeni oluşan ID'ler bir listede toplanır.

## Dönüş Değeri
*   `array`: `deletedIds` ve `createdItems` (yeni oluşan parçalar) bilgisini içeren dizi.
