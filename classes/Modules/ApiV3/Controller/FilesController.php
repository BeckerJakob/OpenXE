<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Domain\FilesService;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class FilesController extends AbstractController
{
    /** @var FilesService */
    private $files;

    public function __construct(ApiV3ResponseFactory $responses, FilesService $files)
    {
        parent::__construct($responses);
        $this->files = $files;
    }

    public function upload(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $upload = $request->getUploadedFile('file');
        if ($upload !== null) {
            if ($upload->hasError()) {
                throw new ApiV3Exception(422, 'file_upload_error', $upload->getErrorMessage());
            }

            $payload = [
                'filename'    => $upload->getClientFileName(),
                'temp_path'   => $upload->getRealPath(),
                'title'       => (string)$request->getQueryParam('title', $upload->getClientFileName()),
                'description' => (string)$request->getQueryParam('description', ''),
                'subject'     => (string)$request->getQueryParam('subject', 'API v3 upload'),
                'object'      => (string)$request->getQueryParam('object', 'api_v3_file'),
                'parameter'   => (string)$request->getQueryParam('parameter', ''),
            ];
        } else {
            $payload = $this->requireJsonBody($request);
        }

        $file = $this->files->uploadFile($payload);

        return $this->created($file, '/api/v3/files/' . $file['id']);
    }
}
