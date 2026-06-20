# Sınav Programı Düzenleme İşlemleri Çalışma Mantığı Özeti

Sistemdeki sınav programı (Vize, Final, Büt) işlemleri, normal ders programından farklı bir veri yapısı ve ilişki mantığı üzerine kuruludur. Aşağıda bu yapının temel taşları özetlenmiştir.

## 1. Sınav Tipleri ve Kapsam
Sistemde üç ana sınav tipi tanımlıdır:
- `midterm-exam` (Vize)
- `final-exam` (Final)
- `makeup-exam` (Bütünleme)

Bu tiplerdeki programlar `ExamService` üzerinden yönetilir ve normal ders programı (`lesson`) mantığından ayrıştırılır. Ancak, sınav süresi hesaplamaları ve çakışma kontrollerinde `App\Helpers\TimeHelper` ve `App\Services\TimelineService` yardımcı sınıfları ortaklaşa kullanılır.

## 2. Çoklu Atama (Assignments) Yapısı
Normal derslerin aksine, bir sınav aynı anda **birden fazla derslikte** ve **birden fazla gözetmenle** gerçekleştirilebilir. 
- Bir sınav item'ı kaydedilirken `detail.assignments` dizisi içerisinde hangi gözetmenin hangi derslikte görevli olduğu tutulur.
- Her bir atama, hem Gözetmen hem de Derslik programlarına ayrı birer `ScheduleItem` olarak yansıtılır.

## 3. Veri Kayıt ve Referans Mantığı
Sınav item'ları kaydedilirken (`saveExamScheduleItems`) şu hiyerarşi izlenir:

### A. Program ve Ders Kayıtları
- İlgili program (Bölüm/Sınıf) ve dersin kendi programına kayıt atılır.
- Bu kayıtlarda `lecturer_id` ve `classroom_id` sütunları **null** bırakılır (Çünkü tek bir hoca veya derslik yoktur).
- Veri (`data`) kısmında sadece `lesson_id` saklanır.

### B. Gözetmen ve Derslik Kayıtları
- Her bir atama için gözetmen (user) ve derslik (classroom) programlarına kayıt atılır.
- Bu kayıtlarda tam veri (hoca + derslik + ders) saklanır.
- **Kritik Detay:** Bu kayıtların `detail` alanında `program_item_id` referansı tutulur. Bu referans, yukarıdaki (A) maddesinde oluşturulan ana program kaydını işaret eder. Böylece tüm parçalar birbirine bağlanır.

## 4. Çakışma Kontrolü (ConflictService)
Sınav çakışmaları şu prensiplere göre denetlenir:
- **Öğrenci Çakışması:** Aynı sınıfa/programa ait iki sınav aynı saatte olamaz.
- **Gözetmen Çakışması:** Bir gözetmen aynı saatte birden fazla sınavda görevlendirilemez.
- **Derslik Çakışması:** Bir derslik aynı saatte birden fazla sınava ev sahipliği yapamaz.
- **Ders Çakışması:** Bir dersin sınavı aynı anda iki farklı zaman dilimine konulamaz.

`ConflictService`, gelen veride `assignments` olup olmadığına bakarak sınav çakışma mantığını otomatik olarak devreye sokar.

## 5. Müsait Gözetmen Bulma
`AvailabilityService::availableObservers` metodu:
- Tüm hocaları ve yetkili personeli (Gözetmen Havuzu) tarar.
- Belirtilen gün ve saatte halihazırda başka bir sınavda (Vize/Final/Büt) görevli olup olmadıklarını kontrol eder.
- Sadece boşta olanları listeler.

## 6. Gruplu Derslerin Birleştirilmesi ve Child Dersler (YENİ)
Aynı programa (`program_id`) ve döneme (`semester_no`) ait, ders kodu (`code`) aynı olan ancak grup numaraları (`group_no`) farklı olan dersler, sınav programı düzenleme listesinde **tek bir kart** olarak birleştirilir.
- **Öğrenci Sayısı:** Tüm grupların toplam mevcudu otomatik olarak toplanır ve tek bir mevcudiyet olarak gösterilir.
- **Toplu İşlem:** Bu kart programa eklendiğinde, aynı koda sahip tüm gruplar için otomatik olarak sınav kaydı oluşturulur ve çakışma kontrolleri tüm gruplar için eş zamanlı yapılır.
- **İstisna:** `parent_lesson_id` üzerinden birbirine bağlı olan "birleştirilmiş (merged)" dersler bu otomatik gruplamanın dışındadır ve mevcut ilişkisel yapılarını korurlar.
- **Kayıt Mantığı:** Eskiden tüm programlara "ana dersin (`mainLesson`)" `lesson_id`'si kaydediliyordu. Yapılan değişiklikle beraber artık programlar `childLessons` dahil her derse ait olan programa kayıt işlemini kendi (`actual_lesson_id`) gerçek `lesson_id`'si ile yapar. Bu sayede program ara yüzünde her ders "Kendi Grubu" veya "Kendi Bölümü" ile net şekilde görülür.

