<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Engine;

use Throwable;
use Xentral\Components\Http\JsonResponse;
use Xentral\Components\Http\Request;
use Xentral\Components\Http\Response;
use Xentral\Modules\ApiV3\Auth\OpaqueTokenAuthenticator;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;
use Xentral\Modules\ApiV3\Repository\IdempotencyRepository;
use Xentral\Modules\ApiV3\Repository\ApiV3TokenRepository;
use Xentral\Modules\ApiV3\Routing\Router;

final class ApiV3Application
{
    /** @var ApiV3Container */
    private $container;

    public function __construct(ApiV3Container $container)
    {
        $this->container = $container;
    }

    public function handle(?Request $request = null): Response
    {
        if ($request !== null) {
            $this->container->setRequest($request);
        }

        /** @var ApiV3ResponseFactory $responses */
        $responses = $this->container->get(ApiV3ResponseFactory::class);

        try {
            /** @var SchemaManager $schemaManager */
            $schemaManager = $this->container->get(SchemaManager::class);
            $schemaManager->ensureSchema();

            /** @var ApiV3Request $apiRequest */
            $apiRequest = $this->container->get(ApiV3Request::class);
            /** @var Router $router */
            $router = $this->container->get(Router::class);
            $route = $router->dispatch($apiRequest->getMethod(), $apiRequest->getPath());

            /** @var OpaqueTokenAuthenticator $authenticator */
            $authenticator = $this->container->get(OpaqueTokenAuthenticator::class);
            $principal = $authenticator->authenticate($apiRequest);
            $authenticator->assertScope($principal, $route['scope']);

            $cachedResponse = $this->loadIdempotentResponse($apiRequest, $principal);
            if ($cachedResponse !== null) {
                return $cachedResponse;
            }

            $controller = $this->container->getController($route['controller']);
            $action = $route['action'];
            /** @var JsonResponse $response */
            $response = $controller->$action($apiRequest, $principal, $route['vars']);

            $this->storeIdempotentResponse($apiRequest, $principal, $response);
            /** @var ApiV3TokenRepository $tokenRepository */
            $tokenRepository = $this->container->get(ApiV3TokenRepository::class);
            $tokenRepository->purgeExpiredIdempotencyKeys();

            return $response;
        } catch (ApiV3Exception $exception) {
            return $responses->fromException($exception);
        } catch (Throwable $throwable) {
            return $responses->internalServerError($throwable);
        }
    }

    /**
     * @param array<string, mixed> $principal
     */
    private function loadIdempotentResponse(ApiV3Request $request, array $principal): ?JsonResponse
    {
        $idempotencyKey = $request->getIdempotencyKey();
        if ($idempotencyKey === '' || !$request->isWriteMethod()) {
            return null;
        }

        $requestHash = $this->buildRequestHash($request);
        /** @var IdempotencyRepository $repository */
        $repository = $this->container->get(IdempotencyRepository::class);
        $record = $repository->find(
            (int)$principal['api_account_id'],
            $request->getMethod(),
            $request->getPath(),
            $idempotencyKey
        );

        if ($record === null) {
            return null;
        }

        if ((string)$record['request_hash'] !== $requestHash) {
            throw new ApiV3Exception(
                Response::HTTP_CONFILICT,
                'idempotency_conflict',
                'The supplied idempotency key has already been used with a different payload.'
            );
        }

        $payload = json_decode((string)$record['response_body'], true);
        if (!is_array($payload)) {
            throw new ApiV3Exception(500, 'idempotency_corrupt', 'The cached idempotency response is invalid.');
        }

        return new JsonResponse($payload, (int)$record['response_status']);
    }

    /**
     * @param array<string, mixed> $principal
     */
    private function storeIdempotentResponse(ApiV3Request $request, array $principal, JsonResponse $response): void
    {
        $idempotencyKey = $request->getIdempotencyKey();
        if ($idempotencyKey === '' || !$request->isWriteMethod()) {
            return;
        }

        /** @var IdempotencyRepository $repository */
        $repository = $this->container->get(IdempotencyRepository::class);
        $repository->store(
            (int)$principal['api_account_id'],
            $request->getMethod(),
            $request->getPath(),
            $idempotencyKey,
            $this->buildRequestHash($request),
            $response->getStatusCode(),
            (string)$response->getContent()
        );
    }

    private function buildRequestHash(ApiV3Request $request): string
    {
        return hash(
            'sha256',
            $request->getMethod() . "\n" . $request->getPath() . "\n" . trim($request->getBody())
        );
    }
}
