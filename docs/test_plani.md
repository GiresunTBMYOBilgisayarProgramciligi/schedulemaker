# 🧪 Manuel Test Planı — Master Dalına Birleştirme Öncesi

**Tarih:** 2026-03-06  
**Amaç:** Mimari dönüşüm sonrası sistemin tüm işlevlerinin sorunsuz çalıştığını doğrulamak.

> **Nasıl kullanılır:** Her test adımını sırayla uygula. Başarılıysa `✅`, sorun varsa `❌ [not]` olarak işaretle.

---

## 0. Ön Hazırlık

- [✅] Uygulamayı başlat, giriş sayfasına ulaşıldığını doğrula
- [✅] **Admin** rolüyle giriş yap
- [✅] Tarayıcı geliştirici araçları (F12) → Console'da JavaScript hatası olmadığını kontrol et
- [✅] Network sekmesini aç, 500/4xx hataları gözlemle

---

## 1. 👤 Kullanıcı İşlemleri

### 1.1 Kullanıcı Ekleme
- [✅] **Admin → Kullanıcılar → Yeni Kullanıcı**
- [✅] Tüm alanları doldur (ad, soyad, e-posta, şifre, rol: Akademisyen, bölüm, program)
- [✅] Kaydet → "Kullanıcı başarıyla eklendi" mesajı bekleniyor
- [✅] Aynı e-posta ile tekrar ekle → "Bu e-posta adresi zaten kayıtlı" hatası bekleniyor

### 1.2 Kullanıcı Güncelleme
- [✅] Eklenen kullanıcıyı bul → Düzenle
- [✅] Adı güncelle, kaydet → Başarı mesajı bekleniyor
- [✅] **Şifre alanını boş bırakarak** güncelle → Şifre değişmemeli, giriş eski şifreyle çalışmalı
- [✅] Şifre alanını doldurarak güncelle → Yeni şifreyle giriş yapılabilmeli

### 1.3 Kullanıcı Silme
- [✅] Eklenen test kullanıcısını sil → "Kullanıcı başarıyla Silindi" mesajı bekleniyor

### 1.4 Kullanıcı İçe Aktarma (Excel)
- [✅] **Admin → Kullanıcılar → İçe Aktar**
- [✅] Örnek Excel dosyasını yükle → Başarı/hata raporu bekleniyor
- [✅] İçe aktarılan kullanıcıların listede göründüğünü doğrula

### 1.5 Giriş / Çıkış
- [✅] Eklenen kullanıcı ile giriş yap → Başarılı
- [✅] Yanlış şifreyle giriş → "Şifre Yanlış" hatası bekleniyor
- [✅] Kayıtlı olmayan e-posta → "Kullanıcı kayıtlı değil" hatası bekleniyor
- [✅] Çıkış yap → Giriş sayfasına yönlendiriliyor mu?
- [✅] "Beni Hatırla" seçili çıkış yapıp tarayıcıyı kapat → Tekrar açınca otomatik giriş

---

## 2. 📚 Ders İşlemleri

### 2.1 Ders Ekleme
- [✅] **Admin → Dersler → Yeni Ders**
- [✅] Tüm alanları doldur (kod, ad, hoca, bölüm, program, saat, dönem no, tür)
- [✅] Kaydet → "Ders başarıyla eklendi" bekleniyor
- [✅] Aynı ders kodu ile tekrar ekle → Hata mesajı bekleniyor

### 2.2 Ders Güncelleme
- [✅] Eklenen dersi bul → Düzenle
- [✅] Ders saatini güncelle → Başarı mesajı bekleniyor
- [✅] **Hoca** rolüyle giriş yapıp kendi dersinin yalnızca `size` alanını güncelle → İzin verilmeli
- [✅] Hoca rolüyle başka alanı güncellemeye çalış → Yetki hatası bekleniyor

