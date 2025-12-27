[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](README.md) / **checkScheduleCrash**

---
# ScheduleController::checkScheduleCrash(array $items)

Çoklu ders ekleme işlemleri öncesinde toplu bir çakışma denetimi yapar.

## İşleyiş
1.  Gelen ders listesindeki her bir öğe için `checkItemConflict` metodunu çağırır.
2.  Herhangi bir öğede kural ihlali (hata) tespit edilirse işlemi hemen durdurur.
3.  Kayıt yapmaz, sadece "bu işlem güvenli mi?" sorusuna yanıt verir.

## Kullanım Alanı
Genellikle Frontend tarafında sürükle-bırak onaylanmadan hemen önce veya toplu taşıma işlemlerinde güvenlik katmanı olarak kullanılır.
