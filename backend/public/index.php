<?php

declare(strict_types=1);

use Bogana\ApiClient;
use Bogana\Config;
use Bogana\Router;
use Bogana\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::fromEnv();
$view = new View(dirname(__DIR__) . '/templates');
$router = new Router(new ApiClient($config), $view);

$router->dispatch((string) ($_GET['page'] ?? 'dashboard'));