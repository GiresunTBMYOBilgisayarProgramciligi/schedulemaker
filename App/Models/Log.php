<?php

namespace App\Models;

use App\Core\Model;

class Log extends Model
{
    protected string $table_name = 'logs';

    // Define public properties to allow Model::fill() to hydrate them
    public ?int $id = null;
    public ?string $created_at = null;
    public ?string $username = null;
    public ?int $user_id = null;
    public ?string $level = null;
    public ?string $channel = null;
    public ?string $message = null;
    public ?string $class = null;
    public ?string $method = null;
    public ?string $function = null;
    public ?string $file = null;
    public ?int $line = null;
    public ?string $url = null;
    public ?string $ip = null;
    public ?string $trace = null;
    public $context = null; // JSON/LONGTEXT
    public $extra = null;   // JSON/LONGTEXT
}
