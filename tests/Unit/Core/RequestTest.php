<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Csrf;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_SERVER  = [];
    }

    public function testGetMethodDefaultsToGet(): void
    {
        $request = new Request();
        $this->assertSame('GET', $request->getMethod());
    }

    public function testGetMethodReadsServerMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $request = new Request();
        $this->assertSame('POST', $request->getMethod());
    }

    public function testGetPathStripsTrailingSlash(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard/';
        $request = new Request();
        $this->assertSame('/dashboard', $request->getPath());
    }

    public function testGetPathReturnsRootForEmpty(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $request = new Request();
        $this->assertSame('/', $request->getPath());
    }

    public function testGetPathStripsQueryString(): void
    {
        $_SERVER['REQUEST_URI'] = '/trees?page=2&q=test';
        $request = new Request();
        $this->assertSame('/trees', $request->getPath());
    }

    public function testVerifyCsrfPassesWithValidToken(): void
    {
        $token = Csrf::generate();
        $_POST['_csrf_token'] = $token;
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();
        // verifyCsrf() doesn't return — it throws or passes silently
        $request->verifyCsrf();
        $this->assertTrue(true); // Reached without exception
    }

    public function testVerifyCsrfFailsWithInvalidToken(): void
    {
        Csrf::generate();
        $_POST['_csrf_token'] = 'invalid-token';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();
        $this->expectException(\RuntimeException::class);
        $request->verifyCsrf();
    }

    public function testVerifyCsrfFailsWhenTokenMissing(): void
    {
        Csrf::generate();
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request();
        $this->expectException(\RuntimeException::class);
        $request->verifyCsrf();
    }

    public function testIsPostIsGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $r1 = new Request();
        $this->assertTrue($r1->isPost());
        $this->assertFalse($r1->isGet());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $r2 = new Request();
        $this->assertTrue($r2->isGet());
        $this->assertFalse($r2->isPost());
    }
}
