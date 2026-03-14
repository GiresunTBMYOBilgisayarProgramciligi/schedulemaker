# Ders Programı Oluşturma Algoritması

Bu dokümantasyon, ders programı oluşturma sürecinin detaylı algoritmasını ve akış diyagramını içermektedir.

## Genel Bakış

Ders programı oluşturma sistemi, dersleri belirli zaman dilimlerine atarken çakışmaları kontrol eder, grup derslerini yönetir ve tüm paydaşların (öğretim görevlileri, derslikler, programlar, dersler) programlarını senkronize eder.

## Ana Kavramlar

### Schedule (Program)
Her paydaş için ayrı bir program nesnesi oluşturulur:
- **owner_type**: `user` (öğretim görevlisi), `classroom` (derslik), `program` (bölüm programı), `lesson` (ders)
- **owner_id**: İlgili kaydın ID'si
- **type**: `lesson` (ders programı), `midterm-exam` (vize), `final-exam` (final), `makeup-exam` (bütünleme)
- **semester**: Bahar/Güz dönemi
- **academic_year**: Akademik yıl
- **semester_no**: Dönem numarası (sadece program ve ders için)

### ScheduleItem (Program Öğesi)
Programdaki her zaman dilimi için bir öğe:
- **day_index**: Gün (0=Pazartesi, 6=Pazar)
- **week_index**: Hafta numarası (çok haftalı programlar için)
- **start_time**: Başlangıç saati (HH:MM formatında)
- **end_time**: Bitiş saati (HH:MM formatında)
- **status**: Durum (`single`, `group`, `preferred`, `unavailable`)
- **data**: Ders, öğretim görevlisi, derslik bilgileri
- **detail**: Ek detaylar

### Status Türleri

1. **single**: Normal tekil ders
   - Üzerine başka ders eklenemez
   - Aynı zaman diliminde çakışma yasaktır

2. **group**: Grup dersleri
   - Birden fazla ders aynı saatte olabilir
   - Farklı grup numaralarına sahip olmalıdırlar
   - Aynı öğretim görevlisi farklı gruplarda olamaz

3. **preferred**: Tercih edilen saat aralığı (dummy card)
   - Öğretim görevlisinin tercih ettiği saatler
   - Gerçek ders eklendiğinde otomatik çözümlenir

4. **unavailable**: Müsait olmayan saat aralığı (dummy card)
   - Bu saatte ders atanamaz

## Algoritma Akışı

### 1. saveScheduleItems - Ana Kaydetme Metodu

```
GİRDİ: itemsData (kaydedilecek program öğeleri dizisi)
ÇIKTI: createdIds (oluşturulan öğelerin ID listesi)
```

#### Adımlar:

**1.1. Transaction Başlatma**
- Veri tutarlılığı için transaction başlatılır
- Hata durumunda rollback yapılabilir

**1.2. Her Öğe için Döngü**

Her `itemData` için:

**1.2.1. Veri Hazırlığı**
```
- İsDummy kontrolü (status: preferred veya unavailable)
- Eğer değilse:
  - lesson_id, lecturer_id, classroom_id çıkarılır
  - İlgili Lesson modeli child lessons ile birlikte çekilir
```

**1.2.2. Paydaş Belirleme (Owners)**

Dummy değilse tüm ilgili paydaşlar belirlenir:

```
Ana Paydaşlar:
- user (öğretim görevlisi)
- classroom (derslik - UZEM değilse)
- program (bölüm programı)
- lesson (ders)

Bağlı Dersler (Child Lessons):
- Her child lesson için:
  - lesson (child ders)
  - program (child dersin programı)
```

**1.2.3. Hedef Schedule Bulma**
```
- itemData içindeki schedule_id'den hedef Schedule bulunur
- semester ve academic_year bilgileri alınır
```

**1.2.4. Çakışma Kontrolü**

Her paydaş için:

```pseudo
FOR EACH owner IN owners:
    # İlgili schedule'ı bul veya oluştur
    schedule = findOrCreateSchedule(owner, semester, academic_year, type)
    
    # Aynı zaman dilimindeki mevcut öğeleri bul
    existingItems = findItemsByTime(schedule, day_index, week_index, start_time, end_time)
    
    FOR EACH existingItem IN existingItems:
        IF checkOverlap(new_time, existing_time):
            # Çakışma tespit edildi - çözümleme stratejisi belirle
            
            IF existingItem.status == 'preferred':
                # Preferred item'i çözümle (kısalt/böl/sil)
                resolvePreferredConflict(new_time, existingItem)
            ELSE:
                # Çakışma kurallarını kontrol et
                # Not: owner listesindeki 'lesson_context' (ana ders veya çocuk ders) kullanılır
                lessonContext = owner.lesson_context ?: lesson
                error = resolveConflict(newItemData, existingItem, lessonContext, schedule)
                IF error:
                    THROW EXCEPTION(error)
```

**1.2.5. Grup İşleme**

Eğer status = 'group' ise:
```
processGroupItemSaving(schedule, day_index, start_time, end_time, data, detail, week_index)
```

