<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Exceptions\NotFoundException;
use App\Exceptions\AppException;

class NotFoundExceptionTest extends TestCase
{
    public function test_not_found_exception_instantiation(): void
    {
        $exception = new NotFoundException("Sayfa bulunamadı.");

        $this->assertInstanceOf(AppException::class, $exception);
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals("Sayfa bulunamadı.", $exception->getMessage());
    }
}
