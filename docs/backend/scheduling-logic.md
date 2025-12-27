# Backend Ders Programı Mantığı

Bu belge, `ScheduleController.php` içinde yer alan ve projenin kalbini oluşturan programlama mantığını (Scheduling Logic) detaylandırır.

## Temel Kavramlar

### Status (Durum) Türleri
`schedule_items` tablosundaki `status` alanı, bir zaman bloğunun davranışını belirler.

1.  **`single`**: Standart, tekil bir ders atamasıdır.
    *   **Kural**: Aynı saatte, aynı kaynak (Hoca/Sınıf) için başka bir `single` kayıt olamaz. Sert çakışma hatası fırlatır.
2.  **`group`**: Birleştirilebilir ders atamasıdır.
    *   **Kullanım**: Farklı programların (örn: Bilgisayar Müh. ve Yazılım Müh.) aynı dersi (örn: Fizik-1) aynı hoca ve sınıfta ortak alması durumunda kullanılır.
    *   **Merge Mantığı**:
        *   Farklı **Ders** ID + Farklı **Grup** No -> **BİRLEŞİR**.
        *   Aynı **Ders** ID + Aynı **Grup** No -> **HATA** (Duplicate).
        *   Aynı **Ders** ID + Farklı **Grup** No -> **HATA** (Hoca bölünemez).
3.  **`preferred`** (Tercih):
    *   Hoca, Derslik veya Programın "Ben bu saatte müsaitim / ders istiyorum" beyanıdır.
    *   **Soft Conflict**: Üzerine gerçek bir ders (`single` veya `group`) eklendiğinde **SİLİNİR** veya **BÖLÜNÜR**. Asla hata fırlatmaz.
    *   Yeni eklenen dersin `detail` alanına `preferred => true` bilgisi eklenir.
4.  **`unavailable`** (Kapalı):
    *   Kaynağın o saatte kesinlikle müsait olmadığını belirtir.
    *   **Hard Conflict**: Üzerine ders eklenemez, hata fırlatır.

---

## Conflict Resolution (Çakışma Çözümleme)

`saveScheduleItems` metodu çalışırken, eklenen her blok için `checkOverlap` ve `resolveConflict` metodları çağrılır.

### Algoritma
1.  **Kardeş Kontrolü**: Eklenen dersin Hoca, Sınıf ve Program programları taranır.
2.  **Çakışma Tespiti**: Eğer çakışan bir `schedule_item` varsa:
    *   Durumu `unavailable` ise -> **Exception**.
    *   Durumu `single` ise -> **Exception**.
    *   Durumu `preferred` ise -> **resolvePreferredConflict()** devreye girer.

### `resolvePreferredConflict`
Bu metod, `preferred` bir bloğun üzerine ders geldiğinde onu akıllıca yönetir:
*   **Tam Örtüşme**: Preferred blok tamamen silinir.
*   **Kısmi Örtüşme**: Preferred blok, çakışmayan kısımları kalacak şekilde (start/end güncellenerek) küçültülür veya ikiye bölünür.

---

## Grup Mantığı ve Birleştirme (`processGroupItemSaving`)

Grup dersleri eklenirken sistem sadece basit bir insert yapmaz. O günün zaman çizelgesini analiz eder.

**Senaryo**: 10:00-12:00 arası bir grup dersi ekleniyor. Ancak 11:00-13:00 arasında başka bir grup dersi zaten var.
**Çözüm**: "Flatten Timeline" (Düzleştirilmiş Zaman Çizelgesi).

