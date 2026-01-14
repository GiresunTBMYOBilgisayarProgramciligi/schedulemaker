[🏠 Ana Sayfa](../../../README.md) / [App](../../README.md) / [Controllers](../README.md) / [LessonController](README.md) / **combineLesson**

---
# LessonController::combineLesson()

İki veya daha fazla dersi birbirine bağlayarak "Bağlı Dersler" (Connected Lessons) yapısını oluşturur.

## İşleyiş

1.  **İlişki Kurulumu**: Seçilen derslerden biri "Ana Ders" (Parent) olarak belirlenir (veya mevcut ana ders korunur), diğerleri ona `parent_lesson_id` ile bağlanır.
2.  **İlk Senkronizasyon**:
    *   Bağlanan derslerin (Child) mevcut tüm özel programları (`owner_type = 'lesson'`) ve bölüm programları (`owner_type = 'program'`) temizlenir.
    *   Ana dersin mevcut programı, tüm bağlı derslerin programlarına kopyalanır.
3.  **Veri Tutarlılığı**: Bu işlemden sonra, ana ders üzerinde yapılan her program değişikliği bağlı derslere otomatik olarak yansır.

## Teknik Not
Bu metod sadece derslerin mantıksal bağını kurar ve mevcut veriyi eşitler. Dinamik senkronizasyon (ekleme/silme anında) `ScheduleController` tarafından yönetilir.
