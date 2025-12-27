[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Helpers](../README.md) / [FilterValidator](./README.md) / **__construct**

---
# FilterValidator::__construct()

Sınıf ilklendirildiğinde doğrulanacak tüm anahtarları (master schema) ve her işlem için gerekli kuralları (operation rules) tanımlar.

## Mantık (Algoritma)
1.  **Master Şema Tanımı**: Sistemde kullanılabilecek tüm filtre anahtarlarını (`lesson_id`, `semester_no`, `type` vb.) ve bunların beklenen temel veri türlerini (`int`, `string`, `array`, `int[]`) `$this->masterSchema` dizisine kaydeder.
2.  **Operasyon Kuralları**: Uygulamadaki her bir fonksiyonellik için (örn: `checkScheduleCrash`, `availableLessons`) hangi filtrelerin:
    - **Zorunlu (Required)**: Gönderilmesi şart olanlar.
    - **Opsiyonel (Optional)**: Gönderilmese de olur dediklerimiz.
    - **Varsayılan (Defaults)**: Gönderilmezse `getSettingValue` ile otomatik doldurulacaklar.
3.  dizilerini `$this->operationRules` içinde haritalar.
