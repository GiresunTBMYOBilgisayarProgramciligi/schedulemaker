[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **syncTableItems**

---
# ScheduleCard.syncTableItems(updatedItems, externalTemplates)

Sunucudan gelen güncel ders verilerini, tablodaki mevcut HTML elemanları ile eşleştirir ve `data-` özniteliklerini günceller.

## Mantık (Algoritma)
1.  Sunucudan dönen her bir ders nesnesi (`updatedItems`) için:
    - **ID Filtresi**: Gelen öğenin `schedule_id` değeri ile aktif programın ID'si karşılaştırılır. Sadece aktif programa ait olan öğeler işlenir.
    - **Hücre Bulma**: Öğenin `day_index` ve `start_time` / `end_time` bilgilerine göre tablodaki ilgili hücreler tespit edilir.
    - **Veri Senkronizasyonu**:
        - Hücredeki mevcut kartların `data-schedule-item-id` değerlerini günceller.
        - Eğer hücrede o ders için kart yoksa (örneğin split sonrası yeni oluşan bir parça), uygun bir template (kart örneği) bulur. Arama sırası:
            1. `externalTemplates` parametresindeki kopyalar (silinmeden önce alınanlar).
            2. Tablodaki diğer mevcut kartlar.
            3. `this.draggedLesson.HTMLElement` (eğer sürüklenen ders ise).
        - Bulunan template'i kullanarak yeni bir kart oluşturur ve hücreye/gruba ekler.

    - **Grup Desteği**: `item.data` içindeki tüm dersleri (multiple assignment/group) kontrol eder ve her biri için gerekli kart eşleşmesini veya oluşturulmasını sağlar.
2.  Bu işlem, özellikle toplu kaydetme veya ders bölme (split) işlemlerinden sonra ID çakışmalarını önlemek için kritiktir.
