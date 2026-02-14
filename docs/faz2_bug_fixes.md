# Faz 2 Bug Fix Özeti

## Test Süreci ve Bulunan Hatalar

### 1. Validation Hatası ❌ ✅
**Hata:** `Schedule item validation failed: Item #0: data içinde lesson_id gerekli`

**Kök Neden:** 
- Validator `data[0]['lesson_id']` formatı beklerken
- Frontend `data: {"lesson_id": ...}` gönderiyordu

**Düzeltme:**
- Frontend'in doğru göndermesi lazım: `data: [{"lesson_id": ...}]` (array of objects)
- Validator güncellendi - sadece array of objects formatını kabul ediyor

### 2. Return Format Uyumsuzluğu ❌ ✅
**Hata:** `foreach() argument must be of type array|object, int given` (AjaxRouter.php:644)

**Kök Neden:**
- Yeni service `[1, 2, 3]` döndürüyordu
- Eski sistem `[[{'id': [1,2,3]}]]` nested format bekliyor

**Düzeltme:**
```php
// ScheduleController::formatServiceResultToLegacy()
return [
    ['id' => $result->createdIds]  // Nested format
];
```

### 3. checkScheduleCrash Data Format Hatası ❌ ✅
**Hata:** `Cannot access offset of type string on string`

**Kök Neden:**
- `checkItemConflict` metodu `data['lesson_id']` direkt erişiyordu
- Ama eski sistem `data[0]['lesson_id']` formatında

**Düzeltme:**
```php
// checkItemConflict - data JSON string olabilir ve array of objects formatında
$data = is_string($data) ? json_decode($data, true) : $data;
$lessonId = $data[0]['lesson_id'] ?? null;
```

### 4. Exception Logging İyileştirmesi ✅
**Sorun:** Exception loglarında context bilgisi yoktu

**Çözüm:**
```php
// AppException::__toString() - context otomatik loglara ekle
// ValidationException::__construct() - error'ları mesaja ekle
```

### 5. Frontend Data Format Standardizasyonu ❌ ✅
**Hata:** `generateScheduleItems` direkt object kullanıyordu

**Kök Neden:**
```javascript
// YANLIŞ
data: {
    "lesson_id": this.draggedLesson.lesson_id,
    ...
}
```

**Düzeltme:**
```javascript
// DOĞRU - ESKİ SİSTEM FORMATI
data: [{
    "lesson_id": this.draggedLesson.lesson_id,
    ...
}]
```

## Öğrenilenler

### Eski Sistem Veri Yapısı
**Schedule Item Data Formatı:**
```json
{
  "id": 131,
  "schedule_id": 531,
  "day_index": 2,
  "week_index": 0,
  "start_time": "08:00",
  "end_time": "10:50",
  "status": "single",  // veya "group", "preferred", "unavailable"
  "data": [  // DİKKAT: ARRAY OF OBJECTS!
    {
      "lesson_id": "503",
      "lecturer_id": "158",
      "classroom_id": "1"
    }
  ],
  "detail": null
}
```

**Neden Array of Objects?**
- **Single item:** 1 elemanlı array: `[{lesson_id: 503, ...}]`
- **Group item:** Çok elemanlı array: `[{lesson_id: 503}, {lesson_id: 504}]`
- **Preferred/Unavailable:** `data` null

### Kritik Pattern'ler

1. **Fallback Pattern (Controller):**
   ```php
   $lessonId = $itemData['data'][0]['lesson_id'] ?? $itemData['data']['lesson_id'] ?? null;
   ```
   ❌ Bu yanlış - eski sistem sadece array of objects kullanıyor!

2. **JSON String Check:**
   ```php
   $data = is_string($data) ? json_decode($data, true) : $data;
   ```
   ✅ Model'den gelen veri JSON string olabilir

3. **Exception Context:**
   ```php
   throw new ValidationException('message', $errors, ['context' => 'data']);
   ```
   ✅ Context otomatik loglanır (__toString override sayesinde)

## Sonraki Adımlar

1. ✅ Tüm data format kullanımları standardize edildi
2. ⏳ Unit testler yazılacak
3. ⏳ Parallel testing (eski vs yeni sistem karşılaştırma)
4. ⏳ v2.0 özellikleri: Conflict resolution, multi-schedule, group items

---

## 6. Duplicate Entry Hatası - Group Items ❌ ⚠️ (Geçici Çözüm)

**Hata:** `Duplicate entry '531-1-0-08:00:00-11:50:00' for key 'schedule_items.schedule_id'`

**Kök Neden:**
- Yeni ScheduleService v1.0 basit INSERT yapıyor
- Group item'lar için **merge/replace mantığı YOK**
- Eski sistem `processGroupItemSaving` ile çakışan itemları silip yeniden oluşturuyordu

**Geçici Çözüm:**
```php
// ScheduleController::saveScheduleItems()
// Group item'lar için feature flag bypass
if (FeatureFlags::useNewScheduleService() && !$hasGroupItems) {
    // Yeni service - sadece single/preferred/unavailable
} else {
    // Eski sistem - group items için
}
```

**Durum:** ⚠️ Group item'lar şu an eski sistem kullanıyor  
**TODO:** v2.0'da group item merge mantığı implement edilecek

---

## Bilinen Sorunlar (v2.0'da Çözülecek)

### 1. Multi-Schedule Kaydetme
**Sorun:** Sadece program programına kaydediliyor, ders/derslik/hoca programlarına kaydedilmiyor  
**Neden:** `ScheduleService.php:L196-200` TODO olarak işaretlenmiş  
**Çözüm:** v2.0'da owner detection ve multi-schedule creation

### 2. resolveConflict Group Data Kontrolü
**Sorun:** Sadece `data[0]['lecturer_id']` kontrol ediliyor  
**Neden:** Group item'larda `data[1]`, `data[2]` de olabilir  
**Çözüm:** Tüm data array'ini loop ile kontrol et

### 3. Uygun Derslik Listesi
**Sorun:** Doğru gelmiyor (kullanıcı raporu)  
**TODO:** Detaylı debugging gerekiyor

