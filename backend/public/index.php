<?php

declare(strict_types=1);

use Bogana\ApiClient;
use Bogana\Auth;
use Bogana\Config;
use Bogana\Router;
use Bogana\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::fromEnv();
$auth = Auth::fromEnv();

$view = new View(dirname(__DIR__) . '/templates', [
    'readOnly' => $auth->isReadOnly(),
    'currentUser' => $auth->displayName(),
    'canLogout' => $auth->requiresLogin(),
]);

$router = new Router(new ApiClient($config), $view, $auth);

$router->dispatch((string) ($_GET['page'] ?? 'dashboard'));
