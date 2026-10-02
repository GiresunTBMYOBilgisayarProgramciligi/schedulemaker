<?php

namespace App\Attributes;

use Attribute;

/**
 * Bu metot veya sınıfın CSRF doğrulamasından muaf tutulacağını belirtir (örn. webhook veya public callback'ler).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class WithoutCsrf
{
}
