[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **exportScheduleIcs**

---
# ImportExportManager::exportScheduleIcs(array $filters)

Ders programlarını harici takvim uygulamalarına (Google Takvim, Apple Takvim vb.) aktarılabilir `.ics` formatında üretir.

## Mantık (Algoritma)
1.  **ICS Başlığı**: VCALENDAR standartlarına uygun başlık bilgilerini (VERSION, CALSCALE, METHOD:PUBLISH) hazırlar.
2.  **Akademik Takvim Ayarları**: Ayarlar tablosundan derslerin başlangıç (`lesson_start_date`) ve bitiş (`lesson_end_date`) tarihlerini okur.
3.  **Ders Döngüsü**: Filtreye uyan her bir ders programı satırı için:
    - **Tarih Hesaplama**: Dersin haftalık günü (Pazartesi, Salı vb.) ve saati (`08:00 - 08:50`) ile akademik takvimin ilk ders gününü eşleştirir.
    - **Tekrarlama Kuralı (RRULE)**: Eğer akademik tarih aralığı tanımlıysa, dersin dönem bitene kadar HER HAFTA (`FREQ=WEEKLY`) tekrarlanması için `RRULE` oluşturur.
4.  **Event (Olay) Oluşturma**: Her ders için `BEGIN:VEVENT` bloğu açar; ders adı, hoca, derslik ve UID (benzersiz kimlik) bilgilerini ekler.
5.  **Escape (Kaçış)**: ICS formatında hata oluşmaması için metinlerdeki virgul (`,`) ve noktalı virgül (`;`) gibi karakterleri kaçırır.
6.  **Çıktı**: Oluşturulan metin yığınını `text/calendar` başlığıyla `.ics` dosyası olarak tarayıcıya gönderir.
