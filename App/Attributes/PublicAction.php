<?php

namespace App\Attributes;

use Attribute;

/**
 * Bu metot veya sınıfa herkesin (ziyaretçilerin) erişebileceğini belirtir.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class PublicAction
{
}
