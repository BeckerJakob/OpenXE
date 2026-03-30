<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;
use Xentral\Modules\ApiV3\Repository\ReferenceDataRepository;

final class ReferenceDataController extends AbstractController
{
    /** @var ReferenceDataRepository */
    private $referenceData;

    public function __construct(ApiV3ResponseFactory $responses, ReferenceDataRepository $referenceData)
    {
        parent::__construct($responses);
        $this->referenceData = $referenceData;
    }

    public function me(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success([
            'api_account_id' => (int)$principal['api_account_id'],
            'token_id'       => (int)$principal['token_id'],
            'token_label'    => (string)$principal['label'],
            'scopes'         => $principal['scopes'],
            'expires_at'     => $principal['expires_at'],
        ]);
    }

    public function projects(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listProjects()]);
    }

    public function warehouseLocations(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listWarehouseLocations()]);
    }

    public function paymentMethods(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listPaymentMethods()]);
    }

    public function shippingMethods(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listShippingMethods()]);
    }

    public function taxRates(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listTaxRates()]);
    }

    public function bankAccounts(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listBankAccounts()]);
    }

    public function ledgerAccounts(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success(['items' => $this->referenceData->listLedgerAccounts()]);
    }
}
