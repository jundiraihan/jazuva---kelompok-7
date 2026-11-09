<?php
require_once __DIR__ . '/LegacyModuleController.php';
final class AuthController extends LegacyModuleController
{
    public function login(): never { $this->page('login.php'); }
    public function logout(): never { $this->page('logout.php'); }
    public function register(): never { $this->page('register.php'); }
}
