<?php

use Xentral\Modules\ApiV3\Engine\ApiV3Container;

if (!defined('API_REQUEST')) {
    define('API_REQUEST', true);
}

if (!class_exists('Config', true)) {
    include dirname(dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])))) . DIRECTORY_SEPARATOR . 'conf/main.conf.php';
}

if (isset($_SERVER['HTTP_MULTIDB']) && (string)$_SERVER['HTTP_MULTIDB'] !== '') {
    define('MULTIDB', (string)$_SERVER['HTTP_MULTIDB']);
}

return new ApiV3Container();
