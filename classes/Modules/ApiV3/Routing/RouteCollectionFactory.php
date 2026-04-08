<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Routing;

use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Xentral\Modules\ApiV3\Auth\ScopeRegistry;
use Xentral\Modules\ApiV3\Controller\BankingController;
use Xentral\Modules\ApiV3\Controller\FilesController;
use Xentral\Modules\ApiV3\Controller\OrdersController;
use Xentral\Modules\ApiV3\Controller\PartnersController;
use Xentral\Modules\ApiV3\Controller\PayablesController;
use Xentral\Modules\ApiV3\Controller\ProductsController;
use Xentral\Modules\ApiV3\Controller\ReferenceDataController;

final class RouteCollectionFactory
{
    public function create(): RouteCollector
    {
        $routes = new RouteCollector(new RouteParser(), new DataGenerator());

        $routes->addRoute('GET', '/me', [ReferenceDataController::class, 'me', ScopeRegistry::ME_READ]);
        $routes->addRoute('GET', '/projects', [ReferenceDataController::class, 'projects', ScopeRegistry::REFERENCE_READ]);
        $routes->addRoute('GET', '/warehouse-locations', [ReferenceDataController::class, 'warehouseLocations', ScopeRegistry::REFERENCE_READ]);
        $routes->addRoute('GET', '/payment-methods', [ReferenceDataController::class, 'paymentMethods', ScopeRegistry::REFERENCE_READ]);
        $routes->addRoute('GET', '/shipping-methods', [ReferenceDataController::class, 'shippingMethods', ScopeRegistry::REFERENCE_READ]);
        $routes->addRoute('GET', '/tax-rates', [ReferenceDataController::class, 'taxRates', ScopeRegistry::REFERENCE_READ]);
        $routes->addRoute('GET', '/bank-accounts', [ReferenceDataController::class, 'bankAccounts', ScopeRegistry::REFERENCE_READ]);
        $routes->addRoute('GET', '/ledger-accounts', [ReferenceDataController::class, 'ledgerAccounts', ScopeRegistry::REFERENCE_READ]);

        $routes->addRoute('GET', '/customers', [PartnersController::class, 'listCustomers', ScopeRegistry::CUSTOMERS_READ]);
        $routes->addRoute('GET', '/customers/{id:\d+}', [PartnersController::class, 'getCustomer', ScopeRegistry::CUSTOMERS_READ]);
        $routes->addRoute('POST', '/customers', [PartnersController::class, 'createCustomer', ScopeRegistry::CUSTOMERS_WRITE]);
        $routes->addRoute('PATCH', '/customers/{id:\d+}', [PartnersController::class, 'updateCustomer', ScopeRegistry::CUSTOMERS_WRITE]);
        $routes->addRoute('POST', '/customers/{id:\d+}/project-links', [PartnersController::class, 'createCustomerProjectLink', ScopeRegistry::CUSTOMERS_WRITE]);
        $routes->addRoute('GET', '/suppliers', [PartnersController::class, 'listSuppliers', ScopeRegistry::SUPPLIERS_READ]);
        $routes->addRoute('GET', '/suppliers/{id:\d+}', [PartnersController::class, 'getSupplier', ScopeRegistry::SUPPLIERS_READ]);

        $routes->addRoute('GET', '/products', [ProductsController::class, 'listProducts', ScopeRegistry::PRODUCTS_READ]);
        $routes->addRoute('GET', '/products/{id:\d+}', [ProductsController::class, 'getProduct', ScopeRegistry::PRODUCTS_READ]);
        $routes->addRoute('POST', '/products', [ProductsController::class, 'createProduct', ScopeRegistry::PRODUCTS_WRITE]);
        $routes->addRoute('PATCH', '/products/{id:\d+}', [ProductsController::class, 'updateProduct', ScopeRegistry::PRODUCTS_WRITE]);
        $routes->addRoute('POST', '/products/{id:\d+}/supplier-prices', [ProductsController::class, 'addSupplierPrice', ScopeRegistry::PRODUCTS_WRITE]);
        $routes->addRoute('PUT', '/inventory-levels/{locationId:\d+}/{sku:.+}', [ProductsController::class, 'updateInventoryLevel', ScopeRegistry::INVENTORY_WRITE]);

        $routes->addRoute('GET', '/sales-orders', [OrdersController::class, 'listSalesOrders', ScopeRegistry::SALES_ORDERS_READ]);
        $routes->addRoute('GET', '/sales-orders/{id:\d+}', [OrdersController::class, 'getSalesOrder', ScopeRegistry::SALES_ORDERS_READ]);
        $routes->addRoute('POST', '/sales-orders/{id:\d+}/invoices', [OrdersController::class, 'createInvoiceFromSalesOrder', ScopeRegistry::SALES_ORDERS_WRITE]);
        $routes->addRoute('POST', '/sales-orders', [OrdersController::class, 'createSalesOrder', ScopeRegistry::SALES_ORDERS_WRITE]);

        $routes->addRoute('GET', '/bank-transactions', [BankingController::class, 'listBankTransactions', ScopeRegistry::BANKING_READ]);
        $routes->addRoute('POST', '/bank-transaction-imports', [BankingController::class, 'importBankTransactions', ScopeRegistry::BANKING_WRITE]);

        $routes->addRoute('GET', '/payables', [PayablesController::class, 'listPayables', ScopeRegistry::PAYABLES_READ]);
        $routes->addRoute('GET', '/payables/{id:\d+}', [PayablesController::class, 'getPayable', ScopeRegistry::PAYABLES_READ]);
        $routes->addRoute('POST', '/payables', [PayablesController::class, 'createPayable', ScopeRegistry::PAYABLES_WRITE]);
        $routes->addRoute('POST', '/payables/{id:\d+}/attachments', [PayablesController::class, 'attachFile', ScopeRegistry::PAYABLES_WRITE]);
        $routes->addRoute('POST', '/files', [FilesController::class, 'upload', ScopeRegistry::FILES_WRITE]);

        return $routes;
    }
}
