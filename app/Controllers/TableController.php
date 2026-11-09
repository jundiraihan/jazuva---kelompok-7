<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class TableController extends LegacyModuleController
{
    public function index(): never { $this->page('admin/tables.php', ['admin']); }
    public function qr(): never { $this->page('admin/qr-management.php', ['admin']); }
}
