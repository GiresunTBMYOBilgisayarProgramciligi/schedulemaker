[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / **LessonScheduleCard**

---

# LessonScheduleCard

`LessonScheduleCard`, [ScheduleCard](./ScheduleCard/README.md) sınıfından türetilmiştir ve standart ders programı (dönem içi dersler) işlemlerini yönetir.

## ScheduleCard'dan Farkları

Bu sınıf, temel sınıfın sunduğu iskeleti kullanarak normal dersler için özelleşmiş şu mantıkları uygular:

- **openAssignmentModal**: Sadece tek bir derslik seçimine izin veren, ders saati süresini (blok ders) ayarlamaya olanak tanıyan basit bir modal açar.
- **checkCrash**: Ders bazlı çakışma kontrollerini yapar. Gruplu derslerin aynı hücreye girmesine izin verir ancak farklı derslerin veya aynı hocanın farklı derslerinin çakışmasını engeller.


## Önemli Metodlar

### [openAssignmentModal]
Ders ataması sırasında açılan penceredir. Seçilen ders saati kadar boş yer olup olmadığını frontend tarafında kontrol eder.

### [checkCrash]
Derslerin üst üste binme (crash) durumunu kontrol eder. 
- Eğer hücre boşsa geçişe izin verir.
- Eğer hücrede ders varsa, bu dersin bir grup dersi olup olmadığını, eklenen dersle çakışıp çakışmadığını ve **aynı hocaya ait olup olmadığını** kontrol eder. Hoca çakışması durumunda işlem engellenir.

