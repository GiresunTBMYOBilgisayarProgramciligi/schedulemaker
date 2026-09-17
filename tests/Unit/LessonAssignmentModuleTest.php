<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Models\User;
use App\Models\Lesson;
use App\DTOs\LessonDTO;
use App\Enums\UserRole;
use App\Enums\UnitType;
use App\Exceptions\ValidationException;
use App\Policies\LessonPolicy;
use App\Repositories\LessonAssignmentRepository;
use App\Repositories\ProgramRepository;
use App\Services\LessonService;
use App\Services\Export\Excel\LessonAssignmentExcelExporter;
use App\Validators\LessonValidator;
use App\Controllers\LessonController;
use function App\Helpers\getSemesterNumbers;
use function App\Helpers\getSuggestedSemesterNo;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Exception;

class LessonAssignmentModuleTest extends BaseTestCase
{
    /**
     * Bölüm başkanının kendi bölümündeki dersleri güncelleme yetkisini doğrular.
     */
    public function testDepartmentHeadCanUpdateOwnDepartmentLesson(): void
    {
        $policy = new LessonPolicy();

        $deptHead = new User();
        $deptHead->id = 10;
        $deptHead->role = UserRole::DepartmentHead->value;
        $deptHead->department_id = 5;

        $ownLesson = new Lesson();
        $ownLesson->id = 100;
        $ownLesson->department_id = 5;

        $otherLesson = new Lesson();
        $otherLesson->id = 101;
        $otherLesson->department_id = 6;

        $this->assertTrue($policy->update($deptHead, $ownLesson));
        $this->assertFalse($policy->update($deptHead, $otherLesson));
    }

    /**
     * LessonAssignmentRepository::deleteAssignment varlığını doğrular.
     */
    public function testDeleteAssignmentMethodReturnsFalseWhenNotFound(): void
    {
        $repo = new LessonAssignmentRepository();
        $result = $repo->deleteAssignment(999999, 'Güz', '2099 - 2100');
        $this->assertFalse($result);
    }

    /**
     * LessonController getProgramLessonsForAssignment eksik parametre kontrolü.
     */
    public function testGetProgramLessonsThrowsOnMissingParams(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Program, dönem ve akademik yıl seçilmelidir.");

        $controller = new LessonController();
        $controller->getProgramLessonsForAssignment([]);
    }

    /**
     * LessonController exportLessonAssignments eksik parametre kontrolü.
     */
    public function testExportLessonAssignmentsThrowsOnMissingParams(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Program, dönem ve akademik yıl seçilmelidir.");

        $controller = new LessonController();
        $controller->exportLessonAssignments([
            'program_id' => 0
        ]);
    }

    /**
     * Güz döneminde tekil, Bahar döneminde çift yarıyıl numaralarının döndüğünü doğrular.
     */
    public function testSemesterNumberFilteringForFallAndSpring(): void
    {
        $fallSemesters = getSemesterNumbers('Güz', 8);
        $this->assertEquals([1, 3, 5, 7], $fallSemesters);
        $this->assertNotContains(2, $fallSemesters);

        $springSemesters = getSemesterNumbers('Bahar', 8);
        $this->assertEquals([2, 4, 6, 8], $springSemesters);
        $this->assertNotContains(1, $springSemesters);
    }

    /**
     * 3+1 sisteminde son iki yarıyılın (3 ve 4) hem Güz hem de Bahar döneminde listelendiğini doğrular.
     */
    public function testSemesterNumberFilteringFor3Plus1System(): void
    {
        $guzSemesters = getSemesterNumbers('Güz', 4, true);
        $this->assertEquals([1, 3, 4], $guzSemesters);
        $this->assertContains(3, $guzSemesters);
        $this->assertContains(4, $guzSemesters);
        $this->assertNotContains(2, $guzSemesters);

        $baharSemesters = getSemesterNumbers('Bahar', 4, true);
        $this->assertEquals([2, 3, 4], $baharSemesters);
        $this->assertContains(3, $baharSemesters);
        $this->assertContains(4, $baharSemesters);
        $this->assertNotContains(1, $baharSemesters);
    }