### 2.3 Ders Silme
- [✅] Test dersini sil → Başarı mesajı bekleniyor
- [✅] Programı olan bir dersi sil → İlgili schedule item'larının da silindiğini doğrula

### 2.4 Ders İçe Aktarma (Excel)
- [✅] **Admin → Dersler → İçe Aktar**
- [✅] Örnek Excel dosyasını yükle → Başarı/hata raporu bekleniyor
- [✅] İçe aktarılan derslerin listede göründüğünü doğrula

### 2.5 Ders Birleştirme (Child Lesson)
- [✅] İki ders seç → "Ders Birleştir" işlemi yap
- [✅] Birleştirme sonrası child dersin programının parent'tan kopyalandığını doğrula
- [✅] Child dersin kendi bağımsız schedule'ının silindiğini doğrula
- [✅] Aynı derse zaten bağlı bir dersi tekrar birleştirmeye çalış → Hata bekleniyor
- [✅] Parent'ın saati child'dan az olduğunda birleştir → "Saat az olamaz" hatası bekleniyor

### 2.6 Ders Bağlantısı Kaldırma
- [✅] Birleşik dersi seç → "Bağlantıyı Kaldır"
- [✅] Child'ın schedule'larının silindiğini doğrula, parent'ın korunduğunu doğrula

---

## 3. 🏫 Derslik İşlemleri

### 3.1 Derslik Ekleme
- [✅] **Admin → Derslikler → Yeni Derslik**
- [✅] Ad, kapasite, tür doldur → Kaydet
- [✅] Aynı isimle tekrar ekle → Hata mesajı bekleniyor

### 3.2 Derslik Güncelleme
- [✅] Eklenen dersliği bul → Kapasiteyi güncelle → Başarı bekleniyor

### 3.3 Derslik Silme
- [✅] Test dersliğini sil → Başarı bekleniyor

---

## 4. 🏛️ Bölüm ve Program İşlemleri

### 4.1 Bölüm Ekleme / Güncelleme / Silme
- [✅] Yeni bölüm ekle → Başarı
- [✅] Bölüm adını güncelle → Başarı
- [✅] Test bölümünü sil → Başarı

### 4.2 Program Ekleme / Güncelleme / Silme
- [✅] Bir bölüme yeni program ekle → Başarı
- [✅] Program adını güncelle → Başarı
- [✅] Test programını sil → Başarı

---

## 5. 📅 Ders Programı İşlemleri

### 5.1 Program Görüntüleme
- [✅] Ders programı sayfasını aç (program / hoca / derslik bazlı)
- [✅] Uygun dersler listesinin yüklendiğini doğrula  
- [✅] Uygun derslikler listesinin yüklendiğini doğrula

### 5.2 Ders Programı Ekleme (Tek Item)
- [✅] Ders listesinden bir ders sürükle/seç → Bir hücreye bırak
- [✅] Derslik seç → Kaydet
- [✅] Item'ın program, ders, hoca ve derslik schedule'larında oluştuğunu doğrula

### 5.3 Ders Programı Ekleme (Grup Item)
- [✅] İki farklı grubu aynı hücreye yerleştir → Grup item oluştuğunu doğrula

### 5.4 Çakışma Kontrolü
- [✅] Aynı hocayı aynı saatte iki farklı derse ekle → Çakışma uyarısı bekleniyor
- [✅] Aynı dersliği aynı saatte iki derse ekle → Çakışma uyarısı bekleniyor
- [✅] Aynı programı aynı saatte iki derse ekle → Çakışma uyarısı bekleniyor

### 5.5 Preferred / Unavailable Slot
- [✅] Tercih edilen slot üzerine ders ekle → Preferred kaldırılıp ders yerleşmeli
- [✅] Müsait olmayan slot'a ders eklemeye çalış → Hata bekleniyor

