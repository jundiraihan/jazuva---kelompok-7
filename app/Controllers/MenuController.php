<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class MenuController extends LegacyModuleController
{
    public function index(): never { $this->page('admin/menus.php', ['admin']); }
    public function customer(): never { $this->page('menu.php'); }
}
