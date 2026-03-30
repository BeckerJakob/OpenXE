<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Domain\FilesService;
use Xentral\Modules\ApiV3\Domain\PayablesService;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class PayablesController extends AbstractController
{
    /** @var PayablesService */
    private $payables;

    /** @var FilesService */
    private $files;

    public function __construct(ApiV3ResponseFactory $responses, PayablesService $payables, FilesService $files)
    {
        parent::__construct($responses);
        $this->payables = $payables;
        $this->files = $files;
    }

    public function listPayables(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $pagination = $request->getPagination();
        $result = $this->payables->listPayables(
            [
                'invoice_number' => $request->getQueryParam('invoice_number', ''),
                'supplier_id'    => $request->getQueryParam('supplier_id', ''),
            ],
            $pagination
        );

        return $this->successCollection($result['items'], $pagination, $result['total']);
    }

    public function getPayable(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->payables->getPayable((int)$vars['id']));
    }

    public function createPayable(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $payable = $this->payables->createPayable($this->requireJsonBody($request));

        return $this->created($payable, '/api/v3/payables/' . $payable['id']);
    }

    public function attachFile(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->created($this->files->attachToPayable((int)$vars['id'], $this->requireJsonBody($request)));
    }
}
