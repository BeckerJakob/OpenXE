<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

require dirname(dirname(dirname(__DIR__))) . '/xentral_autoloader.php';
require dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

define('DEBUG_MODE', false);

/** @var \Xentral\Modules\ApiV3\Engine\ApiV3Container $container */
$container = include __DIR__ . '/bootstrap.php';

$errorHandler = new \Xentral\Modules\ApiV3\Engine\ErrorHandler(
    $container->get(\Xentral\Modules\ApiV3\Http\ApiV3ResponseFactory::class)
);
$errorHandler->register();

$application = new \Xentral\Modules\ApiV3\Engine\ApiV3Application($container);
$response = $application->handle();
$response->send();
