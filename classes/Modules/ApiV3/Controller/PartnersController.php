<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Domain\PartnerService;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class PartnersController extends AbstractController
{
    /** @var PartnerService */
    private $partners;

    public function __construct(ApiV3ResponseFactory $responses, PartnerService $partners)
    {
        parent::__construct($responses);
        $this->partners = $partners;
    }

    public function listCustomers(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $pagination = $request->getPagination();
        $result = $this->partners->listCustomers(
            [
                'customer_number' => $request->getQueryParam('customer_number', ''),
                'email'           => $request->getQueryParam('email', ''),
                'query'           => $request->getQueryParam('query', ''),
            ],
            $pagination
        );

        return $this->successCollection($result['items'], $pagination, $result['total']);
    }

    public function getCustomer(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->partners->getCustomer((int)$vars['id']));
    }

    public function createCustomer(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $customer = $this->partners->createCustomer($this->requireJsonBody($request));

        return $this->created($customer, '/api/v3/customers/' . $customer['id']);
    }

    public function updateCustomer(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->partners->updateCustomer((int)$vars['id'], $this->requireJsonBody($request)));
    }

    public function createCustomerProjectLink(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $result = $this->partners->createCustomerProjectLink((int)$vars['id'], $this->requireJsonBody($request));

        return $this->created($result);
    }

    public function listSuppliers(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $pagination = $request->getPagination();
        $result = $this->partners->listSuppliers(
            (string)$request->getQueryParam('supplier_number', ''),
            $pagination
        );

        return $this->successCollection($result['items'], $pagination, $result['total']);
    }

    public function getSupplier(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->partners->getSupplier((int)$vars['id']));
    }
}