Normal ise:
```
# Yeni ScheduleItem oluştur ve kaydet
newItem = new ScheduleItem(schedule_id, day_index, week_index, start_time, end_time, status, data, detail)
newItem.create()
```

**1.2.6. Haftalık Saat Kontrolü**

Her etkilenen ders için:
```
checkLesson.IsScheduleComplete(type)
IF checkLesson.remaining_size < 0:
    THROW EXCEPTION("Ders saati aşıldı")
```

**1.3. Transaction Commit**
- Tüm işlemler başarılıysa commit
- Hata varsa rollback

---

### 2. checkOverlap - Çakışma Kontrolü

İki zaman aralığının çakışıp çakışmadığını kontrol eder.

```
GİRDİ: start1, end1, start2, end2
ÇIKTI: boolean (çakışma var/yok)

MANTIK: (start1 < end2) AND (start2 < end1)
```

**Örnek Senaryolar:**

```
Durum 1: Tam Çakışma
[08:00---10:00]  (Yeni)
  [09:00---11:00]  (Mevcut)
Sonuç: ÇAKIYOR

Durum 2: Çakışma Yok
[08:00--09:00]  (Yeni)
            [10:00--11:00]  (Mevcut)
Sonuç: ÇAKIŞMIYOR

Durum 3: Kısmi Çakışma
[08:00-----10:00]  (Yeni)
      [09:00-----11:00]  (Mevcut)
Sonuç: ÇAKIYOR
```

---

### 3. resolveConflict - Çakışma Çözümleme

Çakışma tespit edildiğinde kuralları kontrol eder.

```
#### Gruplandırılmış ve Birleştirilmiş Ders Kuralları:

- **Ders Bağlamı (Lesson Context)**: Birleştirilmiş derslerde, her program (bölüm) kendi çocuk ders sayfasını görür. Çakışma kontrolü yapılırken program bazlı olarak o programda kayıtlı olan fiziksel `Lesson` nesnesi (`lesson_context`) baz alınır. Bu sayede ana dersin grubu ile çocuk dersin grubunun farklı olması durumunda hatalı çakışmalar önlenir.
- **Kardeş Kontrolü**: Aynı dersin veya birleşmiş halinin (parent_id eşleşmesi) aynı saatte tekrar eklenmesi çakışma sebebi sayılır (Grup dersi olsa bile).
```

#### Kurallar:

**3.1. Status: unavailable**
```
SONUÇ: HATA - "Bu saat aralığı uygun değil"
```

**3.2. Status: single**
```
SONUÇ: HATA - "Bu saatte zaten bir ders mevcut: [Ders Adı]"
```

**3.3. Status: group**
```
IF newLesson.group_no < 1:
    SONUÇ: HATA - "Grup dersi üzerine normal ders eklenemez"

FOR EACH existingGroupLesson IN existingItem.data:
    # Aynı ders kontrolü
    IF existingGroupLesson.id == newLesson.id:
        SONUÇ: HATA - "Aynı ders aynı saatte tekrar eklenemez"
    
    # Aynı öğretim görevlisi kontrolü
    IF existingGroupLesson.lecturer_id == newLesson.lecturer_id:
        SONUÇ: HATA - "Hoca aynı anda iki farklı derse giremez"
    
    # Aynı grup numarası kontrolü
    IF existingGroupLesson.group_no == newLesson.group_no:
        SONUÇ: HATA - "Aynı grup numarasına sahip dersler çakışamaz"

SONUÇ: BAŞARILI (Grup kurallarına uygun)
```

**3.4. Status: preferred**
```
SONUÇ: BAŞARILI (Tercih edilen saat, çakışma yok - resolvePreferredConflict çağrılır)
```

---

### 4. resolvePreferredConflict - Preferred Item Çözümlemesi

Preferred item ile çakışma durumunda item'i günceller.

```
GİRDİ: newStart, newEnd, preferredItem
```

#### Durumlar:

**4.1. Tam Kapsama**
```
Pref:  [10:00----------12:00]
New:    [10:00----------12:00]

SONUÇ: preferredItem.delete()
```

**4.2. Son Taraf Örtüşmesi**
```
Pref:  [10:00----------12:00]
New:            [11:00----------13:00]

SONUÇ: 
    preferredItem.end_time = "11:00"
    preferredItem.update()
    
Yeni Pref: [10:00--11:00]
```

**4.3. Baş Taraf Örtüşmesi**
```
Pref:          [10:00----------12:00]
New:    [09:00----------11:00]

SONUÇ: 
    preferredItem.start_time = "11:00"
    preferredItem.update()
    
Yeni Pref:     [11:00--12:00]
```

**4.4. Ortada Bölme**
```
Pref:  [10:00--------------------14:00]
New:            [11:00--12:00]

SONUÇ:
    # Sol parça
    preferredItem.end_time = "11:00"
    preferredItem.update()
    
    # Sağ parça (yeni oluştur)
    rightPart.start_time = "12:00"
    rightPart.end_time = "14:00"
    rightPart.create()
    
Yeni Durumu:
    [10:00-11:00]  [12:00----14:00]
```

