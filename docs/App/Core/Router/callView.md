[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Router](./README.md) / **callView**

---
# Router::callView(string $view_path)

Görünüm (View) katmanını ayağa kaldıran ana yönlendirme metodudur.

## Mantık (Algoritma)
1.  **Parçalama**: Verilen `$view_path` (örn: "admin/lessons/list") stringini slash (`/`) işaretine göre bölümlere ayırır.
2.  **Hiyerarşi Belirleme**:
    - 1. Parça: `view_folder` (Klasör - örn: admin)
    - 2. Parça: `view_page` (Sayfa Grubu - örn: lessons)
    - 3. Parça: `view_file` (Dosya Adı - örn: list)
3.  **Nesne Oluşturma**: `App\Core\View` sınıfından yeni bir nesne türetir ve bu hiyerarşik bilgileri yapıcıya (constructor) iletir.
4.  **Render**: Router'ın sahip olduğu `$view_data` dizisini (assetler, sayfa başlığı vb. dahil) View nesnesinin `Render()` metoduna göndererek sayfanın ekrana basılmasını sağlar.
