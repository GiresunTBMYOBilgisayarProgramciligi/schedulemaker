# initContextMenu

Ders kartları (`.lesson-card`) için sağ tık menüsü (context menu) olayını başlatan metottur.

## Tanım

```javascript
initContextMenu()
```

## Açıklama

Bu metot, `this.card` elemanı üzerinde bir `contextmenu` olay dinleyicisi oluşturur. Bir kullanıcı ders kartına sağ tıkladığında varsayılan tarayıcı menüsünü engeller ve `showContextMenu` metodunu çağırarak özel menüyü gösterir. Ayrıca, sayfa üzerindeki herhangi bir yere tıklandığında açık olan menünün kapatılmasını sağlar.

## İşleyiş

1. `this.card` üzerine `contextmenu` olay dinleyicisi ekler.
2. Tıklanan öğenin `.lesson-card` olup olmadığını ve `dummy` sınıfına sahip olup olmadığını kontrol eder.
3. Eğer geçerli bir ders kartı ise `event.preventDefault()` ile tarayıcı menüsünü engeller.
4. `showContextMenu` metodunu tıklama koordinatları ve ders kartı öğesi ile çağırır.
5. `document` üzerine `click` dinleyicisi ekleyerek menünün kapatılmasını yönetir.

## Dosya Bilgisi

- **Dosya:** [ScheduleCard.js](file:///home/sametatabasch/PhpstormProjects/schedulemaker/Public/assets/js/admin/ScheduleCard.js)
- **Sınıf:** `ScheduleCard`
- **Konum:** [Satır: 275-290](file:///home/sametatabasch/PhpstormProjects/schedulemaker/Public/assets/js/admin/ScheduleCard.js#L275-L290) (Yaklaşık)
