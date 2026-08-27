<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Validators\Auth\LoginValidator;
use App\Exceptions\ValidationException;

class LoginValidatorTest extends BaseTestCase
{
    public function testValidLoginData(): void
    {
        $validator = new LoginValidator();
        $dto = $validator->getDTO([
            'mail'        => 'user@example.com',
            'password'    => 'secret123',
            'remember_me' => '1'
        ]);

        $this->assertEquals('user@example.com', $dto->mail);
        $this->assertEquals('secret123', $dto->password);
        $this->assertTrue($dto->rememberMe);
    }

    public function testInvalidMailThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $validator = new LoginValidator();
        $validator->getDTO([
            'mail'     => 'invalid-email',
            'password' => 'secret123'
        ]);
    }

    public function testMissingPasswordThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $validator = new LoginValidator();
        $validator->getDTO([
            'mail' => 'user@example.com'
        ]);
    }
}
