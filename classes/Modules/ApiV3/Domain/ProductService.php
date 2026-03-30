<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Domain;

use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\ProductRepository;

final class ProductService
{
    /** @var ProductRepository */
    private $products;

    public function __construct(ProductRepository $products)
    {
        $this->products = $products;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listProducts(array $filters, array $pagination): array
    {
        return $this->products->searchProducts($filters, $pagination);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(int $id): array
    {
        $product = $this->products->findProductById($id);
        if ($product === null) {
            throw new ApiV3Exception(404, 'product_not_found', 'The product was not found.');
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    public function createProduct(array $payload): array
    {
        $attributes = $this->extractProductAttributes($payload);
        $sku = trim((string)($attributes['nummer'] ?? ''));
        if ($sku === '') {
            throw new ApiV3Exception(422, 'missing_sku', 'A product SKU is required.');
        }

        $existing = $this->products->findProductBySku($sku);
        if ($existing !== null) {
            return $existing;
        }

        $productId = $this->products->insertProduct($attributes);

        return $this->getProduct($productId);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateProduct(int $id, array $payload): array
    {
        $this->getProduct($id);
        $attributes = $this->extractProductAttributes($payload, false);
        if (empty($attributes)) {
            throw new ApiV3Exception(422, 'empty_product_patch', 'No updatable product fields were provided.');
        }

        $this->products->updateProduct($id, $attributes);

        return $this->getProduct($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function addSupplierPrice(int $productId, array $payload): array
    {
        $product = $this->getProduct($productId);
        $attributes = isset($payload['attributes']) && is_array($payload['attributes'])
            ? $payload['attributes']
            : [
                'artikel'     => $productId,
                'adresse'     => (int)($payload['supplier_id'] ?? 0),
                'projekt'     => (string)($payload['project_id'] ?? ''),
                'preis'       => (float)($payload['purchase_price'] ?? 0),
                'waehrung'    => (string)($payload['currency'] ?? 'EUR'),
                'bezeichnung' => (string)($payload['label'] ?? ''),
            ];

        if ((int)($attributes['artikel'] ?? 0) === 0) {
            $attributes['artikel'] = $productId;
        }

        $priceId = $this->products->insertSupplierPrice($attributes);

        return [
            'id'         => $priceId,
            'product_id' => (int)$product['id'],
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateInventoryLevel(int $locationId, string $sku, array $payload): array
    {
        $product = $this->products->findProductBySku($sku);
        if ($product === null) {
            throw new ApiV3Exception(404, 'product_not_found', 'The product for the requested SKU was not found.');
        }

        $quantity = (float)($payload['quantity'] ?? 0);
        $projectId = (int)($payload['project_id'] ?? 2);

        $existing = $this->products->findInventoryLevel($locationId, (int)$product['id']);
        if ($existing !== null) {
            $this->products->updateInventoryLevel((int)$existing['id'], $quantity);
        } else {
            $this->products->insertInventoryLevel($locationId, (int)$product['id'], $quantity, $projectId);
        }

        return [
            'location_id' => $locationId,
            'sku'         => $sku,
            'article_id'  => (int)$product['id'],
            'quantity'    => $quantity,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param bool                 $withDefaults
     *
     * @return array<string, mixed>
     */
    private function extractProductAttributes(array $payload, bool $withDefaults = true): array
    {
        if (isset($payload['attributes']) && is_array($payload['attributes'])) {
            return $payload['attributes'];
        }

        $attributes = [
            'nummer'        => (string)($payload['sku'] ?? ''),
            'name_de'       => (string)($payload['name'] ?? ''),
            'kurztext_de'   => (string)($payload['short_text'] ?? ''),
            'beschreibung_de' => (string)($payload['description'] ?? ''),
            'typ'           => (string)($payload['type'] ?? 'produkt'),
            'projekt'       => (int)($payload['project_id'] ?? 2),
            'adresse'       => (int)($payload['supplier_id'] ?? 0),
            'umsatzsteuer'  => (string)($payload['tax_code'] ?? 'normal'),
            'lagerartikel'  => (int)($payload['is_stocked'] ?? 1),
        ];

        if ($withDefaults) {
            $attributes += [
                'checksum'      => '',
                'inaktiv'       => 0,
                'ausverkauft'   => 0,
                'geloescht'     => 0,
                'klasse'        => '',
                'gueltigbis'    => '0000-00-00',
                'firma'         => 0,
                'warengruppe'   => '',
                'name_en'       => '',
                'kurztext_en'   => '',
                'beschreibung_en' => '',
            ];
        }

        return array_filter(
            $attributes,
            static function ($value): bool {
                return $value !== null;
            }
        );
    }
}
