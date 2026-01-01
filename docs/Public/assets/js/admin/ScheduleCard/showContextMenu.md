# showContextMenu

Belirtilen koordinatlarda ders kartına özel sağ tık menüsünü oluşturan ve görüntüleyen metottur.

## Tanım

```javascript
showContextMenu(x, y, lessonCard)
```

### Parametreler

- `x` (number): Menünün gösterileceği X koordinatı.
- `y` (number): Menünün gösterileceği Y koordinatı.
- `lessonCard` (HTMLElement): Üzerinde sağ tıklanan ders kartı elemanı.

## Açıklama

Ders kartı üzerindeki `data-lecturer-id` ve `data-classroom-id` değerlerini kontrol ederek dinamik bir menü oluşturur. Eğer ilgili ID'ler mevcutsa menüye "Hoca programını göster" veya "Derslik programını göster" seçeneklerini ekler.

## Menü Seçenekleri

- **Hoca programını göster:** Hocanın haftalık programını modal içerisinde açar.
- **Derslik programını göster:** Dersliğin doluluk programını modal içerisinde açar.

## Önemli Detaylar

- Menü oluşturulmadan önce varsa eski menü (`#lesson-context-menu`) DOM'dan kaldırılır.
- Menü elemanları için Bootstrap ikonları (`bi-person-badge`, `bi-door-open`) kullanılır.
- Menü `z-index: 2000` ile diğer elemanların üzerinde görünecek şekilde ayarlanır.

## Dosya Bilgisi

- **Dosya:** [ScheduleCard.js](file:///home/sametatabasch/PhpstormProjects/schedulemaker/Public/assets/js/admin/ScheduleCard.js)
- **Sınıf:** `ScheduleCard`
- **Konum:** [Satır: 295-333](file:///home/sametatabasch/PhpstormProjects/schedulemaker/Public/assets/js/admin/ScheduleCard.js#L295-L333) (Yaklaşık)
