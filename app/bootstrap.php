<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Core/Security.php';
require_once __DIR__ . '/Core/Controller.php';
require_once __DIR__ . '/Core/App.php';

spl_autoload_register(static function (string $class): void {
    foreach ([__DIR__ . '/Models/' . $class . '.php', __DIR__ . '/Controllers/' . $class . '.php'] as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
Security::startSession();

function createApplication(): App
{
    $app = new App();
    $routes = [
        ['GET', '/login', 'AuthController@login'],
        ['GET', '/logout', 'AuthController@logout'],
        ['GET', '/register', 'AuthController@register'],
        ['GET', '/admin', 'DashboardController@index'],
        ['GET', '/admin/tables', 'TableController@index'],
        ['GET', '/admin/qr', 'TableController@qr'],
        ['GET', '/admin/menus', 'MenuController@index'],
        ['GET', '/admin/orders', 'OrderController@index'],
        ['GET', '/admin/reports', 'ReportController@index'],
        ['GET', '/menu', 'MenuController@customer'],
        ['GET', '/order-status', 'OrderController@status'],
        ['GET', '/kitchen', 'KitchenController@index'],
        ['GET', '/barista', 'BaristaController@index'],
        ['GET', '/cashier', 'CashierController@index'],
        ['GET', '/cashier/history', 'CashierController@history'],
        ['GET', '/cashier/receipt', 'CashierController@receipt'],
        ['GET', '/inventory', 'InventoryController@index'],
        ['GET', '/inventory/ingredients', 'InventoryController@ingredients'],
        ['GET', '/inventory/recipes', 'InventoryController@recipes'],
        ['GET', '/inventory/movements', 'InventoryController@movements'],
    ];
    foreach ($routes as [$verb, $path, $action]) {
        $app->{strtolower($verb)}($path, $action);
    }
    $postRoutes = [
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
        '/admin/tables' => 'TableController@index',
        '/admin/menus' => 'MenuController@index',
        '/admin/orders' => 'OrderController@index',
        '/menu' => 'MenuController@customer',
        '/cashier' => 'CashierController@index',
        '/cashier/history' => 'CashierController@history',
        '/inventory/ingredients' => 'InventoryController@ingredients',
        '/inventory/recipes' => 'InventoryController@recipes',
    ];
    foreach ($postRoutes as $path => $action) {
        $app->post($path, $action);
    }
    return $app;
}
