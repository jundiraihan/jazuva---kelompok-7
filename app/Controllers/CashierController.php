<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class CashierController extends LegacyModuleController
{
    public function index(): never { $this->page('cashier/dashboard.php', ['admin', 'cashier']); }
    public function history(): never { $this->page('cashier/history.php', ['admin', 'cashier']); }
    public function receipt(): never { $this->page('cashier/receipt.php', ['admin', 'cashier']); }
}