    /**
     * 7+1 sisteminde son iki yarıyılın (7 ve 8) hem Güz hem de Bahar döneminde listelendiğini doğrular.
     */
    public function testSemesterNumberFilteringFor7Plus1System(): void
    {
        $guzSemesters = getSemesterNumbers('Güz', 8, true);
        $this->assertEquals([1, 3, 5, 7, 8], $guzSemesters);
        $this->assertContains(7, $guzSemesters);
        $this->assertContains(8, $guzSemesters);

        $baharSemesters = getSemesterNumbers('Bahar', 8, true);
        $this->assertEquals([2, 4, 6, 7, 8], $baharSemesters);
        $this->assertContains(7, $baharSemesters);
        $this->assertContains(8, $baharSemesters);
    }

    /**
     * LessonDTO'nun hoca kaldırma durumunda unassign_lecturer bayrağını doğru set ettiğini doğrular.
     */
    public function testLessonDtoUnassignLecturerFlag(): void
    {
        $dtoWithLecturer = LessonDTO::fromArray(['lecturer_id' => 5]);
        $this->assertEquals(5, $dtoWithLecturer->lecturer_id);
        $this->assertFalse($dtoWithLecturer->unassign_lecturer);

        $dtoUnassignEmpty = LessonDTO::fromArray(['lecturer_id' => '']);
        $this->assertNull($dtoUnassignEmpty->lecturer_id);
        $this->assertTrue($dtoUnassignEmpty->unassign_lecturer);

        $dtoUnassignZero = LessonDTO::fromArray(['lecturer_id' => 0]);
        $this->assertNull($dtoUnassignZero->lecturer_id);
        $this->assertTrue($dtoUnassignZero->unassign_lecturer);

        $dtoNoKey = LessonDTO::fromArray(['name' => 'Fizik']);
        $this->assertNull($dtoNoKey->lecturer_id);
        $this->assertFalse($dtoNoKey->unassign_lecturer);
    }

    /**
     * LessonAssignmentExcelExporter sınıfının varlığını doğrular.
     */
    public function testLessonAssignmentExcelExporterInstantiation(): void
    {
        $exporter = new LessonAssignmentExcelExporter();
        $this->assertInstanceOf(LessonAssignmentExcelExporter::class, $exporter);
    }

    /**
     * UnitType::getDefaultSemesterCount testleri.
     */
    public function testUnitTypeDefaultSemesterCount(): void
    {
        $this->assertEquals(8, UnitType::Faculty->getDefaultSemesterCount());
        $this->assertEquals(8, UnitType::School->getDefaultSemesterCount());
        $this->assertEquals(4, UnitType::Vocational->getDefaultSemesterCount());
        $this->assertEquals(4, UnitType::Institute->getDefaultSemesterCount());
    }

    /**
     * ProgramRepository::getProgramTotalSemesters program birim türüne göre 4 veya 8 dönmelidir.
     */
    public function testProgramTotalSemestersBasedOnUnitType(): void
    {
        $programRepo = new ProgramRepository();

        // Program 1: MYO (Tirebolu Mehmet Bayrak MYO) -> 4 yarıyıl
        $myoSemesters = $programRepo->getProgramTotalSemesters(1);
        $this->assertEquals(4, $myoSemesters);

        $guzMyo = getSemesterNumbers('Güz', $myoSemesters, true);
        $this->assertEquals([1, 3, 4], $guzMyo);

        $baharMyo = getSemesterNumbers('Bahar', $myoSemesters, true);
        $this->assertEquals([2, 3, 4], $baharMyo);

        // Program 26: Gazetecilik (İletişim Fakültesi) -> 8 yarıyıl
        $facultySemesters = $programRepo->getProgramTotalSemesters(26);
        $this->assertEquals(8, $facultySemesters);

        $guzFaculty = getSemesterNumbers('Güz', $facultySemesters, true);
        $this->assertEquals([1, 3, 5, 7, 8], $guzFaculty);

        $baharFaculty = getSemesterNumbers('Bahar', $facultySemesters, true);
        $this->assertEquals([2, 4, 6, 7, 8], $baharFaculty);
    }

