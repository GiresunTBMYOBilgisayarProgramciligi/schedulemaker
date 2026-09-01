<?php

namespace App\Services\Schedule;

use App\Services\BaseService;
use App\DTOs\DeleteScheduleResult;
use App\DTOs\ScheduleItemDTO;
use App\DTOs\ScheduleFilterDTO;
use App\Helpers\TimeHelper;
use App\Core\Database;
use App\Enums\ExamType;
use App\Enums\LessonType;
use App\Enums\OwnerType;
use App\Enums\ScheduleItemStatus;
use App\Models\Lesson;
use App\Models\LessonCombination;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Repositories\ScheduleItemRepository;
use App\Repositories\ScheduleRepository;
use App\Validators\ScheduleItemValidator;
use App\DTOs\ToggleLockScheduleItemDTO;
use Exception;

use function App\Helpers\getSettingValue;

/**
 * Schedule Service
 * 
 */
class ScheduleService extends BaseService
{
    protected ScheduleRepository $scheduleRepo;
    protected ScheduleItemRepository $itemRepo;
    protected ScheduleItemValidator $validator;
    protected TimelineService $timelineService;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->itemRepo = new ScheduleItemRepository();
        $this->validator = new ScheduleItemValidator();
        $this->timelineService = new TimelineService();
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
    protected function checkLessonHourLimits(array $lessonIds, string $scheduleType): void
    {
        $semester = getSettingValue('semester');
        $academicYear = getSettingValue('academic_year');

        foreach ($lessonIds as $lessonId) {
            $lesson = (new Lesson())->find($lessonId);
            if (!$lesson) {
                continue;
            }

            // IsScheduleComplete metodunu çalıştırarak remaining_size hesaplatıyoruz
            $lesson->IsScheduleComplete($scheduleType, $semester, $academicYear);

            if ($lesson->remaining_size < 0) {
                // Child lesson kontrolü (lesson_combinations tablosu üzerinden)
                $hasParent = (new LessonCombination())->get()->where([
                    'child_lesson_id' => $lesson->id,
                    'type' => $scheduleType === 'lesson' ? 'lesson' : 'exam',
                    'semester' => $semester,
                    'academic_year' => $academicYear
                ])->first() !== null;

                if ($hasParent) {
                    // Child lesson → Fazla saatleri otomatik temizle
                    $this->logger->debug("Child lesson hour limit exceeded, cleaning up", $this->logContext([
                        'lesson_id' => $lesson->id,
                        'lesson_name' => $lesson->getFullName(true,true,true,true),
                        'excess_hours' => abs($lesson->remaining_size)
                    ]));

                    $this->cleanupExcessChildHours($lesson, $scheduleType, $semester, $academicYear);
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
     * @param Lesson $childLesson Child lesson entity'si
     * @param string $scheduleType Schedule tipi ('lesson', 'midterm-exam', etc.)
     * @param string|null $semester Dönem
     * @param string|null $academicYear Akademik yıl
     * @return void
     */
    private function cleanupExcessChildHours(Lesson $childLesson, string $scheduleType, ?string $semester = null, ?string $academicYear = null): void
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

        $semester = $semester ?? getSettingValue('semester');
        $academicYear = $academicYear ?? getSettingValue('academic_year');

        // Bu child lesson'a ait lesson ve program schedule'ları bul
        $childSchedules = (new Schedule())->get()->where([
            'owner_type' => ['in' => [OwnerType::LESSON->value, OwnerType::PROGRAM->value]],
            'owner_id' => ['in' => array_filter([$childLesson->id, $childLesson->program_id])],
            'type' => $scheduleType,
            'semester' => $semester,
            'academic_year' => $academicYear,
        ])->all();

        if (empty($childSchedules)) {
            $this->logger->error("No schedules found for child lesson cleanup", $this->logContext([
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

        foreach ($childSchedules as $schedule) {
            $slotsToRemove = $excessSlots;

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

                // Sadece child dersi içeren item'ları kontrol et
                $itemData = is_array($item->data) ? $item->data : (unserialize($item->data) ?: []);
                $hasChild = false;
                foreach ($itemData as $d) {
                    if (($d['lesson_id'] ?? null) == $childLesson->id) {
                        $hasChild = true;
                        break;
                    }
                }
                if (!$hasChild && $schedule->owner_type === OwnerType::PROGRAM->value) {
                    continue;
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

        $this->logger->debug("Child lesson excess hours cleaned up", $this->logContext([
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
     * @param ScheduleItemDTO $dto Schedule item verisi
     * @param Lesson|null $lesson İlgili ders entity'si (dummy items için null olabilir)
     * @return array Owner listesi, her biri ['type' => 'user|program|lesson|classroom', 'id' => int] formatında
     * @throws Exception Dummy olmayan item için lesson yoksa
     */
    protected function determineOwners(ScheduleItemDTO $dto, ?Lesson $lesson): array
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
     * @param ScheduleItemDTO $dto Schedule item verisi, içinde lecturer_id ve classroom_id var
     * @param Lesson $lesson Ders entity'si, program_id ve classroom_type bilgilerini içerir
     * @return array Owner listesi [['type' => 'user|program|lesson|classroom', 'id' => int], ...]
     */
    private function determineLessonOwners(ScheduleItemDTO $dto, Lesson $lesson): array
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

        // Program owner (varsa - staj dersleri program schedule_items tablosuna girmez)
        if ($lesson->program_id && (int)$lesson->type !== LessonType::INTERNSHIP->value) {
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
     * ExamScheduleService'e delege edilir.
     *
     * @deprecated ExamScheduleService::determineExamOwners kullanın
     */
    private function determineExamOwners(Lesson $lesson, array $examAssignments): array
    {
        return (new ExamScheduleService())->determineExamOwners($lesson, $examAssignments);
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
    protected function findOrCreateSchedule(
        array $owner,
        string $academicYear,
        string $semester,
        string $type
    ): Schedule {
        $semesterNo = ($owner['type'] === 'program' && isset($owner['semester_no']) && $owner['semester_no'] !== '')
            ? (int) $owner['semester_no']
            : null;

        // Önce varolan schedule'ı ara
        $existing = $this->scheduleRepo->findByOwnerAndPeriod(
            $owner['type'],
            $owner['id'],
            $academicYear,
            $semester,
            $type,
            $semesterNo
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
        if (isset($owner['semester_no']) && $owner['type'] === 'program') {
            $schedule->semester_no = $owner['semester_no'];
        }

        $schedule->create();

        return $schedule;
    }

    /**
     * İlgili schedule kayıtlarının updated_at tarihini günceller.
     *
     * @param int|array<int> $scheduleIds
     * @param string|null $timestamp
     * @return int
     */
    public function touchSchedules(int|array $scheduleIds, ?string $timestamp = null): int
    {
        return $this->scheduleRepo->touch($scheduleIds, $timestamp);
    }

    /**
     * DTO'ya göre Schedule bulur veya oluşturur.
     */
    public function getOrCreateSchedule(ScheduleFilterDTO $dto): Schedule
    {
        return $this->scheduleRepo->findOrCreate($dto->toArray());
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
    protected function determineOwnersFromItem(ScheduleItem $item, array $lessonIds): array
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

            if ($lesson->program_id && (int)$lesson->type !== LessonType::INTERNSHIP->value) {
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
            if (!empty($lesson->parentLesson)) {
                $parent = (new Lesson())
                    ->where(['id' => $lesson->parentLesson->id])
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
            if (!empty($slotData->lecturer) && isset($slotData->lecturer->id)) {
                $owners[] = ['type' => 'user', 'id' => $slotData->lecturer->id, 'semester_no' => null];
            }

            // Classroom owner (UZEM değilse)
            if (!empty($slotData->classroom) && isset($slotData->classroom->id) && (!$lesson || $lesson->classroom_type != 3)) {
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
     * @param ScheduleItem $baseItem
     * @param array $lessonIds
     * @return ScheduleItem[]
     */
    protected function findSiblingItems(ScheduleItem $baseItem, array $lessonIds): array
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
        // Model yerine Repository kullanımı tercih edildi
        $schedules = $this->scheduleRepo->findBy([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId
        ]);

        foreach ($schedules as $schedule) {
            $items = (new ScheduleItem())->get()->where(['schedule_id' => $schedule->id])->all();
            foreach ($items as $item) {
                // deleteScheduleItems sibling'leri de bulup siler
                $this->deleteScheduleItems([ScheduleItemDTO::fromArray($item->getArray())], false);
            }
            $schedule->delete();
        }

        $this->logger->debug("wipeResourceSchedules COMPLETED for $ownerType ID: $ownerId");
    }

    /**
     * Program öğelerinin kilit durumunu değiştirir (çoklu seçim destekli).
     * Sibling (kardeş) öğelerin de kilit durumu senkronize edilir.
     *
     * @param ToggleLockScheduleItemDTO $dto
     * @return array [successCount, finalState]
     * @throws Exception
     */
    public function toggleLockScheduleItems(ToggleLockScheduleItemDTO $dto): array
    {
        $successCount = 0;
        $finalState = null;

        Database::transaction(function () use ($dto, &$successCount, &$finalState) {
            $processedSiblingIds = [];
            $affectedScheduleIds = [];

            foreach ($dto->ids as $id) {
                if (in_array($id, $processedSiblingIds)) {
                    continue;
                }

                /** @var ScheduleItem|null $item */
                $item = (new ScheduleItem())
                    ->where(['id' => $id])
                    ->with('schedule')
                    ->first();

                if (!$item) {
                    $this->logger->warning("toggleLockScheduleItems failed: Item not found", $this->logContext(['item_id' => $id]));
                    continue;
                }

                if ($item->schedule_id) {
                    $affectedScheduleIds[] = (int) $item->schedule_id;
                }

                $detail = $item->detail ?? [];
                $isLocked = !empty($detail['is_locked']);

                // Eğer target_state belirtilmişse o duruma zorla, yoksa toggle yap
                $newState = $dto->target_state !== null ? $dto->target_state : !$isLocked;
                $finalState = $newState;

                // Schedule türünü belirle
                $type = 'lesson';
                if ($item->schedule && ExamType::isExamType($item->schedule->type)) {
                    $type = 'exam';
                }

                // Sibling (kardeş) öğeleri bul
                $baseLessonIds = [];
                foreach ($item->getSlotDatas() as $sd) {
                    if ($sd->lesson) {
                        $baseLessonIds[] = (int) $sd->lesson->id;
                    }
                }

                $siblings = $this->findSiblingItems($item, $baseLessonIds);
                if ($type === 'exam') {
                    $examSiblings = (new ExamScheduleService())->findExamSiblingItems($item);
                    if (count($examSiblings) > 1) {
                        $siblings = $examSiblings;
                    }
                }

                // Tüm sibling'lerin kilit durumunu güncelle
                foreach ($siblings as $sibling) {
                    if (in_array($sibling->id, $processedSiblingIds)) {
                        continue;
                    }

                    if ($sibling->schedule_id) {
                        $affectedScheduleIds[] = (int) $sibling->schedule_id;
                    }

                    $siblingDetail = $sibling->detail ?? [];
                    $siblingDetail['is_locked'] = $newState;
                    $sibling->detail = $siblingDetail;
                    $sibling->update();

                    $processedSiblingIds[] = $sibling->id;
                    $successCount++;

                    $this->logger->debug("Lock status updated successfully", $this->logContext([
                        'item_id' => $sibling->id,
                        'is_locked' => $newState,
                        'is_sibling' => ($sibling->id !== $item->id)
                    ]));
                }
            }

            if (!empty($affectedScheduleIds)) {
                $this->touchSchedules($affectedScheduleIds);
            }
        });

        return [$successCount, $finalState];
    }

    /**
     * Schedule item'larını siler ve sibling'leri de temizler.
     *
     * @param ScheduleItemDTO[] $dtos Ekran üzerinden gelen silinecek item'ların DTO verileri
     * @param bool $expandGroup
     * @return DeleteScheduleResult
     * @throws Exception
     */
    public function deleteScheduleItems(
        array $dtos,
        bool $expandGroup = true
    ): DeleteScheduleResult {
        if (empty($dtos)) {
            return DeleteScheduleResult::success([], []);
        }

        $firstSchedule = (new Schedule())->find($dtos[0]->scheduleId);
        if ($firstSchedule && ExamType::isExamType($firstSchedule->type)) {
            return (new ExamScheduleService())->deleteScheduleItems($dtos, $expandGroup);
        }

        return (new LessonScheduleService())->deleteScheduleItems($dtos, $expandGroup);
    }

    /**
     * Item silindiğinde, o item tarafından daha önce yerinden edilmiş (displaced)
     * `preferred` slot'larını schedule'a geri yükler.
     *
     * **Çalışma Mantığı:**
     * - `$item->detail['displaced_preferred']` alanında saklanan her aralık için:
     *   1. Silme aralığıyla (`$deleteIntervals`) kesişimi hesaplar.
     *   2. Kesişim varsa (item gerçekten o bölgede siliniyor), kesişen preferred
     *      aralığını yeni bir `preferred` ScheduleItem olarak oluşturur.
     * - Eğer item kısmen silindiyse (partial delete), yalnızca silinen bölgeye
     *   karşılık gelen preferred kısım geri yüklenir.
     *
     * **Neden deleteIntervals ile kesiştirilir?**
     * Partial delete senaryosunda item'ın tamamı silinmeyebilir; bu durumda
     * yalnızca gerçekten silinen zaman dilimine denk gelen preferred parçaları
     * geri yüklenmelidir.
     *
     * @param ScheduleItem $item           Silinen/parçalanan item
     * @param array        $deleteIntervals Silme aralıkları [['start'=>'HH:MM','end'=>'HH:MM'],...]
     * @return ScheduleItem[] Geri oluşturulan preferred item'ların listesi
     */
    private function restoreDisplacedPreferred(ScheduleItem $item, array $deleteIntervals): array
    {
        $displacedList = $item->detail['displaced_preferred'] ?? [];

        if (empty($displacedList) || !is_array($displacedList)) {
            return [];
        }

        $restored = [];

        foreach ($displacedList as $dp) {
            $dpStart = $dp['start'] ?? null;
            $dpEnd   = $dp['end']   ?? null;

            if (!$dpStart || !$dpEnd) {
                continue;
            }

            // Silinen bölgeler ile bu displaced aralığın kesişimini bul.
            // Yalnızca gerçekten silinen kısma karşılık gelen preferred geri yüklenir.
            foreach ($deleteIntervals as $del) {
                $restoreInterval = TimeHelper::getOverlapInterval(
                    $dpStart,
                    $dpEnd,
                    $del['start'],
                    $del['end']
                );

                if ($restoreInterval === null) {
                    continue; // Bu silme aralığıyla kesişim yok
                }

                // Preferred item'ı geri oluştur
                $preferredItem = new ScheduleItem();
                $preferredItem->schedule_id = $item->schedule_id;
                $preferredItem->day_index   = $item->day_index;
                $preferredItem->week_index  = $item->week_index;
                $preferredItem->start_time  = $restoreInterval['start'];
                $preferredItem->end_time    = $restoreInterval['end'];
                $preferredItem->status      = ScheduleItemStatus::PREFERRED->value;
                $preferredItem->data        = null;
                $preferredItem->detail      = null;
                $preferredItem->create();

                $this->logger->debug('Displaced preferred slot geri yüklendi', $this->logContext([
                    'schedule_id'    => $item->schedule_id,
                    'day_index'      => $item->day_index,
                    'week_index'     => $item->week_index,
                    'restored_start' => $restoreInterval['start'],
                    'restored_end'   => $restoreInterval['end'],
                ]));

                $restored[] = $preferredItem;
            }
        }

        return $restored;
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
            'status' => ScheduleItemStatus::GROUP->value
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
        // Yeni item'ın orijinal aralığı (kapsama kontrolü için)
        $newStartStr = substr($startTime, 0, 5);
        $newEndStr = substr($endTime, 0, 5);

        // Yeni item ve mevcut çakışan itemların birleşik zaman aralığı (getCriticalPoints için)
        $startStr = $newStartStr;
        $endStr = $newEndStr;

        foreach ($involvedItems as $item) {
            $itemStart = $item->getShortStartTime();
            $itemEnd = $item->getShortEndTime();
            if ($itemStart < $startStr) {
                $startStr = $itemStart;
            }
            if ($itemEnd > $endStr) {
                $endStr = $itemEnd;
            }
        }
        
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

            // Mevcut itemler bu aralığı kapsıyor mu? (Önce mevcut verileri ekle - sıralama korunur)
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

            // Yeni veri bu aralığı kapsıyor mu? (Orijinal aralık kullanılır)
            if ($newStartStr <= $pStart && $newEndStr >= $pEnd) {
                $mergedData = array_merge($mergedData, $newData);
                if ($newDetail) {
                    $mergedDetail = array_merge($mergedDetail, $newDetail);
                }
            }

            if (!empty($mergedData)) {
                // Mükerrer dersleri temizle
                $uniqueData = [];
                $seenHashes = [];
                foreach ($mergedData as $d) {
                    $lid = $d['lesson_id'] ?? null;
                    if ($lid) {
                        $cid = $d['classroom_id'] ?? '';
                        $lec = $d['lecturer_id'] ?? '';
                        $hash = $lid . '_' . $cid . '_' . $lec;
                        
                        if (!in_array($hash, $seenHashes)) {
                            $seenHashes[] = $hash;
                            $uniqueData[] = $d;
                        }
                    } else {
                        $uniqueData[] = $d;
                    }
                }

                // Dersleri lesson_id'ye göre sırala (tutarlı sıralama)
                usort($uniqueData, function ($a, $b) {
                    return ((int) ($a['lesson_id'] ?? 0)) - ((int) ($b['lesson_id'] ?? 0));
                });

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
            $mergedItem = $this->timelineService->mergeAdjacentItems($newItem, $break);
            $createdGroupIds[] = $mergedItem->id;
        }

        return $createdGroupIds;
    }
}

