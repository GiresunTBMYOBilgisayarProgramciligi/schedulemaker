<?php

namespace App\Models;

use App\Core\Model;

class Setting extends Model
{
    public ?int $id = null;
    public ?string $key = null;
    public ?string $value = null;
    /**
     * enum ('string', 'integer', 'boolean', 'json', 'array')
     * @var string|null
     */
    public ?string $type = null;
    public ?string $group = null;
    public ?\DateTime $created_at = null;
    public ?\DateTime $updated_at = null;

    protected string $table_name='settings';

    protected array $dateFields = ['created_at', 'updated_at'];


}