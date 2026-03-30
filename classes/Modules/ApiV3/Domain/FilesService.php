<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Domain;

use RuntimeException;
use Xentral\Modules\Api\LegacyBridge\LegacyApplication;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\FileRepository;
use Xentral\Modules\ApiV3\Repository\PayablesRepository;

final class FilesService
{
    /** @var FileRepository */
    private $files;

    /** @var PayablesRepository */
    private $payables;

    /** @var LegacyApplication|null */
    private $legacyApplication;

    public function __construct(FileRepository $files, PayablesRepository $payables)
    {
        $this->files = $files;
        $this->payables = $payables;
        $this->legacyApplication = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadFile(array $payload): array
    {
        $name = trim((string)($payload['filename'] ?? ''));
        if ($name === '') {
            throw new ApiV3Exception(422, 'missing_filename', 'A `filename` is required.');
        }

        [$path, $cleanup] = $this->resolveFilePath($payload);
        try {
            $titel = (string)($payload['title'] ?? $name);
            $beschreibung = (string)($payload['description'] ?? '');
            $nummer = (string)($payload['number'] ?? '');
            $parameter = (string)($payload['parameter'] ?? ('api-v3-file-' . sha1($name . '|' . filesize($path))));
            $subject = (string)($payload['subject'] ?? 'API v3 upload');
            $object = (string)($payload['object'] ?? 'api_v3_file');
            $protected = isset($payload['protected']) ? (bool)$payload['protected'] : null;

            $fileId = (int)$this->getLegacyApplication()->erp->CreateDateiWithStichwort(
                $name,
                $titel,
                $beschreibung,
                $nummer,
                $path,
                'API v3',
                $subject,
                $object,
                $parameter,
                '',
                true,
                $protected
            );
        } finally {
            if ($cleanup !== null && is_file($cleanup)) {
                @unlink($cleanup);
            }
        }

        if ($fileId <= 0) {
            throw new ApiV3Exception(500, 'file_upload_failed', 'The file could not be stored.');
        }

        $file = $this->files->findFileById($fileId);
        if ($file === null) {
            throw new ApiV3Exception(500, 'file_upload_incomplete', 'The stored file could not be loaded afterwards.');
        }

        return $file;
    }

    /**
     * @return array<string, mixed>
     */
    public function attachToPayable(int $payableId, array $payload): array
    {
        $payable = $this->payables->findPayableById($payableId);
        if ($payable === null) {
            throw new ApiV3Exception(404, 'payable_not_found', 'The payable was not found.');
        }

        $fileId = (int)($payload['file_id'] ?? 0);
        $file = $this->files->findFileById($fileId);
        if ($file === null) {
            throw new ApiV3Exception(404, 'file_not_found', 'The file was not found.');
        }

        $subject = (string)($payload['subject'] ?? 'Verbindlichkeit');
        $object = (string)($payload['object'] ?? 'verbindlichkeit');
        $parameter = (string)$payableId;

        $this->getLegacyApplication()->erp->AddDateiStichwort(
            $fileId,
            $subject,
            $object,
            $parameter,
            true
        );

        return [
            'payable_id' => $payableId,
            'file_id'    => $fileId,
            'subject'    => $subject,
            'object'     => $object,
            'parameter'  => $parameter,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0:string,1:string|null}
     */
    private function resolveFilePath(array $payload): array
    {
        $tempPath = isset($payload['temp_path']) ? (string)$payload['temp_path'] : '';
        if ($tempPath !== '' && is_file($tempPath)) {
            return [$tempPath, null];
        }

        $contentBase64 = isset($payload['content_base64']) ? (string)$payload['content_base64'] : '';
        if ($contentBase64 === '') {
            throw new ApiV3Exception(422, 'missing_file_content', 'File content must be provided as upload or base64 string.');
        }

        $binary = base64_decode($contentBase64, true);
        if ($binary === false) {
            throw new ApiV3Exception(422, 'invalid_base64', 'The provided file content is not valid base64.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'api_v3_');
        if ($tempFile === false) {
            throw new RuntimeException('Temporary upload file could not be created.');
        }

        file_put_contents($tempFile, $binary);

        return [$tempFile, $tempFile];
    }

    private function getLegacyApplication(): LegacyApplication
    {
        if ($this->legacyApplication === null) {
            $this->legacyApplication = new LegacyApplication();
        }

        return $this->legacyApplication;
    }
}
