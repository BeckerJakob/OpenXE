<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Routing;

use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased as RouteDispatcher;
use FastRoute\RouteCollector;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;

final class Router
{
    /** @var RouteCollector */
    private $routes;

    public function __construct(RouteCollector $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @return array{controller:string,action:string,scope:string,vars:array<string, string>}
     */
    public function dispatch(string $method, string $path): array
    {
        $dispatcher = new RouteDispatcher($this->routes->getData());
        $routeInfo = $dispatcher->dispatch($method, $path);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new ApiV3Exception(404, 'route_not_found', 'The requested resource was not found.');

            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new ApiV3Exception(
                    405,
                    'method_not_allowed',
                    'The request method is not allowed for this resource.',
                    ['allowed_methods' => $routeInfo[1]],
                    ['Allow' => implode(', ', $routeInfo[1])]
                );

            case Dispatcher::FOUND:
                /** @var array{0:string,1:string,2:string} $handler */
                $handler = $routeInfo[1];

                return [
                    'controller' => $handler[0],
                    'action'     => $handler[1],
                    'scope'      => $handler[2],
                    'vars'       => $routeInfo[2],
                ];
        }

        throw new ApiV3Exception(500, 'router_failure', 'The router failed to dispatch the request.');
    }
}
