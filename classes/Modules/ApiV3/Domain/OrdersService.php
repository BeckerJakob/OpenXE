<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Domain;

use Xentral\Modules\Api\LegacyBridge\LegacyApplication;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\SalesOrderRepository;

final class OrdersService
{
    /** @var SalesOrderRepository */
    private $orders;

    /** @var LegacyApplication */
    private $legacy;

    public function __construct(SalesOrderRepository $orders, LegacyApplication $legacy)
    {
        $this->orders = $orders;
        $this->legacy = $legacy;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listSalesOrders(array $filters, array $pagination): array
    {
        return $this->orders->searchSalesOrders($filters, $pagination);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSalesOrder(int $id): array
    {
        $order = $this->orders->findSalesOrderById($id);
        if ($order === null) {
            throw new ApiV3Exception(404, 'sales_order_not_found', 'The sales order was not found.');
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function createSalesOrder(array $payload): array
    {
        $orderAttributes = isset($payload['attributes']) && is_array($payload['attributes'])
            ? $payload['attributes']
            : $this->buildGenericOrderAttributes($payload);

        $externalRef = trim((string)($orderAttributes['belegnr'] ?? ($payload['external_ref'] ?? '')));
        if ($externalRef === '') {
            throw new ApiV3Exception(422, 'missing_external_ref', 'A sales order external reference is required.');
        }

        $existing = $this->orders->findSalesOrderByExternalRef($externalRef);
        if ($existing !== null) {
            return $this->getSalesOrder((int)$existing['id']);
        }

        $positions = [];
        if (isset($payload['positions']) && is_array($payload['positions'])) {
            foreach ($payload['positions'] as $positionIndex => $positionPayload) {
                if (!is_array($positionPayload)) {
                    continue;
                }

                $positions[] = isset($positionPayload['attributes']) && is_array($positionPayload['attributes'])
                    ? $positionPayload['attributes']
                    : $this->buildGenericPositionAttributes($positionPayload, $positionIndex + 1);
            }
        }

        $orderId = $this->orders->createSalesOrder($orderAttributes, $positions);

        return $this->getSalesOrder($orderId);
    }

    /**
     * @return array{invoice_id:int,sales_order_id:int,document_number:string}
     */
    public function createInvoiceFromSalesOrder(int $orderId): array
    {
        $order = $this->orders->findSalesOrderById($orderId);
        if ($order === null) {
            throw new ApiV3Exception(404, 'sales_order_not_found', 'The sales order was not found.');
        }
        if (empty($order['positions'])) {
            throw new ApiV3Exception(
                422,
                'sales_order_without_positions',
                'The sales order has no line items; cannot create an invoice.'
            );
        }

        $app = $this->legacy;
        class_exists('FormHandler', true);
        if (empty($app->FormHandler)) {
            $app->FormHandler = new \FormHandler($app);
        }

        $invoiceId = (int)$app->erp->WeiterfuehrenAuftragZuRechnung($orderId);
        if ($invoiceId <= 0) {
            throw new ApiV3Exception(
                422,
                'invoice_creation_failed',
                'Could not create invoice from sales order.'
            );
        }

        $app->erp->AuftragProtokoll($orderId, 'Auftrag manuell als Rechnung weitergeführt');
        $app->erp->BelegFreigabe('rechnung', $invoiceId);

        $belegnr = (string)$app->DB->Select(
            sprintf('SELECT belegnr FROM rechnung WHERE id = %d LIMIT 1', $invoiceId)
        );

        return [
            'invoice_id'      => $invoiceId,
            'sales_order_id'  => $orderId,
            'document_number' => $belegnr,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildGenericOrderAttributes(array $payload): array
    {
        return [
            'projekt'         => (int)($payload['project_id'] ?? 2),
            'belegnr'         => (string)($payload['external_ref'] ?? ''),
            'internet'        => (string)($payload['external_ref'] ?? ''),
            'datum'           => (string)($payload['order_date'] ?? date('Y-m-d')),
            'sortierdatum'    => (string)($payload['ordered_at'] ?? date('Y-m-d H:i:s')),
            'art'             => 'standardauftrag',
            'status'          => (string)($payload['status'] ?? 'freigegeben'),
            'adresse'         => (int)($payload['customer_id'] ?? 0),
            'kundennummer'    => (string)($payload['customer_number'] ?? ''),
            'name'            => (string)($payload['name'] ?? ''),
            'strasse'         => (string)($payload['street'] ?? ''),
            'plz'             => (string)($payload['postal_code'] ?? ''),
            'ort'             => (string)($payload['city'] ?? ''),
            'land'            => (string)($payload['country_code'] ?? 'DE'),
            'email'           => (string)($payload['email'] ?? ''),
            'telefon'         => (string)($payload['phone'] ?? ''),
            'versandart'      => (string)($payload['shipping_method'] ?? 'dpd'),
            'zahlungsweise'   => (string)($payload['payment_method'] ?? 'rechnung'),
            'gesamtsumme'     => (float)($payload['total_gross'] ?? 0),
            'erloes_netto'    => (float)($payload['total_net'] ?? 0),
            'umsatz_netto'    => (float)($payload['total_net'] ?? 0),
            'firma'           => 0,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildGenericPositionAttributes(array $payload, int $position): array
    {
        return [
            'sort'         => $position,
            'artikel'      => (int)($payload['article_id'] ?? 0),
            'bezeichnung'  => (string)($payload['name'] ?? ''),
            'beschreibung' => (string)($payload['description'] ?? ''),
            'nummer'       => (string)($payload['sku'] ?? ''),
            'menge'        => (float)($payload['quantity'] ?? 1),
            'preis'        => (float)($payload['unit_price'] ?? 0),
            'umsatzsteuer' => (string)($payload['tax_code'] ?? 'normal'),
        ];
    }
}
