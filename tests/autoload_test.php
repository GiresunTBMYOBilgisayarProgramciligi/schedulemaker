<?php

/**
 * Basit Autoloading Testi
 * 
 * Yeni oluşturulan namespace'lerin düzgün çalışıp çalışmadığını test eder
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../App/Core/Database.php';
require_once __DIR__ . '/../App/Core/Log.php';

use App\Services\BaseService;
use App\Repositories\BaseRepository;
use App\Validators\BaseValidator;
use App\Validators\ValidationResult;
use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Exceptions\ScheduleConflictException;
use App\Exceptions\LessonHourExceededException;

echo "🧪 Namespace Autoloading Test\n";
echo str_repeat("=", 50) . "\n\n";

// Test 1: ValidationResult
echo "✓ Test 1: ValidationResult...\n";
$successResult = ValidationResult::success();
assert($successResult->isValid === true, "Success result should be valid");
assert(empty($successResult->errors), "Success result should have no errors");

$failedResult = ValidationResult::failed(['Error 1', 'Error 2']);
assert($failedResult->isValid === false, "Failed result should be invalid");
assert(count($failedResult->errors) === 2, "Failed result should have 2 errors");

$singleErrorResult = ValidationResult::failedWithError('Single error');
assert($singleErrorResult->isValid === false);
assert($singleErrorResult->getErrorsAsString() === 'Single error');

echo "  ✓ ValidationResult class çalışıyor\n\n";

// Test 2: AppException
echo "✓ Test 2: AppException...\n";
try {
    throw new ValidationException('Test validation error', ['field' => 'email']);
} catch (ValidationException $e) {
    assert($e->getMessage() === 'Test validation error');
    assert($e->getContext()['field'] === 'email');
    $array = $e->toArray();
    assert($array['error'] === true);
    assert($array['message'] === 'Test validation error');
}
echo "  ✓ Exception classes çalışıyor\n\n";

// Test 3: BaseService (mock test)
echo "✓ Test 3: BaseService...\n";
$concreteService = new class extends BaseService {
    public function testMethod(): string
    {
        return "Service working";
    }
};
assert($concreteService->testMethod() === "Service working");
echo "  ✓ BaseService extend edilebiliyor\n\n";

// Test 4: BaseRepository (mock test)
echo "✓ Test 4: BaseRepository...\n";
$concreteRepo = new class extends BaseRepository {
    protected string $modelClass = \App\Models\Lesson::class;

    public function testMethod(): string
    {
        return "Repository working";
    }
};
assert($concreteRepo->testMethod() === "Repository working");
echo "  ✓ BaseRepository extend edilebiliyor\n\n";

// Test 5: BaseValidator (mock test)
echo "✓ Test 5: BaseValidator...\n";
$concreteValidator = new class extends BaseValidator {
    public function validate(array $data): ValidationResult
    {
        if (empty($data['test'])) {
            return ValidationResult::failedWithError('Test field required');
        }
        return ValidationResult::success();
    }
};

$validResult = $concreteValidator->validate(['test' => 'value']);
assert($validResult->isValid === true);

$invalidResult = $concreteValidator->validate([]);
assert($invalidResult->isValid === false);
echo "  ✓ BaseValidator extend edilebiliyor\n\n";

echo str_repeat("=", 50) . "\n";
echo "✅ TÜM TESTLER BAŞARILI!\n";
echo "Namespace autoloading düzgün çalışıyor.\n";
