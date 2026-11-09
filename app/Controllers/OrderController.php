<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class OrderController extends LegacyModuleController
{
    public function index(): never { $this->page('admin/orders.php', ['admin', 'cashier']); }
    public function status(): never { $this->page('order-status.php'); }
}
