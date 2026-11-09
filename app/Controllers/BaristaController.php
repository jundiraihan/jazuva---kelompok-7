<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class BaristaController extends LegacyModuleController
{
    public function index(): never { $this->page('barista/dashboard.php', ['admin', 'barista']); }
}
