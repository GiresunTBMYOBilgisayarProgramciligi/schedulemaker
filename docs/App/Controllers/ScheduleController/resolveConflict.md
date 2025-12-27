[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **resolveConflict**

---
# ScheduleController::resolveConflict(array $newItemData, ScheduleItem $existingItem, Lesson $newLesson)

İki öğe arasında fiziksel bir zaman çakışması tespit edildiğinde, bu durumun bir hata (Error) olup olmadığını veya birleştirilebileceğini belirleyen kural motorudur.

## Kurallar ve Durumlar

### 1. `unavailable` ve `single` Statüleri
*   Eğer mevcut öğe `unavailable` (kapalı) veya `single` (tekil ders) statüsündeyse:
    *   **Sonuç**: İşlem durdurulur ve kullanıcıya hata mesajı (Exception) fırlatılır.

### 2. `group` Statüsü
*   Eğer mevcut öğe bir grup dersiyse (`group`):
    *   **Kural A**: Yeni eklenen ders de bir grup dersi olmalıdır (`group_no > 0`). Değilse hata fırlatılır.
    *   **Kural B**: Aynı hücrede aynı Ders ID'sine sahip iki ders bulunamaz.
    *   **Kural C**: Aynı grup numarasına (`group_no`) sahip farklı dersler çakışamaz.
*   **Sonuç**: Eğer tüm kurallar sağlanırsa, çakışma bir hata olarak kabul edilmez ve birleştirme işlemine izin verilir.

### 3. `preferred` Statüsü
*   Eğer mevcut öğe sadece "tercih edilen" bir alansa:
    *   **Sonuç**: Hiçbir kısıtlama uygulanmaz, çakışma yoksayılır (Çünkü kayıt aşamasında bu alan zaten otomatik daraltılır).

## Teknik Not
Bu metod hiçbir değer dönmez (`void`). Sadece kural ihlali durumunda `Exception` fırlatarak işlemi (ve transaction'ı) durdurur.
