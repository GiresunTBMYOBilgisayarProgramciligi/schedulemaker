[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **checkCrash**

---
# ScheduleCard.checkCrash(selectedHours, classroom = null)

Dersin tabloya yerleştirilmeden önce frontend tarafında mevcut hücreler üzerinde çakışma olup olmadığını kontrol eder. Bu metot sadece tarayıcı tarafındaki (DOM) verileri kontrol eder, sunucuya istek atmaz.

## Mantık (Algoritma)
1.  **Girdi Analizi**: Eklenecek saat sayısını (`selectedHours`) ve (varsa) hedef dersliği alır.
2.  **Hücre Taraması**: Sürüklenen dersin bırakıldığı hücreden başlayarak, dersin süresi kadar alt satırları (saatleri) kontrol eder. Hücre, `dataset.dayIndex` attribute'u üzerinden bulunur (rowspan desteği için `cellIndex` yerine `dataset.dayIndex` kullanılır).
3.  **Hücre Kontrolü**:
    - Satırın sınır dışına çıkıp çıkmadığına bakar.
    - Hücrenin `drop-zone` olup olmadığını ve kısıtlı (`slot-unavailable`) olup olmadığını kontrol eder.
4.  **Ders Kontrolü (Çakışma)**:
    - **Sınav Programı**: Aynı saatte aynı derslikte veya aynı gözetmenle başka bir sınav olup olmadığına bakar. Aynı dersin farklı şubeleri aynı saatte farklı sınıflarda olabilir (bu duruma izin verilir).
    - **Ders Programı**: Hücrenin gruplu ders alanı olup olmadığını, eklenen dersin gruplu olup olmadığını ve aynı grup numarasının mükerrer olup olmadığını kontrol eder.
5.  **Sonuç**: Eğer herhangi bir kısıt ihlali varsa `reject`, sorun yoksa `resolve(true)` döner.
