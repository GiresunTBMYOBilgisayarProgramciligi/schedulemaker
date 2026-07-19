<?php

namespace App\Services;

use App\Enums\PermissionType;

use App\Enums\ExamType;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Services\Schedule\ScheduleService;
use App\Services\Schedule\ScheduleSyncService;
use App\Core\Database;
use App\DTOs\CombineLessonDTO;
use App\DTOs\CombineExamLessonDTO;
use App\DTOs\DeleteCombineLessonDTO;
use App\Models\LessonCombination;
use App\DTOs\ScheduleItemDTO;
use function App\Helpers\getSettingValue;
use App\Repositories\LessonRepository;
use App\Core\Gate;
use App\DTOs\LessonDTO;
use App\Enums\OwnerType;
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
        $this->logger->info('Yeni ders ekleniyor', ['name' => $dto->name, 'code' => $dto->code ?? null]);

        try {
            return Database::transaction(function () use ($dto) {
                $lesson = new Lesson();
                $lesson->fill($dto->toArray());
                $lesson->create();
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
     * @param int $id Ders ID'si
     * @param array $requestData Güncellenecek veri
     * @param bool $isLecturerOwnLesson İşlemi yapanın kendi dersi olup olmadığı
     * @return int Güncellenen dersin ID'si
     * @throws Exception
     */
    public function updateLessonData(int $id, array $requestData, bool $isLecturerOwnLesson): int
    {
        /** @var Lesson $lessonFromDb */
        $lessonFromDb = (new LessonRepository())->find($id);
        if (!$lessonFromDb) {
            throw new Exception("Güncellenecek ders bulunamadı.");
        }

        if ($isLecturerOwnLesson) {
            Gate::authorize(PermissionType::UPDATE->value, $lessonFromDb, "Ders güncelleme yetkiniz yok");
            
            $lessonFromDb->size = (int)($requestData['size'] ?? 0);
            if (isset($requestData['classroom_type']) && $requestData['classroom_type'] !== '') {
                $lessonFromDb->classroom_type = (int)$requestData['classroom_type'];
            }
        } else {
            Gate::authorize(PermissionType::UPDATE->value, $lessonFromDb, "Ders güncelleme yetkiniz yok");

            $dto = LessonDTO::fromArray($requestData);
            $lessonFromDb->fill($dto->toArray());
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
        $this->logger->info('Ders güncelleniyor', ['id' => $lesson->id]);

        try {
            return Database::transaction(function () use ($lesson) {
                $lesson->update();
                $this->logger->info('Ders güncellendi', ['id' => $lesson->id]);
                return $lesson->id;
            });
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                throw new Exception("Bu kodda zaten kayıtlı. Lütfen farklı bir kod giriniz.");
            }
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
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
        $this->logger->info('Ders siliniyor', ['id' => $lesson->id]);

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
     * @param int $parentLessonId
     * @param int $childLessonId
     * @param array $slotsToSkip Kopyalanmayacak slotlar [item_id => [slot_index, ...]]
     * @throws Exception
     */
    public function combineLesson(CombineLessonDTO $dto): void
    {
        $parentLessonId = $dto->parentId;
        $childLessonId = $dto->childId;
        $slotsToSkip = $dto->getParsedItemsToRemove();

        $this->logger->info('Ders birleştirme başlatıldı', [
            'parent_id' => $parentLessonId,
            'child_id' => $childLessonId,
        ]);

        Database::transaction(function () use ($parentLessonId, $childLessonId, $slotsToSkip, $dto) {
            /** @var Lesson $parentLesson */
            $parentLesson = (new Lesson())
                ->where(['id' => $parentLessonId])
                ->with(['parentLesson' => ['with' => ['program']], 'childLessons', 'program'])
                ->first()
                ?: throw new Exception("Birleştirilecek üst ders bulunamadı");

            /** @var Lesson $childLesson */
            $childLesson = (new Lesson())
                ->where(['id' => $childLessonId])
                ->with(['parentLesson' => ['with' => ['program']], 'childLessons', 'program'])
                ->first()
                ?: throw new Exception("Birleştirilecek ders bulunamadı");

            // Kendine bağlama kontrolü
            if ($parentLessonId === $childLessonId) {
                throw new Exception("Bir ders kendisiyle birleştirilemez.");
            }

            // Child zaten başka bir derse bağlıysa hata
            if ($childLesson->parentLesson) {
                throw new Exception(
                    $childLesson->getFullName(addCode:true,addProgram:true)
                    . " zaten "
                    . $childLesson->parentLesson->getFullName(addCode:true,addProgram:true)
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

            // Child'ın mevcut schedule'larını sil (bağlamadan ÖNCE — parent korunur)
            $this->scheduleService->wipeResourceSchedules('lesson', $childLesson->id);

            // Bağlantıyı kur (ders + sınav birleştirme)
            $lc1 = new LessonCombination();
            $lc1->parent_lesson_id = $parentLesson->id;
            $lc1->child_lesson_id = $childLesson->id;
            $lc1->type = 'lesson';
            $lc1->semester = $dto->semester;
            $lc1->academic_year = $dto->academicYear;
            $lc1->create();

            $lc2 = new LessonCombination();
            $lc2->parent_lesson_id = $parentLesson->id;
            $lc2->child_lesson_id = $childLesson->id;
            $lc2->type = 'exam';
            $lc2->semester = $dto->semester;
            $lc2->academic_year = $dto->academicYear;
            $lc2->create();

            // Child'ın alt child'larını da parent'a bağla
            foreach ($childLesson->childLessons as $grandChild) {
                $this->scheduleService->wipeResourceSchedules('lesson', $grandChild->id);
                $db = new LessonCombination();
                $db->get()->where([
                    'child_lesson_id' => $grandChild->id,
                    'semester' => $dto->semester,
                    'academic_year' => $dto->academicYear
                ])->update(['parent_lesson_id' => $parentLesson->id]);
            }

            // Parent'ın mevcut schedule'ı varsa child için item kopyala (seçilen slotlar hariç)
            (new ScheduleSyncService())->syncChildScheduleFromParent($parentLesson, $childLesson, $slotsToSkip);

            $this->logger->info('Ders birleştirildi', [
                'parent_id' => $parentLesson->id,
                'child_id' => $childLesson->id,
            ]);
        });
    }

    /**
     * Child dersin parent bağlantısını kaldırır ve tüm schedule kayıtlarını temizler.
     *
     * @param int $lessonId Child dersin ID'si
     * @throws Exception
     */
    public function deleteParentLesson(DeleteCombineLessonDTO $dto): void
    {
        $lessonId = $dto->id;
        $this->logger->info('Ders bağlantısı kaldırılıyor', ['lesson_id' => $lessonId]);

        /** @var Lesson $lesson */
        $lesson = (new Lesson())->find($lessonId)
            ?: throw new Exception("Ebeveyni silinecek ders bulunamadı");

        $this->scheduleService->wipeResourceSchedules('lesson', $lessonId);

        $db = new LessonCombination();
        $db->get()->where([
            'child_lesson_id' => $lessonId,
            'type' => 'lesson',
            'semester' => $dto->semester,
            'academic_year' => $dto->academicYear
        ])->delete();

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

        // Aynı akademik yıl ve dönemdeki dersleri al
        $lessons = (new LessonRepository())->getExamCombineLessonList(
            $currentLesson->id, 
            $currentLesson->semester, 
            $currentLesson->academic_year
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
                'id'    => $lesson->id,
                'text'  => $label,
                'size'  => $lesson->size,
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
     * @param int $parentLessonId Üst ders ID'si
     * @param int $childLessonId Alt ders ID'si
     * @throws Exception
     */
    public function combineExamLesson(CombineExamLessonDTO $dto): void
    {
        $parentLessonId = $dto->parentId;
        $childLessonId = $dto->childId;

        $this->logger->info('Sınav birleştirme başlatıldı', [
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
            $lc = new LessonCombination();
            $lc->parent_lesson_id = $parentLesson->id;
            $lc->child_lesson_id = $childLesson->id;
            $lc->type = 'exam';
            $lc->semester = $dto->semester;
            $lc->academic_year = $dto->academicYear;
            $lc->create();

            // Child'ın exam alt child'larını da parent'a bağla
            foreach ($childLesson->examChildLessons as $grandChild) {
                $db = new LessonCombination();
                $db->get()->where([
                    'child_lesson_id' => $grandChild->id,
                    'type' => 'exam',
                    'semester' => $dto->semester,
                    'academic_year' => $dto->academicYear
                ])->update(['parent_lesson_id' => $parentLesson->id]);
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
     * @param int $lessonId Child dersin ID'si
     * @throws Exception
     */
    public function deleteExamParentLesson(DeleteCombineLessonDTO $dto): void
    {
        $lessonId = $dto->id;
        $this->logger->info('Sınav birleştirme bağlantısı kaldırılıyor', ['lesson_id' => $lessonId]);

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

        $db = new LessonCombination();
        $db->get()->where([
            'child_lesson_id' => $lessonId,
            'type' => 'exam',
            'semester' => $dto->semester,
            'academic_year' => $dto->academicYear
        ])->delete();

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
        $childLesson  = (new Lesson())->find($dto->childId)
            ?: throw new Exception("Bağlanacak ders bulunamadı");

        $hoursDiff = $parentLesson->hours - $childLesson->hours;

        if ($hoursDiff <= 0) {
            return ['needs_confirmation' => false];
        }

        // Parent'ın ders programı var mı?
        $parentSchedule = (new Schedule())
            ->get()
            ->where(['owner_type' => OwnerType::LESSON->value, 'owner_id' => $dto->parentId])
            ->with(['items'])
            ->first();

        if (!$parentSchedule || empty($parentSchedule->items)) {
            return ['needs_confirmation' => false];
        }

        // Ayarlardan ders süresi ve mola bilgisini al
        $duration = (int) getSettingValue('duration', 'lesson', 50); // dakika
        $break    = (int) getSettingValue('break', 'lesson', 10);    // dakika

        $dayNames = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];

        $slots = [];
        foreach ($parentSchedule->items as $item) {
            if (in_array($item->status, ['unavailable', 'preferred'])) {
                continue;
            }
            $start = \DateTime::createFromFormat('H:i:s', $item->start_time)
                  ?: \DateTime::createFromFormat('H:i', $item->start_time);
            if (!$start) continue;

            // İtem kaç saat içeriyor?
            $slotStart = clone $start;
            $slotIndex = 0;

            // Saatleri tek tek üret: süre+mola adımlarıyla
            while (true) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$duration} minutes");

                $slots[] = [
                    'id'         => "{$item->id}_{$slotIndex}",
                    'item_id'    => $item->id,
                    'slot_index' => $slotIndex,
                    'day_name'   => $dayNames[$item->day_index] ?? "Gün {$item->day_index}",
                    'day_index'  => $item->day_index,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time'   => $slotEnd->format('H:i'),
                ];

                // Bir sonraki slot başlangıcı: mola ekle
                $slotStart = clone $slotEnd;
                $slotStart->modify("+{$break} minutes");
                $slotIndex++;

                // Item'in bitiş saatini geçti mi? (mola süresini tolere et)
                $itemEnd = \DateTime::createFromFormat('H:i:s', $item->end_time)
                        ?: \DateTime::createFromFormat('H:i', $item->end_time);
                if (!$itemEnd || $slotStart >= $itemEnd) break;
            }
        }

        // Gün ve saate göre sırala
        usort($slots, fn($a, $b) => $a['day_index'] <=> $b['day_index'] ?: $a['start_time'] <=> $b['start_time']);

        return [
            'needs_confirmation' => true,
            'hours_diff'         => $hoursDiff,
            'parent_hours'       => $parentLesson->hours,
            'child_hours'        => $childLesson->hours,
            'items'              => $slots,
        ];
    }
}