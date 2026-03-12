[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **dropHandler**

---
# ScheduleCard.dropHandler(e)

Sürüklenen ders kartı bir tablo hücresine veya tekrar listeye bırakıldığında tetiklenen ana mantık yöneticisidir.

## Mantık (Algoritma)
1.  **Hazırlık**: Varsayılan davranışları engeller ve sürükleme stilini (`.dragging`) kaldırır.
2.  **Hedef Belirleme**: Bırakılan yer bir tablo hücresi (`<td>`) ise:
    - Hücreden `day` ve `time` (saat) bilgilerini alır.
    - **Çakışma Kontrolü**: `checkCrash()` metodunu çağırarak dersin oraya sığıp sığmadığını, hoca/derslik çakışması olup olmadığını denetler.
    - **İşlem Tipi**:
        - Listeden tabloya çekiliyorsa (`start_element == "list"`): `refreshScheduleCard()` ile UI güncellenir.
        - Tablo içinde yer değiştiriyorsa (`start_element == "table"`): Mevcut kaydı günceller veya bölerek taşır.
3.  **Veri Senkronizasyonu**: `dataTransfer` üzerinden gelen ders verilerini parse eder. Eğer veri boş veya hatalıysa işlemi güvenli bir şekilde sonlandırır.
4.  **Listeye İade**: Eğer kart tekrar `available-schedule-items` (sol liste) üzerine bırakılmışsa, `dropTableToList()` metodunu çağırarak dersi tablodan siler ve listeye geri gönderir.
5.  **Temizlik**: `clearCells()` ile hücrelerdeki vurguları, `clearSelection()` ile toplu seçimleri kaldırır ve `resetDraggedLesson()` ile süreci sonlandırır.
