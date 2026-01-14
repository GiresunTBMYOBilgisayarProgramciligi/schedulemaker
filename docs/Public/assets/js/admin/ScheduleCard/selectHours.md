[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **selectHours**

---
# ScheduleCard.selectHours(n)

Bir dersin kaç saatlik bir blok halinde yerleştirileceğini seçmek için kullanılır (Toplu seçim modu).

## Mantık (Algoritma)
1.  Kullanıcının tıkladığı saat sayısını (`n`) girdi olarak alır.
2.  `this.selectedHoursPerItem` değerini günceller.
3.  **Sürükleme Hazırlığı**: Eğer bir ders sürükleniyorsa, bu yeni sürenin tabloda kaplayacağı alanı (çakışma kontrolüyle birlikte) dinamik olarak yeniden hesaplar.
4.  Görsel olarak "seçili blok boyutu" bilgisini UI'da vurgular.
