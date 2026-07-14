# Ana Sayfa
- Ana sayfa açılıyor.
- Ders programı, yıl ve dönem seçimleri düzgün listelenmiş
- Bölüm ve program seçimleri çalışıyor
- Bölüm ve Program seçimi sonrası Ders programı Gösteriliyor.
- Dönem ve yıl değişimi yapıldığında program gösteriliyor.
- Hocaya özgü ders programı gösteriliyor
- Dersliğe özgün ders programı gösteriliyor.
- Program ders programı excel olarak indiriliyor
- Hoca ders programı excel olarak indiriliyor
- Derslik ders programı excel olarak indiriliyor
- Dışa aktarma seçenekleri çalışıyor.
- Takvime aktarma işlemleri çalışıyor.
- Bölüm Ara sınav programı çalışıyor.
- Bölüm Ara sınav programı excele aktarılıyor.
- Bölüm Final sınav programı çalışıyor.
- Bölüm Final sınav programı excele aktarılıyor.
- Program Ara sınav programı çalışıyor.
- Program Ara sınav programı excele aktarılıyor.
- Program Final sınav programı çalışıyor.
- final programı ikinci haftaya geçiyirç 
- Program Final sınav programı excele aktarılıyor.
- Takvime aktarma işlemleri çalışıyor
- Yönetim paneli butonu çalışıyor

# Giriş sayfası
- başlık ana sayfaya yönlendiriyor
- Giriş başarısız oluyor.
  - Form kontrollerü yapılıyor.
  - Form hata mesajları sonraki denemede temizleniyor. 
- Giriş yapılıyor.
- Giriş sonrası yönetici paneline yönlendiriliyor.


# Yönetici Sayfası

## Admin yetkisi için
- Tm menüler aktif gözüküyor.
  - Başlangıç, Kullanıcı İşlemleri -> Liste, Ekle, İçe Aktar|, Ders işlemleri -> Liste, Ekle, İçe Aktar|, Derslik İşlemleri -> Liste, Ekle|, Akademik Birimler -> Bölüm İşlemleri -> Liste, Ekle|, Program İşlemleri -> Liste, Ekle|, Takvim İşlemleri -> Ders Programını Düzenle, Sınav Programını Düzenle, Dışa Aktar|, Ayarlar-> Genel, Kayıtlar
- Ana sayfada sayısal veriler düzgün çalışıyor
- Tüm programların temel bilgilerinin bulunduğu kartlar çalışıyor.
- Bölüm başkanı linkleri çalışıyor
- Bölüm detay butonu ve detay butonu doru sayfaya gidiyor
- Profil butonu çalışıyor
- Çıkış yap butonu çalışıyor

### Kullanıcı işlemleri
#### Kullanıcı listesi
- Tüm kullanıcılar listeleniyor
- işlemler dropdown çalışıyor. (Gör, Düzenle, Sil)
  - Gör butonu çalışıyor
  - Düzenle butonu çalışıyor
  - Sil butonu çalışıyor
- Arama çalışıyor.

- sayfalama çalışıyor.
- filtre ve sıralama çalışıyor
- Excel ve PDF çıktısı çalışıyor
#### Kullanıcı ekleme
- form doldurulmadan gönderilmiyor
- Hatalı girişlerde hata mesajları gösteriliyor.
- Hata mesajları sonraki denemede temizleniyor
- Başarılı kayıt oluyor.

#### Kullanıcı düzenleme
- Parola boş ise işleme alınmıyor. 
- düzenleme işlemi soruncus çalışıyor.
#### Kullanıcı silme
- 1 id li kullanıcı silinmiyor. 
- silme işlemi sorunsuz çalışıyor
#### Kullanıcı içe aktarma
- Şablon indir butonu çalışıyor
- Hatalı görev ve ünvan verilerinde satır bazlı hata mesajları gösteriliyor.
- Bölüm ve program bilgisi olmadan kayıt yapılabiliyor.
- İçe aktarmada girilen bölüm ve programın birbiriyle uyumu kontrol ediliyor.
- Ders ekleme başarılı olursa eklenen dersler listeleniyor.

