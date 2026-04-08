<?php

declare(strict_types=1);

namespace Xentral\Modules\ApiV3\Engine;

use RuntimeException;
use Xentral\Components\Database\Database;
use Xentral\Components\Http\Request;
use Xentral\Modules\Api\LegacyBridge\LegacyApplication;
use Xentral\Modules\ApiV3\Auth\OpaqueTokenAuthenticator;
use Xentral\Modules\ApiV3\Auth\TokenService;
use Xentral\Modules\ApiV3\Controller\BankingController;
use Xentral\Modules\ApiV3\Controller\FilesController;
use Xentral\Modules\ApiV3\Controller\OrdersController;
use Xentral\Modules\ApiV3\Controller\PartnersController;
use Xentral\Modules\ApiV3\Controller\PayablesController;
use Xentral\Modules\ApiV3\Controller\ProductsController;
use Xentral\Modules\ApiV3\Controller\ReferenceDataController;
use Xentral\Modules\ApiV3\Domain\BankingService;
use Xentral\Modules\ApiV3\Domain\FilesService;
use Xentral\Modules\ApiV3\Domain\OrdersService;
use Xentral\Modules\ApiV3\Domain\PartnerService;
use Xentral\Modules\ApiV3\Domain\PayablesService;
use Xentral\Modules\ApiV3\Domain\ProductService;
use Xentral\Modules\ApiV3\Http\ApiV3Request;
use Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory;
use Xentral\Modules\ApiV3\Repository\ApiAccountRepository;
use Xentral\Modules\ApiV3\Repository\ApiV3TokenRepository;
use Xentral\Modules\ApiV3\Repository\BankingRepository;
use Xentral\Modules\ApiV3\Repository\FileRepository;
use Xentral\Modules\ApiV3\Repository\IdempotencyRepository;
use Xentral\Modules\ApiV3\Repository\PartnerRepository;
use Xentral\Modules\ApiV3\Repository\PayablesRepository;
use Xentral\Modules\ApiV3\Repository\ProductRepository;
use Xentral\Modules\ApiV3\Repository\ReferenceDataRepository;
use Xentral\Modules\ApiV3\Repository\SalesOrderRepository;
use Xentral\Modules\ApiV3\Routing\RouteCollectionFactory;
use Xentral\Modules\ApiV3\Routing\Router;

final class ApiV3Container
{
    /** @var array<string, object> */
    private $services = [];

    public function setRequest(Request $request): void
    {
        $this->services[Request::class] = $request;
        unset($this->services[ApiV3Request::class]);
    }

    /**
     * @return object
     */
    public function get(string $id)
    {
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        switch ($id) {
            case Request::class:
                $service = Request::createFromGlobals(file_get_contents('php://input'));
                break;
            case ApiV3Request::class:
                $service = new ApiV3Request($this->get(Request::class));
                break;
            case LegacyApplication::class:
                $service = new LegacyApplication();
                break;
            case Database::class:
                $service = $this->get(LegacyApplication::class)->Container->get('Database');
                break;
            case ApiV3ResponseFactory::class:
                $service = new ApiV3ResponseFactory();
                break;
            case SchemaManager::class:
                $service = new SchemaManager($this->get(Database::class));
                break;
            case RouteCollectionFactory::class:
                $service = new RouteCollectionFactory();
                break;
            case Router::class:
                $service = new Router($this->get(RouteCollectionFactory::class)->create());
                break;
            case ApiAccountRepository::class:
                $service = new ApiAccountRepository($this->get(Database::class));
                break;
            case ApiV3TokenRepository::class:
                $service = new ApiV3TokenRepository($this->get(Database::class));
                break;
            case IdempotencyRepository::class:
                $service = new IdempotencyRepository($this->get(Database::class));
                break;
            case ReferenceDataRepository::class:
                $service = new ReferenceDataRepository($this->get(Database::class));
                break;
            case PartnerRepository::class:
                $service = new PartnerRepository($this->get(Database::class));
                break;
            case ProductRepository::class:
                $service = new ProductRepository($this->get(Database::class));
                break;
            case SalesOrderRepository::class:
                $service = new SalesOrderRepository($this->get(Database::class));
                break;
            case BankingRepository::class:
                $service = new BankingRepository($this->get(Database::class));
                break;
            case PayablesRepository::class:
                $service = new PayablesRepository($this->get(Database::class));
                break;
            case FileRepository::class:
                $service = new FileRepository($this->get(Database::class));
                break;
            case OpaqueTokenAuthenticator::class:
                $service = new OpaqueTokenAuthenticator($this->get(ApiV3TokenRepository::class));
                break;
            case TokenService::class:
                $service = new TokenService(
                    $this->get(ApiAccountRepository::class),
                    $this->get(ApiV3TokenRepository::class)
                );
                break;
            case PartnerService::class:
                $service = new PartnerService($this->get(Database::class), $this->get(PartnerRepository::class));
                break;
            case ProductService::class:
                $service = new ProductService($this->get(ProductRepository::class));
                break;
            case OrdersService::class:
                $service = new OrdersService(
                    $this->get(SalesOrderRepository::class),
                    $this->get(LegacyApplication::class)
                );
                break;
            case BankingService::class:
                $service = new BankingService($this->get(BankingRepository::class));
                break;
            case PayablesService::class:
                $service = new PayablesService(
                    $this->get(PayablesRepository::class),
                    $this->get(PartnerRepository::class),
                    $this->get(ReferenceDataRepository::class)
                );
                break;
            case FilesService::class:
                $service = new FilesService($this->get(FileRepository::class), $this->get(PayablesRepository::class));
                break;
            default:
                throw new RuntimeException(sprintf('Unknown ApiV3 service "%s".', $id));
        }

        $this->services[$id] = $service;

        return $service;
    }

    /**
     * @return object
     */
    public function getController(string $className)
    {
        switch ($className) {
            case ReferenceDataController::class:
                return new ReferenceDataController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(ReferenceDataRepository::class)
                );
            case PartnersController::class:
                return new PartnersController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(PartnerService::class)
                );
            case ProductsController::class:
                return new ProductsController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(ProductService::class)
                );
            case OrdersController::class:
                return new OrdersController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(OrdersService::class)
                );
            case BankingController::class:
                return new BankingController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(BankingService::class)
                );
            case PayablesController::class:
                return new PayablesController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(PayablesService::class),
                    $this->get(FilesService::class)
                );
            case FilesController::class:
                return new FilesController(
                    $this->get(ApiV3ResponseFactory::class),
                    $this->get(FilesService::class)
                );
            default:
                throw new RuntimeException(sprintf('Unknown ApiV3 controller "%s".', $className));
        }
    }
}
