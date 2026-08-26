<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Models\User;
use App\Controllers\AdminPageController;
use App\Core\AssetManager;
use App\Services\Schedule\AvailabilityService;

class ScheduleProfileScheduleCreationTest extends BaseTestCase
{
    /**
     * @test
     */
    public function it_does_not_create_duplicate_schedules_with_semester_no_on_profile_page()
    {
        $rand = rand(1000, 9999);
        $deptId = $this->insert('departments', ['name' => 'Dept ' . $rand]);
        $userId = $this->insert('users', [
            'mail' => "user{$rand}@test.com",
            'name' => 'Test',
            'last_name' => 'User',
            'role' => 'admin',
            'department_id' => $deptId
        ]);

        $sessionKey = $_ENV["SESSION_KEY"] ?? 'user_id';
        $_SESSION[$sessionKey] = $userId;

        // Reset AuthMiddleware cache
        $ref = new \ReflectionClass(\App\Middlewares\AuthMiddleware::class);
        $propResolved = $ref->getProperty('isResolved');
        $propResolved->setAccessible(true);
        $propResolved->setValue(null, false);
        $propUser = $ref->getProperty('currentUser');
        $propUser->setAccessible(true);
        $propUser->setValue(null, null);

        $user = (new User())->find($userId);
        $pageController = new AdminPageController();
        $assetManager = new AssetManager();

        // Profil sayfası verisini çek
        $pageController->getProfilePageData($user, $assetManager, $userId);

        // Kullanıcıya ait oluşturulan schedule'ları kontrol et
        $stmt = $this->getDb()->prepare("SELECT * FROM schedules WHERE owner_type = 'user' AND owner_id = ?");
        $stmt->execute([$userId]);
        $schedules = $stmt->fetchAll();

        // Toplamda her türden (lesson, midterm-exam, final-exam, makeup-exam) sadece 1'er tane olmalı (toplam 4)
        $this->assertCount(4, $schedules);

        // Hiçbirinin semester_no'su dolu olmamalıdır
        foreach ($schedules as $sch) {
            $this->assertNull($sch['semester_no'], "Hoca schedule'ında semester_no null olmalıdır, fakat {$sch['semester_no']} bulundu.");
        }
    }

    /**
     * @test
     */
    public function availability_checks_do_not_create_empty_schedules()
    {
        $rand = rand(1000, 9999);
        $deptId = $this->insert('departments', ['name' => 'Dept ' . $rand]);
        $progId = $this->insert('programs', ['name' => 'Prog ' . $rand, 'department_id' => $deptId]);
        $classroomId = $this->insert('classrooms', ['name' => 'Lab ' . $rand, 'type' => 2]);
        $lessonId = $this->insert('lessons', [
            'code' => 'T101' . $rand, 
            'name' => 'Test Lesson', 
            'program_id' => $progId, 
            'department_id' => $deptId,
            'hours' => 2,
            'semester_no' => 1,
            'classroom_type' => 2
        ]);
        $userId = $this->insert('users', [
            'mail' => "obs{$rand}@test.com",
            'name' => 'Obs',
            'last_name' => 'User',
            'role' => 'lecturer',
            'department_id' => $deptId
        ]);

        $scheduleId = $this->insert('schedules', [
            'type' => 'lesson',
            'owner_type' => 'program',
            'owner_id' => $progId,
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'semester_no' => 1
        ]);

        $availabilityService = new AvailabilityService();

        // Derslik uygunluk kontrolü
        $availabilityService->availableClassrooms([
            'schedule_id' => $scheduleId,
            'lesson_id' => $lessonId,
            'day_index' => 1,
            'week_index' => 0,
            'items' => json_encode([['start_time' => '08:00', 'end_time' => '09:50']])
        ]);

        // Derslik için schedule tablosuna gereksiz kayıt eklenmemiş olmalı
        $stmt = $this->getDb()->prepare("SELECT COUNT(*) FROM schedules WHERE owner_type = 'classroom' AND owner_id = ?");
        $stmt->execute([$classroomId]);
        $this->assertEquals(0, (int)$stmt->fetchColumn());

        // Gözetmen uygunluk kontrolü
        $availabilityService->availableObservers([
            'schedule_id' => $scheduleId,
            'lesson_id' => $lessonId,
            'day_index' => 1,
            'week_index' => 0,
            'type' => 'midterm-exam',
            'semester' => 'Güz',
            'academic_year' => '2025-2026',
            'items' => json_encode([['start_time' => '08:00', 'end_time' => '09:50']])
        ]);

        // Gözetmen için schedule tablosuna gereksiz kayıt eklenmemiş olmalı
        $stmt = $this->getDb()->prepare("SELECT COUNT(*) FROM schedules WHERE owner_type = 'user' AND owner_id = ?");
        $stmt->execute([$userId]);
        $this->assertEquals(0, (int)$stmt->fetchColumn());
    }
}
