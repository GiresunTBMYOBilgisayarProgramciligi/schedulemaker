<?php

namespace App\Services;

use App\Enums\PermissionType;

use App\Enums\ExamType;
use App\Enums\ScheduleItemStatus;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Services\Schedule\ScheduleService;
use App\Services\Schedule\ScheduleSyncService;
use App\Core\Database;
use App\DTOs\CombineLessonDTO;
use App\DTOs\CombineExamLessonDTO;
use App\DTOs\DeleteCombineLessonDTO;

use App\DTOs\ScheduleItemDTO;
use function App\Helpers\getSettingValue;
use function App\Helpers\getSemesterNumbers;
use App\Repositories\LessonRepository;
use App\Repositories\ProgramRepository;
use App\Repositories\LessonAssignmentRepository;
use App\Repositories\LessonCombinationRepository;
use App\Core\Gate;
use App\DTOs\LessonDTO;
use App\DTOs\BulkDeleteDTO;
use App\DTOs\BulkUpdateDTO;
use App\DTOs\BulkActionResultDTO;
use App\Enums\OwnerType;
use App\Helpers\TimeHelper;
use App\Exceptions\ScheduleConflictException;
use Exception;


/**
 * Ders yönetimi iş mantığı servisi.
 *
 * Sorumluluklar:
 * - Ders CRUD işlemleri (saveNew, updateLesson)
 * - Child lesson bağlama/kopma işlemleri (combineLesson, deleteParentLesson)
 * - Ders bağlanırken schedule senkronizasyonu
 */
