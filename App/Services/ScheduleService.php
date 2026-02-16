<?php

namespace App\Services;

use App\DTOs\DeleteScheduleResult;
use App\DTOs\SaveScheduleResult;
use App\DTOs\ScheduleItemData;
use App\Exceptions\ValidationException;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Repositories\ScheduleItemRepository;
use App\Repositories\ScheduleRepository;
use App\Validators\ScheduleItemValidator;
use Exception;

use function App\Helpers\getSettingValue;

/**
 * Schedule Service
 * 
 * Schedule ve ScheduleItem işlemleri için iş mantığı katmanı
 * 
 * v1.0 - Basit versiyon:
 * - saveScheduleItems: Temel kaydetme işlemi
 * - Validation
 * - Repository kullanımı
 * 
 * TODO (v2.0):
 * - Conflict resolution
 * - Group item processing
 * - Child lesson handling
 * - Exam assignment
 */
class ScheduleService extends BaseService
{
    private ScheduleRepository $scheduleRepo;
    private ScheduleItemRepository $itemRepo;
    private ScheduleItemValidator $validator;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->itemRepo = new ScheduleItemRepository();
        $this->validator = new ScheduleItemValidator();
    }

    /**
     * Schedule item'larını kaydeder (v1.0 - Basit versiyon)
     * 
     * @param array $itemsData Ham item verileri (array of arrays)
     * @return SaveScheduleResult
     * @throws ValidationException
     * @throws Exception
     */
    public function saveScheduleItems(array $itemsData): SaveScheduleResult
    {
        $this->logger->debug("ScheduleService::saveScheduleItems START", $this->logContext(['count' => count($itemsData)]));

        // 1. Validation - batch olarak tüm item'ları kontrol et
        $validationResult = $this->validator->validateBatch($itemsData);
        if (!$validationResult->isValid) {
            throw new ValidationException(
                'Schedule item validation failed',
                $validationResult->errors,
                ['item_count' => count($itemsData), 'itemsData' => $itemsData]
            );
        }

        // 2. Transaction başlat
        $this->beginTransaction();

        $createdIds = [];
        $affectedLessonIds = [];

        try {
            foreach ($itemsData as $index => $itemData) {
                $this->logger->debug("Processing item #$index", $this->logContext(['itemData' => $itemData]));

                // DTO'ya dönüştür
                $dto = ScheduleItemData::fromArray($itemData);

                // İlgili bilgileri al
                $schedule = $this->scheduleRepo->find($dto->scheduleId);
                if (!$schedule) {
                    throw new Exception("Schedule not found: {$dto->scheduleId}");
                }

                /** @var Schedule $schedule */

                $isDummy = $dto->isDummy();
                $lesson = null;

                // Dummy olmayan itemlar için lesson bilgisini al (child lessons ile birlikte)
                if (!$isDummy) {
                    $lessonId = null;

                    // data bir array of arrays, ilk elemanı kontrol et
                    if (!empty($dto->data) && isset($dto->data[0]['lesson_id'])) {
                        $lessonId = $dto->data[0]['lesson_id'];
                    }

                    if ($lessonId) {
                        $lesson = (new Lesson())->where(['id' => $lessonId])->with(['childLessons'])->first();
                        if (!$lesson) {
                            throw new Exception("Lesson not found: {$lessonId}");
                        }
                    }
                }

                // Basit çakışma kontrolü (v1.0)
                $conflicts = $this->itemRepo->findConflicting(
                    $dto->scheduleId,
                    $dto->dayIndex,
                    $dto->weekIndex,
                    $dto->startTime,
                    $dto->endTime
                );

                if (!empty($conflicts)) {
                    // V1: Sadece logluyoruz, çözümleme v2'de
                    $this->logger->warning("Conflict detected for item #$index", $this->logContext([
                        'conflicts' => count($conflicts),
                        'schedule_id' => $dto->scheduleId
                    ]));
                    // TODO v2: Conflict resolution (preferred handling, error throwing)
                }

                // MULTI-SCHEDULE KAYDETME: Tüm ilgili schedule'lara kaydet
                $itemIds = $this->saveToMultipleSchedules($dto, $lesson, $schedule);
                $createdIds = array_merge($createdIds, $itemIds);

                $this->logger->debug("Item #{$index} saved to " . count($itemIds) . " schedules", $this->logContext([
                    'item_ids' => $itemIds
                ]));

                // Etkilenen ders ID'lerini kaydet
                if (!$isDummy && $lesson) {
                    $affectedLessonIds[] = $lesson->id;
                }
            }

            // Ders saati kontrolü (basit versiyon)
            if (!empty($affectedLessonIds)) {
                $this->checkLessonHourLimits(array_unique($affectedLessonIds), $schedule->type);
            }

            // Commit
            $this->commit();

            $this->logger->info("Schedule items saved successfully", $this->logContext([
                'created_count' => count($createdIds),
                'schedule_id' => $itemsData[0]['schedule_id'] ?? null
            ]));

            return SaveScheduleResult::success($createdIds, count($itemsData));

        } catch (Exception $e) {
            $this->rollback();
            $this->logger->error("Failed to save schedule items: " . $e->getMessage(), $this->logContext([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            throw $e;
        }
    }

    /**
     * Ders saati limitlerini kontrol eder
     * 
     * Normal dersler için: Aşım varsa exception fırlatır
     * Child lessons için: Aşım varsa fazla saatleri otomatik temizler
     * 
     * @param array $lessonIds
     * @param string $scheduleType
     * @throws Exception
     */
    private function checkLessonHourLimits(array $lessonIds, string $scheduleType): void
    {
        foreach ($lessonIds as $lessonId) {
            $lesson = (new Lesson())->find($lessonId);
            if (!$lesson) {
                continue;
            }

            // IsScheduleComplete metodunu çalıştırarak remaining_size hesaplatıyoruz
            $lesson->IsScheduleComplete($scheduleType);

            if ($lesson->remaining_size < 0) {
                // Child lesson kontrolü
                if ($lesson->parent_id !== null) {
                    // Child lesson → Fazla saatleri otomatik temizle
                    $this->logger->info("Child lesson hour limit exceeded, cleaning up", $this->logContext([
                        'lesson_id' => $lesson->id,
                        'lesson_name' => $lesson->getFullName(),
                        'parent_id' => $lesson->parent_id,
                        'excess_hours' => abs($lesson->remaining_size)
                    ]));

                    $this->cleanupExcessChildHours($lesson, $scheduleType);
                } else {
                    // Normal lesson → Exception fırlat (mevcut davranış)
                    $errorMsg = ($scheduleType === 'lesson')
                        ? "{$lesson->getFullName()} dersinin toplam saati aşılıyor. (Fazla: " . abs($lesson->remaining_size) . " saat)"
                        : "{$lesson->getFullName()} dersinin sınav mevcudu aşılıyor. (Fazla: " . abs($lesson->remaining_size) . " kişi)";

                    throw new Exception($errorMsg);
                }
            }
        }
    }

    /**
     * Child lesson'ın fazla olan schedule item'larını siler veya kısaltır
     * 
     * Parent ders ile child ders saati farklı olduğunda, child'a parent kadar
     * saat eklenebilir. Bu durumda child'ın toplam saati aşılır. Bu metod,
     * child'ın fazla olan saatlerini son eklenenlerden başlayarak siler veya kısaltır.
     * 
     * **Slot-Based Yaklaşım:**
     * - Duration: 50dk, Break: 10dk → 1 slot = 60dk
     * - Item'lar slot cinsinden işlenir
     * - Eğer son item fazlaysa → Item kısaltılır (end_time güncellenir)
     * - Tam slot silme gerekiyorsa → Item silinir
     * 
     * **Örnek:**
     * - Parent: 4 saat/hafta
     * - Child: 2 saat/hafta
     * - Parent'a 4 saatlik item eklendi → Child'a da 4 saat eklendi
     * - Child fazla: 2 saat (2 slot)
     * → Son eklenen item'ları 2 slot azalt
     * 
     * @param Lesson $childLesson Child lesson entity'si
     * @param string $scheduleType Schedule tipi ('lesson', 'midterm-exam', etc.)
     * @return void
     */
    private function cleanupExcessChildHours(Lesson $childLesson, string $scheduleType): void
    {
        $excessSlots = abs($childLesson->remaining_size);

        $this->logger->warning(
            "Child lesson hour limit exceeded, cleaning up excess hours",
            $this->logContext([
                'lesson_id' => $childLesson->id,
                'lesson_name' => $childLesson->getFullName(),
                'excess_slots' => $excessSlots,
                'schedule_type' => $scheduleType
            ])
        );

        // Bu child lesson'a ait lesson schedule'ları bul
        // (Sadece owner_type='lesson' schedule'ları - program schedule'lara dokunma)
        $childSchedules = $this->scheduleRepo->findBy([
            'owner_type' => 'lesson',
            'owner_id' => $childLesson->id,
            'type' => $scheduleType
        ]);

        if (empty($childSchedules)) {
            $this->logger->error("No lesson schedules found for child lesson", $this->logContext([
                'lesson_id' => $childLesson->id
            ]));
            return;
        }

        // Sistem ayarlarından slot bilgilerini al
        $group = ($scheduleType === 'lesson') ? 'lesson' : 'exam';
        $duration = (int) getSettingValue('duration', $group, 50);
        $breakTime = (int) getSettingValue('break', $group, 10);
        $slotSize = $duration + $breakTime; // Dakika cinsinden

        // Her schedule'dan fazla slot'ları sil/kısalt
        $totalDeleted = 0;
        $totalShortened = 0;
        $slotsToRemove = $excessSlots;

        foreach ($childSchedules as $schedule) {
            if ($slotsToRemove <= 0) {
                break;
            }

            // En son eklenen item'ları bul (id DESC)
            $items = (new ScheduleItem())
                ->where(['schedule_id' => $schedule->id])
                ->orderBy('id', 'DESC')
                ->get()
                ->all();

            foreach ($items as $item) {
                if ($slotsToRemove <= 0) {
                    break;
                }

                // Item'ın kaç slot olduğunu hesapla
                $itemSlots = $this->calculateItemSlots($item, $slotSize);

                if ($itemSlots <= $slotsToRemove) {
                    // Tüm item'ı sil
                    $item->delete();
                    $totalDeleted++;
                    $slotsToRemove -= $itemSlots;

                    $this->logger->debug("Deleted excess child lesson item", $this->logContext([
                        'item_id' => $item->id,
                        'item_slots' => $itemSlots,
                        'remaining_to_remove' => $slotsToRemove
                    ]));
                } else {
                    // Item'ı kısalt (end_time güncelle)
                    $newSlots = $itemSlots - $slotsToRemove;
                    $newEndTime = $this->calculateNewEndTime($item->start_time, $newSlots, $slotSize);

                    $item->end_time = $newEndTime;
                    $item->update();
                    $totalShortened++;

                    $this->logger->debug("Shortened excess child lesson item", $this->logContext([
                        'item_id' => $item->id,
                        'old_slots' => $itemSlots,
                        'new_slots' => $newSlots,
                        'old_end_time' => $item->end_time,
                        'new_end_time' => $newEndTime
                    ]));

                    $slotsToRemove = 0; // İşlem tamamlandı
                }
            }
        }

        $this->logger->info("Child lesson excess hours cleaned up", $this->logContext([
            'lesson_id' => $childLesson->id,
            'deleted_items' => $totalDeleted,
            'shortened_items' => $totalShortened,
            'excess_slots' => $excessSlots
        ]));
    }

    /**
     * Schedule item'ın kaç slot olduğunu hesaplar
     * 
     * **Slot-Based Hesaplama:**
     * - 1 slot = duration + break (örn: 50 + 10 = 60 dk)
     * - 08:00-08:50 → 1 slot
     * - 08:00-09:50 → 2 slot
     * 
     * @param ScheduleItem $item
     * @param int $slotSizeMinutes Slot boyutu (dakika)
     * @return int Slot sayısı
     */
    private function calculateItemSlots(ScheduleItem $item, int $slotSizeMinutes): int
    {
        $start = strtotime($item->start_time);
        $end = strtotime($item->end_time);
        $totalMinutes = ($end - $start) / 60;

        // Slot sayısını hesapla (yukarı yuvarla)
        return (int) ceil($totalMinutes / $slotSizeMinutes);
    }

    /**
     * Yeni end_time hesaplar (slot bazlı)
     * 
     * @param string $startTime Başlangıç saati (HH:MM)
     * @param int $slots Slot sayısı
     * @param int $slotSizeMinutes Slot boyutu (dakika)
     * @return string Yeni end_time (HH:MM)
     */
    private function calculateNewEndTime(string $startTime, int $slots, int $slotSizeMinutes): string
    {
        $start = strtotime($startTime);

        // Son slot'un ders bitiş zamanı (break dahil değil)
        $duration = (int) getSettingValue('duration', 'lesson', 50);
        $breakTime = (int) getSettingValue('break', 'lesson', 10);

        // Slot sayısı kadar iler - son slot'ta break yok
        $totalMinutes = ($slots - 1) * $slotSizeMinutes + $duration;
        $end = $start + ($totalMinutes * 60);

        return date('H:i', $end);
    }

    // ==================== MULTI-SCHEDULE KAYDETME ====================

    /**
     * Schedule item için ilgili tüm owner'ları (sahip programlar/kullanıcılar) belirler
     * 
     * Bu metod bir schedule item'ın hangi programlara, derslere, kullanıcılara ve dersliklere
     * ait olduğunu belirler. Her owner için ayrı bir schedule item oluşturulacaktır.
     * 
     * **Dummy Items (Preferred/Unavailable):**
     * Sadece ilgili target schedule'a kaydedilir (örn: bir hocanın tercih ettiği slot sadece o hocanın programına eklenir)
     * 
     * **Normal Ders:**
     * - Program schedule (dersin bağlı olduğu program)
     * - Lesson schedule (dersin kendisi)
     * - User schedule (öğretim üyesi)
     * - Classroom schedule (derslik, UZEM değilse)
     * 
     * **Sınav (Exam Assignments):**
     * Sınav atamaları ($dto->detail['assignments']) sınavda görevli gözlemciler ve kullanılacak derslikleri içerir.
     * Örnek: [{'observer_id': 146, 'classroom_id': 3}, {'observer_id': 152, 'classroom_id': 5}]
     * Her gözlemci ve derslik için ayrı schedule item oluşturulur.
     * 
     * @param ScheduleItemData $dto Schedule item verisi
     * @param Lesson|null $lesson İlgili ders entity'si (dummy items için null olabilir)
     * @return array Owner listesi, her biri ['type' => 'user|program|lesson|classroom', 'id' => int] formatında
     * @throws Exception Dummy olmayan item için lesson yoksa
     */
    private function determineOwners(ScheduleItemData $dto, ?Lesson $lesson): array
    {
        $owners = [];

        // Dummy items (preferred/unavailable) → Sadece target schedule
        if ($dto->isDummy()) {
            /** @var Schedule $targetSchedule */
            $targetSchedule = $this->scheduleRepo->find($dto->scheduleId);
            if ($targetSchedule) {
                return [
                    [
                        'type' => $targetSchedule->owner_type,
                        'id' => $targetSchedule->owner_id,
                        'semester_no' => $targetSchedule->semester ?? null
                    ]
                ];
            }
            return [];
        }

        // Normal ders/sınav
        if (!$lesson) {
            throw new Exception("Lesson required for non-dummy items");
        }

        // SINAV KONTROLÜ: detail->assignments varsa bu bir sınav programı demektir
        // assignments: Sınavda görevlendirilmiş gözlemciler ve kullanılacak derslikler
        // Örnek: [{'observer_id': 146, 'classroom_id': 3}, {'observer_id': 152, 'classroom_id': 5}]
        $examAssignments = $dto->detail['assignments'] ?? null;

        if ($examAssignments) {
            // SINAV - Çoklu gözlemci/derslik atamaları
            $owners = $this->determineExamOwners($lesson, $examAssignments);
        } else {
            // NORMAL DERS - Tek öğretim üyesi, tek derslik
            $owners = $this->determineLessonOwners($dto, $lesson);
        }

        // Child lessons dahil et (bağlı alt dersler varsa)
        if (!empty($lesson->childLessons)) {
            $childOwners = $this->determineChildLessonOwners($lesson->childLessons);
            $owners = array_merge($owners, $childOwners);
        }

        return $owners;
    }

    /**
     * Normal ders için owner listesini belirler
     * 
     * Bir normal ders için 4 owner olabilir:
     * 1. Program - Dersin bağlı olduğu program
     * 2. Lesson - Dersin kendisi
     * 3. User - Dersi veren öğretim üyesi
     * 4. Classroom - Dersin yapıldığı derslik (UZEM değilse)
     * 
     * **UZEM Kuralı:** 
     * classroom_type = 3 olan dersler UZEM (Uzaktan Eğitim) dersidir.
     * Bu dersler fiziksel derslik kullanmadığı için classroom schedule oluşturulmaz.
     * 
     * @param ScheduleItemData $dto Schedule item verisi, içinde lecturer_id ve classroom_id var
     * @param Lesson $lesson Ders entity'si, program_id ve classroom_type bilgilerini içerir
     * @return array Owner listesi [['type' => 'user|program|lesson|classroom', 'id' => int], ...]
     */
    private function determineLessonOwners(ScheduleItemData $dto, Lesson $lesson): array
    {
        // data bir array of arrays: [{lesson_id: 489, lecturer_id: 147, classroom_id: 1}]
        $lecturerId = null;
        $classroomId = null;

        if (!empty($dto->data) && isset($dto->data[0])) {
            $lecturerId = $dto->data[0]['lecturer_id'] ?? null;
            $classroomId = $dto->data[0]['classroom_id'] ?? null;
        }

        $owners = [];

        // Lesson owner (her zaman var)
        $owners[] = ['type' => 'lesson', 'id' => $lesson->id];

        // Program owner (varsa)
        if ($lesson->program_id) {
            $owners[] = [
                'type' => 'program',
                'id' => $lesson->program_id,
                'semester_no' => $lesson->semester_no
            ];
        }

        // User owner (varsa)
        if ($lecturerId) {
            $owners[] = ['type' => 'user', 'id' => $lecturerId];
        }

        // Classroom owner (UZEM değilse ve classroom varsa)
        // classroom_type: 1=Normal, 2=Lab, 3=UZEM
        if ($lesson->classroom_type != 3 && $classroomId) {
            $owners[] = ['type' => 'classroom', 'id' => $classroomId];
        }

        return $owners;
    }

    /**
     * Sınav için owner listesini belirler
     * 
     * Sınav programları normal derslerden farklıdır:
     * - Bir sınavda birden fazla gözlemci olabilir
     * - Birden fazla derslik kullanılabilir
     * - Her gözlemci ve derslik için ayrı schedule item oluşturulur
     * 
     * **Exam Assignments Formatı:**
     * ```php
     * [
     *   ['observer_id' => 146, 'classroom_id' => 3],  // Ahmet Hoca, A101'de
     *   ['observer_id' => 152, 'classroom_id' => 5]   // Mehmet Hoca, B202'de
     * ]
     * ```
     * 
     * @param Lesson $lesson Sınav dersi entity'si
     * @param array $examAssignments Gözlemci-derslik atamaları
     * @return array Owner listesi, her assignment için user ve classroom owner'ı içerir
     */
    private function determineExamOwners(Lesson $lesson, array $examAssignments): array
    {
        $owners = [
            ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
            ['type' => 'lesson', 'id' => $lesson->id]
        ];

        // Her sınav ataması için gözlemci ve derslik owner'ı ekle
        foreach ($examAssignments as $assignment) {
            $owners[] = ['type' => 'classroom', 'id' => $assignment['classroom_id']];
            $owners[] = ['type' => 'user', 'id' => $assignment['observer_id']];
        }

        return $owners;
    }

    /**
     * Bağlı alt dersler (child lessons) için owner listesini belirler
     * 
     * **Child Lesson Nedir?**
     * Bazı dersler başka derslere bağlıdır. Örneğin:
     * - "Veritabanı" dersi (parent) → Bilgisayar Programcılığı programına ait
     * - "Veritabanı-Lab" dersi (child) → Yönetim Bilişim Sistemleri programına ait
     * 
     * Parent ders programlandığında, child'ın da kendi programına eklenmesi gerekir.
     * 
     * **is_child Metadata:**
     * Child lesson owner'ları 'is_child' = true ve 'child_lesson_id' bilgisi taşır.
     * Bu sayede schedule item'da hangi child'a ait olduğu bilinir.
     * 
     * @param array $childLessons Child lesson entity'leri dizisi
     * @return array Owner listesi, her child için lesson ve (varsa) program owner'ı
     */
    private function determineChildLessonOwners(array $childLessons): array
    {
        $owners = [];

        foreach ($childLessons as $childLesson) {
            // Child lesson'un kendi schedule'ı
            $owners[] = [
                'type' => 'lesson',
                'id' => $childLesson->id,
                'is_child' => true,
                'child_lesson_id' => $childLesson->id,
                'lesson_hours' => $childLesson->hours  // Duration bilgisi (DB column: hours)
            ];

            // Child lesson'un programı varsa
            if ($childLesson->program_id) {
                $owners[] = [
                    'type' => 'program',
                    'id' => $childLesson->program_id,
                    'semester_no' => $childLesson->semester_no,
                    'is_child' => true,
                    'child_lesson_id' => $childLesson->id,
                    'lesson_hours' => $childLesson->hours  // Duration bilgisi (DB column: hours)
                ];
            }
        }

        return $owners;
    }

    /**
     * Belirtilen owner için schedule bulur, yoksa oluşturur
     * 
     * Schedule'lar akademik yıl, dönem ve tipe göre unique'tir:
     * - owner_type + owner_id + academic_year + semester + type → Unique constraint
     * 
     * **Örnek:**
     * - Ahmet Hoca (user_id=146)
     * - 2023-2024 Güz dönemi
     * - Ders programı (type='lesson')
     * → Bu kriterlere uyan schedule varsa kullan, yoksa oluştur
     * 
     * @param array $owner Owner bilgisi ['type' => 'user', 'id' => 146, 'semester_no' => 3]
     * @param string $academicYear Akademik yıl (örn: '2023-2024')
     * @param string $semester Dönem ('Güz', 'Bahar', 'Yaz')
     * @param string $type Schedule tipi ('lesson', 'midterm-exam', 'final-exam', 'makeup-exam')
     * @return Schedule Bulunan veya yeni oluşturulan schedule
     */
    private function findOrCreateSchedule(
        array $owner,
        string $academicYear,
        string $semester,
        string $type
    ): Schedule {
        // Önce varolan schedule'ı ara
        $existing = $this->scheduleRepo->findByOwnerAndPeriod(
            $owner['type'],
            $owner['id'],
            $academicYear,
            $semester,
            $type,
            $owner['semester_no'] ?? null
        );

        if ($existing) {
            return $existing;
        }

        // Yoksa yeni schedule oluştur
        $schedule = new Schedule();
        $schedule->owner_type = $owner['type'];
        $schedule->owner_id = $owner['id'];
        $schedule->academic_year = $academicYear;
        $schedule->semester = $semester;
        $schedule->type = $type;

        // Program schedule'ları için semester_no gerekli
        if (isset($owner['semester_no'])) {
            $schedule->semester_no = $owner['semester_no'];
        }

        $schedule->create();

        return $schedule;
    }

    /**
     * Schedule item'ı tüm ilgili owner'ların schedule'larına kaydeder
     * 
     * **İşlem Akışı:**
     * 1. Owner'ları belirle (determineOwners)
     * 2. Her owner için:
     *    a. Schedule bul veya oluştur (findOrCreateSchedule)
     *    b. Schedule item oluştur ve kaydet
     * 3. Oluşturulan tüm item ID'lerini döndür
     * 
     * **Örnek:**
     * Input: Pazartesi 09:00-10:50, Algorithm dersi, Ahmet Hoca, A101
     * Output: [45, 46, 47, 48] → 4 ayrı schedule item ID'si
     * - ID 45: Program schedule item (Bilgisayar Programcılığı)
     * - ID 46: Lesson schedule item (Algorithm)
     * - ID 47: User schedule item (Ahmet Hoca)
     * - ID 48: Classroom schedule item (A101)
     * 
     * **Child Lesson Metadata:**
     * Child lesson'lar için oluşturulan item'larda detail['child_lesson_id'] bilgisi eklenir.
     * Bu sayede hangi child'a ait olduğu bilinir.
     * 
     * @param ScheduleItemData $dto Schedule item verisi
     * @param Lesson|null $lesson İlgili ders (dummy items için null)
     * @param Schedule $sourceSchedule Kaynak schedule (akademik yıl/dönem bilgisi için)
     * @return array Oluşturulan schedule item ID'leri
     */
    private function saveToMultipleSchedules(
        ScheduleItemData $dto,
        ?Lesson $lesson,
        Schedule $sourceSchedule
    ): array {
        $owners = $this->determineOwners($dto, $lesson);
        $createdIds = [];

        // DEBUG: Owner listesini logla
        $this->logger->debug('saveToMultipleSchedules: Owner list determined', [
            'username' => $this->currentUser->username ?? 'System',
            'user_id' => $this->currentUser->id ?? null,
            'class' => self::class,
            'method' => __FUNCTION__,
            'owner_count' => count($owners),
            'owners' => array_map(function ($o) {
                return [
                    'type' => $o['type'],
                    'id' => $o['id'],
                    'is_child' => $o['is_child'] ?? false,
                    'child_lesson_id' => $o['child_lesson_id'] ?? null
                ];
            }, $owners),
            'lesson_id' => $lesson ? $lesson->id : null,
            'lesson_name' => $lesson ? $lesson->name : null
        ]);

        // Ana ders için saat kontrolü (Hata fırlatılacaksa burada fırlatılır)
        if ($lesson && !$dto->isDummy()) {
            $lesson->IsScheduleComplete($sourceSchedule->type);

            // Eklenecek slot sayısını hesapla
            $lessonDuration = (int) getSettingValue('duration', 'lesson', 50);
            $breakTime = (int) getSettingValue('break', 'lesson', 10);
            $slotSize = $lessonDuration + $breakTime;

            $addedMinutes = $this->getItemDurationMinutes($dto->startTime, $dto->endTime);
            $addedSlots = round($addedMinutes / $slotSize);

            // Eğer ana dersin saati aşılıyorsa hata fırlat
            if ($lesson->remaining_size < $addedSlots) {
                $errorMsg = ($sourceSchedule->type === 'lesson')
                    ? "{$lesson->getFullName()} dersinin toplam saati aşılıyor. (Kalan: {$lesson->remaining_size} saat, Eklenmek istenen: {$addedSlots} saat)"
                    : "{$lesson->getFullName()} dersinin sınav mevcudu aşılıyor. (Kalan: {$lesson->remaining_size}, Eklenmek istenen: {$addedSlots})";

                throw new \Exception($errorMsg);
            }
        }

        // Child lesson'lar için transaction içi takip (bu loop içinde eklenenler)
        $childLessonHoursAdded = [];

        foreach ($owners as $owner) {
            // Owner için schedule bul/oluştur
            /** @var Schedule $targetSchedule */
            $targetSchedule = $this->findOrCreateSchedule(
                $owner,
                $sourceSchedule->academic_year,
                $sourceSchedule->semester,
                $sourceSchedule->type
            );

            // Item oluştur (kopya)
            $item = new ScheduleItem();
            $item->schedule_id = $targetSchedule->id;
            $item->day_index = $dto->dayIndex;
            $item->week_index = $dto->weekIndex;
            $item->start_time = $dto->startTime;
            $item->end_time = $dto->endTime;
            $item->status = $dto->status;

            // Child lesson kontrolü
            if (isset($owner['is_child']) && $owner['is_child']) {
                $childLessonId = $owner['child_lesson_id'];

                // Child lesson verisini çek
                $childLesson = (new Lesson())->find($childLessonId);
                if (!$childLesson) {
                    continue;
                }

                $childLesson->IsScheduleComplete($sourceSchedule->type);

                // Bu loop içinde daha önce bu child için ne kadar eklendi?
                $alreadyAddedSlots = $childLessonHoursAdded[$childLessonId] ?? 0;
                $currentRemaining = $childLesson->remaining_size - $alreadyAddedSlots;

                if ($currentRemaining <= 0) {
                    $this->logger->debug("Child lesson already full, skipping schedule #{$targetSchedule->id}", [
                        'lesson_id' => $childLessonId,
                        'lesson_name' => $childLesson->getFullName()
                    ]);
                    continue;
                }

                // Eklenecek slot sayısı
                $lessonDuration = (int) getSettingValue('duration', 'lesson', 50);
                $breakTime = (int) getSettingValue('break', 'lesson', 10);
                $slotSize = $lessonDuration + $breakTime;

                $parentMinutes = $this->getItemDurationMinutes($dto->startTime, $dto->endTime);
                $parentSlots = round($parentMinutes / $slotSize);

                // Eğer child'a sığmıyorsa kısalt
                $slotsToAdd = min($parentSlots, $currentRemaining);
                if ($slotsToAdd < $parentSlots) {
                    $item->end_time = $this->calculateEndTime($dto->startTime, $slotsToAdd);
                    $this->logger->warning("Child lesson partially full, shortening item duration", [
                        'lesson_id' => $childLessonId,
                        'original_slots' => $parentSlots,
                        'added_slots' => $slotsToAdd
                    ]);
                }

                // Tracking güncelle
                $childLessonHoursAdded[$childLessonId] = ($childLessonHoursAdded[$childLessonId] ?? 0) + $slotsToAdd;

                // Data güncelle (child lesson id ile)
                $item->data = array_map(function ($d) use ($childLessonId) {
                    $childData = $d;
                    $childData['lesson_id'] = $childLessonId;
                    return $childData;
                }, $dto->data);

                if (!is_array($item->detail)) {
                    $item->detail = [];
                }
                $item->detail['child_lesson_id'] = $childLessonId;
            } else {
                // Normal owner
                $item->data = $dto->data;
                $item->detail = $dto->detail;
            }

            // Kaydet
            $item->create();
            $createdIds[] = $item->id;
        }

        return $createdIds;
    }

    // ==================== DELETE OPERATIONS ====================

    /**
     * Mevcut item'dan owner'ları belirler (sibling bulma için)
     * 
     * `determineOwners()` ile benzer mantık ama mevcut bir item'dan çalışır.
     * Sibling bulma işleminde hangi schedule'lara bakılacağını belirlemek için kullanılır.
     * 
     * **Önemli:** Child lesson owner'ları da dahil edilir!
     * 
     * @param ScheduleItem $item Schedule item
     * @param array $lessonIds İlgili ders ID'leri
     * @return array Owner listesi [['type' => 'user', 'id' => 146], ...]
     */
    private function determineOwnersFromItem(ScheduleItem $item, array $lessonIds): array
    {
        $owners = [];

        // Item'ın slotData'sından bilgi çek
        $slotDatas = $item->getSlotDatas();

        foreach ($slotDatas as $slotData) {
            $lesson = $slotData->lesson;
            if (!$lesson) {
                continue;
            }

            // Sadece hedef lesson ID'ler arasındaysa işle
            if (!in_array((int) $lesson->id, $lessonIds)) {
                continue;
            }

            // Lesson ve program owner'ları
            $owners[] = ['type' => 'lesson', 'id' => $lesson->id, 'semester_no' => null];

            if ($lesson->program_id) {
                $owners[] = [
                    'type' => 'program',
                    'id' => $lesson->program_id,
                    'semester_no' => $lesson->semester_no
                ];
            }

            // Child lessons (önemli!)
            if (!empty($lesson->childLessons)) {
                foreach ($lesson->childLessons as $childLesson) {
                    $owners[] = [
                        'type' => 'lesson',
                        'id' => $childLesson->id,
                        'semester_no' => null
                    ];

                    if ($childLesson->program_id) {
                        $owners[] = [
                            'type' => 'program',
                            'id' => $childLesson->program_id,
                            'semester_no' => $childLesson->semester_no
                        ];
                    }
                }
            }

            // Parent lesson varsa onu ve kardeşlerini de ekle
            if ($lesson->parent_lesson_id) {
                $parent = (new Lesson())
                    ->where(['id' => $lesson->parent_lesson_id])
                    ->with(['childLessons'])
                    ->first();

                if ($parent) {
                    $owners[] = ['type' => 'lesson', 'id' => $parent->id, 'semester_no' => null];

                    if ($parent->program_id) {
                        $owners[] = [
                            'type' => 'program',
                            'id' => $parent->program_id,
                            'semester_no' => $parent->semester_no
                        ];
                    }

                    // Parent'ın diğer child'ları
                    foreach ($parent->childLessons as $sibling) {
                        if ($sibling->id !== $lesson->id) {
                            $owners[] = ['type' => 'lesson', 'id' => $sibling->id, 'semester_no' => null];

                            if ($sibling->program_id) {
                                $owners[] = [
                                    'type' => 'program',
                                    'id' => $sibling->program_id,
                                    'semester_no' => $sibling->semester_no
                                ];
                            }
                        }
                    }
                }
            }

            // Lecturer (User) owner
            if ($slotData->lecturer) {
                $owners[] = ['type' => 'user', 'id' => $slotData->lecturer->id, 'semester_no' => null];
            }

            // Classroom owner (UZEM değilse)
            if ($slotData->classroom && $lesson->classroom_type != 3) {
                $owners[] = ['type' => 'classroom', 'id' => $slotData->classroom->id, 'semester_no' => null];
            }
        }

        // Unique yap
        $uniqueOwners = [];
        foreach ($owners as $owner) {
            $key = $owner['type'] . '_' . $owner['id'] . '_' . ($owner['semester_no'] ?? 'null');
            $uniqueOwners[$key] = $owner;
        }

        return array_values($uniqueOwners);
    }

    /**
     * Sibling item'ları bulur (multi-schedule kaydetme ile eklenen kopyalar)
     * 
     * **Sibling Nedir?**
     * Aynı ders item'ının farklı schedule'lardaki kopyaları:
     * - Program schedule
     * - Lesson schedule
     * - User schedule
     * - Classroom schedule
     * - Child lesson'ların schedule'ları (!)
     * 
     * **Zaman Çakışması:**
     * Sadece baseItem ile çakışan item'lar sibling sayılır.
     * Aynı günde farklı saatlerdeki item'lar sibling değildir.
     * 
     * @param ScheduleItem $baseItem Kaynak item
     * @param array $lessonIds İlgili ders ID'leri
     * @return array ScheduleItem[]
     */
    private function findSiblingItems(ScheduleItem $baseItem, array $lessonIds): array
    {
        $siblingsKeyed = [$baseItem->id => $baseItem];

        /** @var Schedule $baseSchedule */
        $baseSchedule = $this->scheduleRepo->find($baseItem->schedule_id);
        if (!$baseSchedule) {
            return array_values($siblingsKeyed);
        }

        // Owner'ları belirle (determineOwners mantığı ile)
        $owners = $this->determineOwnersFromItem($baseItem, $lessonIds);

        // Her owner için ilgili schedule'ları bul
        foreach ($owners as $owner) {
            $scheduleFilters = [
                'semester' => $baseSchedule->semester,
                'academic_year' => $baseSchedule->academic_year,
                'type' => $baseSchedule->type,
                'owner_type' => $owner['type'],
                'owner_id' => $owner['id']
            ];

            if (isset($owner['semester_no'])) {
                $scheduleFilters['semester_no'] = $owner['semester_no'];
            }

            $schedules = (new Schedule())->get()->where($scheduleFilters)->all();

            foreach ($schedules as $schedule) {
                // İlgili schedule ve gün için item'ları getir
                $items = (new ScheduleItem())->get()->where([
                    'schedule_id' => $schedule->id,
                    'day_index' => $baseItem->day_index,
                    'week_index' => $baseItem->week_index
                ])->all();

                foreach ($items as $item) {
                    // Zaman çakışması kontrolü
                    if (
                        $this->checkTimeOverlap(
                            $baseItem->start_time,
                            $baseItem->end_time,
                            $item->start_time,
                            $item->end_time
                        )
                    ) {
                        if (!isset($siblingsKeyed[$item->id])) {
                            $siblingsKeyed[$item->id] = $item;
                        }
                    }
                }
            }
        }

        return array_values($siblingsKeyed);
    }

    /**
     * Zaman çakışması kontrolü
     * 
     * @param string $start1
     * @param string $end1
     * @param string $start2
     * @param string $end2
     * @return bool
     */
    private function checkTimeOverlap(
        string $start1,
        string $end1,
        string $start2,
        string $end2
    ): bool {
        $s1 = strtotime($start1);
        $e1 = strtotime($end1);
        $s2 = strtotime($start2);
        $e2 = strtotime($end2);

        return !($e1 <= $s2 || $s1 >= $e2);
    }

    /**
     * Schedule item'ları siler (multi-schedule aware)
     * 
     * @param array $itemsData Silinecek item'lar
     * @param bool $expandGroup Child lesson grubu genişletilsin mi?
     * @return DeleteScheduleResult
     * @throws Exception
     */
    public function deleteScheduleItems(
        array $itemsData,
        bool $expandGroup = true
    ): DeleteScheduleResult {
        $deletedIds = [];
        $createdItemIds = [];
        $processedSiblingIds = [];

        $isInitiator = !$this->db->inTransaction();
        if ($isInitiator) {
            $this->beginTransaction();
        }

        try {
            foreach ($itemsData as $itemData) {
                $id = (int) ($itemData['id'] ?? 0);
                if (!$id) {
                    continue;
                }

                if (in_array($id, $processedSiblingIds)) {
                    continue;
                }

                $scheduleItem = (new ScheduleItem())
                    ->where(['id' => $id])
                    ->with('schedule')
                    ->first();

                if (!$scheduleItem) {
                    continue;
                }

                $type = 'lesson';
                if ($scheduleItem->schedule && in_array($scheduleItem->schedule->type, ['midterm-exam', 'final-exam', 'makeup-exam'])) {
                    $type = 'exam';
                }

                $duration = (int) getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
                $break = (int) getSettingValue('break', $type, $type === 'exam' ? 0 : 10);

                $baseLessonIds = [];
                foreach ($scheduleItem->getSlotDatas() as $sd) {
                    if ($sd->lesson) {
                        $baseLessonIds[] = (int) $sd->lesson->id;
                    }
                }

                $siblings = $this->findSiblingItems($scheduleItem, $baseLessonIds);
                $siblingIds = array_map(fn($s) => (int) $s->id, $siblings);

                $rawIntervals = [];
                $targetLessonIds = [];

                foreach ($itemsData as $reqItem) {
                    if (in_array((int) $reqItem['id'], $siblingIds)) {
                        $rawIntervals[] = [
                            'start' => substr($reqItem['start_time'] ?? $scheduleItem->start_time, 0, 5),
                            'end' => substr($reqItem['end_time'] ?? $scheduleItem->end_time, 0, 5)
                        ];

                        if (!empty($reqItem['data'])) {
                            foreach ($reqItem['data'] as $d) {
                                if (isset($d['lesson_id'])) {
                                    $lId = (int) $d['lesson_id'];
                                    if (!in_array($lId, $targetLessonIds)) {
                                        $targetLessonIds[] = $lId;

                                        if ($expandGroup) {
                                            $lObj = (new Lesson())
                                                ->where(['id' => $lId])
                                                ->with(['childLessons', 'parentLesson'])
                                                ->first();

                                            if ($lObj) {
                                                if ($lObj->parent_lesson_id) {
                                                    if (!in_array((int) $lObj->parent_lesson_id, $targetLessonIds)) {
                                                        $targetLessonIds[] = (int) $lObj->parent_lesson_id;
                                                    }

                                                    $parentObj = (new Lesson())
                                                        ->where(['id' => $lObj->parent_lesson_id])
                                                        ->with(['childLessons'])
                                                        ->first();

                                                    if ($parentObj) {
                                                        foreach ($parentObj->childLessons as $cl) {
                                                            if (!in_array((int) $cl->id, $targetLessonIds)) {
                                                                $targetLessonIds[] = (int) $cl->id;
                                                            }
                                                        }
                                                    }
                                                } elseif (!empty($lObj->childLessons)) {
                                                    foreach ($lObj->childLessons as $cl) {
                                                        if (!in_array((int) $cl->id, $targetLessonIds)) {
                                                            $targetLessonIds[] = (int) $cl->id;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                usort($rawIntervals, fn($a, $b) => strcmp($a['start'], $b['start']));
                $mergedIntervals = [];

                foreach ($rawIntervals as $interval) {
                    if (empty($mergedIntervals)) {
                        $mergedIntervals[] = $interval;
                    } else {
                        $lastIdx = count($mergedIntervals) - 1;
                        $lastEnd = $mergedIntervals[$lastIdx]['end'];
                        $gapMinutes = (strtotime($interval['start']) - strtotime($lastEnd)) / 60;

                        if ($gapMinutes >= 0 && $gapMinutes <= $break) {
                            $mergedIntervals[$lastIdx]['end'] = max($mergedIntervals[$lastIdx]['end'], $interval['end']);
                        } else {
                            $mergedIntervals[] = $interval;
                        }
                    }
                }

                if (empty($mergedIntervals)) {
                    continue;
                }

                foreach ($siblings as $sibling) {
                    $sibling->delete();
                    $deletedIds[] = $sibling->id;
                }

                foreach ($siblings as $sibling) {
                    $result = $this->processItemDeletion(
                        $sibling,
                        $mergedIntervals,
                        $targetLessonIds,
                        $duration,
                        $break,
                        false
                    );

                    if (!empty($result['created'])) {
                        foreach ($result['created'] as $createdItem) {
                            $createdItemIds[] = $createdItem->id;
                        }
                    }
                }

                $processedSiblingIds = array_unique(array_merge($processedSiblingIds, $siblingIds));
            }

            if ($isInitiator) {
                $this->commit();
            }

            $this->logger->info(
                "Schedule item'lar silindi: " . count($deletedIds) . " silindi, " . count($createdItemIds) . " oluşturuldu",
                $this->logContext(['deletedIds' => $deletedIds, 'createdIds' => $createdItemIds])
            );

            return DeleteScheduleResult::success($deletedIds, $createdItemIds);

        } catch (Exception $e) {
            if ($isInitiator) {
                $this->rollback();
            }

            $this->logger->error(
                "Silme işlemi başarısız: " . $e->getMessage(),
                $this->logContext(['exception' => $e])
            );

            throw $e;
        }
    }

    /**
     * Item parçalama (flatten timeline logic)
     * 
     * **Flatten Timeline Yaklaşımı:**
     * 1. Item'ı slot bazlı parçalara ayır (duration + break)
     * 2. Silme aralıklarını uygula
     * 3. Break temizliği (yalnız kalan break'leri sil)
     * 4. Kalan parçaları birleştir
     * 
     * **Partial Delete:**
     * - Zaman bazlı: Belirli saatleri sil
     * - Ders bazlı: Group item'dan belirli dersleri çıkar
     * 
     * @param ScheduleItem $item Item
     * @param array $deleteIntervals Silme aralıkları [['start' => '09:00', 'end' => '10:00'], ...]
     * @param array $targetLessonIds Silinecek ders ID'leri (boşsa tümü)
     * @param int $duration Ders süresi (dakika)
     * @param int $break Teneffüs süresi (dakika)
     * @param bool $deleteOriginal Original item'ı sil mi?
     * @return array ['deleted' => bool, 'created' => ScheduleItem[]]
     */
    private function processItemDeletion(
        ScheduleItem $item,
        array $deleteIntervals,
        array $targetLessonIds = [],
        int $duration = 50,
        int $break = 10,
        bool $deleteOriginal = true
    ): array {
        $startStr = $item->getShortStartTime();
        $endStr = $item->getShortEndTime();

        // 1. Kritik noktaları topla (Zaman çizelgesini düzleştir)
        $points = [$startStr, $endStr];

        // İç slot sınırlarını ekle (Duration ve Break geçişleri)
        $current = strtotime($startStr);
        $endUnix = strtotime($endStr);

        while ($current < $endUnix) {
            // Ders sonu
            $current += ($duration * 60);

            if ($current <= $endUnix) {
                $pointStr = date("H:i", $current);
                if (!in_array($pointStr, $points)) {
                    $points[] = $pointStr;
                }

                // Teneffüs sonu
                if ($current < $endUnix) {
                    $current += ($break * 60);
                    $pointStr = date("H:i", $current);
                    if (!in_array($pointStr, $points)) {
                        $points[] = $pointStr;
                    }
                }
            }
        }

        // Silme aralığı sınırlarını ekle
        foreach ($deleteIntervals as $del) {
            $dStart = substr($del['start'], 0, 5);
            $dEnd = substr($del['end'], 0, 5);

            if ($dStart > $startStr && $dStart < $endStr) {
                $points[] = $dStart;
            }
            if ($dEnd > $startStr && $dEnd < $endStr) {
                $points[] = $dEnd;
            }
        }

        $points = array_unique($points);
        sort($points);

        $dataList = $item->data ?: [];

        // 2. Dilimler (segments) üzerinden geç
        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            if ($pStart >= $pEnd) {
                continue;
            }

            // Bu dilimin tipi (break vs lesson)
            $diff = (strtotime($pEnd) - strtotime($pStart)) / 60;
            $isBreak = ($diff == $break);

            // Bu dilim silinecek mi?
            $isDeleteZone = false;
            foreach ($deleteIntervals as $del) {
                if ($del['start'] <= $pStart && $del['end'] >= $pEnd) {
                    $isDeleteZone = true;
                    break;
                }
            }

            $currentData = $dataList;
            $shouldKeep = true;

            if ($isDeleteZone) {
                if (!empty($targetLessonIds)) {
                    // Sadece belirli dersleri çıkar (partial delete)
                    $currentData = array_values(array_filter($dataList, function ($l) use ($targetLessonIds) {
                        return !in_array((int) $l['lesson_id'], $targetLessonIds);
                    }));
                } else {
                    // Tüm item siliniyor
                    $currentData = [];
                }
            }

            // Dummy öğeler (Preferred/Unavailable) için data boştur
            $isSpecial = in_array($item->status, ['preferred', 'unavailable']);
            $wasPreferred = ($item->detail['preferred'] ?? false);

            if (empty($currentData)) {
                if ($isSpecial) {
                    $shouldKeep = !$isDeleteZone;
                } elseif ($wasPreferred && $isDeleteZone) {
                    // Üzerinde ders olan preferred alan siliniyorsa, alanı preferred olarak geri kazan
                    $shouldKeep = true;
                } else {
                    $shouldKeep = false;
                }
            }

            // Segment orijinal öğenin zaman aralığı içinde olmalı
            if ($pStart < $startStr || $pEnd > $endStr) {
                continue;
            }

            $segments[] = [
                'start' => $pStart,
                'end' => $pEnd,
                'data' => $currentData,
                'isBreak' => $isBreak,
                'shouldKeep' => $shouldKeep
            ];
        }

        // 3. Teneffüs Temizliği (Break Sanitization)
        // Bir teneffüs ancak hem öncesindeki hem sonrasındaki ders tutuluyorsa tutulur
        for ($i = 0; $i < count($segments); $i++) {
            if ($segments[$i]['isBreak']) {
                $prevKept = ($i > 0 && $segments[$i - 1]['shouldKeep']);
                $nextKept = ($i < count($segments) - 1 && $segments[$i + 1]['shouldKeep']);

                if (!$prevKept || !$nextKept) {
                    $segments[$i]['shouldKeep'] = false;
                    $segments[$i]['data'] = [];
                }
            }
        }

        // 4. Parçaları birleştir (merge contiguous segments with same data)
        $newSegments = [];
        foreach ($segments as $seg) {
            if (!$seg['shouldKeep']) {
                continue;
            }

            $lastIdx = count($newSegments) - 1;
            if (
                $lastIdx >= 0 &&
                $newSegments[$lastIdx]['end'] === $seg['start'] &&
                serialize($newSegments[$lastIdx]['data']) === serialize($seg['data'])
            ) {
                // Birleştir
                $newSegments[$lastIdx]['end'] = $seg['end'];
            } else {
                // Yeni segment
                $newSegments[] = [
                    'start' => $seg['start'],
                    'end' => $seg['end'],
                    'data' => $seg['data']
                ];
            }
        }

        // 5. Veritabanı güncelleme
        if ($deleteOriginal) {
            $item->delete();
        }

        $createdItems = [];
        if (!empty($newSegments)) {
            foreach ($newSegments as $seg) {
                $newItem = new ScheduleItem();
                $newItem->schedule_id = $item->schedule_id;
                $newItem->day_index = $item->day_index;
                $newItem->week_index = $item->week_index;
                $newItem->start_time = $seg['start'];
                $newItem->end_time = $seg['end'];

                // Status belirleme
                if (in_array($item->status, ['preferred', 'unavailable'])) {
                    $newItem->status = $item->status;
                } elseif ($item->detail['preferred'] ?? false) {
                    // Preferred alanda parça oluştuysa
                    if (empty($seg['data'])) {
                        $newItem->status = 'preferred';
                    } else {
                        // Hala ders varsa status belirlenir
                        $isGroup = false;
                        foreach ($seg['data'] as $d) {
                            $lessonId = $d['lesson_id'] ?? null;
                            if ($lessonId) {
                                $lesson = (new Lesson())->find($lessonId);
                                if ($lesson && $lesson->group_no > 0) {
                                    $isGroup = true;
                                    break;
                                }
                            }
                        }
                        $newItem->status = $isGroup ? 'group' : 'single';
                    }
                } else {
                    // Normal item
                    $isGroup = false;
                    foreach ($seg['data'] as $d) {
                        $lessonId = $d['lesson_id'] ?? null;
                        if ($lessonId) {
                            $lesson = (new Lesson())->find($lessonId);
                            if ($lesson && $lesson->group_no > 0) {
                                $isGroup = true;
                                break;
                            }
                        }
                    }
                    $newItem->status = $isGroup ? 'group' : 'single';
                }

                $newItem->data = $seg['data'];
                $newItem->detail = $item->detail;
                $newItem->create();
                $createdItems[] = $newItem;
            }
        }

        return ['deleted' => true, 'created' => $createdItems];
    }

    /**
     * Group item'ları merge et (flatten timeline ile)
     * 
     * **Flatten Timeline Yaklaşımı:**
     * 1. Çakışan tüm group item'ları topla
     * 2. Tüm başlangıç/bitiş noktalarını belirle
     * 3. Her aralık için çakışan item'ların data'larını merge et
     * 4. Duplicate lesson'ları temizle
     * 5. Bitişik ve aynı data'lı segmentleri birleştir
     * 6. Eski item'ları sil, yeni item'ları oluştur
     * 
     * **Örnek:**
     * ```
     * Item A: 09:00-10:00 [Ders 1, Ders 2]
     * Item B: 09:30-11:00 [Ders 3, Ders 4]
     * Yeni:   09:00-10:00 [Ders 5]
     * 
     * Sonuç:
     * - 09:00-09:30 → [Ders 1, Ders 2, Ders 5]
     * - 09:30-10:00 → [Ders 1, Ders 2, Ders 3, Ders 4, Ders 5]
     * - 10:00-11:00 → [Ders 3, Ders 4]
     * ```
     * 
     * @param int $scheduleId Schedule ID
     * @param int $dayIndex Gün index
     * @param int $weekIndex Hafta index
     * @param string $startTime Başlangıç saati (HH:MM)
     * @param string $endTime Bitiş saati (HH:MM)
     * @param array $newData Yeni item'ın data'sı [['lesson_id' => 1, ...], ...]
     * @param array|null $newDetail Yeni item'ın detail'i
     * @return array Created item IDs
     */
    public function mergeGroupItems(
        int $scheduleId,
        int $dayIndex,
        int $weekIndex,
        string $startTime,
        string $endTime,
        array $newData,
        ?array $newDetail = null
    ): array {
        // 1. İlgili günün tüm 'group' itemlerini çek
        $allDayItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $scheduleId,
            'day_index' => $dayIndex,
            'week_index' => $weekIndex,
            'status' => 'group'
        ])->all();

        // Sadece zaman çakışanları filtrele
        $involvedItems = array_filter($allDayItems, function ($item) use ($startTime, $endTime) {
            return $this->checkTimeOverlap(
                $startTime,
                $endTime,
                $item->getShortStartTime(),
                $item->getShortEndTime()
            );
        });

        // Eğer hiç çakışma yoksa direkt oluştur
        if (empty($involvedItems)) {
            $newItem = new ScheduleItem();
            $newItem->schedule_id = $scheduleId;
            $newItem->day_index = $dayIndex;
            $newItem->week_index = $weekIndex;
            $newItem->start_time = $startTime;
            $newItem->end_time = $endTime;
            $newItem->status = 'group';
            $newItem->data = $newData;
            $newItem->detail = $newDetail;
            $newItem->create();
            return [$newItem->id];
        }

        // 2. Zaman çizelgesini düzleştir (Flatten Timeline)
        // Tüm başlangıç ve bitiş noktalarını topla
        $startTime = substr($startTime, 0, 5);
        $endTime = substr($endTime, 0, 5);
        $points = [$startTime, $endTime];

        foreach ($involvedItems as $item) {
            $points[] = $item->getShortStartTime();
            $points[] = $item->getShortEndTime();
        }

        $points = array_unique($points);
        sort($points);

        // 3. Aralıkları yeniden oluştur ve merge et
        $pendingItems = [];

        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            // Aralık uzunluğu kontrolü
            if ($pStart >= $pEnd) {
                continue;
            }

            $mergedData = [];
            $mergedDetail = [];

            // Yeni veri bu aralığı kapsıyor mu?
            if ($startTime <= $pStart && $endTime >= $pEnd) {
                $mergedData = array_merge($mergedData, $newData);
                if ($newDetail) {
                    $mergedDetail = array_merge($mergedDetail, $newDetail);
                }
            }

            // Mevcut itemler bu aralığı kapsıyor mu?
            foreach ($involvedItems as $item) {
                if ($item->getShortStartTime() <= $pStart && $item->getShortEndTime() >= $pEnd) {
                    $itemData = $item->data;
                    if (is_array($itemData)) {
                        $mergedData = array_merge($mergedData, $itemData);
                    }

                    $itemDetail = $item->detail;
                    if (is_array($itemDetail)) {
                        $mergedDetail = array_merge($mergedDetail, $itemDetail);
                    }
                }
            }

            // Data varsa listeye ekle
            if (!empty($mergedData)) {
                // Duplicate lesson'ları temizle (lesson_id bazlı)
                $uniqueData = [];
                $seenLessonIds = [];

                foreach ($mergedData as $d) {
                    $lid = $d['lesson_id'] ?? null;
                    if ($lid && !in_array($lid, $seenLessonIds)) {
                        $seenLessonIds[] = $lid;
                        $uniqueData[] = $d;
                    } elseif (!$lid) {
                        $uniqueData[] = $d;
                    }
                }

                // Optimization: Eğer bir önceki item ile datalar ve detail aynı ise zaman aralığını uzat
                $lastIdx = count($pendingItems) - 1;
                if ($lastIdx >= 0) {
                    $lastItem = &$pendingItems[$lastIdx];
                    if (
                        $lastItem['end'] == $pStart &&
                        json_encode($lastItem['data']) === json_encode($uniqueData) &&
                        json_encode($lastItem['detail']) === json_encode($mergedDetail)
                    ) {
                        // Birleştir
                        $lastItem['end'] = $pEnd;
                        continue;
                    }
                }

                // Yeni segment ekle
                $pendingItems[] = [
                    'start' => $pStart,
                    'end' => $pEnd,
                    'data' => $uniqueData,
                    'detail' => $mergedDetail
                ];
            }
        }

        // 4. Veritabanı İşlemleri
        // Eski itemleri sil
        foreach ($involvedItems as $item) {
            $item->delete();
        }

        $createdGroupIds = [];
        // Yeni itemleri oluştur
        foreach ($pendingItems as $pItem) {
            $newItem = new ScheduleItem();
            $newItem->schedule_id = $scheduleId;
            $newItem->day_index = $dayIndex;
            $newItem->week_index = $weekIndex;
            $newItem->start_time = $pItem['start'];
            $newItem->end_time = $pItem['end'];
            $newItem->status = 'group';
            $newItem->data = $pItem['data'];
            $newItem->detail = !empty($pItem['detail']) ? $pItem['detail'] : null;
            $newItem->create();
            $createdGroupIds[] = $newItem->id;
        }

        return $createdGroupIds;
    }

    // ==================== CHILD LESSON HOUR CONTROL HELPERS ====================

    /**
     * Schedule'daki belirli bir lesson için mevcut scheduled hours'ı hesapla
     * 
     * @param int $scheduleId Schedule ID
     * @param int $lessonId Lesson ID
     * @return float Scheduled hours (saat cinsinden)
     */
    private function calculateScheduledHours(int $scheduleId, int $lessonId): float
    {
        $items = (new ScheduleItem())->get()->where([
            'schedule_id' => $scheduleId
        ])->all();

        $totalMinutes = 0;
        foreach ($items as $item) {
            // Check if this item belongs to this lesson
            $itemData = is_array($item->data) ? $item->data : json_decode($item->data, true);
            if (isset($itemData[0]['lesson_id']) && $itemData[0]['lesson_id'] == $lessonId) {
                $totalMinutes += $this->getItemDurationMinutes($item->start_time, $item->end_time);
            }
        }

        // Convert to hours
        return $totalMinutes / 60;
    }

    /**
     * İki zaman arasındaki duration'ı dakika cinsinden hesapla
     * 
     * @param string $startTime HH:MM format
     * @param string $endTime HH:MM format
     * @return int Duration in minutes
     */
    private function getItemDurationMinutes(string $startTime, string $endTime): int
    {
        $start = \DateTime::createFromFormat('H:i', $startTime);
        $end = \DateTime::createFromFormat('H:i', $endTime);

        if (!$start || !$end) {
            return 0;
        }

        return ($end->getTimestamp() - $start->getTimestamp()) / 60;
    }

    /**
     * Start time'dan itibaren N saat sonraki end time'ı hesapla
     * 
     * @param string $startTime HH:MM format
     * @param float $hours Hours to add
     * @return string End time in HH:MM format
     */
    private function calculateEndTime(string $startTime, float $hours): string
    {
        $start = new \DateTime($startTime);
        $minutes = (int) ($hours * 60);
        $end = clone $start;
        $end->modify("+{$minutes} minutes");
        return $end->format('H:i');
    }
}