### 5.6 Ders Programı Silme
- [✅] Tek item sil → Sadece o item silinmeli (tüm schedule kopyaları)
- [✅] Partial silme (büyük bloğun bir saatini sil) → Kalan saat ayrı item olarak oluşmalı
- [✅] Grup item'dan tek ders sil → Diğer ders kalan item'da görünmeli

### 5.7 Çakışma Raporu
- [✅] `checkScheduleCrash` → Çakışan item'lar varsa listele

### 5.8 Hoca / Derslik / Program Schedule Kontrolü
- [✅] `checkLecturerScheduleAction` → Doğru sonuç
- [✅] `checkClassroomScheduleAction` → Doğru sonuç
- [✅] `checkProgramScheduleAction` → Doğru sonuç

---

## 6. 🎓 Sınav Programı İşlemleri

### 6.1 Sınav Programı Ekleme
- [ ] Sınav programı (vize/final) sayfasını aç
- [ ] Bir derse sınav saati ekle → Derslik ve gözlemci ata → Kaydet
- [ ] İlgili schedule'ların oluştuğunu doğrula

### 6.2 Sınav Çakışma Kontrolü
- [ ] Aynı saatte iki sınav ekle → Çakışma uyarısı bekleniyor

### 6.3 Sınav Silme
- [ ] Eklenen sınav item'larını sil → Temizlendiğini doğrula

---

## 7. 📤 Dışa Aktarma İşlemleri

### 7.1 Excel Dışa Aktarma
- [✅] Ders programını Excel olarak dışa aktar → Dosya indirildi
- [✅] Excel içeriğini kontrol et → Doğru veriler, "İşlemler" sütunu yok

### 7.2 PDF Dışa Aktarma
- [✅] Ders programını PDF olarak dışa aktar → Dosya indirildi/yeni sekmede açıldı

### 7.3 ICS (Takvim) Dışa Aktarma
- [✅] Ders programını ICS olarak dışa aktar → Dosya indirildi
- [✅] Takvim uygulamasına içe aktar → Etkinliklerin doğru göründüğünü doğrula

---

## 8. ⚙️ Ayarlar

### 8.1 Ayarları Güncelleme
- [✅] **Admin → Ayarlar** sayfasını aç
- [✅] Bir ayarı değiştir → Kaydet → "Ayarlar kaydedildi" bekleniyor

### 8.2 Logları Temizleme
- [✅] Log temizle butonuna tıkla → Başarı mesajı bekleniyor

---

## 9. 🔒 Yetki Kontrolleri (Kritik)

| Test | Beklenen |
|------|----------|
| `Kullanıcı` rolüyle ders eklemeye çalış | ❌ Yetki hatası |
| `Akademisyen` rolüyle başka hocanın dersini güncelle | ❌ Yetki hatası |
| `Bölüm Başkanı` rolüyle kullanıcı sil | ❌ Yetki hatası |
| `Müdür Yardımcısı` rolüyle ders birleştir | ✅ İzin verilmeli |
| `Admin` rolüyle tüm işlemler | ✅ İzin verilmeli |

---

## 10. 🔁 Uçtan Uca Senaryo

> Gerçek kullanım senaryosu — tek seferde çalıştır:

1. Yeni bölüm ekle
2. Bölüme program ekle
3. Programa hoca (Akademisyen kullanıcısı) ekle
4. Programdan 2 ders ekle
5. İki dersi birleştir (child lesson)
6. Ders programı sayfasına git → Her iki ders de uygun listede görünmeli
7. Ana derse program saati ekle → Child ders için de otomatik eklenmiş olmalı
8. Programı Excel ve PDF olarak dışa aktar
9. Ana dersin program saatini sil → Child için de silinmeli
10. Ders bağlantısını kaldır
11. Eklenen tüm test verilerini temizle

---

## Sonuç

- **Tüm testler geçti** → `git merge` işlemi güvenli ✅  
- **Hata var** → `docs/kalan_isler.md` dosyasına ekle, fix sonrası tekrar test et
