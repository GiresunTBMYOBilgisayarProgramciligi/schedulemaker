<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Models\Unit;
use App\Models\Department;
use App\Models\Program;
use function App\Helpers\getClassFromSemesterNo;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSemesterSelectOptions;
use function App\Helpers\getMaxSemesterNo;

class FunctionsTest extends BaseTestCase
{
    public function testGetClassFromSemesterNo(): void
    {
        $this->assertEquals('1', getClassFromSemesterNo(1));
        $this->assertEquals('1', getClassFromSemesterNo(2));
        $this->assertEquals('2', getClassFromSemesterNo(3));
        $this->assertEquals('2', getClassFromSemesterNo(4));
        $this->assertEquals('3', getClassFromSemesterNo(5));
        $this->assertEquals('3', getClassFromSemesterNo(6));
        $this->assertEquals('4', getClassFromSemesterNo(7));
        $this->assertEquals('4', getClassFromSemesterNo(8));
        $this->assertEquals('5', getClassFromSemesterNo(9));
        $this->assertEquals('5', getClassFromSemesterNo(10));
        $this->assertEquals('6', getClassFromSemesterNo(11));
        $this->assertEquals('6', getClassFromSemesterNo(12));
        $this->assertEquals('7', getClassFromSemesterNo(13));
    }

    public function testGetSemesterNumbersForGuzAndBahar(): void
    {
        $guz = getSemesterNumbers('Güz');
        $this->assertNotEmpty($guz);
        foreach ($guz as $num) {
            $this->assertEquals(1, $num % 2, "Güz dönemi numaraları tek sayı olmalıdır: $num");
        }

        $bahar = getSemesterNumbers('Bahar');
        $this->assertNotEmpty($bahar);
        foreach ($bahar as $num) {
            $this->assertEquals(0, $num % 2, "Bahar dönemi numaraları çift sayı olmalıdır: $num");
        }
    }

    public function testGetSemesterNumbersWithExplicitMaxSemester(): void
    {
        // MYO için maxSemester = 4
        $guzMyo = getSemesterNumbers('Güz', 4);
        $this->assertEquals([1, 3], $guzMyo);

        $baharMyo = getSemesterNumbers('Bahar', 4);
        $this->assertEquals([2, 4], $baharMyo);

        // Fakülte için maxSemester = 8
        $guzFaculty = getSemesterNumbers('Güz', 8);
        $this->assertEquals([1, 3, 5, 7], $guzFaculty);

        $baharFaculty = getSemesterNumbers('Bahar', 8);
        $this->assertEquals([2, 4, 6, 8], $baharFaculty);
    }

    public function testGetSemesterSelectOptionsForGuz(): void
    {
        $options = getSemesterSelectOptions('Güz', 12);
        $this->assertCount(6, $options);
        $this->assertArrayHasKey(1, $options);
        $this->assertArrayHasKey(3, $options);
        $this->assertArrayHasKey(5, $options);
        $this->assertArrayHasKey(7, $options);
        $this->assertArrayHasKey(9, $options);
        $this->assertArrayHasKey(11, $options);
        $this->assertEquals('1. Sınıf (1. Yarıyıl)', $options[1]);
        $this->assertEquals('2. Sınıf (3. Yarıyıl)', $options[3]);
        $this->assertEquals('6. Sınıf (11. Yarıyıl)', $options[11]);
    }

    public function testGetSemesterSelectOptionsForMyoLimit(): void
    {
        // MYO için maxSemester = 4 (sadece 1. ve 2. sınıf olmalı, 4. sınıf olmamalı)
        $guzOptions = getSemesterSelectOptions('Güz', 4);
        $this->assertCount(2, $guzOptions);
        $this->assertArrayHasKey(1, $guzOptions);
        $this->assertArrayHasKey(3, $guzOptions);
        $this->assertArrayNotHasKey(5, $guzOptions);
        $this->assertArrayNotHasKey(7, $guzOptions);

        $baharOptions = getSemesterSelectOptions('Bahar', 4);
        $this->assertCount(2, $baharOptions);
        $this->assertArrayHasKey(2, $baharOptions);
        $this->assertArrayHasKey(4, $baharOptions);
        $this->assertArrayNotHasKey(6, $baharOptions);
        $this->assertArrayNotHasKey(8, $baharOptions);
    }

    public function testGetSemesterSelectOptionsForBahar(): void
    {
        $options = getSemesterSelectOptions('Bahar', 12);
        $this->assertCount(6, $options);
        $this->assertArrayHasKey(2, $options);
        $this->assertArrayHasKey(4, $options);
        $this->assertArrayHasKey(6, $options);
        $this->assertArrayHasKey(8, $options);
        $this->assertArrayHasKey(10, $options);
        $this->assertArrayHasKey(12, $options);
        $this->assertEquals('1. Sınıf (2. Yarıyıl)', $options[2]);
        $this->assertEquals('2. Sınıf (4. Yarıyıl)', $options[4]);
        $this->assertEquals('6. Sınıf (12. Yarıyıl)', $options[12]);
    }

    public function testGetSemesterSelectOptionsForYaz(): void
    {
        $options = getSemesterSelectOptions('Yaz', 12);
        $this->assertCount(12, $options);
        for ($i = 1; $i <= 12; $i++) {
            $this->assertArrayHasKey($i, $options);
        }
    }

    public function testGetMaxSemesterNoFallback(): void
    {
        $maxSemester = getMaxSemesterNo(null, null, null);
        $this->assertGreaterThanOrEqual(4, $maxSemester);
        $this->assertLessThanOrEqual(12, $maxSemester);
    }

    public function testGetMaxSemesterNoWithNonExistentIds(): void
    {
        // Var olmayan ID'ler verildiğinde SQL hatası fırlatmamalı ve güvenli varsayılan değer dönmeli
        $maxByUnit = getMaxSemesterNo(null, null, 999999);
        $this->assertGreaterThanOrEqual(4, $maxByUnit);

        $maxByDept = getMaxSemesterNo(null, 999999, null);
        $this->assertGreaterThanOrEqual(4, $maxByDept);

        $maxByProg = getMaxSemesterNo(999999, null, null);
        $this->assertGreaterThanOrEqual(4, $maxByProg);
    }
}


