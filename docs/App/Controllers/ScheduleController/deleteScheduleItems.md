[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **deleteScheduleItems**

---
# ScheduleController::deleteScheduleItems(array $items)

Seçilen ders veya zaman aralıklarını tüm ilgili takvimlerden siler.

## Algoritma Adımları

1.  **Paydaş Tespiti**: Silinmek istenen her bir öğe için `findSiblingItems` çağrılarak, o dersin diğer takvimlerdeki (Hoca, Sınıf, Program) tüm kopyaları bulunur.
2.  **Aralık Birleştirme**: Aynı ID'ye sahip öğeler için gelen farklı silme talepleri (Eğer parça parça geliyorsa) zaman bazlı olarak birleştirilir.
3.  **Flatten Timeline Uygulaması**:
    *   Her bir öğe için `processItemDeletion` çağrılır.
    *   Metod, bloğu zaman çizelgesi üzerinde "düzleştirir" ve istenen aralığı çıkarıp geriye kalanları yeni bloklar olarak kaydeder.
4.  **ID Senkronizasyonu**: Silinen ID'ler ve bölünme (split) sonucu yeni oluşan ID'ler bir listede toplanır.

## Dönüş Değeri
*   `array`: `deletedIds` ve `createdItems` (yeni oluşan parçalar) bilgisini içeren dizi.
