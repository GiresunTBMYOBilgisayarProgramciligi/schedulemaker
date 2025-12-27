[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [View](./README.md) / **renderPartial**

---
# View::renderPartial(string $folder, string $page, string $file, array $data = [])

Sayfanın tamamını değil, sadece küçük bir bölümünü (snippet) render etmek için kullanılır (Static metod).

## Mantık (Algoritma)
1.  **Tam Yol Belirleme**: `folder/pages/page/partials/file.php` hiyerarşisine uygun olarak dosya yolunu oluşturur.
2.  **Dosya Kontrolü**: Belirtilen partial dosyasının varlığını kontrol eder.
3.  **Veri Aktarımı**: Gelen `$data` dizisini `extract()` ile değişkenlere dönüştürür.
4.  **Tamponlama**: `ob_start()` ile çıktıyı yakalamaya başlar, dosyayı `include` eder.
5.  **Dönüş**: Yakalanan içeriği `ob_get_clean()` ile bir string olarak döndürür (ekrana basmaz, kontrolcüye teslim eder).
