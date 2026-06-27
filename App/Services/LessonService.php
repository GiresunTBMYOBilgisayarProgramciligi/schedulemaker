<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Core\Database;
use Exception;
use function App\Helpers\getSettingValue;
//todo  Services içerisinde Schedule klasörü içine taşınacak
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
    // ──────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────

    /**
     * Yeni ders kaydeder.
     *
     * @param Lesson $lesson Doldurulmuş Lesson nesnesi
     * @return int Oluşturulan dersin ID'si
     * @throws Exception Duplicate lesson_code veya kayıt hatası
     */
    public function saveNew(Lesson $lesson): int
    {
        $this->logger->info('Yeni ders ekleniyor', ['name' => $lesson->name, 'code' => $lesson->code ?? null]);

        try {
            return Database::transaction(function () use ($lesson) {
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
                (new ScheduleService())->wipeResourceSchedules('lesson', $lesson->id);

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
    public function combineLesson(int $parentLessonId, int $childLessonId, array $slotsToSkip = []): void
    {
        $this->logger->info('Ders birleştirme başlatıldı', [
            'parent_id' => $parentLessonId,
            'child_id' => $childLessonId,
        ]);

        $isInitiator = !$this->db->inTransaction();
        if ($isInitiator) {
            $this->db->beginTransaction();
        }

        try {
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
            (new ScheduleService())->wipeResourceSchedules('lesson', $childLesson->id);

            // Bağlantıyı kur (ders + sınav birleştirme)
            $childLesson->parent_lesson_id = $parentLesson->id;
            $childLesson->exam_parent_lesson_id = $parentLesson->id;
            $childLesson->update();

            // Child'ın alt child'larını da parent'a bağla
            foreach ($childLesson->childLessons as $grandChild) {
                (new ScheduleService())->wipeResourceSchedules('lesson', $grandChild->id);
                $grandChild->parent_lesson_id = $parentLesson->id;
                $grandChild->exam_parent_lesson_id = $parentLesson->id;
                $grandChild->update();
            }

            // Parent'ın mevcut schedule'ı varsa child için item kopyala (seçilen slotlar hariç)
            $this->syncChildScheduleFromParent($parentLesson, $childLesson, $slotsToSkip);

            if ($isInitiator) {
                $this->db->commit();
            }

            $this->logger->info('Ders birleştirildi', [
                'parent_id' => $parentLesson->id,
                'child_id' => $childLesson->id,
            ]);
        } catch (Exception $e) {
            if ($isInitiator) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Child dersin parent bağlantısını kaldırır ve tüm schedule kayıtlarını temizler.
     *
     * @param int $lessonId Child dersin ID'si
     * @throws Exception
     */
    public function deleteParentLesson(int $lessonId): void
    {
        $this->logger->info('Ders bağlantısı kaldırılıyor', ['lesson_id' => $lessonId]);

        /** @var Lesson $lesson */
        $lesson = (new Lesson())->find($lessonId)
            ?: throw new Exception("Ebeveyni silinecek ders bulunamadı");

        (new ScheduleService())->wipeResourceSchedules('lesson', $lessonId);

        $lesson->parent_lesson_id = null;
        $lesson->update();

        $this->logger->info('Ders bağlantısı kaldırıldı', ['lesson_id' => $lessonId]);
    }

    // ──────────────────────────────────────────
    // Sınav Birleştirme (exam_parent_lesson_id)
    // ──────────────────────────────────────────

    /**
     * Sınav için dersleri birleştirir (exam_parent_lesson_id).
     * Ders programını etkilemez, sadece sınav programında ortak sınav grubu oluşturur.
     * Hoca kısıtı yoktur — farklı hocaların dersleri birleştirilebilir.
     *
     * @param int $parentLessonId Üst ders ID'si
     * @param int $childLessonId Alt ders ID'si
     * @throws Exception
     */
    public function combineExamLesson(int $parentLessonId, int $childLessonId): void
    {
        $this->logger->info('Sınav birleştirme başlatıldı', [
            'parent_id' => $parentLessonId,
            'child_id' => $childLessonId,
        ]);

        $isInitiator = !$this->db->inTransaction();
        if ($isInitiator) {
            $this->db->beginTransaction();
        }

        try {
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
            $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
            $scheduleService = new ScheduleService();
            $examSchedules = (new Schedule())->get()->where([
                'owner_type' => 'lesson',
                'owner_id' => $childLesson->id,
                'type' => ['in' => $examTypes]
            ])->all();
            foreach ($examSchedules as $examSchedule) {
                $items = (new ScheduleItem())->get()->where(['schedule_id' => $examSchedule->id])->all();
                foreach ($items as $item) {
                    $scheduleService->deleteScheduleItems([$item->getArray()], false);
                }
            }

            // Sınav birleştirme bağlantısını kur
            $childLesson->exam_parent_lesson_id = $parentLesson->id;
            $childLesson->update();

            // Child'ın exam alt child'larını da parent'a bağla
            foreach ($childLesson->examChildLessons as $grandChild) {
                $grandChild->exam_parent_lesson_id = $parentLesson->id;
                $grandChild->update();
            }

            // Parent'ın mevcut sınav programı varsa child için kopyala
            $this->syncExamChildFromParent($parentLesson, $childLesson);

            if ($isInitiator) {
                $this->db->commit();
            }

            $this->logger->info('Sınav birleştirme tamamlandı', [
                'parent_id' => $parentLesson->id,
                'child_id' => $childLesson->id,
            ]);
        } catch (Exception $e) {
            if ($isInitiator) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sınav birleştirme bağlantısını kaldırır.
     * Ders programını etkilemez, sadece sınav schedule'larını temizler.
     *
     * @param int $lessonId Child dersin ID'si
     * @throws Exception
     */
    public function deleteExamParentLesson(int $lessonId): void
    {
        $this->logger->info('Sınav birleştirme bağlantısı kaldırılıyor', ['lesson_id' => $lessonId]);

        /** @var Lesson $lesson */
        $lesson = (new Lesson())->find($lessonId)
            ?: throw new Exception("Sınav ebeveyni silinecek ders bulunamadı");

        // Sadece sınav schedule'larını temizle
        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];
        $scheduleService = new ScheduleService();
        $examSchedules = (new Schedule())->get()->where([
            'owner_type' => 'lesson',
            'owner_id' => $lesson->id,
            'type' => ['in' => $examTypes]
        ])->all();
        foreach ($examSchedules as $examSchedule) {
            $items = (new ScheduleItem())->get()->where(['schedule_id' => $examSchedule->id])->all();
            foreach ($items as $item) {
                $scheduleService->deleteScheduleItems([$item->getArray()], false);
            }
        }

        $lesson->exam_parent_lesson_id = null;
        $lesson->update();

        $this->logger->info('Sınav birleştirme bağlantısı kaldırıldı', ['lesson_id' => $lessonId]);
    }

    // ──────────────────────────────────────────
    // Yardımcı (Private)
    // ──────────────────────────────────────────

    /**
     * Parent dersin sınav schedule item'larını child için kopyalar.
     * Sadece ders ve program owner'larına kopyalama yapar.
     * Gözetmen/derslik atamaları kopyalanmaz — ExamService::saveExamScheduleItems
     * zaten exam child'ların owner'larını da kaydeder.
     *
     * @param Lesson $parentLesson
     * @param Lesson $childLesson
     * @throws \Exception
     */
    private function syncExamChildFromParent(Lesson $parentLesson, Lesson $childLesson): void
    {
        $examTypes = ['midterm-exam', 'final-exam', 'makeup-exam'];

        // Parent'ın sınav schedule'larını bul
        $parentExamSchedules = (new Schedule())->get()->where([
            'owner_type' => 'lesson',
            'owner_id' => $parentLesson->id,
            'type' => ['in' => $examTypes]
        ])->with(['items'])->all();

        if (empty($parentExamSchedules)) {
            return;
        }

        foreach ($parentExamSchedules as $parentSchedule) {
            if (empty($parentSchedule->items)) {
                continue;
            }

            // Child ders ve program owner'ları
            $owners = [
                ['type' => 'lesson', 'id' => $childLesson->id, 'semester_no' => null],
                ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no],
            ];

            foreach ($parentSchedule->items as $item) {
                // Sadece program/ders item'larını kopyala (gözetmen/derslik atamaları hariç)
                $detail = $item->detail;
                if (isset($detail['reference_type']) && $detail['reference_type'] === 'exam_assignment') {
                    continue;
                }

                foreach ($owners as $owner) {
                    if (!$owner['id']) {
                        continue;
                    }

                    $scheduleFilters = [
                        'owner_type' => $owner['type'],
                        'owner_id' => $owner['id'],
                        'semester' => $parentSchedule->semester,
                        'academic_year' => $parentSchedule->academic_year,
                        'type' => $parentSchedule->type,
                        'semester_no' => $owner['type'] === 'program' ? $owner['semester_no'] : null,
                    ];

                    $childSchedule = (new Schedule())->firstOrCreate($scheduleFilters);

                    $newItem = new ScheduleItem();
                    $newItem->schedule_id = $childSchedule->id;
                    $newItem->day_index = $item->day_index;
                    $newItem->week_index = $item->week_index;
                    $newItem->start_time = $item->start_time;
                    $newItem->end_time = $item->end_time;
                    $newItem->status = $item->status;
                    $newItem->data = [
                        [
                            'lesson_id' => $childLesson->id,
                            'lecturer_id' => null,
                            'classroom_id' => null,
                        ]
                    ];
                    $newItem->detail = $item->detail;
                    $newItem->create();
                }
            }
        }

        $this->logger->info('Sınav programı child\'a kopyalandı', [
            'parent_id' => $parentLesson->id,
            'child_id' => $childLesson->id,
        ]);
    }

    /**
     * Parent dersin schedule item'larını child için kopyalar.
     * Belirli slot'lar harici tutulabilir; saat aralıklı item'lar bireysel 1-saatlik item'lara ayrılır.
     *
     * @param Lesson $parentLesson
     * @param Lesson $childLesson
     * @param array  $slotsToSkip Kopyalanmayacak slotlar [item_id => [slot_index, ...]]
     * @throws Exception
     */
    private function syncChildScheduleFromParent(Lesson $parentLesson, Lesson $childLesson, array $slotsToSkip = []): void
    {
        /** @var Schedule $parentSchedule */
        $parentSchedule = (new Schedule())
            ->get()
            ->where(['owner_type' => 'lesson', 'owner_id' => $parentLesson->id])
            ->with(['items'])
            ->first();

        if (!$parentSchedule) {
            return;
        }

        $duration = (int) getSettingValue('duration', 'lesson', 50);
        $break    = (int) getSettingValue('break', 'lesson', 10);

        foreach ($parentSchedule->items as $item) {
            $skippedSlots = $slotsToSkip[$item->id] ?? [];

            if (empty($skippedSlots)) {
                // Hiç slot silinmiyor — item'ı olduğu gibi kopyala (mevcut davranış)
                $this->copyItemToChild($parentSchedule, $item, $this->buildChildItemData($item, $parentLesson, $childLesson), $childLesson);
                continue;
            }

            // Bazı slotlar silinecek — item'ı bireysel 1-saatlik parçalara ayır, seçilenleri atla
            $start = \DateTime::createFromFormat('H:i:s', $item->start_time)
                  ?: \DateTime::createFromFormat('H:i', $item->start_time);
            if (!$start) continue;

            $slotStart = clone $start;
            $slotIndex = 0;

            while (true) {
                $slotEnd = clone $slotStart;
                $slotEnd->modify("+{$duration} minutes");

                if (!in_array($slotIndex, $skippedSlots)) {
                    // Bu slot kopyalanacak: yeni baş/bitiş zamanlarıyla tek item oluştur
                    $partialItem = clone $item;
                    $partialItem->id         = null; // yeni kayıt
                    $partialItem->start_time = $slotStart->format('H:i:s');
                    $partialItem->end_time   = $slotEnd->format('H:i:s');
                    $this->copyItemToChild($parentSchedule, $partialItem, $this->buildChildItemData($item, $parentLesson, $childLesson), $childLesson);
                }

                $slotStart = clone $slotEnd;
                $slotStart->modify("+{$break} minutes");
                $slotIndex++;

                $itemEnd = \DateTime::createFromFormat('H:i:s', $item->end_time)
                        ?: \DateTime::createFromFormat('H:i', $item->end_time);
                if (!$itemEnd || $slotStart >= $itemEnd) break;
            }
        }
    }

    /**
     * Tek bir schedule item'\u0131 child ders için gerekli owner'lara kopyalar.
     */
    private function copyItemToChild(Schedule $parentSchedule, ScheduleItem $item, array $itemData, Lesson $childLesson): void
    {
        $owners = [
            ['type' => 'lesson', 'id' => $childLesson->id, 'semester_no' => null],
            ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no],
        ];

        foreach ($owners as $owner) {
            if (!$owner['id']) {
                continue;
            }

            $scheduleFilters = [
                'owner_type'   => $owner['type'],
                'owner_id'     => $owner['id'],
                'semester'     => $parentSchedule->semester,
                'academic_year' => $parentSchedule->academic_year,
                'type'         => $parentSchedule->type,
                'semester_no'  => $owner['type'] === 'program' ? $owner['semester_no'] : null,
            ];

            $childSchedule = (new Schedule())->firstOrCreate($scheduleFilters);

            $newItem = new ScheduleItem();
            $newItem->schedule_id = $childSchedule->id;
            $newItem->day_index   = $item->day_index;
            $newItem->start_time  = $item->start_time;
            $newItem->end_time    = $item->end_time;
            $newItem->status      = $item->status;
            $newItem->data        = $itemData;
            $newItem->detail      = $item->detail;
            $newItem->create();
        }
    }

    /**
     * Bir schedule item için child'a ait data dizisini oluşturur.
     * Group item'larda parent'ın lesson_id'sine ait slot bulunur, diğerlerinde ilk slot kullanılır.
     *
     * @param ScheduleItem $item
     * @param Lesson       $parentLesson
     * @param Lesson       $childLesson
     * @return array
     */
    private function buildChildItemData(ScheduleItem $item, Lesson $parentLesson, Lesson $childLesson): array
    {
        $itemData = [['lesson_id' => null, 'lecturer_id' => null, 'classroom_id' => null]];

        if ($item->status === 'group') {
            foreach ($item->getSlotDatas() as $slotData) {
                if ($slotData->lesson_id == $parentLesson->id) {
                    $itemData[0] = [
                        'lesson_id' => $childLesson->id,
                        'lecturer_id' => $childLesson->lecturer_id,
                        'classroom_id' => $slotData->classroom->id,
                    ];
                    break;
                }
            }
        } else {
            $slotData = $item->getSlotDatas()[0];
            $itemData[0] = [
                'lesson_id' => $childLesson->id,
                'lecturer_id' => $childLesson->lecturer_id,
                'classroom_id' => $slotData->classroom->id,
            ];
        }

        return $itemData;
    }
}
