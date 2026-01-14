[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [View](./README.md) / **Render**

---
# View::Render(array $data)

PHP dosyalarını birleştirerek nihai HTML çıktısını üretir.

## Mantık (Algoritma)
1.  **Yol Hesaplama**: `VIEWS_PATH` ortam değişkenini kullanarak hedef klasör ve sayfa dosyasının tam yolunu (`.php` uzantılı) belirler.
2.  **Dosya Kontrolü**: Belirlenen dosyanın fiziksel varlığını denetler. Yoksa bir `Exception` fırlatır.
3.  **Veri Aktarımı**: `extract($data)` fonksiyonu ile gelen dizideki anahtarları birer PHP değişkenine dönüştürür.
4.  **Tamponlama (Buffering)**: `ob_start()` ile çıktı tamponlamayı başlatır. Bu sayede sayfadaki içerikler anında ekrana basılmaz, bellekte tutulur.
5.  **Tema Dahil Etme**: Klasördeki `theme.php` dosyasını `include` eder. 
    - *Not*: `theme.php` içerisinde asıl sayfa dosyası bu aşamada tamponun içindeyken çağrılır.
6.  **Çıktılama**: `ob_end_flush()` ile tamponlanan tüm HTML içeriğini tarayıcıya gönderir.
