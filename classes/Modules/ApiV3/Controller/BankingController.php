<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Domain\BankingService;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class BankingController extends AbstractController
{
    /** @var BankingService */
    private $banking;

    public function __construct(ApiV3ResponseFactory $responses, BankingService $banking)
    {
        parent::__construct($responses);
        $this->banking = $banking;
    }

    public function listBankTransactions(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $pagination = $request->getPagination();
        $result = $this->banking->listTransactions(
            [
                'account_id' => $request->getQueryParam('account_id', ''),
                'from'       => $request->getQueryParam('from', ''),
            ],
            $pagination
        );

        return $this->successCollection($result['items'], $pagination, $result['total']);
    }

    public function importBankTransactions(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->created($this->banking->importTransactions($this->requireJsonBody($request)));
    }
}
