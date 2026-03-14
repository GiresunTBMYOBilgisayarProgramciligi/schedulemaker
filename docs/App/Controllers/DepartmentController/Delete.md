[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [DepartmentController](./README.md) / **Delete**

---

# Delete()

Akademik bir bölümü sistemden tamamen siler.

> [!WARNING]
> Bölüm silindiğinde, bu bölüme bağlı olan tüm **programlar** ve bu programlar altındaki tüm **dersler** de kalıcı olarak silinecektir.

## İşleyiş

1. Kullanıcı liste sayfasında "Sil" butonuna tıklar.
2. JavaScript (`ajax.js`) tarafında `data-confirm-message` özniteliği kontrol edilir.
3. Kullanıcıya aşağıdaki uyarı mesajı gösterilir:
   > "Bölümü sildiğinizde altındaki tüm programlar ve bu programlara ait dersler de silinecektir. Devam etmek istiyor musunuz?"
4. Kullanıcı onay verirse, `/ajax/deletedepartment/{id}` adresine POST isteği gönderilir.

## Güvenlik ve Kurallar

- Bu işlem sadece yönetici (admin) yetkisine sahip kullanıcılar tarafından gerçekleştirilebilir.
- Silme işlemi `ON DELETE CASCADE` veritabanı kuralları veya uygulama katmanındaki `delete` metodu ile ilişkili verileri de temizler.
