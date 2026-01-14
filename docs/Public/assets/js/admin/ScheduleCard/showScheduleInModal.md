# showScheduleInModal

Belirtilen program sahibine (hoca veya derslik) ait programı AJAX ile çekip bir modal içerisinde görüntüleyen metottur.

## Tanım

```javascript
async showScheduleInModal(ownerType, ownerId, title)
```

### Parametreler

- `ownerType` (string): Programın sahibi türü (`user` veya `classroom`).
- `ownerId` (number|string): Sahibin benzersiz kimliği.
- `title` (string): Modal başlığı.

## Açıklama

`myHTMLElements.js` içindeki `Modal` sınıfını kullanarak bir "xl" boyutunda modal açar. Başlangıçta bir yükleniyor (spinner) gösterir, ardından `/ajax/getScheduleHTML` uç noktasına istek göndererek ilgili programı `only_table=true` parametresi ile çeker.

## İstek Parametreleri

İstek sırasında gönderilen `FormData` içeriği:
- `owner_type`: Parametre olarak gelen tür.
- `owner_id`: Parametre olarak gelen ID.
- `semester`: Mevcut programın dönemi.
- `academic_year`: Mevcut programın akademik yılı.
- `type`: Program türü (ders, sınav vb.).
- `only_table`: `true` (Sadece tablo formatında HTML almak için).

## Dosya Bilgisi

- **Dosya:** [ScheduleCard.js](file:///home/sametatabasch/PhpstormProjects/schedulemaker/Public/assets/js/admin/ScheduleCard.js)
- **Sınıf:** `ScheduleCard`
- **Konum:** [Satır: 338-375](file:///home/sametatabasch/PhpstormProjects/schedulemaker/Public/assets/js/admin/ScheduleCard.js#L338-L375) (Yaklaşık)
