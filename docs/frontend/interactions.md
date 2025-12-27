# Frontend Etkileşimleri ve Mantığı

Frontend işlemleri büyük ölçüde `ScheduleCard.js` dosyası üzerindeki `ScheduleCard` sınıfı tarafından yönetilir. Bu sınıf; sürükle-bırak işlemleri, çakışma görselleştirmesi ve sunucu ile senkronizasyonu sağlar.

## Temel Sınıf: `ScheduleCard`

### Özellikler (Properties)
*   `draggedLesson`: O an sürüklenen dersin verisini tutar (`lesson_id`, `group_no`, `duration` vb.).
*   `table`: Ders programı tablosu DOM elementi.
*   `list`: Uygun derslerin listelendiği DOM elementi.
*   `type`: Program türü (`day`, `exam` vb.).

### Başlatma (Initialization)
Sayfa yüklendiğinde `initialize()` metodu çalışır:
1.  `initStickyHeaders()`: Tablo başlığı ve ders listesinin scroll ile takip etmesini sağlar.
2.  `initBulkSelection()`: Çoklu seçim özelliklerini aktif eder.
3.  Event Listener'lar: Drag & Drop ve Click olaylarını dinlemeye başlar.

---

## Sürükle - Bırak Mantığı (Drag & Drop Flow)

### 1. Drag Start (`dragStartHandler`)
Kullanıcı bir derse basılı tutup sürüklemeye başladığında:
*   Dersin bilgileri `draggedLesson` objesine aktarılır.
*   `highlightUnavailableCells()` asenkron olarak çağrılır.
    *   **Backend Çağrısı**: `checkLecturerSchedule` ve `checkClassroomSchedule`.
    *   **Görselleştirme**: Gelen yanıta göre hocanın veya sınıfın dolu olduğu saatler (`unavailable`) kırmızı, tercih ettiği saatler (`preferred`) yeşil çerçeve ile işaretlenir.

### 2. Drag Over (`dragOverHandler`)
Sürükleme sırasında farenin üzerinde olduğu hücrenin uygunluğu görsel olarak belirtilir.

### 3. Drop (`dropHandler`)
Kullanıcı dersi bir hücreye bıraktığında:
*   **Hedef Kontrolü**: Bırakılan yer geçerli bir hücre mi? (`drop-zone`).
*   **Çakışma Kontrolü (Frontend)**: `checkCrash()` metodu çalışır.
    *   **Sınav**: Aynı hoca/sınıf/ders çakışması var mı?
    *   **Ders**: Grup kurallarına uyuyor mu? (Farklı grup no vs).
*   **Modal**: Eğer yeni bir ders ekleniyorsa, kaç saat süreceği ve hangi sınıfta olacağını soran Modal açılır.
*   **Kayıt**: Kullanıcı onaylarsa `saveScheduleItem` ile sunucuya gönderilir.

---

## Veri Senkronizasyonu

### Ekleme Sonrası
`saveScheduleItem` başarılı olursa, dönen `createdIds` kullanılarak:
1.  Yeni ders kartı (`lesson-card`) oluşturulur (veya kopyalanır).
2.  İlgili hücreye eklenir (`moveLessonListToTable`).

### Silme ve Taşıma Sonrası
Ders tablodan listeye sürüklenirse (Silme):
*   `deleteScheduleItems` başarılı olursa, dönen `createdIds` ile tablo güncellenir.
*   Backend, parçalanan dersler için yeni ID'ler üretmiş olabilir. `syncTableItems` metodu, tablodaki canlı hücrelerin ID'lerini bu yeni ID'ler ile günceller. Bu sayede sayfa yenilenmeden işlem devam edebilir.

---

## Fonksiyon Detayları

Frontend mantığını yöneten `ScheduleCard` sınıfındaki kritik metodların işleyişi aşağıdadır.

### 1. `highlightUnavailableCells()`
Ders sürüklenmeye başlandığında (`dragstart`), hangi hücrelerin uygun olduğunu/olmadığını belirler.
1.  **Backend Sorgusu**: `AjaxRouter` üzerinden `checkLecturerSchedule` ve `checkClassroomSchedule` metodlarına istek atar.
2.  **Yanıt Analizi**: Gelen veriler, hücrelerin (satır/sütun matrisi) uygunluk durumunu içerir.
3.  **Görsel İşaretleme**:
    *   **Dolu/Uygun Değil**: Hücreye `.slot-unavailable` sınıfı eklenir (Kırmızı).
    *   **Tercih Edilen**: Hücreye `.slot-preferred` sınıfı eklenir (Ders bırakılabilir ancak uyarı/bilgi verir).

### 2. `checkCrash(selectedHours, classroom)`
Bırakma (Drop) anında frontend tarafında kural ihlali olup olmadığını kontrol eder.
1.  **Sınav Kontrolü**:
    *   Aynı saat diliminde farklı bir dersin sınavı var mı?
    *   Aynı sırada aynı derslik veya aynı gözetmen kullanılmış mı?
2.  **Ders Kontrolü**:
    *   Hedef hücre `group` mu? (Eğer sürüklenen ders grupluysa).
    *   Aynı ders ID'si veya aynı Grup No çakışıyor mu?
3.  **Sonuç**: Eğer kural ihlali tespit edilirse `reject` (Hata Mesajı) döner, işlem durur.

### 3. `dropHandler(element, event)`
Sürükleme işleminin bittiği ve aksiyonun tetiklendiği ana metoddur.
1.  **Düzey Kontrolü**: Düşülen yerin `list` (silme) mi yoksa `table` (kaydetme) mi olduğunu belirler.
2.  **Bulk (Toplu) İşlem**: Birden fazla ders seçiliyse (`dragData.type === 'bulk'`), döngüye girerek her birini sırayla işler.
3.  **Branching**:
    *   `dropListToTable()`: Listeden tabloya (Yeni Kayıt).
    *   `dropTableToList()`: Tablodan listeye (Silme).
    *   `dropTableToTable()`: Tablodan tabloya (Taşıma).

### 4. `syncTableItems(createdItems)`
Backend'de oluşan değişiklikleri (özellikle bölünme/split sonrası) frontend'e yansıtır.
1.  **ID Güncelleme**: Backend'den gelen yeni `schedule_id`'lerini tablodaki ilgili hücrelerin `dataset`'lerine işler.
2.  **Görsel Onarım (Re-creation)**:
    *   Eğer bir hücrede ders olması gerekiyor (ID var) ama görsel kart (`lesson-card`) yoksa:
    *   Aynı dersin başka bir kartını şablon olarak bulur.
    *   Kopyasını oluşturur (Clone) ve boş hücreye yerleştirir.
3.  **Senkronizasyon**: Bu işlem sayesinde sayfa yenilenmeden veritabanı ile görsel arayüz tam uyumlu kalır.
