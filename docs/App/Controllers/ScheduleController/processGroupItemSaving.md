[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **processGroupItemSaving**

---
# ScheduleController::processGroupItemSaving(...)

Grup derslerinin birleştirilmesi ve bölünmesi işlemlerini yöneten "Flatten Timeline" tabanlı algoritmadır.

## Algoritma: Flatten Timeline (Zaman Çizelgesi Düzleştirme)

1.  **Nokta Toplama**:
    *   Yeni eklenen dersin başlangıç/bitiş saatlerini al.
    *   O günkü mevcut tüm `group` öğelerinin başlangıç/bitiş saatlerini topla.
    *   Tüm bu saatleri benzersiz bir dizide (`points`) topla ve kronolojik olarak sırala.
2.  **Segment Oluşturma**:
    *   Sıralanan her iki ardışık nokta arasını birer "segment" (dilim) olarak kabul et.
    *   Her segment için:
        *   Bu dilimi kapsayan tüm derslerin verilerini (`data`) topla.
        *   Aynı ders ID'lerini temizle (`unique`).
        *   Mevcut detayları (`detail`) birleştir.
3.  **Optimizasyon**:
    *   Ardışık iki segmentin içeriği (dersler ve detaylar) tamamen aynıysa, bu iki segmenti tek bir blokta birleştir.
4.  **Veritabanı Güncelleme**:
    *   İşlem gören eski tüm `group` öğelerini sil.
    *   Hesaplanan yeni segmentleri yeni `ScheduleItem` kayıtları olarak oluştur.

## Neden Bu Yöntem?
Geleneksel yöntemlerde bir bloğun üstüne ders bindiğinde bloğu bölmek çok karmaşıktır. "Flatten Timeline" yönteminde ise zaman dilimlere bölünür ve her dilim bağımsız olarak hesaplanır, ardından benzer olanlar birleştirilir. Bu, hatasız ve esnek bir yapı sağlar.
