<?php
declare(strict_types=1);

/**
 * Set HTTP Security Headers + generate CSP nonce per-request.
 * Pakai csp_script_nonce() di inline <script>.
 */
function security_headers_init(array $opts = []): string {
    if (headers_sent()) return '';

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? null) == 443);

    // per-request nonce untuk inline script yang diizinkan
    $nonce = base64_encode(random_bytes(16));

    // Compose CSP (boleh di-override via $opts['csp'] kalau perlu)
    $defaultCsp = [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'self'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",  // aman jika CSS sudah dibundle; nanti bisa dihilangkan
        "script-src 'self' 'nonce-{$nonce}'",
        "connect-src 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "upgrade-insecure-requests",
    ];
    $csp = $opts['csp'] ?? implode('; ', $defaultCsp);

    header("Content-Security-Policy: {$csp}");
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN'); // redundant dgn frame-ancestors, tetap dipasang
    header('X-XSS-Protection: 0');         // matikan legacy filter
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    $GLOBALS['__CSP_NONCE'] = $nonce;
    return $nonce;
}

function csp_script_nonce(): string {
    return htmlspecialchars($GLOBALS['__CSP_NONCE'] ?? '', ENT_QUOTES, 'UTF-8');
}
