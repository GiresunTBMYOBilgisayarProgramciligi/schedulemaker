<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Exceptions\ScheduleConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\LessonHourExceededException;
use App\Models\Lesson;

class ExceptionTest extends BaseTestCase
{
    public function testAppExceptionContextAndArray(): void
    {
        $exception = new class('Temel uygulama hatası', ['user_id' => 123], 400) extends AppException {};

        $this->assertEquals('Temel uygulama hatası', $exception->getMessage());
        $this->assertEquals(['user_id' => 123], $exception->getContext());
        $this->assertEquals(400, $exception->getCode());

        $exception->addContext('action', 'test');
        $this->assertEquals(['user_id' => 123, 'action' => 'test'], $exception->getContext());

        $array = $exception->toArray();
        $this->assertTrue($array['error']);
        $this->assertEquals('Temel uygulama hatası', $array['message']);
        $this->assertEquals(400, $array['code']);
        $this->assertEquals(['user_id' => 123, 'action' => 'test'], $array['context']);

        $stringRepresentation = (string) $exception;
        $this->assertStringContainsString('Context:', $stringRepresentation);
        $this->assertStringContainsString('123', $stringRepresentation);
    }

    public function testValidationException(): void
    {
        $errors = ['email' => 'Geçersiz e-posta formatı', 'password' => 'Şifre çok kısa'];
        $exception = new ValidationException('Doğrulama hatası', $errors);

        $this->assertEquals('Doğrulama hatası: Geçersiz e-posta formatı; Şifre çok kısa', $exception->getMessage());
        $this->assertEquals($errors, $exception->getValidationErrors());
    }

    public function testAuthorizationException(): void
    {
        $exception = new AuthorizationException('Yetkiniz yok', ['user_id' => 5], 403);
        $this->assertEquals('Yetkiniz yok', $exception->getMessage());
        $this->assertEquals(403, $exception->getCode());
        $this->assertEquals(['user_id' => 5], $exception->getContext());
    }

    public function testNotFoundException(): void
    {
        $exception = new NotFoundException('Kayıt bulunamadı', ['id' => 99], 404);
        $this->assertEquals('Kayıt bulunamadı', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
        $this->assertEquals(['id' => 99], $exception->getContext());
    }

    public function testLessonHourExceededException(): void
    {
        $lesson = new Lesson();
        $lesson->id = 10;
        $lesson->name = 'Matematik';
        $lesson->code = 'MAT101';

        $exception = new LessonHourExceededException($lesson, -2, 'lesson');
        $this->assertStringContainsString('Matematik (MAT101), Kalan: -2', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals(10, $context['lesson_id']);
        $this->assertEquals('Matematik', $context['lesson_name']);
        $this->assertEquals('MAT101', $context['lesson_code']);
        $this->assertEquals(-2, $context['remaining_size']);
        $this->assertEquals('lesson', $context['schedule_type']);
    }

    public function testScheduleConflictException(): void
    {
        $exception = new ScheduleConflictException('Çakışma algılandı', null, null, ['custom' => 'data']);
        $this->assertEquals('Çakışma algılandı', $exception->getMessage());
        $this->assertEquals(['custom' => 'data'], $exception->getContext());
    }
}
