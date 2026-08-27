<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Validators\PermissionValidator;
use App\Exceptions\ValidationException;

class PermissionValidatorTest extends BaseTestCase
{
    public function testValidPermissionData(): void
    {
        $validator = new PermissionValidator();
        $dto = $validator->getDTO([
            'user_id'     => '15',
            'scope'       => 'departments',
            'target_id'   => '3',
            'permissions' => json_encode(['view' => true])
        ]);

        $this->assertEquals(15, $dto->userId);
        $this->assertEquals('departments', $dto->scope);
        $this->assertEquals(3, $dto->targetId);
        $this->assertEquals(['view' => true], $dto->permissions);
    }

    public function testMissingUserIdThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $validator = new PermissionValidator();
        $validator->getDTO([
            'scope'     => 'departments',
            'target_id' => '3'
        ]);
    }
}
