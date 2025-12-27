# API ve Router Yapısı

Schedule Maker, frontend ile haberleşmek için AJAX tabanlı bir API kullanır. İstekler `AjaxRouter.php` tarafından karşılanır ve doğrulanarak ilgili metodlara yönlendirilir.

## Genel İstek Yapısı

*   **URL**: `/ajax/{ActionName}`
*   **Method**: `POST`
*   **Header**: `X-Requested-With: XMLHttpRequest` (Güvenlik kontrolü için zorunludur).
*   **Response**: JSON formatında döner.
    *   Success: `{"status": "success", "msg": "...", "data": ...}`
    *   Error: `{"status": "error", "msg": "..."}`

## Önemli Uç Noktalar (Endpoints)

### 1. Ders Programı Kaydetme
*   **Action**: `saveScheduleItem`
*   **Parametreler**:
    *   `items`: JSON string. Eklenecek/Güncellenecek öğelerin listesi.
*   **İşlev**: `ScheduleController::saveScheduleItems` metodunu çağırır. Çakışma kontrollerini yapar ve veritabanına yazar.
*   **Dönüş**: `createdIds` (Yeni oluşturulan item ID'leri).

### 2. Ders Programı Silme
*   **Action**: `deleteScheduleItems`
*   **Parametreler**:
    *   `items`: JSON string. Silinecek öğelerin listesi.
*   **İşlev**: `ScheduleController::deleteScheduleItems` metodunu çağırır.
*   **Dönüş**: `deletedIds` (Silinen ID'ler) ve `createdIds` (Parçalanma sonucu oluşan yeni ID'ler).

### 3. Çakışma Kontrolü
*   **Action**: `checkScheduleCrash`
*   **Parametreler**:
    *   `items`: Kontrol edilecek öğeler.
*   **İşlev**: Sadece çakışma olup olmadığını kontrol eder, kayıt yapmaz.

### 4. Uygunluk Kontrolü (Hoca/Sınıf)
*   **Action**: `checkLecturerSchedule` / `checkClassroomSchedule`
*   **İşlev**: Belirtilen hoca veya sınıfın programını tarar ve dolu (`unavailable`) veya tercihli (`preferred`) saatleri matris olarak döner. Frontend bu bilgiyi kullanarak tabloyu kırmızı/yeşil boyar.

---

## Yetkilendirme

Her action, `isAuthorized()` fonksiyonu ile yetki kontrolü yapar.
*   `submanager`: Yönetici yetkisi gerektiren işlemler (Kullanıcı ekleme vb.).
*   `lecturer`: Hoca yetkisi gerektiren işlemler.
