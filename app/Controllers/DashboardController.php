<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class DashboardController extends LegacyModuleController
{
    public function index(): never { $this->page('admin/dashboard.php', ['admin']); }
}
