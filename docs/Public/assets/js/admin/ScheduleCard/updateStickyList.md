[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **updateStickyList**

---
# ScheduleCard.updateStickyList()

Sayfa kaydırıldığında üstte sabitlenen (sticky) ders listesinin içeriğini orijinal liste ile senkronize eder.

## Mantık (Algoritma)
1.  Orijinal ders listesinin (`.available-list`) HTML içeriğini alır.
2.  Bu içeriği, `sticky-header-wrapper` içindeki klonlanmış listenin içine kopyalar.
3.  Özellikle dersler tablodan listeye geri döndüğünde veya listeden tabloya taşındığında, her iki listenin de güncel kalmasını sağlar.
4.  Klonlanan listedeki `[data-bs-toggle="popover"]` elementleri için Bootstrap Popover'ları yeniden initialize eder. (`cloneNode(true)` Bootstrap instance'larını kopyalamaz.)
