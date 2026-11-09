<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class ReportController extends LegacyModuleController
{
    public function index(): never { $this->page('admin/reports.php', ['admin']); }
}
