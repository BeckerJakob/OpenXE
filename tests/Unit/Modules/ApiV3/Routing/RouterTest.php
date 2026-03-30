<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ApiV3\Routing;

use PHPUnit\Framework\TestCase;
use Xentral\Modules\ApiV3\Auth\ScopeRegistry;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Routing\RouteCollectionFactory;
use Xentral\Modules\ApiV3\Routing\Router;

final class RouterTest extends TestCase
{
    public function testDispatchesKnownRoute(): void
    {
        $router = new Router((new RouteCollectionFactory())->create());

        $route = $router->dispatch('PATCH', '/customers/42');

        self::assertSame('Xentral\Modules\ApiV3\Controller\PartnersController', $route['controller']);
        self::assertSame('updateCustomer', $route['action']);
        self::assertSame(ScopeRegistry::CUSTOMERS_WRITE, $route['scope']);
        self::assertSame(['id' => '42'], $route['vars']);
    }

    public function testThrowsMethodNotAllowedForWrongVerb(): void
    {
        $this->expectException(ApiV3Exception::class);
        $this->expectExceptionMessage('The request method is not allowed for this resource.');

        $router = new Router((new RouteCollectionFactory())->create());
        $router->dispatch('DELETE', '/customers/42');
    }
}
