# ScheduleController::processGroupItemSaving(...)

Grup derslerinin birleştirilmesi ve bölünmesi işlemlerini yöneten "Flatten Timeline" tabanlı algoritmadır. Hafta bazlı (`week_index`) işlem yapar.

## Algoritma: Flatten Timeline (Zaman Çizelgesi Düzleştirme)

1.  **Nokta Toplama**:
    *   Yeni eklenen dersin başlangıç/bitiş saatlerini al.
    *   İlgili hafta (`week_index`) ve o günkü mevcut tüm `group` öğelerinin saatlerini topla.
    *   Tüm bu saatleri benzersiz bir dizide (`points`) topla ve kronolojik olarak sırala.
2.  **Segment Oluşturma**:
    *   Sıralanan her iki ardışık nokta arasını birer "segment" (dilim) olarak kabul et.
    *   Her segment için:
        *   Bu dilimi kapsayan tüm derslerin verilerini (`data`) topla.
        *   Aynı ders ID'lerini temizle (`unique`).
        *   Mevcut detayları (`detail`) birleştir.
3.  **Optimizasyon**:
    *   Ardışık iki segmentin içeriği tamamen aynıysa, bu iki segmenti tek bir blokta birleştir.
4.  **Veritabanı Güncelleme**:
    *   İşlem gören eski tüm `group` öğelerini (ilgili hafta/gün/saat aralığındaki) sil.
    *   Hesaplanan yeni segmentleri yeni `ScheduleItem` kayıtları (doğru `week_index` ile) olarak oluştur.

## Neden Bu Yöntem?
Geleneksel yöntemlerde bir bloğun üstüne ders bindiğinde bloğu bölmek çok karmaşıktır. "Flatten Timeline" yönteminde ise zaman dilimlere bölünür ve her dilim bağımsız olarak hesaplanır. Haftalık yapı ile birleştiğinde, farklı haftalardaki grup derslerinin birbirini etkilememesi sağlanır.
