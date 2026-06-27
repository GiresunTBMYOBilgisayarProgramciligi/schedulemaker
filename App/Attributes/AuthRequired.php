<?php

namespace App\Attributes;

use Attribute;

/**
 * Bu metot veya sınıfa sadece oturum açmış kullanıcıların erişebileceğini belirtir.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class AuthRequired
{
}
