[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [DbLogHandler](./README.md) / **__construct**

---
# DbLogHandler::__construct($level = Level::Debug, bool $bubble = true)

Handler nesnesini oluşturur ve hangi log seviyelerinin yakalanacağını belirler.

## Mantık (Algoritma)
1.  **Level Atama**: Logların hangi önem derecesinden itibaren (örn: Debug, Info, Error) işleneceğini set eder.
2.  **Bubble Ayarı**: Logun işlendikten sonra diğer handler'lara (örn: FileHandler) iletilip iletilmeyeceğine karar verir.
3.  **İlklendirme**: Üst sınıf olan `AbstractProcessingHandler` yapısını ayağa kaldırır.
