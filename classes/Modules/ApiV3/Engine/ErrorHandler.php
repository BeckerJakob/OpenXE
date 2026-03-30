<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Engine;

use ErrorException;
use Throwable;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class ErrorHandler
{
    /** @var ApiV3ResponseFactory */
    private $responses;

    public function __construct(ApiV3ResponseFactory $responses)
    {
        $this->responses = $responses;
    }

    public function register(): void
    {
        register_shutdown_function([$this, 'onShutdown']);
        set_exception_handler([$this, 'onException']);
        set_error_handler([$this, 'onError']);
    }

    public function onShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        if (!in_array((int)$error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }

        $this->sendThrowable(new ErrorException(
            (string)$error['message'],
            0,
            (int)$error['type'],
            (string)$error['file'],
            (int)$error['line']
        ));
    }

    public function onException(Throwable $throwable): void
    {
        $this->sendThrowable($throwable);
    }

    public function onError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    private function sendThrowable(Throwable $throwable): void
    {
        if (!headers_sent()) {
            $this->responses->internalServerError($throwable)->send();
        }
        exit;
    }
}
