# ScheduleController::checkItemConflict(array $itemData)

Tek bir program öğesinin tüm paydaşlar için çakışma kontrolünü yapan "bekçi" fonksiyondur. Hafta bazlı çakışmaları (`week_index`) destekler.

## İşleyiş
1.  Gelen `itemData` içindeki `lesson_id`, `lecturer_id` ve `classroom_id` üzerinden ilgili modelleri çeker.
2.  Dersin bağlı olduğu **Program**'ı tespit eder.
3. Şu takvimleri (`Schedule`) tarar:
    *   Hocanın şahsi takvimi.
    *   Sınıfın doluluk takvimi. *(İstisna: UZEM (3) tipi dersler için bu adım atlanır)*
    *   Programın (Öğrencilerin) ders takvimi.
    *   Dersin kendi koduna ait özel takvim.
    *   **Bağlı Dersler**: Eğer ders veya bağlı olduğu üst ders (Parent) bir gruba aitse, gruptaki tüm diğer derslerin program ve özel takvimleri de bu kontrole dahil edilir.
4.  Belirlenen takvimlerde **aynı hafta** (`week_index`), **aynı gün** (`day_index`) ve **çakışan saatlerde** başka ders olup olmadığı kontrol edilir.
5.  Herhangi birinde `resolveConflict` hatası alınırsa işlemi durdurur.

## Teknik Not
Bu metod, `saveScheduleItems` işleminden bağımsız olarak, sadece kontrol amaçlı (`checkScheduleCrash` üzerinden) da çağrılabilir. Çok haftalı sınav programlarında çakışmaların doğru tespit edilmesi için `itemData` içinde mutlaka `week_index` belirtilmelidir (varsayılan: 0).
