# Exception Logging İyileştirmeleri

## Sorun
Exception'lar loglanırken sadece mesaj görünüyor, context bilgileri (örn: validation error'ları) kayboluyordu.

**Önceki Log:**
```
app.ERROR: Schedule item validation failed
```

**İstenilen:**
```
app.ERROR: Schedule item validation failed: Item #0: data içinde lesson_id gerekli
Context: {
  "validation_errors": ["Item #0: data içinde lesson_id gerekli"],
  "item_count": 1
}
```

## Çözüm

### 1. AppException::__toString() Override
```php
public function __toString(): string
{
    $base = parent::__toString();
    
    if (!empty($this->context)) {
        $contextJson = json_encode($this->context, JSON_UNESCAPED_UNICODE);
        $base .= "\nContext: " . $contextJson;
    }
    
    return $base;
}
```

**Sonuç:** Exception string'e çevrildiğinde (log'a yazılırken) context otomatik eklenir.

### 2. ValidationException Mesaj İyileştirme
```php
public function __construct(
    string $message = 'Validation failed',
    array $validationErrors = [],
    array $context = []
) {
    // Validation error'larını mesaja ekle
    if (!empty($validationErrors)) {
        $message .= ': ' . implode('; ', $validationErrors);
    }
    
    $context['validation_errors'] = $validationErrors;
    parent::__construct($message, $context);
}
```

**Sonuç:** Validation error'ları hem mesajda hem context'te olur.

## Test Sonuçları

**Yeni Log Formatı:**
```
[2026-02-14T16:35:00] app.ERROR: 
Schedule item validation failed: Item #0: data içinde lesson_id gerekli
Context: {
  "validation_errors": ["Item #0: data içinde lesson_id gerekli"],
  "item_count": 1
}
```

## Diğer Exception'lar

Aynı yaklaşım tüm AppException türevleri için geçerli:
- `ScheduleConflictException` - çakışma detayları context'te
- `LessonHourExceededException` - ders bilgisi ve fazla saat context'te

## Best Practices

✅ **YAP:**
- Context'i kullan önemli bilgileri taşımak için
- Exception mesajını kısa ve açıklayıcı tut
- ValidationException için error'ları array olarak ver

❌ **YAPMA:**
- Exception throw etmeden önce manuel log yazma (gereksiz tekrar)
- Context'e çok büyük objeler koyma (performans)
- Hassas bilgileri (password gibi) context'e ekleme
