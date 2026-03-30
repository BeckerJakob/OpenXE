<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

abstract class AbstractController
{
    /** @var ApiV3ResponseFactory */
    protected $responses;

    public function __construct(ApiV3ResponseFactory $responses)
    {
        $this->responses = $responses;
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function created(array $body, ?string $location = null): JsonResponse
    {
        return $this->responses->created($body, $location);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $meta
     */
    protected function success(array $body, array $meta = []): JsonResponse
    {
        return $this->responses->success($body, 200, $meta);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function successCollection(array $items, array $pagination, int $total): JsonResponse
    {
        return $this->responses->success(
            ['items' => $items],
            200,
            [
                'pagination' => [
                    'page'     => $pagination['page'],
                    'per_page' => $pagination['per_page'],
                    'total'    => $total,
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireJsonBody(ApiV3Request $request): array
    {
        $body = $request->getJsonBody();
        if ($body === []) {
            throw new ApiV3Exception(422, 'empty_request_body', 'A JSON request body is required.');
        }

        return $body;
    }
}