---

### 5. Zaman Çizelgesi Yönetimi ve Dilimleme (TimelineService)

`ScheduleService` sınıfının en karmaşık işlemlerinden biri olan zaman çizelgesini yönetme (ekleme, silme, parçalama) işlemleri `App\Services\TimelineService` sınıfına delege edilmiştir.

Bu servis, **Flatten Timeline (Düzleştirilmiş Zaman Çizelgesi)** yaklaşımını kullanarak program öğelerini atomik dilimlere ayırır, işler ve tekrar birleştirir.

#### 5.1. Flatten Timeline (Düzleştirme) Yaklaşımı
1. Mevcut ve yeni öğelerin tüm kritik zaman noktaları (başlangıç, bitiş, teneffüs sınırları) toplanır.
2. Bu noktalara göre çizelge küçük parçalara (segment) bölünür.
3. Her parça için veriler (data) ve kurallar (isBreak, shouldKeep) uygulanır.
4. Bitişken ve aynı veriye sahip parçalar birleştirilerek nihai program öğeleri oluşturulur.

#### 5.2. mergeGroupItems - Grup Item İşleme
Grup statusundaki itemleri birleştirir ve yeniden oluşturur.

```
GİRDİ: schedule, dayIndex, startTime, endTime, newData, newDetail, weekIndex
ÇIKTI: createdGroupIds (oluşturulan grup item ID'leri)
```

#### Adımlar:

**5.1. Mevcut Grup Itemlerini Getir**
```
allDayItems = findGroupItems(schedule, dayIndex, weekIndex)
involvedItems = filterOverlappingItems(allDayItems, startTime, endTime)
```

**5.2. Hiç Çakışma Yoksa**
```
IF involvedItems.isEmpty():
    newItem = createScheduleItem(schedule, dayIndex, weekIndex, startTime, endTime, newData, newDetail)
    RETURN [newItem.id]
```

**5.3. Zaman Çizelgesini Düzleştir (Timeline Flattening)**

Tüm başlangıç ve bitiş noktalarını topla:
```
points = [newStart, newEnd]
FOR EACH item IN involvedItems:
    points.add(item.start)
    points.add(item.end)

points = unique(points)
sort(points)
```

**Örnek:**
```
Yeni:       [09:00----------11:00]
Mevcut 1:      [08:00--09:30]
Mevcut 2:                [10:00--12:00]

Points: [08:00, 09:00, 09:30, 10:00, 11:00, 12:00]
```

**5.4. Aralıkları Yeniden Oluştur**

Her ardışık nokta çifti için:
```
FOR i = 0 TO points.length - 2:
    pStart = points[i]
    pEnd = points[i + 1]
    
    mergedData = []
    mergedDetail = []
    
    # Yeni veri bu aralığı kapsıyor mu?
    IF newStartTime <= pStart AND newEndTime >= pEnd:
        mergedData.merge(newData)
        mergedDetail.merge(newDetail)
    
    # Mevcut itemler bu aralığı kapsıyor mu?
    FOR EACH item IN involvedItems:
        IF item.start <= pStart AND item.end >= pEnd:
            mergedData.merge(item.data)
            mergedDetail.merge(item.detail)
    
    # Duplicate lesson'ları temizle
    mergedData = removeDuplicateLessons(mergedData)
    
    # Optimization: Önceki item ile aynıysa birleştir
    IF lastItem.end == pStart AND dataEquals(lastItem.data, mergedData):
        lastItem.end = pEnd
    ELSE:
        pendingItems.add({start: pStart, end: pEnd, data: mergedData, detail: mergedDetail})
```

**5.5. Veritabanı İşlemleri**
```
# Eski itemleri sil
FOR EACH item IN involvedItems:
    item.delete()

# Yeni birleştirilmiş itemleri oluştur
FOR EACH pItem IN pendingItems:
    newItem = createScheduleItem(schedule, dayIndex, weekIndex, pItem.start, pItem.end, pItem.data, pItem.detail)
    createdGroupIds.add(newItem.id)

RETURN createdGroupIds
```

**Örnek Senaryo:**
```
Başlangıç:
Ders A (Grup 1): [09:00----------11:00]
Ders B (Grup 2):    [08:00--09:30]

İşlem: Ders C (Grup 3) ekle [10:00----------12:00]

Adımlar:
1. Points: [08:00, 09:00, 09:30, 10:00, 11:00, 12:00]

2. Aralıklar:
   [08:00-09:00]: Ders B
   [09:00-09:30]: Ders A, Ders B
   [09:30-10:00]: Ders A
   [10:00-11:00]: Ders A, Ders C
   [11:00-12:00]: Ders C

3. Veritabanı:
   - Eski A ve B silinir
   - 5 yeni grup item oluşturulur
```

---

### 6. availableClassrooms - Müsait Derslik Kontrolü

Belirtilen zaman dilimi için müsait derslikleri bulur.

```
GİRDİ: filters {schedule_id, lesson_id, day_index, week_index, items: [{start_time, end_time}]}
ÇIKTI: availableClassrooms (müsait derslik listesi)
```

