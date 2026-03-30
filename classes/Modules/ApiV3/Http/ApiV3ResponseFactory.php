<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Http;

use Throwable;
use Xentral\Components\Http\JsonResponse;
use Xentral\Components\Http\Response;

final class ApiV3ResponseFactory
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @param array<string, string|string[]> $headers
     */
    public function success(array $data = [], int $statusCode = Response::HTTP_OK, array $meta = [], array $headers = []): JsonResponse
    {
        $payload = [
            'status' => 'success',
            'data'   => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return $this->buildResponse($payload, $statusCode, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|string[]> $headers
     */
    public function created(array $data, ?string $location = null, array $headers = []): JsonResponse
    {
        if ($location !== null && $location !== '') {
            $headers['Location'] = $location;
        }

        return $this->success($data, Response::HTTP_CREATED, [], $headers);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string|string[]> $headers
     */
    public function error(int $statusCode, string $errorCode, string $message, array $details = [], array $headers = []): JsonResponse
    {
        return $this->buildResponse(
            [
                'status' => 'error',
                'error'  => [
                    'code'    => $errorCode,
                    'message' => $message,
                    'details' => (object)$details,
                ],
            ],
            $statusCode,
            $headers
        );
    }

    public function fromException(ApiV3Exception $exception): JsonResponse
    {
        return $this->error(
            $exception->getStatusCode(),
            $exception->getErrorCode(),
            $exception->getMessage(),
            $exception->getDetails(),
            $exception->getHeaders()
        );
    }

    public function internalServerError(Throwable $throwable): JsonResponse
    {
        return $this->error(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'internal_server_error',
            'An unexpected server error occurred.',
            ['exception' => get_class($throwable)]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|string[]> $headers
     */
    private function buildResponse(array $payload, int $statusCode, array $headers = []): JsonResponse
    {
        $response = new JsonResponse($payload, $statusCode);
        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }
}
