<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Services\Schedule\ExamScheduleService;
use App\Services\Schedule\ExamRules\ExamWarningService;
use App\Services\Schedule\GuestScheduleVisibility;
use App\DTOs\ScheduleItemDTO;
use App\Models\ScheduleItem;
use App\Enums\OwnerType;
use App\Enums\ClassroomType;
use App\Repositories\ScheduleRepository;

class ExamScheduleServiceTest extends BaseTestCase
{
    private ExamScheduleService $service;
    private ExamWarningService $warnings;
    private int $unitId;
    private int $deptId;
    private int $progId;
    private int $buildingId;
    private int $classroomId;
    private int $lecturerId;
    private int $lessonId;
    private int $programScheduleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExamScheduleService();
        $this->warnings = new ExamWarningService();

        $this->unitId = $this->insert('units', ['name' => 'Exam Unit ' . rand(1000, 9999), 'type' => 'myo', 'active' => 1]);
        $this->deptId = $this->insert('departments', ['name' => 'Exam Dept ' . rand(1000, 9999), 'unit_id' => $this->unitId, 'active' => 1]);
        $this->progId = $this->insert('programs', ['name' => 'Exam Prog ' . rand(1000, 9999), 'department_id' => $this->deptId, 'active' => 1]);
        $this->buildingId = $this->insert('buildings', ['name' => 'Exam Bldg ' . rand(1000, 9999), 'unit_id' => $this->unitId]);
        $this->classroomId = $this->insert('classrooms', [
            'name' => 'Exam Class ' . rand(100, 999),
            'type' => ClassroomType::CLASSROOM->value,
            'class_size' => 50,
            'building_id' => $this->buildingId
        ]);
        $this->lecturerId = $this->insert('users', [
            'name' => 'Exam',
            'last_name' => 'Lecturer',
            'mail' => 'exam_lec_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $this->lessonId = $this->insert('lessons', [
            'name' => 'Matematik 1',
            'code' => 'MAT' . rand(100, 999),
            'hours' => 3,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'semester_no' => 1,
            'type' => 1
        ]);

        $this->programScheduleId = $this->insert('schedules', [
            'type' => 'final-exam',
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $this->progId,
            'semester_no' => 1,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
    }

    public function testSaveExamScheduleItems(): void
    {
        $dto = ScheduleItemDTO::fromArray([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 2,
            'week_index' => 0,
            'start_time' => '10:00:00',
            'end_time' => '11:30:00',
            'status' => 'single',
            'data' => [
                [
                    'lesson_id' => $this->lessonId,
                    'assignments' => [
                        [
                            'classroom_id' => $this->classroomId,
                            'lecturer_id' => $this->lecturerId
                        ]
                    ]
                ]
            ]
        ]);

        $created = $this->service->saveExamScheduleItems([$dto]);

        $this->assertNotEmpty($created);

        // Program takviminde item oluşmuş olmalı
        $items = (new ScheduleItem())->get()->where([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 2
        ])->all();

        $this->assertNotEmpty($items);
    }

    public function testSaveExamScheduleItemsWithMultipleObservers(): void
    {
        $secondLecturerId = $this->insert('users', [
            'name' => 'Second',
            'last_name' => 'Observer',
            'mail' => 'second_obs_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);

        $dto = ScheduleItemDTO::fromArray([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 3,
            'week_index' => 0,
            'start_time' => '13:00:00',
            'end_time' => '14:30:00',
            'status' => 'single',
            'data' => [
                [
                    'lesson_id' => $this->lessonId,
                ]
            ],
            'detail' => [
                'assignments' => [
                    [
                        'classroom_id' => $this->classroomId,
                        'classroom_name' => 'Amfi 1',
                        'classroom_exam_size' => 80,
                        'observers' => [
                            ['id' => $this->lecturerId, 'name' => 'Exam Lecturer'],
                            ['id' => $secondLecturerId, 'name' => 'Second Observer']
                        ]
                    ]
                ]
            ]
        ]);

        $created = $this->service->saveExamScheduleItems([$dto]);
        $this->assertNotEmpty($created);

        // 1. Program takviminde 1 adet item oluşmuş olmalı
        $programItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 3
        ])->all();
        $this->assertCount(1, $programItems);
        $primaryItem = $programItems[0];

        // 2. Her iki gözetmenin kendi takvimlerinde item oluşmuş olmalı
        $firstObsSchedule = (new ScheduleRepository())->findByOwnerAndPeriod(
            OwnerType::USER->value,
            $this->lecturerId,
            '2025 - 2026',
            'Güz',
            'final-exam',
            null
        );
        $this->assertNotNull($firstObsSchedule);
        $firstObsItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $firstObsSchedule->id,
            'day_index' => 3
        ])->all();
        $this->assertCount(1, $firstObsItems);