#### Adımlar:

**6.1. Derslik Türü Belirleme**
```
lesson = findLesson(filters.lesson_id)

IF schedule.type IN ['midterm-exam', 'final-exam', 'makeup-exam']:
    # Sınav programı - UZEM hariç tüm derslikler
    classrooms = findClassrooms(type != 3)
ELSE:
    # Ders programı
    IF lesson.classroom_type == 4:  # Karma
        classroom_types = [1, 2]  # Derslik ve Lab
    ELSE:
        classroom_types = [lesson.classroom_type]
    
    classrooms = findClassrooms(type IN classroom_types)
```

**6.2. Her Derslik için Müsaitlik Kontrolü**
```
FOR EACH classroom IN classrooms:
    # İlgili schedule'ı bul veya oluştur
    classroomSchedule = findOrCreateSchedule(
        owner_type: 'classroom',
        owner_id: classroom.id,
        semester: schedule.semester,
        academic_year: schedule.academic_year,
        type: schedule.type
    )
    
    # Mevcut schedule itemlerini getir
    existingItems = findItems(
        schedule_id: classroomSchedule.id,
        day_index: filters.day_index,
        week_index: filters.week_index
    )
    
    isAvailable = true
    
    # UZEM her zaman müsait
    IF classroom.type == 3:
        isAvailable = true
    ELSE:
        # Çakışma kontrolü
        FOR EACH checkItem IN filters.items:
            FOR EACH existingItem IN existingItems:
                IF checkOverlap(checkItem.start_time, checkItem.end_time, existingItem.start_time, existingItem.end_time):
                    isAvailable = false
                    BREAK
    
    IF isAvailable:
        availableClassrooms.add(classroom)

RETURN availableClassrooms
```

---

### 7. Bağlı Dersler (Child Lessons) Senkronizasyonu

Birbirine bağlı derslerin programları otomatik senkronize edilir.

#### combineLesson - Dersleri Bağlama

```
GİRDİ: lessonIds (bağlanacak ders ID'leri)
```

**Adımlar:**

1. **İlişki Kurulumu**
   ```
   parentLesson = lessons[0]
   
   FOR EACH lesson IN lessons[1..n]:
       lesson.parent_lesson_id = parentLesson.id
       lesson.update()
   ```

2. **Mevcut Programları Temizle**
   ```
   FOR EACH childLesson IN childLessons:
       # Ders bazlı programları sil
       deleteSchedules(owner_type: 'lesson', owner_id: childLesson.id)
       
       # Program bazlı programları sil
       deleteSchedules(owner_type: 'program', owner_id: childLesson.program_id, semester_no: childLesson.semester_no)
   ```

3. **Ana Ders Programını Kopyala**
   ```
   parentScheduleItems = findScheduleItems(parent_lesson_id)
   
   FOR EACH childLesson IN childLessons:
       FOR EACH parentItem IN parentScheduleItems:
           # Child lesson için schedule oluştur
           childSchedule = findOrCreateSchedule(owner_type: 'lesson', owner_id: childLesson.id)
           
           # Item'i kopyala
           newItem = cloneItem(parentItem)
           newItem.schedule_id = childSchedule.id
           newItem.create()
           
           # Child program için de kopyala
           childProgramSchedule = findOrCreateSchedule(owner_type: 'program', owner_id: childLesson.program_id)
           newProgramItem = cloneItem(parentItem)
           newProgramItem.schedule_id = childProgramSchedule.id
           newProgramItem.create()
   ```

#### saveScheduleItems İçinde Child Senkronizasyonu

Ana derste program değişikliği olduğunda:

```
IF lesson.childLessons.isNotEmpty():
    FOR EACH childLesson IN lesson.childLessons:
        # Child lesson için owner ekle
        owners.add({
            type: 'lesson',
            id: childLesson.id,
            is_child: true,
            child_lesson_id: childLesson.id
        })
        
        # Child program için owner ekle
        IF childLesson.program_id:
            owners.add({
                type: 'program',
                id: childLesson.program_id,
                semester_no: childLesson.semester_no,
                is_child: true,
                child_lesson_id: childLesson.id
            })
```

Bu sayede ana derste yapılan her değişiklik otomatik olarak tüm bağlı derslere yansıtılır.

---

## Validasyon Kuralları

### 1. Zaman Validasyonu
- Başlangıç saati < Bitiş saati
- Saat formatı: HH:MM
- Gün indeksi: 0-6 arası
- Hafta indeksi: 0 veya pozitif tam sayı

### 2. Öğretim Görevlisi Kuralları
- Aynı anda farklı derslere giremez
- Grup derslerinde bile aynı öğretim görevlisi olamaz
- Unavailable saatlerde ders verilemez

### 3. Derslik Kuralları
- UZEM (tip: 3) dersleri için derslik kontrolü yapılmaz
- Normal dersler için derslik türü eşleşmeli
- Karma dersler (tip: 4) hem derslik hem lab kullanabilir
- Aynı derslik aynı saatte farklı derslere atanamaz

