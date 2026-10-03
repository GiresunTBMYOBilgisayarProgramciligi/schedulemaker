# mysql -u kullanici_adi vt_adi < update-database.sql
#v0.3.4 -> v0.3.5

-- ============================================================================
-- Veritabanı loglama iptali: logs tablosunun kaldırılması (v0.3.5)
-- ============================================================================
DROP TABLE IF EXISTS `logs`;