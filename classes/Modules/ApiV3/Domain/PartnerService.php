<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Domain;

use Xentral\Components\Database\Database;
use Xentral\Modules\ApiV3\Http\ApiV3Exception;
use Xentral\Modules\ApiV3\Repository\PartnerRepository;

final class PartnerService
{
    /** @var Database */
    private $database;

    /** @var PartnerRepository */
    private $partners;

    public function __construct(Database $database, PartnerRepository $partners)
    {
        $this->database = $database;
        $this->partners = $partners;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listCustomers(array $filters, array $pagination): array
    {
        return $this->partners->searchCustomers($filters, $pagination);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomer(int $id): array
    {
        $customer = $this->partners->findCustomerById($id);
        if ($customer === null) {
            throw new ApiV3Exception(404, 'customer_not_found', 'The customer was not found.');
        }

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function createCustomer(array $payload): array
    {
        $attributes = $this->extractCustomerAttributes($payload);

        $email = trim((string)($attributes['email'] ?? ''));
        if ($email !== '') {
            $existing = $this->partners->findCustomerByEmail($email);
            if ($existing !== null) {
                return $this->getCustomer((int)$existing['id']);
            }
        }

        $this->database->beginTransaction();
        try {
            if (empty($attributes['kundennummer_buchhaltung'])) {
                $nextCustomerNumber = $this->partners->lockNextCustomerAccountingNumber();
                if ($nextCustomerNumber <= 0) {
                    throw new ApiV3Exception(500, 'customer_number_unavailable', 'The next customer number could not be resolved.');
                }
                $attributes['kundennummer_buchhaltung'] = (string)$nextCustomerNumber;
                $this->partners->incrementNextCustomerAccountingNumber();
            }

            $customerId = $this->partners->insertAddress($attributes);
            $this->database->commit();

            return $this->getCustomer($customerId);
        } catch (\Throwable $throwable) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function updateCustomer(int $id, array $payload): array
    {
        $this->getCustomer($id);
        $attributes = $this->extractCustomerAttributes($payload, false);
        if (empty($attributes)) {
            throw new ApiV3Exception(422, 'empty_customer_patch', 'No updatable customer fields were provided.');
        }

        $this->partners->updateAddress($id, $attributes);

        return $this->getCustomer($id);
    }

    public function createCustomerProjectLink(int $customerId, array $payload): array
    {
        $customer = $this->getCustomer($customerId);
        $projectId = (int)($payload['project_id'] ?? 0);
        if ($projectId <= 0) {
            throw new ApiV3Exception(422, 'missing_project_id', 'A valid `project_id` is required.');
        }
        $fromDate = trim((string)($payload['from_date'] ?? date('Y-m-d')));
        $roleId = $this->partners->createAddressRole($customerId, $projectId, $fromDate);

        return [
            'role_id'     => $roleId,
            'customer_id' => (int)$customer['id'],
            'project_id'  => $projectId,
            'from_date'   => $fromDate,
        ];
    }

    /**
     * @param array{page:int,per_page:int,offset:int} $pagination
     *
     * @return array{items:array<int, array<string, mixed>>,total:int}
     */
    public function listSuppliers(string $supplierNumber, array $pagination): array
    {
        return $this->partners->searchSuppliers($supplierNumber, $pagination);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSupplier(int $id): array
    {
        $supplier = $this->partners->findSupplierById($id);
        if ($supplier === null) {
            throw new ApiV3Exception(404, 'supplier_not_found', 'The supplier was not found.');
        }

        return $supplier;
    }

    /**
     * @param array<string, mixed> $payload
     * @param bool                 $withDefaults
     *
     * @return array<string, mixed>
     */
    private function extractCustomerAttributes(array $payload, bool $withDefaults = true): array
    {
        if (isset($payload['attributes']) && is_array($payload['attributes'])) {
            return $payload['attributes'];
        }

        $attributes = [
            'kundennummer'               => (string)($payload['customer_number'] ?? ''),
            'name'                       => (string)($payload['name'] ?? ''),
            'vorname'                    => (string)($payload['first_name'] ?? ''),
            'nachname'                   => (string)($payload['last_name'] ?? ''),
            'typ'                        => (string)($payload['type'] ?? 'privat'),
            'firma'                      => !empty($payload['company']) ? 1 : 0,
            'email'                      => (string)($payload['email'] ?? ''),
            'telefon'                    => (string)($payload['phone'] ?? ''),
            'land'                       => (string)($payload['country_code'] ?? 'DE'),
            'plz'                        => (string)($payload['postal_code'] ?? ''),
            'ort'                        => (string)($payload['city'] ?? ''),
            'strasse'                    => (string)($payload['street'] ?? ''),
            'adresszusatz'               => (string)($payload['address_addition'] ?? ''),
            'projekt'                    => (int)($payload['project_id'] ?? 2),
            'kundennummer_buchhaltung'   => (string)($payload['accounting_customer_number'] ?? ''),
            'lieferantennummer'          => (string)($payload['supplier_number'] ?? ''),
            'rechnungs_email'            => (string)($payload['invoice_email'] ?? ($payload['email'] ?? '')),
            'zahlungskonditionen_festschreiben' => 0,
        ];

        if ($withDefaults) {
            $attributes += [
                'geloescht'              => 0,
                'waehrung'               => (string)($payload['currency'] ?? 'EUR'),
                'sprache'                => (string)($payload['language'] ?? 'deutsch'),
                'versandart'             => (string)($payload['shipping_method'] ?? 'dpd'),
                'zahlungsweise'          => (string)($payload['payment_method'] ?? 'rechnung'),
                'zahlungsweiselieferant' => 'rechnung',
                'usereditid'             => 1,
                'land'                   => (string)($payload['country_code'] ?? 'DE'),
                'typ'                    => (string)($payload['type'] ?? (!empty($payload['company']) ? 'firma' : 'privat')),
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
