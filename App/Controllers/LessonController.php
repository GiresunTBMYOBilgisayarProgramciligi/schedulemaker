<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use Exception;
use PDO;
use PDOException;

class LessonController extends Controller
{
    protected string $table_name = "lessons";

    protected string $modelName = "App\Models\Lesson";

    /**
     * Belirtilen id numarasına sahip ders Modeli döndürülür
     * @param $id
     * @return Lesson
     * @throws Exception
     */
    public function getLesson($id): Lesson
    {
        if (!is_null($id)) {
            try {
                $stmt = $this->database->prepare("select * from $this->table_name where id=:id");
                $stmt->bindValue(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $lessonData = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($lessonData) {
                    $lesson = new Lesson();
                    $lesson->fill($lessonData);

                    return $lesson;
                } else throw new Exception("Lesson not found");
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } else throw new Exception("Ders id numarası belirtilmelidir");
    }

    /**
     * todo Sayısal değer olarak kullanılabilir.
     * Dersin türünü seçmek için kullanılacak diziyi döner
     * @return string[]
     */
    public function getTypeList(): array
    {
        return [
            "Zorunlu",
            "Seçmeli",
            "Üniversite Seçmeli"
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
        try {
            $filters = [];
            if (!is_null($lecturer_id)) $filters["lecturer_id"] = $lecturer_id;
            return $this->getListByFilters($filters);
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
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
                throw new Exception("Bu kodda ders zaten kayıtlı. Lütfen farklı bir kod giriniz.", $e->getCode());
            } else {
                throw $e;
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
            if ($stmt->rowCount() > 0) {
                return $lesson->id;
            } else throw new Exception("Ders Güncellenemedi");
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                // UNIQUE kısıtlaması ihlali durumu (duplicate entry hatası)
                throw new Exception("Bu kodda zaten kayıtlı. Lütfen farklı bir kod giriniz.", $e->getCode());
            } else {
                throw $e;
            }
        }
    }

    /**
     * En yüksek dönem numarasını verir.
     * @return int
     * @throws Exception
     */
    public function getSemesterCount(): int
    {
        try {
            return $this->database->query("select max(semester_no) as semester_count from $this->table_name")->fetchColumn();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
}