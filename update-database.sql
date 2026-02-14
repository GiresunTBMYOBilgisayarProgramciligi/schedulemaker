#v0.1.3 -> v0.2.0

-- Feature Flag: Yeni Schedule Service
-- Settings tablosu yapısı: `key`, `value`, `type`, `group`

-- Yeni sistemi AKTİF ET
INSERT INTO
    settings (
        `key`,
        `value`,
        `type`,
        `group`
    )
VALUES (
        'use_new_schedule_service',
        '0',
        'boolean',
        'feature_flags'
    )
ON DUPLICATE KEY UPDATE
    `value` = '1';

-- Mevcut durumu kontrol et
SELECT `key`, `value`, `group`
FROM settings
WHERE
    `group` = 'feature_flags';

-- Sorun olursa ESKİ SİSTEME DÖN
-- UPDATE settings SET `value` = '0' WHERE `key` = 'use_new_schedule_service' AND `group` = 'feature_flags';