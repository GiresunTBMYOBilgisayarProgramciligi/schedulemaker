[🏠 Ana Sayfa](../../../../../README.md) / [Public](../../../../README.md) / [assets](../../../README.md) / [js](../../README.md) / [admin](../README.md) / **ExamScheduleCard**

---

# ExamScheduleCard

`ExamScheduleCard`, [ScheduleCard](./ScheduleCard/README.md) sınıfından türetilmiştir ve sınav programı (vize, final, büt) işlemlerini yönetir.

## ScheduleCard'dan Farkları

Sınav programı, normal ders programına göre daha karmaşık atama süreçlerine sahiptir. Sınavlar bir ders için birden fazla dersliğe ve gözetmene paylaştırılabilir.

- **openAssignmentModal**: Çoklu derslik ve gözetmen seçimine izin veren gelişmiş bir modal açar. Toplam kapasiteyi ders mevcuduyla karşılaştırır.
- **checkCrash**: Sınav bazlı çakışma kontrollerini yapar. 
    - Aynı hoca/gözetmen aynı saatte farklı sınavda olamaz.
    - Aynı derslik aynı saatte farklı sınavda olamaz.
    - Ancak aynı dersin farklı grupları/şubeleri aynı saatte farklı dersliklerde bulunabilir.

- **showContextMenu**: Sağ tık menüsünü sınava özel olarak düzenler. Sınava atanmış tüm gözetmen hocaların ve dersliklerin programlarını görüntüleme seçeneklerini dinamik olarak menüye ekler.

## Önemli Metodlar

### [openAssignmentModal]
Gelişmiş sınav atama penceresidir. 
- Dinamik satır ekleme (Derslik + Gözetmen).
- Otomatik kapasite hesaplama.
- Mevcut kontrolü (Kapasite yetersizse kullanıcıyı uyarır).
- Sınav atama modalının başlığına gün adını, varsa tarihi (`data-date` üzerinden) ve saati ekleyerek "Ders Adı - Gün (Tarih) - Saat" formatında yazar.
- **Kalan mevcudu yok say:** Derslik kapasitesi ders mevcudundan az olduğunda "Kalan mevcudu yok say" seçeneği otomatik görünür. İşaretlendiğinde `detail.ignore_remaining = true` bayrağı kaydedilir ve backend bu dersi tamamlanmış kabul eder.

### [checkCrash]
Sınavların çakışma kurallarını denetler:
- Gözetmen çakışması.
- Derslik çakışması.
- Aynı saat diliminde farklı derslerin sınavlarının yapılamaması kuralı.


