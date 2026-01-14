[🏠 Ana Sayfa](../../README.md) / [App](../README.md) / **Helpers**

---
# App\Helpers

`Helpers.php` dosyası, uygulama genelinde kullanılan global yardımcı fonksiyonları içerir.

### 1. `getSettingValue($key, $group, $default)`
*   Veritabanındaki `settings` tablosundan ayar çeker ve tip dönüşümü yapar.

### 2. `getCurrentYearAndSemester()`
*   Aktif akademik yıl ve dönem bilgisini (Örn: "2024-2025 Güz") döner.

### 3. `getSemesterNumbers($semester)`
*   Güz/Bahar dönemine göre uygun yarıyıl numaralarını (tek/çift) filtreler.

### 4. `isAuthorized(string $role, bool $reverse, $model)`
*   Kullanıcının belirtilen yetki seviyesine sahip olup olmadığını kontrol eder.

### 5. `find_key_starting_with(array $array, string $prefix)`
*   Dizi içinde belirli bir ön ek ile başlayan anahtarı bulur.

---
### 🛡️ [FilterValidator](./FilterValidator/README.md)
Gelen istek verilerini şema bazlı doğrulayan ve temizleyen gelişmiş doğrulama sınıfı.
