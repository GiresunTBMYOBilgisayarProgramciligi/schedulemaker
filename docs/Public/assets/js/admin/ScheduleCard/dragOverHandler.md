[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **dragOverHandler**

---
# ScheduleCard.dragOverHandler(e)

Sürüklenen bir eleman, bırakılabilecek bir alanın (genellikle tablo hücresi) üzerindeyken sürekli tetiklenir.

## Mantık (Algoritma)
1.  **Varsayılanı Engelle**: `e.preventDefault()` çağrısı yaparak tarayıcının "bırakılamaz" varsayılan davranışını iptal eder. Bu, `drop` olayının tetiklenmesi için zorunludur.
2.  **Hedef Kontrolü**: Üzerinde bulunulan elemanın bir tablo hücresi (`<td>`) veya hücre içindeki bir alan olup olmadığını kontrol eder.
3.  **Görsel Geribildirim**: Eğer alan geçerli bir bırakma noktasıysa (çakışma yoksa), imleci "kopyala" veya "taşı" formuna sokar.
