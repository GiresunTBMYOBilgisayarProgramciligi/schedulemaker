[🏠 Ana Sayfa](../../../../README.md) / [JS Assets](../../../README.md) / [Admin](../../README.md) / [ScheduleCard](./README.md) / **initStickyHeaders**

---
# ScheduleCard.initStickyHeaders()

Kullanıcı sayfayı aşağı kaydırdığında ders listesi ve tablo başlıklarının ekranın üstünde yapışkan (sticky) kalmasını sağlayan sistemi kurar.

## Mantık (Algoritma)
1.  **Wrapper Oluşturma**: `sticky-header-wrapper` sınıfına sahip, `fixed` pozisyonlu bir kapsayıcı element oluşturur.
2.  **Ofset Hesaplama**: Sayfada `navbar` varsa yüksekliğini ölçer ve yapışkan başlığın bu ofsetin altında kalmasını sağlar.
3.  **Klonlama**:
    - Mevcut ders listesini (`available-list`) ve tablo başlığını (`thead`) klonlar.
    - Klonları `wrapper` içine yerleştirir.
4.  **Scroll Dinleyicisi**: Sayfa kaydırıldığında;
    - Eğer ders programı kartı ekranın üstüne ulaşmışsa yapışkan wrapper'ı `display: block` ile gösterir.
    - Orijinal listeyi ve başlığı `visibility: hidden` yaparak gizler (yer kaplamaya devam ederler).
5.  **Genişlik Senkronizasyonu**: Tablo hücre genişliklerinin orijinaliyle aynı kalması için `syncWidths` fonksiyonunu çalıştırır.
6.  **Yatay Scroll**: Orijinal tablo yatayda kaydırıldığında, klonlanmış başlığın da aynı oranda kaymasını sağlar.
