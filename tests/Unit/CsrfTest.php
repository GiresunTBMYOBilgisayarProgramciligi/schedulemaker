<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Core\Csrf;
use App\Middlewares\CsrfMiddleware;
use App\Exceptions\AuthorizationException;
use function App\Helpers\csrf_token;
use function App\Helpers\csrf_field;

class CsrfTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Csrf::$enabled = true;
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['_csrf_token']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        unset($_SERVER['HTTP_X_XSRF_TOKEN']);
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        unset($_POST['_csrf_token']);
        unset($_POST['csrf_token']);
    }

    protected function tearDown(): void
    {
        Csrf::$enabled = true;
        unset($_SESSION['_csrf_token']);
        parent::tearDown();
    }

    public function testGetTokenGenerates64CharHexToken(): void
    {
        $token = Csrf::getToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertEquals($token, $_SESSION['_csrf_token']);
    }

    public function testGetTokenPersistsInSession(): void
    {
        $token1 = Csrf::getToken();
        $token2 = Csrf::getToken();

        $this->assertSame($token1, $token2);
    }

    public function testRefreshTokenChangesToken(): void
    {
        $token1 = Csrf::getToken();
        $token2 = Csrf::refreshToken();

        $this->assertNotSame($token1, $token2);
        $this->assertEquals(64, strlen($token2));
        $this->assertSame($token2, Csrf::getToken());
    }

    public function testValidateSucceedsWithValidToken(): void
    {
        $token = Csrf::getToken();

        $this->assertTrue(Csrf::validate($token));
    }

    public function testValidateFailsWithInvalidToken(): void
    {
        Csrf::getToken();

        $this->assertFalse(Csrf::validate('invalid_token_value_12345'));
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
    }

    public function testValidateFailsWhenNoSessionToken(): void
    {
        unset($_SESSION['_csrf_token']);

        $this->assertFalse(Csrf::validate('any_token'));
    }

    public function testCsrfFieldGeneratesCorrectHtml(): void
    {
        $token = Csrf::getToken();
        $html = Csrf::field();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString('value="' . $token . '"', $html);
    }

    public function testHelpersFunctionProperly(): void
    {
        $token = csrf_token();
        $this->assertEquals(64, strlen($token));

        $field = csrf_field();
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString($token, $field);
    }

    public function testCsrfMiddlewareAllowsSafeMethods(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        CsrfMiddleware::handle();
        $this->assertTrue(true);

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        CsrfMiddleware::handle();
        $this->assertTrue(true);

        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        CsrfMiddleware::handle();
        $this->assertTrue(true);
    }

    public function testCsrfMiddlewareAllowsWhenDisabled(): void
    {
        Csrf::$enabled = false;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        CsrfMiddleware::handle();
        $this->assertTrue(true);
    }

    public function testCsrfMiddlewareAllowsWhenSkipCsrfIsTrue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        CsrfMiddleware::handle(true);
        $this->assertTrue(true);
    }

    public function testCsrfMiddlewareAllowsPostWithValidHeader(): void
    {
        $token = Csrf::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        CsrfMiddleware::handle();
        $this->assertTrue(true);
    }

    public function testCsrfMiddlewareAllowsPostWithValidPostField(): void
    {
        $token = Csrf::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = $token;

        CsrfMiddleware::handle();
        $this->assertTrue(true);
    }

    public function testCsrfMiddlewareRejectsPostWithoutToken(): void
    {
        $this->expectException(AuthorizationException::class);

        Csrf::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';

        CsrfMiddleware::handle();
    }

    public function testCsrfMiddlewareRejectsPostWithInvalidToken(): void
    {
        $this->expectException(AuthorizationException::class);

        Csrf::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'invalid_tampered_token';

        CsrfMiddleware::handle();
    }
}