### 4. Grup Dersleri Kuralları
- Sadece group_no > 0 olan dersler grup dersi olabilir
- Aynı grup numaralı dersler çakışamaz
- Farklı grup numaralı dersler aynı saatte olabilir
- Grup dersi üzerine normal ders eklenemez

### 5. Haftalık Saat Kontrolü
- Her dersin toplam yerleştirilen saati (`placed_size`) hesaplanır
- `remaining_size` = `target_size` - `placed_size`
- `remaining_size` < 0 ise hata fırlatılır

### 6. Bağlı Dersler Kuralları
- Child lesson'ların program değişiklikleri parent'tan kalıtılır
- Child lesson'ların kendi özel programları temizlenir
- Parent üzerindeki tüm değişiklikler child'lara otomatik yansır

### 7. Preferred/Unavailable Çakışma Kuralları
- Preferred/unavailable item kaydedilirken hedef schedule'da aynı zaman diliminde gerçek ders (single/group) varsa, preferred item kaydedilmez
- Tablo oluştururken (prepareScheduleRows) aynı zaman dilimine denk gelen preferred ve gerçek ders çakıştığında, gerçek ders tercih edilir ve preferred item yok sayılır
- Bu kurallar, view katmanında "Attempt to read property on array" hatalarının önüne geçer

---

## Akış Diyagramı

