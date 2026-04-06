<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function makeRequest(string $method, string $path): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $path;
        return new Request();
    }

    public function testStaticGetRoute(): void
    {
        $router = new Router();
        $called = false;

        $router->get('/test', function () use (&$called) {
            $called = true;
        });

        $router->dispatch($this->makeRequest('GET', '/test'));
        $this->assertTrue($called);
    }

    public function testStaticPostRoute(): void
    {
        $router = new Router();
        $called = false;

        $router->post('/submit', function () use (&$called) {
            $called = true;
        });

        $router->dispatch($this->makeRequest('POST', '/submit'));
        $this->assertTrue($called);
    }

    public function testRouteWithParameter(): void
    {
        $router  = new Router();
        $capturedId = null;

        $router->get('/items/{id}', function (Request $req) use (&$capturedId) {
            $capturedId = $req->getRouteParam('id');
        });

        $router->dispatch($this->makeRequest('GET', '/items/42'));
        $this->assertSame('42', $capturedId);
    }

    public function testNotFoundThrowsException(): void
    {
        $this->expectException(NotFoundException::class);

        $router = new Router();
        $router->dispatch($this->makeRequest('GET', '/nonexistent'));
    }

    public function testGroupPrefixApplied(): void
    {
        $router  = new Router();
        $called  = false;

        $router->group('/api', [], function (Router $r) use (&$called) {
            $r->get('/users', function () use (&$called) {
                $called = true;
            });
        });

        $router->dispatch($this->makeRequest('GET', '/api/users'));
        $this->assertTrue($called);
    }

    public function testMiddlewareIsExecuted(): void
    {
        $router  = new Router();
        $log     = [];

        $middleware = new class($log) {
            private array $log;
            public function __construct(array &$log) { $this->log = &$log; }
            public function handle(Request $req): bool
            {
                $this->log[] = 'middleware';
                return true;
            }
        };

        $router->get('/protected', function () use (&$log) {
            $log[] = 'handler';
        }, [[$middleware, 'handle']]);

        $router->dispatch($this->makeRequest('GET', '/protected'));
        $this->assertSame(['middleware', 'handler'], $log);
    }
}
