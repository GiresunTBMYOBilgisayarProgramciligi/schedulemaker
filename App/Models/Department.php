<?php

namespace App\Models;

use App\Controllers\ProgramController;
use App\Controllers\UserController;
use App\Core\Model;
use Exception;
use PDO;
use PDOException;

class Department extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?int $chairperson_id = null;

    public ?bool $active = null;
    protected array $excludeFromDb = [];
    protected string $table_name = "departments";


    /**
     * Bölüm başkanı Modelini döner. Eğer bölüm başkanı tanımlı değilse Boş Model döner
     * @return User | null Chair Person
     * @throws Exception
     */
    public function getChairperson(): ?User
    {
        if (is_null($this->chairperson_id)) {
            return new User(); // bölüm başkanı tanımlı değilse boş kullanıcı döndür.
        } else
            return (new User())->find($this->chairperson_id);
    }

    public function getProgramCount(): int
    {
        return (new Program())->get()->where(['department_id'=>$this->id])->count();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getPrograms(): array
    {
        return (new Program())->get()->where(['department_id'=>$this->id])->all();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLecturers(): array
    {
        return (new User())->get()->where(['department_id'=>$this->id,'!role'=>'user'])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLecturerCount(): mixed
    {
        return (new User())->get()->where(['department_id'=>$this->id,'!role'=>'user'])->count();
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLessons(): array
    {
        return (new Lesson())->get()->where(['department_id'=>$this->id])->all();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLessonCount(): mixed
    {
        return (new Lesson())->get()->where(['department_id'=>$this->id])->count();
    }
}