## 7. Sibling (Kardeş) Item İlişkisi
Bir sınav item'ı silindiğinde veya taşındığında, ona bağlı olan tüm gözetmen ve derslik kayıtları da (`findExamSiblingItems` üzerinden) bulunur. `program_item_id` referans zinciri sayesinde, ana sınav silindiğinde tüm atamalar otomatik olarak temizlenir.

## 8. Kalan Mevcudu Yok Sayma (ignore_remaining)
Sınav programında bir dersin mevcudu ile seçilen derslik kapasitesi arasında küçük farklar olabilir (örn: 36 öğrenci, 35 kişilik derslik). Bu durumda:

- Sınav atama modalında derslik kapasitesi ders mevcudundan az olduğunda "Kalan mevcudu yok say" seçeneği görünür.
- Kullanıcı bu seçeneği işaretlediğinde, kaydedilen schedule item'ın `detail` alanına `ignore_remaining: true` bayrağı eklenir.
- `Lesson::IsScheduleComplete()` metodu, `remaining_size > 0` olduğunda item'ların detail alanında bu bayrağı arar. Bayrak bulunursa `remaining_size` sıfırlanır ve ders tamamlanmış kabul edilir.
- Bu sayede küçük kapasite farkları nedeniyle ders "tamamlanmamış" olarak listede kalmaz.

## 9. Sınav Birleştirme (exam_parent_lesson_id)
Öğrenci sayısı düşük olan derslerin aynı derslikte, aynı gözetmenle, aynı saat diliminde ortak sınava alınması için `exam_parent_lesson_id` mekanizması kullanılır.

### Farklar (parent_lesson_id vs exam_parent_lesson_id)
| Özellik | `parent_lesson_id` | `exam_parent_lesson_id` |
|---------|---------------------|--------------------------|
| **Amaç** | Ders programında ortak ders | Sınav programında ortak sınav |
| **Hoca kısıtı** | Aynı hoca olmalı | Farklı hocalar olabilir |
| **Ders kodu** | Genelde aynı veya benzer | Tamamen farklı olabilir |
| **Ders programı etkisi** | Child bağımsız düzenlenemez | Ders programı etkilenmez |
| **Sınav programı etkisi** | **Etkisiz** (sınav programında dikkate alınmaz) | Child otomatik exam_parent ile aynı slota |

### Temel Prensipler
1. **`parent_lesson_id` sınav programında etkisizdir** — Sınav programında sadece `exam_parent_lesson_id` dikkate alınır. `parent_lesson_id` (ders birleştirme) ile bağlı child dersler sınav programında bağımsız olarak listelenir.
2. **Otomatik bağlantı:** `parent_lesson_id` atandığında (ders birleştirme) `exam_parent_lesson_id` de otomatik atanır
3. **Bağımsız koparma:** Sınav birleştirmesi kaldırıldığında ders birleştirmesi etkilenmez
4. **Hoca kısıtı yok** — Farklı hocaların dersleri sınav için birleştirilebilir
5. **Sınav süresi parent'tan** — Modal'da belirlenen süre tüm exam child'lar için geçerli

### Birleştirme Yapılabilecek Yerler
- **Ders detay sayfası:** "Sınav Birleştir" butonu → TomSelect ile ders arama
- **Sınav programı düzenleme:** Available lessons listesinde sağ tık → "Sınav Birleştir"

### Teknik Detaylar
- `Lesson` modeline `exam_parent_lesson_id` property'si, `examParentLesson` ve `examChildLessons` relation'ları eklendi
- `Lesson::getScheduleCSSClass(isExam)` — Sınav programında `exam_parent_lesson_id` ile child tespiti
- `LessonService::combineExamLesson()` — Sınav birleştirme + parent'ın sınav programını child'a kopyalar (`syncExamChildFromParent`)
- `LessonService::deleteExamParentLesson()` — Sınav birleştirmeyi kaldırma
- `ExamService::saveExamScheduleItems()` — Sadece `exam_parent_lesson_id` üzerinden owner tespiti (`parent_lesson_id` dikkate alınmaz)
- `AvailabilityService::availableLessons()` — Exam child'lar listeden gizlenir, parent mevcuduna eklenir
- `ScheduleViewHelper` — `isDraggable`, `buildAvailableLessonAttributes`, `buildLessonCardAttributes` metotlarında sınav/ders ayrımı
- `_childLessons.php` — Sınav programında `examChildLessons`, ders programında `childLessons` gösterilir
- `_lessonCard.php` + `_availableLessonCard.php` — Popover'da sınav birleştirmesi bilgisi
- `ExamScheduleCard::checkCrash()` — Base lesson mismatch kontrolü yumuşatıldı
- `LessonService::combineLesson()` — `parent_lesson_id` atanırken `exam_parent_lesson_id` de otomatik atanır
