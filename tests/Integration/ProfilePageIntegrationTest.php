<?php

namespace Tests\Integration;

use Tests\BaseTestCase;
use App\Models\User;
use App\Core\AssetManager;
use App\Core\View;
use App\Controllers\AdminPageController;
use App\Middlewares\AuthMiddleware;

class ProfilePageIntegrationTest extends BaseTestCase
{
    public function testProfilePageRendersSuccessfullyWithoutErrors(): void
    {
        $deptId = $this->insert('departments', ['name' => 'Profile Dept ' . rand(1000, 9999)]);
        $userId = $this->insert('users', [
            'mail' => 'profile_test_' . rand(1000, 9999) . '@test.com',
            'name' => 'Ali',
            'last_name' => 'Yılmaz',
            'role' => 'lecturer',
            'title' => 'Dr. Öğr. Üyesi',
            'department_id' => $deptId
        ]);

        $user = (new User())->find($userId);
        $this->assertNotNull($user);

        // Oturum açtır
        $sessionKey = $_ENV['SESSION_KEY'] ?? 'user_id';
        $_SESSION[$sessionKey] = $userId;

        $ref = new \ReflectionClass(AuthMiddleware::class);
        $propResolved = $ref->getProperty('isResolved');
        $propResolved->setValue(null, false);
        $propUser = $ref->getProperty('currentUser');
        $propUser->setValue(null, null);

        $assetManager = new AssetManager();
        $controller = new AdminPageController();

        $pageData = $controller->getProfilePageData($user, $assetManager);
        $pageData['assetManager'] = $assetManager;
        $pageData['currentUser'] = $user;

        $this->assertIsArray($pageData);
        $this->assertEquals($user->getFullName() . ' Profil Sayfası', $pageData['page_title']);

        // View render test
        $view = new View('admin', 'users', 'profile');
        
        ob_start();
        $view->Render($pageData);
        $html = ob_get_clean();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Ali Yılmaz', $html);
        $this->assertStringContainsString('Dr. Öğr. Üyesi', $html);
        $this->assertStringContainsString('Profil Sayfası', $html);
    }
}
