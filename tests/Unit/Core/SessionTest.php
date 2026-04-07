<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset session state przed każdym testem (PHPUnit nie startuje sesji)
        $_SESSION = [];
    }

    public function testFlashStoresInExpectedFormat(): void
    {
        Session::flash('success', 'Konto utworzone');

        $this->assertArrayHasKey('flash', $_SESSION);
        $this->assertSame(['type' => 'success', 'message' => 'Konto utworzone'], $_SESSION['flash']);
    }

    public function testGetFlashReturnsAndClears(): void
    {
        Session::flash('error', 'Coś poszło nie tak');

        $flash = Session::getFlash();

        $this->assertSame(['type' => 'error', 'message' => 'Coś poszło nie tak'], $flash);
        $this->assertArrayNotHasKey('flash', $_SESSION);
    }

    public function testGetFlashReturnsEmptyWhenNoFlash(): void
    {
        $this->assertSame([], Session::getFlash());
    }

    public function testFlashOverwritesPreviousFlash(): void
    {
        Session::flash('info', 'first');
        Session::flash('error', 'second');

        $this->assertSame(['type' => 'error', 'message' => 'second'], $_SESSION['flash']);
    }

    public function testSetGetHasDelete(): void
    {
        $this->assertFalse(Session::has('user_id'));

        Session::set('user_id', 'uuid-123');

        $this->assertTrue(Session::has('user_id'));
        $this->assertSame('uuid-123', Session::get('user_id'));
        $this->assertSame('default', Session::get('non_existent', 'default'));

        Session::delete('user_id');
        $this->assertFalse(Session::has('user_id'));
    }
}
