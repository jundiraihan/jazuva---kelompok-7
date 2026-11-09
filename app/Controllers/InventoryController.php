<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class InventoryController extends LegacyModuleController
{
    public function index(): never { $this->page('inventory/dashboard.php', ['admin', 'inventory']); }
    public function ingredients(): never { $this->page('inventory/ingredients.php', ['admin', 'inventory']); }
    public function recipes(): never { $this->page('inventory/recipes.php', ['admin', 'inventory']); }
    public function movements(): never { $this->page('inventory/stock-history.php', ['admin', 'inventory']); }
}