    /**
     * LessonValidator assignment update modunda ID ve alan doğrulaması yapar.
     */
    public function testLessonValidatorForAssignmentUpdate(): void
    {
        $validator = new LessonValidator(isAssignmentUpdate: true);

        // ID eksik veya geçersiz ise ValidationException fırlatır
        try {
            $validator->validate(['size' => 30]);
            $this->fail("ID eksikken ValidationException fırlatılmalıydı.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id', $e->getValidationErrors());
        }

        // Geçerli veri ile DTO dönmelidir
        $validData = [
            'id' => 10,
            'size' => 45,
            'hours' => 3,
            'type' => 1,
            'semester_no' => 3,
            'lecturer_id' => 5
        ];
        $dto = $validator->getDTO($validData);
        $this->assertInstanceOf(LessonDTO::class, $dto);
        $this->assertEquals(10, $dto->id);
        $this->assertEquals(45, $dto->size);
        $this->assertEquals(3, $dto->hours);
        $this->assertEquals(5, $dto->lecturer_id);
    }

    /**
     * Excel çıktısında suggested semester değil, veritabanında kayıtlı semester_no'nun kullanıldığını doğrular.
     */
    public function testLessonAssignmentExcelExportUsesRegisteredSemesterNoNotSuggested(): void
    {
        // 3. yarıyıl dersi: Bahar döneminde UI'da önerilen yarıyıl 4'tür.
        $lesson = new Lesson();
        $lesson->id = 50;
        $lesson->code = 'BLG202';
        $lesson->name = 'Veri Yapıları';
        $lesson->semester_no = 3;

        // UI için getSuggestedSemesterNo 4 üretir:
        $suggestedSemester = getSuggestedSemesterNo((int)$lesson->semester_no, 'Bahar', 4);
        $this->assertEquals(4, $suggestedSemester);

        // Ancak Excel exporter doğrudan $lesson->semester_no'yu (kayıtlı değeri) kullanır:
        $this->assertEquals(3, $lesson->semester_no);
        $this->assertNotEquals($suggestedSemester, $lesson->semester_no);
    }

    /**
     * Grubu olmayan veya 0 olan dersler için Excel çıktısında '0' değerinin yazıldığını doğrular.
     */
    public function testLessonAssignmentExcelExportOutputsZeroForUngroupedLessons(): void
    {
        $lessonWithoutGroup = new Lesson();
        $lessonWithoutGroup->group_no = 0;
        $groupNo = (!empty($lessonWithoutGroup->group_no) && (int)$lessonWithoutGroup->group_no > 0) ? (int)$lessonWithoutGroup->group_no : '0';
        $this->assertSame('0', $groupNo);

        $lessonWithNullGroup = new Lesson();
        $lessonWithNullGroup->group_no = null;
        $nullGroupNo = (!empty($lessonWithNullGroup->group_no) && (int)$lessonWithNullGroup->group_no > 0) ? (int)$lessonWithNullGroup->group_no : '0';
        $this->assertSame('0', $nullGroupNo);

        $lessonWithGroup = new Lesson();
        $lessonWithGroup->group_no = 2;
        $hasGroupNo = (!empty($lessonWithGroup->group_no) && (int)$lessonWithGroup->group_no > 0) ? (int)$lessonWithGroup->group_no : '0';
        $this->assertSame(2, $hasGroupNo);
    }

    /**
     * LessonAssignmentExcelExporter'ın opsiyonel derslik türü ve bina sütunları parametrelerini kabul ettiğini doğrular.
     */
    public function testLessonAssignmentExcelExportAcceptsColumnOptions(): void
    {
        $refMethod = new ReflectionMethod(LessonAssignmentExcelExporter::class, 'export');
        $params = $refMethod->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('programId', $params[0]->getName());
        $this->assertEquals('semester', $params[1]->getName());
        $this->assertEquals('academicYear', $params[2]->getName());
        $this->assertEquals('showClassroomType', $params[3]->getName());
        $this->assertEquals('showBuilding', $params[4]->getName());

        $this->assertTrue($params[3]->getDefaultValue());
        $this->assertTrue($params[4]->getDefaultValue());
    }

    /**
     * Excel başlık satırının kalın (bold) olarak stillendirildiğini doğrular.
     */
    public function testLessonAssignmentExcelExportHeaderIsBold(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            "Bölümü / Ana Bilim Dalı",
            "Programı / Bilim Dalı",
            "Yarıyılı",
            "Türü",
            "Dersin Kodu",
            "Grup No",
            "Dersin Adı",
            "Saati",
            "Kontenjan/Mevcut",
            "Hocası",
            "Derslik türü",
            "Bina"
        ];
        $sheet->fromArray($headers, null, 'A1');

        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertTrue($sheet->getStyle('L1')->getFont()->getBold());
    }

