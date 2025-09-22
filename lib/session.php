<?php
declare(strict_types=1);

function session_boot(): bool {
    if (session_status() === PHP_SESSION_ACTIVE) return true;

    // Set cookie secure flags
    $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '80') === '443';
    $params   = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!headers_sent()) {
        if (session_name() === 'PHPSESSID') {
            // beri nama agar tidak bentrok dengan aplikasi lain di server yg sama
            session_name('RSUDSESS');
        }
        return @session_start();
    }

    // Sudah ada output → tidak bisa start di sini
    return false;
}

/** Cek login tanpa memaksa membuka sesi. */
function session_user_or_null(): ?array {
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}

/** Set user ke session (dipakai saat callback login). */
function session_set_user(array $user): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION['user'] = $user;
}

/** Hapus sesi user. */
function session_logout(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'] ?? '/', $params['domain'] ?? '', !empty($_SERVER['HTTPS']), true);
    }
    @session_destroy();
}
