<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Http;

use RuntimeException;
use Throwable;

final class ApiV3Exception extends RuntimeException
{
    /** @var int */
    private $statusCode;

    /** @var string */
    private $errorCode;

    /** @var array<string, mixed> */
    private $details;

    /** @var array<string, string|string[]> */
    private $headers;

    /**
     * @param array<string, mixed>          $details
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        int $statusCode,
        string $errorCode,
        string $message,
        array $details = [],
        array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
        $this->headers = $headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
