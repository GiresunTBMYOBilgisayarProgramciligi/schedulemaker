[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **processItemDeletion**

---
# ScheduleController::processItemDeletion(...)

Bir ders programı bloğundan belirli bir dersin veya zaman diliminin "cerrahi" bir şekilde çıkarılmasını sağlar.

## Algoritma Adımları

1.  **Atomik Parçalama**:
    *   İşlem gören bloğu; ders süresi (`duration`) ve teneffüs (`break`) sınırlarına göre küçük parçalara böler.
    *   Silinmek istenen zaman aralığının başlangıç/bitiş noktalarını da bu parçalama sınırlarına ekler.
2.  **Seçici Filtreleme**:
    *   Eğer `targetLessonIds` boşsa, o zaman dilimindeki tüm blok imha edilir.
    *   Eğer `targetLessonIds` doluysa (örn: gruplu dersin bir parçası), sadece listedeki dersler `data` (ders listesi) içerisinden çıkartılarak filtreleme yapılır; diğer dersler korunur.
    *   **Preferred Slot Geri Kazanımı**: Eğer silinen dersin `detail` alanında `preferred => true` bayrağı varsa, silme işlemi sonrası o alan tamamen boşalmak yerine tekrar `statüsü preferred` olan boş bir slot haline getirilir. Orijinal açıklama (`description`) korunur.
3.  **Teneffüs (Break) Sanitasyonu**:
    *   Eğer bir teneffüs diliminin hem öncesinde hem sonrasında ders kalmadıysa, o teneffüs de otomatik olarak silinir (Yetim teneffüslerin önlenmesi).
4.  **Yeniden Birleştirme**:
    *   Yan yana duran ve verisi (ders listesi) aynı olan parçaları tek bir `ScheduleItem` bloğu haline getirir.
5.  **Veritabanı Senkronizasyonu**:
    *   Orijinal Blok silinir.
    *   Oluşan yeni parçalar (parçalanma olduysa birden fazla) yeni kayıtlar olarak eklenir.

## Teknik Detay
Bu metod, silme işleminin sadece görsel değil, veritabanı seviyesinde de "bölünmüş" bloklar oluşturmasını sağlar.
