[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Core](../README.md) / [Model](./README.md) / **getDepartmentProgramsList**

---
# Model::getDepartmentProgramsList()

Ekleme ve düzenleme sayfaları için dersin bölümüne uygun program listesini hazırlar.

## Mantık (Algoritma)
1.  **Bölüm Kontrolü**: Model üzerinde `department_id` tanımlı mı bakar.
2.  **Filtreleme**:
    - Bölüm tanımlıysa: Sadece o bölüme (`department_id`) ait programları çeker.
    - Bölüm tanımsızsa: Tüm programları veya varsayılan bir listeyi çeker.
3.  **Formatlama**: `Program` modelini kullanarak veritabanı sorgusunu çalıştırır ve sonuçları bir dizi nesne olarak UI'ya sunar.
