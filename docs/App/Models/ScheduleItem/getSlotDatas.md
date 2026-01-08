[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Models](../README.md) / [ScheduleItem](./README.md) / **getSlotDatas**

---

# getSlotDatas()

Bu metod, `ScheduleItem` nesnesinin `data` alanında tutulan ders, öğretim elemanı ve derslik bilgilerini ilgili modeller üzerinden yükleyerek bir nesne dizisi olarak döndürür.

## Kullanım

```php
$slotDatas = $scheduleItem->getSlotDatas();
foreach ($slotDatas as $data) {
    echo $data->lesson->name;
    echo $data->lecturer->getFullName();
    echo $data->classroom->name;
}
```

## Hata Yönetimi

Metod, veritabanı tutarlılığını sağlamak için katı bir kontrol mekanizmasına sahiptir. Eğer `data` içinde belirtilen ID'lere sahip nesnelerden herhangi biri (Lesson, User/Lecturer, Classroom) veritabanında bulunamazsa, metod bir `\Exception` fırlatır.

### Fırlatılan İstisnalar

> [!IMPORTANT]
> Eğer aşağıdaki nesnelerden biri `null` dönerse, ilgili `ScheduleItem` ID'sini de içeren bir hata mesajıyla `Exception` fırlatılır:

1.  **Ders Bulunamadı**: "ScheduleItem ID: {id} için ders (ID: {lesson_id}) bulunamadı."
2.  **Öğretim Elemanı Bulunamadı**: "ScheduleItem ID: {id} için öğretim elemanı (ID: {lecturer_id}) bulunamadı."
3.  **Derslik Bulunamadı**: "ScheduleItem ID: {id} için derslik (ID: {classroom_id}) bulunamadı."

## Teknik Detaylar

- **Lesson**: `with(['childLessons', 'program'])` eager loading ile yüklenir.
- **Lecturer**: `User` modeli üzerinden yüklenir.
- **Classroom**: `Classroom` modeli üzerinden yüklenir.
