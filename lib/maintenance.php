<?php
declare(strict_types=1);

/**
 * Aktif jika ada file storage/maintenance.lock
 * atau konstanta APP_MAINTENANCE = true (mis. dari _private/website.php).
 */
function maintenance_enabled(): bool {
    $flagFile = __DIR__ . '/../storage/maintenance.lock';
    if (is_file($flagFile)) return true;
    return defined('APP_MAINTENANCE') && APP_MAINTENANCE;
}

/**
 * Blokir non-admin ketika maintenance ON.
 * Admin ditentukan dari $_SESSION['user']['role'] (admin/superuser).
 */
function maintenance_guard(?callable $isAdmin = null): void {
    if (!maintenance_enabled()) return;

    $admin = false;
    if ($isAdmin) {
        $admin = (bool)$isAdmin();
    } else {
        $u = $_SESSION['user'] ?? null;
        $role = strtolower($u['role'] ?? '');
        $admin = ($role === 'admin' || $role === 'superuser');
    }
    if ($admin) return;

    http_response_code(503);
    header('Retry-After: 3600');
    require __DIR__ . '/../pages/errors/maintenance.php';
    exit;
}
