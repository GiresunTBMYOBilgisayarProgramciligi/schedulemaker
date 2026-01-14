[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [View](./README.md) / **__construct**

---
# View::__construct(string $view_folder, string $view_page, string $view_file = 'index')

Bir görünüm nesnesi oluşturur ve görüntülenecek dosyanın hiyerarşik yolunu belirler.

## Mantık (Algoritma)
1.  **Parametre Atama**:
    - `$view_folder`: Tema klasörü (örn: `admin`).
    - `$view_page`: Sayfa grubu (örn: `lessons`).
    - `$view_file`: Tekil sayfa dosyası (örn: `lesson_edit`).
2.  **İlklendirme**: Gelen değerleri nesnenin dahili özelliklerine (`properties`) kaydeder.
