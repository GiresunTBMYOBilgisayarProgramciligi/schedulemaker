<?php
namespace App\Models;

use App\Core\Model;

class UserAffiliation extends Model
{
    public int $user_id;
    public ?int $unit_id = null;
    public ?int $department_id = null;
    public ?int $program_id = null;

    protected string $table_name = 'user_affiliations';

    public function __construct()
    {
        parent::__construct();
    }
}
