<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ApiV3\Http;

use PHPUnit\Framework\TestCase;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class ApiV3ResponseFactoryTest extends TestCase
{
    public function testCreatedResponseUsesUnifiedEnvelope(): void
    {
        $factory = new ApiV3ResponseFactory();

        $response = $factory->created(['id' => 123], '/api/v3/customers/123');
        $payload = json_decode((string)$response->getContent(), true);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('success', $payload['status']);
        self::assertSame(['id' => 123], $payload['data']);
        self::assertSame('/api/v3/customers/123', $response->getHeaderLine('Location'));
    }

    public function testExceptionResponseUsesErrorEnvelope(): void
    {
        $factory = new ApiV3ResponseFactory();
        $response = $factory->fromException(
            new ApiV3Exception(422, 'invalid_payload', 'Payload invalid.', ['field' => 'email'])
        );
        $payload = json_decode((string)$response->getContent(), true);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('error', $payload['status']);
        self::assertSame('invalid_payload', $payload['error']['code']);
        self::assertSame('Payload invalid.', $payload['error']['message']);
        self::assertSame('email', $payload['error']['details']['field']);
    }
}
