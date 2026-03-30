<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Domain;

use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\PartnerRepository;
use Xentral\Modules\ApiV3\Repository\PayablesRepository;
use Xentral\Modules\ApiV3\Repository\ReferenceDataRepository;

final class PayablesService
{
    /** @var PayablesRepository */
    private $payables;

    /** @var PartnerRepository */
    private $partners;

    /** @var ReferenceDataRepository */
    private $referenceData;

    public function __construct(
        PayablesRepository $payables,
        PartnerRepository $partners,
        ReferenceDataRepository $referenceData
    ) {
        $this->payables = $payables;
        $this->partners = $partners;
        $this->referenceData = $referenceData;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listPayables(array $filters, array $pagination): array
    {
        return $this->payables->searchPayables($filters, $pagination);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayable(int $id): array
    {
        $payable = $this->payables->findPayableById($id);
        if ($payable === null) {
            throw new ApiV3Exception(404, 'payable_not_found', 'The payable was not found.');
        }

        return $payable;
    }

    /**
     * @return array<string, mixed>
     */
    public function createPayable(array $payload): array
    {
        $attributes = isset($payload['attributes']) && is_array($payload['attributes'])
            ? $payload['attributes']
            : $this->buildGenericAttributes($payload);

        $supplierId = (int)($attributes['adresse'] ?? 0);
        if ($supplierId <= 0 || $this->partners->findSupplierById($supplierId) === null) {
            throw new ApiV3Exception(422, 'invalid_supplier', 'A valid supplier is required.');
        }

        $invoiceNumber = trim((string)($attributes['rechnungsnummer'] ?? ''));
        if ($invoiceNumber === '') {
            throw new ApiV3Exception(422, 'missing_invoice_number', 'An invoice number is required.');
        }

        $duplicates = $this->payables->findPayablesByInvoiceNumber($invoiceNumber, $supplierId, 1);
        if (!empty($duplicates)) {
            return $this->getPayable((int)$duplicates[0]['id']);
        }

        $payableId = $this->payables->insertPayable($attributes);

        return $this->getPayable($payableId);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function buildGenericAttributes(array $payload): array
    {
        $ledgerNumber = trim((string)($payload['ledger_account_number'] ?? ''));
        $ledgerAccount = $ledgerNumber !== ''
            ? $this->referenceData->findLedgerAccountByNumber($ledgerNumber)
            : null;

        return [
            'adresse'         => (int)($payload['supplier_id'] ?? 0),
            'belegnr'         => (string)($payload['external_ref'] ?? ''),
            'rechnungsnummer' => (string)($payload['invoice_number'] ?? ''),
            'datum'           => (string)($payload['invoice_date'] ?? date('Y-m-d')),
            'lieferdatum'     => (string)($payload['delivery_date'] ?? ($payload['invoice_date'] ?? date('Y-m-d'))),
            'faelligkeitsdatum' => (string)($payload['due_date'] ?? ($payload['invoice_date'] ?? date('Y-m-d'))),
            'projekt'         => (int)($payload['project_id'] ?? 2),
            'waehrung'        => (string)($payload['currency'] ?? 'EUR'),
            'betragnetto'     => (float)($payload['amount_net'] ?? 0),
            'betragsteuer'    => (float)($payload['amount_tax'] ?? 0),
            'betragbrutto'    => (float)($payload['amount_gross'] ?? 0),
            'sachkonto'       => (string)($ledgerAccount['account_number'] ?? $ledgerNumber),
            'status'          => (string)($payload['status'] ?? 'angelegt'),
            'zahlungsweise'   => (string)($payload['payment_method'] ?? 'rechnung'),
            'referenz'        => (string)($payload['reference'] ?? ''),
            'notiz'           => (string)($payload['note'] ?? ''),
        ];
    }
}