class LessonService extends BaseService
{
    private ScheduleService $scheduleService;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleService = new ScheduleService();
    }
    // ──────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────

    /**
     * Yeni ders kaydeder.
     *
     * @param LessonDTO $dto Ders verileri
     * @return int Oluşturulan dersin ID'si
     * @throws Exception Duplicate lesson_code veya kayıt hatası
     */
    public function saveNew(LessonDTO $dto): int
    {
        $this->logger->debug('Yeni ders ekleniyor', ['name' => $dto->name, 'code' => $dto->code ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $lesson = new Lesson();
                $lesson->fill($dto->toArray());
                $lesson->create();

                $semester = $dto->semester ?? getSettingValue('semester');
                $academicYear = $dto->academic_year ?? getSettingValue('academic_year');
                if (!empty($dto->lecturer_id) && !empty($semester) && !empty($academicYear)) {
                    (new LessonAssignmentRepository())->upsert(
                        $lesson->id,
                        $dto->lecturer_id,
                        $semester,
                        $academicYear
                    );
                    $this->logger->info('Ders hoca ataması oluşturuldu', [
                        'lesson_id' => $lesson->id,
                        'lecturer_id' => $dto->lecturer_id,
                        'semester' => $semester,
                        'academic_year' => $academicYear
                    ]);
                }

                $this->logger->info('Ders eklendi', ['id' => $lesson->id, 'name' => $lesson->name]);
                return $lesson->id;
            });
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu kodda ders zaten kayıtlı. Lütfen farklı bir kod giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }

    }

    /**
     * Controller'dan gelen verilerle dersi günceller (Business logic).
     * Hoca sadece kontenjan ve derslik tipi güncelleyebilir.
     *
     * @param int $id Güncellenecek ders ID'si
     * @param LessonDTO $dto Güncelleme verilerini taşıyan DTO
     * @param bool $isLecturerOwnLesson Hoca kendi dersini mi güncelliyor?
     * @return int Güncellenen dersin ID'si
     * @throws Exception
     */
    public function updateLessonData(int $id, LessonDTO $dto, bool $isLecturerOwnLesson): int
    {
        /** @var Lesson $lessonFromDb */
        $lessonFromDb = (new LessonRepository())->find($id);
        if (!$lessonFromDb) {
            throw new Exception("Güncellenecek ders bulunamadı.");
        }

        if ($isLecturerOwnLesson) {
            Gate::authorize(PermissionType::UPDATE->value, $lessonFromDb, "Ders güncelleme yetkiniz yok");

            $lessonFromDb->size = (int) ($dto->size ?? $lessonFromDb->size ?? 0);
            if ($dto->classroom_type !== null) {
                $lessonFromDb->classroom_type = (int) $dto->classroom_type;
            }
        } else {
            Gate::authorize(PermissionType::UPDATE->value, $lessonFromDb, "Ders güncelleme yetkiniz yok");

            // program_id değişiyorsa, hedef programda aynı code+group_no kombinasyonu var mı kontrol et
            if (!empty($dto->program_id) && $dto->program_id !== $lessonFromDb->program_id) {
                $targetCode    = $dto->code ?? $lessonFromDb->code;
                $targetGroupNo = $dto->group_no ?? $lessonFromDb->group_no;
                /** @var Lesson|null $conflicting */
                $conflicting = (new LessonRepository())->findOneBy([
                    'code'       => $targetCode,
                    'program_id' => $dto->program_id,
                    'group_no'   => $targetGroupNo,
                    'id'         => ['!=' => $lessonFromDb->id],
                ]);
                if ($conflicting) {
                    // Hedef programda çakışan kayıt var: kaynağın verilerini çakışana aktar, kaynağı sil
                    return $this->mergeSourceIntoConflicting($lessonFromDb, $conflicting);
                }
            }

            $lessonFromDb->fill($dto->toArray());

            if (!empty($dto->lecturer_id)) {
                $semester = $dto->semester ?? getSettingValue('semester');
                $academicYear = $dto->academic_year ?? getSettingValue('academic_year');
                (new LessonAssignmentRepository())->upsert(
                    $lessonFromDb->id,
                    (int) $dto->lecturer_id,
                    $semester,
                    $academicYear
                );
                $this->logger->info('Ders hoca ataması güncellendi', [
                    'lesson_id'     => $lessonFromDb->id,
                    'lecturer_id'   => $dto->lecturer_id,
                    'semester'      => $semester,
                    'academic_year' => $academicYear
                ]);
            } elseif ($dto->unassign_lecturer) {
                $semester = $dto->semester ?? getSettingValue('semester');
                $academicYear = $dto->academic_year ?? getSettingValue('academic_year');
                if (!empty($semester) && !empty($academicYear)) {
                    (new LessonAssignmentRepository())->deleteAssignment(
                        $lessonFromDb->id,
                        $semester,
                        $academicYear
                    );
                    $this->logger->info('Ders hoca ataması kaldırıldı', [
                        'lesson_id'     => $lessonFromDb->id,
                        'semester'      => $semester,
                        'academic_year' => $academicYear
                    ]);
                }
            }
        }

        return $this->updateLesson($lessonFromDb);
    }



    /**
     * Mevcut dersi günceller.
     *
     * @param Lesson $lesson Güncellenmiş Lesson nesnesi
     * @return int Dersin ID'si
     * @throws Exception Duplicate lesson_code veya güncelleme hatası
     */
    public function updateLesson(Lesson $lesson): int
    {
        $this->logger->debug('Ders güncelleniyor', ['id' => $lesson->id]);

        try {
            return Database::transaction(function () use ($lesson) {
                $lesson->update();
                $this->logger->info('Ders güncellendi', ['id' => $lesson->id]);
                return $lesson->id;
            });
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Güncelleme başarısız: Bu ders (aynı kod ve grup no ile) belirtilen programda zaten mevcut olabilir.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Çakışma durumunda kaynak dersi hedef (çakışan) derse birleştirir.
     *
     * Yapılan işlemler (tek transaction içinde):
     *  1. Çakışan kaydın scalar alanları kaynaktan güncellenir.
     *  2. Kaynak derse ait lesson_assignment'lar çakışan derse upsert edilir.
     *  3. Kaynak dersi referans eden lesson_combination satırları çakışan id'sine taşınır.
     *  4. Kaynak dersin schedule kayıtları silinir, ardından kaynak ders silinir.
     *
     * @param Lesson $source     Taşınmak istenen (kaynak) ders
     * @param Lesson $conflicting Hedef programda zaten var olan (çakışan) ders
     * @return int               Güncellenen çakışan dersin ID'si
     * @throws Exception
     */
    private function mergeSourceIntoConflicting(Lesson $source, Lesson $conflicting): int
    {
        $this->logger->debug('Ders birleştirme başlatıldı: kaynak → çakışan', [
            'source_id'      => $source->id,
            'conflicting_id' => $conflicting->id,
        ]);

        Database::transaction(function () use ($source, $conflicting) {
            // 1. Kaynak dersin scalar alanlarını çakışan derse aktar
            //    Unique key alanları (code, group_no, program_id) değiştirilmez.
            $transferFields = ['name', 'size', 'hours', 'type', 'semester_no', 'department_id', 'classroom_type', 'building_id'];
            foreach ($transferFields as $field) {
                if (!is_null($source->$field)) {
                    $conflicting->$field = $source->$field;
                }
            }
            $conflicting->update();

            // 2. Kaynak derse ait lesson_assignment'ları çakışan derse taşı
            $assignmentRepo = new LessonAssignmentRepository();
            $sourceAssignments = $assignmentRepo->findByLesson($source->id);
            foreach ($sourceAssignments as $assignment) {
                $assignmentRepo->upsert(
                    $conflicting->id,
                    $assignment->lecturer_id,
                    $assignment->semester,
                    $assignment->academic_year
                );
            }

            // 3. lesson_combinations: kaynak dersi referans eden satırları çakışan derse yönlendir
            //    (CASCADE DELETE devreye girmeden önce yapılmalı)
            (new LessonCombinationRepository())->transferCombinations($source->id, $conflicting->id);

            // 4. Kaynak dersin schedule kayıtlarını temizle ve kaynağı sil
            $this->scheduleService->wipeResourceSchedules('lesson', $source->id);
            $source->delete();
        });

        $this->logger->info('Ders birleştirme tamamlandı', [
            'source_id'      => $source->id,
            'conflicting_id' => $conflicting->id,
        ]);

        return $conflicting->id;
    }

    /**
     * Dersi sistemden siler.
     * Silme işleminden önce, derse ait tüm program (schedule) kayıtlarını temizler.
     *
     * @param Lesson $lesson Silinecek ders nesnesi
     * @throws Exception
     */
    public function deleteLesson(Lesson $lesson): void
    {
        $this->logger->debug('Ders siliniyor', ['id' => $lesson->id]);

        try {
            Database::transaction(function () use ($lesson) {
                // 1. Derse ait tüm schedule (program) kayıtlarını temizle
                $this->scheduleService->wipeResourceSchedules('lesson', $lesson->id);

                // 2. Dersi sil
                $lesson->delete();
            });

            $this->logger->info('Ders başarıyla silindi', ['id' => $lesson->id]);
        } catch (Exception $e) {
            $this->logger->error('Ders silinirken hata oluştu', [
                'id' => $lesson->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Ders silinirken bir hata oluştu: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────
    // Child Lesson Yönetimi
    // ──────────────────────────────────────────

    /**
     * Çocuk dersi üst derse bağlar ve mevcut schedule'ı senkronize eder.
     *
     * @param CombineLessonDTO $dto
     * @throws Exception
     */
    public function combineLesson(CombineLessonDTO $dto): void
    {
        $parentLessonId = $dto->parentId;
        $childLessonId = $dto->childId;
        $slotsToSkip = $dto->getParsedItemsToRemove();

        $this->logger->debug('Ders birleştirme başlatıldı', [
            'parent_id' => $parentLessonId,
            'child_id' => $childLessonId,
        ]);

        Database::transaction(function () use ($parentLessonId, $childLessonId, $slotsToSkip, $dto) {
            /** @var Lesson $parentLesson */
            $parentLesson = (new Lesson())
                ->where(['id' => $parentLessonId])
                ->with([
                    'parentLesson' => ['with' => ['program']],
                    'childLessons',
                    'program',
                    'lecturer' => ['semester' => $dto->semester, 'academic_year' => $dto->academicYear]
                ])
                ->first()
                ?: throw new Exception("Birleştirilecek üst ders bulunamadı");

            /** @var Lesson $childLesson */
            $childLesson = (new Lesson())
                ->where(['id' => $childLessonId])
                ->with([
                    'parentLesson' => ['with' => ['program']],
                    'childLessons',
                    'program',
                    'lecturer' => ['semester' => $dto->semester, 'academic_year' => $dto->academicYear]
                ])
                ->first()
                ?: throw new Exception("Birleştirilecek ders bulunamadı");

            // Kendine bağlama kontrolü
            if ($parentLessonId === $childLessonId) {
                throw new Exception("Bir ders kendisiyle birleştirilemez.");
            }

            // Child zaten başka bir derse bağlıysa hata
            if ($childLesson->parentLesson) {
                throw new Exception(
                    $childLesson->getFullName(addCode: true, addProgram: true)
                    . " zaten "
                    . $childLesson->parentLesson->getFullName(addCode: true, addProgram: true)
                    . " dersine bağlı"
                );
            }

            // Eğer parent kendisi de bir child'sa gerçek ebeveyni kullan
            if ($parentLesson->parentLesson) {
                $parentLesson = $parentLesson->parentLesson
                    ?: throw new Exception("Bağlanmak istenilen dersin üst ebeveyni bulunamadı");
            }

            // Saat kontrolü
            if ($parentLesson->hours < $childLesson->hours) {
                throw new Exception(
                    "Üst dersin ({$parentLesson->hours} saat) ders saati, bağlanacak dersten ({$childLesson->hours} saat) az olamaz."
                );
            }

            // Çakışma kontrolü (Parent'ın kopyalanacak slotları child veya alt child'ların programlarında başka bir dersle çakışıyor mu?)
            $this->validateCombinationSlots($parentLesson, $childLesson, $slotsToSkip, $dto->semester, $dto->academicYear);
            foreach ($childLesson->childLessons as $grandChild) {
                $this->validateCombinationSlots($parentLesson, $grandChild, $slotsToSkip, $dto->semester, $dto->academicYear);
            }

            // Child'ın mevcut schedule'larını sil (bağlamadan ÖNCE — parent korunur)
            $this->scheduleService->wipeResourceSchedules('lesson', $childLesson->id);

            // Bağlantıyı kur (ders + sınav birleştirme)
            $combinationRepo = new LessonCombinationRepository();
            $combinationRepo->createLessonAndExamLink(
                $parentLesson->id,
                $childLesson->id,
                $dto->semester,
                $dto->academicYear
            );

            // Child'ın alt child'larını da parent'a bağla
            $reparentedChildren = [];
            foreach ($childLesson->childLessons as $grandChild) {
                $this->scheduleService->wipeResourceSchedules('lesson', $grandChild->id);
                $combinationRepo->reparentChild($grandChild->id, $parentLesson->id, $dto->semester, $dto->academicYear);
                $reparentedChildren[] = $grandChild;
            }

            // Parent'ın mevcut schedule'ı varsa child ve tüm reparent edilen dersler için kopyala
            $syncService = new ScheduleSyncService();
            $syncService->syncChildScheduleFromParent($parentLesson, $childLesson, $slotsToSkip);
            $syncService->syncExamChildFromParent($parentLesson, $childLesson);

            foreach ($reparentedChildren as $grandChild) {
                $syncService->syncChildScheduleFromParent($parentLesson, $grandChild, $slotsToSkip);
                $syncService->syncExamChildFromParent($parentLesson, $grandChild);
            }

            $this->logger->info('Ders birleştirildi', [
                'parent_id' => $parentLesson->id,
                'child_id' => $childLesson->id,
            ]);
        });
    }

    /**
     * Child dersin parent bağlantısını kaldırır ve tüm schedule kayıtlarını temizler.
     *
     * @param DeleteCombineLessonDTO $dto
     * @throws Exception
     */
    public function deleteParentLesson(DeleteCombineLessonDTO $dto): void
    {
        $lessonId = $dto->id;
        $this->logger->debug('Ders bağlantısı kaldırılıyor', ['lesson_id' => $lessonId]);

        /** @var Lesson $lesson */
        $lesson = (new Lesson())->find($lessonId)
            ?: throw new Exception("Ebeveyni silinecek ders bulunamadı");

        $this->scheduleService->wipeResourceSchedules('lesson', $lessonId);

        (new LessonCombinationRepository())->deleteLessonLink($lessonId, $dto->semester, $dto->academicYear);

        $this->logger->info('Ders bağlantısı kaldırıldı', ['lesson_id' => $lessonId]);
    }

    // ──────────────────────────────────────────
    // Sınav Birleştirme (exam_parent_lesson_id)
    // ──────────────────────────────────────────

    /**
     * Sınav birleştirme için aranabilir ders listesini (TomSelect AJAX formatında) döner.
     * Aynı akademik yıl ve dönemdeki dersleri getirir ve zaten bağlı olanları/kendisini eler.
     *
     * @param int $lessonId Dışlanacak ve baz alınacak dersin ID'si
     * @param string $search Arama terimi (TomSelect filtrelemesi için)
     * @return array Seçilebilir ders listesi
     * @throws Exception
     */
    public function getExamCombinableLessonsForSelect(int $lessonId, string $search = ''): array
    {
        if (!$lessonId) {
            throw new Exception("Ders ID belirtilmemiş");
        }

        $currentLesson = clone (new LessonRepository())->findLessonWithDetails($lessonId);

        if (!$currentLesson) {
            throw new Exception("Ders bulunamadı");
        }

        $semester = getSettingValue('semester');
        $academicYear = getSettingValue('academic_year');

        // Aynı akademik yıl ve dönemdeki dersleri al
        $lessons = (new LessonRepository())->getExamCombineLessonList(
            $currentLesson->id,
            $semester,
            $academicYear
        );


        // Zaten bağlı olanları ve kendisini filtrele
        $existingChildIds = array_map(fn($c) => $c->id, $currentLesson->examChildLessons);

        $result = [];
        foreach ($lessons as $lesson) {
            // Kendisi zaten bir exam child ise atla (zaten birleştirilmiş)
            if (!empty($lesson->examParentLesson) && $lesson->examParentLesson->id !== $currentLesson->id) {
                continue;
            }
            // Zaten bu derse bağlı olanları atla
            if (in_array($lesson->id, $existingChildIds)) {
                continue;
            }

            $label = $lesson->getFullName(addCode: true, addProgram: true, addSize: true);

            // TomSelect arama filtresi
            if ($search !== '' && stripos($label, $search) === false) {
                continue;
            }

            $result[] = [
                'id' => $lesson->id,
                'text' => $label,
                'size' => $lesson->size,
                'program' => $lesson->program->name ?? '',
            ];
        }

        return $result;
    }

    /**
     * Sınav için dersleri birleştirir (exam_parent_lesson_id).
     * Ders programını etkilemez, sadece sınav programında ortak sınav grubu oluşturur.
     * Hoca kısıtı yoktur — farklı hocaların dersleri birleştirilebilir.
     *
     * @param CombineExamLessonDTO $dto
     * @throws Exception
     */
    public function combineExamLesson(CombineExamLessonDTO $dto): void
    {
        $parentLessonId = $dto->parentId;
        $childLessonId = $dto->childId;

        $this->logger->debug('Sınav birleştirme başlatıldı', [
            'parent_id' => $parentLessonId,
            'child_id' => $childLessonId,
        ]);

        Database::transaction(function () use ($parentLessonId, $childLessonId, $dto) {
            /** @var Lesson $parentLesson */
            $parentLesson = (new Lesson())
                ->where(['id' => $parentLessonId])
                ->with(['examParentLesson', 'examChildLessons', 'program'])
                ->first()
                ?: throw new Exception("Birleştirilecek üst ders bulunamadı");

            /** @var Lesson $childLesson */
            $childLesson = (new Lesson())
                ->where(['id' => $childLessonId])
                ->with(['examParentLesson', 'examChildLessons', 'program'])
                ->first()
                ?: throw new Exception("Birleştirilecek ders bulunamadı");

            // Kendine bağlama kontrolü
            if ($parentLessonId === $childLessonId) {
                throw new Exception("Bir ders kendisiyle birleştirilemez.");
            }

            // Child zaten başka bir sınav ebeveynine bağlıysa hata
            if ($childLesson->examParentLesson) {
                throw new Exception(
                    $childLesson->getFullName(addCode: true, addProgram: true)
                    . " zaten sınav programında "
                    . $childLesson->examParentLesson->getFullName(addCode: true, addProgram: true)
                    . " dersine bağlı"
                );
            }

            // Eğer parent kendisi de bir exam child'sa gerçek ebeveyni kullan
            if ($parentLesson->examParentLesson) {
                $parentLesson = $parentLesson->examParentLesson
                    ?: throw new Exception("Bağlanmak istenilen dersin sınav üst ebeveyni bulunamadı");
            }

            // Child'ın mevcut sınav schedule'larını temizle
            $examTypes = ExamType::values();
            $scheduleService = $this->scheduleService;
            $examSchedules = (new Schedule())->get()->where([
                'owner_type' => OwnerType::LESSON->value,
                'owner_id' => $childLesson->id,
                'type' => ['in' => $examTypes]
            ])->all();
            foreach ($examSchedules as $examSchedule) {
                $items = (new ScheduleItem())->get()->where(['schedule_id' => $examSchedule->id])->all();
                foreach ($items as $item) {
                    $scheduleService->deleteScheduleItems([ScheduleItemDTO::fromArray($item->getArray())], false);
                }
            }

            // Sınav birleştirme bağlantısını kur
            $combinationRepo = new LessonCombinationRepository();
            $combinationRepo->createExamLink(
                $parentLesson->id,
                $childLesson->id,
                $dto->semester,
                $dto->academicYear
            );

            // Child'ın exam alt child'larını da parent'a bağla
            foreach ($childLesson->examChildLessons as $grandChild) {
                $combinationRepo->reparentExamChild($grandChild->id, $parentLesson->id, $dto->semester, $dto->academicYear);
            }

            // Parent'ın mevcut sınav programı varsa child için kopyala
            (new ScheduleSyncService())->syncExamChildFromParent($parentLesson, $childLesson);

            $this->logger->info('Sınav birleştirme tamamlandı', [
                'parent_id' => $parentLesson->id,
                'child_id' => $childLesson->id,
            ]);
        });
    }

    /**
     * Sınav birleştirme bağlantısını kaldırır.
     * Ders programını etkilemez, sadece sınav schedule'larını temizler.
     *
     * @param DeleteCombineLessonDTO $dto
     * @throws Exception
     */
    public function deleteExamParentLesson(DeleteCombineLessonDTO $dto): void
    {
        $lessonId = $dto->id;
        $this->logger->debug('Sınav birleştirme bağlantısı kaldırılıyor', ['lesson_id' => $lessonId]);

        /** @var Lesson $lesson */
        $lesson = (new Lesson())->find($lessonId)
            ?: throw new Exception("Sınav ebeveyni silinecek ders bulunamadı");

        // Sadece sınav schedule'larını temizle
        $examTypes = ExamType::values();
        $scheduleService = $this->scheduleService;
        $examSchedules = (new Schedule())->get()->where([
            'owner_type' => OwnerType::LESSON->value,
            'owner_id' => $lesson->id,
            'type' => ['in' => $examTypes]
        ])->all();
        foreach ($examSchedules as $examSchedule) {
            $items = (new ScheduleItem())->get()->where(['schedule_id' => $examSchedule->id])->all();
            foreach ($items as $item) {
                $scheduleService->deleteScheduleItems([ScheduleItemDTO::fromArray($item->getArray())], false);
            }
        }

        (new LessonCombinationRepository())->deleteExamLink($lessonId, $dto->semester, $dto->academicYear);

        $this->logger->info('Sınav birleştirme bağlantısı kaldırıldı', ['lesson_id' => $lessonId]);
    }

    /**
     * Ders birleştirme önizleme — DB değişikliği yapmaz.
     * Saat farkı varsa parent'ın schedule item'larını bireysel saat dilimleri olarak döner.
     *
     * @param CombineLessonDTO $dto
     * @return array
     * @throws Exception
     */
    public function previewCombineLesson(CombineLessonDTO $dto): array
    {
        if (!$dto->parentId || !$dto->childId) {
            throw new Exception("Birleştirmek için dersler belirtilmemiş");
        }

        $parentLesson = (new Lesson())->find($dto->parentId)
            ?: throw new Exception("Üst ders bulunamadı");
        $childLesson = (new Lesson())->find($dto->childId)
            ?: throw new Exception("Bağlanacak ders bulunamadı");
        $semester = $dto->semester !== '' ? $dto->semester : getSettingValue('semester');
        $academicYear = $dto->academicYear !== '' ? $dto->academicYear : getSettingValue('academic_year');

        // Parent'ın ders programı var mı?
        $parentSchedule = (new Schedule())
            ->get()
            ->where([
                'owner_type' => OwnerType::LESSON->value,
                'owner_id' => $parentLesson->id,
                'type' => 'lesson',
                'semester' => $semester,
                'academic_year' => $academicYear,
            ])
            ->with(['items'])
            ->first();

        // Dönem bazlı bulunamazsa genel lesson schedule'ı dene
        if (!$parentSchedule) {
            $parentSchedule = (new Schedule())
                ->get()
                ->where([
                    'owner_type' => OwnerType::LESSON->value,
                    'owner_id' => $parentLesson->id,
                    'type' => 'lesson',
                ])
                ->with(['items'])
                ->first();
        }

        $duration = (int) getSettingValue('duration', 'lesson', 50); // dakika
        $break = (int) getSettingValue('break', 'lesson', 10);    // dakika

        $slots = [];
        if ($parentSchedule && !empty($parentSchedule->items)) {
            foreach ($parentSchedule->items as $item) {
                if (in_array($item->status, [ScheduleItemStatus::UNAVAILABLE->value, ScheduleItemStatus::PREFERRED->value])) {
                    continue;
                }
                $start = \DateTime::createFromFormat('H:i:s', $item->start_time)
                    ?: \DateTime::createFromFormat('H:i', $item->start_time);
                if (!$start)
                    continue;

                // İtem kaç saat içeriyor?
                $slotStart = clone $start;
                $slotIndex = 0;

                // Saatleri tek tek üret: süre+mola adımlarıyla
                while (true) {
                    $slotEnd = clone $slotStart;
                    $slotEnd->modify("+{$duration} minutes");

                    $slots[] = [
                        'id' => "{$item->id}_{$slotIndex}",
                        'item_id' => $item->id,
                        'slot_index' => $slotIndex,
                        'day_name' => Schedule::getdayName("day{$item->day_index}"),
                        'day_index' => $item->day_index,
                        'start_time' => $slotStart->format('H:i'),
                        'end_time' => $slotEnd->format('H:i'),
                    ];

                    // Bir sonraki slot başlangıcı: mola ekle
                    $slotStart = clone $slotEnd;
                    $slotStart->modify("+{$break} minutes");
                    $slotIndex++;

                    // Item'in bitiş saatini geçti mi? (mola süresini tolere et)
                    $itemEnd = \DateTime::createFromFormat('H:i:s', $item->end_time)
                        ?: \DateTime::createFromFormat('H:i', $item->end_time);
                    if (!$itemEnd || $slotStart >= $itemEnd)
                        break;
                }
            }
        }

        // Çakışmaları denetle (child dersin programında bu slotlarda başka ders var mı?)
        $conflictingSlots = [];
        foreach ($slots as &$slot) {
            $conflict = $this->checkProgramScheduleConflict(
                $childLesson,
                $semester,
                $academicYear,
                $slot['day_index'],
                $slot['start_time'],
                $slot['end_time']
            );
            if ($conflict) {
                $slot['has_conflict'] = true;
                $slot['conflict_reason'] = "'{$conflict['lesson_name']}' dersi ile çakışıyor";
                $conflictingSlots[] = $slot;
            } else {
                $slot['has_conflict'] = false;
            }
        }
        unset($slot);

        $hoursDiff = $parentLesson->hours - $childLesson->hours;

        // Eğer saat farkı yoksa (tüm slotlar aktarılacaksa) ve en az bir slot çakışıyorsa birleştirmeyi engelle
        if ($hoursDiff <= 0) {
            if (!empty($conflictingSlots)) {
                $c = $conflictingSlots[0];
                throw new ScheduleConflictException(
                    "Ders programında çakışma tespit edildi: " .
                    ($childLesson->program?->name ?? 'Program') . " programında " .
                    "{$c['day_name']} {$c['start_time']}–{$c['end_time']} saatlerinde {$c['conflict_reason']}. " .
                    "Dersler birleştirilemez."
                );
            }
            return ['needs_confirmation' => false];
        }

        // Saat farkı varsa: Kullanıcı $hoursDiff kadar slot seçecek (kopyalanmayacak olanlar).
        // Eğer çakışmayan slot sayısı child dersin saati için yetersizse kaçınılmaz çakışma vardır!
        $nonConflictingCount = count($slots) - count($conflictingSlots);
        if ($nonConflictingCount < $childLesson->hours && !empty($conflictingSlots)) {
            $c = $conflictingSlots[0];
            throw new ScheduleConflictException(
                "Ders programında çakışma tespit edildi: " .
                ($childLesson->program?->name ?? 'Program') . " programında " .
                "{$c['day_name']} {$c['start_time']}–{$c['end_time']} saatlerinde {$c['conflict_reason']}. " .
                "Kalan uygun saatler bağlanacak dersin süresini ({$childLesson->hours} saat) karşılamadığından dersler birleştirilemez."
            );
        }

        if (empty($slots)) {
            return ['needs_confirmation' => false];
        }

        // Gün ve saate göre sırala
        usort($slots, fn($a, $b) => $a['day_index'] <=> $b['day_index'] ?: $a['start_time'] <=> $b['start_time']);

        // Sıralanmış slotlara saat numarası ver
        foreach ($slots as $index => &$slot) {
            $slot['slot_number'] = $index + 1;
            $slot['slot_name'] = "{$slot['day_name']} {$slot['start_time']} – {$slot['end_time']}";
            if (!empty($slot['has_conflict'])) {
                $slot['slot_name'] .= " ({$slot['conflict_reason']})";
            }
        }
        unset($slot);

        return [
            'needs_confirmation' => true,
            'hours_diff' => $hoursDiff,
            'parent_hours' => $parentLesson->hours,
            'child_hours' => $childLesson->hours,
            'items' => $slots,
        ];
    }

    /**
     * Bağlanacak dersin bölüm ders programında belirtilen gün ve saat diliminde başka bir ders olup olmadığını denetler.
     *
     * @param Lesson $childLesson
     * @param string $semester
     * @param string $academicYear
     * @param int $dayIndex
     * @param string $startTime
     * @param string $endTime
     * @return array|null Çakışma bilgisi veya null
     */
    public function checkProgramScheduleConflict(
        Lesson $childLesson,
        string $semester,
        string $academicYear,
        int $dayIndex,
        string $startTime,
        string $endTime
    ): ?array {
        if (!$childLesson->program_id || !$childLesson->semester_no) {
            return null;
        }

        $conditions = [
            'owner_type'    => OwnerType::PROGRAM->value,
            'owner_id'      => $childLesson->program_id,
            'semester_no'   => $childLesson->semester_no,
            'semester'      => $semester,
            'academic_year' => $academicYear,
            'type'          => 'lesson',
        ];

        /** @var Schedule|null $programSchedule */
        $programSchedule = (new Schedule())->get()->where($conditions)->with(['items'])->first();
        if (!$programSchedule || empty($programSchedule->items)) {
            return null;
        }

        $startTimeStr = substr($startTime, 0, 5);
        $endTimeStr = substr($endTime, 0, 5);

        foreach ($programSchedule->items as $item) {
            if ((int)$item->day_index !== $dayIndex) {
                continue;
            }
            if (in_array($item->status, [ScheduleItemStatus::UNAVAILABLE->value, ScheduleItemStatus::PREFERRED->value])) {
                continue;
            }

            $exStart = substr($item->start_time, 0, 5);
            $exEnd = substr($item->end_time, 0, 5);

            if (TimeHelper::isOverlapping($startTimeStr, $endTimeStr, $exStart, $exEnd)) {
                $data = is_array($item->data) ? $item->data : (unserialize($item->data) ?: []);
                $lessonIds = array_filter(array_column($data, 'lesson_id'));

                // Eğer tek ders varsa ve o da bağlanacak child ders ise, birleştirme sırasında zaten wipe edilecek; çakışma sayılmaz
                if (!empty($lessonIds) && in_array($childLesson->id, $lessonIds) && count($lessonIds) === 1) {
                    continue;
                }

                $conflictingLessonId = null;
                foreach ($lessonIds as $lid) {
                    if ($lid != $childLesson->id) {
                        $conflictingLessonId = $lid;
                        break;
                    }
                }

                $conflictingLesson = $conflictingLessonId ? (new Lesson())->find($conflictingLessonId) : null;
                $conflictingName = $conflictingLesson ? $conflictingLesson->getFullName(true) : "Başka bir ders";

                return [
                    'conflicting_item'     => $item,
                    'conflicting_schedule' => $programSchedule,
                    'lesson_name'          => $conflictingName,
                    'start_time'           => $exStart,
                    'end_time'             => $exEnd,
                    'day_index'            => $dayIndex,
                ];
            }
        }

        return null;
    }

    /**
     * Parent dersten child derse aktarılacak slotların child programında çakışma yaratıp yaratmadığını doğrular.
     * Çakışma varsa ScheduleConflictException fırlatır.
     *
     * @param Lesson $parentLesson
     * @param Lesson $childLesson
     * @param array $slotsToSkip
     * @param string $semester
     * @param string $academicYear
     * @throws ScheduleConflictException
     */
    public function validateCombinationSlots(
        Lesson $parentLesson,
        Lesson $childLesson,
        array $slotsToSkip,
        string $semester,
        string $academicYear
    ): void {
        $parentSchedule = (new Schedule())
            ->get()
            ->where([
                'owner_type'    => OwnerType::LESSON->value,
                'owner_id'      => $parentLesson->id,
                'type'          => 'lesson',
                'semester'      => $semester,
                'academic_year' => $academicYear,
            ])
            ->with(['items'])
            ->first();

        if (!$parentSchedule) {
            $parentSchedule = (new Schedule())
                ->get()
                ->where([
                    'owner_type' => OwnerType::LESSON->value,
                    'owner_id'   => $parentLesson->id,
                    'type'       => 'lesson',
                ])
                ->with(['items'])
                ->first();
        }

        if (!$parentSchedule || empty($parentSchedule->items)) {
            return;
        }

        $duration = (int) getSettingValue('duration', 'lesson', 50);
        $break = (int) getSettingValue('break', 'lesson', 10);

        foreach ($parentSchedule->items as $item) {
            if (in_array($item->status, [ScheduleItemStatus::UNAVAILABLE->value, ScheduleItemStatus::PREFERRED->value])) {
                continue;
            }

            $skippedSlots = $slotsToSkip[$item->id] ?? [];

            $start = \DateTime::createFromFormat('H:i:s', $item->start_time)
                ?: \DateTime::createFromFormat('H:i', $item->start_time);
            if (!$start) {
                continue;
            }

            $slotStart = clone $start;
            $slotIndex = 0;

            while (true) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$duration} minutes");

                if (!in_array($slotIndex, $skippedSlots)) {
                    $startTime = $slotStart->format('H:i');
                    $endTime = $slotEnd->format('H:i');

                    $conflict = $this->checkProgramScheduleConflict(
                        $childLesson,
                        $semester,
                        $academicYear,
                        $item->day_index,
                        $startTime,
                        $endTime
                    );

                    if ($conflict) {
                        $dayName = Schedule::getdayName("day{$item->day_index}");
                        throw new ScheduleConflictException(
                            "Ders programında çakışma tespit edildi: " .
                            ($conflict['conflicting_schedule']?->getScheduleScreenName() ?? 'Program') . " programında " .
                            "{$dayName} {$startTime}–{$endTime} saatlerinde '{$conflict['lesson_name']}' dersi bulunmaktadır. " .
                            "Dersler birleştirilemez.",
                            $conflict['conflicting_item'] ?? null,
                            $conflict['conflicting_schedule'] ?? null
                        );
                    }
                }

                $slotStart = clone $slotEnd;
                $slotStart->modify("+{$break} minutes");
                $slotIndex++;

                $itemEnd = \DateTime::createFromFormat('H:i:s', $item->end_time)
                    ?: \DateTime::createFromFormat('H:i', $item->end_time);
                if (!$itemEnd || $slotStart >= $itemEnd) {
                    break;
                }
            }
        }
    }

    // ──────────────────────────────────────────
    // Toplu İşlemler
    // ──────────────────────────────────────────

    /**
     * Birden fazla dersi toplu siler.
     *
     * @param BulkDeleteDTO|array $dtoOrIds
     * @return BulkActionResultDTO
     */
    public function bulkDelete(BulkDeleteDTO|array $dtoOrIds): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkDeleteDTO ? $dtoOrIds : new BulkDeleteDTO(ids: array_map('intval', (array)$dtoOrIds));
        $this->logger->debug('Toplu ders silme başlatıldı', ['ids' => $dto->ids]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                $lesson = (new Lesson())->find($id);
                if (!$lesson) {
                    $failed[$id] = "Ders bulunamadı.";
                    continue;
                }

                if (!Gate::check(PermissionType::DELETE->value, $lesson)) {
                    $failed[$id] = "Silme yetkiniz yok.";
                    continue;
                }

                $this->deleteLesson($lesson);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu ders silme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Birden fazla dersi toplu günceller.
     *
     * @param BulkUpdateDTO|array $dtoOrIds
     * @param array<string, mixed> $fields
     * @return BulkActionResultDTO
     */
    public function bulkUpdate(BulkUpdateDTO|array $dtoOrIds, array $fields = []): BulkActionResultDTO
    {
        $dto = $dtoOrIds instanceof BulkUpdateDTO
            ? $dtoOrIds
            : new BulkUpdateDTO(ids: array_map('intval', (array)$dtoOrIds), fields: $fields);

        $this->logger->debug('Toplu ders güncelleme başlatıldı', ['ids' => $dto->ids, 'fields' => $dto->fields]);

        $success = [];
        $failed = [];

        foreach ($dto->ids as $id) {
            try {
                // Toplu düzenlemede tüm yetki ve ilişkili tablo (LessonAssignment vb) kontrollerini 
                // tekil güncelleme yapan updateLessonData metodu üzerinden yürüt.
                $lessonDto = LessonDTO::fromArray($dto->fields);
                $this->updateLessonData($id, $lessonDto, false);
                $success[] = $id;
            } catch (Exception $e) {
                $failed[$id] = $e->getMessage();
            }
        }

        $this->logger->info('Toplu ders güncelleme tamamlandı', [
            'success_count' => count($success),
            'failed_count'  => count($failed)
        ]);

        return new BulkActionResultDTO(success: $success, failed: $failed);
    }

    /**
     * Seçilen programa, döneme ve akademik yıla ait dersleri hoca atamalarıyla birlikte getirir.
     *
     * @param int $programId
     * @param string $semester
     * @param string $academicYear
     * @return Lesson[]
     * @throws Exception
     */
    public function getLessonsByProgramAndPeriod(int $programId, string $semester, string $academicYear, ?int $totalSemesters = null): array
    {
        $totalSemesters ??= (new ProgramRepository())->getProgramTotalSemesters($programId);
        $validSemesters = getSemesterNumbers($semester, $totalSemesters, true);

        return (new LessonRepository())->getLessonsByProgramAndSemesters($programId, $validSemesters, $semester, $academicYear);
    }
}