#### Profil sayfası
- bilgi güncelleme formu çalışıyor. 
- Parola alanı boş bırakıldığında işleme alınmayacağı notu gösteriliyor.
- hocaya ait sayısal bilgiler gösteriliyor.
- Varsa gravatar görseli yükleniyor.
- Tercih edilen gün ve saatler düzeltiliyor.
- Tercih edilen gün ve saatler düzeltildikten sonra eklenen notlar popover ile gösteriliyor.
- Tercih edilen alan düzenlemeleri silinebiliyor.
- Tercih edilen alanlar taşınabiliyor. parçalı ve tek parça olarak seçilebiliyor.
- Final sınavında haftalar arasında geçiş yapılabiliryor.
- Kullanıcı sil butonu çalışıyor.

### Ders İşlemleri
#### Ders Listesi
- Arama çalışıyor
- Sayfalama çalışıyor
- Filtre ve sıralama çalışıyor
- Gör butonu çalışıyor.
- Düzenle butonu çalışıyor.
- Silme işlemi çalışıyor.
- excel çıktısı çalışıyor
- 
#### Ders Ekleme
- Form validatorler çalışıyor.
- Validasyon hataları forma işleniyor.
- Ders ekleniyor

#### Ders İçe Aktarma
- Şablon indir butonu çalışıyor
- Hatalı veri girişlerinde satır bazlı hata mesajları gösteriliyor.
- Bölüm ve program bilgisi olmadan kayıt yapılabiliyor.
- İçe aktarmada girilen bölüm ve programın birbiriyle uyumu kontrol ediliyor.
- Ders ekleme başarılı olursa eklenen dersler listeleniyor.

#### Ders Düzenleme
- Form validatorler çalışıyor.
- Validasyon hataları forma işleniyor.
- Form alanlarının türkerli değiştirilse de arkaplandaki validator işlemleri çalışıyor
- Ders düzenleme işlemi sorunsuz çalışıyor.

#### Ders Sayfası
- Ders bilgileri ve hoca bilgileri gösteriliyor.
- Ders birleştirme işlemi yapıldığında sınav birleştirme işlemi de otomatikyapılıyor. 


### Ders Programı Düzenleme
- Bölüm ve program listesi ile program seçimi yapılıyor.
- yıl ve dönem seçimi çalışıyor
- tekil ders kaydı yapılıyor
- öğle arası atlanarak ders ekleniyor
- uygun olmayan saatler atlanıyor
- uygun olmayan saatlere ekeme yapılamıyor
- tekli dersin tüm saatleri silinebiliyor
- tekli dersin 3 saatinden 2. silinebiliyor
- tekli dersin 3 saatinden 2. taşınabiliyor.
- taşıma işlemleri çalışıyor
- diğer dönemlere bırakma işlemi yapılamıyor
- var olan bir dersin üstine der eklenmeye çalışıldığında hata veriyor.
- Gruplu dersler aynı saate eklenebiliyor. 
- Aynı grup dersler aynı gün ve saate eklenemiyor.
- gruplu dersler'den bir tanesinin orta saatleri taşınabiliyor.
- gruplu derslerin bir tenesinin tüm saatleri taşınabiliyor
- Gruplu derslerin ikisi birden taşınabiliyor (Şuan taşınamıyor.)
- bağlı dersin alt programındaki çakışmalar da kontrol ediliyor.
- programa yerleştirilmiş bir dersi başka bir derse bağladığımızda o dersin programı bağlı desin programı ile aynı olur. 

- Farklı derslerin programları seçilerek tek seferde silme işlemi yapılabiliyor.


### Ara sınav programı düzenleme


### Final sınav programı düzenleme

### Bütünleme sınav programı düzenleme

### Ayarlar