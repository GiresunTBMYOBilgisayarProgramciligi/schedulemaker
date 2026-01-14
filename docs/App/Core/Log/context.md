[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Log](./README.md) / **context**

---
# Log::context(?object $self = null, array $extra = [])

Her log kaydına eklenecek olan standart meta-verileri hazırlar.

## Mantık (Algoritma)
1.  **Kullanıcı Tespiti**: `UserController` aracılığıyla mevcut oturumdaki kullanıcıyı (ad-soyad ve ID) bulur.
2.  **Backtrace Analizi**: PHP'nin `debug_backtrace` fonksiyonunu kullanarak log fonksiyonunu çağıran asıl dosya, metod ve satır bilgisini çıkarır.
3.  **İstek Bilgileri**: Mevcut URL (`REQUEST_URI`) ve IP adresi (`REMOTE_ADDR`) bilgilerini toplar.
4.  **Tablo Tespiti**: Eğer çağrıyı yapan nesne bir `Model` ise ve `table_name` özelliğine sahipse, ilgili veritabanı tablosu adını da bağlama ekler.
5.  **Birleştirme**: Toplanan tüm verileri `$extra` dizisiyle birleştirerek döndürür.
