<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Controller;

use Xentral\Components\Http\JsonResponse;
use Xentral\Modules\ApiV3\Domain\ProductService;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;

final class ProductsController extends AbstractController
{
    /** @var ProductService */
    private $products;

    public function __construct(ApiV3ResponseFactory $responses, ProductService $products)
    {
        parent::__construct($responses);
        $this->products = $products;
    }

    public function listProducts(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $pagination = $request->getPagination();
        $result = $this->products->listProducts(
            [
                'sku'   => $request->getQueryParam('sku', ''),
                'query' => $request->getQueryParam('query', ''),
            ],
            $pagination
        );

        return $this->successCollection($result['items'], $pagination, $result['total']);
    }

    public function getProduct(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->products->getProduct((int)$vars['id']));
    }

    public function createProduct(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $product = $this->products->createProduct($this->requireJsonBody($request));

        return $this->created($product, '/api/v3/products/' . $product['id']);
    }

    public function updateProduct(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        return $this->success($this->products->updateProduct((int)$vars['id'], $this->requireJsonBody($request)));
    }

    public function addSupplierPrice(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $result = $this->products->addSupplierPrice((int)$vars['id'], $this->requireJsonBody($request));

        return $this->created($result);
    }

    public function updateInventoryLevel(ApiV3Request $request, array $principal, array $vars = []): JsonResponse
    {
        $result = $this->products->updateInventoryLevel(
            (int)$vars['locationId'],
            (string)$vars['sku'],
            $this->requireJsonBody($request)
        );

        return $this->success($result);
    }
}
