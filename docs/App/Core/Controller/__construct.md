[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Controller](./README.md) / **__construct**

---
# Controller::__construct()

Temel kontrolcü sınıfını ilklendirir. Tüm alt kontrolcüler (`ScheduleController` vb.) bu yapıyı kullanır.

## Mantık (Algoritma)
1.  **DB Erişimi**: `Database::getConnection()` üzerinden paylaşımlı veritabanı bağlantısını `$this->database` özelliğine atar.
2.  **Amaç**: Alt kontrolcülerin veritabanı işlemlerine doğrudan erişmesini sağlamak.
