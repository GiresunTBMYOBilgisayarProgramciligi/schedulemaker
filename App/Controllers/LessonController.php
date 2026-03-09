<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use Exception;

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

}