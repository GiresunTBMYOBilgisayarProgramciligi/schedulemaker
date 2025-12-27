[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **openAssignmentModal**

---
# ScheduleCard.openAssignmentModal(scheduleItem)

Bir ders kartına tıklandığında veya yeni atama yapıldığında detayların (derslik, hoca, not) girilebileceği modal penceresini açar.

## Mantık (Algoritma)
1.  **Veri Yükleme**: Tıklanan dersin mevcut ID, derslik ve hoca bilgilerini `scheduleItem` üzerinden okur.
2.  **Seçenekleri Getir**: `fetchOptions()` metodunu çağırarak o zaman dilimine uygun güncel derslik/hoca listesini arka planda çeker.
3.  **Form Doldurma**: Modal içerisindeki input alanlarına dersin mevcut verilerini ön-tanımlı (default) olarak yazar.
4.  **Görünürlük**: Bootstrap modal tetikleyicisi ile pencereyi kullanıcıya gösterir.
5.  **Kaydetme Olayı**: Modaldaki "Kaydet" butonunun `click` olayını bu ders kartıyla ilişkilendirir.
