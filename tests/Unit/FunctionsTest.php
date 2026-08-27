<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSemesterNumbers;

class FunctionsTest extends BaseTestCase
{
    public function testGetClassFromSemesterNo(): void
    {
        $this->assertEquals(1, getClassFromSemesterNo(1));
        $this->assertEquals(1, getClassFromSemesterNo(2));
        $this->assertEquals(2, getClassFromSemesterNo(3));
        $this->assertEquals(2, getClassFromSemesterNo(4));
        $this->assertEquals(3, getClassFromSemesterNo(5));
        $this->assertEquals(3, getClassFromSemesterNo(6));
        $this->assertEquals(4, getClassFromSemesterNo(7));
        $this->assertEquals(4, getClassFromSemesterNo(8));
    }

    public function testGetSemesterNumbersForGuzAndBahar(): void
    {
        $guz = getSemesterNumbers('Güz');
        foreach ($guz as $num) {
            $this->assertEquals(1, $num % 2, "Güz dönemi numaraları tek sayı olmalıdır: $num");
        }

        $bahar = getSemesterNumbers('Bahar');
        foreach ($bahar as $num) {
            $this->assertEquals(0, $num % 2, "Bahar dönemi numaraları çift sayı olmalıdır: $num");
        }
    }
}
