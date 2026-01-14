[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ImportExportManager](./README.md) / **__construct**

---
# ImportExportManager::__construct(?array $uploadedFile = null, array $formData = [])

İçe aktarma (import) veya dışa aktarma (export) işlemleri için gerekli başlangıç yapılandırmasını yapar.

## Mantık (Algoritma)
1.  **Veri Atama**: Yüklenen dosya bilgisini (`uploadedFile`) ve formdan gelen ek meta verileri (`formData`) sınıf özelliklerine kaydeder.
2.  **Spreadsheet İlklendirme**: `PhpOffice\PhpSpreadsheet\Spreadsheet` nesnesini oluşturarak Excel işlemlerine hazırlar.
