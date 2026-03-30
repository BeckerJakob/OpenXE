<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Http;

use Xentral\Components\Http\File\FileUpload;
use Xentral\Components\Http\Request;

final class ApiV3Request
{
    /** @var Request */
    private $request;

    /** @var array<string, mixed>|null */
    private $jsonBody;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->jsonBody = null;
    }

    public function getRawRequest(): Request
    {
        return $this->request;
    }

    public function getMethod(): string
    {
        return strtoupper($this->request->getMethod());
    }

    public function getPath(): string
    {
        $path = trim((string)$this->request->getPathInfo());
        if ($path !== '') {
            return '/' . ltrim($path, '/');
        }

        $path = trim((string)$this->request->getGet('path', ''));
        if ($path !== '') {
            return '/' . ltrim($path, '/');
        }

        $requestPath = (string)parse_url((string)$this->request->getRequestUri(), PHP_URL_PATH);
        $scriptName = str_replace('\\', '/', (string)$this->request->getServer('SCRIPT_NAME', ''));
        $scriptDir = str_replace('\\', '/', dirname($scriptName));

        if ($scriptName !== '' && strpos($requestPath, $scriptName) === 0) {
            $requestPath = substr($requestPath, strlen($scriptName));
        } elseif ($scriptDir !== '' && $scriptDir !== '/' && strpos($requestPath, $scriptDir) === 0) {
            $requestPath = substr($requestPath, strlen($scriptDir));
        }

        $requestPath = '/' . ltrim($requestPath, '/');

        return $requestPath === '//' ? '/' : $requestPath;
    }

    public function getQueryParam(string $name, $default = null)
    {
        return $this->request->getGet($name, $default);
    }

    public function getHeader(string $name, $default = null)
    {
        return $this->request->getHeader($name, $default);
    }

    public function getContentType(): ?string
    {
        return $this->request->getContentType();
    }

    public function getBody(): string
    {
        return $this->request->getContent();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $body = trim($this->getBody());
        if ($body === '') {
            $this->jsonBody = [];

            return $this->jsonBody;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ApiV3Exception(400, 'invalid_json', 'Request body must contain a valid JSON object.');
        }

        $this->jsonBody = $decoded;

        return $this->jsonBody;
    }

    public function getIdempotencyKey(): string
    {
        return trim((string)$this->getHeader('Idempotency-Key', ''));
    }

    public function getBearerToken(): string
    {
        $header = trim((string)$this->getHeader('Authorization', ''));
        if ($header === '') {
            return '';
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) !== 1) {
            return '';
        }

        return trim((string)$matches[1]);
    }

    public function getUploadedFile(string $name): ?FileUpload
    {
        $file = $this->request->getFile($name);

        return $file instanceof FileUpload ? $file : null;
    }

    public function isWriteMethod(): bool
    {
        return in_array($this->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * @return array{page:int,per_page:int,offset:int}
     */
    public function getPagination(int $defaultPerPage = 50, int $maxPerPage = 200): array
    {
        $page = max(1, (int)$this->getQueryParam('page', 1));
        $perPage = max(1, min($maxPerPage, (int)$this->getQueryParam('per_page', $defaultPerPage)));

        return [
            'page'     => $page,
            'per_page' => $perPage,
            'offset'   => ($page - 1) * $perPage,
        ];
    }
}
