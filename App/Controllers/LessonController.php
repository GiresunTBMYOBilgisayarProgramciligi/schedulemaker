<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use PDO;
use PDOException;

class LessonController extends Controller
{
    protected string $table_name = "lessons";

    public function getLesson($id)
    {
        if (!is_null($id)) {
            try {
                $u = $this->database->prepare("select * from $this->table_name where id=:id");
                $u->bindValue(":id", $id, PDO::PARAM_INT);
                $u->execute();
                $u = $u->fetch(\PDO::FETCH_ASSOC);
                if ($u) {
                    $lesson = new Lesson();
                    $lesson->fill($u);

                    return $lesson;
                } else throw new \Exception("Lesson not found");
            } catch (\Exception $e) {
                // todo sitede bildirim şeklinde bir hata mesajı gösterip silsin.
                echo $e->getMessage();
            }
        }
    }

    public function getTypeList()
    {
        return [
            "Zorunlu",
            "Seçmeli",
            "Üniversite Seçmeli"
        ];
    }

    public function getSeasonList()
    {
        return ["Güz", "Bahar", "Yaz"];
    }

    /**
     * @param ?int $lecturer_id Girildiğinde o kullanıcıya ait derslerin listesini döner
     * @return array
     */
    public function getLessonsList(?int $lecturer_id = null)
    {
        try {
            if (is_null($lecturer_id)) {
                $stmt = $this->database->prepare("select * from $this->table_name");
                $stmt->execute();
            } else {
                $stmt = $this->database->prepare("select * from $this->table_name where lecturer_id=:lecturer_id");
                $stmt->bindValue(":lecturer_id", $lecturer_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            $lessons_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $lessons = [];
            foreach ($lessons_list as $lesson_data) {
                $lesson = new Lesson();
                $lesson->fill($lesson_data);
                $lessons[] = $lesson;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            return [];
        }
        return $lessons;
    }

    /**
     * AjaxControllerdan gelen verilele yeni ders oluşturur
     * @param array $data
     * @return array
     */
    public function saveNew(Lesson $new_lesson): array
    {
        try {
            $q = $this->database->prepare(
                "INSERT INTO $this->table_name(code, name, size, hours, type, season lecturer_id, department_id, program_id) 
            values  (:code, :name, :size, :hours, :type, :season, :lecturer_id, :department_id, :program_id)");
            if ($q) {
                $new_lesson_arr = $new_lesson->getArray(['table_name', 'database', 'id']);
                $q->execute($new_lesson_arr);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu kodda ders zaten kayıtlı. Lütfen farklı bir kod giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }

        return ["status" => "success"];
    }

    public function updateLesson(Lesson $lesson)
    {
        try {
            $lessonData = $lesson->getArray(['table_name', 'database', 'id']);
            $i = 0;
            $query = "UPDATE $this->table_name SET ";
            foreach ($lessonData as $k => $v) {
                if (is_null($v)) continue;
                if (++$i === count($lessonData)) $query .= $k . "=:" . $k . " ";
                else $query .= $k . "=:" . $k . ", ";
            }
            $query .= " WHERE id=:id";
            $lessonData["id"] = $lesson->id;
            $u = $this->database->prepare($query);
            $u->execute($lessonData);


        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                return ["status" => "error", "msg" => "Bu kodda zaten kayıtlı. Lütfen farklı bir kod giriniz." . $e->getMessage()];
            } else {
                return ["status" => "error", "msg" => $e->getMessage() . $e->getLine()];
            }
        }
        return ["status" => "success"];
    }
}