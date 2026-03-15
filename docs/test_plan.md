# Test Uygulama Planı

Bu doküman, "Ders Programı Düzenleme Sistemi" için test altyapısının kurulması ve sürdürülebilir bir test stratejisinin uygulanması için yol haritasını içerir.

## 1. Test Stratejisi

Proje için üç katmanlı bir test yaklaşımı benimsenecektir:

- **Unit Tests (Birim Testler)**: Harici bağımlılığı olmayan (DB, Dosya Sistemi vb.) yardımcı sınıfların ve iş mantığının test edilmesi.
- **Integration Tests (Entegrasyon Testleri)**: Birden fazla bileşenin (Servis + Repository + Database) birlikte çalışmasının test edilmesi.

## 2. Test Ortamı ve Araçlar

- **Test Framework**: PHPUnit 10+
- **Database**: 
    - Testler için ayrı bir MySQL veritabanı (`schedulemaker_test`) veya SQLite (InMemory) kullanılacaktır.
    - Her test öncesinde veritabanı "Clean State" (temiz durum) haline getirilecektir.
- **Configuration**: `.env.test` dosyası aracılığıyla test ortamı ayarları yönetilecektir.

## 3. Öncelikli Test Alanları

### 3.1. Yardımcı Sınıflar (Unit Tests)
- `App\Helpers\TimeHelper`: Zaman hesaplamaları, aşım kontrolleri, çakışma mantığı.
- `App\Services\TimelineService`: Zaman çizelgesi düzleştirme (flattening), dilim birleştirme.
- `App\Validators\ScheduleItemValidator`: Veri giriş validasyonları.

### 3.2. Servis Katmanı (Integration Tests)
- `App\Services\ScheduleService`:
    - `saveScheduleItems`: Başarılı kayıt, çakışma durumunda hata yönetimi.
    - `processItemDeletion`: Parçalı silme ve zaman kayması kontrolleri.
    - `mergeGroupItems`: Gruplu derslerin doğru birleştirilmesi.
- `App\Services\AvailabilityService`: Müsait hoca ve derslik sorgularının doğruluğu.
- `App\Services\ConflictService`: Karmaşık çakışma kurallarının (Grup dersi, hoca çakışması vb.) kontrolü.

## 4. Uygulama Adımları

1. **Hazırlık**:
    - `composer require --dev phpunit/phpunit` komutu ile PHPUnit kurulur.
    - `phpunit.xml` konfigürasyon dosyası oluşturulur.
    - `tests/` dizini altında `Unit` ve `Integration` klasörleri oluşturulur.

2. **Temel Testlerin Yazılması**:
    - İlk olarak `TimeHelperTest.php` yazılarak altyapı doğrulanır.
    - BaseTestCase oluşturularak DB transaction yönetimi ve setup/teardown işlemleri standardize edilir.

3. **Otomatikleştirme**:
    - Git push öncesi testlerin çalışması sağlanır (İsteğe bağlı).

## 5. Doğrulama

Testler çalıştırıldığında:
- Tüm Unit testler %100 başarıyla geçmelidir.
- Kod kapsama (Code Coverage) oranı kritik servisler için %80 üzerine çıkarılmalıdır.
