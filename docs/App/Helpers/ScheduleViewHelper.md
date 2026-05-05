# ScheduleViewHelper

Schedule tablo görünümlerinde kullanılan ortak yardımcı sınıf.

**Dosya:** `App/Helpers/ScheduleViewHelper.php`

## Amaç

`examScheduleTable.php` ve `lessonScheduleTable.php` partial dosyalarındaki tekrarlanan kod bloklarını merkezileştirir. Lesson card HTML attribute'ları oluşturma, sürüklenebilirlik kontrolü ve attribute render işlemlerini sağlar.

## Metotlar

### `buildLessonCardAttributes()`

Lesson card div'i için data attribute dizisi oluşturur.

```php
public static function buildLessonCardAttributes(
    ScheduleItem $scheduleItem,
    object       $slotData,
    Schedule     $schedule,
    bool         $draggable,
    string       $type = 'lesson'  // 'lesson' veya 'exam'
): array
```

**Parametreler:**
- `$scheduleItem` — İlgili ScheduleItem nesnesi
- `$slotData` — Slot verisi (lesson, lecturer, classroom içerir)
- `$schedule` — Üst schedule nesnesi
- `$draggable` — Sürüklenebilir mi
- `$type` — Tablo tipi. `'exam'` için `h-100 m-0` sınıfı ve `data-detail` attribute'u eklenir. `'lesson'` için child lesson program bilgileri eklenir.

**Dönüş:** HTML attribute'larını key-value olarak içeren dizi.

**Tip bazlı farklılıklar:**

| Özellik | `lesson` | `exam` |
|---------|----------|--------|
| CSS Sınıfı | `lesson-card` | `lesson-card h-100 m-0` |
| `data-lesson-name` | `getFullName(addCode: true)` | `$lesson->name` |
| `data-detail` | ❌ | ✅ |
| Child lesson program attrs | ✅ | ❌ |

---

### `renderAttributes()`

Attribute dizisini HTML string'e dönüştürür. Tüm değerler `htmlspecialchars()` ile güvenli hale getirilir ve `null` değerler boş string'e dönüştürülür.

```php
public static function renderAttributes(array $attrs): string
```

---

### `isDraggable()`

Bir ders kartının sürüklenebilir olup olmadığını belirler.

```php
public static function isDraggable(
    object   $slotData,
    Schedule $schedule,
    bool     $onlyTable = false,
    bool     $preferenceMode = false
): bool
```

**Sürüklenemez koşullar:**
1. Bağlı (child) ders ise (`parent_lesson_id !== null`)
2. Akademik yıl mevcut ayar ile uyuşmuyorsa
3. Dönem mevcut ayar ile uyuşmuyorsa
4. Salt okunur tablo modunda ise (`$onlyTable = true`)
5. Tercih modunda ise (`$preferenceMode = true`)

## Kullanım Örneği

```php
use App\Helpers\ScheduleViewHelper;

$draggable = ScheduleViewHelper::isDraggable($slotData, $schedule, $onlyTable, $preferenceMode);
$dataAttrs = ScheduleViewHelper::buildLessonCardAttributes($scheduleItem, $slotData, $schedule, $draggable, 'exam');
$attrString = ScheduleViewHelper::renderAttributes($dataAttrs);
```

```html
<div <?= $attrString ?> role="button" aria-grabbed="false" tabindex="0">
    ...
</div>
```
