<?php
/**
 * JAZUVA - Authentication & Access Control
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Security.php';
Security::startSession();

/**
 * Check if a user is logged in
 */
function isLoggedIn(): bool {
    return Security::authenticated();
}

/**
 * Get currently logged-in user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role_id'   => $_SESSION['role_id'] ?? 0,
        'role_name' => $_SESSION['role_name'] ?? '',
        'role_display' => $_SESSION['role_display'] ?? '',
        'avatar'    => $_SESSION['avatar'] ?? 'default-avatar.png',
    ];
}

/**
 * Check if the user has a specific role or one of multiple roles
 */
function hasRole($roles): bool {
    return Security::allows($roles);
}

/**
 * Enforce login
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses halaman ini.';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Enforce role permission
 */
function requireRole($roles): void {
    requireLogin();
    if (!hasRole($roles)) {
        $_SESSION['flash_error'] = 'Anda tidak memiliki hak akses untuk membuka halaman tersebut.';
        redirectBasedOnRole($_SESSION['role_name'] ?? '');
        exit;
    }
}

/**
 * Redirect user to their respective dashboard
 */
function redirectBasedOnRole(string $role): void {
    $role = strtolower($role);
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            break;
        case 'cashier':
            header('Location: ' . BASE_URL . '/cashier/dashboard.php');
            break;
        case 'kitchen':
            header('Location: ' . BASE_URL . '/kitchen/dashboard.php');
            break;
        case 'barista':
            header('Location: ' . BASE_URL . '/barista/dashboard.php');
            break;
        case 'inventory':
            header('Location: ' . BASE_URL . '/inventory/dashboard.php');
            break;
        default:
            header('Location: ' . BASE_URL . '/index.php');
            break;
    }
    exit;
}

/**
 * CSRF Protection
 */
function generateCsrfToken(): string {
    return Security::csrfToken();
}

function validateCsrfToken(?string $token): bool {
    return Security::verifyCsrf($token);
}

function requireApiRole($roles): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Silakan login untuk melanjutkan.']);
        exit;
    }

    if (!hasRole($roles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses.']);
        exit;
    }
}

function requireApiCsrfToken(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid. Muat ulang halaman dan coba kembali.']);
        exit;
    }
}

function requireCsrfPost(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Permintaan ditolak. Muat ulang halaman dan coba kembali.');
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}
