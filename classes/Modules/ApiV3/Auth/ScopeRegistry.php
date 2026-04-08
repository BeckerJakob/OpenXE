<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Auth;

final class ScopeRegistry
{
    public const ME_READ = 'me.read';
    public const REFERENCE_READ = 'reference.read';
    public const CUSTOMERS_READ = 'customers.read';
    public const CUSTOMERS_WRITE = 'customers.write';
    public const SUPPLIERS_READ = 'suppliers.read';
    public const PRODUCTS_READ = 'products.read';
    public const PRODUCTS_WRITE = 'products.write';
    public const INVENTORY_WRITE = 'inventory.write';
    public const SALES_ORDERS_READ = 'sales-orders.read';
    public const SALES_ORDERS_WRITE = 'sales-orders.write';
    public const BANKING_READ = 'banking.read';
    public const BANKING_WRITE = 'banking.write';
    public const PAYABLES_READ = 'payables.read';
    public const PAYABLES_WRITE = 'payables.write';
    public const FILES_WRITE = 'files.write';

    /**
     * @return array<string, array{group:string,label:string,description:string}>
     */
    public static function definitions(): array
    {
        return [
            self::ME_READ            => ['group' => 'General', 'label' => 'Own token/account data', 'description' => 'Read `/me` for the authenticated API account.'],
            self::REFERENCE_READ     => ['group' => 'Reference Data', 'label' => 'Reference data', 'description' => 'Read projects, warehouse locations, payment methods, shipping methods, tax rates, bank accounts and ledger accounts.'],
            self::CUSTOMERS_READ     => ['group' => 'Customers', 'label' => 'Read customers', 'description' => 'Search and read customer records.'],
            self::CUSTOMERS_WRITE    => ['group' => 'Customers', 'label' => 'Write customers', 'description' => 'Create and update customer records and project links.'],
            self::SUPPLIERS_READ     => ['group' => 'Suppliers', 'label' => 'Read suppliers', 'description' => 'Search and read supplier records.'],
            self::PRODUCTS_READ      => ['group' => 'Products', 'label' => 'Read products', 'description' => 'Search and read products.'],
            self::PRODUCTS_WRITE     => ['group' => 'Products', 'label' => 'Write products', 'description' => 'Create and update products and supplier prices.'],
            self::INVENTORY_WRITE    => ['group' => 'Products', 'label' => 'Write inventory', 'description' => 'Update inventory levels by warehouse location and SKU.'],
            self::SALES_ORDERS_READ  => ['group' => 'Sales Orders', 'label' => 'Read sales orders', 'description' => 'Search and read sales orders.'],
            self::SALES_ORDERS_WRITE => ['group' => 'Sales Orders', 'label' => 'Write sales orders', 'description' => 'Create sales orders with line items and create invoices from sales orders (same as "Rechnung erstellen" in the UI).'],
            self::BANKING_READ       => ['group' => 'Banking', 'label' => 'Read bank transactions', 'description' => 'Read bank transactions and delta windows.'],
            self::BANKING_WRITE      => ['group' => 'Banking', 'label' => 'Import bank transactions', 'description' => 'Import bank transactions with idempotency support.'],
            self::PAYABLES_READ      => ['group' => 'Payables', 'label' => 'Read payables', 'description' => 'Read suppliers, payables and payable lookup results.'],
            self::PAYABLES_WRITE     => ['group' => 'Payables', 'label' => 'Write payables', 'description' => 'Create payables and link attachments.'],
            self::FILES_WRITE        => ['group' => 'Files', 'label' => 'Upload files', 'description' => 'Upload DMS files for later attachment linking.'],
        ];
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    public static function exists(string $scope): bool
    {
        return array_key_exists($scope, self::definitions());
    }

    /**
     * @param string[] $scopes
     *
     * @return string[]
     */
    public static function normalize(array $scopes): array
    {
        $normalized = [];
        foreach ($scopes as $scope) {
            $scope = trim((string)$scope);
            if ($scope !== '' && self::exists($scope)) {
                $normalized[] = $scope;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