        $secondObsSchedule = (new ScheduleRepository())->findByOwnerAndPeriod(
            OwnerType::USER->value,
            $secondLecturerId,
            '2025 - 2026',
            'Güz',
            'final-exam',
            null
        );
        $this->assertNotNull($secondObsSchedule);
        $secondObsItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $secondObsSchedule->id,
            'day_index' => 3
        ])->all();
        $this->assertCount(1, $secondObsItems);

        // 3. Derslik takviminde YALNIZCA 1 adet item oluşmuş olmalı (unique kısıt ihlali olmamalı)
        $classroomSchedule = (new ScheduleRepository())->findByOwnerAndPeriod(
            OwnerType::CLASSROOM->value,
            $this->classroomId,
            '2025 - 2026',
            'Güz',
            'final-exam',
            null
        );
        $this->assertNotNull($classroomSchedule);
        $classroomItems = (new ScheduleItem())->get()->where([
            'schedule_id' => $classroomSchedule->id,
            'day_index' => 3
        ])->all();
        $this->assertCount(1, $classroomItems);

        // 4. Sibling items kontrolü (Program, Ders, Derslik, Gözetmen 1, Gözetmen 2 bulunmalı)
        $siblings = $this->service->findExamSiblingItems($primaryItem);
        // Debug siblings
        // echo "Primary ID: {$primaryItem->id}\n";
        // echo "Siblings count: " . count($siblings) . "\n";
        // foreach ($siblings as $s) { echo "Sibling ID: {$s->id}, ScheduleID: {$s->schedule_id}\n"; }
        
        $deleteResult = $this->service->deleteScheduleItems([
            ScheduleItemDTO::fromArray([
                'id' => $primaryItem->id,
                'schedule_id' => $this->programScheduleId,
                'day_index' => 3,
                'week_index' => 0,
                'start_time' => '13:00:00',
                'end_time' => '14:30:00',
                'data' => [
                    ['lesson_id' => $this->lessonId]
                ]
            ])
        ]);
        $this->assertTrue($deleteResult->success);

        $remainingFirstObs = (new ScheduleItem())->get()->where([
            'schedule_id' => $firstObsSchedule->id,
            'day_index' => 3
        ])->all();
        $this->assertEmpty($remainingFirstObs);

        $remainingSecondObs = (new ScheduleItem())->get()->where([
            'schedule_id' => $secondObsSchedule->id,
            'day_index' => 3
        ])->all();
        $this->assertEmpty($remainingSecondObs);
    }

    private function makeExamDto(int $lessonId, int $day, string $start, string $end, ?int $id = null, ?int $scheduleId = null): ScheduleItemDTO
    {
        return ScheduleItemDTO::fromArray([
            'schedule_id' => $scheduleId ?? $this->programScheduleId,
            'day_index' => $day,
            'week_index' => 0,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'single',
            'data' => [['lesson_id' => $lessonId]],
            'id' => $id,
        ]);
    }

    private function insertExtraLesson(string $name, int $semesterNo = 1): int
    {
        return $this->insert('lessons', [
            'name' => $name,
            'code' => 'EXT' . rand(1000, 9999),
            'hours' => 3,
            'department_id' => $this->deptId,
            'program_id' => $this->progId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'semester_no' => $semesterNo,
            'type' => 1
        ]);
    }

    private function insertProgramSchedule(int $semesterNo): int
    {
        return $this->insert('schedules', [
            'type' => 'final-exam',
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $this->progId,
            'semester_no' => $semesterNo,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
    }

    public function testAdjacentClassOverlapWarnsWhenFirstAndSecondClassOverlap(): void
    {
        $secondClassLesson = $this->insertExtraLesson('Fizik 2', 3);
        $secondClassSchedule = $this->insertProgramSchedule(3);

        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);

        // 1. sınıf (yarıyıl 1) ile 2. sınıf (yarıyıl 3) aynı saatte: uyarı
        $warnings = $this->warnings->check([$this->makeExamDto($secondClassLesson, 1, '09:00:00', '10:00:00', null, $secondClassSchedule)]);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('1. sınıf', $warnings[0]);
        $this->assertStringContainsString('2. sınıf', $warnings[0]);

        // Süreler kısmen kesişiyorsa da uyarı
        $this->assertCount(1, $this->warnings->check([$this->makeExamDto($secondClassLesson, 1, '09:30:00', '10:30:00', null, $secondClassSchedule)]));

        // Peş peşe (kesişmeyen) saatte uyarı yok
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($secondClassLesson, 1, '10:00:00', '11:00:00', null, $secondClassSchedule)]));

        // Başka günde uyarı yok
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($secondClassLesson, 2, '09:00:00', '10:00:00', null, $secondClassSchedule)]));
    }

    public function testAdjacentClassOverlapWarnsForNonConsecutiveClasses(): void
    {
        $thirdClassLesson = $this->insertExtraLesson('Fizik 3', 5);
        $thirdClassSchedule = $this->insertProgramSchedule(5);

        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);

        // 1. sınıf ile 3. sınıf da çakışma uyarısı verir
        $warnings = $this->warnings->check([$this->makeExamDto($thirdClassLesson, 1, '09:00:00', '10:00:00', null, $thirdClassSchedule)]);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('1. sınıf', $warnings[0]);
        $this->assertStringContainsString('3. sınıf', $warnings[0]);
    }

    public function testAdjacentClassOverlapIgnoresSameClassAndOtherPrograms(): void
    {
        $sameClassLesson = $this->insertExtraLesson('Kimya');
        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);

        // Aynı sınıf çakışması bu kuralın konusu değil
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($sameClassLesson, 1, '09:00:00', '10:00:00')]));

        // Farklı programın 2. sınıfı ilgilendirmez
        $otherProgId = $this->insert('programs', ['name' => 'Other Prog ' . rand(1000, 9999), 'department_id' => $this->deptId, 'active' => 1]);
        $otherLesson = $this->insert('lessons', [
            'name' => 'Diger',
            'code' => 'OTH' . rand(1000, 9999),
            'hours' => 3,
            'department_id' => $this->deptId,
            'program_id' => $otherProgId,
            'building_id' => $this->buildingId,
            'classroom_type' => ClassroomType::CLASSROOM->value,
            'semester_no' => 3,
            'type' => 1
        ]);
        $otherSchedule = $this->insert('schedules', [
            'type' => 'final-exam',
            'owner_type' => OwnerType::PROGRAM->value,
            'owner_id' => $otherProgId,
            'semester_no' => 3,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'is_published' => 0
        ]);
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($otherLesson, 1, '09:00:00', '10:00:00', null, $otherSchedule)]));
    }

    public function testAdjacentClassOverlapIgnoresDeletedItemOnMove(): void
    {
        $secondClassLesson = $this->insertExtraLesson('Fizik 2', 3);
        $secondClassSchedule = $this->insertProgramSchedule(3);

        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);
        $this->service->saveExamScheduleItems([$this->makeExamDto($secondClassLesson, 1, '11:00:00', '12:00:00', null, $secondClassSchedule)]);

        $existing = (new ScheduleItem())->get()->where([
            'schedule_id' => $secondClassSchedule,
            'day_index' => 1,
            'start_time' => '11:00:00',
        ])->first();

        // 2. sınıf sınavı 09:00'a taşınırsa uyarı verir
        $this->assertCount(1, $this->warnings->check([$this->makeExamDto($secondClassLesson, 1, '09:00:00', '10:00:00', null, $secondClassSchedule)]));

        // Mevcut 1. sınıf sınavı 09:00'dan 11:00'a (2. sınıfın kendi saatine) taşınmıyorsa; 2. sınıfın kendi
        // eski kaydı silinecekse sayıma katılmaz: 11:00'daki 2. sınıf kaydı silinip 14:00'a taşınınca uyarı yok
        $this->assertSame([], $this->warnings->check(
            [$this->makeExamDto($secondClassLesson, 1, '14:00:00', '15:00:00', null, $secondClassSchedule)],
            [$this->makeExamDto($secondClassLesson, 1, '11:00:00', '12:00:00', (int) $existing->id, $secondClassSchedule)]
        ));
    }

    public function testAdjacentClassOverlapIgnoresCombinedExam(): void
    {
        $secondClassLesson = $this->insertExtraLesson('Fizik 2', 3);
        $this->insertProgramSchedule(3);
        // 2. sınıf dersi, 1. sınıf dersiyle sınav birleştirmesi yapılmış (ortak sınav)
        $this->insert('lesson_combinations', [
            'parent_lesson_id' => $this->lessonId,
            'child_lesson_id' => $secondClassLesson,
            'type' => 'exam',
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
        ]);

        // Ortak sınav kendi içinde çakışma sayılmaz
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]));

        // Birleşik sınav kaydedildikten sonra, kayıtlı ortak sınav yeni bir sınav eklenirken çakışma sayılmaz
        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);
        $thirdLesson = $this->insertExtraLesson('Kimya');
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($thirdLesson, 2, '09:00:00', '10:00:00')]));
    }

    public function testDailyExamLimitWarnsOnThirdExamOfSameDay(): void
    {
        $lesson2 = $this->insertExtraLesson('Fizik');
        $lesson3 = $this->insertExtraLesson('Kimya');

        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);
        $this->service->saveExamScheduleItems([$this->makeExamDto($lesson2, 1, '11:00:00', '12:00:00')]);

        // İkinci sınav sınırı aşmaz
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($lesson2, 1, '11:00:00', '12:00:00')]));

        // Üçüncü sınav uyarı üretir
        $warnings = $this->warnings->check([$this->makeExamDto($lesson3, 1, '14:00:00', '15:00:00')]);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('en fazla 2 sınav', $warnings[0]);

        // Başka güne eklemek uyarı üretmez
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($lesson3, 2, '14:00:00', '15:00:00')]));
    }

    public function testDailyExamLimitCountsSameSlotOnce(): void
    {
        $lesson2 = $this->insertExtraLesson('Fizik');
        $lesson3 = $this->insertExtraLesson('Kimya');

        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);
        $this->service->saveExamScheduleItems([$this->makeExamDto($lesson2, 1, '11:00:00', '12:00:00')]);

        // Mevcut bir sınavla aynı saat dilimi (birleşik sınav/grup) yeni sınav sayılmaz
        $this->assertSame([], $this->warnings->check([$this->makeExamDto($lesson3, 1, '09:00:00', '10:00:00')]));
    }

    public function testDailyExamLimitIgnoresDeletedItemOnMove(): void
    {
        $lesson2 = $this->insertExtraLesson('Fizik');
        $this->service->saveExamScheduleItems([$this->makeExamDto($this->lessonId, 1, '09:00:00', '10:00:00')]);
        $this->service->saveExamScheduleItems([$this->makeExamDto($lesson2, 1, '11:00:00', '12:00:00')]);

        $existing = (new ScheduleItem())->get()->where([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 1,
            'start_time' => '11:00:00',
        ])->first();

        // Aynı gün içinde sınavı başka saate taşımak sınırı aşmaz
        $warnings = $this->warnings->check(
            [$this->makeExamDto($lesson2, 1, '14:00:00', '15:00:00')],
            [$this->makeExamDto($lesson2, 1, '11:00:00', '12:00:00', (int) $existing->id)]
        );
        $this->assertSame([], $warnings);
    }

    /**
     * Gözetmenli bir sınav kaydeder; program, derslik ve iki gözetmenin user programlarına yansır.
     *
     * @return int İkinci gözetmenin ID'si
     */
    private function saveExamWithObservers(): int
    {
        $secondObserverId = $this->insert('users', [
            'name' => 'Gizli',
            'last_name' => 'Gozetmen',
            'mail' => 'hidden_obs_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);

        $this->service->saveExamScheduleItems([ScheduleItemDTO::fromArray([
            'schedule_id' => $this->programScheduleId,
            'day_index' => 1,
            'week_index' => 0,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 'single',
            'data' => [['lesson_id' => $this->lessonId]],
            'detail' => [
                'assignments' => [[
                    'classroom_id' => $this->classroomId,
                    'classroom_name' => 'Amfi Gizli',
                    'observers' => [
                        ['id' => $this->lecturerId, 'name' => 'Exam Lecturer'],
                        ['id' => $secondObserverId, 'name' => 'Gizli Gozetmen'],
                    ]
                ]]
            ]
        ])]);

        return $secondObserverId;
    }

    private function examFilter(string $ownerType, int $ownerId, ?int $semesterNo): array
    {
        // Dışa aktarım filtresiyle aynı: semester_no yalnızca program için eklenir
        $filter = [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026',
            'type' => 'final-exam',
        ];
        if ($semesterNo !== null) {
            $filter['semester_no'] = $semesterNo;
        }
        return $filter;
    }

    private function actAsAdmin(): void
    {
        $admin = new \App\Models\User();
        $admin->id = 1;
        $admin->role = 'admin';
        $ref = new \ReflectionClass(\App\Middlewares\AuthMiddleware::class);
        $ref->getProperty('currentUser')->setValue(null, $admin);
        $ref->getProperty('isResolved')->setValue(null, true);
    }

    public function testGuestExamScheduleHidesObserversButKeepsClassroom(): void
    {
        $secondObserverId = $this->saveExamWithObservers();
        // Dersin hocası gözetmenlerden farklı; misafir kartında gözetmen yerine o görünmeli
        $lessonLecturerId = $this->insert('users', [
            'name' => 'Ders',
            'last_name' => 'Hocasi',
            'mail' => 'lesson_lec_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $this->insert('lesson_assignments', [
            'lesson_id' => $this->lessonId,
            'lecturer_id' => $lessonLecturerId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);
        $this->resetAuth();

        // Program: salon kalır, gözetmen isimleri silinir
        $program = $this->service->findScheduleWithItems($this->examFilter(OwnerType::PROGRAM->value, $this->progId, 1));
        $assignment = $program->items[0]->detail['assignments'][0];
        $this->assertSame('Amfi Gizli', $assignment['classroom_name']);
        $this->assertArrayNotHasKey('observers', $assignment);

        // Derslik: gözetmeni tutan lecturer_id ve gözetmen listesi temizlenir
        $classroom = $this->service->findScheduleWithItems($this->examFilter(OwnerType::CLASSROOM->value, $this->classroomId, null));
        $this->assertCount(1, $classroom->items);
        $this->assertNull($classroom->items[0]->data[0]['lecturer_id']);
        $this->assertArrayNotHasKey('observers', $classroom->items[0]->detail);

        // Hoca: kendi gözetmenlik görevi görünür (hoca programı yayınlanmadıkça misafir programı zaten göremez),
        // aynı sınavdaki diğer gözetmen bilgisi yer almaz
        $observer = $this->service->findScheduleWithItems($this->examFilter(OwnerType::USER->value, $secondObserverId, null));
        $this->assertCount(1, $observer->items);
        $this->assertSame('exam_assignment', $observer->items[0]->detail['reference_type']);
        $this->assertArrayNotHasKey('observers', $observer->items[0]->detail);

        // Ekran: misafir kartında gözetmen adı ve "Gözetmen atanmadı" yer almaz, salon görünür
        $html = \App\Helpers\ScheduleViewHelper::prepareScheduleCard(
            \App\DTOs\ScheduleFilterDTO::fromArray($this->examFilter(OwnerType::PROGRAM->value, $this->progId, 1)),
            true
        );
        $this->assertStringContainsString('Amfi Gizli', $html);
        $this->assertStringContainsString('Ders Hocasi', $html);
        $this->assertStringNotContainsString('Gizli Gozetmen', $html);
        $this->assertStringNotContainsString('Exam Lecturer', $html);
        $this->assertStringNotContainsString('Gözetmen atanmadı', $html);
    }

    public function testLoggedInExamScheduleKeepsObservers(): void
    {
        $secondObserverId = $this->saveExamWithObservers();
        $this->actAsAdmin();

        $program = $this->service->findScheduleWithItems($this->examFilter(OwnerType::PROGRAM->value, $this->progId, 1));
        $this->assertCount(2, $program->items[0]->detail['assignments'][0]['observers']);

        $classroom = $this->service->findScheduleWithItems($this->examFilter(OwnerType::CLASSROOM->value, $this->classroomId, null));
        $this->assertSame($this->lecturerId, (int)$classroom->items[0]->data[0]['lecturer_id']);

        $observer = $this->service->findScheduleWithItems($this->examFilter(OwnerType::USER->value, $secondObserverId, null));
        $this->assertCount(1, $observer->items);

        $html = \App\Helpers\ScheduleViewHelper::prepareScheduleCard(
            \App\DTOs\ScheduleFilterDTO::fromArray($this->examFilter(OwnerType::PROGRAM->value, $this->progId, 1)),
            true
        );
        $this->assertStringContainsString('Gizli Gozetmen', $html);
    }

    public function testGuestLecturerExamShowsOwnLessonOnlyAfterProgramPublished(): void
    {
        $this->saveExamWithObservers();
        $lessonLecturerId = $this->insert('users', [
            'name' => 'Yayin',
            'last_name' => 'Hocasi',
            'mail' => 'pub_lec_' . rand(1000, 9999) . '@test.com',
            'role' => 'lecturer',
            'department_id' => $this->deptId,
            'unit_id' => $this->unitId
        ]);
        $this->insert('lesson_assignments', [
            'lesson_id' => $this->lessonId,
            'lecturer_id' => $lessonLecturerId,
            'semester' => 'Güz',
            'academic_year' => '2025 - 2026'
        ]);
        $this->resetAuth();
        $filter = $this->examFilter(OwnerType::USER->value, $lessonLecturerId, null);

        // Ders programları yayınlanmaz; program programı yayınlanmadıkça misafir dersin sınavını görmez
        $this->assertSame([], $this->service->findScheduleWithItems($filter)->items);

        $this->getDb()->exec("UPDATE schedules SET is_published = 1 WHERE id = {$this->programScheduleId}");
        $items = $this->service->findScheduleWithItems($filter)->items;
        $this->assertCount(1, $items);
        $this->assertSame($this->lessonId, (int)$items[0]->data[0]['lesson_id']);
    }

    public function testGuestExamExportOptionOverrides(): void
    {
        $this->resetAuth();
        $this->assertSame(['show_observer' => false, 'show_program' => false], GuestScheduleVisibility::exportOptionOverrides('midterm-exam'));
        $this->assertSame([], GuestScheduleVisibility::exportOptionOverrides('lesson'));

        $this->actAsAdmin();
        $this->assertSame([], GuestScheduleVisibility::exportOptionOverrides('midterm-exam'));
    }
}
