<?php
require_once __DIR__ . '/../Core/Controller.php';

abstract class LegacyModuleController extends Controller
{
    protected function page(string $path, string|array|null $roles = null): never
    {
        if ($roles !== null) {
            require_once BASE_PATH . '/includes/auth.php';
            requireRole($roles);
        }
        $views = [
            'login.php' => 'auth/login',
            'logout.php' => 'auth/logout',
            'register.php' => 'auth/register',
            'admin/dashboard.php' => 'dashboard/index',
            'admin/tables.php' => 'tables/index',
            'admin/qr-management.php' => 'tables/qr',
            'admin/menus.php' => 'menus/index',
            'admin/orders.php' => 'orders/index',
            'menu.php' => 'menus/customer',
            'order-status.php' => 'orders/status',
            'kitchen/dashboard.php' => 'kitchen/index',
            'barista/dashboard.php' => 'barista/index',
            'cashier/dashboard.php' => 'cashier/index',
            'cashier/history.php' => 'cashier/history',
            'cashier/receipt.php' => 'cashier/receipt',
            'inventory/dashboard.php' => 'inventory/index',
            'inventory/ingredients.php' => 'inventory/ingredients',
            'inventory/recipes.php' => 'inventory/recipes',
            'inventory/stock-history.php' => 'inventory/movements',
            'admin/reports.php' => 'reports/index',
        ];
        if (!isset($views[$path])) {
            throw new RuntimeException('Halaman modul tidak terdaftar.');
        }
        $this->view($views[$path], ['legacyPath' => $path]);
        exit;
    }
}
