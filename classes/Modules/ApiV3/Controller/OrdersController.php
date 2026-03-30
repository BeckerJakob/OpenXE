<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Domain\OrdersService;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class OrdersController extends AbstractController
{
    /** @var OrdersService */
    private $orders;

    public function __construct(ApiV3ResponseFactory $responses, OrdersService $orders)
    {
        parent::__construct($responses);
        $this->orders = $orders;
    }

    public function listSalesOrders(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $pagination = $request->getPagination();
        $result = $this->orders->listSalesOrders(
            [
                'external_ref' => $request->getQueryParam('external_ref', ''),
                'customer_id'  => $request->getQueryParam('customer_id', ''),
            ],
            $pagination
        );

        return $this->successCollection($result['items'], $pagination, $result['total']);
    }

    public function getSalesOrder(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->orders->getSalesOrder((int)$vars['id']));
    }

    public function createSalesOrder(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $order = $this->orders->createSalesOrder($this->requireJsonBody($request));

        return $this->created($order, '/api/v3/sales-orders/' . $order['id']);
    }
}
