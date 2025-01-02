<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Lesson;
use PDO;

class LessonController extends Controller
{
    private $table_name = "lessons";
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

    /**
     * @return array
     */
    public function getLessons()
    {
        try {
            $q = $this->database->prepare("Select * From $this->table_name");
            $q->execute();
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo $e->getMessage();
            return [];
        }
    }
}