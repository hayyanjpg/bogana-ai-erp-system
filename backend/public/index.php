<?php

declare(strict_types=1);

use Bogana\ApiClient;
use Bogana\Auth;
use Bogana\Config;
use Bogana\Router;
use Bogana\View;

require dirname(__DIR__) . '/vendor/autoload.php';

// Tanggal dashboard dan form order memakai WIB, sama seperti seed database.
date_default_timezone_set('Asia/Jakarta');

$config = Config::fromEnv();
$auth = Auth::fromEnv();

$view = new View(dirname(__DIR__) . '/templates', [
    'accessMode' => $auth->mode(),
    'currentUser' => $auth->displayName(),
    'canLogout' => $auth->requiresLogin(),
]);

$router = new Router(new ApiClient($config), $view, $auth);

$router->dispatch((string) ($_GET['page'] ?? 'dashboard'));
