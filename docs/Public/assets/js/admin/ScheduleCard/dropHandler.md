[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / [ScheduleCard](README.md) / **dropHandler**

---
# ScheduleCard::dropHandler(element, event)

Sürükleme işlemi bittiğinde ve fare bırakıldığında tetiklenen ana karar verme metodudur.

## İşleyiş

1.  **State Kontrolü**: Eğer sistem zaten bir işlem yapıyorsa (`isProcessing`), yeni drop taleplerini yoksayar.
2.  **Veri Çözümleme**: `dataTransfer` üzerinden gelen ders ID'si ve tipini (`single`/`bulk`) ayıklar.
3.  **Hedef Analizi**: Fare nereye bırakıldı?
    *   **Tablodan Listeye**: Ders silme işlemi tetiklenir (`dropTableToList`).
    *   **Listeden Tabloya**: Yeni ders atama işlemi başlatılır (`dropListToTable`).
    *   **Tablodan Tabloya**: Dersin yerini değiştirme (Taşıma) işlemi yapılır (`dropTableToTable`).
4.  **Bulk (Toplu) İşlem**: Eğer birden fazla kart seçiliyse, her bir kart için bu akışı döngü içinde çalıştırır.
5.  **Temizlik**: Görsel seçimleri ve geçici işaretlemeleri (`slot-unavailable` vb.) temizler.
