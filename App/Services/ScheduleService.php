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

                if (!$isDummy && isset($dto->data['lesson_id'])) {
                    $lesson = (new Lesson())->where(['id' => $dto->data['lesson_id']])->first();
                    if (!$lesson) {
                        throw new Exception("Lesson not found: {$dto->data['lesson_id']}");
                    }
                }

                // Basit çakışma kontrolü
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

                // Item oluştur ve kaydet
                $newItem = new ScheduleItem();
                $newItem->schedule_id = $dto->scheduleId;
                $newItem->day_index = $dto->dayIndex;
                $newItem->week_index = $dto->weekIndex;
                $newItem->start_time = $dto->startTime;
                $newItem->end_time = $dto->endTime;
                $newItem->status = $dto->status;
                $newItem->data = $dto->data;
                $newItem->detail = $dto->detail;
                $newItem->create();

                $createdIds[] = $newItem->id;

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

    /**
     * TODO v2: Çakışma çözümleme
     * - Preferred conflict handling
     * - Normal conflict error throwing
     * - Unavailable slot handling
     */

    /**
     * TODO v2: Multi-schedule kaydetme
     * - Owner'lar belirleme (user, classroom, program, lesson)
     * - Her owner için ayrı schedule oluşturma
     * - Child lesson handling
     */

    /**
     * TODO v2: Group item processing
     * - Merge/split logic
     * - Time slot overlapping
     */

    /**
     * TODO v3: Exam assignment
     * - Observer assignment
     * - Classroom assignment
     * - Cross-schedule replication
     */
}
