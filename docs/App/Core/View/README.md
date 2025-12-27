[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / **View**

---
# View

`View` sınıfı, uygulamanın sunum katmanıdır. PHP dosyalarını birleştirerek ve verileri enjekte ederek kullanıcıya gösterilecek nihai HTML içeriğini yönetir.

## Temel Görevi
Kontrolcülerden gelen verileri (`data`), `Views` klasöründeki şablonlar (templates) ile buluşturmak ve bu şablonları bir ana tema (theme) içerisinde sarmalayarak çıktı üretmektir.

## Metodlar
*   [__construct()](./__construct.md): Görünümün ait olduğu klasör, sayfa ve dosya bilgilerini set eder.
*   [Render()](./Render.md): Ana temayı ve sayfa içeriğini birleştirerek ekrana basar.
*   [renderPartial()](./renderPartial.md): (Static) Sadece belirli bir parça (snippet) HTML dosyasını render edip string olarak döner.
