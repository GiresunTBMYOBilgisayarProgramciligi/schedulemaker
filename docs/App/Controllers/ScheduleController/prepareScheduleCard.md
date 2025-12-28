[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](./README.md) / **prepareScheduleCard()**

---

# prepareScheduleCard()

Ders programı düzenleme sayfasında; ders profili, bölüm ve program sayfalarındaki ders program kartlarının HTML çıktısını oluşturur.

## Metod İmzası

```php
private function prepareScheduleCard(array $filters, bool $only_table = false): string
```

### Parametreler

| Parametre | Tip | Açıklama |
| :--- | :--- | :--- |
| `$filters` | `array` | Filtreleme kriterleri (owner_type, owner_id, semester, academic_year vb.) |
| `$only_table` | `bool` | `true` ise sadece tabloyu gösterir, checkbox vb. düzenleme araçlarını gizler. (Varsayılan: `false`) |

### Dönüş Değeri

| Tip | Açıklama |
| :--- | :--- |
| `string` | Hazırlanan ders programı kartının HTML çıktısı. |

## Çalışma Mantığı

1.  **Filtre Doğrulama**: Gelen filtreler `FilterValidator` üzerinden geçirilir.
2.  **Dönem Ayarı**: Hoca, derslik ve ders programları için `semester_no` null set edilir (Genel program).
3.  **Veri Hazırlama**:
    -   `prepareScheduleRows()` ile tablonun satır verileri (`$scheduleRows`) oluşturulur.
    -   `availableLessons()` ile eklenebilir dersler listesi oluşturulur.
4.  **View Render**:
    -   `availableLessons` partial'ı render edilir.
    -   `scheduleTable` partial'ı render edilir.
    -   Son olarak `scheduleCard` partial'ı tüm içerikle birlikte render edilerek döndürülür.

## Notlar
-   `$only_table` parametresi `true` gönderildiğinde, `availableLessons` ve `scheduleTable` partial'larına bu değer aktarılır. `scheduleTable` içerisindeki ders kartlarında toplu işlem checkbox'ları (`.lesson-bulk-checkbox`) gizlenir.
