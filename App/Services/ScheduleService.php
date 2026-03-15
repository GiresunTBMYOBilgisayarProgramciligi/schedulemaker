<?php

namespace App\Services;

use App\DTOs\DeleteScheduleResult;
use App\DTOs\SaveScheduleResult;
use App\DTOs\ScheduleItemData;
use App\Exceptions\ValidationException;
use App\Helpers\TimeHelper;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Repositories\ScheduleItemRepository;
use App\Repositories\ScheduleRepository;
use App\Services\ExamService;
use App\Services\TimelineService;
use App\Validators\ScheduleItemValidator;
use Exception;

use function App\Helpers\getSettingValue;

/**
 * Schedule Service
 * 
 * Schedule ve ScheduleItem işlemleri için iş mantığı katmanı
 * 
 */
class ScheduleService extends BaseService
{
    private ScheduleRepository $scheduleRepo;
    private ScheduleItemRepository $itemRepo;
    private ScheduleItemValidator $validator;
    private TimelineService $timelineService;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->itemRepo = new ScheduleItemRepository();
        $this->validator = new ScheduleItemValidator();
        $this->timelineService = new TimelineService();
    }

    /**
     * Schedule item'larını kaydeder (single, group ve dummy destekler)
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
                /** @var Schedule $schedule */
                $schedule = $this->scheduleRepo->find($dto->scheduleId);
                if (!$schedule) {
                    throw new Exception("Schedule not found: {$dto->scheduleId}");
                }

                $isDummy = $dto->isDummy();
                $isGroup = ($dto->status === 'group');
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

                if ($isGroup) {
                    // GROUP ITEM: mergeGroupItems kullanarak multi-schedule'a kaydet
                    $itemIds = $this->saveGroupItemToSchedules($dto, $lesson, $schedule);
                } else {
                    // SINGLE/DUMMY ITEM: Basit çakışma kontrolü (v1.0)
                    $conflicts = $this->itemRepo->findConflicting(
                        $dto->scheduleId,
                        $dto->dayIndex,
                        $dto->weekIndex,
                        $dto->startTime,
                        $dto->endTime
                    );

                    if (!empty($conflicts)) {
                        $this->logger->warning("Conflict detected for item #$index", $this->logContext([
                            'conflicts' => count($conflicts),
                            'schedule_id' => $dto->scheduleId
                        ]));
                    }

                    // MULTI-SCHEDULE KAYDETME: Tüm ilgili schedule'lara kaydet
                    $itemIds = $this->saveToMultipleSchedules($dto, $lesson, $schedule);
                }

                $createdIds = array_merge($createdIds, $itemIds);

                $this->logger->debug("Item #{$index} saved to " . count($itemIds) . " schedules", $this->logContext([
                    'item_ids' => $itemIds,
                    'is_group' => $isGroup
                ]));

                // Etkilenen ders ID'lerini kaydet
                if (!$isDummy && $lesson) {
                    $affectedLessonIds[] = $lesson->id;
                }
            }

            // Ders saati kontrolü
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
                        'lesson_name' => $lesson->getFullName(true,true,true,true),
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
                'lesson_name' => $childLesson->getFullName(true),
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
                $itemSlots = TimeHelper::calculateItemSlots($item->start_time, $item->end_time, $slotSize);

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
                    $newEndTime = TimeHelper::calculateEndTimeBySlots($item->start_time, $newSlots, $slotSize, $scheduleType === 'lesson' ? 'lesson' : 'exam');

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
                        'semester_no' => $targetSchedule->semester_no ?? null
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
     * Sınav için owner listesini belirler.
     * ExamService'e delege edilir.
     *
     * @deprecated ExamService::determineExamOwners kullanın
     */
    private function determineExamOwners(Lesson $lesson, array $examAssignments): array
    {
        return (new ExamService())->determineExamOwners($lesson, $examAssignments);
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
                'lesson_hours' => $childLesson->hours,  // Duration bilgisi (DB column: hours)
                'lesson_context' => $childLesson
            ];

            // Child lesson'un programı varsa
            if ($childLesson->program_id) {
                $owners[] = [
                    'type' => 'program',
                    'id' => $childLesson->program_id,
                    'semester_no' => $childLesson->semester_no,
                    'is_child' => true,
                    'child_lesson_id' => $childLesson->id,
                    'lesson_hours' => $childLesson->hours,  // Duration bilgisi (DB column: hours)
                    'lesson_context' => $childLesson
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
            isset($owner['semester_no']) && $owner['semester_no'] !== '' ? (int) $owner['semester_no'] : null
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
        $owners = array_map(function ($o) use ($lesson) {
            // Eğer owner bir child lesson ile ilişkili değilse, mevcut dersi bağlam olarak ekle
            if (!isset($o['is_child']) || !$o['is_child']) {
                $o['lesson_context'] = $lesson;
            } else {
                // Child lesson için lesson_context zaten determineChildLessonOwners veya benzeri bir yerde eklenmeli
                // Veya burada child lesson nesnesi çekilip eklenebilir.
                // optimize etmek için determineChildLessonOwners metodunu güncelliyoruz.
            }
            return $o;
        }, $this->determineOwners($dto, $lesson));
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
            $lessonType = ($sourceSchedule->type === 'lesson') ? 'lesson' : 'exam';
            $slotSize = (int) getSettingValue('duration', $lessonType, $lessonType === 'exam' ? 30 : 50) + 
                        (int) getSettingValue('break', $lessonType, $lessonType === 'exam' ? 0 : 10);

            $addedSlots = TimeHelper::calculateItemSlots($dto->startTime, $dto->endTime, $slotSize);

            // Eğer ana dersin saati aşılıyorsa hata fırlat
            if ($lesson->remaining_size < $addedSlots) {
                $errorMsg = ($sourceSchedule->type === 'lesson')
                    ? "{$lesson->getFullName()} dersinin toplam saati aşılıyor. (Kalan: {$lesson->remaining_size} saat, Eklenmek istenen: {$addedSlots} saat)"
                    : "{$lesson->getFullName()} dersinin sınav mevcudu aşılıyor. (Kalan: {$lesson->remaining_size}, Eklenmek istenen: {$addedSlots})";

                throw new Exception($errorMsg);
            }
        }

        // Child lesson'lar için döngü ÖNCESİNDE remaining_size hesapla ve önbelleğe al.
        // Önemli: Döngü içinde IsScheduleComplete çağrıldığında, bir önceki owner'a yapılan
        // kayıt (commit olmasa da) aynı lesson'ın diğer schedule'larında "dolu" görünmesine
        // neden olur. Bu önbellek sayesinde tüm child owner'lar aynı başlangıç noktasından başlar.
        $childLessonRemaining = []; // [ lessonId => remaining_slots ]
        foreach ($owners as $owner) {
            if (!isset($owner['is_child']) || !$owner['is_child']) {
                continue;
            }
            $childLessonId = $owner['child_lesson_id'];
            if (isset($childLessonRemaining[$childLessonId])) {
                continue; // Aynı child lesson zaten hesaplandı
            }
            $childLesson = (new Lesson())->find($childLessonId);
            if ($childLesson) {
                $childLesson->IsScheduleComplete($sourceSchedule->type);
                $childLessonRemaining[$childLessonId] = [
                    'lesson' => $childLesson,
                    'remaining' => (int) ($childLesson->remaining_size ?? 0),
                ];
            }
        }

        // Her owner için bu loop içinde kaç slot eklendi takibi
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

                // Önbellekten remaining_size al (döngü başında DB sorgusu yapıldı)
                if (!isset($childLessonRemaining[$childLessonId])) {
                    continue; // Child lesson bulunamadı, atla
                }
                $childLesson = $childLessonRemaining[$childLessonId]['lesson'];
                $baseRemaining = $childLessonRemaining[$childLessonId]['remaining'];

                // Bu owner için daha önce bu child'dan eklendi mi? (schedule bazında takip)
                $trackingKey = "{$childLessonId}_{$owner['type']}";
                $alreadyAddedSlots = $childLessonHoursAdded[$trackingKey] ?? 0;
                $currentRemaining = $baseRemaining - $alreadyAddedSlots;

                if ($currentRemaining <= 0) {
                    $this->logger->debug("Child lesson already full, skipping schedule #{$targetSchedule->id}", [
                        'lesson_id' => $childLessonId,
                        'lesson_name' => $childLesson->getFullName()
                    ]);
                    continue;
                }

                // Eklenecek slot sayısı
                $lessonType = ($sourceSchedule->type === 'lesson') ? 'lesson' : 'exam';
                $slotSize = (int) getSettingValue('duration', $lessonType, $lessonType === 'exam' ? 30 : 50) + 
                            (int) getSettingValue('break', $lessonType, $lessonType === 'exam' ? 0 : 10);

                $parentSlots = TimeHelper::calculateItemSlots($dto->startTime, $dto->endTime, $slotSize);

                // Eğer child'a sığmıyorsa kısalt
                $slotsToAdd = min($parentSlots, (int) $currentRemaining);
                if ($slotsToAdd < $parentSlots) {
                    $item->end_time = TimeHelper::calculateEndTimeBySlots($dto->startTime, $slotsToAdd, $slotSize, $lessonType);
                    $this->logger->warning("Bağlı ders kapasitesi dolu, süre kısaltılıyor", [
                        'lesson_id' => $childLessonId,
                        'original_slots' => $parentSlots,
                        'added_slots' => $slotsToAdd
                    ]);
                }

                // Tracking güncelle (owner type bazlı: lesson ve program owner'ları birbirini etkilemesin)
                $childLessonHoursAdded[$trackingKey] = ($childLessonHoursAdded[$trackingKey] ?? 0) + $slotsToAdd;

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

            // Preferred/unavailable item için çakışma kontrolü
            // Eğer kaydedilecek item dummy (preferred/unavailable) ise ve hedef schedule'da
            // o zaman diliminde gerçek bir ders (single/group) varsa, preferred item kaydedilmez.
            if ($dto->isDummy()) {
                $conflicts = $this->itemRepo->findConflicting(
                    $targetSchedule->id,
                    $dto->dayIndex,
                    $dto->weekIndex,
                    $item->start_time,
                    $item->end_time
                );

                if (!empty($conflicts)) {
                    $hasRealConflict = false;
                    foreach ($conflicts as $conflict) {
                        if (!in_array($conflict->status, ['preferred', 'unavailable'])) {
                            $hasRealConflict = true;
                            break;
                        }
                    }
                    if ($hasRealConflict) {
                        $this->logger->warning("Preferred/unavailable item skipped due to existing lesson conflict", $this->logContext([
                            'schedule_id' => $targetSchedule->id,
                            'day_index' => $dto->dayIndex,
                            'start_time' => $item->start_time,
                            'end_time' => $item->end_time,
                            'status' => $dto->status
                        ]));
                        continue; // Bu owner için kaydetme
                    }
                }
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
                    if (TimeHelper::isOverlapping(
                        $baseItem->start_time,
                        $baseItem->end_time,
                        $item->start_time,
                        $item->end_time
                    )) {
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
     * Bir kaynağa ait tüm schedule'ları ve item'larını temizler.
     * Model beforeDelete hook'larından çağrılır.
     *
     * @param string $ownerType 'lesson' | 'user' | 'classroom' | 'program'
     * @param int $ownerId
     */
    public function wipeResourceSchedules(string $ownerType, int $ownerId): void
    {
        $this->logger->debug("wipeResourceSchedules START for $ownerType ID: $ownerId");

        $schedules = (new Schedule())->get()->where([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId
        ])->all();

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where(['schedule_id' => $schedule->id])->all();
            foreach ($items as $item) {
                // deleteScheduleItems sibling'leri de bulup siler
                $this->deleteScheduleItems([$item->getArray()], false);
            }
            $schedule->delete();
        }

        $this->logger->debug("wipeResourceSchedules COMPLETED for $ownerType ID: $ownerId");
    }

    /**
     * Schedule item'larını siler ve sibling'leri de temizler.
     *
     * @param array $itemsData
     * @param bool $expandGroup
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
                if ($type === 'exam') {
                    // Sınav item'ları için ExamService'deki sibling bulma mantığı kullanılır
                    $examSiblings = (new ExamService())->findExamSiblingItems($scheduleItem);
                    if (count($examSiblings) > 1) {
                        $siblings = $examSiblings;
                    }
                }
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
        $internalPoints = [];
        foreach ($deleteIntervals as $del) {
            $internalPoints[] = $del['start'];
            $internalPoints[] = $del['end'];
        }

        $points = $this->timelineService->getCriticalPoints($startStr, $endStr, $internalPoints, $duration, $break);

        $dataList = $item->data ?: [];

        // 2. Dilimler (segments) üzerinden geç
        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            // Bu dilim silinecek mi?
            $isDeleteZone = false;
            foreach ($deleteIntervals as $del) {
                if ($del['start'] <= $pStart && $del['end'] >= $pEnd) {
                    $isDeleteZone = true;
                    break;
                }
            }

            $currentData = $dataList;
            if ($isDeleteZone) {
                if (!empty($targetLessonIds)) {
                    $currentData = array_values(array_filter($dataList, function ($l) use ($targetLessonIds) {
                        return !in_array((int) $l['lesson_id'], $targetLessonIds);
                    }));
                } else {
                    $currentData = [];
                }
            }

            $isSpecial = in_array($item->status, ['preferred', 'unavailable']);
            $wasPreferred = ($item->detail['preferred'] ?? false);

            $shouldKeep = true;
            if (empty($currentData)) {
                $shouldKeep = $isSpecial ? !$isDeleteZone : ($wasPreferred && $isDeleteZone);
            }

            $segments[] = [
                'start' => $pStart,
                'end' => $pEnd,
                'data' => $currentData,
                'detail' => $item->detail, // Detail verisini segmentlere ekle
                'isBreak' => (TimeHelper::getDurationMinutes($pStart, $pEnd) == $break),
                'shouldKeep' => $shouldKeep
            ];
        }

        // 3. & 4. Birleştirme ve Temizlik
        $newSegments = $this->timelineService->mergeContiguousSegments($segments, $break);

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
                $newItem->status = $this->timelineService->determineStatus(
                    $seg['data'], 
                    $item->status, 
                    $item->detail['preferred'] ?? false
                );

                $newItem->data = $seg['data'];
                $newItem->detail = $item->detail;
                $newItem->create();
                $createdItems[] = $newItem;
            }
        }

        return ['deleted' => true, 'created' => $createdItems];
    }

    /**
     * Group statuslu item'ı tüm ilgili schedule'lara kaydeder.
     *
     * Single item kaydetmeden farkı: Her schedule'a doğrudan INSERT yapmak yerine
     * o schedule'daki mevcut group item'larla mergeGroupItems aracılığıyla birleştirir.
     *
     * @param ScheduleItemData $dto
     * @param Lesson|null $lesson
     * @param Schedule $sourceSchedule
     * @return array Oluşturulan item ID'leri
     * @throws Exception
     */
    private function saveGroupItemToSchedules(
        ScheduleItemData $dto,
        ?Lesson $lesson,
        Schedule $sourceSchedule
    ): array {
        $owners = array_map(function ($o) use ($lesson) {
            if (!isset($o['is_child']) || !$o['is_child']) {
                $o['lesson_context'] = $lesson;
            }
            return $o;
        }, $this->determineOwners($dto, $lesson));
        $createdIds = [];

        // Child lesson önbelleği (remaining_size döngü başında bir kez hesaplanır)
        $childLessonRemaining = [];
        foreach ($owners as $owner) {
            if (!isset($owner['is_child']) || !$owner['is_child']) {
                continue;
            }
            $clId = $owner['child_lesson_id'];
            if (!isset($childLessonRemaining[$clId])) {
                $childLesson = (new Lesson())->find($clId);
                if ($childLesson) {
                    $childLesson->IsScheduleComplete($sourceSchedule->type);
                    $childLessonRemaining[$clId] = [
                        'lesson' => $childLesson,
                        'remaining' => (int) ($childLesson->remaining_size ?? 0),
                    ];
                }
            }
        }

        $childSlotsAdded = []; // Her owner type için eklenen slot takibi

        foreach ($owners as $owner) {
            /** @var Schedule $targetSchedule */
            $targetSchedule = $this->findOrCreateSchedule(
                $owner,
                $sourceSchedule->academic_year,
                $sourceSchedule->semester,
                $sourceSchedule->type
            );

            // Child lesson için data ve zaman aralığını belirle
            $data = $dto->data;
            $startTime = $dto->startTime;
            $endTime = $dto->endTime;

            if (isset($owner['is_child']) && $owner['is_child']) {
                $childLessonId = $owner['child_lesson_id'];

                if (!isset($childLessonRemaining[$childLessonId])) {
                    continue; // Child lesson bulunamadı
                }

                $baseRemaining = $childLessonRemaining[$childLessonId]['remaining'];
                $trackingKey = "{$childLessonId}_{$owner['type']}";
                $alreadyAdded = $childSlotsAdded[$trackingKey] ?? 0;
                $currentRemaining = $baseRemaining - $alreadyAdded;

                if ($currentRemaining <= 0) {
                    $this->logger->debug("Group: child lesson already full, skipping", [
                        'lesson_id' => $childLessonId,
                        'schedule_id' => $targetSchedule->id
                    ]);
                    continue;
                }

                // Gerekirse süreyi kısalt
                $lessonType = 'lesson';
                $slotSize = (int) getSettingValue('duration', $lessonType, 50) + 
                            (int) getSettingValue('break', $lessonType, 10);

                $parentSlots = TimeHelper::calculateItemSlots($dto->startTime, $dto->endTime, $slotSize);
                $slotsToAdd = min($parentSlots, (int) $currentRemaining);
                if ($slotsToAdd < $parentSlots) {
                    $endTime = TimeHelper::calculateEndTimeBySlots($dto->startTime, $slotsToAdd, $slotSize, $lessonType);
                }

                $childSlotsAdded[$trackingKey] = $alreadyAdded + $slotsToAdd;

                // Data'yı child lesson ID'si ile değiştir
                $data = array_map(function ($d) use ($childLessonId) {
                    $childData = $d;
                    $childData['lesson_id'] = $childLessonId;
                    return $childData;
                }, $dto->data);
            }

            // mergeGroupItems: Bu schedule'daki mevcut group item'larla birleştir
            $newIds = $this->mergeGroupItems(
                $targetSchedule->id,
                $dto->dayIndex,
                $dto->weekIndex,
                $startTime,
                $endTime,
                $data,
                $dto->detail
            );

            $createdIds = array_merge($createdIds, $newIds);
        }

        return $createdIds;
    }

    /**
     * Group item'ları merge et (flatten timeline ile)
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
            return TimeHelper::isOverlapping(
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
        $startStr = substr($startTime, 0, 5);
        $endStr = substr($endTime, 0, 5);
        
        $internalPoints = [];
        foreach ($involvedItems as $item) {
            $internalPoints[] = $item->getShortStartTime();
            $internalPoints[] = $item->getShortEndTime();
        }

        // Sistem parametrelerini al
        $lessonType = 'lesson'; // Group itemlar genelde ders programı içindir
        $duration = (int) getSettingValue('duration', $lessonType, 50);
        $break = (int) getSettingValue('break', $lessonType, 10);

        $points = $this->timelineService->getCriticalPoints($startStr, $endStr, $internalPoints, $duration, $break);

        // 3. Dilimler (segments) üzerinden geç
        $segments = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $pStart = $points[$i];
            $pEnd = $points[$i + 1];

            $mergedData = [];
            $mergedDetail = [];

            // Yeni veri bu aralığı kapsıyor mu?
            if ($startStr <= $pStart && $endStr >= $pEnd) {
                $mergedData = array_merge($mergedData, $newData);
                if ($newDetail) {
                    $mergedDetail = array_merge($mergedDetail, $newDetail);
                }
            }

            // Mevcut itemler bu aralığı kapsıyor mu?
            foreach ($involvedItems as $item) {
                if ($item->getShortStartTime() <= $pStart && $item->getShortEndTime() >= $pEnd) {
                    if (is_array($item->data)) {
                        $mergedData = array_merge($mergedData, $item->data);
                    }
                    if (is_array($item->detail)) {
                        $mergedDetail = array_merge($mergedDetail, $item->detail);
                    }
                }
            }

            if (!empty($mergedData)) {
                // Mükerrer dersleri temizle
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

                $segments[] = [
                    'start' => $pStart,
                    'end' => $pEnd,
                    'data' => $uniqueData,
                    'detail' => $mergedDetail,
                    'isBreak' => (TimeHelper::getDurationMinutes($pStart, $pEnd) == $break),
                    'shouldKeep' => true
                ];
            }
        }

        // 4. Birleştirme
        $newSegments = $this->timelineService->mergeContiguousSegments($segments, $break);

        // 5. Veritabanı İşlemleri
        foreach ($involvedItems as $item) {
            $item->delete();
        }

        $createdGroupIds = [];
        foreach ($newSegments as $seg) {
            $newItem = new ScheduleItem();
            $newItem->schedule_id = $scheduleId;
            $newItem->day_index = $dayIndex;
            $newItem->week_index = $weekIndex;
            $newItem->start_time = $seg['start'];
            $newItem->end_time = $seg['end'];
            $newItem->status = 'group';
            $newItem->data = $seg['data'];
            // Detail bilgisini segmentten alıyoruz (TimelineService::mergeContiguousSegments metodunun detail bilgisini koruduğundan emin olmalıyız)
            // mergeContiguousSegments şu an için detail bilgisini korumuyor olabilir, onu düzeltelim veya burada manuel yönetelim.
            // TimelineService'i güncellediğimizi varsayarsak segment['detail'] olmalı.
            $newItem->detail = $seg['detail'] ?? null;
            $newItem->create();
            $createdGroupIds[] = $newItem->id;
        }

        return $createdGroupIds;
    }


    // Silinen yardımcı metotlar (TimeHelper'a taşındı):
    // - calculateScheduledHours
    // - getItemDurationMinutes 
    // - calculateEndTime
}

