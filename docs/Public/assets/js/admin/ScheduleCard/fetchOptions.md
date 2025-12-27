[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **fetchOptions**

---
# ScheduleCard.fetchOptions(day, startTime, duration)

Dersin yerleştirileceği zaman dilimine göre uygun olan derslik ve gözetmen seçeneklerini sunucudan çeker.

## Mantık (Algoritma)
1.  **İstek Gönderimi**: Seçili gün, başlangıç saati ve ders süresini kullanarak `/ajax/get-available-options` gibi bir endpoint'e AJAX isteği atar.
2.  **Paralel Getirme**: Hem `fetchAvailableClassrooms` hem de `fetchAvailableObservers` süreçlerini yönetir.
3.  **Sonuç İşleme**: Gelen verileri (müsait derslikler ve gözetmenler) bir modal veya dropdown içine doldurulmak üzere hazır hale getirir.
