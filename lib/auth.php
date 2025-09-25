<?php
declare(strict_types=1);

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

/** Cek path "next" aman (hanya internal path). */
function auth_is_safe_next(?string $n): bool {
    if (!is_string($n) || $n === '') return false;
    if ($n[0] !== '/') return false;
    if (str_starts_with($n, '//')) return false;
    if (str_contains($n, "\r") || str_contains($n, "\n")) return false;
    if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $n)) return false; // skema
    return true;
}

/**
 * Tentukan tujuan default setelah login/daftar.
 * - Jika $next aman → pakai $next.
 * - Jika role admin/editor → /pages/dashboard
 * - Lainnya → /pages/portal
 */
function auth_default_destination(?array $user, ?string $next = null): string {
    if (auth_is_safe_next($next)) return (string)$next;

    // Normalisasi role
    $role = strtolower(trim((string)($user['role'] ?? '')));
    if ($role === 'admin' || $role === 'editor') {
        return '/pages/dashboard';
    }
    return '/pages/portal';
}
