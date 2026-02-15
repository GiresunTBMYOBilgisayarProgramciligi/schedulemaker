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
                if (!$isDummy && isset($dto->data['lesson_id'])) {
                    $lesson = (new Lesson())->where(['id' => $dto->data['lesson_id']])->with(['childLessons'])->first();
                    if (!$lesson) {
                        throw new Exception("Lesson not found: {$dto->data['lesson_id']}");
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
        $lecturerId = $dto->data['lecturer_id'] ?? null;
        $classroomId = $dto->data['classroom_id'] ?? null;

        $owners = [
            ['type' => 'user', 'id' => $lecturerId],
            ['type' => 'program', 'id' => $lesson->program_id, 'semester_no' => $lesson->semester_no],
            ['type' => 'lesson', 'id' => $lesson->id]
        ];

        // UZEM dersleri için classroom schedule oluşturma
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
                'child_lesson_id' => $childLesson->id
            ];

            // Child lesson'un programı varsa
            if ($childLesson->program_id) {
                $owners[] = [
                    'type' => 'program',
                    'id' => $childLesson->program_id,
                    'semester_no' => $childLesson->semester_no,
                    'is_child' => true,
                    'child_lesson_id' => $childLesson->id
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

        foreach ($owners as $owner) {
            // Owner için schedule bul/oluştur
            $targetSchedule = $this->findOrCreateSchedule(
                $owner,
                $sourceSchedule->academic_year,
                $sourceSchedule->semester,
                $sourceSchedule->type
            );

            // Item oluştur
            $item = new ScheduleItem();
            $item->schedule_id = $targetSchedule->id;
            $item->day_index = $dto->dayIndex;
            $item->week_index = $dto->weekIndex;
            $item->start_time = $dto->startTime;
            $item->end_time = $dto->endTime;
            $item->status = $dto->status;
            $item->data = $dto->data;
            $item->detail = $dto->detail;

            // Child lesson metadata ekle
            if (isset($owner['is_child']) && $owner['is_child']) {
                if (!is_array($item->detail)) {
                    $item->detail = [];
                }
                $item->detail['child_lesson_id'] = $owner['child_lesson_id'];
            }

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
                'owner_id' => $owner['id'],
                'semester_no' => $owner['semester_no']
            ];

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
                $id = (int)($itemData['id'] ?? 0);
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

                $duration = (int)getSettingValue('duration', $type, $type === 'exam' ? 30 : 50);
                $break = (int)getSettingValue('break', $type, $type === 'exam' ? 0 : 10);

                $baseLessonIds = [];
                foreach ($scheduleItem->getSlotDatas() as $sd) {
                    if ($sd->lesson) {
                        $baseLessonIds[] = (int)$sd->lesson->id;
                    }
                }

                $siblings = $this->findSiblingItems($scheduleItem, $baseLessonIds);
                $siblingIds = array_map(fn($s) => (int)$s->id, $siblings);

                $rawIntervals = [];
                $targetLessonIds = [];

                foreach ($itemsData as $reqItem) {
                    if (in_array((int)$reqItem['id'], $siblingIds)) {
                        $rawIntervals[] = [
                            'start' => substr($reqItem['start_time'] ?? $scheduleItem->start_time, 0, 5),
                            'end' => substr($reqItem['end_time'] ?? $scheduleItem->end_time, 0, 5)
                        ];

                        if (!empty($reqItem['data'])) {
                            foreach ($reqItem['data'] as $d) {
                                if (isset($d['lesson_id'])) {
                                    $lId = (int)$d['lesson_id'];
                                    if (!in_array($lId, $targetLessonIds)) {
                                        $targetLessonIds[] = $lId;

                                        if ($expandGroup) {
                                            $lObj = (new Lesson())
                                                ->where(['id' => $lId])
                                                ->with(['childLessons', 'parentLesson'])
                                                ->first();

                                            if ($lObj) {
                                                if ($lObj->parent_lesson_id) {
                                                    if (!in_array((int)$lObj->parent_lesson_id, $targetLessonIds)) {
                                                        $targetLessonIds[] = (int)$lObj->parent_lesson_id;
                                                    }

                                                    $parentObj = (new Lesson())
                                                        ->where(['id' => $lObj->parent_lesson_id])
                                                        ->with(['childLessons'])
                                                        ->first();

                                                    if ($parentObj) {
                                                        foreach ($parentObj->childLessons as $cl) {
                                                            if (!in_array((int)$cl->id, $targetLessonIds)) {
                                                                $targetLessonIds[] = (int)$cl->id;
                                                            }
                                                        }
                                                    }
                                                } elseif (!empty($lObj->childLessons)) {
                                                    foreach ($lObj->childLessons as $cl) {
                                                        if (!in_array((int)$cl->id, $targetLessonIds)) {
                                                            $targetLessonIds[] = (int)$cl->id;
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
     * Item parçalama (flatten timeline) - geçici stub
     */
    private function processItemDeletion(
        ScheduleItem $item,
        array $deleteIntervals,
        array $targetLessonIds = [],
        int $duration = 50,
        int $break = 10,
        bool $deleteOriginal = true
    ): array {
        if ($deleteOriginal) {
            $item->delete();
        }
        
        return ['deleted' => true, 'created' => []];
    }
}

