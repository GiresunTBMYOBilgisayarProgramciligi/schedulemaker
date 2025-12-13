<?php

namespace App\Models;

use App\Core\Model;

class Log extends Model
{
  protected string $table_name = 'logs';
  protected array $excludeFromDb = [];

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

  public function getSource(): string
  {
    $src = [];
    if (!empty($this->file))
      $src[] = basename($this->file) . ':' . $this->line;
    if (!empty($this->class))
      $src[] = $this->class;
    if (!empty($this->method))
      $src[] = $this->method;

    return htmlspecialchars(implode(' | ', $src));
  }

  public function getLevelHtml(): string
  {
    $this->level = htmlspecialchars($this->level);
    $levelText = match ($this->level) {
      'ERROR' => 'danger',
      'DEBUG' => 'secondary',
      default => mb_strtolower($this->level)
    };
    return '<span class="badge bg-' . $levelText . '">' . $this->level . '</span>';
  }

  public function getContextHtml(): ?string
  {
    $output = '<!-- Button trigger modal -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contextModal-' . $this->id . '">
                  Göster
                </button>
                
                <!-- Modal -->
                <div class="modal fade" id="contextModal-' . $this->id . '" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h1 class="modal-title fs-5" id="contextModal-' . $this->id . 'ModalLabel">Modal title</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        ';
    foreach (json_decode($this->context) as $key => $value) {
      $output .= '<p><strong>' . $key . '</strong>: <pre>' . var_export($value, true) . '</pre></p>';
    }
    $output .= '
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
                ';

    $output .= '</details>';
    return $output;


  }
}
