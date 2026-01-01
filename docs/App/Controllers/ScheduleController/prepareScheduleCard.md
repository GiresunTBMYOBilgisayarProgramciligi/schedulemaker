# prepareScheduleCard()

Ders programı düzenleme sayfasında; ders profili, bölüm ve program sayfalarındaki ders program kartlarının HTML çıktısını oluşturur. Çok haftalı yapı ve tarihli başlıkları destekler.

## Metod İmzası

```php
private function prepareScheduleCard(array $filters, bool $only_table = false, bool $preference_mode = false): string
```

### Parametreler

| Parametre | Tip | Açıklama |
| :--- | :--- | :--- |
| `$filters` | `array` | Filtreleme kriterleri (owner_type, owner_id, semester, academic_year vb.) |
| `$only_table` | `bool` | `true` ise sadece tabloyu gösterir, checkbox vb. düzenleme araçlarını gizler. |
| `$preference_mode` | `bool` | Tercihli alan ekleme modu. |

### Dönüş Değeri

| Tip | Açıklama |
| :--- | :--- |
| `string` | Hazırlanan ders programı kartının HTML çıktısı. |

## Çalışma Mantığı

1.  **Filtre Doğrulama**: Gelen filtreler `FilterValidator` üzerinden geçirilir.
2.  **Dönem Ayarı**: Hoca, derslik ve ders programları için `semester_no` null set edilir (Genel program).
3.  **Veri Hazırlama**:
    -   `prepareScheduleRows()` ile çok haftalı satır verileri (`$scheduleRows`) oluşturulur.
    -   Eğer birden fazla dönem birleştiriliyorsa, haftalar ve satırlar çakışmayacak şekilde merge edilir.
    -   `availableLessons()` ile eklenebilir dersler listesi oluşturulur.
4.  **Tarihli Başlık Hesaplama**: Sınav programları için ayarlardan başlangıç tarihi alınarak her hafta için günlere özel tarihler hesaplanır.
5.  **View Render**:
    -   `availableLessons` partial'ı render edilir.
    -   `scheduleTable` partial'ı (hafta ve tarih bilgileriyle) render edilir.
    -   Son olarak `scheduleCard` (hafta navigasyonu dahil) render edilerek döndürülür.

## Notlar
-   Çok haftalı programlarda (Final sınavları gibi) her hafta için ayrı bir `scheduleTable` üretilir.
-   `weekCount` değeri kaç haftalık veri üretildiğini takip eder ve navigasyon butonlarının görünürlüğünü kontrol eder.
