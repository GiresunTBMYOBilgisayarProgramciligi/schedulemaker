[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Services](../README.md) / [AvailabilityService](README.md) / **getProgramAvailability**

---
# AvailabilityService::getProgramAvailability(array $filters)

Program bazlı çakışmaları kontrol eder ve müsait olmayan hücreleri döner.

## İşleyiş
1.  Verilen `lesson_id` üzerinden ders ve bağlı olduğu program bilgileri alınır.
2.  Dersin varsa çocuk dersleri (`childLessons`) de dahil edilerek ilgili tüm programlar (`Schedule`) çekilir.
3.  Zaman dilimleri (`slots`) üzerinden döngü kurularak çakışmalar kontrol edilir.
4.  **Grup Kontrolü:** Eğer yerleştirilmeye çalışılan ders gruplu bir ders ise (`group_no > 0`), aynı slottaki diğer gruplu derslerle çakışmasına izin verilir. Sadece aynı grup numarasına sahip dersler çakışma olarak kabul edilir.

## Parametreler
- `array $filters`: 
    - `lesson_id`: Kontrol edilecek dersin ID'si.
    - `type`: Program türü (lesson, midterm-exam vb.).
    - `semester`: Dönem.
    - `academic_year`: Akademik yıl.
    - `week_index`: Hafta indeksi.

## Grup Kontrolü Detayı
Hoca programı düzenlenirken, aynı saatte farklı bölümlerde aynı dersin farklı grupları (A grubu, B grubu vb.) olabilir. Eski yapıda bu durum "PROGRAM ÇAKIŞMASI" uyarısına neden oluyordu. Yeni yapıda:
- Sürüklenen dersin `group_no` değeri 0'dan büyükse.
- Çakışan slotta bir `ScheduleItem` varsa ve durumu `group` ise.
- Item içindeki derslerin hiçbirinin `group_no` değeri sürüklenen dersle aynı değilse.
Bu durum çakışma olarak sayılmaz ve hücre müsait kabul edilir.

## Dönüş Değeri
*   `array`: `unavailableCells` anahtarı altında müsait olmayan hücrelerin koordinatlarını içeren dizi.
