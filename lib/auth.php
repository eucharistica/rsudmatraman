<?php
// /lib/auth.php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

/** Ambil user saat ini dari session (tidak memanggil session_start bila header sudah terkirim). */
function auth_current_user(): ?array {
    return session_user_or_null();
}

/** Apakah sudah login. */
function auth_is_logged_in(): bool {
    return auth_current_user() !== null;
}

/** Ambil role lowercased. */
function auth_role(): string {
    $u = auth_current_user();
    return strtolower($u['role'] ?? '');
}
