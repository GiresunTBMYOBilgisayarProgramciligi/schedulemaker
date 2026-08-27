# E2E ve Arayüz Testleri (Playwright Checklist)

Aşağıdaki tüm uçtan uca (E2E) senaryolar `tests/e2e/` altındaki Playwright test paketleriyle **otomasyona bağlanmış ve %100 başarılı** olarak doğrulanmıştır:

| Test Paketi | Kapsanan Kontroller & Fonksiyonel Akışlar | Durum |
| :--- | :--- | :---: |
| [`schedule-lifecycle.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/schedule-lifecycle.spec.ts) | **Canlı Yaşam Döngüsü (Tam E2E):** Birim/Bölüm/Program seçimi, sol listeden takvime **Sürükle-Bırak**, Sınıf Seçim Modalı, Takvime Yerleştirme, **Sağ Tık -> Dersliği Düzenle**, Başka Slota **Taşıma (Table->Table)** ve Takvimden **Silme (Table->List)**. | ✅ Geçti |
| [`schedule-full-flow.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/schedule-full-flow.spec.ts) | **Ders Programı Akışı:** Hiyerarşik seçimler, Takvim tablosu ve ders listesi doğrulaması, Context Menü tetiklemeleri. | ✅ Geçti |
| [`exam-schedule-flow.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/exam-schedule-flow.spec.ts) | **Sınav Programı Akışı:** Sınav türü seçimi (Ara Sınav / Final / Bütünleme), 2. Hafta / 1. Hafta navigasyonu ve sınav takvim yüklemesi. | ✅ Geçti |
| [`schedule-drag-drop.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/schedule-drag-drop.spec.ts) | **Takvim Düzenleyici:** Program, Hoca ve Derslik sekmeleri arası geçiş, Yıl/Dönem seçimi, Notlar & Bildirim butonları. | ✅ Geçti |
| [`home-extended.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/home-extended.spec.ts) | **Ana Sayfa Detay:** Program türü geçişleri (Ders, Ara Sınav, Final, Bütünleme), Hoca/Derslik sekmeleri, Excel/iCal butonları. | ✅ Geçti |
| [`user-crud.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/user-crud.spec.ts) | **Kullanıcı İşlemleri:** DataTable arama/filtreleme, HTML5 `required` validasyon kontrolü, yeni kullanıcı ekleme ve anlık arama. | ✅ Geçti |
| [`lesson-crud.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/lesson-crud.spec.ts) | **Ders İşlemleri:** Ders listesi, Ders Ekleme formu açılışı, validasyonlar ve bağımlı seçimler. | ✅ Geçti |
| [`classroom-building-crud.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/classroom-building-crud.spec.ts) | **Derslik & Bölüm & Program:** Yeni derslik ekleme, yeni bölüm ekleme ve yeni program ekleme form kontrolleri. | ✅ Geçti |
| [`profile-preferences.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/profile-preferences.spec.ts) | **Profilim & Tercihler:** Bilgi formu, ders yükü istatistikleri, parola notu ve tercih takvim kartları. | ✅ Geçti |
| [`role-permissions.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/role-permissions.spec.ts) | **RBAC Yetkilendirme:** Öğretim Görevlisi, Bölüm Başkanı ve Admin rolleri ile arayüz erişim doğrulamaları. | ✅ Geçti |
| [`admin-crud.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/admin-crud.spec.ts) | **Yönetim Paneli:** Dashboard bileşenleri, Sidebar listeleri ve Çıkış yapma akışı. | ✅ Geçti |
| [`schedule-editor.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/schedule-editor.spec.ts) | **Program Yönetimi:** Sınav programı düzenleme, Dışa aktarma, Yayınlama, Sistem ayarları ve Log takip sayfaları. | ✅ Geçti |
| [`home-page.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/home-page.spec.ts) | **Ana Sayfa & Navigasyon:** Başlık, filtreler ve giriş yönlendirmesi. | ✅ Geçti |
| [`auth.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/auth.spec.ts) | **Kimlik Doğrulama:** Geçersiz giriş uyarıları ve form validasyonları. | ✅ Geçti |
| [`admin-navigation.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/admin-navigation.spec.ts) | **Erişim Koruması:** Misafir kullanıcıların yönetim sayfalarına erişim engeli. | ✅ Geçti |
| [`schedule-view.spec.ts`](file:///home/sametatabasch/PhpstormProjects/schedulemaker/tests/e2e/schedule-view.spec.ts) | **Program Gösterimi:** Takvim tablosu yükleme ve export butonları. | ✅ Geçti |
