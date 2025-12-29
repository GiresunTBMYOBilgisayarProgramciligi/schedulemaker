[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / **editSchedule**

---
# editSchedule.js

Bu script, ders programı düzenleme sayfasındaki `ScheduleCard` nesnelerinin başlatılmasından ve sayfa başlığının ayarlanmasından sorumludur.

## İşlevler

### ScheduleCard Başlatma

Script, `#schedule_container .card` seçicisi ile bulunan tüm elemanları dolaşır ve her biri için yeni bir `ScheduleCard` nesnesi oluşturur.

### Sayfa Başlığı Ayarlama

Sayfada en az bir schedule kartı varsa ve bu kart `data-schedule-screen-name` niteliğine sahipse, tarayıcı sekmesinin başlığı (`document.title`) bu değerle güncellenir.

```javascript
if (scheduleCardElements.length > 0 && scheduleCardElements[0].dataset.scheduleScreenName) {
    document.title = scheduleCardElements[0].dataset.scheduleScreenName;
}
```

### Drag & Drop Yönetimi

`lessonDrop` olayı dinlenerek, herhangi bir kartta bir ders bırakıldığında (drop işlemi), diğer tüm kartlardaki sürükleme durumu (`isDragging`) ve geçici görselleştirmeler (`clearCells`) temizlenir.
