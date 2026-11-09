<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class KitchenController extends LegacyModuleController
{
    public function index(): never { $this->page('kitchen/dashboard.php', ['admin', 'kitchen']); }
}