\`\`\`mermaid
flowchart TD
    START([Başla: saveScheduleItems])
    
    START --> TX_BEGIN[Transaction Başlat]
    TX_BEGIN --> LOOP_START{Her itemData için}
    
    LOOP_START -->|Bir sonraki item| EXTRACT[Veriyi Çıkar:<br/>day_index, start_time,<br/>end_time, status]
    
    EXTRACT --> IS_DUMMY{Dummy<br/>Item mi?}
    
    IS_DUMMY -->|Evet<br/>preferred/unavailable| DUMMY_OWNER[Hedef Schedule'dan<br/>owner bilgisi al]
    IS_DUMMY -->|Hayır<br/>single/group| GET_LESSON[Lesson modelini<br/>child lessons ile çek]
    
    GET_LESSON --> BUILD_OWNERS[Paydaşları Belirle:<br/>- user<br/>- classroom<br/>- program<br/>- lesson]
    
    BUILD_OWNERS --> HAS_CHILD{Child<br/>Lessons<br/>var mı?}
    
    HAS_CHILD -->|Evet| ADD_CHILD[Her child için<br/>lesson ve program<br/>paydaşı ekle]
    HAS_CHILD -->|Hayır| DUMMY_OWNER
    
    ADD_CHILD --> DUMMY_OWNER
    DUMMY_OWNER --> OWNER_LOOP{Her owner için}
    
    OWNER_LOOP -->|Bir sonraki owner| FIND_SCHEDULE[İlgili Schedule'ı<br/>bul veya oluştur]
    
    FIND_SCHEDULE --> FIND_ITEMS[Aynı gün ve<br/>hafta indeksindeki<br/>mevcut itemleri bul]
    
    FIND_ITEMS --> ITEM_LOOP{Her existingItem için}
    
    ITEM_LOOP -->|Bir sonraki item| CHECK_OVERLAP{checkOverlap<br/>Çakışıyor mu?}
    
    CHECK_OVERLAP -->|Hayır| ITEM_LOOP
    CHECK_OVERLAP -->|Evet| CHECK_STATUS{existingItem<br/>status nedir?}
    
    CHECK_STATUS -->|preferred| RESOLVE_PREF[resolvePreferredConflict:<br/>Item'i kısalt/böl/sil]
    CHECK_STATUS -->|single/unavailable/group| RESOLVE_CONFLICT[resolveConflict:<br/>Kuralları kontrol et]
    
    RESOLVE_CONFLICT --> HAS_ERROR{Hata<br/>var mı?}
    
    HAS_ERROR -->|Evet| THROW_ERROR[Exception fırlat:<br/>Çakışma kuralı ihlali]
    HAS_ERROR -->|Hayır| RESOLVE_PREF
    
    RESOLVE_PREF --> ITEM_LOOP
    
    ITEM_LOOP -->|Tüm itemler kontrol edildi| OWNER_LOOP
    
    OWNER_LOOP -->|Tüm ownerlar kontrol edildi| IS_GROUP{status ==<br/>group?}
    
    IS_GROUP -->|Evet| PROCESS_GROUP[processGroupItemSaving:<br/>- Timeline flatten<br/>- Merge data<br/>- Rebuild items]
    IS_GROUP -->|Hayır| CREATE_ITEM[Yeni ScheduleItem<br/>oluştur ve kaydet]
    
    PROCESS_GROUP --> ADD_ID[createdIds'e<br/>ID'leri ekle]
    CREATE_ITEM --> ADD_ID
    
    ADD_ID --> ADD_LESSON_ID[Etkilenen ders ID'sini<br/>affectedLessonIds'e ekle]
    
    ADD_LESSON_ID --> LOOP_START
    
    LOOP_START -->|Tüm itemlar işlendi| CHECK_LESSONS{Her affectedLesson için}
    
    CHECK_LESSONS -->|Bir sonraki ders| IS_COMPLETE[IsScheduleComplete<br/>çağır]
    
    IS_COMPLETE --> CHECK_SIZE{remaining_size<br/>< 0 mi?}
    
    CHECK_SIZE -->|Evet| THROW_HOUR_ERROR[Exception fırlat:<br/>Ders saati aşıldı]
    CHECK_SIZE -->|Hayır| CHECK_LESSONS
    
    CHECK_LESSONS -->|Tüm dersler kontrol edildi| TX_COMMIT[Transaction Commit]
    
    TX_COMMIT --> RETURN_IDS[createdIds döndür]
    RETURN_IDS --> END([Bitir])
    
    THROW_ERROR --> TX_ROLLBACK[Transaction Rollback]
    THROW_HOUR_ERROR --> TX_ROLLBACK
    TX_ROLLBACK --> END
    
    style START fill:#90EE90
    style END fill:#FFB6C1
    style THROW_ERROR fill:#FF6B6B
    style THROW_HOUR_ERROR fill:#FF6B6B
    style TX_ROLLBACK fill:#FF6B6B
    style TX_COMMIT fill:#90EE90
    style IS_DUMMY fill:#FFE4B5
    style HAS_CHILD fill:#FFE4B5
    style CHECK_OVERLAP fill:#FFE4B5
    style CHECK_STATUS fill:#FFE4B5
    style HAS_ERROR fill:#FFE4B5
    style IS_GROUP fill:#FFE4B5
    style CHECK_SIZE fill:#FFE4B5
    style RESOLVE_PREF fill:#87CEEB
    style PROCESS_GROUP fill:#87CEEB
\`\`\`

### Ek Akış Diyagramları

#### resolveConflict Detay Akışı

\`\`\`mermaid
flowchart TD
    START([resolveConflict])
    
    START --> SELF_CHECK{Kendi kendisiyle<br/>çakışma mı?}
    
    SELF_CHECK -->|Evet| RETURN_NULL[null döndür]
    SELF_CHECK -->|Hayır| CHECK_STATUS{existingItem<br/>status?}
    
    CHECK_STATUS -->|unavailable| ERR_UNAVAIL[Hata: Saat uygun değil]
    CHECK_STATUS -->|single| ERR_SINGLE[Hata: Saat dolu]
    CHECK_STATUS -->|group| CHECK_NEW_GROUP{newLesson<br/>group_no > 0?}
    CHECK_STATUS -->|preferred| RETURN_NULL
    
    CHECK_NEW_GROUP -->|Hayır| ERR_NOT_GROUP[Hata: Grup dersi<br/>üzerine normal ders]
    CHECK_NEW_GROUP -->|Evet| LOOP_GROUP{existingItem'daki<br/>her ders için}
    
    LOOP_GROUP -->|Bir sonraki ders| SAME_LESSON{Aynı ders mi?}
    
    SAME_LESSON -->|Evet| ERR_SAME[Hata: Aynı ders<br/>tekrar eklenemez]
    SAME_LESSON -->|Hayır| SAME_LECTURER{Aynı<br/>öğretim<br/>görevlisi mi?}
    
    SAME_LECTURER -->|Evet| ERR_LECTURER[Hata: Hoca aynı<br/>anda iki derse giremez]
    SAME_LECTURER -->|Hayır| SAME_GROUP_NO{Aynı grup<br/>numarası mı?}
    
    SAME_GROUP_NO -->|Evet| ERR_GROUP_NO[Hata: Aynı grup<br/>numaralı dersler<br/>çakışamaz]
    SAME_GROUP_NO -->|Hayır| LOOP_GROUP
    
    LOOP_GROUP -->|Tüm dersler OK| RETURN_NULL
    
    ERR_UNAVAIL --> END([Hata mesajı döndür])
    ERR_SINGLE --> END
    ERR_NOT_GROUP --> END
    ERR_SAME --> END
    ERR_LECTURER --> END
    ERR_GROUP_NO --> END
    RETURN_NULL --> END_OK([null döndür])
    
    style START fill:#90EE90
    style END fill:#FF6B6B
    style END_OK fill:#90EE90
    style RETURN_NULL fill:#90EE90
    style ERR_UNAVAIL fill:#FF6B6B
    style ERR_SINGLE fill:#FF6B6B
    style ERR_NOT_GROUP fill:#FF6B6B
    style ERR_SAME fill:#FF6B6B
    style ERR_LECTURER fill:#FF6B6B
    style ERR_GROUP_NO fill:#FF6B6B
\`\`\`

#### resolvePreferredConflict Detay Akışı

\`\`\`mermaid
flowchart TD
    START([resolvePreferredConflict])
    
    START --> NORMALIZE[Zamanları<br/>H:i formatına<br/>normalize et]
    
    NORMALIZE --> CHECK_CASE{Çakışma<br/>durumu?}
    
    CHECK_CASE -->|Tam Kapsama<br/>new tamamen<br/>pref'i kapsıyor| DELETE[preferredItem.delete]
    
    CHECK_CASE -->|Son Taraf<br/>new pref'in<br/>son tarafını kapsıyor| TRIM_END[preferredItem.end_time<br/>= newStart]
    
    CHECK_CASE -->|Baş Taraf<br/>new pref'in<br/>baş tarafını kapsıyor| TRIM_START[preferredItem.start_time<br/>= newEnd]
    
    CHECK_CASE -->|Ortada<br/>new pref'in<br/>ortasında| SPLIT[Sol: preferredItem.end_time = newStart<br/>Sağ: Yeni item oluştur]
    
    TRIM_END --> CHECK_ZERO_END{Süre<br/>sıfır mı?}
    TRIM_START --> CHECK_ZERO_START{Süre<br/>sıfır mı?}
    
    CHECK_ZERO_END -->|Evet| DELETE
    CHECK_ZERO_END -->|Hayır| UPDATE_END[preferredItem.update]
    
    CHECK_ZERO_START -->|Evet| DELETE
    CHECK_ZERO_START -->|Hayır| UPDATE_START[preferredItem.update]
    
    SPLIT --> UPDATE_LEFT[Sol parça<br/>preferredItem.update]
    UPDATE_LEFT --> CREATE_RIGHT[Sağ parça<br/>yeni item oluştur]
    
    DELETE --> END([Bitir])
    UPDATE_END --> END
    UPDATE_START --> END
    CREATE_RIGHT --> END
    
    style START fill:#90EE90
    style END fill:#90EE90
    style DELETE fill:#FFB6C1
    style SPLIT fill:#87CEEB
\`\`\`

#### processGroupItemSaving Detay Akışı

\`\`\`mermaid
flowchart TD
    START([processGroupItemSaving])
    
    START --> GET_ITEMS[İlgili günün tüm<br/>group itemlerini getir]
    
    GET_ITEMS --> FILTER[Çakışan itemleri<br/>filtrele]
    
    FILTER --> IS_EMPTY{Çakışan<br/>item var mı?}
    
    IS_EMPTY -->|Hayır| DIRECT_CREATE[Direkt yeni item<br/>oluştur ve döndür]
    IS_EMPTY -->|Evet| COLLECT_POINTS[Tüm start/end<br/>noktalarını topla<br/>ve sırala]
    
    COLLECT_POINTS --> INTERVAL_LOOP{Her ardışık<br/>nokta çifti için}
    
    INTERVAL_LOOP -->|pStart, pEnd| INIT_MERGE[mergedData = []<br/>mergedDetail = []]
    
    INIT_MERGE --> NEW_COVERS{Yeni veri<br/>bu aralığı<br/>kapsıyor mu?}
    
    NEW_COVERS -->|Evet| MERGE_NEW[newData ve<br/>newDetail'i merge et]
    NEW_COVERS -->|Hayır| EXISTING_LOOP
    
    MERGE_NEW --> EXISTING_LOOP{Her mevcut<br/>item için}
    
    EXISTING_LOOP -->|Bir sonraki item| ITEM_COVERS{Item bu<br/>aralığı<br/>kapsıyor mu?}
    
    ITEM_COVERS -->|Evet| MERGE_ITEM[item.data ve<br/>item.detail'i merge et]
    ITEM_COVERS -->|Hayır| EXISTING_LOOP
    
    MERGE_ITEM --> EXISTING_LOOP
    
    EXISTING_LOOP -->|Tüm itemler kontrol edildi| REMOVE_DUP[Duplicate lesson_id'leri<br/>temizle]
    
    REMOVE_DUP --> HAS_DATA{mergedData<br/>boş değil mi?}
    
    HAS_DATA -->|Hayır| INTERVAL_LOOP
    HAS_DATA -->|Evet| CAN_MERGE{Önceki item ile<br/>birleştirilebilir mi?<br/>Aynı data/detail +<br/>bitişik zaman}
    
    CAN_MERGE -->|Evet| EXTEND[Önceki item'in<br/>end zamanını uzat]
    CAN_MERGE -->|Hayır| ADD_PENDING[pendingItems'a<br/>yeni aralık ekle]
    
    EXTEND --> INTERVAL_LOOP
    ADD_PENDING --> INTERVAL_LOOP
    
    INTERVAL_LOOP -->|Tüm aralıklar işlendi| DELETE_OLD[Eski involved<br/>itemleri sil]
    
    DELETE_OLD --> CREATE_LOOP{Her pendingItem için}
    
    CREATE_LOOP -->|Bir sonraki pending| CREATE_NEW[Yeni ScheduleItem<br/>oluştur]
    
    CREATE_NEW --> ADD_ID[createdGroupIds'e<br/>ID ekle]
    
    ADD_ID --> CREATE_LOOP
    
    CREATE_LOOP -->|Tüm itemlar oluşturuldu| RETURN_IDS[createdGroupIds<br/>döndür]
    
    DIRECT_CREATE --> END([Bitir])
    RETURN_IDS --> END
    
    style START fill:#90EE90
    style END fill:#90EE90
    style DIRECT_CREATE fill:#87CEEB
    style COLLECT_POINTS fill:#FFE4B5
    style MERGE_NEW fill:#87CEEB
    style MERGE_ITEM fill:#87CEEB
    style EXTEND fill:#87CEEB
    style DELETE_OLD fill:#FFB6C1
\`\`\`

---

## Örnek Senaryo

### Senaryo: Grup Dersi Ekleme

**Başlangıç Durumu:**
- Pazartesi 09:00-11:00: Matematik I (Grup 1, Öğretim Görevlisi: Ahmet Yılmaz)

**İşlem:**
- Fizik I (Grup 2, Öğretim Görevlisi: Mehmet Kaya) dersini aynı saate ekle

**Algoritma Akışı:**

1. **saveScheduleItems** çağrılır
   - itemData: {day_index: 0, start_time: "09:00", end_time: "11:00", lesson: "Fizik I", lecturer: "Mehmet Kaya"}

2. **Paydaş Belirleme**
   - user: Mehmet Kaya
   - program: Bilgisayar Programcılığı (dönem: 1)
   - lesson: Fizik I
   - classroom: B101

3. **Çakışma Kontrolü**
   - Program schedule'da 09:00-11:00 aralığında Matematik I bulunur
   - checkOverlap = true
   - existingItem.status = 'group'
   
4. **resolveConflict**
   - Fizik I.group_no = 2 > 0 ✓
   - Matematik I.id != Fizik I.id ✓
   - Matematik I.lecturer (Ahmet Yılmaz) != Fizik I.lecturer (Mehmet Kaya) ✓
   - Matematik I.group_no (1) != Fizik I.group_no (2) ✓
   - Sonuç: null (çakışma yok)

5. **processGroupItemSaving**
   
   **Timeline Flattening:**
   - Points: [09:00, 11:00]
   
   **Interval Rebuild:**
   - [09:00-11:00]: 
     - Matematik I (Grup 1, Ahmet Yılmaz)
     - Fizik I (Grup 2, Mehmet Kaya)
   
   **Database Operations:**
   - Eski Matematik I item'i silinir
   - Yeni merged item oluşturulur:
     ```
     {
       day_index: 0,
       start_time: "09:00",
       end_time: "11:00",
       status: "group",
       data: [
         {lesson_id: 1, lecturer_id: 1, classroom_id: 5},  // Matematik I
         {lesson_id: 2, lecturer_id: 2, classroom_id: 5}   // Fizik I
       ]
     }
     ```

6. **Senkronizasyon**
   - Tüm paydaşlara (Mehmet Kaya, B101, Fizik I) aynı item kopyalanır

**Sonuç:**
Pazartesi 09:00-11:00 saatinde hem Matematik I hem Fizik I dersleri B101'de farklı gruplar olarak kayıtlı.

---

## Performans Optimizasyonları

### 1. Timeline Flattening Optimizasyonu
- Ardışık aynı veri içeren aralıklar birleştirilir
- Gereksiz veritabanı kayıtları önlenir

### 2. Transaction Yönetimi
- Tüm işlemler tek transaction içinde
- Hata durumunda otomatik rollback
- Veri tutarlılığı garanti altında

### 3. Eager Loading
- Child lessons ilişkileri önceden yüklenir
- N+1 sorgu problemi önlenir

### 4. Duplicate Kontrol
- lesson_id bazlı duplicate temizleme
- Aynı dersin birden fazla kez eklenmesini önler

---

## Hata Yönetimi

### Exception Türleri

1. **Çakışma Hataları**
   - Unavailable saatte ders ekleme
   - Single ders üzerine ders ekleme
   - Aynı öğretim görevlisi çakışması
   - Aynı grup numarası çakışması

2. **Validasyon Hataları**
   - Geçersiz zaman formatı
   - Başlangıç >= Bitiş
   - Geçersiz gün/hafta indeksi

3. **Kaynak Hataları**
   - Ders bulunamadı
   - Schedule bulunamadı
   - Derslik bulunamadı

4. **Saat Aşım Hataları**
   - remaining_size < 0
   - Haftalık ders saati aşıldı

### Rollback Stratejisi

Herhangi bir hata durumunda:
```
TRY:
    beginTransaction()
    ... işlemler ...
    commit()
CATCH Exception:
    rollback()
    throw Exception
```

Tüm veritabanı değişiklikleri geri alınır ve sistem tutarlı kalır.

---

## Özet

Ders programı oluşturma algoritması:

1. **Modüler Yapı**: Her işlem (çakışma kontrolü, grup işleme, preferred çözümleme) ayrı metodlarda
2. **Atomik İşlemler**: Transaction yönetimi ile veri tutarlılığı
3. **Kapsamlı Validasyon**: Tüm kurallar kontrol edilir
4. **Otomatik Senkronizasyon**: Bağlı dersler ve paydaşlar otomatik güncellenir
5. **Hata Toleransı**: Exception handling ve rollback mekanizması
6. **Performans**: Timeline flattening ve eager loading optimizasyonları
