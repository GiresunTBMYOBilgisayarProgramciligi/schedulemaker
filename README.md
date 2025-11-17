# Ders Programı Hazırlama Projesi

## Gereksinimler

- PHP 8.0+
## Kod Dökümantasyonu

### Kurulum
Apache yapılandırmasını yaptıktan sonra apache rewrite modu aktif edilmeli
```bash

a2enmod rewrite

```
Composer ve nmp paketleri yüklenmeli
```bash

composer install
cd Public/assets/
npm install
```
.env dosyası oluşturulup düzenlenmeli 
```bash

cd App
cp .env.example .env
```
Veri tabanı oluşturulup setup.sql bu veritabanında çalıştırılmalı
```mysql
Create database schedule CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'kullanici_adi'@'localhost' IDENTIFIED BY 'parola';
GRANT ALL PRIVILEGES ON schedule.* TO 'kullanici_adi'@'localhost';
```
## Notlar
Hata mesajları Router sınıflarında toplanır. Daha alt katmanlarda hata mesajı gösterilmez. 