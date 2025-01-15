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
        $list = [];
        for ($i = 1; $i <= 12; $i++) {
            $list[] = "$i. Yarıyıl";
        }
        return $list;
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
     * Parametre olarak gelen alanlara göre otomatik koşul oluşturur ve koşullara uyan dersleri dizi olarak döner. Her bir eleman Lesson nesnesidir
     * todo her Controller a eklenebilir. ana sınıfa da eklenebilir.
     * @param array $filters
     * @return array
     */
    public function getLessonsListByFilters(array $filters)
    {
        try {
            // Koşullar ve parametreler
            $conditions = [];
            $parameters = [];

            // Parametrelerden WHERE koşullarını oluştur
            foreach ($filters as $column => $value) {
                $conditions[] = "$column = :$column";
                $parameters[":$column"] = $value;
            }
            // WHERE ifadesini oluştur
            $whereClause = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";

            // Sorguyu hazırla
            $sql = "SELECT * FROM $this->table_name $whereClause";
            $stmt = $this->database->prepare($sql);
            // Parametreleri bağla
            foreach ($parameters as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();

            // Verileri işle
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $lessons = [];

            if ($result) {
                foreach ($result as $lesson_data) {
                    $lesson = new Lesson();
                    $lesson->fill($lesson_data);
                    $lessons[] = $lesson;
                }
            }
            return $lessons;

        } catch (\Exception $e) {
            echo $e->getMessage();
            return [];
        }
    }

    /**
     * AjaxControllerdan gelen verilele yeni ders oluşturur
     * @param array $data
     * @return array
     */
    public function saveNew(Lesson $new_lesson): array
    {
        try {
            // Yeni kullanıcı verilerini bir dizi olarak alın
            $new_lesson_arr = $new_lesson->getArray(['table_name', 'database', 'id', "register_date", "last_login"]);

            // Dinamik SQL sorgusu oluştur
            $sql = $this->createInsertSQL($new_lesson_arr);
            // Hazırlama ve parametre bağlama
            $q = $this->database->prepare($sql);
            $q->execute($new_lesson_arr);
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
            // Lesson nesnesinden filtrelenmiş verileri al
            $lessonData = $lesson->getArray(['table_name', 'database', 'id'], true);

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