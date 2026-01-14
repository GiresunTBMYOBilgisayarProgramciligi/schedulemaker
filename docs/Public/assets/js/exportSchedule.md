[🏠 Ana Sayfa](../../../../README.md) / [Public](../../../README.md) / [assets](../../README.md) / [js](../README.md) / **exportSchedule.js**

---
# Public\assets\js\exportSchedule.js

Ders programlarını Excel (.xlsx) ve iCalendar (.ics) formatlarında dışa aktarmak için kullanılan JavaScript modülüdür.

## Genel Bakış

Bu dosya, kullanıcı etkileşimlerini (buton tıklamaları) dinler ve ilgili dışa aktarma işlemlerini başlatır. `fetch` API kullanarak sunucuya asenkron istekler gönderir ve dönen dosya verisini (blob) tarayıcı üzerinden indirtir. İşlem sırasında `Spinner` sınıfını kullanarak kullanıcıya yükleniyor durumu gösterir.

## Bağımlılıklar

*   **myHTMLElements.js**: Modal ve Spinner bileşenleri için gereklidir.
*   **Toast**: Hata mesajlarını göstermek için notifikasyon sistemi.

## Kullanım

Bu modül `DOMContentLoaded` eventi ile otomatik olarak başlatılır ve sayfadaki belirli ID desenlerine sahip butonları dinlemeye başlar.

### Buton ID Yapıları

*   **Export Butonları**: ID'si `Export` ile biten butonlar Excel çıktısı alır.
    *   `singlePageExport`: Tek bir sayfa/kart için (örn: Profil sayfası). `data-owner-type` ve `data-owner-id` attribute'larına ihtiyaç duyar.
    *   `lecturerExport`: Hoca seçimi tabanlı.
    *   `classroomExport`: Sınıf seçimi tabanlı.
    *   `departmentAndProgramExport`: Bölüm veya Program seçimi tabanlı.

*   **Calendar Butonları**: ID'si `Calendar` ile biten butonlar ICS dosyası indirir.
    *   `singlePageCalendar`: Tek bir sayfa/kart için. `data-owner-type` ve `data-owner-id` gerektirir.
    *   Diğerleri export butonları ile benzer mantıkla çalışır.

### Veri Nitelikleri (Data Attributes)

Özellikle `singlePageExport` butonlarında aşağıdaki veri nitelikleri HTML elementinde bulunmalıdır:

```html
<button id="singlePageExport" 
        data-owner-type="user" 
        data-owner-id="15">
    Excel'e Aktar
</button>
```

*   `data-owner-type`: Programın sahibinin türü (`user`, `classroom`, `program`, `department`, `lesson`).
*   `data-owner-id`: İlgili sahibin veritabanı ID'si.

### Modal Seçenekleri

Excel dışa aktarma işleminde kullanıcıya seçenekler sunan bir modal açılır:
*   **Ders Kodu**: Her zaman gösterilir.
*   **Hoca Adı**: Program, Sınıf ve Bölüm programlarında gösterilir.
*   **Program/Bölüm Adı**: Hoca ve Sınıf programlarında gösterilir.

## Fonksiyonlar

*   `showExportOptionsModal(ownerType, onConfirm)`: Kullanıcıya dışa aktarma seçeneklerini soran modalı açar. Seçilen seçenekleri `onConfirm` callback'ine iletir.
*   `fetchExportSchedule(data)`: `/ajax/exportSchedule` endpoint'ine POST isteği atar ve dönen Excel dosyasını indirir.
*   `fetchExportIcs(data)`: `/ajax/exportScheduleIcs` endpoint'ine POST isteği atar ve dönen ICS dosyasını indirir.
