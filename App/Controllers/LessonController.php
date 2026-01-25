<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;

class LessonController extends Controller
{
    protected string $table_name = "lessons";

    protected string $modelName = "App\Models\Lesson";

    /**
     * Dersin türünü seçmek için kullanılacak diziyi döner
     * @return string[]
     */
    public function getTypeList(): array
    {
        return [
            1 => "Zorunlu",
            2 => "Seçmeli",
            3 => "Üniversite Seçmeli",
            4 => "Staj"
        ];
    }

    /**
     * Yarıyıl seçimi yaparken kıllanılacak verileri dizi olarak döner
     * @return array
     */
    public function getSemesterNoList(): array
    {
        $list = [];
        for ($i = 1; $i <= 12; $i++) {
            $list[$i] = "$i. Yarıyıl";
        }
        return $list;
    }

    /**
     * @param ?int $lecturer_id Girildiğinde o kullanıcıya ait derslerin listesini döner
     * @return array
     * @throws Exception
     */
    public function getLessonsList(?int $lecturer_id = null): array
    {
        $filters = [];
        if (!is_null($lecturer_id))
            $filters["lecturer_id"] = $lecturer_id;
        return $this->getListByFilters($filters);
    }

    /**
     * AjaxControllerdan gelen verilele yeni ders oluşturur
     * @param Lesson $new_lesson
     * @return int
     * @throws Exception
     */
    public function saveNew(Lesson $new_lesson): int
    {
        try {
            $new_lesson->create();
            return $new_lesson->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu kodda ders zaten kayıtlı. Lütfen farklı bir kod giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }
        }
    }

    public function updateLesson(Lesson $lesson): int
    {
        try {
            $lesson->update();
            return $lesson->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu kodda zaten kayıtlı. Lütfen farklı bir kod giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }
        }
    }

    /**
     * En yüksek dönem numarasını verir.
     * @return int|null
     */
    public function getMaxSemesterNo(): ?int
    {
        return $this->database->query("select max(semester_no) as semester_count from $this->table_name")->fetchColumn();
    }

    /**
     * @throws Exception
     */
    public function combineLesson(?int $parentLessonId = null, ?int $childLessonId = null): void
    {
        $this->database->beginTransaction();
        try {
            /**
             * @var Lesson $parentLesson
             * @var Lesson $childLesson
             */
            $parentLesson = (new Lesson())->where(["id" => $parentLessonId])->with(["parentLesson" => ['with' => ['program']], "childLessons", "program"])->first() ?: throw new Exception("Birleştirilecek üst ders bulunamadı");
            $childLesson = (new Lesson())->where(["id" => $childLessonId])->with(["parentLesson" => ['with' => ['program']], "childLessons", "program"])->first() ?: throw new Exception("Birleştirilecek ders bulunamadı");
            /*
             * istenilen bir ders zaten bir derse bağlı ise hata verir.
             */
            if ($childLesson->parentLesson) {
                throw new Exception($childLesson->program->name . " - " . $childLesson->name . " zaten " . $childLesson->parentLesson->program->name . " - " . $childLesson->parentLesson->getFullName() . " dersine bağlı");
            }
            /*
             * Bağlanmak istenilen ders zaten başka bir derse bağlı ise bağlantı üst ebeveyne yapılır
             */
            if ($parentLesson->parentLesson) {
                $parentLesson = $parentLesson->parentLesson ?: throw new Exception("Bağlanmak istenilen dersin üst ebeveyni bulunamadı");
            }

            /*
             * Saat kontrolü: Üst dersin saati çocuk dersten az olamaz.
             */
            if ($parentLesson->hours < $childLesson->hours) {
                throw new Exception("Üst dersin ({$parentLesson->hours} saat) ders saati, bağlanacak dersten ({$childLesson->hours} saat) az olamaz.");
            }

            /*
             * Çocuk dersin daha önceden kaydedilmiş bir programı varsa silinmeli.
             * Hoca, ders, program, derslik programlarının hepsinin silinmesi lazım.
             * ÖNEMLİ: Bu işlem dersler bağlanmadan ÖNCE yapılmalı, aksi takdirde ebeveyn dersin programı da silinir.
             */
            (new ScheduleController())->wipeResourceSchedules('lesson', $childLesson->id);

            // İlişkiyi güncelle
            $childLesson->parent_lesson_id = $parentLesson->id;
            $childLesson->update();

            //Başka derse bağlanan derse bağlı dersler varsa onlarda bu derse bağlanır
            foreach ($childLesson->childLessons as $child) {
                // Alt çocukların programlarını da temizle (Eğer varsa)
                (new ScheduleController())->wipeResourceSchedules('lesson', $child->id);

                $child->parent_lesson_id = $parentLesson->id;
                $child->update();
            }

            /**
             * Bağlanılan dersin ders programında bir kaydı varsa bu bağlanan ders için de kaydedilir
             * @var Schedule $parentSchedule
             */
            $parentSchedule = (new Schedule())->get()->where(['owner_type' => "lesson", "owner_id" => $parentLesson->id])->with(['items'])->first();

            if ($parentSchedule) {
                foreach ($parentSchedule->items as $item) {
                    // Item datası içindeki lesson_id'yi güncellemek için hazırlık
                    $itemData = [["lesson_id" => null, "lecturer_id" => null, "classroom_id" => null]];
                    //genel mantık olarak gruplu ders birleştirilmez ama yine de işlemler yapılsın 
                    if ($item->status === 'group') {
                        foreach ($item->getSlotDatas() as $slotData) {
                            if ($slotData->lesson_id == $parentLesson->id) {
                                $itemData[0] = [
                                    "lesson_id" => $childLesson->id,
                                    "lecturer_id" => $childLesson->lecturer_id,
                                    "classroom_id" => $slotData->classroom->id
                                ];
                            }
                        }
                    } else {
                        $slotData = $item->getSlotDatas()[0];
                        $itemData[0] = [
                            "lesson_id" => $childLesson->id,
                            "lecturer_id" => $childLesson->lecturer_id,
                            "classroom_id" => $slotData->classroom->id
                        ];
                    }

                    // Child Lesson ve Programı için item oluştur
                    $owners = [
                        ['type' => 'lesson', 'id' => $childLesson->id, 'semester_no' => null],
                        ['type' => 'program', 'id' => $childLesson->program_id, 'semester_no' => $childLesson->semester_no]
                    ];

                    foreach ($owners as $owner) {
                        if (!$owner['id'])
                            continue;

                        $scheduleFilters = [
                            'owner_type' => $owner['type'],
                            'owner_id' => $owner['id'],
                            'semester' => $parentSchedule->semester,
                            'academic_year' => $parentSchedule->academic_year,
                            'type' => $parentSchedule->type
                        ];

                        if ($owner['type'] == 'program') {
                            $scheduleFilters['semester_no'] = $owner['semester_no'];
                        } else {
                            $scheduleFilters['semester_no'] = null;
                        }

                        $childSchedule = (new Schedule())->firstOrCreate($scheduleFilters);

                        // Yeni Item oluştur
                        $newItem = new \App\Models\ScheduleItem();
                        $newItem->schedule_id = $childSchedule->id;
                        $newItem->day_index = $item->day_index;
                        $newItem->start_time = $item->start_time;
                        $newItem->end_time = $item->end_time;
                        $newItem->status = $item->status;
                        $newItem->data = $itemData;
                        $newItem->detail = $item->detail;
                        $newItem->create();
                    }
                }
            }
            $this->database->commit();
        } catch (Exception $e) {
            $this->database->rollBack();
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function deleteParentLesson(int $lessonId): void
    {
        /**
         * @var Lesson $lesson
         */
        $lesson = (new Lesson())->find($lessonId) ?: throw new Exception("Ebeveyni silinecek ders bulunamadı");

        // İlişkiyi kaldırmadan önce derse ait program kayıtlarını temizle
        (new ScheduleController())->wipeResourceSchedules('lesson', $lessonId);

        $lesson->parent_lesson_id = null;
        $lesson->update();
    }
}