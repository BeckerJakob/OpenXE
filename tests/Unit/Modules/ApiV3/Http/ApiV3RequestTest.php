<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ApiV3\Http;

use PHPUnit\Framework\TestCase;
use Xentral\Components\Http\Request;
use Xentral\Modules\ApiV3\Http\ApiV3Request;

final class ApiV3RequestTest extends TestCase
{
    public function testExtractsPatchMethodBearerTokenAndIdempotencyKey(): void
    {
        $request = new Request(
            ['page' => '2', 'per_page' => '25'],
            [],
            [],
            [
                'REQUEST_METHOD' => 'PATCH',
                'PATH_INFO' => '/customers/42',
                'HTTP_AUTHORIZATION' => 'Bearer test-token',
                'HTTP_IDEMPOTENCY_KEY' => 'idem-123',
            ],
            [],
            '{"name":"Max"}'
        );

        $apiRequest = new ApiV3Request($request);

        self::assertSame('PATCH', $apiRequest->getMethod());
        self::assertSame('/customers/42', $apiRequest->getPath());
        self::assertSame('test-token', $apiRequest->getBearerToken());
        self::assertSame('idem-123', $apiRequest->getIdempotencyKey());
        self::assertSame(['name' => 'Max'], $apiRequest->getJsonBody());
        self::assertSame(
            ['page' => 2, 'per_page' => 25, 'offset' => 25],
            $apiRequest->getPagination()
        );
    }

    public function testBuildsPathFromRequestUriWhenPathInfoIsMissing(): void
    {
        $request = new Request(
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/www/api/v3/index.php/products/15?foo=bar',
                'SCRIPT_NAME' => '/www/api/v3/index.php',
            ]
        );

        $apiRequest = new ApiV3Request($request);

        self::assertSame('/products/15', $apiRequest->getPath());
    }
}
