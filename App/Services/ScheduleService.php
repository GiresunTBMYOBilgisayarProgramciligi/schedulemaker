<?php

namespace App\Services;

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
                $errorMsg = ($scheduleType === 'lesson')
                    ? "{$lesson->getFullName()} dersinin toplam saati aşılıyor. (Fazla: " . abs($lesson->remaining_size) . " saat)"
                    : "{$lesson->getFullName()} dersinin sınav mevcudu aşılıyor. (Fazla: " . abs($lesson->remaining_size) . " kişi)";

                throw new Exception($errorMsg);
            }
        }
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
}