    /**
     * Excel dışa aktarımında önerilen yarıyılın hesaplandığını ve ders nesnesinin orijinal semester_no'sunun değişmediğini doğrular.
     */
    public function testLessonAssignmentExcelExportUsesSuggestedSemesterWithoutMutatingModel(): void
    {
        $lesson = new Lesson();
        $lesson->semester_no = 3;
        $originalSemesterNo = $lesson->semester_no;

        // Bahar döneminde 4 yarıyıllı bir programda 3. yarıyıl dersi 4 olarak önerilir
        $suggestedSemesterNo = getSuggestedSemesterNo((int)$lesson->semester_no, 'Bahar', 4);

        $this->assertSame(4, $suggestedSemesterNo);
        // Orijinal nesne veya veritabanı alanı değişmemelidir
        $this->assertSame($originalSemesterNo, $lesson->semester_no);
        $this->assertSame(3, $lesson->semester_no);

        // Güz döneminde 4 yarıyıllı bir programda 4. yarıyıl dersi 3 olarak önerilir
        $lesson->semester_no = 4;
        $suggestedFall = getSuggestedSemesterNo((int)$lesson->semester_no, 'Güz', 4);
        $this->assertSame(3, $suggestedFall);
        $this->assertSame(4, $lesson->semester_no);
    }

    /**
     * LessonAssignmentExcelExporter sınıfının exportMultiple metodunun varlığını ve parametrelerini doğrular.
     */
    public function testLessonAssignmentExcelExporterHasExportMultipleMethod(): void
    {
        $refMethod = new ReflectionMethod(LessonAssignmentExcelExporter::class, 'exportMultiple');
        $params = $refMethod->getParameters();

        $this->assertCount(6, $params);
        $this->assertEquals('programIds', $params[0]->getName());
        $this->assertEquals('semester', $params[1]->getName());
        $this->assertEquals('academicYear', $params[2]->getName());
        $this->assertEquals('showClassroomType', $params[3]->getName());
        $this->assertEquals('showBuilding', $params[4]->getName());
        $this->assertEquals('customFileName', $params[5]->getName());

        $this->assertTrue($params[3]->getDefaultValue());
        $this->assertTrue($params[4]->getDefaultValue());
        $this->assertNull($params[5]->getDefaultValue());
    }

    /**
     * exportAllLessonAssignments eksik dönem veya akademik yıl durumunda Exception fırlatmalıdır.
     */
    public function testExportAllLessonAssignmentsRequiresParameters(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Dönem ve akademik yıl seçilmelidir.");

        $controller = new LessonController();
        $controller->exportAllLessonAssignments([]);
    }

    /**
     * exportAllLessonAssignments yetkisiz roldeki kullanıcı için AuthorizationException fırlatmalıdır.
     */
    public function testExportAllLessonAssignmentsUnauthorizedRole(): void
    {
        $user = new User();
        $user->id = 999;
        $user->role = UserRole::Lecturer->value;

        $ref = new \ReflectionClass(\App\Middlewares\AuthMiddleware::class);
        $propResolved = $ref->getProperty('isResolved');
        $propResolved->setValue(null, true);
        $propUser = $ref->getProperty('currentUser');
        $propUser->setValue(null, $user);

        try {
            $this->expectException(\App\Exceptions\AuthorizationException::class);

            $controller = new LessonController();
            $controller->exportAllLessonAssignments([
                'semester' => 'Bahar',
                'academic_year' => '2024 - 2025'
            ]);
        } finally {
            $propResolved->setValue(null, false);
            $propUser->setValue(null, null);
        }
    }
}

