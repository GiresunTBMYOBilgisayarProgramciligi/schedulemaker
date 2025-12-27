[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **syncTableItems**

---
# ScheduleCard.syncTableItems(updatedItems)

Sunucudan gelen güncel ders verilerini, tablodaki mevcut HTML elemanları ile eşleştirir ve `data-` özniteliklerini günceller.

## Mantık (Algoritma)
1.  Sunucudan dönen her bir ders nesnesi (`updatedItems`) için:
    - Tabloda o dersin geçici veya eski haline ait HTML elemanını bulur.
    - Elemanın `data-id` değerini sunucudan gelen kalıcı ID ile günceller.
    - Eleman üzerindeki diğer meta verileri (hoca, derslik, koordinat) senkronize eder.
2.  Bu işlem, özellikle toplu kaydetme veya ders bölme (split) işlemlerinden sonra ID çakışmalarını önlemek için kritiktir.
