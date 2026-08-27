<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Validators\BuildingValidator;
use App\Validators\ClassroomValidator;
use App\Validators\DepartmentValidator;
use App\Validators\ProgramValidator;
use App\Validators\UnitValidator;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use App\DTOs\BuildingDTO;
use App\DTOs\ClassroomDTO;
use App\DTOs\DepartmentDTO;
use App\DTOs\ProgramDTO;
use App\DTOs\UnitDTO;
use App\DTOs\UserDTO;
use App\Enums\ClassroomType;
use App\Enums\UnitType;
use App\Enums\UserRole;

class CoreEntityValidatorsTest extends BaseTestCase
{
    public function testBuildingValidatorValidData(): void
    {
        $validator = new BuildingValidator();
        $data = ['name' => 'Mühendislik Binası', 'unit_id' => 2];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(BuildingDTO::class, $dto);
        $this->assertEquals('Mühendislik Binası', $dto->name);
        $this->assertEquals(2, $dto->unit_id);
    }

    public function testBuildingValidatorInvalidData(): void
    {
        $validator = new BuildingValidator();

        $this->expectException(ValidationException::class);
        $validator->validate(['name' => '', 'unit_id' => null]);
    }

    public function testClassroomValidatorValidData(): void
    {
        $validator = new ClassroomValidator();
        $data = [
            'name' => 'Lab 101',
            'class_size' => 45,
            'exam_size' => 30,
            'building_id' => 1,
            'type' => ClassroomType::COMPUTER_LAB->value
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(ClassroomDTO::class, $dto);
        $this->assertEquals('Lab 101', $dto->name);
        $this->assertEquals(45, $dto->class_size);
        $this->assertEquals(ClassroomType::COMPUTER_LAB, $dto->type);
    }

    public function testClassroomValidatorInvalidData(): void
    {
        $validator = new ClassroomValidator();

        $this->expectException(ValidationException::class);
        $validator->validate(['name' => '', 'building_id' => 'abc', 'type' => 999]);
    }

    public function testDepartmentValidatorValidData(): void
    {
        $validator = new DepartmentValidator();
        $data = [
            'name' => 'Bilgisayar Teknolojileri',
            'chairperson_id' => 5,
            'unit_id' => 1,
            'active' => 1
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(DepartmentDTO::class, $dto);
        $this->assertEquals('Bilgisayar Teknolojileri', $dto->name);
        $this->assertEquals(5, $dto->chairperson_id);
        $this->assertEquals(1, $dto->unit_id);
    }

    public function testDepartmentValidatorInvalidData(): void
    {
        $validator = new DepartmentValidator();

        $this->expectException(ValidationException::class);
        $validator->validate(['name' => 'A', 'unit_id' => '0']);
    }

    public function testProgramValidatorValidData(): void
    {
        $validator = new ProgramValidator();
        $data = [
            'name' => 'İnternet ve Ağ Teknolojileri',
            'department_id' => 3,
            'active' => 1
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(ProgramDTO::class, $dto);
        $this->assertEquals('İnternet ve Ağ Teknolojileri', $dto->name);
        $this->assertEquals(3, $dto->department_id);
    }

    public function testProgramValidatorInvalidData(): void
    {
        $validator = new ProgramValidator();

        $this->expectException(ValidationException::class);
        $validator->validate(['name' => '', 'department_id' => null]);
    }

    public function testUnitValidatorValidData(): void
    {
        $validator = new UnitValidator();
        $data = [
            'name' => 'Teknik Bilimler MYO',
            'type' => UnitType::Vocational->value,
            'active' => 1
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(UnitDTO::class, $dto);
        $this->assertEquals('Teknik Bilimler MYO', $dto->name);
        $this->assertEquals(UnitType::Vocational, $dto->type);
    }

    public function testUnitValidatorInvalidData(): void
    {
        $validator = new UnitValidator();

        $this->expectException(ValidationException::class);
        $validator->validate(['name' => '', 'type' => 'gecersiz_tur']);
    }

    public function testUserValidatorValidData(): void
    {
        $validator = new UserValidator();
        $data = [
            'name' => 'Ahmet',
            'last_name' => 'Kaya',
            'mail' => 'ahmet.kaya@test.edu.tr',
            'role' => UserRole::Lecturer->value,
            'password' => 'GucluSifre123!',
            'department_id' => 1,
            'program_id' => 1,
            'unit_id' => 1
        ];

        $dto = $validator->getDTO($data);
        $this->assertInstanceOf(UserDTO::class, $dto);
        $this->assertEquals('Ahmet', $dto->name);
        $this->assertEquals('Kaya', $dto->lastName);
        $this->assertEquals('ahmet.kaya@test.edu.tr', $dto->mail);
        $this->assertEquals(UserRole::Lecturer, $dto->role);
    }

    public function testUserValidatorInvalidEmailAndEmptyFields(): void
    {
        $validator = new UserValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'name' => '',
            'last_name' => '',
            'mail' => 'gecersiz-mail-formati',
            'role' => 'olmayan_rol'
        ]);
    }
}
