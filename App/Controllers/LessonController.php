<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use App\Models\Schedule;
use Exception;
use PDO;
use PDOException;
use function App\Helpers\isAuthorized;

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
        if (!is_null($lecturer_id)) $filters["lecturer_id"] = $lecturer_id;
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
            if (!isAuthorized("submanager", false, $new_lesson)) {
                throw new Exception("Yeni Ders oluşturma yetkiniz yok");
            }

            // Yeni kullanıcı verilerini bir dizi olarak alın
            $new_lesson_arr = $new_lesson->getArray(['table_name', 'database', 'id', "register_date", "last_login"]);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_lesson_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_lesson_arr);
            return $this->database->lastInsertId();
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu kodda ders zaten kayıtlı. Lütfen farklı bir kod giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * @param Lesson $lesson
     * @return int
     * @throws Exception
     */
    public function updateLesson(Lesson $lesson): int
    {
        try {
            if (!isAuthorized("submanager", false, $lesson)) {
                throw new Exception("Ders güncelleme yetkiniz yok");
            }

            // Lesson nesnesinden filtrelenmiş verileri al
            $lessonData = $lesson->getArray(['table_name', 'database', 'id']);
            // Sorgu ve placeholder'lar için başlangıç ayarları
            $columns = [];
            $parameters = [];

            foreach ($lessonData as $key => $value) {
                $columns[] = "$key = :$key";
                $parameters[$key] = $value; // NULL dahil her değeri parametre olarak alıyoruz
            }

            // WHERE koşulu ekleniyor
            $parameters["id"] = $lesson->id;

            // Dinamik SQL sorgusu oluştur
            $query = sprintf(
                "UPDATE %s SET %s WHERE id = :id",
                $this->table_name,
                implode(", ", $columns)
            );

            // Sorguyu hazırla ve çalıştır
            $stmt = $this->database->prepare($query);
            $stmt->execute($parameters);
            return $lesson->id;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu kodda zaten kayıtlı. Lütfen farklı bir kod giriniz.");
            } else {
                throw new Exception($e->getMessage(), (int)$e->getCode(), $e);
            }
        }
    }

    /**
     * @param int $id Silinecek dersin id numarası
     * @throws Exception
     */
    public function delete(int $id): void
    {
        // ilişkili tüm programı sil
        $schedules = (new Schedule())->get()->where(["owner_type" => "lesson", "owner_id" => $id])->all();
        foreach ($schedules as $schedule) {
            $schedule->delete();
        }
        (new Lesson())->find($id)->delete();
    }

    /**
     * En yüksek dönem numarasını verir.
     * @return int
     * @throws Exception
     */
    public function getSemesterCount(): int
    {
        return $this->database->query("select max(semester_no) as semester_count from $this->table_name")->fetchColumn();
    }
}