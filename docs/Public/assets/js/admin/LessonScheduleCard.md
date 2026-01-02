[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / **LessonScheduleCard**

---

# LessonScheduleCard

`LessonScheduleCard`, [ScheduleCard](./ScheduleCard/README.md) sınıfından türetilmiştir ve standart ders programı (dönem içi dersler) işlemlerini yönetir.

## ScheduleCard'dan Farkları

Bu sınıf, temel sınıfın sunduğu iskeleti kullanarak normal dersler için özelleşmiş şu mantıkları uygular:

- **openAssignmentModal**: Sadece tek bir derslik seçimine izin veren, ders saati süresini (blok ders) ayarlamaya olanak tanıyan basit bir modal açar.
- **checkCrash**: Ders bazlı çakışma kontrollerini yapar. Gruplu derslerin aynı hücreye girmesine izin verir ancak farklı derslerin çakışmasını engeller.
- **moveLessonListToTable**: Ders tabloya eklendiğinde "Kalan Saat" bilgisini günceller. Eğer dersin tüm saatleri yerleştirildiyse kartı listeden kaldırır.

## Önemli Metodlar

### [openAssignmentModal]
Ders ataması sırasında açılan penceredir. Seçilen ders saati kadar boş yer olup olmadığını frontend tarafında kontrol eder.

### [checkCrash]
Derslerin üst üste binme (crash) durumunu kontrol eder. 
- Eğer hücre boşsa geçişe izin verir.
- Eğer hücrede ders varsa, bu dersin bir grup dersi olup olmadığını ve eklenen dersle çakışıp çakışmadığını kontrol eder.

### [moveLessonListToTable]
UI tarafında ders kartlarını tabloya yerleştirir ve sol menüdeki ders listesiyle senkronizasyon sağlar.