1.  Tüm başlangıç ve bitiş noktaları toplanır: `10:00, 11:00, 12:00, 13:00`.
2.  Zaman dilimleri oluşturulur:
    *   `10:00 - 11:00`: Sadece yeni ders.
    *   `11:00 - 12:00`: Yeni ders + Eski ders (**MERGE** - `data` json'ları birleşir).
    *   `12:00 - 13:00`: Sadece eski ders.
3.  Veritabanında bu 3 parça ayrı ayrı `schedule_item` olarak saklanır ancak hepsi `group` statüsündedir.

---

## Fonksiyon Detayları

Backend mantığını oluşturan kritik fonksiyonların adım adım işleyişi aşağıdadır.

### 1. `saveScheduleItems(array $itemsData)`
Yeni ders programı öğelerini kaydetmek için ana giriş noktasıdır.
1.  **Transaction Başlatma**: Veri bütünlüğü için tüm işlemler bir veritabanı işlemi (`transaction`) içinde yapılır.
2.  **Payload Analizi**: Gelen JSON verisindeki her bir `item` için:
    *   Hoca, Derslik ve Program'ın o dönemdeki `Schedule` kayıtlarını bulur veya yoksa oluşturur.
    *   Tüm paydaş takvimlerinde zaman çakışması (`checkOverlap`) taraması yapar.
3.  **Conflict Resolution**:
    *   `preferred` çakışması varsa `resolvePreferredConflict` çağrılır.
    *   Diğer çakışmalarda `resolveConflict` ile kural kontrolü yapılır.
4.  **Kayıt**:
    *   Statü `group` ise `processGroupItemSaving` ile merge/split yapılır.
    *   Değilse direkt insert edilir.
5.  **Commit**: Başarılı ise işlemi onaylar.

### 2. `resolveConflict(newItemData, existingItem, newLesson)`
Sert çakışmaları (Error) yönetir.
*   `unavailable` veya `single` statüsündeki bir kaydın üzerine ders eklenmeye çalışılırsa **Exception** fırlatır.
*   `group` çakışmasında:
    *   Yeni eklenen ders de "grup dersi" olmalıdır.
    *   Mevcut grupta aynı Ders ID'si olmamalıdır.
    *   Aynı grup numarası ile çakışamaz.

### 3. `processGroupItemSaving(...)`
Grup derslerini akıllıca birleştirir ("Flatten Timeline").
1.  **Nokta Belirleme**: Eklenecek dersin ve o dündeki mevcut tüm grup derslerinin başlangıç/bitiş saatlerini bir diziye toplar.
2.  **Sıralama**: Tüm zaman noktalarını kronolojik olarak sıralar.
3.  **Dilimleme**: Her iki nokta arası (segment) için:
    *   Hangi derslerin bu dilimi kapsadığını bulur.
    *   Dersleri (`data`) ve detayları (`detail`) birleştirir (Array Merge).
    *   Aynı Ders ID'lerini temizleyerek (Unique) veri şişmesini önler.
4.  **Yeniden Oluşturma**: Eski `group` öğelerini siler ve yeni segmentleri oluşturur.

### 4. `deleteScheduleItems(array $items)`
Silme işlemini tüm paydaş takvimlerinde senkronize eder.
1.  **Sibling Tespiti**: Silinecek öğenin tüm kopyalarını (Hoca, Sınıf, Diğer Programlar) bulur.
2.  **Aralık Birleştirme**: Aynı ID'ye sahip silme isteklerini zaman bazlı gruplandırır.
3.  **Process Deletion**: Her bir kardeş öğe için `processItemDeletion` çağrılır.

### 5. `processItemDeletion(item, intervals, ...)`
Bir bloğun içinden belirli bir zamanı veya dersi "cerrahi" titizlikle çıkarır.
1.  **Dilimleme**: Bloğu teneffüs ve ders süresi sınırlarına göre parçalara böler.
2.  **Filteleme**: Silinmek istenen zaman aralığına denk gelen parçaları işaretler.
    *   Eğer ders bazlı silme yapılıyorsa sadece ilgili Ders ID'sini `data` içinden çıkarır.
3.  **Teneffüs Yönetimi**: Eğer bir parçanın öncesinde ve sonrasında ders kalmadıysa, o aradaki teneffüsü de otomatik siler.
4.  **Birleştirme**: Yan yana kalan ve verisi aynı olan segmentleri tek blokta birleştirir.
5.  **Güncelleme**: Eski öğeyi silip yeni parçaları oluşturur.
