<?php

namespace App\Models;

use App\Core\Model;

class LessonCombination extends Model
{
    protected string $table_name = 'lesson_combinations';
    public int $parent_lesson_id;
    public int $child_lesson_id;
    public string $type;
    public string $semester;
    public string $academic_year;

}
