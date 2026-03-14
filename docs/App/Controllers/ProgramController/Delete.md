[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [ProgramController](./README.md) / **Delete**

---

# Delete()

Akademik bir programı (örn: Bilgisayar Programcılığı) sistemden tamamen siler.

> [!WARNING]
> Program silindiğinde, bu programa bağlı olan tüm **dersler** kalıcı olarak silinecektir.

## İşleyiş

1. Kullanıcı liste sayfasında "Sil" butonuna tıklar.
2. JavaScript (`ajax.js`) tarafında `data-confirm-message` özniteliği kontrol edilir.
3. Kullanıcıya aşağıdaki uyarı mesajı gösterilir:
   > "Programı sildiğinizde bu programa ait tüm dersler de silinecektir. Devam etmek istiyor musunuz?"
4. Kullanıcı onay verirse, `/ajax/deleteprogram/{id}` adresine POST isteği gönderilir.

## Güvenlik ve Kurallar

- Bu işlem sadece yönetici (admin) yetkisine sahip kullanıcılar tarafından gerçekleştirilebilir.
- Silme işlemi veritabanı düzeyinde veya uygulama düzeyinde ilişkili derslerin de silinmesini tetikler.
