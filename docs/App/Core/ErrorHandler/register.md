[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [ErrorHandler](./README.md) / **register**

---
# ErrorHandler::register()

Uygulamanın hata yönetim sistemini PHP'ye entegre eder.

## Mantık (Algoritma)
1.  **set_error_handler**: PHP'nin standart hatalarını (`warning`, `notice` vb.) yakalamak için `handleError` metodunu atar.
2.  **set_exception_handler**: Yakalanmamış tüm PHP istisnaları için `handleException` metodunu atar.
3.  **register_shutdown_function**: Script sonlandığında çalışacak olan `handleShutdown` metodunu kaydeder (ölümcül hataları yakalamak için).
