<?php

namespace App\Repositories;

use App\Models\Program;
use Exception;

class ProgramRepository extends BaseRepository
{
    protected string $modelClass = Program::class;

    /**
     * Ada göre program bulur.
     * 
     * @param string $name
     * @return Program|null
     * @throws Exception
     */
    public function findByName(string $name): ?Program
    {
        return $this->findOneBy(["name" => $name]);
    }
}
