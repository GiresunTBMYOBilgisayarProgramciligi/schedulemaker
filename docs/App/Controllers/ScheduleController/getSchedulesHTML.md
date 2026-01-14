[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ScheduleController](./README.md) / **getSchedulesHTML()**

---

# getSchedulesHTML()

Verilen filtrelere uygun olan bir veya birden fazla ders programı kartının (Schedule Card) HTML çıktısını döndürür.

## Metod İmzası

```php
public function getSchedulesHTML(array $filters = [], bool $only_table = false): string
```

### Parametreler

| Parametre | Tip | Açıklama |
| :--- | :--- | :--- |
| `$filters` | `array` | Arama ve filtreleme kriterleri. |
| `$only_table` | `bool` | `true` ise alt metodlara (`prepareScheduleCard`) iletilerek kartların sadece tablo modunda oluşturulmasını sağlar. (Varsayılan: `false`) |

### Dönüş Değeri

| Tip | Açıklama |
| :--- | :--- |
| `string` | Hazırlanan tüm program kartlarının birleştirilmiş HTML çıktısı. |

## Çalışma Mantığı

1.  **Doğrulama**: `$filters` dizisi `FilterValidator` ile doğrulanır.
2.  **Dönem Kontrolü**:
    -   Eğer `semester_no` bir dizi ise (birleştirilmiş dönem), tek bir kart oluşturulur.
    -   `user`, `classroom` veya `lesson` türünde bir program isteniyorsa, `semester_no` null set edilerek genel bir program kartı oluşturulur.
    -   Diğer durumlarda (örn. Bölüm programı), ilgili dönemdeki tüm yarıyıllar için (`getSemesterNumbers`) döngüye girilerek her biri için `prepareScheduleCard` çağrılır.
3.  **Birleştirme**: Oluşturulan tüm kart HTML'leri birleştirilerek döndürülür.

## Notlar
-   Bu metod `AjaxRouter` içinden gelen talepleri karşılamak için ana giriş noktasıdır.
-   `$only_table` parametresi, çıktının düzenlenebilir olup olmayacağını belirler.
