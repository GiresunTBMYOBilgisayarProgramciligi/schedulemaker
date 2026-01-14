[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **LayoutHelpers**

---
# ScheduleCard Layout ve Scroll Yardımcı Metotları

Tablo görünümü ve yapışkan başlıkların (sticky header) görsel bütünlüğünü sağlayan metotlar.

## [syncWidths()](./syncWidths.md)
Klonlanmış başlık hücrelerinin genişliğini, orijinal tablodaki hücrelerin anlık genişliğiyle birebir aynı yapar. Pencer boyutlandırıldığında veya içerik değiştiğinde çalıştırılır.

## [getStickyWrapper()](./getStickyWrapper.md)
Ekranın üstünde sabitlenen kapsayıcı elemente kolay erişim sağlar.

## [getScrollContainer()](./getScrollContainer.md)
Tablonun içinde bulunduğu kaydırılabilir (scrollable) alanı döner.

## [handleScroll()](./handleScroll.md)
Dikey kaydırma (vertical scroll) sırasında yapışkan başlıkların ne zaman görünüp ne zaman gizleneceğine karar veren lojiktir.

## [handleHorizontalScroll()](./handleHorizontalScroll.md)
Tablo yatayda kaydırıldığında, üstteki sabitlenmiş başlığın da tabloyla eşzamanlı kaymasını sağlar.
