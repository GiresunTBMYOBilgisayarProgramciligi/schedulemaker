# Sınav Programı Düzenleme İşlemleri Çalışma Mantığı Özeti

Sistemdeki sınav programı (Vize, Final, Büt) işlemleri, normal ders programından farklı bir veri yapısı ve ilişki mantığı üzerine kuruludur. Aşağıda bu yapının temel taşları özetlenmiştir.

## 1. Sınav Tipleri ve Kapsam
Sistemde üç ana sınav tipi tanımlıdır:
- `midterm-exam` (Vize)
- `final-exam` (Final)
- `makeup-exam` (Bütünleme)

Bu tiplerdeki programlar `ExamService` üzerinden yönetilir ve normal ders programı (`lesson`) mantığından ayrıştırılır.

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
`ExamService::availableObservers` metodu:
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
