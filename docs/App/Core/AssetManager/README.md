[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **AssetManager**

---
# App\Core\AssetManager

`AssetManager` sınıfı, uygulamanın CSS ve JavaScript varlıklarını (assets) merkezi bir noktadan yöneten, sayfa bazlı dinamik yükleme yapan yardımcı sınıftır.

## Temel İşlevler

1.  **Global Varlıklar**: Uygulamanın her sayfasında yüklenmesi gereken temel kütüphaneleri (Bootstrap, AdminLTE vb.) otomatik olarak ilklendirir.
2.  **Sayfa Özel Yükleme**: Belirli sayfalar için (`homeIndex`, `listpages` vb.) önceden tanımlanmış asset gruplarını tek komutla yükler.
3.  **Tekilleştirme**: Aynı dosyanın birden fazla kez eklenmesini önleyerek gereksiz yükleme ve çakışmaların önüne geçer.

## Metod Listesi

*   [__construct()](./__construct.md): Global assetleri ($globalCss ve $globalJs) listeye dahil eder.
*   [addCss()](./addCss.md): Listeye yeni bir CSS dosyası ekler (mükerrer kontrolü yapar).
*   [addJs()](./addJs.md): Listeye yeni bir JavaScript dosyası ekler (mükerrer kontrolü yapar).
*   [loadPageAssets()](./loadPageAssets.md): Sayfa adına göre ilgili asset grubunu topluca yükler.
*   [renderCss()](./renderCss.md): Kayıtlı tüm CSS dosyalarını HTML `<link>` etiketlerine dönüştürür.
*   [renderJs()](./renderJs.md): Kayıtlı tüm JavaScript dosyalarını HTML `<script>` etiketlerine dönüştürür.
